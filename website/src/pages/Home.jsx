import React from 'react';
import { Link } from 'react-router-dom';
import SEO from '../components/SEO';
import Icon from '../components/Icons';
import QuoteForm from '../components/QuoteForm';
import { ResourceHero, FinalCtaBand } from '../components/resource/ResourceHero';
import FaqAccordion from '../components/resource/FaqAccordion';
import { businessCovers, coverTypes } from '../data/covers';
import { publishedPosts, formatDate } from '../data/posts';
import { homeFaqSchema } from '../data/homeFaqs';
import { SITE } from '../config/site';
import '../components/Article.css';
import './Home.css';

const orgSchema = {
  '@context': 'https://schema.org',
  '@type': 'InsuranceAgency',
  name: SITE.name,
  legalName: SITE.legalName,
  url: SITE.url,
  logo: `${SITE.url}/favicon.svg`,
  telephone: '+44-1942-403370',
  email: SITE.email,
  address: { '@type': 'PostalAddress', streetAddress: '98 Standishgate', addressLocality: 'Wigan', postalCode: 'WN1 1XA', addressCountry: 'GB' },
  areaServed: 'GB',
  identifier: { '@type': 'PropertyValue', propertyID: 'FCA FRN', value: SITE.fcaNumber },
};

const Home = () => (
  <>
    <SEO
      title="Cleaning Business Insurance UK"
      description="Specialist insurance for UK cleaning businesses: public liability, employers' liability, loss of keys and equipment cover for contract, commercial, domestic and window cleaners."
      keywords="cleaning business insurance, cleaners insurance, public liability insurance for cleaners, contract cleaners insurance, window cleaners insurance"
      canonical="/"
      schema={[orgSchema, { '@context': 'https://schema.org', '@type': 'WebSite', name: SITE.name, url: SITE.url }, homeFaqSchema]}
    />

    <ResourceHero
      eyebrow="Specialist cleaning insurance"
      title={<>Insurance for <span className="text-highlight">cleaning businesses</span></>}
      description="From sole-trader window cleaners to contract cleaning companies with hundreds of staff, we arrange cover that matches the work you do, the premises you work in and the contracts you hold."
      aside={<QuoteForm heading="Get a quote" />}
    />

    <section className="section">
      <div className="container">
        <div className="section-header">
          <p className="eyebrow">How it works</p>
          <h2>Quotes without the <span className="text-highlight">long phone call</span></h2>
          <p>We only ask for your contact details up front. The rest happens in your own time.</p>
        </div>
        <div className="steps">
          <div className="step"><h3>Send your details</h3><p>Your name, email and mobile number. That is all we need to get started.</p></div>
          <div className="step"><h3>Complete your questionnaire</h3><p>We send you a secure link. It only asks the questions that apply to your business and saves as you go.</p></div>
          <div className="step"><h3>We find your cover</h3><p>We take your answers to insurers and come back to you with options and a clear explanation of what is covered.</p></div>
        </div>
      </div>
    </section>

    <section className="section services" id="cleaning-businesses">
      <div className="container">
        <div className="section-header">
          <p className="eyebrow">Who we insure</p>
          <h2>Cover for every kind of <span className="text-highlight">cleaning business</span></h2>
          <p>Choose your type of business to see the risks insurers look at and the covers most cleaners in your line of work choose.</p>
        </div>
        <div className="services-grid">
          {businessCovers.map((c) => (
            <Link to={`/cleaning-insurance/${c.slug}`} className="service-card" key={c.slug}>
              <div className="service-icon"><Icon name={c.icon} size={28} /></div>
              <h3>{c.title}</h3>
              <p>{c.cardBlurb}</p>
              <span className="service-link">Find out more &rarr;</span>
            </Link>
          ))}
        </div>
      </div>
    </section>

    <section className="section why">
      <div className="container">
        <div className="section-header">
          <p className="eyebrow" style={{ color: 'var(--color-secondary-light)' }}>Why Polished</p>
          <h2>We only focus on <span className="text-highlight">cleaning</span></h2>
          <p>General business insurance forms are not written with cleaners in mind. Ours are.</p>
        </div>
        <div className="why-grid">
          <div className="why-item"><div className="service-icon"><Icon name="clipboard" /></div><h3>The right questions</h3><p>Our questionnaire covers the things insurers need to know about cleaning work, so there are fewer surprises when you claim.</p></div>
          <div className="why-item"><div className="service-icon"><Icon name="search" /></div><h3>A panel of insurers</h3><p>We approach insurers that understand the cleaning sector, rather than offering a single product.</p></div>
          <div className="why-item"><div className="service-icon"><Icon name="briefcase" /></div><h3>Contract-ready cover</h3><p>Tell us what your contracts require and we will match your limits and paperwork to them.</p></div>
          <div className="why-item"><div className="service-icon"><Icon name="shield" /></div><h3>FCA regulated</h3><p>{SITE.name} is a trading name of {SITE.legalName}, authorised and regulated by the Financial Conduct Authority.</p></div>
        </div>
      </div>
    </section>

    <section className="section">
      <div className="container">
        <div className="section-header">
          <p className="eyebrow">Covers</p>
          <h2>The covers cleaners <span className="text-highlight">ask us about</span></h2>
        </div>
        <div className="services-grid">
          {coverTypes.map((c) => (
            <Link to={`/cleaning-insurance/${c.slug}`} className="service-card" key={c.slug}>
              <div className="service-icon"><Icon name={c.icon} size={28} /></div>
              <h3>{c.title}</h3>
              <p>{c.cardBlurb}</p>
              <span className="service-link">Find out more &rarr;</span>
            </Link>
          ))}
        </div>
      </div>
    </section>

    {publishedPosts.length > 0 && (
      <section className="section services">
        <div className="container">
          <div className="section-header">
            <p className="eyebrow">Guides</p>
            <h2>Insurance guides for <span className="text-highlight">cleaners</span></h2>
          </div>
          <div className="guide-teasers">
            {publishedPosts.slice(0, 3).map((p) => (
              <Link key={p.slug} to={`/guides/${p.slug}`} className="blog-card">
                <div className="blog-card-content">
                  <h3>{p.title}</h3>
                  <p>{p.excerpt}</p>
                  <p className="blog-card-date">Published {formatDate(p.publishedAt || p.date)}</p>
                  <span className="read-more">Read guide &rarr;</span>
                </div>
              </Link>
            ))}
          </div>
          <p className="text-center" style={{ marginTop: '2rem' }}><Link to="/guides" className="btn btn-navy">All guides</Link></p>
        </div>
      </section>
    )}

    <section className="section home-faq" id="faq">
      <div className="container">
        <div className="section-header">
          <p className="eyebrow">Common questions</p>
          <h2>Cleaning insurance, <span className="text-highlight">answered</span></h2>
        </div>
        <FaqAccordion faqSchema={homeFaqSchema} />
      </div>
    </section>

    <FinalCtaBand />
  </>
);

export default Home;
