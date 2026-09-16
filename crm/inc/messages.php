<?php
/**
 * Client-facing message wording (emails and texts), kept in one place so the manual button, the
 * new-lead auto-send and the reminder cron all say the same thing.
 *
 * The wording below is the DEFAULT. Staff can edit any message on the CRM's Messages page
 * (messages.php); edits are stored in the `setting` table under msg_<key> and used from then on.
 * "Restore original" simply deletes the stored copy. If the database cannot be read for any
 * reason, the defaults here are used, so a message is never sent empty.
 *
 * Templates are plain text with placeholders: {first_name} {link} {phone} {reference}.
 * A blank line starts a new paragraph. {link} is written out in full as a clickable link.
 */
declare(strict_types=1);

/** key => [label, type (email|sms), subject (emails), body, note]. */
function msg_defaults(): array {
    return [
        'email_1' => [
            'label' => 'Email 1 — questionnaire link',
            'type' => 'email',
            'note' => 'Sent when a team member clicks “Send questionnaire link + start reminders”.',
            'subject' => 'Your cleaning business insurance quote: next steps',
            'body' => "Hi {first_name},\n\n"
                . "Thank you for your enquiry with Polished Insurance. We specialise in insurance for cleaning businesses and will search our panel of insurers for the right cover at the right price.\n\n"
                . "To do that, we need some details about your business: what you do, where you work, your turnover and staff, and the covers you need. We have already filled in your contact details.\n\n"
                . "Please click the link below to complete your questionnaire. It takes about 4 to 5 minutes and your answers save as you go, so you can stop and come back to the same link:\n\n"
                . "{link}\n\n"
                . "If anything is unclear, call us on {phone} and we will help.",
        ],
        'sms_1' => [
            'label' => 'Text 1 — questionnaire link',
            'type' => 'sms',
            'note' => 'Sent at the same time as email 1, if the lead has a mobile number.',
            'body' => "Hi {first_name}, thanks for your enquiry with Polished Insurance. To get your quotes, please complete your questionnaire (about 5 minutes, saves as you go): {link}",
        ],
        'email_2' => [
            'label' => 'Email 2 — reminder',
            'type' => 'email',
            'note' => 'First reminder, for someone who has not opened the questionnaire yet.',
            'subject' => 'Quick reminder: your cleaning business insurance quote',
            'body' => "Hi {first_name},\n\n"
                . "Just a quick reminder: we need a few details about your cleaning business before we can go to our insurers for quotes.\n\n"
                . "{link}\n\n"
                . "Most businesses finish it in about 4 to 5 minutes. If you would rather go through it over the phone, call us on {phone}.",
        ],
        'email_2_started' => [
            'label' => 'Email 2 — reminder (already started)',
            'type' => 'email',
            'note' => 'Used instead of email 2 when the client has started the questionnaire.',
            'subject' => 'Your insurance questionnaire is nearly there',
            'body' => "Hi {first_name},\n\n"
                . "You have made a start on your insurance questionnaire. Your answers are saved, so you can pick up exactly where you left off:\n\n"
                . "{link}\n\n"
                . "If you would rather finish it over the phone, call us on {phone}.",
        ],
        'sms_2' => [
            'label' => 'Text 2 — reminder',
            'type' => 'sms',
            'note' => 'First reminder by text.',
            'body' => "Hi {first_name}, a quick reminder from Polished Insurance: we need a few details to get your cleaning insurance quotes. {link}",
        ],
        'sms_2_started' => [
            'label' => 'Text 2 — reminder (already started)',
            'type' => 'sms',
            'note' => 'Used instead of text 2 when the client has started the questionnaire.',
            'body' => "Hi {first_name}, your Polished Insurance questionnaire is saved. Pick up where you left off: {link}",
        ],
        'email_3' => [
            'label' => 'Email 3 — last reminder',
            'type' => 'email',
            'note' => 'Final reminder. The enquiry closes automatically a few days later.',
            'subject' => 'Last reminder: your insurance quote request',
            'body' => "Hi {first_name},\n\n"
                . "This is our final reminder about your insurance enquiry. Without the questionnaire we cannot approach insurers on your behalf.\n\n"
                . "{link}\n\n"
                . "If you no longer need a quote, no problem, you do not need to do anything and we will close your enquiry in a few days. You are welcome to come back to us at any time.",
        ],
        'sms_3' => [
            'label' => 'Text 3 — last reminder',
            'type' => 'sms',
            'note' => 'Final reminder by text.',
            'body' => "Hi {first_name}, last reminder from Polished Insurance about your quote. Complete your details here: {link} or call {phone}. Reply STOP to opt out.",
        ],
        'email_submitted' => [
            'label' => 'Email — questionnaire received',
            'type' => 'email',
            'note' => 'Confirmation sent to the client as soon as they submit the questionnaire. {reference} is their enquiry reference.',
            'subject' => 'We have received your insurance questionnaire',
            'body' => "Hi {first_name},\n\n"
                . "Thank you, we have received your completed insurance questionnaire (reference {reference}).\n\n"
                . "One of our team will review your answers and approach our insurers. We will be in touch, usually within one working day, if we need anything else or once your quotes are ready.\n\n"
                . "If you need to change an answer, call us on {phone}.",
        ],
    ];
}

/** Stored (edited) wording, keyed msg_<key>_subject / msg_<key>_body. Never throws. */
function msg_overrides(bool $fresh = false): array {
    static $rows = null;
    if ($rows === null || $fresh) {
        $rows = [];
        try {
            // '#' escapes the underscore so it is a literal, not a single-character wildcard.
            foreach (db()->query("SELECT skey, svalue FROM setting WHERE skey LIKE 'msg#_%' ESCAPE '#'") as $r) {
                $rows[$r['skey']] = (string)$r['svalue'];
            }
        } catch (\Throwable $e) {
            error_log('Polished CRM: could not read message wording, using defaults — ' . $e->getMessage());
        }
    }
    return $rows;
}

