<?php
/**
 * First run only: creates the first admin account. Does nothing once any user exists,
 * so it is safe to leave deployed (add further staff from the Users page).
 */
require __DIR__ . '/lib.php';
if ((int)db()->query('SELECT COUNT(*) FROM app_user')->fetchColumn() > 0) {
    flash('Setup has already been completed — please sign in.');
    redirect('login.php');
}
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = trim((string)post('username', ''));
    $pw = (string)post('password', '');
    if (!preg_match('/^[a-z0-9._-]{3,40}$/i', $username)) $err = 'Username: 3-40 letters, numbers, dots, dashes or underscores.';
    elseif (strlen($pw) < 10) $err = 'Password must be at least 10 characters.';
    else {
        db()->prepare('INSERT INTO app_user (username, display_name, email, password_hash, role, created_at) VALUES (?,?,?,?,?,?)')
            ->execute([$username, trim((string)post('display_name', '')), trim((string)post('email', '')), password_hash($pw, PASSWORD_DEFAULT), 'admin', now()]);
        try_login($username, $pw);
        flash('Welcome! Your admin account is ready.');
        redirect('index.php');
    }
}
layout_header('First-time setup', 'is-login');
?>
<div class="login">
  <h1>Create the first admin</h1>
  <p class="hint">This page only works while there are no users yet.</p>
  <?php if ($err) echo "<div class='err'>" . e($err) . "</div>"; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label>Your name<input name="display_name" required value="<?= e(post('display_name', '')) ?>"></label>
    <label>Email<input name="email" type="email" value="<?= e(post('email', '')) ?>"></label>
    <label>Username<input name="username" required value="<?= e(post('username', '')) ?>"></label>
    <label>Password (10+ characters)<input name="password" type="password" required autocomplete="new-password"></label>
    <button type="submit">Create admin</button>
  </form>
</div>
<?php layout_footer();
