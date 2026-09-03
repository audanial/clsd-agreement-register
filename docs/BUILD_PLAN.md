# CLSD Agreement Register — MVP Build Plan + AI Team Briefing

> **Project:** Internal UniKL "CLSD Agreement Register"
> **Stack:** Laravel 13 · Livewire 4 · Tailwind 4 · Vite · SQLite
> **Repo state at plan time:** 2 commits — schema + seeders + partial models. No auth, no routes, no Livewire components, no real tests.
> **Team (agreed operating model):**
> - **Amir** = Project Manager. Makes every call. Approves plans before any implementation.
> - **Claude Code** = Lead Architect. Reads the codebase, verifies state, proposes architecture, flags decisions. Writes plans, **never implements without approval**.
> - **OpenCode Go** = Senior Dev. Implements approved plans, one milestone at a time, tests green before handing back.

This is a **work-hours project**. Build at the office. QA study evenings and job applications at home stay untouched.

---

## 1. Verified current state (checked 19 Aug 2026 on the home-PC clone)

### ✅ Done
| Area | Notes |
|---|---|
| Schema (10 migrations) | Solid. Business rules documented in migration comments — treat those comments as the design doc. |
| `Campus` / `Country` / `Partner` / `AgreementFile` / `AgreementActivity` models | `#[Fillable]`/`#[Hidden]` attribute config, casts, relations in place. |
| `CampusSeeder` | 16 rows (12 institutes + UIO/ACE/CPS + TBD), idempotent `updateOrInsert`. |
| composer scripts | `composer setup` / `composer dev` / `composer test` wired. |

### ✅ Verified facts (checked on the actual repo, 19 Aug 2026 — do not re-verify, do not question)
- **`laravel/pao` is real and behaves as documented.** `composer.lock` pins `laravel/pao v1.1.4` from `github.com/laravel/pao`; Packagist describes it as *"Agent-optimized output for PHP testing tools"*, authored by Taylor Otwell, and it depends on `laravel/agent-detector` — that dependency is exactly why it detects an AI agent and switches `php artisan test` to compact JSON. Versions before April 2026 may not show it; this project's lockfile is authoritative. **Statement is verified fact, not an assumption.**

### ❌ Not done / gaps (the build work)
| Gap | Detail |
|---|---|
| **Auth** | Nothing. No login, no middleware config, no Breeze/sanctum. Role-based system with zero authentication. |
| **`Agreement` model** | Empty stub — no relations, casts, fillable, or scopes. This is the core model. |
| Routes / Livewire / views | Default `welcome` page only. Zero `⚡` components. |
| `pending` global scope | The "hidden from non-Legal users" rule exists in a comment only. |
| Countries data | `DatabaseSeeder` runs **only** `CampusSeeder` → `countries` table will be **empty**; partner country dropdown would be empty. |
| Test data | `UserFactory` exists but sets **no `role`**; no Agreement/Partner factories. |
| Tests | Stock `ExampleTest` only. |

### 🐛 Two bugs found in review — fix in M1/M2
1. **`User::$fillable` is missing `role`** → `User::create([... 'role' => 'legal'])` silently drops the role and defaults to `viewer`. Silent data bug.
2. **`UserFactory` doesn't define `role`** → every generated user is `viewer`; you'd never see Legal-only behavior in dev/tests.

---

## 2. MVP Milestones (definition of done = colleagues can use it daily without you)

