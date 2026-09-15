<?php
/**
 * Data for the "Guides" page (content engine dashboard).
 *
 * Everything is read straight from the GitHub repository, which is where the content engine
 * keeps its state: the topic queue (website/content-engine/topics.json), the published guides
 * (website/src/data/blogPosts.json), the publishing schedule (the cron lines in
 * .github/workflows/publish-guide.yml) and the recent "Publish guide" runs (GitHub API).
 * Responses are cached for 10 minutes in data/ so the page is quick and stays well inside
 * GitHub's limit for anonymous requests. If the repository is ever made private, add a
 * read-only 'github_token' to config.php.
 */
declare(strict_types=1);

const GUIDES_CACHE_TTL = 600;

function guides_repo(): string {
    return (string)cfg('github_repo', 'farrimond-ma/polished-website-system');
}

function guides_cache_file(string $key): string {
    return __DIR__ . '/../data/cache_guides_' . sha1($key) . '.json';
}

/** GET a URL with a small file cache. Returns ['body' => ?string, 'error' => ?string, 'at' => int]. */
function guides_fetch(string $url, bool $fresh = false): array {
    $file = guides_cache_file($url);
    $cached = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
    if (!$fresh && is_array($cached) && $cached['at'] > time() - GUIDES_CACHE_TTL) {
        return ['body' => $cached['body'], 'error' => null, 'at' => (int)$cached['at']];
    }

    $headers = ['User-Agent: Polished-CRM', 'Accept: application/vnd.github+json'];
    $token = cfg('github_token');
    if ($token && str_contains($url, 'api.github.com')) $headers[] = 'Authorization: Bearer ' . $token;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);

    if ($body === false || $code >= 400) {
        $why = $body === false ? "GitHub could not be reached ($err)" : "GitHub returned an error (HTTP $code)";
        if (is_array($cached)) return ['body' => $cached['body'], 'error' => "$why, so this shows the copy saved at " . date('H:i', (int)$cached['at']) . '.', 'at' => (int)$cached['at']];
        return ['body' => null, 'error' => "$why.", 'at' => time()];
    }
    @file_put_contents($file, json_encode(['at' => time(), 'body' => $body]));
    return ['body' => (string)$body, 'error' => null, 'at' => time()];
}

/** Is a page on the live website responding? Cached like guides_fetch(). */
function guides_is_live(string $url, bool $fresh = false): bool {
    $file = guides_cache_file('live:' . $url);
    $cached = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
    if (!$fresh && is_array($cached) && $cached['at'] > time() - GUIDES_CACHE_TTL) return (bool)$cached['live'];
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_NOBODY => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 8, CURLOPT_USERAGENT => 'Polished-CRM']);
    curl_exec($ch);
    $live = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200;
    @file_put_contents($file, json_encode(['at' => time(), 'live' => $live]));
    return $live;
}

/** Scheduled slots from the workflow's cron lines, e.g. '15 7 * * 1,3' -> [['min'=>15,'hour'=>7,'dow'=>1], ...]. */
function guides_schedule_slots(string $yml): array {
    preg_match_all("/cron:\s*['\"](\d{1,2})\s+(\d{1,2})\s+\*\s+\*\s+([0-6](?:,[0-6])*)['\"]/", $yml, $m, PREG_SET_ORDER);
    $slots = [];
    foreach ($m as $line) {
        foreach (explode(',', $line[3]) as $dow) $slots[] = ['min' => (int)$line[1], 'hour' => (int)$line[2], 'dow' => (int)$dow];
    }
    return $slots;
}

/** The next $count publish times (UTC DateTimeImmutable), soonest first. */
function guides_next_runs(array $slots, int $count, ?int $now = null): array {
    if (!$slots) return [];
    $utc = new DateTimeZone('UTC');
    $now = $now ?? time();
    $day = (new DateTimeImmutable('@' . $now))->setTimezone($utc)->setTime(0, 0);
    $runs = [];
    for ($i = 0; count($runs) < $count && $i < 400; $i++) {
        $d = $day->modify("+$i day");
        $todays = [];
        foreach ($slots as $s) {
            if ((int)$d->format('w') !== $s['dow']) continue;
            $t = $d->setTime($s['hour'], $s['min']);
            if ($t->getTimestamp() > $now) $todays[] = $t;
        }
        usort($todays, fn($a, $b) => $a <=> $b);
        foreach ($todays as $t) if (count($runs) < $count) $runs[] = $t;
    }
    return $runs;
}

