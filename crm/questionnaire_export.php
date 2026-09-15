<?php
/** One lead's questionnaire as CSV (section, question, answer, field key) for keying into insurer systems. */
require __DIR__ . '/lib.php';
require_login();
$id = (int)param('id', 0);
$lead = find_lead($id);
if (!$lead) { flash('Lead not found.'); redirect('leads.php'); }
$data = q_data_with_prefill($lead);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . lead_ref($id) . '-questionnaire.csv"');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // Excel-friendly UTF-8
fputcsv($out, ['Section', 'Question', 'Answer', 'Field key', 'Acturis field']);

// Neutralise spreadsheet formula injection from client-typed text.
$safe = fn($s) => preg_match('/^[=+\-@\t\r]/', (string)$s) ? "'" . $s : (string)$s;

foreach (q_schema()['sections'] as $section) {
    foreach ($section['items'] as $it) {
        $type = $it['type'] ?? '';
        if ($type === 'heading' || $type === 'note') continue;
        if (!q_condition_met($it['showIf'] ?? null, $data)) continue;
        $acturis = ($it['acturis'] ?? true) !== false ? 'Yes' : 'No';
        if ($type === 'percent_group') {
            foreach ($it['fields'] as $f) {
                if (($data[$f['id']] ?? '') === '') continue;
                fputcsv($out, [$section['title'], $it['label'] . ' — ' . $f['label'], $safe($data[$f['id']] . '%'), $f['id'], $acturis]);
            }
            continue;
        }
        $v = $data[$it['id']] ?? null;
        if ($type === 'table') {
            foreach ((array)$v as $i => $row) {
                if (!is_array($row) || !array_filter($row, fn($x) => $x !== '')) continue;
                $cells = [];
                foreach ($it['columns'] as $c) $cells[] = $c['label'] . ': ' . ($row[$c['id']] ?? '');
                fputcsv($out, [$section['title'], $it['label'] . ' #' . ($i + 1), $safe(implode(' | ', $cells)), $it['id'], $acturis]);
            }
            continue;
        }
        $shown = q_display_value($it, $v);
        if ($shown === '') continue;
        fputcsv($out, [$section['title'], $it['label'], $safe($shown), $it['id'], $acturis]);
    }
}
fclose($out);
