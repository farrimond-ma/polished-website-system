import React from 'react';
import { Link } from 'react-router-dom';
import SEO from '../components/SEO';
import { ResourceHero, FinalCtaBand } from '../components/resource/ResourceHero';
import { SITE, REGULATORY_LINE } from '../config/site';
import { breadcrumbSchema, webPageSchema } from '../lib/schema';

const DESCRIPTION = 'About Polished Insurance: specialist insurance for UK cleaning businesses from Allied Insurance Services Ltd, authorised and regulated by the FCA.';

const AboutUs = () => (
  <div className="page-plain">
    <SEO title="About Us: Cleaning Insurance Brokers" description={DESCRIPTION} canonical="/about-us" schema={[webPageSchema('AboutPage', 'About Polished Insurance', DESCRIPTION, '/about-us'), breadcrumbSchema([['Home', '/'], ['About us', '/about-us']])]} />
    <ResourceHero title="About Polished Insurance" heroImage="/images/hero/about.webp" description="Specialist insurance for cleaning businesses, from a broker that takes the time to understand how you work." />
    <div className="content">
      <h2>Insurance built around cleaning</h2>
      <p>{SITE.name} was set up to make insurance simpler for cleaning businesses. Generic business insurance forms rarely ask the questions that matter to cleaners, such as the keys you hold, the heights you work at, the premises you clean or the equipment you rely on. Missing those details can leave gaps that only show up when you need to make a claim.</p>
      <p>Our online questionnaire is designed specifically for cleaning businesses. It only asks the questions that apply to you, and it gives insurers the full picture they need to quote accurately.</p>

      <h2>How we work</h2>
      <p>Getting a quote starts with your contact details. A member of our team then sends you a secure link to your questionnaire by email and text. You can complete it on your phone, save as you go and come back to it later. We take your answers to insurers that understand the cleaning sector and explain the options, including what each policy covers and any exclusions that matter to your work.</p>
      <p>If your contracts set minimum limits for public liability or employers&rsquo; liability, or ask for extras such as loss of keys cover, tell us and we will take that into account when we approach insurers.</p>

      <h2>Who we help</h2>
      <p>We work with window cleaners, domestic cleaners, carpet and oven cleaning specialists, end of tenancy teams, pressure washing businesses and contract cleaning companies of every size. See <Link to="/cleaning-insurance">all the businesses we insure</Link>.</p>

      <h2>Part of Allied Insurance Services</h2>
      <p>{REGULATORY_LINE}</p>
      <p>You can check our registration on the <a href="https://register.fca.org.uk" target="_blank" rel="noopener noreferrer">Financial Services Register</a>.</p>

      <h2>Contact us</h2>
      <p>Phone <a href={SITE.phoneHref}>{SITE.phoneDisplay}</a> ({SITE.hours}) or email <a href={`mailto:${SITE.email}`}>{SITE.email}</a>.</p>
    </div>
    <FinalCtaBand />
  </div>
);

export default AboutUs;
