import React, { useEffect } from 'react';
import { Outlet, useLocation, Link } from 'react-router-dom';
import Navbar from './components/Navbar';
import Footer from './components/Footer';
import Icon from './components/Icons';
import CookieBanner from './components/CookieBanner';
import { captureAttribution } from './lib/attribution';
import { initConsent, trackPageView } from './lib/consent';
import { SITE } from './config/site';

// Funnel pages get a minimal navbar and no floating CTA, so nothing pulls the visitor away.
const FUNNEL = ['/insurance-questionnaire', '/get-a-quote/thank-you'];

const Layout = () => {
  const { pathname } = useLocation();
  const isFunnel = FUNNEL.includes(pathname);
  const isQuotePage = pathname.startsWith('/get-a-quote');

  const firstRender = React.useRef(true);
  useEffect(() => { captureAttribution(); initConsent(); }, []);
  useEffect(() => {
    window.scrollTo(0, 0);
    // The initial PageView is sent when the Pixel loads; later route changes are tracked here.
    if (firstRender.current) firstRender.current = false; else trackPageView();
  }, [pathname]);

  return (
    <div className="App">
      <Navbar minimal={isFunnel} />
      <main>
        <Outlet />
      </main>
      <Footer />
      <CookieBanner />
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
