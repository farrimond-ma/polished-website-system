<?php
/**
 * Shared bootstrap: config, PDO, session, helpers, auth, pipeline, chasing, email/SMS, layout.
 * Every page starts with: require __DIR__ . '/lib.php';
 *
 * Patterned on the Boxx CRM (send_email / send_sms / chase flow) but a separate app with
 * its own database, config and branding.
 */
declare(strict_types=1);

// All dates in this app are UK local time (SiteGround's PHP default is UTC).
date_default_timezone_set('Europe/London');

require_once __DIR__ . '/vendor/PHPMailer/Exception.php';
require_once __DIR__ . '/vendor/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/vendor/PHPMailer/SMTP.php';
require_once __DIR__ . '/inc/db_schema.php';
require_once __DIR__ . '/inc/questionnaire.php';
require_once __DIR__ . '/inc/messages.php';

// Any other unexpected error: log the detail, show a plain page instead of a blank 500.
set_exception_handler(function (\Throwable $ex) {
    error_log('Polished CRM unhandled ' . get_class($ex) . ': ' . $ex->getMessage() . ' in ' . basename($ex->getFile()) . ':' . $ex->getLine());
    if (!headers_sent()) http_response_code(500);
    if (defined('POLISHED_API')) { header('Content-Type: application/json'); echo json_encode(['ok' => false, 'error' => 'Something went wrong. Please try again or call us.']); return; }
    echo '<!doctype html><meta charset="utf-8"><title>Polished CRM error</title><div style="font-family:Arial,sans-serif;max-width:620px;margin:10vh auto;padding:24px;border:1px solid #e2e8ef;border-top:4px solid #b42323;border-radius:8px">'
        . '<h1 style="font-size:20px;margin:0 0 10px">Something went wrong</h1><p>Error type: ' . htmlspecialchars(get_class($ex)) . ' in '
        . htmlspecialchars(basename($ex->getFile())) . ' line ' . (int)$ex->getLine() . '. The full detail is in the php_errorlog file in the CRM folder.</p></div>';
});

if (PHP_SAPI !== 'cli' && !defined('POLISHED_API')) {
    session_set_cookie_params([
        'httponly' => true,
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
    ]);
    session_start();
}

/**
 * Stops with a plain explanation of a set-up problem (instead of a blank 500 page).
 * Never includes credentials; the full technical error goes to the PHP error log.
 */
function setup_problem(string $message): void {
    http_response_code(503);
    if (PHP_SAPI === 'cli') { fwrite(STDERR, "Polished CRM set-up problem: $message\n"); exit(1); }
    if (defined('POLISHED_API')) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'The service is temporarily unavailable. Please call us.']);
        exit;
    }
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><meta name="robots" content="noindex"><title>Polished CRM set-up</title>'
        . '<div style="font-family:Arial,sans-serif;max-width:620px;margin:10vh auto;padding:24px;border:1px solid #e2e8ef;border-top:4px solid #1664f0;border-radius:8px">'
        . '<h1 style="font-size:20px;margin:0 0 10px;color:#0a192f">The CRM is not set up yet</h1>'
        . '<p style="line-height:1.6;color:#1f2733">' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p></div>';
    exit;
}

function config(): array {
    static $cfg = null;
    if ($cfg === null) {
        $env = getenv('POLISHED_CONFIG'); // env override is for local testing
        $file = $env ?: __DIR__ . '/config.php';
        if (!is_file($file)) {
            setup_problem('config.php has not been uploaded. Copy your completed config.php into this folder (next to index.php) using SiteGround File Manager.');
        }
        try {
            $cfg = require $file;
        } catch (\ParseError $ex) {
            error_log('Polished CRM config.php parse error: ' . $ex->getMessage() . ' line ' . $ex->getLine());
            setup_problem('config.php has a typing error near line ' . $ex->getLine() . '. The most common cause is an apostrophe (\') or backslash (\\) inside a password: put a backslash before it (e.g. it\\\'s) or change the password. Also check every value is wrapped in single quotes and each line ends with a comma.');
        }
        if (!is_array($cfg)) setup_problem('config.php is not in the expected format. Start again from config.sample.php and fill in the values.');
        $unfilled = [];
        array_walk_recursive($cfg, function ($v, $k) use (&$unfilled) {
            if (is_string($v) && str_starts_with($v, 'ENTER_')) $unfilled[] = $k;
        });
        if ($unfilled) setup_problem('config.php still has placeholder values for: ' . implode(', ', $unfilled) . '. Replace each ENTER_… value and upload it again.');
    }
    return $cfg;
}

