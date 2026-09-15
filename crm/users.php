<?php
require __DIR__ . '/lib.php';
require_admin();
$pdo = db();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string)post('action', '');
    $targetId = (int)post('user_id', 0);
    $self = $targetId === (int)current_user()['user_id'];
    if ($action === 'add') {
        $username = trim((string)post('username', ''));
        $pw = (string)post('password', '');
        if (!preg_match('/^[a-z0-9._-]{3,40}$/i', $username)) flash('Username: 3-40 letters, numbers, dots, dashes or underscores.');
        elseif (strlen($pw) < 10) flash('Password must be at least 10 characters.');
        else {
            try {
                $pdo->prepare('INSERT INTO app_user (username, display_name, email, password_hash, role, created_at) VALUES (?,?,?,?,?,?)')
                    ->execute([$username, trim((string)post('display_name', '')), trim((string)post('email', '')),
                        password_hash($pw, PASSWORD_DEFAULT), post('role') === 'admin' ? 'admin' : 'staff', now()]);
                flash("User $username added.");
            } catch (PDOException $ex) {
                flash('That username is already taken.');
            }
        }
    } elseif ($action === 'reset') {
        $pw = (string)post('password', '');
        if (strlen($pw) < 10) flash('Password must be at least 10 characters.');
        else {
            $pdo->prepare('UPDATE app_user SET password_hash = ? WHERE user_id = ?')->execute([password_hash($pw, PASSWORD_DEFAULT), $targetId]);
            flash('Password reset.');
        }
    } elseif ($action === 'role') {
        if ($self) flash('You cannot change your own role.');
        else {
            $pdo->prepare('UPDATE app_user SET role = ? WHERE user_id = ?')->execute([post('role') === 'admin' ? 'admin' : 'staff', $targetId]);
            flash('Role updated.');
        }
    } elseif ($action === 'delete') {
        if ($self) flash('You cannot delete yourself.');
        else {
            $pdo->prepare('UPDATE leads SET assigned_to = NULL WHERE assigned_to = ?')->execute([$targetId]);
            $pdo->prepare('UPDATE lead_task SET assigned_to = NULL WHERE assigned_to = ?')->execute([$targetId]);
            $pdo->prepare('DELETE FROM app_user WHERE user_id = ?')->execute([$targetId]);
            flash('User deleted.');
        }
    }
    redirect('users.php');
}
layout_header('Users');
?>
<div class="page-head"><h1>Users</h1></div>
<table class="grid">
  <thead><tr><th>Name</th><th>Username</th><th>Email</th><th>Role</th><th>Actions</th></tr></thead>
  <tbody>
  <?php foreach (users() as $u): $uid = (int)$u['user_id']; ?>
    <tr>
      <td><?= e($u['display_name']) ?></td>
      <td class="mono"><?= e($u['username']) ?></td>
      <td><?= e($u['email']) ?></td>
      <td>
        <form method="post" class="inline-form"><?= csrf_field() ?>
          <input type="hidden" name="action" value="role"><input type="hidden" name="user_id" value="<?= $uid ?>">
          <select name="role" onchange="this.form.submit()">
            <option value="staff" <?= $u['role'] === 'staff' ? 'selected' : '' ?>>Staff</option>
            <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
          </select>
        </form>
      </td>
      <td>
        <form method="post" class="inline-form"><?= csrf_field() ?>
          <input type="hidden" name="action" value="reset"><input type="hidden" name="user_id" value="<?= $uid ?>">
          <input name="password" type="password" placeholder="New password" autocomplete="new-password">
          <button class="btn small ghost">Reset</button>
        </form>
        <form method="post" class="inline-form" onsubmit="return confirm('Delete this user?')"><?= csrf_field() ?>
          <input type="hidden" name="action" value="delete"><input type="hidden" name="user_id" value="<?= $uid ?>">
          <button class="btn small ghost danger">Delete</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<form method="post" class="form" style="margin-top:18px;max-width:760px">
  <?= csrf_field() ?><input type="hidden" name="action" value="add">
  <h2>Add a user</h2>
  <div class="grid2">
    <label>Name<input name="display_name" required></label>
    <label>Email<input name="email" type="email"></label>
    <label>Username<input name="username" required></label>
    <label>Password (10+ characters)<input name="password" type="password" required autocomplete="new-password"></label>
    <label>Role<select name="role"><option value="staff">Staff</option><option value="admin">Admin</option></select></label>
  </div>
  <div class="form-actions"><button type="submit">Add user</button></div>
</form>
<?php layout_footer();
