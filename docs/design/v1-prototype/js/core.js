/* Core simulated state, product rules and workflow transitions.
   In production every rule here is enforced on the server; the prototype mirrors the
   rules in the browser only so reviewers can see what is allowed and what is blocked. */
(function () {
  'use strict';
  const A = window.AMS;
  const STORE_KEY = 'unikl-ams-v1-prototype-state';

  let S = null;

  // ---------- persistence ----------
  function load() {
    try {
      const raw = localStorage.getItem(STORE_KEY);
      if (raw) {
        const parsed = JSON.parse(raw);
        if (parsed && parsed.version === 3) { S = parsed; return; }
      }
    } catch (e) { /* storage unavailable: fall back to in-memory seed */ }
    S = A.buildSeed();
  }
  function save() {
    try { localStorage.setItem(STORE_KEY, JSON.stringify(S)); } catch (e) { /* ignore */ }
  }
  function reset() {
    S = A.buildSeed();
    save();
  }
  const state = () => S;

  // ---------- clock & ids ----------
  const now = () => S.clock;
  function tick(minutes) { S.clock += (minutes || 4) * 60000; return S.clock; }
  let uidN = 0;
  const uid = (p) => p + '-' + Date.now().toString(36) + (++uidN).toString(36);

  // ---------- formatting (Malaysian time, UTC+8) ----------
  const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  function myt(ms) { return new Date(ms + 8 * 3600000); }
  function fmt(ms) {
    if (ms == null) return '—';
    const d = myt(ms);
    let h = d.getUTCHours(); const ap = h >= 12 ? 'PM' : 'AM'; h = h % 12 || 12;
    return `${d.getUTCDate()} ${MONTHS[d.getUTCMonth()]} ${d.getUTCFullYear()}, ${h}:${String(d.getUTCMinutes()).padStart(2, '0')} ${ap}`;
  }
  function fmtDate(v) {
    if (v == null || v === '') return '—';
    if (typeof v === 'string') { const [y, m, d] = v.split('-').map(Number); return `${d} ${MONTHS[m - 1]} ${y}`; }
    const d = myt(v); return `${d.getUTCDate()} ${MONTHS[d.getUTCMonth()]} ${d.getUTCFullYear()}`;
  }
  function rel(ms) {
    const diff = Math.round((now() - ms) / 60000);
    if (diff < 1) return 'just now';
    if (diff < 60) return `${diff} minute${diff === 1 ? '' : 's'} ago`;
    const h = Math.round(diff / 60);
    if (h < 24) return `${h} hour${h === 1 ? '' : 's'} ago`;
    const d = Math.round(h / 24);
    return `${d} day${d === 1 ? '' : 's'} ago`;
  }
  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  }
  const size = (mb) => mb < 1 ? `${Math.round(mb * 1024)} KB` : `${mb.toFixed(1)} MB`;

  // ---------- lookups ----------
  const user = (id) => A.USERS[id];
  const me = () => A.USERS[S.currentUser];
  const isLegal = (u) => !!u && (u.role === 'legal' || u.role === 'admin');
  const sub = (id) => S.submissions.find((s) => s.id === id);
  const agr = (id) => S.register.find((r) => r.id === id);
  const campusName = (code) => (A.CAMPUSES.find((c) => c[0] === code) || [code, code])[1];
  const campusShort = (code) => {
    const n = campusName(code);
    return n.startsWith('UniKL ') ? n.split(' — ')[0] : n;
  };
  const slotLabel = (key) => A.SLOT_DEFS[key].label;
  const signatory = (s) => (s.category === 'Industry' ? 'CEO' : 'Vice Chancellor');

  // ---------- checklist ----------
  function requiredIntake(category, location) {
    if (category === 'Academic') return ['agreement', 'requisition'];
    if (category === 'Industry') {
      const corp = location === 'International' ? 'business_reg' : location === 'Local' ? 'ssm' : null;
      return ['agreement', 'memo', 'requisition', 'due_diligence', 'company_profile'].concat(corp ? [corp] : []);
    }
    return [];
  }
  // true = required, false = not required, null = undecided (Addendum awaiting Legal's decision)
  function stampingApplies(s) {
    if (s.type === 'MOA') return true;
    if (s.type === 'ADDENDUM') return s.addendum ? s.addendum.stampingRequired : null;
    return false;
  }
  function currentVersion(s, key) {
    const sl = s.slots[key];
    return sl && sl.versions.length ? sl.versions[sl.versions.length - 1] : null;
  }
  function versionNo(s, key, v) { return s.slots[key].versions.indexOf(v) + 1; }

  // Ordered slot list for display: intake slots (required, then retired-with-history) and execution slot.
  function slotList(s) {
    const req = requiredIntake(s.category, s.location);
    const out = req.map((k) => ({ key: k, required: true, retired: false, execution: false }));
    Object.keys(s.slots).forEach((k) => {
      if (k === 'stamp_certificate' || req.includes(k)) return;
      if (s.slots[k].versions.length) out.push({ key: k, required: false, retired: true, execution: false });
    });
    const st = stampingApplies(s);
    const certHasFiles = s.slots.stamp_certificate && s.slots.stamp_certificate.versions.length;
    if (st === true || certHasFiles) out.push({ key: 'stamp_certificate', required: st === true, retired: false, execution: true, undecided: false });
    else if (st === null) out.push({ key: 'stamp_certificate', required: false, retired: false, execution: true, undecided: true });
    return out;
  }
  function ensureSlot(s, key) { if (!s.slots[key]) s.slots[key] = { versions: [], reopened: false }; return s.slots[key]; }

  // ---------- responsibility ----------
  function nextActor(s) { return A.STATUS[s.status].actor; }
  function nextActorLabel(s) {
    const a = nextActor(s);
    if (a === 'legal') return 'Waiting for Legal';
    if (a === 'requester') return 'Waiting for Requester';
    if (a === 'closed') return 'Closed';
    return 'Complete';
  }

  // ---------- access ----------
  function canView(u, s) { return isLegal(u) || (s && s.requesterId === u.id); }
  function mySubmissions(u) { return S.submissions.filter((s) => s.requesterId === u.id); }
  function unreadCount(u, s) {
    return s.messages.filter((m) => m.by !== u.id && !m.readBy.includes(u.id)).length;
  }
  function totalUnread(u) {
    const list = isLegal(u) ? S.submissions : mySubmissions(u);
    return list.reduce((n, s) => n + unreadCount(u, s), 0);
  }
  // Addendum originals a requester may pick: only Register records linked to their own registered submissions.
  function accessibleOriginals(u) {
    return S.register.filter((r) => r.submissionId && (sub(r.submissionId) || {}).requesterId === u.id);
  }
  function openRequest(s) {
    if (!s.request) return null;
    const m = s.messages.find((x) => x.id === s.request);
    return m && m.request.status === 'open' ? m : null;
  }
  function recentAccess(s, u, key) {
    // Another Legal user opened/downloaded this document within the last 60 simulated minutes.
    return s.access.filter((a) => a.slot === key && a.by !== u.id && now() - a.at <= 60 * 60000)
      .sort((a, b) => b.at - a.at)[0] || null;
  }

  // ---------- upload rules ----------
  const REVIEW_TYPES = ['pdf', 'docx'];
  const FINAL_TYPES = ['pdf'];
  const MAX_MB = 20;

  function uploadRule(u, s, key) {
    const legal = isLegal(u);
    const locked = (reason) => ({ ok: false, reason });
    if (!canView(u, s)) return locked('Not available.');
    const st = s.status;
    const isCert = key === 'stamp_certificate';
    if (st === 'not_proceeding') return locked('This submission is closed. Documents and history are kept for reference.');
    if (st === 'fully_executed' || st === 'registered') return locked('Final documents are locked. Downloads and version history remain available.');
    if (legal) {
      if (key === 'agreement' && st === 'review_completed') return { ok: true, accept: FINAL_TYPES, label: 'UniKL Signed', verb: `Upload UniKL-signed agreement (${signatory(s)})` };
      if (st === 'pending_review' || st === 'in_review') return locked('Requester documents stay as submitted. Use a revision request to ask for a replacement.');
      if (st === 'action_required') return locked('Waiting for the requester to respond to the current request.');
      return locked('Legal uploads only the UniKL-signed agreement at this stage.');
    }
    // Requester
    if (st === 'pending_review' || st === 'in_review') return locked('Legal is reviewing this version. Send a message if a correction is needed.');
    if (st === 'review_completed') return locked('Legal is obtaining the UniKL signature. Nothing to upload yet.');
    if (st === 'final_verification') return locked('Legal is verifying your final documents. Send a message if something is wrong.');
    if (st === 'action_required') {
      const req = openRequest(s);
      if (req && req.request.slots.includes(key)) {
        if (req.request.phase === 'final') {
          return { ok: true, accept: FINAL_TYPES, label: isCert ? 'LHDN Stamp Certificate' : 'Both Parties Signed', verb: 'Upload corrected PDF' };
        }
        const first = !(s.slots[key] && s.slots[key].versions.length);
        return { ok: true, accept: REVIEW_TYPES, label: first ? 'Submitted for Review' : 'Revised During Legal Review', verb: first ? 'Upload file' : 'Upload replacement' };
      }
      return locked('Not part of Legal\'s request — this document stays locked.');
    }
    if (st === 'awaiting_partner') {
      if (key === 'agreement') return { ok: true, accept: FINAL_TYPES, label: 'Both Parties Signed', verb: 'Upload both-parties-signed PDF' };
      if (isCert) return locked('Upload the both-parties-signed agreement first.');
      return locked('Intake documents are locked after review.');
    }
    if (st === 'awaiting_stamping') {
      if (isCert) return { ok: true, accept: FINAL_TYPES, label: 'LHDN Stamp Certificate', verb: 'Upload stamp certificate PDF' };
      if (key === 'agreement') return locked('Signed agreement received. Send a message if it needs correcting.');
      return locked('Intake documents are locked after review.');
    }
    return locked('Locked at this stage.');
  }

  function validateFile(file, accept) {
    const ext = file.filename.split('.').pop().toLowerCase();
    if (['jpg', 'jpeg', 'png', 'heic'].includes(ext)) return 'Standalone images are not accepted. Scan the document to PDF instead.';
    if (['zip', 'rar', '7z'].includes(ext)) return 'Archive files (ZIP) are not accepted. Upload each document in its own slot.';
    if (['docm', 'dotm'].includes(ext)) return 'Macro-enabled Word files (.docm) are not accepted. Save as .docx or PDF.';
    if (['exe', 'bat', 'msi'].includes(ext)) return 'Executable files are not accepted.';
    if (!accept.includes(ext)) return accept.length === 1 ? 'This stage needs a PDF file. Word files are accepted only during review.' : 'Only PDF or Word (.docx) files are accepted.';
    if (file.sizeMB > MAX_MB) return `This file is ${size(file.sizeMB)}. The limit is ${MAX_MB} MB per file.`;
    return null;
  }

  // ---------- mutation plumbing ----------
  function log(s, text, legalOnly) {
    s.activity.push({ id: uid('a'), at: now(), by: S.currentUser, text, legalOnly: !!legalOnly });
  }
  function commit(s, opts) {
    s.rev += 1;
    // Legal-only events (Internal Notes) must not move the requester-visible "last update" time.
    if (!(opts && opts.internal)) s.updatedAt = now();
    if (isLegal(me()) && !(opts && opts.noHandler)) s.lastHandler = S.currentUser;
    save();
    return { ok: true, rev: s.rev };
  }
  function fail(error) { return { ok: false, error }; }
  // Server-side style stale check: the action carries the revision the user was looking at.
  function stale(s, expectRev) {
    if (expectRev == null || expectRev === s.rev) return false;
    s.activity.push({ id: uid('a'), at: now(), by: S.currentUser, text: 'Rejected a conflicting action — the submission had changed since it was opened', legalOnly: true });
    save();
    return true;
  }
  function begin(id, expectRev, needLegal) {
    const s = sub(id);
    const u = me();
    if (!s || !canView(u, s)) return { err: fail('This submission is not available to you.') };
    if (needLegal && !isLegal(u)) return { err: fail('Only Legal or Admin can do this.') };
    if (needLegal && stale(s, expectRev)) return { err: { ok: false, conflict: true, error: 'This submission changed since you opened it. Refresh to see the latest version.' } };
    tick(3);
    return { s, u };
  }
  function statusChange(s, to) {
    const from = s.status;
    s.status = to;
    return `status changed to ${A.STATUS[to].label}`;
  }

  // ---------- actions ----------
  const actions = {};

  actions.createSubmission = function (f) {
    const u = me();
    if (u.role !== 'requester') return fail('Only Requesting Staff create submissions.');
    tick(2);
    const idn = 'SUB-2026-' + String(S.nextSub++).padStart(4, '0');
    const req = requiredIntake(f.category, f.location);
    const slots = {};
    req.forEach((k) => {
      const file = f.files[k];
      slots[k] = { versions: [{ id: uid('v'), filename: file.filename, sizeMB: file.sizeMB, ext: file.filename.split('.').pop().toLowerCase(), by: u.id, at: now(), label: 'Submitted for Review' }], reopened: false };
    });
    const s = {
      id: idn, title: f.title.trim(), requesterId: u.id, campus: f.campus, partner: f.partner.trim(), scope: f.scope.trim(),
      category: f.category, location: f.location, type: f.type, moaSubtype: f.type === 'MOA' ? (f.moaSubtype || '').trim() : '',
      addendum: null, status: 'pending_review', prevStatus: null, createdAt: now(), submittedAt: now(), updatedAt: now(), lastHandler: null,
      slots, request: null, milestones: {}, closure: null, reopenings: [], agreementId: null, messages: [], notes: [], activity: [], access: [], rev: 1, finalAccepted: null,
    };
    let extra = '';
    if (f.type === 'ADDENDUM') {
      s.addendum = {
        mode: f.addMode, originalId: f.addMode === 'selected' ? f.originalId : null,
        notFound: f.addMode === 'not_found' ? Object.assign({}, f.notFound) : null,
        purpose: f.purpose.trim(), linkStatus: f.addMode === 'selected' ? 'requester_selected' : 'unresolved',
        confirmedBy: null, confirmedAt: null, stampingRequired: null, stampingBy: null, stampingAt: null,
      };
      extra = f.addMode === 'selected' ? ` — original agreement ${f.originalId} selected by requester` : ' — original agreement marked "Agreement not found"';
    }
    s.activity.push({ id: uid('a'), at: now(), by: u.id, text: `Submission created and officially submitted with ${req.length} of ${req.length} required documents${extra}`, legalOnly: false });
    S.submissions.unshift(s);
    save();
    return { ok: true, id: idn };
  };

  actions.upload = function (id, key, file, expectRev) {
    const s0 = sub(id); const u0 = me();
    if (!s0) return fail('Not available.');
    const rule = uploadRule(u0, s0, key);
    if (!rule.ok) return fail(rule.reason);
    const err = validateFile(file, rule.accept);
    if (err) {
      s0.activity.push({ id: uid('a'), at: now(), by: u0.id, text: `Upload rejected for ${slotLabel(key)} (${file.filename}) — current file unchanged`, legalOnly: false });
      save();
      return { ok: false, error: err, validation: true };
    }
    const { s, u, err: e } = begin(id, expectRev, isLegal(u0));
    if (e) return e;
    const sl = ensureSlot(s, key);
    sl.versions.push({ id: uid('v'), filename: file.filename, sizeMB: file.sizeMB, ext: file.filename.split('.').pop().toLowerCase(), by: u.id, at: now(), label: rule.label });
    const n = sl.versions.length;
    if (rule.label === 'UniKL Signed') s.milestones.unikSignedAt = now();
    log(s, `Uploaded ${slotLabel(key)} v${n} (${rule.label})${n > 1 ? ' — earlier versions retained' : ''}`);
    return commit(s);
  };

  actions.download = function (id, key, n) {
    const s = sub(id); const u = me();
    if (!s || !canView(u, s)) return fail('Not available.');
    const v = s.slots[key].versions[n - 1];
    if (isLegal(u)) s.access.push({ by: u.id, slot: key, n, at: now() });
    log(s, `Downloaded ${slotLabel(key)} v${n} (${v.filename})`);
    save();
    return { ok: true, version: v };
  };

  actions.sendMessage = function (id, text) {
    if (!text || !text.trim()) return fail('Write a message first.');
    const { s, u, err } = begin(id);
    if (err) return err;
    s.messages.push({ id: uid('m'), kind: 'message', by: u.id, at: now(), text: text.trim(), readBy: [u.id] });
    return commit(s, { noHandler: false });
  };

  actions.addNote = function (id, text) {
    if (!isLegal(me())) return fail('Only Legal or Admin can add Internal Legal Notes.');
    if (!text || !text.trim()) return fail('Write a note first.');
    const { s, u, err } = begin(id);
    if (err) return err;
    s.notes.push({ id: uid('n'), by: u.id, at: now(), text: text.trim() });
    log(s, 'Added an Internal Legal Note', true);
    return commit(s, { internal: true });
  };

  actions.markRead = function (id) {
    const s = sub(id); const u = me();
    if (!s || !canView(u, s)) return;
    let changed = false;
    s.messages.forEach((m) => { if (!m.readBy.includes(u.id)) { m.readBy.push(u.id); changed = true; } });
    if (changed) save();
  };

  actions.startReview = function (id, rev) {
    const { s, err } = begin(id, rev, true);
    if (err) return err;
    if (s.status !== 'pending_review') return fail('Review can only start from Pending Legal Review.');
    log(s, 'Started Legal review — ' + statusChange(s, 'in_review'));
    return commit(s);
  };

  actions.requestAction = function (id, p, rev) {
    const s0 = sub(id);
    const phase = s0 && s0.status === 'final_verification' ? 'final' : 'review';
    if (!p.text || p.text.trim().length < 15) return fail('Write a clear instruction (at least a full sentence) so the requester knows exactly what to do.');
    if (!p.clarify && !(p.slots && p.slots.length)) return fail('Choose a written clarification, at least one document to replace, or both.');
    const { s, u, err } = begin(id, rev, true);
    if (err) return err;
    if (!['in_review', 'final_verification'].includes(s.status)) return fail(`Requests can be sent during In Review or Final Verification. This submission is ${A.STATUS[s.status].label}.`);
    const m = { id: uid('m'), kind: 'request', by: u.id, at: now(), text: p.text.trim(), readBy: [u.id], request: { clarify: !!p.clarify, slots: p.slots.slice(), phase, status: 'open' } };
    s.messages.push(m);
    s.request = m.id;
    p.slots.forEach((k) => { ensureSlot(s, k).reopened = true; });
    const parts = [];
    if (p.clarify) parts.push('clarification');
    if (p.slots.length) parts.push('replacement of ' + p.slots.map(slotLabel).join(', '));
    const verb = phase === 'final' ? 'Returned final package for correction' : 'Requested';
    log(s, `${verb} ${parts.join(' and ')} — ${statusChange(s, 'action_required')}`);
    return commit(s);
  };

  function responseReadiness(s) {
    const req = openRequest(s);
    if (!req) return null;
    const done = []; const pending = [];
    req.request.slots.forEach((k) => {
      const v = currentVersion(s, k);
      if (v && v.at > req.at && v.by === s.requesterId) done.push(k); else pending.push(k);
    });
    return { req, done, pending, needAnswer: req.request.clarify };
  }

  actions.submitResponse = function (id, answer) {
    const s0 = sub(id); const u = me();
    if (!s0 || s0.requesterId !== u.id) return fail('Only the submitting requester can respond.');
    if (s0.status !== 'action_required') return fail('There is no open request to respond to.');
    const r = responseReadiness(s0);
    if (r.needAnswer && (!answer || answer.trim().length < 3)) return fail('Answer Legal\'s clarification question before submitting.');
    if (r.pending.length) return fail('Upload a new file for: ' + r.pending.map(slotLabel).join(', ') + '.');
    const { s, err } = begin(id);
    if (err) return err;
    const req = r.req;
    req.request.status = 'answered';
    req.request.answeredAt = now();
    s.messages.push({ id: uid('m'), kind: 'response', by: u.id, at: now(), text: (answer || '').trim(), readBy: [u.id], response: { requestId: req.id, replaced: r.done.slice() } });
    req.request.slots.forEach((k) => { s.slots[k].reopened = false; });
    s.request = null;
    const to = req.request.phase === 'final' ? 'final_verification' : 'in_review';
    log(s, `Submitted response${r.done.length ? ' with new ' + r.done.map(slotLabel).join(', ') : ''} — ${statusChange(s, to)}`);
    return commit(s);
  };

  actions.correctClassification = function (id, next, reason, rev) {
    if (!reason || reason.trim().length < 10) return fail('Explain the correction — the requester will see this reason.');
    const s0 = sub(id);
    if (!s0) return fail('Not available.');
    if (!['pending_review', 'in_review'].includes(s0.status)) return fail('Classification can be corrected before review is completed, while no request is open.');
    const changes = [];
    ['category', 'location', 'type'].forEach((k) => { if (next[k] !== s0[k]) changes.push(`${k === 'type' ? 'Agreement type' : k === 'category' ? 'Engagement category' : 'Partner location'}: ${s0[k]} → ${next[k]}`); });
    if (!changes.length) return fail('Nothing changed. Choose a different classification or cancel.');
    const { s, u, err } = begin(id, rev, true);
    if (err) return err;
    const before = requiredIntake(s.category, s.location);
    const after = requiredIntake(next.category, next.location);
    s.category = next.category; s.location = next.location;
    if (next.type !== s.type) {
      s.type = next.type;
      if (s.type !== 'MOA' && s.moaSubtype) { changes.push(`MOA subtype "${s.moaSubtype}" kept in history only`); s.moaSubtype = ''; }
    }
    const added = after.filter((k) => !before.includes(k));
    const removed = before.filter((k) => !after.includes(k));
    const missing = after.filter((k) => !(s.slots[k] && s.slots[k].versions.length));
    let msgText = `Classification corrected by Legal — ${changes.join('; ')}.`;
    if (added.length) msgText += ` Checklist now also requires: ${added.map(slotLabel).join(', ')}.`;
    if (removed.length) msgText += ` No longer required (kept with history): ${removed.map(slotLabel).join(', ')}.`;
    log(s, msgText);
    if (missing.length) {
      if (s.status === 'pending_review') log(s, 'Started Legal review — status changed to In Review');
      const m = {
        id: uid('m'), kind: 'request', by: u.id, at: now(), readBy: [u.id],
        text: `${reason.trim()}\n\nBecause the classification changed, please upload: ${missing.map(slotLabel).join(', ')}. Your existing documents are kept.`,
        request: { clarify: false, slots: missing.slice(), phase: 'review', status: 'open' },
      };
      s.messages.push(m);
      s.request = m.id;
      missing.forEach((k) => { ensureSlot(s, k).reopened = true; });
      log(s, `Requested ${missing.map(slotLabel).join(', ')} after classification correction — ${statusChange(s, 'action_required')}`);
    } else {
      s.messages.push({ id: uid('m'), kind: 'message', by: u.id, at: now(), text: `${reason.trim()}\n\n(${changes.join('; ')}. No new documents are needed.)`, readBy: [u.id] });
    }
    return commit(s);
  };

  function reviewBlockers(s) {
    const b = [];
    if (s.status !== 'in_review') b.push(`Status must be In Review (currently ${A.STATUS[s.status].label}).`);
    if (openRequest(s)) b.push('An Action Required request is still open.');
    const missing = requiredIntake(s.category, s.location).filter((k) => !(s.slots[k] && s.slots[k].versions.length));
    if (missing.length) b.push('Missing required documents: ' + missing.map(slotLabel).join(', ') + '.');
    if (s.type === 'ADDENDUM' && s.addendum.stampingRequired == null) b.push('Record whether LHDN stamping is required for this Addendum.');
    return b;
  }

  actions.completeReview = function (id, rev) {
    const s0 = sub(id);
    const b = s0 ? reviewBlockers(s0) : ['Not available.'];
    if (b.length) return fail(b.join(' '));
    const { s, u, err } = begin(id, rev, true);
    if (err) return err;
    s.milestones.reviewCompletedAt = now();
    s.milestones.reviewCompletedBy = u.id;
    log(s, `Completed Legal review — ${statusChange(s, 'review_completed')} (${signatory(s)})`);
    return commit(s);
  };

  function unikSignedVersion(s) {
    const v = currentVersion(s, 'agreement');
    return v && v.label === 'UniKL Signed' ? v : null;
  }

  actions.sendForPartner = function (id, rev) {
    const s0 = sub(id);
    if (!s0 || s0.status !== 'review_completed') return fail('Only available after Review Completed.');
    if (!unikSignedVersion(s0)) return fail('Upload the UniKL-signed agreement PDF first.');
    const { s, err } = begin(id, rev, true);
    if (err) return err;
    s.milestones.sentForPartnerAt = now();
    log(s, `Sent Agreement v${s.slots.agreement.versions.length} for partner signature — ${statusChange(s, 'awaiting_partner')}`);
    return commit(s);
  };

  function signedThisStage(s) {
    const v = currentVersion(s, 'agreement');
    return v && v.label === 'Both Parties Signed' && v.at >= (s.milestones.sentForPartnerAt || 0) ? v : null;
  }
  function certThisStage(s) {
    const v = currentVersion(s, 'stamp_certificate');
    return v && v.at >= (s.milestones.partnerSignedAt || 0) ? v : null;
  }

  actions.submitSigned = function (id) {
    const s0 = sub(id); const u = me();
    if (!s0 || s0.requesterId !== u.id) return fail('Only the submitting requester can do this.');
    if (s0.status !== 'awaiting_partner') return fail('Not at the partner-signature stage.');
    if (!signedThisStage(s0)) return fail('Upload the both-parties-signed agreement PDF first.');
    const stamp = stampingApplies(s0);
    if (stamp == null) return fail('Legal has not yet recorded whether this Addendum needs LHDN stamping. Send Legal a message.');
    const { s, err } = begin(id);
    if (err) return err;
    s.milestones.partnerSignedAt = now();
    const to = stamp ? 'awaiting_stamping' : 'final_verification';
    log(s, `Submitted both-parties-signed Agreement v${s.slots.agreement.versions.length} — ${statusChange(s, to)}`);
    return commit(s);
  };

  actions.submitCertificate = function (id) {
    const s0 = sub(id); const u = me();
    if (!s0 || s0.requesterId !== u.id) return fail('Only the submitting requester can do this.');
    if (s0.status !== 'awaiting_stamping') return fail('Not at the stamping stage.');
    if (!certThisStage(s0)) return fail('Upload the LHDN stamp certificate PDF first.');
    const { s, err } = begin(id);
    if (err) return err;
    s.milestones.stampedAt = now();
    log(s, `Submitted final documents (Agreement + LHDN Stamp Certificate) — ${statusChange(s, 'final_verification')}`);
    return commit(s);
  };

  function finalBlockers(s) {
    const b = [];
    if (s.status !== 'final_verification') b.push('Status must be Final Verification.');
    const a = currentVersion(s, 'agreement');
    if (!a || a.label !== 'Both Parties Signed' || a.ext !== 'pdf') b.push('A both-parties-signed Agreement PDF is required.');
    const st = stampingApplies(s);
    if (st === null) b.push('Stamping decision for this Addendum is not recorded.');
    if (st === true) { const c = currentVersion(s, 'stamp_certificate'); if (!c || c.ext !== 'pdf') b.push('An LHDN stamp certificate PDF is required.'); }
    return b;
  }

  actions.verifyFinal = function (id, checks, rev) {
    const s0 = sub(id);
    const b = s0 ? finalBlockers(s0) : ['Not available.'];
    if (b.length) return fail(b.join(' '));
    const needCert = stampingApplies(s0) === true;
    if (!checks.agreement || (needCert && !checks.certificate)) return fail('Confirm each verification check before marking Fully Executed.');
    const { s, u, err } = begin(id, rev, true);
    if (err) return err;
    const a = currentVersion(s, 'agreement'); a.finalExecuted = true;
    s.finalAccepted = { agreement: s.slots.agreement.versions.length };
    let what = `Agreement v${s.finalAccepted.agreement}`;
    if (needCert) { const c = currentVersion(s, 'stamp_certificate'); c.verified = true; s.finalAccepted.stamp_certificate = s.slots.stamp_certificate.versions.length; what += `, LHDN Stamp Certificate v${s.finalAccepted.stamp_certificate}`; }
    s.milestones.fullyExecutedAt = now(); s.milestones.fullyExecutedBy = u.id;
    log(s, `Verified final package (${what}) — ${statusChange(s, 'fully_executed')}`);
    return commit(s);
  };

  const CLOSABLE = ['pending_review', 'in_review', 'action_required', 'review_completed', 'awaiting_partner', 'awaiting_stamping', 'final_verification'];

  actions.closeNotProceeding = function (id, reason, rev) {
    if (!reason || reason.trim().length < 10) return fail('A closure reason is required.');
    const s0 = sub(id);
    if (!s0 || !CLOSABLE.includes(s0.status)) return fail('Only submissions that are not yet Fully Executed can be closed as Not Proceeding.');
    const { s, u, err } = begin(id, rev, true);
    if (err) return err;
    s.prevStatus = s.status;
    s.closure = { reason: reason.trim(), by: u.id, at: now() };
    log(s, `Closed as Not Proceeding — reason: ${reason.trim()}`);
    s.status = 'not_proceeding';
    return commit(s);
  };

  actions.reopen = function (id, reason, rev) {
    if (!reason || reason.trim().length < 10) return fail('A reopening reason is required.');
    const s0 = sub(id);
    if (!s0 || s0.status !== 'not_proceeding') return fail('Only Not Proceeding submissions can be reopened.');
    const { s, u, err } = begin(id, rev, true);
    if (err) return err;
    const to = s.prevStatus || 'in_review';
    s.reopenings.push({ reason: reason.trim(), by: u.id, at: now(), closure: s.closure, to });
    s.closure = null;
    log(s, `Reopened — reason: ${reason.trim()} — ${statusChange(s, to)}`);
    return commit(s);
  };

  actions.confirmLink = function (id, agrId, rev) {
    const s0 = sub(id);
    if (!s0 || s0.type !== 'ADDENDUM') return fail('Not an Addendum.');
    if (['registered', 'not_proceeding'].includes(s0.status)) return fail('The original-agreement link can no longer be changed.');
    if (!agr(agrId)) return fail('Choose an existing Register record.');
    const { s, u, err } = begin(id, rev, true);
    if (err) return err;
    const was = s.addendum.linkStatus;
    s.addendum.originalId = agrId; s.addendum.linkStatus = 'confirmed'; s.addendum.confirmedBy = u.id; s.addendum.confirmedAt = now();
    log(s, was === 'unresolved' ? `Resolved original agreement — linked to ${agrId}` : `Confirmed original agreement ${agrId}`);
    return commit(s);
  };

  actions.decideStamping = function (id, required, rev) {
    const s0 = sub(id);
    if (!s0 || s0.type !== 'ADDENDUM') return fail('Stamping is decided explicitly only for Addendums.');
    if (!['pending_review', 'in_review', 'action_required', 'review_completed', 'awaiting_partner'].includes(s0.status)) return fail('The stamping decision is locked once the signed Addendum has been submitted.');
    const { s, u, err } = begin(id, rev, true);
    if (err) return err;
    const prev = s.addendum.stampingRequired;
    s.addendum.stampingRequired = required; s.addendum.stampingBy = u.id; s.addendum.stampingAt = now();
    log(s, `${prev == null ? 'Recorded' : 'Changed'} stamping decision: LHDN stamping ${required ? 'required' : 'not required'} for this Addendum`);
    return commit(s);
  };

  function registerBlockers(s) {
    const b = [];
    if (s.status !== 'fully_executed') b.push('Only Fully Executed submissions can be registered.');
    if (s.agreementId) b.push(`Already registered as ${s.agreementId}.`);
    if (s.type === 'ADDENDUM' && s.addendum.linkStatus !== 'confirmed') b.push('Confirm the original agreement this Addendum modifies before registering.');
    return b;
  }

  actions.createRegister = function (id, f, rev) {
    const s0 = sub(id);
    const b = s0 ? registerBlockers(s0) : ['Not available.'];
    if (b.length) return fail(b.join(' '));
    const errs = registerFieldErrors(f);
    if (Object.keys(errs).length) return { ok: false, error: 'Complete the highlighted fields.', fields: errs };
    const { s, u, err } = begin(id, rev, true);
    if (err) return err;
    // Atomic: build the record and link first, then commit both together.
    const agrId = 'AGR-2026-' + String(S.nextAgr).padStart(3, '0');
    const rec = {
      id: agrId, type: f.type, moaSubtype: f.type === 'MOA' ? f.moaSubtype.trim() : '', title: f.title.trim(), partner: f.partner.trim(),
      location: f.location, category: f.category, campus: f.campus, dateSigned: f.dateSigned, expiry: f.indefinite ? null : f.expiry,
      pic: f.pic.trim(), status: 'signed', submissionId: s.id, originalId: s.type === 'ADDENDUM' ? s.addendum.originalId : null,
      notes: f.notes.trim(), createdBy: u.id, createdAt: now(),
    };
    S.nextAgr += 1;
    S.register.unshift(rec);
    s.agreementId = agrId;
    s.milestones.registeredAt = now();
    log(s, `Created Agreement Register record ${agrId} (Signed) and linked it to this submission${rec.originalId ? ` — Addendum linked to original ${rec.originalId}` : ''} — ${statusChange(s, 'registered')}`);
    const res = commit(s);
    res.agrId = agrId;
    return res;
  };

  function registerFieldErrors(f) {
    const e = {};
    if (!f.title || !f.title.trim()) e.title = 'Enter the agreement title.';
    if (!f.partner || !f.partner.trim()) e.partner = 'Enter the partner name.';
    if (!f.dateSigned) e.dateSigned = 'Enter the date the agreement was fully signed.';
    if (!f.indefinite && !f.expiry) e.expiry = 'Enter an expiry date, or tick "No fixed expiry".';
    if (!f.indefinite && f.expiry && f.dateSigned && f.expiry <= f.dateSigned) e.expiry = 'Expiry must be after the signing date.';
    if (!f.pic || !f.pic.trim()) e.pic = 'Enter the PIC\'s name.';
    return e;
  }

  // Demo only: another Legal user changes this submission in a different session.
  actions.simulateOtherLegal = function (id) {
    const s = sub(id); const u = me();
    if (!s || !isLegal(u)) return fail('Open a submission as a Legal user first.');
    const other = u.id === 'nadia' ? 'hana' : 'nadia';
    tick(2);
    let what;
    if (s.status === 'pending_review') {
      s.status = 'in_review';
      s.activity.push({ id: uid('a'), at: now(), by: other, text: 'Started Legal review — status changed to In Review', legalOnly: false });
      what = `${user(other).name} started the review`;
    } else {
      s.notes.push({ id: uid('n'), by: other, at: now(), text: 'Checked the partner details against the company profile — consistent. (Added from another session.)' });
      s.activity.push({ id: uid('a'), at: now(), by: other, text: 'Added an Internal Legal Note', legalOnly: true });
      what = `${user(other).name} added an Internal Legal Note`;
    }
    s.rev += 1; s.lastHandler = other;
    if (s.status === 'in_review' && what.includes('started')) s.updatedAt = now();
    save();
    return { ok: true, what };
  };

  window.AMS = Object.assign(A, {
    load, save, reset, state, now, tick, uid, fmt, fmtDate, rel, esc, size,
    user, me, isLegal, sub, agr, campusName, campusShort, slotLabel, signatory,
    requiredIntake, stampingApplies, currentVersion, versionNo, slotList,
    nextActor, nextActorLabel, canView, mySubmissions, unreadCount, totalUnread, accessibleOriginals,
    openRequest, recentAccess, uploadRule, validateFile, responseReadiness, reviewBlockers, finalBlockers,
    registerBlockers, registerFieldErrors, unikSignedVersion, signedThisStage, certThisStage, CLOSABLE, MAX_MB,
    actions,
  });
})();
