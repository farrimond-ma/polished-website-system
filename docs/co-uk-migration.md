# Migration from the previous www.polished-insurance.co.uk

The previous site was a Next.js app on Vercel. The full text of its 20 sitemap pages was captured on 2026-09-15 in `current-co-uk-site-archive.md`.

## What the previous site had

| Item | Previous site | New site |
|---|---|---|
| Hosting | Vercel (A `216.150.1.1`, `www` CNAME to Vercel) | SiteGround, deployed from GitHub |
| Canonical host | `www.polished-insurance.co.uk` (its sitemap wrongly used `polishedinsurance.co.uk`) | `https://www.polished-insurance.co.uk` |
| Phone | 01924 403370 (the live site showed 01924) | **01942 403370** (confirmed by the business, Wigan code) |
| Email | hello@polished-insurance.co.uk | unchanged |
| Contact form | `/api/contact` on Vercel (name, email, phone, message) | quote form, sent to the CRM (`crm.polished-insurance.co.uk/api/intake.php`) |
| Positioning | Zurich-underwritten, "from £120 / £140 / £225", instant documents, est. 2004, testimonial | **Not carried over** (decision 2026-09-15). The new site describes the specialist broker and questionnaire service. |
| Meta Pixel | `2585938745052925`, loaded on every page with no consent | Same Pixel, **only after cookie consent**, plus a `Lead` event on quote form submission |
| Privacy / Terms / Cookie / Make a Claim | footer links went nowhere (`#`) | real pages: privacy policy, terms of business, complaints, cookie policy |
| Images | logo and stock photos (`/logo.png`, `/service-*.jpg`, `/specialist-*.png`) | not reused; new branding. Can be added later if wanted. |

## 301 redirects (`website/public/.htaccess`)

| Old URL | New URL |
|---|---|
| `/insurance` | `/cleaning-insurance` |
| `/insurance/carpet-cleaners-insurance` | `/cleaning-insurance/carpet-cleaners-insurance` |
| `/insurance/upholstery-cleaners-insurance` | `/cleaning-insurance/carpet-cleaners-insurance` (the Carpet & Upholstery page) |
| `/insurance/domestic-cleaners-insurance` | `/cleaning-insurance/domestic-cleaners-insurance` |
| `/insurance/contract-cleaners-insurance` | `/cleaning-insurance/contract-cleaners-insurance` |
| `/insurance/end-of-tenancy-cleaners-insurance` | `/cleaning-insurance/end-of-tenancy-cleaning-insurance` |
| `/insurance/office-cleaners-insurance` | `/cleaning-insurance/commercial-cleaning-insurance` (the Commercial & Office page) |
| `/insurance/window-cleaners-insurance` | `/cleaning-insurance/window-cleaners-insurance` |
| `/insurance/oven-cleaners-insurance` | `/cleaning-insurance/oven-cleaners-insurance` |
| `/insurance/driveway-cleaners-insurance` | `/cleaning-insurance/pressure-washing-insurance` (the Pressure Washing & Driveway page) |
| `/contact` | `/get-a-quote` |
| `/blog` | `/guides` |
| `/blog/<slug>` | `/guides/<slug>` (same slug) |

All redirects go straight to `https://www.` in a single hop.

## Blog posts migrated as guides

All 8 posts keep their slug and original publish date, and show "last updated 15 September 2026". While migrating them:

- Zurich, "from £120", "£6.50/month" and "£15/month" claims were removed, as were "our policy covers it" statements. They now say "check your policy includes…".
- The invented injury figures in "The true cost of a claim" were replaced with a description of what drives the cost.
- The employers' liability exemption wording was corrected, and links to the HSE COSHH guidance were added.
- Each post gained links to its matching cover page, enquiry page and related guides, plus 2 FAQs (shown in an accordion with FAQ schema).

| Slug | Cover page / enquiry page |
|---|---|
| do-i-need-insurance-for-cleaning | public-liability-insurance-for-cleaners |
| legal-liability-cleaner-entering-premises | public-liability-insurance-for-cleaners |
| public-liability-vs-employers-liability | employers-liability-insurance-for-cleaners |
| coshh-explained-simply | commercial-cleaning-insurance |
| the-true-cost-of-a-cleaning-claim | public-liability-insurance-for-cleaners |
| keyholding-risks-explained | loss-of-keys-insurance |
| handling-accidental-damage-cleaning | domestic-cleaners-insurance |
| what-is-treatment-risk-insurance | carpet-cleaners-insurance |

Content engine topics that would have duplicated these posts (t001, t020, t023, t031, t035) are marked `skipped` in `topics.json`.
