# UniKL Agreement Management System V1 Design

> **Status:** Approved in conversation by Amir on 25 September 2026; written specification pending repository review.
> **Product name:** UniKL Agreement Management System
> **Modules:** Submission Portal and Agreement Register
> **Department:** Corporate Legal & Secretariat Department (CLSD)
> **Repository:** Existing Laravel 13, Livewire 4, Tailwind CSS 4 application

## 1. Outcome

V1 extends the implemented LP1 Submission Portal into a complete, traceable agreement workflow. A Requesting Staff user submits the required documents, Legal reviews and communicates through the portal, Legal obtains UniKL's signature, the requester obtains the partner's signature and any required LHDN stamping, Legal verifies the final documents, and Legal creates the official Agreement Register record through a guided confirmation step.

The system manages agreements rather than every activity of the Legal department. The product name is therefore **UniKL Agreement Management System**, not UniKL Legal Management System. The interface may describe it as managed by CLSD.

V1 succeeds when Intan can operate the complete workflow without technical assistance, Requesting Staff always know what to do next, and Intan and Siti can validate the complete journey from the approved mockup before implementation continues.

## 2. Current system and changed access direction

LP1 already provides basic requester submission creation, private requester queues, a shared read-only Legal/Admin queue, initial audit creation, and Requesting Staff read-only access to the broader Agreement Register. Viewer users currently retain Register-only access.

The approved V1 direction changes that access model:

- Legal and Admin receive full Agreement Register and Submission Portal access.
- Requesting Staff see only submissions they created and the Agreement Register outcomes linked to those submissions.
- Viewer is removed from the target V1 product because no confirmed user group or recurring Viewer workflow exists.
- External partners receive no account or system access.

This reverses LP1 Amendment 2 for the target V1. It does not authorise an immediate access removal. Existing requester and Viewer access remains unchanged during LP2-LP5. LP6 reviews existing accounts and performs the access realignment only after the portal provides the replacement linked-record experience.

## 3. Roles and capabilities

### 3.1 Requesting Staff

Requesting Staff can:

- create a submission and provide every mandatory intake document;
- view only submissions they created;
- participate in the shared Conversation for their submissions;
- answer clarification requests and supply requested revisions;
- view and download the shared document-version history;
- download the UniKL-signed agreement;
- upload the partner-signed agreement and, for an MOA, the LHDN stamp certificate;
- see requester-visible activity and the person expected to act next; and
- open the Agreement Register outcome linked to their own registered submission.

Requesting Staff cannot browse the complete Agreement Register, access another requester's submission, see Internal Legal Notes, perform Legal transitions, or delete a submitted record.

### 3.2 Legal

Legal can:

- access the shared submission queue and every submission;
- review every shared document version;
- correct submission classifications and resulting checklists;
- request written clarification, document revision, or both;
- participate in Conversation and write Internal Legal Notes;
- complete Legal review and upload the UniKL-signed agreement;
- verify final signatures and applicable stamping;
- mark a submission Fully Executed or Not Proceeding;
- reopen a Not Proceeding submission with a recorded reason; and
- create and confirm the linked Agreement Register record.

### 3.3 Admin

Admin receives the same portal and Register capabilities as Legal and retains existing user-management responsibilities. Every action is attributed to the individual account.

## 4. Submission classification and intake

Every new submission requires these classifications:

- **Engagement category:** Academic or Industry;
- **Partner location:** Local or International; and
- **Agreement type:** NDA, MOA, MOU, or ADDENDUM.

The requester supplies the classifications because they know the intended arrangement when starting the submission. Legal can correct a misclassification during review. A correction recalculates the mandatory checklist, records an audit event, and moves the submission to Action Required from Requester when the correction introduces a missing requirement.

LOI is discontinued for new submissions. Historical LOI records remain visible and unchanged in the Agreement Register.

### 4.1 Mandatory intake checklist

**Academic submissions require:**

- Agreement; and
- Requisition Form.

**Industry submissions require:**

- Agreement;
- Memo;
- Requisition Form;
- Due Diligence Form;
- Company Profile; and
- SSM or equivalent Malaysian corporate information for a Local partner, or an equivalent business-registration document for an International partner.

The checklist applies to every new submission, including an Addendum. Local or International changes the corporate-registration requirement for Industry submissions. The portal identifies missing items and prevents official submission until every mandatory intake slot has a current file.

### 4.2 MOA subtype

