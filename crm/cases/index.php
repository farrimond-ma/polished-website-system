<?php
require __DIR__ . '/lib.php';
require_login();

$q      = trim((string)param('q', ''));
$status = trim((string)param('status', ''));

$sql = "SELECT c.case_id, c.status, c.created_at, c.insurer_policy_number,
               cl.client_ref, cl.title, cl.first_name, cl.last_name, cl.joint_names, cl.trading_name, cl.postcode,
               s.policy_no_prefix, s.policy_no_suffix,
               t.transaction_type, t.sequence_label, t.inception_date, t.expiry_date, t.total_premium, t.balance
        FROM case_policy c
        JOIN client cl ON cl.client_ref = c.client_ref
        JOIN scheme s  ON s.scheme_id = c.scheme_id
        LEFT JOIN policy_term t
               ON t.term_id = (SELECT MAX(t2.term_id) FROM policy_term t2 WHERE t2.case_id = c.case_id)
        WHERE 1=1";
$args = [];
if ($q !== '') {
    $sql .= " AND (CAST(c.case_id AS CHAR) LIKE ? OR cl.trading_name LIKE ? OR cl.joint_names LIKE ?
                   OR cl.first_name LIKE ? OR cl.last_name LIKE ? OR cl.client_ref LIKE ? OR cl.postcode LIKE ?
                   OR c.insurer_policy_number LIKE ?)";
    $like = "%$q%";
    array_push($args, $like, $like, $like, $like, $like, $like, $like, $like);
}
if ($status !== '') { $sql .= " AND c.status = ?"; $args[] = $status; }
$sql .= " ORDER BY c.created_at DESC";

// CAST(... AS CHAR) is MySQL; SQLite wants no CAST. Swap for sqlite.
if ((cfg('driver', 'mysql') ?? 'mysql') === 'sqlite') {
    $sql = str_replace('CAST(c.case_id AS CHAR)', 'c.case_id', $sql);
}
$st = db()->prepare($sql);
$st->execute($args);
$rows = $st->fetchAll();

function client_name(array $r): string {
    if (!empty($r['joint_names'])) return $r['joint_names'];
    $n = trim(($r['title'] ?? '') . ' ' . ($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
    return $n !== '' ? $n : ($r['trading_name'] ?? $r['client_ref']);
}
function policy_no(array $r): string {
    $mid = ($r['insurer_policy_number'] ?? '') !== '' ? $r['insurer_policy_number'] : $r['case_id'];
    return ($r['policy_no_prefix'] ?? 'ZCLP') . '/' . $mid . '/' . ($r['policy_no_suffix'] ?? '1');
}
$statuses = db()->query("SELECT DISTINCT status FROM case_policy ORDER BY status")->fetchAll(PDO::FETCH_COLUMN);

cases_header('Cases');
?>
<div class="page-head">
  <h1>Cases</h1>
  <a class="btn" href="case_edit.php">+ New case</a>
</div>
<form class="filters" method="get">
  <input name="q" value="<?= e($q) ?>" placeholder="Case ID, policy no, client, trading name, postcode…">
  <select name="status">
    <option value="">All statuses</option>
    <?php foreach ($statuses as $s): ?>
      <option value="<?= e($s) ?>" <?= $s === $status ? 'selected' : '' ?>><?= e($s) ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit">Search</button>
</form>

<table class="grid">
  <thead><tr><th>Case</th><th>Policy no</th><th>Client</th><th>Status</th><th>Transaction</th><th>Inception</th><th>Expiry</th><th class="r">Premium</th><th class="r">Balance</th></tr></thead>
  <tbody>
  <?php if (!$rows): ?>
    <tr><td colspan="9" class="empty">No cases found.</td></tr>
  <?php else: foreach ($rows as $r): ?>
    <tr onclick="location='case.php?id=<?= (int)$r['case_id'] ?>'">
      <td class="mono"><?= (int)$r['case_id'] ?></td>
      <td class="mono"><?= e(policy_no($r)) ?></td>
      <td><strong><?= e(client_name($r)) ?></strong><?= $r['trading_name'] ? "<br><span class='sub'>t/as " . e($r['trading_name']) . "</span>" : '' ?></td>
      <td><span class="pill <?= e(strtolower(str_replace(' ','',$r['status']))) ?>"><?= e($r['status']) ?></span></td>
      <td><?= e($r['sequence_label'] ?: $r['transaction_type'] ?: '—') ?></td>
      <td><?= d($r['inception_date']) ?></td>
      <td><?= d($r['expiry_date']) ?></td>
      <td class="r"><?= money($r['total_premium']) ?></td>
      <td class="r"><?= money($r['balance']) ?></td>
    </tr>
  <?php endforeach; endif; ?>
  </tbody>
</table>
<p class="count"><?= count($rows) ?> case(s)</p>
<?php cases_footer();
