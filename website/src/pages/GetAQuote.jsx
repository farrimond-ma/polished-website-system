import React from 'react';
import { useParams, Link } from 'react-router-dom';
import SEO from '../components/SEO';
import QuoteForm from '../components/QuoteForm';
import Icon from '../components/Icons';
import { coversBySlug, covers } from '../data/covers';
import { SITE } from '../config/site';
import './GetAQuote.css';

// Enquiry pages: /get-a-quote and /get-a-quote/:slug (one per cover page). Guides and cover
// pages link here so the enquiry is tagged with the cover the visitor was reading about.
const GetAQuote = () => {
  const { slug } = useParams();
  const cover = slug ? coversBySlug[slug] : null;
  const label = cover ? cover.title : 'Cleaning Business Insurance';

  return (
    <div className="quote-page" data-page-type="quote-page">
      <SEO
        title={cover ? `${cover.title} Quote` : 'Get a Cleaning Insurance Quote'}
        description={`Request a ${label.toLowerCase()} quote from Polished Insurance. Send your contact details and we will send you a short questionnaire to complete online.`}
        canonical={cover ? `/get-a-quote/${cover.slug}` : '/get-a-quote'}
      />
      <div className="quote-page-hero">
        <div className="container quote-page-grid">
          <div className="quote-page-copy">
            <p className="eyebrow">Get a quote</p>
            <h1>{cover ? <>{cover.title} <span className="text-highlight">quote</span></> : <>Get a cleaning insurance <span className="text-highlight">quote</span></>}</h1>
            <p className="quote-page-lead">
              {cover ? cover.description : 'Tell us how to reach you. We will send you a secure link to a short questionnaire about your business, then approach insurers on your behalf.'}
            </p>
            <ol className="quote-steps">
              <li><Icon name="check" size={18} /><div><strong>Now:</strong> your name, email and mobile number.</div></li>
              <li><Icon name="clipboard" size={18} /><div><strong>Next:</strong> our team sends you a secure link to your questionnaire by email and text. It only asks what applies to you, and saves as you go.</div></li>
              <li><Icon name="search" size={18} /><div><strong>Then:</strong> we take your answers to insurers and come back to you with options.</div></li>
            </ol>
            <p className="quote-page-call">
              Prefer to talk? Call <a href={SITE.phoneHref}>{SITE.phoneDisplay}</a> ({SITE.hours}).
            </p>
          </div>
          <div className="quote-page-form">
            <QuoteForm heading={cover ? 'Your contact details' : 'Your contact details'} coverInterest={cover ? cover.title : ''} />
          </div>
        </div>
      </div>
      {!cover && (
        <div className="container quote-page-links">
          <p>Looking for something specific?</p>
          <div>
            {covers.slice(0, 9).map((c) => <Link key={c.slug} to={`/get-a-quote/${c.slug}`}>{c.title}</Link>)}
          </div>
        </div>
      )}
    </div>
  );
};

export default GetAQuote;
