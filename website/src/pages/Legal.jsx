import React from 'react';
import { Link } from 'react-router-dom';
import SEO from '../components/SEO';
import { ResourceHero } from '../components/resource/ResourceHero';
import { SITE, REGULATORY_LINE } from '../config/site';
import { openConsent } from '../lib/consent';
import { breadcrumbSchema, webPageSchema } from '../lib/schema';

// DRAFT legal wording — must be reviewed and approved by Allied Insurance Services' compliance
// team before the site goes live (particularly retention periods, insurer/partner lists and the
// complaints process, which must match the firm's actual procedures and Terms of Business).

const LegalPage = ({ title, description, path, children }) => (
  <div className="page-plain">
    <SEO title={title} description={description} canonical={path} schema={[webPageSchema('WebPage', title, description, path), breadcrumbSchema([['Home', '/'], [title, path]])]} />
    <ResourceHero title={title} showTrust={false} description={description} />
    <div className="content">
      {children}
      <p className="updated">{REGULATORY_LINE}</p>
    </div>
  </div>
);

export const PrivacyPolicy = () => (
  <LegalPage title="Privacy Policy" path="/privacy-policy" description="How Polished Insurance collects, uses, shares and protects your personal information when you enquire about insurance for your cleaning business.">
    <p>{SITE.name} is a trading name of {SITE.legalName} (&ldquo;we&rdquo;, &ldquo;us&rdquo;). We are the data controller for the personal information described in this policy. Our registered office is {SITE.address}.</p>

    <h2>Information we collect</h2>
    <ul>
      <li><strong>Contact details</strong> you give us through our quote form: name, business name, email address and phone number.</li>
      <li><strong>Questionnaire answers</strong> about your business, including turnover, staff, activities, covers required, claims history and declarations about directors or partners. Some answers, such as criminal convictions, may be special category or criminal offence data.</li>
      <li><strong>Records of our contact with you</strong>, including emails, text messages and notes of calls.</li>
      <li><strong>Technical information</strong> such as the pages you visited before making an enquiry and any campaign tags in the link you followed. If you accept advertising cookies, Meta (Facebook) also receives information about your visit through its Pixel; see our <Link to="/cookie-policy">cookie policy</Link>.</li>
    </ul>

    <h2>How we use your information</h2>
    <ul>
      <li>To respond to your enquiry and send you your questionnaire link and reminders to complete it.</li>
      <li>To obtain quotations from insurers and arrange, administer and renew your insurance.</li>
      <li>To meet our legal and regulatory obligations, including those of the Financial Conduct Authority, and to prevent fraud.</li>
      <li>To improve our website and services.</li>
    </ul>

    <h2>Our lawful basis</h2>
    <p>We use your information to take steps at your request before entering into a contract and to perform that contract, to comply with legal obligations, and for our legitimate interests in running and improving our business. Where we process special category or criminal offence data, we do so because it is necessary for an insurance purpose, as permitted by the Data Protection Act 2018. You can ask us to stop sending reminders at any time.</p>

    <h2>Who we share it with</h2>
    <p>We share information with insurers, underwriting agencies and premium finance providers where needed to obtain quotes and arrange cover, with service providers who support our business (such as IT, email and text message providers), and with regulators, fraud prevention agencies or law enforcement where required. We do not sell your information.</p>

    <h2>How long we keep it</h2>
    <p>If you do not proceed with a policy, we keep your enquiry and questionnaire for a limited period in case you come back to us, then delete it. Where we arrange a policy, we keep records for as long as required by law and by our regulator, which can be several years after the policy ends.</p>

    <h2>Your rights</h2>
    <p>You have the right to access your information, to have it corrected or erased, to restrict or object to its use, and to data portability. To exercise any of these rights, email <a href={`mailto:${SITE.email}`}>{SITE.email}</a>. If you are unhappy with how we have handled your information, you can complain to the Information Commissioner&rsquo;s Office at <a href="https://ico.org.uk" rel="noopener noreferrer" target="_blank">ico.org.uk</a>.</p>
  </LegalPage>
);

