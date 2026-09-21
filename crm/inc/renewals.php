<?php
/**
 * Renewal questionnaires — importing existing clients from an Acturis export.
 *
 * The export's column headings are whatever Acturis produces, so nothing is hard-coded: staff map
 * each column to a field once on the Renewal Questionnaires page and the mapping is remembered
 * (setting 'acturis_map'). Importing then creates (or updates) a lead per row with the contact
 * details and as much of the questionnaire as the export can fill in, ready to send to the client
 * to check and confirm.
 */
declare(strict_types=1);

/** Fields a column can be mapped to: id => [label, where, note]. 'where' is lead or questionnaire. */
function renewal_fields(): array {
    return [
        // Who the client is (also used to match an existing lead)
        'email'         => ['Email address', 'lead', 'Used to match clients already in the CRM.'],
        'first_name'    => ['Contact first name', 'lead', ''],
        'last_name'     => ['Contact last name', 'lead', ''],
        'full_name'     => ['Contact full name', 'lead', 'Use instead of first/last if the export has one name column.'],
        'company_name'  => ['Business name', 'lead', ''],
        'phone'         => ['Phone / mobile', 'lead', ''],
        'renewal_date'  => ['Renewal date', 'lead', 'Also fills the questionnaire renewal date.'],
        'policy_number' => ['Policy number', 'lead', 'Kept in the lead history for reference.'],
        // Questionnaire answers the export can pre-fill
        'insured_name'         => ['Name of insured', 'q', ''],
        'trading_names'        => ['Trading name(s)', 'q', ''],
        'address'              => ['Address', 'q', ''],
        'entity_status'        => ['Status of entity', 'q', 'Limited company, sole trader, partnership...'],
        'business_established' => ['Business established date', 'q', ''],
        'turnover'             => ['Total estimated turnover', 'q', ''],
        'manual_wages'         => ['Manual wageroll', 'q', ''],
        'clerical_wages'       => ['Clerical wageroll', 'q', ''],
        'bfsc_payments'        => ['Bona fide subcontractor payments', 'q', ''],
        'num_clerical'         => ['Number of clerical staff', 'q', ''],
        'num_manual_directors' => ['Number of manual directors/partners', 'q', ''],
        'num_manual_employees' => ['Number of manual employees', 'q', ''],
        'num_losc'             => ['Number of labour-only subcontractors', 'q', ''],
        'pl_limit'             => ['Public liability limit', 'q', ''],
        'el_limit'             => ['Employers liability limit', 'q', ''],
        'business_description' => ['Business description', 'q', ''],
        'prev_insurer_details' => ['Current insurer', 'q', ''],
        'claims_5yr'           => ['Claims in the last 5 years', 'q', 'Yes or No.'],
        'hr_max_height'        => ['Maximum working height (metres)', 'q', ''],
        'cw_tools'             => ['Tools and equipment sum insured', 'q', ''],
    ];
}

/* ===================== Acturis documents (.docx / .pdf), one client each ===================== */

/** Plain text from a Word .docx (paragraphs and table cells become lines). */
function renewal_docx_text(string $path): array {
    if (!class_exists('ZipArchive')) return ['text' => '', 'error' => 'This server cannot open .docx files (the PHP zip extension is missing).'];
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) return ['text' => '', 'error' => 'That file could not be opened — is it really a Word .docx?'];
    $xml = '';
    foreach (['word/document.xml', 'word/document2.xml'] as $entry) {
        $x = $zip->getFromName($entry);
        if ($x !== false) { $xml = $x; break; }
    }
    $zip->close();
    if ($xml === '') return ['text' => '', 'error' => 'No text was found in that Word file.'];
    // Keep the shape of the document: cell and paragraph ends become line breaks.
    $xml = preg_replace('#<w:tab[^>]*/>#', "\t", $xml);
    $xml = preg_replace('#</w:(p|tr)>#', "\n", $xml);
    $xml = preg_replace('#</w:tc>#', "\t", $xml);
    $text = html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
    $text = preg_replace("/[ \t]+/", ' ', $text);
    $text = preg_replace("/\n{3,}/", "\n\n", $text);
    return ['text' => trim($text), 'error' => null];
}

/** Plain text from a straightforward (text-based) PDF. Scanned PDFs cannot be read this way. */
function renewal_pdf_text(string $path): array {
    require_once __DIR__ . '/pdf_text.php';
    return pdf_text((string)file_get_contents($path));
}

