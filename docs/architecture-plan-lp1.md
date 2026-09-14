# LP1 — Legal Submission Portal Foundation

> **Status:** Approved by Amir on 14 Sep 2026; ready for LP1-A implementation.
> **Date:** 14 Sep 2026
> **Scope:** Submission records, creation, role-aware lists, detail visibility, and creation audit history.
> **Repository:** Existing CLSD Agreement Register; Laravel 13, Livewire 4, SQLite locally, MySQL 8.4 in production.
> **Implementation constraint:** This document authorises no migration execution, commit, push, deployment, or production command. Amir performs those steps manually after reviewing the implementation diff.

## S1. Outcome

LP1 establishes the smallest useful and secure submission workflow inside the existing application. A Requesting Staff user can submit a request and see only requests they created. Legal and Admin users share one queue and can see every submission. Viewer users cannot access the portal.

Every newly submitted request starts as `pending` and immediately receives a `submission_created` audit event. LP1 does not include documents or Legal review actions; those are later milestones.

## S2. Repository fit

The repository already provides the foundations LP1 needs:

- authentication and active-account enforcement;
- the internal `requester` role with the user-facing label **Requesting Staff**;
- `User::canAccessPortal()` for Admin, Legal, and Requesting Staff;
- persistent role middleware for Livewire update requests;
- conventional, emoji-free Livewire 4 single-file components under `resources/views/livewire/`;
- a private `documents` disk reserved for a later upload milestone;
- reusable campuses and the `Campus::active()` scope;
- an established activity-recording pattern; and
- PHPUnit feature tests using in-memory SQLite.

LP1 should introduce a separate `Submission` domain rather than add fields to `Agreement`. A submission has a different owner, audience, lifecycle, and confidentiality boundary. Keeping the domains separate also prevents Requesting Staff from accidentally gaining a path into the Agreement Register.

## S3. Confirmed decisions

The following decisions are locked for LP1:

1. Admin receives the same all-submissions queue and detail access as Legal.
2. Audit history begins in LP1. Creating a submission writes a `submission_created` event.
3. `submissions.agreement_id` is added as a nullable, dormant V2 preparation field.
4. The V1 interface, model API, routes, and workflow do not expose or use the Agreement link.
5. The internal role value remains `requester`; the interface says **Requesting Staff**.
6. Livewire components remain emoji-free single-file components. LP1 does not introduce a mixed class-based architecture.

## S4. LP1 user stories

### Requesting Staff

- Create and submit a basic agreement request.
- See a list containing only submissions they personally created.
- Open only their own submission details.
- See that a submitted request is `Pending` and locked.

### Legal and Admin

- See one shared queue containing all submissions.
- Open every submission and see its requester and creation information.
- Perform no review action yet; LP1 is read-only for Legal after submission creation.

### Viewer

- See no portal navigation.
- Receive `403 Forbidden` for portal entry routes.

## S5. Proposed submission fields

The recommended first form is intentionally short:

| Field | Storage | Rule | Reason |
|---|---|---|---|
| Submission title | `title` | Required, string, maximum 255 | Gives the queue a useful human-readable identifier. |
| Campus / Department | `campus_id` | Required active campus; exclude `TBD` | New requests should identify their real originating unit; `TBD` exists for incomplete historical register data. |
| Partner / organisation | `partner_name` | Required, string, maximum 255 | Free text avoids exposing or modifying the Agreement Register's curated partners table. |
| Agreement type | `agreement_type` | Nullable known type | Requesting Staff may select LOI, NDA, MOA, MOU, SEA, MOC, or ADDENDUM; “Not sure” stores `null`. Legal can classify it later. |
| Purpose / scope | `purpose` | Required text with an explicit maximum | Gives Legal enough context without turning LP1 into a long questionnaire. |

Recommended `purpose` maximum: 5,000 characters. This is large enough for a useful explanation while limiting accidental pasting of an entire document into the form.

Alternative: link `partner_id` directly to the existing partners table. That would improve data reuse, but it would expose register reference data to Requesting Staff and create difficult rules for new partners. Free text is safer and simpler for V1.

## S6. Proposed database entities

### `submissions`

| Column | Type and rule |
|---|---|
| `id` | Primary key |
| `created_by` | Required foreign key to `users`; `restrictOnDelete()` |
| `campus_id` | Required foreign key to `campuses`; `restrictOnDelete()` |
| `agreement_id` | Nullable foreign key to `agreements`; `nullOnDelete()`; unused in V1 |
| `title` | String |
| `partner_name` | String |
| `agreement_type` | Nullable string |
| `purpose` | Text |
| `status` | String, default `pending` |
| `submitted_at` | Timestamp |
| `created_at`, `updated_at` | Laravel timestamps |

