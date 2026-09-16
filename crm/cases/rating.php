<?php
/**
 * Rating engine — computes Polished Insurance premiums from the rate_table,
 * mirroring the validated Excel calculator. Pure functions; no output.
 */
declare(strict_types=1);

/** Load all scheme rates into a lookup structure (cached per request). */
function load_rates(int $scheme_id = 1): array {
    static $cache = [];
    if (isset($cache[$scheme_id])) return $cache[$scheme_id];
    $st = db()->prepare("SELECT part_code, pl_limit, band_label, turnover_band, rate_pct, flat_amount FROM rate_table WHERE scheme_id=?");
    $st->execute([$scheme_id]);
    $r = ['pct'=>[], 'flat'=>[], 'pi'=>[], 'plant'=>[]];
    foreach ($st->fetchAll() as $row) {
        $lim = $row['pl_limit'] !== null ? (string)(int)$row['pl_limit'] : '_';
        $key = $row['part_code'].'|'.$lim.'|'.$row['band_label'];
        if ($row['rate_pct'] !== null)  $r['pct'][$key]  = (float)$row['rate_pct'];
        if ($row['flat_amount'] !== null) $r['flat'][$key] = (float)$row['flat_amount'];
        if ($row['part_code'] === 'D' && $row['turnover_band'] !== null)
            $r['pi'][(int)$row['pl_limit']][] = ['band'=>(float)$row['turnover_band'], 'prem'=>(float)$row['flat_amount']];
        if ($row['part_code'] === 'J' && $row['turnover_band'] !== null)
            $r['plant'][$row['band_label']] = (float)$row['flat_amount'];
    }
    return $cache[$scheme_id] = $r;
}
function rpct(array $r, string $part, string $band, $limit=null): ?float {
    $lim = $limit !== null ? (string)(int)$limit : '_';
    return $r['pct']["$part|$lim|$band"] ?? null;
}
function rflat(array $r, string $part, string $band, $limit=null): ?float {
    $lim = $limit !== null ? (string)(int)$limit : '_';
    return $r['flat']["$part|$lim|$band"] ?? null;
}

/** Daily pro-rata factor: portion of the term still to run from the effective date. 0..1 */
function prorata_factor(?string $inception, ?string $expiry, ?string $effective): float {
    if (!$inception || !$expiry || !$effective) return 0.0;
    $d0 = strtotime($inception); $d1 = strtotime($expiry); $de = strtotime($effective);
    $total = ($d1 - $d0) / 86400 + 1;
    if ($total <= 0) return 0.0;
    $remaining = ($d1 - $de) / 86400 + 1;
    return max(0.0, min(1.0, $remaining / $total));
}

/** Rebuild calculate_quote() inputs for an existing term (from stored JSON, else from cover+rating). */
function reconstruct_inputs(PDO $db, int $termId): array {
    $t = $db->prepare("SELECT quote_inputs FROM policy_term WHERE term_id=?");
    $t->execute([$termId]);
    $qi = $t->fetchColumn();
    if ($qi) { $a = json_decode($qi, true); if (is_array($a) && $a) return $a; }

    $in = ['who_works' => 'Me and others', 'pl_limit' => 1000000, 'el_included' => 'No',
           'prof_advice' => 'None', 'pi' => 'None', 'do' => '', 'owned_plant' => 'None',
           'years_experience' => 'Over 3 years'];
    $cs = $db->prepare("SELECT part_code, included, limit_of_indemnity FROM cover_selection WHERE term_id=?");
    $cs->execute([$termId]);
    foreach ($cs->fetchAll(PDO::FETCH_ASSOC) as $c) {
        if ($c['part_code'] === 'B') $in['pl_limit'] = (int)$c['limit_of_indemnity'];
        if ($c['part_code'] === 'A' && $c['included']) $in['el_included'] = '1';
        if ($c['part_code'] === 'C' && $c['included']) $in['do'] = '1';
        if ($c['part_code'] === 'D' && $c['included']) $in['pi'] = (string)(int)$c['limit_of_indemnity'];
        if ($c['part_code'] === 'J' && $c['included']) $in['owned_plant'] = (string)(int)$c['limit_of_indemnity'];
    }
    $map = ['Turnover Internal & Ground' => 'to_ig', 'Turnover up to 15m' => 'to_15m',
            'Turnover up to 100m' => 'to_100m', 'Turnover for BFSC' => 'to_bfsc',
            'Internal Wage Roll' => 'wr_internal', 'Ground Wage Roll' => 'wr_ground',
            'Up to 15m Wage Roll' => 'wr_15m', 'Up to 100m Wage Roll' => 'wr_100m'];
    $rl = $db->prepare("SELECT line_label, exposure FROM rating_line WHERE term_id=?");
    $rl->execute([$termId]);
    foreach ($rl->fetchAll(PDO::FETCH_ASSOC) as $r)
        if (isset($map[$r['line_label']])) $in[$map[$r['line_label']]] = (float)$r['exposure'];
    $in['turnover'] = ($in['to_ig'] ?? 0) + ($in['to_15m'] ?? 0) + ($in['to_100m'] ?? 0) + ($in['to_bfsc'] ?? 0);
    $in['wr_employed'] = ($in['wr_internal'] ?? 0) + ($in['wr_ground'] ?? 0) + ($in['wr_15m'] ?? 0) + ($in['wr_100m'] ?? 0);
    return $in;
}

