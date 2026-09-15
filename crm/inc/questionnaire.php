<?php
/**
 * Questionnaire schema helpers — shared by the staff editor, the client API, print and export.
 *
 * The schema (inc/schema.json) was copied from the SSR Questionnaire app and extended:
 *  - contact_name / contact_phone fields so a lead's contact details can be pre-filled
 *  - item.client = true marks an "Extra" (acturis:false) question the CLIENT should still see
 *  - showIf conditions may also be {"all":[...]}, {"any":[...]} or {"field":x,"in":[...]}
 *  - a whole section may carry showIf (e.g. Claims History only when claims_5yr = yes)
 *
 * The same visibility rules are implemented in assets/questionnaire.js (staff) and in the
 * website's React questionnaire — keep all three in step if you add a new condition type.
 */
declare(strict_types=1);

function q_schema(): array {
    static $schema = null;
    if ($schema === null) {
        $schema = json_decode((string)file_get_contents(__DIR__ . '/schema.json'), true);
        if (!is_array($schema)) throw new RuntimeException('inc/schema.json is not valid JSON.');
    }
    return $schema;
}

function q_is_input(array $item): bool {
    $t = $item['type'] ?? '';
    return $t !== '' && $t !== 'heading' && $t !== 'note';
}

/** Extra (non-Acturis) questions are hidden from clients unless flagged client:true. */
function q_hidden_from_client(array $item): bool {
    return ($item['acturis'] ?? true) === false && ($item['client'] ?? false) !== true;
}

/** Every answer key an item can write (percent groups write one key per sub-field). */
function q_item_keys(array $item): array {
    if (($item['type'] ?? '') === 'percent_group') {
        return array_map(fn($f) => $f['id'], $item['fields'] ?? []);
    }
    return isset($item['id']) ? [$item['id']] : [];
}

/** The schema as the client sees it: Extra questions and empty headings/sections removed. */
function q_client_schema(array $hiddenSections = []): array {
    $schema = q_schema();
    $out = ['title' => $schema['title'] ?? '', 'intro' => $schema['intro'] ?? '', 'sections' => []];
    foreach ($schema['sections'] as $section) {
        if (in_array($section['id'], $hiddenSections, true)) continue;
        $items = array_values(array_filter($section['items'], fn($it) => !q_hidden_from_client($it)));
        // drop headings left with no question beneath them
        $clean = [];
        foreach ($items as $i => $it) {
            if (($it['type'] ?? '') === 'heading') {
                $hasInput = false;
                for ($j = $i + 1; $j < count($items); $j++) {
                    if (($items[$j]['type'] ?? '') === 'heading') break;
                    if (q_is_input($items[$j])) { $hasInput = true; break; }
                }
                if (!$hasInput) continue;
            }
            $clean[] = $it;
        }
        if (!array_filter($clean, 'q_is_input')) continue;
        $section['items'] = $clean;
        $out['sections'][] = $section;
    }
    return $out;
}

/** Keys the client is allowed to read and write. */
function q_client_keys(array $hiddenSections = []): array {
    $keys = [];
    foreach (q_client_schema($hiddenSections)['sections'] as $section) {
        foreach ($section['items'] as $it) {
            foreach (q_item_keys($it) as $k) $keys[$k] = true;
        }
    }
    return $keys;
}

function q_num($v): float {
    return is_numeric($v) ? (float)$v : 0.0;
}

/** Evaluates a showIf condition against the answers (mirrors questionnaire.js). */
function q_condition_met($cond, array $data): bool {
    if (!$cond || !is_array($cond)) return true;
    if (isset($cond['all'])) {
        foreach ($cond['all'] as $c) if (!q_condition_met($c, $data)) return false;
        return true;
    }
    if (isset($cond['any'])) {
        foreach ($cond['any'] as $c) if (q_condition_met($c, $data)) return true;
        return false;
    }
    if (isset($cond['field'])) {
        $v = $data[$cond['field']] ?? null;
        if (isset($cond['in'])) return in_array($v, $cond['in'], true);
        return $v === ($cond['equals'] ?? null);
    }
    if (isset($cond['anyYes'])) {
        foreach ($cond['anyYes'] as $id) if (($data[$id] ?? null) === 'yes') return true;
        return false;
    }
    if (isset($cond['fieldGt0'])) return q_num($data[$cond['fieldGt0']] ?? 0) > 0;
    if (isset($cond['anyGt0'])) {
        foreach ($cond['anyGt0'] as $id) if (q_num($data[$id] ?? 0) > 0) return true;
        return false;
    }
    return true;
}

/** Contact details from the lead, keyed by questionnaire field, for pre-filling. */
function q_prefill_from_lead(array $lead): array {
    $person = trim(($lead['first_name'] ?? '') . ' ' . ($lead['last_name'] ?? ''));
    $pre = [
        'insured_name'  => trim((string)($lead['company_name'] ?? '')) !== '' ? $lead['company_name'] : $person,
        'contact_name'  => $person,
        'contact_phone' => $lead['phone'] ?? '',
        'insured_email' => $lead['email'] ?? '',
    ];
    if (!empty($lead['renewal_date'])) $pre['renewal_date'] = $lead['renewal_date'];
    return array_filter($pre, fn($v) => trim((string)$v) !== '');
}

