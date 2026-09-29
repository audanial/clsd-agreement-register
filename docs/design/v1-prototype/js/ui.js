/* Shell, router, demo toolbar, modals, toasts and shared presentation components. */
(function () {
  'use strict';
  const A = window.AMS;
  const esc = A.esc;

  // Transient UI state (not persisted): filters, form inputs, open panels, viewed revisions.
  const UI = {
    viewRev: {},
    expanded: {},
    uploadErrors: {},
    composer: {},
    noteComposer: {},
    responseDraft: {},
    myFilter: { tab: 'all', q: '' },
    queueFilter: { tab: 'legal', q: '', status: '', category: '', campus: '', since: '' },
    regFilter: { q: '', type: '' },
    newForm: null,
    regForm: {},
    modal: null,
    drawer: false,
    previewEmpty: false,
    busy: false,
  };
  A.UI = UI;

  // ---------- icons (inline SVG, stroke-based) ----------
  const P = {
    inbox: '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
    plus: '<path d="M12 5v14M5 12h14"/>',
    file: '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>',
    lock: '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
    unlock: '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/>',
    download: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5M12 15V3"/>',
    upload: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m17 8-5-5-5 5M12 3v12"/>',
    message: '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
    shield: '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
    activity: '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
    check: '<path d="M20 6 9 17l-5-5"/>',
    x: '<path d="M18 6 6 18M6 6l12 12"/>',
    alert: '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><path d="M12 9v4M12 17h.01"/>',
    info: '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>',
    clock: '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
    user: '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
    users: '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
    book: '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>',
    arrow: '<path d="M5 12h14M12 5l7 7-7 7"/>',
    refresh: '<path d="M23 4v6h-6M1 20v-6h6"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>',
    search: '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>',
    link: '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
    stamp: '<path d="M5 22h14M19 18H5a2 2 0 0 1 0-4h3.5l-1-6a4.5 4.5 0 1 1 9 0l-1 6H19a2 2 0 0 1 0 4z"/>',
    pen: '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/>',
    history: '<path d="M3 3v5h5"/><path d="M3.05 13A9 9 0 1 0 6 5.3L3 8"/><path d="M12 7v5l4 2"/>',
    help: '<circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3M12 17h.01"/>',
    reset: '<path d="M1 4v6h6"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/>',
    flag: '<path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><path d="M4 22v-7"/>',
    ban: '<circle cx="12" cy="12" r="10"/><path d="m4.93 4.93 14.14 14.14"/>',
    eye: '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
    eye_off:'<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><path d="m1 1 22 22"/>',
  };
  const icon = (n, cls) => `<svg class="ico ${cls || ''}" viewBox="0 0 24 24" aria-hidden="true">${P[n] || ''}</svg>`;

  // ---------- small components ----------
  function statusBadge(s) {
    const st = A.STATUS[s.status];
    return `<span class="badge tone-${st.tone}">${esc(st.label)}</span>`;
  }
  function regStatusBadge(r) {
    const st = A.REGISTER_STATUS[r.status];
    return `<span class="badge tone-${st.tone}">${esc(st.label)}</span>`;
  }
  function nextActorHtml(s, viewer) {
    const a = A.nextActor(s);
    const mine = viewer && ((a === 'requester' && viewer.role === 'requester') || (a === 'legal' && A.isLegal(viewer)));
    if (a === 'legal') return `<span class="next-actor next-legal">${icon('clock')}Waiting for Legal</span>`;
    if (a === 'requester') return `<span class="next-actor next-requester">${icon(mine ? 'flag' : 'clock')}${mine ? 'Your action needed' : 'Waiting for Requester'}</span>`;
    if (a === 'closed') return `<span class="next-actor next-closed">${icon('ban')}Closed</span>`;
    return `<span class="next-actor next-done">${icon('check')}Complete</span>`;
  }
  function personName(id, withRole) {
    const u = A.user(id);
    if (!u) return 'Unknown';
    return withRole ? `${u.name}${A.isLegal(u) ? ' (Legal)' : ''}` : u.name;
  }
  function avatar(id, sm) {
    const u = A.user(id);
    return `<span class="avatar ${sm ? 'sm' : ''} ${A.isLegal(u) ? 'legal' : ''}" aria-hidden="true">${esc(u.initials)}</span>`;
  }
  function fileIco(ext) { return `<span class="file-ico ${ext === 'pdf' ? 'pdf' : ext === 'docx' ? 'docx' : 'none'}" aria-hidden="true">${ext ? esc(ext.toUpperCase()) : ''}</span>`; }
  const pcCue = () => `<span class="pc-cue" title="Private &amp; Confidential">${icon('lock')}Private &amp; Confidential</span>`;
  const typeLabel = (s) => s.type === 'MOA' && s.moaSubtype ? `MOA · ${esc(s.moaSubtype)}` : esc(s.type);

  function progress(s) {
    const m = s.milestones;
    const stamp = A.stampingApplies(s);
    const req = A.openRequest(s);
    const finalPhase = s.status === 'action_required' && req && req.request.phase === 'final';
    const steps = [
      { label: 'Submitted', done: true, when: s.submittedAt },
      { label: 'Legal review', done: !!m.reviewCompletedAt, when: m.reviewCompletedAt, current: ['pending_review', 'in_review'].includes(s.status) || (s.status === 'action_required' && !finalPhase) },
      { label: `UniKL signed`, sub: A.signatory(s), done: !!m.sentForPartnerAt, when: m.sentForPartnerAt, current: s.status === 'review_completed' },
      { label: 'Partner signed', done: !!m.partnerSignedAt, when: m.partnerSignedAt, current: s.status === 'awaiting_partner' },
      stamp === false ? { label: 'LHDN stamping', sub: 'Not required', skipped: true }
        : stamp === null ? { label: 'LHDN stamping', sub: 'Legal to decide', undecided: true }
          : { label: 'LHDN stamped', done: !!m.stampedAt, when: m.stampedAt, current: s.status === 'awaiting_stamping' },
      { label: 'Final verification', done: !!m.fullyExecutedAt, when: m.fullyExecutedAt, current: s.status === 'final_verification' || finalPhase },
      { label: 'Registered', done: !!m.registeredAt, when: m.registeredAt, current: s.status === 'fully_executed' },
    ];
    const flag = s.status === 'action_required';
    return `<div class="card progress ${s.status === 'not_proceeding' ? 'closed' : ''}" role="list" aria-label="Submission progress">
      ${steps.map((st) => {
        const cls = [st.done ? 'done' : '', st.current ? 'current' : '', st.current && flag ? 'flag' : '', st.skipped ? 'skipped' : '', st.undecided ? 'undecided' : ''].join(' ');
        const inner = st.done ? icon('check') : '';
        return `<div class="step ${cls}" role="listitem"><div class="dot">${inner}</div>${esc(st.label)}${st.sub ? `<span class="when">${esc(st.sub)}</span>` : ''}${st.when ? `<span class="when">${A.fmtDate(st.when)}</span>` : ''}${st.current && flag ? '<span class="when" style="color:var(--amber)">Action required</span>' : ''}</div>`;
      }).join('')}
    </div>`;
  }

  function versionTable(s, key, viewer) {
    const sl = s.slots[key];
    const accepted = s.finalAccepted && s.finalAccepted[key];
    return `<div class="versions"><table><thead><tr><th>Version</th><th>File</th><th>Stage</th><th>Uploaded by</th><th>When</th><th></th></tr></thead><tbody>
      ${sl.versions.slice().reverse().map((v, i) => {
        const n = sl.versions.length - i;
        const current = i === 0;
        return `<tr><td class="nowrap"><strong>v${n}</strong>${current ? ' <span class="chip">Current</span>' : ''}</td>
        <td>${esc(v.filename)} <span class="faint">· ${A.size(v.sizeMB)}</span></td>
        <td>${esc(v.label)}${v.finalExecuted || accepted === n && key === 'agreement' ? ' <span class="badge tone-green plain">Final Executed</span>' : ''}${v.verified || (accepted === n && key === 'stamp_certificate') ? ' <span class="badge tone-green plain">Verified</span>' : ''}</td>
        <td>${esc(personName(v.by, true))}</td><td class="nowrap">${A.fmt(v.at)}</td>
        <td><button class="btn sm ghost" data-act="download" data-sub="${s.id}" data-key="${key}" data-n="${n}">${icon('download')}Download</button></td></tr>`;
      }).join('')}
    </tbody></table></div>`;
  }

  // ---------- router ----------
  function route() {
    const h = (location.hash || '').replace(/^#\/?/, '');
    const parts = h.split('/').filter(Boolean).map(decodeURIComponent);
    return { name: parts[0] || '', a: parts[1], b: parts[2] };
  }
  function go(hash) {
    if (location.hash === hash) A.render(); else location.hash = hash;
  }
  function home() { return A.isLegal(A.me()) ? '#/queue' : '#/my'; }

  // ---------- rendering ----------
  const views = {};
  A.views = views;

  function render() {
    renderDemoBar();
    renderSidebar();
    const r = route();
    const main = document.getElementById('main');
    let html;
    try {
      const u = A.me();
      const legal = A.isLegal(u);
      switch (r.name) {
        case 'my': html = legal ? denied('My Submissions is the Requesting Staff home. Legal users work from the Submission Queue.') : views.mySubmissions(); break;
        case 'new': html = legal ? denied('Submissions are created by Requesting Staff.') : views.newSubmission(r.a); break;
        case 'sub': html = views.workspace(r.a, r.b || 'overview'); break;
        case 'queue': html = legal ? views.queue() : denied('The Submission Queue is available to Legal and Admin only.'); break;
        case 'register': html = legal ? (r.a ? views.registerRecord(r.a) : views.register()) : denied('The full Agreement Register is available to Legal and Admin. Your own registered outcomes appear on each completed submission.'); break;
        case 'register-create': html = legal ? views.registerCreate(r.a) : denied('Only Legal or Admin can create Agreement Register records.'); break;
        default: location.replace(home()); return;
      }
    } catch (e) {
      console.error(e);
      html = `<div class="page"><div class="notice error">${icon('alert')}<div><strong>Prototype error.</strong> ${esc(e.message)} — use Reset demo if this persists.</div></div></div>`;
    }
    main.innerHTML = html;
    renderModal();
    renderDrawer();
    if (A.afterRender) A.afterRender(r);
  }
  A.render = render;
  A.renderDemoBar = () => renderDemoBar();
  A.go = go;
  A.route = route;
  A.home = home;

  function denied(text) {
    return `<div class="page"><div class="card empty">${icon('lock')}<h3>Not available</h3><p>${esc(text)}</p><p style="margin-top:14px"><a class="btn" href="${home()}">Go to my home page</a></p></div></div>`;
  }
  A.denied = denied;

  function renderDemoBar() {
    const S = A.state();
    const r = route();
    const u = A.me();
    const canSim = A.isLegal(u) && r.name === 'sub' && A.sub(r.a);
    document.getElementById('demo-bar').innerHTML = `
      <span class="demo-tag" title="Demonstration controls — not part of the product">Prototype · fictional data</span>
      <span class="demo-label">Demo: view as</span>
      <div class="demo-roles" role="group" aria-label="Demo perspective">
        ${A.PERSPECTIVES.map((id) => { const p = A.user(id); return `<button class="demo-role" data-act="switch-user" data-user="${id}" aria-pressed="${S.currentUser === id}">${esc(p.name)} <span class="r">${p.role === 'legal' ? 'Legal' : 'Requesting Staff'}</span></button>`; }).join('')}
      </div>
      <button class="demo-btn" data-act="open-guide">${icon('help')} Guide &amp; 12 views</button>
      ${canSim ? `<button class="demo-btn warn" data-act="simulate-other" data-sub="${esc(r.a)}" title="Makes a change as the other Legal user without refreshing your page">Simulate other Legal user's change</button>` : ''}
      <span class="spacer"></span>
      <span class="demo-clock" title="Simulated clock — advances a few minutes with each action">Simulated time: ${A.fmt(S.clock)} MYT</span>
      <button class="demo-btn" data-act="reset-demo">${icon('reset')} Reset demo</button>`;
  }

  function renderSidebar() {
    const u = A.me();
    const r = route();
    const legal = A.isLegal(u);
    const unread = A.totalUnread(u);
    const cur = (names) => names.includes(r.name) ? 'aria-current="page"' : '';
    const items = legal
      ? `<div class="nav-section">Submission Portal</div>
         <a href="#/queue" ${cur(['queue', 'sub'])}>${icon('inbox', 'nav-ico')}Submission Queue${unread ? `<span class="count" title="${unread} unread message${unread === 1 ? '' : 's'} for you">${unread}</span>` : ''}</a>
         <div class="nav-section">Records</div>
         <a href="#/register" ${cur(['register', 'register-create'])}>${icon('book', 'nav-ico')}Agreement Register</a>`
      : `<div class="nav-section">Submission Portal</div>
         <a href="#/my" ${cur(['my', 'sub'])}>${icon('inbox', 'nav-ico')}My Submissions${unread ? `<span class="count" title="${unread} unread message${unread === 1 ? '' : 's'}">${unread}</span>` : ''}</a>
         <a href="#/new" ${cur(['new'])}>${icon('plus', 'nav-ico')}New Submission</a>`;
    document.getElementById('sidebar').innerHTML = `
      <div class="brand"><div class="brand-mark">Uni<span>KL</span></div><div class="brand-name">Agreement Management System</div><div class="brand-sub">Corporate Legal &amp; Secretariat Department</div></div>
      <nav class="nav">${items}</nav>
      <div class="me">${avatar(u.id)}<div><div class="who">${esc(u.name)}</div><div class="role">${legal ? 'Legal' : 'Requesting Staff'} · ${esc(u.title.split(', ')[1] || '')}</div></div></div>
      <div class="proto-note">Prototype for workflow review. Fictional data only.</div>`;
  }

  // ---------- modal ----------
  const MODALS = {};
  A.MODALS = MODALS;
  function openModal(key, data) {
    // Remember what opened the dialog so focus can return there when it closes.
    if (!UI.modal) { const t = document.activeElement; UI.modalTrigger = t && t.dataset && t.dataset.act ? { act: t.dataset.act, sub: t.dataset.sub, key: t.dataset.key } : null; }
    UI.modal = { key, data: data || {}, error: null, conflict: false };
    renderModal();
    setTimeout(() => {
      const root = document.getElementById('modal-root');
      const f = root.querySelector('[autofocus], .modal-body input:not([type=hidden]), .modal-body textarea, .modal-body select, .modal-body button');
      const target = f || root.querySelector('.modal');
      if (target) target.focus();
    }, 0);
  }
  function closeModal() {
    UI.modal = null; renderModal();
    const t = UI.modalTrigger; UI.modalTrigger = null;
    // The page may re-render after closing, so look the trigger up again just after.
    setTimeout(() => {
      const q = t ? `[data-act="${t.act}"]${t.sub ? `[data-sub="${t.sub}"]` : ''}${t.key ? `[data-key="${t.key}"]` : ''}` : '';
      const el = q ? document.querySelector(q) : null;
      (el || document.getElementById('main')).focus({ preventScroll: true });
    }, 0);
  }
  function renderModal() {
    const root = document.getElementById('modal-root');
    if (!UI.modal) { root.innerHTML = ''; return; }
    const def = MODALS[UI.modal.key];
    const d = UI.modal.data;
    const conflict = UI.modal.conflict ? `<div class="notice error" style="margin-bottom:14px">${icon('refresh')}<div><strong>This submission changed since you opened it.</strong> Refresh to see the latest version. Your unsent input below has been kept.<div style="margin-top:8px"><button class="btn sm" data-act="refresh-sub" data-sub="${esc(d.subId || '')}">${icon('refresh')}Refresh submission</button></div></div></div>` : '';
    const error = UI.modal.error ? `<div class="notice error" style="margin-bottom:14px" role="alert">${icon('alert')}<div>${esc(UI.modal.error)}</div></div>` : '';
    root.innerHTML = `<div class="overlay" data-act="modal-backdrop"><div class="modal ${def.wide ? 'wide' : ''}" role="dialog" aria-modal="true" aria-labelledby="modal-title" tabindex="-1">
      <div class="modal-head"><h2 id="modal-title">${def.title(d)}</h2><button class="icon-btn" data-act="close-modal" aria-label="Close">${icon('x')}</button></div>
      <div class="modal-body">${conflict}${error}${def.body(d)}</div>
      <div class="modal-foot">${def.foot(d)}</div></div></div>`;
  }
  A.openModal = openModal;
  A.closeModal = closeModal;
  A.renderModal = renderModal;

  // Apply an action result inside a modal: conflict/validation stays in the modal, success closes it.
  function modalResult(res, successMsg) {
    if (res.ok) { closeModal(); toast(successMsg, 'success'); A.render(); return true; }
    UI.modal.conflict = !!res.conflict;
    UI.modal.error = res.conflict ? null : res.error;
    renderModal();
    return false;
  }
  A.modalResult = modalResult;

  // ---------- drawer (demo guide) ----------
  function renderDrawer() {
    const root = document.getElementById('drawer-root');
    if (!UI.drawer) { root.innerHTML = ''; return; }
    root.innerHTML = `<div class="drawer-overlay" data-act="close-guide"></div>
      <aside class="drawer" role="dialog" aria-label="Demo guide" tabindex="-1">
        <div class="drawer-head"><span class="demo-tag">Demo guide</span><strong style="flex:1">Views &amp; walkthroughs</strong><button class="icon-btn" data-act="close-guide" aria-label="Close guide">${icon('x')}</button></div>
        <div class="drawer-body">${A.guideHtml()}</div>
      </aside>`;
  }
  A.renderDrawer = renderDrawer;

  // ---------- toast ----------
  function toast(text, kind) {
    const root = document.getElementById('toast-root');
    const el = document.createElement('div');
    el.className = 'toast ' + (kind || '');
    el.innerHTML = `${icon(kind === 'error' ? 'alert' : kind === 'demo' ? 'info' : 'check')}<div>${esc(text)}</div>`;
    root.appendChild(el);
    while (root.children.length > 3) root.firstElementChild.remove();
    setTimeout(() => el.remove(), kind === 'error' ? 6000 : 4200);
  }
  A.toast = toast;

  // ---------- simulated files ----------
  function sampleFiles(baseName, accept) {
    const finalOnly = accept.length === 1;
    const list = [
      { filename: `${baseName}.pdf`, sizeMB: 1.4, note: 'PDF' },
      { filename: `${baseName}.docx`, sizeMB: 0.6, note: finalOnly ? 'Word — not accepted at this stage' : 'Word document' },
      { filename: `${baseName}_photo.jpg`, sizeMB: 3.1, note: 'Standalone image' },
      { filename: `${baseName}_bundle.zip`, sizeMB: 4.8, note: 'ZIP archive' },
      { filename: `${baseName}.docm`, sizeMB: 0.7, note: 'Macro-enabled Word' },
      { filename: `${baseName}_high_res.pdf`, sizeMB: 24.6, note: 'Over 20 MB' },
    ];
    return list;
  }
  A.sampleFiles = sampleFiles;

  function downloadBlob(v, key, n, s) {
    const text = [
      'UniKL Agreement Management System — PROTOTYPE (fictional data)',
      '',
      'This is a simulated download. No real document exists.',
      `Submission: ${s.id} — ${s.title}`,
      `Document: ${A.slotLabel(key)} — version ${n} (${v.label})`,
      `Original filename: ${v.filename}`,
      `Uploaded by: ${personName(v.by, true)} on ${A.fmt(v.at)} (Malaysian time)`,
      '',
      'In the real system, files stay on private storage and every download is authorised and recorded in Activity & Audit.',
    ].join('\r\n');
    try {
      const blob = new Blob([text], { type: 'text/plain' });
      const a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = `SIMULATED_${v.filename}.txt`;
      document.body.appendChild(a); a.click(); a.remove();
      setTimeout(() => URL.revokeObjectURL(a.href), 1000);
    } catch (e) { /* ignore */ }
  }
  A.downloadBlob = downloadBlob;

  Object.assign(A, { icon, statusBadge, regStatusBadge, nextActorHtml, personName, avatar, fileIco, pcCue, typeLabel, progress, versionTable });
})();
