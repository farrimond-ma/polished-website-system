import React from 'react';

// Header (and default): the Polished Insurance logo with the "Part of the Allied Insurance Group"
// line, on a transparent background. Footer: the earlier logo, whose lighter lettering suits the
// navy footer. Width/height match each file so the browser reserves the right space.
const VARIANTS = {
  // Phones and small tablets (menu collapsed, logo 50px tall) get the version without the small
  // "Part of the Allied Insurance Group" line, which is unreadable at that size.
  default: { base: '/images/brand/logo', mobile: '/images/brand/logo-mobile', w: 398, h: 240 },
  footer: { base: '/images/brand/logo-footer', w: 193, h: 120 },
};

const Logo = ({ height = 68, className = '', variant = 'default' }) => {
  const v = VARIANTS[variant] || VARIANTS.default;
  return (
    <picture className={`logo ${className}`.trim()}>
      {v.mobile && <source media="(max-width: 920px)" srcSet={`${v.mobile}.webp`} type="image/webp" />}
      {v.mobile && <source media="(max-width: 920px)" srcSet={`${v.mobile}.png`} type="image/png" />}
      <source srcSet={`${v.base}.webp`} type="image/webp" />
      <img src={`${v.base}.png`} alt="Polished Insurance" width={Math.round((height * v.w) / v.h)} height={height} style={{ height, width: 'auto' }} />
    </picture>
  );
};

export default Logo;
