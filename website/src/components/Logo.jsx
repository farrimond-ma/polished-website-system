import React from 'react';

// Wordmark: shield-with-sparkle mark + "Polished Insurance". `light` for dark backgrounds.
const Logo = ({ light = false, size = 38 }) => (
  <span className={`logo${light ? ' logo-light' : ''}`}>
    <svg width={size} height={size} viewBox="0 0 48 48" aria-hidden="true">
      <path d="M24 3 7 9.5v12.2C7 33 14.3 41.6 24 45c9.7-3.4 17-12 17-23.3V9.5z" fill="#0a192f" stroke="#1664f0" strokeWidth="2.5" />
      <path d="M24 13l2.4 6.6L33 22l-6.6 2.4L24 31l-2.4-6.6L15 22l6.6-2.4z" fill="#4d8dff" />
      <path d="M33 29l1 2.6 2.6 1-2.6 1L33 36l-1-2.4-2.6-1 2.6-1z" fill="#bcd6ff" />
    </svg>
    <span className="logo-text">
      <span className="logo-word">Polished</span>
      <span className="logo-sub">Insurance</span>
    </span>
  </span>
);

export default Logo;
