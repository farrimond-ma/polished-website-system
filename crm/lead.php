<?php
require __DIR__ . '/lib.php';
require_login();
$pdo = db();
$id = (int)param('id', 0);
$lead = find_lead($id);
if (!$lead) { flash('Record not found.'); redirect('leads.php'); }
$list = record_list($lead);
$word = record_word($lead);
$me = (int)current_user()['user_id'];
$back = 'lead.php?id=' . $id;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    switch ((string)post('action', '')) {
        case 'status':
            $new = (string)post('status', '');
            if (in_array($new, statuses(), true) && $new !== $lead['status']) {
                $fields = ['status' => $new];
                if (in_array($new, terminal_statuses(), true)) {
                    $fields += ['chasing' => 0, 'next_chase_date' => null, 'next_chase_window' => null, 'next_follow_up' => null];
                }
                touch_lead($id, $fields);
                add_note($id, "Status changed from {$lead['status']} to $new." . ($lead['chasing'] && isset($fields['chasing']) ? ' Automatic reminders stopped.' : ''), $me);
                flash("Status set to $new.");
            }
            break;
        case 'assign':
            touch_lead($id, ['assigned_to' => (int)post('assigned_to') ?: null]);
            add_note($id, 'Assigned to ' . user_name((int)post('assigned_to') ?: null) . '.', $me);
            flash('Assignment updated.');
            break;
        case 'follow_up':
            touch_lead($id, ['next_follow_up' => post('next_follow_up') ?: null]);
            flash('Follow-up date saved.');
            break;
        case 'start_chase':
            $r = start_questionnaire_chase($lead, 'Questionnaire link sent by ' . (current_user()['display_name'] ?: current_user()['username']) . ' (message 1 of 3)', $me);
            // Whoever sends the link looks after the client from here, unless someone already does.
            if ($r['ok'] && !$lead['assigned_to']) touch_lead($id, ['assigned_to' => $me]);
            flash($r['message']);
            break;
        case 'resend_email':
        case 'resend_sms':
            $channel = post('action') === 'resend_email' ? 'email' : 'sms';
            ensure_link_token($lead);
            $link = questionnaire_link($lead['link_token']);
            if ($channel === 'email') {
                if (trim($lead['email']) === '') { flash('No email address on file.'); break; }
                $m = build_chase_email($lead, $link, 1);
                $r = send_email($lead['email'], lead_name($lead), $m['subject'], $m['html'], $m['text']);
            } else {
                if (!is_mobile_number($lead['phone'])) { flash('No UK mobile number on file.'); break; }
                $r = send_sms($lead['phone'], build_chase_sms($lead, $link, 1));
            }
            add_note($id, "Questionnaire link re-sent by $channel: " . ($r['ok'] ? 'sent.' : 'FAILED — ' . $r['error']), $me);
            flash($r['ok'] ? "Link sent by $channel." : "Could not send: {$r['error']}");
            break;
        case 'custom_email':
            $subject = trim((string)post('subject', ''));
            $body = trim((string)post('body', ''));
            if (trim($lead['email']) === '') { flash('No email address on file for this client.'); break; }
            if ($subject === '' || $body === '') { flash('Please fill in both the subject and the message.'); break; }
            $subject = mb_substr($subject, 0, 200);
            $body = mb_substr($body, 0, 5000);
            $link = questionnaire_link(ensure_link_token($lead));
            $m = build_custom_email($lead, $subject, $body, $link);
            $r = send_email($lead['email'], lead_name($lead), $m['subject'], $m['html'], $m['text']);
            // The history keeps what the client actually received, with the placeholders filled in.
            $sentBody = msg_fill_text($body, msg_vars($lead, $link));
            add_note($id, ($r['ok'] ? 'Email sent to ' : 'Email FAILED to ') . $lead['email']
                . ($r['ok'] ? '' : ' (' . $r['error'] . ')') . "\nSubject: " . $m['subject'] . "\n\n" . $sentBody, $me);
            flash($r['ok'] ? 'Email sent and saved to the history.' : 'Could not send: ' . $r['error']);
            break;
        case 'stop_chase':
            stop_chasing($id);
            add_note($id, 'Automatic reminders stopped.', $me);
            flash('Automatic reminders stopped.');
            break;
        case 'regenerate_link':
            touch_lead($id, ['link_token' => new_link_token()]);
            add_note($id, 'Questionnaire link regenerated — the old link no longer works.', $me);
            flash('New link created. The previous link has stopped working.');
            break;
        case 'reopen':
            touch_lead($id, ['q_status' => 'in_progress', 'q_submitted_at' => null]);
            add_note($id, 'Questionnaire reopened so the client can make changes.', $me);
            flash('Questionnaire reopened — the client can edit it again using their link.');
            break;
        case 'mark_submitted':
            $fields = ['q_status' => 'submitted', 'q_submitted_at' => now(), 'chasing' => 0, 'next_chase_date' => null, 'next_chase_window' => null];
            if (in_array($lead['status'], pre_questionnaire_statuses(), true)) $fields['status'] = 'Questionnaire Completed';
            touch_lead($id, $fields);
            add_note($id, 'Questionnaire marked as completed by staff.', $me);
            flash('Questionnaire marked as completed.');
            break;
        case 'link_policy':
            $policyId = (int)post('policy_case_id', 0);
            if (!$policyId) { flash('Please choose a policy first.'); break; }
            if ($other = policy_linked_elsewhere($policyId, $id)) {
                flash('That policy is already linked to ' . lead_ref($other) . '.');
                break;
            }
            touch_lead($id, ['policy_case_id' => $policyId, 'is_case' => 1]);
            add_note($id, 'Linked to policy ' . $policyId . '.', $me);
            flash('Policy linked.');
            break;
        case 'unlink_policy':
            touch_lead($id, ['policy_case_id' => null]);
            add_note($id, 'Policy unlinked.', $me);
            flash('Policy unlinked — the policy itself is untouched.');
            break;
        case 'note':
            $body = trim((string)post('body', ''));
            if ($body !== '') { add_note($id, $body, $me); flash('Note added.'); }
            break;
        case 'task':
            $body = trim((string)post('body', ''));
            if ($body !== '' && post('due_date')) {
                $pdo->prepare('INSERT INTO lead_task (lead_id, body, due_date, assigned_to, created_by, created_at) VALUES (?,?,?,?,?,?)')
                    ->execute([$id, mb_substr($body, 0, 500), post('due_date'), (int)post('assigned_to') ?: null, $me, now()]);
                flash('Task added.');
            }
            break;
        case 'task_done':
            $pdo->prepare('UPDATE lead_task SET done_at = ? WHERE task_id = ? AND lead_id = ?')->execute([now(), (int)post('task_id'), $id]);
            flash('Task completed.');
            break;
        case 'delete':
            if (!is_admin()) { flash('Admins only.'); break; }
            $pdo->prepare('DELETE FROM lead_note WHERE lead_id = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM lead_task WHERE lead_id = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM leads WHERE lead_id = ?')->execute([$id]);
            flash(lead_ref($id) . ' and all its data have been deleted.');
            redirect($list);
    }
    redirect($back);
}