/** The wording in use for one message: ['subject' => ?string, 'body' => string, 'edited' => bool]. */
function msg_template(string $key): array {
    $defaults = msg_defaults();
    if (!isset($defaults[$key])) throw new InvalidArgumentException("Unknown message $key");
    $d = $defaults[$key];
    $stored = msg_overrides();
    $subject = $stored['msg_' . $key . '_subject'] ?? ($d['subject'] ?? null);
    $body = $stored['msg_' . $key . '_body'] ?? $d['body'];
    $edited = isset($stored['msg_' . $key . '_body']) || isset($stored['msg_' . $key . '_subject']);
    return ['subject' => $subject, 'body' => $body, 'edited' => $edited];
}

/** Save edited wording (null body/subject removes the override and restores the default). */
function msg_save(string $key, ?string $subject, ?string $body): void {
    $pdo = db();
    foreach (['subject' => $subject, 'body' => $body] as $part => $value) {
        $skey = 'msg_' . $key . '_' . $part;
        if ($value === null || trim($value) === '') {
            $pdo->prepare('DELETE FROM setting WHERE skey = ?')->execute([$skey]);
            continue;
        }
        $pdo->prepare('DELETE FROM setting WHERE skey = ?')->execute([$skey]);
        $pdo->prepare('INSERT INTO setting (skey, svalue) VALUES (?, ?)')->execute([$skey, mb_substr(trim($value), 0, 5000)]);
    }
    msg_overrides(true);
}

function msg_first_name(array $lead): string {
    $f = trim((string)($lead['first_name'] ?? ''));
    return $f !== '' ? $f : 'there';
}

/** Placeholder values for one lead. */
function msg_vars(array $lead, string $link): array {
    return [
        'first_name' => msg_first_name($lead),
        'link' => $link,
        'phone' => (string)cfg('company_phone', '01942 403370'),
        'reference' => isset($lead['lead_id']) ? lead_ref((int)$lead['lead_id']) : '',
    ];
}

/** Plain-text version (used for the text part of emails and for SMS). */
function msg_fill_text(string $template, array $vars): string {
    return strtr($template, array_combine(array_map(fn($k) => '{' . $k . '}', array_keys($vars)), array_values($vars)));
}

/** HTML version: escaped, blank lines become paragraphs, {link} becomes a clickable link. */
function msg_fill_html(string $template, array $vars): string {
    $escaped = e($template);
    $map = [];
    foreach ($vars as $k => $v) {
        $map['{' . $k . '}'] = $k === 'link'
            ? '<a href="' . e($v) . '" style="color:#0b4fd0;font-weight:bold;word-break:break-all">' . e($v) . '</a>'
            : e($v);
    }
    $filled = strtr($escaped, $map);
    $paragraphs = preg_split('/\R{2,}/', trim($filled)) ?: [];
    return implode('', array_map(fn($p) => '<p>' . nl2br(trim($p)) . '</p>', $paragraphs));
}

function msg_email_wrap(string $innerHtml): string {
    $phone = e(cfg('company_phone', '01942 403370'));
    return '<div style="font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.55;color:#1f2733;max-width:600px">'
        // No images at all: plain text in a simple bordered card, which is the least likely to be
        // held back by spam filters or blocked by email software.
        . '<div style="border:1px solid #e2e8ef;border-top:3px solid #1664f0;padding:22px 24px;border-radius:8px">'
        . $innerHtml
        . '<p style="margin-top:26px">Kind regards,<br>The Polished Insurance team<br>'
        . "<a href=\"tel:" . preg_replace('/\s+/', '', $phone) . "\" style=\"color:#0a192f\">$phone</a></p>"
        . '</div>'
        . '<p style="font-size:11.5px;color:#8a97a5;margin-top:14px">Polished Insurance is a trading name of Allied Insurance Services Ltd, '
        . 'authorised and regulated by the Financial Conduct Authority (FRN 309497). Registered in England No. 4319831. '
        . '98 Standishgate, Wigan, WN1 1XA.</p></div>';
}

/** Builds one email from its template: ['subject', 'html', 'text']. */
function msg_build_email(string $key, array $lead, string $link): array {
    $t = msg_template($key);
    $vars = msg_vars($lead, $link);
    $text = msg_fill_text($t['body'], $vars) . "\n\nKind regards,\nThe Polished Insurance team\n" . $vars['phone'];
    return [
        'subject' => msg_fill_text((string)$t['subject'], $vars),
        'html' => msg_email_wrap(msg_fill_html($t['body'], $vars)),
        'text' => $text,
    ];
}

/** $n = 1, 2 or 3. A client who has started gets the "pick up where you left off" wording. */
function build_chase_email(array $lead, string $link, int $n = 1): array {
    $started = ($lead['q_status'] ?? '') === 'in_progress';
    $key = $n === 2 ? ($started ? 'email_2_started' : 'email_2') : ($n === 3 ? 'email_3' : 'email_1');
    return msg_build_email($key, $lead, $link);
}

function build_chase_sms(array $lead, string $link, int $n = 1): string {
    $started = ($lead['q_status'] ?? '') === 'in_progress';
    $key = $n === 2 ? ($started ? 'sms_2_started' : 'sms_2') : ($n === 3 ? 'sms_3' : 'sms_1');
    return msg_fill_text(msg_template($key)['body'], msg_vars($lead, $link));
}

/** Confirmation to the client after they submit the questionnaire. */
function build_submitted_email(array $lead): array {
    return msg_build_email('email_submitted', $lead, '');
}
