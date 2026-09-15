import React from 'react';
import { SITE } from '../config/site';

// React 19 hoists <title>, <meta> and <link> into <head>, so no helmet library is needed
// (same approach as the Boxx site). JSON-LD renders in place, which Google reads anywhere.
const DEFAULT_OG_IMAGE = `${SITE.url}/og-image.png`;

const SEO = ({ title, description, keywords, type, schema, image, canonical, noIndex = false, robots }) => {
  // Google shows roughly 60 characters: add the brand suffix only when the whole title still fits.
  const withBrand = title ? `${title} | ${SITE.name}` : `${SITE.name} | Insurance for Cleaning Businesses`;
  const fullTitle = title && withBrand.length > 60 ? title : withBrand;
  const robotsContent = robots || (noIndex ? 'noindex, nofollow' : '');
  const path = canonical ?? (typeof window !== 'undefined' ? window.location.pathname.replace(/\/$/, '') || '/' : '/');
  const canonicalUrl = path.startsWith('http') ? path : `${SITE.url}${path}`;
  const ogImage = image ? (image.startsWith('http') ? image : `${SITE.url}${image}`) : DEFAULT_OG_IMAGE;
  const keywordsContent = Array.isArray(keywords) ? keywords.join(', ') : keywords;

  return (
    <>
      <title>{fullTitle}</title>
      {description && <meta name="description" content={description} />}
      {robotsContent && <meta name="robots" content={robotsContent} />}
      {keywordsContent && <meta name="keywords" content={keywordsContent} />}
      {!noIndex && <link rel="canonical" href={canonicalUrl} />}

      <meta property="og:type" content={type || 'website'} />
      <meta property="og:title" content={fullTitle} />
      {description && <meta property="og:description" content={description} />}
      <meta property="og:url" content={canonicalUrl} />
      <meta property="og:image" content={ogImage} />
      <meta property="og:site_name" content={SITE.name} />
      <meta property="og:locale" content="en_GB" />
      <meta name="twitter:card" content="summary_large_image" />
      <meta name="twitter:title" content={fullTitle} />
      {description && <meta name="twitter:description" content={description} />}
      <meta name="twitter:image" content={ogImage} />

      {schema && (Array.isArray(schema) ? schema : [schema]).map((s, i) => (
        <script key={i} type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(s) }} />
      ))}
    </>
  );
};

export default SEO;
