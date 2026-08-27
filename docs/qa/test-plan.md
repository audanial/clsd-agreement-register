# CLSD Agreement Register — Test Plan

> **Milestone:** M4 (QA Portfolio Evidence)  
> **Approved:** 24 Aug 2026  
> **Scope:** Documents and targeted test corrections/additions only. No application code changes.

---

## 1. Scope

### In scope

- Authentication and session management (login, logout, rate limiting, deactivated users).
- Role-based access control (`admin`, `legal`, `viewer`).
- The single core business rule: agreements with `document_status = pending` are invisible to non-Legal users.
- Agreement list, search, filtering, pagination and archive handling.
- Agreement creation and editing, including validation, partner quick-create, and role-aware dropdowns.
- Agreement detail view, status-change controls, and the activity feed.
- Date semantics: null expiry means indefinite, stale-project-status badge, soft date warning.
- Activity logging: `created` and `status_changed` rows; ordinary edits must not log `updated` rows.
- Data integrity: model casts, relations, scopes, idempotent seeders.

### Out of scope

- File upload UI (`agreement_files` table exists; no UI in MVP).
- Excel import / CSV export.
- Dashboard analytics.
- Archive / restore UX (archive is implemented as a data state, not as a user-facing archive workflow).
- Email / password-reset flows (replaced by the `user:password` artisan command).
- Browser-level Livewire behaviour (debounce, navigation history) — tested only by manual walkthrough.

---

## 2. Approach

### Automated tests

The primary verification layer is PHPUnit feature tests against Livewire single-file components and plain HTTP routes. Tests run against an in-memory SQLite database (`phpunit.xml`). Every feature test class uses `RefreshDatabase`.

- **Unit tests** cover role helpers and small model predicates that have no framework interaction.
- **Feature tests** cover HTTP routes, middleware, Livewire component state, Eloquent scopes, and the database state after operations.
- **Regression tests** are added when a defect is found so the same failure cannot return silently.

### Manual verification

Manual passes are used where the automated suite is structurally unable to prove the user experience:

- Visual rendering of the stale badge, status badges, and flash messages.
- Real browser session behaviour (session expiry, back-button behaviour).
- The "similar partner" warning appearing and not blocking save.
- The amber date warning rendering inline next to the expiry field.

Manual cases are marked **Manual only** in `test-cases.md` with the reason stated.

---

## 3. Environment

| Item | Version / configuration |
|---|---|
| Framework | Laravel 13.25 |
| Livewire | 4.4 |
| PHP | 8.3 |
| Database (app) | SQLite (`database/database.sqlite`) |
| Database (tests) | In-memory SQLite (`phpunit.xml`) |
| Test runner | PHPUnit via `composer test` |
| Formatter | Laravel Pint (`vendor\bin\pint`) |
| Agent output | `laravel/pao` emits compact JSON when an AI agent is detected; the `result` field is parsed rather than relying on pretty output |

---

## 4. Entry criteria

- M1, M2 and M3 code is merged.
- `composer test` is green at 115 tests.
- `vendor\bin\pint` is clean on the existing codebase.

---

## 5. Exit criteria

- Every business rule in `traceability-matrix.md` maps to at least one executed test case or an explicitly documented gap.
- Every automated test named in the matrix and test-case document exists and is spelled correctly.
- All defects in `defect-log.md` have a severity, root cause, and disposition (`Closed`, `Deferred`, or `Accepted`).
- `composer test` is green at 117 tests.
- `git status` shows no modified application files (`app/`, `database/migrations/`, `database/seeders/`, `resources/views/`, `routes/`).

---

## 6. Roles

| Role | Responsibility |
|---|---|
| Amir (PM) | Approves plans, decides on defects and scope, signs off exit criteria. |
| Claude Code (Lead Architect) | Wrote the M4 plan; defines business rules and traceability expectations. |
| OpenCode Go (Senior Dev) | Implements M4 and M5a: creates QA artifacts, corrects/adds tests, keeps suite green. |
| Intan / Legal (System owner) | Confirmed business thresholds (stale days, expiry semantics) in M5-8; will operate the system after handover. |

---

## 7. Risks and assumptions

- **Assumption:** The in-memory SQLite test environment is representative of the production SQLite target for the behaviours under test. Constructs such as `whereRaw('LOWER(name) like ?', ...)` and JSON path queries on `meta` would need re-verification if the database ever changes to MySQL.
- **Risk:** The similar-partner matcher is deliberately limited (DEF-003). Users may create duplicates that the warning does not surface.
- **Risk:** Concurrent edits are unguarded (DEF-007). The window is small for a single internal team, but last-write-wins is the current behaviour.
