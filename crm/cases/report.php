<?php
require __DIR__ . '/lib.php';
require_login();
$pdo = db();

/* ---------------- saved report templates ---------------- */
$templates = [];
try { $templates = $pdo->query("SELECT * FROM report_template ORDER BY name")->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) {}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_template') {
    csrf_check();
    $nm = trim((string)($_POST['tpl_name'] ?? ''));
    if ($nm !== '') {
        $fltr = []; $ff = (array)($_POST['f_field'] ?? []);
        foreach ($ff as $i => $fld) if ($fld !== '') $fltr[] = ['field'=>$fld, 'op'=>$_POST['f_op'][$i] ?? 'is', 'v'=>$_POST['f_val'][$i] ?? '', 'v2'=>$_POST['f_val2'][$i] ?? ''];
        $pdo->prepare("INSERT INTO report_template (name,report_type,columns,filters,created_by,created_at) VALUES (?,?,?,?,?,?)")
            ->execute([$nm, (string)($_POST['type'] ?? 'new_business'), json_encode((array)($_POST['cols'] ?? [])), json_encode($fltr), current_user()['user_id'] ?? null, date('Y-m-d H:i:s')]);
        header('Location: report.php?load=' . (int)$pdo->lastInsertId()); exit;
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_template') {
    csrf_check();
    $pdo->prepare("DELETE FROM report_template WHERE template_id=?")->execute([(int)($_POST['template_id'] ?? 0)]);
    header('Location: report.php'); exit;
}
$loadId = (int)($_GET['load'] ?? 0); $loaded = null;
foreach ($templates as $tpl) if ((int)$tpl['template_id'] === $loadId) { $loaded = $tpl; break; }

/* ---------------- field catalogue ----------------
   Each field: label, get($row) => value, money(optional) => format as £ in the table.
   Two datasets: 'policy' (a policy_term joined to case/client) and 'document'.  */
