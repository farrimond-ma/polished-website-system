import React from 'react';
import SEO from '../components/SEO';
import CoverGrid from '../components/CoverGrid';
import { ResourceHero, FinalCtaBand } from '../components/resource/ResourceHero';
import { businessCovers, coverTypes } from '../data/covers';

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
        <CoverGrid items={businessCovers} />
      </div>
    </section>
    <section className="section">
      <div className="container">
        <div className="section-header"><h2>By type of <span className="text-highlight">cover</span></h2></div>
        <CoverGrid items={coverTypes} />
      </div>
    </section>
    <FinalCtaBand />
  </>
);

export default CoversHub;