$notes = $pdo->prepare('SELECT n.*, u.display_name, u.username FROM lead_note n LEFT JOIN app_user u ON u.user_id = n.created_by WHERE n.lead_id = ? ORDER BY n.note_id DESC');
$notes->execute([$id]);
$notes = $notes->fetchAll();
$tasks = $pdo->prepare('SELECT * FROM lead_task WHERE lead_id = ? ORDER BY done_at IS NOT NULL, due_date');
$tasks->execute([$id]);
$tasks = $tasks->fetchAll();

$qData = q_lead_data($lead);
$hiddenSections = array_values(array_filter((array)($qData['_hidden_sections'] ?? []), 'is_string'));
$sectionsAll = count(q_client_schema([])['sections'] ?? []);
$sectionsShown = count(q_client_schema($hiddenSections)['sections'] ?? []);

$isCase = !empty($lead['is_case']);
$policy = $isCase ? policy_for_case($lead['policy_case_id'] ? (int)$lead['policy_case_id'] : null) : null;
$policyFind = $isCase && !$policy ? policy_search(trim((string)param('findpolicy', ''))) : [];

$link = $lead['link_token'] ? questionnaire_link($lead['link_token']) : '';
$progress = q_progress($lead);
$today = date('Y-m-d');

layout_header(lead_ref($id) . ' ' . lead_name($lead));
?>
<div class="page-head">
  <div>
    <h1><span class="mono"><?= e(lead_ref($id)) ?></span> · <?= e(lead_name($lead)) ?></h1>
    <div class="sub"><a href="<?= e($list) ?>">&lsaquo; <?= e($word === 'Case' ? 'Cases' : 'Leads') ?></a>
      · <?= e($lead['company_name']) ?> · <?= e($lead['source']) ?>
      · <?= $word === 'Case' ? 'client since ' : 'received ' ?><?= dt($lead['created_at']) ?>
      <?php if ($word === 'Case' && $lead['renewal_date']): ?> · renews <?= d($lead['renewal_date']) ?><?php endif; ?></div>
  </div>
  <div>
    <span class="pill pill-lg <?= status_class($lead['status']) ?>"><?= e($lead['status']) ?></span>
    <?php if ($lead['chasing']): ?><span class="pill pill-lg chasing">Chasing <?= (int)$lead['auto_chase_count'] ?>/3</span><?php endif; ?>
    <?php if (!empty($lead['prefers_call'])): ?><span class="pill pill-lg call" title="Asked to be called rather than sent the questionnaire link">Wants a call</span><?php endif; ?>
  </div>
