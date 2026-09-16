<?php
require __DIR__ . '/lib.php';
require __DIR__ . '/doc_engine.php';
require __DIR__ . '/doc_field_map.php';
require_login();

$DOCS = [
    'schedule'          => 'Schedule',
    'invoice'           => 'Invoice',
    'pl_certificate'    => 'PL Certificate',
    'el_certificate'    => 'EL Certificate',
    'twimc_letter'      => 'To Whom It May Concern',
    'adjustment'        => 'Adjustment Endorsement',
    'statement_of_fact' => 'Statement of Fact',
];
$DOCDIR = __DIR__ . '/documents';

/* ---- serve a previously saved document (login-gated) ---- */
if ($sid = (int)param('saved', 0)) {
    $st = db()->prepare("SELECT * FROM document WHERE doc_id=?");
    $st->execute([$sid]); $doc = $st->fetch();
    if ($doc && !empty($doc['file_path']) && is_file($DOCDIR . '/' . basename($doc['file_path']))) {
        echo file_get_contents($DOCDIR . '/' . basename($doc['file_path'])); exit;
    }
    http_response_code(404); exit('Saved document not found.');
}

$case_id = (int)($_GET['case'] ?? $_POST['case'] ?? 0);   // may arrive via GET or POST (save)
$doc     = (string)($_GET['doc'] ?? $_POST['doc'] ?? 'schedule');
if (!isset($DOCS[$doc])) $doc = 'schedule';

$st = db()->prepare("SELECT c.case_id, c.scheme_id, COALESCE(NULLIF(cl.trading_name,''),NULLIF(cl.joint_names,''),cl.client_ref) AS nm
                     FROM case_policy c JOIN client cl ON cl.client_ref=c.client_ref WHERE c.case_id=?");
$st->execute([$case_id]); $case = $st->fetch();
$termId = 0;
if ($case) {
    $t = db()->prepare("SELECT term_id FROM policy_term WHERE case_id=? ORDER BY term_id DESC LIMIT 1");
    $t->execute([$case_id]); $termId = (int)($t->fetchColumn() ?: 0);
}

$file = __DIR__ . "/templates/$doc.html";
$rendered = '';
if ($case && is_file($file)) $rendered = doc_render(file_get_contents($file), doc_build_data(db(), $case_id, $doc));

/* ---- save the rendered document to the case ---- */
$flashMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save' && $case && $termId) {
    csrf_check();
    if (!is_dir($DOCDIR)) @mkdir($DOCDIR, 0775, true);
    $stamp = date('Ymd_His');
    $fname = "{$case_id}_{$doc}_{$stamp}.html";
    // full standalone HTML snapshot
    $snapshot = "<!doctype html><html><head><meta charset='utf-8'><title>" . e($DOCS[$doc]) . " " . $case_id . "</title></head><body>" . $rendered . "</body></html>";
    if (@file_put_contents($DOCDIR . '/' . $fname, $snapshot) !== false) {
        $uid = current_user()['user_id'] ?? null;
        db()->prepare("INSERT INTO document (term_id,doc_type,name,generated_at,generated_by,file_path) VALUES (?,?,?,?,?,?)")
            ->execute([$termId, $DOCS[$doc], $DOCS[$doc] . ' ' . date('d/m/Y H:i'), date('Y-m-d H:i:s'), $uid, $fname]);
        $flashMsg = 'Saved to case.';
        // ---- PDF hook: if you add Dompdf to a vendor/ folder, convert $snapshot to PDF here ----
    } else {
        $flashMsg = 'Could not write to the documents folder — check it exists and is writable.';
    }
}

// documents already on file for this case
$onfile = [];
if ($termId) {
    $of = db()->prepare("SELECT doc_id,name,generated_at,file_path FROM document WHERE term_id=? AND file_path IS NOT NULL ORDER BY generated_at DESC");
    $of->execute([$termId]); $onfile = $of->fetchAll();
}
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($DOCS[$doc]) ?> — <?= $case ? e($case['nm']) : 'Document' ?></title>
<style>
  body{margin:0;background:#e9edf1;font-family:Arial,sans-serif}
  .toolbar{position:sticky;top:0;background:#0b2a3a;color:#fff;padding:10px 16px;display:flex;gap:12px;align-items:center;flex-wrap:wrap;z-index:5}
  .toolbar a{color:#cfe4ee;text-decoration:none}
  .toolbar select,.toolbar button{padding:7px 10px;border-radius:6px;border:0;font-size:14px}
  .toolbar button{background:#0aa2c0;color:#fff;font-weight:bold;cursor:pointer}
  .toolbar form{display:inline;margin:0}
  .toolbar .sp{margin-left:auto}
  .sheet{max-width:800px;margin:22px auto;background:#fff;padding:40px 46px;box-shadow:0 2px 12px rgba(0,0,0,.15)}
  .bar{max-width:800px;margin:14px auto 0;font-size:13px}
  .warn{color:#8a5a00;background:#fff3cd;border:1px solid #ffe08a;padding:8px 12px;border-radius:6px}
  .ok{color:#0a7a3d;background:#d8f5e3;border:1px solid #a9e5c0;padding:8px 12px;border-radius:6px;margin-bottom:8px}
  .onfile{background:#fff;border:1px solid #dbe3ea;border-radius:6px;padding:8px 12px;margin-bottom:8px}
  .onfile a{color:#0aa2c0}
  @media print{ .toolbar,.bar{display:none} body{background:#fff} .sheet{box-shadow:none;margin:0;max-width:none;padding:0} }
</style></head><body>
<div class="toolbar">
  <a href="case.php?id=<?= $case_id ?>">← Case <?= $case_id ?></a>
  <form method="get">
    <input type="hidden" name="case" value="<?= $case_id ?>">
    <select name="doc" onchange="this.form.submit()">
      <?php foreach ($DOCS as $k=>$label): ?>
        <option value="<?= e($k) ?>" <?= $k===$doc?'selected':'' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
  <span><?= $case ? e($case['nm']) : '' ?></span>
  <span class="sp"></span>
  <?php if ($case && $termId): ?>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="case" value="<?= $case_id ?>">
    <input type="hidden" name="doc" value="<?= e($doc) ?>">
    <button type="submit" name="action" value="save">💾 Save to case</button>
  </form>
  <?php endif; ?>
  <button type="button" onclick="window.print()">🖨 Print / Save as PDF</button>
</div>
<div class="bar">
  <?php if ($flashMsg) echo "<div class='ok'>" . e($flashMsg) . "</div>"; ?>
  <?php if ($onfile): ?>
    <div class="onfile"><strong>On file:</strong>
      <?php foreach ($onfile as $f): ?>
        <a href="document.php?saved=<?= (int)$f['doc_id'] ?>" target="_blank"><?= e($f['name']) ?></a><?= $f !== end($onfile) ? ' · ' : '' ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <?php if (!$case): ?><div class="warn">Case not found.</div>
  <?php else: ?><div class="warn">Live preview. Any field still shown as <code>[Name]</code> isn't mapped yet (edit <code>doc_field_map.php</code>).</div><?php endif; ?>
</div>
<?php if ($case): ?><div class="sheet"><?= $rendered ?></div><?php endif; ?>
</body></html>
