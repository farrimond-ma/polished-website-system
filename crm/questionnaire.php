<?php
/**
 * Staff view of the questionnaire, laid out like the client's questionnaire on the website.
 *  - Staff view (default): every question, including staff-only ones, with their badges.
 *  - Client view (?view=client): exactly what the client is asked, in their order — handy on a
 *    call. Answers still save either way.
 */
require __DIR__ . '/lib.php';
require_login();
$id = (int)param('id', 0);
$lead = find_lead($id);
if (!$lead) { flash('Lead not found.'); redirect('leads.php'); }

$view = param('view') === 'client' ? 'client' : 'staff';
$data = q_data_with_prefill($lead);
$json = fn($v) => json_encode($v, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

layout_header('Questionnaire ' . lead_ref($id));
echo "<link rel='stylesheet' href='" . asset('assets/questionnaire.css') . "'>";
?>
<div class="page-head">
  <div>
    <h1>Questionnaire · <span class="mono"><?= e(lead_ref($id)) ?></span> <?= e(lead_name($lead)) ?></h1>
    <div class="sub">Status: <strong><?= e(q_status_label($lead['q_status'])) ?></strong>
      <?= $lead['q_submitted_at'] ? ' · submitted ' . dt($lead['q_submitted_at']) : '' ?>
      · Changes save automatically.
      <?php if ($lead['q_status'] === 'in_progress'): ?> The client may be editing too — reload before making big changes.<?php endif; ?></div>
  </div>
  <div class="btn-row">
    <span class="view-switch">
      <a class="<?= $view === 'staff' ? 'on' : '' ?>" href="questionnaire.php?id=<?= $id ?>">Staff view</a>
      <a class="<?= $view === 'client' ? 'on' : '' ?>" href="questionnaire.php?id=<?= $id ?>&amp;view=client">Client view</a>
    </span>
    <a class="btn ghost" href="lead.php?id=<?= $id ?>">‹ Back to lead</a>
    <a class="btn ghost" href="questionnaire_print.php?id=<?= $id ?>" target="_blank">Print / PDF</a>
  </div>
</div>

<?php if ($view === 'client'): ?>
  <div class="client-view-note">You are seeing exactly what the client sees, in their order — staff-only questions are hidden.
    Anything you type here still saves to the lead. <a href="questionnaire.php?id=<?= $id ?>">Switch to staff view</a> for every question.</div>
<?php endif; ?>

<div class="qwrap">
  <div class="qbar">
    <div class="qbar-row">
      <div style="flex:1">
        <p class="qbar-kicker"><?= $view === 'client' ? 'Client view' : 'Staff view' ?> · <?= e(lead_ref($id)) ?></p>
        <div class="qprogress"><div id="qprogress-fill"></div></div>
      </div>
      <span id="save-status" class="save-status">All changes saved</span>
    </div>
    <nav id="progress-nav" class="progress-nav"></nav>
  </div>
  <div id="form-root"></div>
  <div class="card submit-bar">
    <div id="validation-summary" class="validation-summary"></div>
    <div class="submit-row">
      <span class="sub"><?= $view === 'client' ? 'The client submits this themselves; you can also mark it completed.' : 'Every question, including staff-only ones.' ?></span>
      <button id="submit-btn" type="button" class="btn primary" <?= $lead['q_status'] === 'submitted' ? 'style="display:none"' : '' ?>>Mark questionnaire completed</button>
    </div>
  </div>
</div>

<script>
  window.FORM_MODE = <?= $json($view) ?>;
  window.STAFF_SUBMIT = true;           // even in client view this is a staff page
  window.FORM_SCHEMA = <?= $json(q_schema()) ?>;
  window.FORM_DATA = <?= $json((object)$data) ?>;
  window.SAVE_URL = "questionnaire_save.php?id=<?= $id ?>";
  window.SUBMIT_URL = "questionnaire_save.php?id=<?= $id ?>&action=submit";
  window.CSRF_TOKEN = <?= $json(csrf_token()) ?>;
</script>
<script src="<?= asset('assets/questionnaire.js') ?>"></script>
<?php layout_footer();