/** A config value, treating 'CHANGE_ME' and empty as "not set". */
function cfg(string $key, $default = null) {
    $v = config()[$key] ?? $default;
    if ($v === 'CHANGE_ME' || $v === '') return $default;
    return $v;
}

function db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    $c = config();
    $sqlite = ($c['driver'] ?? 'mysql') === 'sqlite';
    if ($sqlite) {
        $path = getenv('POLISHED_SQLITE') ?: $c['sqlite_path'];
        if (!is_dir(dirname($path))) mkdir(dirname($path), 0775, true);
        $pdo = new PDO('sqlite:' . $path);
    } else {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['host'], $c['port'] ?? 3306, $c['dbname']);
        try {
            $pdo = new PDO($dsn, $c['user'], $c['password']);
        } catch (PDOException $ex) {
            error_log('Polished CRM database connection failed: ' . $ex->getMessage());
            $code = (int)($ex->errorInfo[1] ?? 0) ?: (int)$ex->getCode();
            setup_problem(match ($code) {
                1045 => 'The database username or password in config.php is not accepted. Check them in Site Tools → MySQL → Users (and that the user has been added to the database).',
                1044 => 'The database user exists but has no access to this database. In Site Tools → MySQL → Databases, add the user to the database with All Privileges.',
                1049 => 'The database name in config.php does not exist. Copy the exact name from Site Tools → MySQL → Databases.',
                2002, 2003, 2005 => 'The database server could not be reached. The host in config.php should normally be localhost.',
                default => 'The CRM could not connect to its database (error ' . $code . '). Check the database settings in config.php.',
            });
        }
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    try {
        ensure_schema($pdo, $sqlite);
    } catch (PDOException $ex) {
        error_log('Polished CRM could not create database tables: ' . $ex->getMessage());
        $code = (int)($ex->errorInfo[1] ?? 0);
        setup_problem(match ($code) {
            1142, 1044, 1227 => 'The database user is not allowed to create the CRM tables. In Site Tools → MySQL → Databases, make sure the user is added to this database with All Privileges.',
            default => 'The database connected, but the CRM tables could not be created (database error ' . ($code ?: $ex->getCode()) . '). Send this number to your developer.',
        });
    }
    return $pdo;
}

function now(): string { return date('Y-m-d H:i:s'); }

/* ---------- helpers ---------- */
function e($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function d($v): string { if (!$v) return '—'; $t = strtotime((string)$v); return $t ? date('d M Y', $t) : e($v); }
function dt($v): string { if (!$v) return '—'; $t = strtotime((string)$v); return $t ? date('d M Y H:i', $t) : e($v); }
function param(string $k, $def = null) { return $_GET[$k] ?? $def; }
function post(string $k, $def = null) { return $_POST[$k] ?? $def; }
function redirect(string $url): void { header('Location: ' . $url); exit; }

/* ---------- CSRF ---------- */
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; }
function csrf_check(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $sent = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)$sent)) {
        http_response_code(400); exit('Invalid or expired form token — please go back, refresh and try again.');
    }
}

/* ---------- auth ---------- */
function current_user(): ?array { return $_SESSION['user'] ?? null; }
function require_login(): void {
    if (!current_user()) redirect('login.php');
}
function is_admin(): bool { return (current_user()['role'] ?? '') === 'admin'; }
function require_admin(): void {
    require_login();
    if (!is_admin()) { flash('Admins only.'); redirect('index.php'); }
}
function try_login(string $username, string $password): bool {
    $st = db()->prepare('SELECT * FROM app_user WHERE username = ?');
    $st->execute([$username]);
    $u = $st->fetch();
    if ($u && password_verify($password, $u['password_hash'])) {
        unset($u['password_hash']);
        session_regenerate_id(true);
        $_SESSION['user'] = $u;
        return true;
    }
    usleep(250000);
    return false;
}
function logout(): void { $_SESSION = []; session_destroy(); }
function users(): array {
    return db()->query('SELECT user_id, username, display_name, email, role FROM app_user ORDER BY display_name, username')->fetchAll();
}
function user_name(?int $id): string {
    if (!$id) return '—';
    foreach (users() as $u) if ((int)$u['user_id'] === $id) return $u['display_name'] ?: $u['username'];
    return '—';
}

