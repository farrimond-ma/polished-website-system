import React from 'react';
import { Link } from 'react-router-dom';
import { coversBySlug } from '../data/covers';
import { SITE } from '../config/site';
import './Article.css';

// Conversion blocks injected into guides at RENDER time (same approach as the Boxx site), so
// every guide, including ones already published, gets the same centrally edited CTAs.
// Each routes to the enquiry page for the guide's cover type, e.g. /get-a-quote/window-cleaners-insurance.

export const quoteLinkFor = (coverSlug) => (coverSlug && coversBySlug[coverSlug] ? `/get-a-quote/${coverSlug}` : '/get-a-quote');
const coverName = (coverSlug) => (coverSlug && coversBySlug[coverSlug] ? coversBySlug[coverSlug].title : 'cleaning business insurance');

export const SoftCta = ({ coverSlug }) => (
  <aside className="article-cta article-cta-soft">
    <h3>Not sure what cover your cleaning business needs?</h3>
    <p>
      We specialise in insurance for cleaners. Tell us how to reach you and we will send a short questionnaire,
      then approach insurers on your behalf.
    </p>
    <Link to={quoteLinkFor(coverSlug)} className="article-cta-link">Start your quote &rarr;</Link>
  </aside>
);

export const MidCta = ({ coverSlug }) => (
  <aside className="article-cta article-cta-mid">
    <h3>Looking for {coverName(coverSlug).toLowerCase().startsWith('cleaning') ? 'cleaning business insurance' : coverName(coverSlug)}?</h3>
    <p>Cover arranged by people who understand how cleaning businesses work.</p>
    <ul className="article-cta-ticks">
      <li>Public and employers&rsquo; liability limits to match your contracts</li>
      <li>Loss of keys, equipment and damage to property worked upon</li>
      <li>Quotes from a panel of UK insurers</li>
    </ul>
    <Link to={quoteLinkFor(coverSlug)} className="btn btn-primary">Get a quote</Link>
  </aside>
);

export const EndCta = ({ coverSlug }) => (
  <aside className="article-cta article-cta-end">
    <h3>Get the right cover for your cleaning business</h3>
    <p>
      Every cleaning business is different: the premises you work in, the equipment you use and the contracts you
      hold all affect the cover you need. Send us your contact details and we will take it from there.
    </p>
    <div className="article-cta-actions">
      <Link to={quoteLinkFor(coverSlug)} className="btn btn-primary">Start your quote</Link>
      <span className="article-cta-or">or call <a href={SITE.phoneHref}>{SITE.phoneDisplay}</a></span>
    </div>
  </aside>
);

// Generated guides end with their own "Frequently Asked Questions" section; the accordion below
// the article renders the same FAQs, so the inline copy is stripped to avoid showing them twice.
const stripInlineFaq = (html) => (html || '').replace(/<h2[^>]*>\s*Frequently Asked Questions\s*<\/h2>[\s\S]*$/i, '');

const ArticleBody = ({ html, coverSlug }) => {
  const sections = React.useMemo(() => stripInlineFaq(html).split(/(?=<h2[\s>])/i), [html]);
  if (sections.length < 4) {
    return (
      <>
        <div dangerouslySetInnerHTML={{ __html: sections.join('') }} />
        <MidCta coverSlug={coverSlug} />
      </>
    );
  }
  const mid = Math.ceil((sections.length + 1) / 2);
  return (
    <>
      <div dangerouslySetInnerHTML={{ __html: sections[0] }} />
      <SoftCta coverSlug={coverSlug} />
      <div dangerouslySetInnerHTML={{ __html: sections.slice(1, mid).join('') }} />
      <MidCta coverSlug={coverSlug} />
      <div dangerouslySetInnerHTML={{ __html: sections.slice(mid).join('') }} />
    </>
  );
};

export default ArticleBody;
