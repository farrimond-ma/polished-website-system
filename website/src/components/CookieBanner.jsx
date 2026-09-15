import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { getConsent, setConsent, CONSENT_EVENT } from '../lib/consent';
import './CookieBanner.css';

const CookieBanner = () => {
  const [open, setOpen] = useState(false);

  useEffect(() => {
    // Not shown to the prerender crawler (so it isn't baked into static HTML) or once a choice exists.
    if (!navigator.webdriver && !getConsent()) setOpen(true);
    const reopen = () => setOpen(true);
    window.addEventListener(CONSENT_EVENT, reopen);
    return () => window.removeEventListener(CONSENT_EVENT, reopen);
  }, []);

  if (!open) return null;
  const choose = (v) => { setConsent(v); setOpen(false); };

  return (
    <div className="cookie-banner" role="dialog" aria-live="polite" aria-label="Cookie consent">
      <p>
        We use essential storage to run this site. With your permission we would also like to use Meta (Facebook) Pixel cookies to
        measure our adverts. <Link to="/cookie-policy">Cookie policy</Link>
      </p>
      <div className="cookie-actions">
        <button type="button" className="btn btn-outline-dark" onClick={() => choose('rejected')}>Reject</button>
        <button type="button" className="btn btn-primary" onClick={() => choose('accepted')}>Accept</button>
      </div>
    </div>
  );
};

export default CookieBanner;
