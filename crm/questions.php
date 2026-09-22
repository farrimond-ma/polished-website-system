<?php
/**
 * Questions — which questions clients are asked.
 *
 * Switching a question off here means no client is asked it from now on. Nothing is deleted:
 * answers already given stay on the record, and staff still see every question on the
 * questionnaire screen. To skip a question for one client only, use the tick beside it on their
 * own questionnaire instead.
 */
require __DIR__ . '/lib.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (post('action') === 'restore') {
        q_set_globally_hidden([]);
        flash('Every question is being asked again.');
        redirect('questions.php');
    }
    // The form posts the ids that are ASKED, so a box left unticked switches the question off.
    $asked = array_map('strval', (array)post('ask', []));
    $off = [];
    foreach (q_schema()['sections'] as $section) {
        foreach ($section['items'] as $it) {
            if (!q_is_input($it) || q_hidden_from_client($it)) continue;
            if (!in_array((string)$it['id'], $asked, true)) $off[] = (string)$it['id'];
        }
    }
    q_set_globally_hidden($off);
    flash($off ? count($off) . ' question(s) switched off for everyone.' : 'Every question is being asked.');
    redirect('questions.php');
}

$off = q_globally_hidden();
$schema = q_schema();
$search = trim((string)param('q', ''));

layout_header('Questions');
?>
<div class="page-head">
  <div>
    <h1>Questions</h1>
    <div class="sub">What clients are asked. Untick a question and no client is asked it from then on.
      Answers already given are kept, and staff still see every question.</div>
  </div>
  <div class="btn-row">
    <?php if ($off): ?>
      <form method="post" onsubmit="return confirm('Ask every question again?')"><?= csrf_field() ?>
        <input type="hidden" name="action" value="restore">
        <button class="btn ghost">Ask everything again</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php if ($off): ?>
  <div class="flash"><?= count($off) ?> question(s) are switched off: clients are not asked them.</div>
<?php endif; ?>

<form class="filters" method="get">
  <input name="q" value="<?= e($search) ?>" placeholder="Find a question">
  <button type="submit">Search</button>
  <?php if ($search): ?><a class="btn ghost small" href="questions.php">Clear</a><?php endif; ?>
</form>

<form method="post">
  <?= csrf_field() ?>
  <?php foreach ($schema['sections'] as $section): ?>
    <?php
      $rows = array_values(array_filter($section['items'], function ($it) use ($search) {
          if (!q_is_input($it) || q_hidden_from_client($it)) return false;
          return $search === '' || mb_stripos((string)($it['label'] ?? ''), $search) !== false;
      }));
      if (!$rows) continue;
    ?>
    <div class="card">
      <div class="page-head" style="margin-bottom:8px">
        <h2 style="margin:0"><?= e($section['title'] ?? $section['id']) ?></h2>
        <span class="sub"><?= count($rows) ?> question(s)</span>
      </div>
      <table class="grid small">
        <thead><tr><th style="width:90px">Asked?</th><th>Question</th><th style="width:130px">Type</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $it): $id = (string)$it['id']; $isOff = in_array($id, $off, true); ?>
          <tr class="<?= $isOff ? 'section-off' : '' ?>">
            <td>
              <label class="sub" style="display:flex;align-items:center;gap:6px">
                <input type="checkbox" name="ask[]" value="<?= e($id) ?>" <?= $isOff ? '' : 'checked' ?>>
                <?= $isOff ? 'No' : 'Yes' ?>
              </label>
            </td>
            <td><?= e((string)($it['label'] ?? $id)) ?>
              <div class="sub mono"><?= e($id) ?><?= !empty($it['required']) ? ' · required' : '' ?></div></td>
            <td class="sub"><?= e((string)($it['type'] ?? '')) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endforeach; ?>
  <div class="btn-row" style="position:sticky;bottom:0;background:#fff;padding:12px 0">
    <button class="btn">Save what clients are asked</button>
    <a class="btn ghost" href="questions.php">Cancel</a>
  </div>
</form>

<p class="sub">A question switched off here is skipped for everyone, including anyone part-way through
  their questionnaire. Required questions that are switched off never block a client from submitting.</p>
<?php layout_footer();
