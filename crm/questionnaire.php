<?php
/** Staff view of the full questionnaire (every question, including staff-only ones). */
require __DIR__ . '/lib.php';
require_login();
$id = (int)param('id', 0);
$lead = find_lead($id);
if (!$lead) { flash('Lead not found.'); redirect('leads.php'); }

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
    <a class="btn ghost" href="lead.php?id=<?= $id ?>">‹ Back to lead</a>
    <a class="btn ghost" href="questionnaire_print.php?id=<?= $id ?>" target="_blank">Print / PDF</a>
  </div>
</div>

<div class="qwrap">
  <nav id="progress-nav" class="progress-nav"></nav>
  <div id="form-root"></div>
  <div class="card submit-bar">
    <div id="validation-summary" class="validation-summary"></div>
    <div class="submit-row">
      <span id="save-status" class="save-status">All changes saved</span>
      <button id="submit-btn" type="button" class="btn" <?= $lead['q_status'] === 'submitted' ? 'style="display:none"' : '' ?>>Mark questionnaire completed</button>
    </div>
  </div>
</div>

<script>
  window.FORM_MODE = "staff";
  window.FORM_SCHEMA = <?= $json(q_schema()) ?>;
  window.FORM_DATA = <?= $json((object)$data) ?>;
  window.SAVE_URL = "questionnaire_save.php?id=<?= $id ?>";
  window.SUBMIT_URL = "questionnaire_save.php?id=<?= $id ?>&action=submit";
  window.CSRF_TOKEN = <?= $json(csrf_token()) ?>;
</script>
<script src="<?= asset('assets/questionnaire.js') ?>"></script>
<?php layout_footer();
