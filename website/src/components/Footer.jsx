import React from 'react';
import { Link } from 'react-router-dom';
import Logo from './Logo';
import { businessCovers, coverTypes } from '../data/covers';
import { SITE, REGULATORY_LINE } from '../config/site';
import './Footer.css';

const Footer = () => (
  <footer className="footer">
    <div className="container">
      <div className="footer-content">
        <div className="footer-brand">
          <Link to="/" className="footer-logo" aria-label="Polished Insurance home"><Logo light /></Link>
          <p>Specialist insurance for UK cleaning businesses, arranged by a team that understands your trade.</p>
          <p className="footer-contact-line">
            <a href={SITE.phoneHref}>{SITE.phoneDisplay}</a><br />
            <a href={`mailto:${SITE.email}`}>{SITE.email}</a><br />
            <span>{SITE.hours}</span>
          </p>
        </div>

        <div className="footer-links">
          <h4>Cleaning businesses</h4>
          <ul>
            {businessCovers.map((c) => (
              <li key={c.slug}><Link to={`/cleaning-insurance/${c.slug}`}>{c.title}</Link></li>
            ))}
          </ul>
        </div>

        <div className="footer-links">
          <h4>Covers</h4>
          <ul>
            {coverTypes.map((c) => (
              <li key={c.slug}><Link to={`/cleaning-insurance/${c.slug}`}>{c.title.replace(/ (for|&) Clean.*$/, '')}</Link></li>
            ))}
          </ul>
          <h4 className="footer-h4-gap">Help</h4>
          <ul>
            <li><Link to="/get-a-quote">Get a quote</Link></li>
            <li><Link to="/guides">Insurance guides</Link></li>
            <li><Link to="/about-us">About us</Link></li>
          </ul>
        </div>

        <div className="footer-links">
          <h4>Legal</h4>
          <ul>
            <li><Link to="/privacy-policy">Privacy policy</Link></li>
            <li><Link to="/terms-of-business">Terms of business</Link></li>
            <li><Link to="/complaints">Complaints</Link></li>
            <li><Link to="/cookie-policy">Cookie policy</Link></li>
          </ul>
        </div>
      </div>

      <div className="footer-bottom">
        <p className="footer-disclaimer">{REGULATORY_LINE}</p>
        <p className="footer-disclaimer">
          The information on this website is general guidance about insurance for cleaning businesses and is not personal advice.
          Cover is subject to the terms, conditions and exclusions of the policy, and to insurer acceptance.
        </p>
        <p>&copy; {new Date().getFullYear()} {SITE.name}. All rights reserved.</p>
      </div>
    </div>
  </footer>
);

export default Footer;
