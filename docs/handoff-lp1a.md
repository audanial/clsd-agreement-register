# LP1-A — Submission Foundation: Senior Developer Handoff

> **Audience:** OpenCode Go (Senior Developer)
> **Prepared by:** Lead Architect, 14 Sep 2026
> **Status:** Approved by Amir on 14 Sep 2026, together with Amendment 1 of the LP1 plan.
> **Governing plan:** `docs/architecture-plan-lp1.md`, including Amendment 1 (S20). Where this handoff and the plan appear to differ, stop and ask; do not choose.
> **Milestone:** LP1-A only: schema, models, audit creation, and authorization contract. LP1-B is a separate, later handoff.

## S1. Objective

Build the tested security foundation for Legal Submission Portal records before any page exposes them.

When LP1-A is complete, the application has submission and submission-activity schema definitions, models, a fictional-data factory, one trusted atomic creation action, an activity-recording action, and `SubmissionPolicy`. All of it is proven by focused automated tests. No user can reach any of it yet, because LP1-A adds no route, page, or navigation.

## S2. Required reading before writing code

1. `AGENTS.md`: repository conventions, especially PHP attribute model configuration, commented migrations, the foreign-key convention, and the emoji-free Livewire rule.
2. `docs/architecture-plan-lp1.md`: S5 to S9, S13, S15, S19, and S20.
3. `docs/handoff-lp0.md`: the current role model and the `requester` / **Requesting Staff** distinction.
4. Existing patterns to mirror:
   - `app/Models/AgreementActivity.php` and `app/Actions/RecordAgreementActivity.php` for the activity shape;
   - `app/Models/Agreement.php` and `app/Models/User.php` for attribute configuration and `casts()`;
   - `database/migrations/2026_01_01_000006_create_agreement_activities_table.php` for migration comment style;
   - `database/factories/AgreementFactory.php` for factory style; and
   - `tests/Feature/Agreement/*Test.php` for PHPUnit class-based tests with `RefreshDatabase`.

## S3. In scope

Implement only:

1. Migration definitions for `submissions` and `submission_activities`.
2. `Submission` and `SubmissionActivity` models and their relationships, plus `User::submissions()`.
3. A fictional-data `SubmissionFactory`.
4. The atomic creation action `CreateSubmission` and the audit action `RecordSubmissionActivity`.
5. `SubmissionPolicy`.
6. Focused model, validation, atomicity, foreign-key, index, and negative authorization tests.

## S4. Explicitly out of scope

Do not create, modify, or run any of the following:

- routes or changes to `routes/web.php`;
- Livewire pages or components, including anything under `resources/views/livewire/`;
- navigation, layout, or dashboard changes;
- document handling of any kind: uploads, downloads, previews, the `documents` disk, or file-related columns;
- all LP1-B functionality: submission list, form, detail page, role-aware queries, portal labels, HTTP or Livewire route tests, and QA document updates;
- review, status-transition, messaging, internal-note, revision, notification, or reporting features;
- an `agreement()` relationship or any code that reads or writes `agreement_id` beyond leaving it `null`;
- the DEF-013 deactivated-session fix, or any change to authentication, `EnsureUserHasRole`, or login;
- **development migration execution**: do not run `php artisan migrate`, `migrate:fresh`, `migrate:rollback`, `db:seed`, or any command against `database/database.sqlite`. The PHPUnit suite's in-memory SQLite migrations are permitted and required;
- production work of any kind: Laravel Cloud, MySQL, deployment, or production commands;
- package installation or `composer.json` / `package.json` changes; and
- commits, pushes, branches, or tags.

If any in-scope item appears to require an out-of-scope change, stop and report instead of making it.

## S5. Files

### Create

- `database/migrations/2026_09_14_000001_create_submissions_table.php`
- `database/migrations/2026_09_14_000002_create_submission_activities_table.php`
- `app/Models/Submission.php`
- `app/Models/SubmissionActivity.php`
- `app/Actions/CreateSubmission.php`
- `app/Actions/RecordSubmissionActivity.php`
- `app/Policies/SubmissionPolicy.php`
- `database/factories/SubmissionFactory.php`
- `tests/Feature/Submission/SubmissionSchemaTest.php`
- `tests/Feature/Submission/SubmissionRelationsTest.php`
- `tests/Feature/Submission/SubmissionPolicyTest.php`
- `tests/Feature/Submission/SubmissionCreationTest.php`
- `tests/Feature/Submission/SubmissionValidationTest.php`

If the date prefix on the migration filenames must change, keep `submissions` before `submission_activities`.

### Modify

- `app/Models/User.php`: add `submissions()` only. Change nothing else in the file.

No other file may be created or modified.

## S6. Specification

