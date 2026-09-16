<?php
require __DIR__ . '/lib.php';
require __DIR__ . '/rating.php';
require_login();

$in = $_POST ?: [];
$result = null; $saved = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $result = calculate_quote($in);

    if (($_POST['action'] ?? '') === 'save' && !empty($in['client_ref'])) {
        $pdo = db(); $pdo->beginTransaction();
        try {
            $newId = (int)($pdo->query("SELECT COALESCE(MAX(case_id),9000000)+1 FROM case_policy")->fetchColumn());
            $pdo->prepare("INSERT INTO case_policy (case_id,scheme_id,client_ref,agent_id,created_at,status) VALUES (?,?,?,1,?,?)")
                ->execute([$newId,1,$in['client_ref'],date('Y-m-d H:i:s'),'Draft']);
            $P = $result['parts'];
            $pb = json_encode(['pl'=>round($P['B']['premium'],2),'el'=>round($P['A']['premium'],2),
                'tools'=>round($P['J']['owned'],2),'hired'=>round($P['J']['hired'],2),
                'do'=>round($P['C']['premium'],2),'pi'=>round($P['D']['premium'],2)]);
            $pdo->prepare("INSERT INTO policy_term (case_id,transaction_type,sequence_label,status,inception_date,expiry_date,total_premium,adjustment,balance,quote_inputs,premium_breakdown) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([$newId,'New Business','New Business','Draft',($in['inception_date']??null)?:null,null,round($result['net'],2),0,0,json_encode($in),$pb]);
            $termId = (int)$pdo->lastInsertId();
            foreach ($result['parts'] as $pc=>$p) {
                if ($pc==='B' || ($pc==='A' && !empty($in['el_included']))) {
                    foreach (($p['lines']??[]) as $ln) {
                        $pdo->prepare("INSERT INTO rating_line (term_id,part_code,insurer_id,line_label,exposure,rate_pct,premium) VALUES (?,?,1,?,?,?,?)")
                            ->execute([$termId,$pc,$ln[0],$ln[1],$ln[2],round((float)$ln[3],4)]);
                    }
                    $pdo->prepare("INSERT INTO rating_adjustment (term_id,part_code,basic_total,minimum_premium,ni_load_pct,experience_load_pct,discretionary_pct,net_total,commission_from_insurer_pct,commission_to_allied_pct) VALUES (?,?,?,?,?,?,?,?,?,?)")
                        ->execute([$termId,$pc,round((float)($p['basic']??0),2),0,0,(float)($in['nv_load_pct']??0),(float)($in['discretionary_pct']??0),round((float)($p['net']??$p['premium']),2),25,0]);
                }
                if (in_array($pc,['A','B','C','D','J'],true) && ($p['premium']??0)>0) {
                    $lim = $pc==='B' ? $result['limit'] : ($pc==='A' ? 10000000 : null);
                    $pdo->prepare("INSERT INTO cover_selection (term_id,part_code,included,limit_of_indemnity) VALUES (?,?,1,?)")
                        ->execute([$termId,$pc,$lim]);
                }
            }
            $pdo->commit();
            flash("Quote saved as case $newId.");
            header("Location: case.php?id=$newId&tab=rating"); exit;
        } catch (Throwable $ex) { $pdo->rollBack(); $saved = 'Error saving: '.$ex->getMessage(); }
    }
}

$clients = db()->query("SELECT client_ref, COALESCE(NULLIF(joint_names,''),NULLIF(trading_name,''),client_ref) nm FROM client ORDER BY client_ref")->fetchAll();
$v = fn($k,$d='')=> e($in[$k] ?? $d);
$sel = fn($k,$val)=> (($in[$k] ?? '')===$val?'selected':'');
$chk = fn($k)=> !empty($in[$k])?'checked':'';
cases_header('Quote calculator');
?>
<div class="page-head"><h1>Quote calculator</h1></div>
<p class="sub">Computes premiums live from the <a href="rates.php">rate table</a> (same logic as the Excel calculator). Enter exposures, get a Part-by-Part breakdown and referral flags.</p>

