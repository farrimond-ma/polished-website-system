# Going live

Work through these in order. The code is on GitHub (`farrimond-ma/polished-website-system`); no DNS, database or server changes have been made yet.

## 0. Before you start

- The new site replaces the current **www.polished-insurance.co.uk** (a Next.js site on Vercel). Its full text is archived in `docs/current-co-uk-site-archive.md`, and every old URL has a 301 redirect; see `docs/co-uk-migration.md`.
- **The existing `quote.polished-insurance.com` (SSR Questionnaire app) is on a different domain and stays exactly as it is.**
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
| `WEBSITE_SERVER_DIR` | variable (optional) | FTP path to the site's web root; default `polished-insurance.co.uk/public_html/` |
| `CRM_SERVER_DIR` | variable (optional) | default `crm.polished-insurance.co.uk/public_html/` |
| `VITE_CRM_URL` | variable (optional) | default `https://crm.polished-insurance.co.uk` |

Optionally use separate `CRM_FTP_SERVER` / `CRM_FTP_USERNAME` / `CRM_FTP_PASSWORD` secrets for the CRM.

Generate random keys with:

```bash
openssl rand -hex 32
```

## 2. SiteGround: the CRM (do this first, so the website form has somewhere to send leads)

1. **Site Tools → Domain → Subdomains**: create `crm.polished-insurance.co.uk`.
2. **Security → SSL Manager**: install Let's Encrypt for `crm.polished-insurance.co.uk`.
3. **MySQL → Databases**: create a database and a user with all privileges. Keep this separate from the SSR questionnaire's database.
4. Run the **Deploy CRM** workflow (Actions tab → Deploy CRM → Run workflow), or upload the `crm/` folder contents.
5. In File Manager, copy `config.sample.php` to **`config.php`** in the CRM web root and fill in:
   - `dbname`, `user`, `password`
   - `intake_key` (same as the `VITE_INTAKE_KEY` secret) and `cron_key`
   - `mail`: SMTP details for the sending mailbox. SiteGround email or Microsoft 365 SMTP AUTH both work. Use port 465/`ssl` or 587/`tls`.
   - `sms`: Twilio account SID, auth token and sender (the same set-up as the Boxx CRM, on a separate Polished account or sender)
   - `notify_emails`: who gets new-enquiry and questionnaire-completed alerts
   - optional `telegram`
6. Visit `https://crm.polished-insurance.co.uk/setup.php` to create the first admin. The tables create themselves. `setup.php` stops working once a user exists.
7. **Devs → Cron Jobs**: add the three reminder runs.

   ```
   30 9  * * *  php /home/customer/www/crm.polished-insurance.co.uk/public_html/auto_chase.php 1
   0  13 * * *  php /home/customer/www/crm.polished-insurance.co.uk/public_html/auto_chase.php 2
   30 17 * * *  php /home/customer/www/crm.polished-insurance.co.uk/public_html/auto_chase.php 3
   ```

   Check the exact path in Site Tools; it is shown when you create a cron job.
8. Add staff under **Users**.

**Chasing behaviour**: nothing is sent to a client automatically when an enquiry arrives. The lead appears under **Needs attention**, and the team is alerted. A team member opens it and clicks **Send questionnaire link + start reminders**, which sends message 1 straight away; reminders 2 and 3 then follow automatically until the questionnaire is completed. Timings are `first_auto_chase_days`, `second_auto_chase_days` and `auto_close_after_days` in `config.php`. (`send_questionnaire_automatically => true` would send message 1 as soon as an enquiry arrives.)

## 3. SiteGround: the website

The domain's nameservers are **already SiteGround** (`ns1/ns2.siteground.net`), and so is its email (`mx10/20/30.mailspamprotection.com`). Only the website records currently point to Vercel, so the switch is a two-record change and **email is not affected**.

1. In the SiteGround site for `polished-insurance.co.uk`, install SSL for both `polished-insurance.co.uk` and `www.polished-insurance.co.uk`.
2. Run the **Deploy website** workflow. It builds, prerenders and uploads `website/dist/` to the site's `public_html`. Visitors still see the Vercel site until step 3.
3. **Site Tools → Domain → DNS Zone Editor**. Change only these two records:
   - `polished-insurance.co.uk` **A** record: currently `216.150.1.1` (Vercel). Change it to the SiteGround server IP shown in Site Tools.
   - `www` record: currently a **CNAME** to `...vercel-dns-016.com`. Change it to an A record with the same SiteGround IP, or a CNAME to `polished-insurance.co.uk`.
   - **Do not change** the MX, SPF (`v=spf1 ...`) or `google-site-verification` TXT records.
4. Wait for DNS (usually minutes, up to a few hours), purge SiteGround's cache (Speed → Caching), then test:
   - `https://polished-insurance.co.uk` redirects to `https://www.polished-insurance.co.uk`.
   - Old URLs redirect: `/insurance/window-cleaners-insurance`, `/contact`, `/blog`, `/blog/keyholding-risks-explained`.
   - Submit the quote form on the home page. The lead should appear in the CRM, and the email and text should arrive.
   - Open the questionnaire link, complete it and submit. The lead should become "Questionnaire Completed".
   - Accept cookies, then check the Pixel fires in Meta Events Manager (Test Events).
5. **Google Search Console** (the domain is already verified): submit `https://www.polished-insurance.co.uk/sitemap.xml`. The old sitemap listed `polishedinsurance.co.uk` without the hyphen, which was wrong, so remove it if it appears.
6. When the new site is confirmed working, remove the domain from the Vercel project so Vercel stops trying to serve it.

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