### S6.1 `submissions` migration

| Column | Definition |
|---|---|
| `id` | `$table->id()` |
| `created_by` | `foreignId('created_by')->constrained('users')->restrictOnDelete()` |
| `campus_id` | `foreignId('campus_id')->constrained()->restrictOnDelete()` |
| `agreement_id` | `foreignId('agreement_id')->nullable()->constrained()->nullOnDelete()` |
| `title` | `string` |
| `partner_name` | `string` |
| `agreement_type` | `string`, nullable. Not a database enum. |
| `purpose` | `text` |
| `status` | `string`, default `pending`. Not a database enum. |
| `submitted_at` | `timestamp`, not nullable |
| `created_at`, `updated_at` | `timestamps()` |

Indexes:

- composite `created_by, created_at`;
- composite `status, created_at`;
- single `campus_id`; and
- single `agreement_id`.

Comment the business rules in the migration, following the existing house style: the permanent owner, the `TBD` exclusion being enforced by the application, the dormant V2 `agreement_id`, why `status` is a plain string, and why the two single-column indexes exist (SQLite does not index foreign-key columns automatically).

### S6.2 `submission_activities` migration

| Column | Definition |
|---|---|
| `id` | `$table->id()` |
| `submission_id` | `foreignId('submission_id')->constrained()->restrictOnDelete()` |
| `user_id` | `foreignId('user_id')->nullable()->constrained()->nullOnDelete()` |
| `type` | `string` |
| `description` | `string` |
| `meta` | `json`, nullable |
| `created_at`, `updated_at` | `timestamps()` |

Indexes:

- composite `submission_id, created_at`; and
- single `user_id`.

The migration comment must state that `restrictOnDelete()` is a deliberate exception to the repository's cascade-owned-children convention. It protects audit history because `Submission` has no soft deletes. Do **not** use `cascadeOnDelete()`.

### S6.3 `App\Models\Submission`

- Use `HasFactory` and `#[Fillable([...])]`. Fillable is exactly `campus_id`, `title`, `partner_name`, `agreement_type`, `purpose`.
- `created_by`, `status`, `submitted_at`, and `agreement_id` must **not** be fillable.
- `casts()` returns: `created_by` → `integer`, `campus_id` → `integer`, `agreement_id` → `integer`, `submitted_at` → `datetime`.
- Constants:
  - `STATUS_PENDING = 'pending'`;
  - `STATUSES` listing `pending`, `in_review`, `revision_required`, `completed`; and
  - `AGREEMENT_TYPES` listing exactly `LOI`, `NDA`, `MOA`, `MOU`, `SEA`, `ADDENDUM`. **No `MOC`.**
- Relationships: `requester()` belongs to `User` via `created_by`; `campus()` belongs to `Campus`; `activities()` has many `SubmissionActivity`.
- No `agreement()` relationship, no soft deletes, no global scopes, and no status-transition methods.

### S6.4 `App\Models\SubmissionActivity`

- `#[Fillable(['submission_id', 'user_id', 'type', 'description', 'meta'])]`, mirroring `AgreementActivity`.
- `casts()`: `meta` → `array`.
- Constant `TYPE_SUBMISSION_CREATED = 'submission_created'`.
- Relationships: `submission()` belongs to `Submission`; `user()` belongs to `User`.

### S6.5 `App\Models\User`

- Add `submissions(): HasMany` returning `$this->hasMany(Submission::class, 'created_by')`.

### S6.6 `App\Actions\RecordSubmissionActivity`

- `__invoke(Submission $submission, User $actor, string $type, string $description, ?array $meta = null): SubmissionActivity`.
- Creates one row. It adds no update or delete method.

### S6.7 `App\Actions\CreateSubmission`: the trusted creation boundary

- Signature: `__invoke(array $input): Submission`. It must **not** accept a `User` or owner argument.
- Resolve the owner from the authenticated session, for example by injecting `Illuminate\Contracts\Auth\Factory` or `Guard`. If no user is authenticated, throw `Illuminate\Auth\Access\AuthorizationException` before any write.
- Authorize `create` on `Submission::class` for that user, for example `Gate::forUser($user)->authorize(...)`.
- Validate `$input` inside the action with `Validator::make(...)->validate()`, using exactly these rules:
  - `title`: `required`, `string`, `max:255`;
  - `campus_id`: `required`, `integer`, and a `Rule::exists('campuses', 'id')` constraint limited to `is_active = true` and `code <> 'TBD'`;
  - `partner_name`: `required`, `string`, `max:255`;
  - `agreement_type`: `nullable`, `Rule::in(Submission::AGREEMENT_TYPES)`;
  - `purpose`: `required`, `string`, `max:5000`.
