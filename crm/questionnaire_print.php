<?php
/** Printable answers (browser "Save as PDF"). Shows only questions that apply to the answers given. */
require __DIR__ . '/lib.php';
require_login();
$id = (int)param('id', 0);
$lead = find_lead($id);
if (!$lead) { flash('Lead not found.'); redirect('leads.php'); }
$data = q_data_with_prefill($lead);
$all = param('all') === '1';
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="robots" content="noindex">
<title><?= e(lead_ref($id) . ' questionnaire — ' . lead_name($lead)) ?></title>
<style>
  body{font-family:Arial,Helvetica,sans-serif;color:#1f2733;font-size:12.5px;margin:24px auto;max-width:820px;padding:0 16px}
  h1{font-size:20px;margin:0}h2{font-size:14px;background:#0a192f;color:#fff;padding:6px 10px;margin:22px 0 6px;border-radius:4px;break-after:avoid}
  h3{font-size:12.5px;margin:12px 0 4px;color:#0b4fd0}
  table{width:100%;border-collapse:collapse}td,th{border-bottom:1px solid #e2e8ef;padding:5px 6px;vertical-align:top;text-align:left}
  td.q{width:58%;color:#54626f}.empty{color:#b0b8c2}.sub{color:#6b7785}
  .bar{display:flex;justify-content:space-between;align-items:flex-end;border-bottom:3px solid #1664f0;padding-bottom:10px}
  .noprint{margin:10px 0}@media print{.noprint{display:none}body{margin:0}}
  table.inner th{background:#f4f6f9;font-size:11px}
</style></head><body>
<div class="noprint"><button onclick="print()">Print / Save as PDF</button>
  <a href="?id=<?= $id ?>&amp;all=<?= $all ? '0' : '1' ?>"><?= $all ? 'Hide' : 'Show' ?> unanswered / not applicable questions</a></div>
<div class="bar">
  <div><h1><?= e(q_schema()['title']) ?></h1><div class="sub"><?= e(lead_name($lead)) ?> · <?= e($lead['company_name']) ?></div></div>
  <div class="sub" style="text-align:right"><strong><?= e(lead_ref($id)) ?></strong><br>Status: <?= e(q_status_label($lead['q_status'])) ?><br><?= $lead['q_submitted_at'] ? 'Submitted ' . dt($lead['q_submitted_at']) : 'Printed ' . dt(now()) ?></div>
</div>
<?php foreach (q_schema()['sections'] as $section):
    $applies = q_condition_met($section['showIf'] ?? null, $data);
    if (!$applies && !$all) continue;
    ob_start();
    $rows = 0;
    foreach ($section['items'] as $it) {
        $visible = q_condition_met($it['showIf'] ?? null, $data);
        if (!$visible && !$all) continue;
        $type = $it['type'] ?? '';
        if ($type === 'heading') { echo '<tr><td colspan="2"><h3>' . e($it['text']) . '</h3></td></tr>'; continue; }
        if ($type === 'note') continue;
        if ($type === 'percent_group') {
            $parts = [];
            foreach ($it['fields'] as $f) if (isset($data[$f['id']]) && $data[$f['id']] !== '' && (float)$data[$f['id']] > 0) $parts[] = e($f['label']) . ': ' . e($data[$f['id']]) . '%';
            if (!$parts && !$all) continue;
            echo '<tr><td class="q">' . e($it['label']) . '</td><td>' . ($parts ? implode('<br>', $parts) : '<span class="empty">—</span>') . '</td></tr>';
            $rows++;
            continue;
        }
        $v = $data[$it['id']] ?? null;
        if ($type === 'table') {
            $filled = array_filter((array)$v, fn($r) => is_array($r) && array_filter($r, fn($x) => $x !== ''));
            if (!$filled && !$all) continue;
            echo '<tr><td colspan="2"><strong>' . e($it['label']) . '</strong><table class="inner"><tr>';
            foreach ($it['columns'] as $c) echo '<th>' . e($c['label']) . '</th>';
            echo '</tr>';
            foreach ($filled as $r) {
                echo '<tr>';
                foreach ($it['columns'] as $c) echo '<td>' . e(q_display_value(['type' => $c['type'] ?? 'text'], $r[$c['id']] ?? '')) . '</td>';
                echo '</tr>';
            }
            echo '</table></td></tr>';
            $rows++;
            continue;
        }
        $shown = q_display_value($it, $v);
        if ($shown === '' && !$all) continue;
        echo '<tr><td class="q">' . e($it['label']) . (q_hidden_from_client($it) ? ' <span class="sub">(staff)</span>' : '') . '</td><td>'
            . ($shown === '' ? '<span class="empty">—</span>' : nl2br(e($shown))) . '</td></tr>';
        $rows++;
    }
    $html = ob_get_clean();
    if (!$rows && !$all) continue;
?>
  <h2><?= e($section['title']) ?><?= $applies ? '' : ' (not applicable)' ?></h2>
  <table><?= $html ?></table>
<?php endforeach; ?>
<p class="sub" style="margin-top:28px">Polished Insurance is a trading name of Allied Insurance Services Ltd, authorised and regulated by the Financial Conduct Authority (FRN 309497).</p>
</body></html>
