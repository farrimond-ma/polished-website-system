import React, { useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import SEO from '../components/SEO';
import { titleCase } from '../lib/titleCase';
import { heroSrcSet } from '../lib/images';
import { ResourceHero, FinalCtaBand } from '../components/resource/ResourceHero';
import { publishedPosts, formatDate, DEFAULT_GUIDE_IMAGE } from '../data/posts';
import { breadcrumbSchema, webPageSchema } from '../lib/schema';
import '../components/Article.css';

const DESCRIPTION = "Practical insurance guides for UK cleaning businesses: public and employers' liability, contracts, keys, equipment, claims and running a cleaning company.";

const Guides = () => {
  const filters = useMemo(() => {
    const counts = {};
    publishedPosts.forEach((p) => { if (p.category) counts[p.category] = (counts[p.category] || 0) + 1; });
    return Object.entries(counts).filter(([, n]) => n >= 2).sort((a, b) => b[1] - a[1]).map(([n]) => n);
  }, []);
  const [active, setActive] = useState('All');
  const shown = active === 'All' ? publishedPosts : publishedPosts.filter((p) => p.category === active);

  return (
    <div className="blog-page">
      <SEO
        title="Insurance Guides for Cleaning Businesses"
        description={DESCRIPTION}
        canonical="/guides"
        schema={[webPageSchema('CollectionPage', 'Insurance Guides for Cleaning Businesses', DESCRIPTION, '/guides'), breadcrumbSchema([['Home', '/'], ['Guides', '/guides']])]}
      />
      <ResourceHero
        title="Insurance guides for cleaning businesses"
        heroImage="/images/hero/guides.webp"
        description="Plain-English guides to the cover, contracts and risks that matter when you run a cleaning business in the UK. General guidance, not personal advice."
      />
      <section className="section">
        <div className="container">
          {filters.length > 0 && (
            <div className="blog-filter" role="group" aria-label="Filter guides by topic">
              <button type="button" className={`blog-filter-chip${active === 'All' ? ' is-active' : ''}`} onClick={() => setActive('All')}>
                All <span className="blog-filter-count">{publishedPosts.length}</span>
              </button>
              {filters.map((name) => (
                <button key={name} type="button" className={`blog-filter-chip${active === name ? ' is-active' : ''}`} onClick={() => setActive(name)}>{name}</button>
              ))}
            </div>
          )}
          {shown.length > 0 ? (
            <div className="blog-grid">
              {shown.map((p) => (
                <Link key={p.slug} to={`/guides/${p.slug}`} className="blog-card" aria-label={p.title}>
                  <div className="blog-card-img">
                    <img src={p.heroImage || DEFAULT_GUIDE_IMAGE} srcSet={p.heroImage ? heroSrcSet(p.heroImage) : undefined} sizes="(max-width: 640px) 100vw, 380px" alt="" loading="lazy" width="1440" height="900" onError={(e) => { e.currentTarget.src = DEFAULT_GUIDE_IMAGE; }} />
                    {p.category && <span className="blog-card-tag">{p.category}</span>}
                  </div>
                  <div className="blog-card-content">
                    <h2>{titleCase(p.title)}</h2>
                    <p>{p.excerpt}</p>
                    <p className="blog-card-date">{p.updatedAt ? `Updated ${formatDate(p.updatedAt)}` : `Published ${formatDate(p.publishedAt || p.date)}`}</p>
                    <span className="read-more">Read guide &rarr;</span>
                  </div>
                </Link>
              ))}
            </div>
          ) : (
            <div className="text-center"><h2>New guides are on the way</h2><p>In the meantime, explore our <Link to="/cleaning-insurance">cleaning insurance pages</Link>.</p></div>
          )}
        </div>
      </section>
      <FinalCtaBand />
    </div>
  );
};

export default Guides;