function client_name(array $r): string {
    if (!empty($r['joint_names'])) return $r['joint_names'];
    if (!empty($r['trading_name'])) return $r['trading_name'];
    return trim(($r['title'] ?? '') . ' ' . ($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')) ?: ($r['client_ref'] ?? '');
}
function policy_number(array $r): string {
    $mid = ($r['insurer_policy_number'] ?? '') !== '' ? $r['insurer_policy_number'] : ($r['case_id'] ?? '');
    return ($r['policy_no_prefix'] ?? 'ZCLP') . '/' . $mid . '/' . ($r['policy_no_suffix'] ?? '1');
}
$POLICY_FIELDS = [
 'case_id'         => ['Case / Policy ID', fn($r)=>$r['case_id']],
 'policy_number'   => ['Policy Number',    fn($r)=>policy_number($r)],
 'client_name'     => ['Client / Business', fn($r)=>client_name($r)],
 'trading_name'    => ['Trading name',     fn($r)=>$r['trading_name'] ?? ''],
 'contact_name'    => ['Contact name',     fn($r)=>trim(($r['first_name']??'').' '.($r['last_name']??''))],
 'postcode'        => ['Postcode',         fn($r)=>$r['postcode'] ?? ''],
 'email'           => ['Email',            fn($r)=>$r['email'] ?? ''],
 'phone'           => ['Phone',            fn($r)=>$r['phone_mobile'] ?: ($r['phone_landline'] ?? '')],
 'status'          => ['Status',           fn($r)=>$r['case_status'] ?? ''],
 'transaction'     => ['Transaction',      fn($r)=>$r['sequence_label'] ?: $r['transaction_type']],
 'transaction_type'=> ['Transaction type', fn($r)=>$r['transaction_type'] ?? ''],
 'inception_date'  => ['Inception',        fn($r)=>$r['inception_date'] ?? ''],
 'expiry_date'     => ['Expiry',           fn($r)=>$r['expiry_date'] ?? ''],
 'effective_date'  => ['Effective',        fn($r)=>$r['effective_date'] ?? ''],
 'created_at'      => ['Created',           fn($r)=>substr((string)($r['created_at']??''),0,10)],
 'pl_limit'        => ['PL limit',         fn($r)=>$r['pl_limit'] ?? 0, true],
 'el_limit'        => ['EL limit',         fn($r)=>$r['el_limit'] ?? 0, true],
 'net_premium'     => ['Net premium',      fn($r)=>(float)($r['total_premium']??0), true],
 'ipt'             => ['IPT (12%)',        fn($r)=>round((float)($r['total_premium']??0)*0.12,2), true],
 'gross_premium'   => ['Gross premium',    fn($r)=>round(((float)($r['total_premium']??0))*1.12*1.125,2), true],
 'adjustment'      => ['Adjustment (net)', fn($r)=>(float)($r['adjustment']??0), true],
 'agent'           => ['Agent',            fn($r)=>$r['agent_name'] ?? ''],
];
$DOC_FIELDS = [
 'case_id'      => ['Case / Policy ID', fn($r)=>$r['case_id']],
 'client_name'  => ['Client / Business', fn($r)=>client_name($r)],
 'policy_number'=> ['Policy Number',    fn($r)=>policy_number($r)],
 'doc_name'     => ['Document',          fn($r)=>$r['name'] ?? ''],
 'doc_type'     => ['Type',              fn($r)=>$r['doc_type'] ?? ''],
 'generated_at' => ['Issued',            fn($r)=>$r['generated_at'] ?? ''],
 'generated_by' => ['Issued by',         fn($r)=>$r['generated_by'] ?? ''],
];

$REPORTS = [
 'new_business'  => ['New Business',        'policy',  "t.transaction_type='New Business'", 'c.created_at',
                     ['case_id','client_name','policy_number','inception_date','expiry_date','net_premium','gross_premium']],
 'renewals'      => ['Renewals',            'policy',  "t.transaction_type='Renewal'", 't.inception_date',
                     ['case_id','client_name','policy_number','inception_date','expiry_date','net_premium']],
 'adjustments'   => ['Adjustments',         'policy',  "t.transaction_type='Adjustment'", 't.effective_date',
                     ['case_id','client_name','transaction','effective_date','adjustment']],
 'cancellations' => ['Cancellations',       'policy',  "t.transaction_type='Cancellation'", 't.effective_date',
                     ['case_id','client_name','effective_date','adjustment']],
 'current'       => ['All current policies','latest',  '', 't.expiry_date',
                     ['case_id','client_name','policy_number','status','inception_date','expiry_date','net_premium']],
 'documents'     => ['Documents issued',    'document','', 'd.generated_at',
                     ['case_id','client_name','doc_name','generated_at','generated_by']],
 'custom'        => ['Custom report',        'policy',  '', 'c.created_at',
                     ['case_id','client_name','policy_number','transaction_type','status','net_premium']],
 'bordereau'     => ['Insurer bordereau (movements)', 'bordereau', '', 't.effective_date', []],
];

// Filterable fields for the Custom report: key => [label, SQL expression, type].
$FILTER_FIELDS = [
 'case_id'          => ['Case ID',          'c.case_id', 'number'],
 'status'           => ['Status',           'c.status', 'text'],
 'transaction_type' => ['Transaction type', 't.transaction_type', 'text'],
 'client_name'      => ['Client / Business', "COALESCE(NULLIF(cl.trading_name,''),NULLIF(cl.joint_names,''),cl.last_name)", 'text'],
 'trading_name'     => ['Trading name',     'cl.trading_name', 'text'],
 'town'             => ['Town',             'cl.town', 'text'],
 'county'           => ['County',           'cl.county', 'text'],
 'postcode'         => ['Postcode',         'cl.postcode', 'text'],
 'email'            => ['Email',            'cl.email', 'text'],
 'inception_date'   => ['Inception date',   'DATE(t.inception_date)', 'date'],
 'expiry_date'      => ['Expiry date',      'DATE(t.expiry_date)', 'date'],
 'effective_date'   => ['Effective date',   'DATE(t.effective_date)', 'date'],
 'created_at'       => ['Created date',      'DATE(c.created_at)', 'date'],
 'net_premium'      => ['Net premium',      't.total_premium', 'number'],
 'adjustment'       => ['Adjustment (net)', 't.adjustment', 'number'],
 'pl_limit'         => ['PL limit',         "(SELECT limit_of_indemnity FROM cover_selection WHERE term_id=t.term_id AND part_code='B')", 'number'],
 'el_limit'         => ['EL limit',         "(SELECT limit_of_indemnity FROM cover_selection WHERE term_id=t.term_id AND part_code='A' AND included=1)", 'number'],
];
// operator label => [sql, arg count]
$OPS = ['is'=>['= ?',1], 'is not'=>['<> ?',1], 'contains'=>['LIKE ?',1], 'starts with'=>['LIKE ?',1],
        'greater than'=>['> ?',1], 'less than'=>['< ?',1], 'at least'=>['>= ?',1], 'at most'=>['<= ?',1],
        'between'=>['BETWEEN ? AND ?',2]];

$type   = $loaded ? (string)$loaded['report_type'] : (string)param('type', 'new_business');
if (!isset($REPORTS[$type])) $type = 'new_business';
[$label, $set, $filter, $dateCol, $defaults] = $REPORTS[$type];
$isDoc  = ($set === 'document');
$CAT    = $isDoc ? $DOC_FIELDS : $POLICY_FIELDS;
$from   = trim((string)param('from', ''));
$to     = trim((string)param('to', ''));
$cols   = $loaded ? (json_decode((string)$loaded['columns'], true) ?: $defaults) : ($_GET['cols'] ?? $defaults);
$cols   = array_values(array_intersect(array_keys($CAT), (array)$cols));  // sanitise + keep order
if (!$cols) $cols = $defaults;
$run    = param('run') !== null || param('export') !== null || $loaded !== null;

// ---- custom-report filters ----
$filters = [];
if ($loaded && !empty($loaded['filters'])) {
    $filters = json_decode((string)$loaded['filters'], true) ?: [];
} else {
    $ff = (array)($_GET['f_field'] ?? []); $fo = (array)($_GET['f_op'] ?? []);
    $fv = (array)($_GET['f_val'] ?? []);   $fv2 = (array)($_GET['f_val2'] ?? []);
    foreach ($ff as $i => $fld) {
        if ($fld === '') continue;
        $filters[] = ['field'=>$fld, 'op'=>$fo[$i] ?? 'is', 'v'=>$fv[$i] ?? '', 'v2'=>$fv2[$i] ?? ''];
    }
}
$filterWhere = ''; $filterArgs = [];
if ($type === 'custom' && $filters) {
    $clauses = [];
    foreach ($filters as $f) {
        $fld = $f['field'] ?? ''; $op = $f['op'] ?? 'is';
        if (!isset($FILTER_FIELDS[$fld]) || !isset($OPS[$op])) continue;
        $expr = $FILTER_FIELDS[$fld][1]; $v = $f['v'] ?? ''; $v2 = $f['v2'] ?? '';
        if ($op === 'contains')       { $clauses[] = "$expr LIKE ?";  $filterArgs[] = '%' . $v . '%'; }
        elseif ($op === 'starts with'){ $clauses[] = "$expr LIKE ?";  $filterArgs[] = $v . '%'; }
        elseif ($op === 'between')    { if ($v === '' || $v2 === '') continue; $clauses[] = "$expr BETWEEN ? AND ?"; $filterArgs[] = $v; $filterArgs[] = $v2; }
        else { if ($v === '') continue; $clauses[] = "$expr " . $OPS[$op][0]; $filterArgs[] = $v; }
    }
    $filterWhere = implode(' AND ', $clauses);
}

/* ---------------- build the dataset ---------------- */
function base_query(string $set, string $filter, string $dateCol, ?string $from, ?string $to, array &$args, string $extraWhere = '', array $extraArgs = []): string {
    if ($set === 'document') {
        $sql = "SELECT d.doc_id,d.doc_type,d.name,d.generated_at,u.display_name AS generated_by,
                       t.case_id,cl.*,c.insurer_policy_number,s.policy_no_prefix,s.policy_no_suffix
                FROM document d
                JOIN policy_term t ON t.term_id=d.term_id
                JOIN case_policy c ON c.case_id=t.case_id
                JOIN client cl ON cl.client_ref=c.client_ref
                JOIN scheme s ON s.scheme_id=c.scheme_id
                LEFT JOIN app_user u ON u.user_id=d.generated_by
                WHERE d.file_path IS NOT NULL";
    } else {
        $sql = "SELECT c.case_id,c.status AS case_status,c.created_at,c.insurer_policy_number,
                       cl.*, t.term_id,t.transaction_type,t.sequence_label,t.inception_date,t.expiry_date,
                       t.effective_date,t.total_premium,t.adjustment,
                       a.name AS agent_name,s.policy_no_prefix,s.policy_no_suffix,
                       (SELECT limit_of_indemnity FROM cover_selection WHERE term_id=t.term_id AND part_code='B') AS pl_limit,
                       (SELECT limit_of_indemnity FROM cover_selection WHERE term_id=t.term_id AND part_code='A' AND included=1) AS el_limit
                FROM case_policy c
                JOIN client cl ON cl.client_ref=c.client_ref
                JOIN policy_term t ON t.case_id=c.case_id
                JOIN scheme s ON s.scheme_id=c.scheme_id
                LEFT JOIN agent a ON a.agent_id=c.agent_id
                WHERE 1=1";
        if ($set === 'latest') $sql .= " AND t.term_id=(SELECT MAX(term_id) FROM policy_term WHERE case_id=c.case_id)";
        if ($filter) $sql .= " AND $filter";
    }
    if ($extraWhere !== '') { $sql .= " AND ($extraWhere)"; foreach ($extraArgs as $a) $args[] = $a; }
    if ($from !== '' && $dateCol) { $sql .= " AND DATE($dateCol) >= ?"; $args[] = $from; }
    if ($to   !== '' && $dateCol) { $sql .= " AND DATE($dateCol) <= ?"; $args[] = $to; }
    $sql .= " ORDER BY " . ($dateCol ?: 'c.case_id') . " DESC";
    return $sql;
}
// ---- insurer bordereau (fixed 39-column format) ----
$isBord = ($type === 'bordereau');
$bordRows = []; $bordHead = [];
if ($isBord) {
    require_once __DIR__ . '/bordereau_lib.php';
    $bordHead = bordereau_headers();
    if ($run) $bordRows = bordereau_rows($pdo, $from !== '' ? $from : '1900-01-01', $to !== '' ? $to : '2999-12-31');
    if (param('export') === 'csv' && $run) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="bordereau_' . date('Ymd') . '.csv"');
        $out = fopen('php://output', 'w'); fputcsv($out, $bordHead);
        foreach ($bordRows as $r) fputcsv($out, $r);
        fclose($out); exit;
    }
}

$rows = [];
if ($run && !$isBord) {
    $args = [];
    $st = $pdo->prepare(base_query($set, $filter, $dateCol, $from, $to, $args, $filterWhere, $filterArgs));
    $st->execute($args);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
}

/* ---------------- CSV export ---------------- */
if (param('export') === 'csv' && $run && !$isBord) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $type . '_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, array_map(fn($k)=>$CAT[$k][0], $cols));
    foreach ($rows as $r) {
        $line = [];
        foreach ($cols as $k) { $v = $CAT[$k][1]($r); $line[] = is_float($v) ? number_format($v,2,'.','') : $v; }
        fputcsv($out, $line);
    }
    fclose($out); exit;
}

