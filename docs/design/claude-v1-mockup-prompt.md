# Claude prompt: UniKL Agreement Management System V1

Create a polished, interactive prototype of **UniKL Agreement Management System** for Universiti Kuala Lumpur's Corporate Legal & Secretariat Department (CLSD). Deliver a working preview artifact that we can click through with Legal staff. Use English interface text.

## Purpose and source priority

The prototype will help two Legal staff members validate a practical workflow before development. Success means they can explain what happens next, who is responsible, which document is current, and why a submission can or cannot advance.

Build from the requirements below. The attached `unikl-agreement-management-v1-spec.md` contains the approved product design. If an older HTML prototype is attached, use it only for visual inspiration. Its features, agreement types, workflows, sample policies, and roles are superseded by this brief. Do not import its scripts or bundled assets.

Design the completed target V1, including its approved access restrictions. The production application currently has fewer capabilities; this prototype demonstrates the intended future experience.

Use local fictional data and simulated interactions. No real accounts, documents, signatures, external services, or production changes are needed. Label the artifact discreetly as a prototype using fictional data. Demonstration controls must be visually separate from the application navigation.

## Visual direction

Create a calm, credible institutional application that non-technical staff can use daily. Carry forward the reference's navy `#1F3A5F`, restrained maroon `#7B2231`, pale background `#F4F6F8`, white surfaces, and subtle borders. Use a readable sans-serif font, clear headings, compact but comfortable tables, and status badges with text as well as colour. Do not invent an official university crest; use a typographic UniKL identity if no logo asset is supplied.

Prioritise document names, status, the next responsible person, and the primary action. Keep secondary actions quieter. Use progressive disclosure for old versions, full timelines, and infrequent actions. Avoid giant decorative dashboards, excessive cards, gradients, and repeated progress widgets.

Optimise for a laptop at approximately 1366–1440 px wide. Allow sensible stacking on narrower screens. Provide clear labels, keyboard focus, accessible contrast, friendly validation, and readable dates such as `25 Sep 2026, 3:20 PM` in Malaysian time. Include discreet Private & Confidential cues around submission documents.

## Roles and navigation

- **Requesting Staff:** own submissions only, including their shared documents, conversations, and linked Register outcome. No organisation-wide Agreement Register browsing.
- **Legal:** shared submission queue and full Agreement Register access. Both Legal staff can act on every submission; no exclusive reviewer assignment.
- **Admin:** same workflow access as Legal plus existing user-management responsibility. Do not add a user-management screen to this mockup.
- No Viewer role or external partner accounts.

Create a small, clearly labelled prototype toolbar to switch between one fictional requester and two fictional Legal users, and to reset demonstrations. Admin can reuse the Legal view. The role switcher is a demonstration aid, never a product feature.

Requester navigation centres on My Submissions and New Submission. Legal navigation centres on Submission Queue and Agreement Register. The Register outcome can be shown inside the approved completion or registration view; a complete Register redesign is outside this brief.

## Exactly twelve views

Use these twelve views as the coverage list. Several may be states or tabs of the same workspace rather than twelve navigation items. Keep navigation understandable. Modals, drawers, validation states, and status variants do not require extra product pages.

1. **My Submissions:** own records, title, partner, type, current status, next action, per-user unread indicator, latest update, search/filter, and New Submission action. Include an empty state and Not Proceeding history.
2. **New Submission:** basic details, required classifications, conditional MOA/Addendum fields, dynamic checklist, upload validation, and clear submission readiness.
3. **Requester Submission Workspace:** overview, current documents, version history, Conversation, and requester-visible Activity. Show a specific next-step instruction.
4. **Action Required:** Legal's instructions, clarification response, selected reopened upload slots, unchanged locked slots, and an explicit Submit Response action.
5. **Post-review Execution:** download the UniKL-signed agreement, upload the partner-signed PDF, and conditionally upload the separate LHDN stamp certificate.
6. **Completed Submission:** Fully Executed and Registered states, final PDFs, retained history, and an authorised linked Register summary. Include a Not Proceeding variant with closure reason; it has no Register record.
7. **Shared Submission Queue:** every submission, requester, campus/department, status, next actor, unread indicator, last handler, latest update, and filters for status/category/campus/date.
8. **Legal Review Workspace:** documents and versions, classification correction, Start Review, clarification/revision requests, and recent-access warning.
9. **Internal Legal Notes:** a separate Legal/Admin-only tab with author, timestamp, new-note composer, and clear confidentiality copy.
10. **Review and Signature Progression:** Complete Legal Review, UniKL-signed upload, signing/stamping progress, final verification, rejection of incorrect final files, closure and reopening variants.
11. **Guided Register Creation:** available only after Fully Executed; pre-filled details, Legal review and completion, confirmation, success, and permanent submission link.
12. **Addendum Workflow:** original-agreement selection, Agreement not found fallback, amendment purpose, Legal link resolution, and Legal's explicit stamping decision.

## Intake and checklist rules

