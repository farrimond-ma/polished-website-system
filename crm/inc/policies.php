<?php
/**
 * The policy behind a case.
 *
 * A case in the CRM (a row in leads with is_case = 1) is the client and their questionnaire. The
 * policy itself — cover, premiums, adjustments and documents — lives in the SchemeServe tables
 * under crm/cases/. This joins the two: leads.policy_case_id points at case_policy.case_id.
 *
 * Every function here copes with those tables not existing yet (they are created the first time
 * someone opens the policy screens), so a case still works before any policy data is imported.
 */
declare(strict_types=1);

/** Are the policy tables there? */
function policy_tables_ready(): bool {
    static $ready = null;
    if ($ready === null) {
        try { db()->query('SELECT 1 FROM case_policy LIMIT 1'); $ready = true; }
        catch (\Throwable $e) { $ready = false; }
    }
    return $ready;
}

/** How a policy client is named on screen. */
function policy_client_name(array $r): string {
    if (!empty($r['joint_names'])) return (string)$r['joint_names'];
    $n = trim((string)($r['title'] ?? '') . ' ' . (string)($r['first_name'] ?? '') . ' ' . (string)($r['last_name'] ?? ''));
    return $n !== '' ? $n : (string)($r['trading_name'] ?? $r['client_ref'] ?? '');
}

/** The policy a case is linked to, with its latest term — or null. */
function policy_for_case(?int $caseId): ?array {
    if (!$caseId || !policy_tables_ready()) return null;
    $sql = "SELECT c.case_id, c.status, c.insurer_policy_number, c.client_ref,
                   cl.title, cl.first_name, cl.last_name, cl.joint_names, cl.trading_name, cl.postcode,
                   s.scheme_name, s.policy_no_prefix, s.policy_no_suffix,
                   t.transaction_type, t.sequence_label, t.inception_date, t.expiry_date, t.total_premium
            FROM case_policy c
            JOIN client cl ON cl.client_ref = c.client_ref
            LEFT JOIN scheme s ON s.scheme_id = c.scheme_id
            LEFT JOIN policy_term t ON t.term_id = (SELECT MAX(t2.term_id) FROM policy_term t2 WHERE t2.case_id = c.case_id)
            WHERE c.case_id = ?";
    try {
        $st = db()->prepare($sql);
        $st->execute([$caseId]);
        return $st->fetch() ?: null;
    } catch (\Throwable $e) {
        return null;
    }
}

/** Policies matching a search, for linking one to a case. */
function policy_search(string $q, int $limit = 12): array {
    if (!policy_tables_ready()) return [];
    $q = trim($q);
    if ($q === '') return [];
    $like = '%' . $q . '%';
    $sql = "SELECT c.case_id, c.status, c.insurer_policy_number,
                   cl.client_ref, cl.title, cl.first_name, cl.last_name, cl.joint_names, cl.trading_name, cl.postcode,
                   t.expiry_date
            FROM case_policy c
            JOIN client cl ON cl.client_ref = c.client_ref
            LEFT JOIN policy_term t ON t.term_id = (SELECT MAX(t2.term_id) FROM policy_term t2 WHERE t2.case_id = c.case_id)
            WHERE cl.trading_name LIKE ? OR cl.joint_names LIKE ? OR cl.last_name LIKE ?
                  OR cl.client_ref LIKE ? OR cl.postcode LIKE ? OR c.insurer_policy_number LIKE ?
            ORDER BY c.case_id DESC";
    try {
        $st = db()->prepare($sql);
        $st->execute([$like, $like, $like, $like, $like, $like]);
        return array_slice($st->fetchAll(), 0, $limit);
    } catch (\Throwable $e) {
        return [];
    }
}

/** Is another case already linked to this policy? Returns that lead_id, or 0. */
function policy_linked_elsewhere(int $policyCaseId, int $exceptLeadId = 0): int {
    try {
        $st = db()->prepare('SELECT lead_id FROM leads WHERE policy_case_id = ? AND lead_id <> ? LIMIT 1');
        $st->execute([$policyCaseId, $exceptLeadId]);
        return (int)$st->fetchColumn();
    } catch (\Throwable $e) {
        return 0;
    }
}

/** The policy number as staff know it (prefix + number + suffix). */
function policy_number(array $p): string {
    if (!empty($p['insurer_policy_number'])) return (string)$p['insurer_policy_number'];
    $n = trim((string)($p['policy_no_prefix'] ?? '') . (string)($p['case_id'] ?? '') . (string)($p['policy_no_suffix'] ?? ''));
    return $n !== '' ? $n : (string)($p['case_id'] ?? '');
}