- Build the submission only from **validated** values. Any other input keys, including `created_by`, `status`, `submitted_at`, and `agreement_id`, must have no effect.
- Inside one `DB::transaction()`:
  1. assign validated fields;
  2. set `created_by` to the authenticated user's ID;
  3. set `status` to `Submission::STATUS_PENDING`;
  4. set `submitted_at` to `now()`;
  5. leave `agreement_id` unset;
  6. save; and
  7. call `RecordSubmissionActivity` with the authenticated user, `SubmissionActivity::TYPE_SUBMISSION_CREATED`, and the description `Submission created`.
- Authentication, authorization, and validation all complete before the transaction opens, so rejected input writes nothing.
- Return the created `Submission`.

### S6.8 `App\Policies\SubmissionPolicy`

The policy is auto-discovered by Laravel naming conventions. Do not register it manually.

| Ability | Rule |
|---|---|
| `viewAny(User $user): bool` | Active user **and** `canAccessPortal()`. |
| `view(User $user, Submission $submission): Response` | Inactive user → deny. Active Admin or Legal → allow. Active Requesting Staff who owns the submission (`$submission->created_by === $user->id`) → allow. Active Requesting Staff who does not own it → `Response::denyAsNotFound()`. Anyone else → deny. |
| `create(User $user): bool` | Active user **and** `isRequester()`. |
| `update(User $user, Submission $submission): bool` | Always `false` in LP1. |
| `delete(User $user, Submission $submission): bool` | Always `false` in LP1. |

The inactive-user check must come first in every ability, before any role-based allow.

### S6.9 `Database\Factories\SubmissionFactory`

- `created_by`: `User::factory()->requester()`.
- `campus_id`: a fictional active campus, created with `firstOrCreate` using a non-`TBD` code such as `TEST` and a name such as `Fictional Test Campus`.
- `agreement_id`: `null`.
- `title`, `partner_name`, `purpose`: Faker-generated fictional text.
- `agreement_type`: a random value from `Submission::AGREEMENT_TYPES`.
- `status`: `Submission::STATUS_PENDING`.
- `submitted_at`: `now()`.
- No real UniKL staff, partners, campuses, legal matters, or credentials.

## S7. Required tests

Use PHPUnit class-based feature tests with `RefreshDatabase`, matching the existing suite. Do not introduce Pest.

**Refetch rule:** every authorization and persistence assertion must use records reloaded from the database, such as `Submission::query()->findOrFail($id)`, `User::query()->findOrFail($id)`, `->fresh()`, or `assertDatabaseHas()`. Asserting only against the in-memory instance returned by a factory or action does not satisfy this handoff.

**No false positives:** where a test proves a denial or rejection, it must also assert that nothing was written. Pair each important negative test with a positive control, so a rule that rejects everyone cannot pass.

### S7.1 `SubmissionSchemaTest`

1. On SQLite, every foreign-key column on `submissions` (`created_by`, `campus_id`, `agreement_id`) and `submission_activities` (`submission_id`, `user_id`) is covered by an index whose **leading** column is that foreign-key column. Use `Schema::getIndexes()` and `Schema::getForeignKeys()`, and derive the foreign-key list from the schema rather than hard-coding it.
2. `submissions.status` defaults to `pending`.
3. `submissions.agreement_id` exists and is nullable.

### S7.2 `SubmissionRelationsTest`

1. `requester`, `campus`, `activities`, `SubmissionActivity::submission`, `SubmissionActivity::user`, and `User::submissions` resolve on refetched models.
2. After refetch, `created_by` and `campus_id` are integers, a set `agreement_id` is an integer, and an unset `agreement_id` is `null`.
3. `submitted_at` is a date-time instance after refetch.
4. `Submission` has no `agreement()` method.
5. `Submission::AGREEMENT_TYPES` is exactly the six approved types and does not contain `MOC`.
6. `created_by`, `status`, `submitted_at`, and `agreement_id` are not fillable: filling them onto a new `Submission` leaves them unset.
7. Hard-deleting a user who owns a submission throws `QueryException`, and the submission still exists.
8. Hard-deleting a campus used by a submission throws `QueryException`, and the submission still exists.
9. Force-deleting an agreement linked through `agreement_id` sets the link to `null`, and the submission still exists.
10. Hard-deleting a submission that has an activity throws `QueryException`, and both the submission and the activity still exist.
11. Hard-deleting a user who authored an activity, but owns no submission, sets `submission_activities.user_id` to `null` and keeps the activity.

### S7.3 `SubmissionPolicyTest`

All submissions and users under test are refetched before each policy decision.

