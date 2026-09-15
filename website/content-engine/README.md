# Polished content engine

Publishes detailed insurance guides for UK cleaning businesses to `polished-insurance.com/guides`. It follows the same loop as the Boxx site's engine (generate → commit → deploy) but is simpler: the topic queue is a JSON file in the repo, so there is no Google Sheet to maintain.

## Schedule

`.github/workflows/publish-guide.yml` runs **Tuesday and Friday at 07:15 UTC**, which gives 2 guides a week. Delete one cron line for 1 a week. You can also run it manually from the Actions tab, with an optional dry run or a specific topic id.

## What one run does

1. **Picks** the next `queued` topic in `topics.json`. It skips any topic that is too similar to a published guide.
2. **Writes** the guide with Claude (`claude-opus-5`, adaptive thinking, structured JSON output). The brief is in `lib/prompts.mjs` and covers:
   - tone, UK accuracy, and compliance (no invented statistics, no "cheapest", general guidance only)
   - 2,000–2,800 words, at least 6 H2 sections, a checklist, comparison tables where useful, and an FAQ section
   - internal links: at least 6, including the matching cover page and **the matching enquiry page `/get-a-quote/<slug>` at least twice**
3. **Cleans** the HTML (`lib/html.mjs`). Only safe tags are kept. Any internal link that isn't a real page is removed, and external links are only kept for gov.uk, HSE, legislation.gov.uk, ICO and FCA.
4. **Audits** length, headings, link counts, the enquiry-page links, FAQs and banned claims. If anything fails, it asks Claude to revise the draft, up to 2 times. If the enquiry link is still missing after that, a closing paragraph with the link is added automatically.
5. **Hero image** from Pexels, if `PEXELS_API_KEY` is set. It is converted to WebP at `public/images/guides/<slug>.webp`. Otherwise the branded default artwork is used.
6. **Cross-links**: the new guide lists up to 4 related guides, and the 2 most related older guides link back to it.
7. **Saves** `src/data/blogPosts.json` and `topics.json`. When fewer than 6 topics are left, it asks Claude for 12 new, non-overlapping topics.
8. The workflow **commits** the changes and runs **Deploy website**.

At launch the queue holds 36 hand-picked topics.

## Where the guides link

Guides can only link to the URLs the engine gives Claude. They are built from `website/src/data/covers.js` plus the existing guides:

- `/cleaning-insurance/<slug>` information pages
- `/get-a-quote/<slug>` enquiry pages
- `/guides/<slug>` other guides

On the page, three calls-to-action are also inserted around the article (`src/components/ArticleCtas.jsx`), all pointing at the guide's enquiry page.

## Editing topics

Edit `topics.json` directly. Each topic looks like this:

```json
{ "id": "t037", "status": "queued", "title": "…", "keyword": "…", "coverSlug": "window-cleaners-insurance", "angle": "…" }
```

`status` is one of `queued`, `published` or `skipped`. `coverSlug` must be one of the slugs in `covers.js`.

To unpublish a guide, set its `status` in `blogPosts.json` to `draft` and push.

## Running locally

```bash
cd website/content-engine
npm ci
ANTHROPIC_API_KEY=... node publish-guide.mjs --dry-run      # writes dry-run-output.json, changes nothing
ANTHROPIC_API_KEY=... node publish-guide.mjs --topic t005
node refill-topics.mjs 12                                    # add 12 topic ideas
```

`CONTENT_ENGINE_MOCK=path/to/article.json` replays a saved response instead of calling the API. It is only for testing the pipeline.

## Cost

Each guide is one Claude Opus 5 request, plus a revision request only when the audit fails. The fixed brief is sent with prompt caching, so a revision pass reuses the cached copy. Check actual usage in the Claude Console after the first few runs.