/** Text from whichever document type was uploaded. */
function renewal_document_text(string $path, string $filename): array {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if ($ext === 'docx') return renewal_docx_text($path);
    if ($ext === 'pdf') return renewal_pdf_text($path);
    return ['text' => '', 'error' => 'Please upload a Word (.docx) or PDF document.'];
}

/**
 * What to look for in the document, per field: the wording before the value. Matching is
 * case-insensitive and allows a colon, spaces, tabs or a line break between label and value.
 */
function renewal_labels(): array {
    return [
        'email'                => ['email address', 'e-mail address', 'email'],
        'full_name'            => ['what is your name', 'contact name', 'contact', 'proposer', 'attention of'],
        'company_name'         => ['name of the business to be', 'name of insured', 'insured name', 'the insured',
                                   'client name', 'business name', 'insured'],
        'phone'                => ['mobile number', 'mobile', 'telephone number', 'telephone', 'contact number'],
        'renewal_date'         => ['period of insurance', 'renewal date', 'expiry date', 'renewal'],
        'policy_number'        => ['policy number', 'policy no', 'policy ref', 'policy document reference',
                                   'quotation reference'],
        'trading_names'        => ['trading name', 'trading as', 't/as'],
        'address'              => ['address of the business', 'postal address', 'correspondence address',
                                   'registered address', 'risk address', 'address'],
        'entity_status'        => ['what type of business do you operate', 'legal status', 'status of entity',
                                   'business type', 'constitution'],
        'business_established' => ['business established', 'established', 'trading since'],
        'business_description' => ['the business:', 'business description', 'what activities does your business undertake'],
        'prev_insurer_details' => ['insurer:', 'current insurer', 'present insurer'],
        'turnover'             => ['estimated annual turnover', 'estimated turnover', 'total turnover', 'turnover'],
        'manual_wages'         => ['annual wageroll', 'manual wageroll', 'manual wages', 'wageroll'],
        'clerical_wages'       => ['clerical wageroll', 'clerical wages'],
        'bfsc_payments'        => ['payments to bona', 'bona fide sub-contractor', 'bona fide subcontractor',
                                   'bfsc payments', 'bfsc'],
        'num_clerical'         => ['number of clerical', 'clerical employees', 'clerical staff'],
        'num_manual_directors' => ['manual directors', 'working directors', 'manual partners'],
        'num_manual_employees' => ['manual employees', 'number of manual', 'manual staff'],
        'num_losc'             => ['labour only sub-contractors', 'labour-only subcontractors', 'labour only', 'losc'],
        'pl_limit'             => ['public liability limit', 'limit do you require for public liability',
                                   'limit of indemnity - public', 'public & products liability', 'public liability'],
        'el_limit'             => ['employers liability limit', "employers' liability limit", 'employers liability',
                                   "employers' liability"],
        'claims_5yr'           => ['sustained any loss or damage', 'claims in the last 5 years', 'any claims'],
        'hr_max_height'        => ['maximum height you work at', 'maximum height worked', 'working at height'],
        'cw_tools'             => ['level of cover you need for all the', 'tools and equipment', 'plant, tools'],
    ];
}

/**
 * Pulls "label: value" pairs out of the document text. Returns [field => value] for whatever it
 * recognised — staff check and correct everything on screen before anything is saved.
 */
/** Tidies one value read out of a document: no label punctuation, no bullet, no stray spaces. */
function renewal_clean_value(string $value): string {
    $value = trim($value, " \t\r\n:?-|\xE2\x80\xA2");
    return trim((string)preg_replace('/\s+/u', ' ', $value));
}

/** Yes/No answers as the questionnaire stores them. */
function renewal_yesno_value(string $value): ?string {
    $v = strtolower(trim($value));
    if (preg_match('/^(yes|y|true|insured)\b/', $v)) return 'yes';
    if (preg_match('/^(no|n|false|not insured|none)\b/', $v)) return 'no';
    return null;
}

