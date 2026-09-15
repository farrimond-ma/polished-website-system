// UK-style title case for H1s and page titles (no imports, so the content engine can use it too).
// Small words stay lower-case unless they start the title or follow a colon/question mark.
// Acronyms and words that already contain capitals or digits (COSHH, UK, 2026) are left as written.
const SMALL = new Set(['a', 'an', 'and', 'as', 'at', 'but', 'by', 'for', 'from', 'in', 'into', 'nor', 'of', 'on', 'or', 'per', 'the', 'to', 'vs', 'via', 'with']);

const capitalise = (part) => part.replace(/^([^A-Za-z]*)([a-z])/, (m, lead, c) => lead + c.toUpperCase());

export function titleCase(input) {
  const words = String(input).trim().split(/\s+/);
  return words.map((word, i) => {
    if (/[A-Z].*[A-Z]|\d/.test(word)) return word;
    const startsPhrase = i === 0 || /[:?!]$/.test(words[i - 1]);
    return word.split('-').map((part, j) => {
      const small = SMALL.has(part.toLowerCase().replace(/[^a-z]/g, ''));
      return small && !(startsPhrase && j === 0) ? part.toLowerCase() : capitalise(part);
    }).join('-');
  }).join(' ');
}
