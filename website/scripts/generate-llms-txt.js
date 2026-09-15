import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { covers } from '../src/data/covers.js';

// llms.txt: a plain-text map of the site for AI answer engines (same idea as the Boxx site).
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const BASE_URL = 'https://polished-insurance.com';
const posts = JSON.parse(fs.readFileSync(path.join(root, 'src/data/blogPosts.json'), 'utf8')).filter((p) => p.status === 'published');

const txt = `# Polished Insurance

> Specialist insurance broker for UK cleaning businesses. Polished Insurance is a trading name of Allied Insurance Services Ltd, authorised and regulated by the Financial Conduct Authority (FRN 309497). Phone 0330 056 8970.

## Insurance for cleaning businesses
${covers.map((c) => `- [${c.title}](${BASE_URL}/cleaning-insurance/${c.slug}): ${c.cardBlurb}`).join('\n')}

## Guides
${posts.map((p) => `- [${p.title}](${BASE_URL}/guides/${p.slug}): ${p.excerpt || ''}`).join('\n') || '- Guides are published weekly at ' + BASE_URL + '/guides'}

## Get a quote
- [Get a quote](${BASE_URL}/get-a-quote)
`;
for (const dir of ['public', 'dist']) {
  if (fs.existsSync(path.join(root, dir))) fs.writeFileSync(path.join(root, dir, 'llms.txt'), txt);
}
console.log('llms.txt written');
