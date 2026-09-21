<?php
/**
 * "Forgot your password?" — step one.
 *
 * Asks for a username (an email address works too) and, if that account exists and has an email
 * address on it, emails a link that sets a new password. The same message is shown either way, so
 * this page cannot be used to find out whether a username exists.
 */
require __DIR__ . '/lib.php';
if (current_user()) redirect('index.php');

$done = false;
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $who = trim((string)post('username', ''));
    if ($who === '') {
        $err = 'Please enter your username.';
    } else {
        $st = db()->prepare('SELECT * FROM app_user WHERE LOWER(username) = LOWER(?) OR (email <> \'\' AND LOWER(email) = LOWER(?)) LIMIT 1');
        $st->execute([$who, $who]);
        $user = $st->fetch();

        if ($user && trim((string)$user['email']) !== '') {
            $token = bin2hex(random_bytes(32));
            db()->prepare('UPDATE app_user SET reset_token = ?, reset_expires = ? WHERE user_id = ?')
                ->execute([hash('sha256', $token), date('Y-m-d H:i:s', time() + 3600), $user['user_id']]);

            $link = rtrim((string)cfg('crm_base_url', ''), '/') . '/reset.php?token=' . $token;
            $name = (string)($user['display_name'] ?: $user['username']);
            $text = "Hello $name,\n\nSomeone asked to reset the password for your Polished Insurance CRM account "
                . "({$user['username']}). To choose a new one, open this link within the next hour:\n\n$link\n\n"
                . "If that was not you, ignore this email — your password has not changed.";
            $html = msg_email_wrap(
                '<p style="margin:0 0 16px">Hello ' . e($name) . ',</p>'
                . '<p style="margin:0 0 16px">Someone asked to reset the password for your Polished Insurance CRM account ('
                . e((string)$user['username']) . '). To choose a new one, open this link within the next hour:</p>'
                . '<p style="margin:0 0 16px"><a href="' . e($link) . '">' . e($link) . '</a></p>'
                . '<p style="margin:0 0 16px">If that was not you, ignore this email — your password has not changed.</p>');
            $r = send_email((string)$user['email'], $name, 'Reset your Polished Insurance CRM password', $html, $text);
            if (empty($r['ok'])) error_log('Polished CRM (password reset): ' . (string)($r['error'] ?? 'unknown error'));
        }
        $done = true;   // the same answer whether or not that account exists
    }
}

layout_header('Forgot your password', 'is-login');
?>
<div class="login">
  <h1>Forgot your password</h1>
  <?php if ($done): ?>
    <p>If that account exists and has an email address on it, a link to set a new password is on its way.
      It works once and lasts an hour.</p>
    <p class="hint">Nothing arrived? Check your junk folder, or ask a colleague to reset it for you on the Users page.</p>
    <p class="hint"><a href="login.php">Back to sign in</a></p>
  <?php else: ?>
    <?php if ($err) echo "<div class='err'>" . e($err) . '</div>'; ?>
    <p class="hint">Enter your username and we will email you a link to set a new password.</p>
    <form method="post">
      <?= csrf_field() ?>
      <label>Username<input name="username" autofocus required autocomplete="username"></label>
      <button type="submit">Email me a link</button>
    </form>
    <p class="hint"><a href="login.php">Back to sign in</a></p>
  <?php endif; ?>
</div>
<?php layout_footer();
