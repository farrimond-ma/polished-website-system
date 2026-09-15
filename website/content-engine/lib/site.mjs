import fs from 'fs';
import path from 'path';
import { fileURLToPath, pathToFileURL } from 'url';

export const ENGINE_DIR = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
export const SITE_DIR = path.resolve(ENGINE_DIR, '..');
export const POSTS_FILE = path.join(SITE_DIR, 'src', 'data', 'blogPosts.json');
export const TOPICS_FILE = path.join(ENGINE_DIR, 'topics.json');
export const IMAGES_DIR = path.join(SITE_DIR, 'public', 'images', 'guides');
export const SITE_URL = 'https://polished-insurance.com';

export const CATEGORIES = ['Business types', 'Covers explained', 'Contracts & tenders', 'Running your business', 'Claims & risk'];

export const readJSON = (file, fallback) => (fs.existsSync(file) ? JSON.parse(fs.readFileSync(file, 'utf8')) : fallback);
export const writeJSON = (file, data) => fs.writeFileSync(file, `${JSON.stringify(data, null, 2)}\n`);

/** Cover pages from the website's own data file, so guides can only link to pages that exist. */
export async function loadCovers() {
  const mod = await import(pathToFileURL(path.join(SITE_DIR, 'src', 'data', 'covers.js')).href);
  return mod.covers;
}

/** Every internal URL a guide may link to, with a label for the prompt. */
export async function allowedLinks(posts) {
  const covers = await loadCovers();
  const links = [
    { url: '/get-a-quote', label: 'Get a quote (general enquiry page)', kind: 'quote' },
    { url: '/cleaning-insurance', label: 'All cleaning insurance covers (hub)', kind: 'hub' },
    { url: '/guides', label: 'All guides', kind: 'hub' },
  ];
  for (const c of covers) {
    links.push({ url: `/cleaning-insurance/${c.slug}`, label: `${c.title} (information page)`, kind: 'cover', slug: c.slug });
    links.push({ url: `/get-a-quote/${c.slug}`, label: `${c.title} quote (enquiry page)`, kind: 'quote', slug: c.slug });
  }
  for (const p of posts.filter((x) => x.status === 'published')) {
    links.push({ url: `/guides/${p.slug}`, label: `Guide: ${p.title}`, kind: 'guide', slug: p.slug });
  }
  return { covers, links };
}

export const slugify = (s) => String(s).toLowerCase()
  .replace(/&/g, ' and ').replace(/['’]/g, '')
  .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '')
  .slice(0, 80).replace(/-+$/, '');

const STOP = new Set('a an and are as at be by can do does for from how i in is it my of on or should the to what when which who why with you your cleaners cleaning cleaner insurance uk business businesses guide'.split(' '));
export const tokens = (s) => new Set(String(s).toLowerCase().replace(/[^a-z0-9 ]+/g, ' ').split(/\s+/).filter((w) => w.length > 2 && !STOP.has(w)));

/** Jaccard similarity between two phrases (used for duplicate checks and related guides). */
export function similarity(a, b) {
  const A = tokens(a); const B = tokens(b);
  if (!A.size || !B.size) return 0;
  let inter = 0;
  for (const w of A) if (B.has(w)) inter++;
  return inter / (A.size + B.size - inter);
}
