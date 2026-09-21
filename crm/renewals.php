<?php
/** Renewal Questionnaires: import existing clients from an Acturis export and send their questionnaire. */
require __DIR__ . '/lib.php';
require_once __DIR__ . '/inc/renewals.php';
require_login();
$me = (int)current_user()['user_id'];

$dir = __DIR__ . '/data';
$token = preg_replace('/[^a-f0-9]/', '', (string)param('file', ''));
$path = $token !== '' ? $dir . '/renewal_import_' . $token . '.csv' : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string)post('action', '');

    if ($action === 'upload') {
        $f = $_FILES['csv'] ?? null;
        if (!$f || ($f['error'] ?? 1) !== UPLOAD_ERR_OK) {
            flash('Please choose a CSV file to upload.');
            redirect('renewals.php');
        }
        if (($f['size'] ?? 0) > 8 * 1024 * 1024) { flash('That file is larger than 8MB — please export in smaller batches.'); redirect('renewals.php'); }
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $token = bin2hex(random_bytes(8));
        if (!@move_uploaded_file($f['tmp_name'], $dir . '/renewal_import_' . $token . '.csv')) {
            flash('The file could not be saved — check the data folder is writable.');
            redirect('renewals.php');
        }
        redirect('renewals.php?file=' . $token);
    }

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
$csv = $path !== '' && is_file($path) ? renewal_read_csv($path) : null;
$map = $csv ? (renewal_map() ?: renewal_guess_map($csv['headers'])) : renewal_map();
$search = trim((string)param('q', ''));
$leads = renewal_leads($search);
$today = date('Y-m-d');

layout_header('Renewal Questionnaires');
?>
<div class="page-head">
  <h1>Renewal Questionnaires</h1>
</div>

<?php if ($csv && !$csv['error']): ?>
  <!-- Step 2: match the export's columns to our fields -->
  <div class="card">
    <h2>Match the columns</h2>
    <p class="sub">Your file has <strong><?= count($csv['rows']) ?></strong> row(s) and <strong><?= count($csv['headers']) ?></strong> column(s).
      Tell us which column holds which detail — we remember this for next time, so you only do it once.
      Leave anything you do not have as “—”.</p>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="import"><input type="hidden" name="file" value="<?= e($token) ?>">
      <div class="table-scroll"><table class="grid small">
        <thead><tr><th>Field</th><th>Column in your file</th><th>Example from row 1</th></tr></thead>
        <tbody>
        <?php foreach ($fields as $id => [$label, $where, $note]): $sel = $map[$id] ?? ''; ?>
          <tr>
            <td><?= e($label) ?><?= $id === 'email' ? ' <span class="req" title="required">*</span>' : '' ?>
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
      <p class="sub">Clients already in the CRM (matched on email address) are updated, not duplicated, and anything
        a client has already answered themselves is kept.</p>
    </form>
  </div>
<?php else: ?>
  <?php if ($csv && $csv['error']): ?><div class="flash"><?= e($csv['error']) ?></div><?php endif; ?>
  <!-- Step 1: upload -->
  <div class="card">
    <h2>Import from Acturis</h2>
    <p class="sub">Export your existing clients from Acturis as a CSV file and upload it here. You will then match up the
      columns. Each client gets a questionnaire pre-filled with what we already know, so they only have to check it and
      fill in the gaps.</p>
    <form method="post" enctype="multipart/form-data" class="btn-row">
      <?= csrf_field() ?><input type="hidden" name="action" value="upload">
      <input type="file" name="csv" accept=".csv,text/csv" required>
      <button class="btn">Upload</button>
    </form>
    <?php if (renewal_map()): ?><p class="sub">A column mapping is saved from last time and will be filled in for you.</p><?php endif; ?>
  </div>
<?php endif; ?>

<div class="card">
  <div class="page-head" style="margin-bottom:8px">
    <h2 style="margin:0">Renewal clients (<?= count($leads) ?>)</h2>
    <form method="get" class="btn-row"><input name="q" value="<?= e($search) ?>" placeholder="Name, business or email"><button class="btn ghost small">Search</button></form>
  </div>
  <div class="table-scroll"><table class="grid small">
    <thead><tr><th>Renewal</th><th>Client</th><th>Email</th><th>Questionnaire</th><th>Reminders</th><th></th></tr></thead>
    <tbody>
    <?php if (!$leads): ?><tr><td colspan="6" class="empty">No renewal clients yet — import an Acturis export above.</td></tr><?php endif; ?>
    <?php foreach ($leads as $l): ?>
      <tr>
        <td class="<?= $l['renewal_date'] && $l['renewal_date'] < $today ? 'overdue' : '' ?>" style="white-space:nowrap"><?= d($l['renewal_date']) ?></td>
        <td><?= e(trim($l['first_name'] . ' ' . $l['last_name'])) ?><?= $l['company_name'] ? '<div class="sub">' . e($l['company_name']) . '</div>' : '' ?></td>
        <td class="sub"><?= e($l['email']) ?></td>
        <td><span class="pill q-<?= e($l['q_status']) ?>"><?= e(q_status_label($l['q_status'])) ?></span></td>
        <td><?= $l['chasing'] ? "<span class='pill chasing'>Chasing " . (int)$l['auto_chase_count'] . "/3</span>" : '<span class="sub">—</span>' ?></td>
        <td class="r"><a class="btn ghost small" href="lead.php?id=<?= (int)$l['lead_id'] ?>">Open</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <p class="sub">Open a client to send their questionnaire link and start the reminders.</p>
</div>
<?php layout_footer();
