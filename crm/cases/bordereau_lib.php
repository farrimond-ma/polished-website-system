<?php
/** Insurer bordereau (monthly movements) — the 39-column format the insurer expects. */
declare(strict_types=1);

function bordereau_headers(): array {
    return ['Record Id','Inception Date','Expiry Date','Effective Date','Insurer_Policy_Number','Status',
        'Business Description','Client Name','Client Address','PostCode','Turnover','Wageroll - Employees',
        'Wageroll - Self Employed','BFSC Payments','PL Premium','EL Premium','IPT (Liability)','Commission (Liability)',
        'Payment (Liability)','Tools & Equipment Premium','Hired Plant Premium','D&O Premium','D&O IPT','D&O Commission',
        'D&O Payment','Risk Reference','QuotationProductID','EL Limit','PL Limit','D&O Sum Insured','Tools Sum Insured',
        'Hired Plant Sum Insured','Hiring Charges','CAR IPT','CAR Commission','CAR Payment','Commission Rate Used (%)',
        'ERN Number','Status'];
}

/** Rows for the insurer bordereau between two effective dates (each row = a transaction's movement). */
function bordereau_rows(PDO $db, string $from, string $to): array {
    $sql = "SELECT t.*, c.insurer_policy_number, c.status AS case_status,
                   COALESCE(NULLIF(cl.trading_name,''),NULLIF(cl.joint_names,''),cl.client_ref) AS client_name,
                   cl.addr1, cl.addr2, cl.town, cl.county, cl.postcode,
                   (SELECT limit_of_indemnity FROM cover_selection WHERE term_id=t.term_id AND part_code='B') AS pl_limit,
                   (SELECT limit_of_indemnity FROM cover_selection WHERE term_id=t.term_id AND part_code='A' AND included=1) AS el_limit,
                   (SELECT limit_of_indemnity FROM cover_selection WHERE term_id=t.term_id AND part_code='J') AS plant_si,
                   (SELECT answer_value FROM risk_answer WHERE term_id=t.term_id AND question_key='Client_Business_Description' LIMIT 1) AS biz
            FROM policy_term t
            JOIN case_policy c ON c.case_id=t.case_id
            JOIN client cl ON cl.client_ref=c.client_ref
            WHERE DATE(t.effective_date) BETWEEN ? AND ?
            ORDER BY t.effective_date, t.case_id, t.term_id";
    $st = $db->prepare($sql); $st->execute([$from, $to]);
    $terms = $st->fetchAll(PDO::FETCH_ASSOC);

    $prevStmt = $db->prepare("SELECT premium_breakdown, quote_inputs,
                   (SELECT limit_of_indemnity FROM cover_selection WHERE term_id=p.term_id AND part_code='B') AS pl_limit,
                   (SELECT limit_of_indemnity FROM cover_selection WHERE term_id=p.term_id AND part_code='A' AND included=1) AS el_limit
                   FROM policy_term p WHERE p.case_id=? AND p.term_id<? ORDER BY p.term_id DESC LIMIT 1");

    $dt = fn($v) => $v ? date('d/m/Y', strtotime((string)$v)) : '';
    $g  = fn($a, $k) => (float)($a[$k] ?? 0);
    $rows = [];
    foreach ($terms as $t) {
        $qi = json_decode((string)($t['quote_inputs'] ?? ''), true) ?: [];
        $pb = json_decode((string)($t['premium_breakdown'] ?? ''), true) ?: [];
        $tt = (string)$t['transaction_type'];
        $isCancel = stripos($tt, 'cancel') !== false;
        $isAdjust = stripos($tt, 'adjust') !== false;

        $prevPb = $prevQi = []; $prevPl = 0.0; $prevEl = 0.0;
        if ($isCancel || $isAdjust) {
            $prevStmt->execute([$t['case_id'], $t['term_id']]);
            if ($pr = $prevStmt->fetch(PDO::FETCH_ASSOC)) {
                $prevPb = json_decode((string)($pr['premium_breakdown'] ?? ''), true) ?: [];
                $prevQi = json_decode((string)($pr['quote_inputs'] ?? ''), true) ?: [];
                $prevPl = (float)($pr['pl_limit'] ?? 0); $prevEl = (float)($pr['el_limit'] ?? 0);
            }
        }

        if ($isCancel) {                                   // full reversal of the in-force policy
            $pl=-$g($prevPb,'pl'); $el=-$g($prevPb,'el'); $tools=-$g($prevPb,'tools'); $hired=-$g($prevPb,'hired'); $do=-$g($prevPb,'do');
            $turn=-$g($prevQi,'turnover'); $we=-$g($prevQi,'wr_employed'); $ws=-$g($prevQi,'wr_selfemp'); $bf=-$g($prevQi,'bfsc_payments');
            $plLim=-$prevPl; $elLim=-$prevEl; $statusCol='Cancellation';
        } elseif ($isAdjust) {                             // change in written premium (new - previous)
            $pl=$g($pb,'pl')-$g($prevPb,'pl'); $el=$g($pb,'el')-$g($prevPb,'el'); $tools=$g($pb,'tools')-$g($prevPb,'tools');
            $hired=$g($pb,'hired')-$g($prevPb,'hired'); $do=$g($pb,'do')-$g($prevPb,'do');
            $turn=$g($qi,'turnover')-$g($prevQi,'turnover'); $we=$g($qi,'wr_employed')-$g($prevQi,'wr_employed');
            $ws=$g($qi,'wr_selfemp')-$g($prevQi,'wr_selfemp'); $bf=$g($qi,'bfsc_payments')-$g($prevQi,'bfsc_payments');
            $plLim=(float)($t['pl_limit'] ?? 0); $elLim=(float)($t['el_limit'] ?? 0);
            $statusCol = ($pl+$el+$tools+$hired+$do) >= 0 ? 'Additional Premium' : 'Refund Premium';
        } else {                                           // New Business / Renewal — full written premium
            $pl=$g($pb,'pl'); $el=$g($pb,'el'); $tools=$g($pb,'tools'); $hired=$g($pb,'hired'); $do=$g($pb,'do');
            $turn=$g($qi,'turnover'); $we=$g($qi,'wr_employed'); $ws=$g($qi,'wr_selfemp'); $bf=$g($qi,'bfsc_payments');
            $plLim=(float)($t['pl_limit'] ?? 0); $elLim=(float)($t['el_limit'] ?? 0);
            $statusCol = (stripos($tt, 'new') !== false) ? 'New' : ($tt ?: 'Renewal');
        }

        $liab=$pl+$el; $liabIpt=round($liab*0.12,2); $liabComm=round($liab*0.25,2); $liabPay=round($liab+$liabIpt-$liabComm,2);
        $car=$tools+$hired; $carIpt=round($car*0.12,2); $carComm=round($car*0.25,2); $carPay=round($car+$carIpt-$carComm,2);
        $doIpt=round($do*0.12,2); $doComm=round($do*0.25,2); $doPay=round($do+$doIpt-$doComm,2);
        $addr = trim(implode(', ', array_filter([$t['addr1'],$t['addr2'],$t['town'],$t['county']])));

        $rows[] = [
            $t['case_id'], $dt($t['inception_date']), $dt($t['expiry_date']), $dt($t['effective_date']),
            $t['insurer_policy_number'], $statusCol, $t['biz'], $t['client_name'], $addr, $t['postcode'],
            round($turn,2), round($we,2), round($ws,2), round($bf,2),
            round($pl,2), round($el,2), $liabIpt, $liabComm, $liabPay,
            round($tools,2), round($hired,2), round($do,2), $doIpt, $doComm, $doPay,
            '', '', round($elLim), round($plLim), ($do != 0 ? 100000 : 0),
            ($tools != 0 ? ($t['plant_si'] ?? '') : 'Not Insured'), 'Not Insured', 'Not Insured',
            $carIpt, $carComm, $carPay, 25, '', $t['case_status'],
        ];
    }
    return $rows;
}
