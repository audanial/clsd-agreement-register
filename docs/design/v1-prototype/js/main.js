/* Event wiring, dialogs and the demo guide. */
(function () {
  'use strict';
  const A = window.AMS;
  const UI = A.UI;
  const esc = A.esc;
  const icon = (n, c) => A.icon(n, c);
  const M = A.MODALS;

  // ---------- helpers ----------
  function getPath(obj, path) { return path.split('.').reduce((o, k) => (o == null ? o : o[k]), obj); }
  function setPath(obj, path, value) {
    const ks = path.split('.'); const last = ks.pop();
    const target = ks.reduce((o, k) => (o[k] == null ? (o[k] = {}) : o[k]), obj);
    target[last] = value;
  }
  // Re-render while keeping focus/caret in the field the user was typing in.
  function rerender(which) {
    const el = document.activeElement;
    const bind = el && el.getAttribute && el.getAttribute('data-bind');
    const val = el && el.type === 'radio' ? el.value : null;
    let sel = null;
    try { sel = el && 'selectionStart' in el ? [el.selectionStart, el.selectionEnd] : null; } catch (e) { sel = null; }
    if (which === 'modal') A.renderModal(); else A.render();
    if (bind) {
      const q = `[data-bind="${bind}"]${val ? `[value="${val}"]` : ''}`;
      const n = document.querySelector(q);
      if (n) { n.focus(); try { if (sel && n.setSelectionRange && n.type !== 'radio' && n.type !== 'checkbox') n.setSelectionRange(sel[0], sel[1]); } catch (e) { /* ignore */ } }
    }
  }
  function after(id, res) { if (res && res.ok && res.rev) UI.viewRev[id] = res.rev; }
  function rev(id) { return UI.viewRev[id]; }
  function done(res, id, msg) {
    if (res.ok) { after(id, res); A.toast(msg, 'success'); A.render(); return true; }
    if (res.conflict) { A.openModal('conflict', { subId: id }); return false; }
    A.toast(res.error, 'error');
    return false;
  }
  function confirmBox(opts) { A.openModal('confirm', opts); }

  // ---------- modals ----------
  M.confirm = {
    title: (d) => esc(d.title),
    body: (d) => d.body,
    foot: (d) => `<button class="btn" data-act="close-modal">Cancel</button><button class="btn ${d.tone || 'primary'}" data-act="confirm-ok">${esc(d.ok)}</button>`,
  };

  M.conflict = {
    title: () => 'This submission changed',
    body: (d) => `<div class="notice error">${icon('refresh')}<div><strong>This submission changed since you opened it. Refresh to see the latest version.</strong><div class="small" style="margin-top:4px">Your action was not applied, so nothing was overwritten. Another Legal user may have acted on it.</div></div></div>`,
    foot: (d) => `<button class="btn" data-act="close-modal">Close</button><button class="btn primary" data-act="refresh-sub" data-sub="${esc(d.subId)}" data-close="1">${icon('refresh')}Refresh submission</button>`,
  };

  M.upload = {
    title: (d) => `Upload — ${esc(A.slotLabel(d.key))}`,
    body: (d) => {
      let accept; let context;
      if (d.mode === 'new') {
        accept = ['pdf', 'docx'];
        context = UI.newForm.files[d.key] ? `This replaces <strong>${esc(UI.newForm.files[d.key].filename)}</strong> in your form. Before submission, files can be replaced freely.` : 'Attach this document to your submission.';
      } else {
        const s = A.sub(d.subId);
        const rule = A.uploadRule(A.me(), s, d.key);
        if (!rule.ok) return `<div class="notice warn">${icon('lock')}<div>${esc(rule.reason)}</div></div>`;
        accept = rule.accept;
        const n = (s.slots[d.key] ? s.slots[d.key].versions.length : 0);
        context = n ? `This becomes <strong>v${n + 1} · ${esc(rule.label)}</strong>. The current v${n} and all earlier versions are kept.` : `This becomes <strong>v1 · ${esc(rule.label)}</strong>.`;
        if (d.key === 'stamp_certificate') context += ' The certificate is its own document — it never replaces the Agreement.';
      }
      const base = baseName(d);
      const files = A.sampleFiles(base, accept);
      d.files = files;
      return `<div class="notice info small" style="margin-bottom:12px">${icon('info')}<div><strong>Prototype:</strong> choose a fictional sample file. Nothing is read from your computer — please don't use real agreements for this review.</div></div>
        <p>${context}</p>
        <p class="small muted" style="margin:6px 0 12px">Accepted: ${accept.length === 1 ? '<strong>PDF only</strong>' : '<strong>PDF or Word (.docx)</strong>'}, up to 20 MB. Not accepted: images, ZIP archives, macro-enabled Word files, executables.</p>
        <div class="choice-list" role="radiogroup" aria-label="Sample files">${files.map((f, i) => `<label class="choice ${d.pick === i ? 'selected' : ''}"><input type="radio" name="sample" data-act="upload-pick" data-i="${i}" ${d.pick === i ? 'checked' : ''}>
          ${A.fileIco(f.filename.split('.').pop())}<div><div class="file-name">${esc(f.filename)}</div><div class="file-meta">${A.size(f.sizeMB)} · ${esc(f.note)}</div></div></label>`).join('')}</div>`;
    },
    foot: (d) => `<button class="btn" data-act="close-modal">Cancel</button><button class="btn primary" data-act="upload-confirm" ${d.pick == null ? 'disabled' : ''}>${icon('upload')}Upload</button>`,
  };
  function baseName(d) {
    const partner = (d.mode === 'new' ? UI.newForm.partner : A.sub(d.subId).partner) || 'Partner';
    const p = partner.split(/[\s.,]+/).filter(Boolean).slice(0, 2).join('');
    const slot = { agreement: 'Agreement', memo: 'Memo', requisition: 'Requisition_Form', due_diligence: 'Due_Diligence_Form', company_profile: 'Company_Profile', ssm: 'SSM_Company_Info', business_reg: 'Business_Registration', stamp_certificate: 'LHDN_Stamp_Certificate' }[d.key];
    if (d.mode !== 'new') {
      const s = A.sub(d.subId);
      const rule = A.uploadRule(A.me(), s, d.key);
      if (rule.label === 'UniKL Signed') return `${slot}_${p}_UniKL_signed`;
      if (rule.label === 'Both Parties Signed') return `${slot}_${p}_both_signed`;
      if (rule.label === 'Revised During Legal Review') return `${slot}_${p}_revised`;
    }
    return `${slot}_${p}`;
  }

  M.request = {
    wide: true,
    title: (d) => A.sub(d.subId).status === 'final_verification' ? 'Return final package for correction' : 'Request clarification or revision',
    body: (d) => {
      const s = A.sub(d.subId);
      const final = s.status === 'final_verification';
      const options = final
        ? ['agreement'].concat(A.stampingApplies(s) === true ? ['stamp_certificate'] : [])
        : A.slotList(s).filter((x) => !x.execution && !x.retired).map((x) => x.key);
      const locked = options.filter((k) => !d.slots.includes(k));
      return `<p class="muted">${final ? 'Tell the requester exactly what is wrong with the final package. Only the documents you select are reopened. The review-completion milestone stays recorded.' : 'Ask for a written clarification, replacement documents, or both. Only the documents you select are reopened — everything else stays locked.'}</p>
        <div class="stack" style="margin-top:14px">
          <label class="check"><input type="checkbox" data-bind="modal.data.clarify" data-live="modal" ${d.clarify ? 'checked' : ''}><span><strong>Ask for a written clarification</strong><br><span class="small muted">The requester must answer in writing before they can submit their response.</span></span></label>
          <fieldset><legend>Documents to replace</legend><div class="choice-list">
            ${options.map((k) => { const v = A.currentVersion(s, k); return `<label class="check choice ${d.slots.includes(k) ? 'selected' : ''}"><input type="checkbox" data-act="request-slot" data-key="${k}" ${d.slots.includes(k) ? 'checked' : ''}><span><strong>${esc(A.slotLabel(k))}</strong><br><span class="small muted">${v ? `Current: v${A.versionNo(s, k, v)} · ${esc(v.filename)}` : 'No file yet'}</span></span></label>`; }).join('')}
          </div></fieldset>
          <label class="field"><span class="label">Instructions for the requester <span class="req">*</span></span>
            <textarea class="textarea" data-bind="modal.data.text" placeholder="Be specific: what is wrong, and exactly what you need from the requester." style="min-height:110px">${esc(d.text)}</textarea>
            <div class="hint">Required. The requester sees this as an Action Required card in the Conversation and on their submission.</div></label>
          <div class="notice neutral small">${icon('info')}<div>Sending moves the submission to <strong>Action Required from Requester</strong>. ${d.slots.length ? `Reopens: <strong>${d.slots.map((k) => esc(A.slotLabel(k))).join(', ')}</strong>.` : 'No document is reopened.'} ${locked.length ? `Stays locked: ${locked.map((k) => esc(A.slotLabel(k))).join(', ')}.` : ''} It returns to ${final ? 'Final Verification' : 'In Review'} only when the requester selects Submit Response.</div></div>
        </div>`;
    },
    foot: (d) => `<button class="btn" data-act="close-modal">Cancel</button><button class="btn maroon" data-act="request-send">${icon('flag')}Send request</button>`,
  };

  M.classify = {
    wide: true,
    title: () => 'Correct classification',
    body: (d) => {
      const s = A.sub(d.subId);
      const before = A.requiredIntake(s.category, s.location);
      const after = A.requiredIntake(d.category, d.location);
      const keys = [...new Set(before.concat(after))];
      const has = (k) => s.slots[k] && s.slots[k].versions.length;
      const missing = after.filter((k) => !has(k));
      const typeLocked = s.type === 'ADDENDUM';
      const sel = (bind, opts, val, dis) => `<select class="select" data-bind="modal.data.${bind}" data-live="modal" ${dis ? 'disabled' : ''}>${opts.map((o) => `<option ${o === val ? 'selected' : ''}>${o}</option>`).join('')}</select>`;
      return `<p class="muted">Current: <strong>${esc(s.category)} · ${esc(s.location)} · ${esc(s.type)}</strong>. Correcting the classification recalculates the checklist. Existing documents and their history are always kept.</p>
        <div class="form-grid" style="margin-top:14px;grid-template-columns:1fr 1fr 1fr">
          <label class="field"><span class="label">Engagement category</span>${sel('category', ['Academic', 'Industry'], d.category)}</label>
          <label class="field"><span class="label">Partner location</span>${sel('location', ['Local', 'International'], d.location)}</label>
          <label class="field"><span class="label">Agreement type</span>${sel('type', typeLocked ? ['ADDENDUM'] : ['NDA', 'MOA', 'MOU'], d.type, typeLocked)}${typeLocked ? '<div class="hint">Changing to or from Addendum is not offered here.</div>' : ''}</label>
        </div>
        <div class="card" style="margin-top:14px"><div class="card-head"><h4 style="flex:1">Checklist after correction</h4><span class="chip">${esc(d.category)} · ${after.length} required</span></div>
          <div class="card-body stack-sm">${keys.map((k) => {
            const inAfter = after.includes(k); const inBefore = before.includes(k);
            let badge;
            if (inAfter && !inBefore) badge = has(k) ? '<span class="badge tone-green">Newly required — file exists</span>' : '<span class="badge tone-amber">Newly required — missing</span>';
            else if (inAfter) badge = '<span class="badge tone-grey plain">Still required — kept</span>';
            else badge = '<span class="chip">No longer required — kept with history</span>';
            return `<div class="row" style="justify-content:space-between"><span>${esc(A.slotLabel(k))}</span>${badge}</div>`;
          }).join('')}</div></div>
        <div class="notice ${missing.length ? 'warn' : 'neutral'} small" style="margin-top:12px">${icon(missing.length ? 'flag' : 'info')}<div>${missing.length ? `Saving moves the submission to <strong>Action Required from Requester</strong> and reopens only: <strong>${missing.map((k) => esc(A.slotLabel(k))).join(', ')}</strong>.` : 'No new documents are needed. The status does not change; the requester is told about the correction in the Conversation.'}</div></div>
        <label class="field" style="margin-top:12px"><span class="label">Reason for the correction <span class="req">*</span></span>
          <textarea class="textarea" data-bind="modal.data.reason" placeholder="Shared with the requester, e.g. The partner is a commercial company, so this is an Industry engagement.">${esc(d.reason)}</textarea></label>`;
    },
    foot: () => `<button class="btn" data-act="close-modal">Cancel</button><button class="btn primary" data-act="classify-save">${icon('check')}Save correction</button>`,
  };

  M.close = {
    title: () => 'Close as Not Proceeding',
    body: (d) => `<p class="muted">Use this when the requester withdraws, the partner declines, or Legal determines the agreement cannot proceed. Nothing is deleted: documents, versions, Conversation, Internal Legal Notes and audit history are all kept, and the requester can still view it.</p>
      <fieldset style="margin-top:14px"><legend>Why is it not proceeding? <span class="req" style="color:var(--maroon)">*</span></legend><div class="choice-list">
        ${['Requester withdrew', 'Partner declined', 'Legal determined it cannot proceed'].map((o) => `<label class="choice ${d.kind === o ? 'selected' : ''}"><input type="radio" name="closekind" value="${o}" data-bind="modal.data.kind" data-live="modal" ${d.kind === o ? 'checked' : ''}><span>${o}</span></label>`).join('')}</div></fieldset>
      <label class="field" style="margin-top:12px"><span class="label">Closure reason <span class="req">*</span></span><textarea class="textarea" data-bind="modal.data.text" placeholder="What happened? The requester will see this reason.">${esc(d.text)}</textarea></label>
      <p class="small muted" style="margin-top:8px">${icon('ban')} A Not Proceeding submission cannot create an Agreement Register record. Legal can reopen it later with a reason.</p>`,
    foot: () => `<button class="btn" data-act="close-modal">Cancel</button><button class="btn danger" data-act="close-confirm">${icon('ban')}Close as Not Proceeding</button>`,
  };

  M.reopen = {
    title: () => 'Reopen submission',
    body: (d) => {
      const s = A.sub(d.subId);
      return `<p class="muted">Reopening returns this submission to <strong>${esc(A.STATUS[s.prevStatus || 'in_review'].label)}</strong>, the stage it was closed from. The closure and its reason stay in the history.</p>
        <label class="field" style="margin-top:12px"><span class="label">Reason for reopening <span class="req">*</span></span><textarea class="textarea" data-bind="modal.data.text" placeholder="e.g. The partner has confirmed the placement will go ahead in January.">${esc(d.text)}</textarea></label>`;
    },
    foot: () => `<button class="btn" data-act="close-modal">Cancel</button><button class="btn primary" data-act="reopen-confirm">${icon('refresh')}Reopen</button>`,
  };

  M.link = {
    wide: true,
    title: () => 'Find the original agreement in the Register',
    body: (d) => {
      const S = A.state();
      const q = (d.q || '').trim().toLowerCase();
      const res = S.register.filter((r) => r.type !== 'ADDENDUM' || true).filter((r) => !q || [r.id, r.title, r.partner, r.pic, A.campusShort(r.campus)].join(' ').toLowerCase().includes(q));
      return `<p class="muted">Search the full Agreement Register (Legal only). Choose the agreement this Addendum modifies.</p>
        <label class="field" style="margin-top:12px"><span class="label">Search</span><input class="input" type="search" data-bind="modal.data.q" data-live="modal" value="${esc(d.q)}" placeholder="Partner, title, PIC or AGR number"></label>
        <div class="choice-list" style="margin-top:12px">${res.length ? res.map((r) => `<label class="choice ${d.pick === r.id ? 'selected' : ''}"><input type="radio" name="agrpick" data-act="link-pick" data-agr="${r.id}" ${d.pick === r.id ? 'checked' : ''}>
          <div style="flex:1"><div class="strong">${esc(r.title)} <span class="faint">· ${esc(r.id)}</span></div><div class="small muted">${esc(r.partner)} · ${esc(r.type)}${r.moaSubtype ? ' · ' + esc(r.moaSubtype) : ''} · ${esc(A.campusShort(r.campus))} · signed ${A.fmtDate(r.dateSigned)}</div></div>${A.regStatusBadge(r)}</label>`).join('') : '<p class="muted small">No Register records match.</p>'}</div>
        <div class="notice neutral small" style="margin-top:12px">${icon('info')}<div>Not in the Register? Add the original through the existing Register process first, then return here. The portal never creates an original record automatically.</div></div>`;
    },
    foot: (d) => `<button class="btn" data-act="close-modal">Cancel</button><button class="btn primary" data-act="link-save" ${d.pick ? '' : 'disabled'}>${icon('link')}Link as original agreement</button>`,
  };

  M.stamping = {
    title: () => 'LHDN stamping for this Addendum',
    body: (d) => `<p class="muted">Record whether this Addendum needs LHDN stamping. It is never assumed either way. LHDN guidance indicates that a binding Addendum can itself be a dutiable instrument, so decide per Addendum.</p>
      <div class="choice-list" style="margin-top:12px">
        <label class="choice ${d.value === 'yes' ? 'selected' : ''}"><input type="radio" name="stamp" value="yes" data-bind="modal.data.value" data-live="modal" ${d.value === 'yes' ? 'checked' : ''}><div><strong>Stamping required</strong><div class="small muted">After the partner signs, the requester must upload the LHDN stamp certificate before final verification.</div></div></label>
        <label class="choice ${d.value === 'no' ? 'selected' : ''}"><input type="radio" name="stamp" value="no" data-bind="modal.data.value" data-live="modal" ${d.value === 'no' ? 'checked' : ''}><div><strong>Stamping not required</strong><div class="small muted">The signed Addendum goes straight to final verification.</div></div></label>
      </div>`,
    foot: (d) => `<button class="btn" data-act="close-modal">Cancel</button><button class="btn primary" data-act="stamping-save" ${d.value ? '' : 'disabled'}>Record decision</button>`,
  };

  M.submitNew = {
    title: () => 'Submit to CLSD Legal?',
    body: () => {
      const f = UI.newForm;
      const req = A.requiredIntake(f.category, f.location);
      return `<dl class="kv"><dt>Title</dt><dd>${esc(f.title)}</dd><dt>Partner</dt><dd>${esc(f.partner)}</dd><dt>Classification</dt><dd>${esc(f.category)} · ${esc(f.location)} · ${esc(f.type)}${f.type === 'MOA' && f.moaSubtype ? ' · ' + esc(f.moaSubtype) : ''}</dd>
        ${f.type === 'ADDENDUM' ? `<dt>Original</dt><dd>${f.addMode === 'selected' ? esc(f.originalId) : 'Agreement not found — details provided'}</dd>` : ''}
        <dt>Documents</dt><dd>${req.map((k) => `${esc(A.slotLabel(k))}: ${esc(f.files[k].filename)}`).join('<br>')}</dd></dl>
        <div class="notice info small" style="margin-top:14px">${icon('lock')}<div>After you submit, your documents lock while Legal reviews them. If you spot a mistake, send Legal a message — they can reopen a document.</div></div>`;
    },
    foot: () => `<button class="btn" data-act="close-modal">Go back</button><button class="btn primary" data-act="submit-new-confirm">${icon('arrow')}Submit officially</button>`,
  };

  // ---------- guide ----------
  const J = (user, hash, label) => `<button class="btn sm" data-act="guide-jump" data-user="${user}" data-hash="${hash}">${label || 'Open'}</button>`;
  A.guideHtml = function () {
    const views = [
      ['My Submissions', 'Own records, unread, filters, Not Proceeding history, empty state', J('aisyah', '#/my')],
      ['New Submission', 'Classification, dynamic checklist, upload validation, readiness', J('aisyah', '#/new')],
      ['Requester Submission Workspace', 'Overview, documents & versions, Conversation, Activity', J('aisyah', '#/sub/SUB-2026-0041/overview')],
      ['Action Required', 'Legal instructions, answer, reopened vs locked slots, Submit Response', J('aisyah', '#/sub/SUB-2026-0038/overview')],
      ['Post-review Execution', 'UniKL-signed download, partner-signed upload, separate certificate', J('aisyah', '#/sub/SUB-2026-0036/overview')],
      ['Completed Submission', 'Registered, Fully Executed and Not Proceeding variants', `${J('aisyah', '#/sub/SUB-2026-0029/overview', 'Registered')} ${J('aisyah', '#/sub/SUB-2026-0031/overview', 'Fully Executed')} ${J('aisyah', '#/sub/SUB-2026-0035/overview', 'Not Proceeding')}`],
      ['Shared Submission Queue', 'All submissions, next actor, personal unread, last handler, filters', J('nadia', '#/queue')],
      ['Legal Review Workspace', 'Versions, recent-access warning, classification correction, requests', `${J('nadia', '#/sub/SUB-2026-0045/documents', 'Recent access')} ${J('nadia', '#/sub/SUB-2026-0044/overview', 'Classification')}`],
      ['Internal Legal Notes', 'Legal/Admin-only tab with append-only composer', J('nadia', '#/sub/SUB-2026-0038/notes')],
      ['Review & Signature Progression', 'UniKL signature, final verification, return, close/reopen', `${J('nadia', '#/sub/SUB-2026-0043/overview', 'UniKL signature')} ${J('nadia', '#/sub/SUB-2026-0040/overview', 'Final verification')}`],
      ['Guided Register Creation', 'Only after Fully Executed; pre-filled, confirmed, linked', J('nadia', '#/register-create/SUB-2026-0031')],
      ['Addendum Workflow', 'Requester picker & fallback; Legal link resolution & stamping decision', `${J('aisyah', '#/new/addendum', 'Requester form')} ${J('nadia', '#/sub/SUB-2026-0046/overview', 'Selected original')} ${J('nadia', '#/sub/SUB-2026-0039/overview', 'Not found')}`],
    ];
    const journeys = [
      ['1 · Academic MOU — clarification only, no stamping', 'SUB-2026-0041', [
        'As Nadia: open SUB-2026-0041 and select <strong>Start Review</strong>.',
        '<strong>Request clarification or revision</strong> → tick only “written clarification”, write an instruction, Send. No document is reopened.',
        'Switch to Aisyah: the unread badge and “Needs my action” appear. Answer, then <strong>Submit Response</strong>. (Sending an ordinary message instead does not change the status.)',
        'As Nadia or Hana: <strong>Complete Legal Review</strong> (Vice Chancellor signs Academic agreements).',
        'Upload the UniKL-signed PDF, then <strong>Send to requester for partner signature</strong>.',
        'As Aisyah: download the UniKL-signed version, upload the both-parties-signed PDF, <strong>Submit for final verification</strong> (no stamping for an MOU).',
        'As Legal: tick the verification check → <strong>Mark Fully Executed</strong> → <strong>Create Register Record</strong> → confirm.',
        'As Aisyah: the submission shows Registered with her linked Register summary only.',
      ], J('nadia', '#/sub/SUB-2026-0041/overview', 'Start as Nadia')],
      ['2 · Industry Local MOA — targeted replacement, certificate', 'SUB-2026-0038', [
        'As Aisyah: open SUB-2026-0038. Only the Due Diligence Form is reopened; five documents stay locked.',
        'Try a sample that fails (image, ZIP or 24.6 MB) — the current file is unchanged. Then upload a valid PDF, answer the clarification and <strong>Submit Response</strong>.',
        'Documents tab → Due Diligence Form → version history shows v1 and v2.',
        'As Legal: <strong>Complete Legal Review</strong> (CEO signs Industry agreements) → upload UniKL-signed PDF → send to requester.',
        'As Aisyah: upload the both-parties-signed PDF → <strong>Submit signed agreement &amp; continue to stamping</strong> → upload the standalone LHDN certificate (its own slot) → submit.',
        'As Legal: confirm both checks → <strong>Mark Fully Executed</strong> → register.',
      ], J('aisyah', '#/sub/SUB-2026-0038/overview', 'Start as Aisyah')],
      ['3 · Industry International — slot change, missing docs, correction', 'SUB-2026-0044', [
        'As Aisyah: New Submission → Industry, then switch Local ↔ International and watch document 6 change between SSM and business registration.',
        'Select <strong>Submit to CLSD Legal</strong> with documents missing — the readiness panel and missing slots are highlighted and nothing is submitted.',
        'As Nadia: open SUB-2026-0044 (submitted as Academic). More actions → <strong>Correct classification</strong> → Industry. The preview shows four newly required documents.',
        'Save: the submission moves to Action Required and reopens only the four new slots; existing files are kept.',
        'As Aisyah: upload the four documents and <strong>Submit Response</strong>.',
      ], `${J('aisyah', '#/new', 'Form as Aisyah')} ${J('nadia', '#/sub/SUB-2026-0044/overview', 'Correction as Nadia')}`],
      ['4 · Addendum — original link and stamping decision', 'SUB-2026-0046 / 0039', [
        'As Aisyah: New Submission → ADDENDUM. The picker lists only her two registered agreements. Choose <strong>Agreement not found</strong> to see the fallback fields.',
        'As Nadia: SUB-2026-0046 → <strong>Confirm link</strong> → <strong>Record stamping decision: required</strong>. The progress bar now includes LHDN stamping.',
        'As Nadia: SUB-2026-0039 (original not found) → <strong>Find in Agreement Register</strong> → search “Teras” → choose AGR-2025-006 → link. Record <strong>stamping not required</strong>.',
        'Note: Complete Legal Review is blocked until the stamping decision is recorded; Register creation is blocked until the original is confirmed.',
      ], `${J('aisyah', '#/new/addendum', 'Form as Aisyah')} ${J('nadia', '#/sub/SUB-2026-0039/overview', 'Resolve as Nadia')}`],
      ['5 · Not Proceeding — closure and reopening', 'SUB-2026-0035', [
        'As Aisyah: SUB-2026-0035 shows the closure reason, full history and no Register record. No Internal Legal Notes are visible.',
        'As Nadia: open it — the Internal Legal Notes tab exists only here. <strong>Reopen with a reason</strong>: it returns to In Review.',
        'More actions → <strong>Close as Not Proceeding</strong> with a reason. Register creation stays unavailable while it is closed.',
      ], `${J('aisyah', '#/sub/SUB-2026-0035/overview', 'As Aisyah')} ${J('nadia', '#/sub/SUB-2026-0035/overview', 'As Nadia')}`],
    ];
    return `<p class="small muted">Demo aid only — not a product feature. Views show the current demo state; <strong>Reset demo</strong> restores the seeded stages.</p>
      <h3 style="margin:14px 0 8px">Twelve approved views</h3>
      <ol class="guide-list">${views.map((v, i) => `<li><span class="num">${i + 1}</span><div style="flex:1"><div class="g-title">${v[0]}</div><div class="g-desc">${v[1]}</div><div class="row-wrap" style="margin-top:6px">${v[2]}</div></div></li>`).join('')}</ol>
      <h3 style="margin:18px 0 8px">Five walkthroughs</h3>
      ${journeys.map((j) => `<details class="journey"><summary>${j[0]} <span class="faint small">(${j[1]})</span></summary><ol>${j[2].map((x) => `<li>${x}</li>`).join('')}</ol><div class="row-wrap" style="padding:0 14px 12px">${j[3]}</div></details>`).join('')}
      <h3 style="margin:18px 0 8px">Also try</h3>
      <ul class="small" style="padding-left:18px;color:var(--text-2)">
        <li><strong>Personal unread:</strong> as Nadia open SUB-2026-0038 → Conversation. Switch to Hana: her unread count is unchanged. ${J('nadia', '#/sub/SUB-2026-0038/conversation', 'Open as Nadia')}</li>
        <li style="margin-top:6px"><strong>Stale action:</strong> as Legal open a dialog (e.g. Request clarification), type something, click <em>Simulate other Legal user's change</em> in this bar, then send. The action is refused, your text is kept, and you can refresh.</li>
        <li style="margin-top:6px"><strong>Requester isolation:</strong> Aisyah trying to open another requester's submission sees nothing. ${J('aisyah', '#/sub/SUB-2026-0045/overview', 'Try it')}</li>
      </ul>
      <p class="xsmall faint" style="margin-top:14px">Front-end role switching here is a simulation. In the real system every page and action is authorised on the server.</p>`;
  };

  // ---------- click handlers ----------
  const H = {};

  H['switch-user'] = (el) => {
    const id = el.dataset.user;
    const S = A.state();
    if (S.currentUser === id) return;
    S.currentUser = id; A.save();
    UI.viewRev = {}; UI.justRead = {}; UI.verifyChecks = {}; UI.modal = null;
    const u = A.me();
    const r = A.route();
    let target = A.home();
    if ((r.name === 'sub' || r.name === 'register-create') && A.sub(r.a) && A.canView(u, A.sub(r.a))) {
      const tab = r.name === 'register-create' ? 'overview' : (r.b === 'notes' && !A.isLegal(u) ? 'overview' : (r.b || 'overview'));
      target = `#/sub/${r.a}/${tab}`;
    }
    A.go(target);
    A.toast(`Demo: now viewing as ${u.name} (${A.isLegal(u) ? 'Legal' : 'Requesting Staff'}).`, 'demo');
  };
  H['open-guide'] = () => { UI.drawer = true; A.renderDrawer(); const d = document.querySelector('.drawer'); if (d) d.focus(); };
  H['close-guide'] = () => { UI.drawer = false; A.renderDrawer(); };
  H['guide-jump'] = (el) => {
    UI.drawer = false;
    const S = A.state();
    if (S.currentUser !== el.dataset.user) {
      S.currentUser = el.dataset.user; A.save();
      UI.viewRev = {}; UI.justRead = {}; UI.verifyChecks = {};
    }
    UI.modal = null;
    if (el.dataset.hash.startsWith('#/new')) { UI.newForm = null; }
    if (el.dataset.hash === '#/my') UI.previewEmpty = false;
    A.go(el.dataset.hash);
  };
  H['reset-demo'] = () => confirmBox({
    title: 'Reset the demo?', ok: 'Reset demo', tone: 'danger',
    body: '<p>This restores every fictional submission, message, version and unread count to its starting state. Nothing outside this prototype is affected.</p>',
    fn: () => {
      A.reset();
      Object.assign(UI, { viewRev: {}, expanded: {}, uploadErrors: {}, composer: {}, noteComposer: {}, responseDraft: {}, newForm: null, regForm: {}, justRead: {}, verifyChecks: {}, previewEmpty: false, drawer: false, busy: false });
      UI.myFilter = { tab: 'all', q: '' };
      UI.queueFilter = { tab: 'legal', q: '', status: '', category: '', campus: '', since: '' };
      UI.regFilter = { q: '', type: '' };
      A.closeModal();
      A.go(A.home());
      A.toast('Demo reset to its starting state.', 'demo');
    },
  });
  H['confirm-ok'] = () => { const fn = UI.modal && UI.modal.data.fn; if (fn) fn(); };
  H['simulate-other'] = (el) => {
    const res = A.actions.simulateOtherLegal(el.dataset.sub);
    if (!res.ok) { A.toast(res.error, 'error'); return; }
    A.toast(`Demo: ${res.what} in another session. Your page has not been refreshed — try an action now.`, 'demo');
    A.renderDemoBar(); // deliberately no page re-render: this session is now stale
  };
  H['close-modal'] = () => A.closeModal();
  H['modal-backdrop'] = (el, e) => { if (e.target === el) A.closeModal(); };
  H.blocked = (el) => A.toast(el.dataset.msg || 'Not available at this stage.', 'error');

  H['open-sub'] = (el, e) => { if (e.target.closest('a')) return; A.go(`#/sub/${el.dataset.sub}/overview`); };
  H['open-agr'] = (el, e) => { if (e.target.closest('a')) return; A.go(`#/register/${el.dataset.agr}`); };
  H['my-tab'] = (el) => { UI.myFilter.tab = el.dataset.tab; A.render(); };
  H['queue-tab'] = (el) => { UI.queueFilter.tab = el.dataset.tab; A.render(); };
  H['queue-clear'] = () => { Object.assign(UI.queueFilter, { q: '', status: '', category: '', campus: '', since: '' }); A.render(); };
  H['toggle-empty'] = () => { UI.previewEmpty = !UI.previewEmpty; A.render(); };

  // New submission
  H['upload-new'] = (el) => A.openModal('upload', { mode: 'new', key: el.dataset.key, pick: null });
  H['remove-new-file'] = (el) => { delete UI.newForm.files[el.dataset.key]; A.render(); };
  H['pick-original'] = (el) => {
    const f = UI.newForm;
    if (el.dataset.id) { f.addMode = 'selected'; f.originalId = el.dataset.id; } else { f.addMode = 'not_found'; f.originalId = ''; }
    rerender();
  };
  H['new-submit'] = () => {
    const f = UI.newForm;
    const missing = A.formChecks(f).filter((c) => !c.ok);
    if (missing.length) {
      f.tried = true; A.render();
      A.toast(`Not submitted: ${missing.length} item${missing.length === 1 ? ' is' : 's are'} missing. See the readiness panel.`, 'error');
      const first = missing.find((c) => c.field);
      const target = first && (document.getElementById(first.field) || document.querySelector(`[data-bind="newForm.${first.field}"]`) || document.querySelector('.field.invalid, .missing-highlight'));
      if (target) { target.scrollIntoView({ behavior: 'smooth', block: 'center' }); if (target.focus) target.focus({ preventScroll: true }); }
      return;
    }
    A.openModal('submitNew', {});
  };
  H['submit-new-confirm'] = () => {
    const res = A.actions.createSubmission(UI.newForm);
    if (!res.ok) { UI.modal.error = res.error; A.renderModal(); return; }
    UI.newForm = null; A.closeModal();
    A.go(`#/sub/${res.id}/overview`);
    A.toast(`Submitted as ${res.id}. Legal has been notified in the portal queue.`, 'success');
  };

  // Documents
  H.upload = (el) => A.openModal('upload', { mode: 'sub', subId: el.dataset.sub, key: el.dataset.key, pick: null });
  H['upload-pick'] = (el) => { UI.modal.data.pick = Number(el.dataset.i); UI.modal.error = null; A.renderModal(); };
  H['upload-confirm'] = () => {
    const d = UI.modal.data;
    const file = d.files[d.pick];
    if (d.mode === 'new') {
      const err = A.validateFile(file, ['pdf', 'docx']);
      if (err) { UI.uploadErrors['new:' + d.key] = err; UI.modal.error = `Upload failed: ${err} ${UI.newForm.files[d.key] ? 'Your current file is unchanged.' : ''} Choose another file.`; A.render(); return; }
      const hadFile = !!UI.newForm.files[d.key];
      UI.newForm.files[d.key] = { filename: file.filename, sizeMB: file.sizeMB };
      delete UI.uploadErrors['new:' + d.key];
      A.closeModal(); A.render();
      A.toast(`${hadFile ? 'Replaced' : 'Attached'} ${A.slotLabel(d.key)}: ${file.filename}`, 'success');
      return;
    }
    const res = A.actions.upload(d.subId, d.key, file, rev(d.subId));
    if (!res.ok) {
      if (res.validation) {
        UI.uploadErrors[d.subId + ':' + d.key] = res.error;
        UI.modal.error = `Upload failed: ${res.error} The current file is unchanged. Choose another file or cancel.`;
        A.render();
        return;
      }
      A.modalResult(res);
      return;
    }
    delete UI.uploadErrors[d.subId + ':' + d.key];
    after(d.subId, res);
    const n = A.sub(d.subId).slots[d.key].versions.length;
    A.modalResult(res, `Uploaded ${A.slotLabel(d.key)} as v${n}.${n > 1 ? ' Earlier versions are kept.' : ''}`);
  };
  H.download = (el) => {
    const id = el.dataset.sub; const key = el.dataset.key; const n = Number(el.dataset.n);
    const res = A.actions.download(id, key, n);
    if (!res.ok) { A.toast(res.error, 'error'); return; }
    A.downloadBlob(res.version, key, n, A.sub(id));
    A.toast(`Simulated download of ${A.slotLabel(key)} v${n} — recorded in Activity.`, 'success');
    A.render();
  };
  H['toggle-versions'] = (el) => { const k = el.dataset.sub + ':' + el.dataset.key; UI.expanded[k] = !UI.expanded[k]; A.render(); };

  // Conversation & notes
  H['send-message'] = (el) => {
    const id = el.dataset.sub;
    const res = A.actions.sendMessage(id, UI.composer[id]);
    if (!res.ok) { A.toast(res.error, 'error'); return; }
    UI.composer[id] = ''; after(id, res); A.render();
    A.toast('Message sent. The submission status has not changed.', 'success');
  };
  H['add-note'] = (el) => {
    const id = el.dataset.sub;
    const res = A.actions.addNote(id, UI.noteComposer[id]);
    if (!res.ok) { A.toast(res.error, 'error'); return; }
    UI.noteComposer[id] = ''; after(id, res); A.render();
    A.toast('Internal note added (Legal and Admin only).', 'success');
  };

  // Legal review
  H['start-review'] = (el) => done(A.actions.startReview(el.dataset.sub, rev(el.dataset.sub)), el.dataset.sub, 'Review started — status is now In Review.');
  H['complete-review'] = (el) => {
    const id = el.dataset.sub; const s = A.sub(id);
    const b = A.reviewBlockers(s);
    if (b.length) { A.toast(b.join(' '), 'error'); return; }
    confirmBox({
      subId: id, title: 'Complete Legal Review?', ok: 'Complete Legal Review',
      body: `<p>This records the <strong>Review Completed</strong> milestone. Next, Legal obtains the <strong>${esc(A.signatory(s))}</strong>'s signature.</p><p class="small muted" style="margin-top:8px">It does not create an Agreement Register record and is not the same as Fully Executed.</p>`,
      fn: () => A.modalResult(A.actions.completeReview(id, rev(id)), 'Review completed — awaiting UniKL signature.') && after(id, { ok: true, rev: A.sub(id).rev }),
    });
  };
  H['open-request'] = (el) => A.openModal('request', { subId: el.dataset.sub, clarify: false, slots: [], text: '' });
  H['request-slot'] = (el) => {
    const d = UI.modal.data; const k = el.dataset.key;
    d.slots = el.checked ? d.slots.concat([k]) : d.slots.filter((x) => x !== k);
    rerender('modal');
  };
  H['request-send'] = () => {
    const d = UI.modal.data;
    const res = A.actions.requestAction(d.subId, { clarify: d.clarify, slots: d.slots, text: d.text }, rev(d.subId));
    if (res.ok) after(d.subId, res);
    A.modalResult(res, 'Request sent — waiting for the requester.');
  };
  H['open-classify'] = (el) => { const s = A.sub(el.dataset.sub); A.openModal('classify', { subId: s.id, category: s.category, location: s.location, type: s.type, reason: '' }); };
  H['classify-save'] = () => {
    const d = UI.modal.data;
    const res = A.actions.correctClassification(d.subId, { category: d.category, location: d.location, type: d.type }, d.reason, rev(d.subId));
    if (res.ok) after(d.subId, res);
    A.modalResult(res, A.sub(d.subId).status === 'action_required' ? 'Classification corrected — missing documents requested from the requester.' : 'Classification corrected.');
  };
  H['send-partner'] = (el) => {
    const id = el.dataset.sub;
    if (!A.unikSignedVersion(A.sub(id))) { A.toast('Upload the UniKL-signed agreement PDF first.', 'error'); return; }
    done(A.actions.sendForPartner(id, rev(id)), id, 'Sent to the requester for the partner\'s signature.');
  };
  H['verify-check'] = (el) => { const c = UI.verifyChecks[el.dataset.sub]; c[el.dataset.key] = el.checked; A.render(); };
  H['verify-final'] = (el) => {
    const id = el.dataset.sub; const s = A.sub(id);
    const c = UI.verifyChecks[id] || {};
    const b = A.finalBlockers(s);
    if (b.length) { A.toast(b.join(' '), 'error'); return; }
    if (!c.agreement || (A.stampingApplies(s) === true && !c.certificate)) { A.toast('Confirm each verification check first.', 'error'); return; }
    confirmBox({
      subId: id, title: 'Mark Fully Executed?', ok: 'Mark Fully Executed',
      body: '<p>This confirms the final signed package. Replacement uploads are then locked; downloads and history remain available.</p><p class="small muted" style="margin-top:8px">The Agreement Register record is <strong>not</strong> created automatically — you will create it in the next step.</p>',
      fn: () => { const r = A.actions.verifyFinal(id, c, rev(id)); if (r.ok) after(id, r); A.modalResult(r, 'Fully Executed. You can now create the Register record.'); },
    });
  };
  H['open-close'] = (el) => A.openModal('close', { subId: el.dataset.sub, kind: '', text: '' });
  H['close-confirm'] = () => {
    const d = UI.modal.data;
    if (!d.kind) { UI.modal.error = 'Choose why it is not proceeding.'; A.renderModal(); return; }
    const res = A.actions.closeNotProceeding(d.subId, d.text && d.text.trim() ? `${d.kind}: ${d.text.trim()}` : '', rev(d.subId));
    if (res.ok) after(d.subId, res);
    A.modalResult(res, 'Closed as Not Proceeding. History is kept.');
  };
  H['open-reopen'] = (el) => A.openModal('reopen', { subId: el.dataset.sub, text: '' });
  H['reopen-confirm'] = () => {
    const d = UI.modal.data;
    const res = A.actions.reopen(d.subId, d.text, rev(d.subId));
    if (res.ok) after(d.subId, res);
    A.modalResult(res, 'Submission reopened.');
  };

  // Addendum
  H['confirm-link'] = (el) => done(A.actions.confirmLink(el.dataset.sub, el.dataset.agr, rev(el.dataset.sub)), el.dataset.sub, `Original agreement ${el.dataset.agr} confirmed.`);
  H['open-link'] = (el) => {
    const s = A.sub(el.dataset.sub);
    const hint = s.addendum.notFound ? s.addendum.notFound.partner : s.partner;
    A.openModal('link', { subId: s.id, q: hint.split(' ')[0], pick: null });
  };
  H['link-pick'] = (el) => { UI.modal.data.pick = el.dataset.agr; A.renderModal(); };
  H['link-save'] = () => {
    const d = UI.modal.data;
    const res = A.actions.confirmLink(d.subId, d.pick, rev(d.subId));
    if (res.ok) after(d.subId, res);
    A.modalResult(res, `Linked to original agreement ${d.pick}.`);
  };
  H['open-stamping'] = (el) => {
    const s = A.sub(el.dataset.sub);
    const v = s.addendum.stampingRequired;
    A.openModal('stamping', { subId: s.id, value: v == null ? '' : v ? 'yes' : 'no' });
  };
  H['stamping-save'] = () => {
    const d = UI.modal.data;
    const res = A.actions.decideStamping(d.subId, d.value === 'yes', rev(d.subId));
    if (res.ok) after(d.subId, res);
    A.modalResult(res, `Stamping decision recorded: ${d.value === 'yes' ? 'required' : 'not required'}.`);
  };

  // Requester workflow
  H['submit-response'] = (el) => {
    const id = el.dataset.sub;
    const res = A.actions.submitResponse(id, UI.responseDraft[id]);
    if (!res.ok) { A.toast(res.error, 'error'); return; }
    UI.responseDraft[id] = '';
    A.render();
    A.toast('Response submitted — Legal has been notified and the review resumes.', 'success');
  };
  H['submit-signed'] = (el) => {
    const id = el.dataset.sub;
    const res = A.actions.submitSigned(id);
    if (!res.ok) { A.toast(res.error, 'error'); return; }
    A.render();
    A.toast(A.sub(id).status === 'awaiting_stamping' ? 'Signed agreement submitted. Next: LHDN stamping.' : 'Submitted for final verification.', 'success');
  };
  H['submit-certificate'] = (el) => {
    const res = A.actions.submitCertificate(el.dataset.sub);
    if (!res.ok) { A.toast(res.error, 'error'); return; }
    A.render();
    A.toast('Submitted for final verification.', 'success');
  };

  // Register creation
  H['register-review'] = (el) => {
    const id = el.dataset.sub; const f = UI.regForm[id];
    f.tried = true;
    const e = A.registerFieldErrors(f);
    if (Object.keys(e).length) { A.render(); A.toast('Complete the highlighted fields.', 'error'); return; }
    A.openModal('registerConfirm', { subId: id, busy: false });
  };
  H['register-confirm'] = (el) => {
    const d = UI.modal.data;
    if (d.busy || UI.busy) return; // duplicate-click guard
    d.busy = true; UI.busy = true; A.renderModal();
    setTimeout(() => {
      const res = A.actions.createRegister(d.subId, UI.regForm[d.subId], rev(d.subId));
      UI.busy = false;
      if (!res.ok) { d.busy = false; if (res.conflict) { UI.modal.conflict = true; } else { UI.modal.error = res.error; } A.renderModal(); return; }
      after(d.subId, res);
      A.closeModal();
      A.render();
      A.toast(`Register record ${res.agrId} created and linked.`, 'success');
    }, 700);
  };

  H['refresh-sub'] = (el) => {
    const id = el.dataset.sub; const s = A.sub(id);
    if (s) UI.viewRev[id] = s.rev;
    if (el.dataset.close) A.closeModal();
    else if (UI.modal) { UI.modal.conflict = false; UI.modal.error = null; }
    A.render();
    A.toast('Refreshed — you are now looking at the latest version.', 'success');
  };

  // ---------- wiring ----------
  document.addEventListener('click', (e) => {
    const el = e.target.closest('[data-act]');
    if (!el) return;
    const act = el.dataset.act;
    const fn = H[act];
    if (!fn) return;
    if (el.tagName === 'BUTTON' || el.tagName === 'A') e.preventDefault();
    if (el.getAttribute('disabled') != null) return;
    fn(el, e);
  });

  function onBind(e) {
    const el = e.target;
    const path = el.getAttribute && el.getAttribute('data-bind');
    if (!path) return;
    let value;
    if (el.type === 'checkbox') value = el.checked;
    else if (el.type === 'radio') { if (!el.checked) return; value = el.value; }
    else value = el.value;
    setPath(UI, path, value);
    const live = el.getAttribute('data-live');
    const isText = el.tagName === 'TEXTAREA' || (el.tagName === 'INPUT' && !['radio', 'checkbox', 'date'].includes(el.type));
    if (e.type === 'input' && !isText) return; // selects/radios handled on change
    // Text fields render on 'input'. Their 'change' repeats the same value and fires while render() is
    // removing the focused field, so handling it would nest a second render inside the first.
    if (e.type === 'change' && isText) return;
    if (path.startsWith('newForm.location') || path.startsWith('newForm.category')) UI.newForm.changed = A.requiredIntake(UI.newForm.category, UI.newForm.location).slice(-1)[0];
    if (live === 'rerender') rerender();
    else if (live === 'modal') rerender('modal');
    else if (live === 'readiness') { const r = document.getElementById('readiness'); if (r && UI.newForm) r.innerHTML = A.readinessHtml(UI.newForm); if (el.tagName === 'SELECT') rerender(); }
    else if (live === 'response') { rerender(); }
  }
  document.addEventListener('input', onBind);
  document.addEventListener('change', onBind);

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      if (UI.modal) { A.closeModal(); } else if (UI.drawer) { UI.drawer = false; A.renderDrawer(); }
    }
  });

  let lastSubCtx = null;
  A.beforeRender = function (r) {
    const ctx = (r.name === 'sub' || r.name === 'register-create') ? r.a : null;
    if (ctx !== lastSubCtx) { if (ctx) delete UI.viewRev[ctx]; lastSubCtx = ctx; }
    if (r.name === 'sub') {
      const s = A.sub(r.a); const u = A.me();
      if (s && A.canView(u, s)) {
        if ((r.b || 'overview') === 'conversation') {
          const un = s.messages.filter((m) => m.by !== u.id && !m.readBy.includes(u.id)).map((m) => m.id);
          if (un.length) UI.justRead[s.id] = (UI.justRead[s.id] || []).concat(un);
          A.actions.markRead(s.id);
        } else {
          delete UI.justRead[s.id];
        }
      }
    }
    if (r.name !== 'new' && UI.newForm && !UI.newForm.keep) { /* drafts are not saved: form resets when leaving */ UI.newForm = null; }
  };

  const baseRender = A.render;
  A.render = function () {
    A.beforeRender(A.route());
    baseRender();
  };

  window.addEventListener('hashchange', () => { UI.modal = null; A.render(); window.scrollTo(0, 0); document.getElementById('main').focus({ preventScroll: true }); });

  A.load();
  if (!location.hash) location.replace(A.home());
  A.render();
})();
