// Structured data helpers (JSON-LD) shared by pages.
import { SITE } from '../config/site';

export const absolute = (path) => (path.startsWith('http') ? path : `${SITE.url}${path === '/' ? '' : path}`);

/** BreadcrumbList from [['Home','/'], ['Guides','/guides'], ['Title','/guides/x']] */
export const breadcrumbSchema = (items) => ({
  '@context': 'https://schema.org',
  '@type': 'BreadcrumbList',
  itemListElement: items.map(([name, path], i) => ({ '@type': 'ListItem', position: i + 1, name, item: absolute(path) })),
});

export const webPageSchema = (type, name, description, path) => ({
  '@context': 'https://schema.org',
  '@type': type,
  name,
  description,
  url: absolute(path),
  isPartOf: { '@type': 'WebSite', name: SITE.name, url: SITE.url },
  publisher: { '@type': 'InsuranceAgency', name: SITE.name, url: SITE.url },
});
