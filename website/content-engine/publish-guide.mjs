#!/usr/bin/env node
/**
 * Publishes ONE new guide from the topic queue.
 *
 *   node publish-guide.mjs              publish the next queued topic
 *   node publish-guide.mjs --dry-run    generate and print, change nothing
 *   node publish-guide.mjs --topic t012 publish a specific queued topic
 *
 * Flow: pick topic -> duplicate check -> generate (Claude) -> sanitise + audit links/length ->
 * revise pass if needed -> hero image -> related-guide cross links -> write blogPosts.json +
 * topics.json -> top up the queue if it is running low. The GitHub workflow then commits the
 * changes and runs the deploy, exactly like the Boxx site's publishing loop.
 */
import fs from 'fs';
import path from 'path';
import { generateJSON } from './lib/claude.mjs';
import { ARTICLE_BRIEF, articleSchema } from './lib/prompts.mjs';
import { sanitiseHtml, auditArticle, wordCount } from './lib/html.mjs';
import { fetchHeroImage } from './lib/images.mjs';
import { titleCase } from '../src/lib/titleCase.js';
import { POSTS_FILE, TOPICS_FILE, ENGINE_DIR, readJSON, writeJSON, allowedLinks, slugify, similarity } from './lib/site.mjs';
import { refillTopics } from './refill-topics.mjs';

const args = process.argv.slice(2);
const DRY_RUN = args.includes('--dry-run');
const topicArg = args.includes('--topic') ? args[args.indexOf('--topic') + 1] : null;
const MIN_WORDS = 1800;
const MIN_QUEUE = Number(process.env.MIN_QUEUE || 6);

function pickTopic(topics, posts) {
  const queued = topics.filter((t) => t.status === 'queued');
  const candidates = topicArg ? queued.filter((t) => t.id === topicArg) : queued;
  for (const t of candidates) {
    const clash = posts.find((p) => p.status === 'published' && (similarity(p.title, t.title) >= 0.6 || (p.keywords || []).some((k) => similarity(k, t.keyword) >= 0.8)));
    if (clash) {
      console.log(`[topic] Skipping "${t.title}" — too close to published guide "${clash.title}".`);
      t.status = 'skipped';
      t.note = `Overlaps /guides/${clash.slug}`;
      continue;
    }
    return t;
  }
  return null;
}

function relatedFor(post, posts) {
  return posts
    .filter((p) => p.status === 'published' && p.slug !== post.slug)
    .map((p) => ({
      slug: p.slug,
      score: similarity(`${p.title} ${(p.keywords || []).join(' ')}`, `${post.title} ${post.keywords.join(' ')}`) + (p.coverSlug === post.coverSlug ? 0.25 : 0),
    }))
    .sort((a, b) => b.score - a.score)
    .slice(0, 4)
    .filter((x) => x.score > 0.05)
    .map((x) => x.slug);
}

function userPrompt(topic, links, posts, issues, previous) {
  const linkList = links.map((l) => `- ${l.url} — ${l.label}`).join('\n');
  const existing = posts.filter((p) => p.status === 'published').map((p) => `- ${p.title}`).join('\n') || '- (none yet)';
  let text = `Write a new guide.

Topic: ${topic.title}
Main keyword: ${topic.keyword}
Matching cover page slug (coverSlug): ${topic.coverSlug}
Angle / reader need: ${topic.angle || 'Answer the question thoroughly for a UK cleaning business owner.'}
Today's date: ${new Date().toISOString().slice(0, 10)}

Allowed internal URLs (use only these, exactly as written):
${linkList}

Existing guides (do not duplicate their angle; link to them where genuinely relevant):
${existing}`;
  if (issues?.length) {
    text += `

Your previous draft is below as JSON. Revise it and return the complete corrected article. Fix every one of these problems while keeping everything that was good:
${issues.map((i) => `- ${i}`).join('\n')}

Previous draft:
${JSON.stringify(previous)}`;
  }
  return text;
}

