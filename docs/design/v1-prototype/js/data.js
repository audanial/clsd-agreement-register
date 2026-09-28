/* Fictional demonstration data and fixed product rules.
   Every person, partner, submission, file and message here is invented.
   UniKL campus names are the only real institutional context. */
(function () {
  'use strict';

  // Parse "YYYY-MM-DD HH:MM" as Malaysian time (UTC+8, no DST) and return epoch ms.
  function T(s) {
    const [d, t] = s.split(' ');
    const [y, m, day] = d.split('-').map(Number);
    const [hh, mm] = (t || '09:00').split(':').map(Number);
    return Date.UTC(y, m - 1, day, hh - 8, mm);
  }

  const USERS = {
    aisyah: { id: 'aisyah', name: 'Aisyah Kamal', role: 'requester', title: 'Senior Lecturer, UniKL MIIT', initials: 'AK' },
    nadia: { id: 'nadia', name: 'Nadia Idris', role: 'legal', title: 'Legal Executive, CLSD', initials: 'NI' },
    hana: { id: 'hana', name: 'Hana Yusof', role: 'legal', title: 'Legal Officer, CLSD', initials: 'HY' },
    // Other requesters appear only in the Legal queue; they are not demo perspectives.
    lim: { id: 'lim', name: 'Dr. Lim Wei Jie', role: 'requester', title: 'Head of Programme, UniKL BMI', initials: 'LW' },
    hafiz: { id: 'hafiz', name: 'Hafiz Osman', role: 'requester', title: 'Industry Liaison Officer, UniKL MIMET', initials: 'HO' },
    rosli: { id: 'rosli', name: 'Rosli Abdullah', role: 'requester', title: 'Programme Coordinator, UniKL MIAT', initials: 'RA' },
    farah: { id: 'farah', name: 'Dr. Farah Nabila', role: 'requester', title: 'Clinical Coordinator, UniKL RCMP', initials: 'FN' },
  };

  const PERSPECTIVES = ['aisyah', 'nadia', 'hana'];

  const CAMPUSES = [
    ['MIIT', 'UniKL MIIT — Malaysian Institute of Information Technology'],
    ['RCMP', 'UniKL RCMP — Royal College of Medicine Perak'],
    ['MIMET', 'UniKL MIMET — Malaysian Institute of Marine Engineering Technology'],
    ['MSI', 'UniKL MSI — Malaysian Spanish Institute'],
    ['MESTECH', 'UniKL MESTECH — Institute of Medical Science Technology'],
    ['MFI', 'UniKL MFI — Malaysia France Institute'],
    ['MIDI', 'UniKL MIDI — Malaysia Italy Design Institute'],
    ['MICET', 'UniKL MICET — Malaysian Institute of Chemical & Bio-Engineering Technology'],
    ['MITEC', 'UniKL MITEC — Malaysian Institute of Industrial Technology'],
    ['MIAT', 'UniKL MIAT — Malaysian Institute of Aviation Technology'],
    ['BMI', 'UniKL BMI — British Malaysian Institute'],
    ['BiS', 'UniKL Business School'],
    ['UIO', 'UniKL International Office'],
    ['ACE', 'Centre for Advancement & Continuing Education'],
    ['CPS', 'Centre for Postgraduate Studies'],
    ['MCI', 'UniKL Malaysia China Institute'],
    ['CIL', 'Centre for Industrial Linkages'],
    ['CoRI', 'Centre for Research and Innovation'],
  ];

  // Checklist slot definitions. Exactly the approved V1 intake documents plus the separate
  // execution-stage LHDN stamp certificate.
  const SLOT_DEFS = {
    agreement: { label: 'Agreement', help: 'The agreement under review. Later versions hold the UniKL-signed and both-parties-signed copies.' },
    memo: { label: 'Memo', help: 'Internal memo explaining the purpose and benefit of the engagement.' },
    requisition: { label: 'Requisition Form', help: 'Completed requisition form from your campus or department.' },
    due_diligence: { label: 'Due Diligence Form', help: 'Completed due diligence form for the industry partner.' },
    company_profile: { label: 'Company Profile', help: "The partner's current company profile." },
    ssm: { label: 'SSM / Malaysian corporate information', help: 'SSM company information or equivalent Malaysian corporate information — required for a Local partner.' },
    business_reg: { label: 'Business-registration document', help: "The partner's equivalent business-registration document — required for an International partner." },
    stamp_certificate: { label: 'LHDN Stamp Certificate', help: 'Standalone PDF certificate issued after LHDN stamping. A separate document with its own versions — never a replacement Agreement file.' },
  };

  const AGREEMENT_TYPES = ['NDA', 'MOA', 'MOU', 'ADDENDUM'];
  const TYPE_NAMES = {
    NDA: 'Non-Disclosure Agreement', MOA: 'Memorandum of Agreement', MOU: 'Memorandum of Understanding',
    ADDENDUM: 'Addendum', LOI: 'Letter of Intent (historical)', SEA: 'Student Exchange Agreement (historical)',
  };

  const STATUS = {
    pending_review: { label: 'Pending Legal Review', tone: 'blue', actor: 'legal' },
    in_review: { label: 'In Review', tone: 'blue', actor: 'legal' },
    action_required: { label: 'Action Required from Requester', tone: 'amber', actor: 'requester' },
    review_completed: { label: 'Review Completed — Awaiting UniKL Signature', tone: 'purple', actor: 'legal' },
    awaiting_partner: { label: 'Awaiting Partner Signature', tone: 'amber', actor: 'requester' },
    awaiting_stamping: { label: 'Awaiting LHDN Stamping', tone: 'amber', actor: 'requester' },
    final_verification: { label: 'Final Verification', tone: 'purple', actor: 'legal' },
    fully_executed: { label: 'Fully Executed', tone: 'green', actor: 'legal' },
    registered: { label: 'Registered', tone: 'navy', actor: 'none' },
    not_proceeding: { label: 'Not Proceeding', tone: 'grey', actor: 'closed' },
  };

  const REGISTER_STATUS = {
    signed: { label: 'Signed', tone: 'green' },
    awaiting_partner: { label: 'Awaiting partner (legacy)', tone: 'amber' },
    pending: { label: 'Pending (legacy)', tone: 'blue' },
    expired: { label: 'Expired', tone: 'grey' },
  };

  // ---------- builders ----------
  let seq = 0;
  const id = (p) => p + '-' + (++seq).toString(36);
  const ver = (filename, sizeMB, by, at, label) => ({
    id: id('v'), filename, sizeMB, ext: filename.split('.').pop().toLowerCase(), by, at, label,
  });
  const slot = (versions) => ({ versions: versions || [], reopened: false });
  const msg = (kind, by, at, text, readBy, extra) => Object.assign({ id: id('m'), kind, by, at, text, readBy: readBy.slice() }, extra || {});
  const note = (by, at, text) => ({ id: id('n'), by, at, text });
  const ev = (at, by, text, legalOnly) => ({ id: id('a'), at, by, text, legalOnly: !!legalOnly });

  function baseSub(o) {
    return Object.assign({
      moaSubtype: '', addendum: null, prevStatus: null, lastHandler: null, request: null,
      milestones: {}, closure: null, reopenings: [], agreementId: null, messages: [], notes: [],
      activity: [], access: [], rev: 1, finalAccepted: null,
    }, o);
  }

  const industrySlots = (loc, who, at, names) => {
    const corp = loc === 'International' ? 'business_reg' : 'ssm';
    const s = {
      agreement: slot([ver(names.agreement, names.agreementMB || 0.6, who, at, 'Submitted for Review')]),
      memo: slot([ver(names.memo, 0.2, who, at, 'Submitted for Review')]),
      requisition: slot([ver(names.requisition, 0.3, who, at, 'Submitted for Review')]),
      due_diligence: slot([ver(names.dd, 0.5, who, at, 'Submitted for Review')]),
      company_profile: slot([ver(names.profile, 2.4, who, at, 'Submitted for Review')]),
    };
    s[corp] = slot([ver(names.corp, 0.4, who, at, 'Submitted for Review')]);
    return s;
  }

  function buildSeed() {
    seq = 0;
    const subs = [];

    // 1 — Journey 1: Academic Local MOU, freshly submitted.
    {
      const at = T('2026-09-26 16:05');
      subs.push(baseSub({
        id: 'SUB-2026-0041', title: 'Joint Curriculum Development MOU', requesterId: 'aisyah', campus: 'MIIT',
        partner: 'Northbridge University College', category: 'Academic', location: 'Local', type: 'MOU',
        scope: 'Co-develop two diploma-level software engineering modules and exchange guest lecturers for the 2027 intake.',
        status: 'pending_review', createdAt: at, submittedAt: at, updatedAt: at,
        slots: {
          agreement: slot([ver('Northbridge_MOU_draft.docx', 0.4, 'aisyah', at, 'Submitted for Review')]),
          requisition: slot([ver('Requisition_Form_MIIT_Northbridge.pdf', 0.3, 'aisyah', at, 'Submitted for Review')]),
        },
        activity: [ev(at, 'aisyah', 'Submission created and officially submitted with 2 of 2 required documents')],
      }));
    }

    // 2 — Journey 2: Industry Local MOA at Action Required (clarification + targeted replacement).
    {
      const sub = T('2026-09-18 11:20');
      const start = T('2026-09-19 09:10');
      const req = T('2026-09-24 14:45');
      const s = baseSub({
        id: 'SUB-2026-0038', title: 'Robotics Training & Equipment Collaboration', requesterId: 'aisyah', campus: 'MIIT',
        partner: 'Seri Maju Robotics Sdn. Bhd.', category: 'Industry', location: 'Local', type: 'MOA', moaSubtype: 'Research Collaboration',
        scope: 'Industry-sponsored robotics lab equipment, joint final-year projects and a staff industrial attachment programme.',
        status: 'action_required', createdAt: sub, submittedAt: sub, updatedAt: T('2026-09-25 09:12'), lastHandler: 'nadia',
        slots: industrySlots('Local', 'aisyah', sub, {
          agreement: 'SeriMaju_Robotics_MOA_draft.docx', memo: 'Memo_Robotics_Collaboration.pdf', requisition: 'Requisition_Form_MIIT_SeriMaju.pdf',
          dd: 'Due_Diligence_SeriMaju.pdf', profile: 'SeriMaju_Company_Profile_2026.pdf', corp: 'SSM_Profile_SeriMaju.pdf',
        }),
      });
      s.slots.due_diligence.reopened = true;
      const m3 = msg('request', 'nadia', req,
        'Please confirm whether Seri Maju Robotics will provide the lab equipment on loan or as a donation — clause 5 of the draft says "loan" but the requisition form says "donation".\n\nThe Due Diligence Form is also missing the Dean\'s signature. Please upload the signed form.',
        ['nadia', 'hana'], { request: { clarify: true, slots: ['due_diligence'], phase: 'review', status: 'open' } });
      s.request = m3.id;
      s.messages = [
        msg('message', 'aisyah', T('2026-09-19 10:02'), 'Hi Legal team, the partner hopes to sign before the end of October if possible.', ['aisyah', 'nadia', 'hana']),
        msg('message', 'nadia', T('2026-09-19 11:30'), 'Thank you, noted. We have started the review and will update you here.', ['nadia', 'aisyah', 'hana']),
        m3,
        msg('message', 'aisyah', T('2026-09-25 09:12'), 'Noted — I am checking clause 5 with the partner and will get the Dean to sign the form this week.', ['aisyah']),
      ];
      s.notes = [
        note('nadia', T('2026-09-24 14:40'), 'Clause 5 (equipment) conflicts with the requisition form: requisition says donation, draft says loan. Holding review completion until clarified.'),
        note('hana', T('2026-09-25 10:05'), 'Agree with Nadia. If it is a loan we will need an insurance and return-condition clause. There is a usable precedent in the Andalas Precision MOA (AGR-2025-011).'),
      ];
      s.activity = [
        ev(sub, 'aisyah', 'Submission created and officially submitted with 6 of 6 required documents'),
        ev(start, 'nadia', 'Started Legal review — status changed to In Review'),
        ev(T('2026-09-22 15:02'), 'hana', 'Downloaded Agreement v1 (SeriMaju_Robotics_MOA_draft.docx)'),
        ev(T('2026-09-24 14:40'), 'nadia', 'Added an Internal Legal Note', true),
        ev(req, 'nadia', 'Requested clarification and replacement of Due Diligence Form — status changed to Action Required from Requester'),
        ev(T('2026-09-25 10:05'), 'hana', 'Added an Internal Legal Note', true),
      ];
      subs.push(s);
    }

    // 3 — Journey 3: submitted as Academic International MOU; Legal will correct to Industry.
    {
      const at = T('2026-09-25 15:20');
      subs.push(baseSub({
        id: 'SUB-2026-0044', title: 'Data Analytics Internship Programme', requesterId: 'aisyah', campus: 'MIIT',
        partner: 'Kestrel Analytics GmbH', category: 'Academic', location: 'International', type: 'MOU',
        scope: 'Paid internship placements for final-year data analytics students at the partner\'s Munich office, with joint supervision.',
        status: 'pending_review', createdAt: at, submittedAt: at, updatedAt: at,
        slots: {
          agreement: slot([ver('Kestrel_MOU_draft_v1.docx', 0.5, 'aisyah', at, 'Submitted for Review')]),
          requisition: slot([ver('Requisition_Form_MIIT_Kestrel.pdf', 0.3, 'aisyah', at, 'Submitted for Review')]),
        },
        activity: [ev(at, 'aisyah', 'Submission created and officially submitted with 2 of 2 required documents')],
      }));
    }

    // 4 — Journey 4: Addendum with an authorised original selected by the requester.
    {
      const at = T('2026-09-26 11:40');
      subs.push(baseSub({
        id: 'SUB-2026-0046', title: 'Addendum 1 — Second Mobility Cohort', requesterId: 'aisyah', campus: 'MIIT',
        partner: 'Universitas Harapan Timur', category: 'Academic', location: 'International', type: 'ADDENDUM',
        scope: 'Adds a second student cohort for Semester 2, 2027 and extends the exchange window by 12 months.',
        addendum: {
          mode: 'selected', originalId: 'AGR-2026-014', notFound: null,
          purpose: 'Extension and scope change: adds a second student cohort (Semester 2, 2027) and extends the exchange window by 12 months. All other terms unchanged.',
          linkStatus: 'requester_selected', confirmedBy: null, confirmedAt: null,
          stampingRequired: null, stampingBy: null, stampingAt: null,
        },
        status: 'pending_review', createdAt: at, submittedAt: at, updatedAt: at,
        slots: {
          agreement: slot([ver('UHT_Addendum_1_draft.docx', 0.3, 'aisyah', at, 'Submitted for Review')]),
          requisition: slot([ver('Requisition_Form_MIIT_UHT_Addendum.pdf', 0.3, 'aisyah', at, 'Submitted for Review')]),
        },
        activity: [ev(at, 'aisyah', 'Submission created and officially submitted with 2 of 2 required documents — original agreement AGR-2026-014 selected by requester')],
      }));
    }

    // 5 — View 5: MOA awaiting partner signature (requester downloads UniKL-signed, uploads final PDFs).
    {
      const at = T('2026-09-02 10:30');
      const s = baseSub({
        id: 'SUB-2026-0036', title: 'Erasmus+ Staff and Student Mobility', requesterId: 'aisyah', campus: 'MIIT',
        partner: 'Hochschule Lindenfeld', category: 'Academic', location: 'International', type: 'MOA', moaSubtype: 'Erasmus+',
        scope: 'Erasmus+ credit mobility for up to six students and two staff per year between MIIT and the partner\'s Faculty of Computing.',
        status: 'awaiting_partner', createdAt: at, submittedAt: at, updatedAt: T('2026-09-22 16:15'), lastHandler: 'nadia',
        milestones: { reviewCompletedAt: T('2026-09-15 11:00'), reviewCompletedBy: 'hana', unikSignedAt: T('2026-09-22 16:10'), sentForPartnerAt: T('2026-09-22 16:15') },
        slots: {
          agreement: slot([
            ver('Lindenfeld_Erasmus_MOA_draft.docx', 0.7, 'aisyah', at, 'Submitted for Review'),
            ver('Lindenfeld_Erasmus_MOA_rev1.docx', 0.7, 'aisyah', T('2026-09-10 09:45'), 'Revised During Legal Review'),
            ver('Lindenfeld_Erasmus_MOA_UniKL_signed.pdf', 1.9, 'nadia', T('2026-09-22 16:10'), 'UniKL Signed'),
          ]),
          requisition: slot([ver('Requisition_Form_MIIT_Lindenfeld.pdf', 0.3, 'aisyah', at, 'Submitted for Review')]),
        },
      });
      const rq = msg('request', 'hana', T('2026-09-08 15:20'),
        'Clause 9 (data protection) needs to reference the Personal Data Protection Act 2010. Please ask the partner to accept the attached wording from our template and upload the revised agreement.',
        ['hana', 'nadia', 'aisyah'], { request: { clarify: false, slots: ['agreement'], phase: 'review', status: 'answered', answeredAt: T('2026-09-10 09:50') } });
      s.messages = [
        rq,
        msg('response', 'aisyah', T('2026-09-10 09:50'), 'The partner accepted the PDPA wording. Revised agreement uploaded.', ['aisyah', 'hana', 'nadia'], { response: { requestId: rq.id, replaced: ['agreement'] } }),
        msg('message', 'nadia', T('2026-09-22 16:15'), 'The Vice Chancellor has signed. Please download version 3 (UniKL Signed), obtain the partner\'s signature on this same version, and upload the fully signed PDF. As this is an MOA, LHDN stamping follows.', ['nadia', 'hana']),
      ];
      s.activity = [
        ev(at, 'aisyah', 'Submission created and officially submitted with 2 of 2 required documents'),
        ev(T('2026-09-03 09:00'), 'hana', 'Started Legal review — status changed to In Review'),
        ev(T('2026-09-08 15:20'), 'hana', 'Requested replacement of Agreement — status changed to Action Required from Requester'),
        ev(T('2026-09-10 09:45'), 'aisyah', 'Uploaded Agreement v2 (Revised During Legal Review)'),
        ev(T('2026-09-10 09:50'), 'aisyah', 'Submitted response — status changed to In Review'),
        ev(T('2026-09-15 11:00'), 'hana', 'Completed Legal review — status changed to Review Completed — Awaiting UniKL Signature (Vice Chancellor)'),
        ev(T('2026-09-22 16:10'), 'nadia', 'Uploaded Agreement v3 (UniKL Signed)'),
        ev(T('2026-09-22 16:15'), 'nadia', 'Sent for partner signature — status changed to Awaiting Partner Signature'),
      ];
      s.notes = [note('hana', T('2026-09-15 10:52'), 'PDPA wording accepted verbatim. Nothing else outstanding; routing to VC office for signature.')];
      subs.push(s);
    }

    // 6 — View 6 / 11: Fully Executed NDA awaiting guided Register creation.
    {
      const at = T('2026-09-04 14:00');
      subs.push(baseSub({
        id: 'SUB-2026-0031', title: 'Guest Lecturer Confidentiality Arrangement', requesterId: 'aisyah', campus: 'MIIT',
        partner: 'Institut Teknologi Sinar Utara', category: 'Academic', location: 'Local', type: 'NDA',
        scope: 'Mutual confidentiality for unpublished course material shared by visiting guest lecturers during 2026–2027.',
        status: 'fully_executed', createdAt: at, submittedAt: at, updatedAt: T('2026-09-23 10:20'), lastHandler: 'nadia',
        milestones: {
          reviewCompletedAt: T('2026-09-09 16:00'), reviewCompletedBy: 'nadia', unikSignedAt: T('2026-09-15 12:00'),
          sentForPartnerAt: T('2026-09-15 12:05'), partnerSignedAt: T('2026-09-21 17:30'), fullyExecutedAt: T('2026-09-23 10:20'), fullyExecutedBy: 'nadia',
        },
        slots: {
          agreement: slot([
            ver('Sinar_Utara_NDA_draft.docx', 0.2, 'aisyah', at, 'Submitted for Review'),
            ver('Sinar_Utara_NDA_UniKL_signed.pdf', 0.9, 'hana', T('2026-09-15 12:00'), 'UniKL Signed'),
            ver('Sinar_Utara_NDA_both_signed.pdf', 1.1, 'aisyah', T('2026-09-21 17:30'), 'Both Parties Signed'),
          ]),
          requisition: slot([ver('Requisition_Form_MIIT_SinarUtara.pdf', 0.3, 'aisyah', at, 'Submitted for Review')]),
        },
        messages: [
          msg('message', 'nadia', T('2026-09-23 10:22'), 'Final package verified — this NDA is now Fully Executed. We will create the Agreement Register record shortly.', ['nadia', 'hana', 'aisyah']),
        ],
        activity: [
          ev(at, 'aisyah', 'Submission created and officially submitted with 2 of 2 required documents'),
          ev(T('2026-09-05 09:30'), 'nadia', 'Started Legal review — status changed to In Review'),
          ev(T('2026-09-09 16:00'), 'nadia', 'Completed Legal review — status changed to Review Completed — Awaiting UniKL Signature (Vice Chancellor)'),
          ev(T('2026-09-15 12:00'), 'hana', 'Uploaded Agreement v2 (UniKL Signed)'),
          ev(T('2026-09-15 12:05'), 'hana', 'Sent for partner signature — status changed to Awaiting Partner Signature'),
          ev(T('2026-09-21 17:30'), 'aisyah', 'Uploaded Agreement v3 (Both Parties Signed)'),
          ev(T('2026-09-21 17:32'), 'aisyah', 'Submitted final documents — status changed to Final Verification'),
          ev(T('2026-09-23 10:20'), 'nadia', 'Verified final package (Agreement v3) — status changed to Fully Executed'),
        ],
      }));
      subs[subs.length - 1].finalAccepted = { agreement: 3 };
    }

    // 7 — Registered MOA; becomes an authorised Addendum original for the requester.
    {
      const at = T('2026-05-11 10:00');
      const s = baseSub({
        id: 'SUB-2026-0029', title: 'Student Exchange Agreement', requesterId: 'aisyah', campus: 'MIIT',
        partner: 'Universitas Harapan Timur', category: 'Academic', location: 'International', type: 'MOA', moaSubtype: 'Student Exchange Agreement',
        scope: 'Two-way semester exchange for up to ten undergraduate students per year in computing programmes.',
        status: 'registered', createdAt: at, submittedAt: at, updatedAt: T('2026-07-21 11:05'), lastHandler: 'hana', agreementId: 'AGR-2026-014',
        milestones: {
          reviewCompletedAt: T('2026-05-28 15:00'), reviewCompletedBy: 'hana', unikSignedAt: T('2026-06-10 10:00'), sentForPartnerAt: T('2026-06-10 10:05'),
          partnerSignedAt: T('2026-07-02 09:15'), stampedAt: T('2026-07-14 14:40'), fullyExecutedAt: T('2026-07-20 16:00'), fullyExecutedBy: 'hana', registeredAt: T('2026-07-21 11:05'),
        },
        slots: {
          agreement: slot([
            ver('UHT_Student_Exchange_MOA_draft.docx', 0.6, 'aisyah', at, 'Submitted for Review'),
            ver('UHT_Student_Exchange_MOA_UniKL_signed.pdf', 1.7, 'hana', T('2026-06-10 10:00'), 'UniKL Signed'),
            ver('UHT_Student_Exchange_MOA_both_signed.pdf', 1.9, 'aisyah', T('2026-07-02 09:15'), 'Both Parties Signed'),
          ]),
          requisition: slot([ver('Requisition_Form_MIIT_UHT.pdf', 0.3, 'aisyah', at, 'Submitted for Review')]),
          stamp_certificate: slot([ver('LHDN_Stamp_Certificate_UHT_MOA.pdf', 0.2, 'aisyah', T('2026-07-14 14:40'), 'LHDN Stamp Certificate')]),
        },
        messages: [
          msg('message', 'hana', T('2026-07-21 11:06'), 'Registered as AGR-2026-014. You can see the Register summary on this page. Thank you!', ['hana', 'nadia', 'aisyah']),
        ],
        activity: [
          ev(at, 'aisyah', 'Submission created and officially submitted with 2 of 2 required documents'),
          ev(T('2026-05-28 15:00'), 'hana', 'Completed Legal review — status changed to Review Completed — Awaiting UniKL Signature (Vice Chancellor)'),
          ev(T('2026-06-10 10:05'), 'hana', 'Sent for partner signature — status changed to Awaiting Partner Signature'),
          ev(T('2026-07-02 09:20'), 'aisyah', 'Submitted both-parties-signed Agreement — status changed to Awaiting LHDN Stamping'),
          ev(T('2026-07-14 14:45'), 'aisyah', 'Submitted final documents — status changed to Final Verification'),
          ev(T('2026-07-20 16:00'), 'hana', 'Verified final package (Agreement v3, LHDN Stamp Certificate v1) — status changed to Fully Executed'),
          ev(T('2026-07-21 11:05'), 'hana', 'Created Agreement Register record AGR-2026-014 and linked it to this submission — status changed to Registered'),
        ],
        finalAccepted: { agreement: 3, stamp_certificate: 1 },
      });
      subs.push(s);
    }

    // 8 — Registered MOU; second authorised original for the requester's Addendum picker.
    {
      const at = T('2026-04-20 09:30');
      subs.push(baseSub({
        id: 'SUB-2026-0027', title: 'Industrial Design Studio Exchange', requesterId: 'aisyah', campus: 'MIIT',
        partner: 'Kolej Seni Reka Puncak', category: 'Academic', location: 'Local', type: 'MOU',
        scope: 'Shared design-studio sessions and joint student showcases between MIIT multimedia and the partner college.',
        status: 'registered', createdAt: at, submittedAt: at, updatedAt: T('2026-06-15 10:00'), lastHandler: 'nadia', agreementId: 'AGR-2026-009',
        milestones: {
          reviewCompletedAt: T('2026-05-06 12:00'), reviewCompletedBy: 'nadia', unikSignedAt: T('2026-05-20 10:00'), sentForPartnerAt: T('2026-05-20 10:05'),
          partnerSignedAt: T('2026-06-12 15:00'), fullyExecutedAt: T('2026-06-14 09:00'), fullyExecutedBy: 'nadia', registeredAt: T('2026-06-15 10:00'),
        },
        slots: {
          agreement: slot([
            ver('KSRP_Design_MOU_draft.docx', 0.3, 'aisyah', at, 'Submitted for Review'),
            ver('KSRP_Design_MOU_UniKL_signed.pdf', 1.2, 'nadia', T('2026-05-20 10:00'), 'UniKL Signed'),
            ver('KSRP_Design_MOU_both_signed.pdf', 1.3, 'aisyah', T('2026-06-12 15:00'), 'Both Parties Signed'),
          ]),
          requisition: slot([ver('Requisition_Form_MIIT_KSRP.pdf', 0.3, 'aisyah', at, 'Submitted for Review')]),
        },
        activity: [
          ev(at, 'aisyah', 'Submission created and officially submitted with 2 of 2 required documents'),
          ev(T('2026-06-14 09:00'), 'nadia', 'Verified final package (Agreement v3) — status changed to Fully Executed'),
          ev(T('2026-06-15 10:00'), 'nadia', 'Created Agreement Register record AGR-2026-009 and linked it to this submission — status changed to Registered'),
        ],
        finalAccepted: { agreement: 3 },
      }));
    }

    // 9 — Journey 5: Not Proceeding, closed with a reason; history retained.
    {
      const at = T('2026-09-10 13:15');
      subs.push(baseSub({
        id: 'SUB-2026-0035', title: 'Visiting Researcher Confidentiality Agreement', requesterId: 'aisyah', campus: 'MIIT',
        partner: 'Pacific Rim Design Academy', category: 'Academic', location: 'International', type: 'NDA',
        scope: 'Confidentiality for a three-month visiting researcher placement on a joint UX research project.',
        status: 'not_proceeding', prevStatus: 'in_review', createdAt: at, submittedAt: at, updatedAt: T('2026-09-20 10:40'), lastHandler: 'hana',
        closure: { reason: 'Partner declined to proceed: the partner cancelled the visiting researcher placement (requester\'s message of 19 Sep 2026). No document was signed.', by: 'hana', at: T('2026-09-20 10:40') },
        slots: {
          agreement: slot([ver('PRDA_Visiting_Researcher_NDA.docx', 0.2, 'aisyah', at, 'Submitted for Review')]),
          requisition: slot([ver('Requisition_Form_MIIT_PRDA.pdf', 0.3, 'aisyah', at, 'Submitted for Review')]),
        },
        messages: [
          msg('message', 'aisyah', T('2026-09-19 16:20'), 'The partner has told us they are cancelling the placement. Please close this submission — sorry for the trouble.', ['aisyah', 'hana', 'nadia']),
          msg('message', 'hana', T('2026-09-20 10:42'), 'No problem. I have closed it as Not Proceeding. You can still view the record and its documents here, and we can reopen it if the placement is revived.', ['hana', 'nadia', 'aisyah']),
        ],
        notes: [note('hana', T('2026-09-20 10:38'), 'Confirmed with the partner\'s coordinator by phone. Nothing signed on either side. Close, do not register.')],
        activity: [
          ev(at, 'aisyah', 'Submission created and officially submitted with 2 of 2 required documents'),
          ev(T('2026-09-11 09:00'), 'hana', 'Started Legal review — status changed to In Review'),
          ev(T('2026-09-20 10:38'), 'hana', 'Added an Internal Legal Note', true),
          ev(T('2026-09-20 10:40'), 'hana', 'Closed as Not Proceeding — reason recorded'),
        ],
      }));
    }

    // 10 — Other requester, In Review with a recent document access by Hana (recent-access warning).
    {
      const at = T('2026-09-24 10:10');
      const s = baseSub({
        id: 'SUB-2026-0045', title: 'Predictive Maintenance Data Sharing NDA', requesterId: 'lim', campus: 'BMI',
        partner: 'Voltaris Grid Solutions Sdn. Bhd.', category: 'Industry', location: 'Local', type: 'NDA',
        scope: 'Mutual NDA covering transformer sensor data shared for a final-year predictive maintenance project.',
        status: 'in_review', createdAt: at, submittedAt: at, updatedAt: T('2026-09-27 09:32'), lastHandler: 'hana',
        slots: industrySlots('Local', 'lim', at, {
          agreement: 'Voltaris_Mutual_NDA.docx', agreementMB: 0.3, memo: 'Memo_Voltaris_Data_Project.pdf', requisition: 'Requisition_Form_BMI_Voltaris.pdf',
          dd: 'Due_Diligence_Voltaris.pdf', profile: 'Voltaris_Company_Profile.pdf', corp: 'SSM_Voltaris_2026.pdf',
        }),
        access: [{ by: 'hana', slot: 'agreement', n: 1, at: T('2026-09-27 09:32') }],
      });
      s.messages = [
        msg('message', 'lim', T('2026-09-26 17:05'), 'Dear Legal, could the NDA term be kept short? The project ends in June 2027.', ['lim', 'hana']),
      ];
      s.notes = [note('hana', T('2026-09-26 16:35'), 'Template mutual NDA, but the confidentiality term is 10 years — propose 5 years to match our standard.')];
      s.activity = [
        ev(at, 'lim', 'Submission created and officially submitted with 6 of 6 required documents'),
        ev(T('2026-09-26 16:30'), 'hana', 'Started Legal review — status changed to In Review'),
        ev(T('2026-09-26 16:35'), 'hana', 'Added an Internal Legal Note', true),
        ev(T('2026-09-27 09:32'), 'hana', 'Downloaded Agreement v1 (Voltaris_Mutual_NDA.docx)'),
      ];
      subs.push(s);
    }

    // 11 — Other requester, Review Completed — awaiting UniKL (CEO) signature.
    {
      const at = T('2026-09-15 09:40');
      subs.push(baseSub({
        id: 'SUB-2026-0043', title: 'Aircraft Maintenance Apprenticeship MOU', requesterId: 'rosli', campus: 'MIAT',
        partner: 'Langit Biru Aero Services Sdn. Bhd.', category: 'Industry', location: 'Local', type: 'MOU',
        scope: 'Structured apprenticeship placements in line maintenance for MIAT diploma students.',
        status: 'review_completed', createdAt: at, submittedAt: at, updatedAt: T('2026-09-25 17:10'), lastHandler: 'nadia',
        milestones: { reviewCompletedAt: T('2026-09-25 17:10'), reviewCompletedBy: 'nadia' },
        slots: industrySlots('Local', 'rosli', at, {
          agreement: 'LangitBiru_Apprenticeship_MOU.docx', memo: 'Memo_MIAT_LangitBiru.pdf', requisition: 'Requisition_Form_MIAT_LangitBiru.pdf',
          dd: 'Due_Diligence_LangitBiru.pdf', profile: 'LangitBiru_Profile.pdf', corp: 'SSM_LangitBiru.pdf',
        }),
        messages: [
          msg('message', 'nadia', T('2026-09-25 17:12'), 'Review completed. We are arranging the CEO\'s signature and will upload the UniKL-signed copy here.', ['nadia', 'hana', 'rosli']),
        ],
        activity: [
          ev(at, 'rosli', 'Submission created and officially submitted with 6 of 6 required documents'),
          ev(T('2026-09-16 10:00'), 'nadia', 'Started Legal review — status changed to In Review'),
          ev(T('2026-09-25 17:10'), 'nadia', 'Completed Legal review — status changed to Review Completed — Awaiting UniKL Signature (CEO)'),
        ],
      }));
    }

    // 12 — Other requester, Industry International MOA at Final Verification with certificate.
    {
      const at = T('2026-08-20 11:00');
      const s = baseSub({
        id: 'SUB-2026-0040', title: 'Marine Engine Diagnostics Collaboration', requesterId: 'hafiz', campus: 'MIMET',
        partner: 'Nordhavn Marine Systems AS', category: 'Industry', location: 'International', type: 'MOA', moaSubtype: 'Research Collaboration',
        scope: 'Joint research on remote engine diagnostics with shared test-bed access at MIMET Lumut.',
        status: 'final_verification', createdAt: at, submittedAt: at, updatedAt: T('2026-09-26 15:30'), lastHandler: 'nadia',
        milestones: {
          reviewCompletedAt: T('2026-09-01 10:00'), reviewCompletedBy: 'nadia', unikSignedAt: T('2026-09-09 11:00'), sentForPartnerAt: T('2026-09-09 11:05'),
          partnerSignedAt: T('2026-09-18 14:00'), stampedAt: T('2026-09-26 15:25'),
        },
        slots: industrySlots('International', 'hafiz', at, {
          agreement: 'Nordhavn_Diagnostics_MOA_draft.docx', memo: 'Memo_MIMET_Nordhavn.pdf', requisition: 'Requisition_Form_MIMET_Nordhavn.pdf',
          dd: 'Due_Diligence_Nordhavn.pdf', profile: 'Nordhavn_Company_Profile.pdf', corp: 'Nordhavn_Business_Registration_Certificate.pdf',
        }),
      });
      s.slots.agreement.versions.push(
        ver('Nordhavn_Diagnostics_MOA_UniKL_signed.pdf', 2.1, 'nadia', T('2026-09-09 11:00'), 'UniKL Signed'),
        ver('Nordhavn_Diagnostics_MOA_both_signed.pdf', 2.3, 'hafiz', T('2026-09-18 14:00'), 'Both Parties Signed'));
      s.slots.stamp_certificate = slot([ver('LHDN_Stamp_Certificate_Nordhavn.pdf', 0.2, 'hafiz', T('2026-09-26 15:25'), 'LHDN Stamp Certificate')]);
      s.messages = [
        msg('message', 'hafiz', T('2026-09-26 15:30'), 'Stamping done — certificate uploaded. Please verify when you can.', ['hafiz']),
      ];
      s.activity = [
        ev(at, 'hafiz', 'Submission created and officially submitted with 6 of 6 required documents'),
        ev(T('2026-09-01 10:00'), 'nadia', 'Completed Legal review — status changed to Review Completed — Awaiting UniKL Signature (CEO)'),
        ev(T('2026-09-09 11:05'), 'nadia', 'Sent for partner signature — status changed to Awaiting Partner Signature'),
        ev(T('2026-09-18 14:05'), 'hafiz', 'Submitted both-parties-signed Agreement — status changed to Awaiting LHDN Stamping'),
        ev(T('2026-09-26 15:25'), 'hafiz', 'Uploaded LHDN Stamp Certificate v1'),
        ev(T('2026-09-26 15:26'), 'hafiz', 'Submitted final documents — status changed to Final Verification'),
      ];
      subs.push(s);
    }

    // 13 — Addendum where the requester could not find the original (Legal must resolve).
    {
      const at = T('2026-09-22 14:30');
      const s = baseSub({
        id: 'SUB-2026-0039', title: 'Addendum — Engine-Room Simulator Training', requesterId: 'hafiz', campus: 'MIMET',
        partner: 'Teras Marine Services Sdn. Bhd.', category: 'Industry', location: 'Local', type: 'ADDENDUM',
        scope: 'Adds engine-room simulator training modules to the existing crew-training collaboration.',
        addendum: {
          mode: 'not_found', originalId: null,
          notFound: {
            title: 'MOU on vessel crew training', partner: 'Teras Marine Services Sdn. Bhd.', approxDate: 'Around March 2025',
            campus: 'UniKL MIMET', details: 'Signed by the previous Dean of MIMET. I only have a scanned copy of the signature page.',
          },
          purpose: 'Scope change: adds engine-room simulator training to the original crew-training programme. Duration and fees unchanged.',
          linkStatus: 'unresolved', confirmedBy: null, confirmedAt: null, stampingRequired: null, stampingBy: null, stampingAt: null,
        },
        status: 'in_review', createdAt: at, submittedAt: at, updatedAt: T('2026-09-24 09:15'), lastHandler: 'nadia',
        slots: industrySlots('Local', 'hafiz', at, {
          agreement: 'Teras_Addendum_Simulator_Training.docx', agreementMB: 0.2, memo: 'Memo_MIMET_Teras_Addendum.pdf', requisition: 'Requisition_Form_MIMET_Teras.pdf',
          dd: 'Due_Diligence_Teras_2026.pdf', profile: 'Teras_Company_Profile.pdf', corp: 'SSM_Teras_Marine.pdf',
        }),
      });
      s.activity = [
        ev(at, 'hafiz', 'Submission created and officially submitted with 6 of 6 required documents — original agreement marked "Agreement not found"'),
        ev(T('2026-09-24 09:15'), 'nadia', 'Started Legal review — status changed to In Review'),
      ];
      subs.push(s);
    }

    // 14 — Other requester, fresh Academic Local MOU.
    {
      const at = T('2026-09-27 08:55');
      subs.push(baseSub({
        id: 'SUB-2026-0047', title: 'Clinical Placement MOU', requesterId: 'farah', campus: 'RCMP',
        partner: 'Hospital Pakar Seri Cahaya', category: 'Academic', location: 'Local', type: 'MOU',
        scope: 'Clinical placements for final-year medical imaging students.',
        status: 'pending_review', createdAt: at, submittedAt: at, updatedAt: at,
        slots: {
          agreement: slot([ver('SeriCahaya_Clinical_Placement_MOU.docx', 0.4, 'farah', at, 'Submitted for Review')]),
          requisition: slot([ver('Requisition_Form_RCMP_SeriCahaya.pdf', 0.3, 'farah', at, 'Submitted for Review')]),
        },
        activity: [ev(at, 'farah', 'Submission created and officially submitted with 2 of 2 required documents')],
      }));
    }

    // Existing Agreement Register (historical + portal-created). Historical LOI and legacy statuses stay unchanged.
    const register = [
      { id: 'AGR-2026-014', type: 'MOA', moaSubtype: 'Student Exchange Agreement', title: 'Student Exchange Agreement', partner: 'Universitas Harapan Timur', location: 'International', category: 'Academic', campus: 'MIIT', dateSigned: '2026-07-02', expiry: '2029-07-01', pic: 'Aisyah Kamal', status: 'signed', submissionId: 'SUB-2026-0029', originalId: null, createdBy: 'hana', createdAt: T('2026-07-21 11:05') },
      { id: 'AGR-2026-009', type: 'MOU', moaSubtype: '', title: 'Industrial Design Studio Exchange', partner: 'Kolej Seni Reka Puncak', location: 'Local', category: 'Academic', campus: 'MIIT', dateSigned: '2026-06-12', expiry: '2029-06-11', pic: 'Aisyah Kamal', status: 'signed', submissionId: 'SUB-2026-0027', originalId: null, createdBy: 'nadia', createdAt: T('2026-06-15 10:00') },
      { id: 'AGR-2025-019', type: 'MOU', moaSubtype: '', title: 'Cadet Sponsorship Programme', partner: 'Teras Marine Services Sdn. Bhd.', location: 'Local', category: 'Industry', campus: 'MIMET', dateSigned: null, expiry: null, pic: 'Kamarul Hisham', status: 'pending', submissionId: null, originalId: null },
      { id: 'AGR-2025-011', type: 'MOA', moaSubtype: 'Research Collaboration', title: 'Precision Machining Research Collaboration', partner: 'Andalas Precision Engineering Sdn. Bhd.', location: 'Local', category: 'Industry', campus: 'MITEC', dateSigned: '2025-08-05', expiry: '2028-08-04', pic: 'Dr. Zulkifli Hamid', status: 'signed', submissionId: null, originalId: null },
      { id: 'AGR-2025-006', type: 'MOU', moaSubtype: '', title: 'MOU on Vessel Crew Training', partner: 'Teras Marine Services Sdn. Bhd.', location: 'Local', category: 'Industry', campus: 'MIMET', dateSigned: '2025-03-18', expiry: '2028-03-17', pic: 'Kamarul Hisham', status: 'signed', submissionId: null, originalId: null },
      { id: 'AGR-2024-022', type: 'NDA', moaSubtype: '', title: 'Battery Test Data NDA', partner: 'Voltaris Grid Solutions Sdn. Bhd.', location: 'Local', category: 'Industry', campus: 'BMI', dateSigned: null, expiry: null, pic: 'Dr. Lim Wei Jie', status: 'awaiting_partner', submissionId: null, originalId: null },
      { id: 'AGR-2023-004', type: 'LOI', moaSubtype: '', title: 'Letter of Intent — Pilot Training Pathway', partner: 'Aurora Aviation Academy', location: 'International', category: 'Academic', campus: 'MIAT', dateSigned: '2023-02-09', expiry: '2024-02-08', pic: 'Capt. Faizal Rahim', status: 'expired', submissionId: null, originalId: null },
      { id: 'AGR-2022-031', type: 'MOA', moaSubtype: '', title: 'Chemical Process Operator Training', partner: 'Kimia Hijau Industries Sdn. Bhd.', location: 'Local', category: 'Industry', campus: 'MICET', dateSigned: '2022-10-03', expiry: '2025-10-02', pic: 'Pn. Rohana Said', status: 'expired', submissionId: null, originalId: null },
    ];

    return {
      version: 3,
      clock: T('2026-09-27 09:40'),
      currentUser: 'aisyah',
      nextSub: 48,
      nextAgr: 15,
      submissions: subs,
      register,
      seqBase: seq,
    };
  }

  window.AMS = window.AMS || {};
  Object.assign(window.AMS, { T, USERS, PERSPECTIVES, CAMPUSES, SLOT_DEFS, AGREEMENT_TYPES, TYPE_NAMES, STATUS, REGISTER_STATUS, buildSeed });
})();
