import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useSearchParams, Link } from 'react-router-dom';
import SEO from '../components/SEO';
import Icon from '../components/Icons';
import { CRM_URL, SITE } from '../config/site';
import { conditionMet, isInput, sectionProblems, withDefaults, groupTotal, displayValue, isEmpty } from '../lib/questionnaireLogic';
import './Questionnaire.css';

// Client questionnaire: /insurance-questionnaire?t=<token>
// The link is emailed/texted by the CRM. Schema, saved answers and pre-filled contact details
// come from the CRM API; answers autosave back to it. Only questions that apply are shown.

const api = (token, action) => `${CRM_URL}/api/questionnaire.php?t=${encodeURIComponent(token)}${action ? `&action=${action}` : ''}`;

/* ───────────────────────────── field controls ───────────────────────────── */

const TextInput = ({ item, value, onChange, prefilled, invalid }) => {
  const type = { email: 'email', date: 'date', number: 'number' }[item.type] || 'text';
  return (
    <input
      id={`q-${item.id}`}
      type={type}
      inputMode={item.type === 'number' ? 'numeric' : undefined}
      min={item.type === 'number' ? 0 : undefined}
      className={prefilled ? 'is-prefilled' : undefined}
      value={value ?? ''}
      aria-invalid={invalid}
      onChange={(e) => onChange(item.id, e.target.value)}
    />
  );
};

const Adorned = ({ item, value, onChange, symbol, pct, invalid }) => (
  <span className={`q-adorned${pct ? ' is-pct' : ''}`}>
    <span className="q-sym">{symbol}</span>
    <input
      id={`q-${item.id}`}
      type="number"
      inputMode="decimal"
      min="0"
      max={pct ? 100 : undefined}
      step="any"
      value={value ?? ''}
      aria-invalid={invalid}
      onChange={(e) => onChange(item.id, e.target.value)}
    />
  </span>
);

const YesNo = ({ item, value, onChange }) => (
  <div className="q-yesno" role="radiogroup" aria-labelledby={`q-label-${item.id}`} id={`q-${item.id}`}>
    {['yes', 'no'].map((opt) => (
      <button
        key={opt}
        type="button"
        role="radio"
        aria-checked={value === opt}
        className={value === opt ? `is-${opt}` : ''}
        onClick={() => onChange(item.id, value === opt ? '' : opt)}
      >
        {opt === 'yes' ? 'Yes' : 'No'}
      </button>
    ))}
  </div>
);

const TableInput = ({ item, value, onChange }) => {
  const rows = Array.isArray(value) && value.length ? value : (item.fixedRows ? item.fixedRows.map((n) => ({ [item.columns[0].id]: n })) : [{}]);
  const update = (idx, col, v) => {
    const next = rows.map((r, i) => (i === idx ? { ...r, [col]: v } : r));
    onChange(item.id, next);
  };
  return (
    <div className="q-table-wrap" id={`q-${item.id}`}>
      {rows.map((row, idx) => (
        <div className="q-table-row" key={idx}>
          {item.columns.map((c) => (
            <label key={c.id} className="q-table-cell">
              <span>{c.label}</span>
              {c.fixed ? (
                <input type="text" value={row[c.id] ?? ''} readOnly />
              ) : c.type === 'select' ? (
                <select value={row[c.id] ?? ''} onChange={(e) => update(idx, c.id, e.target.value)}>
                  <option value="">Select…</option>
                  {c.options.map((o) => <option key={o}>{o}</option>)}
                </select>
              ) : (
                <input
                  type={c.type === 'date' ? 'date' : c.type === 'number' || c.type === 'currency' ? 'number' : 'text'}
                  min={c.type === 'number' || c.type === 'currency' ? 0 : undefined}
                  value={row[c.id] ?? ''}
                  onChange={(e) => update(idx, c.id, e.target.value)}
                />
              )}
            </label>
          ))}
          {!(item.fixedRows && idx < item.fixedRows.length) && rows.length > 1 && (
            <button type="button" className="q-row-remove" onClick={() => onChange(item.id, rows.filter((_, i) => i !== idx))}>Remove</button>
          )}
        </div>
      ))}
      <button type="button" className="q-add-row" onClick={() => onChange(item.id, [...rows, {}])}>+ {item.addLabel || 'Add row'}</button>
    </div>
  );
};

