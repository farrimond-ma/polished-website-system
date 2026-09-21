<?php
/**
 * Client questionnaire API, used by the website page /insurance-questionnaire?t=<token>.
 * The token (48 hex chars, emailed/texted to the client) is the only credential.
 *
 *   GET  api/questionnaire.php?t=TOKEN             -> schema (client view) + saved answers
 *   POST api/questionnaire.php?t=TOKEN&action=save   {data:{...}} -> autosave
 *   POST api/questionnaire.php?t=TOKEN&action=submit {data:{...}} -> final submit
 *
 * Only client-visible answer keys are returned or accepted, so anything staff record in
 * "Extra" fields is never exposed to — or overwritable by — the client.
 */
declare(strict_types=1);
define('POLISHED_API', true);
require __DIR__ . '/../lib.php';

api_cors();

$lead = find_lead_by_token((string)($_GET['t'] ?? ''));
if (!$lead) json_out(['ok' => false, 'error' => 'This link is not valid. Please check the link in your email or call us.'], 404);

$leadId = (int)$lead['lead_id'];
$stored = q_lead_data($lead);
$hidden = array_values(array_filter((array)($stored['_hidden_sections'] ?? []), 'is_string'));
$clientKeys = q_client_keys($hidden);
$submitted = $lead['q_status'] === 'submitted';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $data = array_intersect_key(q_data_with_prefill($lead), $clientKeys);
    json_out([
        'ok' => true,
        'reference' => lead_ref($leadId),
        'first_name' => $lead['first_name'],
        'submitted' => $submitted,
        'submitted_at' => $lead['q_submitted_at'],
        'prefilled' => array_keys(array_intersect_key(q_prefill_from_lead($lead), $clientKeys)),
        'schema' => q_client_schema($hidden),
        'data' => (object)$data,
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false, 'error' => 'Method not allowed'], 405);
if ($submitted) json_out(['ok' => false, 'error' => 'This questionnaire has already been submitted. Please call us if you need to change anything.'], 409);

$body = json_body();
$incoming = is_array($body['data'] ?? null) ? $body['data'] : [];
$clean = q_sanitise($incoming, $clientKeys);

// Keep everything staff-only (including hidden table columns); replace the client-visible part
// with what the client sent, and record schema defaults for questions the client never sees.
$merged = q_merge_client_answers($stored, $clean, $clientKeys);
$json = json_encode($merged, JSON_UNESCAPED_UNICODE);
if (strlen($json) > 1000000) json_out(['ok' => false, 'error' => 'Too much data.'], 413);

$action = (string)($_GET['action'] ?? 'save');
$fields = ['q_data' => $json, 'q_saved_at' => now()];

if ($lead['q_status'] === 'not_started') {
    $fields['q_status'] = 'in_progress';
    $fields['q_started_at'] = now();
    add_note($leadId, 'Client opened the questionnaire and started filling it in.');
}

if ($action !== 'submit') {
    touch_lead($leadId, $fields);
    json_out(['ok' => true, 'saved_at' => $fields['q_saved_at']]);
}

// ---- submit ----
$missing = q_missing_required($merged, true, $hidden);
if ($missing) {
    touch_lead($leadId, $fields);
    json_out(['ok' => false, 'error' => 'Some required questions are still unanswered.', 'missing' => $missing], 422);
}

$wasStatus = $lead['status'];
$fields['q_status'] = 'submitted';
$fields['q_submitted_at'] = now();
$fields['chasing'] = 0;
$fields['next_chase_date'] = null;
$fields['next_chase_window'] = null;
$fields['next_follow_up'] = date('Y-m-d');
if (in_array($wasStatus, pre_questionnaire_statuses(), true)) $fields['status'] = 'Questionnaire Completed';
touch_lead($leadId, $fields);
add_note($leadId, 'Client submitted the questionnaire. Automatic reminders stopped.'
    . (in_array($wasStatus, terminal_statuses(), true) ? " Lead reopened (was $wasStatus)." : ''));

json_out_then(['ok' => true, 'reference' => lead_ref($leadId)], function () use ($leadId) {
    $lead = find_lead($leadId);
    if (trim($lead['email']) !== '') {
        $m = build_submitted_email($lead);
        send_email($lead['email'], lead_name($lead), $m['subject'], $m['html'], $m['text']);
    }
    notify_team('Questionnaire completed: ' . lead_ref($leadId) . ' ' . lead_name($lead),
        lead_name($lead) . ($lead['company_name'] ? ' (' . $lead['company_name'] . ')' : '') . " has submitted their questionnaire.\n"
        . rtrim((string)cfg('crm_base_url', ''), '/') . '/lead.php?id=' . $leadId,
        (int)($lead['assigned_to'] ?? 0) ?: null);
});