/** "Limited company" and the like matched to the choices the questionnaire offers. */
function renewal_entity_value(string $value): string {
    $v = strtolower($value);
    if (str_contains($v, 'limited liability partnership') || str_contains($v, 'llp')) return 'Limited liability partnership (LLP)';
    if (str_contains($v, 'partnership')) return 'Partnership';
    if (str_contains($v, 'sole trader') || str_contains($v, 'individual')) return 'Individual trading as (sole trader)';
    if (str_contains($v, 'limited') || str_contains($v, 'ltd')) return 'Limited company (LTD)';
    return $value;
}

/**
 * Reads a client's details out of an Acturis document.
 *
 * Acturis lays a quotation out as a question with its answer on the same line or just below it,
 * and a long question wraps onto two or three lines before the answer appears — so each label is
 * looked for on its own line first and then a few lines further down. Nothing is trusted blindly:
 * whatever is found is shown to staff to check before it is imported.
 */
function renewal_parse_document(string $text): array {
    $numeric = ['turnover', 'manual_wages', 'clerical_wages', 'bfsc_payments', 'pl_limit', 'el_limit',
                'num_clerical', 'num_manual_directors', 'num_manual_employees', 'num_losc',
                'hr_max_height', 'cw_tools', 'renewal_date', 'business_established'];
    $yesno = ['claims_5yr'];
    // Wording that means the line belongs to a different field, however well the label matches
    // ("Email Address" is not the postal address).
    $excludes = [
        'address'      => ['email', 'e-mail'],
        'company_name' => ['contact name'],
        'full_name'    => ['company name', 'business name', 'name of the business'],
        'phone'        => ['email', 'misuse', 'contact us on'],
        'num_clerical' => ['wageroll', 'wages'],
        'num_manual_employees' => ['wageroll', 'wages'],
        'num_losc'     => ['payment'],
        'manual_wages' => ['clerical'],
        'claims_5yr'   => ['we will not', 'we may', 'refuse all claims'],
    ];

    // Straighten out typographic quotes and dashes, and drop the page footers, which would
    // otherwise be read as an answer.
    $text = strtr($text, ["\xE2\x80\x99" => "'", "\xE2\x80\x98" => "'", "\xE2\x80\x9C" => '"',
                          "\xE2\x80\x9D" => '"', "\xE2\x80\x93" => '-', "\xE2\x80\x94" => '-',
                          "\xC2\xA0" => ' ']);
    $lines = array_map(fn($l) => trim((string)$l), preg_split('/\R/', $text) ?: []);
    foreach ($lines as $i => $line) {
        if (preg_match('/^page \d+ of \d+$/i', $line)) $lines[$i] = '';
    }
    $count = count($lines);

    // The next few lines of the same block (a blank line ends a block).
    $blockAfter = function (int $from, int $howMany) use ($lines, $count): array {
        $out = [];
        for ($j = $from + 1; $j < $count && count($out) < $howMany; $j++) {
            if ($lines[$j] === '') { if ($out) break; continue; }
            $out[] = [$j, $lines[$j]];
        }
        return $out;
    };

    $found = [];
    foreach (renewal_labels() as $field => $labels) {
        $needsDigits = in_array($field, $numeric, true);
        $needsYesNo = in_array($field, $yesno, true);
        // Does this look like the answer we are after, rather than more of the question?
        $usable = function (string $v) use ($field, $needsDigits, $needsYesNo): bool {
            if ($v === '' || mb_strlen($v) > 200) return false;
            if ($needsYesNo) return renewal_yesno_value($v) !== null;
            if ($needsDigits) return (bool)preg_match('/\d/', $v);
            if ($field === 'phone') return (bool)preg_match('/\d{6}/', preg_replace('/[^0-9]/', '', $v) ?? '');
            return true;
        };
        foreach ($labels as $label) {
            foreach ($lines as $i => $line) {
                if ($line === '') continue;
                foreach ($excludes[$field] ?? [] as $bad) {
                    if (mb_stripos($line, $bad) !== false) continue 2;
                }
                $pos = mb_stripos($line, $label);
                if ($pos === false) continue;

                // The answer sits after the label on the same line...
                $at = $i;
                $value = renewal_clean_value(mb_substr($line, $pos + mb_strlen($label)));
                if (!$usable($value)) {
                    // ...or below it, where a long question carries on for a line or two first.
                    $value = '';
                    foreach ($blockAfter($i, $needsYesNo ? 10 : 4) as [$j, $candidate]) {
                        if (str_ends_with($candidate, '?')) continue;          // still the question
                        $candidate = renewal_clean_value($candidate);
                        if ($usable($candidate)) { $value = $candidate; $at = $j; break; }
                        if (!$needsDigits && !$needsYesNo) break;
                    }
                }
                if (!$usable($value)) continue;

                // An address, or a description of the business, carries on over the lines beneath it.
                if (in_array($field, ['address', 'business_description'], true)) {
                    $join = $field === 'address' ? ', ' : ' ';
                    foreach ($blockAfter($at, 5) as [, $more]) {
                        $more = renewal_clean_value($more);
                        if ($more === '' || str_contains($more, '?')) break;
                        if ($field === 'address' && mb_strlen($more) > 60) break;
                        $value .= $join . $more;
                    }
                }
                $found[$field] = $value;
                break 2;
            }
        }
    }

    // "Period of Insurance: 12 October 2025 to 11 October 2026" — the renewal is the later date.
    if (isset($found['renewal_date']) && preg_match('/^(.*)\bto\b(.*)$/i', $found['renewal_date'], $m)) {
        if (renewal_date_value(trim($m[2]))) $found['renewal_date'] = trim($m[2]);
    }

    // Tidy the values we know the shape of
    foreach (['renewal_date', 'business_established'] as $f) {
        if (isset($found[$f])) {
            $d = renewal_date_value($found[$f]);
            if ($d) $found[$f] = $d; else unset($found[$f]);
        }
    }
    foreach (['turnover', 'manual_wages', 'clerical_wages', 'bfsc_payments', 'pl_limit', 'el_limit',
              'num_clerical', 'num_manual_directors', 'num_manual_employees', 'num_losc',
              'hr_max_height', 'cw_tools'] as $f) {
        if (isset($found[$f])) {
            $n = renewal_number_value($found[$f]);
            if ($n !== null) $found[$f] = $n; else unset($found[$f]);
        }
    }
    foreach ($yesno as $f) {
        if (isset($found[$f])) {
            $v = renewal_yesno_value($found[$f]);
            if ($v !== null) $found[$f] = $v; else unset($found[$f]);
        }
    }
    if (isset($found['entity_status'])) $found['entity_status'] = renewal_entity_value($found['entity_status']);

    if (isset($found['email']) && !filter_var($found['email'], FILTER_VALIDATE_EMAIL)) {
        // an email is often followed by other text on the same line
        if (preg_match('/[^\s,;]+@[^\s,;]+\.[a-z]{2,}/i', $found['email'], $m)) $found['email'] = $m[0];
        else unset($found['email']);
    }
    if (!isset($found['email']) && preg_match('/[^\s,;<>()]+@[^\s,;<>()]+\.[a-z]{2,}/i', $text, $m)) {
        $found['email'] = $m[0];   // fall back to the first email anywhere in the document
    }
    return $found;
}

