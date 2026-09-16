<?php
require __DIR__ . '/lib.php';
require_login();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);   // GET normally; POST on delete
$tab = (string)param('tab', 'summary');

// ---- delete a case (and all its child records) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'delete_case' && $id) {
    csrf_check();
    $db = db();
    $cr = $db->prepare("SELECT client_ref FROM case_policy WHERE case_id=?"); $cr->execute([$id]);
    $clientRef = $cr->fetchColumn();
    $terms = $db->prepare("SELECT term_id FROM policy_term WHERE case_id=?"); $terms->execute([$id]);
    foreach ($terms->fetchAll(PDO::FETCH_COLUMN) as $tid)
        foreach (['risk_answer','cover_selection','rating_line','rating_adjustment','applied_endorsement','document'] as $tbl)
            $db->prepare("DELETE FROM $tbl WHERE term_id=?")->execute([$tid]);
    foreach (['policy_term','money_transaction','note','activity_log','claim','debiting_schedule'] as $tbl)
        $db->prepare("DELETE FROM $tbl WHERE case_id=?")->execute([$id]);
    $db->prepare("DELETE FROM case_policy WHERE case_id=?")->execute([$id]);
    // remove the client too, if it has no other cases
    if ($clientRef) {
        $oth = $db->prepare("SELECT COUNT(*) FROM case_policy WHERE client_ref=?"); $oth->execute([$clientRef]);
        if ((int)$oth->fetchColumn() === 0) $db->prepare("DELETE FROM client WHERE client_ref=?")->execute([$clientRef]);
    }
    flash("Case $id deleted.");
    header('Location: index.php'); exit;
}

// ---- delete (undo) the most recent adjustment / cancellation ----
// Only the latest term may be removed, and only if it's an Adjustment/Cancellation (never the
// original New Business/Renewal), so the delta-based premium chain and bordereau stay consistent.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'delete_term' && $id) {
    csrf_check();
    $db = db();
    $termId = (int)post('term_id');
    $lt = $db->prepare("SELECT term_id, transaction_type FROM policy_term WHERE case_id=? ORDER BY term_id DESC LIMIT 1");
    $lt->execute([$id]); $lt = $lt->fetch();
    $tc = $db->prepare("SELECT COUNT(*) FROM policy_term WHERE case_id=?"); $tc->execute([$id]);
    $termCount = (int)$tc->fetchColumn();
    $ttl = strtolower((string)($lt['transaction_type'] ?? ''));
    $isMTA = (strpos($ttl,'adjust') !== false || strpos($ttl,'cancel') !== false);
    if ($lt && (int)$lt['term_id'] === $termId && $termCount > 1 && $isMTA) {
        // remove the saved document files for this term
        $dq = $db->prepare("SELECT file_path FROM document WHERE term_id=? AND file_path IS NOT NULL"); $dq->execute([$termId]);
        foreach ($dq->fetchAll(PDO::FETCH_COLUMN) as $fp) { $full = __DIR__.'/documents/'.basename((string)$fp); if (is_file($full)) @unlink($full); }
        foreach (['risk_answer','cover_selection','rating_line','rating_adjustment','applied_endorsement','document'] as $tbl)
            $db->prepare("DELETE FROM $tbl WHERE term_id=?")->execute([$termId]);
        $db->prepare("DELETE FROM policy_term WHERE term_id=?")->execute([$termId]);
        // revert the case status to the now-latest term (a deleted cancellation -> back On Cover, etc.)
        $ns = $db->prepare("SELECT status FROM policy_term WHERE case_id=? ORDER BY term_id DESC LIMIT 1"); $ns->execute([$id]);
        if ($newStatus = $ns->fetchColumn()) $db->prepare("UPDATE case_policy SET status=? WHERE case_id=?")->execute([$newStatus, $id]);
        $uid = current_user()['user_id'] ?? null;
        $what = strpos($ttl,'cancel') !== false ? 'Cancellation' : 'Adjustment';
        $db->prepare("INSERT INTO activity_log (case_id,type,details,user_id,created_at) VALUES (?,?,?,?,?)")
           ->execute([$id, 'Policy', "$what (term #$termId) deleted — policy reverted to the previous term.", $uid, date('Y-m-d H:i:s')]);
        flash("$what deleted. The policy has been reverted to the previous term.");
    } else {
        flash('That transaction can’t be deleted — only the most recent adjustment or cancellation can be removed.');
    }
    header("Location: case.php?id=$id"); exit;
}

