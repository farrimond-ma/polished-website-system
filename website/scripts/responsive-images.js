import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import sharp from 'sharp';

// Creates the smaller copies phones download instead of the full-size photos (runs before dev and
// build, so guide images added by the content engine get them too). Existing copies are skipped.
//   covers/<slug>.webp (800px cards)          -> <slug>-480.webp
//   hero/, guides/, covers/<slug>-hero.webp  -> <name>-800.webp
// Keep the names in step with src/lib/images.js.
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const IMAGES = path.join(root, 'public/images');
const VARIANT = /-\d+\.webp$/;

const jobs = [];
for (const dir of ['hero', 'guides', 'covers']) {
  const full = path.join(IMAGES, dir);
  if (!fs.existsSync(full)) continue;
  for (const name of fs.readdirSync(full)) {
    if (!name.endsWith('.webp') || VARIANT.test(name)) continue;
    const isCard = dir === 'covers' && !name.endsWith('-hero.webp');
    const width = isCard ? 480 : 800;
    jobs.push({ src: path.join(full, name), out: path.join(full, name.replace(/\.webp$/, `-${width}.webp`)), width });
  }
}

let made = 0;
for (const { src, out, width } of jobs) {
  if (fs.existsSync(out) && fs.statSync(out).mtimeMs >= fs.statSync(src).mtimeMs) continue;
  await sharp(src).resize({ width, withoutEnlargement: true }).webp({ quality: 74 }).toFile(out);
  made++;
}
console.log(`Responsive images: ${made} created, ${jobs.length - made} up to date.`);