</div>

<?php if ($isCase): ?>
  <!-- The policy behind this case: cover, premiums, adjustments and documents. -->
  <div class="card">
    <div class="page-head" style="margin-bottom:8px">
      <h2 style="margin:0">Policy</h2>
      <?php if ($policy): ?>
        <div class="btn-row">
          <a class="btn ghost small" href="cases/case.php?id=<?= (int)$policy['case_id'] ?>">Open policy</a>
          <a class="btn ghost small" href="cases/adjust.php?case=<?= (int)$policy['case_id'] ?>">Adjust (MTA)</a>
          <a class="btn ghost small" href="cases/document.php?case=<?= (int)$policy['case_id'] ?>">Documents</a>
        </div>
      <?php endif; ?>
    </div>

    <?php if ($policy): ?>
      <dl>
        <dt>Policy number</dt><dd class="mono"><?= e(policy_number($policy)) ?></dd>
        <dt>Policyholder</dt><dd><?= e(policy_client_name($policy)) ?><?= $policy['postcode'] ? ' · ' . e((string)$policy['postcode']) : '' ?></dd>
        <dt>Scheme</dt><dd><?= e((string)($policy['scheme_name'] ?? '—')) ?></dd>
        <dt>Status</dt><dd><?= e((string)$policy['status']) ?><?= $policy['sequence_label'] ? ' · ' . e((string)$policy['sequence_label']) : '' ?></dd>
        <dt>Period</dt><dd><?= d($policy['inception_date']) ?> to <?= d($policy['expiry_date']) ?></dd>
        <dt>Premium</dt><dd><?= $policy['total_premium'] === null ? '—' : '£' . number_format((float)$policy['total_premium'], 2) ?></dd>
      </dl>
      <form method="post" class="btn-row" onsubmit="return confirm('Unlink this policy from the case? The policy itself is not changed.')">
        <?= csrf_field() ?><input type="hidden" name="action" value="unlink_policy">
        <button class="btn ghost small">Unlink</button>
      </form>

    <?php elseif (!policy_tables_ready()): ?>
      <p class="sub">The policy records are not set up on this server yet. Open
        <a href="cases/index.php">Policies &amp; documents</a> once to create them, then link this case to its policy.</p>

    <?php else: ?>
      <p class="sub">This case is not linked to a policy yet. Find it by business name, policy number or postcode —
        then cover, adjustments and documents are one click from here.</p>
      <form method="get" class="filters">
        <input type="hidden" name="id" value="<?= $id ?>">
        <input name="findpolicy" value="<?= e((string)param('findpolicy', '')) ?>" placeholder="Business name, policy number or postcode">
        <button type="submit">Find policy</button>
      </form>
      <?php if ($policyFind): ?>
        <table class="grid small">
          <thead><tr><th>Policy</th><th>Policyholder</th><th>Expires</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($policyFind as $row): ?>
            <tr>
              <td class="mono"><?= e(policy_number($row)) ?></td>
              <td><?= e(policy_client_name($row)) ?><?= $row['postcode'] ? '<div class="sub">' . e((string)$row['postcode']) . '</div>' : '' ?></td>
              <td><?= d($row['expiry_date']) ?></td>
              <td class="r">
                <form method="post"><?= csrf_field() ?>
                  <input type="hidden" name="action" value="link_policy">
                  <input type="hidden" name="policy_case_id" value="<?= (int)$row['case_id'] ?>">
                  <button class="btn small">Link</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php elseif (param('findpolicy', '') !== ''): ?>
        <p class="sub">No policy matched that. Check the Cases data extract has been imported.</p>
      <?php endif; ?>
    <?php endif; ?>
  </div>
