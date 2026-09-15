# Polished Website System

Everything for **polished-insurance.co.uk** in one repository:

| Folder | What it is | Where it runs |
|---|---|---|
| `website/` | Marketing site (React 19 + Vite, prerendered to static HTML), built on the same design and pipeline as the Boxx Finance site | `polished-insurance.co.uk` (SiteGround) |
| `website/content-engine/` | Writes 1–2 detailed insurance guides a week for UK cleaning businesses, with internal links to enquiry pages | GitHub Actions (Tuesday + Friday) |
| `crm/` | Staff CRM (PHP 8 + MySQL): leads, the full SSR cleaning questionnaire, automated email + SMS chasing | `crm.polished-insurance.co.uk` (SiteGround) |
| `.github/workflows/` | Deploy website, deploy CRM, publish guides | GitHub |

Nothing in the existing Boxx CRM, Boxx Commercial Finance, SSR Questionnaire or Polished Insurance folders was changed. Files were copied from them and adapted here.

## How a lead flows

```
Website quote form  ──POST──▶  crm/api/intake.php  ──▶  new lead (POL-0001), status "New Enquiry"
(contact details only)                                   │
                                                         └─ team alert (email / optional Telegram)

Team member clicks "Send questionnaire link + start reminders" on the lead
                        └─ email + SMS: questionnaire link (message 1 of 3)

Client opens link  ──▶  polished-insurance.co.uk/insurance-questionnaire?t=<token>
                        │  schema + saved answers + pre-filled contact details from crm/api/questionnaire.php
                        │  shows only the questions that apply (conditional logic), autosaves
                        └─ submit ──▶ lead → "Questionnaire Completed", reminders stop,
                                      client gets confirmation email, team is alerted

crm/auto_chase.php (cron, 3x a day): reminder 2 after 2 days, reminder 3 after 3 more days,
                                     auto-close after 5 more days if still not completed
```

Staff see and edit **every** questionnaire field in the CRM (including staff-only "Extra" questions the client never sees), print it to PDF, or export it as CSV.

## Website pages

- `/` home, with the quote form in the hero
- `/cleaning-insurance` hub, and 14 cover pages `/cleaning-insurance/<slug>` (9 business types, 5 cover types). The content lives in `website/src/data/covers.js`.
- `/get-a-quote` and `/get-a-quote/<slug>`: enquiry pages. Every cover page and guide links to its matching one.
- `/guides` and `/guides/<slug>`: written by the content engine
- `/insurance-questionnaire?t=…`: the client questionnaire (noindex)
- `/about-us`, `/privacy-policy`, `/terms-of-business`, `/complaints`, `/cookie-policy`

Brand, phone and regulatory details live in `website/src/config/site.js`.

## The questionnaire

`crm/inc/schema.json` is a copy of the SSR Questionnaire's question set (`SSR Questionnaire/Siteground Files/inc/schema.json`) with these changes for a web-first journey:

- Added `contact_name` and `contact_phone` so the lead's details can be pre-filled.
- Added a single high-risk gate question (`hr_any`). A cleaner who answers "No" skips the 36 detailed high-risk questions.
- Claims History only appears when the client declares claims, and at least one claim row is then required.
- Some follow-up "detail" questions the SSR form hid from clients are shown to them when they apply (`client: true`), for example details of convictions or of other activities.
- Rewrote the Business Interruption note so it makes sense to a client.

Visibility rules (`showIf`) are implemented in three places that must stay in step: `crm/inc/questionnaire.php`, `crm/assets/questionnaire.js` (staff view) and `website/src/lib/questionnaireLogic.js` (client view).

> If staff edited questions in the live SSR app's in-app editor, those edits are stored in that app's database, not in its `schema.json`. Check before go-live and copy any changes across.

## Local development

```bash
# CRM (PHP 8.3 with SQLite, sending faked to crm/data/outbox.log)
cd crm
POLISHED_CONFIG=/path/to/config.local.php POLISHED_FAKE_SEND=1 php -S 127.0.0.1:8765

# Website (points the forms at the local CRM)
cd website
npm install
VITE_CRM_URL=http://127.0.0.1:8765 VITE_INTAKE_KEY=<intake_key> npx vite --port 5188
```

A local config is `config.sample.php` with `'driver' => 'sqlite'`, a `sqlite_path`, and `http://localhost:5188` added to `allowed_origins`. The database tables create themselves on first connection.

See **DEPLOYMENT.md** to go live and **website/content-engine/README.md** for the content engine.
