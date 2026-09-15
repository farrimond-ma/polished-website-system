// Cookie consent + Meta (Facebook) Pixel.
// PECR: the Pixel sets advertising cookies, so it is only loaded after the visitor clicks
// "Accept". Rejecting (or not choosing yet) means no Facebook script is requested at all.
import { FB_PIXEL_ID } from '../config/site';

const KEY = 'polished_cookie_consent'; // 'accepted' | 'rejected'
export const CONSENT_EVENT = 'polished:consent-open';

export function getConsent() {
  try { return localStorage.getItem(KEY); } catch { return null; }
}

export function setConsent(value) {
  try { localStorage.setItem(KEY, value); } catch { /* storage blocked: choice lasts for this page only */ }
  if (value === 'accepted') loadPixel();
}

/** Re-open the banner (footer "Cookie settings" link). */
export const openConsent = () => window.dispatchEvent(new Event(CONSENT_EVENT));

let loaded = false;
export function loadPixel() {
  if (loaded || !FB_PIXEL_ID || typeof window === 'undefined') return;
  loaded = true;
  /* eslint-disable */
  !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');
  /* eslint-enable */
  window.fbq('init', FB_PIXEL_ID);
  window.fbq('track', 'PageView');
}

export function trackPageView() {
  if (loaded && window.fbq) window.fbq('track', 'PageView');
}

export function trackLead(params = {}) {
  if (loaded && window.fbq) window.fbq('track', 'Lead', params);
}

/** Called once on app start: returning visitors who accepted get the Pixel straight away. */
export function initConsent() {
  if (getConsent() === 'accepted') loadPixel();
}