> Order is deliberate: auth before CRUD, because the one business rule that matters (Legal sees `pending`, others don't) can't exist without roles.

### M0 — Boot locally
- [x] Toolchain installed; `composer setup` and `composer test` green.

### M1 — Auth + roles
- [x] Minimal custom auth; `role` + `is_active` on User; factory states; login/logout; role checks; dev admin + legal accounts.

### M2 — Agreement model + access rule
- [x] Relations, casts, global pending scope, archive/expiry scopes, `CountrySeeder`.

### M3 — Vertical slice: Register actually usable
- [x] Livewire 4 **single-file** components (`php artisan make:livewire ...` → `⚡`-prefixed file):
  - `AgreementsIndex` — list + search + filters + pagination; role-aware.
  - `AgreementForm` — create + edit.
  - `AgreementShow` — detail view + status change + activity feed.
- [x] Status change flow writes `AgreementActivity` rows with `meta` from→to.
- [x] **Stale-status flag (Decision M5-8):** badge when `project_status_updated_at` is 90+ days old; boundary crossed at the start of the day.
- [x] Validation rules, failures shown inline.
- [x] Boot to a page where a `legal` user can register an agreement end-to-end.

### M4 — Tests that protect the business rule (QA portfolio evidence)
- [x] Role-visibility tests; create/update/status-change feature tests; factories; validation tests.
- [x] `composer test` green at 115 tests.

### M5 — Handover (in progress)
- [x] README rewritten: setup, roles, user management, tests, out-of-scope items.
- [x] `docs/HANDOVER.md` skeleton created for Intan; Amir writes the prose.
- [x] M4/M5a QA artifact repair: defect log, traceability matrix, test cases; boundary tests E-5/E-6 added.
- [ ] M5-2 admin-only user-creation web form — still open; documented as future work in `AGENTS.md` and `README.md`.
- [ ] Excel import of 2022–2024 historical data — **only if explicitly requested**.

### M6 — Post-deployment usability fixes (in progress)
Split into three handoffs (schema-first order):
- [x] M6b — PIC becomes a plain `pic_name` string (handoff-m6b-pic.md).
- [ ] M6c — Merge `agreement_date` + `effective_date` into "Date Signed" and drop `effective_date` (handoff-m6c-date-merge.md). Local code done; production migration gated on confirmed backup.
- [ ] M6a — UI-only fixes: list columns, duration format, partner-mode `wire:model.live`, date-format consistency (handoff-m6a-ui.md).

### 🚫 Explicitly OUT of MVP scope (phase-later, do not build now)
File upload UI · dashboard analytics · archive/restore UX · notifications/reminders · CSV export · Excel import · approval workflow engine · spatie/laravel-permission.

---

## 3. Field-level spec for the agreement create/edit form

| Field | Type | Required | Rules / notes |
|---|---|---|---|
| `title` | text | ✅ | Max 255 |
| `type` | select | ✅ | One of `LOI / NDA / MOA / MOU / SEA / MOC / ADDENDUM` |
| `partner_id` | select (+ quick-create) | ✅ | Existing partner or inline "add new" (name required, short_name/country optional) |
| `campus_id` | select | ✅ | Active campuses only; TBD shows as "Not Assigned" |
| `pic_user_id` | select | ❌ | Active UniKL staff; nullable (historical rows) |
| `sector` | select | ❌ | `academic` / `industri` |
| `agreement_date` | date | ❌ | Year is derived from this — no separate year field |
| `effective_date` | date | ❌ | Nullable for historical imports |
| `expiry_date` | date | ❌ | **Null = indefinite / until completion** — never treat as missing |
| `document_status` | select | ✅ | Default `pending`; only admin/legal may move past it; viewer read-only |
| `project_status` | select | ✅ | Default `not_started`; updating it also stamps `project_status_updated_at` (powers the "stale status" flag later) |
| `scope` / `notes` | textarea | ❌ | Free text |
| Files / activities | — | — | Schema ready; **UI deferred** — no upload UI in MVP |

**Validation caution (Open Decision 3):** `expiry_date >= effective_date` is a tempting rule, but historical rows may violate it. Suggest soft warning over hard failure until Ms. Haniza confirms the data is clean.

---

## 4. Open decisions — status as of M5a (27 Aug 2026)

All open decisions from the original plan are now closed or superseded.

1. **Auth approach — ✅ DECIDED: (b) minimal custom auth.** Login/logout via Laravel `auth`; `admin` creates users via `php artisan user:create`.
2. **Who may create agreements? — ✅ DECIDED:** `admin` and `legal` create/edit; `viewer` is read-only.
3. **Date validation strictness — ✅ DECIDED (Decision D3):** expiry earlier than effective date produces a soft warning, never blocks save.
4. **PIC project-status edits / stale-status flag — ✅ DECIDED (Decision M5-8):** Legal updates project status on behalf; stale badge appears at 90 days, with the boundary crossed at the start of the day.
5. **File uploads in MVP — ✅ DECIDED:** deferred; `agreement_files` table exists but has no UI.

**Convention (agreed earlier with Kimi):** do NOT add `HasFactory` to a model until its factory actually exists. `Agreement`/`Partner` get `HasFactory` only in M4 alongside their factories — not before.

---

## 5. Claude Code prompt — LEAD ARCHITECT briefing (copy the whole block below)

Run Claude Code from the repo root on the **work PC** (or from this clone after `composer setup`). Paste this block:

```text
Role: You are the LEAD ARCHITECT on the CLSD Agreement Register, an internal
UniKL Laravel app. You propose architecture and verify the codebase. You do NOT
implement code. Amir is the Project Manager and makes every decision — anything
you are unsure about becomes an "Open Decision" with options and a recommendation.

Context: Read AGENTS.md and docs/BUILD_PLAN.md in this repo. They contain the
verified current state, the MVP milestones, and the field-level spec.

Task:
1. Verify the actual state of the repo yourself — do not trust the plan file
   blindly. Read the migrations, every model, routes/web.php, bootstrap/app.php,
   composer.json, seeders, factories, and tests. Report anything in the plan
   that is already stale or wrong.
2. Produce a concrete implementation plan for milestones M1 and M2 ONLY
   (auth+roles, then the Agreement model + pending global scope). Do not plan
   M3+ in detail until those are approved.
3. For M1, compare the auth options in Open Decision 1 with real tradeoffs:
   effort, files added, what must be stripped, security for an internal role-
   based app. Give a recommendation, but keep it as a proposal.
4. For M2, specify: exact model methods and scopes, the global-scope
   implementation (where it lives so Eloquent applies it app-wide), and the
   cast/date handling. The migrations are the design doc — you may NOT change
   schema or business rules unless Amir approves.
5. List the exact files you would create or modify for M1+M2 (paths, one per
   line), and the exact tests you would write, including the role-visibility
   test (viewer must never see document_status=pending; legal must).
6. Open Decisions 2, 3 and 5 from the plan are still open — answer each with a
   one-line recommendation. Open Decisions 1 and 4 are ALREADY DECIDED in the
   plan file: plan around minimal custom auth (no Breeze, no registration) and
   the stale-status flag in the MVP. Do not reopen them.

Required changes (when Amir approves this plan and hands it to the senior dev):
- Follow the repo conventions: PHP 8 attribute config (#[Fillable], #[Hidden]),
  Livewire 4 single-file components only (⚡-prefixed files), migrations keep
  their business-rule comments, enums stay plain strings for now.
- If you run `php artisan test` or `composer test`, laravel/pao outputs compact
  JSON when it detects an AI agent — parse the JSON result field; do not re-run
  expecting pretty output. (This is already verified — see the "Verified facts"
  section of the plan file. Do not spend effort re-verifying it.)

Do not change:
- Any migration file or seeded data (campus_id stays NOT NULL; TBD campus stays;
  expiry_date null stays meaningful; archive-not-delete stays).
- npm behavior (.npmrc sets ignore-scripts=true) or vite.config.js (it fetches
  the Instrument Sans font at build time — needs network).
- The three-role enum or anything that would require spatie/laravel-permission.

Acceptance checks (plan deliverable only — no code):
- Your plan states the verified state explicitly and flags any drift from the
  plan file.
- M1+M2 plan lists exact files, exact methods/scopes, and exact tests.
- Every open decision has options + a recommendation.
- No migration/schema changes proposed without an explicit "needs Amir approval"
  marker.

After editing:
- Summarize your verified findings vs the plan file.
- Summarize your proposed M1+M2 file list and the decision you recommend.
- Restate the open decisions for Amir in a numbered list.
- Save the plan to docs/architecture-plan.md in this repo.
```

---

## 6. OpenCode Go prompt — SENIOR DEV handoff (use AFTER Amir approves the plan)

Run from repo root on the work PC. Adjust milestone number as you go:

```text
Role: You are the SENIOR DEV on the CLSD Agreement Register (Laravel 13 +
Livewire 4, SQLite). Amir is the PM: implement exactly what the approved plan
says — do not redesign, do not add scope.

Context: Read AGENTS.md, docs/BUILD_PLAN.md (the MVP milestones), and
docs/architecture-plan.md (the approved architecture). The architecture plan is
the contract. If a decision in it is missing, ask Amir — do not guess.

Task: Implement milestone M1 (auth + roles) end-to-end:
1. Add `role` to the User model's #[Fillable] list and fix the UserFactory to
   support admin/legal/viewer states (these are known bugs).
2. Implement the approved auth approach exactly as the architecture plan
   specifies.
3. Add the role checks and seed one admin + one legal dev account.
4. Write the tests the plan lists for M1 (including the role-visibility test
   if the plan assigns it to M1).
5. Run `composer test` — laravel/pao prints JSON when it detects an AI agent;
   parse the "result" field. All tests must pass.

Do not change:
- Any migration or seeder (unless the approved plan explicitly says so).
- Schema constraints, the TBD campus behavior, or the document_status meaning.
- Anything outside the M1 file/test list from the approved plan.
- Do not run npm scripts (ignore-scripts=true).

Acceptance checks:
- `composer test` green (parse the JSON output; report result field).
- `vendor\bin\pint` run on all PHP you touched.
- Login works for the seeded admin and legal accounts; viewer cannot reach
  admin pages.

After editing:
- List every file created/modified.
- Report exact test names and results.
- Tell Amir what to manually check in the browser.
- Do not commit unless Amir says to.
```

---

## 7. How to use this file (workflow)

1. **Office, M1 kickoff:** paste the §5 block into Claude Code → it verifies + plans M1/M2 → saves `docs/architecture-plan.md`.
2. **Amir** reads the plan, answers the open decisions (or overrides recommendations).
3. **Send the §6 block (edited to the approved milestone) to OpenCode Go** → it implements → tests green → handback.
4. **Amir smoke-tests in the browser**, then prompts Claude for a code review of the diff before committing.
5. Repeat per milestone. **M4 tests are portfolio evidence** — keep them in the repo, they travel to the QA job hunt.