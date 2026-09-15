// Guide index helpers. blogIndex.json is generated from blogPosts.json by scripts/split-content.js
// (article bodies are fetched per page from /content/guides/<slug>.json).
import blogIndex from './blogIndex.json';

export const publishedAt = (p) => p.publishedAt || p.date || '';
export const byNewest = (a, b) => (publishedAt(b) > publishedAt(a) ? 1 : -1);

export const publishedPosts = blogIndex.filter((p) => p && p.status === 'published').sort(byNewest);
export const postsBySlug = Object.fromEntries(publishedPosts.map((p) => [p.slug, p]));

export const categories = ['Business types', 'Covers explained', 'Contracts & tenders', 'Running your business', 'Claims & risk'];

export const formatDate = (d, precise = false) => {
  const date = new Date(d);
  if (Number.isNaN(date.getTime())) return '';
  return date.toLocaleDateString('en-GB', precise ? { day: 'numeric', month: 'long', year: 'numeric' } : { month: 'long', year: 'numeric' });
};

/** Guides for a cover page, newest first; falls back to the latest guides. */
export const guidesForCover = (coverSlug, limit = 6) => {
  const matched = publishedPosts.filter((p) => p.coverSlug === coverSlug);
  const rest = publishedPosts.filter((p) => p.coverSlug !== coverSlug);
  return [...matched, ...rest].slice(0, limit);
};

export const DEFAULT_GUIDE_IMAGE = '/images/hero/guide-default.svg';
