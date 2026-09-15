import React from 'react';

// The Polished Insurance logo carried over from the previous site (transparent PNG, trimmed and
// resized by the image pipeline). Works on the white header and the navy footer.
const Logo = ({ height = 68, className = '' }) => (
  <picture className={`logo ${className}`.trim()}>
    <source srcSet="/images/brand/logo.webp" type="image/webp" />
    <img src="/images/brand/logo.png" alt="Polished Insurance" width={Math.round((height * 193) / 120)} height={height} style={{ height, width: 'auto' }} />
  </picture>
);

export default Logo;
