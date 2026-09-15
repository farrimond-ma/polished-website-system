<?php
require __DIR__ . '/lib.php';
require_login();
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $u = current_user();
    $st = db()->prepare('SELECT password_hash FROM app_user WHERE user_id = ?');
    $st->execute([$u['user_id']]);
    $new = (string)post('new_password', '');
    if (!password_verify((string)post('current_password', ''), (string)$st->fetchColumn())) $err = 'Your current password is incorrect.';
    elseif (strlen($new) < 10) $err = 'New password must be at least 10 characters.';
    elseif ($new !== (string)post('confirm_password', '')) $err = 'The new passwords do not match.';
    else {
        db()->prepare('UPDATE app_user SET password_hash = ? WHERE user_id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $u['user_id']]);
        flash('Password changed.');
        redirect('index.php');
    }
}
layout_header('Change password');
?>
<div class="login">
  <h1>Change password</h1>
  <?php if ($err) echo "<div class='err'>" . e($err) . "</div>"; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label>Current password<input name="current_password" type="password" required autocomplete="current-password"></label>
    <label>New password (10+ characters)<input name="new_password" type="password" required autocomplete="new-password"></label>
    <label>Confirm new password<input name="confirm_password" type="password" required autocomplete="new-password"></label>
    <button type="submit">Save</button>
  </form>
</div>
<?php layout_footer();
