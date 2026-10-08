# LP2 — Documents and complete intake

> **Date:** 5 October 2026
> **Status:** Approved by Amir on 5 October 2026, including the consolidated written architecture. Design sections 1–2 were approved on 2 October; sections 3–5 on 5 October. The corresponding implementation plan provides execution guidance; architecture approval does not by itself approve implementation or production actions.
> **Sources:** `docs/design/unikl-agreement-management-v1-spec.md`; Amir's confirmed feedback and design approvals on 2 and 5 October 2026.
> **Precedence:** Human decisions override conflicting preserved prototype wording. The V1 specification and AGENTS.md were reconciled with confirmed 2/5 October amendments on 5 October; historical milestone handoffs and prototype assets remain preserved.
> **Scope:** One-session classified intake, temporary uploads, dynamic checklists, private permanent files, immutable versions, authorised downloads, and representative-file UAT.
> **Execution boundary:** This document records architecture approval, not implementation or release evidence. Implementation, working-database migrations and production actions require their separate instructions.

## S1. Design approval record

This architecture implements the approved one-session, no-saved-draft design and Legal's post-demo amendments. The following record distinguishes design approval from implementation, UAT and deployment evidence.

**Approved design section 1:** one-session intake with no saved drafts; classifications and conditional MOA/Addendum fields; complete mandatory uploads before submission; permanent submission, slots, Version 1 files and audit created together; post-submission locking.

**Approved design section 2:** separate document slots and immutable versions; nullable historical classification fields; private development and production storage with distinct identities; authenticated, authorised, audited application downloads; database rollback and cleanup of new files on failed creation.

**Approved design section 3 (5 October 2026):** temporary uploads, interface/readiness states, classification-change handling, validation, cleanup, failure recovery and the locked submitted workspace. Approval covers the behaviour presented in chat; exact transport, retry/idempotency and recovery mechanisms must be specified in the implementation plan and verified.

**Approved design section 4 (5 October 2026):** automated verification of intake, real-file validation, atomic submission/failure recovery, retries and duplicate submission, privacy, post-submission locking and historical compatibility; representative-file UAT with Intan/Siti using fictional or sanitised documents. Release readiness requires passing automated checks and no unresolved UAT issue blocking submission, downloading or confidentiality. This approval is for the verification design; it does not claim tests or UAT have been performed.

**Approved design section 5 (5 October 2026):** keep private local development storage; prepare and verify private persistent production Object Storage, distinguish storage identities, verify upload/cleanup and protected downloads, review costs/configuration before provisioning, and release with backup, verification and handoff evidence. Amir confirmed understanding and directed continuation. This does not create a bucket or authorise a specific paid resource or deployment.

**Written-architecture approval:** Amir explicitly approved the LP2 architecture plan on 5 October 2026. The approved technical choices below are carried into the detailed implementation plan. No repeated architecture approval is required. No tests, UAT or Cloud inspection have been performed by this planning session.

## S2. Confirmed meeting feedback and follow-ups

| Confirmed feedback | Treatment |
|---|---|
| Three engagement categories: Academic, Industry, Business / Commercial | LP2 intake; Business / Commercial covers arrangements such as tenancy and uses Industry's six-document checklist |
| Memo wording must be specific | Display **Memo (For CEO)** in checklist, readiness, workspace and document history for both six-document categories |
| Replace the signature instruction | Later Legal Review screen uses **Proceed to Signing**; implement in LP4 |
| Closed matters must remain usable as reference | Keep Not Proceeding submissions in the portal; keep registered expired/terminated agreements searchable and readable with unmistakable closed styling |
| Wider reference access needs a role boundary | Target: Requesting Staff see their own submissions and linked agreements; Viewer retains wider read-only Register access; Legal/Admin retain their appropriate wider access |

The closed-Register discussion also approved **All agreements** as the default view, with **Active**, **Expired**, and **Terminated** filters. This is a separate Register UX correction; its implementation timing is not silently added to LP2 Documents. A completed project does not itself mean the agreement is expired or terminated.

Requesters do not receive a metadata-only catalogue of unrelated closed matters. The approved access direction retains Viewer, superseding the older V1 specification's Viewer-removal proposal. Existing production Requester/Viewer Register access remains unchanged during LP2–LP5; realignment is handled in LP6 alongside the replacement linked-record experience.

