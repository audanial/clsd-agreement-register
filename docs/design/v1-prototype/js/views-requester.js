/* Requesting Staff views: 1 My Submissions, 2 New Submission (incl. requester side of 12 Addendum). */
(function () {
  'use strict';
  const A = window.AMS;
  const UI = A.UI;
  const esc = A.esc;
  const icon = (n, c) => A.icon(n, c);

  // Short "what happens next" line, phrased for the viewer.
  A.nextStepShort = function (s, forLegal) {
    const req = A.openRequest(s);
    switch (s.status) {
      case 'pending_review': return forLegal ? 'Start review' : 'Legal will start the review';
      case 'in_review': return forLegal ? 'Continue review' : 'Legal is reviewing';
      case 'action_required': return forLegal ? 'Requester to respond' : (req && req.request.phase === 'final' ? 'Correct final documents' : 'Respond to Legal\'s request');
      case 'review_completed': return forLegal ? `Obtain ${A.signatory(s)} signature` : 'Legal is obtaining UniKL\'s signature';
      case 'awaiting_partner': return forLegal ? 'Requester obtains partner signature' : 'Get the partner\'s signature, then upload';
      case 'awaiting_stamping': return forLegal ? 'Requester completes LHDN stamping' : 'Upload the LHDN stamp certificate';
      case 'final_verification': return forLegal ? 'Verify final documents' : 'Legal is verifying the final documents';
      case 'fully_executed': return forLegal ? 'Create Register record' : 'Legal will create the Register record';
      case 'registered': return `Registered as ${s.agreementId}`;
      case 'not_proceeding': return 'Closed — history kept';
      default: return '';
    }
  };

  // ---------- 1. My Submissions ----------
  A.views.mySubmissions = function () {
    const u = A.me();
    const all = UI.previewEmpty ? [] : A.mySubmissions(u).slice().sort((a, b) => b.updatedAt - a.updatedAt);
    const groups = {
      all: () => true,
      mine: (s) => A.nextActor(s) === 'requester',
      legal: (s) => A.nextActor(s) === 'legal',
      done: (s) => ['registered', 'fully_executed'].includes(s.status),
      closed: (s) => s.status === 'not_proceeding',
    };
    const labels = { all: 'All', mine: 'Needs my action', legal: 'With Legal', done: 'Completed', closed: 'Not Proceeding' };
    const f = UI.myFilter;
    const q = f.q.trim().toLowerCase();
    const rows = all.filter(groups[f.tab]).filter((s) => !q || [s.id, s.title, s.partner, s.type].join(' ').toLowerCase().includes(q));
    const mine = all.filter(groups.mine);

    const head = `<div class="page-head"><div style="flex:1"><h1>My Submissions</h1><p class="lede">Track your agreement submissions.</p></div>
      <a class="btn primary lg" href="#/new">${icon('plus')}New Submission</a></div>`;

    if (!all.length) {
      return `<div class="page">${head}<div class="card empty">${icon('inbox')}<h3>No submissions yet</h3><p>Select New Submission to get started.</p>
        <p style="margin-top:16px"><a class="btn primary" href="#/new">${icon('plus')}Start a new submission</a></p>
        ${UI.previewEmpty ? `<p class="xsmall faint" style="margin-top:18px">Demo preview of a first-time requester. <button class="link-btn" data-act="toggle-empty">Show Aisyah's submissions again</button></p>` : ''}</div></div>`;
    }

    const attention = mine.length ? `<div class="notice warn" style="margin-bottom:16px">${icon('flag')}<div><strong>${mine.length} submission${mine.length === 1 ? ' needs' : 's need'} your action.</strong></div></div>` : '';

    const table = rows.length ? `<div class="table-wrap"><table class="table"><thead><tr>
        <th>Submission</th><th>Type</th><th>Status</th><th>Next step</th><th><span class="sr-only">Unread</span>${icon('message')}</th><th>Last update</th></tr></thead><tbody>
      ${rows.map((s) => {
        const un = A.unreadCount(u, s);
        return `<tr class="clickable ${un ? 'unread' : ''}" data-act="open-sub" data-sub="${s.id}">
          <td class="title-cell"><a href="#/sub/${s.id}/overview">${esc(s.title)}</a><div class="sub">${esc(s.id)} · ${esc(s.partner)}</div></td>
          <td><span class="chip">${A.typeLabel(s)}</span><div class="sub">${esc(s.category)} · ${esc(s.location)}</div></td>
          <td>${A.statusBadge(s)}</td>
          <td>${A.nextActorHtml(s, u)}<div class="sub">${esc(A.nextStepShort(s, false))}</div></td>
          <td>${un ? `<span class="unread-dot" title="${un} unread message${un === 1 ? '' : 's'}">${un}</span>` : '<span class="faint">—</span>'}</td>
          <td class="nowrap">${A.fmt(s.updatedAt)}</td></tr>`;
      }).join('')}</tbody></table></div>`
      : `<div class="empty">${icon('search')}<h3>No matching submissions.</h3></div>`;

    return `<div class="page">${head}${attention}
      <div class="card">
        <div class="toolbar">
          <div class="tabs-pill" role="group" aria-label="Filter submissions">
            ${Object.keys(groups).map((k) => `<button class="pill" data-act="my-tab" data-tab="${k}" aria-pressed="${f.tab === k}">${labels[k]}<span class="n">${all.filter(groups[k]).length}</span></button>`).join('')}
          </div>
          <span class="spacer"></span>
          <label class="sr-only" for="my-q">Search my submissions</label>
          <input id="my-q" class="input search" type="search" placeholder="Search title, partner or ID" value="${esc(f.q)}" data-bind="myFilter.q" data-live="rerender">
        </div>
        ${table}
      </div>
      <p class="xsmall faint" style="margin-top:10px"><button class="link-btn" data-act="toggle-empty">Demo: empty state</button></p>
    </div>`;
  };

  // ---------- 2. New Submission ----------
  function blankForm(type) {
    return {
      title: '', campus: 'MIIT', partner: '', scope: '', category: '', location: '', type: type || '', moaSubtype: '',
      addMode: '', originalId: '', notFound: { title: '', partner: '', approxDate: '', campus: '', details: '' }, purpose: '',
      files: {}, tried: false, changed: null,
    };
  }
  A.blankForm = blankForm;

  function formChecks(f) {
    const c = [];
    c.push({ ok: !!f.title.trim(), label: 'Submission title', field: 'title' });
    c.push({ ok: !!f.campus, label: 'Campus or department', field: 'campus' });
    c.push({ ok: !!f.partner.trim(), label: 'Partner / organisation name', field: 'partner' });
    c.push({ ok: !!f.scope.trim(), label: 'Purpose and scope', field: 'scope' });
    c.push({ ok: !!f.category, label: 'Engagement category', field: 'category' });
    c.push({ ok: !!f.location, label: 'Partner location', field: 'location' });
    c.push({ ok: !!f.type, label: 'Agreement type', field: 'type' });
    if (f.type === 'ADDENDUM') {
      const nf = f.notFound;
      c.push({ ok: (f.addMode === 'selected' && !!f.originalId) || f.addMode === 'not_found', label: 'Original agreement', field: 'original' });
      if (f.addMode === 'not_found') {
        c.push({ ok: !!nf.title.trim(), label: 'Original agreement title', field: 'nf-title' });
        c.push({ ok: !!nf.partner.trim(), label: 'Original agreement partner', field: 'nf-partner' });
        c.push({ ok: !!nf.approxDate.trim(), label: 'Original agreement approximate date', field: 'nf-approxDate' });
        c.push({ ok: !!nf.campus.trim(), label: 'Original agreement campus or department', field: 'nf-campus' });
      }
      c.push({ ok: !!f.purpose.trim(), label: 'Purpose of Addendum', field: 'purpose' });
    }
    const req = A.requiredIntake(f.category, f.location);
    if (f.category) req.forEach((k) => c.push({ ok: !!f.files[k], label: A.slotLabel(k), field: 'file-' + k, doc: true }));
    if (f.category === 'Industry' && !f.location) c.push({ ok: false, label: 'Company-registration document (choose partner location first)', doc: true });
    if (!f.category) c.push({ ok: false, label: 'Required documents (choose engagement category first)', doc: true });
    return c;
  }
  A.formChecks = formChecks;

  function fieldErr(f, key, checks) {
    if (!f.tried) return '';
    const c = checks.find((x) => x.field === key);
    return c && !c.ok ? `<div class="error">${icon('alert')}Required</div>` : '';
  }
  function invalid(f, key, checks) {
    if (!f.tried) return '';
    const c = checks.find((x) => x.field === key);
    return c && !c.ok ? 'invalid' : '';
  }

  // Addendum fields: a specific inline message tied to the field, so the first invalid one can take focus.
  function addErr(f, key, checks, msg) {
    if (!f.tried) return '';
    const c = checks.find((x) => x.field === key);
    return c && !c.ok ? `<div class="error" id="err-${key}">${icon('alert')}${esc(msg)}</div>` : '';
  }
  function addAttrs(f, key, checks, hintId) {
    const bad = f.tried && checks.some((x) => x.field === key && !x.ok);
    const desc = [hintId, bad ? `err-${key}` : ''].filter(Boolean).join(' ');
    return `id="${key}" data-live="rerender" ${bad ? 'aria-invalid="true"' : ''} ${desc ? `aria-describedby="${desc}"` : ''}`;
  }

  function seg(name, options, value, bind) {
    return `<div class="seg" role="radiogroup">${options.map((o) => {
      const v = typeof o === 'string' ? o : o.v;
      const l = typeof o === 'string' ? o : o.l;
      const small = typeof o === 'string' ? '' : (o.s ? `<small>${o.s}</small>` : '');
      return `<label><input type="radio" name="${name}" value="${v}" ${value === v ? 'checked' : ''} data-bind="${bind}" data-live="rerender"><span>${l}${small}</span></label>`;
    }).join('')}</div>`;
  }

  A.readinessHtml = function (f) {
    const checks = formChecks(f);
    const missing = checks.filter((c) => !c.ok);
    return `<div class="card-head"><h3 style="flex:1">Submission readiness</h3>${missing.length ? `<span class="badge tone-amber">${missing.length} missing</span>` : '<span class="badge tone-green">Ready</span>'}</div>
      <div class="card-body">
        <ul class="ready-list">${checks.map((c) => `<li class="${c.ok ? 'ok' : 'no'}">${icon(c.ok ? 'check' : 'alert')}<span>${esc(c.label)}${c.doc && !c.ok ? ' — <em>missing</em>' : ''}</span></li>`).join('')}</ul>
      </div>
      <div class="card-foot stack-sm">
        <button class="btn primary lg" style="width:100%" data-act="new-submit" ${missing.length ? 'aria-disabled="true"' : ''}>${icon('arrow')}Submit to CLSD Legal</button>
        <p class="xsmall muted">${missing.length ? 'Complete the missing items to submit.' : 'Documents lock during Legal review.'}</p>
        <p class="xsmall faint">Leaving this page discards the form.</p>
      </div>`;
  };

  A.views.newSubmission = function (preset) {
    if (!UI.newForm) UI.newForm = blankForm(preset === 'addendum' ? 'ADDENDUM' : '');
    const f = UI.newForm;
    const u = A.me();
    const checks = formChecks(f);
    const originals = A.accessibleOriginals(u);

    const addendum = f.type !== 'ADDENDUM' ? '' : `
      <div class="card" style="margin-top:16px" id="addendum-section">
        <div class="card-head"><h2 style="flex:1">Addendum details</h2></div>
        <div class="card-body stack">
          <fieldset class="field ${invalid(f, 'original', checks)}"><legend id="original-legend">Original agreement <span class="req">*</span></legend>
            <div class="hint" id="original-hint" style="margin:0 0 8px">The agreement this Addendum changes. Only agreements from your own registered submissions are listed.</div>
            <div class="choice-list" role="radiogroup" aria-labelledby="original-legend" aria-describedby="original-hint${f.tried && checks.some((x) => x.field === 'original' && !x.ok) ? ' err-original' : ''}" ${f.tried && checks.some((x) => x.field === 'original' && !x.ok) ? 'aria-invalid="true"' : ''}>
              ${originals.map((r, i) => `<label class="choice ${f.addMode === 'selected' && f.originalId === r.id ? 'selected' : ''}">
                  <input type="radio" ${i === 0 ? 'id="original"' : ''} name="orig" data-act="pick-original" data-id="${r.id}" ${f.addMode === 'selected' && f.originalId === r.id ? 'checked' : ''}>
                  <div style="min-width:0;overflow-wrap:anywhere"><div class="strong">${esc(r.title)} <span class="faint">· ${esc(r.id)}</span></div>
                  <div class="small muted">${esc(r.partner)} · ${esc(r.type)}${r.moaSubtype ? ' · ' + esc(r.moaSubtype) : ''} · signed ${A.fmtDate(r.dateSigned)}</div></div></label>`).join('')}
              <label class="choice ${f.addMode === 'not_found' ? 'selected' : ''}">
                <input type="radio" ${originals.length ? '' : 'id="original"'} name="orig" data-act="pick-original" data-id="" ${f.addMode === 'not_found' ? 'checked' : ''}>
                <div><div class="strong">Agreement not found</div><div class="small muted">I can't find or access it. Legal will identify it from your details.</div></div></label>
            </div>
            ${addErr(f, 'original', checks, 'Choose an agreement, or Agreement not found.')}
          </fieldset>
          ${f.addMode === 'not_found' ? `<div class="form-grid" style="background:var(--surface-2);padding:14px;border-radius:6px;border:1px solid var(--border)">
            <label class="field ${invalid(f, 'nf-title', checks)}"><span class="label">Original agreement title <span class="req">*</span></span><input class="input" ${addAttrs(f, 'nf-title', checks)} data-bind="newForm.notFound.title" value="${esc(f.notFound.title)}" placeholder="e.g. MOU on vessel crew training">${addErr(f, 'nf-title', checks, 'Enter the original agreement title.')}</label>
            <label class="field ${invalid(f, 'nf-partner', checks)}"><span class="label">Partner <span class="req">*</span></span><input class="input" ${addAttrs(f, 'nf-partner', checks)} data-bind="newForm.notFound.partner" value="${esc(f.notFound.partner)}">${addErr(f, 'nf-partner', checks, 'Enter the partner named in the original.')}</label>
            <label class="field ${invalid(f, 'nf-approxDate', checks)}"><span class="label">Approximate date <span class="req">*</span></span><input class="input" ${addAttrs(f, 'nf-approxDate', checks)} data-bind="newForm.notFound.approxDate" value="${esc(f.notFound.approxDate)}" placeholder="e.g. Around March 2025">${addErr(f, 'nf-approxDate', checks, 'Enter an approximate date.')}</label>
            <label class="field ${invalid(f, 'nf-campus', checks)}"><span class="label">Campus or department <span class="req">*</span></span><input class="input" ${addAttrs(f, 'nf-campus', checks)} data-bind="newForm.notFound.campus" value="${esc(f.notFound.campus)}">${addErr(f, 'nf-campus', checks, 'Enter the campus or department.')}</label>
            <label class="field full"><span class="label">Other identifying details <span class="faint">(optional)</span></span><textarea class="textarea" data-bind="newForm.notFound.details" placeholder="Who signed it, reference numbers, what it covers">${esc(f.notFound.details)}</textarea></label>
          </div>` : ''}
          <label class="field ${invalid(f, 'purpose', checks)}"><span class="label">What does this Addendum change? <span class="req">*</span></span>
            <textarea class="textarea" ${addAttrs(f, 'purpose', checks, 'purpose-hint')} data-bind="newForm.purpose" placeholder="e.g. Extends the term by two years">${esc(f.purpose)}</textarea>
            <div class="hint" id="purpose-hint">For example an extension, scope change, party-detail correction or clause amendment.</div>${addErr(f, 'purpose', checks, 'Describe what the Addendum changes.')}</label>
          <p class="small muted">Legal confirms the original and decides whether LHDN stamping is needed.</p>
        </div>
      </div>`;

    const req = A.requiredIntake(f.category, f.location);
    const keepOther = f.category === 'Industry' && ((f.location === 'International' && f.files.ssm) || (f.location === 'Local' && f.files.business_reg));
    const checklistItems = !f.category
      ? `<div class="empty" style="padding:28px">${icon('file')}<p>Choose Academic or Industry to see the required documents.</p></div>`
      : `<div class="checklist">${req.map((k, i) => clItem(f, k, i + 1)).join('')}
          ${f.category === 'Industry' && !f.location ? `<div class="cl-item missing-highlight"><span class="cl-num">6</span><div><div class="strong">Company-registration document</div><div class="small muted">Choose Local or International above. Local partners need SSM / Malaysian corporate information; International partners need an equivalent business-registration document.</div></div><span></span></div>` : ''}
        </div>
        ${keepOther ? `<div class="notice neutral" style="margin:0 16px 14px">${icon('info')}<div>Your ${f.location === 'International' ? 'SSM / Malaysian corporate information' : 'business-registration'} file is kept in this form in case you switch the partner location back. Only the document for the selected location is submitted.</div></div>` : ''}`;

    return `<div class="page">
      <div class="crumbs"><a href="#/my">My Submissions</a> / New Submission</div>
      <div class="page-head"><div style="flex:1"><h1>New Submission</h1><p class="lede">Submit an agreement for Legal review.</p></div></div>
      <div class="grid-form">
        <div>
          <div class="card">
            <div class="card-head"><h2>1. Basic details</h2></div>
            <div class="card-body form-grid">
              <label class="field full ${invalid(f, 'title', checks)}"><span class="label">Submission title <span class="req">*</span></span><input class="input" data-bind="newForm.title" data-live="readiness" value="${esc(f.title)}" placeholder="e.g. Joint Curriculum Development MOU">${fieldErr(f, 'title', checks)}</label>
              <label class="field ${invalid(f, 'campus', checks)}"><span class="label">Campus or department <span class="req">*</span></span>
                <select class="select" data-bind="newForm.campus" data-live="readiness"><option value="">Choose…</option>${A.CAMPUSES.map((c) => `<option value="${c[0]}" ${f.campus === c[0] ? 'selected' : ''}>${esc(c[1])}</option>`).join('')}</select>${fieldErr(f, 'campus', checks)}</label>
              <label class="field ${invalid(f, 'partner', checks)}"><span class="label">Partner / organisation name <span class="req">*</span></span><input class="input" data-bind="newForm.partner" data-live="readiness" value="${esc(f.partner)}" placeholder="Full legal name of the partner">${fieldErr(f, 'partner', checks)}</label>
              <label class="field full ${invalid(f, 'scope', checks)}"><span class="label">Purpose and scope <span class="req">*</span></span><textarea class="textarea" data-bind="newForm.scope" data-live="readiness" placeholder="What the agreement covers and why UniKL is entering it">${esc(f.scope)}</textarea>${fieldErr(f, 'scope', checks)}</label>
            </div>
          </div>

          <div class="card" style="margin-top:16px">
            <div class="card-head"><h2>2. Classification</h2></div>
            <div class="card-body stack-lg">
              <fieldset class="field ${invalid(f, 'category', checks)}"><legend>Engagement category <span class="req">*</span></legend>
                ${seg('category', [{ v: 'Academic', l: 'Academic', s: '2 documents' }, { v: 'Industry', l: 'Industry', s: '6 documents' }], f.category, 'newForm.category')}${fieldErr(f, 'category', checks)}</fieldset>
              <fieldset class="field ${invalid(f, 'location', checks)}"><legend>Partner location <span class="req">*</span></legend>
                ${seg('location', [{ v: 'Local', l: 'Local', s: 'Malaysia' }, { v: 'International', l: 'International', s: 'Outside Malaysia' }], f.location, 'newForm.location')}${fieldErr(f, 'location', checks)}</fieldset>
              <fieldset class="field ${invalid(f, 'type', checks)}"><legend>Agreement type <span class="req">*</span></legend>
                ${seg('type', [{ v: 'NDA', l: 'NDA' }, { v: 'MOA', l: 'MOA' }, { v: 'MOU', l: 'MOU' }, { v: 'ADDENDUM', l: 'ADDENDUM' }], f.type, 'newForm.type')}
                <div class="hint">LOI is not available. For SEA, Research Collaboration or Erasmus+, choose MOA and enter the arrangement below.</div>${fieldErr(f, 'type', checks)}</fieldset>
              ${f.type === 'MOA' ? `<label class="field"><span class="label">MOA subtype / arrangement <span class="faint">(optional)</span></span><input class="input" data-bind="newForm.moaSubtype" value="${esc(f.moaSubtype)}" placeholder="e.g. Student Exchange Agreement, Research Collaboration, Erasmus+"><div class="hint">Free text. The Register type stays MOA.</div></label>` : ''}
            </div>
          </div>
          ${addendum}

          <div class="card" style="margin-top:16px" id="checklist-card">
            <div class="card-head"><h2 style="flex:1">3. Required documents</h2>${f.category ? `<span class="chip">${esc(f.category)} checklist · ${req.length || 6} required</span>` : ''}${A.pcCue()}</div>
            <div class="notice neutral" style="margin:14px 16px 4px;border-radius:6px">${icon('info')}<div><strong>PDF or Word (.docx), up to 20 MB.</strong> Use fictional files only in this prototype.</div></div>
            ${checklistItems}
          </div>
        </div>
        <div class="card sticky" id="readiness">${A.readinessHtml(f)}</div>
      </div>
    </div>`;
  };

  function clItem(f, k, n) {
    const d = A.SLOT_DEFS[k];
    const file = f.files[k];
    const errKey = 'new:' + k;
    const err = UI.uploadErrors[errKey];
    const changed = f.changed === k ? 'changed' : '';
    const showMissing = f.tried && !file;
    return `<div class="cl-item ${file ? 'has-file' : ''} ${showMissing ? 'missing-highlight' : ''} ${changed}" id="file-${k}">
      <span class="cl-num">${file ? icon('check') : n}</span>
      <div><div class="strong">${esc(d.label)} <span class="req" style="color:var(--maroon)">*</span></div><div class="small muted">${esc(d.help)}</div>
        ${file ? `<div class="file" style="margin-top:8px">${A.fileIco(file.filename.split('.').pop())}<div><div class="file-name">${esc(file.filename)}</div><div class="file-meta">${A.size(file.sizeMB)} · Ready to submit</div></div></div>`
          : showMissing ? `<div class="small" style="margin-top:6px;color:var(--red)">${icon('alert')} Required</div>` : ''}
        ${err ? `<div class="notice error" style="margin-top:8px">${icon('alert')}<div><strong>Upload failed.</strong> ${esc(err)} ${file ? 'Your current file is unchanged.' : ''} <button class="link-btn" data-act="upload-new" data-key="${k}">Try again</button></div></div>` : ''}
      </div>
      <div class="row">${file ? `<button class="btn sm" data-act="upload-new" data-key="${k}">${icon('upload')}Replace</button><button class="btn sm ghost" data-act="remove-new-file" data-key="${k}" aria-label="Remove ${esc(d.label)}">Remove</button>`
        : `<button class="btn sm primary" data-act="upload-new" data-key="${k}">${icon('upload')}Upload</button>`}</div>
    </div>`;
  }
})();
