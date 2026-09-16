<?php
/**
 * Database schema. (The main table is "leads": LEAD is a reserved word in MySQL 8 / MariaDB.) Runs automatically (cheaply — guarded by a version number in the
 * setting table) the first time db() connects after a deploy, so there are no SQL
 * files to remember to run in phpMyAdmin. Works on MySQL and on SQLite for local tests.
 *
 * To change the schema: add a new entry to schema_migrations() — never edit an old one.
 */
declare(strict_types=1);

function schema_migrations(bool $sqlite): array {
    $pk   = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
    $fk   = $sqlite ? 'INTEGER' : 'INT UNSIGNED';
    $big  = $sqlite ? 'TEXT' : 'MEDIUMTEXT';
    $now  = $sqlite ? "TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP" : 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP';
    $dt   = $sqlite ? 'TEXT NULL' : 'DATETIME NULL';
    $tail = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    return [
        1 => [
            "CREATE TABLE IF NOT EXISTS app_user (
                user_id $pk,
                username VARCHAR(80) NOT NULL UNIQUE,
                display_name VARCHAR(120) NOT NULL DEFAULT '',
                email VARCHAR(190) NOT NULL DEFAULT '',
                password_hash VARCHAR(255) NOT NULL,
                role VARCHAR(20) NOT NULL DEFAULT 'staff',
                created_at $now
            )$tail",
            "CREATE TABLE IF NOT EXISTS leads (
                lead_id $pk,
                status VARCHAR(40) NOT NULL DEFAULT 'New Enquiry',
                first_name VARCHAR(100) NOT NULL DEFAULT '',
                last_name VARCHAR(100) NOT NULL DEFAULT '',
                company_name VARCHAR(190) NOT NULL DEFAULT '',
                email VARCHAR(190) NOT NULL DEFAULT '',
                phone VARCHAR(40) NOT NULL DEFAULT '',
                source VARCHAR(60) NOT NULL DEFAULT 'Website form',
                landing_page VARCHAR(255) NOT NULL DEFAULT '',
                cover_interest VARCHAR(120) NOT NULL DEFAULT '',
                utm_source VARCHAR(120) NOT NULL DEFAULT '',
                utm_medium VARCHAR(120) NOT NULL DEFAULT '',
                utm_campaign VARCHAR(190) NOT NULL DEFAULT '',
                consent_text VARCHAR(500) NOT NULL DEFAULT '',
                consent_at $dt,
                ip_hash VARCHAR(64) NOT NULL DEFAULT '',
                renewal_date DATE NULL,
                assigned_to $fk NULL,
                next_follow_up DATE NULL,
                link_token VARCHAR(64) NULL UNIQUE,
                q_status VARCHAR(20) NOT NULL DEFAULT 'not_started',
                q_data $big NULL,
                q_started_at $dt,
                q_saved_at $dt,
                q_submitted_at $dt,
                chasing TINYINT NOT NULL DEFAULT 0,
                auto_chase_count INT NOT NULL DEFAULT 0,
                next_chase_date DATE NULL,
                next_chase_window TINYINT NULL,
                created_at $now,
                updated_at $dt
            )$tail",
            "CREATE TABLE IF NOT EXISTS lead_note (
                note_id $pk,
                lead_id $fk NOT NULL,
                body TEXT NOT NULL,
                created_by $fk NULL,
                created_at $now
            )$tail",
            "CREATE TABLE IF NOT EXISTS lead_task (
                task_id $pk,
                lead_id $fk NULL,
                body VARCHAR(500) NOT NULL,
                due_date DATE NOT NULL,
                assigned_to $fk NULL,
                done_at $dt,
                created_by $fk NULL,
                created_at $now
            )$tail",
            "CREATE TABLE IF NOT EXISTS setting (
                skey VARCHAR(80) NOT NULL PRIMARY KEY,
                svalue $big NULL
            )$tail",
            "CREATE INDEX idx_lead_status ON leads (status)",
            "CREATE INDEX idx_lead_email ON leads (email)",
            "CREATE INDEX idx_lead_chase ON leads (chasing, next_chase_date)",
            "CREATE INDEX idx_note_lead ON lead_note (lead_id)",
            "CREATE INDEX idx_task_due ON lead_task (due_date)",
        ],
        2 => [
            // The website form asks whether they would rather have a call than the questionnaire link.
            'ALTER TABLE leads ADD COLUMN prefers_call ' . ($sqlite ? 'INTEGER NOT NULL DEFAULT 0' : 'TINYINT(1) NOT NULL DEFAULT 0'),
        ],
        // The Cases tables are NOT installed here: they are set up the first time someone opens the
        // Cases tab (see crm/cases/lib.php), so a problem there can never stop the rest of the CRM.
    ];
}

function ensure_schema(PDO $pdo, bool $sqlite): void {
    // setting may not exist yet on a brand-new database.
    $current = 0;
    try {
        $v = $pdo->query("SELECT svalue FROM setting WHERE skey='schema_version'")->fetchColumn();
        $current = (int)$v;
    } catch (\Throwable $e) {
        $current = 0;
    }
    $migrations = schema_migrations($sqlite);
    $latest = max(array_keys($migrations));
    if ($current >= $latest) return;

    foreach ($migrations as $version => $statements) {
        if ($version <= $current) continue;
        foreach ($statements as $sql) {
            try {
                $pdo->exec($sql);
            } catch (\PDOException $e) {
                // Re-running an index/column that already exists is harmless — anything else isn't.
                $msg = $e->getMessage();
                if (stripos($msg, 'exists') === false && stripos($msg, 'Duplicate') === false) throw $e;
            }
        }
        $pdo->prepare("DELETE FROM setting WHERE skey='schema_version'")->execute();
        $pdo->prepare("INSERT INTO setting (skey, svalue) VALUES ('schema_version', ?)")->execute([(string)$version]);
    }
}
