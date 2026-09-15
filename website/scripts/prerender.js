import fs from 'fs';
import path from 'path';
import http from 'http';
import { fileURLToPath } from 'url';
import puppeteer from 'puppeteer';
import { covers } from '../src/data/covers.js';

// Renders every sitemap URL to static HTML so search engines and AI crawlers that don't run
// JavaScript see real content. Adapted from the Boxx site's scripts/prerender.js: the sitemap is
// the route list, and the build FAILS if any sitemap URL doesn't produce HTML.

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const distDir = path.join(root, 'dist');
const PORT = 4174;

const MIME = {
  '.html': 'text/html; charset=utf-8', '.js': 'application/javascript; charset=utf-8', '.css': 'text/css; charset=utf-8',
  '.json': 'application/json; charset=utf-8', '.svg': 'image/svg+xml', '.png': 'image/png', '.jpg': 'image/jpeg',
  '.webp': 'image/webp', '.xml': 'application/xml; charset=utf-8', '.txt': 'text/plain; charset=utf-8', '.woff2': 'font/woff2',
};

function startServer() {
  // Snapshot the neutral shell ONCE: route '/' overwrites dist/index.html with the rendered home page.
  const shell = fs.readFileSync(path.join(distDir, 'index.html'));
  const server = http.createServer((req, res) => {
    let p = decodeURIComponent((req.url || '/').split('?')[0]);
    let file = path.join(distDir, p === '/' ? 'index.html' : p);
    if (fs.existsSync(file) && fs.statSync(file).isDirectory()) file = path.join(file, 'index.html');
    const useShell = !fs.existsSync(file) || p === '/';
    res.writeHead(200, { 'Content-Type': useShell ? MIME['.html'] : (MIME[path.extname(file)] || 'application/octet-stream') });
    res.end(useShell ? shell : fs.readFileSync(file));
  });
  return new Promise((resolve) => server.listen(PORT, () => resolve(server)));
}

function sitemapRoutes() {
  const file = [path.join(distDir, 'sitemap.xml'), path.join(root, 'public/sitemap.xml')].find((f) => fs.existsSync(f));
  if (!file) throw new Error('sitemap.xml not found — run "npm run sitemap" first.');
  const routes = [...fs.readFileSync(file, 'utf8').matchAll(/<loc>\s*([^<\s]+)\s*<\/loc>/g)]
    .map((m) => m[1].replace(/^https?:\/\/[^/]+/, '') || '/');
  if (!routes.length) throw new Error('No <loc> entries in sitemap.xml');
  return [...new Set(routes)];
}

// Pages that are NOT in the sitemap (noindex) but must still exist as real HTML, because the
// .htaccess no longer sends unknown URLs to the app shell (that caused soft 404s).
const EXTRA_ROUTES = [
  ...covers.map((c) => `/get-a-quote/${c.slug}`),
  '/get-a-quote/thank-you',
  '/insurance-questionnaire',
];
const NOT_FOUND_ROUTE = '/404'; // any unknown path renders the Not Found page -> saved as dist/404.html

const outputPath = (route) => (route === '/' ? path.join(distDir, 'index.html') : route === NOT_FOUND_ROUTE ? path.join(distDir, '404.html') : path.join(distDir, route.slice(1), 'index.html'));

async function render(browser, route) {
  const page = await browser.newPage();
  await page.goto(`http://127.0.0.1:${PORT}${route}`, { waitUntil: 'networkidle0', timeout: 120000 });
  if (route.startsWith('/guides/')) await page.waitForSelector('.blog-post-content', { timeout: 15000 });
  if (route.startsWith('/cleaning-insurance/')) await page.waitForSelector('[data-page-type="cover-page"]', { timeout: 15000 });
  await new Promise((r) => setTimeout(r, 400));
  const html = await page.content();
  await page.close();

  if (route !== NOT_FOUND_ROUTE && html.includes('data-page-type="not-found"')) throw new Error(`Prerender failed for ${route}: rendered the Not Found page.`);
  if (route === NOT_FOUND_ROUTE && !html.includes('data-page-type="not-found"')) throw new Error('Prerender failed for the 404 page.');
  if (!html.includes('og:title')) throw new Error(`Prerender failed for ${route}: no OpenGraph tags.`);
  if ((route.startsWith('/guides/') || route.startsWith('/cleaning-insurance/')) && !html.includes('application/ld+json')) {
    throw new Error(`Prerender failed for ${route}: no JSON-LD schema.`);
  }
  const out = outputPath(route);
  fs.mkdirSync(path.dirname(out), { recursive: true });
  fs.writeFileSync(out, html, 'utf8');
  console.log(`Prerendered ${route}`);
}

async function main() {
  if (!fs.existsSync(distDir)) throw new Error('dist/ not found — run vite build first.');
  const routes = [...new Set([...sitemapRoutes(), ...EXTRA_ROUTES, NOT_FOUND_ROUTE])];
  const executablePath = process.env.PUPPETEER_EXECUTABLE_PATH || process.env.CHROME_BIN || undefined;
  const browser = await puppeteer.launch({
    headless: true,
    executablePath,
    args: process.platform === 'win32' ? ['--disable-gpu'] : ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage', '--disable-gpu'],
  });
  const server = await startServer();
  try {
    // '/' last so the shell snapshot is never needed after the home page overwrites index.html.
    for (const r of [...routes.filter((x) => x !== '/'), '/']) await render(browser, r);
  } finally {
    await browser.close();
    server.close();
  }
  const missing = routes.filter((r) => !fs.existsSync(outputPath(r)));
  if (missing.length) throw new Error(`Prerender incomplete — no HTML for:\n  ${missing.join('\n  ')}`);
  console.log(`\nAll ${routes.length} sitemap routes prerendered.`);
}

main().catch((err) => { console.error(err); process.exit(1); });