/** Persist a calculate_quote() result to a term (cover_selection + rating_line + rating_adjustment). */
function persist_quote_to_term(PDO $db, int $termId, array $in, array $result): void {
    $db->prepare("DELETE FROM rating_line WHERE term_id=?")->execute([$termId]);
    $db->prepare("DELETE FROM rating_adjustment WHERE term_id=?")->execute([$termId]);
    $db->prepare("DELETE FROM cover_selection WHERE term_id=?")->execute([$termId]);
    foreach ($result['parts'] as $pc => $p) {
        if ($pc === 'B' || ($pc === 'A' && !empty($in['el_included']))) {
            foreach (($p['lines'] ?? []) as $ln)
                $db->prepare("INSERT INTO rating_line (term_id,part_code,insurer_id,line_label,exposure,rate_pct,premium) VALUES (?,?,1,?,?,?,?)")
                   ->execute([$termId, $pc, $ln[0], $ln[1], $ln[2], round((float)$ln[3], 4)]);
            $db->prepare("INSERT INTO rating_adjustment (term_id,part_code,basic_total,minimum_premium,ni_load_pct,experience_load_pct,discretionary_pct,net_total,commission_from_insurer_pct,commission_to_allied_pct) VALUES (?,?,?,?,?,?,?,?,?,?)")
               ->execute([$termId, $pc, round((float)($p['basic'] ?? 0), 2), 0, 0, (float)($in['nv_load_pct'] ?? 0), (float)($in['discretionary_pct'] ?? 0), round((float)($p['net'] ?? $p['premium']), 2), 25, 0]);
        }
        if (in_array($pc, ['A','B','C','D','J'], true) && ($p['premium'] ?? 0) > 0) {
            $lim = $pc === 'B' ? $result['limit'] : ($pc === 'A' ? 10000000 : null);
            $db->prepare("INSERT INTO cover_selection (term_id,part_code,included,limit_of_indemnity) VALUES (?,?,1,?)")
               ->execute([$termId, $pc, $lim]);
        }
    }
}

/**
 * SchemeServe-style split: from total turnover / total wageroll + work-split percentages,
 * derive the PL turnover and EL wageroll height splits the rating engine needs.
 * Triggered when any percentage field is present; otherwise the explicit split £ inputs are used as-is.
 */