/* ---------- flash ---------- */
function flash(?string $msg = null): ?string {
    if ($msg !== null) { $_SESSION['flash'] = $msg; return null; }
    $m = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $m;
}

/* ---------- pipeline ---------- */
function statuses(): array {
    return ['New Enquiry', 'Contacted', 'Questionnaire Sent', 'Questionnaire Completed',
            'Quoting', 'Quote Sent', 'Won', 'Lost', 'Not Proceeding', 'Closed'];
}
function terminal_statuses(): array { return ['Won', 'Lost', 'Not Proceeding', 'Closed']; }
/** Statuses a completed questionnaire moves a lead forward FROM (later stages are left alone). */
function pre_questionnaire_statuses(): array {
    return ['New Enquiry', 'Contacted', 'Questionnaire Sent', 'Lost', 'Not Proceeding', 'Closed'];
}
function status_class(string $s): string { return 'st-' . strtolower(preg_replace('/[^a-z0-9]+/i', '-', $s)); }
function q_status_label(string $s): string {
    return ['not_started' => 'Not started', 'in_progress' => 'In progress', 'submitted' => 'Completed'][$s] ?? $s;
}
function lead_sources(): array { return ['Website form', 'Phone', 'Email', 'Referral', 'Renewal', 'Manual Input']; }

function lead_ref(int $id): string { return 'POL-' . str_pad((string)$id, 4, '0', STR_PAD_LEFT); }
function parse_ref_search(string $q): int {
    return preg_match('/^\s*(?:POL-?)?0*(\d+)\s*$/i', $q, $m) ? (int)$m[1] : 0;
}
function lead_name(array $lead): string {
    $n = trim($lead['first_name'] . ' ' . $lead['last_name']);
    return $n !== '' ? $n : ($lead['company_name'] ?: lead_ref((int)$lead['lead_id']));
}