/** Imports one client from checked values ([field => value]). Returns [leadId, 'added'|'updated']. */
function renewal_import_one(array $values, ?int $userId = null): array {
    // renewal_prepare_row() works on a row keyed by column name, so map field => field.
    $map = [];
    foreach (array_keys(renewal_fields()) as $f) $map[$f] = $f;
    $p = renewal_prepare_row($values, $map);
    if ($p['problems']) return [0, implode('; ', $p['problems'])];

    $st = db()->prepare('SELECT * FROM leads WHERE email = ? ORDER BY lead_id DESC LIMIT 1');
    $st->execute([$p['lead']['email']]);
    $existing = $st->fetch();
    if ($existing) {
        $leadId = (int)$existing['lead_id'];
        $data = q_lead_data($existing);
        foreach ($p['q'] as $k => $v) if (q_blank($data[$k] ?? null)) $data[$k] = $v;
        $fields = array_filter($p['lead'], fn($v) => $v !== '' && $v !== null);
        unset($fields['email']);
        $fields['q_data'] = json_encode($data, JSON_UNESCAPED_UNICODE);
        $fields['source'] = 'Renewal';
        touch_lead($leadId, $fields);
        add_note($leadId, 'Updated from an Acturis document.' . ($p['policy_number'] !== '' ? ' Policy ' . $p['policy_number'] . '.' : ''), $userId);
        return [$leadId, 'updated'];
    }
    db()->prepare('INSERT INTO leads (status, first_name, last_name, company_name, email, phone, source,
            renewal_date, q_data, link_token, next_follow_up, created_at, updated_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute(['New Enquiry', $p['lead']['first_name'], $p['lead']['last_name'], $p['lead']['company_name'],
            $p['lead']['email'], $p['lead']['phone'], 'Renewal', $p['lead']['renewal_date'] ?? null,
            json_encode($p['q'], JSON_UNESCAPED_UNICODE), new_link_token(),
            $p['lead']['renewal_date'] ?? date('Y-m-d'), now(), now()]);
    $leadId = (int)db()->lastInsertId();
    add_note($leadId, 'Imported from an Acturis document for renewal.' . ($p['policy_number'] !== '' ? ' Policy ' . $p['policy_number'] . '.' : ''), $userId);
    return [$leadId, 'added'];
}

/* ===================== Spreadsheet exports (CSV), many clients per file ===================== */

function renewal_map(): array {
    try {
        $v = db()->query("SELECT svalue FROM setting WHERE skey='acturis_map'")->fetchColumn();
        $m = $v ? json_decode((string)$v, true) : [];
        return is_array($m) ? $m : [];
    } catch (\Throwable $e) { return []; }
}

function renewal_map_save(array $map): void {
    $clean = [];
    foreach (renewal_fields() as $id => $_) {
        $col = trim((string)($map[$id] ?? ''));
        if ($col !== '') $clean[$id] = $col;
    }
    db()->prepare("DELETE FROM setting WHERE skey='acturis_map'")->execute();
    db()->prepare("INSERT INTO setting (skey, svalue) VALUES ('acturis_map', ?)")->execute([json_encode($clean)]);
}

/** Reads a CSV export: ['headers' => [...], 'rows' => [ [header => value], ... ], 'error' => ?string]. */
function renewal_read_csv(string $path, int $limit = 2000): array {
    $fh = @fopen($path, 'r');
    if (!$fh) return ['headers' => [], 'rows' => [], 'error' => 'The file could not be opened.'];
    $headers = fgetcsv($fh);
    if (!$headers) { fclose($fh); return ['headers' => [], 'rows' => [], 'error' => 'The file appears to be empty.']; }
    // Excel exports often start with a byte-order mark on the first heading
    $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$headers[0]);
    $headers = array_map(fn($h) => trim((string)$h), $headers);
    $rows = [];
    while (($line = fgetcsv($fh)) !== false && count($rows) < $limit) {
        if (count(array_filter($line, fn($v) => trim((string)$v) !== '')) === 0) continue; // blank line
        $row = [];
        foreach ($headers as $i => $h) $row[$h] = trim((string)($line[$i] ?? ''));
        $rows[] = $row;
    }
    fclose($fh);
    return ['headers' => $headers, 'rows' => $rows, 'error' => null];
}

