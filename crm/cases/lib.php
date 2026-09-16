<?php
/**
 * Cases — bridge to the CRM.
 *
 * The Cases screens came from the SchemeServe V2 reference app (see README.md), which had its own
 * database connection, login and page layout. Inside the CRM none of that is needed: this file
 * hands them the CRM's database, session, login and shell, and adds the few helpers that were
 * unique to the Cases app.
 *
 * Every Cases page starts with: require __DIR__.'/lib.php';
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib.php';

/* ---------- helpers that only the Cases screens use ---------- */
function money($v): string { return $v === null || $v === '' ? '—' : '£' . number_format((float)$v, 2); }
function pct($v): string { return $v === null || $v === '' ? '—' : rtrim(rtrim(number_format((float)$v, 5, '.', ''), '0'), '.') . '%'; }

/* ---------- page shell: the CRM's header plus the Cases sub-menu ---------- */
function cases_header(string $title = ''): void {
    layout_header($title !== '' ? $title : 'Cases');
    echo "<link rel='stylesheet' href='" . asset('cases/assets/cases.css') . "'>";
    $page = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $tabs = [
        'index.php' => 'Cases',
        'clients.php' => 'Clients',
        'quote.php' => 'Quote',
        'report.php' => 'Reports',
        'rates.php' => 'Rates',
        'import.php' => 'Import',
    ];
    echo "<nav class='cases-nav'>";
    foreach ($tabs as $href => $label) {
        $on = $page === $href
            || ($href === 'index.php' && in_array($page, ['case.php', 'case_edit.php', 'adjust.php', 'document.php'], true))
            || ($href === 'clients.php' && in_array($page, ['client.php', 'client_edit.php'], true));
        echo "<a class='" . ($on ? 'on' : '') . "' href='" . e($href) . "'>" . e($label) . '</a>';
    }
    echo '</nav>';
    foreach (($GLOBALS['cases_install_problems'] ?? []) as $p) {
        echo "<div class='flash' style='background:#fef3f2;border-color:#fecdca;color:#b42318'>Cases set-up problem: " . e($p) . '</div>';
    }
}

function cases_footer(): void { layout_footer(); }

/* ---------- one-time set-up of the Cases tables ----------
 * Deliberately NOT part of the CRM's own start-up: it runs the first time someone opens the Cases
 * tab, so if anything goes wrong it affects this section only and the rest of the CRM keeps working.
 */
const CASES_SCHEMA_VERSION = 1;

/** The statements in inc/cases_schema.sql (generated from the SchemeServe V2 SQL — see README.md). */
function cases_schema_statements(): array {
    $file = __DIR__ . '/../inc/cases_schema.sql';
    if (!is_file($file)) return [];
    $sql = preg_replace('#^\s*--.*$#m', '', (string)file_get_contents($file));
    return array_values(array_filter(array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', (string)$sql)), fn($s) => $s !== ''));
}

/** Installs the tables if they are not there yet. Returns any problems (empty = all good). */
function cases_install(): array {
    static $problems = null;
    if ($problems !== null) return $problems;
    $problems = [];
    try {
        $done = (int)(db()->query("SELECT svalue FROM setting WHERE skey='cases_schema_version'")->fetchColumn() ?: 0);
    } catch (\Throwable $e) {
        return $problems = ['The CRM database is not available.'];
    }
    if ($done >= CASES_SCHEMA_VERSION) return $problems;

    $statements = cases_schema_statements();
    if (!$statements) return $problems = ['inc/cases_schema.sql is missing, so the Cases tables cannot be created.'];
    foreach ($statements as $sql) {
        try {
            db()->exec($sql);
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            // Re-running something that already exists is fine; anything else is worth reporting.
            if (stripos($msg, 'exists') === false && stripos($msg, 'Duplicate') === false) {
                $problems[] = mb_substr($msg, 0, 300);
                error_log('Polished CRM (Cases install): ' . $msg);
            }
        }
    }
    if (!$problems) {
        db()->prepare("DELETE FROM setting WHERE skey='cases_schema_version'")->execute();
        db()->prepare("INSERT INTO setting (skey, svalue) VALUES ('cases_schema_version', ?)")->execute([(string)CASES_SCHEMA_VERSION]);
    }
    return $problems;
}

/** Are the Cases tables there? */
function cases_ready(): bool {
    static $ready = null;
    if ($ready === null) {
        try { db()->query('SELECT 1 FROM case_policy LIMIT 1'); $ready = true; }
        catch (\Throwable $e) { $ready = false; }
    }
    return $ready;
}

// Set up on first use (a single quick check once it is done). If the tables still are not there,
// show a plain explanation rather than letting every Cases page fail on its first query.
$GLOBALS['cases_install_problems'] = cases_install();
if (current_user() && !cases_ready()) {
    cases_header('Cases');
    echo '<div class="card"><h2>Cases is not set up yet</h2>'
        . '<p>The policy tables could not be created in the CRM database, so cases, clients and documents are not available.</p>'
        . '<p class="sub">Any error above says why. The most common causes are the database user not being allowed to create tables, '
        . 'or the CRM running on SQLite (the Cases tables need MySQL). Nothing else in the CRM is affected.</p></div>';
    cases_footer();
    exit;
}
