# LP1 — Legal Submission Portal Foundation

> **Status:** Approved by Amir on 14 Sep 2026. Amendment 1 approved by Amir on 14 Sep 2026; see S20. Amendment 2 approved by Amir on 17 Sep 2026; see S21.
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

Active-account enforcement currently applies at login only. A deactivated user may keep an already-authenticated session; this pre-existing gap is recorded as DEF-013 in `docs/qa/defect-log.md` and is not fixed in LP1-A.

LP1 should introduce a separate `Submission` domain rather than add fields to `Agreement`. A submission has a different owner, audience, lifecycle, and confidentiality boundary. Keeping the domains separate prevents a requester's private submission access from becoming access to another requester's records. Agreement Register access remains a separate read-only reference permission for non-Legal users.

## S3. Confirmed decisions

The following decisions are locked for LP1:

1. Admin receives the same all-submissions queue and detail access as Legal.
2. Audit history begins in LP1. Creating a submission writes a `submission_created` event.
3. `submissions.agreement_id` is added as a nullable, dormant V2 preparation field.
4. The V1 interface, model API, routes, and workflow do not expose or use the Agreement link.
5. The internal role value remains `requester`; the interface says **Requesting Staff**.
6. Livewire components remain emoji-free single-file components. LP1 does not introduce a mixed class-based architecture.
7. Requesting Staff retain read-only access to the Agreement Register, like Viewer users. This does not grant access to `pending` agreements or any register mutation. See Amendment 2 in S21.

## S4. LP1 user stories

### Requesting Staff

- Create and submit a basic agreement request.
- See a list containing only submissions they personally created.
- Open only their own submission details.
- See that a submitted request is `Pending` and locked.
- Use the Agreement Register as a read-only reference, excluding agreements still in internal Legal vetting (`document_status = pending`).

### Legal and Admin

- See one shared queue containing all submissions.
- Open every submission and see its requester and creation information.
- Perform no review action yet; LP1 is read-only for Legal after submission creation.

### Viewer

- Use the Agreement Register as a read-only reference, excluding agreements still in internal Legal vetting (`document_status = pending`).
- See no portal navigation.
- Receive `403 Forbidden` for portal entry routes.

## S5. Proposed submission fields

The recommended first form is intentionally short:

| Field | Storage | Rule | Reason |
|---|---|---|---|
| Submission title | `title` | Required, string, maximum 255 | Gives the queue a useful human-readable identifier. |
| Campus / Department | `campus_id` | Required active campus; exclude `TBD` | New requests should identify their real originating unit; `TBD` exists for incomplete historical register data. |
| Partner / organisation | `partner_name` | Required, string, maximum 255 | Free text avoids exposing or modifying the Agreement Register's curated partners table. |
| Agreement type | `agreement_type` | Nullable known type | Requesting Staff may select LOI, NDA, MOA, MOU, SEA, or ADDENDUM; “Not sure” stores `null`. Legal can classify it later. `MOC` is excluded: it remains only in the legacy `agreements.type` database enum, and the current Agreement form deliberately rejects it (amended 14 Sep 2026; see S20). |
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

Required indexes:

- `created_by, created_at` for “My Submissions” (its leading column also covers the `created_by` foreign key);
- `status, created_at` for the Legal queue;
- an explicit single-column index on `campus_id`; and
- an explicit single-column index on `agreement_id`.

Foreign-key index rule (amended 14 Sep 2026; see S20): MySQL InnoDB automatically indexes foreign-key columns, but SQLite does not. Every foreign-key column must therefore have an explicit index unless it is already the leading column of a composite index on the same table. Do not rely on the database engine to supply it.

`status` should be a plain string, not a database enum. The repository already had to rebuild `users` to escape an inflexible enum. Application validation and controlled transition methods provide safer evolution across SQLite and MySQL.

`submitted_at` is retained even though it initially matches `created_at`. It is the business timestamp for submission and remains meaningful if a draft feature is introduced later. LP1 itself has no drafts.

### `submission_activities`

| Column | Type and rule |
|---|---|
| `id` | Primary key |
| `submission_id` | Required foreign key to `submissions`; `restrictOnDelete()` |
| `user_id` | Nullable foreign key to `users`; `nullOnDelete()` |
| `type` | String |
| `description` | Human-readable string |
| `meta` | Nullable JSON |
| `created_at`, `updated_at` | Laravel timestamps |