/**
 * Best guess at which column belongs to which field, for a first-time mapping. Fields are matched
 * most-specific first, each column is used only once, and 'not' words stop obvious mix-ups such as
 * "Email Address" being taken for the postal address.
 */
function renewal_guess_map(array $headers): array {
    $hints = [
        'email'                => [['email', 'e-mail'], []],
        'policy_number'        => [['policy no', 'policy number', 'policy ref', 'policy'], []],
        'renewal_date'         => [['renewal date', 'renewal', 'expiry'], []],
        'first_name'           => [['first name', 'forename'], []],
        'last_name'            => [['last name', 'surname'], []],
        'full_name'            => [['contact name', 'contact'], ['email', 'phone', 'number']],
        'company_name'         => [['business name', 'company name', 'insured name', 'client name', 'company', 'client'], ['contact', 'trading']],
        'phone'                => [['mobile', 'phone', 'telephone', 'tel'], ['email']],
        'trading_names'        => [['trading'], []],
        'address'              => [['postal address', 'correspondence address', 'risk address', 'address'], ['email', 'e-mail']],
        'entity_status'        => [['legal status', 'entity', 'business type', 'constitution'], []],
        'business_established' => [['established', 'inception of business', 'trading since'], ['policy']],
        'turnover'             => [['turnover'], []],
        'manual_wages'         => [['manual wage', 'wageroll manual', 'manual roll'], []],
        'clerical_wages'       => [['clerical wage', 'wageroll clerical'], []],
        'bfsc_payments'        => [['bona fide', 'bfsc'], []],
        'num_clerical'         => [['clerical staff', 'clerical employees', 'no. clerical', 'number of clerical'], ['wage']],
        'num_manual_directors' => [['manual director', 'working director', 'manual partner'], ['wage']],
        'num_manual_employees' => [['manual employee', 'manual staff', 'number of manual'], ['wage', 'director']],
        'num_losc'             => [['labour only', 'labour-only', 'losc'], ['wage', 'payment']],
        'pl_limit'             => [['public liability limit', 'pl limit', 'public liability'], []],
        'el_limit'             => [['employers liability limit', 'el limit', 'employers liability'], []],
        'insured_name'         => [['insured name', 'insured'], ['email', 'address']],
    ];
    $map = [];
    $used = [];
    foreach ($hints as $field => [$words, $notWords]) {
        foreach ($words as $w) {                       // most specific wording first
            foreach ($headers as $h) {
                if (in_array($h, $used, true)) continue;
                $l = strtolower($h);
                if (!str_contains($l, $w)) continue;
                foreach ($notWords as $n) if (str_contains($l, $n)) continue 2;
                $map[$field] = $h;
                $used[] = $h;
                break 2;
            }
        }
    }
    return $map;
}

