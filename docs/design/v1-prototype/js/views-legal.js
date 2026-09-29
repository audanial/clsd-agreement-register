/* Legal/Admin views: 7 Shared Submission Queue, Agreement Register list/record, 11 Guided Register creation. */
(function () {
  'use strict';
  const A = window.AMS;
  const UI = A.UI;
  const esc = A.esc;
  const icon = (n, c) => A.icon(n, c);

  // ---------- 7. Shared Submission Queue ----------
  A.views.queue = function () {
    const u = A.me();
    const S = A.state();
    const f = UI.queueFilter;
    const all = S.submissions.slice().sort((a, b) => b.updatedAt - a.updatedAt);
    const active = (s) => !['registered', 'not_proceeding'].includes(s.status);
    const groups = {
      legal: { label: 'Waiting for Legal', fn: (s) => A.nextActor(s) === 'legal' },
      requester: { label: 'Waiting for Requester', fn: (s) => A.nextActor(s) === 'requester' },
      unread: { label: 'Unread for me', fn: (s) => A.unreadCount(u, s) > 0 },
      active: { label: 'All active', fn: active },
      registered: { label: 'Registered', fn: (s) => s.status === 'registered' },
      closed: { label: 'Not Proceeding', fn: (s) => s.status === 'not_proceeding' },
      all: { label: 'All', fn: () => true },
    };
    const sinceMs = { '1': 86400000, '7': 7 * 86400000, '30': 30 * 86400000 }[f.since];
    const q = f.q.trim().toLowerCase();
    const rows = all.filter(groups[f.tab].fn)
      .filter((s) => !f.status || s.status === f.status)
      .filter((s) => !f.category || s.category === f.category)
      .filter((s) => !f.campus || s.campus === f.campus)
      .filter((s) => !sinceMs || A.now() - s.updatedAt <= sinceMs)
      .filter((s) => !q || [s.id, s.title, s.partner, A.user(s.requesterId).name, s.type].join(' ').toLowerCase().includes(q));
    const usedCampuses = [...new Set(all.map((s) => s.campus))];
    const filtered = f.status || f.category || f.campus || f.since || q;

    const primary = ['legal', 'requester', 'unread'];
    const pill = (k) => `<button class="pill" data-act="queue-tab" data-tab="${k}" aria-pressed="${f.tab === k}">${groups[k].label}<span class="n">${all.filter(groups[k].fn).length}</span></button>`;
    // The view already says who acts next in the two waiting views, so those rows show only the step.
    const showActor = !['legal', 'requester'].includes(f.tab);

    return `<div class="page">
      <div class="page-head"><div style="flex:1"><h1>Submission Queue</h1><p class="lede">Shared by all Legal users. Anyone can act on any submission.</p></div></div>
      <div class="card">
        <div class="toolbar">
          <div class="tabs-pill" role="group" aria-label="Queue views">
            ${primary.map(pill).join('')}<span class="pill-sep" aria-hidden="true"></span>${Object.keys(groups).filter((k) => !primary.includes(k)).map(pill).join('')}
          </div>
        </div>
        <div class="toolbar queue-filters" role="search" aria-label="Filter submissions" style="background:var(--surface-2)">
          <label class="sr-only" for="q-search">Search by ID, title, partner, requester or type</label>
          <input id="q-search" class="input search" type="search" placeholder="Search ID, title, partner, requester" value="${esc(f.q)}" data-bind="queueFilter.q" data-live="rerender">
          <label class="sr-only" for="q-status">Status</label>
          <select id="q-status" class="select inline" data-bind="queueFilter.status" data-live="rerender"><option value="">Any status</option>${Object.keys(A.STATUS).map((k) => `<option value="${k}" ${f.status === k ? 'selected' : ''}>${esc(A.STATUS[k].label)}</option>`).join('')}</select>
          <label class="sr-only" for="q-cat">Category</label>
          <select id="q-cat" class="select inline" data-bind="queueFilter.category" data-live="rerender"><option value="">Any category</option><option ${f.category === 'Academic' ? 'selected' : ''}>Academic</option><option ${f.category === 'Industry' ? 'selected' : ''}>Industry</option></select>
          <label class="sr-only" for="q-campus">Campus or department</label>
          <select id="q-campus" class="select inline" data-bind="queueFilter.campus" data-live="rerender"><option value="">Any campus / dept.</option>${usedCampuses.map((c) => `<option value="${c}" ${f.campus === c ? 'selected' : ''}>${esc(A.campusShort(c))}</option>`).join('')}</select>
          <label class="sr-only" for="q-since">Latest update</label>
          <select id="q-since" class="select inline" data-bind="queueFilter.since" data-live="rerender"><option value="">Updated any time</option><option value="1" ${f.since === '1' ? 'selected' : ''}>Updated in 24 hours</option><option value="7" ${f.since === '7' ? 'selected' : ''}>Updated in 7 days</option><option value="30" ${f.since === '30' ? 'selected' : ''}>Updated in 30 days</option></select>
          ${filtered ? '<button class="btn sm ghost" data-act="queue-clear">Clear filters</button>' : ''}
        </div>
        ${rows.length ? `<div class="table-wrap"><table class="table queue-table"><caption class="sr-only">${esc(groups[f.tab].label)}: ${rows.length} submission${rows.length === 1 ? '' : 's'}, latest update first</caption><thead><tr>
          <th scope="col">Submission</th><th scope="col">Requester</th><th scope="col">${showActor ? 'Next · Status' : 'Next step · Status'}</th><th scope="col">Unread</th><th scope="col">Latest update</th></tr></thead><tbody>
          ${rows.map((s) => {
            const un = A.unreadCount(u, s);
            const req = A.user(s.requesterId);
            const addWarn = s.type === 'ADDENDUM' && s.addendum.linkStatus === 'unresolved';
            return `<tr class="clickable ${un ? 'unread' : ''}" data-act="open-sub" data-sub="${s.id}">
              <td class="title-cell"><a href="#/sub/${s.id}/overview">${esc(s.title)}</a><div class="sub">${esc(s.id)} · ${esc(s.partner)}</div><div class="sub">${A.typeLabel(s)} · ${esc(s.category)} · ${esc(s.location)}</div>${addWarn ? `<div class="sub" style="color:var(--amber)">${icon('alert')} Original agreement unresolved</div>` : ''}</td>
              <td>${esc(req.name)}<div class="sub">${esc(A.campusShort(s.campus))}</div></td>
              <td class="status-cell">${showActor ? `${A.nextActorHtml(s, u)}<div class="sub">${esc(A.nextStepShort(s, true))}</div>` : `<div class="strong">${esc(A.nextStepShort(s, true))}</div>`}<div style="margin-top:6px">${A.statusBadge(s)}</div></td>
              <td>${un ? `<span class="unread-dot" title="${un} unread for you">${un}<span class="sr-only"> unread for you</span></span>` : '<span class="faint" aria-hidden="true">—</span><span class="sr-only">None unread</span>'}</td>
              <td>${A.fmt(s.updatedAt)}<div class="sub">${s.lastHandler ? `Last handled by ${esc(A.personName(s.lastHandler))}` : 'Not yet handled by Legal'}</div></td></tr>`;
          }).join('')}</tbody></table></div>`
          : `<div class="empty" role="status">${icon('search')}<h3>${filtered ? 'No matching submissions' : `Nothing in ${esc(groups[f.tab].label)}`}</h3>
            <p>${filtered ? 'Change the search or filters.' : 'Choose another view.'}</p>
            ${filtered ? '<button class="btn sm" data-act="queue-clear">Clear filters</button>' : f.tab !== 'all' ? '<button class="btn sm" data-act="queue-tab" data-tab="all">View all</button>' : ''}</div>`}
      </div>
      <p class="xsmall faint" style="margin-top:10px">Unread counts are yours alone. “Last handled by” is information, not an assignment.</p>
    </div>`;
  };

  // ---------- Agreement Register (Legal) ----------
  A.views.register = function () {
    const S = A.state();
    const f = UI.regFilter;
    const q = f.q.trim().toLowerCase();
    const rows = S.register.filter((r) => !f.type || r.type === f.type)
      .filter((r) => !q || [r.id, r.title, r.partner, r.pic, r.moaSubtype].join(' ').toLowerCase().includes(q));
    const types = [...new Set(S.register.map((r) => r.type))];
    return `<div class="page">
      <div class="page-head"><div style="flex:1"><h1>Agreement Register</h1><p class="lede">Official agreement records. Portal records are created only by Legal from Fully Executed submissions.</p></div></div>
      <div class="notice neutral" style="margin-bottom:14px">${icon('info')}<div>Historical LOI records and legacy <em>Pending</em> / <em>Awaiting partner</em> records are preserved unchanged. This list is included for context only — a full Register redesign is outside this prototype.</div></div>
      <div class="card">
        <div class="toolbar">
          <label class="sr-only" for="r-q">Search the Register</label>
          <input id="r-q" class="input search" type="search" placeholder="Search title, partner, PIC or ID" value="${esc(f.q)}" data-bind="regFilter.q" data-live="rerender">
          <label class="sr-only" for="r-type">Type</label>
          <select id="r-type" class="select inline" data-bind="regFilter.type" data-live="rerender"><option value="">All types</option>${types.map((t) => `<option ${f.type === t ? 'selected' : ''}>${t}</option>`).join('')}</select>
        </div>
        <div class="table-wrap"><table class="table"><thead><tr><th>Record</th><th>Partner</th><th>Type</th><th>Campus</th><th>Date signed</th><th>Expiry</th><th>Status</th><th>Source</th></tr></thead><tbody>
          ${rows.map((r) => `<tr class="clickable" data-act="open-agr" data-agr="${r.id}">
            <td class="title-cell"><a href="#/register/${r.id}">${esc(r.title)}</a><div class="sub">${esc(r.id)}${r.originalId ? ` · amends ${esc(r.originalId)}` : ''}</div></td>
            <td>${esc(r.partner)}<div class="sub">${esc(r.location)}</div></td>
            <td><span class="chip">${esc(r.type)}</span>${r.moaSubtype ? `<div class="sub">${esc(r.moaSubtype)}</div>` : ''}</td>
            <td>${esc(A.campusShort(r.campus))}</td>
            <td class="nowrap">${A.fmtDate(r.dateSigned)}</td>
            <td class="nowrap">${r.status === 'signed' && !r.expiry ? 'No fixed expiry' : A.fmtDate(r.expiry)}</td>
            <td>${A.regStatusBadge(r)}</td>
            <td>${r.submissionId ? `<a href="#/sub/${r.submissionId}/overview">${esc(r.submissionId)}</a>` : '<span class="faint">Historical record</span>'}</td></tr>`).join('')}
        </tbody></table></div>
      </div></div>`;
  };

  A.views.registerRecord = function (id) {
    const r = A.agr(id);
    if (!r) return A.denied('This Register record does not exist.');
    const S = A.state();
    const addenda = S.register.filter((x) => x.originalId === r.id);
    const inProgress = S.submissions.filter((s) => s.type === 'ADDENDUM' && s.addendum.originalId === r.id && s.status !== 'registered');
    const orig = r.originalId ? A.agr(r.originalId) : null;
    return `<div class="page">
      <div class="crumbs"><a href="#/register">Agreement Register</a> / ${esc(r.id)}</div>
      <div class="page-head"><div style="flex:1"><div class="ws-id">${esc(r.id)}</div><h1>${esc(r.title)}</h1><div class="ws-meta">${A.regStatusBadge(r)} <span class="chip">${esc(r.type)}${r.moaSubtype ? ' · ' + esc(r.moaSubtype) : ''}</span></div></div></div>
      <div class="grid-2"><div class="card card-pad"><dl class="kv">
        <dt>Partner</dt><dd>${esc(r.partner)} (${esc(r.location)})</dd>
        <dt>Category</dt><dd>${esc(r.category)}</dd>
        <dt>Campus</dt><dd>${esc(A.campusName(r.campus))}</dd>
        <dt>Date signed</dt><dd>${A.fmtDate(r.dateSigned)}</dd>
        <dt>Expiry</dt><dd>${r.expiry ? A.fmtDate(r.expiry) : r.status === 'signed' ? 'No fixed expiry (indefinite / until completion)' : '—'}</dd>
        <dt>PIC</dt><dd>${esc(r.pic)} <span class="xsmall faint">(name only — not a system account)</span></dd>
        ${orig ? `<dt>Amends</dt><dd><a href="#/register/${orig.id}">${esc(orig.id)}</a> — ${esc(orig.title)}</dd>` : ''}
        ${r.notes ? `<dt>Remarks</dt><dd>${esc(r.notes)}</dd>` : ''}
        <dt>Linked submission</dt><dd>${r.submissionId ? `<a href="#/sub/${r.submissionId}/overview">${esc(r.submissionId)}</a> (permanent link)` : 'None — historical record'}</dd>
        ${r.createdAt ? `<dt>Created</dt><dd>${A.fmt(r.createdAt)} by ${esc(A.personName(r.createdBy, true))}</dd>` : ''}
      </dl></div>
      <div class="card card-pad side-card"><h3>Addendums</h3>
        ${addenda.length || inProgress.length ? `<ul class="blockers">${addenda.map((x) => `<li class="ok">${icon('book')}<a href="#/register/${x.id}">${esc(x.id)}</a> — ${esc(x.title)}</li>`).join('')}${inProgress.map((s) => `<li class="no">${icon('clock')}<a href="#/sub/${s.id}/overview">${esc(s.id)}</a> — ${esc(A.STATUS[s.status].label)}${s.addendum.linkStatus === 'confirmed' ? '' : ' (link not yet confirmed)'}</li>`).join('')}</ul>` : '<p class="small muted">No Addendums linked.</p>'}
      </div></div></div>`;
  };

  // ---------- 11. Guided Register creation ----------
  function isoDate(ms) { const d = new Date(ms + 8 * 3600000); return d.toISOString().slice(0, 10); }
  function initForm(s) {
    const m = s.milestones;
    return {
      title: s.title, partner: s.partner, location: s.location, category: s.category, campus: s.campus, type: s.type, moaSubtype: s.moaSubtype || '',
      dateSigned: m.partnerSignedAt ? isoDate(m.partnerSignedAt) : '', expiry: '', indefinite: false, pic: A.user(s.requesterId).name, notes: '', tried: false, fields: {},
    };
  }

  A.views.registerCreate = function (id) {
    const s = A.sub(id);
    if (!s) return A.denied('This submission does not exist.');
    if (UI.viewRev[id] == null) UI.viewRev[id] = s.rev;
    const back = `<div class="crumbs"><a href="#/queue">Submission Queue</a> / <a href="#/sub/${s.id}/overview">${esc(s.id)}</a> / Create Register Record</div>`;

    if (s.status === 'registered') {
      const r = A.agr(s.agreementId);
      return `<div class="page">${back}
        <div class="card card-pad next-card done"><div class="kicker">Success</div><h1 style="margin-top:4px">${icon('check')} Register record ${esc(r.id)} created</h1>
          <p class="muted" style="margin-top:6px">One Signed record was created and permanently linked to ${esc(s.id)}. The submission is now <strong>Registered</strong> and the requester can see this outcome on their submission.</p>
          ${A.registerSummary(s, true)}
          <div class="actions"><a class="btn primary" href="#/register/${r.id}">Open Register record</a><a class="btn" href="#/sub/${s.id}/overview">Back to submission</a><a class="btn ghost" href="#/queue">Submission Queue</a></div></div></div>`;
    }
    const blockers = A.registerBlockers(s);
    if (blockers.length) {
      return `<div class="page">${back}<div class="card card-pad"><h1>Create Register Record</h1>
        <div class="notice warn" style="margin-top:12px">${icon('lock')}<div><strong>Not available yet.</strong><ul class="blockers">${blockers.map((b) => `<li class="no">${icon('alert')}${esc(b)}</li>`).join('')}</ul></div></div>
        <p style="margin-top:14px"><a class="btn" href="#/sub/${s.id}/overview">Back to submission</a></p></div></div>`;
    }

    const f = UI.regForm[id] || (UI.regForm[id] = initForm(s));
    const e = f.tried ? A.registerFieldErrors(f) : {};
    const fld = (k) => e[k] ? 'invalid' : '';
    const err = (k) => e[k] ? `<div class="error">${icon('alert')}${esc(e[k])}</div>` : '';
    const orig = s.type === 'ADDENDUM' ? A.agr(s.addendum.originalId) : null;
    return `<div class="page">${back}
      <div class="page-head"><div style="flex:1"><h1>Create Register Record</h1><p class="lede">Pre-filled from ${esc(s.id)}. Review, correct and complete the official details, then confirm. Nothing is saved until you confirm.</p></div></div>
      <div class="grid-form">
        <div class="stack">
          <div class="card"><div class="card-head"><h2>Agreement details</h2><span class="spacer"></span><span class="chip">Pre-filled — please check</span></div>
            <div class="card-body form-grid">
              <label class="field full ${fld('title')}"><span class="label">Title <span class="req">*</span></span><input class="input" data-bind="regForm.${id}.title" value="${esc(f.title)}">${err('title')}</label>
              <label class="field"><span class="label">Agreement type</span><input class="input" value="${esc(f.type)}" disabled><div class="hint">From the submission classification.</div></label>
              ${f.type === 'MOA' ? `<label class="field"><span class="label">MOA subtype / arrangement <span class="faint">(descriptive)</span></span><input class="input" data-bind="regForm.${id}.moaSubtype" value="${esc(f.moaSubtype)}"><div class="hint">The Register type stays MOA.</div></label>` : '<div></div>'}
              <label class="field ${fld('partner')}"><span class="label">Partner <span class="req">*</span></span><input class="input" data-bind="regForm.${id}.partner" value="${esc(f.partner)}">${err('partner')}</label>
              <label class="field"><span class="label">Partner location</span><select class="select" data-bind="regForm.${id}.location"><option ${f.location === 'Local' ? 'selected' : ''}>Local</option><option ${f.location === 'International' ? 'selected' : ''}>International</option></select></label>
              <label class="field"><span class="label">Engagement category</span><select class="select" data-bind="regForm.${id}.category"><option ${f.category === 'Academic' ? 'selected' : ''}>Academic</option><option ${f.category === 'Industry' ? 'selected' : ''}>Industry</option></select></label>
              <label class="field"><span class="label">Campus / department</span><select class="select" data-bind="regForm.${id}.campus">${A.CAMPUSES.map((c) => `<option value="${c[0]}" ${f.campus === c[0] ? 'selected' : ''}>${esc(c[1])}</option>`).join('')}</select></label>
            </div></div>
          <div class="card"><div class="card-head"><h2>Register details to complete</h2></div>
            <div class="card-body form-grid">
              <label class="field ${fld('dateSigned')}"><span class="label">Date signed <span class="req">*</span></span><input class="input" type="date" data-bind="regForm.${id}.dateSigned" value="${esc(f.dateSigned)}"><div class="hint">Suggested from when the both-parties-signed PDF was submitted — check it against the document.</div>${err('dateSigned')}</label>
              <div class="field ${fld('expiry')}"><span class="label">Expiry date ${f.indefinite ? '' : '<span class="req">*</span>'}</span>
                <input class="input" type="date" data-bind="regForm.${id}.expiry" value="${esc(f.expiry)}" ${f.indefinite ? 'disabled' : ''} aria-label="Expiry date">
                <label class="check" style="margin-top:8px"><input type="checkbox" data-bind="regForm.${id}.indefinite" data-live="rerender" ${f.indefinite ? 'checked' : ''}><span class="small">No fixed expiry (indefinite / until completion)</span></label>${err('expiry')}</div>
              <label class="field ${fld('pic')}"><span class="label">PIC name <span class="req">*</span></span><input class="input" data-bind="regForm.${id}.pic" value="${esc(f.pic)}"><div class="hint">A person's name only — not a system account.</div>${err('pic')}</label>
              <label class="field"><span class="label">Remarks <span class="faint">(optional)</span></span><input class="input" data-bind="regForm.${id}.notes" value="${esc(f.notes)}"></label>
              ${orig ? `<div class="field full"><span class="label">Original agreement (Addendum)</span><div class="slot-row done"><div><strong>${esc(orig.id)}</strong> — ${esc(orig.title)} <div class="small muted">${esc(orig.partner)} · ${esc(orig.type)}${orig.moaSubtype ? ' · ' + esc(orig.moaSubtype) : ''} · confirmed by ${esc(A.personName(s.addendum.confirmedBy, true))}</div></div>${icon('link')}</div></div>` : ''}
            </div></div>
        </div>
        <div class="card sticky"><div class="card-head"><h3>What confirming does</h3></div>
          <div class="card-body"><ul class="ready-list">
            <li class="ok">${icon('check')}Creates <strong>one</strong> ${esc(s.type)} record with status Signed</li>
            <li class="ok">${icon('link')}Links it permanently to ${esc(s.id)}</li>
            ${orig ? `<li class="ok">${icon('link')}Links this Addendum to ${esc(orig.id)}</li>` : ''}
            <li class="ok">${icon('activity')}Records the creation and link in Activity &amp; Audit</li>
            <li class="ok">${icon('arrow')}Moves the submission to Registered</li>
          </ul><p class="xsmall muted" style="margin-top:10px">All of this succeeds together or nothing is saved. There is no separate Effective Date — the Register uses Date signed.</p></div>
          <div class="card-foot"><button class="btn primary lg" style="width:100%" data-act="register-review" data-sub="${s.id}">${icon('check')}Review and confirm</button></div></div>
      </div></div>`;
  };

  A.MODALS.registerConfirm = {
    title: () => 'Confirm Register record',
    body: (d) => {
      const s = A.sub(d.subId); const f = UI.regForm[d.subId];
      return `<p>Create this record and link it to <strong>${esc(s.id)}</strong>? It cannot be undone from this screen.</p>
        <dl class="kv" style="margin-top:12px"><dt>Title</dt><dd>${esc(f.title)}</dd><dt>Type</dt><dd>${esc(f.type)}${f.type === 'MOA' && f.moaSubtype ? ' · ' + esc(f.moaSubtype) : ''}</dd><dt>Partner</dt><dd>${esc(f.partner)} (${esc(f.location)})</dd>
        <dt>Campus</dt><dd>${esc(A.campusShort(f.campus))}</dd><dt>Date signed</dt><dd>${A.fmtDate(f.dateSigned)}</dd><dt>Expiry</dt><dd>${f.indefinite ? 'No fixed expiry' : A.fmtDate(f.expiry)}</dd><dt>PIC</dt><dd>${esc(f.pic)}</dd><dt>Status</dt><dd>Signed</dd></dl>`;
    },
    foot: (d) => `<button class="btn" data-act="close-modal">Go back and edit</button><button class="btn primary" data-act="register-confirm" data-sub="${d.subId}" ${d.busy ? 'disabled aria-busy="true"' : ''}>${d.busy ? 'Creating record…' : 'Create record'}</button>`,
  };
})();
