/**
 * Cleans guides that were published before the humanising pass existed.
 *
 *   node clean-guides.mjs                 # report only: what would change, nothing written
 *   node clean-guides.mjs --write         # rewrite and save
 *   node clean-guides.mjs --write --slug=window-cleaners-insurance-working-at-height
 *   node clean-guides.mjs --write --dashes-only   # no model call: strip em dashes mechanically
 *
 * Each guide is rewritten block by block with the same checks as a new article (links kept,
 * length kept, no new tags), so a guide can only come out of this as itself, minus the tells.
 */
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { findAiTells, humaniseHtml, stripEmDashes } from './lib/humanise.mjs';
import { wordCount } from './lib/html.mjs';

const ENGINE_DIR = path.dirname(fileURLToPath(import.meta.url));
const POSTS_FILE = path.join(ENGINE_DIR, '..', 'src', 'data', 'blogPosts.json');

const args = process.argv.slice(2);
const WRITE = args.includes('--write');
const DASHES_ONLY = args.includes('--dashes-only');
const ONLY_SLUG = (args.find((a) => a.startsWith('--slug=')) || '').split('=')[1] || null;

const linksIn = (html) => [...String(html).matchAll(/href="([^"]*)"/g)].map((m) => m[1]).join('|');

async function main() {
  const raw = JSON.parse(fs.readFileSync(POSTS_FILE, 'utf8'));
  const posts = Array.isArray(raw) ? raw : raw.posts;
  const targets = posts.filter((p) => (!ONLY_SLUG || p.slug === ONLY_SLUG) && findAiTells(p.content || '').length);

  if (!targets.length) {
    console.log('Nothing to clean: no guide contains an em dash or any of the other tells.');
    return;
  }
  console.log(`${targets.length} guide(s) to clean${WRITE ? '' : ' (report only — add --write to save)'}\n`);

  let changed = 0;
  for (const post of targets) {
    const before = findAiTells(post.content);
    console.log(`${post.slug}`);
    console.log(`  found: ${before.map((t) => `${t.id} x${t.count}`).join(', ')}`);

    let result;
    if (DASHES_ONLY || (!WRITE && !process.env.ANTHROPIC_API_KEY)) {
      const html = stripEmDashes(post.content);
      result = { html, before, after: findAiTells(html), rewritten: 0, kept: 0 };
      if (!DASHES_ONLY) console.log('  (no ANTHROPIC_API_KEY here, so this preview only removes the dashes)');
    } else {
      result = await humaniseHtml(post.content, { label: `humanise ${post.slug}` });
      console.log(`  rewrote ${result.rewritten} block(s), kept ${result.kept} as they were`);
    }

    const wBefore = wordCount(post.content.replace(/—/g, ' '));
    const wAfter = wordCount(result.html);
    const linksKept = linksIn(post.content) === linksIn(result.html);
    console.log(`  words ${wBefore} -> ${wAfter}, links ${linksKept ? 'all kept' : 'CHANGED — not saving'}`);
    if (result.after.length) console.log(`  still present: ${result.after.map((t) => `${t.id} x${t.count}`).join(', ')}`);

    if (!linksKept) continue;
    if (wAfter < wBefore * 0.9) { console.log('  the guide came back noticeably shorter — not saving'); continue; }

    if (WRITE) {
      post.content = result.html;
      post.wordCount = wAfter;
      post.readingMinutes = Math.max(4, Math.round(wAfter / 230));
      if (Array.isArray(post.faqs)) {
        post.faqs = post.faqs.map((f) => ({
          ...f,
          question: stripEmDashes(f.question || ''),
          answer: stripEmDashes(f.answer || ''),
        }));
      }
      post.excerpt = stripEmDashes(post.excerpt || '');
      post.metaDescription = stripEmDashes(post.metaDescription || '');
      changed++;
    }
    console.log('');
  }

  if (WRITE && changed) {
    fs.writeFileSync(POSTS_FILE, JSON.stringify(raw, null, 2) + '\n', 'utf8');
    console.log(`Saved ${changed} guide(s) to ${path.relative(process.cwd(), POSTS_FILE)}.`);
    console.log('Commit and push to publish them.');
  } else if (WRITE) {
    console.log('Nothing saved.');
  }
}

main().catch((err) => { console.error(err); process.exit(1); });