/** UK-time label for a UTC time or ISO string. */
function guides_uk($when, string $format = 'D j M Y, H:i'): string {
    if (!$when) return '—';
    $dt = $when instanceof DateTimeInterface ? DateTimeImmutable::createFromInterface($when) : new DateTimeImmutable((string)$when);
    return $dt->setTimezone(new DateTimeZone('Europe/London'))->format($format);
}

function guides_cover_name(?string $slug): string {
    return $slug ? ucfirst(str_replace('-', ' ', $slug)) : '—';
}

/** Everything the Guides page shows. */
function guides_dashboard(bool $fresh = false): array {
    $repo = guides_repo();
    $raw = "https://raw.githubusercontent.com/$repo/main/";
    $topicsRes = guides_fetch($raw . 'website/content-engine/topics.json', $fresh);
    $postsRes = guides_fetch($raw . 'website/src/data/blogPosts.json', $fresh);
    $ymlRes = guides_fetch($raw . '.github/workflows/publish-guide.yml', $fresh);
    $runsRes = guides_fetch("https://api.github.com/repos/$repo/actions/workflows/publish-guide.yml/runs?per_page=12", $fresh);

    $errors = array_values(array_unique(array_filter([$topicsRes['error'], $postsRes['error'], $ymlRes['error'], $runsRes['error']])));
    $topics = json_decode((string)$topicsRes['body'], true);
    $posts = json_decode((string)$postsRes['body'], true);
    $runsData = json_decode((string)$runsRes['body'], true);
    $topics = is_array($topics) ? $topics : [];
    $posts = is_array($posts) ? $posts : [];

    $siteUrl = rtrim((string)cfg('site_url', 'https://www.polished-insurance.co.uk'), '/');
    $published = array_values(array_filter($posts, fn($p) => ($p['status'] ?? '') === 'published' && !empty($p['slug'])));
    usort($published, fn($a, $b) => strcmp((string)($b['publishedAt'] ?? $b['date'] ?? ''), (string)($a['publishedAt'] ?? $a['date'] ?? '')));
    foreach ($published as &$p) $p['url'] = $siteUrl . '/guides/' . $p['slug'];
    unset($p);
    // Confirm the newest few really are live on the website (the deploy runs after publishing).
    foreach (array_slice(array_keys($published), 0, 5) as $i) $published[$i]['live'] = guides_is_live($published[$i]['url'], $fresh);

    $queued = array_values(array_filter($topics, fn($t) => ($t['status'] ?? '') === 'queued'));
    $skipped = array_values(array_filter($topics, fn($t) => ($t['status'] ?? '') === 'skipped'));
    $slots = guides_schedule_slots((string)$ymlRes['body']);
    $nextRuns = guides_next_runs($slots, count($queued));
    foreach ($queued as $i => &$t) $t['due'] = $nextRuns[$i] ?? null;
    unset($t);

    $runs = [];
    foreach (($runsData['workflow_runs'] ?? []) as $r) {
        $start = strtotime((string)$r['created_at']);
        $end = strtotime((string)($r['updated_at'] ?? $r['created_at'])) + 120;
        $guide = null;
        foreach ($published as $p) {
            $at = strtotime((string)($p['publishedAt'] ?? ''));
            if ($at && $at >= $start && $at <= $end) { $guide = $p; break; }
        }
        if (($r['status'] ?? '') !== 'completed') $result = ['Running', 'run'];
        elseif (($r['conclusion'] ?? '') === 'success') $result = $guide ? ['Published', 'ok'] : ['Ran — nothing published', ''];
        elseif (($r['conclusion'] ?? '') === 'cancelled') $result = ['Cancelled', ''];
        else $result = ['Failed', 'bad'];
        $runs[] = [
            'when' => $r['created_at'],
            'trigger' => ($r['event'] ?? '') === 'schedule' ? 'Scheduled' : 'Manual',
            'result' => $result[0],
            'class' => $result[1],
            'guide' => $guide,
            'url' => $r['html_url'] ?? '',
        ];
    }

    return [
        'repo' => $repo,
        'errors' => $errors,
        'fetchedAt' => min($topicsRes['at'], $postsRes['at'], $ymlRes['at'], $runsRes['at']),
        'slots' => $slots,
        'queued' => $queued,
        'skipped' => $skipped,
        'published' => $published,
        'runs' => $runs,
        'nextRun' => guides_next_runs($slots, 1)[0] ?? null,
    ];
}