function renewal_value(array $row, array $map, string $field): string {
    $col = $map[$field] ?? '';
    return $col === '' ? '' : trim((string)($row[$col] ?? ''));
}

/** Dates in an export come in all shapes; store as YYYY-MM-DD when we can read them. */
function renewal_date_value(string $v): ?string {
    $v = trim($v);
    if ($v === '') return null;
    foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'd/m/y', 'j M Y', 'd M Y', 'Y/m/d'] as $fmt) {
        $d = DateTimeImmutable::createFromFormat($fmt, $v);
        if ($d && $d->format($fmt) === $v) return $d->format('Y-m-d');
    }
    $t = strtotime($v);
    return $t ? date('Y-m-d', $t) : null;
}

/** Money/number columns arrive as "£72,500.00" etc. */
function renewal_number_value(string $v): ?string {
    $clean = preg_replace('/[^0-9.\-]/', '', $v);
    return ($clean === '' || !is_numeric($clean)) ? null : (string)(0 + (float)$clean);
}

/**
 * Turns one export row into ['lead' => [...], 'q' => [...], 'problems' => [...]].
 * Nothing is written here — renewal_import() does that.
 */
function renewal_prepare_row(array $row, array $map): array {
    $lead = []; $q = []; $problems = [];

    $email = strtolower(renewal_value($row, $map, 'email'));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $problems[] = 'no valid email address';
    $lead['email'] = $email;

    $first = renewal_value($row, $map, 'first_name');
    $last = renewal_value($row, $map, 'last_name');
    if ($first === '' && ($full = renewal_value($row, $map, 'full_name')) !== '') {
        $parts = preg_split('/\s+/', $full, 2);
        $first = $parts[0]; $last = $parts[1] ?? '';
    }
    $lead['first_name'] = mb_substr($first, 0, 100);
    $lead['last_name'] = mb_substr($last, 0, 100);
    $lead['company_name'] = mb_substr(renewal_value($row, $map, 'company_name'), 0, 190);
    $lead['phone'] = mb_substr(renewal_value($row, $map, 'phone'), 0, 40);
    $renewal = renewal_date_value(renewal_value($row, $map, 'renewal_date'));
    if ($renewal) { $lead['renewal_date'] = $renewal; $q['renewal_date'] = $renewal; }

    // Questionnaire answers
    foreach (renewal_fields() as $field => [$label, $where, $note]) {
        if ($where !== 'q' || $field === 'renewal_date') continue;
        $raw = renewal_value($row, $map, $field);
        if ($raw === '') continue;
        if (in_array($field, ['turnover', 'manual_wages', 'clerical_wages', 'bfsc_payments', 'pl_limit', 'el_limit',
                              'num_clerical', 'num_manual_directors', 'num_manual_employees', 'num_losc',
                              'hr_max_height', 'cw_tools'], true)) {
            $n = renewal_number_value($raw);
            if ($n !== null) $q[$field] = $n;
            continue;
        }
        if ($field === 'claims_5yr') {
            $yn = renewal_yesno_value($raw);
            if ($yn !== null) $q[$field] = $yn;
            continue;
        }
        if ($field === 'entity_status') { $q[$field] = renewal_entity_value($raw); continue; }
        if ($field === 'business_established') {
            $d = renewal_date_value($raw);
            if ($d) $q[$field] = $d;
            continue;
        }
        $q[$field] = mb_substr($raw, 0, 300);
    }
    // Sensible extras the client can correct
    if (($q['insured_name'] ?? '') === '' && $lead['company_name'] !== '') $q['insured_name'] = $lead['company_name'];
    $contact = trim($lead['first_name'] . ' ' . $lead['last_name']);
    if ($contact !== '') $q['contact_name'] = $contact;
    if ($lead['phone'] !== '') $q['contact_phone'] = $lead['phone'];
    if ($email !== '') $q['insured_email'] = $email;
    if (!empty($q['el_limit'])) $q['el_required'] = 'yes';
    if (!empty($q['cw_tools'])) $q['cw_tools_req'] = 'yes';

    return ['lead' => $lead, 'q' => $q, 'problems' => $problems, 'policy_number' => renewal_value($row, $map, 'policy_number')];
}

