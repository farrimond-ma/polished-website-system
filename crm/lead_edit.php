<?php
require __DIR__ . '/lib.php';
require_login();
$pdo = db();
$id = (int)param('id', 0);
$lead = $id ? find_lead($id) : null;
if ($id && !$lead) { flash('Record not found.'); redirect('leads.php'); }

$f = $lead ?? ['first_name' => '', 'last_name' => '', 'company_name' => '', 'email' => '', 'phone' => '', 'source' => 'Phone',
    'cover_interest' => '', 'renewal_date' => null, 'assigned_to' => current_user()['user_id'], 'next_follow_up' => null];
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach (['first_name', 'last_name', 'company_name', 'email', 'phone', 'cover_interest'] as $k) $f[$k] = trim((string)post($k, ''));
    $f['email'] = strtolower($f['email']);
    $f['source'] = in_array(post('source'), lead_sources(), true) ? post('source') : 'Manual Input';
    $f['renewal_date'] = post('renewal_date') ?: null;
    $f['next_follow_up'] = post('next_follow_up') ?: null;
    $f['assigned_to'] = (int)post('assigned_to') ?: null;

    if ($f['first_name'] === '' && $f['company_name'] === '') $err = 'Enter at least a name or a business name.';
    elseif ($f['email'] !== '' && !filter_var($f['email'], FILTER_VALIDATE_EMAIL)) $err = 'That email address does not look right.';
    else {
        $cols = ['first_name', 'last_name', 'company_name', 'email', 'phone', 'source', 'cover_interest', 'renewal_date', 'next_follow_up', 'assigned_to'];
        $vals = array_map(fn($c) => $f[$c], $cols);
        if ($lead) {
            touch_lead($id, array_combine($cols, $vals));
            add_note($id, 'Contact details edited.', (int)current_user()['user_id']);
            flash('Lead updated.');
        } else {
            $pdo->prepare('INSERT INTO leads (' . implode(',', $cols) . ', status, link_token, created_at, updated_at) VALUES (' . str_repeat('?,', count($cols)) . '?,?,?,?)')
                ->execute([...$vals, 'New Enquiry', new_link_token(), now(), now()]);
            $id = (int)$pdo->lastInsertId();
            add_note($id, 'Lead added manually (' . $f['source'] . ').', (int)current_user()['user_id']);
            flash('Lead ' . lead_ref($id) . ' created.');
        }
        redirect('lead.php?id=' . $id);
    }
}

layout_header($lead ? 'Edit ' . lead_ref($id) : 'Add lead');
?>
<div class="page-head"><h1><?= $lead ? 'Edit ' . e(lead_ref($id)) : 'Add lead' ?></h1></div>
<?php if ($err) echo "<div class='err'>" . e($err) . "</div>"; ?>
<form method="post" class="form" style="max-width:760px">
  <?= csrf_field() ?>
  <h2>Contact</h2>
  <div class="grid2">
    <label>First name<input name="first_name" value="<?= e($f['first_name']) ?>"></label>
    <label>Last name<input name="last_name" value="<?= e($f['last_name']) ?>"></label>
    <label class="span2">Business name<input name="company_name" value="<?= e($f['company_name']) ?>"></label>
    <label>Email<input name="email" type="email" value="<?= e($f['email']) ?>"></label>
    <label>Phone (mobile for text reminders)<input name="phone" value="<?= e($f['phone']) ?>"></label>
  </div>
  <h2>Lead details</h2>
  <div class="grid2">
    <label>Source<select name="source"><?php foreach (lead_sources() as $s): ?><option <?= $f['source'] === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?></select></label>
    <label>Cover / business type of interest<input name="cover_interest" value="<?= e($f['cover_interest']) ?>" placeholder="e.g. Contract cleaners PL + EL"></label>
    <label>Renewal date<input name="renewal_date" type="date" value="<?= e($f['renewal_date']) ?>"></label>
    <label>Next follow-up<input name="next_follow_up" type="date" value="<?= e($f['next_follow_up']) ?>"></label>
    <label>Assigned to<select name="assigned_to"><option value="">— unassigned —</option>
      <?php foreach (users() as $u): ?><option value="<?= (int)$u['user_id'] ?>" <?= (int)$f['assigned_to'] === (int)$u['user_id'] ? 'selected' : '' ?>><?= e($u['display_name'] ?: $u['username']) ?></option><?php endforeach; ?>
    </select></label>
  </div>
  <div class="form-actions">
    <button type="submit"><?= $lead ? 'Save changes' : 'Create lead' ?></button>
    <a class="btn ghost" href="<?= $lead ? 'lead.php?id=' . $id : 'leads.php' ?>">Cancel</a>
  </div>
</form>
<?php layout_footer();