<?php endif; ?>

<div class="cols">
  <div class="col">
    <div class="card">
      <h2>Questionnaire</h2>
      <div class="q-summary">
        <span class="pill pill-lg q-<?= e($lead['q_status']) ?>"><?= e(q_status_label($lead['q_status'])) ?></span>
        <div class="progress"><div style="width:<?= $progress['pct'] ?>%"></div></div>
        <span class="sub"><?= $progress['answered'] ?> of <?= $progress['total'] ?> client questions answered</span>
      </div>
      <dl>
        <dt>Started</dt><dd><?= dt($lead['q_started_at']) ?></dd>
        <dt>Last saved</dt><dd><?= dt($lead['q_saved_at']) ?></dd>
        <dt>Submitted</dt><dd><?= dt($lead['q_submitted_at']) ?></dd>
      </dl>
      <p class="sub">The client is asked <strong><?= $sectionsShown ?></strong> of <?= $sectionsAll ?> pages<?php
        if ($hiddenSections): ?> — <?= count($hiddenSections) ?> hidden: <?= e(implode(', ', array_map('q_section_title', $hiddenSections))) ?><?php
        endif; ?>. Open the questionnaire to hide a page you do not need for this client
        <?= $lead['q_status'] === 'not_started' ? ' — worth doing before you send the link.' : '.' ?></p>
      <div class="btn-row">
        <a class="btn" href="questionnaire.php?id=<?= $id ?>">Open full questionnaire</a>
        <a class="btn ghost" href="questionnaire.php?id=<?= $id ?>&amp;view=client">See what the client sees</a>
        <a class="btn ghost" href="questionnaire_print.php?id=<?= $id ?>" target="_blank">Print / PDF</a>
        <a class="btn ghost" href="questionnaire_export.php?id=<?= $id ?>">Download CSV</a>
      </div>

      <h2 class="mt">Client link &amp; reminders</h2>
      <?php if ($link): ?>
        <div class="link-row">
          <input class="link-field" id="client-link" value="<?= e($link) ?>" readonly>
          <button type="button" class="btn small ghost" onclick="navigator.clipboard.writeText(document.getElementById('client-link').value);this.textContent='Copied ✓'">Copy</button>
        </div>
      <?php endif; ?>
      <p class="chase-row">
        <?php if ($lead['chasing']): ?>
          Automatic reminders are <strong>on</strong>. Next reminder
          <?= (int)$lead['auto_chase_count'] >= 3 ? '(auto-close check)' : '(message ' . ((int)$lead['auto_chase_count'] + 1) . ' of 3)' ?>
          on <strong><?= d($lead['next_chase_date']) ?></strong>.
        <?php elseif ($lead['q_status'] === 'submitted'): ?>
          Questionnaire completed — no reminders needed.
        <?php else: ?>
          Automatic reminders are <strong>off</strong>.
        <?php endif; ?>
      </p>
      <div class="btn-row">
        <?php if ($lead['q_status'] !== 'submitted'): ?>
          <?php if (!$lead['chasing']): ?>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="start_chase"><button class="btn" title="Emails and texts the link now, then sends 2 automatic reminders">Send questionnaire link + start reminders</button></form>
          <?php else: ?>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="stop_chase"><button class="btn ghost">Stop reminders</button></form>
          <?php endif; ?>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="resend_email"><button class="btn ghost small">Email link only</button></form>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="resend_sms"><button class="btn ghost small">Text link only</button></form>
          <form method="post" onsubmit="return confirm('Mark the questionnaire as completed? The client will no longer be able to edit it.')"><?= csrf_field() ?><input type="hidden" name="action" value="mark_submitted"><button class="btn ghost small">Mark completed</button></form>
        <?php else: ?>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="reopen"><button class="btn ghost small">Reopen for client edits</button></form>
        <?php endif; ?>
        <form method="post" onsubmit="return confirm('Create a new link? The current link will stop working.')"><?= csrf_field() ?><input type="hidden" name="action" value="regenerate_link"><button class="btn ghost small">New link</button></form>
      </div>
    </div>

    <div class="card">
      <h2>Email this client</h2>
      <?php if (trim($lead['email']) === ''): ?>
        <p class="sub">No email address on file for this client.</p>
      <?php else: ?>
        <p class="sub">Sent from <?= e((string)((config()['mail'] ?? [])['from_email'] ?? 'the CRM')) ?> with our usual sign-off and footer, and saved to the history below.
          You can use <strong>{first_name}</strong>, <strong>{link}</strong> (their questionnaire link), <strong>{phone}</strong> and <strong>{reference}</strong>.</p>
        <form method="post" class="custom-email-form" onsubmit="return confirm('Send this email to <?= e($lead['email']) ?>?')">
          <?= csrf_field() ?><input type="hidden" name="action" value="custom_email">
          <input name="subject" placeholder="Subject" maxlength="200" required>
          <textarea name="body" rows="7" placeholder="Hi {first_name},&#10;&#10;Type your message here. Leave a blank line between paragraphs." maxlength="5000" required></textarea>
          <div><button type="submit" class="btn small">Send email</button></div>
        </form>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2>History</h2>
      <form method="post" class="note-form"><?= csrf_field() ?><input type="hidden" name="action" value="note">
        <textarea name="body" rows="3" placeholder="Add a note (calls, emails, quotes...)"></textarea>
        <div><button type="submit" class="btn small">Add note</button></div>
      </form>
      <?php foreach ($notes as $n): ?>
        <div class="note <?= $n['created_by'] ? '' : 'system' ?>">
          <div class="note-meta"><?= dt($n['created_at']) ?> · <?= $n['created_by'] ? e($n['display_name'] ?: $n['username']) : 'System' ?></div>
          <?= nl2br(e($n['body'])) ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="col">
    <div class="card">
      <h2>Contact</h2>
      <dl>
        <dt>Name</dt><dd><?= e(trim($lead['first_name'] . ' ' . $lead['last_name'])) ?: '—' ?></dd>
        <dt>Business</dt><dd><?= e($lead['company_name']) ?: '—' ?></dd>
        <dt>Email</dt><dd><?= $lead['email'] ? "<a href='mailto:" . e($lead['email']) . "'>" . e($lead['email']) . '</a>' : '—' ?></dd>
        <dt>Phone</dt><dd><?= $lead['phone'] ? "<a href='tel:" . e(normalise_uk_phone($lead['phone'])) . "'>" . e($lead['phone']) . '</a>' : '—' ?></dd>
        <dt>Interest</dt><dd><?= e($lead['cover_interest']) ?: '—' ?></dd>
        <dt>Renewal</dt><dd><?= d($lead['renewal_date']) ?></dd>
        <?php if ($lead['landing_page']): ?><dt>Page</dt><dd class="sub"><?= e($lead['landing_page']) ?></dd><?php endif; ?>
        <?php if ($lead['utm_source'] || $lead['utm_campaign']): ?><dt>Campaign</dt><dd class="sub"><?= e(trim($lead['utm_source'] . ' / ' . $lead['utm_medium'] . ' / ' . $lead['utm_campaign'], ' /')) ?></dd><?php endif; ?>
        <?php if ($lead['consent_at']): ?><dt>Consent</dt><dd class="sub"><?= dt($lead['consent_at']) ?> — <?= e($lead['consent_text']) ?></dd><?php endif; ?>
      </dl>
      <p><a class="btn ghost small" href="lead_edit.php?id=<?= $id ?>">Edit details</a></p>
    </div>

    <div class="card">
      <h2>Pipeline</h2>
      <form method="post" class="status-form"><?= csrf_field() ?><input type="hidden" name="action" value="status">
        <select name="status"><?php foreach (statuses() as $s): ?><option <?= $s === $lead['status'] ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?></select>
        <button class="btn small">Update</button>
      </form>
      <form method="post" class="status-form"><?= csrf_field() ?><input type="hidden" name="action" value="assign">
        <select name="assigned_to"><option value="">— unassigned —</option>
          <?php foreach (users() as $u): ?><option value="<?= (int)$u['user_id'] ?>" <?= (int)$lead['assigned_to'] === (int)$u['user_id'] ? 'selected' : '' ?>><?= e($u['display_name'] ?: $u['username']) ?></option><?php endforeach; ?>
        </select>
        <button class="btn small">Assign</button>
      </form>
      <form method="post" class="status-form"><?= csrf_field() ?><input type="hidden" name="action" value="follow_up">
        <input type="date" name="next_follow_up" value="<?= e($lead['next_follow_up']) ?>">
        <button class="btn small">Set follow-up</button>
      </form>
    </div>

    <div class="card">
      <h2>Tasks</h2>
      <?php foreach ($tasks as $t): ?>
        <div class="task-row <?= $t['done_at'] ? 'done' : '' ?>">
          <div><strong class="<?= !$t['done_at'] && $t['due_date'] < $today ? 'overdue' : '' ?>"><?= d($t['due_date']) ?></strong> — <?= e($t['body']) ?>
            <div class="sub"><?= e(user_name($t['assigned_to'] ? (int)$t['assigned_to'] : null)) ?><?= $t['done_at'] ? ' · done ' . dt($t['done_at']) : '' ?></div></div>
          <?php if (!$t['done_at']): ?>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="task_done"><input type="hidden" name="task_id" value="<?= (int)$t['task_id'] ?>"><button class="btn ghost small">Done</button></form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
      <form method="post" class="task-form"><?= csrf_field() ?><input type="hidden" name="action" value="task">
        <input name="body" placeholder="New task, e.g. Call to discuss quotes" required>
        <input type="date" name="due_date" value="<?= e(date('Y-m-d', strtotime('+1 day'))) ?>" required>
        <select name="assigned_to"><?php foreach (users() as $u): ?><option value="<?= (int)$u['user_id'] ?>" <?= (int)$u['user_id'] === $me ? 'selected' : '' ?>><?= e($u['display_name'] ?: $u['username']) ?></option><?php endforeach; ?></select>
        <button class="btn small">Add task</button>
      </form>
    </div>

    <?php if (is_admin()): ?>
      <form method="post" onsubmit="return confirm('Permanently delete this lead, its questionnaire answers, notes and tasks? This cannot be undone.')">
        <?= csrf_field() ?><input type="hidden" name="action" value="delete">
        <button class="btn ghost danger small">Delete lead (GDPR erasure)</button>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php layout_footer();
