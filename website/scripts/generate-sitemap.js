import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { covers } from '../src/data/covers.js';

// The sitemap is also the prerender route list (see prerender.js), so every public page must be here.
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const BASE_URL = 'https://www.polished-insurance.co.uk';
const today = new Date().toISOString().slice(0, 10);

const staticRoutes = [
  ['/', '1.0', 'weekly'],
  ['/cleaning-insurance', '0.9', 'monthly'],
  ['/guides', '0.8', 'weekly'],
  ['/get-a-quote', '0.8', 'monthly'],
  ['/about-us', '0.5', 'yearly'],
  ['/privacy-policy', '0.2', 'yearly'],
  ['/terms-of-business', '0.2', 'yearly'],
  ['/complaints', '0.2', 'yearly'],
  ['/cookie-policy', '0.2', 'yearly'],
];

const posts = JSON.parse(fs.readFileSync(path.join(root, 'src/data/blogPosts.json'), 'utf8'))
  .filter((p) => p.status === 'published' && p.slug);

const urls = [
  ...staticRoutes.map(([loc, priority, changefreq]) => ({ loc, priority, changefreq, lastmod: today })),
  ...covers.map((c) => ({ loc: `/cleaning-insurance/${c.slug}`, priority: '0.9', changefreq: 'monthly', lastmod: today })),
  ...covers.map((c) => ({ loc: `/get-a-quote/${c.slug}`, priority: '0.6', changefreq: 'monthly', lastmod: today })),
  ...posts.map((p) => ({ loc: `/guides/${p.slug}`, priority: '0.7', changefreq: 'monthly', lastmod: (p.updatedAt || p.publishedAt || p.date || today).slice(0, 10) })),
];

const xml = `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
${urls.map((u) => `  <url><loc>${BASE_URL}${u.loc === '/' ? '' : u.loc}</loc><lastmod>${u.lastmod}</lastmod><changefreq>${u.changefreq}</changefreq><priority>${u.priority}</priority></url>`).join('\n')}
</urlset>
`;
for (const out of [path.join(root, 'public/sitemap.xml'), path.join(root, 'dist/sitemap.xml')]) {
  if (fs.existsSync(path.dirname(out))) fs.writeFileSync(out, xml);
}
console.log(`Sitemap: ${urls.length} URLs`);
