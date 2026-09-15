// Remembers where a visitor first landed and any UTM tags, for the session, so an enquiry
// made several pages later is still attributed to the page/campaign that brought them in.
const KEY = 'polished_attribution';

export function captureAttribution() {
  if (typeof window === 'undefined') return;
  try {
    if (sessionStorage.getItem(KEY)) return;
    const params = new URLSearchParams(window.location.search);
    sessionStorage.setItem(KEY, JSON.stringify({
      landing_page: window.location.pathname,
      utm_source: params.get('utm_source') || '',
      utm_medium: params.get('utm_medium') || '',
      utm_campaign: params.get('utm_campaign') || '',
      referrer: document.referrer ? new URL(document.referrer).hostname : '',
    }));
  } catch { /* storage blocked — attribution is best effort */ }
}

export function getAttribution() {
  try {
    return JSON.parse(sessionStorage.getItem(KEY) || '{}');
  } catch {
    return {};
  }
}
