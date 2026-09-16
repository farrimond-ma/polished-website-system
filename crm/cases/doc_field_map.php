<?php
/**
 * FIELD MAP  —  THIS IS THE FILE YOU EDIT TO MAP DOCUMENT FIELDS.
 * =============================================================================
 * For a given case it returns $d[...] = value for every SchemeServe token used
 * in the templates. Anything left unmapped shows in the document as [TokenName]
 * so you can spot it and add it here.
 *
 * To map a field: set  $d['TokenName'] = <value from the database>;
 * The database rows available to you below are: $client, $case, $term,
 * $covers (part_code => limit), and $rating (net/ipt/fee/gross).
 * =============================================================================
 */
declare(strict_types=1);
require_once __DIR__ . '/rating.php';       // for scheme_config()
require_once __DIR__ . '/doc_engine.php';   // for doc_render() (used by doc_generate_and_save)

function doc_build_data(PDO $db, int $case_id, string $docType = ''): array {
    // ---- pull the case data ----
    $st = $db->prepare("SELECT * FROM client cl JOIN case_policy c ON c.client_ref=cl.client_ref WHERE c.case_id=?");
    $st->execute([$case_id]);
    $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
    $t = $db->prepare("SELECT * FROM policy_term WHERE case_id=? ORDER BY term_id DESC LIMIT 1");
    $t->execute([$case_id]); $term = $t->fetch(PDO::FETCH_ASSOC) ?: [];
    $termId = $term['term_id'] ?? 0;

    $covers = [];
    if ($termId) {
        $cs = $db->prepare("SELECT part_code, included, limit_of_indemnity FROM cover_selection WHERE term_id=?");
        $cs->execute([$termId]);
        foreach ($cs->fetchAll(PDO::FETCH_ASSOC) as $c)
            $covers[$c['part_code']] = ['inc' => (int)$c['included'], 'limit' => (float)$c['limit_of_indemnity']];
    }
    $plLimit = $covers['B']['limit'] ?? 0;
    $elLimit = ($covers['A']['inc'] ?? 0) ? ($covers['A']['limit'] ?? 0) : 0;

    // ---- premiums ----
    // For an Adjustment/Cancellation document the premium tokens show the pro-rated
    // ADDITIONAL/RETURN premium (from term.adjustment); otherwise the annual premium.
    $cfg = scheme_config();
    $tt = strtolower((string)($term['transaction_type'] ?? ''));
    $isAdj = ($docType === 'adjustment') || strpos($tt, 'adjust') !== false || strpos($tt, 'cancel') !== false;
    if ($isAdj) {
        $net = (float)($term['adjustment'] ?? 0);
        $ipt = $net * ($cfg['ipt_pct'] / 100);
        $fee = (isset($term['policy_fee']) && $term['policy_fee'] !== null)
             ? (float)$term['policy_fee']
             : round(($net + $ipt) * ($cfg['policy_fee_pct'] / 100), 2);
        $gross = $net + $ipt + $fee;
    } else {
        $net = (float)($term['total_premium'] ?? 0);
        $ipt = $net * ($cfg['ipt_pct'] / 100);
        $fee = ($net + $ipt) * ($cfg['policy_fee_pct'] / 100);
        $gross = $net + $ipt + $fee;
    }

    // ---- endorsements memorandum ----
    $memo = '';
    if ($termId) {
        $en = $db->prepare("SELECT en.endorsement_code, en.title, ae.applies_to_text
                            FROM applied_endorsement ae JOIN endorsement en ON en.endorsement_code=ae.endorsement_code
                            WHERE ae.term_id=?");
        $en->execute([$termId]);
        foreach ($en->fetchAll(PDO::FETCH_ASSOC) as $e)
            $memo .= '<p><strong>' . htmlspecialchars($e['endorsement_code']) . ' — ' . htmlspecialchars($e['title']) . '</strong><br>' . htmlspecialchars($e['applies_to_text'] ?? '') . '</p>';
    }

    $fmtd = fn($v) => $v ? date('d F Y', strtotime((string)$v)) : '';
    $addr = implode(', ', array_filter([$row['addr1'] ?? '', $row['addr2'] ?? '', $row['town'] ?? '', $row['county'] ?? '', $row['postcode'] ?? '']));
    $bizname = $row['trading_name'] ?: ($row['joint_names'] ?: trim(($row['title'] ?? '') . ' ' . ($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')));

    $d = [];

    // ===================== MAPPED (derived from your database) =====================
    $d['ClientName']              = $bizname;
    $d['Client.Firstname']        = $row['first_name'] ?? '';
    $d['Client.Surname']          = $row['last_name'] ?? '';
    $d['ClientAddress']           = $addr;
    $d['Client_Address']          = $addr;                 // template uses both spellings
    $d['CurrentDate']             = date('d F Y');
    $d['InceptionDate']           = $fmtd($term['inception_date'] ?? '');
    $d['ExpiryDate']              = $fmtd($term['expiry_date'] ?? '');
    // Effective date = the date this transaction takes effect (the adjustment/cancellation date;
    // equals inception for new business/renewal). Adjustment documents display this instead of inception.
    $d['EffectiveDate']           = $fmtd($term['effective_date'] ?? '') ?: $fmtd($term['inception_date'] ?? '');
    $d['TransactionType']         = (string)($term['transaction_type'] ?? '');
    $d['IsAdjustment']            = $isAdj ? 'Yes' : 'No';
    // Period-of-insurance start: an adjustment reissues documents running from its effective date;
    // new business / renewal run from inception. Templates use [PeriodStartDate] to [ExpiryDate].
    $d['PeriodStartDate']         = $isAdj ? $d['EffectiveDate'] : $d['InceptionDate'];
    $d['PolicyId']                = (string)$case_id;

    // premiums
    $d['TotalPremiumNet_Result']  = $net;
    $d['IPT_Result']              = $ipt;
    $d['PolicyFee_Result']        = $fee;
    $d['TotalPremium_Result']     = $gross;

    // cover limits / flags
    $d['PL_Limit']                = $plLimit;
    $d['PL_Excess']               = '250';
    $d['Policy_ELLimit_Cover']    = $elLimit;
    $d['DO_YN_Value']             = ($covers['C']['inc'] ?? 0) ? 'Yes' : 'No';
    $d['Tools_YN_Value']          = ($covers['J']['inc'] ?? 0) ? 'Yes' : 'No';
    $d['Policy_OwnPlantSI_Cover'] = $covers['J']['limit'] ?? 0;
    $d['Hired_PlantYN_Value']     = 'No';                  // TODO: set from your hired-plant data
    $d['Underwriting.Memorandum'] = $memo ?: 'None';

    // Policy number = [PolicyNoPrefix]/[Insurer_Policy_Number]/[PolicyNoSuffix]
    // Prefix + suffix come from the scheme row (edit via DB); number is per-case (falls back to case id).
    $schemeRow = [];
    try {
        $sc = $db->prepare("SELECT policy_no_prefix, policy_no_suffix FROM scheme WHERE scheme_id=?");
        $sc->execute([$row['scheme_id'] ?? 1]); $schemeRow = $sc->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) { /* 03_documents.sql not applied yet */ }
    $d['PolicyNoPrefix']        = $schemeRow['policy_no_prefix'] ?? 'ZCLP';
    $d['Insurer_Policy_Number'] = ($row['insurer_policy_number'] ?? '') ?: (string)$case_id;
    $d['PolicyNoSuffix']        = $schemeRow['policy_no_suffix'] ?? '1';

    // Client_Business_Description is auto-built from the activity answers further below.
    $d['Client_Business_Description'] = '';

    // ================= Statement of Fact — risk-answer tokens =================
    // Answers live in the risk_answer table with question_key == the SchemeServe
    // token name (e.g. 'Who_Works_In_Your_Business', 'Activities_WindowCleaning').
    // Each stored answer auto-populates both [Token] and [Token_Value].
    $ans = [];
    if ($termId) {
        $qa = $db->prepare("SELECT question_key, answer_value FROM risk_answer WHERE term_id=?");
        $qa->execute([$termId]);
        foreach ($qa->fetchAll(PDO::FETCH_ASSOC) as $r) $ans[$r['question_key']] = $r['answer_value'];
    }
    foreach ($ans as $k => $v) { $d[$k] = $v; $d[$k . '_Value'] = $v; }

    // ---- financial / structured fields derived from the rating (override) ----
    $ex = ['A' => 0.0, 'B' => 0.0];
    if ($termId) {
        $rl = $db->prepare("SELECT part_code, SUM(exposure) ex FROM rating_line WHERE term_id=? GROUP BY part_code");
        $rl->execute([$termId]);
        foreach ($rl->fetchAll(PDO::FETCH_ASSOC) as $r) $ex[$r['part_code']] = (float)$r['ex'];
    }
    $set = function ($k, $v) use (&$d, $ans) { $d[$k] = $ans[$k] ?? $v; $d[$k . '_Value'] = $ans[$k] ?? $v; };
    $set('Policy_Turnover_Cover',      $ex['B']);
    $set('Wageroll_Employees_Cover',   $ex['A']);
    $set('Wageroll_SelfEmployed_Cover', 0);
    $set('Wageroll_BFSC_Payments_Cover', 0);
    $set('Policy_Hiring_Charges_Cover', 0);
    $d['Tools_YN']            = ($covers['J']['inc'] ?? 0) ? 'Yes' : 'No';
    $d['Hired_PlantYN']       = $ans['Hired_PlantYN'] ?? 'No';
    $d['Hired_PlantYN_Value'] = $d['Hired_PlantYN'];
    $d['ELLimit_Cover']       = $elLimit;                       // 0 => PL-only layout
    // New business shows extra questions; renewals/adjustments hide them
    $tt = strtolower((string)($term['transaction_type'] ?? ''));
    $d['ExistingCaseYN'] = (strpos($tt, 'new') !== false) ? 'No' : 'Yes';

    // ---- default every remaining Statement-of-Fact token to blank ----
    // (so nothing shows as [Token]; fill the risk_answer table to populate them)
    $sofTokens = ['50ShareholderYN','Above1mYesNo','Activities_CarpetUpholsteryCleaning',
      'Activities_CommercialKitchenCleaning','Activities_CommercialPropertyCleaning',
      'Activities_CommercialPropertyCleaning_Other','Activities_CommercialPropertyCleaning_OtherYN',
      'Activities_DomesticOvenCleaning','Activities_DomesticPropertyCleaning','Activities_DuctworkCleaning',
      'Activities_EoT_Cleaning','Activities_FactoriesWarehouses','Activities_GutterCleaning',
      'Activities_HardFloorCleaning','Activities_HardFloorRestoration','Activities_House_Clearance',
      'Activities_LaundryLinenIroning','Activities_NewBuildCommercial','Activities_NewBuildProperties',
      'Activities_NewBuildWindowCleaning','Activities_OtherNotListed','Activities_OtherNotListed_Details',
      'Activities_PL_Only_15M_YN','Activities_PressureWashing_Over3600psi','Activities_PressureWashsing',
      'Activities_RenderCleaning','Activities_RoofCleaning','Activities_RoofCleaningAccess',
      'Activities_ShoppingCentres','Activities_ShoppingCentres_Specific','Activities_SolarFarms',
      'Activities_SolarPanelAccess','Activities_SolarPanelCleaning','Activities_WindowCleaning',
      'BDSC_Y_N','Claims_Last5Years_YN','Claims_NumberInLast5Years','Claims_TotalValueLast5Years',
      'DecV3YN','DecV3_CantAgree_1','DecV3_CantAgree_2','DecV3_YN2','DeclaredHeight','EL_OtherNotListed',
      'GroundLevel_External','GroundLevel_Internal','HowManyYearsTrading','Just_Partner','Ltd_Needs_EL',
      'Ltd_Other_Director','MaxHeight_WorkedAt','PL_Turnover_Exceeds75K_YN','Proceed_PL_ONLY',
      'SoleTrader_Or_LtdCompany','Turover_Over_140k','TypeOfBusiness_If_NOT_JustMe','UseOfDataYN',
      'WAH_15_to_25m_Methods','WAH_15_to_25m','WAH_1_to_5m_Methods','WAH_1_to_5m','WAH_25_to_65m_Methods',
      'WAH_25_to_65m','WAH_5_to_15m_Methods','WAH_5_to_15m','WS_WhereIsWorkUndertaken',
      'Who_Else_1','Who_Else_2','Who_Else_3','Who_Else_4','Who_Works_In_Your_Business','Years_Experience'];
    foreach ($sofTokens as $tk) {
        if (!array_key_exists($tk, $d))            $d[$tk] = '';
        if (!array_key_exists($tk . '_Value', $d)) $d[$tk . '_Value'] = $d[$tk];
    }

    // Build the business description from the activity answers (SchemeServe logic).
    $built = doc_business_description($ans, (float)$elLimit);
    if ($built !== '') $d['Client_Business_Description'] = $built;

    return $d;
}

/** Render a document for a case and save it against the term. Returns doc_id or 0. */
function doc_generate_and_save(PDO $db, int $case_id, int $termId, string $docKey, string $docLabel, ?int $uid): int {
    $file = __DIR__ . "/templates/$docKey.html";
    if (!is_file($file)) return 0;
    $html = doc_render(file_get_contents($file), doc_build_data($db, $case_id, $docKey));
    $dir = __DIR__ . '/documents';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $fname = "{$case_id}_{$docKey}_" . date('Ymd_His') . ".html";
    $snap = "<!doctype html><html><head><meta charset='utf-8'><title>" . htmlspecialchars($docLabel) . " " . $case_id . "</title></head><body>" . $html . "</body></html>";
    if (@file_put_contents($dir . '/' . $fname, $snap) === false) return 0;
    $db->prepare("INSERT INTO document (term_id,doc_type,name,generated_at,generated_by,file_path) VALUES (?,?,?,?,?,?)")
       ->execute([$termId, $docLabel, $docLabel . ' ' . date('d/m/Y H:i'), date('Y-m-d H:i:s'), $uid, $fname]);
    return (int)$db->lastInsertId();
}

/** Build Client_Business_Description from checked activity answers + a height statement. */
function doc_business_description(array $ans, float $elLimit): string {
    $checked = fn($k) => (($ans[$k] ?? '') === 'checked');
    $phrases = [
        'Activities_DomesticPropertyCleaning'   => 'Internal cleaning of domestic properties including holiday homes. ',
        'Activities_CommercialPropertyCleaning' => 'Internal Cleaning of Commercial Premises - shops, offices, hotels, pubs, restaurants and nursing homes. ',
        'Activities_CarpetUpholsteryCleaning'   => 'Carpet and Upholstery Cleaning (including soft furnishings). ',
        'Activities_WindowCleaning'             => 'Window Cleaning including soffits, fascias, frames, conservatory roofs and signage. ',
        'Activities_DomesticOvenCleaning'       => 'Domestic Oven Cleaning. ',
        'Activities_GutterCleaning'             => 'Gutter Cleaning. ',
        'Activities_SolarPanelCleaning'         => 'Solar Panel Cleaning. ',
        'Activities_PressureWashsing'           => 'Pressure Washing. ',
        'Activities_RoofCleaning'               => 'Roof Cleaning. ',
    ];
    $desc = '';
    foreach ($phrases as $k => $p) if ($checked($k)) $desc .= $p;
    if ($checked('Activities_OtherNotListed') && !empty($ans['Activities_OtherNotListed_Details']))
        $desc .= $ans['Activities_OtherNotListed_Details'] . '. ';
    if ($desc === '') return '';
    // height statement
    $declared = (string)($ans['DeclaredHeight'] ?? '');
    if ($elLimit == 0) {
        $desc .= (($ans['Activities_PL_Only_15M_YN'] ?? '') === 'Yes')
            ? 'Working up to ' . $declared . ' metres.' : 'Working up to 15 metres.';
    } elseif (($ans['Above1mYesNo'] ?? '') === 'Yes') {
        $mh = $ans['MaxHeight_WorkedAt'] ?? '';
        $desc .= in_array($mh, ['Up to 5 metres','Up to 15 metres','Up to 25 metres'], true)
            ? 'Working ' . lcfirst($mh) . '.' : 'Working up to ' . $declared . ' metres.';
    } else {
        $desc .= 'Working up to 1 metre.';
    }
    return trim($desc);
}