Include submission title, campus/department, partner/organisation name, and purpose/scope. The requester must select:

- Engagement category: **Academic** or **Industry**.
- Partner location: **Local** or **International**.
- Agreement type: **NDA**, **MOA**, **MOU**, or **ADDENDUM**.

LOI is unavailable for new submissions. Historical LOI records are preserved. SEA, Research Collaboration, and Erasmus+ are descriptions under MOA, not additional top-level agreement types. Selecting MOA reveals an optional free-text **MOA subtype / arrangement** field with those examples.

The Academic checklist has exactly two mandatory slots:

1. Agreement
2. Requisition Form

The Industry checklist has exactly six mandatory slots:

1. Agreement
2. Memo
3. Requisition Form
4. Due Diligence Form
5. Company Profile
6. SSM / Malaysian corporate information for Local, OR business-registration document for International

Local/International does not add an Academic document requirement. Checklist requirements apply to Addendums according to their Academic/Industry classification too. Do not introduce quotations, board resolutions, or other requirements from the old prototype.

Show a short explanation beside each slot, the current filename, size, upload state, and what is missing. Official submission is blocked until all required fields and files are supplied. Files may be replaced while completing the form. Do not add saved drafts or autosave promises.

Legal can correct the classification during review. The checklist recalculates; any newly missing requirement becomes Action Required from Requester. Preserve existing documents and history when classifications change. Show what changed and what needs supplying rather than silently deleting files.

## File rules and versions

- Review documents accept PDF and DOCX, initially up to **20 MB per file**.
- Final signed agreements and stamp certificates must be PDF.
- No standalone images, archives, macro-enabled documents, or executable files.
- One current file per checklist item; replacements retain every earlier version.
- Each version shows filename, version number, uploader, time, and stage where relevant.
- The owning requester and Legal/Admin can download shared versions.
- Failed replacement preserves the current file and allows retry.
- Downloads produce audit activity.

Use a version-history drawer or expandable list. Clearly identify submitted/revised versions, the UniKL-signed version, and the both-parties-signed version. Legal's verification marks the accepted final version; it need not invent a duplicate upload.

**The LHDN stamp certificate is a separate document with its own versions. It is never a replacement Agreement file.**

For the prototype, simulated files and small fictional downloads are enough. Explain simulation honestly. Do not ask reviewers to upload real confidential agreements or imply that front-end role switching provides production security.

## Lifecycle and responsibility

Use these labels consistently:

| Status | Responsible party / transition |
|---|---|
| Pending Legal Review | Complete intake submitted; Legal starts review |
| In Review | Legal reviews the contents and supporting documents |
| Action Required from Requester | Requester supplies the specifically requested response/files |
| Review Completed — Awaiting UniKL Signature | Legal approved contents and obtains UniKL's signature |
| Awaiting Partner Signature | Legal uploaded the UniKL-signed agreement; requester obtains partner signature |
| Awaiting LHDN Stamping | Requester obtains and uploads the certificate when required |
| Final Verification | Legal checks the final signed PDF and any required certificate |
| Fully Executed | Legal confirmed the final package; Register creation is available |
| Registered | Legal confirmed and created the linked Register record |
| Not Proceeding | Legal closed the submission with a reason; history retained |

Review Completed and Fully Executed are different milestones. Legal obtains UniKL's signature first: Vice Chancellor for Academic; CEO for Industry. The requester obtains the partner's signature on that same agreement.

For MOA, the requester uploads the both-parties-signed PDF, then the standalone LHDN stamp certificate. MOU and NDA skip stamping under the approved workflow. For Addendum, Legal explicitly decides whether stamping is required. Do not default an undecided Addendum to “not required.”

Final Verification requires all applicable final PDFs. Only Legal/Admin can mark Fully Executed. If something is incorrect, Legal requests a correction to the affected document and verifies the returned final package before completion. Do not imply that a routine signature-file correction discards the already recorded review-completion milestone.

Show a concise progress indicator and one prominent next action. Never expose an unrestricted status dropdown that bypasses missing files or role requirements. Completed/closed records should show completion or closure instead of a misleading “Waiting for Requester” label.

## Upload control

After official submission, requester replacement controls are locked while Legal reviews. A Legal revision request reopens only specified slots. Later execution stages open only the appropriate signature or certificate slot. Fully Executed and Registered lock replacement uploads while preserving downloads and history.

Explain locked slots in plain language, such as “Legal is reviewing this version. Send a message if a correction is needed.” Both Legal and requester can contribute versions when their stage permits it.

## Conversation and requests

Separate **Conversation**, **Internal Legal Notes**, and **Activity & Audit**. Requesters never see an Internal Legal Notes tab, note content, snippets, or note-derived counts in shared views.

Conversation supports general messages, formal clarification requests, formal document-revision requests, and responses. Every entry shows author and time. Sent entries cannot be edited or deleted; a correction is another message. No attachments in messages: all files use document slots.

