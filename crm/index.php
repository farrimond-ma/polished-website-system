<?php
require __DIR__ . '/lib.php';
require_login();
$pdo = db();
$me = (int)current_user()['user_id'];
$today = date('Y-m-d');

$stat = fn(string $sql, array $a = []) => (function () use ($pdo, $sql, $a) { $s = $pdo->prepare($sql); $s->execute($a); return (int)$s->fetchColumn(); })();
$newThisWeek = $stat('SELECT COUNT(*) FROM lead WHERE created_at >= ?', [date('Y-m-d 00:00:00', strtotime('monday this week'))]);
$chasing = $stat('SELECT COUNT(*) FROM lead WHERE chasing = 1');
$inProgress = $stat("SELECT COUNT(*) FROM lead WHERE q_status = 'in_progress'");
$completed30 = $stat("SELECT COUNT(*) FROM lead WHERE q_status = 'submitted' AND q_submitted_at >= ?", [date('Y-m-d H:i:s', strtotime('-30 days'))]);

$attention = needs_attention_leads();

$st = $pdo->prepare('SELECT t.*, l.first_name, l.last_name, l.company_name FROM lead_task t LEFT JOIN lead l ON l.lead_id = t.lead_id
    WHERE t.done_at IS NULL AND t.due_date <= ? AND (t.assigned_to = ? OR t.assigned_to IS NULL) ORDER BY t.due_date LIMIT 30');
$st->execute([$today, $me]);
$tasks = $st->fetchAll();

$recent = $pdo->query("SELECT n.*, l.first_name, l.last_name, l.company_name FROM lead_note n JOIN lead l ON l.lead_id = n.lead_id
    WHERE n.created_by IS NULL ORDER BY n.note_id DESC LIMIT 12")->fetchAll();

layout_header('Dashboard');
?>
<div class="page-head"><h1>Dashboard</h1><a class="btn" href="lead_edit.php">+ Add lead</a></div>
<div class="kpis">
  <a class="kpi" href="leads.php?status=all"><strong><?= $newThisWeek ?></strong><span>New leads this week</span></a>
  <a class="kpi" href="leads.php?status=Questionnaire+Sent"><strong><?= $chasing ?></strong><span>Being chased automatically</span></a>
  <a class="kpi" href="leads.php?status=all&amp;qs=in_progress"><strong><?= $inProgress ?></strong><span>Questionnaires in progress</span></a>
  <a class="kpi" href="leads.php?status=all&amp;qs=submitted"><strong><?= $completed30 ?></strong><span>Completed (30 days)</span></a>
</div>
<div class="cols">
  <div class="col">
    <div class="card">
      <h2>Needs attention</h2>
      <p class="hint">New enquiries not yet being chased, completed questionnaires ready to quote, and follow-ups that are due.</p>
      <table class="grid small">
        <thead><tr><th>Lead</th><th>Status</th><th>Follow-up</th></tr></thead>
        <tbody>
        <?php if (!$attention): ?><tr><td colspan="3" class="empty">Nothing needs attention right now.</td></tr><?php endif; ?>
        <?php foreach ($attention as $l): ?>
          <tr onclick="location.href='lead.php?id=<?= (int)$l['lead_id'] ?>'">
            <td><span class="mono"><?= e(lead_ref((int)$l['lead_id'])) ?></span> <?= e(lead_name($l)) ?><div class="sub"><?= e($l['company_name']) ?></div></td>
            <td><span class="pill <?= status_class($l['status']) ?>"><?= e($l['status']) ?></span></td>
            <td class="<?= $l['next_follow_up'] && $l['next_follow_up'] < $today ? 'overdue' : '' ?>"><?= d($l['next_follow_up']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="col">
    <div class="card">
      <h2>My tasks due</h2>
      <?php if (!$tasks): ?><p class="hint">No tasks due. <a href="tasks.php">All tasks</a></p><?php endif; ?>
      <?php foreach ($tasks as $t): ?>
        <div class="task-row">
          <div><strong class="<?= $t['due_date'] < $today ? 'overdue' : '' ?>"><?= d($t['due_date']) ?></strong> — <?= e($t['body']) ?>
            <?php if ($t['lead_id']): ?><div class="sub"><a href="lead.php?id=<?= (int)$t['lead_id'] ?>"><?= e(lead_ref((int)$t['lead_id'])) ?> <?= e(trim($t['first_name'] . ' ' . $t['last_name'])) ?></a></div><?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="card">
      <h2>Latest automatic activity</h2>
      <?php if (!$recent): ?><p class="hint">Nothing yet.</p><?php endif; ?>
      <?php foreach ($recent as $n): ?>
        <div class="note"><div class="note-meta"><?= dt($n['created_at']) ?> · <a href="lead.php?id=<?= (int)$n['lead_id'] ?>"><?= e(lead_ref((int)$n['lead_id'])) ?> <?= e(trim($n['first_name'] . ' ' . $n['last_name'])) ?></a></div><?= e($n['body']) ?></div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php layout_footer();