const PercentGroup = ({ item, data, onChange, invalid }) => {
  const total = groupTotal(item, data);
  const ok = !item.mustTotal || total === item.mustTotal;
  return (
    <fieldset className={`q-pgroup${invalid ? ' is-invalid' : ''}`} id={`q-${item.id}`}>
      <legend>{item.label.replace(/\s*\(must total 100%\)/i, '')}</legend>
      {item.mustTotal && <p className="q-help">Enter a percentage for each that applies. They must add up to {item.mustTotal}%.</p>}
      <div className={`q-pgroup-grid${item.columns === 2 ? ' cols-2' : ''}`}>
        {item.fields.map((f) => (
          <label className="q-pgroup-row" key={f.id}>
            <span>{f.label}</span>
            <span className="q-adorned is-pct">
              <span className="q-sym">%</span>
              <input type="number" inputMode="decimal" min="0" max="100" step="any" value={data[f.id] ?? ''} onChange={(e) => onChange(f.id, e.target.value)} />
            </span>
          </label>
        ))}
      </div>
      <div className={`q-pgroup-total${ok ? ' is-ok' : ' is-bad'}`}>
        Total: {total}%{item.mustTotal ? (ok ? ' ✓' : ` of ${item.mustTotal}%`) : ''}
      </div>
    </fieldset>
  );
};

const Field = ({ item, data, onChange, error, prefilled }) => {
  if (item.type === 'heading') return <h3 className="q-heading">{item.text}</h3>;
  if (item.type === 'note') return <p className="q-note">{item.text}</p>;
  if (item.type === 'percent_group') return <PercentGroup item={item} data={data} onChange={onChange} invalid={!!error} />;

  const value = data[item.id];
  let control;
  switch (item.type) {
    case 'textarea':
      control = <textarea id={`q-${item.id}`} rows={4} value={value ?? ''} aria-invalid={!!error} onChange={(e) => onChange(item.id, e.target.value)} />;
      break;
    case 'select':
      control = (
        <select id={`q-${item.id}`} value={value ?? ''} aria-invalid={!!error} onChange={(e) => onChange(item.id, e.target.value)}>
          <option value="">Please select…</option>
          {item.options.map((o) => <option key={o}>{o}</option>)}
        </select>
      );
      break;
    case 'yesno': control = <YesNo item={item} value={value} onChange={onChange} />; break;
    case 'currency': control = <Adorned item={item} value={value} onChange={onChange} symbol="£" invalid={!!error} />; break;
    case 'percent': control = <Adorned item={item} value={value} onChange={onChange} symbol="%" pct invalid={!!error} />; break;
    case 'table': control = <TableInput item={item} value={value} onChange={onChange} />; break;
    case 'checkbox':
      return (
        <div className={`q-field q-check${error ? ' has-error' : ''}`}>
          <label>
            <input type="checkbox" id={`q-${item.id}`} checked={!!value} onChange={(e) => onChange(item.id, e.target.checked)} />
            <span>{item.label}</span>
          </label>
          {item.help && <p className="q-help">{item.help}</p>}
        </div>
      );
    default:
      control = <TextInput item={item} value={value} onChange={onChange} prefilled={prefilled} invalid={!!error} />;
  }

  return (
    <div className={`q-field${error ? ' has-error' : ''}${item.type === 'yesno' ? ' is-yesno' : ''}`}>
      <label htmlFor={`q-${item.id}`} id={`q-label-${item.id}`} className="q-label">
        {item.label}{item.required && <span className="q-req" aria-label="required"> *</span>}
      </label>
      {item.help && <p className="q-help">{item.help}</p>}
      {control}
      {prefilled && <p className="q-prefilled-note">Pre-filled from your enquiry. Please check it is correct.</p>}
      {error && <p className="q-error" role="alert">{error}</p>}
    </div>
  );
};

/* ───────────────────────────── page ───────────────────────────── */

