#!/usr/bin/env node
/**
 * Tops up topics.json with new guide ideas when the queue runs low (called automatically by
 * publish-guide.mjs). Run on its own with: node refill-topics.mjs [count]
 */
import { fileURLToPath } from 'url';
import { generateJSON } from './lib/claude.mjs';
import { TOPICS_BRIEF, topicsSchema } from './lib/prompts.mjs';
import { POSTS_FILE, TOPICS_FILE, readJSON, writeJSON, loadCovers, similarity } from './lib/site.mjs';

export async function refillTopics({ topics, posts, covers, count = 12 }) {
  const coverSlugs = covers.map((c) => c.slug);
  const existing = [
    ...posts.filter((p) => p.status === 'published').map((p) => `- (published) ${p.title}`),
    ...topics.filter((t) => t.status !== 'skipped').map((t) => `- (${t.status}) ${t.title}`),
  ].join('\n') || '- (none)';

  const result = await generateJSON({
    system: TOPICS_BRIEF,
    user: `Propose ${count + 4} new guide topics.

Cover page slugs (each topic must use one; spread topics across them, weighting towards the business types):
${covers.map((c) => `- ${c.slug}: ${c.title}`).join('\n')}

Already published or queued (do not overlap with these):
${existing}`,
    schema: topicsSchema(coverSlugs),
    effort: 'medium',
    maxTokens: 16000,
    label: 'topic ideas',
  });

  const known = [...posts.map((p) => p.title), ...topics.map((t) => t.title)];
  let nextId = topics.reduce((m, t) => Math.max(m, Number(String(t.id).replace(/\D/g, '')) || 0), 0) + 1;
  let added = 0;
  for (const t of result.topics) {
    if (added >= count) break;
    if (known.some((k) => similarity(k, t.title) >= 0.5)) continue;
    topics.push({ id: `t${String(nextId++).padStart(3, '0')}`, status: 'queued', title: t.title, keyword: t.keyword, coverSlug: t.coverSlug, angle: t.angle, addedAt: new Date().toISOString().slice(0, 10), source: 'ai' });
    known.push(t.title);
    added++;
  }
  console.log(`[topics] Added ${added} new topic(s).`);
  return added;
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
  const posts = readJSON(POSTS_FILE, []);
  const topics = readJSON(TOPICS_FILE, []);
  const covers = await loadCovers();
  await refillTopics({ topics, posts, covers, count: Number(process.argv[2] || 12) });
  writeJSON(TOPICS_FILE, topics);
}
