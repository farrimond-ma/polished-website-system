import React from 'react';
import { Link } from 'react-router-dom';
import SEO from '../components/SEO';

const NotFound = () => (
  <div className="page-plain" data-page-type="not-found">
    <SEO title="Page not found" description="The page you requested could not be found." noIndex />
    <div className="content" style={{ paddingTop: '9rem', textAlign: 'center' }}>
      <h1>Page not found</h1>
      <p>Sorry, we could not find that page. It may have moved.</p>
      <p>
        <Link to="/" className="btn btn-navy">Home</Link>{' '}
        <Link to="/get-a-quote" className="btn btn-primary" style={{ textDecoration: 'none' }}>Get a quote</Link>
      </p>
    </div>
  </div>
);

export default NotFound;
