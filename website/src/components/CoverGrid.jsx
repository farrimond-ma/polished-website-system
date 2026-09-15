import React from 'react';
import { Link } from 'react-router-dom';
import { coverImage } from '../data/covers';
import { imageVariant } from '../lib/images';

// Photo cards linking to cover pages (home page and /cleaning-insurance hub).
const CoverGrid = ({ items }) => (
  <div className="services-grid">
    {items.map((c) => (
      <Link to={`/cleaning-insurance/${c.slug}`} className="service-card has-image" key={c.slug}>
        <div className="service-card-img">
          <img src={coverImage(c.slug)} srcSet={`${imageVariant(coverImage(c.slug), 480)} 480w, ${coverImage(c.slug)} 800w`} sizes="(max-width: 640px) 100vw, 380px" alt="" loading="lazy" width="800" height="600" />
        </div>
        <div className="service-card-body">
          <h3>{c.title}</h3>
          <p>{c.cardBlurb}</p>
          <span className="service-link">Find out more &rarr;</span>
        </div>
      </Link>
    ))}
  </div>
);

export default CoverGrid;