Recommended indexes:

- `created_by, created_at` for “My Submissions”;
- `status, created_at` for the Legal queue; and
- foreign-key indexes provided for `campus_id` and `agreement_id`.

`status` should be a plain string, not a database enum. The repository already had to rebuild `users` to escape an inflexible enum. Application validation and controlled transition methods provide safer evolution across SQLite and MySQL.

`submitted_at` is retained even though it initially matches `created_at`. It is the business timestamp for submission and remains meaningful if a draft feature is introduced later. LP1 itself has no drafts.

### `submission_activities`

| Column | Type and rule |
|---|---|
| `id` | Primary key |
| `submission_id` | Required foreign key to `submissions`; owned child |
| `user_id` | Nullable foreign key to `users`; `nullOnDelete()` |
| `type` | String |
| `description` | Human-readable string |
| `meta` | Nullable JSON |
| `created_at`, `updated_at` | Laravel timestamps |

Add an index on `submission_id, created_at`. The initial event type is `submission_created`. Later milestones add status, message, note, revision, document, download, and completion events without changing the table shape.

## S7. Models and relationships

Create:

- `App\Models\Submission` using `HasFactory` and the repository's PHP attribute configuration;
- `App\Models\SubmissionActivity`; and
- a `SubmissionFactory` containing fictional data only.

Relationships:

- `Submission::requester()` belongs to `User` through `created_by`;
- `Submission::campus()` belongs to `Campus`;
- `Submission::activities()` has many `SubmissionActivity` records;
- `SubmissionActivity::submission()` belongs to `Submission`; and
- `SubmissionActivity::user()` belongs to the actor.

Do not add an `agreement()` relationship in LP1. The nullable column is migration preparation only, and omitting the relationship prevents dormant V2 scope from leaking into V1 code.

Recommended user relations:

- `User::submissions()` for requests created by that user.

## S8. Authorization model

Create `SubmissionPolicy`; the repository currently has no policy layer, so this becomes the first record-level authorization policy.

| Ability | Requesting Staff | Legal | Admin | Viewer |
|---|---:|---:|---:|---:|
| `viewAny` | Own list | All | All | No |
| `view` | Own only | All | All | No |
| `create` | Yes | No | No | No |
| `update` | No in LP1 | No in LP1 | No in LP1 | No |
| `delete` | No | No | No | No |

For an authenticated Requesting Staff user who guesses another submission ID, the policy should deny as not found (`404`), not reveal that the record exists. Viewer access is rejected by role middleware with `403` before record authorization.

Use layered enforcement:

1. `auth` middleware establishes identity.
2. `role:admin,legal,requester` protects portal routes and persists on Livewire updates.
3. `SubmissionPolicy` decides record-level access.
4. Every Livewire mutation calls authorization inside the action method.
5. Queries are role-scoped; the UI never fetches other requesters' rows and hides them afterward.

The policy is the authoritative access rule. Blade conditions are presentation only.

Alternative: a global scope that automatically hides other requesters' submissions. It gives broad defence but introduces authentication-sensitive model behaviour in commands, jobs, factories, and Legal queries. An explicit policy plus role-aware queries is easier to reason about in this small application.

## S9. Creation transaction and audit

Submission creation should run in one database transaction:

1. Authorize `create`.
2. Validate input server-side.
3. Ignore any client-supplied owner, status, submission timestamp, or agreement link.
4. Set `created_by` from the authenticated user.
5. Set `status` to `pending` in trusted application code.
6. Set `submitted_at` from the server clock.
7. Create the `submission_created` activity through a small `RecordSubmissionActivity` action.
8. Commit both records together and redirect to the new detail page.

If activity creation fails, submission creation must roll back. This prevents a real submission from existing without its first required audit event.

The activity feed is append-only at the application boundary: LP1 provides no edit or delete route, component action, or ordinary maintenance UI for activity records. The actor may later be deleted, so `user_id` is nullable while the description and timestamps remain.

## S10. Status and locking rules

LP1 recognises all four confirmed status values at the domain level:

- `pending`
- `in_review`
- `revision_required`
- `completed`

Only `pending` can be written in LP1. No LP1 screen or request may choose a status.

There is no draft state. Clicking Submit creates the official record, and the submission fields are immediately locked. Requesting Staff cannot edit them afterward. Legal and Admin can read them but cannot change status until the review milestone.

Future state transitions are reserved as follows:

| Current | Allowed next state | Future action |
|---|---|---|
| Pending | In Review | Legal starts review |
| In Review | Revision Required | Legal requests revised or missing material |
| In Review | Completed | Legal completes review |
| Revision Required | Pending | Requesting Staff submits requested revisions |
| Completed | None in V1 | Terminal state |

