import React from 'react';
import { Link } from 'react-router-dom';
import { covers, coversBySlug } from '../../data/covers';

// "You may also need" panel. Uses the page's own related list where it has one.
const CoverCards = ({ currentSlug, slugs, heading = 'You may also need' }) => {
  const list = (slugs && slugs.length ? slugs.map((s) => coversBySlug[s]).filter(Boolean) : covers)
    .filter((c) => c.slug !== currentSlug)
    .slice(0, 4);
  if (!list.length) return null;
  return (
    <section className="resource-cover-cards">
      <h2>{heading}</h2>
      <div className="cover-cards-grid">
        {list.map((c) => (
          <Link key={c.slug} to={`/cleaning-insurance/${c.slug}`} className="cover-card">
            <span className="cover-card-title">{c.title}</span>
            <span className="cover-card-blurb">{c.cardBlurb}</span>
          </Link>
        ))}
      </div>
    </section>
  );
};

export default CoverCards;
