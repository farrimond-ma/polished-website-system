import React from 'react';
import QuoteForm from './QuoteForm';
import './QuoteSection.css';

// The quote form in its own section straight after the hero (target of the hero's
// "Get your quote" button). Desktop: the hero image beside the form. Phones: a slim image
// banner above the form, so the form is reached with minimal scrolling.
const QuoteSection = ({ image, imageAlt = '', coverInterest = '', heading = 'Get your quote', compact = false }) => (
  <section id="quote" className={`quote-section${compact ? ' is-compact' : ''}`}>
    <div className="quote-section-grid">
      {image && (
        <div className="quote-section-image">
          <img src={image} alt={imageAlt} loading="eager" width="1440" height="1024" />
        </div>
      )}
      <div className="quote-section-form">
        <QuoteForm heading={heading} coverInterest={coverInterest} compact />
      </div>
    </div>
  </section>
);

export default QuoteSection;