cases_header('Reports');
?>
<div class="page-head"><h1>Reports</h1></div>
<?php if ($templates): ?>
<div class="saved-bar"><span class="sub">Saved reports:</span>
  <?php foreach ($templates as $tpl): ?>
    <a class="tpl <?= $loaded && (int)$loaded['template_id']===(int)$tpl['template_id']?'on':'' ?>" href="report.php?load=<?= (int)$tpl['template_id'] ?>"><?= e($tpl['name']) ?></a>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<form method="get" class="form">
  <div class="grid2">
    <label>Report<select name="type" onchange="this.form.submit()">
      <?php foreach ($REPORTS as $k=>$rp): ?><option value="<?= e($k) ?>" <?= $k===$type?'selected':'' ?>><?= e($rp[0]) ?></option><?php endforeach; ?>
    </select></label>
    <div></div>
    <label>From (date)<input type="date" name="from" value="<?= e($from) ?>"></label>
    <label>To (date)<input type="date" name="to" value="<?= e($to) ?>"></label>
  </div>
  <?php if ($type === 'custom'): ?>
  <h3>Filters <span class="sub">(all conditions must match)</span></h3>
  <div id="filters">
    <?php $frows = $filters; $frows[] = ['field'=>'','op'=>'is','v'=>'','v2'=>'']; $frows[] = ['field'=>'','op'=>'is','v'=>'','v2'=>'']; foreach ($frows as $f): ?>
    <div class="filter-row">
      <select name="f_field[]"><option value="">— field —</option>
        <?php foreach ($FILTER_FIELDS as $k=>$ffd): ?><option value="<?= e($k) ?>" <?= ($f['field']??'')===$k?'selected':'' ?>><?= e($ffd[0]) ?></option><?php endforeach; ?>
      </select>
      <select name="f_op[]"><?php foreach (array_keys($OPS) as $o): ?><option <?= ($f['op']??'is')===$o?'selected':'' ?>><?= e($o) ?></option><?php endforeach; ?></select>
      <input name="f_val[]" value="<?= e($f['v']??'') ?>" placeholder="value">
      <input name="f_val2[]" value="<?= e($f['v2']??'') ?>" placeholder="and… (between)">
    </div>
    <?php endforeach; ?>
  </div>
  <button type="button" class="btn ghost" onclick="var r=document.querySelector('.filter-row').cloneNode(true);r.querySelectorAll('input').forEach(i=>i.value='');document.getElementById('filters').appendChild(r);">+ Add filter</button>
  <?php endif; ?>
  <?php if ($isBord): ?>
    <p class="sub">This is a fixed 39-column insurer format. Set the effective-date range above, then Run report or Export CSV.</p>
  <?php else: ?>
  <h3>Columns <span class="sub">(tick to add/remove)</span></h3>
  <div class="colpick">
    <?php foreach ($CAT as $k=>$f): ?>
      <label class="chk"><input type="checkbox" name="cols[]" value="<?= e($k) ?>" <?= in_array($k,$cols,true)?'checked':'' ?>> <?= e($f[0]) ?></label>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <div class="form-actions">
    <button type="submit" name="run" value="1">Run report</button>
    <button type="submit" name="export" value="csv" formtarget="_blank">Export CSV</button>
  </div>
