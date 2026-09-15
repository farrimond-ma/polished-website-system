import React from 'react';
import { Link } from 'react-router-dom';
import { guidesForCover, postsBySlug } from '../../data/posts';

// Related guides list. Uses explicit related slugs (set by the content engine) first.
const GuidesList = ({ coverSlug, currentSlug, relatedSlugs = [], heading = 'Related guides' }) => {
  const explicit = relatedSlugs.map((s) => postsBySlug[s]).filter(Boolean);
  const seen = new Set(explicit.map((p) => p.slug));
  const list = [...explicit, ...guidesForCover(coverSlug, 12).filter((p) => !seen.has(p.slug))]
    .filter((p) => p.slug !== currentSlug)
    .slice(0, 6);
  if (!list.length) return null;
  return (
    <section className="resource-guides">
      <h2>{heading}</h2>
      <ul className="resource-guides-grid">
        {list.map((p) => (
          <li key={p.slug}><Link to={`/guides/${p.slug}`}>{p.title}</Link></li>
        ))}
      </ul>
    </section>
  );
};

export default GuidesList;
