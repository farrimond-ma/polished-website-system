import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const BASE_URL = 'https://polished-insurance.com';
const esc = (s) => String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
const posts = JSON.parse(fs.readFileSync(path.join(root, 'src/data/blogPosts.json'), 'utf8'))
  .filter((p) => p.status === 'published')
  .sort((a, b) => ((b.publishedAt || b.date) > (a.publishedAt || a.date) ? 1 : -1))
  .slice(0, 30);

const rss = `<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
  <title>Polished Insurance guides</title>
  <link>${BASE_URL}/guides</link>
  <atom:link href="${BASE_URL}/rss.xml" rel="self" type="application/rss+xml" />
  <description>Insurance guides for UK cleaning businesses.</description>
  <language>en-gb</language>
${posts.map((p) => `  <item>
    <title>${esc(p.title)}</title>
    <link>${BASE_URL}/guides/${p.slug}</link>
    <guid>${BASE_URL}/guides/${p.slug}</guid>
    <pubDate>${new Date(p.publishedAt || p.date).toUTCString()}</pubDate>
    <description>${esc(p.excerpt)}</description>
  </item>`).join('\n')}
</channel>
</rss>
`;
for (const dir of ['public', 'dist']) {
  if (fs.existsSync(path.join(root, dir))) fs.writeFileSync(path.join(root, dir, 'rss.xml'), rss);
}
console.log(`RSS: ${posts.length} items`);
