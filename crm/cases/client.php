<?php
require __DIR__ . '/lib.php';
require_login();
$ref = (string)param('ref','');
$st = db()->prepare("SELECT * FROM client WHERE client_ref = ?");
$st->execute([$ref]); $cl = $st->fetch();
if (!$cl) { cases_header('Client'); echo "<p class='empty'>Client not found.</p>"; cases_footer(); exit; }
$cs = db()->prepare("SELECT c.case_id, c.status,
        t.total_premium, t.expiry_date, t.sequence_label
        FROM case_policy c
        LEFT JOIN policy_term t ON t.term_id=(SELECT MAX(term_id) FROM policy_term WHERE case_id=c.case_id)
        WHERE c.client_ref=? ORDER BY c.case_id DESC");
$cs->execute([$ref]); $cases = $cs->fetchAll();
function fullname(array $r): string {
    if (!empty($r['joint_names'])) return $r['joint_names'];
    $n = trim(($r['title']??'').' '.($r['first_name']??'').' '.($r['last_name']??''));
    return $n !== '' ? $n : ($r['trading_name'] ?? $r['client_ref']);
}
cases_header('Client ' . $cl['client_ref']);
?>
<div class="page-head">
  <h1><?= e(fullname($cl)) ?> <span class="ck"><?= e($cl['client_ref']) ?></span></h1>
  <a class="btn ghost" href="client_edit.php?ref=<?= e($cl['client_ref']) ?>">Edit</a>
</div>
<div class="two-col">
  <div class="client-card">
    <?= $cl['trading_name'] ? "<div class='sub'>t/as ".e($cl['trading_name'])."</div>" : '' ?>
    <div class="addr"><?= e($cl['addr1']) ?><?= $cl['addr2']?'<br>'.e($cl['addr2']):'' ?>
      <?= $cl['town']?'<br>'.e($cl['town']):'' ?><?= $cl['county']?'<br>'.e($cl['county']):'' ?>
      <?= $cl['postcode']?'<br>'.e($cl['postcode']):'' ?></div>
    <div class="sub"><?= $cl['phone_landline']?'☎ '.e($cl['phone_landline']).'<br>':'' ?>
      <?= $cl['phone_mobile']?'📱 '.e($cl['phone_mobile']).'<br>':'' ?>
      <?= $cl['email']?'✉ '.e($cl['email']):'' ?></div>
    <div class="sub">Domicile: <?= e($cl['domicile'] ?: '—') ?></div>
  </div>
  <div class="term-card">
    <h3>Cases</h3>
    <table class="grid"><thead><tr><th>Case</th><th>Status</th><th>Transaction</th><th class="r">Premium</th></tr></thead><tbody>
    <?php foreach($cases as $c): ?>
      <tr onclick="location='case.php?id=<?= (int)$c['case_id'] ?>'">
        <td class="mono"><?= (int)$c['case_id'] ?></td><td><?= e($c['status']) ?></td>
        <td><?= e($c['sequence_label'] ?: '—') ?></td><td class="r"><?= money($c['total_premium']) ?></td>
      </tr>
    <?php endforeach; if(!$cases) echo "<tr><td colspan='4' class='empty'>No cases.</td></tr>"; ?>
    </tbody></table>
  </div>
</div>
<?php cases_footer();