function find_lead(int $id): ?array {
    $st = db()->prepare('SELECT * FROM leads WHERE lead_id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}
function find_lead_by_token(string $token): ?array {
    if (!preg_match('/^[a-f0-9]{48}$/', $token)) return null;
    $st = db()->prepare('SELECT * FROM leads WHERE link_token = ?');
    $st->execute([$token]);
    return $st->fetch() ?: null;
}

function add_note(int $leadId, string $body, ?int $userId = null): void {
    db()->prepare('INSERT INTO lead_note (lead_id, body, created_by, created_at) VALUES (?,?,?,?)')
        ->execute([$leadId, $body, $userId, now()]);
}

function touch_lead(int $leadId, array $fields): void {
    $fields['updated_at'] = now();
    $sets = implode(', ', array_map(fn($k) => "$k = ?", array_keys($fields)));
    db()->prepare("UPDATE leads SET $sets WHERE lead_id = ?")->execute([...array_values($fields), $leadId]);
}

/* ---------- client links ---------- */
function new_link_token(): string { return bin2hex(random_bytes(24)); }
function ensure_link_token(array &$lead): string {
    if (empty($lead['link_token'])) {
        $lead['link_token'] = new_link_token();
        touch_lead((int)$lead['lead_id'], ['link_token' => $lead['link_token']]);
    }
    return $lead['link_token'];
}
function questionnaire_link(string $token): string {
    return rtrim((string)cfg('questionnaire_url', 'https://www.polished-insurance.co.uk/insurance-questionnaire'), '/') . '?t=' . urlencode($token);
}

/* ---------- outgoing email ---------- */
// Returns ['ok'=>true] or ['ok'=>false,'error'=>...] — never throws.
function send_email(string $toEmail, string $toName, string $subject, string $html, string $text, array $bcc = []): array {
    $m = config()['mail'] ?? [];
    foreach (['smtp_host', 'smtp_user', 'smtp_pass'] as $k) {
        if (empty($m[$k]) || $m[$k] === 'CHANGE_ME') return ['ok' => false, 'error' => 'Email is not configured yet (config.php "mail").'];
    }
    if (getenv('POLISHED_FAKE_SEND')) return fake_send('email', $toEmail, $subject . "\n" . $text);
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->CharSet = 'UTF-8';
        $mail->isSMTP();
        $mail->Host = $m['smtp_host'];
        $mail->Port = (int)($m['smtp_port'] ?? 465);
        $mail->SMTPSecure = $m['smtp_secure'] ?? 'ssl';
        $mail->SMTPAuth = true;
        $mail->Username = $m['smtp_user'];
        $mail->Password = $m['smtp_pass'];
        $mail->setFrom($m['from_email'], $m['from_name'] ?? 'Polished Insurance');
        $mail->addAddress($toEmail, $toName);
        foreach ($bcc as $b) if ($b !== '') $mail->addBCC($b);
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body = $html;
        $mail->AltBody = $text;
        $mail->send();
        return ['ok' => true];
    } catch (\Throwable $ex) {
        return ['ok' => false, 'error' => $ex->getMessage()];
    }
}

/* ---------- outgoing SMS (Twilio) ---------- */
/** UK numbers to E.164 (07700 900123 -> +447700900123). Leaves other formats alone. */
function normalise_uk_phone(string $phone): string {
    $p = preg_replace('/[^\d+]/', '', $phone);
    if (str_starts_with($p, '00')) $p = '+' . substr($p, 2);
    if (str_starts_with($p, '0')) $p = '+44' . substr($p, 1);
    if (str_starts_with($p, '44')) $p = '+' . $p;
    return $p;
}
function is_mobile_number(string $phone): bool {
    return (bool)preg_match('/^\+447\d{9}$/', normalise_uk_phone($phone));
}
function send_sms(string $toPhone, string $body): array {
    $s = config()['sms'] ?? [];
    if (empty($s['account_sid']) || $s['account_sid'] === 'CHANGE_ME' || empty($s['auth_token']) || $s['auth_token'] === 'CHANGE_ME') {
        return ['ok' => false, 'error' => 'SMS is not configured yet (config.php "sms").'];
    }
    $to = normalise_uk_phone($toPhone);
    if (getenv('POLISHED_FAKE_SEND')) return fake_send('sms', $to, $body);
    $ch = curl_init("https://api.twilio.com/2010-04-01/Accounts/{$s['account_sid']}/Messages.json");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => $s['account_sid'] . ':' . $s['auth_token'],
        CURLOPT_POSTFIELDS => http_build_query(['To' => $to, 'From' => $s['from_number'], 'Body' => $body]),
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($response === false) return ['ok' => false, 'error' => 'Could not reach Twilio: ' . $err];
    if ($code >= 200 && $code < 300) return ['ok' => true];
    $decoded = json_decode((string)$response, true);
    return ['ok' => false, 'error' => $decoded['message'] ?? ('Twilio returned HTTP ' . $code)];
}

function send_telegram_alert(string $text): void {
    $t = config()['telegram'] ?? [];
    if (empty($t['bot_token']) || $t['bot_token'] === 'CHANGE_ME' || getenv('POLISHED_FAKE_SEND')) return;
    $ch = curl_init("https://api.telegram.org/bot{$t['bot_token']}/sendMessage");
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10,
        CURLOPT_POSTFIELDS => http_build_query(['chat_id' => $t['chat_id'], 'text' => $text, 'parse_mode' => 'HTML']),
    ]);
    curl_exec($ch);
    curl_close($ch);
}

/** Local testing only (POLISHED_FAKE_SEND=1): writes messages to data/outbox.log instead of sending. */
function fake_send(string $channel, string $to, string $body): array {
    $dir = __DIR__ . '/data';
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    file_put_contents("$dir/outbox.log", '[' . now() . "] $channel -> $to\n$body\n----\n", FILE_APPEND);
    return ['ok' => true];
}

