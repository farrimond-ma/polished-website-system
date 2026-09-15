import React, { useEffect, useState } from 'react';
import { Link, NavLink, useLocation } from 'react-router-dom';
import Logo from './Logo';
import Icon from './Icons';
import { SITE } from '../config/site';
import './Navbar.css';

// White fixed navbar with logo, links and a phone CTA — same pattern as the Boxx site.
const Navbar = ({ minimal = false }) => {
  const [open, setOpen] = useState(false);
  const { pathname } = useLocation();
  useEffect(() => setOpen(false), [pathname]);

  return (
    <nav className="navbar">
      <div className="navbar-container container">
        <Link to="/" className="navbar-logo" aria-label="Polished Insurance home">
          <Logo />
        </Link>
        <div className={`navbar-menu${open ? ' active' : ''}${minimal ? ' navbar-menu-minimal' : ''}`}>
          {!minimal && (
            <>
              <NavLink to="/cleaning-insurance" className="navbar-link">Insurance</NavLink>
              <NavLink to="/guides" className="navbar-link">Guides</NavLink>
              <NavLink to="/about-us" className="navbar-link">About Us</NavLink>
              <a href={SITE.phoneHref} className="navbar-link navbar-phone">
                <Icon name="phone" size={16} /> {SITE.phoneDisplay}
              </a>
              <Link to="/get-a-quote" className="btn btn-quote navbar-btn">Get a quote</Link>
            </>
          )}
          {minimal && (
            <a href={SITE.phoneHref} className="btn btn-primary navbar-btn">
              <Icon name="phone" size={16} /> {SITE.phoneDisplay}
            </a>
          )}
        </div>
        {!minimal && (
          <button type="button" className="navbar-toggle" aria-label="Menu" aria-expanded={open} onClick={() => setOpen(!open)}>
            <span className="bar" /><span className="bar" /><span className="bar" />
          </button>
        )}
      </div>
    </nav>
  );
};

export default Navbar;
