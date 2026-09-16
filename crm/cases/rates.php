<?php
require __DIR__ . '/lib.php';
require_login();
$rows = db()->query("SELECT rt.*, p.part_name FROM rate_table rt JOIN cover_part_ref p ON p.part_code=rt.part_code ORDER BY rt.part_code, rt.pl_limit, rt.rate_id")->fetchAll();
$byPart = [];
foreach ($rows as $r) $byPart[$r['part_name']][] = $r;
cases_header('Rate tables');
?>
<div class="page-head"><h1>Rate tables</h1></div>
<p class="sub">Scheme reference rates. <strong>source</strong> shows where each figure came from — <em>Live 9676245</em> = confirmed on SchemeServe, <em>TBC</em> = still to confirm.</p>
<?php foreach ($byPart as $part => $rs): ?>
  <h3><?= e($part) ?></h3>
  <table class="grid">
    <thead><tr><th>Band / item</th><th>PL limit</th><th class="r">Rate</th><th class="r">Flat £</th><th>Basis</th><th>Source</th><th>Note</th></tr></thead>
    <tbody>
    <?php foreach ($rs as $r): ?>
      <tr class="<?= $r['source']==='TBC'?'tbc':'' ?>">
        <td><?= e($r['band_label']) ?></td>
        <td><?= $r['pl_limit']!==null?money($r['pl_limit']):'—' ?></td>
        <td class="r"><?= $r['rate_pct']!==null?pct($r['rate_pct']):'—' ?></td>
        <td class="r"><?= $r['flat_amount']!==null?money($r['flat_amount']):'—' ?></td>
        <td><?= e($r['exposure_basis'] ?: '—') ?></td>
        <td><span class="tag <?= $r['source']==='TBC'?'warn':'' ?>"><?= e($r['source']) ?></span></td>
        <td class="sub"><?= e($r['note'] ?: '') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endforeach; ?>
<?php cases_footer();