</form>

<div class="report-tools">
  <form method="post" class="save-tpl">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_template">
    <input type="hidden" name="type" value="<?= e($type) ?>">
    <?php foreach ($cols as $c) echo "<input type='hidden' name='cols[]' value='" . e($c) . "'>"; ?>
    <?php foreach ($filters as $f): ?>
      <input type="hidden" name="f_field[]" value="<?= e($f['field']??'') ?>"><input type="hidden" name="f_op[]" value="<?= e($f['op']??'is') ?>"><input type="hidden" name="f_val[]" value="<?= e($f['v']??'') ?>"><input type="hidden" name="f_val2[]" value="<?= e($f['v2']??'') ?>">
    <?php endforeach; ?>
    <input name="tpl_name" placeholder="Save current report as…" required>
    <button type="submit">Save report</button>
  </form>
  <?php if ($loaded): ?>
  <form method="post" onsubmit="return confirm('Delete this saved report?')">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete_template">
    <input type="hidden" name="template_id" value="<?= (int)$loaded['template_id'] ?>">
    <button type="submit" class="del">Delete “<?= e($loaded['name']) ?>”</button>
  </form>
  <?php endif; ?>
</div>

<?php if ($run && $isBord): ?>
  <p class="count"><?= count($bordRows) ?> movement(s) · <?= e($label) ?><?= $from||$to ? ' · ' . e($from ?: '…') . ' to ' . e($to ?: '…') : '' ?></p>
  <div style="overflow-x:auto">
  <table class="grid">
    <thead><tr><?php foreach ($bordHead as $h) echo '<th>'.e($h).'</th>'; ?></tr></thead>
    <tbody>
    <?php if (!$bordRows): ?><tr><td colspan="<?= count($bordHead) ?>" class="empty">No movements in this period.</td></tr><?php endif; ?>
    <?php foreach ($bordRows as $r): ?>
      <tr><?php foreach ($r as $i=>$v): ?><td<?= is_numeric($v) && $i>=10 ? ' class="r"':'' ?>><?= e((string)$v) ?></td><?php endforeach; ?></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
