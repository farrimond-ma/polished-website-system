<?php
/**
 * Client-facing message wording, kept in one place so the manual button, the new-lead
 * auto-send and the reminder cron all say the same thing. $n = message 1, 2 or 3.
 * A client who has started (but not finished) the questionnaire gets "pick up where you
 * left off" wording instead of "get started".
 */
declare(strict_types=1);

function msg_first_name(array $lead): string {
    $f = trim((string)($lead['first_name'] ?? ''));
    return $f !== '' ? $f : 'there';
}

function msg_email_wrap(string $innerHtml): string {
    $phone = e(cfg('company_phone', '01942 403370'));
    return '<div style="font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.55;color:#1f2733;max-width:600px">'
        . '<div style="background:#ffffff;padding:18px 24px;border:1px solid #e2e8ef;border-bottom:3px solid #1664f0;border-radius:8px 8px 0 0">'
        . '<img src="' . e(rtrim((string)cfg('site_url', 'https://www.polished-insurance.co.uk'), '/')) . '/images/brand/logo-email.png" width="180" alt="Polished Insurance" style="display:block;border:0;height:auto"></div>'
        . '<div style="border:1px solid #e2e8ef;border-top:0;padding:22px 24px;border-radius:0 0 8px 8px">'
        . $innerHtml
        . '<p style="margin-top:26px">Kind regards,<br>The Polished Insurance team<br>'
        . "<a href=\"tel:" . preg_replace('/\s+/', '', $phone) . "\" style=\"color:#0a192f\">$phone</a></p>"
        . '</div>'
        . '<p style="font-size:11.5px;color:#8a97a5;margin-top:14px">Polished Insurance is a trading name of Allied Insurance Services Ltd, '
        . 'authorised and regulated by the Financial Conduct Authority (FRN 309497). Registered in England No. 4319831. '
        . '98 Standishgate, Wigan, WN1 1XA.</p></div>';
}

function msg_button(string $link, string $label): string {
    return '<p style="margin:22px 0"><a href="' . e($link) . '" style="background:#1664f0;color:#ffffff;font-weight:bold;'
        . 'padding:13px 22px;border-radius:6px;text-decoration:none;display:inline-block">' . e($label) . '</a></p>'
        . '<p style="font-size:12.5px;color:#6b7785">If the button does not work, copy this link into your browser:<br>' . e($link) . '</p>';
}

function build_chase_email(array $lead, string $link, int $n = 1): array {
    $first = e(msg_first_name($lead));
    $started = ($lead['q_status'] ?? '') === 'in_progress';
    $phone = cfg('company_phone', '01942 403370');
    $btn = $started ? 'Continue my questionnaire' : 'Start my questionnaire';

    if ($n === 2) {
        $subject = $started ? 'Your insurance questionnaire is nearly there' : 'Quick reminder: your cleaning business insurance quote';
        $body = "<p>Hi {$first},</p>"
            . ($started
                ? '<p>You have made a start on your insurance questionnaire. Your answers are saved, so you can pick up exactly where you left off.</p>'
                : '<p>Just a quick reminder: we need a few details about your cleaning business before we can go to our insurers for quotes.</p>')
            . msg_button($link, $btn)
            . '<p>Most businesses finish it in around 10 to 15 minutes. If you would rather go through it over the phone, call us on ' . e($phone) . '.</p>';
    } elseif ($n === 3) {
        $subject = 'Last reminder: your insurance quote request';
        $body = "<p>Hi {$first},</p>"
            . '<p>This is our final reminder about your insurance enquiry. Without the questionnaire we cannot approach insurers on your behalf.</p>'
            . msg_button($link, $btn)
            . '<p>If you no longer need a quote, no problem, you do not need to do anything and we will close your enquiry in a few days. '
            . 'You are welcome to come back to us at any time.</p>';
    } else {
        $subject = 'Your cleaning business insurance quote: next steps';
        $body = "<p>Hi {$first},</p>"
            . '<p>Thank you for your enquiry with Polished Insurance. We specialise in insurance for cleaning businesses and will search our panel of insurers for the right cover at the right price.</p>'
            . '<p>To do that, we need some details about your business: what you do, where you work, your turnover and staff, and the covers you need. '
            . 'We have already filled in your contact details.</p>'
            . msg_button($link, $btn)
            . '<p>Your answers save automatically as you go, so you can stop and come back using the same link. '
            . 'If anything is unclear, call us on ' . e($phone) . ' and we will help.</p>';
    }

    $text = strip_tags(str_replace(['</p>', '<br>'], ["\n\n", "\n"], $body)) . "Your questionnaire: $link\n\nKind regards,\nThe Polished Insurance team\n$phone";
    return ['subject' => $subject, 'html' => msg_email_wrap($body), 'text' => html_entity_decode($text, ENT_QUOTES, 'UTF-8')];
}

function build_chase_sms(array $lead, string $link, int $n = 1): string {
    $first = msg_first_name($lead);
    $phone = cfg('company_phone', '01942 403370');
    $started = ($lead['q_status'] ?? '') === 'in_progress';
    if ($n === 2) {
        return $started
            ? "Hi {$first}, your Polished Insurance questionnaire is saved. Pick up where you left off: {$link}"
            : "Hi {$first}, a quick reminder from Polished Insurance: we need a few details to get your cleaning insurance quotes. {$link}";
    }
    if ($n === 3) {
        return "Hi {$first}, last reminder from Polished Insurance about your quote. Complete your details here: {$link} or call {$phone}. Reply STOP to opt out.";
    }
    return "Hi {$first}, thanks for your enquiry with Polished Insurance. To get your quotes, please complete your questionnaire (about 10 mins, saves as you go): {$link}";
}

/** Confirmation to the client after they submit the questionnaire. */
function build_submitted_email(array $lead): array {
    $first = e(msg_first_name($lead));
    $phone = cfg('company_phone', '01942 403370');
    $body = "<p>Hi {$first},</p><p>Thank you, we have received your completed insurance questionnaire (reference "
        . e(lead_ref((int)$lead['lead_id'])) . ').</p>'
        . '<p>One of our team will review your answers and approach our insurers. We will be in touch, usually within one working day, '
        . 'if we need anything else or once your quotes are ready.</p>'
        . '<p>If you need to change an answer, call us on ' . e($phone) . '.</p>';
    $text = strip_tags(str_replace('</p>', "\n\n", $body)) . "Kind regards,\nThe Polished Insurance team\n$phone";
    return ['subject' => 'We have received your insurance questionnaire', 'html' => msg_email_wrap($body), 'text' => html_entity_decode($text, ENT_QUOTES, 'UTF-8')];
}