**Unconfirmed Legal follow-up questions:** Outlook email notifications, first-time-requester document templates, and a production workflow guide explaining the end-to-end journey. They are not approved LP2 or V1 requirements. Email remains outside V1 unless explicitly amended.

The 28 September amendments also remain binding: concise, action-led production copy and fixed expiry for every newly created Register record. Existing historical null-expiry records remain supported; LP6 must apply the same new-record rule as the manual form.

## S3. Outcome and milestone boundary

Requesting Staff complete the New Submission form and upload the required files in one session. Files remain temporary until **Submit to Legal**. Reopening the page does not restore a saved form or submission. Successful submission creates a complete, locked Pending Legal Review package. Legal/Admin inspect and download it through the application.

LP2 includes classification, conditional intake information, dynamic checklists, temporary upload validation, atomic official creation with storage compensation, permanent Version 1 history, and private downloads/audit.

LP3 owns Conversation, Internal Legal Notes, unread indicators and broader collaboration presentation. LP4 owns Legal classification correction, clarification/revision requests, selected-slot reopening and controlled review actions. LP5 owns signing, stamping, final verification, Not Proceeding and reopening. LP6 owns guided registration, final Addendum/Register relationships and access realignment.

No saved-draft state, draft workspace, persisted pre-submission version history, or draft-resume action is introduced. Temporary Replace/Remove actions do not create official versions. No Register outcome is created in LP2.

## S4. Repository and production findings

- `CreateSubmission::__invoke(array $input): Submission` currently immediately creates pending submission and `submission_created` audit. LP2 extends this trusted boundary to require a complete file package.
- `submitted_at` is non-nullable and stays non-nullable. No draft migration or draft status is needed.
- Existing portal components are Livewire 4 single-file components in `resources/views/livewire/submissions/`.
- `SubmissionPolicy` authorises active owners and Legal/Admin; update/delete deny everyone. Preserve post-submission mutation denial in LP2.
- Permanent documents currently have a private `documents` disk with `serve => false` and `throw => true`; no LP2 files or download controller exist.
- Installed Livewire's temporary default is 12,288 KB. LP2 must intentionally support the approved 20 MB limit end to end.
- Livewire's signed temporary upload/preview requests are transport checks, not substitutes for per-user permanent download authorisation and audit. No public or temporary document download URL is exposed.
- Local development uses SQLite; prior architecture records MySQL 8.4 in production. Verify migrations on populated history and avoid SQLite-only schema assumptions.
- The 2 October session inspected production and recorded **no Object Storage bucket**. No real document uploads were enabled. A verified private persistent bucket is a production-release prerequisite. This is a dated observation, not a fresh Cloud inspection on 5 October.

## S5. Intake and checklist — approved direction

Collect title (255), active non-TBD campus/department, partner name (255), purpose (5,000), engagement category, partner location and agreement type. Proposed category values are `academic`, `industry`, `business_commercial`; location values are `local`, `international`. Exact internal names and field limits beyond existing base limits are technical proposals for written-architecture review, not additional business requirements.

New intake choices are exactly NDA, MOA, MOU, ADDENDUM. Introduce a new-intake vocabulary without removing historical LOI/SEA/null values. A blank type prompt cannot be officially submitted. SEA is an optional free-text MOA arrangement for new intake, rather than a standalone new-submission type.

| Category | Location | Mandatory slots |
|---|---|---|
| Academic | Local or International | Agreement; Requisition Form |
| Industry | Local | Agreement; Memo (For CEO); Requisition Form; Due Diligence Form; Company Profile; Malaysian Corporate Information (SSM or equivalent) |
| Industry | International | Agreement; Memo (For CEO); Requisition Form; Due Diligence Form; Company Profile; Business Registration Document |
| Business / Commercial | Local | Same six Local Industry slots |
| Business / Commercial | International | Same six International Industry slots |

The matrix applies to Addenda. Stable proposed keys: `agreement`, `requisition_form`, `memo`, `due_diligence_form`, `company_profile`, `corporate_registration_local`, `corporate_registration_international`. Corporate-registration keys distinguish the two requirements so changing location cannot silently accept the wrong selected file.

MOA shows optional free-text subtype/arrangement, proposed maximum 255 characters. Non-MOA intake must not persist a hidden subtype value.

