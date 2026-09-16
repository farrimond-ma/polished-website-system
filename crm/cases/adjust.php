<?php
require __DIR__ . '/lib.php';
require __DIR__ . '/rating.php';
require __DIR__ . '/doc_field_map.php';   // doc_generate_and_save() for auto-issuing documents
require_login();

$case_id = (int)($_GET['case'] ?? $_POST['case'] ?? 0);
$mode    = (string)($_GET['mode'] ?? $_POST['mode'] ?? 'adjust');   // adjust | cancel
$mode    = $mode === 'cancel' ? 'cancel' : 'adjust';

// case + current in-force term
$st = db()->prepare("SELECT c.case_id, c.client_ref, cl.trading_name, cl.joint_names, cl.addr1, cl.addr2,
                     cl.town, cl.county, cl.postcode,
                     COALESCE(NULLIF(cl.trading_name,''),NULLIF(cl.joint_names,''),cl.client_ref) nm
                     FROM case_policy c JOIN client cl ON cl.client_ref=c.client_ref WHERE c.case_id=?");
$st->execute([$case_id]); $case = $st->fetch();
$tm = db()->prepare("SELECT * FROM policy_term WHERE case_id=? ORDER BY term_id DESC LIMIT 1");
$tm->execute([$case_id]); $curTerm = $tm->fetch();

if (!$case || !$curTerm) { cases_header('Adjust'); echo "<p class='empty'>Case or in-force term not found.</p>"; cases_footer(); exit; }

$incept   = $curTerm['inception_date'];
$expiry   = $curTerm['expiry_date'];
$cur      = reconstruct_inputs(db(), (int)$curTerm['term_id']);
// in-force premium = the stored net (the actual current premium; correct for imported records too)
$oldNet   = (float)$curTerm['total_premium'];
// the actual in-force premium of each cover part (PL/EL/Tools/Hired/D&O/PI), as charged
$oldBreak = json_decode((string)($curTerm['premium_breakdown'] ?? ''), true) ?: [];
// normalise the in-force inputs with the same defaults the form applies, so an untouched MTA
// re-rates every part identically (=> zero change) instead of drifting on missing keys
$curForm  = $cur;
foreach (['pct_inside'=>'100','pct_outside'=>'0','pct_1_5m'=>'0','pct_5_15m'=>'0','pct_15_25m'=>'0','pct_over_25m'=>'0'] as $k=>$dv)
    if (($curForm[$k] ?? '') === '') $curForm[$k] = $dv;
// current business description / activities (shown editable on the MTA)
$bd = db()->prepare("SELECT answer_value FROM risk_answer WHERE term_id=? AND question_key='Client_Business_Description' ORDER BY answer_id DESC LIMIT 1");
$bd->execute([(int)$curTerm['term_id']]); $bizDesc = (string)$bd->fetchColumn();

$posted = ($_SERVER['REQUEST_METHOD'] === 'POST');
if ($posted) csrf_check();
$src = $posted ? $_POST : $cur;                        // form values
$eff = $posted ? (string)post('effective_date','') : date('Y-m-d');
if ($eff === '') $eff = date('Y-m-d');

$result = null;
if ($posted) {
    $factor = prorata_factor($incept, $expiry, $eff);
    if ($mode === 'adjust') {
        $freshNew = calculate_quote($_POST);
        if ($oldBreak) {
            // Preserve the actual in-force premium of every cover part; add only the change this
            // MTA rates for that part. A part the MTA doesn't touch re-rates identically old vs new
            // (delta 0), so its premium — including any minimum/uplift above the pure rate — stays put.
            $pO = calculate_quote($curForm)['parts']; $pN = $freshNew['parts'];
            $ob = fn($k) => (float)($oldBreak[$k] ?? 0);
            $newPb = [
                'pl'    => round($ob('pl')    + ($pN['B']['premium'] - $pO['B']['premium']), 2),
                'el'    => round($ob('el')    + ($pN['A']['premium'] - $pO['A']['premium']), 2),
                'tools' => round($ob('tools') + ($pN['J']['owned']   - $pO['J']['owned']),   2),
                'hired' => round($ob('hired') + ($pN['J']['hired']   - $pO['J']['hired']),   2),
                'do'    => round($ob('do')    + ($pN['C']['premium'] - $pO['C']['premium']), 2),
                'pi'    => round($ob('pi')    + ($pN['D']['premium'] - $pO['D']['premium']), 2),
            ];
            $newNet = round(array_sum($newPb), 2);
        } else {
            $newPb  = null;                            // no stored breakdown -> full re-rate (legacy)
            $newNet = (float)$freshNew['net'];
        }
        $computed = ($newNet - $oldNet) * $factor;
    } else {
        $newNet = 0.0;
        $computed = -($oldNet * $factor);              // return premium (negative)
    }
    // "Set premium to nil" forces the net (and its fee) to zero — e.g. a return premium we don't want to give.
    $nil = (post('nil', '') !== '');
    $override = $nil ? 0.0 : ((post('override', '') !== '') ? (float)post('override') : round($computed, 2));
    $ipt = $override * (scheme_cfg('ipt_pct', 12.0) / 100);
    $feeDefault = round(($override + $ipt) * (scheme_cfg('policy_fee_pct', 0) / 100), 2);   // 12.5% of net+IPT
    $fee = $nil ? 0.0 : ((post('fee_override', '') !== '') ? (float)post('fee_override') : $feeDefault);
    $total = $override + $ipt + $fee;
    $result = compact('factor','newNet','computed','override','ipt','fee','feeDefault','total');

    if (post('action') === 'save') {
        $seq = ($mode === 'cancel' ? 'Cancellation' : 'MTA') . ': ' . date('d M Y', strtotime($eff));
        $tt  = $mode === 'cancel' ? 'Cancellation' : 'Adjustment';
        $status = $mode === 'cancel' ? 'Cancelled' : 'On Cover';
        // a nil net adjustment (override = £0) means no premium movement at all: keep the in-force
        // annual premium and breakdown so nothing propagates to renewal or the insurer bordereau.
        $isNilAdj = ($mode === 'adjust' && abs(round($override, 2)) < 0.005);
        $annual = $mode === 'cancel' ? 0 : ($isNilAdj ? $oldNet : $newNet);
        if ($mode === 'adjust' && !$isNilAdj) {
            if ($newPb === null) {
                $rp = $freshNew['parts'];
                $newPb = ['pl'=>round($rp['B']['premium'],2),'el'=>round($rp['A']['premium'],2),
                    'tools'=>round($rp['J']['owned'],2),'hired'=>round($rp['J']['hired'],2),
                    'do'=>round($rp['C']['premium'],2),'pi'=>round($rp['D']['premium'],2)];
            }
            $pb = json_encode($newPb);   // new in-force breakdown (in-force + this MTA's rated change)
        } else {
            $pb = (string)($curTerm['premium_breakdown'] ?? '');   // unchanged — nil adjustment or cancellation
        }
        db()->prepare("INSERT INTO policy_term (case_id,transaction_type,sequence_label,status,inception_date,expiry_date,effective_date,total_premium,adjustment,balance,policy_fee,quote_inputs,premium_breakdown) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$case_id,$tt,$seq,$status,$incept,$expiry,$eff,$annual,round($override,2),round(-$total,2),round($fee,2),
                       $mode==='cancel'?null:json_encode($_POST),$pb]);
        $newTermId = (int)db()->lastInsertId();
        if ($mode === 'adjust') {
            persist_quote_to_term(db(), $newTermId, $_POST, calculate_quote($_POST));
            // carry the risk answers forward so the new Statement of Fact reflects the policy
            db()->prepare("INSERT INTO risk_answer (term_id,part_name,question_key,question_text,answer_value)
                           SELECT ?,part_name,question_key,question_text,answer_value FROM risk_answer WHERE term_id=?")
                ->execute([$newTermId, (int)$curTerm['term_id']]);
            // apply the (possibly edited) business description / activities to the new term
            $bizNew = trim((string)post('biz_desc',''));
            db()->prepare("DELETE FROM risk_answer WHERE term_id=? AND question_key='Client_Business_Description'")->execute([$newTermId]);
            if ($bizNew !== '') db()->prepare("INSERT INTO risk_answer (term_id,part_name,question_key,question_text,answer_value) VALUES (?, 'Import','Client_Business_Description','Business Description',?)")->execute([$newTermId, $bizNew]);
        }
        if ($mode === 'cancel') db()->prepare("UPDATE case_policy SET status='Cancelled' WHERE case_id=?")->execute([$case_id]);

        // update the insured name / address (editable on the MTA)
        db()->prepare("UPDATE client SET joint_names=?, trading_name=?, addr1=?, addr2=?, town=?, county=?, postcode=? WHERE client_ref=?")
            ->execute([trim((string)post('ins_name','')), trim((string)post('ins_trading','')),
                       trim((string)post('ins_addr1','')), trim((string)post('ins_addr2','')),
                       trim((string)post('ins_town','')), trim((string)post('ins_county','')),
                       trim((string)post('ins_postcode','')), $case['client_ref']]);

        // audit note on the case
        $uid = current_user()['user_id'] ?? null;
        $detail = ($mode === 'cancel'
            ? 'Policy cancelled effective ' . date('d/m/Y', strtotime($eff)) . '. Return premium £' . number_format($override, 2) . ' (total inc IPT £' . number_format($total, 2) . ').'
            : 'Mid-term adjustment effective ' . date('d/m/Y', strtotime($eff)) . '. Additional/return net £' . number_format($override, 2) . ' (total inc IPT £' . number_format($total, 2) . ').');
        db()->prepare("INSERT INTO activity_log (case_id,type,details,user_id,created_at) VALUES (?,?,?,?,?)")
            ->execute([$case_id, 'Policy', $detail, $uid, date('Y-m-d H:i:s')]);

        // auto-issue documents to the case: adjustment/cancellation, plus a revised Schedule for adjustments
        doc_generate_and_save(db(), $case_id, $newTermId, 'adjustment', $mode === 'cancel' ? 'Cancellation' : 'Adjustment', $uid);
        if ($mode !== 'cancel') {
            doc_generate_and_save(db(), $case_id, $newTermId, 'schedule', 'Revised Schedule', $uid);
            doc_generate_and_save(db(), $case_id, $newTermId, 'statement_of_fact', 'Statement of Fact', $uid);
        }

        flash(($mode==='cancel'?'Cancellation':'Adjustment').' saved. Documents issued to the case.');
        header("Location: document.php?case=$case_id&doc=adjustment"); exit;
    }
}

$v   = fn($k,$d='') => e($src[$k] ?? $cur[$k] ?? $d);
$sel = fn($k,$val) => (($src[$k] ?? $cur[$k] ?? '') == $val ? 'selected' : '');
$chk = fn($k) => (!empty($src[$k] ?? $cur[$k] ?? '') ? 'checked' : '');
cases_header(($mode==='cancel'?'Cancel':'Adjust').' policy '.$case_id);
?>
<div class="page-head"><h1><?= $mode==='cancel'?'Cancel':'Mid-term adjustment —' ?> Case <?= $case_id ?></h1></div>
<p class="sub"><?= e($case['nm']) ?> · in-force premium <?= money($oldNet) ?> · period <?= d($incept) ?> to <?= d($expiry) ?>
   · <a href="adjust.php?case=<?= $case_id ?>&mode=<?= $mode==='cancel'?'adjust':'cancel' ?>">switch to <?= $mode==='cancel'?'adjustment':'cancellation' ?></a></p>

<form method="post" class="form">
  <?= csrf_field() ?>
  <input type="hidden" name="case" value="<?= $case_id ?>">
  <input type="hidden" name="mode" value="<?= e($mode) ?>">
  <div class="grid2">
    <label>Effective date<input type="date" name="effective_date" value="<?= e($eff) ?>" required></label>
  </div>

  <h3>Insured details <span class="sub">(editable)</span></h3>
  <?php $iv = fn($k,$col) => e($posted ? (string)post($k,'') : (string)($case[$col] ?? '')); ?>
  <div class="grid2">
    <label>Insured name<input name="ins_name" value="<?= $iv('ins_name','joint_names') ?>"></label>
    <label>Trading as<input name="ins_trading" value="<?= $iv('ins_trading','trading_name') ?>"></label>
    <label>Address line 1<input name="ins_addr1" value="<?= $iv('ins_addr1','addr1') ?>"></label>
    <label>Address line 2<input name="ins_addr2" value="<?= $iv('ins_addr2','addr2') ?>"></label>
    <label>Town<input name="ins_town" value="<?= $iv('ins_town','town') ?>"></label>
    <label>County<input name="ins_county" value="<?= $iv('ins_county','county') ?>"></label>
    <label>Postcode<input name="ins_postcode" value="<?= $iv('ins_postcode','postcode') ?>"></label>
  </div>
  <label>Business description / activities <span class="sub">(edit to add or remove an activity — this prints on the schedule &amp; documents)</span>
    <textarea name="biz_desc" rows="3" style="width:100%;padding:8px 10px;border:1px solid #cdd6df;border-radius:6px;font:inherit"><?= e($posted ? (string)post('biz_desc','') : $bizDesc) ?></textarea>
  </label>

  <?php if ($mode === 'adjust'): ?>
  <h3>Revised cover &amp; exposures</h3>
  <div class="grid2">
    <label>Who works<select name="who_works"><?php foreach(['Me and others','Just me'] as $w): ?><option <?=$sel('who_works',$w)?>><?=e($w)?></option><?php endforeach; ?></select></label>
    <label>PL limit<select name="pl_limit"><?php foreach([1000000,2000000,5000000,10000000] as $l): ?><option value="<?=$l?>" <?=$sel('pl_limit',$l)?>>£<?=number_format($l)?></option><?php endforeach; ?></select></label>
    <label>Turnover £<input name="turnover" type="number" step="0.01" value="<?=$v('turnover')?>"></label>
    <label class="inline"><input type="checkbox" name="el_included" value="1" <?=$chk('el_included')?>> Include EL</label>
    <label>Total wageroll — employed £<input name="wr_employed" type="number" step="0.01" value="<?=$v('wr_employed')?>"></label>
    <label>Total payments — self-employed £<input name="wr_selfemp" type="number" step="0.01" value="<?=$v('wr_selfemp')?>"></label>
    <label>Payments to BFSC £<input name="bfsc_payments" type="number" step="0.01" value="<?=$v('bfsc_payments')?>"></label>
    <div></div>
    <label>% inside buildings<input name="pct_inside" type="number" step="0.1" value="<?=$v('pct_inside','100')?>"></label>
    <label>% outside buildings<input name="pct_outside" type="number" step="0.1" value="<?=$v('pct_outside','0')?>"></label>
    <label>% at 1–5m<input name="pct_1_5m" type="number" step="0.1" value="<?=$v('pct_1_5m','0')?>"></label>
    <label>% at 5–15m<input name="pct_5_15m" type="number" step="0.1" value="<?=$v('pct_5_15m','0')?>"></label>
    <label>% at 15–25m<input name="pct_15_25m" type="number" step="0.1" value="<?=$v('pct_15_25m','0')?>"></label>
    <label>% over 25m<input name="pct_over_25m" type="number" step="0.1" value="<?=$v('pct_over_25m','0')?>"></label>
    <label>Professional Advice<select name="prof_advice"><option>None</option><option value="100000" <?=$sel('prof_advice','100000')?>>£100,000</option><option value="250000" <?=$sel('prof_advice','250000')?>>£250,000</option></select></label>
    <label>Professional Indemnity<select name="pi"><option>None</option><option value="100000" <?=$sel('pi','100000')?>>£100,000</option><option value="250000" <?=$sel('pi','250000')?>>£250,000</option></select></label>
    <label class="inline"><input type="checkbox" name="do" value="1" <?=$chk('do')?>> D&amp;O</label>
    <label>Owned plant<select name="owned_plant"><option>None</option><?php foreach([1000,6000,10000,15000,20000,25000,35000,50000] as $o): ?><option value="<?=$o?>" <?=$sel('owned_plant',$o)?>>£<?=number_format($o)?></option><?php endforeach; ?></select></label>
    <label>Years experience<select name="years_experience"><?php foreach(['Over 3 years','Between 2 and 3 years','Between 1 and 2 years','Less than 1 year'] as $yb): ?><option <?=$sel('years_experience',$yb)?>><?=e($yb)?></option><?php endforeach; ?></select></label>
  </div>
  <?php endif; ?>

  <div class="form-actions"><button type="submit" name="action" value="calc"><?= $mode==='cancel'?'Calculate return':'Recalculate' ?></button></div>

<?php if ($result): ?>
  <h2 style="margin-top:22px"><?= $mode==='cancel'?'Return premium':'Adjustment premium' ?></h2>
  <table class="grid mini">
    <tbody>
      <?php if ($mode==='adjust'): ?>
        <tr><td>Old annual premium (net)</td><td class="r"><?=money($oldNet)?></td></tr>
        <tr><td>New annual premium (net)</td><td class="r"><?=money($result['newNet'])?></td></tr>
      <?php else: ?>
        <tr><td>In-force annual premium (net)</td><td class="r"><?=money($oldNet)?></td></tr>
      <?php endif; ?>
      <tr><td>Portion of period remaining</td><td class="r"><?=number_format($result['factor']*100,1)?>%</td></tr>
      <tr><td>Calculated <?= $mode==='cancel'?'return':'additional/return' ?> (net)</td><td class="r"><?=money($result['computed'])?></td></tr>
    </tbody>
  </table>
  <div class="grid2" style="margin-top:10px">
    <label>Override net figure (edit to overwrite)<input name="override" type="number" step="0.01" value="<?= e(number_format($result['override'],2,'.','')) ?>"></label>
    <label>Policy fee (12.5% — editable)<input name="fee_override" type="number" step="0.01" value="<?= e(number_format($result['fee'],2,'.','')) ?>"></label>
  </div>
  <p class="sub">The figures below are what will be <strong>saved and printed on the documents</strong>. Edit either box (or press
     <em>Set premium to nil</em>) and click <em>Recalculate</em> to update. <strong>Nothing is saved</strong> until you click
     <em>Save</em> &amp; create document — and you can adjust it again after that with a further adjustment.</p>
  <div class="form-actions" style="margin:2px 0 6px">
    <button type="submit" name="action" value="calc" class="btn ghost">Recalculate</button>
    <button type="submit" name="nil" value="1" class="btn ghost">Set premium to nil (£0.00)</button>
  </div>
  <table class="grid mini" style="margin-top:8px"><tbody>
    <tr><td><?= $mode==='cancel'?'Return':'Additional' ?> net premium</td><td class="r"><?=money($result['override'])?></td></tr>
    <tr><td>IPT (12%)</td><td class="r"><?=money($result['ipt'])?></td></tr>
    <tr><td>Policy fee</td><td class="r"><?=money($result['fee'])?></td></tr>
    <tr class="tot"><td>Total <?= $mode==='cancel'?'return':'additional' ?> premium</td><td class="r"><?=money($result['total'])?></td></tr>
  </tbody></table>
  <div class="form-actions">
    <button type="submit" name="action" value="save" onclick="return confirmSave(this.form)"><?= $mode==='cancel'?'Save cancellation':'Save adjustment' ?> &amp; create document</button>
    <a class="btn ghost" href="case.php?id=<?= $case_id ?>">Cancel</a>
  </div>
  <script>
  function confirmSave(f){
    var iptPct = <?= (float)scheme_cfg('ipt_pct', 12.0) ?>/100;
    var net = parseFloat(f.override.value || 0) || 0;
    var fee = parseFloat(f.fee_override.value || 0) || 0;
    var ipt = net * iptPct, total = net + ipt + fee;
    var m = function(n){ return '£' + n.toFixed(2); };
    return confirm('Confirm and issue documents to the case?\n\n' +
      'Net premium: ' + m(net) + '\nIPT: ' + m(ipt) + '\nPolicy fee: ' + m(fee) +
      '\n----------------------------\nTotal: ' + m(total) +
      (net === 0 && fee === 0 ? '\n\n(Nil premium — a £0.00 adjustment will be recorded.)' : ''));
  }
  </script>
<?php endif; ?>
</form>
<?php cases_footer();