async function main() {
  const posts = readJSON(POSTS_FILE, []);
  const topics = readJSON(TOPICS_FILE, []);
  const { covers, links } = await allowedLinks(posts);
  const coverSlugs = covers.map((c) => c.slug);
  const allowedUrls = new Set(links.map((l) => l.url));

  let topic = pickTopic(topics, posts);
  if (!topic && !topicArg) {
    console.log('[topic] Queue is empty — generating new topics first.');
    await refillTopics({ topics, posts, covers, count: 12, dryRun: false });
    topic = pickTopic(topics, posts);
  }
  if (!topic) throw new Error(topicArg ? `Topic ${topicArg} is not queued.` : 'No topic available to publish.');
  console.log(`[topic] ${topic.id}: ${topic.title}`);

  // ---- generate, audit, revise (up to 2 revisions) ----
  const schema = articleSchema(coverSlugs);
  let article = null;
  let issues = [];
  for (let attempt = 0; attempt < 3; attempt++) {
    const raw = await generateJSON({
      system: ARTICLE_BRIEF,
      user: userPrompt(topic, links, posts, attempt ? issues : null, article),
      schema,
      label: attempt ? `revise #${attempt}` : 'draft',
    });
    const { html, removedLinks } = sanitiseHtml(raw.contentHtml, allowedUrls);
    if (removedLinks.length) console.log(`[links] Removed links not on the allowed list: ${removedLinks.join(', ')}`);
    article = { ...raw, contentHtml: html, coverSlug: topic.coverSlug || raw.coverSlug };
    issues = auditArticle(article, { coverSlug: article.coverSlug, minWords: MIN_WORDS });
    console.log(`[audit] attempt ${attempt + 1}: ${wordCount(html)} words, ${issues.length} issue(s)`);
    if (!issues.length) break;
    issues.forEach((i) => console.log(`        - ${i}`));
  }

  // Hard requirements that must never ship broken. Link shortfalls are topped up deterministically.
  const words = wordCount(article.contentHtml);
  if (words < MIN_WORDS * 0.85) throw new Error(`Article too short after revisions (${words} words) — not publishing.`);
  const cover = covers.find((c) => c.slug === article.coverSlug);
  if (!article.contentHtml.includes(`href="/get-a-quote/${article.coverSlug}"`)) {
    const para = `<p>If you would like cover arranged around the way your business works, you can <a href="/get-a-quote/${article.coverSlug}">request a ${cover.title.toLowerCase()} quote</a> or read more about <a href="/cleaning-insurance/${article.coverSlug}">${cover.title.toLowerCase()}</a>.</p>`;
    article.contentHtml = article.contentHtml.replace(/(<h2>\s*Frequently Asked Questions\s*<\/h2>)/i, `${para}\n$1`);
    if (!article.contentHtml.includes(para)) article.contentHtml += `\n${para}`;
    console.log('[links] Added the enquiry-page link paragraph automatically.');
  }

  // ---- unique slug ----
  let slug = slugify(article.slug || article.title);
  const taken = new Set(posts.map((p) => p.slug));
  for (let n = 2; taken.has(slug); n++) slug = `${slugify(article.slug || article.title)}-${n}`;

  const now = new Date().toISOString();
  const post = {
    id: Date.now(),
    status: 'published',
    slug,
    title: titleCase(article.title),
    excerpt: article.excerpt.trim(),
    metaTitle: titleCase(article.metaTitle).slice(0, 60),
    metaDescription: article.metaDescription.trim().slice(0, 170),
    keywords: article.keywords.slice(0, 8),
    category: article.category,
    coverSlug: article.coverSlug,
    author: 'Polished Insurance team',
    date: now.slice(0, 10),
    publishedAt: now,
    readingMinutes: Math.max(4, Math.round(wordCount(article.contentHtml) / 230)),
    wordCount: wordCount(article.contentHtml),
    heroImage: null,
    heroAlt: article.imageAlt,
    heroCredit: null,
    relatedSlugs: [],
    topicId: topic.id,
    content: article.contentHtml,
    faqs: article.faqs.slice(0, 8),
  };
  post.relatedSlugs = relatedFor(post, posts);

  if (DRY_RUN) {
    const out = path.join(ENGINE_DIR, 'dry-run-output.json');
    writeJSON(out, post);
    console.log(`\n[dry-run] Nothing published. Article written to ${out}`);
    console.log(`[dry-run] "${post.title}" /guides/${post.slug} — ${post.wordCount} words, related: ${post.relatedSlugs.join(', ') || 'none'}`);
    return;
  }

  const used = new Set(posts.map((p) => p.heroPhotoId).filter(Boolean));
  try {
    Object.assign(post, await fetchHeroImage({ query: article.imageSearchQuery || topic.keyword, slug, usedPhotoIds: used }) || {});
  } catch (err) {
    console.warn(`[image] ${err.message} — publishing without a photo.`);
  }

  // Inbound links: the two most related older guides list the new one in "Related guides".
  for (const relSlug of post.relatedSlugs.slice(0, 2)) {
    const older = posts.find((p) => p.slug === relSlug);
    if (older) older.relatedSlugs = [slug, ...(older.relatedSlugs || []).filter((s) => s !== slug)].slice(0, 6);
  }

  posts.push(post);
  topic.status = 'published';
  topic.slug = slug;
  topic.publishedAt = now;
  writeJSON(POSTS_FILE, posts);

  if (topics.filter((t) => t.status === 'queued').length < MIN_QUEUE) {
    try {
      await refillTopics({ topics, posts, covers, count: 12, dryRun: false });
    } catch (err) {
      console.warn(`[topics] Could not refill the queue this run: ${err.message}`);
    }
  }
  writeJSON(TOPICS_FILE, topics);

  if (process.env.GITHUB_OUTPUT) fs.appendFileSync(process.env.GITHUB_OUTPUT, `slug=${slug}\ntitle=${post.title.replace(/\n/g, ' ')}\n`);
  console.log(`\n[published] "${post.title}" -> /guides/${slug} (${post.wordCount} words)`);
}

main().catch((err) => {
  console.error(`\n[failed] ${err.stack || err.message}`);
  process.exit(1);
});
