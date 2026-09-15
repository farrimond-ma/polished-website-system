import React, { useRef, useState } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { CRM_URL, INTAKE_KEY, SITE } from '../config/site';
import { getAttribution } from '../lib/attribution';
import { trackLead } from '../lib/consent';
import './QuoteForm.css';

const CONSENT_TEXT =
  'I agree to Polished Insurance contacting me by phone, email and text message about my insurance quote, and I have read the privacy policy.';

// Lead capture: contact details only. Everything else is collected afterwards by the
// questionnaire link the CRM emails and texts to the client.
const QuoteForm = ({ coverInterest = '', heading = 'Get a quote', intro, compact = false }) => {
  const navigate = useNavigate();
  const { pathname } = useLocation();
  const startedAt = useRef(Date.now());
  const [values, setValues] = useState({ first_name: '', last_name: '', company_name: '', email: '', phone: '', consent: false, website: '' });
  const [errors, setErrors] = useState({});
  const [status, setStatus] = useState('idle'); // idle | sending | error
  const [serverError, setServerError] = useState('');

  const set = (e) => {
    const { name, type, checked, value } = e.target;
    setValues((v) => ({ ...v, [name]: type === 'checkbox' ? checked : value }));
    if (errors[name]) setErrors((x) => ({ ...x, [name]: undefined }));
  };

  const validate = () => {
    const x = {};
    if (!values.first_name.trim()) x.first_name = 'Please enter your first name.';
    if (!values.last_name.trim()) x.last_name = 'Please enter your last name.';
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(values.email.trim())) x.email = 'Please enter a valid email address.';
    if (values.phone.replace(/\D/g, '').length < 10) x.phone = 'Please enter a valid UK phone number.';
    if (!values.consent) x.consent = 'Please tick to agree so we can contact you about your quote.';
    return x;
  };

  const submit = async (e) => {
    e.preventDefault();
    const x = validate();
    setErrors(x);
    if (Object.keys(x).length) return;
    setStatus('sending');
    setServerError('');
    try {
      const attribution = getAttribution();
      const res = await fetch(`${CRM_URL}/api/intake.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Polished-Intake-Key': INTAKE_KEY },
        body: JSON.stringify({
          ...values,
          consent_text: CONSENT_TEXT,
          cover_interest: coverInterest,
          landing_page: attribution.landing_page && attribution.landing_page !== pathname
            ? `${attribution.landing_page} → ${pathname}` : pathname,
          utm_source: attribution.utm_source || attribution.referrer || '',
          utm_medium: attribution.utm_medium || '',
          utm_campaign: attribution.utm_campaign || '',
          elapsed_ms: Date.now() - startedAt.current,
        }),
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok || !data.ok) throw new Error(data.error || 'Something went wrong.');
      trackLead({ content_name: coverInterest || 'Cleaning business insurance' }); // no-op unless cookies accepted
      navigate('/get-a-quote/thank-you', { state: { firstName: values.first_name.trim(), reference: data.reference, phone: values.phone } });
    } catch (err) {
      setStatus('error');
      setServerError(err.message && err.message !== 'Failed to fetch'
        ? err.message
        : `We could not send your details. Please try again or call us on ${SITE.phoneDisplay}.`);
    }
  };

  const field = (name, label, props = {}) => (
    <div className={`qf-field${errors[name] ? ' has-error' : ''}`}>
      <label htmlFor={`qf-${name}`}>{label}</label>
      <input id={`qf-${name}`} name={name} value={values[name]} onChange={set} aria-invalid={!!errors[name]} {...props} />
      {errors[name] && <span className="qf-error">{errors[name]}</span>}
    </div>
  );

  return (
    <form className={`quote-form${compact ? ' is-compact' : ''}`} onSubmit={submit} noValidate>
      {heading && <h2 className="qf-heading">{heading}</h2>}
      <p className="qf-intro">{intro || 'Just your contact details for now. We will send you a short questionnaire to complete in your own time.'}</p>
      <div className="qf-row">
        {field('first_name', 'First name', { autoComplete: 'given-name', required: true })}
        {field('last_name', 'Last name', { autoComplete: 'family-name', required: true })}
      </div>
      {field('company_name', 'Business name (optional)', { autoComplete: 'organization' })}
      {field('email', 'Email address', { type: 'email', autoComplete: 'email', inputMode: 'email', required: true })}
      {field('phone', 'Mobile number', { type: 'tel', autoComplete: 'tel', inputMode: 'tel', required: true })}

      {/* Honeypot: hidden from people, filled in by bots. */}
      <div className="qf-hp" aria-hidden="true">
        <label htmlFor="qf-website">Website</label>
        <input id="qf-website" name="website" tabIndex={-1} autoComplete="off" value={values.website} onChange={set} />
      </div>

      <div className={`qf-consent${errors.consent ? ' has-error' : ''}`}>
        <label>
          <input type="checkbox" name="consent" checked={values.consent} onChange={set} />
          <span>
            I agree to Polished Insurance contacting me by phone, email and text about my quote, and I have read
            the <Link to="/privacy-policy" target="_blank">privacy policy</Link>.
          </span>
        </label>
        {errors.consent && <span className="qf-error">{errors.consent}</span>}
      </div>

      {serverError && <div className="qf-server-error" role="alert">{serverError}</div>}

      <button type="submit" className="btn btn-primary qf-submit" disabled={status === 'sending'}>
        {status === 'sending' ? 'Sending…' : 'Request my quote'}
      </button>
      <p className="qf-small">No obligation. Your details are handled in line with our privacy policy.</p>
    </form>
  );
};

export default QuoteForm;
