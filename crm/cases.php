<?php
/**
 * Cases — clients who already have a policy with us.
 *
 * The same list as Leads, and the same record behind it: open a case and you get the questionnaire,
 * the reminders, notes and tasks exactly as on a lead, plus its policy. A record becomes a case when
 * it is imported from Acturis or when a lead is marked Won.
 */
require __DIR__ . '/lib.php';
require_login();
$pdo = db();

$status = (string)param('status', 'open');
$q = trim((string)param('q', ''));
$qs = (string)param('qs', '');
$due = (string)param('due', '');

// On a case, Won is the normal state — they are a client. Only these mean the case is over.
$finished = ['Lost', 'Not Proceeding', 'Closed'];

$counts = [];
foreach ($pdo->query('SELECT status, COUNT(*) c FROM leads WHERE is_case = 1 GROUP BY status') as $r) $counts[$r['status']] = (int)$r['c'];
$openCount = 0;
foreach ($counts as $s => $c) if (!in_array($s, $finished, true)) $openCount += $c;

$where = ['is_case = 1']; $args = [];
if ($status === 'open') {
    $where[] = 'status NOT IN (' . implode(',', array_fill(0, count($finished), '?')) . ')';
    array_push($args, ...$finished);
} elseif ($status !== 'all' && in_array($status, case_statuses(), true)) {
    $where[] = 'status = ?'; $args[] = $status;
}
if (in_array($qs, ['not_started', 'in_progress', 'submitted'], true)) { $where[] = 'q_status = ?'; $args[] = $qs; }
if ($due === 'soon') { $where[] = 'renewal_date IS NOT NULL AND renewal_date <= ?'; $args[] = date('Y-m-d', strtotime('+60 days')); }
if ($q !== '') {
    $id = parse_ref_search($q);
    $like = '%' . $q . '%';
    $where[] = '(lead_id = ? OR first_name LIKE ? OR last_name LIKE ? OR company_name LIKE ? OR email LIKE ? OR phone LIKE ?)';
    array_push($args, $id, $like, $like, $like, $like, $like);
}
// Whoever renews soonest is the one to chase, so those come first.
$sql = 'SELECT * FROM leads WHERE ' . implode(' AND ', $where)
     . ' ORDER BY renewal_date IS NULL, renewal_date, lead_id DESC LIMIT 500';
$st = $pdo->prepare($sql);
$st->execute($args);
$cases = $st->fetchAll();

$dueSoon = (int)$pdo->query('SELECT COUNT(*) FROM leads WHERE is_case = 1 AND renewal_date IS NOT NULL AND renewal_date <= '
    . $pdo->quote(date('Y-m-d', strtotime('+60 days'))))->fetchColumn();

$chip = function (string $key, string $label, int $n) use ($status, $q, $qs) {
    $active = $status === $key ? ' active' : '';
    $href = 'cases.php?' . http_build_query(array_filter(['status' => $key, 'q' => $q, 'qs' => $qs]));
    return "<a class='stage-chip$active' href='" . e($href) . "'>" . e($label) . "<span>$n</span></a>";
};
$today = date('Y-m-d');

layout_header('Cases');
?>
<div class="page-head">
  <div>
    <h1>Cases</h1>
    <div class="sub">Clients who already have a policy with us. New enquiries live under <a href="leads.php">Leads</a>.</div>
  </div>
  <div class="btn-row">
    <a class="btn" href="renewals.php">Import from Acturis</a>
    <?php if (is_admin()): ?><a class="btn ghost" href="cases/index.php">Policies &amp; documents</a><?php endif; ?>
  </div>
</div>
<div class="stage-strip">
  <?= $chip('open', 'All live', $openCount) ?>
  <?php foreach (case_statuses() as $s) echo $chip($s, $s, $counts[$s] ?? 0); ?>
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
  <select name="due" onchange="this.form.submit()">
    <option value="">Renewal: any date</option>
    <option value="soon" <?= $due === 'soon' ? 'selected' : '' ?>>Renewal: within 60 days (<?= $dueSoon ?>)</option>
  </select>
  <button type="submit">Search</button>
</form>
<table class="grid">
  <thead><tr><th>Ref</th><th>Client</th><th>Business</th><th>Renewal</th><th>Status</th><th>Questionnaire</th><th>Assigned</th></tr></thead>
  <tbody>
  <?php if (!$cases): ?>
    <tr><td colspan="7" class="empty">No cases match. Import a client from Acturis, or mark a lead as Won.</td></tr>
  <?php endif; ?>
  <?php foreach ($cases as $l): ?>
    <tr onclick="location.href='lead.php?id=<?= (int)$l['lead_id'] ?>'">
      <td class="mono"><?= e(lead_ref((int)$l['lead_id'])) ?></td>
      <td><strong><?= e(trim($l['first_name'] . ' ' . $l['last_name'])) ?></strong><div class="sub"><?= e($l['email']) ?></div></td>
      <td><?= e($l['company_name']) ?></td>
      <td class="<?= $l['renewal_date'] && $l['renewal_date'] < $today ? 'overdue' : '' ?>" style="white-space:nowrap"><?= d($l['renewal_date']) ?></td>
      <td><span class="pill <?= status_class($l['status']) ?>"><?= e($l['status']) ?></span><?= $l['chasing'] ? " <span class='pill chasing'>Chasing " . (int)$l['auto_chase_count'] . "/3</span>" : '' ?></td>
      <td><span class="pill q-<?= e($l['q_status']) ?>"><?= e(q_status_label($l['q_status'])) ?></span></td>
      <td><?= e(user_name($l['assigned_to'] ? (int)$l['assigned_to'] : null)) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<p class="count"><?= count($cases) ?> shown<?= count($cases) === 500 ? ' (first 500 — refine your search)' : '' ?></p>
<?php layout_footer();
