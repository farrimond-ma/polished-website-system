import React from 'react';
import { Link } from 'react-router-dom';
import Icon from '../Icons';
import { SITE } from '../../config/site';
import './ResourcePage.css';

// Shared navy hero + final CTA band (same visual language as the Boxx site's ResourceHero).
// Text and background image only; the orange "Get your quote" button either jumps to the quote
// form on the same page (quoteAnchor="#quote") or links to an enquiry page (primaryCtaTo).
export const ResourceHero = ({ eyebrow, title, description, heroImage, primaryCtaTo = '/get-a-quote', primaryLabel = 'Get your quote', quoteAnchor, showTrust = true }) => {
  const isString = typeof title === 'string';
  const colonIdx = isString ? title.indexOf(':') : -1;
  const titleMain = isString ? (colonIdx !== -1 ? title.slice(0, colonIdx + 1) : title) : title;
  const titleAccent = isString && colonIdx !== -1 ? title.slice(colonIdx + 1).trim() : '';
  const isLongTitle = isString && title.length > 70;

  return (
    <div
      className={`resource-hero${heroImage ? ' has-hero-image' : ' has-pattern'}`}
      style={heroImage ? { '--hero-image': `url("${heroImage}")` } : undefined}
    >
      <div className="container resource-hero-grid">
        <div className="resource-hero-text">
          {eyebrow && <p className="resource-hero-eyebrow">{eyebrow}</p>}
          <h1 className={isLongTitle ? 'is-long-title' : undefined}>
            {titleMain}
            {titleAccent && <> <span className="text-highlight">{titleAccent}</span></>}
          </h1>
          {description && <p className="resource-hero-lead">{description}</p>}

          <div className="resource-hero-actions">
            {quoteAnchor
              ? <a href={quoteAnchor} className="btn btn-quote">{primaryLabel}</a>
              : <Link to={primaryCtaTo} className="btn btn-quote">{primaryLabel}</Link>}
            <a href={SITE.phoneHref} className="btn btn-outline resource-btn-phone">
              <Icon name="phone" size={18} /> {SITE.phoneDisplay}
            </a>
          </div>

          {showTrust && (
            <ul className="resource-hero-trust" aria-label="Why choose Polished Insurance">
              <li>Cleaning insurance specialists</li>
              <li>Quotes from a panel of UK insurers</li>
              <li>FCA regulated broker</li>
            </ul>
          )}
        </div>
      </div>
    </div>
  );
};

export const FinalCtaBand = ({ ctaTo = '/get-a-quote', heading = 'Ready to get your cleaning business covered?' }) => (
  <div className="resource-final-cta">
    <div className="container">
      <h2>{heading}</h2>
      <p>
        Tell us how to reach you and we will send a short questionnaire about your business. Your answers let us
        approach insurers for cover that fits the way you actually work.
      </p>
      <div className="resource-final-cta-actions">
        <Link to={ctaTo} className="btn btn-quote">Get your quote</Link>
        <span>or call <a href={SITE.phoneHref}>{SITE.phoneDisplay}</a></span>
      </div>
    </div>
  </div>
);

export default ResourceHero;