function derive_splits(array $in): array {
    $pctKeys = ['pct_inside','pct_outside','pct_1_5m','pct_5_15m','pct_15_25m','pct_over_25m'];
    $has = false; foreach ($pctKeys as $k) if (isset($in[$k]) && $in[$k] !== '') { $has = true; break; }
    if (!$has) return $in;
    $n = fn($k) => (float)($in[$k] ?? 0);
    $turn = $n('turnover'); $bfsc = $n('bfsc_payments');
    $wage = $n('wr_employed') + $n('wr_selfemp');
    $inside = $n('pct_inside'); $outside = $n('pct_outside');
    $work15  = $n('pct_1_5m') + $n('pct_5_15m');           // 1–15m
    $work100 = $n('pct_15_25m') + $n('pct_over_25m');      // 15m+
    $igPct = max(0.0, 100 - $work15 - $work100);
    // Part B — turnover
    $excbf = max(0.0, $turn - $bfsc);
    $in['to_ig']   = $excbf * $igPct   / 100;
    $in['to_15m']  = $excbf * $work15  / 100;
    $in['to_100m'] = $excbf * $work100 / 100;
    $in['to_bfsc'] = $bfsc;
    // Part A — wageroll
    $in['wr_15m']      = $wage * $work15  / 100;
    $in['wr_100m']     = $wage * $work100 / 100;
    $in['wr_internal'] = $wage * $inside  / 100;
    $in['wr_ground']   = max(0.0, $wage * $outside / 100 - ($in['wr_15m'] + $in['wr_100m']));
    return $in;
}

/** scheme_config key => value (ipt_pct, policy_fee_pct, ...). Falls back to live defaults. */
function scheme_config(): array {
    static $c = null;
    if ($c !== null) return $c;
    $c = ['ipt_pct'=>12.0,'policy_fee_pct'=>12.5,'commission_from_insurer_pct'=>25.0,'credit_interest_pct'=>5.96];
    try { foreach (db()->query("SELECT ckey,cvalue FROM scheme_config")->fetchAll() as $r) $c[$r['ckey']] = (float)$r['cvalue']; }
    catch (Throwable $e) { /* table not migrated yet — use defaults */ }
    return $c;
}
/** risk-group minimum NET premiums (from the live NP config). */
function min_net_rows(): array {
    static $rows = null;
    if ($rows !== null) return $rows;
    try { $rows = db()->query("SELECT * FROM min_net_premium")->fetchAll(); }
    catch (Throwable $e) { $rows = []; }
    return $rows;
}
function pl_min_net(string $who, int $limit, string $over75k, string $partner): float {
    foreach (min_net_rows() as $r) {
        if ($r['part_code'] !== 'B') continue;
        if ((int)$r['pl_limit'] !== $limit) continue;
        if ($who === 'Just me') {
            if ($r['who_works'] !== 'Just me') continue;
            if ($r['over_75k'] !== null && $r['over_75k'] !== $over75k) continue;
            return (float)$r['min_net'];
        }
        if ($r['who_works'] !== 'Me and others') continue;
        $wantPartner = ($partner === 'Yes') ? 'Yes' : null;
        if (($r['just_partner'] ?? null) !== $wantPartner) continue;
        return (float)$r['min_net'];
    }
    return 0.0;
}
/** experience (new-venture) loading % by years band. */
function experience_loading(): array {
    static $m = null;
    if ($m !== null) return $m;
    $m = ['Less than 1 year'=>10.0,'Between 1 and 2 years'=>7.5,'Between 2 and 3 years'=>5.0,'Over 3 years'=>0.0];
    try { foreach (db()->query("SELECT years_band,load_pct FROM experience_loading")->fetchAll() as $r) $m[$r['years_band']] = (float)$r['load_pct']; }
    catch (Throwable $e) { /* not migrated — use defaults */ }
    return $m;
}
function el_min_net(string $who, string $partner, string $height): float {
    if ($who !== 'Me and others') return 0.0;
    if ($partner === 'Yes') return 0.0;
    foreach (min_net_rows() as $r) {
        if ($r['part_code'] !== 'A') continue;
        if (($r['just_partner'] ?? null) === 'Yes') continue;
        if ($r['height_work'] !== null && $r['height_work'] !== $height) continue;
        return (float)$r['min_net'];
    }
    return $height === 'Yes' ? 250.0 : 132.0;
}

/**
 * @param array $in keys: pl_limit, el_included(bool), turnover, wr_employed, wr_selfemp,
 *   to_ig,to_15m,to_100m,to_bfsc, wr_internal,wr_ground,wr_15m,wr_100m,
 *   prof_advice('None'|'100000'|'250000'), do(bool), pi('None'|'100000'|'250000'),
 *   owned_plant(int|'None'), hired_si(int|'None'), hired_charges(str), fidelity(bool), ni(bool),
 *   years_exp, disc_noclaims,disc_lowclaims,disc_est,disc_noladder,disc_fwc,disc_accred,disc_hs (bool),
 *   discretionary_pct(0..100), nv_load_pct, max_height, bfsc_payments,
 *   title, entity, claims(bool), claims_count, claims_value, declaration_ok(bool)
 */