Required indexes:

- `submission_id, created_at` (its leading column also covers the `submission_id` foreign key); and
- an explicit single-column index on `user_id`.

The initial event type is `submission_created`. Later milestones add status, message, note, revision, document, download, and completion events without changing the table shape.

`submission_id` uses `restrictOnDelete()`, not `cascadeOnDelete()` (amended 14 Sep 2026; see S20). A submission that has audit history cannot be hard-deleted, so no deletion — through code, a console session, or a future feature — can silently erase that history. This is a deliberate exception to the repository convention of cascading owned children: `agreement_activities` can safely cascade because `Agreement` uses soft deletes, whereas `Submission` has no soft deletes and no deletion workflow in V1.

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

Required casts on `Submission` (amended 14 Sep 2026; see S20):

| Attribute | Cast |
|---|---|
| `created_by` | `integer` |
| `campus_id` | `integer` |
| `agreement_id` | `integer` (remains `null` when unset) |
| `submitted_at` | `datetime` |

The policy compares `created_by` with the authenticated user's ID using strict comparison. Explicit integer casts guarantee identical types whether a model is built in memory, refetched from SQLite, or refetched from MySQL, so ownership checks do not depend on database-driver fetch behaviour.

Mass assignment: `created_by`, `status`, `submitted_at`, and `agreement_id` must not be fillable. Only trusted creation code assigns them.

## S8. Authorization model

Create `SubmissionPolicy`; the repository currently has no policy layer, so this becomes the first record-level authorization policy.

| Ability | Requesting Staff | Legal | Admin | Viewer |
|---|---:|---:|---:|---:|
| `viewAny` | Own list | All | All | No |
| `view` | Own only | All | All | No |
| `create` | Yes | No | No | No |
| `update` | No in LP1 | No in LP1 | No in LP1 | No |
| `delete` | No | No | No | No |

Every ability denies an inactive user, whatever their role. This includes inactive Admin, Legal, and Requesting Staff accounts.

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

Submission creation happens at one trusted creation boundary: an application action (`App\Actions\CreateSubmission`). The LP1-B Livewire form calls this action; it does not duplicate its rules or write submissions itself.

The creation boundary (amended 14 Sep 2026; see S20):

1. Derives the owner from the authenticated session. It accepts no `User` argument and no owner field. If no user is authenticated, it denies.
2. Authorizes `create` for that authenticated user.
3. Validates every LP1 submission field with the S13 rules. Validation runs inside the boundary, so every caller is protected, not only the LP1-B form.
4. Uses only validated values, and ignores any caller-supplied owner, status, submission timestamp, or agreement link.
5. Opens one database transaction.
6. Sets `created_by` from the authenticated user.
7. Sets `status` to `pending` in trusted application code.
8. Sets `submitted_at` from the server clock.
9. Leaves `agreement_id` unset, so it remains `null`.
10. Creates the `submission_created` activity through a small `RecordSubmissionActivity` action.
11. Commits both records together. Redirecting to the new detail page is the LP1-B caller's responsibility.

If activity creation fails, submission creation must roll back. This prevents a real submission from existing without its first required audit event.

A validation failure raises Laravel's standard validation exception before any database write. Livewire converts that exception into field errors, so the LP1-B form still displays messages normally.

The activity feed is append-only at the application boundary: LP1 provides no edit or delete route, component action, or ordinary maintenance UI for activity records. The database reinforces this by restricting deletion of any submission that has activity rows (S6). The actor may later be deleted, so `user_id` is nullable while the description and timestamps remain.

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
- **Submission form:** requester-only form that calls the trusted `CreateSubmission` action (S9), which performs validation, ownership and status assignment, and atomic activity creation.
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

The Requesting Staff dashboard should provide clear **Create Submission**, **My Submissions**, and **Open register** entry points. The Agreement Register read routes allow Admin, Legal, Viewer, and Requesting Staff; create/edit routes remain Admin/Legal only. The existing pending-agreement global scope continues hiding `document_status = pending` records from both Viewer and Requesting Staff users.

## S13. Validation and safe input handling

These server-side rules are enforced inside the trusted creation boundary (S9), not only in a form:

- `title`: required string, maximum 255;
- `campus_id`: required integer, exists in active campuses, and cannot reference code `TBD`;
- `partner_name`: required string, maximum 255;
- `agreement_type`: nullable and limited to the six types accepted by the current Agreement form: LOI, NDA, MOA, MOU, SEA, and ADDENDUM (`MOC` is rejected);
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

Refetch rule (amended 14 Sep 2026; see S20): authorization and persistence assertions must use records reloaded from the database — for example `Submission::query()->findOrFail($id)`, `$model->fresh()`, or `assertDatabaseHas()` — not only the in-memory instance returned by a factory or action. LP1-B route-model binding will pass database-loaded models to the policy, so tests must exercise that same path.

### Model and migration tests

- required relationships resolve;
- `status` defaults to `pending`;
- `agreement_id` accepts null and has no V1 relationship/API usage;
- `created_by`, `campus_id`, and a non-null `agreement_id` are integers after refetch; an unset `agreement_id` refetches as `null`;
- status and timestamps cast correctly;
- factory data is fictional;
- foreign-key delete behaviour matches the migration contract, including that a submission with activity history cannot be hard-deleted and its activities remain;
- every foreign-key column is covered by an explicit index or by the leading column of a composite index, verified through the schema builder on SQLite.

### Creation and validation tests

- Requesting Staff can create a valid submission;
- the owner is derived from the authenticated session, and creation is denied when no user is authenticated;
- owner, status, and `submitted_at` are server assigned and verified after refetch;
- creation writes exactly one `submission_created` activity with the actor, verified after refetch;
- submission and activity are atomic;
- Legal, Admin, Viewer, and guests cannot create;
- inactive Admin, Legal, and Requesting Staff cannot create;
- invalid, inactive, and `TBD` campus IDs are rejected at the creation boundary;
- `MOC` and unknown agreement types are rejected at the creation boundary, and `null` is accepted;
- missing required fields and overlong `title`, `partner_name`, and `purpose` values are rejected at the creation boundary;
- a rejected request writes neither a submission nor an activity;
- injected `created_by`, `status`, `submitted_at`, and `agreement_id` values are ignored.

### Negative authorization tests

- Requesting Staff list returns only their own rows;
- a requester cannot see another requester's title, count, activity, or identity;
- direct access to another requester's ID returns `404`;
- the same denial holds during a Livewire update request;
- Legal and Admin can see all submissions;
- inactive Admin, Legal, and Requesting Staff are denied `viewAny`, `view`, and `create`, including for a Requesting Staff user's own submission;
- policy decisions are asserted against database-refetched submissions and users;
- Viewer receives `403` for every portal route;
- guest requests redirect to login;
- no role can update or delete a submission in LP1;
- requester-controlled HTML is escaped.

### Navigation and display tests

- role-appropriate portal labels appear;
- Viewer sees no portal navigation;
- Requesting Staff sees Agreement Register navigation alongside **My Submissions**;
- Requesting Staff can open the Agreement Register list and non-pending detail records but receives no register mutation controls;
- Requesting Staff cannot see a pending agreement in list counts, filters, year options, search results, or direct detail access;
- Legal/Admin detail pages identify the requester;
- Requesting Staff detail pages do not expose internal-only controls or fields.

Run the full suite, not only LP1 tests, because route and navigation changes can regress the Agreement Register.

## S16. Planned files

Expected new files:

- `database/migrations/..._create_submissions_table.php`
- `database/migrations/..._create_submission_activities_table.php`
- `app/Models/Submission.php`
- `app/Models/SubmissionActivity.php`
- `app/Actions/CreateSubmission.php`
- `app/Actions/RecordSubmissionActivity.php`
- `app/Policies/SubmissionPolicy.php`
- `database/factories/SubmissionFactory.php`
- the three Livewire files listed in S11;
- focused feature tests under `tests/Feature/Submission/`;
- `docs/handoff-lp1a.md` as the LP1-A implementation handoff; and
- `docs/handoff-lp1.md` after implementation and verification.

Expected modified files:

- `app/Models/User.php` for its submissions relation;
- `routes/web.php`;
- `resources/views/layouts/app.blade.php`;
- `resources/views/dashboard.blade.php`;
- `docs/qa/test-cases.md`; and
- `docs/qa/traceability-matrix.md`.