/** Internal alert to the team (email + optional Telegram). Best effort — never blocks. */
function notify_team(string $subject, string $text): void {
    $crmLink = rtrim((string)cfg('crm_base_url', ''), '/');
    foreach ((array)(config()['notify_emails'] ?? []) as $to) {
        if (!$to || $to === 'CHANGE_ME') continue;
        send_email($to, 'Polished team', $subject, nl2br(e($text)), $text);
    }
    send_telegram_alert('<b>' . e($subject) . "</b>\n" . e($text));
}

/* ---------- chasing ---------- */
function max_auto_chase_messages(): int { return 3; }
function random_chase_window(): int { return random_int(1, 3); }

/**
 * Sends questionnaire chase message $n (1..3) by email and SMS, whatever contact details exist.
 * Returns ['sent'=>[...], 'failures'=>[...]]. Logs a note either way.
 */
function send_chase_message(array $lead, int $n, string $label, ?int $userId = null): array {
    $token = ensure_link_token($lead);
    $link = questionnaire_link($token);
    $sent = []; $failures = [];
    $mailFrom = (string)((config()['mail'] ?? [])['from_email'] ?? '');

    if (trim($lead['email']) !== '') {
        $msg = build_chase_email($lead, $link, $n);
        $r = send_email($lead['email'], lead_name($lead), $msg['subject'], $msg['html'], $msg['text'], [$mailFrom]);
        $r['ok'] ? ($sent[] = 'email to ' . $lead['email']) : ($failures[] = 'email failed: ' . $r['error']);
    }
    if (trim($lead['phone']) !== '') {
        if (is_mobile_number($lead['phone'])) {
            $r = send_sms($lead['phone'], build_chase_sms($lead, $link, $n));
            $r['ok'] ? ($sent[] = 'SMS to ' . $lead['phone']) : ($failures[] = 'SMS failed: ' . $r['error']);
        } else {
            $failures[] = 'SMS skipped: ' . $lead['phone'] . ' is not a UK mobile number';
        }
    }
    if (trim($lead['email']) === '' && trim($lead['phone']) === '') $failures[] = 'no email or phone on file';

    $bits = [];
    if ($sent) $bits[] = 'Sent via ' . implode(' and ', $sent) . '.';
    if ($failures) $bits[] = implode('; ', $failures) . '.';
    add_note((int)$lead['lead_id'], "$label: " . implode(' ', $bits), $userId);
    return ['sent' => $sent, 'failures' => $failures];
}

/**
 * Message 1 of 3 — starts the automatic chase sequence. Used for new website leads
 * (when auto_chase_new_leads is on) and by the "Send questionnaire link" button.
 */
function start_questionnaire_chase(array $lead, string $label = 'Questionnaire link sent (message 1 of 3)', ?int $userId = null): array {
    if (($lead['q_status'] ?? '') === 'submitted') {
        return ['ok' => false, 'message' => 'The questionnaire is already completed — reopen it first if you need the client to change anything.'];
    }
    $r = send_chase_message($lead, 1, $label, $userId);
    if (!$r['sent']) {
        return ['ok' => false, 'message' => 'Could not send the link (' . implode('; ', $r['failures']) . ') — please contact the client manually.'];
    }
    $fields = [
        'chasing' => 1,
        'auto_chase_count' => 1,
        'next_chase_date' => date('Y-m-d', strtotime('+' . (int)cfg('first_auto_chase_days', 2) . ' days')),
        'next_chase_window' => random_chase_window(),
    ];
    if (in_array($lead['status'], ['New Enquiry', 'Contacted'], true)) $fields['status'] = 'Questionnaire Sent';
    touch_lead((int)$lead['lead_id'], $fields);
    return ['ok' => true, 'message' => 'Questionnaire link sent (' . implode(' and ', $r['sent']) . '). Automatic reminders are on.'];
}

function stop_chasing(int $leadId): void {
    touch_lead($leadId, ['chasing' => 0, 'next_chase_date' => null, 'next_chase_window' => null]);
}