<?php elseif ($run): ?>
  <p class="count"><?= count($rows) ?> row(s) · <?= e($label) ?><?= $from||$to ? ' · ' . e($from ?: '…') . ' to ' . e($to ?: '…') : '' ?></p>
  <div style="overflow-x:auto">
  <table class="grid">
    <thead><tr><?php foreach ($cols as $k) echo '<th>'.e($CAT[$k][0]).'</th>'; ?></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr><td colspan="<?= count($cols) ?>" class="empty">No rows for this report / period.</td></tr><?php endif; ?>
    <?php foreach ($rows as $r): ?>
      <tr>
        <?php foreach ($cols as $k): $v = $CAT[$k][1]($r); $money = $CAT[$k][2] ?? false; ?>
          <td<?= $money?' class="r"':'' ?>><?= $money ? money($v) : e((string)$v) ?></td>
        <?php endforeach; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
<?php endif; ?>
<style>
 .colpick{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:4px 14px;margin:6px 0 4px}
 .chk{display:flex;align-items:center;gap:6px;font-weight:normal;font-size:13px;color:#31404e}
 .chk input{width:auto}
 .saved-bar{display:flex;flex-wrap:wrap;gap:6px;align-items:center;margin:8px 0}
 .saved-bar .tpl{background:#eef2f6;color:#0b2a3a;padding:4px 10px;border-radius:14px;font-size:12.5px}
 .saved-bar .tpl.on{background:var(--brand);color:#fff}
 .saved-bar .tpl:hover{text-decoration:none;filter:brightness(.97)}
 .report-tools{display:flex;gap:14px;align-items:center;margin:10px 0;flex-wrap:wrap}
 .report-tools form{display:flex;gap:8px;align-items:center;margin:0}
 .report-tools input{padding:8px 10px;border:1px solid #cdd6df;border-radius:6px;font-size:14px}
 .report-tools .del{background:#fff;color:#b42323;border:1px solid #f0b6b6}
 .filter-row{display:flex;gap:8px;margin-bottom:6px;flex-wrap:wrap}
 .filter-row select,.filter-row input{padding:7px 9px;border:1px solid #cdd6df;border-radius:6px;font-size:13px}
 .filter-row input{flex:1;min-width:120px}
</style>
<?php cases_footer();