MOA is the top-level Agreement Register type. When MOA is selected, the form shows an optional free-text **MOA subtype / arrangement** field. Examples include Student Exchange Agreement, Research Collaboration, and Erasmus+. The field remains free text because Legal has not established a complete controlled subtype list.

### 4.3 Addendum intake

ADDENDUM remains a standalone agreement type; it is not an MOA subtype. An Addendum modifies an existing agreement and is read together with it.

The requester must:

- select the existing Agreement Register record being amended; or
- choose **Agreement not found** and provide the original agreement's title, partner, approximate date, campus or department, and any other useful identifying detail;
- describe the purpose of the Addendum, such as an extension, scope change, party-detail correction, or clause amendment.

Legal confirms or resolves the original-agreement link during review. An unresolved link never creates a duplicate Agreement record automatically. When the original agreement is an MOA, its subtype remains a property of that original agreement rather than turning ADDENDUM into an MOA subtype.

## 5. Documents and version history

### 5.1 Accepted files

- PDF (`.pdf`) and Microsoft Word (`.docx`) are accepted during review.
- The initial maximum is 20 MB per file. This is a technical limit, not a Legal policy, and may be increased through a later approved configuration change if representative UAT files prove it insufficient.
- ZIP archives, executable files, macro-enabled Word files, and standalone images are not accepted.
- The final executed agreement must be PDF.
- An MOA requires a standalone LHDN stamp-certificate PDF in addition to the fully signed agreement PDF.
- An Addendum requires a stamp certificate when Legal marks stamping as required.

Files remain on private storage. The application never exposes a public storage URL or uses temporary public links as an authorisation substitute.

### 5.2 One current file, preserved versions

Each checklist slot has one current file. Uploading a replacement creates a new immutable version and makes it current without overwriting or deleting earlier versions.

Both the owning requester and Legal/Admin can view and download the shared version history. Each version records its uploader, upload time, original filename, file type, size, and lifecycle label. Agreement-version labels include, as applicable:

1. Submitted for Review;
2. Revised During Legal Review;
3. UniKL Signed;
4. Both Parties Signed; and
5. Final Executed.

The LHDN stamp certificate has its own checklist slot and version history; it is not an Agreement version.

### 5.3 Controlled, stage-based uploads

- Before official submission, the requester can replace intake files freely.
- After submission, requester upload slots are locked while Legal reviews.
- Action Required from Requester reopens only the document slots selected by Legal.
- After Review Completed, Legal uploads the UniKL-signed Agreement version.
- Awaiting Partner Signature allows the requester to upload the both-parties-signed Agreement PDF.
- Awaiting LHDN Stamping allows the requester to upload the standalone stamp-certificate PDF.
- Fully Executed and Registered submissions lock replacement uploads while preserving authorised download access and version history.

If a requester notices a mistake while uploads are locked, they use Conversation and Legal decides whether to reopen the affected slot.

## 6. Conversation, internal notes, and audit

### 6.1 Conversation

Every submission has a shared asynchronous Conversation for:

- general messages;
- written clarification requests;
- document-revision requests; and
- requester responses.

Every message records its author and timestamp. Messages cannot be edited or deleted. Conversation does not accept attachments; documents must use their controlled checklist slots. V1 does not include private direct messages, typing indicators, reactions, online presence, read receipts visible to the other party, or real-time chat behaviour.

### 6.2 Internal Legal Notes

Internal Legal Notes are append-only and visible only to Legal and Admin. Each note records its author and timestamp. A correction is a new note rather than an edit or deletion. Internal notes never appear in requester-facing queries, pages, messages, notifications, downloads, or generated artifacts.

### 6.3 Activity and audit

Activity & Audit records significant events separately from Conversation, including:

- submission creation;
- classification corrections;
- checklist changes;
- uploads, replacements, and downloads;
- clarification and revision requests;
- requester responses;
- status transitions;
- review completion;
- final verification;
- closure and reopening;
- Agreement Register creation and linking; and
- rejected conflicting actions.

Audit records are append-only and identify the actor, event, timestamp, and relevant structured context.

## 7. Legal review and collaboration

Legal and Admin share one non-exclusive submission queue. A submission is not permanently assigned to one reviewer. Intan and Siti can both review and act on every submission.

The queue shows current status, who must act next, per-user unread activity, latest message or action time, the last handler, category, campus or department, requester, and useful filters.

Opening or downloading a document records recent activity. If another Legal user recently accessed the same document, the interface shows a soft warning without blocking access. Before any state-changing action is accepted, the server rechecks the current record state. A stale or conflicting action fails with a clear refresh message rather than overwriting newer work.