Addendum collects amendment purpose and either an authorised original-Agreement selection or **Agreement not found** details: original title, partner, approximate date, campus/department, and optional identifying notes. Proposed limits are 255 for each identifying field and 2,000 for notes. Approximate-date text avoids inventing an exact date. Selection and not-found modes are mutually exclusive.

Original selection must use the current user's Register visibility, never an unscoped agreement query. Revalidate on submit; hidden/pending or forged IDs reveal no foreign content. Include authorised archived records where the Register's normal access permits them. No unresolved original creates a duplicate Agreement. LP2 collects intake information; LP6 confirms the final Register relationship, with Legal review handling questions when introduced in LP4.

## S6. Historical LP1 compatibility — confirmed 5 October

Amir confirmed that existing submissions remain historical. Preserve owner, status, type, submitted timestamp, creation event and all existing data. Do not infer category/location, invent document files, backfill requirements, or reopen history.

Proposed `intake_version` defaults to 1 for existing rows; new LP2 submissions are server-assigned 2. New classification columns remain nullable in the database for history but required at the LP2 creation boundary. Historical detail says **Submitted before document intake was introduced** and offers no upload control.

No old immediate-submit API remains available to new requests. Compatibility permits reading historical rows; it does not bypass new validation.

## S7. Data structure — approved separation, proposed exact schema

Use commented migrations ordered as submission extensions, slots, then versions; never edit applied LP1 migrations. Keep PHP attribute configuration and existing activity-recording conventions.

### Submission extensions

Proposed fields: `intake_version` (unsigned small integer, default 1), nullable string `engagement_category`, `partner_location`, `moa_subtype`; nullable indexed `original_agreement_id` with `restrictOnDelete()`; boolean `original_agreement_not_found`; nullable JSON `original_agreement_details`; nullable `submission_key` (UUID string) and `intake_digest` (SHA-256 string). Unique `(created_by, submission_key)` supports duplicate-safe official creation; existing rows keep both new values null. Keep `agreement_id` dormant for the future outcome; never overload it with the original agreement.

No `draft`, nullable submitted timestamp, draft lock version or draft-edit action is added. Ownership, status, submitted timestamp, intake version, submission key/digest and resulting agreement link remain server-assigned. Only validated intake fields may be mass assigned. The submission key identifies one completed submission attempt; it is not a draft record or resume feature.

### Document slots

`submission_document_slots`: primary ID; required indexed `submission_id` FK with `restrictOnDelete()`; stable `key`; `is_required`; nullable `current_version_number`; timestamps. Unique `(submission_id, key)`.

Current version resolves by slot ID plus version number, avoiding a circular FK. Version insertion and current-pointer assignment occur in the same database transaction. LP2 creates only the applicable intake slots. Later checklist correction may preserve obsolete slots/history; LP2 does not ship that action.

### Immutable versions

`submission_document_versions`: primary ID; required `slot_id` FK with `restrictOnDelete()`; monotonic `version_number`; nullable indexed `uploaded_by` FK with `nullOnDelete()`; uploader-name snapshot; disk identity; unique generated path; sanitised original filename; validated MIME type; size in bytes; SHA-256; server lifecycle label; created timestamp only. Unique `(slot_id, version_number)`.

LP2 creates **Version 1 — Submitted for Review** (`submitted_for_review`) in every applicable slot. Replacing a temporary selection before submit creates no permanent version. Later milestones append revised/signed versions; previous rows/bytes cannot be overwritten or deleted by application actions.

Restrict deletion to protect legal history and explain this owned-child cascade exception in migration comments. Optional actor deletion preserves attribution through the snapshot. This is application-level immutability, not a database-administrator access restriction.

## S8. Storage and creation boundary — approved direction

Use distinguishable storage identities, such as `documents_local` and `documents_cloud`, and persist the actual identity in every version. Never repoint one recorded disk name from a local folder to an unrelated cloud bucket and assume historical files remain reachable. Local uses a private folder; production uses private persistent Laravel Cloud Object Storage. Both provide no public serving and throw on write failure. For R2, configure privacy at bucket level and omit unsupported per-object ACL/visibility write headers; verify adapter behaviour instead of copying local-disk options blindly.

`CreateSubmission` resolves the authenticated active requester internally, authorises and validates base/conditional fields and all required temporary files. Server-generated paths and ownership are never accepted from input. Proposed interface: `__invoke(array $input, array $uploadReceipts, string $formTicket): Submission`; receipts are keyed by checklist vocabulary and verified by the server, not raw browser file paths. No alternate metadata-only creation path remains exposed.

