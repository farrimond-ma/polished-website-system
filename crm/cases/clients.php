<?php
require __DIR__ . '/lib.php';
require_login();
$q = trim((string)param('q',''));
$sql = "SELECT cl.*, (SELECT COUNT(*) FROM case_policy c WHERE c.client_ref=cl.client_ref) AS cases
        FROM client cl WHERE 1=1";
$args = [];
if ($q !== '') { $sql .= " AND (cl.client_ref LIKE ? OR cl.trading_name LIKE ? OR cl.joint_names LIKE ? OR cl.first_name LIKE ? OR cl.last_name LIKE ? OR cl.postcode LIKE ?)";
    $like="%$q%"; array_push($args,$like,$like,$like,$like,$like,$like); }
$sql .= " ORDER BY cl.client_ref";
$st = db()->prepare($sql); $st->execute($args); $rows = $st->fetchAll();
function cn(array $r): string {
    if (!empty($r['joint_names'])) return $r['joint_names'];
    $n = trim(($r['title']??'').' '.($r['first_name']??'').' '.($r['last_name']??''));
    return $n !== '' ? $n : ($r['trading_name'] ?? $r['client_ref']);
}
cases_header('Clients');
?>
<div class="page-head"><h1>Clients</h1><a class="btn" href="client_edit.php">+ New client</a></div>
<form class="filters" method="get">
  <input name="q" value="<?= e($q) ?>" placeholder="Ref, name, trading name, postcode…">
  <button type="submit">Search</button>
</form>
<table class="grid">
  <thead><tr><th>Ref</th><th>Name</th><th>Trading as</th><th>Postcode</th><th class="r">Cases</th></tr></thead>
  <tbody>
  <?php if(!$rows): ?><tr><td colspan="5" class="empty">No clients found.</td></tr>
  <?php else: foreach($rows as $r): ?>
    <tr onclick="location='client.php?ref=<?= e($r['client_ref']) ?>'">
      <td class="mono"><?= e($r['client_ref']) ?></td>
      <td><strong><?= e(cn($r)) ?></strong></td>
      <td><?= e($r['trading_name'] ?: '—') ?></td>
      <td><?= e($r['postcode'] ?: '—') ?></td>
      <td class="r"><?= (int)$r['cases'] ?></td>
    </tr>
  <?php endforeach; endif; ?>
  </tbody>
</table>
<?php cases_footer();
