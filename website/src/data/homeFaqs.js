// One source for the visible home page FAQ and its FAQPage JSON-LD, so they never disagree.
export const homeFaqs = [
  {
    q: 'What insurance does a cleaning business need?',
    a: 'Most cleaning businesses start with public liability insurance, which covers injury to other people and damage to their property. If you employ anyone, employers\' liability insurance is usually a legal requirement. Many cleaners also add loss of keys cover, cover for damage to the property they are working on, and tools and equipment insurance.',
  },
  {
    q: 'Is insurance a legal requirement for cleaners in the UK?',
    a: 'Employers\' liability insurance is compulsory for most businesses with employees, with a minimum limit of £5 million. Public liability insurance is not a legal requirement, but most commercial clients, letting agents and many domestic customers expect you to have it.',
  },
  {
    q: 'How does getting a quote work?',
    a: 'You send us your contact details, and we email and text you a link to a short online questionnaire about your business. It only shows the questions that apply to you and saves as you go. Once it is complete, we approach insurers and come back to you with options.',
  },
  {
    q: 'How much does cleaning business insurance cost?',
    a: 'It depends on your turnover, number of staff, the type of cleaning you do, where you work, the limits you need and your claims history. Working at height or in high-risk locations can increase the price, which is why we ask about these in the questionnaire.',
  },
  {
    q: 'Can you insure new cleaning businesses?',
    a: 'Yes. Insurers do cover new cleaning businesses. They will usually ask about your experience in the industry and your expected turnover for the first year.',
  },
];

export const homeFaqSchema = {
  '@context': 'https://schema.org',
  '@type': 'FAQPage',
  mainEntity: homeFaqs.map((f) => ({ '@type': 'Question', name: f.q, acceptedAnswer: { '@type': 'Answer', text: f.a } })),
};
