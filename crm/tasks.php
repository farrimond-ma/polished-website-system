<?php
require __DIR__ . '/lib.php';
require_login();
$pdo = db();
$me = (int)current_user()['user_id'];
$view = param('view', 'mine') === 'all' ? 'all' : 'mine';
$show = param('show', 'open') === 'done' ? 'done' : 'open';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (post('action') === 'done') {
        $pdo->prepare('UPDATE lead_task SET done_at = ? WHERE task_id = ?')->execute([now(), (int)post('task_id')]);
        flash('Task completed.');
    } elseif (post('action') === 'add' && trim((string)post('body', '')) !== '' && post('due_date')) {
        $pdo->prepare('INSERT INTO lead_task (lead_id, body, due_date, assigned_to, created_by, created_at) VALUES (NULL,?,?,?,?,?)')
            ->execute([mb_substr(trim((string)post('body')), 0, 500), post('due_date'), (int)post('assigned_to') ?: null, $me, now()]);
        flash('Task added.');
    }
    redirect('tasks.php?' . http_build_query(['view' => $view, 'show' => $show]));
}

$where = [$show === 'open' ? 't.done_at IS NULL' : 't.done_at IS NOT NULL'];
$args = [];
if ($view === 'mine') { $where[] = '(t.assigned_to = ? OR t.assigned_to IS NULL)'; $args[] = $me; }
$st = $pdo->prepare('SELECT t.*, l.first_name, l.last_name, l.company_name FROM lead_task t LEFT JOIN leads l ON l.lead_id = t.lead_id
    WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . ($show === 'open' ? 't.due_date ASC' : 't.done_at DESC') . ' LIMIT 300');
$st->execute($args);
$tasks = $st->fetchAll();
$today = date('Y-m-d');

layout_header('Tasks');
?>
<div class="page-head">
  <h1>Tasks</h1>
  <div class="task-view-toggle">
    <a class="btn ghost small <?= $view === 'mine' ? 'active' : '' ?>" href="?view=mine&amp;show=<?= $show ?>">Mine</a>
    <a class="btn ghost small <?= $view === 'all' ? 'active' : '' ?>" href="?view=all&amp;show=<?= $show ?>">Everyone</a>
    <a class="btn ghost small <?= $show === 'open' ? 'active' : '' ?>" href="?view=<?= $view ?>&amp;show=open">Open</a>
    <a class="btn ghost small <?= $show === 'done' ? 'active' : '' ?>" href="?view=<?= $view ?>&amp;show=done">Done</a>
  </div>
</div>
<form method="post" class="task-form card"><?= csrf_field() ?><input type="hidden" name="action" value="add">
  <input name="body" placeholder="New task (not linked to a lead)" required>
  <input type="date" name="due_date" value="<?= e($today) ?>" required>
  <select name="assigned_to"><?php foreach (users() as $u): ?><option value="<?= (int)$u['user_id'] ?>" <?= (int)$u['user_id'] === $me ? 'selected' : '' ?>><?= e($u['display_name'] ?: $u['username']) ?></option><?php endforeach; ?></select>
  <button class="btn small">Add</button>
</form>
<table class="grid">
  <thead><tr><th>Due</th><th>Task</th><th>Lead</th><th>Assigned</th><th></th></tr></thead>
  <tbody>
  <?php if (!$tasks): ?><tr><td colspan="5" class="empty">No tasks.</td></tr><?php endif; ?>
  <?php foreach ($tasks as $t): ?>
    <tr>
      <td class="<?= !$t['done_at'] && $t['due_date'] < $today ? 'overdue' : '' ?>"><?= d($t['due_date']) ?></td>
      <td><?= e($t['body']) ?></td>
      <td><?= $t['lead_id'] ? "<a href='lead.php?id=" . (int)$t['lead_id'] . "'>" . e(lead_ref((int)$t['lead_id']) . ' ' . trim($t['first_name'] . ' ' . $t['last_name'])) . '</a>' : '—' ?></td>
      <td><?= e(user_name($t['assigned_to'] ? (int)$t['assigned_to'] : null)) ?></td>
      <td class="r"><?php if (!$t['done_at']): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="done"><input type="hidden" name="task_id" value="<?= (int)$t['task_id'] ?>"><button class="btn ghost small">Done</button></form><?php else: ?><span class="sub"><?= dt($t['done_at']) ?></span><?php endif; ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php layout_footer();