function q_lead_data(array $lead): array {
    $data = json_decode((string)($lead['q_data'] ?? ''), true);
    return is_array($data) ? $data : [];
}

/** Answers with the lead's contact details filled into any still-empty contact field. */
function q_data_with_prefill(array $lead): array {
    $data = q_lead_data($lead);
    foreach (q_prefill_from_lead($lead) as $k => $v) {
        if (!isset($data[$k]) || $data[$k] === '') $data[$k] = $v;
    }
    return $data;
}

/**
 * Cleans an incoming answers object: only known keys (optionally restricted to $allowed),
 * scalars trimmed to a sane length, tables as arrays of flat string rows.
 */
function q_sanitise(array $incoming, ?array $allowed = null): array {
    $types = [];
    foreach (q_schema()['sections'] as $section) {
        foreach ($section['items'] as $it) {
            foreach (q_item_keys($it) as $k) $types[$k] = $it['type'] === 'percent_group' ? 'percent' : $it['type'];
        }
    }
    $out = [];
    foreach ($incoming as $k => $v) {
        if ($k === '_hidden_sections') continue; // staff-only, handled separately
        if (!isset($types[$k])) continue;
        if ($allowed !== null && !isset($allowed[$k])) continue;
        $type = $types[$k];
        if ($type === 'table') {
            if (!is_array($v)) continue;
            $rows = [];
            foreach (array_slice($v, 0, 50) as $row) {
                if (!is_array($row)) continue;
                $clean = [];
                foreach ($row as $ck => $cv) {
                    if (is_scalar($cv) && preg_match('/^[a-z0-9_]{1,40}$/', (string)$ck)) {
                        $clean[$ck] = mb_substr(trim((string)$cv), 0, 500);
                    }
                }
                $rows[] = $clean;
            }
            $out[$k] = $rows;
        } elseif ($type === 'checkbox') {
            if ($v === true || $v === 'true' || $v === 1 || $v === '1') $out[$k] = true;
        } elseif (is_scalar($v)) {
            $s = trim((string)$v);
            if ($s === '') continue;
            $out[$k] = mb_substr($s, 0, $type === 'textarea' ? 5000 : 300);
        }
    }
    return $out;
}

/** Visible required questions still unanswered (client scope), for server-side submit checks. */
function q_missing_required(array $data, bool $clientScope, array $hiddenSections = []): array {
    $schema = $clientScope ? q_client_schema($hiddenSections) : q_schema();
    $missing = [];
    foreach ($schema['sections'] as $section) {
        if (in_array($section['id'], $hiddenSections, true)) continue;
        if (!q_condition_met($section['showIf'] ?? null, $data)) continue;
        foreach ($section['items'] as $it) {
            if (!q_is_input($it) || empty($it['required'])) continue;
            if (!q_condition_met($it['showIf'] ?? null, $data)) continue;
            $v = $data[$it['id']] ?? null;
            if ($it['type'] === 'table' && is_array($v)) {
                $v = array_filter($v, fn($row) => is_array($row) && array_filter($row, fn($x) => $x !== '' && $x !== null));
            }
            if ($v === null || $v === '' || $v === []) $missing[] = $section['title'] . ': ' . $it['label'];
        }
    }
    return $missing;
}

/** Human-readable value for print/export. */
function q_display_value(array $item, $value): string {
    if ($value === null || $value === '' || $value === []) return '';
    switch ($item['type']) {
        case 'yesno':    return $value === 'yes' ? 'Yes' : ($value === 'no' ? 'No' : (string)$value);
        case 'checkbox': return $value ? 'Yes' : '';
        case 'currency': return is_numeric($value) ? '£' . number_format((float)$value, 0) : (string)$value;
        case 'percent':  return $value . '%';
        case 'date':     $t = strtotime((string)$value); return $t ? date('d/m/Y', $t) : (string)$value;
        default:         return is_array($value) ? json_encode($value) : (string)$value;
    }
}

/** Progress for the lead page: answered / total visible client questions. */
function q_progress(array $lead): array {
    $data = q_lead_data($lead);
    $hidden = $data['_hidden_sections'] ?? [];
    $total = 0; $answered = 0;
    foreach (q_client_schema($hidden)['sections'] as $section) {
        if (!q_condition_met($section['showIf'] ?? null, $data)) continue;
        foreach ($section['items'] as $it) {
            if (!q_is_input($it) || !q_condition_met($it['showIf'] ?? null, $data)) continue;
            $total++;
            foreach (q_item_keys($it) as $k) {
                if (isset($data[$k]) && $data[$k] !== '' && $data[$k] !== []) { $answered++; break; }
            }
        }
    }
    return ['answered' => $answered, 'total' => $total, 'pct' => $total ? (int)round($answered * 100 / $total) : 0];
}
