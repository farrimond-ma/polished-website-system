<?php
/**
 * Copy this file to config.php on the server and fill in the real values.
 * config.php is never committed/uploaded from the repo, so deploys can't overwrite it.
 * Any value left as 'CHANGE_ME' switches that feature off (e.g. SMS) rather than erroring.
 */
return [
    // ---- database (SiteGround MySQL) ----
    'driver'   => 'mysql',            // 'sqlite' for local testing only
    'host'     => 'localhost',
    'port'     => 3306,
    'dbname'   => 'CHANGE_ME',
    'user'     => 'CHANGE_ME',
    'password' => 'CHANGE_ME',
    'sqlite_path' => __DIR__ . '/data/local.sqlite',

    // ---- app ----
    'app_name'     => 'Polished CRM',
    'crm_base_url' => 'https://crm.polished-insurance.com',
    // Client questionnaire page on the marketing website (the ?t=<token> is appended).
    'questionnaire_url' => 'https://polished-insurance.com/insurance-questionnaire',
    'site_url'     => 'https://polished-insurance.com',
    'company_phone' => '0330 056 8970',
    'company_email' => 'hello@polished-insurance.co.uk',

    // ---- website -> CRM API ----
    // Shared key the website's enquiry form sends in the X-Polished-Intake-Key header.
    // It is visible in the website's JS bundle, so it only filters casual abuse — the
    // honeypot, timing check and per-IP rate limit in api/intake.php do the real work.
    'intake_key' => 'CHANGE_ME',
    // Origins allowed to call api/intake.php and api/questionnaire.php from the browser.
    'allowed_origins' => ['https://polished-insurance.com', 'https://www.polished-insurance.com'],

    // ---- chasing ----
    // true  = a new website enquiry gets the questionnaire link (email + SMS) straight away.
    // false = staff start the chase from the lead page ("Send questionnaire link").
    'auto_chase_new_leads'   => true,
    'first_auto_chase_days'  => 2,   // message 2 goes this many days after message 1
    'second_auto_chase_days' => 3,   // message 3 goes this many days after message 2
    'auto_close_after_days'  => 5,   // lead auto-closes this many days after message 3
    'follow_up_days'         => 2,   // default "needs attention" follow-up for open leads
    'cron_key'               => 'CHANGE_ME', // for URL-triggered cron runs (?key=)

    // ---- outgoing email (SMTP via PHPMailer) ----
    'mail' => [
        'smtp_host'   => 'CHANGE_ME',
        'smtp_port'   => 465,
        'smtp_secure' => 'ssl',
        'smtp_user'   => 'CHANGE_ME',
        'smtp_pass'   => 'CHANGE_ME',
        'from_email'  => 'hello@polished-insurance.co.uk',
        'from_name'   => 'Polished Insurance',
    ],
    // Who gets "new enquiry" / "questionnaire completed" alerts.
    'notify_emails' => ['CHANGE_ME'],

    // ---- SMS (Twilio) ----
    'sms' => [
        'account_sid' => 'CHANGE_ME',
        'auth_token'  => 'CHANGE_ME',
        'from_number' => 'CHANGE_ME',   // Twilio number or approved alphanumeric sender, e.g. 'Polished'
    ],

    // ---- optional Telegram alerts ----
    'telegram' => [
        'bot_token' => 'CHANGE_ME',
        'chat_id'   => 'CHANGE_ME',
    ],
];
