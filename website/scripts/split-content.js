import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

// Splits src/data/blogPosts.json (the content engine's source of truth) into:
//  1. src/data/blogIndex.json — a light index bundled into the app (no article bodies), and
//  2. public/content/guides/<slug>.json — one file per published guide, fetched on demand.
// Same approach as the Boxx site: keeps article HTML out of the JavaScript bundle.
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const source = path.join(root, 'src', 'data', 'blogPosts.json');
const index = path.join(root, 'src', 'data', 'blogIndex.json');
const contentDir = path.join(root, 'public', 'content', 'guides');
const HEAVY = ['content', 'faqs'];

const posts = JSON.parse(fs.readFileSync(source, 'utf8'));
fs.rmSync(contentDir, { recursive: true, force: true });
fs.mkdirSync(contentDir, { recursive: true });

let written = 0;
const light = posts.map((p) => {
  const copy = { ...p };
  for (const f of HEAVY) delete copy[f];
  return copy;
});
for (const p of posts) {
  if (!p || p.status !== 'published' || !/^[a-z0-9-]+$/.test(p.slug || '')) continue;
  fs.writeFileSync(path.join(contentDir, `${p.slug}.json`), JSON.stringify({ slug: p.slug, content: p.content, faqs: p.faqs || [] }));
  written++;
}
fs.writeFileSync(index, JSON.stringify(light));
console.log(`blogPosts.json: ${light.length} in index, ${written} content files`);
