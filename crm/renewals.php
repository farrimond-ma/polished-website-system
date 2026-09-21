<?php
/** Renewal Questionnaires: import existing clients from Acturis and send their questionnaire. */
require __DIR__ . '/lib.php';
require_once __DIR__ . '/inc/renewals.php';
require_login();
$me = (int)current_user()['user_id'];

$dir = __DIR__ . '/data';
$token = preg_replace('/[^a-f0-9]/', '', (string)param('file', ''));
$ext = in_array(param('ext'), ['docx', 'pdf', 'csv'], true) ? (string)param('ext') : '';
$path = ($token !== '' && $ext !== '') ? $dir . '/renewal_import_' . $token . '.' . $ext : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string)post('action', '');

    if ($action === 'upload') {
        $f = $_FILES['doc'] ?? null;
        if (!$f || ($f['error'] ?? 1) !== UPLOAD_ERR_OK) { flash('Please choose a file to upload.'); redirect('renewals.php'); }
        $ext = strtolower(pathinfo((string)($f['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($ext, ['docx', 'pdf', 'csv'], true)) { flash('Please upload a Word (.docx), PDF or CSV file.'); redirect('renewals.php'); }
        if (($f['size'] ?? 0) > 12 * 1024 * 1024) { flash('That file is larger than 12MB.'); redirect('renewals.php'); }
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $token = bin2hex(random_bytes(8));
        if (!@move_uploaded_file($f['tmp_name'], $dir . '/renewal_import_' . $token . '.' . $ext)) {
            flash('The file could not be saved — check the data folder is writable.');
            redirect('renewals.php');
        }
        redirect('renewals.php?file=' . $token . '&ext=' . $ext);
    }

    // One client, checked on screen after reading their document
    if ($action === 'import_one') {
        $values = [];
        foreach (array_keys(renewal_fields()) as $field) {
            $v = trim((string)post('f_' . $field, ''));
            if ($v !== '') $values[$field] = $v;
        }
        [$leadId, $result] = renewal_import_one($values, $me);
        if (!$leadId) { flash('Nothing was imported: ' . $result); redirect('renewals.php'); }
        if ($path !== '' && is_file($path)) @unlink($path);
        flash(($result === 'added' ? 'Case added' : 'Case updated') . ' — ' . lead_ref($leadId) . '. Open it to send the questionnaire.');
        redirect('lead.php?id=' . $leadId);
    }

    // A spreadsheet of many clients
    if ($action === 'import' && $path !== '' && is_file($path)) {
        renewal_map_save((array)post('map', []));
        $csv = renewal_read_csv($path);
        $r = renewal_import($csv['rows'], renewal_map(), $me);
        @unlink($path);
        $msg = "Imported {$r['added']} new client(s) and updated {$r['updated']}.";
        if ($r['skipped']) {
            $msg .= ' ' . count($r['skipped']) . ' row(s) skipped: '
                . implode(', ', array_map(fn($s) => "row {$s[0]} ({$s[1]})", array_slice($r['skipped'], 0, 5)));
        }
        flash($msg);
        redirect('renewals.php');
    }
    redirect('renewals.php');
}

$fields = renewal_fields();
$doc = null; $csv = null; $parsed = [];
if ($path !== '' && is_file($path)) {
    if ($ext === 'csv') {
        $csv = renewal_read_csv($path);
    } else {
        $doc = renewal_document_text($path, 'x.' . $ext);
        $parsed = $doc['error'] ? [] : renewal_parse_document($doc['text']);
    }
}
$map = $csv ? (renewal_map() ?: renewal_guess_map($csv['headers'])) : renewal_map();

layout_header('Import from Acturis');
?>
<div class="page-head">
  <div>
    <h1>Import from Acturis</h1>
    <div class="sub"><a href="cases.php">‹ Cases</a> · an imported client becomes a case, ready for their
      renewal questionnaire.</div>
  </div>
</div>

<?php if ($doc): ?>
  <!-- Word / PDF: one client, checked before importing -->
  <div class="card">
    <h2>Check the details</h2>
    <?php if ($doc['error']): ?>
      <div class="flash"><?= e($doc['error']) ?></div>
    <?php else: ?>
      <p class="sub">Read from the document — <strong><?= count($parsed) ?></strong> detail(s) recognised. Correct anything
        that is wrong and fill in anything missing, then import. Everything here pre-fills the client's questionnaire for
        them to check and confirm.</p>
      <?php if (!isset($parsed['email'])): ?>
        <p class="sub">An Acturis quotation does not carry the client's email address or phone number, so please add
          them — the email address is how we match them to the CRM and send their questionnaire.</p>
      <?php endif; ?>
    <?php endif; ?>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="import_one">
      <input type="hidden" name="file" value="<?= e($token) ?>"><input type="hidden" name="ext" value="<?= e($ext) ?>">
      <div class="grid2">
        <?php foreach ($fields as $id => [$label, $where, $note]): $v = $parsed[$id] ?? ''; ?>
          <div>
            <label for="f_<?= e($id) ?>" class="sub"><?= e($label) ?><?= $id === 'email' ? ' *' : '' ?>
              <?= isset($parsed[$id]) ? '<span class="pill ok">read from document</span>' : '' ?></label>
            <input id="f_<?= e($id) ?>" name="f_<?= e($id) ?>" value="<?= e($v) ?>" style="width:100%;padding:8px 10px;border:1px solid #cdd6df;border-radius:6px">
          </div>
        <?php endforeach; ?>
      </div>
      <div class="btn-row">
        <button class="btn">Import this client</button>
        <a class="btn ghost" href="renewals.php">Cancel</a>
      </div>
    </form>
    <?php if (!$doc['error']): ?>
      <details class="mt">
        <summary class="sub">Show the text read from the document</summary>
        <pre style="white-space:pre-wrap;background:#f8fafb;border:1px solid #e8edf2;border-radius:6px;padding:10px;max-height:340px;overflow:auto;font-size:12.5px"><?= e(mb_substr($doc['text'], 0, 6000)) ?></pre>
      </details>
    <?php endif; ?>
  </div>

<?php elseif ($csv && !$csv['error']): ?>
  <!-- Spreadsheet: match the columns once, then import every row -->
  <div class="card">
    <h2>Match the columns</h2>
    <p class="sub">Your file has <strong><?= count($csv['rows']) ?></strong> row(s) and <strong><?= count($csv['headers']) ?></strong> column(s).
      Tell us which column holds which detail — we remember this for next time. Leave anything you do not have as “—”.</p>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="import">
      <input type="hidden" name="file" value="<?= e($token) ?>"><input type="hidden" name="ext" value="csv">
      <div class="table-scroll"><table class="grid small">
        <thead><tr><th>Field</th><th>Column in your file</th><th>Example from row 1</th></tr></thead>
        <tbody>
        <?php foreach ($fields as $id => [$label, $where, $note]): $sel = $map[$id] ?? ''; ?>
          <tr>
            <td><?= e($label) ?><?= $id === 'email' ? ' <span class="req">*</span>' : '' ?>
              <?= $note ? '<div class="sub">' . e($note) . '</div>' : '' ?></td>
            <td>
              <select name="map[<?= e($id) ?>]">
                <option value="">—</option>
                <?php foreach ($csv['headers'] as $h): ?>
                  <option value="<?= e($h) ?>" <?= $sel === $h ? 'selected' : '' ?>><?= e($h) ?></option>
                <?php endforeach; ?>
              </select>
            </td>
            <td class="sub"><?= e(mb_substr((string)($csv['rows'][0][$sel] ?? ''), 0, 40)) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <div class="btn-row">
        <button class="btn">Import <?= count($csv['rows']) ?> row(s)</button>
        <a class="btn ghost" href="renewals.php">Cancel</a>
      </div>
    </form>
  </div>

<?php else: ?>
  <?php if ($csv && $csv['error']): ?><div class="flash"><?= e($csv['error']) ?></div><?php endif; ?>
  <div class="card">
    <h2>Import from Acturis</h2>
    <p class="sub">Upload the client's Acturis document — a Word file (.docx) or a PDF, one client per document. We read
      what we can from it, you check it on the next screen, and the client's questionnaire is pre-filled so they only have
      to confirm the details and fill the gaps. A CSV of many clients at once also works. Scanned documents (a photo or
      picture of the page) cannot be read.</p>
    <form method="post" enctype="multipart/form-data" class="btn-row">
      <?= csrf_field() ?><input type="hidden" name="action" value="upload">
      <input type="file" name="doc" accept=".docx,.pdf,.csv" required>
      <button class="btn">Upload</button>
    </form>
    <p class="sub">Clients already in the CRM are matched on email address and updated, never duplicated, and anything a
      client has answered themselves is kept.</p>
  </div>
<?php endif; ?>

<div class="card">
  <h2>Where imported clients go</h2>
  <p class="sub">Every client imported here becomes a case. <a href="cases.php">Open Cases</a> to see them all, sorted by
    renewal date, and to send a client their questionnaire.</p>
</div>
<?php layout_footer();
