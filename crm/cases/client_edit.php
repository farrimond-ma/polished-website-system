<?php
require __DIR__ . '/lib.php';
require_login();
$ref = (string)param('ref','');
$editing = $ref !== '';
$cl = ['client_ref'=>'','title'=>'','first_name'=>'','last_name'=>'','joint_names'=>'','trading_name'=>'',
       'addr1'=>'','addr2'=>'','town'=>'','county'=>'','postcode'=>'','phone_landline'=>'','phone_mobile'=>'',
       'email'=>'','domicile'=>'United Kingdom'];
if ($editing) {
    $st = db()->prepare("SELECT * FROM client WHERE client_ref=?"); $st->execute([$ref]);
    $cl = $st->fetch() ?: $cl;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $f = [];
    foreach (array_keys($cl) as $k) $f[$k] = trim((string)post($k,''));
    if ($f['client_ref'] === '') { flash('Client ref is required.'); }
    else {
        $cols = ['title','first_name','last_name','joint_names','trading_name','addr1','addr2','town','county','postcode','phone_landline','phone_mobile','email','domicile'];
        if ($editing) {
            $set = implode(',', array_map(fn($c)=>"$c=?", $cols));
            $args = array_map(fn($c)=>$f[$c], $cols); $args[] = $ref;
            db()->prepare("UPDATE client SET $set WHERE client_ref=?")->execute($args);
        } else {
            $all = array_merge(['client_ref'], $cols);
            $ph = implode(',', array_fill(0, count($all), '?'));
            db()->prepare("INSERT INTO client (".implode(',',$all).") VALUES ($ph)")
                ->execute(array_map(fn($c)=>$f[$c], $all));
            $ref = $f['client_ref'];
        }
        flash('Client saved.');
        header('Location: client.php?ref=' . urlencode($ref)); exit;
    }
}
cases_header($editing ? 'Edit client' : 'New client');
$fld = fn($k,$label,$ph='')=>"<label>".e($label)."<input name='".e($k)."' value='".e($cl[$k])."' placeholder='".e($ph)."'></label>";
?>
<div class="page-head"><h1><?= $editing ? 'Edit client '.e($ref) : 'New client' ?></h1></div>
<form method="post" class="form">
  <?= csrf_field() ?>
  <div class="grid2">
    <label>Client ref *<input name="client_ref" value="<?= e($cl['client_ref']) ?>" <?= $editing?'readonly':'' ?> required placeholder="e.g. LIL1"></label>
    <?= $fld('trading_name','Trading name (t/as)') ?>
    <?= $fld('title','Title') ?>
    <?= $fld('joint_names','Joint / full names','e.g. Greg Thomson and Kirstie Mair') ?>
    <?= $fld('first_name','First name') ?>
    <?= $fld('last_name','Last name') ?>
    <?= $fld('addr1','Address line 1') ?>
    <?= $fld('addr2','Address line 2') ?>
    <?= $fld('town','Town / City') ?>
    <?= $fld('county','County') ?>
    <?= $fld('postcode','Postcode') ?>
    <?= $fld('domicile','Domicile') ?>
    <?= $fld('phone_landline','Landline') ?>
    <?= $fld('phone_mobile','Mobile') ?>
    <?= $fld('email','Email') ?>
  </div>
  <div class="form-actions"><button type="submit">Save client</button>
    <a class="btn ghost" href="<?= $editing ? 'client.php?ref='.e($ref) : 'clients.php' ?>">Cancel</a></div>
</form>
<?php cases_footer();
