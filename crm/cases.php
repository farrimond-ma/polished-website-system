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

$stage = (string)param('stage', 'open');
$q = trim((string)param('q', ''));
$due = (string)param('due', '');

// On a case, Won is the normal state — they are a client. Only these mean the case is over.
$finished = ['Lost', 'Not Proceeding', 'Closed'];
$live = 'status NOT IN (' . implode(',', array_map(fn($s) => $pdo->quote($s), $finished)) . ')';

// A renewal list is read by where the questionnaire has got to, not by the sales pipeline.
$counts = ['open' => (int)$pdo->query("SELECT COUNT(*) FROM leads WHERE is_case = 1 AND $live")->fetchColumn()];
foreach (array_keys(case_stages()) as $key) {
    $counts[$key] = (int)$pdo->query("SELECT COUNT(*) FROM leads WHERE is_case = 1 AND $live AND (" . case_stage_sql($key) . ')')->fetchColumn();
}
$counts['all'] = (int)$pdo->query('SELECT COUNT(*) FROM leads WHERE is_case = 1')->fetchColumn();

$where = ['is_case = 1']; $args = [];
if ($stage !== 'all') $where[] = $live;
if (isset(case_stages()[$stage])) $where[] = '(' . case_stage_sql($stage) . ')';
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

$chip = function (string $key, string $label) use ($stage, $q, $counts, $due) {
    $active = $stage === $key ? ' active' : '';
    $href = 'cases.php?' . http_build_query(array_filter(['stage' => $key, 'q' => $q, 'due' => $due]));
    return "<a class='stage-chip$active' href='" . e($href) . "'>" . e($label) . '<span>' . (int)($counts[$key] ?? 0) . '</span></a>';
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
  <?= $chip('open', 'All live') ?>
  <?php foreach (case_stages() as $key => $label) echo $chip($key, $label); ?>
  <?= $chip('all', 'Everything') ?>
</div>
<form class="filters" method="get">
  <input type="hidden" name="stage" value="<?= e($stage) ?>">
  <input name="q" value="<?= e($q) ?>" placeholder="Search name, business, email, phone or POL-0001">
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
      <td><span class="pill <?= e(case_stage_class(case_stage($l))) ?>"><?= e(case_stage_short(case_stage($l))) ?></span></td>
      <td><?= e(user_name($l['assigned_to'] ? (int)$l['assigned_to'] : null)) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<p class="count"><?= count($cases) ?> shown<?= count($cases) === 500 ? ' (first 500 — refine your search)' : '' ?></p>
<?php layout_footer();
