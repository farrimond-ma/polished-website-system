<?php
/**
 * Automatic questionnaire reminders (messages 2 and 3), then auto-close.
 * Run 3x a day by SiteGround cron, each run passing its "window" (1, 2 or 3). Each lead is
 * randomly assigned a window when scheduled, so reminders land at varied times of day.
 *
 *   php /home/customer/www/crm.polished-insurance.co.uk/public_html/auto_chase.php 1   (e.g. 09:30)
 *   php /home/customer/www/crm.polished-insurance.co.uk/public_html/auto_chase.php 2   (e.g. 13:00)
 *   php /home/customer/www/crm.polished-insurance.co.uk/public_html/auto_chase.php 3   (e.g. 17:30)
 *
 * Or by URL: https://crm.polished-insurance.co.uk/auto_chase.php?key=<cron_key>&window=1
 *
 * Message 1 is sent immediately when the lead arrives (or when staff click the button).
 * A lead stops being chased the moment the client submits the questionnaire.
 */
declare(strict_types=1);
require __DIR__ . '/lib.php';

$isCli = PHP_SAPI === 'cli';
if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
    $expected = (string)cfg('cron_key', '');
    if ($expected === '' || !hash_equals($expected, (string)param('key', ''))) { http_response_code(401); exit("Invalid or missing key.\n"); }
}
$window = $isCli ? (int)($argv[1] ?? 0) : (int)param('window', 0);
if (!in_array($window, [1, 2, 3], true)) exit("Pass a window number: 1, 2 or 3.\n");

$cap = max_auto_chase_messages();
$secondDelay = (int)cfg('second_auto_chase_days', 3);
$closeDelay = (int)cfg('auto_close_after_days', 5);
$leads = auto_chaseable_leads($window);
if (!$leads) exit("No leads due a reminder in window $window today.\n");

foreach ($leads as $lead) {
    $id = (int)$lead['lead_id'];
    $ref = lead_ref($id);

    if ((int)$lead['auto_chase_count'] >= $cap) {
        $note = "Automatically closed: no completed questionnaire after $cap messages and a further $closeDelay day(s).";
        touch_lead($id, ['status' => 'Closed', 'chasing' => 0, 'next_chase_date' => null, 'next_chase_window' => null, 'next_follow_up' => null]);
        add_note($id, $note);
        echo "$ref: $note\n";
        continue;
    }

    $n = (int)$lead['auto_chase_count'] + 1;
    $r = send_chase_message($lead, $n, "Automatic reminder (message $n of $cap)");
    $more = $n < $cap;
    // Counted as an attempt even if sending failed, so a broken address can't loop forever.
    touch_lead($id, [
        'auto_chase_count' => $n,
        'next_chase_date' => date('Y-m-d', strtotime('+' . ($more ? $secondDelay : $closeDelay) . ' days')),
        'next_chase_window' => random_chase_window(),
    ]);
    echo "$ref: message $n — sent: " . (implode(', ', $r['sent']) ?: 'nothing') . ($r['failures'] ? ' — problems: ' . implode('; ', $r['failures']) : '') . "\n";
}

// Quote chasers are timed in hours and want their own hourly cron (quote_chase.php). Running them
// here as well means they still go out, a little late, if that job has not been set up.
foreach (run_quote_chases() as $line) echo "$line\n";
