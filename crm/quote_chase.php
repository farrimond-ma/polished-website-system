<?php
/**
 * Quote chasers: the three emails to a client who has had a quote.
 *
 * The clock starts when a record is set to Quote Sent. The first email goes an hour later, the
 * second three hours after that, the third twelve hours after that, and nothing is sent after it.
 * Each one quotes the premium recorded on the record, so a record with no premium is left alone.
 *
 * Because the timings are in hours, this wants an HOURLY SiteGround cron:
 *
 *   php /home/customer/www/crm.polished-insurance.co.uk/public_html/quote_chase.php
 *
 * Or by URL: https://crm.polished-insurance.co.uk/quote_chase.php?key=<cron_key>
 *
 * It is safe to run as often as you like: it only sends what is actually due. The three-times-a-day
 * questionnaire cron also runs it, so chasers still go out (just less punctually) if the hourly
 * job is not set up.
 *
 * Chasing stops on its own when the status moves off Quote Sent, or when someone clicks Stop.
 */
declare(strict_types=1);
require __DIR__ . '/lib.php';

$isCli = PHP_SAPI === 'cli';
if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
    $expected = (string)cfg('cron_key', '');
    if ($expected === '' || !hash_equals($expected, (string)param('key', ''))) {
        http_response_code(401);
        exit("Invalid or missing key.\n");
    }
}

$lines = run_quote_chases();
echo $lines ? implode("\n", $lines) . "\n" : "Nothing due.\n";
echo count($lines) . " message(s) sent at " . now() . "\n";
