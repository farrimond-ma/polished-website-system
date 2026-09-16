<?php
/** Messages: edit the wording of the emails and texts sent to clients. */
require __DIR__ . '/lib.php';
require_login();
if (!is_admin()) { flash('Only an administrator can change the message wording.'); redirect('index.php'); }

$defaults = msg_defaults();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $key = (string)post('key', '');
    if (!isset($defaults[$key])) { flash('Unknown message.'); redirect('messages.php'); }
    if (post('action') === 'restore') {
        msg_save($key, null, null);
        flash('“' . $defaults[$key]['label'] . '” restored to the original wording.');
    } else {
        $body = trim((string)post('body', ''));
        if ($body === '') { flash('The message cannot be empty — nothing was saved.'); redirect('messages.php#' . $key); }
        msg_save($key, isset($defaults[$key]['subject']) ? (string)post('subject', '') : null, $body);
        flash('“' . $defaults[$key]['label'] . '” saved. It will be used from the next message sent.');
    }
    redirect('messages.php#' . $key);
}

// Preview with a realistic example lead
$sample = ['lead_id' => 1, 'first_name' => 'Jane', 'q_status' => 'not_started'];
$sampleLink = rtrim((string)cfg('questionnaire_url', 'https://www.polished-insurance.co.uk/insurance-questionnaire'), '/')
    . '?t=' . str_repeat('a1b2c3d4', 6);

layout_header('Messages');
?>
<div class="page-head">
  <h1>Messages</h1>
</div>
<p class="sub" style="max-width:820px">
  The wording of every email and text message sent to clients. Changes are used from the next message sent — messages already
  sent are unaffected. Use these placeholders and they are filled in automatically:
  <strong>{first_name}</strong> the client’s first name (or “there”), <strong>{link}</strong> their personal questionnaire link,
  <strong>{phone}</strong> our phone number, <strong>{reference}</strong> their enquiry reference.
  Leave a blank line between paragraphs. Every email ends with “Kind regards, The Polished Insurance team” and the regulatory footer.
</p>

<?php foreach ($defaults as $key => $d): $t = msg_template($key); $vars = msg_vars($sample, $sampleLink); ?>
  <?php
    $isEmail = $d['type'] === 'email';
    $previewText = msg_fill_text($t['body'], $vars);
    $sms = !$isEmail ? $previewText : '';
    $segments = $sms === '' ? 0 : (int)ceil(mb_strlen($sms) / (preg_match('/[^\x00-\x7F]/', $sms) ? 70 : 160));
  ?>
  <div class="card mt" id="<?= e($key) ?>">
    <div class="section-head" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
      <h2 style="margin:0;font-size:18px"><?= e($d['label']) ?>
        <span class="pill <?= $t['edited'] ? '' : 'ok' ?>"><?= $t['edited'] ? 'Edited' : 'Original wording' ?></span>
      </h2>
      <?php if ($t['edited']): ?>
        <form method="post" onsubmit="return confirm('Restore the original wording for this message?')">
          <?= csrf_field() ?><input type="hidden" name="key" value="<?= e($key) ?>"><input type="hidden" name="action" value="restore">
          <button class="btn ghost small">Restore original</button>
        </form>
      <?php endif; ?>
    </div>
    <p class="sub"><?= e($d['note']) ?></p>

    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="key" value="<?= e($key) ?>">
      <?php if ($isEmail): ?>
        <label class="sub" for="subject_<?= e($key) ?>">Subject</label>
        <input id="subject_<?= e($key) ?>" name="subject" value="<?= e((string)$t['subject']) ?>" style="width:100%;padding:8px 10px;border:1px solid #cdd6df;border-radius:6px;margin-bottom:10px">
      <?php endif; ?>
      <label class="sub" for="body_<?= e($key) ?>"><?= $isEmail ? 'Message' : 'Text message' ?></label>
      <textarea id="body_<?= e($key) ?>" name="body" rows="<?= $isEmail ? 12 : 4 ?>" style="width:100%;padding:10px;border:1px solid #cdd6df;border-radius:6px;font:inherit"><?= e($t['body']) ?></textarea>
      <div class="btn-row"><button class="btn small">Save</button>
        <?php if (!$isEmail): ?><span class="sub"><?= mb_strlen($sms) ?> characters with an example name and link — <?= $segments ?> text message<?= $segments === 1 ? '' : 's' ?></span><?php endif; ?>
      </div>
    </form>

    <details>
      <summary class="sub">Preview (example: Jane, with a sample link)</summary>
      <?php if ($isEmail): ?>
        <p class="sub" style="margin:10px 0 4px"><strong>Subject:</strong> <?= e(msg_fill_text((string)$t['subject'], $vars)) ?></p>
        <iframe title="Email preview" style="width:100%;max-width:640px;height:520px;border:1px solid #e2e8ef;border-radius:8px;background:#fff"
                sandbox srcdoc="<?= e(msg_email_wrap(msg_fill_html($t['body'], $vars))) ?>"></iframe>
      <?php else: ?>
        <p style="background:#eef4ff;border:1px solid #d3e2fd;border-radius:10px;padding:12px 14px;max-width:420px;white-space:pre-wrap"><?= e($sms) ?></p>
      <?php endif; ?>
    </details>
  </div>
<?php endforeach; ?>
<?php layout_footer();
