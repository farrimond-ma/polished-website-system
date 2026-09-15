import React from 'react';
import SEO from '../components/SEO';
import CoverGrid from '../components/CoverGrid';
import { ResourceHero, FinalCtaBand } from '../components/resource/ResourceHero';
import { businessCovers, coverTypes } from '../data/covers';
import { breadcrumbSchema, webPageSchema } from '../lib/schema';

const TITLE = 'Cleaning Insurance: Business Types & Covers';
const DESCRIPTION = "Cleaning insurance for every type of UK cleaning business, plus the covers they need: public and employers' liability, loss of keys, tools and more.";

const CoversHub = () => (
  <>
    <SEO
      title={TITLE}
      description={DESCRIPTION}
      canonical="/cleaning-insurance"
      schema={[webPageSchema('CollectionPage', TITLE, DESCRIPTION, '/cleaning-insurance'), breadcrumbSchema([['Home', '/'], ['Cleaning insurance', '/cleaning-insurance']])]}
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
