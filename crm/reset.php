<?php
/**
 * "Forgot your password?" — step two: the link from the email.
 *
 * The token in the link is compared against its stored hash and must still be within the hour. It
 * works once: setting the password clears it, so the same link cannot be used again.
 */
require __DIR__ . '/lib.php';
if (current_user()) redirect('index.php');

$token = (string)param('token', post('token', ''));
$user = null;
if (preg_match('/^[a-f0-9]{64}$/', $token)) {
    $st = db()->prepare('SELECT * FROM app_user WHERE reset_token = ? LIMIT 1');
    $st->execute([hash('sha256', $token)]);
    $found = $st->fetch();
    if ($found && !empty($found['reset_expires']) && strtotime((string)$found['reset_expires']) > time()) $user = $found;
}

$err = '';
if ($user && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $new = (string)post('new_password', '');
    if (strlen($new) < 10) $err = 'Your new password must be at least 10 characters.';
    elseif ($new !== (string)post('confirm_password', '')) $err = 'The two passwords do not match.';
    else {
        db()->prepare('UPDATE app_user SET password_hash = ?, reset_token = NULL, reset_expires = NULL WHERE user_id = ?')
            ->execute([password_hash($new, PASSWORD_DEFAULT), $user['user_id']]);
        flash('Your password has been changed — please sign in with it.');
        redirect('login.php');
    }
}

layout_header('Set a new password', 'is-login');
?>
<div class="login">
  <h1>Set a new password</h1>
  <?php if (!$user): ?>
    <div class="err">That link has expired or has already been used.</div>
    <p class="hint">Reset links last an hour and work once. <a href="forgot.php">Ask for a new one</a>.</p>
  <?php else: ?>
    <?php if ($err) echo "<div class='err'>" . e($err) . '</div>'; ?>
    <p class="hint">Signing in as <strong><?= e((string)$user['username']) ?></strong>. Use at least 10 characters.</p>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="token" value="<?= e($token) ?>">
      <label>New password<input name="new_password" type="password" autofocus required autocomplete="new-password"></label>
      <label>Confirm new password<input name="confirm_password" type="password" required autocomplete="new-password"></label>
      <button type="submit">Save my new password</button>
    </form>
  <?php endif; ?>
  <p class="hint"><a href="login.php">Back to sign in</a></p>
</div>
<?php layout_footer();
