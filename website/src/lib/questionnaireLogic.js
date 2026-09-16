// Questionnaire visibility and validation rules.
// Mirrors q_condition_met() in the CRM (crm/inc/questionnaire.php) and crm/assets/questionnaire.js —
// if you add a new condition type, add it in all three places.

const num = (v) => {
  const n = parseFloat(v);
  return Number.isNaN(n) ? 0 : n;
};

export function conditionMet(cond, data) {
  if (!cond) return true;
  if (cond.all) return cond.all.every((c) => conditionMet(c, data));
  if (cond.any) return cond.any.some((c) => conditionMet(c, data));
  if (cond.field) return cond.in ? cond.in.includes(data[cond.field]) : data[cond.field] === cond.equals;
  if (cond.anyYes) return cond.anyYes.some((id) => data[id] === 'yes');
  if (cond.fieldGt0) return num(data[cond.fieldGt0]) > 0;
  if (cond.anyGt0) return cond.anyGt0.some((id) => num(data[id]) > 0);
  return true;
}

export const isInput = (item) => item.type && item.type !== 'heading' && item.type !== 'note';

export const isEmpty = (v) => v === undefined || v === null || v === '' || (Array.isArray(v) && v.length === 0);

export const groupTotal = (item, data) => Math.round(item.fields.reduce((s, f) => s + num(data[f.id]), 0) * 100) / 100;

/** Problems on one section: [{id, message}] for visible required fields and percent totals. */
export function sectionProblems(section, data) {
  const problems = [];
  for (const item of section.items) {
    if (!isInput(item) || !conditionMet(item.showIf, data)) continue;
    if (item.type === 'percent_group') {
      if (item.mustTotal) {
        const total = groupTotal(item, data);
        if (total !== item.mustTotal) problems.push({ id: item.id, message: `This must add up to ${item.mustTotal}% (currently ${total}%).` });
      }
      continue;
    }
    // "required" always; "requiredIf" only when its condition is met.
    if (item.required || (item.requiredIf && conditionMet(item.requiredIf, data))) {
      const v = data[item.id];
      const emptyTable = item.type === 'table' && (!Array.isArray(v) || !v.some((r) => Object.values(r || {}).some((x) => !isEmpty(x))));
      if (isEmpty(v) || emptyTable) problems.push({ id: item.id, message: 'Please answer this question.' });
    }
    if (item.type === 'email' && !isEmpty(data[item.id]) && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(String(data[item.id]))) {
      problems.push({ id: item.id, message: 'Please enter a valid email address.' });
    }
    if (item.type === 'percent' && num(data[item.id]) > 100) problems.push({ id: item.id, message: 'Please enter a percentage between 0 and 100.' });
  }
  return problems;
}

/**
 * Schema defaults for fields with no answer yet (the CRM also applies these server-side).
 * A percent group (e.g. UK/EEA 100%) only takes its defaults while every field in it is empty.
 */
export function withDefaults(schema, data) {
  const out = { ...data };
  for (const s of schema.sections) for (const it of s.items) {
    if (it.type === 'percent_group') {
      if (it.fields.every((f) => isEmpty(out[f.id]))) {
        for (const f of it.fields) if (f.default !== undefined) out[f.id] = f.default;
      }
    } else if (it.id && it.default !== undefined && isEmpty(out[it.id])) out[it.id] = it.default;
  }
  return out;
}

/** Friendly display of an answer for the review screen. */
export function displayValue(item, v) {
  if (isEmpty(v)) return '';
  switch (item.type) {
    case 'yesno': return v === 'yes' ? 'Yes' : v === 'no' ? 'No' : String(v);
    case 'checkbox': return v ? 'Yes' : '';
    case 'currency': return `£${Number(v).toLocaleString('en-GB')}`;
    case 'percent': return `${v}%`;
    case 'date': { const d = new Date(v); return Number.isNaN(d.getTime()) ? String(v) : d.toLocaleDateString('en-GB'); }
    default: return String(v);
  }
}
