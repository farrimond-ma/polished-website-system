import React from 'react';
import { Link } from 'react-router-dom';
import SEO from '../components/SEO';
import Icon from '../components/Icons';
import { ResourceHero, FinalCtaBand } from '../components/resource/ResourceHero';
import { businessCovers, coverTypes } from '../data/covers';

const Grid = ({ items }) => (
  <div className="services-grid">
    {items.map((c) => (
      <Link to={`/cleaning-insurance/${c.slug}`} className="service-card" key={c.slug}>
        <div className="service-icon"><Icon name={c.icon} size={28} /></div>
        <h3>{c.title}</h3>
        <p>{c.cardBlurb}</p>
        <span className="service-link">Find out more &rarr;</span>
      </Link>
    ))}
  </div>
);

const CoversHub = () => (
  <>
    <SEO
      title="Cleaning Insurance: Business Types & Covers"
      description="Insurance for every type of UK cleaning business, and the covers they need: public liability, employers' liability, loss of keys, tools and equipment and more."
      canonical="/cleaning-insurance"
    />
    <ResourceHero
      title="Cleaning insurance: find the right cover"
      description="Start with your type of cleaning business, or read about a specific cover. Every page explains the risks insurers look at and links straight to a quote."
    />
    <section className="section services">
      <div className="container">
        <div className="section-header"><h2>By type of <span className="text-highlight">cleaning business</span></h2></div>
        <Grid items={businessCovers} />
      </div>
    </section>
    <section className="section">
      <div className="container">
        <div className="section-header"><h2>By type of <span className="text-highlight">cover</span></h2></div>
        <Grid items={coverTypes} />
      </div>
    </section>
    <FinalCtaBand />
  </>
);

export default CoversHub;
