# Going live

Work through these in order. Nothing here has been done yet: no repository, DNS, database or server changes have been made.

## 0. Before you start

- **The current `quote.polished-insurance.com` (SSR Questionnaire app) stays exactly as it is.** Do not touch that subdomain's DNS or files.
- The current root domain is served from **Vercel**. Moving `polished-insurance.com` to SiteGround replaces the old static site (the new `.htaccess` redirects `blog.html`, `contact.html`, `privacy.html` and `terms.html` to their new pages).
- The public email address is `hello@polished-insurance.co.uk` (set in `website/src/config/site.js` and `crm/config.php`). The CRM sends client emails from that mailbox, so its SMTP details are needed for `config.php`.
- Get compliance sign-off on the legal pages (`website/src/pages/Legal.jsx`) and the client message wording (`crm/inc/messages.php`).

## 1. GitHub repository

1. Create a **private** repository, e.g. `polished-website-system`.
2. Push this folder to its `main` branch. `.gitignore` already excludes `crm/config.php`, `crm/data/`, `node_modules/` and build output.
3. Add **Settings → Secrets and variables → Actions**:

| Name | Type | Value |
|---|---|---|
| `FTP_SERVER` | secret | SiteGround FTP host (Site Tools → Devs → FTP Accounts) |
| `FTP_USERNAME` / `FTP_PASSWORD` | secret | an FTP account with access to both site folders |
| `VITE_INTAKE_KEY` | secret | a long random string; must match `intake_key` in `crm/config.php` |
| `ANTHROPIC_API_KEY` | secret | Claude API key for the content engine |
| `PEXELS_API_KEY` | secret (optional) | free key from pexels.com/api for guide photos |
| `WEBSITE_SERVER_DIR` | variable (optional) | FTP path to the site's web root; default `polished-insurance.com/public_html/` |
| `CRM_SERVER_DIR` | variable (optional) | default `crm.polished-insurance.com/public_html/` |
| `VITE_CRM_URL` | variable (optional) | default `https://crm.polished-insurance.com` |

Optionally use separate `CRM_FTP_SERVER` / `CRM_FTP_USERNAME` / `CRM_FTP_PASSWORD` secrets for the CRM.

Generate random keys with:

```bash
openssl rand -hex 32
```

## 2. SiteGround: the CRM (do this first, so the website form has somewhere to send leads)

1. **Site Tools → Domain → Subdomains**: create `crm.polished-insurance.com`.
2. **Security → SSL Manager**: install Let's Encrypt for `crm.polished-insurance.com`.
3. **MySQL → Databases**: create a database and a user with all privileges. Keep this separate from the SSR questionnaire's database.
4. Run the **Deploy CRM** workflow (Actions tab → Deploy CRM → Run workflow), or upload the `crm/` folder contents.
5. In File Manager, copy `config.sample.php` to **`config.php`** in the CRM web root and fill in:
   - `dbname`, `user`, `password`
   - `intake_key` (same as the `VITE_INTAKE_KEY` secret) and `cron_key`
   - `mail`: SMTP details for the sending mailbox. SiteGround email or Microsoft 365 SMTP AUTH both work. Use port 465/`ssl` or 587/`tls`.
   - `sms`: Twilio account SID, auth token and sender (the same set-up as the Boxx CRM, on a separate Polished account or sender)
   - `notify_emails`: who gets new-enquiry and questionnaire-completed alerts
   - optional `telegram`
6. Visit `https://crm.polished-insurance.com/setup.php` to create the first admin. The tables create themselves. `setup.php` stops working once a user exists.
7. **Devs → Cron Jobs**: add the three reminder runs.

   ```
   30 9  * * *  php /home/customer/www/crm.polished-insurance.com/public_html/auto_chase.php 1
   0  13 * * *  php /home/customer/www/crm.polished-insurance.com/public_html/auto_chase.php 2
   30 17 * * *  php /home/customer/www/crm.polished-insurance.com/public_html/auto_chase.php 3
   ```

   Check the exact path in Site Tools; it is shown when you create a cron job.
8. Add staff under **Users**.

**Chasing behaviour** (in `config.php`): `auto_chase_new_leads => true` sends the questionnaire link as soon as a website lead arrives. Set it to `false` to have staff start the chase from the lead page instead, as in the Boxx CRM. Timings are `first_auto_chase_days`, `second_auto_chase_days` and `auto_close_after_days`.

## 3. SiteGround: the website

1. Add `polished-insurance.com` as a site (or use the existing one) and install SSL.
2. Run the **Deploy website** workflow. It builds, prerenders and uploads `website/dist/` over FTP.
3. **DNS**: point `polished-insurance.com` and `www` at SiteGround (A record / nameservers as SiteGround shows). **Leave the `quote` record unchanged.**
4. After DNS switches, purge SiteGround's cache (Speed → Caching) and test:
   - Submit the quote form on the home page. The lead should appear in the CRM, and the email and text should arrive.
   - Open the questionnaire link, complete it and submit. The lead should become "Questionnaire Completed".
   - Check `https://polished-insurance.com/sitemap.xml`, then submit it in Google Search Console.

## 4. Content engine

1. Make sure `ANTHROPIC_API_KEY` is set (and optionally `PEXELS_API_KEY`).
2. Actions → **Publish guide** → Run workflow with **dry_run** ticked. Read the article in the log.
3. Run it again without dry_run to publish the first guide. It commits the post and deploys the site automatically.
4. From then on it runs every **Tuesday and Friday at 07:15 UTC**. For one post a week, delete one `cron` line in `.github/workflows/publish-guide.yml`.

To get the guides section going at launch, run it manually 3 or 4 times.

## Updating

- **Website or content changes**: push to `main`. The website deploys automatically.
- **CRM changes**: push to `main`. The CRM deploys automatically; `config.php` and `data/` are never overwritten.
- **Database changes**: add a new numbered entry to `schema_migrations()` in `crm/inc/db_schema.php`. It runs automatically on the next page load.

## Backups and data protection

- Back up the CRM MySQL database regularly (SiteGround Backups, or phpMyAdmin → Export).
- Client questionnaires contain personal and potentially criminal-offence data. Keep SiteGround on a UK or EU data centre, and restrict CRM users to staff who need access.
- An admin can permanently delete a lead from its page (GDPR erasure). Agree a retention period for leads that never proceed, and delete them on that schedule.