Update `docs/qa/defect-log.md` only when LP1 work finds a genuine defect or security gap. Do not manufacture defect entries merely to make the document longer. The LP1-A security review found one pre-existing gap outside LP1 scope, recorded as DEF-013.

## S17. Implementation milestones

Recommended safe order:

1. Begin from the approved decisions recorded in S3, S19, and S20.
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

Steps 2 to 4 form LP1-A and are specified for the Senior Developer in `docs/handoff-lp1a.md`. Steps 5 to 7 form LP1-B.

This order establishes the security boundary before exposing the records through UI routes.

## S18. V1 boundary, risks, and trade-offs

### Included in LP1

- submission schema and model;
- dormant nullable `agreement_id` preparation;
- initial audit schema and creation event;
- requester creation form;
- requester-owned list and detail;
- Legal/Admin shared queue and detail;
- read-only Agreement Register access for Requesting Staff, with the same pending-record confidentiality boundary as Viewer;
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
- deletion, archiving, reopening, or Completed-to-In-Review transition;
- V2 Agreement Register linking UI, relationship, button, or workflow; and
- the DEF-013 fix for sessions that survive account deactivation.

### Main risks and trade-offs

- **Free-text partner name:** avoids exposing register data but allows spelling variants. Legal can normalize it when V2 creates an Agreement record.
- **Nullable agreement type:** reduces bad guesses by non-Legal staff but means Legal must classify some requests later.
- **No drafts:** keeps the workflow simple and enforces the confirmed post-submit lock, but users must prepare their information before submitting.
- **Admin queue access:** supports troubleshooting and continuity but broadens confidential-data access. Admin accounts must remain limited to trusted personnel.
- **Dormant `agreement_id`:** prevents a later migration but creates a column with no V1 behavior. Comments and tests must clearly preserve that boundary.
- **Separate activity table:** adds two LP1 files and one transaction, but ensures the audit trail starts with the first real event and scales into later milestones.
- **Restricted submission deletion:** protects audit history, but a mistaken submission can no longer be removed with an ordinary delete. Any future deletion or archiving feature needs its own approved design.
- **Session-derived creation owner:** prevents a caller from creating a submission on another user's behalf, but the action cannot be reused for unattended jobs or seeders without an explicit, separately approved design.

## S19. Resolved questions and LP1 acceptance criteria

### Stakeholder decisions confirmed 14 Sep 2026

1. The recommended form fields in S5 are sufficient for Legal's initial triage.
2. Agreement type may be left as **Not sure**, stored as `null`.
3. `TBD — Not Assigned` is excluded from new portal submissions.
4. V1 has no draft or save-and-continue feature.
5. V1 uses the database ID as its simple submission number. No separate reference-number scheme is introduced unless Legal later identifies a formal requirement.

All LP1 planning questions are resolved. Implementation must follow these decisions unless a documented stakeholder change is approved first.

### Recommended first implementation milestone

**LP1-A: Schema, models, audit creation, and authorization contract.** Do not build the visible pages until this foundation passes. The Senior Developer handoff is `docs/handoff-lp1a.md`.

Acceptance criteria:

1. Both migrations work on fresh in-memory SQLite tests and are written to remain compatible with production MySQL 8.4.
2. Submission ownership, campus, activities, and actor relationships are tested.
3. `agreement_id` is nullable and unused by V1 application behavior.
4. Creating a submission and its `submission_created` event is one atomic operation.
5. The policy proves Requesting Staff can view only their own submissions, Legal/Admin can view all, and Viewer cannot access the portal.
6. Cross-requester access returns `404` and exposes no submission content.
7. No application code, migration, or test uses real staff or legal matter data.
8. The complete implementation diff is shown to Amir before any migration command.
9. `created_by`, `campus_id`, and `agreement_id` have explicit integer casts, proven by refetch tests.
10. The creation boundary derives its owner from the authenticated session and accepts no caller-supplied `User`.
11. The creation boundary validates every LP1 field, including rejection of `MOC`, unknown types, inactive and `TBD` campuses, and overlong text; rejected input writes nothing.
12. Authorization and persistence assertions use database-refetched records.
13. Inactive Admin, Legal, and Requesting Staff are denied every portal ability.
14. `submission_activities.submission_id` restricts deletion, and a test proves a submission with activity history cannot be hard-deleted.
15. Every foreign-key column is covered by an explicit index or the leading column of a composite index on SQLite, proven by a schema test.