Do not implement these transition actions in LP1.

## S11. Livewire pages

Use the existing Livewire 4 single-file convention under `resources/views/livewire/`, without emoji prefixes:

- `submissions/submissions-index.blade.php`
- `submissions/submission-form.blade.php`
- `submissions/submission-show.blade.php`

Responsibilities:

- **Submissions index:** role-aware heading and query. Requesting Staff see **My Submissions**; Legal and Admin see **Submission Queue**.
- **Submission form:** requester-only creation, validation, trusted ownership/status assignment, atomic activity creation.
- **Submission show:** authorized read-only details, status, requester identity for Legal/Admin, and initial activity history.

Keeping the components as single-file components is consistent with every current full-page Livewire component and avoids an unapproved mixed architecture.

## S12. Routes and navigation

Recommended routes inside the existing authenticated group:

| Method | Path | Name | Access |
|---|---|---|---|
| GET | `/submissions` | `submissions.index` | Admin, Legal, Requesting Staff |
| GET | `/submissions/create` | `submissions.create` | Requesting Staff only |
| GET | `/submissions/{submission}` | `submissions.show` | Portal roles, then policy |

The navigation should show:

- **My Submissions** to Requesting Staff;
- **Submission Queue** to Legal and Admin; and
- no portal link to Viewer.

The Requesting Staff dashboard should replace its placeholder portal message with a clear **Create Submission** and/or **My Submissions** entry point. Existing Agreement Register gates remain unchanged.

## S13. Validation and safe input handling

Recommended server-side rules:

- `title`: required string, maximum 255;
- `campus_id`: required integer, exists in active campuses, and cannot reference code `TBD`;
- `partner_name`: required string, maximum 255;
- `agreement_type`: nullable and limited to the seven current known types;
- `purpose`: required string, maximum 5,000.

Render all submitted text through Blade's escaped output. Do not use raw HTML rendering for requester-controlled fields.

The campus dropdown query must include only active campuses and explicitly exclude `TBD`. An ordinary `exists:campuses,id` rule is insufficient because it would accept inactive or `TBD` records submitted by altering the browser request.

## S14. Confidentiality controls

LP1 contains no file uploads, but submission text is still confidential. Required controls are:

- no public or guest portal route;
- no cross-requester list rows, counts, detail records, or audit data;
- no requester-controlled ownership or status fields;
- no Internal Legal Note field or placeholder response data in LP1;
- no submission data in URLs other than the numeric identifier;
- no broad serialization of a Submission model to a requester response; and
- no real legal matters, staff identities, credentials, or documents in factories and tests.

Numeric IDs are acceptable because authorization—not obscurity—is the security boundary. A requester changing the ID must still receive no data.

## S15. Testing strategy

Add focused automated coverage before any local database migration is approved.

### Model and migration tests

- required relationships resolve;
- `status` defaults to `pending`;
- `agreement_id` accepts null and has no V1 relationship/API usage;
- status and timestamps cast correctly;
- factory data is fictional;
- foreign-key delete behaviour matches the migration contract.

### Creation and validation tests

- Requesting Staff can create a valid submission;
- owner, status, and `submitted_at` are server assigned;
- creation writes exactly one `submission_created` activity with the actor;
- submission and activity are atomic;
- Legal, Admin, Viewer, and guests cannot create;
- invalid/inactive/`TBD` campus IDs are rejected;
- unknown agreement types and overlong fields are rejected;
- injected `created_by`, `status`, and `agreement_id` values are ignored or rejected.

### Negative authorization tests

- Requesting Staff list returns only their own rows;
- a requester cannot see another requester's title, count, activity, or identity;
- direct access to another requester's ID returns `404`;
- the same denial holds during a Livewire update request;
- Legal and Admin can see all submissions;
- Viewer receives `403` for every portal route;
- guest requests redirect to login;
- no role can update or delete a submission in LP1;
- requester-controlled HTML is escaped.

### Navigation and display tests

- role-appropriate portal labels appear;
- Viewer sees no portal navigation;
- Requesting Staff sees no Agreement Register navigation;
- Legal/Admin detail pages identify the requester;
- Requesting Staff detail pages do not expose internal-only controls or fields.

Run the full suite, not only LP1 tests, because route and navigation changes can regress the Agreement Register.

## S16. Planned files

Expected new files:

- `database/migrations/..._create_submissions_table.php`
- `database/migrations/..._create_submission_activities_table.php`
- `app/Models/Submission.php`
- `app/Models/SubmissionActivity.php`
- `app/Actions/RecordSubmissionActivity.php`
- `app/Policies/SubmissionPolicy.php`
- `database/factories/SubmissionFactory.php`
- the three Livewire files listed in S11;
- focused feature tests under `tests/Feature/Submission/`; and
- `docs/handoff-lp1.md` after implementation and verification.

Expected modified files:

- `app/Models/User.php` for its submissions relation;
- `routes/web.php`;
- `resources/views/layouts/app.blade.php`;
- `resources/views/dashboard.blade.php`;
- `docs/qa/test-cases.md`; and
- `docs/qa/traceability-matrix.md`.

Update `docs/qa/defect-log.md` only when LP1 work finds a genuine defect or security gap. Do not manufacture defect entries merely to make the document longer.

## S17. Implementation milestones

Recommended safe order:

1. Begin from the approved decisions recorded in S3 and S19.
2. Write schema, models, relationships, factory, and their tests.
3. Write the policy and negative authorization tests before building pages.
4. Build the requester creation transaction and creation-audit test.
5. Build role-aware list and authorized detail pages.
6. Add routes, navigation, and dashboard entry points.
7. Update test cases and traceability documentation.
8. Run formatting on touched PHP files and run the full automated suite.
9. Present the complete diff and migration plan to Amir.
10. Only after approval, Amir manually migrates and performs the browser walkthrough.
11. Record actual verification evidence in `docs/handoff-lp1.md`.
12. Amir manually commits, pushes, and deploys when satisfied.

This order establishes the security boundary before exposing the records through UI routes.

## S18. V1 boundary, risks, and trade-offs

### Included in LP1

- submission schema and model;
- dormant nullable `agreement_id` preparation;
- initial audit schema and creation event;
- requester creation form;
- requester-owned list and detail;
- Legal/Admin shared queue and detail;
- route, query, policy, and Livewire-action authorization;
- navigation and dashboard entry points; and
- automated tests and QA traceability updates.

### Explicitly excluded from LP1

- document upload, download, preview, or storage use;
- document categories and version history;
- Legal review start and reviewer activity;
- requester-visible messages;
- Internal Legal Notes;
- revision requests and revised uploads;
- status-changing controls;
- requester replies;
- email or in-app notifications;
- finalised agreement upload;
- search, advanced filters, exports, dashboards, or reporting;
- deletion, archiving, reopening, or Completed-to-In-Review transition; and
- V2 Agreement Register linking UI, relationship, button, or workflow.

### Main risks and trade-offs

- **Free-text partner name:** avoids exposing register data but allows spelling variants. Legal can normalize it when V2 creates an Agreement record.
- **Nullable agreement type:** reduces bad guesses by non-Legal staff but means Legal must classify some requests later.
- **No drafts:** keeps the workflow simple and enforces the confirmed post-submit lock, but users must prepare their information before submitting.
- **Admin queue access:** supports troubleshooting and continuity but broadens confidential-data access. Admin accounts must remain limited to trusted personnel.
- **Dormant `agreement_id`:** prevents a later migration but creates a column with no V1 behavior. Comments and tests must clearly preserve that boundary.
- **Separate activity table:** adds two LP1 files and one transaction, but ensures the audit trail starts with the first real event and scales into later milestones.

## S19. Resolved questions and LP1 acceptance criteria

### Stakeholder decisions confirmed 14 Sep 2026

1. The recommended form fields in S5 are sufficient for Legal's initial triage.
2. Agreement type may be left as **Not sure**, stored as `null`.
3. `TBD — Not Assigned` is excluded from new portal submissions.
4. V1 has no draft or save-and-continue feature.
5. V1 uses the database ID as its simple submission number. No separate reference-number scheme is introduced unless Legal later identifies a formal requirement.

All LP1 planning questions are resolved. Implementation must follow these decisions unless a documented stakeholder change is approved first.

### Recommended first implementation milestone

**LP1-A: Schema, models, audit creation, and authorization contract.** Do not build the visible pages until this foundation passes.

Acceptance criteria:

1. Both migrations work on fresh in-memory SQLite tests and are written to remain compatible with production MySQL 8.4.
2. Submission ownership, campus, activities, and actor relationships are tested.
3. `agreement_id` is nullable and unused by V1 application behavior.
4. Creating a submission and its `submission_created` event is one atomic operation.
5. The policy proves Requesting Staff can view only their own submissions, Legal/Admin can view all, and Viewer cannot access the portal.
6. Cross-requester access returns `404` and exposes no submission content.
7. No application code, migration, or test uses real staff or legal matter data.
8. The complete implementation diff is shown to Amir before any migration command.