$st = db()->prepare("SELECT c.*, cl.* , s.product_name, s.policy_no_prefix, s.policy_no_suffix, a.name AS agent_name
                     FROM case_policy c
                     JOIN client cl ON cl.client_ref = c.client_ref
                     JOIN scheme s  ON s.scheme_id = c.scheme_id
                     LEFT JOIN agent a ON a.agent_id = c.agent_id
                     WHERE c.case_id = ?");
$st->execute([$id]);
$case = $st->fetch();
if (!$case) { cases_header('Case'); echo "<p class='empty'>Case not found.</p>"; cases_footer(); exit; }

// formatted policy number: <prefix>/<insurer policy number, else case id>/<suffix>
$polMid   = ($case['insurer_policy_number'] ?? '') !== '' ? $case['insurer_policy_number'] : $case['case_id'];
$policyNo = ($case['policy_no_prefix'] ?? 'ZCLP') . '/' . $polMid . '/' . ($case['policy_no_suffix'] ?? '1');

$terms = db()->prepare("SELECT * FROM policy_term WHERE case_id = ? ORDER BY term_id DESC");
$terms->execute([$id]); $terms = $terms->fetchAll();
$current = $terms[0] ?? null;
// the latest term can be deleted (undone) only if it's an adjustment/cancellation and isn't the sole term
$curTt = strtolower((string)($current['transaction_type'] ?? ''));
$canDeleteAdj = $current && count($terms) > 1 && (strpos($curTt,'adjust') !== false || strpos($curTt,'cancel') !== false);
$delLabel = strpos($curTt,'cancel') !== false ? 'cancellation' : 'adjustment';
$termIds = array_column($terms, 'term_id');
$inClause = $termIds ? implode(',', array_map('intval', $termIds)) : '0';

function cname(array $c): string {
    if (!empty($c['joint_names'])) return $c['joint_names'];
    $n = trim(($c['title'] ?? '').' '.($c['first_name'] ?? '').' '.($c['last_name'] ?? ''));
    return $n !== '' ? $n : ($c['trading_name'] ?? $c['client_ref']);
}

$tabs = ['summary'=>'Summary','questions'=>'Questions','rating'=>'Rating (Matrix)','money'=>'Money',
         'documents'=>'Documents','endorsements'=>'Endorsements','notes'=>'Notes','activity'=>'Activity'];

cases_header('Case ' . $id);
?>
<div class="case-head">
  <div>
    <h1>Case <span class="mono"><?= (int)$case['case_id'] ?></span></h1>
    <div class="sub">Policy no <span class="mono"><?= e($policyNo) ?></span></div>
    <div class="sub"><?= e($case['product_name']) ?> · created <?= dt($case['created_at']) ?> · agent <?= e($case['agent_name'] ?: '—') ?></div>
  </div>
  <div class="case-side">
    <span class="pill <?= e(strtolower(str_replace(' ','',$case['status']))) ?>"><?= e($case['status']) ?></span>
    <?php if (($case['source'] ?? '') === 'Bordereau'): ?><span class="pill" style="background:#fff0d0;color:#96650a" title="Imported from bordereau — provisional until the full SchemeServe data is loaded">Provisional</span><?php endif; ?>
    <a class="btn" href="adjust.php?case=<?= (int)$case['case_id'] ?>&mode=adjust">Adjust</a>
    <a class="btn ghost" href="adjust.php?case=<?= (int)$case['case_id'] ?>&mode=cancel">Cancel policy</a>
    <a class="btn" href="document.php?case=<?= (int)$case['case_id'] ?>&doc=schedule">Documents</a>
    <a class="btn ghost" href="case_edit.php?id=<?= (int)$case['case_id'] ?>">Edit</a>
    <?php if ($canDeleteAdj): ?>
    <form method="post" style="display:inline" onsubmit="return confirm('Delete the most recent <?= $delLabel ?> and revert the policy to the previous term? Its documents will be removed too. This cannot be undone.')">
      <?= csrf_field() ?><input type="hidden" name="action" value="delete_term"><input type="hidden" name="term_id" value="<?= (int)$current['term_id'] ?>">
      <button class="btn ghost" style="color:#b42323;border-color:#f0b6b6" type="submit">Delete <?= $delLabel ?></button>
    </form>
    <?php endif; ?>
    <form method="post" style="display:inline" onsubmit="return confirm('Delete this case and ALL its data? This cannot be undone.')">
      <?= csrf_field() ?><input type="hidden" name="action" value="delete_case"><input type="hidden" name="id" value="<?= (int)$case['case_id'] ?>">
      <button class="btn ghost" style="color:#b42323;border-color:#f0b6b6" type="submit">Delete</button>
    </form>
  </div>
</div>

<div class="two-col">
  <div class="client-card">
    <div class="ck"><?= e($case['client_ref']) ?></div>
    <strong><?= e(cname($case)) ?></strong>
    <?= $case['trading_name'] ? "<div class='sub'>t/as ".e($case['trading_name'])."</div>" : '' ?>
    <div class="addr">
      <?= e($case['addr1']) ?><?= $case['addr2'] ? '<br>'.e($case['addr2']) : '' ?>
      <?= $case['town'] ? '<br>'.e($case['town']) : '' ?><?= $case['county'] ? '<br>'.e($case['county']) : '' ?>
      <?= $case['postcode'] ? '<br>'.e($case['postcode']) : '' ?>
    </div>
    <div class="sub">
      <?= $case['phone_landline'] ? '☎ '.e($case['phone_landline']).'<br>' : '' ?>
      <?= $case['phone_mobile'] ? '📱 '.e($case['phone_mobile']).'<br>' : '' ?>
      <?= $case['email'] ? '✉ '.e($case['email']) : '' ?>
    </div>
    <a class="lnk" href="client.php?ref=<?= e($case['client_ref']) ?>">View client →</a>
  </div>

  <div class="term-card">
    <?php if ($current): ?>
      <div class="term-row"><span>Policy number</span><b class="mono"><?= e($policyNo) ?></b></div>
      <div class="term-row"><span>Transaction</span><b><?= e($current['sequence_label'] ?: $current['transaction_type']) ?></b></div>
      <div class="term-row"><span>Inception</span><b><?= d($current['inception_date']) ?></b></div>
      <div class="term-row"><span>Expiry</span><b><?= d($current['expiry_date']) ?></b></div>
      <div class="term-row"><span>Total premium</span><b><?= money($current['total_premium']) ?></b></div>
      <div class="term-row"><span>Adjustment</span><b><?= money($current['adjustment']) ?></b></div>
      <div class="term-row"><span>Balance</span><b><?= money($current['balance']) ?></b></div>
    <?php else: ?><p class="empty">No terms yet.</p><?php endif; ?>
  </div>
</div>

<nav class="tabs">
  <?php foreach ($tabs as $k=>$label): ?>
    <a class="<?= $k===$tab?'on':'' ?>" href="case.php?id=<?= $id ?>&tab=<?= $k ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</nav>

<section class="tabbody">
<?php
$pdo = db();
if ($tab === 'summary') {
    echo "<h3>Policy terms / history</h3><table class='grid'><thead><tr><th>Transaction</th><th>Status</th><th>Inception</th><th>Expiry</th><th class='r'>Premium</th><th class='r'>Adjustment</th></tr></thead><tbody>";
    foreach ($terms as $t) {
        echo "<tr><td>".e($t['sequence_label'] ?: $t['transaction_type'])."</td><td>".e($t['status'])."</td><td>".d($t['inception_date'])."</td><td>".d($t['expiry_date'])."</td><td class='r'>".money($t['total_premium'])."</td><td class='r'>".money($t['adjustment'])."</td></tr>";
    }
    if (!$terms) echo "<tr><td colspan='6' class='empty'>No terms.</td></tr>";
    echo "</tbody></table>";

} elseif ($tab === 'questions') {
    $rows = $pdo->query("SELECT * FROM risk_answer WHERE term_id IN ($inClause) ORDER BY answer_id")->fetchAll();
    $byPart = [];
    foreach ($rows as $r) $byPart[$r['part_name']][] = $r;
    if (!$byPart) echo "<p class='empty'>No stored answers for this case.</p>";
    foreach ($byPart as $part => $qs) {
        echo "<h3>".e($part)."</h3><table class='grid kv'><tbody>";
        foreach ($qs as $r) echo "<tr><td class='k'>".e($r['question_text'] ?: $r['question_key'])."</td><td>".e($r['answer_value'])."</td></tr>";
        echo "</tbody></table>";
    }

} elseif ($tab === 'rating') {
    $lines = $pdo->query("SELECT rl.*, p.part_name FROM rating_line rl JOIN cover_part_ref p ON p.part_code=rl.part_code WHERE rl.term_id IN ($inClause) ORDER BY rl.part_code, rl.id")->fetchAll();
    $adj   = $pdo->query("SELECT ra.*, p.part_name FROM rating_adjustment ra JOIN cover_part_ref p ON p.part_code=ra.part_code WHERE ra.term_id IN ($inClause)")->fetchAll();
    $adjBy = []; foreach ($adj as $a) $adjBy[$a['part_code']] = $a;
    $linesBy = []; foreach ($lines as $l) $linesBy[$l['part_code']][] = $l;
    if (!$lines && !$adj) echo "<p class='empty'>No rating lines for this case.</p>";
    foreach ($linesBy as $pc => $ls) {
        echo "<h3>".e($ls[0]['part_name'])."</h3>";
        echo "<table class='grid'><thead><tr><th>Line</th><th class='r'>Exposure</th><th class='r'>Rate</th><th class='r'>Premium</th></tr></thead><tbody>";
        foreach ($ls as $l) echo "<tr><td>".e($l['line_label'])."</td><td class='r'>".money($l['exposure'])."</td><td class='r'>".pct($l['rate_pct'])."</td><td class='r'>".money($l['premium'])."</td></tr>";
        echo "</tbody></table>";
        if (isset($adjBy[$pc])) { $a = $adjBy[$pc];
            echo "<table class='grid mini'><tbody>";
            echo "<tr><td>Basic total</td><td class='r'>".money($a['basic_total'])."</td></tr>";
            echo "<tr><td>Minimum premium</td><td class='r'>".money($a['minimum_premium'])."</td></tr>";
            echo "<tr><td>NI / Experience / Discretionary</td><td class='r'>".pct($a['ni_load_pct'])." / ".pct($a['experience_load_pct'])." / ".pct($a['discretionary_pct'])."</td></tr>";
            echo "<tr class='tot'><td>Net total</td><td class='r'>".money($a['net_total'])."</td></tr>";
            echo "<tr><td>Commission (insurer / Allied)</td><td class='r'>".pct($a['commission_from_insurer_pct'])." / ".pct($a['commission_to_allied_pct'])."</td></tr>";
            echo "</tbody></table>";
        }
    }

} elseif ($tab === 'money') {
    $tx = $pdo->prepare("SELECT * FROM money_transaction WHERE case_id=? ORDER BY txn_date DESC");
    $tx->execute([$id]); $tx = $tx->fetchAll();
    echo "<h3>Transactions</h3><table class='grid'><thead><tr><th>Txn</th><th>Type</th><th>Date</th><th class='r'>Amount</th><th>Result</th></tr></thead><tbody>";
    foreach ($tx as $t) echo "<tr><td class='mono'>".e($t['txn_id'])."</td><td>".e($t['type'])."</td><td>".d($t['txn_date'])."</td><td class='r'>".money($t['amount'])."</td><td><span class='pill ".e(strtolower($t['result']))."'>".e($t['result'])."</span></td></tr>";
    if (!$tx) echo "<tr><td colspan='5' class='empty'>No transactions.</td></tr>";
    echo "</tbody></table>";
    $sc = $pdo->prepare("SELECT * FROM debiting_schedule WHERE case_id=?"); $sc->execute([$id]); $sc = $sc->fetchAll();
    echo "<h3>Debiting schedules</h3>";
    if (!$sc) echo "<p class='empty'>No debiting schedules for this case.</p>";
    else { echo "<table class='grid'><thead><tr><th>Description</th><th>Frequency</th><th>Next</th><th class='r'>Amount</th><th>Status</th></tr></thead><tbody>";
        foreach ($sc as $s) echo "<tr><td>".e($s['description'])."</td><td>".e($s['frequency'])."</td><td>".d($s['next_date'])."</td><td class='r'>".money($s['amount'])."</td><td>".e($s['status'])."</td></tr>";
        echo "</tbody></table>"; }

} elseif ($tab === 'documents') {
    $docs = $pdo->query("SELECT * FROM document WHERE term_id IN ($inClause) ORDER BY generated_at DESC")->fetchAll();
    echo "<table class='grid'><thead><tr><th>Type</th><th>Name</th><th>Generated</th></tr></thead><tbody>";
    foreach ($docs as $doc) echo "<tr><td>".e($doc['doc_type'])."</td><td>".e($doc['name'])."</td><td>".dt($doc['generated_at'])."</td></tr>";
    if (!$docs) echo "<tr><td colspan='3' class='empty'>No documents.</td></tr>";
    echo "</tbody></table>";

} elseif ($tab === 'endorsements') {
    $en = $pdo->query("SELECT ae.applies_to_text, en.endorsement_code, en.title FROM applied_endorsement ae JOIN endorsement en ON en.endorsement_code=ae.endorsement_code WHERE ae.term_id IN ($inClause)")->fetchAll();
    echo "<table class='grid'><thead><tr><th>Code</th><th>Title</th><th>Applies to</th></tr></thead><tbody>";
    foreach ($en as $x) echo "<tr><td class='mono'>".e($x['endorsement_code'])."</td><td>".e($x['title'])."</td><td>".e($x['applies_to_text'])."</td></tr>";
    if (!$en) echo "<tr><td colspan='3' class='empty'>No endorsements applied.</td></tr>";
    echo "</tbody></table>";

} elseif ($tab === 'notes') {
    $ns = $pdo->prepare("SELECT n.*, u.display_name FROM note n LEFT JOIN app_user u ON u.user_id=n.created_by WHERE n.case_id=? ORDER BY n.created_at DESC");
    $ns->execute([$id]); $ns = $ns->fetchAll();
    foreach ($ns as $n) echo "<div class='note'><div class='note-meta'>".dt($n['created_at'])." · ".e($n['display_name'] ?: '—')." · ref ".e($n['reference'] ?: '—')."</div>".e($n['body'])."</div>";
    if (!$ns) echo "<p class='empty'>No notes.</p>";

} elseif ($tab === 'activity') {
    $ac = $pdo->prepare("SELECT al.*, u.display_name FROM activity_log al LEFT JOIN app_user u ON u.user_id=al.user_id WHERE al.case_id=? ORDER BY al.created_at DESC");
    $ac->execute([$id]); $ac = $ac->fetchAll();
    echo "<table class='grid'><thead><tr><th>Type</th><th>Details</th><th>User</th><th>When</th></tr></thead><tbody>";
    foreach ($ac as $a) echo "<tr><td><span class='tag'>".e($a['type'])."</span></td><td>".e($a['details'])."</td><td>".e($a['display_name'] ?: '—')."</td><td>".dt($a['created_at'])."</td></tr>";
    if (!$ac) echo "<tr><td colspan='4' class='empty'>No activity.</td></tr>";
    echo "</tbody></table>";
}
?>
</section>
<?php cases_footer();
