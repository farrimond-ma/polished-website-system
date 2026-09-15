<?php
/**
 * POST /api/intake.php — the website's "get a quote" enquiry form (contact details only).
 *
 * Body (JSON): first_name, last_name, company_name, email, phone, consent (true),
 *   optional: cover_interest, landing_page, utm_source, utm_medium, utm_campaign,
 *   website (honeypot — must be empty), elapsed_ms (time on form, bots submit instantly)
 * Header: X-Polished-Intake-Key: <intake_key from config.php>
 *
 * Response: {"ok":true,"reference":"POL-0012"}  |  {"ok":false,"error":"..."} with 4xx
 */
declare(strict_types=1);
define('POLISHED_API', true);
require __DIR__ . '/../lib.php';

api_cors();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false, 'error' => 'POST only'], 405);

$key = (string)($_SERVER['HTTP_X_POLISHED_INTAKE_KEY'] ?? '');
$expected = (string)cfg('intake_key', '');
if ($expected === '' || !hash_equals($expected, $key)) json_out(['ok' => false, 'error' => 'Unauthorised'], 401);

$in = json_body();
$str = fn(string $k, int $max = 190) => mb_substr(trim((string)($in[$k] ?? '')), 0, $max);

// Bots: honeypot filled, or submitted faster than a human could type. Pretend success.
if ($str('website') !== '' || (isset($in['elapsed_ms']) && (int)$in['elapsed_ms'] < 2500)) {
    json_out(['ok' => true, 'reference' => 'POL-0000']);
}

$first = $str('first_name', 100);
$last = $str('last_name', 100);
if ($first === '' && ($full = $str('full_name', 200)) !== '') {
    $parts = preg_split('/\s+/', $full, 2);
    $first = $parts[0]; $last = $parts[1] ?? '';
}
$email = strtolower($str('email'));
$phone = $str('phone', 40);

$errors = [];
if ($first === '') $errors[] = 'Please enter your name.';
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
if ($phone === '' || strlen(preg_replace('/\D/', '', $phone)) < 10) $errors[] = 'Please enter a valid phone number.';
if (empty($in['consent'])) $errors[] = 'Please agree to be contacted about your quote.';
if ($errors) json_out(['ok' => false, 'error' => implode(' ', $errors)], 422);

$pdo = db();
$ipHash = client_ip_hash();

// Per-IP rate limit: 5 enquiries an hour is plenty for a real person.
$st = $pdo->prepare('SELECT COUNT(*) FROM leads WHERE ip_hash = ? AND created_at >= ?');
$st->execute([$ipHash, date('Y-m-d H:i:s', time() - 3600)]);
if ((int)$st->fetchColumn() >= 5) json_out(['ok' => false, 'error' => 'Too many enquiries — please call us instead.'], 429);

// Same person enquiring again within 30 days while their lead is still open: log it on that lead.
$terminal = implode(',', array_map(fn($s) => $pdo->quote($s), terminal_statuses()));
$st = $pdo->prepare("SELECT * FROM leads WHERE email = ? AND status NOT IN ($terminal) AND created_at >= ? ORDER BY lead_id DESC LIMIT 1");
$st->execute([$email, date('Y-m-d H:i:s', time() - 30 * 86400)]);
if ($existing = $st->fetch()) {
    add_note((int)$existing['lead_id'], 'Submitted the website enquiry form again'
        . ($str('landing_page', 255) ? ' from ' . $str('landing_page', 255) : '') . '. Phone given: ' . $phone . '.');
    touch_lead((int)$existing['lead_id'], ['next_follow_up' => date('Y-m-d')]);
    json_out_then(['ok' => true, 'reference' => lead_ref((int)$existing['lead_id'])], function () use ($existing) {
        notify_team('Repeat enquiry: ' . lead_ref((int)$existing['lead_id']), lead_name($existing) . " submitted the website form again.\n"
            . rtrim((string)cfg('crm_base_url', ''), '/') . '/lead.php?id=' . $existing['lead_id']);
    });
}

$consentText = $str('consent_text', 500) ?: 'Agreed to be contacted by Polished Insurance about an insurance quote.';
$pdo->prepare('INSERT INTO leads (status, first_name, last_name, company_name, email, phone, source, landing_page, cover_interest,
        utm_source, utm_medium, utm_campaign, consent_text, consent_at, ip_hash, link_token, next_follow_up, created_at, updated_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
    ->execute(['New Enquiry', $first, $last, $str('company_name'), $email, $phone, 'Website form',
        $str('landing_page', 255), $str('cover_interest', 120), $str('utm_source', 120), $str('utm_medium', 120),
        $str('utm_campaign'), $consentText, now(), $ipHash, new_link_token(), date('Y-m-d', strtotime('+' . (int)cfg('follow_up_days', 2) . ' days')), now(), now()]);
$leadId = (int)$pdo->lastInsertId();
add_note($leadId, 'Enquiry received from the website' . ($str('landing_page', 255) ? ' (' . $str('landing_page', 255) . ')' : '') . '.');

json_out_then(['ok' => true, 'reference' => lead_ref($leadId)], function () use ($leadId) {
    $lead = find_lead($leadId);
    $chaseNote = '';
    if (cfg('auto_chase_new_leads', true)) {
        $r = start_questionnaire_chase($lead, 'Automatic questionnaire link sent (message 1 of 3)');
        $chaseNote = "\n" . $r['message'];
    }
    notify_team('New enquiry: ' . lead_ref($leadId) . ' ' . lead_name($lead),
        lead_name($lead) . ($lead['company_name'] ? ' (' . $lead['company_name'] . ')' : '') . "\n"
        . $lead['email'] . ' · ' . $lead['phone'] . "\n"
        . ($lead['cover_interest'] ? 'Interested in: ' . $lead['cover_interest'] . "\n" : '')
        . ($lead['landing_page'] ? 'Page: ' . $lead['landing_page'] . "\n" : '')
        . rtrim((string)cfg('crm_base_url', ''), '/') . '/lead.php?id=' . $leadId . $chaseNote);
});
