// Smaller copies of site photos made by scripts/responsive-images.js. Only local .webp files have them.
export const imageVariant = (src, width) => (src && src.startsWith('/images/') && src.endsWith('.webp') ? src.replace(/\.webp$/, `-${width}.webp`) : null);

// srcSet for a photo that has an 800px copy (heroes, guide images). Returns undefined when there isn't one.
export const heroSrcSet = (src, fullWidth = 1440) => {
  const small = imageVariant(src, 800);
  return small ? `${small} 800w, ${src} ${fullWidth}w` : undefined;
};