const Questionnaire = () => {
  const [params] = useSearchParams();
  const token = params.get('t') || '';
  const [state, setState] = useState({ status: token ? 'loading' : 'nolink' });
  const [data, setData] = useState({});
  const [step, setStep] = useState(0); // 0 = welcome, 1..n = sections, n+1 = review
  const [errors, setErrors] = useState({});
  const [saveState, setSaveState] = useState('saved'); // saved | saving | error
  const [declared, setDeclared] = useState(false);
  const [submitError, setSubmitError] = useState(null);
  const dirty = useRef(false);
  const timer = useRef(null);
  const latest = useRef({});
  const topRef = useRef(null);

  // Load
  useEffect(() => {
    if (!token) return;
    fetch(api(token))
      .then(async (r) => ({ ok: r.ok, body: await r.json().catch(() => ({})) }))
      .then(({ ok, body }) => {
        if (!ok || !body.ok) { setState({ status: 'error', message: body.error || 'This link could not be loaded.' }); return; }
        const initial = withDefaults(body.schema, body.data || {});
        latest.current = initial;
        setData(initial);
        setState({ status: body.submitted ? 'submitted' : 'ready', schema: body.schema, reference: body.reference, firstName: body.first_name, prefilled: new Set(body.prefilled || []) });
      })
      .catch(() => setState({ status: 'error', message: `We could not connect. Please check your internet connection and try again, or call us on ${SITE.phoneDisplay}.` }));
  }, [token]);

  // Autosave
  const save = useCallback(async () => {
    clearTimeout(timer.current);
    if (!dirty.current) return true;
    setSaveState('saving');
    try {
      const r = await fetch(api(token, 'save'), { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ data: latest.current }) });
      if (r.status === 409) { setState((s) => ({ ...s, status: 'submitted' })); return false; }
      if (!r.ok) throw new Error();
      dirty.current = false;
      setSaveState('saved');
      return true;
    } catch {
      setSaveState('error');
      timer.current = setTimeout(save, 5000);
      return false;
    }
  }, [token]);

  useEffect(() => {
    const warn = (e) => { if (dirty.current) { e.preventDefault(); e.returnValue = ''; } };
    window.addEventListener('beforeunload', warn);
    return () => { window.removeEventListener('beforeunload', warn); clearTimeout(timer.current); };
  }, []);

  const onChange = useCallback((id, value) => {
    setData((prev) => {
      const next = { ...prev };
      if (value === '' || value === false || value === null || value === undefined) delete next[id];
      else next[id] = value;
      latest.current = next;
      return next;
    });
    setErrors((e) => (e[id] ? { ...e, [id]: undefined } : e));
    dirty.current = true;
    setSaveState('saving');
    clearTimeout(timer.current);
    timer.current = setTimeout(save, 1200);
  }, [save]);

  const schema = state.schema;
  const sections = useMemo(() => (schema ? schema.sections.filter((s) => conditionMet(s.showIf, data)) : []), [schema, data]);
  const totalSteps = sections.length + 1; // sections + review
  const reviewStep = sections.length + 1;
  const current = step >= 1 && step <= sections.length ? sections[step - 1] : null;

  // If answers hide the section we are on (e.g. claims -> no), keep the step in range.
  useEffect(() => { if (step > reviewStep) setStep(reviewStep); }, [step, reviewStep]);

  const goTo = async (n) => {
    save();
    setStep(n);
    setSubmitError(null);
    requestAnimationFrame(() => topRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
  };

  const next = () => {
    if (current) {
      const problems = sectionProblems(current, data);
      if (problems.length) {
        setErrors(Object.fromEntries(problems.map((p) => [p.id, p.message])));
        const el = document.getElementById(`q-${problems[0].id}`);
        el?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
      }
    }
    setErrors({});
    goTo(step + 1);
  };

  const submit = async () => {
    // Re-check every section before sending.
    for (let i = 0; i < sections.length; i++) {
      const problems = sectionProblems(sections[i], data);
      if (problems.length) {
        setErrors(Object.fromEntries(problems.map((p) => [p.id, p.message])));
        setStep(i + 1);
        setSubmitError(`Please complete “${sections[i].title}” before submitting.`);
        return;
      }
    }
    if (!declared) { setSubmitError('Please tick the declaration to confirm your answers.'); return; }
    setState((s) => ({ ...s, submitting: true }));
    clearTimeout(timer.current);
    try {
      const r = await fetch(api(token, 'submit'), { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ data: latest.current }) });
      const body = await r.json().catch(() => ({}));
      if (r.status === 409 || body.ok) {
        dirty.current = false;
        setState((s) => ({ ...s, status: 'submitted', submitting: false }));
        window.scrollTo({ top: 0 });
        return;
      }
      setSubmitError(body.missing?.length ? `Some questions still need an answer: ${body.missing.slice(0, 3).join('; ')}` : (body.error || 'Something went wrong.'));
    } catch {
      setSubmitError(`We could not submit your questionnaire. Please try again, or call us on ${SITE.phoneDisplay}.`);
    }
    setState((s) => ({ ...s, submitting: false }));
  };

  /* ───── render states ───── */
  const shell = (children) => (
    <div className="q-page">
      <SEO title="Your Insurance Questionnaire" description="Complete your cleaning business insurance questionnaire." noIndex />
      <div className="q-container" ref={topRef}>{children}</div>
    </div>
  );

  if (state.status === 'nolink') {
    return shell(
      <div className="q-card q-center">
        <h1>Your Insurance Questionnaire</h1>
        <p>This page needs the personal link we sent you by email or text message. Please open the link from your message.</p>
        <p>Not received one yet? <Link to="/get-a-quote">Request a quote</Link> or call <a href={SITE.phoneHref}>{SITE.phoneDisplay}</a>.</p>
      </div>,
    );
  }
  if (state.status === 'loading') return shell(<div className="q-card q-center"><div className="q-spinner" aria-label="Loading" /><p>Loading your questionnaire…</p></div>);
  if (state.status === 'error') {
    return shell(
      <div className="q-card q-center">
        <h1>We Could Not Open Your Questionnaire</h1>
        <p>{state.message}</p>
        <p>Call us on <a href={SITE.phoneHref}>{SITE.phoneDisplay}</a> and we will send you a new link.</p>
      </div>,
    );
  }
  if (state.status === 'submitted') {
    return shell(
      <div className="q-card q-center">
        <div className="q-done-icon"><Icon name="check" size={38} strokeWidth={3} /></div>
        <h1>Thank You{state.firstName ? `, ${state.firstName}` : ''}</h1>
        <p>We have received your questionnaire{state.reference ? <> (reference <strong>{state.reference}</strong>)</> : null}. We have also sent you a confirmation email.</p>
        <p>One of our team will review your answers and approach insurers. We will be in touch if we need anything else, or when your quotes are ready.</p>
        <p>Need to change something? Call <a href={SITE.phoneHref}>{SITE.phoneDisplay}</a>.</p>
      </div>,
    );
  }

  const progress = Math.round((Math.min(step, totalSteps) / totalSteps) * 100);

  return shell(
    <>
      <div className="q-topbar">
        <div>
          <p className="q-kicker">Insurance questionnaire{state.reference ? ` · ${state.reference}` : ''}</p>
          <div className="q-progress" aria-label={`Progress ${progress}%`}><div style={{ width: `${progress}%` }} /></div>
        </div>
        <span className={`q-save q-save-${saveState}`}>
          {saveState === 'saving' ? 'Saving…' : saveState === 'error' ? 'Not saved — retrying' : 'All changes saved'}
        </span>
      </div>

      {step === 0 && (
        <div className="q-card">
          <h1>Hi{state.firstName ? ` ${state.firstName}` : ''}, Let&rsquo;s Get Your Cover Sorted</h1>
          <p className="q-lead">These questions help us describe your business accurately to insurers. You will only see the questions that apply to your answers.</p>
          <ul className="q-intro-list">
            <li><Icon name="clipboard" size={20} /> About {sections.length} short sections, usually 4 to 5 minutes</li>
            <li><Icon name="check" size={20} /> Your answers save automatically. Come back any time using the same link</li>
            <li><Icon name="phone" size={20} /> Stuck on a question? Call us on <a href={SITE.phoneHref}>{SITE.phoneDisplay}</a></li>
          </ul>
          <div className="q-callout">
            <strong>Useful to have to hand:</strong> your annual turnover and wages, number of staff, your current policy renewal date, and details of any claims in the last five years.
          </div>
          {schema.intro && <p className="q-small">{schema.intro}</p>}
          <div className="q-actions"><span /><button type="button" className="btn btn-primary" onClick={() => goTo(1)}>Start &rarr;</button></div>
        </div>
      )}

      {current && (
        <div className="q-card" key={current.id}>
          <p className="q-kicker">Section {step} of {sections.length}</p>
          <h2 className="q-section-title">{current.title}</h2>
          {current.description && <p className="q-lead">{current.description}</p>}
          {Object.values(errors).some(Boolean) && <div className="q-error-summary" role="alert">Please check the highlighted questions below.</div>}
          {current.items.filter((it) => conditionMet(it.showIf, data)).map((item, i) => (
            <Field
              key={item.id || `h-${i}`}
              item={item}
              data={data}
              onChange={onChange}
              error={item.id ? errors[item.id] : undefined}
              prefilled={item.id && state.prefilled.has(item.id)}
            />
          ))}
          <div className="q-actions">
            <button type="button" className="btn q-back" onClick={() => goTo(step - 1)}>&larr; Back</button>
            <button type="button" className="btn btn-primary" onClick={next}>{step === sections.length ? 'Review answers →' : 'Save & continue →'}</button>
          </div>
        </div>
      )}

      {step === reviewStep && (
        <div className="q-card">
          <h2 className="q-section-title">Review &amp; submit</h2>
          <p className="q-lead">Please check your answers. You can go back to any section to change them.</p>
          {sections.map((s, i) => (
            <div className="q-review" key={s.id}>
              <div className="q-review-head"><h3>{s.title}</h3><button type="button" onClick={() => goTo(i + 1)}>Edit</button></div>
              <dl>
                {s.items.filter((it) => isInput(it) && conditionMet(it.showIf, data)).map((it) => {
                  if (it.type === 'percent_group') {
                    const parts = it.fields.filter((f) => Number(data[f.id]) > 0).map((f) => `${f.label} ${data[f.id]}%`);
                    return parts.length ? <React.Fragment key={it.id}><dt>{it.label.replace(/\s*\(must total 100%\)/i, '')}</dt><dd>{parts.join(', ')}</dd></React.Fragment> : null;
                  }
                  if (it.type === 'table') {
                    const rows = (Array.isArray(data[it.id]) ? data[it.id] : []).filter((r) => Object.values(r || {}).some((x) => !isEmpty(x)));
                    return rows.length ? <React.Fragment key={it.id}><dt>{it.label}</dt><dd>{rows.map((r, n) => <div key={n}>{it.columns.map((c) => r[c.id]).filter(Boolean).join(' · ')}</div>)}</dd></React.Fragment> : null;
                  }
                  const shown = displayValue(it, data[it.id]);
                  return shown ? <React.Fragment key={it.id}><dt>{it.label}</dt><dd>{shown}</dd></React.Fragment> : null;
                })}
              </dl>
            </div>
          ))}
          <label className="q-declaration">
            <input type="checkbox" checked={declared} onChange={(e) => { setDeclared(e.target.checked); setSubmitError(null); }} />
            <span>
              I confirm that, to the best of my knowledge, the information I have given is true and complete, and that I have disclosed
              everything that could be relevant to insurers. I understand that failing to do so could mean a claim is not paid in full, or at all.
            </span>
          </label>
          {submitError && <div className="q-error-summary" role="alert">{submitError}</div>}
          <div className="q-actions">
            <button type="button" className="btn q-back" onClick={() => goTo(step - 1)}>&larr; Back</button>
            <button type="button" className="btn btn-primary" onClick={submit} disabled={state.submitting}>{state.submitting ? 'Submitting…' : 'Submit questionnaire'}</button>
          </div>
        </div>
      )}
    </>,
  );
};

export default Questionnaire;