1. Active Requesting Staff: `viewAny` allowed; `create` allowed; `view` allowed on their own submission.
2. Active Requesting Staff viewing another requester's submission: denied, and `Gate::inspect()` reports status `404`.
3. Active Legal and active Admin: `viewAny` and `view` allowed on any submission; `create` denied.
4. Active Viewer: `viewAny`, `view`, and `create` all denied.
5. Inactive Admin: `viewAny`, `view`, and `create` all denied.
6. Inactive Legal: `viewAny`, `view`, and `create` all denied.
7. Inactive Requesting Staff: `viewAny`, `create`, and `view` on **their own** submission all denied.
8. For every role, active and inactive: `update` and `delete` are denied.

### S7.4 `SubmissionCreationTest`

1. An authenticated active Requesting Staff user creates a submission. After refetch, `created_by` equals that user, `status` is `pending`, `submitted_at` is set by the server, and `agreement_id` is `null`. Exactly one `submission_created` activity exists, with `user_id` equal to that user.
2. Injected `created_by` (another user), `status` (`completed`), `submitted_at` (a past date), and `agreement_id` (a real agreement) have no effect after refetch.
3. With no authenticated user, the action throws `AuthorizationException`, and both tables are empty.
4. Authenticated as active Admin, Legal, and Viewer in turn, the action throws `AuthorizationException`, and nothing is written.
5. Authenticated as inactive Admin, Legal, and Requesting Staff in turn, the action throws `AuthorizationException`, and nothing is written.
6. If `RecordSubmissionActivity` throws after the submission is saved, the exception propagates and both tables are empty. Substitute the recorder through the service container, for example `$this->mock(...)` or `app()->instance(...)`, so the container-resolved action receives it.
7. The action's `__invoke` has exactly one parameter, `array $input`, and no parameter typed as `User`. Assert this with reflection.

### S7.5 `SubmissionValidationTest`

Each rejection asserts `ValidationException` with an error on the expected field, and that **both** tables remain empty. Authenticate as an active Requesting Staff user.

1. A fully valid payload is accepted. This is the positive control for this class.
2. `agreement_type` of `null` is accepted.
3. Each of the six approved agreement types is accepted.
4. `agreement_type` of `MOC` is rejected.
5. An unknown `agreement_type` is rejected.
6. A missing `campus_id` and a non-existent `campus_id` are rejected.
7. An inactive campus is rejected.
8. The `TBD` campus is rejected, even when active.
9. Missing `title`, `partner_name`, and `purpose` are each rejected.
10. `title` and `partner_name` of 256 characters are rejected; 255 characters are accepted.
11. `purpose` of 5,001 characters is rejected; 5,000 characters are accepted.

## S8. Conventions and constraints

- Follow `AGENTS.md`. Use `#[Fillable]` attributes, not `$fillable` properties.
- Keep all Livewire and view code untouched. LP1-A has none.
- Test data must be fictional. Do not use real UniKL staff names, partner organisations, campus data beyond a fictional `TEST` campus, legal matters, or credentials.
- Do not add packages, service providers, global scopes, observers, or abstractions beyond those listed in S5.
- Keep code comments explanatory and in the existing style.

## S9. Verification before handback

Run only these commands:

1. `vendor/bin/pint` on the PHP files created or modified by LP1-A.
2. `composer test`, the full suite, not only the new tests. This uses in-memory SQLite and does not touch `database/database.sqlite`.

Before handing back, confirm that:

- the full suite passes, with the total test count reported;
- `git status` lists only the files in S5;
- `routes/web.php`, `resources/`, `config/`, `composer.json`, and `database/database.sqlite` are unchanged; and
- no migration, seeder, or production command was run.

## S10. Handback report

Return to Amir and the Lead Architect with:

1. the complete diff, including untracked files;
2. `composer test` output: tests, assertions, and result;
3. any deviation from this handoff, with the reason (there should be none);
4. anything that looked wrong, unclear, or risky, raised as a question rather than resolved silently; and
5. confirmation of the S9 checklist.

Do not commit. Amir reviews the diff, then decides whether to run the development migration and proceed to LP1-B.

## S11. Acceptance criteria

LP1-A is accepted when all of the following hold:

1. Every S7 test exists, passes, and follows the refetch and no-false-positive rules.
2. The full suite passes.
3. `MOC` cannot be stored through the creation boundary.
4. `submission_activities.submission_id` restricts deletion.
5. `created_by`, `campus_id`, and `agreement_id` are cast to integers.
6. The creation boundary derives its owner from the session and validates every field itself.
7. Inactive Admin, Legal, and Requesting Staff are denied every portal ability.
8. Every foreign-key column is covered by a leading index on SQLite.
9. No out-of-scope file, command, or feature from S4 was touched or run.
10. The plan's LP1-A acceptance criteria in `docs/architecture-plan-lp1.md` S19 are all met.
