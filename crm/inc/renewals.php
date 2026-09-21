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
    ];
}

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
                              'num_clerical', 'num_manual_directors', 'num_manual_employees', 'num_losc'], true)) {
            $n = renewal_number_value($raw);
            if ($n !== null) $q[$field] = $n;
            continue;
        }
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