function calculate_quote(array $in): array {
    $in = derive_splits($in);   // SS-style: totals + work-split % -> height splits
    $R = load_rates((int)($in['scheme_id'] ?? 1));
    $limit = (int)($in['pl_limit'] ?? 1000000);
    $num = fn($k)=> (float)($in[$k] ?? 0);
    $yes = fn($k)=> !empty($in[$k]) && $in[$k] !== 'No' && $in[$k] !== '0';

    $who     = $in['who_works'] ?? 'Me and others';
    $partner = $yes('just_partner') ? 'Yes' : 'No';
    $over75k = $num('turnover') > 75000 ? 'Yes' : 'No';

    $niEL = $yes('ni') ? (rpct($R,'A','NI Load (EL)')/100) : 0.0;
    $niPL = $yes('ni') ? (rpct($R,'B','NI Load (PL)')/100) : 0.0;
    // Experience/new-venture loading: auto from years band + optional manual extra
    $expMap  = experience_loading();
    $yearsBand = $in['years_experience'] ?? 'Over 3 years';
    $expPct  = $expMap[$yearsBand] ?? 0.0;
    $nv   = ($expPct + $num('nv_load_pct'))/100;
    $discr= $num('discretionary_pct')/100;

    // general discounts (EL & PL); NoClaims XOR LowClaims + others
    $g = 0.0;
    if ($yes('disc_noclaims'))      $g += rpct($R,'B','Discount No Claims')/100;
    elseif ($yes('disc_lowclaims')) $g += rpct($R,'B','Discount Low Claims')/100;
    if ($yes('disc_est'))    $g += rpct($R,'B','Discount Established 5+ yrs')/100;
    if ($yes('disc_accred')) $g += rpct($R,'B','Discount Professional Accreditations')/100;
    if ($yes('disc_hs'))     $g += rpct($R,'B','Discount H&S Policy')/100;
    $g = min($g, 0.25);
    // EL height-rate discount (no ladders XOR FWC)
    $hd = $yes('disc_noladder') ? rpct($R,'A','Discount Height excl ladders')/100
        : ($yes('disc_fwc') ? rpct($R,'A','Discount Federation Window Cleaners')/100 : 0.0);

    $flags = [];
    $addflag = function($sev,$msg) use (&$flags){ $flags[]=['sev'=>$sev,'msg'=>$msg]; };

    /* ---------- Part A: Employers' Liability ---------- */
    $A = ['lines'=>[], 'premium'=>0.0];
    if ($yes('el_included')) {
        $ai = $num('wr_internal') * (rpct($R,'A','Internal Wage Roll')/100);
        $ag = $num('wr_ground')   * (rpct($R,'A','Ground Wage Roll')/100);
        $a15= $num('wr_15m')      * (rpct($R,'A','Up to 15m Wage Roll')/100);
        $a100=$num('wr_100m')     * (rpct($R,'A','Up to 100m Wage Roll')/100);
        $aHeight = ($a15+$a100)*(1-$hd);
        $basic = $ai+$ag+$aHeight;
        $adj = $basic*(1-$g)*(1+$nv)*(1+$niEL)*(1-$discr);
        $fid = $yes('fidelity') ? (rpct($R,'A','Fidelity Guarantee (ZCL009-D)')/100)*($num('wr_employed')+$num('wr_selfemp')) : 0.0;
        $heightWork = ($num('wr_15m')+$num('wr_100m')) > 0 ? 'Yes' : 'No';
        $min = el_min_net($who, $partner, $heightWork);   // risk-group 7249 min net (floor)
        $A['lines'] = [
            ['Internal Wage Roll',$num('wr_internal'),rpct($R,'A','Internal Wage Roll'),$ai],
            ['Ground Wage Roll',$num('wr_ground'),rpct($R,'A','Ground Wage Roll'),$ag],
            ['Up to 15m Wage Roll',$num('wr_15m'),rpct($R,'A','Up to 15m Wage Roll'),$a15],
            ['Up to 100m Wage Roll',$num('wr_100m'),rpct($R,'A','Up to 100m Wage Roll'),$a100],
        ];
        $A['basic']=$basic; $A['fidelity']=$fid; $A['net']=$adj;
        $A['premium'] = max($adj,$min)+$fid;
    }

    /* ---------- Part B: Public & Products Liability ---------- */
    $bIG  = $num('to_ig')  * (rpct($R,'B','Turnover Internal & Ground',$limit)/100);
    $r15  = rpct($R,'B','Turnover up to 15m',$limit);
    $r100 = rpct($R,'B','Turnover up to 100m',$limit);
    $b15  = $num('to_15m') * (($r15??0)/100);
    if ($num('to_100m')>0 && $r100===null) $addflag('CHECK',"PL 'up to 100m' rate for £".number_format($limit)." limit is not set (TBC) — 100m turnover not rated.");
    $b100 = $num('to_100m')* (($r100??0)/100);
    $bBF  = $num('to_bfsc')* (rpct($R,'B','Turnover for BFSC',$limit)/100);
    $bBasic = $bIG+$b15+$b100+$bBF;
    $bAdj = $bBasic*(1-$g)*(1+$nv)*(1+$niPL)*(1-$discr);
    $pa = $in['prof_advice'] ?? 'None';
    $paAdd = $pa==='100000' ? (rflat($R,'','Professional Advice — £100,000 limit') ?? 100) : ($pa==='250000' ? 250 : 0);
    // professional advice flats are stored on part B via generic band; fall back to fixed
    if ($pa==='100000') $paAdd = 100; if ($pa==='250000') $paAdd = 250;
    $bMin = pl_min_net($who, $limit, $over75k, $partner);   // risk-group 7083 min net (floor)
    $B = ['lines'=>[
            ['Turnover Internal & Ground',$num('to_ig'),rpct($R,'B','Turnover Internal & Ground',$limit),$bIG],
            ['Turnover up to 15m',$num('to_15m'),$r15,$b15],
            ['Turnover up to 100m',$num('to_100m'),$r100,$b100],
            ['Turnover for BFSC',$num('to_bfsc'),rpct($R,'B','Turnover for BFSC',$limit),$bBF],
          ],
          'basic'=>$bBasic,'net'=>$bAdj,'prof_advice'=>$paAdd,
          'premium'=>max($bAdj,$bMin)+$paAdd];

    /* ---------- Part C: D&O ---------- */
    $C = ['premium'=> $yes('do') ? (rflat($R,'C','D&O Flat Premium (100,000 LOI)') ?? 110) : 0.0];

    /* ---------- Part D: PI ---------- */
    $D = ['premium'=>0.0];
    if (($in['pi'] ?? 'None') !== 'None') {
        $limPI = (int)$in['pi']; $to = $num('turnover'); $prem = null;
        $grid = $R['pi'][$limPI] ?? [];
        usort($grid, fn($a,$b)=>$a['band']<=>$b['band']);
        foreach ($grid as $cell) { if ($to <= $cell['band']) { $prem = $cell['prem']; break; } }
        if ($prem === null && $grid) $prem = end($grid)['prem'];
        $D['premium'] = max(190.0, (float)($prem ?? 190));
        if (($in['prof_advice'] ?? 'None') === 'None') $addflag('STOP','Part D PI is operative — Professional Advice extension must also be added.');
    }

    /* ---------- Part J: plant ---------- */
    $ownMap = [1000=>'Owned Plant 1,000',6000=>'Owned Plant 6,000',10000=>'Owned Plant 10,000',15000=>'Owned Plant 15,000',
               20000=>'Owned Plant 20,000',25000=>'Owned Plant 25,000',30000=>'Owned Plant 30,000',35000=>'Owned Plant 35,000',50000=>'Owned Plant 50,000'];
    $ownPrem = 0.0;
    if (isset($in['owned_plant']) && $in['owned_plant']!=='None' && isset($ownMap[(int)$in['owned_plant']]))
        $ownPrem = $R['plant'][$ownMap[(int)$in['owned_plant']]] ?? 0.0;
    // Hired-in plant: live model keyed on hiring charges only (SI = charges).
    // Match on digits so it is robust to the '£' sign / encoding.
    $hiredPrem = 0.0; $chg = (string)($in['hired_charges'] ?? 'N/A');
    if (stripos($chg, 'Over') !== false)          $hiredPrem = 0.0;                 // refer
    elseif (strpos($chg, '10,000') !== false)     $hiredPrem = $R['plant']['Hired Plant hiring charges up to 10,000'] ?? 300.0;
    elseif (strpos($chg, '5,000')  !== false)     $hiredPrem = $R['plant']['Hired Plant hiring charges up to 5,000'] ?? 150.0;
    $J = ['owned'=>$ownPrem,'hired'=>$hiredPrem,'premium'=>$ownPrem+$hiredPrem];

    $total = $A['premium']+$B['premium']+$C['premium']+$D['premium']+$J['premium'];

    /* ---------- referral / decline flags ---------- */
    $t = $in['title'] ?? ''; $entity = $in['entity'] ?? '';
    if (in_array($t,['Rev.','Exec(s) of'],true)) $addflag('STOP',"Proposer title '$t' — refer for Camberford approval.");
    if ($entity==='Community Interest Company') $addflag('STOP','CIC — discuss with Camberford before proceeding.');
    if ($num('turnover')>1500000) $addflag('REFER','Turnover exceeds £1,500,000 — refer to Camberford for terms.');
    if (($num('wr_employed')+$num('wr_selfemp'))>600000) $addflag('REFER','Wageroll exceeds £600,000 — refer to Camberford.');
    if (($num('wr_employed')+$num('wr_selfemp'))>$num('turnover') && $num('turnover')>0) $addflag('STOP','Wageroll exceeds turnover — query and correct.');
    if ($num('bfsc_payments')>0) $addflag('STOP','BFSC payments disclosed — query (confirm genuine BFSC vs LOSC).');
    if ($num('bfsc_payments')>100000 || ($num('turnover')>0 && $num('bfsc_payments')>0.1*$num('turnover'))) $addflag('REFER','BFSC payments > £100k or >10% turnover — refer.');
    if ($num('max_height')>25) $addflag('REFER','Height work exceeds 25m — refer / unacceptable.');
    if (isset($in['owned_plant']) && $in['owned_plant']!=='None' && (int)$in['owned_plant']>50000) $addflag('REFER','Owned plant exceeds £50,000 — refer.');
    if (stripos((string)($in['hired_charges'] ?? ''),'Over')!==false) $addflag('STOP','Hired-in charges over £10,000 — present to Camberford.');
    if ($total>10000) $addflag('REFER','Total premium exceeds £10,000 — refer to Camberford.');
    if ($yes('claims')) $addflag('STOP','Claims/incidents declared — obtain full details.');
    if (in_array(($in['claims_count'] ?? ''),['3','4+'],true)) $addflag('REFER','3+ claims in 5 years — refer / likely decline.');
    if (($in['claims_value'] ?? '')==='£5,000 +') $addflag('REFER','Claims value £5,000+ — refer.');
    if (isset($in['declaration_ok']) && !$yes('declaration_ok')) $addflag('STOP','Declaration not fully agreed — refer.');
    if ($yearsBand !== 'Over 3 years') $addflag('CHECK',"Experience '$yearsBand' — {$expPct}% experience loading auto-applied to PL/EL.");

    $order=['STOP'=>0,'DECLINE'=>1,'REFER'=>2,'CHECK'=>3];
    usort($flags, fn($a,$b)=>$order[$a['sev']]<=>$order[$b['sev']]);
    $status = 'PROCEED';
    foreach (['STOP','DECLINE','REFER','CHECK'] as $s) foreach ($flags as $f) if ($f['sev']===$s){ $status=$s; break 2; }

    // Net -> IPT -> policy fee -> gross (from scheme_config)
    $cfg  = scheme_config();
    $net  = $total;
    $ipt  = $net * ($cfg['ipt_pct']/100);
    $fee  = ($net + $ipt) * ($cfg['policy_fee_pct']/100);
    $gross= $net + $ipt + $fee;

    return ['parts'=>['A'=>$A,'B'=>$B,'C'=>$C,'D'=>$D,'J'=>$J],
            'total'=>$net,'net'=>$net,'ipt'=>$ipt,'policy_fee'=>$fee,'gross'=>$gross,
            'flags'=>$flags,'status'=>$status,'limit'=>$limit];
}