Validate the whole package before permanent writes. Store fresh permanent objects with generated keys, then atomically create submission, slots, Version 1 rows, current pointers and audit in a database transaction. Set pending and submitted timestamp on the server. Recheck current actor, classification and original-record visibility before commit.

Filesystem and database writes are not one transaction. On storage, database or audit failure, roll back records and remove only fresh objects written by that failed attempt. Preserve unrelated files and earlier versions. If cleanup fails, log its generated keys internally for operational recovery without exposing storage details to the user. Keep valid temporary originals available for retry until success or expiry. A crash can leave unreferenced objects; the proposed scheduled recovery operation below covers that gap.

Submission audit records the checklist/classification and exact Version 1 IDs. Use existing `submission_created` and named upload events without recording a success for a rolled-back operation. Exact event names are implementation-plan details.

Proposed retry strategy: issue an encrypted, user-bound, expiring form ticket with a random UUID on opening New Submission. Temporary upload receipts bind objects to that user and ticket. At submission, compute a canonical digest of validated fields and actual file hashes. Persist the ticket UUID as `submission_key` with `intake_digest`. An authenticated retry of the same ticket and matching package returns the previously created owned submission without repeating audit; a different package under an already consumed ticket is rejected. Concurrent creates are resolved by the database unique constraint, not only a disabled button; a losing attempt rolls back and removes only its newly generated permanent objects.

Before requiring temporary objects to remain readable on retry, check an existing owned submission key. Compare its saved digest against validated field values and authenticated receipt hashes, so a lost-success response can recover even after successful temporary cleanup. For new creation, read and hash the actual bytes again and compare them to the receipts. Tickets and receipts convey no authority to set owner/status/disk/path arbitrarily. Opening a fresh form issues a fresh ticket; no endpoint restores unfinished form state. A 24-hour form/receipt lifetime matches abandonment cleanup.

## S9. Downloads and role boundary — approved direction

Proposed route: `GET /submissions/{submission}/documents/{version}/download`, named `submissions.documents.download`, nested inside auth and portal-role middleware. Resolve the version through the specified submission's slots; a version ID from another parent is not sufficient authority.

Allow active owner and Legal/Admin to download current and historical shared versions. Foreign requester or mismatched parent/version gets 404 with no filename/title/content disclosure. Viewer remains denied the portal; guests redirect to login. Inactive enforcement remains in web middleware and policies/actions.

The controller authenticates, authorises, opens a readable stream, records download initiation, then streams an attachment using a safe filename. Opening first prevents missing objects from producing false successful-download events. Audit failure sends no bytes. Proposed headers: private/no-store cache and nosniff. Downloads use saved allow-listed disk identities, not browser paths. No `Storage::url()` or `temporaryUrl()` for document access.

Every Livewire method authorises independently. Re-resolve protected public models and recheck the current actor/ownership on hydration. Requester queries remain ownership constrained before pagination/counts. No Blade control or signed transport link replaces server authorisation.

## S10. Design section 3 — approved 5 October 2026

Amir approved this behaviour in the 5 October chat after reviewing the section-3 explanation:

- Local development temporary uploads use private local storage. Production temporary uploads use a private Object Storage temporary area. No official row or permanent version exists before submit.
- Unsubmitted temporary objects are automatically removed after 24 hours. Reopening the page does not restore the form.
- Raise the current 12 MB temporary limit to 20 MB; verify matching infrastructure and transport limits.
- Each row shows its document name, short purpose, formats/size, Missing/Uploading/Ready/Error, selected filename/size and pre-submission Replace/Remove.
- A concise readiness panel shows remaining blockers.
- A classification change warns before clearing files whose requirements disappear. Industry ↔ Business / Commercial preserves shared six-file selections. Local ↔ International resets the corporate-registration file.
- Validate selection and official submission: extension, server content type, non-empty, maximum size, malformed/macro/renamed disallowed content. Generate storage keys independently of filenames.
- After submission show Version 1, Submitted for Review label, uploader, Malaysian time, filename/type/size, Download, history and Locked while Legal reviews.
- Rejected selection leaves other selections intact. Failed official creation leaves no partial official package. Missing stored content gives a safe message, operational error and no false successful-download audit.

### Proposed transport and recovery details for written review

