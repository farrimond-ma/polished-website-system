<?php
require __DIR__ . '/lib.php';
require_login();
$pdo = db();

function num($s): float { $s = trim((string)$s); if ($s === '' || stripos($s,'Not Insured') !== false) return 0.0; return (float)str_replace([',', '£'], '', $s); }
function isodate($s): ?string {
    $s = trim((string)$s); if ($s === '') return null;
    foreach (['d-M-y','d/m/Y','Y-m-d','d-m-Y','d M Y'] as $fmt) { $d = DateTime::createFromFormat($fmt, $s); if ($d) return $d->format('Y-m-d'); }
    $t = strtotime($s); return $t ? date('Y-m-d', $t) : null;
}
function split_client(string $name): array {
    // "Alan Martin t/as Gleam Pro Clean" -> [trading, contact]
    if (preg_match('/^(.*?)\s+t\/as\s+(.*)$/i', $name, $m)) return [trim($m[2]), trim($m[1])];
    return ['', trim($name)];
}
$TT = ['New'=>'New Business','Renewal'=>'Renewal','Cancellation'=>'Cancellation',
       'Additional Premium'=>'Adjustment','Refund Premium'=>'Adjustment','No change'=>'Renewal'];

$msg = ''; $done = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['csv']['tmp_name'])) {
    csrf_check();
    $override = !empty($_POST['override']);
    $fh = fopen($_FILES['csv']['tmp_name'], 'r');
    if (!$fh) { $msg = 'Could not read the uploaded file.'; }
    else {
        $header = fgetcsv($fh);                       // skip header row
        $ins = 0; $upd = 0; $skip = 0; $bad = 0;
        $pdo->beginTransaction();
        try {
            while (($r = fgetcsv($fh)) !== false) {
                if (count($r) < 39) { if (array_filter($r)) $bad++; continue; }
                $case_id = (int)trim($r[0]);
                if ($case_id <= 0) { $bad++; continue; }
                $client_ref = 'C' . $case_id;
                [$trading, $contact] = split_client($r[7]);
                $addr = trim($r[8]); $pc = trim($r[9]);
                if ($pc && str_ends_with(strtoupper(str_replace(' ', '', $addr)), strtoupper(str_replace(' ', '', $pc))))
                    $addr = preg_replace('/,?\s*' . preg_quote($pc, '/') . '\s*$/i', '', $addr);
                $status_case = trim($r[38]) ?: 'On Cover';
                $txt = trim($r[5]);
                $ttype = $TT[$txt] ?? 'Renewal';
                $net = num($r[14]) + num($r[15]) + num($r[19]) + num($r[20]) + num($r[21]); // PL+EL+Tools+Hired+D&O
                $plLimit = num($r[28]); $elLimit = num($r[27]);
                if ($elLimit <= 0 && num($r[15]) > 0) $elLimit = 10000000;
                $biz = trim($r[6]);

                // ---- client upsert ----
                $ex = $pdo->prepare("SELECT 1 FROM client WHERE client_ref=?"); $ex->execute([$client_ref]);
                if ($ex->fetchColumn()) {
                    $pdo->prepare("UPDATE client SET trading_name=?,joint_names=?,addr1=?,postcode=? WHERE client_ref=?")
                        ->execute([$trading, $contact, $addr, $pc, $client_ref]);
                } else {
                    $pdo->prepare("INSERT INTO client (client_ref,trading_name,joint_names,addr1,postcode,domicile) VALUES (?,?,?,?,?, 'United Kingdom')")
                        ->execute([$client_ref, $trading, $contact, $addr, $pc]);
                }

                // ---- case upsert ----
                $ce = $pdo->prepare("SELECT 1 FROM case_policy WHERE case_id=?"); $ce->execute([$case_id]);
                $exists = (bool)$ce->fetchColumn();
                if ($exists && !$override) { $skip++; continue; }
                if ($exists) {
                    $pdo->prepare("UPDATE case_policy SET client_ref=?,status=?,insurer_policy_number=?,source='Bordereau' WHERE case_id=?")
                        ->execute([$client_ref, $status_case, trim($r[4]), $case_id]);
                    $upd++;
                } else {
                    $pdo->prepare("INSERT INTO case_policy (case_id,scheme_id,client_ref,agent_id,created_at,status,insurer_policy_number,source) VALUES (?,?,?,1,?,?,?, 'Bordereau')")
                        ->execute([$case_id, 1, $client_ref, date('Y-m-d H:i:s'), $status_case, trim($r[4])]);
                    $ins++;
                }

                // ---- term (single per imported case) ----
                // Store the totals as quote_inputs so the Adjust screen pre-fills turnover/wageroll/BFSC.
                $qi = json_encode(['who_works'=>'Me and others', 'pl_limit'=>(int)$plLimit,
                    'el_included'=>$elLimit > 0 ? '1' : '', 'turnover'=>num($r[10]),
                    'wr_employed'=>num($r[11]), 'wr_selfemp'=>num($r[12]), 'bfsc_payments'=>num($r[13]),
                    'years_experience'=>'Over 3 years']);
                $pb = json_encode(['pl'=>num($r[14]), 'el'=>num($r[15]), 'tools'=>num($r[19]),
                                   'hired'=>num($r[20]), 'do'=>num($r[21])]);
                $tq = $pdo->prepare("SELECT MAX(term_id) FROM policy_term WHERE case_id=?"); $tq->execute([$case_id]);
                $termId = (int)$tq->fetchColumn();
                if ($termId) {
                    $pdo->prepare("UPDATE policy_term SET transaction_type=?,sequence_label=?,status=?,inception_date=?,expiry_date=?,effective_date=?,total_premium=?,quote_inputs=?,premium_breakdown=? WHERE term_id=?")
                        ->execute([$ttype, $txt, $status_case, isodate($r[1]), isodate($r[2]), isodate($r[3]), round($net, 2), $qi, $pb, $termId]);
                } else {
                    $pdo->prepare("INSERT INTO policy_term (case_id,transaction_type,sequence_label,status,inception_date,expiry_date,effective_date,total_premium,adjustment,balance,quote_inputs,premium_breakdown) VALUES (?,?,?,?,?,?,?,?,0,0,?,?)")
                        ->execute([$case_id, $ttype, $txt, $status_case, isodate($r[1]), isodate($r[2]), isodate($r[3]), round($net, 2), $qi, $pb]);
                    $termId = (int)$pdo->lastInsertId();
                }
                // ---- cover ----
                $pdo->prepare("DELETE FROM cover_selection WHERE term_id=?")->execute([$termId]);
                if ($plLimit > 0) $pdo->prepare("INSERT INTO cover_selection (term_id,part_code,included,limit_of_indemnity) VALUES (?, 'B',1,?)")->execute([$termId, $plLimit]);
                if ($elLimit > 0) $pdo->prepare("INSERT INTO cover_selection (term_id,part_code,included,limit_of_indemnity) VALUES (?, 'A',1,?)")->execute([$termId, $elLimit]);
                // ---- business description as a risk answer (used by documents) ----
                $pdo->prepare("DELETE FROM risk_answer WHERE term_id=? AND question_key='Client_Business_Description'")->execute([$termId]);
                if ($biz !== '') $pdo->prepare("INSERT INTO risk_answer (term_id,part_name,question_key,question_text,answer_value) VALUES (?, 'Import','Client_Business_Description','Business Description',?)")->execute([$termId, $biz]);
            }
            $pdo->commit();
            $done = ['ins'=>$ins, 'upd'=>$upd, 'skip'=>$skip, 'bad'=>$bad];
        } catch (Throwable $e) { $pdo->rollBack(); $msg = 'Import failed: ' . $e->getMessage(); }
        fclose($fh);
    }
}
$importedCount = 0;
try { $importedCount = (int)$pdo->query("SELECT COUNT(*) FROM case_policy WHERE source='Bordereau'")->fetchColumn(); } catch (Throwable $e) {}