Unread state is tracked separately per user. Opening the Conversation marks its messages seen only for that user. The navigation and queue show unread counts and highlighted rows. The interface always identifies **Waiting for Legal** or **Waiting for Requester**. Email notifications remain outside V1.

## 8. Clarification and revision workflow

Legal can create an Action Required request containing:

- a written clarification with no document slot reopened;
- one or more selected document slots to replace; or
- both a written clarification and selected replacements.

Legal must provide a clear instruction. Unaffected document slots remain locked. The submission shows Action Required from Requester until the requester supplies the requested response and current files. The system then returns it to In Review, preserves the request and response, and records the transition.

## 9. Lifecycle and controlled transitions

The user-facing lifecycle is:

| Stage | Meaning | Next actor |
|---|---|---|
| Pending Legal Review | Complete intake package officially submitted | Legal |
| In Review | Legal is reviewing contents and supporting documents | Legal |
| Action Required from Requester | Legal needs clarification, replacement documents, or both | Requester |
| In Review | Requester responded and Legal review resumes | Legal |
| Review Completed — Awaiting UniKL Signature | Contents approved; Legal must obtain the UniKL signature | Legal |
| Awaiting Partner Signature | Legal uploaded the UniKL-signed Agreement | Requester |
| Awaiting LHDN Stamping | Both parties signed and stamping is required | Requester |
| Final Verification | Requester uploaded every required final PDF | Legal |
| Fully Executed | Legal verified signatures and applicable stamping | Legal |
| Registered | A confirmed Agreement Register record is linked | None |

Review Completed is a recorded milestone distinct from Fully Executed. Legal obtains the Vice Chancellor's signature for an Academic agreement and the CEO's signature for an Industry agreement.

