// Single source for brand, contact and regulatory details — change them here, nowhere else.
export const SITE = {
  name: 'Polished Insurance',
  url: 'https://www.polished-insurance.co.uk',
  phoneDisplay: '01942 403370',
  phoneHref: 'tel:01942403370',
  email: 'hello@polished-insurance.co.uk',
  legalName: 'Allied Insurance Services Ltd',
  fcaNumber: '309497',
  companyNumber: '4319831',
  address: '98 Standishgate, Wigan, WN1 1XA',
  hours: 'Monday to Friday, 9am to 5:30pm',
};

// Website -> CRM. VITE_* values are baked in at build time (see .env.example and deploy.yml).
export const CRM_URL = (import.meta.env.VITE_CRM_URL || 'https://crm.polished-insurance.co.uk').replace(/\/$/, '');
export const INTAKE_KEY = import.meta.env.VITE_INTAKE_KEY || '';

export const REGULATORY_LINE =
  `Polished Insurance is a trading name of ${SITE.legalName}, which is authorised and regulated by the Financial Conduct Authority (FRN ${SITE.fcaNumber}). ` +
  `Registered in England and Wales, company number ${SITE.companyNumber}. Registered office: ${SITE.address}.`;

// Meta (Facebook) Pixel carried over from the previous site. Only loads after cookie consent (lib/consent.js).
export const FB_PIXEL_ID = '2585938745052925';