Use an application upload endpoint, protected by auth, active-account enforcement, requester role, CSRF and throttling, for one file per request. It validates bytes and writes to the private temporary area on the appropriate local/cloud disk; it returns an encrypted expiring receipt containing actor ID, form UUID, slot key, generated object key, safe filename, type, byte count and hash. The Livewire form retains only these receipts and displayed metadata. Replace/Remove verifies the same actor/ticket before retiring an object; reject mismatched or expired receipts without revealing metadata. The server never accepts an arbitrary disk/path from browser input. The submit operation rereads and validates every applicable receipt/object independently.

This application-mediated transport still uses private cloud storage for production temporary files. It avoids issuing document read links or relying on signed storage URLs as user authorisation. It also avoids making native Livewire direct-to-S3 upload assumptions: the installed direct uploader emits an ACL header, while Laravel Cloud's R2 storage uses bucket-level visibility and documents that per-object ACLs are unsupported. Do not modify vendor code. The implementation plan will define the dedicated temporary-upload controller and its browser/form integration, with real HTTP tests.

Define the advertised 20 MB limit as 20,480 KB (20 MiB) consistently in validation. Validate extension and detected content; inspect bounded DOCX package structure and reject macros/malformed packages rather than accepting arbitrary renamed ZIP. Permit valid server ZIP-detected DOCX only after inspection. PDF checks verify expected content; do not promise exhaustive malware detection or arbitrary PDF rendering validation. Verify runtime MIME/ZIP capability and hosting request limits before release.

Use dedicated temporary and permanent-generated prefixes. A scheduled cleanup operation removes expired temporary files after the 24-hour abandonment window and unreferenced permanent objects older than a 24-hour grace period. It lists only application-owned prefixes, rechecks document references immediately before deletion, supports dry run, and never deletes referenced current/old versions. Record failures internally and retry through the scheduled maintenance process; normal users do not need a terminal. This is file-recovery maintenance, not legal-record deletion. Capture private bucket visibility, credentials and real cleanup evidence at release. Persistent storage remains distinct from backups.

Upload failure preserves the previous valid selection until its replacement succeeds. Warn before applying classification changes that clear now-inapplicable selections; switching Industry/Business preserves the shared receipts. Final creation failure preserves usable selections and provides a retry action. Missing/expired selections require reupload of the affected slot. A same-ticket retry after success opens its existing submission; it does not create a second request.

Installed Livewire replaces temporary storage with `tmp-for-tests` in unit tests. Real HTTP/browser/deployment verification is needed in addition to component/storage-fake tests. Infrastructure and adapter requirements must be verified rather than inferred from configuration.

## S11. Design section 4 — testing and representative-file UAT, approved 5 October 2026

Automated acceptance should cover:

1. All three categories, both locations, four new types, exact two/six slot sets and Memo (For CEO) wording; tampered classifications cannot change the trusted matrix.
2. No submission/slot/version rows during temporary selection, replacement or removal. Invalid or incomplete packages write no official record.
3. Successful creation writes owner/status/time/intake version, all Version 1 files/pointers and audit together. Injected owner/path/disk/status/outcome fields have no effect.
4. Valid real fictional PDF/DOCX fixtures, boundaries at/above 20 MB, malformed/macro/spoofed/empty/disallowed files; ZIP-detected DOCX is accepted only when it is a valid Word package.
5. Local/International selection reset and preservation of files when switching Industry/Business categories; no temporary-file loss without the specified warning.
6. Storage, database and audit failure compensation, cleanup failure reporting, user/ticket-bound receipts, same-package retries after temporary cleanup, changed-package rejection and concurrent unique-key duplicate-submit tests. Rejected replacement preserves the previous selection.
7. Owner/Legal/Admin positive download controls; foreign requester, wrong parent/version, inactive, Viewer, guest, missing object and audit-failure negative controls with no confidential bytes.
8. Submitted-file replacement remains forbidden, including deliberately replayed Livewire requests. Later-state strings do not accidentally grant upload permission.
9. Populated migration preserves all historical data, FKs/indexes and historical LOI/SEA/null types. Deletion restrictions and uploader attribution work.
10. Existing Register role/pending/expiry rules and user-management/inactive-session regression coverage continue passing.

Use existing PHPUnit, RefreshDatabase, refetched model assertions and positive controls for important denials. Tests use fictional content. No production test legal record is created without the normal release authorisation.

