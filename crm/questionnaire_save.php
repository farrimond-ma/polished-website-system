<?php
/** Staff autosave / mark-completed endpoint for questionnaire.php (JSON, CSRF header). */
require __DIR__ . '/lib.php';
if (!current_user()) json_out(['ok' => false, 'error' => 'Your session has expired — please sign in again.'], 401);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false, 'error' => 'POST only'], 405);
if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) json_out(['ok' => false, 'error' => 'Invalid token'], 400);

$id = (int)param('id', 0);
$lead = find_lead($id);
if (!$lead) json_out(['ok' => false, 'error' => 'Lead not found'], 404);

$body = json_body();
$incoming = is_array($body['data'] ?? null) ? $body['data'] : [];
$data = q_sanitise($incoming); // staff may write every known key
$keep = fn($list) => array_values(array_filter((array)$list, fn($s) => is_string($s) && preg_match('/^[a-z0-9_]+$/', $s)));
$hidden = $keep($incoming['_hidden_sections'] ?? []);
if ($hidden) $data['_hidden_sections'] = $hidden;
$hiddenItems = $keep($incoming['_hidden_items'] ?? []);
if ($hiddenItems) $data['_hidden_items'] = $hiddenItems;

$me = (int)current_user()['user_id'];
// Staff edits never change q_status on their own (merely opening the page applies schema
// defaults and autosaves) — only the client starting, or an explicit "mark completed", does.
$fields = ['q_data' => json_encode($data, JSON_UNESCAPED_UNICODE), 'q_saved_at' => now()];

if (param('action') === 'submit') {
    $fields += ['q_status' => 'submitted', 'q_submitted_at' => now(), 'chasing' => 0, 'next_chase_date' => null, 'next_chase_window' => null];
    if (in_array($lead['status'], pre_questionnaire_statuses(), true)) $fields['status'] = 'Questionnaire Completed';
    touch_lead($id, $fields);
    add_note($id, 'Questionnaire marked as completed by staff.', $me);
    json_out(['ok' => true]);
}

touch_lead($id, $fields);
json_out(['ok' => true, 'saved_at' => $fields['q_saved_at']]);
