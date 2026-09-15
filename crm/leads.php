<?php
require __DIR__ . '/lib.php';
require_login();
$pdo = db();

$status = (string)param('status', 'open');
$q = trim((string)param('q', ''));
$qs = (string)param('qs', '');

$counts = [];
foreach ($pdo->query('SELECT status, COUNT(*) c FROM lead GROUP BY status') as $r) $counts[$r['status']] = (int)$r['c'];
$openCount = 0;
foreach ($counts as $s => $c) if (!in_array($s, terminal_statuses(), true)) $openCount += $c;

$where = []; $args = [];
if ($status === 'open') {
    $where[] = 'status NOT IN (' . implode(',', array_fill(0, count(terminal_statuses()), '?')) . ')';
    array_push($args, ...terminal_statuses());
} elseif ($status !== 'all' && in_array($status, statuses(), true)) {
    $where[] = 'status = ?'; $args[] = $status;
}
if (in_array($qs, ['not_started', 'in_progress', 'submitted'], true)) { $where[] = 'q_status = ?'; $args[] = $qs; }
if ($q !== '') {
    $id = parse_ref_search($q);
    $like = '%' . $q . '%';
    $where[] = '(lead_id = ? OR first_name LIKE ? OR last_name LIKE ? OR company_name LIKE ? OR email LIKE ? OR phone LIKE ?)';
    array_push($args, $id, $like, $like, $like, $like, $like);
}
$sql = 'SELECT * FROM lead' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY lead_id DESC LIMIT 500';
$st = $pdo->prepare($sql);
$st->execute($args);
$leads = $st->fetchAll();

$chip = function (string $key, string $label, int $n) use ($status, $q, $qs) {
    $active = $status === $key ? ' active' : '';
    $href = 'leads.php?' . http_build_query(array_filter(['status' => $key, 'q' => $q, 'qs' => $qs]));
    return "<a class='stage-chip$active' href='" . e($href) . "'>" . e($label) . "<span>$n</span></a>";
};

layout_header('Leads');
?>
<div class="page-head">
  <h1>Leads</h1>
  <a class="btn" href="lead_edit.php">+ Add lead</a>
</div>
<div class="stage-strip">
  <?= $chip('open', 'All open', $openCount) ?>
  <?php foreach (statuses() as $s) echo $chip($s, $s, $counts[$s] ?? 0); ?>
  <?= $chip('all', 'Everything', array_sum($counts)) ?>
</div>
<form class="filters" method="get">
  <input type="hidden" name="status" value="<?= e($status) ?>">
  <input name="q" value="<?= e($q) ?>" placeholder="Search name, business, email, phone or POL-0001">
  <select name="qs" onchange="this.form.submit()">
    <option value="">Questionnaire: any</option>
    <?php foreach (['not_started', 'in_progress', 'submitted'] as $o): ?>
      <option value="<?= $o ?>" <?= $qs === $o ? 'selected' : '' ?>>Questionnaire: <?= e(q_status_label($o)) ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit">Search</button>
</form>
<table class="grid">
  <thead><tr><th>Ref</th><th>Client</th><th>Business</th><th>Status</th><th>Questionnaire</th><th>Assigned</th><th>Received</th></tr></thead>
  <tbody>
  <?php if (!$leads): ?><tr><td colspan="7" class="empty">No leads match.</td></tr><?php endif; ?>
  <?php foreach ($leads as $l): ?>
    <tr onclick="location.href='lead.php?id=<?= (int)$l['lead_id'] ?>'">
      <td class="mono"><?= e(lead_ref((int)$l['lead_id'])) ?></td>
      <td><strong><?= e(trim($l['first_name'] . ' ' . $l['last_name'])) ?></strong><div class="sub"><?= e($l['email']) ?></div></td>
      <td><?= e($l['company_name']) ?></td>
      <td><span class="pill <?= status_class($l['status']) ?>"><?= e($l['status']) ?></span><?= $l['chasing'] ? " <span class='pill chasing'>Chasing " . (int)$l['auto_chase_count'] . "/3</span>" : '' ?></td>
      <td><span class="pill q-<?= e($l['q_status']) ?>"><?= e(q_status_label($l['q_status'])) ?></span></td>
      <td><?= e(user_name($l['assigned_to'] ? (int)$l['assigned_to'] : null)) ?></td>
      <td><?= dt($l['created_at']) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<p class="count"><?= count($leads) ?> shown<?= count($leads) === 500 ? ' (first 500 — refine your search)' : '' ?></p>
<?php layout_footer();
