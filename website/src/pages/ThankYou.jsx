import React from 'react';
import { useLocation, Link } from 'react-router-dom';
import SEO from '../components/SEO';
import Icon from '../components/Icons';
import { SITE } from '../config/site';
import './GetAQuote.css';

const ThankYou = () => {
  const { state } = useLocation();
  const first = state?.firstName;

  return (
    <div className="page-plain">
      <SEO title="Thank You" description="Thanks for your enquiry." noIndex />
      <div className="thanks">
        <div className="thanks-icon"><Icon name="check" size={36} strokeWidth={3} /></div>
        <h1>Thank You{first ? `, ${first}` : ''}</h1>
        <p>We have received your details{state?.reference ? <> (reference <strong>{state.reference}</strong>)</> : null}.</p>
        <div className="notice">
          <strong>What happens next</strong>
          <ul>
            <li>A member of our team will review your enquiry and send you a secure link to your insurance questionnaire by email and text, usually within one working day.</li>
            <li>It usually takes 10 to 15 minutes. Your answers save as you go, so you can finish it later using the same link.</li>
            <li>When it arrives, please check your junk or spam folder if you cannot see the email.</li>
          </ul>
        </div>
        <p>Questions? Call us on <a href={SITE.phoneHref}>{SITE.phoneDisplay}</a>.</p>
        <p><Link to="/guides" className="btn btn-navy">Read our insurance guides</Link></p>
      </div>
    </div>
  );
};

export default ThankYou;
