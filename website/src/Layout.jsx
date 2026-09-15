import React, { useEffect } from 'react';
import { Outlet, useLocation, Link } from 'react-router-dom';
import Navbar from './components/Navbar';
import Footer from './components/Footer';
import Icon from './components/Icons';
import { captureAttribution } from './lib/attribution';
import { SITE } from './config/site';

// Funnel pages get a minimal navbar and no floating CTA, so nothing pulls the visitor away.
const FUNNEL = ['/insurance-questionnaire', '/get-a-quote/thank-you'];

const Layout = () => {
  const { pathname } = useLocation();
  const isFunnel = FUNNEL.includes(pathname);
  const isQuotePage = pathname.startsWith('/get-a-quote');

  useEffect(() => { captureAttribution(); }, []);
  useEffect(() => { window.scrollTo(0, 0); }, [pathname]);

  return (
    <div className="App">
      <Navbar minimal={isFunnel} />
      <main>
        <Outlet />
      </main>
      <Footer />
      {!isFunnel && !isQuotePage && (
        <div className="mobile-cta-bar">
          <a href={SITE.phoneHref} className="btn btn-outline-dark"><Icon name="phone" size={16} /> Call us</a>
          <Link to="/get-a-quote" className="btn btn-primary">Get a quote</Link>
        </div>
      )}
    </div>
  );
};

export default Layout;
