import { findAiTells, AI_TELLS } from './humanise.mjs';
// Cleans generated article HTML and checks the internal linking rules.
// Guides are rendered with dangerouslySetInnerHTML, so ONLY an allowlist of tags/attributes survives.

const ALLOWED_TAGS = new Set(['h2', 'h3', 'p', 'ul', 'ol', 'li', 'strong', 'em', 'a', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'blockquote', 'br']);
// External sources a guide may cite (authoritative UK bodies only).
const EXTERNAL_OK = /^https:\/\/(www\.)?(gov\.uk|hse\.gov\.uk|legislation\.gov\.uk|ico\.org\.uk|fca\.org\.uk|financial-ombudsman\.org\.uk|fscs\.org\.uk|acas\.org\.uk|companieshouse\.gov\.uk)(\/|$)/i;

const escapeAttr = (s) => String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');

export function sanitiseHtml(html, allowedUrls) {
  const removedLinks = [];
  let out = String(html || '')
    .replace(/<(script|style|iframe|object|embed|form)[\s\S]*?<\/\1>/gi, '')
    .replace(/<!--[\s\S]*?-->/g, '')
    .replace(/<h1(\s[^>]*)?>/gi, '<h2>').replace(/<\/h1>/gi, '</h2>');

  out = out.replace(/<\/?([a-z0-9]+)([^>]*)>/gi, (tag, nameRaw, attrs) => {
    const name = nameRaw.toLowerCase();
    const closing = tag.startsWith('</');
    if (!ALLOWED_TAGS.has(name)) return '';
    if (closing) return `</${name}>`;
    if (name !== 'a') return name === 'br' ? '<br>' : `<${name}>`;

    const m = attrs.match(/href\s*=\s*("([^"]*)"|'([^']*)')/i);
    let href = m ? (m[2] ?? m[3] ?? '').trim() : '';
    href = href.replace(/^https?:\/\/(www\.)?polished-insurance\.co\.uk/i, '') || '/';
    if (href.startsWith('/')) {
      const clean = href.replace(/[?#].*$/, '').replace(/\/$/, '') || '/';
      if (allowedUrls.has(clean)) return `<a href="${escapeAttr(clean)}">`;
      removedLinks.push(href);
      return '<a data-removed>';
    }
    if (EXTERNAL_OK.test(href)) return `<a href="${escapeAttr(href)}" target="_blank" rel="noopener noreferrer">`;
    removedLinks.push(href);
    return '<a data-removed>';
  });

  // Unwrap links we refused (keep their text).
  out = out.replace(/<a data-removed>([\s\S]*?)<\/a>/gi, '$1');
  out = out.replace(/<p>\s*<\/p>/g, '').replace(/\n{3,}/g, '\n\n').trim();
  return { html: out, removedLinks };
}

export const wordCount = (html) => String(html).replace(/<[^>]+>/g, ' ').split(/\s+/).filter(Boolean).length;

export function internalLinks(html) {
  return [...String(html).matchAll(/<a href="(\/[^"]*)"/g)].map((m) => m[1]);
}

/** Problems the revise pass must fix. */
export function auditArticle(article, { coverSlug, minWords }) {
  const issues = [];
  const html = article.contentHtml || '';
  const words = wordCount(html);
  const links = internalLinks(html);
  const unique = new Set(links);
  const quoteLinks = links.filter((l) => l.startsWith('/get-a-quote'));
  const h2s = (html.match(/<h2>/g) || []).length;

  if (words < minWords) issues.push(`The article body is ${words} words; it must be at least ${minWords} words of genuinely useful detail.`);
  if (h2s < 5) issues.push(`Use at least 5 <h2> sections (found ${h2s}).`);
  if (unique.size < 6) issues.push(`Include at least 6 different internal links from the allowed list (found ${unique.size}).`);
  if (quoteLinks.length < 2) issues.push(`Include at least 2 contextual links to enquiry pages (/get-a-quote/...), found ${quoteLinks.length}. At least one must be /get-a-quote/${coverSlug}.`);
  if (coverSlug && !links.includes(`/get-a-quote/${coverSlug}`)) issues.push(`Link to the matching enquiry page /get-a-quote/${coverSlug}.`);
  if (coverSlug && !links.includes(`/cleaning-insurance/${coverSlug}`)) issues.push(`Link to the matching information page /cleaning-insurance/${coverSlug}.`);
  if (!/<h2>\s*Frequently Asked Questions\s*<\/h2>/i.test(html)) issues.push('End with an <h2>Frequently Asked Questions</h2> section containing each FAQ as <h3> question + <p> answer.');
  if (!article.faqs || article.faqs.length < 4) issues.push('Provide at least 4 FAQs in the faqs array, matching the FAQ section wording.');
  if (/\b(cheapest|guaranteed? (the )?(lowest|best)|best price guaranteed)\b/i.test(html)) issues.push('Remove price or outcome guarantees such as "cheapest" or "guaranteed lowest price".');
  if (/\b(we|polished insurance) (are|is) (the )?(leading|number one|no\.? ?1|uk'?s? best)\b/i.test(html)) issues.push('Remove unverifiable superlative claims about Polished Insurance.');
  // House style: the things that make an article read as machine-written.
  for (const tell of findAiTells(html)) {
    issues.push(`Remove the ${tell.id} (${tell.count} found: ${tell.samples.join(', ')}). ${AI_TELLS.find((t) => t.id === tell.id).fix}`);
  }
  return issues;
}
