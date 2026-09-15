import fs from 'fs';
import path from 'path';
import sharp from 'sharp';
import { IMAGES_DIR } from './site.mjs';

// Hero image from Pexels (free licence, attribution appreciated). Optional: without PEXELS_API_KEY
// the guide uses the site's branded default artwork instead.
export async function fetchHeroImage({ query, slug, usedPhotoIds = new Set() }) {
  const key = process.env.PEXELS_API_KEY;
  if (!key) {
    console.log('[image] PEXELS_API_KEY not set — using the default guide artwork.');
    return null;
  }
  const queries = [query, `${query.split(' ').slice(0, 2).join(' ')} cleaning`, 'professional cleaning'];
  for (const q of queries) {
    const res = await fetch(`https://api.pexels.com/v1/search?${new URLSearchParams({ query: q, orientation: 'landscape', per_page: '30', locale: 'en-GB' })}`, {
      headers: { Authorization: key },
    });
    if (!res.ok) { console.warn(`[image] Pexels search failed (${res.status}) for "${q}"`); continue; }
    const data = await res.json();
    const photo = (data.photos || []).find((p) => !usedPhotoIds.has(String(p.id)) && p.width >= 1600);
    if (!photo) continue;

    const img = await fetch(photo.src.large2x || photo.src.original);
    if (!img.ok) continue;
    const buffer = Buffer.from(await img.arrayBuffer());
    fs.mkdirSync(IMAGES_DIR, { recursive: true });
    const file = path.join(IMAGES_DIR, `${slug}.webp`);
    await sharp(buffer).resize({ width: 1600, height: 900, fit: 'cover' }).webp({ quality: 78 }).toFile(file);
    console.log(`[image] Pexels photo ${photo.id} by ${photo.photographer} -> ${path.basename(file)}`);
    return {
      heroImage: `/images/guides/${slug}.webp`,
      heroCredit: `${photo.photographer} / Pexels`,
      heroPhotoId: String(photo.id),
    };
  }
  console.warn('[image] No suitable Pexels photo found — using the default guide artwork.');
  return null;
}
