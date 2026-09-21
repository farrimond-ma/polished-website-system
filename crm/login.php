<?php
require __DIR__ . '/lib.php';
if (current_user()) redirect('index.php');
if ((int)db()->query('SELECT COUNT(*) FROM app_user')->fetchColumn() === 0) redirect('setup.php');
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (try_login(trim((string)post('username', '')), (string)post('password', ''))) redirect('index.php');
    $err = 'Incorrect username or password.';
}
layout_header('Sign in', 'is-login');
?>
<div class="login">
  <h1>Sign in</h1>
  <?php if ($err) echo "<div class='err'>" . e($err) . "</div>"; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label>Username<input name="username" autofocus required autocomplete="username"></label>
    <label>Password<input name="password" type="password" required autocomplete="current-password"></label>
    <button type="submit">Sign in</button>
  </form>
  <p class="hint"><a href="forgot.php">Forgot your password?</a></p>
  <p class="hint">Polished Insurance staff only.</p>
</div>
<?php layout_footer();
