/* Submission workspace, shared by both roles.
   Requester: views 3 (workspace), 4 (Action Required), 5 (execution), 6 (completed / closed).
   Legal:     views 8 (review workspace), 9 (Internal Legal Notes), 10 (review & signature progression),
              12 (Addendum link resolution and stamping decision).
   Internal Legal Notes are rendered ONLY by notesTab(), which is unreachable for Requesting Staff. */
(function () {
  'use strict';
  const A = window.AMS;
  const UI = A.UI;
  const esc = A.esc;
  const icon = (n, c) => A.icon(n, c);
  UI.verifyChecks = UI.verifyChecks || {};
  UI.justRead = UI.justRead || {};

  function notAvailable() {
    return `<div class="page"><div class="card empty">${icon('lock')}<h3>Submission not available</h3>
      <p>This submission does not exist or you do not have access to it.</p>
      <p style="margin-top:14px"><a class="btn" href="${A.home()}">Back to ${A.isLegal(A.me()) ? 'Submission Queue' : 'My Submissions'}</a></p></div></div>`;
  }

  A.views.workspace = function (id, tab) {
    const s = A.sub(id);
    const u = A.me();
    if (!s || !A.canView(u, s)) return notAvailable();
    const legal = A.isLegal(u);
    if (!legal && tab === 'notes') tab = 'overview';
    if (UI.viewRev[id] == null) UI.viewRev[id] = s.rev;

    const crumbs = legal ? `<a href="#/queue">Submission Queue</a> / ${esc(s.id)}` : `<a href="#/my">My Submissions</a> / ${esc(s.id)}`;
    const headState = !legal && s.status === 'action_required'
      ? '<span class="badge tone-amber">Action required</span>'
      : !legal && ['registered', 'not_proceeding'].includes(s.status)
        ? A.statusBadge(s)
        : `${A.statusBadge(s)} ${A.nextActorHtml(s, u)}`;
    const head = `<div class="crumbs">${crumbs}</div>
      <div class="ws-head">
        <div class="ws-title"><div class="ws-id">${esc(s.id)} · ${esc(A.TYPE_NAMES[s.type])}</div><h1>${esc(s.title)}</h1>
          <div class="ws-meta">${headState} <span class="chip">${A.typeLabel(s)}</span> <span class="chip">${esc(s.category)} · ${esc(s.location)}</span> <span class="chip">${esc(A.campusShort(s.campus))}</span> ${A.pcCue()}</div>
        </div>
      </div>`;

    const panel = legal ? legalPanel(s, u) : requesterPanel(s, u);
    const addCard = s.type === 'ADDENDUM' ? addendumCard(s, u) : '';
    const un = A.unreadCount(u, s) + (UI.justRead[id] ? UI.justRead[id].length : 0);
    const docCount = A.slotList(s).filter((x) => !x.undecided).length;
    const tabs = [
      ['overview', 'Overview', 'file', ''],
      ['documents', 'Documents', 'file', `<span class="chip">${docCount}</span>`],
      ['conversation', 'Conversation', 'message', A.unreadCount(u, s) ? `<span class="unread-dot">${A.unreadCount(u, s)}</span>` : `<span class="chip">${s.messages.length}</span>`],
    ];
    if (legal) tabs.push(['notes', 'Internal Legal Notes', 'shield', `<span class="chip">${s.notes.length}</span>`]);
    tabs.push(['activity', legal ? 'Activity & Audit' : 'Activity', 'activity', '']);

    const body = { overview: overviewTab, documents: documentsTab, conversation: conversationTab, notes: notesTab, activity: activityTab }[tab] || overviewTab;

    return `<div class="page">${head}${A.progress(s)}
      <div class="grid-2">
        <div>
          ${panel}
          ${addCard}
          <div class="tabs" role="tablist">${tabs.map((t) => `<a class="tab ${t[0] === 'notes' ? 'legal-only' : ''}" role="tab" href="#/sub/${s.id}/${t[0]}" aria-selected="${tab === t[0]}">${icon(t[2])}${t[1]} ${t[3]}</a>`).join('')}</div>
          <div class="tab-panel" role="tabpanel">${body(s, u)}</div>
        </div>
        <div class="stack">${sidePanel(s, u)}</div>
      </div></div>`;
  };

  // ---------- side panel ----------
  function sidePanel(s, u) {
    const legal = A.isLegal(u);
    const req = A.user(s.requesterId);
    return `<div class="card card-pad side-card"><h3>Submission details</h3>
        <dl class="kv">
          <dt>Requester</dt><dd>${legal ? `${esc(req.name)}<div class="xsmall muted">${esc(req.title)}</div>` : 'You'}</dd>
          <dt>Campus / dept.</dt><dd>${esc(A.campusShort(s.campus))}</dd>
          <dt>Partner</dt><dd>${esc(s.partner)}</dd>
          <dt>Category</dt><dd>${esc(s.category)}</dd>
          <dt>Partner location</dt><dd>${esc(s.location)}</dd>
          <dt>Agreement type</dt><dd>${esc(s.type)}</dd>
          ${s.type === 'MOA' ? `<dt>MOA subtype</dt><dd>${s.moaSubtype ? esc(s.moaSubtype) : '<span class="faint">Not given</span>'}</dd>` : ''}
          <dt>UniKL signatory</dt><dd>${esc(A.signatory(s))}</dd>
          <dt>LHDN stamping</dt><dd>${stampText(s)}</dd>
          <dt>Submitted</dt><dd>${A.fmt(s.submittedAt)}</dd>
          <dt>Last update</dt><dd>${A.fmt(s.updatedAt)}</dd>
          ${legal ? `<dt>Last handled by</dt><dd>${s.lastHandler ? esc(A.personName(s.lastHandler)) : '<span class="faint">Not yet handled</span>'}<div class="xsmall muted">Information only</div></dd>` : ''}
          ${s.agreementId ? `<dt>Register record</dt><dd>${legal ? `<a href="#/register/${s.agreementId}">${esc(s.agreementId)}</a>` : esc(s.agreementId)}</dd>` : ''}
        </dl></div>
      ${legal ? `<div class="notice neutral small">${icon('users')}<div>Shared queue. Anyone in Legal can act on this submission.</div></div>` : `<div class="notice neutral small">${icon('lock')}<div>Visible only to you and CLSD Legal. To withdraw, message Legal.</div></div>`}`;
  }
  function stampText(s) {
    const st = A.stampingApplies(s);
    if (s.type === 'MOA') return 'Required (MOA)';
    if (s.type === 'ADDENDUM') return st === null ? '<span style="color:var(--amber)">Awaiting Legal decision</span>' : st ? 'Required — Legal decision' : 'Not required — Legal decision';
    return `Not required (${esc(s.type)})`;
  }

  // ---------- requester next-step panels ----------
  function infoCard(kind, kicker, title, text, extra) {
    return `<div class="card card-pad next-card ${kind}"><div class="kicker">${esc(kicker)}</div><h2>${title}</h2><p class="muted" style="margin-top:6px">${text}</p>${extra || ''}</div>`;
  }

  function requesterPanel(s, u) {
    const req = A.openRequest(s);
    switch (s.status) {
      case 'pending_review':
        return infoCard('', 'Waiting for Legal', 'Legal will review your submission', 'No action needed. Use Conversation to report a mistake.');
      case 'in_review':
        return infoCard('', 'Next step · Waiting for Legal', 'Legal is reviewing your submission', 'You don\'t need to do anything now. Legal will contact you here if they need a clarification or a revised document.');
      case 'action_required': return actionRequiredPanel(s, u, req);
      case 'review_completed':
        return infoCard('', 'Next step · Waiting for Legal', `Review completed — Legal is obtaining the ${esc(A.signatory(s))}'s signature`, `Legal approved the contents on ${A.fmt(s.milestones.reviewCompletedAt)}. Once UniKL has signed, you'll be asked to download the signed agreement and get the partner's signature.`);
      case 'awaiting_partner':
      case 'awaiting_stamping': return executionPanel(s, u);
      case 'final_verification':
        return infoCard('', 'Next step · Waiting for Legal', 'Legal is verifying your final documents', 'Legal is checking the signatures' + (A.stampingApplies(s) ? ' and the LHDN stamp certificate' : '') + '. Uploads are locked; send a message if something is wrong.');
      case 'fully_executed':
      case 'registered':
      case 'not_proceeding': return completedPanel(s, u);
      default: return '';
    }
  }

  // View 4 — Action Required
  function actionRequiredPanel(s, u, req) {
    if (!req) return '';
    const r = A.responseReadiness(s);
    const draft = UI.responseDraft[s.id] || '';
    const all = A.slotList(s).filter((x) => !x.execution && !x.retired).map((x) => x.key);
    const locked = all.filter((k) => !req.request.slots.includes(k));
    const finalPhase = req.request.phase === 'final';
    const remaining = [];
    if (r.needAnswer && draft.trim().length < 3) remaining.push('written answer');
    req.request.slots.filter((k) => !r.done.includes(k)).forEach((k) => remaining.push(A.slotLabel(k)));
    const ready = remaining.length === 0;
    return `<div class="action-panel" id="action-required">
      <div class="ap-head"><h2>${finalPhase ? 'Correct your final documents' : 'Respond to Legal'}</h2>
        <div class="small muted" style="margin-top:4px">${esc(A.personName(req.by, true))} · ${A.fmt(req.at)}</div></div>
      <div class="ap-body stack">
        <div class="instruction">${esc(req.text)}</div>
        ${r.needAnswer ? `<label class="field"><span class="label">Your answer <span class="req">*</span></span>
          <textarea class="textarea" data-bind="responseDraft.${s.id}" data-live="response" placeholder="Type your answer">${esc(draft)}</textarea>
          </label>` : '<p class="small muted">No written answer needed.</p>'}
        ${req.request.slots.length ? `<div><div class="small strong" style="margin-bottom:6px">Upload ${req.request.slots.length === 1 ? 'this document' : 'these documents'}</div>
          ${req.request.slots.map((k) => {
            const cur = A.currentVersion(s, k);
            const done = r.done.includes(k);
            const err = UI.uploadErrors[s.id + ':' + k];
            return `<div class="slot-row ${done ? 'done' : ''}"><div><div class="strong">${icon(done ? 'check' : 'unlock')} ${esc(A.slotLabel(k))}${finalPhase ? ' <span class="xsmall muted">PDF only</span>' : ''}</div>
              <div class="small muted">${cur ? `${done ? 'New' : 'Current'}: v${A.versionNo(s, k, cur)} · ${esc(cur.filename)}` : 'No file uploaded'}</div>
              ${err ? `<div class="small" style="color:var(--red);margin-top:4px">${icon('alert')} Upload failed: ${esc(err)} ${cur ? 'Current file unchanged.' : ''}</div>` : ''}</div>
              <button class="btn sm ${done ? '' : 'primary'}" data-act="upload" data-sub="${s.id}" data-key="${k}">${icon('upload')}${done ? 'Replace again' : cur ? 'Upload replacement' : 'Upload'}</button></div>`;
          }).join('')}</div>` : ''}
        ${locked.length ? `<details class="disclose"><summary>${locked.length} other document${locked.length === 1 ? ' is' : 's are'} locked</summary>
          <p class="small muted" style="margin-top:8px">Legal did not reopen ${locked.length === 1 ? 'it' : 'these'}. To change ${locked.length === 1 ? 'it' : 'one'}, message Legal.</p>
          <div style="margin-top:8px">${locked.map((k) => `<div class="slot-row locked"><div>${icon('lock')} ${esc(A.slotLabel(k))}</div><span></span></div>`).join('')}</div></details>` : ''}
        <div class="hr"></div>
        <div class="row-wrap"><button class="btn maroon lg" data-act="submit-response" data-sub="${s.id}" aria-describedby="response-status" ${ready ? '' : 'aria-disabled="true"'}>${icon('arrow')}Submit Response</button>
          <div class="small" id="response-status">${ready
            ? `<span style="color:var(--green)">${icon('check')} Ready to submit.</span>`
            : `<span style="color:var(--amber)">${icon('alert')} Still needed: ${esc(remaining.join(', '))}.</span>`}
            <div class="muted">Nothing is sent until you select <strong>Submit Response</strong>.</div></div></div>
      </div></div>`;
  }

  function requesterDownloaded(s, n) {
    return s.activity.some((a) => a.by === s.requesterId && a.text.startsWith(`Downloaded Agreement v${n} `));
  }

  // View 5 — post-review execution
  function executionPanel(s, u) {
    const stamp = A.stampingApplies(s);
    const vs = s.slots.agreement.versions;
    const unikIdx = vs.map((v) => v.label).lastIndexOf('UniKL Signed');
    const unik = vs[unikIdx];
    if (!unik) return infoCard('requester', 'Next step', 'Waiting for the UniKL-signed agreement', 'Legal has not uploaded the UniKL-signed version yet.');
    const signed = A.signedThisStage(s);
    const cert = A.certThisStage(s);
    const stage = s.status;
    const err = (k) => UI.uploadErrors[s.id + ':' + k];
    const stateLabel = { done: 'Completed: ', locked: 'Locked: ', active: '' };
    const step = (n, state, title, body) => `<li class="slot-row ${state === 'done' ? 'done' : state === 'locked' ? 'locked' : ''}" style="align-items:flex-start;grid-template-columns:minmax(0,1fr)">
      <div class="row" style="align-items:flex-start;gap:12px"><span class="cl-num" aria-hidden="true" style="flex:0 0 24px;${state === 'done' ? 'background:var(--green);color:#fff' : state === 'active' ? 'background:var(--navy);color:#fff' : ''}">${state === 'done' ? icon('check') : n}</span><div style="min-width:0"><div class="strong"><span class="sr-only">${stateLabel[state]}</span>${title}</div>${body}</div></div></li>`;
    const fileCard = (name, meta, btn) => `<div class="file" style="margin-top:8px">${A.fileIco('pdf')}<div><div class="file-name">${esc(name)}</div><div class="file-meta">${meta}</div>${btn || ''}</div></div>`;
    const uploadFailed = (k, note) => err(k) ? `<div class="small" style="color:var(--red);margin-top:4px">${icon('alert')} Upload failed: ${esc(err(k))} ${note}</div>` : '';
    const afterSigning = stage === 'awaiting_stamping';

    const uv = unikIdx + 1;
    const s1 = step(1, afterSigning || requesterDownloaded(s, uv) ? 'done' : 'active', 'Download the UniKL-signed agreement',
      afterSigning ? '' : fileCard(unik.filename, `v${uv} · Signed by the ${esc(A.signatory(s))} · ${A.fmt(unik.at)}`,
        `<button class="btn sm" style="margin-top:6px" data-act="download" data-sub="${s.id}" data-key="agreement" data-n="${uv}">${icon('download')}Download v${uv}</button>`));
    const s2 = step(2, signed ? 'done' : 'active', `Send v${uv} to ${esc(s.partner)} to sign`,
      afterSigning ? '' : '<div class="small muted">Outside the system. Do not use an earlier version.</div>');
    const s3 = step(3, signed ? 'done' : 'active', 'Upload the signed PDF',
      signed ? fileCard(signed.filename, `v${vs.length} · Signed by both parties · ${A.fmt(signed.at)}`,
        afterSigning ? '' : `<div class="small muted" style="margin-top:4px">Uploaded, not submitted yet.</div><button class="btn sm" style="margin-top:6px" data-act="upload" data-sub="${s.id}" data-key="agreement">${icon('upload')}Replace</button>`)
        : `<div class="small muted">PDF, up to 20 MB. Saved as a new Agreement version.</div>
           ${uploadFailed('agreement', 'The UniKL-signed version is unchanged.')}
           <button class="btn sm primary" style="margin-top:6px" data-act="upload" data-sub="${s.id}" data-key="agreement">${icon('upload')}Upload signed PDF</button>`);
    let s4 = '';
    if (stamp === true) {
      s4 = step(4, afterSigning ? (cert ? 'done' : 'active') : 'locked', 'Get LHDN stamping, then upload the certificate',
        !afterSigning ? '<div class="small">Unlocks after you submit the signed agreement.</div>'
          : cert ? fileCard(cert.filename, `LHDN Stamp Certificate v${s.slots.stamp_certificate.versions.length} · ${A.fmt(cert.at)}`,
              `<div class="small muted" style="margin-top:4px">Uploaded, not submitted yet.</div><button class="btn sm" style="margin-top:6px" data-act="upload" data-sub="${s.id}" data-key="stamp_certificate">${icon('upload')}Replace</button>`)
            : `<div class="small muted">Stamping happens outside the system. Upload the certificate as a separate PDF. It does not replace the signed agreement.</div>
               ${uploadFailed('stamp_certificate', 'Nothing was changed.')}
               <button class="btn sm primary" style="margin-top:6px" data-act="upload" data-sub="${s.id}" data-key="stamp_certificate">${icon('upload')}Upload stamp certificate</button>`);
    } else if (stamp === null) {
      s4 = step(4, 'locked', 'LHDN stamping', '<div class="small">Legal is deciding whether stamping is needed.</div>');
    }

    const remaining = [];
    let submitAct; let label; let after;
    if (stage === 'awaiting_partner') {
      if (!signed) remaining.push('signed PDF');
      if (stamp === null) remaining.push('Legal\'s stamping decision');
      submitAct = 'submit-signed';
      label = stamp === false ? 'Submit for final verification' : 'Submit signed agreement';
      after = stamp === true ? 'Next: LHDN stamping.' : stamp === false ? 'Next: Legal verifies your documents.' : 'Next step follows Legal\'s stamping decision.';
    } else {
      if (!cert) remaining.push('stamp certificate');
      submitAct = 'submit-certificate';
      label = 'Submit for final verification';
      after = 'Next: Legal verifies your documents.';
    }
    const ready = remaining.length === 0;
    const btn = `<button class="btn maroon lg" data-act="${submitAct}" data-sub="${s.id}" aria-describedby="execution-status" ${ready ? '' : 'aria-disabled="true"'}>${icon('arrow')}${label}</button>
      <div class="small" id="execution-status">${ready
        ? `<span style="color:var(--green)">${icon('check')} Ready to submit.</span>`
        : `<span style="color:var(--amber)">${icon('alert')} Still needed: ${esc(remaining.join(', '))}.</span>`}
        <div class="muted">Uploading does not submit. ${after}</div></div>`;
    return `<div class="card next-card requester"><div class="card-pad">
      <h2>${stage === 'awaiting_partner' ? 'Get the partner\'s signature' : 'Complete LHDN stamping'}</h2>
      ${stage === 'awaiting_partner' && stamp === false ? '<p class="muted" style="margin-top:6px">No LHDN stamping needed.</p>' : ''}
      <ol style="list-style:none;margin:14px 0 0;padding:0">${s1}${s2}${s3}${s4}</ol>
      <div class="row-wrap" style="margin-top:14px">${btn}</div></div></div>`;
  }

  function finalDocs(s) {
    const out = [];
    if (s.finalAccepted && s.finalAccepted.agreement) {
      const n = s.finalAccepted.agreement; const v = s.slots.agreement.versions[n - 1];
      out.push(`<div class="file">${A.fileIco('pdf')}<div><div class="file-name">${esc(v.filename)}</div><div class="file-meta">Agreement v${n}</div></div><button class="btn sm ghost" data-act="download" data-sub="${s.id}" data-key="agreement" data-n="${n}">${icon('download')}Download</button></div>`);
    }
    if (s.finalAccepted && s.finalAccepted.stamp_certificate) {
      const n = s.finalAccepted.stamp_certificate; const v = s.slots.stamp_certificate.versions[n - 1];
      out.push(`<div class="file">${A.fileIco('pdf')}<div><div class="file-name">${esc(v.filename)}</div><div class="file-meta">LHDN Stamp Certificate v${n}</div></div><button class="btn sm ghost" data-act="download" data-sub="${s.id}" data-key="stamp_certificate" data-n="${n}">${icon('download')}Download</button></div>`);
    }
    return out.length ? `<div style="margin-top:14px"><div class="small strong" style="margin-bottom:6px">Final documents</div><div class="stack-sm">${out.join('')}</div></div>` : '';
  }

  function registerSummary(s, legal) {
    const r = A.agr(s.agreementId);
    if (!r) return '';
    const orig = r.originalId ? A.agr(r.originalId) : null;
    // Title and campus repeat the submission header, so they show only when Legal set them differently.
    return `<div class="reg-summary" style="margin-top:14px"><div class="rs-head">${icon('book')}<strong>Agreement Register record</strong>${A.regStatusBadge(r)}<span class="spacer"></span>${legal ? `<a class="btn sm" href="#/register/${r.id}">Open in Register</a>` : ''}</div>
      <div class="rs-body"><dl class="kv">
        ${r.title !== s.title ? `<dt>Title</dt><dd>${esc(r.title)}</dd>` : ''}
        <dt>Type</dt><dd>${esc(r.type)}${r.moaSubtype ? ` · ${esc(r.moaSubtype)}` : ''}</dd>
        <dt>Partner</dt><dd>${esc(r.partner)} (${esc(r.location)})</dd>
        ${r.campus !== s.campus ? `<dt>Campus</dt><dd>${esc(A.campusShort(r.campus))}</dd>` : ''}
        <dt>Date signed</dt><dd>${A.fmtDate(r.dateSigned)}</dd>
        <dt>Expiry</dt><dd>${r.expiry ? A.fmtDate(r.expiry) : 'No fixed expiry (indefinite / until completion)'}</dd>
        <dt>PIC</dt><dd>${esc(r.pic)}</dd>
        ${orig ? `<dt>Amends</dt><dd>${esc(orig.id)} — ${esc(orig.title)}</dd>` : ''}
      </dl>
      <p class="xsmall muted" style="margin-top:10px">${icon('link')} Permanently linked to ${esc(s.id)}.${legal ? '' : ' Only this record is shown to you.'}</p></div></div>`;
  }

  // View 6 — completed / closed
  function completedPanel(s, u) {
    const legal = A.isLegal(u);
    if (s.status === 'not_proceeding') {
      const c = s.closure;
      return `<div class="card card-pad next-card closed"><h2>This submission is closed</h2>
        <div class="instruction" style="margin-top:10px;border-color:var(--grey-border)"><strong>Reason:</strong> ${esc(c.reason)}</div>
        <p class="small muted" style="margin-top:6px">Closed by ${esc(A.personName(c.by, true))} · ${A.fmt(c.at)}</p>
        <p class="small muted" style="margin-top:10px">${icon('ban')} ${legal ? 'No Agreement Register record exists or can be created while it is closed. Documents, versions, messages and history are kept, including Internal Legal Notes.' : 'No Agreement Register record exists. Documents, messages and history remain available.'}</p>
        ${legal ? `<div class="actions"><button class="btn" data-act="open-reopen" data-sub="${s.id}">${icon('refresh')}Reopen with a reason</button></div>`
          : `<div class="actions"><a class="btn" href="#/sub/${s.id}/conversation">${icon('message')}Message Legal</a><span class="small muted">Only Legal can reopen this submission.</span></div>`}
        ${reopenHistory(s)}</div>`;
    }
    const m = s.milestones;
    if (s.status === 'fully_executed') {
      return `<div class="card card-pad next-card done">${legal ? `<div class="kicker">Next step · Legal</div>
        <h2>Fully Executed on ${A.fmtDate(m.fullyExecutedAt)}</h2>
        <p class="muted" style="margin-top:6px">Verified by ${esc(A.personName(m.fullyExecutedBy, true))}. Review the details and create the official Agreement Register record — it is never created automatically.</p>`
        : `<h2>Legal verified your final documents</h2>
        <p class="muted" style="margin-top:6px">No action needed from you. Legal creates the official Agreement Register record next.</p>
        <p class="small muted" style="margin-top:4px">${esc(A.personName(m.fullyExecutedBy, true))} · ${A.fmt(m.fullyExecutedAt)}</p>`}
        ${finalDocs(s)}
        ${legal ? legalRegisterAction(s) : ''}${reopenHistory(s)}</div>`;
    }
    return `<div class="card card-pad next-card done"><h2>Registered as ${esc(s.agreementId)}</h2>
      <p class="muted" style="margin-top:6px">${legal ? '' : 'No action needed. '}Fully Executed ${A.fmtDate(m.fullyExecutedAt)} · Registered ${A.fmtDate(m.registeredAt)}</p>
      ${finalDocs(s)}${registerSummary(s, legal)}${reopenHistory(s)}</div>`;
  }

  function reopenHistory(s) {
    if (!s.reopenings.length) return '';
    return `<details class="disclose" style="margin-top:12px"><summary>Closure and reopening history (${s.reopenings.length})</summary><ul class="timeline" style="margin-top:6px">
      ${s.reopenings.map((r) => `<li><span class="t-when">${A.fmt(r.at)}</span><span>Reopened by ${esc(A.personName(r.by, true))}: ${esc(r.reason)}<div class="xsmall muted">Earlier closure (${A.fmt(r.closure.at)}): ${esc(r.closure.reason)}</div></span></li>`).join('')}</ul></details>`;
  }

  function legalRegisterAction(s) {
    const b = A.registerBlockers(s);
    return `<div class="actions">${b.length ? `<button class="btn primary lg" aria-disabled="true" data-act="blocked" data-msg="${esc(b.join(' '))}">${icon('book')}Create Register Record</button>` : `<a class="btn primary lg" href="#/register-create/${s.id}">${icon('book')}Create Register Record</a>`}</div>
      ${b.length ? `<ul class="blockers">${b.map((x) => `<li class="no">${icon('alert')}${esc(x)}</li>`).join('')}</ul>` : ''}`;
  }

  // ---------- Legal next-step panels (views 8 & 10) ----------
  function legalPanel(s, u) {
    const rev = UI.viewRev[s.id];
    const secondary = (items) => items.length ? `<details class="disclose" style="margin-top:12px"><summary>More actions</summary><div class="row-wrap" style="margin-top:8px">${items.join('')}</div></details>` : '';
    const classify = `<button class="btn sm" data-act="open-classify" data-sub="${s.id}">${icon('pen')}Correct classification</button>`;
    const close = `<button class="btn sm danger" data-act="open-close" data-sub="${s.id}">${icon('ban')}Close as Not Proceeding</button>`;
    const reqBtn = `<button class="btn" data-act="open-request" data-sub="${s.id}">${icon('message')}Request clarification or revision</button>`;
    switch (s.status) {
      case 'pending_review':
        return `<div class="card card-pad next-card"><h2>Start the Legal review</h2>
          <p class="muted" style="margin-top:6px">The requester will see that Legal has started.</p>
          <div class="actions"><button class="btn primary lg" data-act="start-review" data-sub="${s.id}" data-rev="${rev}">${icon('arrow')}Start Review</button></div>
          ${secondary([classify, close])}</div>`;
      case 'in_review': {
        const b = A.reviewBlockers(s);
        return `<div class="card card-pad next-card"><h2>Review the contents and documents</h2>
          <p class="muted" style="margin-top:6px">Next: obtain the <strong>${esc(A.signatory(s))}</strong>'s signature.</p>
          ${b.length ? `<ul class="blockers" id="review-blockers" aria-label="Why Complete Legal Review is unavailable">${b.map((x) => `<li class="no">${icon('alert')}${esc(x)}</li>`).join('')}</ul>` : ''}
          <div class="actions"><button class="btn primary lg" data-act="complete-review" data-sub="${s.id}" data-rev="${rev}" ${b.length ? 'aria-disabled="true" aria-describedby="review-blockers"' : ''}>${icon('check')}Complete Legal Review</button>${reqBtn}</div>
          ${secondary([classify, close])}</div>`;
      }
      case 'action_required': {
        const req = A.openRequest(s);
        const r = A.responseReadiness(s);
        return `<div class="card card-pad next-card requester"><h2>${req.request.phase === 'final' ? 'Final package returned for correction' : 'Waiting for the requester\'s response'}</h2>
          <p class="muted" style="margin-top:6px">${esc(A.personName(req.by, true))} · ${A.fmt(req.at)}. Returns to ${req.request.phase === 'final' ? 'Final Verification' : 'In Review'} when ${esc(A.personName(s.requesterId))} selects Submit Response.</p>
          <div class="instruction" style="margin-top:10px">${esc(req.text)}</div>
          <ul class="blockers">${req.request.clarify ? `<li class="no">${icon('clock')}Written answer: waiting</li>` : ''}${req.request.slots.map((k) => `<li class="${r.done.includes(k) ? 'ok' : 'no'}">${icon(r.done.includes(k) ? 'check' : 'clock')}${esc(A.slotLabel(k))}: ${r.done.includes(k) ? 'new version uploaded, not yet submitted' : 'waiting for new file'}</li>`).join('')}</ul>
          ${secondary([close])}</div>`;
      }
      case 'review_completed': {
        const signed = A.unikSignedVersion(s);
        const err = UI.uploadErrors[s.id + ':agreement'];
        return `<div class="card card-pad next-card"><div class="kicker">Next step · Legal · Review completed ${A.fmtDate(s.milestones.reviewCompletedAt)}</div>
          <h2>Obtain the ${esc(A.signatory(s))}'s signature</h2>
          <p class="muted" style="margin-top:6px">${s.category} agreements are signed for UniKL by the ${esc(A.signatory(s))}. Upload the signed copy as a new Agreement version, then send it to the requester for the partner's signature.</p>
          <div style="margin-top:12px">
            <div class="slot-row ${signed ? 'done' : ''}"><div><div class="strong">${icon(signed ? 'check' : 'upload')} UniKL-signed agreement (PDF)</div>
              <div class="small muted">${signed ? `v${s.slots.agreement.versions.length} · ${esc(signed.filename)} · uploaded ${A.fmt(signed.at)}` : 'Not uploaded yet'}</div>
              ${err ? `<div class="small" style="color:var(--red)">${icon('alert')} Upload failed: ${esc(err)} Current version unchanged.</div>` : ''}</div>
              <button class="btn sm ${signed ? '' : 'primary'}" data-act="upload" data-sub="${s.id}" data-key="agreement">${icon('upload')}${signed ? 'Replace' : 'Upload UniKL-signed PDF'}</button></div>
          </div>
          <div class="actions"><button class="btn primary lg" data-act="send-partner" data-sub="${s.id}" data-rev="${rev}" ${signed ? '' : 'aria-disabled="true"'}>${icon('arrow')}Send to requester for partner signature</button></div>
          ${secondary([close])}</div>`;
      }
      case 'awaiting_partner':
      case 'awaiting_stamping':
        return `<div class="card card-pad next-card requester"><div class="kicker">Waiting for Requester</div>
          <h2>${s.status === 'awaiting_partner' ? 'Requester is obtaining the partner\'s signature' : 'Requester is completing LHDN stamping'}</h2>
          <p class="muted" style="margin-top:6px">${s.status === 'awaiting_partner' ? `UniKL-signed Agreement v${s.slots.agreement.versions.map((v) => v.label).lastIndexOf('UniKL Signed') + 1} was sent on ${A.fmt(s.milestones.sentForPartnerAt)}.` : `The both-parties-signed agreement was submitted on ${A.fmt(s.milestones.partnerSignedAt)}. Waiting for the standalone stamp certificate.`} Nothing for Legal to do until the requester submits.</p>
          ${secondary([close])}</div>`;
      case 'final_verification': return finalVerificationPanel(s, rev, secondary, close);
      case 'fully_executed':
      case 'registered':
      case 'not_proceeding': return completedPanel(s, u);
      default: return '';
    }
  }

  function finalVerificationPanel(s, rev, secondary, close) {
    const c = UI.verifyChecks[s.id] || (UI.verifyChecks[s.id] = { agreement: false, certificate: false });
    const stamp = A.stampingApplies(s);
    const a = A.currentVersion(s, 'agreement'); const an = s.slots.agreement.versions.length;
    const unikN = s.slots.agreement.versions.map((v) => v.label).lastIndexOf('UniKL Signed') + 1;
    const cert = stamp === true ? A.currentVersion(s, 'stamp_certificate') : null;
    const b = A.finalBlockers(s);
    const ready = !b.length && c.agreement && (stamp !== true || c.certificate);
    return `<div class="card card-pad next-card"><div class="kicker">Next step · Legal · Final verification</div>
      <h2>Verify the final package</h2>
      <p class="muted" style="margin-top:6px">Only Legal or Admin can mark Fully Executed. The review-completion milestone (${A.fmtDate(s.milestones.reviewCompletedAt)}) stays recorded whatever happens here.</p>
      <div class="stack-sm" style="margin-top:12px">
        <div class="slot-row"><div><div class="file">${A.fileIco(a.ext)}<div><div class="file-name">${esc(a.filename)}</div><div class="file-meta">Agreement v${an} · ${esc(a.label)} · ${esc(A.personName(a.by, true))}, ${A.fmt(a.at)}</div></div></div>
          <label class="check" style="margin-top:8px"><input type="checkbox" data-act="verify-check" data-sub="${s.id}" data-key="agreement" ${c.agreement ? 'checked' : ''}><span class="small">Both UniKL's and the partner's signatures are on the same agreement that UniKL signed (v${unikN}).</span></label></div>
          <button class="btn sm ghost" data-act="download" data-sub="${s.id}" data-key="agreement" data-n="${an}">${icon('download')}Download</button></div>
        ${stamp === true ? `<div class="slot-row"><div><div class="file">${A.fileIco(cert ? cert.ext : '')}<div><div class="file-name">${cert ? esc(cert.filename) : 'Missing'}</div><div class="file-meta">LHDN Stamp Certificate${cert ? ` v${s.slots.stamp_certificate.versions.length} · separate document` : ''}</div></div></div>
          <label class="check" style="margin-top:8px"><input type="checkbox" data-act="verify-check" data-sub="${s.id}" data-key="certificate" ${c.certificate ? 'checked' : ''}><span class="small">The LHDN stamp certificate matches this agreement.</span></label></div>
          ${cert ? `<button class="btn sm ghost" data-act="download" data-sub="${s.id}" data-key="stamp_certificate" data-n="${s.slots.stamp_certificate.versions.length}">${icon('download')}Download</button>` : ''}</div>`
          : `<div class="notice neutral small">${icon('info')}<div>No LHDN stamp certificate is needed — ${s.type === 'ADDENDUM' ? 'Legal recorded that stamping is not required for this Addendum' : `stamping does not apply to an ${esc(s.type)}`}.</div></div>`}
      </div>
      ${b.length ? `<ul class="blockers">${b.map((x) => `<li class="no">${icon('alert')}${esc(x)}</li>`).join('')}</ul>` : ''}
      <div class="actions"><button class="btn primary lg" data-act="verify-final" data-sub="${s.id}" data-rev="${rev}" ${ready ? '' : 'aria-disabled="true"'}>${icon('check')}Mark Fully Executed</button>
        <button class="btn" data-act="open-request" data-sub="${s.id}">${icon('refresh')}Return for correction</button></div>
      ${secondary([close])}</div>`;
  }

  // ---------- View 12: Addendum original link + stamping decision ----------
  function addendumCard(s, u) {
    const legal = A.isLegal(u);
    const ad = s.addendum;
    const orig = ad.originalId ? A.agr(ad.originalId) : null;
    const editable = !['registered', 'not_proceeding'].includes(s.status);
    const stampEditable = ['pending_review', 'in_review', 'action_required', 'review_completed', 'awaiting_partner'].includes(s.status);
    const origBlock = orig ? `<div class="slot-row" style="margin-top:8px"><div><div class="strong">${esc(orig.title)} <span class="faint">· ${esc(orig.id)}</span></div>
        <div class="small muted">${esc(orig.partner)} · ${esc(orig.type)}${orig.moaSubtype ? ` (subtype: ${esc(orig.moaSubtype)} — stays with the original)` : ''} · signed ${A.fmtDate(orig.dateSigned)} · ${esc(A.campusShort(orig.campus))}</div></div>
        ${legal ? `<a class="btn sm ghost" href="#/register/${orig.id}">View</a>` : ''}</div>` : '';
    let linkHtml;
    if (ad.linkStatus === 'confirmed') {
      linkHtml = `<span class="badge tone-green">Original confirmed</span> <span class="small muted">by ${esc(A.personName(ad.confirmedBy, true))}, ${A.fmt(ad.confirmedAt)}</span>${origBlock}
        ${legal && editable ? `<div style="margin-top:8px"><button class="btn sm ghost" data-act="open-link" data-sub="${s.id}">Change linked original</button></div>` : ''}`;
    } else if (ad.linkStatus === 'requester_selected') {
      linkHtml = `<span class="badge tone-blue">Selected by requester — ${legal ? 'confirm' : 'Legal will confirm'}</span>${origBlock}
        ${legal ? `<div class="row-wrap" style="margin-top:8px"><button class="btn sm primary" data-act="confirm-link" data-sub="${s.id}" data-agr="${ad.originalId}" data-rev="${UI.viewRev[s.id]}">${icon('link')}Confirm link</button><button class="btn sm" data-act="open-link" data-sub="${s.id}">Choose a different record</button></div>` : ''}`;
    } else {
      const nf = ad.notFound || {};
      linkHtml = `<div class="notice warn" style="margin-top:4px">${icon('alert')}<div><strong>Unresolved original.</strong> ${legal ? 'The requester could not find or access the original agreement. Registration is blocked until Legal links it to an existing Register record.' : 'You told Legal you cannot find or access the original agreement. Legal will find and link it.'}</div></div>
        <dl class="kv small" style="margin-top:10px"><dt>Title given</dt><dd>${esc(nf.title || '—')}</dd><dt>Partner</dt><dd>${esc(nf.partner || '—')}</dd><dt>Approximate date</dt><dd>${esc(nf.approxDate || '—')}</dd><dt>Campus / dept.</dt><dd>${esc(nf.campus || '—')}</dd><dt>Other details</dt><dd>${esc(nf.details || '—')}</dd></dl>
        ${legal ? `<div style="margin-top:10px"><button class="btn sm primary" data-act="open-link" data-sub="${s.id}">${icon('search')}Find in Agreement Register</button></div>
          <p class="xsmall muted" style="margin-top:6px">If the original is not in the Register at all, add it through the existing Register process first. The portal never creates an original record automatically.</p>` : ''}`;
    }
    const st = ad.stampingRequired;
    const stampHtml = st == null
      ? `<div class="notice warn">${icon('stamp')}<div><strong>Stamping decision not recorded.</strong> ${legal ? 'Record explicitly whether LHDN stamping is required. It is never assumed, and review cannot be completed without it.' : 'Legal will decide whether this Addendum needs LHDN stamping.'}</div></div>
         ${legal && stampEditable ? `<div class="row-wrap" style="margin-top:8px"><button class="btn sm" data-act="open-stamping" data-sub="${s.id}">${icon('stamp')}Record stamping decision</button></div>` : ''}`
      : `<div class="row"><span class="badge ${st ? 'tone-amber' : 'tone-grey'}">${st ? 'LHDN stamping required' : 'LHDN stamping not required'}</span><span class="small muted">Decided by ${esc(A.personName(ad.stampingBy, true))}, ${A.fmt(ad.stampingAt)}</span>
         ${legal && stampEditable ? `<button class="btn sm ghost" data-act="open-stamping" data-sub="${s.id}">Change</button>` : ''}</div>`;
    return `<div class="card" style="margin-top:16px"><div class="card-head">${icon('link')}<h3 style="flex:1">Addendum — original agreement &amp; stamping</h3><span class="chip">ADDENDUM · standalone type</span></div>
      <div class="card-body stack">
        <div><div class="small strong">Purpose of Addendum</div><p style="margin-top:4px">${esc(ad.purpose)}</p></div>
        <div><div class="small strong" style="margin-bottom:6px">Original agreement</div>${linkHtml}</div>
        <div><div class="small strong" style="margin-bottom:6px">LHDN stamping</div>${stampHtml}</div>
      </div></div>`;
  }

  // ---------- tabs ----------
  function overviewTab(s, u) {
    const legal = A.isLegal(u);
    const current = A.slotList(s).filter((x) => !x.undecided && !x.retired);
    const last = s.messages[s.messages.length - 1];
    const acts = s.activity.filter((a) => legal || !a.legalOnly).slice(-4).reverse();
    return `<div class="stack">
      <div class="card card-pad"><h3>Purpose and scope</h3><p style="margin-top:6px">${esc(s.scope)}</p></div>
      <div class="card"><div class="card-head"><h3 style="flex:1">Current documents</h3>${A.pcCue()}<a class="btn sm ghost" href="#/sub/${s.id}/documents">All versions</a></div>
        <div class="card-body stack-sm">${current.map((x) => {
          const v = A.currentVersion(s, x.key);
          return `<div class="row" style="justify-content:space-between"><span>${icon(v ? 'file' : 'alert')} <strong>${esc(A.slotLabel(x.key))}</strong></span><span class="small ${v ? 'muted' : ''}" style="${v ? '' : 'color:var(--amber)'}">${v ? `v${A.versionNo(s, x.key, v)} · ${esc(v.label)} · ${esc(v.filename)}` : x.execution ? 'Not yet — execution stage' : 'Missing'}</span></div>`;
        }).join('')}</div></div>
      ${last ? `<div class="card card-pad"><div class="row"><h3 style="flex:1">${legal ? 'Latest in Conversation' : 'Latest message'}</h3><a class="btn sm ghost" href="#/sub/${s.id}/conversation">${legal ? 'Open Conversation' : 'View conversation'}</a></div>
        <p class="small muted" style="margin-top:6px">${esc(A.personName(last.by, true))} · ${A.fmt(last.at)}</p><p style="margin-top:4px">${esc(last.text.length > 220 ? last.text.slice(0, 220) + '…' : last.text || '(Response submitted)')}</p></div>` : ''}
      <div class="card card-pad"><div class="row"><h3 style="flex:1">Recent activity</h3><a class="btn sm ghost" href="#/sub/${s.id}/activity">${legal ? 'Full audit' : 'View activity'}</a></div>
        <ul class="timeline" style="margin-top:6px">${acts.map((a) => `<li><span class="t-when">${A.fmt(a.at)}</span><span>${esc(a.text)}<div class="t-who">${esc(A.personName(a.by, true))}</div></span></li>`).join('')}</ul></div>
    </div>`;
  }

  function documentsTab(s, u) {
    const legal = A.isLegal(u);
    const list = A.slotList(s);
    const intake = list.filter((x) => !x.execution && !x.retired);
    const retired = list.filter((x) => x.retired);
    const exec = list.filter((x) => x.execution);
    // When every intake slot is locked for the same reason, say it once instead of on every row.
    const intakeRules = intake.map((x) => A.uploadRule(u, s, x.key));
    const sharedLock = intakeRules.length > 1 && intakeRules.every((r) => !r.ok && r.reason === intakeRules[0].reason) ? intakeRules[0].reason : null;
    const row = (x) => {
      const k = x.key;
      const def = A.SLOT_DEFS[k];
      const sl = s.slots[k] || { versions: [], reopened: false };
      const v = A.currentVersion(s, k);
      const rule = A.uploadRule(u, s, k);
      const expanded = UI.expanded[s.id + ':' + k];
      const err = UI.uploadErrors[s.id + ':' + k];
      const recent = legal && v ? A.recentAccess(s, u, k) : null;
      const req = A.openRequest(s);
      const reopened = req && req.request.slots.includes(k);
      let chip = '';
      if (x.retired) chip = '<span class="chip">No longer required · kept</span>';
      else if (x.undecided) chip = '<span class="badge tone-amber">Legal to decide</span>';
      else if (reopened) chip = '<span class="badge tone-amber">Reopened by Legal</span>';
      else if (!v && x.execution) chip = '<span class="chip">Execution stage</span>';
      else if (!v) chip = '<span class="badge tone-red">Missing</span>';
      else if (rule.ok) chip = '<span class="badge tone-blue">Upload open</span>';
      else if (!sharedLock) chip = `<span class="chip">${icon('lock')}Locked</span>`;
      const older = Math.max(0, sl.versions.length - 1);
      const vMeta = v ? `${older ? 'Current: ' : ''}v${sl.versions.length} · ${esc(v.label)}${(s.finalAccepted && s.finalAccepted[k] === sl.versions.length) ? (k === 'agreement' ? ' · <span class="badge tone-green plain">Final Executed</span>' : ' · <span class="badge tone-green plain">Verified</span>') : ''}<br>${A.size(v.sizeMB)} · ${esc(A.personName(v.by, true))} · ${A.fmt(v.at)}` : '';
      return `<div class="doc ${reopened ? 'reopened' : ''} ${x.retired ? 'retired' : ''}">
        <div><div class="doc-name">${esc(def.label)} ${chip}</div>${v && !x.undecided ? '' : `<div class="doc-help">${esc(x.undecided ? 'Only needed if Legal decides this Addendum requires LHDN stamping. ' + def.help : def.help)}</div>`}</div>
        <div>${v ? `<div class="file">${A.fileIco(v.ext)}<div><div class="file-name">${esc(v.filename)}</div><div class="file-meta">${vMeta}</div></div></div>` : `<div class="file">${A.fileIco('')}<div class="small muted" style="padding-top:10px">No file</div></div>`}</div>
        <div class="doc-actions">
          ${v ? `<button class="btn sm" data-act="download" data-sub="${s.id}" data-key="${k}" data-n="${sl.versions.length}">${icon('download')}Download</button>` : ''}
          ${rule.ok ? `<button class="btn sm primary" data-act="upload" data-sub="${s.id}" data-key="${k}">${icon('upload')}${esc(rule.verb)}</button>` : (!x.retired && !(sharedLock && rule.reason === sharedLock && !x.execution) ? `<div class="lock-note">${icon('lock')}<span>${esc(rule.reason)}</span></div>` : '')}
          ${older ? `<button class="btn sm ghost" data-act="toggle-versions" data-sub="${s.id}" data-key="${k}" aria-expanded="${!!expanded}" aria-controls="versions-${s.id}-${k}">${icon('history')}${expanded ? 'Hide' : 'Show'} older versions (${older})</button>` : ''}
        </div>
        ${recent ? `<div class="notice info access-warn small" role="note">${icon('eye')}<div>${esc(A.personName(recent.by))} (Legal) downloaded v${recent.n} ${A.rel(recent.at)}. You can continue. This is a heads-up only, and nothing is locked or assigned.</div></div>` : ''}
        ${err ? `<div class="notice error upload-error small">${icon('alert')}<div><strong>Upload failed:</strong> ${esc(err)} ${v ? `The current file (v${sl.versions.length}) is unchanged.` : ''} <button class="link-btn" data-act="upload" data-sub="${s.id}" data-key="${k}">Try again</button></div></div>` : ''}
        ${expanded && older ? `<div id="versions-${s.id}-${k}" style="grid-column:1 / -1">${A.versionTable(s, k, u)}</div>` : ''}
      </div>`;
    };
    return `<div class="card">
      <div class="card-head">${A.pcCue()}<span class="small muted" style="flex:1">Visible only to the requester and CLSD Legal. Downloads are recorded.</span></div>
      <div class="doc-group-title">Intake documents · ${esc(s.category)} checklist (${intake.length})</div>
      ${sharedLock ? `<div class="notice neutral small" style="margin:12px 18px 2px">${icon('lock')}<div>${esc(sharedLock)}</div></div>` : ''}
      <div class="doc-list">${intake.map(row).join('')}</div>
      ${retired.length ? `<div class="doc-group-title">No longer required after classification change — kept with history</div><div class="doc-list">${retired.map(row).join('')}</div>` : ''}
      ${exec.length ? `<div class="doc-group-title">Execution documents · separate from the Agreement</div><div class="doc-list">${exec.map(row).join('')}</div>` : ''}
      <div class="card-foot xsmall muted">PDF or Word (.docx), up to 20 MB. Signed agreements and stamp certificates: PDF only. Earlier versions are always kept.</div>
    </div>`;
  }

  function conversationTab(s, u) {
    const legal = A.isLegal(u);
    const justRead = UI.justRead[s.id] || [];
    const canRequest = legal && ['in_review', 'final_verification'].includes(s.status);
    const thread = s.messages.map((m) => {
      const who = A.user(m.by);
      const isL = A.isLegal(who);
      const isNew = justRead.includes(m.id);
      let inner;
      if (m.kind === 'request') {
        const r = m.request;
        const kinds = [r.clarify ? 'Clarification' : null, r.slots.length ? 'Document revision' : null].filter(Boolean).join(' + ');
        inner = `<div class="req-card ${r.status === 'answered' ? 'answered' : ''}"><div class="rc-head">${icon(r.status === 'answered' ? 'check' : 'flag')}${r.phase === 'final' ? 'Final-package correction' : 'Action Required'} · ${esc(kinds)}<span class="spacer"></span><span class="badge ${r.status === 'answered' ? 'tone-green' : 'tone-amber'} plain">${r.status === 'answered' ? `Answered ${A.fmt(r.answeredAt)}` : 'Open'}</span></div>
          <div class="rc-body">${esc(m.text)}</div>
          ${r.slots.length ? `<div class="rc-foot">${icon('unlock')} Reopened: ${r.slots.map((k) => `<strong>${esc(A.slotLabel(k))}</strong>`).join(', ')} · other documents stay locked</div>` : ''}
          ${r.status === 'open' && !legal ? `<div class="rc-foot"><a href="#/sub/${s.id}/overview">Respond to this request →</a></div>` : ''}</div>`;
      } else if (m.kind === 'response') {
        inner = `<div class="resp-card"><div class="rc-title">${icon('check')} Response submitted${m.response.replaced.length ? ` · new ${m.response.replaced.map((k) => esc(A.slotLabel(k))).join(', ')}` : ''}</div>${m.text ? `<div style="white-space:pre-wrap">${esc(m.text)}</div>` : '<div class="small muted">No written answer was requested.</div>'}</div>`;
      } else {
        inner = `<div class="bubble">${esc(m.text)}</div>`;
      }
      return `<div class="msg ${isL ? 'is-legal' : ''} ${isNew ? 'unread' : ''}">${A.avatar(m.by, true)}<div class="msg-body"><div class="msg-head"><span class="who">${esc(who.name)}</span><span class="chip">${isL ? 'Legal' : 'Requester'}</span><span class="when">${A.fmt(m.at)}</span>${isNew ? '<span class="new-flag">New for you</span>' : ''}</div>${inner}</div></div>`;
    }).join('');
    const draft = UI.composer[s.id] || '';
    return `<div class="card card-pad">
      ${s.messages.length ? `<div class="thread">${thread}</div>` : `<div class="empty" style="padding:24px">${icon('message')}<h3>No messages yet</h3><p>Messages between the requester and Legal appear here.</p></div>`}
      <div class="composer">
        <label class="field"><span class="label">${legal ? 'Message to the requester' : 'Message to CLSD Legal'}</span>
          <textarea class="textarea" id="composer" data-bind="composer.${s.id}" placeholder="Write a message">${esc(draft)}</textarea></label>
        <div class="foot"><button class="btn primary" data-act="send-message" data-sub="${s.id}">${icon('message')}Send message</button>
          ${canRequest ? `<button class="btn" data-act="open-request" data-sub="${s.id}">${icon('flag')}${s.status === 'final_verification' ? 'Return for correction' : 'Request clarification or revision'}</button>` : ''}
          <span class="xsmall muted">Messages can't be edited or deleted, and never change the submission status. No attachments — use the document slots.${legal ? '' : ' To withdraw, ask Legal here.'}</span></div>
      </div>
      <p class="xsmall faint" style="margin-top:10px">Read status is personal: opening this Conversation marks messages read for you only.</p>
    </div>`;
  }

  // View 9 — Internal Legal Notes (Legal/Admin only; never called for Requesting Staff).
  function notesTab(s, u) {
    if (!A.isLegal(u)) return '';
    const draft = UI.noteComposer[s.id] || '';
    return `<div class="card">
      <div class="notes-banner">${icon('shield')}<div><strong>Internal Legal Notes — Legal and Admin only.</strong><div class="small" style="opacity:.9">Never shown to the requester: not in pages, messages, activity, notifications, downloads or counts. Notes never change the submission status.</div></div></div>
      <div class="card-body stack">
        <label class="field"><span class="label">New internal note</span><textarea class="textarea" data-bind="noteComposer.${s.id}" placeholder="Visible to Legal and Admin only">${esc(draft)}</textarea></label>
        <div class="row"><button class="btn maroon" data-act="add-note" data-sub="${s.id}">${icon('shield')}Add internal note</button><span class="xsmall muted">Append-only. To correct a note, add a new one.</span></div>
        <div class="hr"></div>
        ${s.notes.length ? s.notes.slice().reverse().map((n) => `<div class="note"><div class="msg-head">${A.avatar(n.by, true)}<span class="who">${esc(A.personName(n.by))}</span><span class="when">${A.fmt(n.at)}</span></div><div class="note-text">${esc(n.text)}</div></div>`).join('')
          : '<p class="muted small">No internal notes yet.</p>'}
      </div></div>`;
  }

  function activityTab(s, u) {
    const legal = A.isLegal(u);
    const items = s.activity.filter((a) => legal || !a.legalOnly).slice().reverse();
    return `<div class="card card-pad">
      <p class="small muted" style="margin-bottom:8px">${legal ? 'Append-only audit of significant events. Entries marked “Legal only” never appear to the requester.' : 'Significant events on your submission. Conversation messages are shown in the Conversation tab.'}</p>
      <ul class="timeline">${items.map((a) => `<li class="${a.legalOnly ? 'legal-only' : ''}"><span class="t-when">${A.fmt(a.at)}</span><span>${esc(a.text)} ${a.legalOnly ? '<span class="chip" style="color:var(--maroon)">Legal only</span>' : ''}<div class="t-who">${esc(A.personName(a.by, true))}</div></span></li>`).join('')}</ul></div>`;
  }

  A.registerSummary = registerSummary;
})();