function auto_chaseable_leads(int $window): array {
    $st = db()->prepare("SELECT * FROM leads
        WHERE chasing = 1 AND auto_chase_count <= ? AND q_status <> 'submitted'
          AND next_chase_date IS NOT NULL AND next_chase_date <= ?
          AND (next_chase_window IS NULL OR next_chase_window <= ?)
        ORDER BY lead_id");
    $st->execute([max_auto_chase_messages(), date('Y-m-d'), $window]);
    return $st->fetchAll();
}

/** Open leads that need a human: overdue follow-up, or chasing finished with no response. */
function needs_attention_leads(): array {
    $terminal = implode(',', array_map(fn($s) => db()->quote($s), terminal_statuses()));
    $st = db()->prepare("SELECT * FROM leads
        WHERE status NOT IN ($terminal) AND chasing = 0
          AND ((next_follow_up IS NOT NULL AND next_follow_up <= ?) OR status IN ('New Enquiry','Questionnaire Completed'))
        ORDER BY COALESCE(next_follow_up, created_at) ASC LIMIT 50");
    $st->execute([date('Y-m-d')]);
    return $st->fetchAll();
}

/* ---------- JSON API helpers (website -> CRM) ---------- */
function api_cors(): void {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowed = (array)(config()['allowed_origins'] ?? []);
    if ($origin !== '' && in_array($origin, $allowed, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-Polished-Intake-Key');
        header('Access-Control-Max-Age: 86400');
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(204); exit; }
}
function json_out(array $payload, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
function json_body(): array {
    $raw = file_get_contents('php://input') ?: '';
    $ct = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($ct, 'application/json') !== false) {
        $d = json_decode($raw, true);
        return is_array($d) ? $d : [];
    }
    return $_POST;
}
function client_ip_hash(): string {
    return hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|polished');
}
/** Sends the JSON response now, then keeps running (e.g. slow SMTP) without the visitor waiting. */
function json_out_then(array $payload, callable $after): void {
    ignore_user_abort(true);
    $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('Content-Length: ' . strlen($body));
    header('Connection: close');
    echo $body;
    if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
    else { @ob_end_flush(); flush(); }
    try { $after(); } catch (\Throwable $ex) { error_log('Polished CRM after-response task failed: ' . $ex->getMessage()); }
    exit;
}

/* ---------- layout ---------- */
function asset(string $path): string {
    $file = __DIR__ . '/' . $path;
    return e($path) . (is_file($file) ? '?v=' . filemtime($file) : '');
}

function layout_header(string $title = '', string $bodyClass = ''): void {
    $u = current_user();
    echo "<!doctype html><html lang='en'><head><meta charset='utf-8'>";
    echo "<meta name='viewport' content='width=device-width, initial-scale=1'><meta name='robots' content='noindex,nofollow'>";
    echo "<title>" . ($title ? e($title) . ' · ' : '') . "Polished CRM</title>";
    echo "<link rel='icon' type='image/png' href='" . asset('assets/img/favicon.png') . "'>";
    echo "<link rel='stylesheet' href='" . asset('assets/style.css') . "'></head><body class='" . e($bodyClass) . "'>";
    echo "<header class='topbar'><div class='wrap'>";
    echo "<a class='logo' href='index.php'><img class='logo-mark' src='" . asset('assets/img/icon.png') . "' alt='' width='30' height='30'><span class='logo-text'>Polished <em>CRM</em></span></a>";
    if ($u) {
        $page = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
        $nav = function (string $href, string $label, array $on) use ($page) {
            $active = in_array($page, $on, true) ? ' active' : '';
            return "<a class='nav-btn$active' href='" . e($href) . "'>" . e($label) . '</a>';
        };
        echo '<nav>'
            . $nav('index.php', 'Dashboard', ['index.php'])
            . $nav('leads.php', 'Leads', ['leads.php', 'lead.php', 'lead_edit.php', 'questionnaire.php'])
            . $nav('tasks.php', 'Tasks', ['tasks.php'])
            . (is_admin() ? $nav('users.php', 'Users', ['users.php']) : '')
            . '</nav>';
        echo "<div class='who'>" . e($u['display_name'] ?: $u['username']) . " · <a href='set_password.php'>Password</a> · <a href='logout.php'>Log out</a></div>";
    }
    echo "</div></header><main class='wrap'>";
    if ($f = flash()) echo "<div class='flash'>" . e($f) . '</div>';
}
function layout_footer(): void {
    echo "</main><footer class='foot'><div class='wrap'>Polished Insurance CRM — internal use only. Contains client personal data: do not share outside the team.</div></footer></body></html>";
}