## S20. Amendment record

### Amendment 1 — 14 Sep 2026 — LP1-A security review corrections

**Status:** Approved by Amir on 14 Sep 2026. The amended sections supersede the originally approved wording.

**Reason:** An independent security review of a trial LP1-A implementation, which was discarded before commit, found gaps in the originally approved plan. This amendment corrects the plan before the Senior Developer implements LP1-A. The stakeholder decisions in S3 and S19 are unchanged.

| # | Correction | Sections |
|---|---|---|
| 1 | Removed `MOC` from portal agreement types. The original S5 and S13 wording allowed seven types, including `MOC`, but the current Agreement form deliberately rejects `MOC`. A submission type must always map to a type the Agreement Register accepts. | S5, S13 |
| 2 | Changed `submission_activities.submission_id` from cascading ("owned child") to `restrictOnDelete()`, so hard deletion cannot erase audit history. `Submission` has no soft deletes, unlike `Agreement`. | S6, S9, S18 |
| 3 | Required explicit integer casts for `created_by`, `campus_id`, and nullable `agreement_id`, so strict ownership comparison is independent of database fetch types. | S7 |
| 4 | Required the creation boundary to derive the owner from the authenticated session instead of accepting a caller-supplied `User`. | S9, S18 |
| 5 | Moved validation of every LP1 field into the trusted creation boundary, so no caller can bypass it. | S9, S11, S13 |
| 6 | Required authorization and persistence assertions against database-refetched records. | S15 |
| 7 | Required inactive Admin, Legal, and Requesting Staff policy tests, and stated that every ability denies inactive users. | S8, S15 |
| 8 | Required explicit SQLite indexes for foreign-key columns not covered by the leading column of a composite index (`submissions.campus_id`, `submissions.agreement_id`, `submission_activities.user_id`). The original wording assumed engine-provided indexes, which SQLite does not create. | S6, S15 |
| 9 | Recorded the pre-existing deactivated-session gap as DEF-013. It is documented only and is not fixed in LP1-A. | S2, S16, S18 |

LP1-A acceptance criteria 9 to 15 in S19 were added by this amendment.

## S21. Amendment 2 — 17 Sep 2026 — Requesting Staff Agreement Register access

**Status:** Approved by Amir on 17 Sep 2026. This amendment supersedes the earlier rule that Requesting Staff have no Agreement Register access.

**Reason:** The Agreement Register and Legal Submission Portal serve different audiences and confidentiality boundaries. The Register is the institutional reference list of agreements for authenticated UniKL users. Submissions are private working requests. Blocking Requesting Staff from the Register confused submission ownership with reference access and prevented staff from consulting existing agreements before or after submitting a request.

The corrected access contract is:

| Role | Agreement Register | Legal Submission Portal |
|---|---|---|
| Requesting Staff | Read-only; non-pending agreements only | Create and view own submissions only |
| Viewer | Read-only; non-pending agreements only | No access |
| Legal | Full working access, including pending agreements | View all submissions |
| Admin | Full access, including pending agreements | View all submissions |
| Guest | No access | No access |

This amendment does not make the Register public and does not weaken submission isolation. In particular:

1. `role:admin,legal,viewer,requester` gates Agreement Register list and detail routes inside `auth`.
2. Agreement create and edit routes remain `role:admin,legal` only.
3. Register mutation controls remain governed by `canWrite()` and therefore remain unavailable to Requesting Staff and Viewer.
4. The existing pending-agreement global scope remains unchanged. Requesting Staff and Viewer receive no pending rows, counts, filters, year options, search results, or direct detail record; direct access resolves as `404`.
5. Portal routes and `SubmissionPolicy` remain unchanged: Requesting Staff see only their own submissions, Viewer sees none, and Legal/Admin see all.
6. The Register is not limited permanently to 2022–2026. Those are the current data years; future agreement years continue to appear normally.

The bounded Senior Developer handoff is `docs/handoff-lp1-register-access.md`. LP1 must not be approved for commit or deployment until that correction is implemented, the automated suite passes, and Amir completes the manual role walkthrough.