Legal can request clarification only, replacements for selected slots, or both, with mandatory clear instructions. Show these as actionable request cards. The requester completes an explicit **Submit Response** action after supplying the requested answer/files. During content review this returns the submission to In Review. Ordinary messages, file selection, or merely opening the page must not silently advance status.

Unread state is personal to each user. Opening Conversation marks its messages read for that user only. Demonstrate that one Legal user opening it does not clear the other's unread state. Show counts in navigation and queue, plus who acts next and latest activity. Do not add email, read receipts visible to the sender, reactions, typing indicators, or real-time presence.

Internal Legal Notes have their own append-only composer, author, and timestamp. They do not change status. Shared Activity shows appropriate milestones, uploads/downloads, and actions; Legal-only information stays outside requester output.

## Shared Legal working

Both fictional Legal users can work on every submission. Show “Last handled by” without implying exclusive ownership. A non-blocking recent-access warning may say: “Another Legal user downloaded Agreement.pdf 8 minutes ago and may still be reviewing it.” Do not claim they are currently online or actively viewing a Word file.

Include a simulated conflicting-action state: “This submission changed since you opened it. Refresh to see the latest version.” Preserve the user's unsent input where practical and prevent the stale action from appearing successful.

## Addendums and access

ADDENDUM is standalone, with an original-agreement reference and Purpose of Addendum. Show the selected original's title, partner, type, and agreement date; show MOA subtype if applicable.

The requester picker must respect the approved access boundary: show only original records already accessible to that requester through their own linked submissions. It must not become a search across other people's confidential agreements. For anything unavailable, offer **Agreement not found** and identifying information for Legal to resolve. Describe this as “Cannot find or access the original agreement.”

Legal can search the broader Register to resolve the link. Show an unresolved-original warning and prevent registration of an Addendum with no confirmed original link. Never create an original record automatically just because the requester cannot find it. If resolving it needs existing Register maintenance, indicate that Legal must complete that existing process; do not invent another page or duplicate-record shortcut.

## Guided registration and closure

Only Fully Executed submissions show **Create Register Record** to Legal/Admin. Pre-fill title, type, partner, campus, and known details. Let Legal review and complete register details such as Date Signed, expiry or indefinite duration, and PIC name. PIC is a person's name, not a system account. Do not add a separate Effective Date, which the existing Register no longer uses.

An MOA subtype remains descriptive metadata; the Register type is MOA. ADDENDUM retains its original-agreement relationship. Confirming creates one Signed record and its link, then displays Registered. Include a success summary and a duplicate-click guard. A requester sees only their authorised linked outcome, without broad Register navigation.

Historical LOI and legacy pending/awaiting-partner records are preserved; do not depict automatic conversion, deletion, or reclassification of them.

Requesters can ask to withdraw through Conversation. Legal closes a submission as **Not Proceeding** with a mandatory reason. Preserve documents, messages, history, and Legal-only notes. The requester can still view their closed submission. Legal may reopen with a mandatory reason. Use a pre-execution example for this demonstration; do not invent cancellation or termination of an already registered agreement.

## Demonstration data and journeys

Use clearly fictional staff names, partners, submission numbers, files, messages, and dates. UniKL and CLSD are the real institutional context, but case content must be fictional. Seed enough varied records to make filters and responsibilities understandable without filling the interface with noise.

Provide these walkthroughs through the twelve views:

1. **Academic MOU:** two intake files → Legal review → clarification-only request → explicit requester response → review complete → UniKL signature → partner signature → final verification without stamping → Fully Executed → Legal-confirmed registration.
2. **Industry Local MOA:** six intake files → targeted replacement and retained history → UniKL signature → partner signature → separate certificate → Legal final verification and registration.
3. **Industry International:** switch location and show the company-registration slot changing; demonstrate a missing-document warning and a Legal classification correction.
4. **Addendum:** authorised original selection and unavailable-original fallback; Legal link resolution; both stamping-required and stamping-not-required variants.
5. **Not Proceeding:** reasoned closure, retained requester history, Legal reopening with a reason, no Register creation while closed.

Demo controls may jump to seeded stages for review, but normal product controls must follow the workflow. Keep one continuous primary scenario so edits, messages, versions, badges, unread counts, and timelines stay consistent across role switches.

## Exclusions and delivery checks

Do not add e-signatures, LHDN integration, physical filing tracking, email, analytics, a marketing homepage, general litigation/legal case management, external access, bulk exports, saved drafts, or automatic registration. Do not add technical architecture diagrams or development milestones to product screens.

Produce the interactive artifact now using a format supported by your environment. Choose local interface details needed to complete it; list any substantive unresolved business question separately instead of silently inventing a policy. Include a brief walkthrough after the artifact.

Before delivering, check that all twelve views are reachable; navigation and major buttons work; the two checklists contain exactly the required files; LOI/SEA are absent from new top-level type choices; Addendum stays standalone; requester access is restricted; Legal notes never leak; ordinary chat does not change status; versions survive replacement; the stamp certificate remains separate; review completion does not create a Register entry; final verification gates Fully Executed; registration requires Legal confirmation; and closed history is retained.
