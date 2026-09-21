/**
 * Making a guide read as though a broker wrote it.
 *
 * Long AI-written articles carry tells: em dashes everywhere, a handful of giveaway words
 * ("delve", "robust", "navigating the"), hedging filler ("it is worth noting"), and the
 * "not just X, but Y" construction. The brief tells the model to avoid them and the audit makes
 * the revise pass fix them, but a 3,500-word article usually still has a few left.
 *
 * This is the last sweep. It rewrites ONLY the paragraphs that still contain a tell — not the
 * whole article, which is how a rewrite pass ends up quietly shortening or mangling a guide.
 * Every rewritten block is checked (same links, similar length, no new tags) and the original is
 * kept if anything looks wrong. Em dashes are then removed mechanically if any survived, so none
 * can ever reach the site.
 */
import { generateJSON } from './claude.mjs';

/** The patterns worth catching. Deliberately short: each one is a real tell, not a style opinion. */
export const AI_TELLS = [
  {
    id: 'em dash',
    test: /—/g,
    fix: 'Rewrite the sentence without the dash: a comma, a full stop or brackets, whichever reads naturally.',
  },
  {
    id: 'AI vocabulary',
    test: /\b(delve[sd]?|delving|tapestry|landscape of|pivotal|underscore[sd]?|underscoring|robust|leverage[sd]?|leveraging|utilis(?:e|es|ed|ing)|multifaceted|nuanced|myriad|realm of|testament to|ever-evolving|seamless(?:ly)?|navigating the|in today's)\b/gi,
    fix: 'Use the plain English word a broker would say.',
  },
  {
    id: 'hedging and filler',
    test: /\b(it(?:'s| is) (?:important|worth) (?:to note|noting)|it should be noted|in order to|due to the fact that|that being said|when it comes to|at the end of the day|needless to say)\b/gi,
    fix: 'Cut it or say the thing directly.',
  },
  {
    id: '"not just X, but Y"',
    test: /\bnot (?:just|only|merely)\b[^.<>]{3,70}?\bbut\b/gi,
    fix: 'Say what it is, in one plain statement.',
  },
];

/** Which tells a piece of HTML contains: [{ id, count, samples }]. */
export function findAiTells(html) {
  const text = String(html).replace(/<[^>]+>/g, ' ');
  const found = [];
  for (const tell of AI_TELLS) {
    const matches = [...text.matchAll(tell.test)];
    if (matches.length) {
      found.push({
        id: tell.id,
        count: matches.length,
        samples: [...new Set(matches.map((m) => m[0].trim()))].slice(0, 4),
      });
    }
  }
  return found;
}

const BLOCK_RE = /<(p|li|h2|h3|th|td|blockquote)(\s[^>]*)?>([\s\S]*?)<\/\1>/gi;
const ALLOWED_INNER = /^(?:strong|em|a|br)$/i;

const hrefsIn = (html) => [...String(html).matchAll(/href="([^"]*)"/g)].map((m) => m[1]);
const words = (html) => String(html).replace(/<[^>]+>/g, ' ').split(/\s+/).filter(Boolean).length;

/** Is this rewritten block safe to use in place of the original? */
function blockIsSound(original, rewritten) {
  if (!rewritten || typeof rewritten !== 'string') return false;
  if (/```|^\s*</.test(rewritten) === false && rewritten.trim() === '') return false;
  if (/```/.test(rewritten)) return false;
  // Same links, in the same order: a rewrite must never drop or invent one.
  const before = hrefsIn(original);
  const after = hrefsIn(rewritten);
  if (before.length !== after.length || before.some((h, i) => h !== after[i])) return false;
  // Only the inline tags we allow inside a block.
  const tags = [...rewritten.matchAll(/<\/?([a-z0-9]+)/gi)].map((m) => m[1]);
  if (tags.some((t) => !ALLOWED_INNER.test(t))) return false;
  // Roughly the same amount of writing: a rewrite is not an edit down.
  const wBefore = words(original);
  const wAfter = words(rewritten);
  if (wAfter < wBefore * 0.7 || wAfter > wBefore * 1.4) return false;
  return true;
}

/**
 * Em dashes removed by hand, as a last resort when a rewrite left one behind.
 * A spaced dash becomes a comma (or a full stop where the next word starts a new sentence);
 * an unspaced one becomes a comma too, which is how the sentence usually reads anyway.
 */
export function stripEmDashes(html) {
  return String(html)
    .replace(/\s*—\s*(?=[A-Z][a-z]+\s+[a-z])/g, '. ')     // "... — However it ..." -> new sentence
    .replace(/,?\s*—\s*/g, ', ')
    .replace(/,\s*,/g, ',')
    .replace(/\s+([,.])/g, '$1')
    .replace(/,\s*\./g, '.');
}

/**
 * Rewrites the blocks that still read as machine-written.
 * Returns { html, before, after, rewritten, kept } — `kept` counts blocks whose rewrite was rejected.
 */
export async function humaniseHtml(html, { label = 'humanise' } = {}) {
  const before = findAiTells(html);
  if (!before.length) return { html, before, after: [], rewritten: 0, kept: 0 };

  // Collect the blocks that contain a tell, keeping where each one sits.
  const blocks = [];
  for (const m of String(html).matchAll(BLOCK_RE)) {
    if (findAiTells(m[3]).length) blocks.push({ whole: m[0], tag: m[1], attrs: m[2] || '', inner: m[3] });
  }
  if (!blocks.length) return { html: stripEmDashes(html), before, after: findAiTells(stripEmDashes(html)), rewritten: 0, kept: 0 };

  // Offline pipeline testing (CONTENT_ENGINE_MOCK) returns one canned response for every call,
  // which is not a rewrite — so do the mechanical clean instead and leave the model out of it.
  if (process.env.CONTENT_ENGINE_MOCK) {
    const cleaned = stripEmDashes(html);
    console.log('[humanise] mock mode: removed em dashes only.');
    return { html: cleaned, before, after: findAiTells(cleaned), rewritten: 0, kept: blocks.length };
  }

  const rules = AI_TELLS.map((t) => `- ${t.id}: ${t.fix}`).join('\n');
  const user = `Below are ${blocks.length} numbered pieces of an insurance guide for UK cleaning businesses. Each one reads as though a machine wrote it.

Rewrite each piece so it reads as an experienced UK insurance broker would write it, fixing:
${rules}

Rules:
- Keep every fact, figure, link and inline tag exactly as it is. Do not drop or add an <a> tag, and never change an href.
- Keep the same length, give or take a few words. This is a rewrite, not an edit down.
- UK spelling. Plain, direct sentences of varied length. No dashes as punctuation.
- Return the inner HTML of each piece only, with no surrounding tag and no markdown fences.
- Return exactly ${blocks.length} pieces, in the same order.

${blocks.map((b, i) => `[${i + 1}]\n${b.inner}`).join('\n\n')}`;

  const schema = {
    type: 'object',
    additionalProperties: false,
    required: ['pieces'],
    properties: {
      pieces: {
        type: 'array',
        minItems: blocks.length,
        maxItems: blocks.length,
        items: { type: 'string' },
      },
    },
  };

  let pieces = [];
  try {
    const out = await generateJSON({
      system: 'You are a UK insurance copy editor. You rewrite machine-written prose so it reads as a person wrote it, without changing any facts, links or HTML.',
      user,
      schema,
      effort: 'medium',
      maxTokens: 16000,
      label,
    });
    pieces = Array.isArray(out.pieces) ? out.pieces : [];
  } catch (err) {
    console.warn(`[humanise] rewrite failed (${err.message}) — falling back to removing em dashes only.`);
    const cleaned = stripEmDashes(html);
    return { html: cleaned, before, after: findAiTells(cleaned), rewritten: 0, kept: blocks.length };
  }

  let out = html;
  let rewritten = 0;
  let kept = 0;
  blocks.forEach((b, i) => {
    const candidate = (pieces[i] || '').trim().replace(/^```(?:html)?\s*/i, '').replace(/```\s*$/i, '').trim();
    if (!blockIsSound(b.inner, candidate)) { kept++; return; }
    out = out.replace(b.whole, `<${b.tag}${b.attrs}>${candidate}</${b.tag}>`);
    rewritten++;
  });

  // Anything the rewrite missed: no em dash ships, ever.
  out = stripEmDashes(out);
  return { html: out, before, after: findAiTells(out), rewritten, kept };
}
