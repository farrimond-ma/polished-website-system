import React from 'react';
import QuoteForm from './QuoteForm';
import './QuoteSection.css';

// The full-width quote form (target of the hero's "Get your quote" button, #quote). It sits part-way
// down the page, after the visitor has read how it works (home) or the first half of a cover page.
// On desktop the fields sit side by side; on phones they stack.
const QuoteSection = ({ coverInterest = '', heading = 'Get your quote', compact = false }) => (
  <section id="quote" className={`quote-section${compact ? ' is-compact' : ''}`}>
    <QuoteForm heading={heading} coverInterest={coverInterest} compact wide />
  </section>
);

export default QuoteSection;
