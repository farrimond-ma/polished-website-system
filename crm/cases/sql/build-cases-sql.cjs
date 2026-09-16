// Builds crm/inc/cases_schema.sql from the SchemeServe V2 SQL files (which are never modified).
// Changes made on the way in:
//   - the app_user table is left alone: the CRM already has one and it holds the real logins
//   - DROP TABLE removed, CREATE TABLE -> CREATE TABLE IF NOT EXISTS (safe to re-run)
//   - user columns become INT UNSIGNED to match the CRM's app_user.user_id
//   - sample client/case records are left out; reference data (rates, endorsements...) is kept
const fs = require('fs');
const SRC = 'C:/Users/MarkFarrimond/Polished Website System/crm/cases/sql/';
const OUT = 'C:/Users/MarkFarrimond/Polished Website System/crm/inc/cases_schema.sql';

let main = fs.readFileSync(SRC + 'schemeserve_backend_mysql.sql', 'utf8');

// 1. Cut the app_user block (drop + create + seed insert)
main = main.replace(/DROP TABLE IF EXISTS `app_user`;\s*/g, '');
main = main.replace(/CREATE TABLE `app_user`[\s\S]*?;\s*/, '-- app_user: the CRM already provides this table (logins live there)\n');
main = main.replace(/INSERT INTO `app_user`[\s\S]*?;\s*/, '');

// 2. Stop at the sample records — everything from the first demo client insert onwards, except the views
const demoStart = main.indexOf('INSERT INTO `client`');
const viewsStart = main.search(/CREATE OR REPLACE VIEW/);
if (demoStart === -1 || viewsStart === -1) throw new Error('could not find the sample data / views boundary');
main = main.slice(0, demoStart) + '\n-- (sample client/case records from the reference copy are not loaded)\n\n' + main.slice(viewsStart);

let sql = main;
for (const f of ['01_rates_update.sql', '03_documents.sql', '04_adjustments.sql', '05_reports.sql', '06_import.sql', '07_mta_fee.sql', '08_bordereau.sql', '09_policy_prefix.sql']) {
  sql += `\n\n-- ===== ${f} =====\n` + fs.readFileSync(SRC + f, 'utf8');
}

// 3. Safe to run more than once
sql = sql.replace(/^\s*DROP TABLE IF EXISTS [^;]+;\s*$/gm, '');
sql = sql.replace(/CREATE TABLE `/g, 'CREATE TABLE IF NOT EXISTS `');
sql = sql.replace(/CREATE TABLE IF NOT EXISTS IF NOT EXISTS/g, 'CREATE TABLE IF NOT EXISTS');
sql = sql.replace(/INSERT INTO `(insurer|scheme|agent|import_batch|cover_part_ref|question_ref|endorsement|rate_table|contents_option|experience_loading|min_net_premium|scheme_config)`/g, 'INSERT IGNORE INTO `$1`');

// 4. Match the CRM's user id type so the links to users work
sql = sql.replace(/`generated_by`\s+INT NULL/g, '`generated_by` INT UNSIGNED NULL');
sql = sql.replace(/`created_by`\s+INT NULL/g, '`created_by` INT UNSIGNED NULL');
sql = sql.replace(/`user_id`\s+INT NULL/g, '`user_id` INT UNSIGNED NULL');

fs.writeFileSync(OUT, `-- GENERATED FILE — do not edit by hand.\n-- Built from SchemeServe V2/webapp SQL by scratchpad/build-cases-sql.cjs; see crm/cases/README.md.\n\n${sql}\n`);

const statements = sql.split(/;\s*(?:\r?\n|$)/).filter((s) => s.trim() && !/^\s*(--|\/\*)/.test(s.trim()));
console.log('written:', OUT);
console.log('statements:', statements.length);
console.log('tables created:', (sql.match(/CREATE TABLE IF NOT EXISTS `([a-z_]+)`/g) || []).map((t) => t.replace(/.*`([a-z_]+)`/, '$1')).join(', '));
console.log('app_user touched:', /(?:DROP|CREATE) TABLE[^;]*`app_user`/.test(sql) ? 'YES — PROBLEM' : 'no');
console.log('sample records loaded:', /INSERT INTO `(client|case_policy|policy_term)`/.test(sql) ? 'YES — PROBLEM' : 'no');
