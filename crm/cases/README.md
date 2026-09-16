# Cases (policy admin)

The Cases tab: clients, policies, mid-term adjustments, documents, imports and reports, mirroring
SchemeServe's own case screen.

## Where this came from
Copied from the reference app in `SchemeServe V2/webapp` (which is never modified). Changes made
when bringing it into the CRM:

- **`lib.php` is a bridge**, not the original: the screens now use the CRM's database connection,
  session, login and page shell. There is no separate Cases login or password.
- The original `login.php`, `logout.php`, `set_password.php` and `config.sample.php` were dropped.
- Pages call `cases_header()` / `cases_footer()` (CRM shell + the Cases sub-menu) instead of the
  reference app's own layout, and read settings through the CRM's `cfg()`.
- **Tables** live in the CRM database. `inc/cases_schema.sql` is generated from the reference SQL by
  `scratchpad/build-cases-sql.cjs`, with the reference app's `app_user` table left out (the CRM's own
  users table holds the logins) and the sample client/case records removed. Reference data — rates,
  endorsements, cover parts, questions — is kept.
- **Set-up runs on first use** (`cases_install()`), not as part of the CRM's start-up, so a problem
  here can never take the rest of the CRM down. It reports any error at the top of the Cases pages.
- `documents/` holds generated client documents; direct web access is blocked by its `.htaccess`
  (they are served through `document.php`, which requires a login).

## Rates
The rating uses the Zurich scheme rates from the underwriting manual and the live SchemeServe
config. These are for internal policy admin only and are never shown on the website.