<form method="post" class="form">
  <?= csrf_field() ?>
  <div class="grid2">
    <label>Who works in the business?<select name="who_works"><?php foreach(['Me and others','Just me'] as $w): ?><option <?=$sel('who_works',$w)?>><?=e($w)?></option><?php endforeach; ?></select></label>
    <label class="inline"><input type="checkbox" name="just_partner" value="1" <?=$chk('just_partner')?>> Others are business partners only (legal partnership)</label>
    <label>PL limit<select name="pl_limit"><?php foreach([1000000,2000000,5000000,10000000] as $l): ?><option value="<?=$l?>" <?=$sel('pl_limit',(string)$l)?>>£<?=number_format($l)?></option><?php endforeach; ?></select></label>
    <label>Estimated annual turnover £<input name="turnover" type="number" step="0.01" value="<?=$v('turnover')?>"></label>
  </div>

  <h3>Wageroll &amp; sub-contractors</h3>
  <div class="grid2">
    <label class="inline"><input type="checkbox" name="el_included" value="1" <?=$chk('el_included')?>> Include Employers' Liability (Part A)</label>
    <div></div>
    <label>Total wageroll — employed £<input name="wr_employed" type="number" step="0.01" value="<?=$v('wr_employed')?>"></label>
    <label>Total payments — self-employed £<input name="wr_selfemp" type="number" step="0.01" value="<?=$v('wr_selfemp')?>"></label>
    <label>Payments to BFSC £<input name="bfsc_payments" type="number" step="0.01" value="<?=$v('bfsc_payments')?>"></label>
  </div>

  <h3>Split of work <span class="sub">(percentages — the system works out the turnover/wageroll height splits)</span></h3>
  <div class="grid2">
    <label>% work inside buildings<input name="pct_inside" type="number" step="0.1" value="<?=$v('pct_inside','100')?>"></label>
    <label>% work outside buildings<input name="pct_outside" type="number" step="0.1" value="<?=$v('pct_outside','0')?>"></label>
    <label>% at 1–5 metres<input name="pct_1_5m" type="number" step="0.1" value="<?=$v('pct_1_5m','0')?>"></label>
    <label>% at 5–15 metres<input name="pct_5_15m" type="number" step="0.1" value="<?=$v('pct_5_15m','0')?>"></label>
    <label>% at 15–25 metres<input name="pct_15_25m" type="number" step="0.1" value="<?=$v('pct_15_25m','0')?>"></label>
    <label>% over 25 metres (refer)<input name="pct_over_25m" type="number" step="0.1" value="<?=$v('pct_over_25m','0')?>"></label>
    <label>Max height worked (m)<input name="max_height" type="number" step="0.1" value="<?=$v('max_height')?>"></label>
  </div>

  <h3>Options</h3>
  <div class="grid2">
    <label>Professional Advice<select name="prof_advice"><option>None</option><option value="100000" <?=$sel('prof_advice','100000')?>>£100,000</option><option value="250000" <?=$sel('prof_advice','250000')?>>£250,000</option></select></label>
    <label>Professional Indemnity<select name="pi"><option>None</option><option value="100000" <?=$sel('pi','100000')?>>£100,000</option><option value="250000" <?=$sel('pi','250000')?>>£250,000</option></select></label>
    <label class="inline"><input type="checkbox" name="do" value="1" <?=$chk('do')?>> Directors' &amp; Officers' (Part C)</label>
    <label class="inline"><input type="checkbox" name="fidelity" value="1" <?=$chk('fidelity')?>> Fidelity Guarantee</label>
    <label>Owned plant<select name="owned_plant"><option>None</option><?php foreach([1000,6000,10000,15000,20000,25000,35000,50000] as $o): ?><option value="<?=$o?>" <?=$sel('owned_plant',(string)$o)?>>£<?=number_format($o)?></option><?php endforeach; ?></select></label>
    <label>Hired-in plant (annual hiring charges)<select name="hired_charges"><option>N/A</option><option <?=$sel('hired_charges','Up to £5,000')?>>Up to £5,000 (£150)</option><option <?=$sel('hired_charges','£5,000 to £10,000')?>>£5,000 to £10,000 (£300)</option><option <?=$sel('hired_charges','Over £10,000')?>>Over £10,000 (refer)</option></select></label>
  </div>

  <h3>Loadings &amp; discounts</h3>
  <div class="grid2">
    <label class="inline"><input type="checkbox" name="ni" value="1" <?=$chk('ni')?>> Northern Ireland</label>
    <label class="inline"><input type="checkbox" name="disc_noclaims" value="1" <?=$chk('disc_noclaims')?>> No claims (10%)</label>
    <label class="inline"><input type="checkbox" name="disc_lowclaims" value="1" <?=$chk('disc_lowclaims')?>> Low claims (5%)</label>
    <label class="inline"><input type="checkbox" name="disc_est" value="1" <?=$chk('disc_est')?>> Established 5+ yrs (5%)</label>
    <label class="inline"><input type="checkbox" name="disc_noladder" value="1" <?=$chk('disc_noladder')?>> No ladders (20% EL height)</label>
    <label class="inline"><input type="checkbox" name="disc_fwc" value="1" <?=$chk('disc_fwc')?>> FWC (10% EL height)</label>
    <label class="inline"><input type="checkbox" name="disc_accred" value="1" <?=$chk('disc_accred')?>> Accreditations (5%)</label>
    <label class="inline"><input type="checkbox" name="disc_hs" value="1" <?=$chk('disc_hs')?>> H&amp;S policy (5%)</label>
    <label>Discretionary %<input name="discretionary_pct" type="number" step="0.1" value="<?=$v('discretionary_pct')?>"></label>
    <label>Years' experience<select name="years_experience"><?php foreach(['Over 3 years','Between 2 and 3 years','Between 1 and 2 years','Less than 1 year'] as $yb): ?><option <?=$sel('years_experience',$yb)?>><?=e($yb)?></option><?php endforeach; ?></select></label>
    <label>Extra manual loading %<input name="nv_load_pct" type="number" step="0.1" value="<?=$v('nv_load_pct')?>"></label>
    <label>Max height (m)<input name="max_height" type="number" step="0.1" value="<?=$v('max_height')?>"></label>
  </div>

  <h3>Underwriting checks</h3>
  <div class="grid2">
    <label>Title<select name="title"><?php foreach(['Mr','Mrs','Miss','Ms','Dr','Prof.','Rev.','Exec(s) of','Mx'] as $tt): ?><option <?=$sel('title',$tt)?>><?=e($tt)?></option><?php endforeach; ?></select></label>
    <label>Entity<select name="entity"><?php foreach(['Sole Proprietor','Limited company','Legal Partnership','Community Interest Company'] as $en): ?><option <?=$sel('entity',$en)?>><?=e($en)?></option><?php endforeach; ?></select></label>
    <label>BFSC payments £<input name="bfsc_payments" type="number" step="0.01" value="<?=$v('bfsc_payments')?>"></label>
    <label class="inline"><input type="checkbox" name="claims" value="1" <?=$chk('claims')?>> Claims in last 5 yrs</label>
    <label>Claims count<select name="claims_count"><option value=""></option><?php foreach(['0','1','2','3','4+'] as $c): ?><option <?=$sel('claims_count',$c)?>><?=e($c)?></option><?php endforeach; ?></select></label>
    <label>Claims value<select name="claims_value"><option value=""></option><?php foreach(['Less than £2,000','£2,000 to £5,000','£5,000 +'] as $c): ?><option <?=$sel('claims_value',$c)?>><?=e($c)?></option><?php endforeach; ?></select></label>
    <label class="inline"><input type="checkbox" name="declaration_ok" value="1" <?=($in?($chk('declaration_ok')):'checked')?>> Declaration agreed</label>
  </div>

  <div class="form-actions"><button type="submit" name="action" value="calc">Calculate</button></div>