cases_header('Import');
?>
<div class="page-head"><h1>Import bordereau</h1></div>
<?php if ($msg) echo "<div class='err'>" . e($msg) . "</div>"; ?>
<?php if ($done): ?>
  <div class="flash">Done — <?= (int)$done['ins'] ?> inserted, <?= (int)$done['upd'] ?> updated, <?= (int)$done['skip'] ?> skipped, <?= (int)$done['bad'] ?> ignored.</div>
<?php endif; ?>
<p class="sub">Upload the Polished Cleaners Scheme bordereau CSV. Records are matched by <strong>Record Id</strong>.
   Imported records are flagged <em>Bordereau (provisional)</em> — you can edit or delete any of them, and when the
   full SchemeServe data dump arrives, re-import with “override” ticked to replace them. Currently
   <strong><?= $importedCount ?></strong> provisional record(s) in the system.</p>
<form method="post" enctype="multipart/form-data" class="form" style="max-width:560px">
  <?= csrf_field() ?>
  <label>Bordereau CSV file<input type="file" name="csv" accept=".csv" required></label>
  <label class="inline" style="margin-top:10px"><input type="checkbox" name="override" value="1" checked> Override existing records with the same Record Id</label>
  <div class="form-actions"><button type="submit">Import</button></div>
</form>
<?php cases_footer();
