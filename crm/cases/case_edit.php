<?php
require __DIR__ . '/lib.php';
require_login();
$pdo = db();
$id = (int)param('id', 0);
$editing = $id > 0;

$case = ['case_id'=>'','client_ref'=>'','scheme_id'=>1,'agent_id'=>1,'status'=>'Draft'];
$term = ['transaction_type'=>'New Business','sequence_label'=>'New Business','status'=>'Draft',
         'inception_date'=>'','expiry_date'=>'','total_premium'=>'','adjustment'=>'0','balance'=>'0'];
$termId = 0;
if ($editing) {
    $st = $pdo->prepare("SELECT * FROM case_policy WHERE case_id=?"); $st->execute([$id]);
    $case = $st->fetch() ?: $case;
    $t = $pdo->prepare("SELECT * FROM policy_term WHERE case_id=? ORDER BY term_id DESC LIMIT 1");
    $t->execute([$id]); $tr = $t->fetch();
    if ($tr) { $term = $tr; $termId = (int)$tr['term_id']; }
}
$clients = $pdo->query("SELECT client_ref, COALESCE(NULLIF(joint_names,''), NULLIF(trading_name,''), client_ref) AS nm FROM client ORDER BY client_ref")->fetchAll();
$schemes = $pdo->query("SELECT scheme_id, product_name FROM scheme ORDER BY scheme_id")->fetchAll();
$statuses = ['Draft','On Cover','Cancelled','Lapsed'];
$txtypes  = ['New Business','Renewal','Adjustment','Cancellation'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $client_ref = trim((string)post('client_ref',''));
    $scheme_id  = (int)post('scheme_id',1);
    $status     = (string)post('status','Draft');
    $tt = ['transaction_type'=>(string)post('transaction_type','New Business'),
           'sequence_label'=>(string)post('sequence_label',''),
           'status'=>$status,
           'inception_date'=>(string)post('inception_date','') ?: null,
           'expiry_date'=>(string)post('expiry_date','') ?: null,
           'total_premium'=>post('total_premium','')!==''?(float)post('total_premium'):null,
           'adjustment'=>post('adjustment','')!==''?(float)post('adjustment'):null,
           'balance'=>post('balance','')!==''?(float)post('balance'):null];
    if ($client_ref === '') { flash('Please choose a client.'); }
    else {
        if ($editing) {
            $pdo->prepare("UPDATE case_policy SET client_ref=?, scheme_id=?, status=? WHERE case_id=?")
                ->execute([$client_ref,$scheme_id,$status,$id]);
            if ($termId) {
                $pdo->prepare("UPDATE policy_term SET transaction_type=?,sequence_label=?,status=?,inception_date=?,expiry_date=?,total_premium=?,adjustment=?,balance=? WHERE term_id=?")
                    ->execute([$tt['transaction_type'],$tt['sequence_label'],$tt['status'],$tt['inception_date'],$tt['expiry_date'],$tt['total_premium'],$tt['adjustment'],$tt['balance'],$termId]);
            } else {
                $pdo->prepare("INSERT INTO policy_term (case_id,transaction_type,sequence_label,status,inception_date,expiry_date,total_premium,adjustment,balance) VALUES (?,?,?,?,?,?,?,?,?)")
                    ->execute([$id,$tt['transaction_type'],$tt['sequence_label'],$tt['status'],$tt['inception_date'],$tt['expiry_date'],$tt['total_premium'],$tt['adjustment'],$tt['balance']]);
            }
        } else {
            $newId = (int)($pdo->query("SELECT COALESCE(MAX(case_id),9000000)+1 FROM case_policy")->fetchColumn());
            $pdo->prepare("INSERT INTO case_policy (case_id,scheme_id,client_ref,agent_id,created_at,status) VALUES (?,?,?,1,?,?)")
                ->execute([$newId,$scheme_id,$client_ref,date('Y-m-d H:i:s'),$status]);
            $pdo->prepare("INSERT INTO policy_term (case_id,transaction_type,sequence_label,status,inception_date,expiry_date,total_premium,adjustment,balance) VALUES (?,?,?,?,?,?,?,?,?)")
                ->execute([$newId,$tt['transaction_type'],$tt['sequence_label'],$tt['status'],$tt['inception_date'],$tt['expiry_date'],$tt['total_premium'],$tt['adjustment'],$tt['balance']]);
            $id = $newId;
        }
        flash('Case saved.');
        header('Location: case.php?id=' . $id); exit;
    }
}
cases_header($editing ? 'Edit case' : 'New case');
?>
<div class="page-head"><h1><?= $editing ? 'Edit case '.(int)$id : 'New case' ?></h1></div>
<form method="post" class="form">
  <?= csrf_field() ?>
  <div class="grid2">
    <label>Client *
      <select name="client_ref" required>
        <option value="">— choose client —</option>
        <?php foreach($clients as $cl): ?>
          <option value="<?= e($cl['client_ref']) ?>" <?= $cl['client_ref']===$case['client_ref']?'selected':'' ?>><?= e($cl['client_ref'].' — '.$cl['nm']) ?></option>
        <?php endforeach; ?>
      </select></label>
    <label>Scheme<select name="scheme_id"><?php foreach($schemes as $s): ?>
      <option value="<?= (int)$s['scheme_id'] ?>" <?= (int)$s['scheme_id']===(int)$case['scheme_id']?'selected':'' ?>><?= e($s['product_name']) ?></option>
    <?php endforeach; ?></select></label>
    <label>Case status<select name="status"><?php foreach($statuses as $s): ?>
      <option <?= $s===$case['status']?'selected':'' ?>><?= e($s) ?></option><?php endforeach; ?></select></label>
    <label>Transaction type<select name="transaction_type"><?php foreach($txtypes as $s): ?>
      <option <?= $s===$term['transaction_type']?'selected':'' ?>><?= e($s) ?></option><?php endforeach; ?></select></label>
    <label>Sequence label<input name="sequence_label" value="<?= e($term['sequence_label']) ?>" placeholder="New Business / 1st / adj: 31 Jul 2026"></label>
    <label>Inception date<input type="date" name="inception_date" value="<?= e($term['inception_date']) ?>"></label>
    <label>Expiry date<input type="date" name="expiry_date" value="<?= e($term['expiry_date']) ?>"></label>
    <label>Total premium £<input type="number" step="0.01" name="total_premium" value="<?= e($term['total_premium']) ?>"></label>
    <label>Adjustment £<input type="number" step="0.01" name="adjustment" value="<?= e($term['adjustment']) ?>"></label>
    <label>Balance £<input type="number" step="0.01" name="balance" value="<?= e($term['balance']) ?>"></label>
  </div>
  <div class="form-actions"><button type="submit">Save case</button>
    <a class="btn ghost" href="<?= $editing ? 'case.php?id='.(int)$id : 'index.php' ?>">Cancel</a></div>
</form>
<?php cases_footer();
