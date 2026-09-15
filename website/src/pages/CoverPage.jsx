import React from 'react';
import { useParams, Link } from 'react-router-dom';
import SEO from '../components/SEO';
import QuoteSection from '../components/QuoteSection';
import { ResourceHero, FinalCtaBand } from '../components/resource/ResourceHero';
import FaqAccordion from '../components/resource/FaqAccordion';
import CoverCards from '../components/resource/CoverCards';
import GuidesList from '../components/resource/GuidesList';
import { coversBySlug, coverHeroImage } from '../data/covers';
import { SITE } from '../config/site';
import NotFound from './NotFound';
import '../components/Article.css';

const CoverPage = () => {
  const { slug } = useParams();
  const cover = coversBySlug[slug];
  if (!cover) return <NotFound />;

  const url = `${SITE.url}/cleaning-insurance/${cover.slug}`;
  const faqSchema = {
    '@context': 'https://schema.org',
    '@type': 'FAQPage',
    mainEntity: cover.faqs.map((f) => ({ '@type': 'Question', name: f.q, acceptedAnswer: { '@type': 'Answer', text: f.a } })),
  };
  const serviceSchema = {
    '@context': 'https://schema.org',
    '@type': 'Service',
    name: cover.title,
    serviceType: 'Insurance broking',
    description: cover.metaDescription,
    url,
    areaServed: 'GB',
    provider: { '@type': 'InsuranceAgency', name: SITE.name, url: SITE.url },
  };
  const breadcrumb = {
    '@context': 'https://schema.org',
    '@type': 'BreadcrumbList',
    itemListElement: [
      { '@type': 'ListItem', position: 1, name: 'Home', item: SITE.url },
      { '@type': 'ListItem', position: 2, name: 'Cleaning insurance', item: `${SITE.url}/cleaning-insurance` },
      { '@type': 'ListItem', position: 3, name: cover.title, item: url },
    ],
  };
  const quoteTo = `/get-a-quote/${cover.slug}`;
  const midpoint = Math.ceil(cover.sections.length / 2);

  const renderSection = (s) => (
    <React.Fragment key={s.heading}>
      <h2>{s.heading}</h2>
      {(s.paragraphs || []).map((p, i) => <p key={i}>{p}</p>)}
      {s.list && <ul>{s.list.map((li, i) => <li key={i}>{li}</li>)}</ul>}
    </React.Fragment>
  );

  return (
    <div className="resource-page" data-page-type="cover-page">
      <SEO title={cover.metaTitle} description={cover.metaDescription} canonical={`/cleaning-insurance/${cover.slug}`} type="website" image={coverHeroImage(cover.slug)} schema={[serviceSchema, faqSchema, breadcrumb]} />
      <ResourceHero
        title={cover.title}
        description={cover.description}
        heroImage={coverHeroImage(cover.slug)}
        primaryCtaTo={quoteTo}
        quoteAnchor="#quote"
      />
      <div className="resource-column">
        <nav className="breadcrumbs" aria-label="Breadcrumb">
          <Link to="/">Home</Link> <span>/</span> <Link to="/cleaning-insurance">Cleaning insurance</Link> <span>/</span> <span>{cover.title}</span>
        </nav>
        <div className="resource-main-card">
          <div className="blog-post-content service-page-content">
            {cover.intro.map((p, i) => <p key={i}>{p}</p>)}
            {cover.sections.slice(0, midpoint).map(renderSection)}
          </div>
          <QuoteSection compact coverInterest={cover.title} heading={`Get your ${cover.title.toLowerCase()} quote`} />
          <div className="blog-post-content service-page-content">
            {cover.sections.slice(midpoint).map(renderSection)}
          </div>
        </div>
        <FaqAccordion faqSchema={faqSchema} />
        <CoverCards currentSlug={cover.slug} slugs={cover.related} />
        <GuidesList coverSlug={cover.slug} heading={`Guides for ${cover.kind === 'business' ? cover.title.replace(/ Insurance$/, '').toLowerCase() : 'cleaning businesses'}`} />
      </div>
      <FinalCtaBand ctaTo={quoteTo} />
    </div>
  );
};

export default CoverPage;