export const TermsOfBusiness = () => (
  <LegalPage title="Terms of Business" path="/terms-of-business" description="Terms of use for the Polished Insurance website and a summary of how we work as an FCA regulated insurance broker for cleaning businesses.">
    <h2>About us</h2>
    <p>{REGULATORY_LINE} You can check our details on the Financial Services Register at <a href="https://register.fca.org.uk" target="_blank" rel="noopener noreferrer">register.fca.org.uk</a>.</p>
    <h2>Our service</h2>
    <p>We arrange general insurance for cleaning businesses. We will explain whether we provide advice or information only, the insurers we approach, and how we are paid, in our full Terms of Business Agreement, which we provide before you buy a policy.</p>
    <h2>Your duty of fair presentation</h2>
    <p>Commercial insurance customers must make a fair presentation of the risk to insurers. That means disclosing every material circumstance you know or ought to know. If you are unsure whether something is relevant, tell us. Failing to do so could result in a claim not being paid in full, or at all.</p>
    <h2>Using this website</h2>
    <p>The guides and information on this website are general in nature and are not personal advice. We try to keep them accurate and up to date but cannot guarantee they are complete. Cover is always subject to the terms, conditions and exclusions of the policy issued.</p>
    <h2>Complaints</h2>
    <p>See our <Link to="/complaints">complaints procedure</Link>.</p>
  </LegalPage>
);

export const Complaints = () => (
  <LegalPage title="Complaints" path="/complaints" description="How to make a complaint to Polished Insurance, what happens next, and when you can refer it to the Financial Ombudsman Service.">
    <p>We aim to provide a high standard of service. If something has gone wrong, please tell us so we can put it right.</p>
    <h2>How to complain</h2>
    <ul>
      <li>Phone: <a href={SITE.phoneHref}>{SITE.phoneDisplay}</a></li>
      <li>Email: <a href={`mailto:${SITE.email}`}>{SITE.email}</a></li>
      <li>Post: Complaints, {SITE.legalName}, {SITE.address}</li>
    </ul>
    <h2>What happens next</h2>
    <p>We will acknowledge your complaint promptly and aim to resolve it as quickly as possible. If we cannot resolve it within eight weeks, or you are unhappy with our final response, you may be able to refer your complaint to the Financial Ombudsman Service, free of charge.</p>
    <h2>Financial Ombudsman Service</h2>
    <p>Exchange Tower, London E14 9SR. Phone 0800 023 4567. Website <a href="https://www.financial-ombudsman.org.uk" target="_blank" rel="noopener noreferrer">financial-ombudsman.org.uk</a>. Eligibility depends on the size of your business.</p>
    <h2>Financial Services Compensation Scheme</h2>
    <p>We are covered by the Financial Services Compensation Scheme (FSCS). You may be entitled to compensation if we cannot meet our obligations, depending on the type of business and the circumstances of the claim. Find out more at <a href="https://www.fscs.org.uk" target="_blank" rel="noopener noreferrer">fscs.org.uk</a>.</p>
  </LegalPage>
);

export const CookiePolicy = () => (
  <LegalPage title="Cookie Policy" path="/cookie-policy" description="How the Polished Insurance website uses cookies and similar technologies, including the Meta Pixel, and how to change your cookie choice.">
    <h2>Essential storage (always on)</h2>
    <ul>
      <li><strong>Enquiry attribution</strong>: for the length of your visit we record the page you first landed on and any campaign tags in the link you followed, so we can see which page an enquiry came from. It is deleted when you close your browser.</li>
      <li><strong>Your cookie choice</strong>: we remember whether you accepted or rejected advertising cookies, so we do not ask again on every page.</li>
    </ul>
    <h2>Advertising cookies (only with your consent)</h2>
    <p>If you click &ldquo;Accept&rdquo;, we load the Meta Pixel, provided by Meta Platforms Ireland Ltd (Facebook and Instagram). It sets cookies such as <code>_fbp</code> and tells Meta which pages you viewed and whether you sent us an enquiry. This helps us measure our adverts and show them to relevant people. Meta&rsquo;s use of this information is covered by its own privacy policy at <a href="https://www.facebook.com/privacy/policy/" target="_blank" rel="noopener noreferrer">facebook.com/privacy/policy</a>.</p>
    <p>If you click &ldquo;Reject&rdquo;, or make no choice, the Meta Pixel is not loaded.</p>
    <h2>Changing your choice</h2>
    <p>You can change your mind at any time using <button type="button" className="link-button" onClick={openConsent}>cookie settings</button>, which also appears in the footer of every page. You can also delete cookies in your browser settings.</p>
  </LegalPage>
);
