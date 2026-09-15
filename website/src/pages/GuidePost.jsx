import React, { useEffect, useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import SEO from '../components/SEO';
import ArticleBody, { EndCta, quoteLinkFor } from '../components/ArticleCtas';
import { ResourceHero, FinalCtaBand } from '../components/resource/ResourceHero';
import TableOfContents from '../components/resource/TableOfContents';
import FaqAccordion from '../components/resource/FaqAccordion';
import CoverCards from '../components/resource/CoverCards';
import GuidesList from '../components/resource/GuidesList';
import { postsBySlug, formatDate } from '../data/posts';
import { coversBySlug } from '../data/covers';
import { SITE } from '../config/site';
import NotFound from './NotFound';
import '../components/Article.css';

const CONTENT_ID = 'guide-article-body';

const GuidePost = () => {
  const { slug } = useParams();
  const post = postsBySlug[String(slug || '').toLowerCase()];
  const [full, setFull] = useState(null);

  useEffect(() => {
    if (!post) return undefined;
    let cancelled = false;
    setFull(null);
    fetch(`/content/guides/${encodeURIComponent(post.slug)}.json`)
      .then((r) => (r.ok ? r.json() : null))
      .then((d) => { if (!cancelled) setFull(d); })
      .catch(() => {});
    return () => { cancelled = true; };
  }, [post?.slug]);

  if (!post) return <NotFound />;

  const url = `${SITE.url}/guides/${post.slug}`;
  const image = post.heroImage ? `${SITE.url}${post.heroImage}` : `${SITE.url}/og-image.png`;
  const faqSchema = full?.faqs?.length ? {
    '@context': 'https://schema.org',
    '@type': 'FAQPage',
    mainEntity: full.faqs.map((f) => ({ '@type': 'Question', name: f.q, acceptedAnswer: { '@type': 'Answer', text: f.a } })),
  } : null;
  const articleSchema = {
    '@context': 'https://schema.org',
    '@type': 'Article',
    headline: post.metaTitle || post.title,
    description: post.metaDescription || post.excerpt,
    image,
    datePublished: post.publishedAt || post.date,
    dateModified: post.updatedAt || post.publishedAt || post.date,
    author: { '@type': 'Organization', name: post.author || `${SITE.name} team`, url: SITE.url },
    publisher: { '@type': 'Organization', name: SITE.name, logo: { '@type': 'ImageObject', url: `${SITE.url}/images/brand/logo.png` } },
    mainEntityOfPage: { '@type': 'WebPage', '@id': url },
  };
  const cover = coversBySlug[post.coverSlug];

  return (
    <div className="resource-page">
      <SEO
        title={post.metaTitle || post.title}
        description={post.metaDescription || post.excerpt}
        keywords={post.keywords}
        type="article"
        canonical={`/guides/${post.slug}`}
        image={post.heroImage}
        schema={faqSchema ? [articleSchema, faqSchema] : articleSchema}
      />
      <ResourceHero title={post.title} description={post.metaDescription || post.excerpt} heroImage={post.heroImage} primaryCtaTo={quoteLinkFor(post.coverSlug)} />
      <div className="resource-column">
        <nav className="breadcrumbs" aria-label="Breadcrumb">
          <Link to="/">Home</Link> <span>/</span> <Link to="/guides">Guides</Link>
          {cover && <> <span>/</span> <Link to={`/cleaning-insurance/${cover.slug}`}>{cover.title}</Link></>}
        </nav>
        <div className="resource-main-card">
          <p className="resource-date">
            {post.updatedAt ? 'Last updated ' : 'Published '}
            <time dateTime={post.updatedAt || post.publishedAt || post.date}>{formatDate(post.updatedAt || post.publishedAt || post.date, true)}</time>
            {post.readingMinutes ? ` · ${post.readingMinutes} min read` : ''}
          </p>
          <TableOfContents containerId={CONTENT_ID} ready={!!full} />
          {full ? (
            <div id={CONTENT_ID} className="blog-post-content">
              <ArticleBody html={full.content} coverSlug={post.coverSlug} />
            </div>
          ) : (
            <div className="resource-skeleton" aria-busy="true" />
          )}
          {post.heroCredit && <p className="resource-date">Photo: {post.heroCredit}</p>}
        </div>
        {full && (
          <>
            <FaqAccordion faqSchema={faqSchema} />
            <CoverCards currentSlug={post.coverSlug} slugs={cover ? [cover.slug, ...cover.related] : undefined} heading="Related cover" />
            <GuidesList coverSlug={post.coverSlug} currentSlug={post.slug} relatedSlugs={post.relatedSlugs || []} />
            <EndCta coverSlug={post.coverSlug} />
            <p className="guide-disclaimer">
              This guide is general information about insurance for cleaning businesses and is not personal advice. Policy cover,
              limits and exclusions vary between insurers, so always check your own policy documents.
            </p>
          </>
        )}
      </div>
      <FinalCtaBand ctaTo={quoteLinkFor(post.coverSlug)} />
    </div>
  );
};

export default GuidePost;
