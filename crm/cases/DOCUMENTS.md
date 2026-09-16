# Document generation — how it works & how to map fields

The app can now generate the six SchemeServe documents (Schedule, Invoice, PL Certificate,
To-Whom-It-May-Concern, Adjustment, Statement of Fact) from a case's data.

**Where:** open a case → **Documents** button → pick a document → **Print / Save as PDF**.

## The pieces
| File | What it is | Do you edit it? |
|---|---|---|
| `templates/*.html` | The SchemeServe HTML templates, **kept verbatim** — all styling and `IF` logic intact | Only to change document *wording/layout* |
| `doc_engine.php` | Renders a template: evaluates `##…##` expressions and substitutes `[tokens]` | No |
| **`doc_field_map.php`** | **Maps each token to your database. THIS is the file you edit.** | **Yes** |
| `document.php` | The on-screen preview + print screen | No |

## How the templates work (unchanged from SchemeServe)
- `[ClientName]` — a merge field, replaced with a value.
- `##[PL_Limit].ToString("C0")##` — an expression: format as currency.
- `##IF([DO_YN_Value]='Yes','£100,000','Not Insured')##` — your **IF logic**, kept as-is and evaluated live.
- Row show/hide (`style="display:##IF(...,'','none')##"`) works automatically.

The engine only does two things: evaluate every `##…##`, then replace every `[token]`.

## Mapping a field (the bit you control)
Open **`doc_field_map.php`**. For each token, set its value, e.g.:
```php
$d['ClientName']    = $bizname;                 // already mapped for you
$d['PL_Limit']      = $plLimit;                 // already mapped
$d['PolicyNoPrefix'] = 'ALL';                   // <-- you decide
```
Anything **not** mapped shows in the document as `[TokenName]`, so you can see exactly what still
needs doing.

### Already mapped (from your database)
Client name / address, dates, all premiums (net, IPT 12%, policy fee, gross), PL limit, EL limit,
D&O flag, owned-plant, endorsements memorandum.

### Statement of Fact — now mapped to the `risk_answer` table
All 107 Statement-of-Fact tokens are mapped. The rule: **store each answer in the `risk_answer` table
with `question_key` = the SchemeServe token name** (e.g. `Who_Works_In_Your_Business`,
`Activities_WindowCleaning`, `Claims_Last5Years_YN`). The map auto-fills both `[Token]` and
`[Token_Value]`, and derives the financial fields (turnover, wageroll) from the rating. Anything not yet
in `risk_answer` simply renders blank. `02_sample_answers.sql` seeds a demo set for case 9676245 so you
can see it populated (optional — safe to skip on live).

### Business description — auto-built
`Client_Business_Description` is now generated from the checked activity answers using SchemeServe's
exact phrasing, followed by a height statement (e.g. "Working up to 1 metre."). No mapping needed —
it populates once the activity answers are in `risk_answer`.

### Policy number — config-driven
Shown as `[PolicyNoPrefix]/[Insurer_Policy_Number]/[PolicyNoSuffix]`:
- **Prefix / suffix** live in the `scheme` table (`policy_no_prefix`, `policy_no_suffix`) — edit once in the DB.
- **Insurer number** comes from `case_policy.insurer_policy_number` (set it per case), falling back to the case id.

### Saving documents to the case
On the document screen, **💾 Save to case** writes a snapshot to the `documents/` folder and records it
against the case. Saved documents appear in the **On file** list and open in a new tab.
- The `documents/` folder must exist and be **writable** (chmod 775 on SiteGround).
- **PDF:** saved snapshots are HTML today; a hook is in `document.php` — drop Dompdf into a `vendor/`
  folder and convert `$snapshot` to PDF there for true PDF files. Browser "Print → Save as PDF" works now.

## Two things to know
- **Logos:** the templates still load the Polished/Zurich logos from SchemeServe's servers. They'll work
  for now, but host your own copies eventually and update the `<img src>` in the templates.
- **PDF:** "Print / Save as PDF" uses the browser's print dialog (choose *Save as PDF*). If you later want
  server-side PDF files saved against the case, that's a small add-on (e.g. Dompdf).

## Mid-term adjustments & cancellations
From a case: **Adjust** or **Cancel policy**.
- **Adjust:** pick an effective date, change the cover/exposures; it recomputes the new annual premium and
  the **pro-rata additional/return** = (new − old annual) × days-remaining ÷ days-in-period. You can
  **overwrite** the net figure; IPT (12%) recomputes off it. Saving creates an Adjustment transaction,
  auto-issues three documents to the case's On-file list — the **Adjustment document** (showing the
  *additional/return*, not the annual), a **revised Schedule**, and a fresh **Statement of Fact** (the risk
  answers carry forward to the new term) — and writes an **audit note** to the case activity log.
  A cancellation issues only the Cancellation document.
- **Cancel:** pro-rata **return** = old annual × days-remaining ÷ days-in-period (overrideable); saving
  marks the policy Cancelled.
- Assumptions: daily pro-rata; no extra policy fee on MTAs (£0). Both easy to change in `adjust.php`.

## Deploying this update to SiteGround
1. Re-upload the refreshed `polished_webapp.zip` and extract over the top (templates, engine, field map,
   `document.php`, `adjust.php`, updated `case.php`/`quote.php`, and the `documents/` folder).
2. In **phpMyAdmin → Import**, run **`03_documents.sql`** then **`04_adjustments.sql`** (policy-number
   config, saved-doc column, and the term's `quote_inputs` column). Optionally `02_sample_answers.sql`.
3. Make the **`documents/` folder writable** (File Manager → Change Permissions → 775).