Manual UAT should exercise Academic, Industry and Business / Commercial Local/International, MOA arrangement and Addendum found/not-found; Replace/Remove/ready states; valid scanned PDF and DOCX above 12 MB and near 20 MB; failed uploads/submission; locked workspace and authorised downloads. Intan/Siti verify concise copy, checklist terminology and practical file usability. Reopening an unfinished form should demonstrate the agreed no-resume behaviour.

### Design section 5 — production storage and release prerequisites, approved 5 October 2026

Before release, verify the actual deployed baseline, database backup/migration plan, installed storage adapter, created private persistent bucket and binding, distinct disk identities, MIME/ZIP support, upload/request limits, private temporary storage and cleanup, failure/orphan recovery, and actual authenticated downloads. The bucket is a prerequisite before accepting real production files; this plan does not create it.

Verify actual Cloud bucket configuration and cost before provisioning; no resource was created here. Bucket privacy prevents public file reads; application authorisation still limits access per submission. Keep test/local documents separate from production and do not copy real production documents to development automatically. Verify persistence by downloading an approved fictional smoke-test document after deployment/replacement, then clean up the test according to the release plan. Add backup/recovery instructions for both database metadata and file objects; persistence alone is not backup evidence.

Sources checked on 5 October: [Laravel Cloud Object Storage](https://laravel.com/cloud/docs/resources/object-storage) documents private/public bucket visibility, adapter requirements and bucket-level permissions; [Laravel Cloud filesystem persistence](https://laravel.com/cloud/docs/knowledge-base/sqlite) explains that application filesystems reset across deployments and infrastructure changes. These sources inform design; they do not prove the project's production bucket is configured.

Record real verification, test counts, deployment commit and smoke-test evidence in `docs/handoff-lp2.md` and `docs/qa/`. Run changed-PHP formatting, composer test and any required asset build during implementation; none are necessary or claimed for this documentation correction.

## S12. Proposed implementation map after design approval

1. Intake/schema/compatibility: extend Submission, checklist support, document models/migrations/factories; preserve non-null submitted timestamp and historical rows.
2. Temporary form: extend the existing submission-form and Livewire/filesystem configuration, implement the approved section-3 decisions and validated package selection.
3. Atomic creation: extend CreateSubmission and focused permanent-storage/version helpers; enforce all-or-nothing records with compensating file cleanup.
4. Submitted workspace/downloads: extend submission-show and index labels, add the nested download controller and audit; retain ownership filters and post-submit mutation denials.
5. Integration/UAT/release evidence: final tests, browser checks, documentation and verified Object Storage prerequisite.

Expected touched areas: `app/Models/Submission.php`, document slot/version models, `app/Actions/CreateSubmission.php`, focused document validation/storage helpers, `app/Policies/SubmissionPolicy.php`, `app/Models/SubmissionActivity.php`, a submission-document controller, submission factories and new migrations, the existing three Livewire submission components, routes, Livewire/filesystem configuration, `.env.example`, focused Submission tests/fixtures and QA/handoff docs. All new Livewire components remain emoji-free in `resources/views/livewire/`.

Additional proposed files for the concrete transport/recovery design: `app/Http/Controllers/SubmissionTemporaryUploadController.php`, `app/Support/SubmissionUploadReceipts.php`, `app/Rules/SubmissionDocumentFile.php`, `app/Support/SubmissionChecklist.php`, `app/Console/Commands/CleanupSubmissionDocumentFiles.php`, `config/submissions.php`, and a focused Alpine upload module if the existing form needs it. Schedule maintenance in `routes/console.php`. Exact signatures, route verbs, migrations and focused tests belong in the subsequent code-level plan. No vendor overrides or generic file manager are introduced.

No draft model/actions, generic workflow engine, premature review/signature controls, access realignment, Outlook integration, templates, guide, or separate Register filter implementation is included. Dependencies and Cloud-resource changes are made concrete and reviewed before execution. Exact code-level task/file instructions follow written-architecture review.

## S13. Next discussion

The five behaviour/design sections and consolidated written architecture are approved. Next deliverable: `docs/implementation-plan-lp2.md`, covering exact files/interfaces, failing tests, implementation sequence, review checkpoints, and release prerequisites. The V1 specification and project continuity instructions reflect confirmed amendments. Product implementation, production provisioning, commits and deployment remain separate steps.