MOA always enters Awaiting LHDN Stamping under the confirmed Legal workflow. MOU and NDA skip it. For an Addendum, Legal explicitly records whether stamping is required. This avoids a blanket stamping assumption because LHDN states that a binding Addendum can itself be a dutiable instrument; see its [2025 stamp-duty question-and-answer compilation](https://www.hasil.gov.my/media/v35c0b0j/kompilasi-soalan-dan-jawapan-spk-2025-bagi-siri-1-14-oktober-2025-topik-transformasi-digital-sistem-taksir-sendiri-ckht-dan-duti-setem.pdf), question 161.

Only Legal or Admin can mark Fully Executed. Legal can return an incorrect final package to Action Required from Requester. Every transition records its actor and timestamp and enforces its prerequisites on the server.

## 10. Signing and final verification

The signing sequence is:

1. Legal completes the content review.
2. Legal obtains the appropriate UniKL signature.
3. Legal uploads the UniKL-signed Agreement version.
4. The requester downloads that version and obtains the partner's signature on the same agreement.
5. The requester uploads the both-parties-signed PDF.
6. For an MOA, the requester completes LHDN stamping and uploads the standalone stamp-certificate PDF.
7. For an Addendum, step 6 occurs only when Legal marked stamping as required.
8. Legal verifies every required final document and marks the submission Fully Executed.

The digital final package is sufficient for Fully Executed. Physical printing, comb-binding, year-labelled box storage, and other hardcopy handling occur outside the system and do not delay that status.

## 11. Guided Agreement Register creation

Fully Executed does not create an Agreement record automatically. Legal selects **Create Register Record** and receives a guided form pre-filled with known submission data. Legal reviews, corrects, and completes the official fields before confirming.

The operation:

- creates the new Agreement as Signed/Fully Executed;
- links the Agreement and Submission permanently;
- links an Addendum to the original Agreement it modifies;
- records the creation and link in Activity & Audit; and
- moves the submission to Registered with a visible Register link.

The creation and linking operation is atomic: either the Agreement and every required link succeed together or nothing is saved. Existing historical `pending`, `awaiting_partner`, `signed`, and LOI records remain unchanged.

## 12. Not Proceeding

A submission can stop without becoming an Agreement because the requester withdraws it, the partner declines, or Legal determines that it cannot proceed.

- Requesting Staff ask to withdraw through Conversation; they cannot delete the submission.
- Legal or Admin closes it as **Not Proceeding** with a mandatory reason.
- The original details, documents, versions, Conversation, Internal Legal Notes, and audit history remain preserved.
- The owning requester can still view their closed submission.
- A Not Proceeding submission cannot create an Agreement Register record.
- Legal or Admin can reopen it with a mandatory reason, producing a new audit event.

Fully Executed, Registered, and Not Proceeding records cannot be hard-deleted through the normal interface.

## 13. Architectural boundaries

The Submission remains the workflow aggregate. Focused supporting units provide:

- dynamic checklist slots;
- immutable document versions;
- shared Conversation messages;
- append-only Internal Legal Notes;
- per-user read state;
- append-only activity and audit events;
- controlled lifecycle transitions; and
- the optional final Agreement link.

Each unit has one responsibility and enforces its own access boundary. The implementation plan will define exact schema and API names while preserving these separations.

## 14. Failure behaviour and security

- Every page and mutation authorises on the server. Hidden controls are presentation only.
- Requesting Staff queries are ownership-scoped and cross-requester direct access reveals no content.
- Livewire actions re-authorise independently and re-resolve scoped or protected models during hydration.
- Internal Legal Notes use a separate access path from requester-visible data.
- Uploads validate extension, MIME type, and size; storage paths are generated by the server.
- A failed replacement leaves the existing current version untouched.
- Old versions cannot be overwritten.
- Missing prerequisites block transitions with a clear explanation.
- Stale state-changing actions fail safely and ask the user to refresh.
- Agreement creation and linking cannot partially complete.
- Inactive-account enforcement continues to apply to page requests and Livewire updates.

## 15. Approved mockup

The requirements-led Claude mockup contains exactly twelve views.

### Requesting Staff

1. My Submissions;
2. New Submission with classification, Addendum linking, dynamic checklist, and upload states;
3. Submission Workspace with overview, documents, Conversation, and Activity;
4. Action Required with Legal instructions and controlled response fields;
5. Post-review execution with the UniKL-signed download and final uploads; and
6. Completed submission showing Fully Executed or Registered outcome.

### Legal and Admin

7. Shared Submission Queue;
8. Legal Review Workspace;
9. Internal Legal Notes;
10. Review and signature progression;
11. Guided Register creation; and
12. Addendum workflow, including existing-agreement selection and Agreement not found resolution.

The mockup uses the existing institutional palette and information hierarchy but does not copy speculative features from the older prototype. It is desktop-first, uses fictional data, demonstrates important state and error variations, and serves as a workflow-validation artifact rather than an implementation specification or production asset.

## 16. Delivery roadmap

### LP2 — Documents

Dynamic checklists, secure upload and download, controlled document slots, immutable version history, and representative-file UAT.

### LP3 — Communication

Conversation, Internal Legal Notes, per-user unread state, navigation and queue indicators, and who-acts-next presentation.

### LP4 — Legal review

Shared actionable queue, classification correction, clarification and revision requests, controlled review transitions, audit expansion, and concurrency safeguards.

### LP5 — Execution

UniKL and partner signing stages, MOA and conditional Addendum stamping, final verification, Fully Executed, Not Proceeding, and reopening.

### LP6 — Registration and release

Guided Agreement Register creation, Addendum relationships, final requester and Viewer access realignment, complete audit coverage, UAT, release evidence, and Intan handover updates.

## 17. Explicit V1 exclusions

V1 does not include:

- electronic or digital signature services;
- direct LHDN integration;
- physical printing, comb-binding, or box-storage tracking;
- email notifications or reminders;
- real-time chat, typing indicators, reactions, or presence;
- automatic Agreement Register creation;
- advanced analytics or reporting;
- public document URLs;
- external partner accounts;
- general Legal-department case management; or
- hard deletion of submitted records.

## 18. Verification requirements

Automated verification must cover:

- every role, route, and mutation boundary;
- cross-requester isolation and negative content assertions;
- Internal Legal Note confidentiality;
- upload validation, private downloads, and download audits;
- immutable version preservation and failed-replacement rollback;
- classification correction and checklist recalculation;
- every permitted and forbidden status transition;
- clarification-only and document-revision requests;
- per-user unread behaviour;
- stale and conflicting Legal actions;
- MOA and Addendum stamping paths;
- Fully Executed, Registered, and Not Proceeding outcomes;
- guided Register creation and permanent linking;
- Addendum-to-original relationships;
- historical LOI and legacy-status preservation; and
- continued inactive-account enforcement.

Manual browser verification must cover complete Requesting Staff, Legal, and Admin journeys with fictional data. Intan and Siti review the approved mockup before LP2 implementation planning, and Intan completes workflow-focused UAT before V1 release.
