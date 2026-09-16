<?php
/** Guides: the content engine's upcoming topics, recent publishing runs and published guides. */
require __DIR__ . '/lib.php';
require_once __DIR__ . '/inc/guides.php';
require_login();
if (!is_admin()) { flash('The Guides page is for administrators.'); redirect('index.php'); }

$g = guides_dashboard(param('refresh') === '1');
$lastRun = $g['runs'][0] ?? null;
$days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$scheduleText = $g['slots']
    ? implode(', ', array_map(fn($s) => $days[$s['dow']], $g['slots'])) . ' at ' . guides_uk((new DateTimeImmutable('now', new DateTimeZone('UTC')))->setTime($g['slots'][0]['hour'], $g['slots'][0]['min']), 'H:i') . ' (UK time)'
    : 'not scheduled';

layout_header('Guides');
?>
<div class="page-head">
  <h1>Guides</h1>
  <div class="btn-row">
    <a class="btn ghost small" href="guides.php?refresh=1">Refresh now</a>
  </div>
</div>

<?php foreach ($g['errors'] as $err): ?><div class="flash"><?= e($err) ?></div><?php endforeach; ?>

<div class="kpis">
  <div class="kpi"><strong><?= $g['nextRun'] ? e(guides_uk($g['nextRun'], 'D j M')) : '—' ?></strong><span>Next guide due<?= $g['nextRun'] ? ' · ' . e(guides_uk($g['nextRun'], 'H:i')) : '' ?></span></div>
  <div class="kpi"><strong><?= count($g['queued']) ?></strong><span>Topics waiting</span></div>
  <div class="kpi"><strong><?= count($g['published']) ?></strong><span>Guides on the website</span></div>
  <div class="kpi"><strong><?= $lastRun ? '<span class="pill pill-lg ' . e($lastRun['class']) . '">' . e($lastRun['result']) . '</span>' : '—' ?></strong><span>Last run<?= $lastRun ? ' · ' . e(guides_uk($lastRun['when'], 'D j M H:i')) : '' ?></span></div>
</div>

<p class="sub">Publishing schedule: <?= e($scheduleText) ?>. GitHub can start a scheduled run up to about 30 minutes late.
  Information from GitHub is refreshed every 10 minutes (last checked <?= e(date('H:i', $g['fetchedAt'])) ?>).</p>

<h2 class="guides-h2">Recent publishing runs</h2>
<div class="table-scroll"><table class="grid small">
  <thead><tr><th>Started</th><th>Trigger</th><th>Result</th><th>Guide</th></tr></thead>
  <tbody>
  <?php if (!$g['runs']): ?><tr><td colspan="4" class="empty">No publishing runs yet. The first one runs on <?= $g['nextRun'] ? e(guides_uk($g['nextRun'])) : 'the next scheduled day' ?>.</td></tr><?php endif; ?>
  <?php foreach ($g['runs'] as $r): ?>
    <tr>
      <td><?= e(guides_uk($r['when'])) ?></td>
      <td><?= e($r['trigger']) ?></td>
      <td><span class="pill <?= e($r['class']) ?>"><?= e($r['result']) ?></span></td>
      <td><?= $r['guide'] ? '<a href="' . e($r['guide']['url']) . '" target="_blank" rel="noopener">' . e($r['guide']['title']) . '</a>' : '<span class="sub">—</span>' ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>

<h2 class="guides-h2">Upcoming topics</h2>
<p class="sub">Published in this order. If a topic turns out too similar to an existing guide it is skipped and the next one moves up.
  When fewer than 6 are left, 12 new topics are planned automatically.</p>
<div class="table-scroll"><table class="grid small">
  <thead><tr><th>Expected</th><th>Topic</th><th>Search phrase</th><th>Links to</th><th>Added</th></tr></thead>
  <tbody>
  <?php if (!$g['queued']): ?><tr><td colspan="5" class="empty">No topics waiting — new ones are planned on the next run.</td></tr><?php endif; ?>
  <?php foreach ($g['queued'] as $t): ?>
    <tr>
      <td style="white-space:nowrap"><?= $t['due'] ? e(guides_uk($t['due'], 'D j M')) : '—' ?></td>
      <td><?= e($t['title'] ?? '') ?></td>
      <td class="sub"><?= e($t['keyword'] ?? '') ?></td>
      <td class="sub"><?= e(guides_cover_name($t['coverSlug'] ?? null)) ?></td>
      <td class="sub"><?= ($t['source'] ?? '') === 'ai' ? 'Planned by AI' : 'Starter list' ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>

<h2 class="guides-h2">Published guides</h2>
<div class="table-scroll"><table class="grid small">
  <thead><tr><th>Published</th><th>Guide</th><th>Words</th><th>On the website</th></tr></thead>
  <tbody>
  <?php if (!$g['published']): ?><tr><td colspan="4" class="empty">No guides found.</td></tr><?php endif; ?>
  <?php foreach ($g['published'] as $p): ?>
    <tr>
      <td style="white-space:nowrap"><?= e(guides_uk($p['publishedAt'] ?? $p['date'] ?? '', 'D j M Y')) ?></td>
      <td><a href="<?= e($p['url']) ?>" target="_blank" rel="noopener"><?= e($p['title'] ?? $p['slug']) ?></a></td>
      <td class="sub"><?= !empty($p['wordCount']) ? number_format((int)$p['wordCount']) : '—' ?></td>
      <td><?php if (!array_key_exists('live', $p)): ?><span class="sub">—</span><?php elseif ($p['live']): ?><span class="pill ok">Live</span><?php else: ?><span class="pill run">Not live yet</span><?php endif; ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>

<?php if ($g['skipped']): ?>
<details class="mt">
  <summary class="sub">Skipped topics (<?= count($g['skipped']) ?>)</summary>
  <table class="grid small mt">
    <thead><tr><th>Topic</th><th>Why</th></tr></thead>
    <tbody>
    <?php foreach ($g['skipped'] as $t): ?><tr><td><?= e($t['title'] ?? '') ?></td><td class="sub"><?= e($t['note'] ?? 'Too similar to an existing guide') ?></td></tr><?php endforeach; ?>
    </tbody>
  </table>
</details>
<?php endif; ?>
<?php layout_footer();