<?php if ($result): $P=$result['parts']; ?>
  <?php if ($saved) echo "<div class='err'>".e($saved)."</div>"; ?>
  <h2 style="margin-top:24px">Quote result</h2>
  <div class="quote-grid">
    <table class="grid">
      <thead><tr><th>Part</th><th class="r">Premium</th></tr></thead>
      <tbody>
        <tr><td>A — Employers' Liability</td><td class="r"><?=money($P['A']['premium'])?></td></tr>
        <tr><td>B — Public &amp; Products Liability</td><td class="r"><?=money($P['B']['premium'])?></td></tr>
        <tr><td>C — Directors' &amp; Officers'</td><td class="r"><?=money($P['C']['premium'])?></td></tr>
        <tr><td>D — Professional Indemnity</td><td class="r"><?=money($P['D']['premium'])?></td></tr>
        <tr><td>J — Contract Works (plant)</td><td class="r"><?=money($P['J']['premium'])?></td></tr>
        <tr class="tot"><td><strong>Net premium</strong></td><td class="r"><strong><?=money($result['net'])?></strong></td></tr>
        <tr><td>IPT (12%)</td><td class="r"><?=money($result['ipt'])?></td></tr>
        <tr><td>Policy fee (12.5% of net+IPT)</td><td class="r"><?=money($result['policy_fee'])?></td></tr>
        <tr class="tot"><td><strong>Gross payable</strong></td><td class="r"><strong><?=money($result['gross'])?></strong></td></tr>
      </tbody>
    </table>
    <div class="status-box status-<?=strtolower($result['status'])?>">
      <div class="status-lbl">Status</div><div class="status-val"><?=e($result['status'])?></div>
      <div class="sub">net £<?=number_format($result['net'],2)?> · gross £<?=number_format($result['gross'],2)?></div>
    </div>
  </div>

  <?php if ($result['flags']): ?>
    <h3>Underwriting flags</h3>
    <table class="grid"><tbody>
    <?php foreach($result['flags'] as $f): ?>
      <tr><td style="width:90px"><span class="pill <?=strtolower($f['sev'])==='stop'||strtolower($f['sev'])==='decline'?'cancelled':(strtolower($f['sev'])==='refer'||strtolower($f['sev'])==='check'?'pending':'oncover')?>"><?=e($f['sev'])?></span></td><td><?=e($f['msg'])?></td></tr>
    <?php endforeach; ?>
    </tbody></table>
  <?php else: ?><p class="flash">No referral or decline flags — within delegated authority.</p><?php endif; ?>

  <h3>Save this quote as a case</h3>
  <div class="save-row">
    <select name="client_ref"><option value="">— choose client —</option><?php foreach($clients as $cl): ?><option value="<?=e($cl['client_ref'])?>"><?=e($cl['client_ref'].' — '.$cl['nm'])?></option><?php endforeach; ?></select>
    <button type="submit" name="action" value="save">Save as new case</button>
  </div>
  <input type="hidden" name="_dummy" value="1">
<?php endif; ?>
</form>
<?php cases_footer();
