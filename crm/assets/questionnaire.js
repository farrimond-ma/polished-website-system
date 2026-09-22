/* Dynamic questionnaire renderer — staff view in the Polished CRM.
   Copied from the SSR Questionnaire app (assets/form.js) and extended with all/any/in
   conditions, section-level showIf and the "Extra · client" badge. The client-facing
   version lives on the website (React) and follows the same rules. */
(function () {
  "use strict";

  const schema = window.FORM_SCHEMA;
  const data = window.FORM_DATA || {};
  const mode = window.FORM_MODE;
  const root = document.getElementById("form-root");
  const nav = document.getElementById("progress-nav");
  const saveStatus = document.getElementById("save-status");
  const submitBtn = document.getElementById("submit-btn");
  const validationSummary = document.getElementById("validation-summary");

  const prefilledIds = new Set(
    Object.keys(data).filter((k) => data[k] !== "" && data[k] !== null && data[k] !== undefined)
  );

  // POST headers, including the CSRF token when the page provides one (staff pages).
  function postHeaders() {
    const h = { "Content-Type": "application/json" };
    if (window.CSRF_TOKEN) h["X-CSRF-Token"] = window.CSRF_TOKEN;
    return h;
  }

  // Public form: ids (sections + questions) the admin has switched off.
  const publicHidden = new Set(window.PUBLIC_HIDDEN || []);

  // Sections staff have marked as not needed for this client.
  // Stored inside the case data under a reserved key so it travels with the record.
  const hiddenSections = new Set(data._hidden_sections || []);

  // Single questions staff have decided not to ask THIS client, and the ones switched off for
  // everyone on the Questions page. Neither deletes an answer: staff still see the question here.
  const hiddenItems = new Set(data._hidden_items || []);
  const offForAll = new Set(window.QUESTIONS_OFF_FOR_ALL || []);

  function persistHiddenItems() {
    if (hiddenItems.size) data._hidden_items = Array.from(hiddenItems);
    else delete data._hidden_items;
    scheduleSave();
  }

  function persistHiddenSections() {
    if (hiddenSections.size) data._hidden_sections = Array.from(hiddenSections);
    else delete data._hidden_sections;
    scheduleSave();
  }

  // ---------------- helpers ----------------

  function el(tag, attrs, ...children) {
    const node = document.createElement(tag);
    if (attrs) {
      for (const [k, v] of Object.entries(attrs)) {
        if (k === "class") node.className = v;
        else if (k === "text") node.textContent = v;
        else if (k.startsWith("on")) node.addEventListener(k.slice(2), v);
        else node.setAttribute(k, v);
      }
    }
    for (const c of children) {
      if (c == null) continue;
      node.appendChild(typeof c === "string" ? document.createTextNode(c) : c);
    }
    return node;
  }

  function val(id) {
    return data[id];
  }

  function numVal(id) {
    const v = parseFloat(data[id]);
    return isNaN(v) ? 0 : v;
  }

  // Is a date within the last so many months? Nothing entered counts as no.
  function dateWithinMonths(value, months) {
    if (!value) return false;
    const d = new Date(value);
    if (isNaN(d.getTime())) return false;
    const cutoff = new Date();
    cutoff.setHours(0, 0, 0, 0);
    cutoff.setMonth(cutoff.getMonth() - months);
    return d >= cutoff;
  }

  // Mirrors q_condition_met() in inc/questionnaire.php and the website's React questionnaire.
  function conditionMet(cond) {
    if (!cond) return true;
    if (cond.all) return cond.all.every(conditionMet);
    if (cond.any) return cond.any.some(conditionMet);
    if (cond.field) {
      if (cond.withinMonths) return dateWithinMonths(val(cond.field), cond.withinMonths);
      return cond.in ? cond.in.includes(val(cond.field)) : val(cond.field) === cond.equals;
    }
    if (cond.anyYes) return cond.anyYes.some((id) => val(id) === "yes");
    if (cond.fieldGt0) return numVal(cond.fieldGt0) > 0;
    if (cond.anyGt0) return cond.anyGt0.some((id) => numVal(id) > 0);
    return true;
  }

  function isVisible(item) {
    if (mode !== "staff" && (hiddenItems.has(item.id) || offForAll.has(item.id))) return false;
    return conditionMet(item.showIf);
  }

  // ---------------- save (debounced autosave) ----------------

  let saveTimer = null;
  let dirty = false;

  function setSaveStatus(text, cls) {
    saveStatus.textContent = text;
    saveStatus.className = "save-status" + (cls ? " " + cls : "");
  }

  function scheduleSave() {
    if (mode === "public") return;   // public form has no autosave (single submit)
    dirty = true;
    setSaveStatus("Saving…", "saving");
    clearTimeout(saveTimer);
    saveTimer = setTimeout(doSave, 1200);
  }

  async function doSave() {
    try {
      const res = await fetch(window.SAVE_URL, {
        method: "POST",
        headers: postHeaders(),
        body: JSON.stringify({ data: data }),
      });
      if (!res.ok) throw new Error("HTTP " + res.status);
      dirty = false;
      setSaveStatus("All changes saved");
    } catch (e) {
      setSaveStatus("Could not save — check your connection. Your latest changes are NOT saved yet.", "error");
      clearTimeout(saveTimer);
      saveTimer = setTimeout(doSave, 4000); // retry
    }
  }

  window.addEventListener("beforeunload", (e) => {
    if (dirty) {
      e.preventDefault();
      e.returnValue = "";
    }
  });

  // ---------------- change handling ----------------

  function onFieldChange(id, value) {
    if (value === "" || value === null || value === undefined || value === false) delete data[id];
    else data[id] = value;
    refreshVisibility();
    refreshTotals();
    refreshNav();
    updatePager();
    scheduleSave();
  }

  // ---------------- field renderers ----------------

  function inputClass(id) {
    return mode === "client" && prefilledIds.has(id) ? "prefilled" : "";
  }

  function renderBasicInput(item) {
    const type = { text: "text", email: "email", date: "date", number: "number" }[item.type] || "text";
    const input = el("input", {
      type,
      id: "f_" + item.id,
      class: inputClass(item.id),
      value: val(item.id) ?? "",
      oninput: (e) => onFieldChange(item.id, e.target.value),
    });
    if (type === "number") input.setAttribute("min", "0");
    return input;
  }

  function renderAdorned(item, symbol, isPct) {
    const input = el("input", {
      type: "number",
      id: "f_" + item.id,
      class: inputClass(item.id),
      min: "0",
      step: "any",
      value: val(item.id) ?? "",
      oninput: (e) => onFieldChange(item.id, e.target.value),
    });
    if (isPct) input.setAttribute("max", "100");
    return el("span", { class: "adorned" + (isPct ? " pct" : "") },
      el("span", { class: "sym", text: symbol }), input);
  }

  function renderTextarea(item) {
    return el("textarea", {
      id: "f_" + item.id,
      class: inputClass(item.id),
      oninput: (e) => onFieldChange(item.id, e.target.value),
    }, val(item.id) ?? "");
  }

  function renderSelect(item) {
    const sel = el("select", {
      id: "f_" + item.id,
      class: inputClass(item.id),
      onchange: (e) => onFieldChange(item.id, e.target.value),
    });
    sel.appendChild(el("option", { value: "", text: "— please select —" }));
    for (const opt of item.options) {
      const o = el("option", { value: opt, text: opt });
      if (val(item.id) === opt) o.selected = true;
      sel.appendChild(o);
    }
    return sel;
  }

  function renderYesNo(item) {
    const wrap = el("div", { class: "yesno", id: "f_" + item.id });
    const mk = (value, label) => {
      const b = el("button", { type: "button", text: label });
      const paint = () => {
        b.className = val(item.id) === value ? (value === "yes" ? "sel-yes" : "sel-no") : "";
      };
      paint();
      b.addEventListener("click", () => {
        onFieldChange(item.id, val(item.id) === value ? "" : value);
        wrap.querySelectorAll("button").forEach((btn) => btn.dispatchEvent(new Event("paint")));
      });
      b.addEventListener("paint", paint);
      return b;
    };
    wrap.appendChild(mk("yes", "Yes"));
    wrap.appendChild(mk("no", "No"));
    return wrap;
  }

  function renderCheckbox(item) {
    const cb = el("input", {
      type: "checkbox",
      id: "f_" + item.id,
      onchange: (e) => onFieldChange(item.id, e.target.checked),
    });
    if (val(item.id)) cb.checked = true;
    return el("label", { class: "checkline" }, cb, el("span", { text: item.label }));
  }

  // ---------------- tables ----------------

  function renderTable(item) {
    const wrap = el("div", { id: "f_" + item.id });
    if (!Array.isArray(data[item.id])) {
      data[item.id] = [];
    }
    const rows = data[item.id];

    // Seed fixed rows (e.g. Business Interruption items) once
    if (item.fixedRows && rows.length === 0) {
      for (const name of item.fixedRows) {
        const r = {};
        r[item.columns[0].id] = name;
        rows.push(r);
      }
    }
    if (!item.fixedRows && rows.length === 0) rows.push({});

    // Columns flagged client:false (e.g. directors' date of birth) are only shown to staff.
    const columns = mode === "staff" ? item.columns : item.columns.filter((c) => c.client !== false);

    function rebuild() {
      wrap.innerHTML = "";
      const table = el("table", { class: "dyn-table" });
      const thead = el("thead");
      const htr = el("tr");
      for (const c of columns) htr.appendChild(el("th", { text: c.label + (mode === "staff" && c.client === false ? " (staff only)" : "") }));
      htr.appendChild(el("th", { class: "rowdel" }));
      thead.appendChild(htr);
      table.appendChild(thead);
      const tbody = el("tbody");

      rows.forEach((row, idx) => {
        const tr = el("tr");
        for (const c of columns) {
          let cell;
          const setter = (v) => {
            if (v === "") delete row[c.id]; else row[c.id] = v;
            scheduleSave();
          };
          if (c.fixed) {
            cell = el("input", { type: "text", value: row[c.id] ?? "", readonly: "readonly" });
          } else if (c.type === "select") {
            cell = el("select", { onchange: (e) => setter(e.target.value) });
            cell.appendChild(el("option", { value: "", text: "—" }));
            for (const opt of c.options) {
              const o = el("option", { value: opt, text: opt });
              if (row[c.id] === opt) o.selected = true;
              cell.appendChild(o);
            }
          } else if (c.type === "currency") {
            const inp = el("input", {
              type: "number", min: "0", step: "any", value: row[c.id] ?? "",
              oninput: (e) => setter(e.target.value),
            });
            cell = el("span", { class: "adorned" }, el("span", { class: "sym", text: "£" }), inp);
          } else {
            cell = el("input", {
              type: c.type === "date" ? "date" : c.type === "number" ? "number" : "text",
              value: row[c.id] ?? "",
              oninput: (e) => setter(e.target.value),
            });
          }
          tr.appendChild(el("td", null, cell));
        }
        const delTd = el("td", { class: "rowdel" });
        if (!(item.fixedRows && idx < item.fixedRows.length)) {
          delTd.appendChild(el("button", {
            type: "button", title: "Remove row", text: "✕",
            onclick: () => { rows.splice(idx, 1); rebuild(); scheduleSave(); },
          }));
        }
        tr.appendChild(delTd);
        tbody.appendChild(tr);
      });

      table.appendChild(tbody);
      wrap.appendChild(table);
      wrap.appendChild(el("button", {
        type: "button", class: "btn small addrow", text: "+ " + (item.addLabel || "Add row"),
        onclick: () => { rows.push({}); rebuild(); scheduleSave(); },
      }));
    }

    rebuild();
    return wrap;
  }

  // ---------------- percent groups ----------------

  const totalUpdaters = [];

  function renderPercentGroup(item) {
    const wrap = el("div", { class: "pgroup", id: "f_" + item.id });
    const title = el("div", { class: "pgroup-title", text: item.label + " " });
    const gbadge = makeBadge(item);
    if (gbadge) title.appendChild(gbadge);
    wrap.appendChild(title);
    const grid = el("div", { class: "pgroup-grid" + (item.columns === 2 ? " cols2" : "") });

    for (const f of item.fields) {
      const inp = el("input", {
        type: "number", min: "0", max: "100", step: "any",
        id: "f_" + f.id,
        class: inputClass(f.id),
        value: val(f.id) ?? "",
        oninput: (e) => onFieldChange(f.id, e.target.value),
      });
      grid.appendChild(el("div", { class: "pgroup-row" },
        el("label", { for: "f_" + f.id, text: f.label }),
        el("span", { class: "adorned pct" }, el("span", { class: "sym", text: "%" }), inp)
      ));
    }
    wrap.appendChild(grid);

    const totalLine = el("div", { class: "pgroup-total" });
    wrap.appendChild(totalLine);

    function updateTotal() {
      const total = item.fields.reduce((s, f) => s + numVal(f.id), 0);
      const rounded = Math.round(total * 100) / 100;
      if (item.mustTotal) {
        const ok = rounded === item.mustTotal;
        totalLine.textContent = "Total: " + rounded + "% (must equal " + item.mustTotal + "%)";
        totalLine.className = "pgroup-total " + (ok ? "ok" : "bad");
      } else {
        totalLine.textContent = "Total: " + rounded + "%";
        totalLine.className = "pgroup-total";
      }
    }
    updateTotal();
    totalUpdaters.push(updateTotal);
    return wrap;
  }

  function refreshTotals() {
    totalUpdaters.forEach((fn) => fn());
  }

  // ---------------- build sections ----------------

  const conditionalItems = []; // {node, item}

  // Staff-only badge showing whether Acturis captures this question
  /**
   * "Asked / Not asked" beside a question, in staff view only. Switching it off means this client
   * is not asked it; the question and any answer stay here for staff.
   */
  function makeSkipToggle(item, field) {
    if (mode !== "staff" || item.client === false) return null;   // staff-only questions already are
    if (offForAll.has(item.id)) {
      field.classList.add("section-off");
      return el("span", { class: "qbadge qbadge-extra", title: "Switched off for every client on the Questions page", text: "Not asked (all clients)" });
    }
    const btn = el("button", { type: "button", class: "btn small ghost toggle-hide" });
    const paint = () => {
      const off = hiddenItems.has(item.id);
      btn.textContent = off ? "Not asked \u2014 click to ask" : "Asked \u2014 click to skip";
      btn.classList.toggle("is-off", off);
      field.classList.toggle("section-off", off);
    };
    btn.addEventListener("click", () => {
      if (hiddenItems.has(item.id)) hiddenItems.delete(item.id);
      else hiddenItems.add(item.id);
      persistHiddenItems();
      paint();
    });
    paint();
    return btn;
  }

  function makeBadge(item) {
    if (mode !== "staff") return null;
    if (item.client === false) {
      return el("span", { class: "qbadge qbadge-extra", title: "Staff only — the client never sees this" + (item.acturis !== false ? " (captured by Acturis)" : ""), text: item.acturis !== false ? "Acturis · staff only" : "Staff only" });
    }
    if (item.acturis !== false) {
      return el("span", { class: "qbadge qbadge-acturis", title: "Captured by Acturis — the client is asked this", text: "Acturis" });
    }
    if (item.client === true) {
      return el("span", { class: "qbadge qbadge-client", title: "Extra question the client is also asked", text: "Extra · client" });
    }
    return el("span", { class: "qbadge qbadge-extra", title: "Staff only — the client never sees this", text: "Staff only" });
  }

  // "Extra" = a question Acturis doesn't capture (acturis:false). These are hidden
  // from the client entirely, and greyed (but still editable) for staff.
  function isExtra(item) { return item.acturis === false; }
  // Hidden from the client invite form: anything flagged client:false, or an Extra (non-Acturis)
  // question unless it's been explicitly marked to show on the client form (item.client === true).
  // Mirrors q_hidden_from_client() in inc/questionnaire.php.
  function hideFromClient(item) { return item.client === false || (isExtra(item) && item.client !== true); }
  function isInput(item) { const t = item.type; return t && t !== "heading" && t !== "note"; }
  function sectionHasClientItems(section) {
    return section.items.some((it) => isInput(it) && !hideFromClient(it));
  }
  // Public form: a question/section is hidden if the admin unticked it.
  function isPublicHidden(item) { return item.id && publicHidden.has(item.id); }
  function sectionHasPublicItems(section) {
    if (publicHidden.has(section.id)) return false;
    return section.items.some((it) => isInput(it) && !isPublicHidden(it));
  }
  // Drop headings that would be left with no question under them once Extra items are removed.
  function cleanupHeadings(items) {
    const keep = [];
    for (let i = 0; i < items.length; i++) {
      const it = items[i];
      if (it.type === "heading") {
        let hasInput = false;
        for (let j = i + 1; j < items.length; j++) {
          if (items[j].type === "heading") break;
          if (isInput(items[j])) { hasInput = true; break; }
        }
        if (!hasInput) continue;
      }
      keep.push(it);
    }
    return keep;
  }

  function renderItem(item) {
    const t = item.type;
    if (t === "heading") {
      const node = el("div", { class: "form-heading", text: item.text });
      if (item.showIf) conditionalItems.push({ node, item });
      return node;
    }
    if (t === "note") {
      const node = el("div", { class: "form-note", text: item.text });
      if (item.showIf) conditionalItems.push({ node, item });
      return node;
    }
    if (t === "percent_group") {
      const pg = renderPercentGroup(item);
      if (mode === "staff" && hideFromClient(item)) pg.classList.add("extra-field");
      if (item.showIf) conditionalItems.push({ node: pg, item });
      return pg;
    }

    let control;
    let wrapLabel = true;
    switch (t) {
      case "textarea": control = renderTextarea(item); break;
      case "select": control = renderSelect(item); break;
      case "yesno": control = renderYesNo(item); break;
      case "checkbox": control = renderCheckbox(item); wrapLabel = false; break;
      case "currency": control = renderAdorned(item, "£", false); break;
      case "percent": control = renderAdorned(item, "%", true); break;
      case "table": control = renderTable(item); break;
      default: control = renderBasicInput(item);
    }

    const field = el("div", { class: "field", "data-fid": item.id });
    const badge = makeBadge(item);
    const skip = makeSkipToggle(item, field);
    if (wrapLabel) {
      const lab = el("label", { for: "f_" + item.id });
      lab.appendChild(document.createTextNode(item.label + " "));
      if (item.required) lab.appendChild(el("span", { class: "req", text: "*" }));
      if (badge) lab.appendChild(badge);
      if (skip) lab.appendChild(skip);
      field.appendChild(lab);
      if (item.help) field.appendChild(el("div", { class: "help", text: item.help }));
      field.appendChild(control);
    } else {
      if (badge) { badge.classList.add("badge-inline"); field.appendChild(badge); }
      field.appendChild(control);
      if (item.help) field.appendChild(el("div", { class: "help", text: item.help }));
    }

    if (mode === "staff" && hideFromClient(item)) field.classList.add("extra-field");
    if (item.showIf) conditionalItems.push({ node: field, item });
    return field;
  }

  // Whole sections with a showIf (e.g. Claims History) stay reachable for staff but are
  // flagged as "not applicable" so it is obvious the client will skip them.
  const conditionalSections = []; // {banner, link, section}

  // Staff see every page (with a "not applicable" banner); in client view a page that does not
  // apply is hidden completely, exactly as the client would experience it.
  function sectionApplies(i) {
    const section = sectionMeta[i];
    if (!section) return false;
    if (hiddenSections.has(section.id) && mode !== "staff") return false;
    return mode === "staff" || conditionMet(section.showIf);
  }

  function applicablePages() {
    return sectionCards.map((_, i) => i).filter(sectionApplies);
  }

  function refreshVisibility() {
    for (const { node, item } of conditionalItems) {
      node.classList.toggle("hidden", !isVisible(item));
    }
    for (const { banner, link, section } of conditionalSections) {
      const applies = conditionMet(section.showIf);
      banner.style.display = applies ? "none" : "";
      link.classList.toggle("na", !applies);
    }
  }

  const sectionMeta = [];
  const sectionCards = [];
  let currentPage = 0;

  function flushSave() {
    if (dirty) {
      clearTimeout(saveTimer);
      doSave();
    }
  }

  function showPage(i, scroll = true) {
    currentPage = Math.max(0, Math.min(i, sectionCards.length - 1));
    if (!sectionApplies(currentPage)) {
      const pages = applicablePages();
      currentPage = pages.find((p) => p >= currentPage) ?? pages[pages.length - 1] ?? 0;
    }
    sectionCards.forEach((card, idx) => {
      card.style.display = idx === currentPage ? "" : "none";
    });
    refreshNav();
    updatePager();
    if (scroll) window.scrollTo({ top: 0, behavior: "smooth" });
  }

  function gotoPage(i) {
    flushSave();
    showPage(i);
  }

  // Back / Next controls inside the sticky bar
  const stepPage = (dir) => {
    const pages = applicablePages();
    const at = pages.indexOf(currentPage);
    return pages[Math.max(0, Math.min(pages.length - 1, (at === -1 ? 0 : at) + dir))] ?? currentPage;
  };
  const pagerBack = el("button", { type: "button", class: "btn pager-btn", text: "‹ Back", onclick: () => gotoPage(stepPage(-1)) });
  const pagerNext = el("button", { type: "button", class: "btn primary pager-btn", onclick: () => gotoPage(stepPage(1)) });
  const pagerLabel = el("span", { class: "pager-label" });

  const progressFill = document.getElementById("qprogress-fill");

  function updatePager() {
    const pages = applicablePages();
    const at = Math.max(0, pages.indexOf(currentPage));
    const last = pages[pages.length - 1];
    if (progressFill && pages.length) progressFill.style.width = Math.round(((at + 1) / pages.length) * 100) + "%";
    pagerBack.disabled = currentPage === pages[0];
    pagerBack.style.visibility = currentPage === pages[0] ? "hidden" : "visible";
    pagerNext.style.display = currentPage === last ? "none" : "";
    pagerNext.textContent = "Save & continue ›";
    pagerLabel.textContent = "Page " + (at + 1) + " of " + pages.length + " — " + sectionMeta[currentPage].title;
    // Submit button only on the last page for clients; staff see it on every page
    if (mode === "client" && !window.STAFF_SUBMIT) {
      submitBtn.style.display = currentPage === last ? "" : "none";
    }
  }

  // Apply schema `default` values to any field that has no value yet (fresh case),
  // so e.g. the chosen PL extensions start on "Yes". Never overrides existing answers.
  // A percent group (e.g. UK/EEA 100%) only takes its defaults while every field in it is empty.
  function applyDefaults() {
    let changed = false;
    schema.sections.forEach((s) => s.items.forEach((it) => {
      if (it && it.type === "percent_group") {
        const empty = (v) => v === undefined || v === null || v === "";
        if (it.fields.every((f) => empty(data[f.id]))) {
          it.fields.forEach((f) => { if (f.default !== undefined) { data[f.id] = f.default; changed = true; } });
        }
      } else if (it && it.default !== undefined) {
        const v = data[it.id];
        if (v === undefined || v === null || v === "") { data[it.id] = it.default; changed = true; }
      }
    }));
    if (changed) scheduleSave();
  }

  function build() {
    applyDefaults();
    // Staff legend explaining the badges
    if (mode === "staff") {
      const legend = el("div", { class: "badge-legend" });
      legend.appendChild(el("span", { class: "qbadge qbadge-acturis", text: "Acturis" }));
      legend.appendChild(el("span", { text: " = captured by Acturis, client is asked  ·  " }));
      legend.appendChild(el("span", { class: "qbadge qbadge-client", text: "Extra · client" }));
      legend.appendChild(el("span", { text: " = extra detail, client is asked  ·  " }));
      legend.appendChild(el("span", { class: "qbadge qbadge-extra", text: "Staff only" }));
      legend.appendChild(el("span", { text: " = only staff see this (greyed)" }));
      root.appendChild(legend);
    }

    // Which sections to show:
    //  - client: drop staff-hidden sections and sections with no non-Extra questions
    //  - public: drop admin-hidden sections and sections with no visible questions
    let sections = schema.sections;
    if (mode === "client") {
      sections = sections.filter((s) => !hiddenSections.has(s.id) && sectionHasClientItems(s));
    } else if (mode === "public") {
      sections = sections.filter((s) => sectionHasPublicItems(s));
    }

    sections.forEach((section, i) => {
      const card = el("div", { class: "card form-section", id: "sec_" + section.id });

      const link = el("a", { href: "#sec_" + section.id, text: (i + 1) + ". " + section.title });
      link.addEventListener("click", (e) => {
        e.preventDefault();
        gotoPage(i);
      });
      nav.appendChild(link);

      if (mode === "staff") {
        const head = el("div", { class: "section-head" });
        head.appendChild(el("h2", { text: section.title }));
        const toggle = el("button", { type: "button", class: "btn small toggle-hide" });
        const banner = el("div", {
          class: "form-note hide-banner",
          text: "This page is hidden from the client — they will not see it or be asked to complete it. Anything already entered here is kept.",
        });
        const paint = () => {
          const off = hiddenSections.has(section.id);
          toggle.textContent = off ? "Hidden from client — click to include" : "Included for client — click to hide";
          toggle.classList.toggle("is-off", off);
          card.classList.toggle("section-off", off);
          link.classList.toggle("off", off);
          banner.style.display = off ? "" : "none";
        };
        toggle.addEventListener("click", () => {
          if (hiddenSections.has(section.id)) hiddenSections.delete(section.id);
          else hiddenSections.add(section.id);
          persistHiddenSections();
          paint();
        });
        head.appendChild(toggle);
        card.appendChild(head);
        card.appendChild(banner);
        paint();
      } else {
        card.appendChild(el("h2", { text: section.title }));
      }

      if (section.showIf) {
        const naBanner = el("div", {
          class: "form-note na-banner",
          text: "Not applicable based on the answers so far — the client skips this page.",
        });
        card.appendChild(naBanner);
        conditionalSections.push({ banner: naBanner, link, section });
      }
      if (section.description) card.appendChild(el("p", { class: "section-desc", text: section.description }));
      let items = section.items;
      if (mode === "client") items = cleanupHeadings(section.items.filter((it) => !hideFromClient(it)));
      else if (mode === "public") items = cleanupHeadings(section.items.filter((it) => !isPublicHidden(it)));
      items.forEach((item) => card.appendChild(renderItem(item)));
      root.appendChild(card);
      sectionMeta.push(section);
      sectionCards.push(card);
    });

    const pager = el("div", { class: "pager" }, pagerBack, pagerLabel, pagerNext);
    const submitRow = submitBtn.closest(".submit-row");
    submitRow.parentNode.insertBefore(pager, submitRow);

    refreshVisibility();
    showPage(0, false);
  }

  function sectionHasData(section) {
    for (const item of section.items) {
      if (item.type === "percent_group") {
        if (item.fields.some((f) => val(f.id) !== undefined && val(f.id) !== "")) return true;
      } else if (item.id && data[item.id] !== undefined) {
        const v = data[item.id];
        if (Array.isArray(v)) {
          if (v.some((r) => Object.values(r).some((x) => x !== "" && x != null))) return true;
        } else if (v !== "" && v != null) return true;
      }
    }
    return false;
  }

  function refreshNav() {
    const links = nav.querySelectorAll("a");
    let shown = 0;
    sectionMeta.forEach((section, i) => {
      const applies = sectionApplies(i);
      links[i].style.display = applies ? "" : "none";
      if (applies) links[i].textContent = ++shown + ". " + section.title;
      links[i].classList.toggle("done", sectionHasData(section));
      links[i].classList.toggle("current", i === currentPage);
    });
  }

  // ---------------- validation & submit ----------------

  function validate() {
    const problems = [];
    sectionMeta.forEach((section, pageIdx) => {
      if (hiddenSections.has(section.id)) return; // staff marked as not needed
      if (!conditionMet(section.showIf)) return;    // page does not apply to this client
      for (const item of section.items) {
        if (mode === "client" && hideFromClient(item)) continue;  // Extra questions are hidden from clients
        if (mode === "public" && isPublicHidden(item)) continue; // admin hid this from the public form
        if (item.type === "percent_group" && item.mustTotal) {
          const total = Math.round(item.fields.reduce((s, f) => s + numVal(f.id), 0) * 100) / 100;
          if (total !== item.mustTotal) {
            problems.push({
              anchor: "f_" + item.id,
              page: pageIdx,
              msg: section.title + ": “" + item.label.replace(/ \(must.*$/, "") + "” totals " + total + "% — it must total " + item.mustTotal + "%.",
            });
          }
        } else if ((item.required || (item.requiredIf && conditionMet(item.requiredIf)))
                   && isVisible(item) && !hiddenItems.has(item.id) && !offForAll.has(item.id)) {
          const v = val(item.id);
          if (v === undefined || v === "" || v === null) {
            problems.push({
              anchor: "f_" + item.id,
              page: pageIdx,
              msg: section.title + ": “" + item.label + "” is required.",
            });
          }
        }
      }
    });
    return problems;
  }

  function showProblems(problems) {
    validationSummary.innerHTML = "";
    if (!problems.length) return;
    validationSummary.appendChild(el("strong", { text: "Please resolve the following before submitting:" }));
    const ul = el("ul");
    for (const p of problems) {
      const a = el("a", { href: "#", text: p.msg });
      a.addEventListener("click", (e) => {
        e.preventDefault();
        if (p.page !== undefined && p.page !== currentPage) showPage(p.page, false);
        const target = document.getElementById(p.anchor);
        if (target) {
          target.scrollIntoView({ behavior: "smooth", block: "center" });
          if (target.focus) target.focus({ preventScroll: true });
        }
      });
      ul.appendChild(el("li", null, a));
    }
    validationSummary.appendChild(ul);
  }

  submitBtn.addEventListener("click", async () => {
    const problems = validate();

    // Public form: also require the consent tick
    const consentEl = document.getElementById("public-consent");
    if (mode === "public" && consentEl && !consentEl.checked) {
      problems.push({ msg: "Please tick the box to agree before submitting." });
    }

    showProblems(problems);
    if (problems.length) {
      validationSummary.scrollIntoView({ behavior: "smooth", block: "center" });
      return;
    }

    if (mode === "public") {
      submitBtn.disabled = true;
      try {
        const res = await fetch(window.SUBMIT_URL, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            data: data,
            consent: consentEl ? consentEl.checked : false,
            website: (document.getElementById("hp-website") || {}).value || "",
          }),
        });
        if (!res.ok) throw new Error("HTTP " + res.status);
        window.location = window.THANKS_URL;
      } catch (e) {
        submitBtn.disabled = false;
        setSaveStatus("Submission failed — please try again.", "error");
      }
      return;
    }

    const confirmMsg = (mode === "client" && !window.STAFF_SUBMIT)
      ? "Submit the questionnaire to Polished Insurance? You will not be able to make further changes without contacting us."
      : "Mark this questionnaire as completed? This stops the automatic reminders and locks the client link.";
    if (!window.confirm(confirmMsg)) return;
    submitBtn.disabled = true;
    try {
      const res = await fetch(window.SUBMIT_URL, {
        method: "POST",
        headers: postHeaders(),
        body: JSON.stringify({ data: data }),
      });
      if (!res.ok) throw new Error("HTTP " + res.status);
      dirty = false;
      window.location.reload();
    } catch (e) {
      submitBtn.disabled = false;
      setSaveStatus("Submission failed — please try again.", "error");
    }
  });

  // Copy-link button (staff view)
  const copyBtn = document.getElementById("copy-link");
  if (copyBtn) {
    copyBtn.addEventListener("click", async () => {
      const input = document.getElementById("client-link");
      try {
        await navigator.clipboard.writeText(input.value);
      } catch (e) {
        input.select();
        document.execCommand("copy");
      }
      copyBtn.textContent = "Copied ✓";
      setTimeout(() => (copyBtn.textContent = "Copy link"), 1800);
    });
  }

  build();
})();