/**
 * Imports prepared rows. Existing clients (matched on email) are updated rather than duplicated.
 * Returns ['added' => n, 'updated' => n, 'skipped' => [[row number, why], ...]].
 */
function renewal_import(array $rows, array $map, ?int $userId = null): array {
    $pdo = db();
    $added = 0; $updated = 0; $skipped = [];
    foreach ($rows as $i => $row) {
        $p = renewal_prepare_row($row, $map);
        if ($p['problems']) { $skipped[] = [$i + 2, implode('; ', $p['problems'])]; continue; }

        $st = $pdo->prepare('SELECT * FROM leads WHERE email = ? ORDER BY lead_id DESC LIMIT 1');
        $st->execute([$p['lead']['email']]);
        $existing = $st->fetch();

        if ($existing) {
            $leadId = (int)$existing['lead_id'];
            $data = q_lead_data($existing);
            // The export is the newer record, but anything the client has already answered wins.
            foreach ($p['q'] as $k => $v) if (q_blank($data[$k] ?? null)) $data[$k] = $v;
            $fields = array_filter($p['lead'], fn($v) => $v !== '' && $v !== null);
            unset($fields['email']);
            $fields['q_data'] = json_encode($data, JSON_UNESCAPED_UNICODE);
            $fields['source'] = 'Renewal';
            touch_lead($leadId, $fields);
            add_note($leadId, 'Updated from the Acturis renewal import.'
                . ($p['policy_number'] !== '' ? ' Policy ' . $p['policy_number'] . '.' : ''), $userId);
            $updated++;
        } else {
            $pdo->prepare('INSERT INTO leads (status, first_name, last_name, company_name, email, phone, source,
                    renewal_date, q_data, link_token, next_follow_up, created_at, updated_at)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute(['New Enquiry', $p['lead']['first_name'], $p['lead']['last_name'], $p['lead']['company_name'],
                    $p['lead']['email'], $p['lead']['phone'], 'Renewal', $p['lead']['renewal_date'] ?? null,
                    json_encode($p['q'], JSON_UNESCAPED_UNICODE), new_link_token(),
                    $p['lead']['renewal_date'] ?? date('Y-m-d'), now(), now()]);
            $leadId = (int)$pdo->lastInsertId();
            add_note($leadId, 'Imported from Acturis for renewal.'
                . ($p['policy_number'] !== '' ? ' Policy ' . $p['policy_number'] . '.' : ''), $userId);
            $added++;
        }
    }
    return ['added' => $added, 'updated' => $updated, 'skipped' => $skipped];
}

/** Renewal leads for the list, newest renewal date first. */
function renewal_leads(string $search = ''): array {
    $sql = "SELECT * FROM leads WHERE source = 'Renewal'";
    $args = [];
    if ($search !== '') {
        $sql .= ' AND (email LIKE ? OR company_name LIKE ? OR first_name LIKE ? OR last_name LIKE ?)';
        $args = array_fill(0, 4, '%' . $search . '%');
    }
    $sql .= ' ORDER BY (renewal_date IS NULL), renewal_date, lead_id DESC LIMIT 500';
    $st = db()->prepare($sql);
    $st->execute($args);
    return $st->fetchAll();
}
