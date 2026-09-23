# AGENTS.md

Internal UniKL "CLSD Agreement Register" — tracks legal agreements (LOI/NDA/MOA/MOU/SEA/ADDENDUM) with partners, per campus, and includes the LP1 Legal Submission Portal. Laravel 13 + Livewire 4 + Tailwind CSS 4 + Vite, SQLite at `database/database.sqlite`. M1–M9, LP0, and LP1 are implemented. DEF-013 is implemented; release evidence belongs in `docs/handoff-*.md` and `docs/qa/`.

## Commands

- `composer setup` — first-time setup: composer install, `.env`, `key:generate`, migrate, `npm install --ignore-scripts`, `vite build`.
- `composer dev` — one TUI running `php artisan serve` + `queue:listen` + `npm run dev` together (queue matters: `QUEUE_CONNECTION=database`).
- `composer test` — clears config cache, then `php artisan test`. Tests use in-memory SQLite (`phpunit.xml`); no external services needed.
- `vendor\bin\pint` — formatter (Laravel preset, no `pint.json`). Run it on PHP changes.
- No CI workflows, no JS tests, no type checker configured.

## Gotchas

- `laravel/pao` is installed: when it detects an AI agent, `php artisan test` prints compact JSON (`{"tool":"phpunit","result":"passed",...}`) instead of the usual pretty output. Parse the JSON; don't re-run expecting normal output.
- Livewire 4, not 3: `php artisan make:livewire Foo` creates a **single-file component** (anonymous class + Blade in one file). Use `--mfc` or `--class` only if the class+view split is explicitly wanted. Do not scaffold `app/Livewire/*.php` by habit.
- **Livewire components live in `resources/views/livewire/`, with no `⚡` emoji prefix** (LP0 / S20 decision, 10 Sep 2026). `config/livewire.php` is published with `make_command.emoji => false`, so `make:livewire` produces the right filename without a flag. `resources/views/components/` is for **anonymous Blade components only** (`<x-date>`, `<x-badges.*>`) — that directory is registered as both a Livewire location and Laravel's anonymous-component path, so keeping the two populations separate is what makes it obvious which kind a file is. The emoji was never required: `Finder::normalizeName()` strips it, and `Finder::resolveSingleFileComponentPath()` falls back to plain filenames. `tests/Feature/Livewire/ComponentResolutionTest.php` enforces this.
- `.npmrc` sets `ignore-scripts=true` — npm lifecycle scripts never run.
- `vite.config.js` downloads the "Instrument Sans" font from Bunny Fonts at dev/build time — needs network access.
- Windows + PowerShell environment.

## Conventions

- Models use PHP 8 attribute config — `#[Fillable([...])]`, `#[Hidden([...])]` on the class (see `app/Models/User.php`) — not `$fillable`/`$hidden` properties.
- Migration FKs: `restrictOnDelete()` for required parents, `nullOnDelete()` for optional ones, `cascadeOnDelete()` only for owned children (`agreement_files`, `agreement_activities`).
- The `2026_*` migrations carry comments explaining business rules — treat them as the design doc and keep writing migrations in that style.

## Domain rules baked into the schema — don't undo

- `agreements.campus_id` is NOT nullable on purpose. The seeded `TBD` campus row is the catch-all for unknown ownership (see `CampusSeeder`); never make `campus_id` nullable.
- `document_status = pending` means "still in Legal vetting" and is intended to be hidden from non-Legal users via a global scope (per migration comment).
- User roles are `admin|legal|viewer|requester` on `users.role`. Since LP0 the column is a plain
  `string`, not an enum — on SQLite an enum is a CHECK constraint that cannot be altered without
  rebuilding the whole table, so this was made the last such rebuild. Valid values are enforced by
  the `in:` rule in the user-manager component and by `EnsureUserHasRole`. Still NOT a case for
  spatie/laravel-permission unless per-action granularity becomes necessary.
- `requester` = non-Legal staff who submit agreement requests through the Legal Submission Portal.
  They also use the Agreement Register as a read-only reference, like Viewer users. Register list
  and detail routes are gated `role:admin,legal,viewer,requester`; create/edit and every mutation
  remain Admin/Legal only. Requesters still see only their own submissions. This correction was
  approved 17 Sep 2026 in LP1 Amendment 2.
- `agreements.expiry_date = null` means indefinite/until-completion, not missing data. Most date columns are nullable because historical imports lack them.
- `php artisan db:seed` runs only `CampusSeeder` (idempotent `updateOrInsert`): 12 UniKL institutes + central units + `TBD`. `countries.is_domestic` drives the Dalam/Luar Negara display.

## Decisions & rationale (why, not just what)

- Document status is 3 states (pending/awaiting_partner/signed) + expired,
  not 4 with MIA — senior exec confirmed MIA isn't tracked. UniKL always
  signs before an agreement leaves Legal, so the only "unsigned" state that
  matters is the partner's signature.
- `pending` = internal vetting (board/CEO/VC approval + Legal signature) —
  hidden from non-Legal users via a global scope. Implemented in M3.
- Project status (not_started/ongoing/stalled/completed) is maintained by
  the PIC/project owner, not Legal — Legal is a middleman answering status
  queries from MARA Corp, not chasing partners themselves.
- Campus is a single belongsTo, not many-to-many — confirmed every
  agreement has exactly one owning campus. TBD campus covers unassigned
  historical rows.
- Year = "the year the agreement was made," derived from agreement_date,
  not typed separately.
- Archive, never hard-delete — legal records get asked about    years later
  (MARA Corp reviving old agreements, status lookups on expired ones).
- Workflow (draft → board approval → signing → release to partner) is
  tracked via nullable date columns on Agreement, not a separate
  workflow-engine table set — deliberately kept simple since the process
  isn't fully finalized yet. Revisit only if branching/non-linear flows
  turn out to be needed.
- Enum columns (document_status, project_status, type, sector) are plain
  strings for now, not PHP enum casts — deferred as a low-risk refactor
  once CRUD is working end-to-end.

## Design philosophy (guides all future decisions, not just M1-M5)

- **Standing visual reference:** `docs/design/reference/CLSD Legal Management System.html` is the
  Claude-generated visual and UX reference for the upgraded management system. Use its
  institutional palette, information hierarchy, cards, tables, status treatments,
  confidentiality cues, and role-aware navigation as the direction for new UI work. It is not a
  feature specification: approved architecture plans, milestone handoffs, and security/domain
  rules take precedence, and future features depicted in the prototype must not be pulled into an
  earlier milestone. Never serve the prototype or import its bundled scripts/assets into the app.

- Optimize for convenience and usability by non-technical Legal staff over
  visual polish or impressive features. The system must remain operable
  by Legal (specifically Intan, the trusted successor as of 26 Aug 2026)
  after Amir is no longer involved.
- Every feature decision should ask: "can Intan do this without technical
  help?" If the answer requires a terminal, a code editor, or developer
  knowledge, it's a gap, not an acceptable tradeoff — see M5-2 (user
  management form) as the first concrete example of this principle in
  practice.
- Ms. Haniza departed Legal 26 Aug 2026; Intan (Legal Executive) is the
  confirmed handover contact and system owner going forward.

## LP0 — Legal Submission Portal foundation (10 Sep 2026) — implemented

Groundwork only. No portal feature shipped: no `submissions` table, no uploads, no portal pages.
The LP0 plan document that was expected to contain the LP1–LP6 roadmap was never committed to this repository. Recover or reconstruct and approve that roadmap before planning LP2.

- **Livewire role middleware is now persistent.** `AppServiceProvider` registers
  `EnsureUserHasRole` via `Livewire::addPersistentMiddleware()`. Livewire only re-runs an
  allow-list of middleware on `/livewire/update`, and our `role:` alias was not on it — so a route
  guarded only by middleware protected the initial page load and nothing else. **Verified
  exploitable before the fix:** with the component's own check removed, a `legal`, `viewer` or
  `requester` account could each create an **admin** user through `user-manager`. Persistent
  middleware is defence in depth, NOT a licence to skip in-method checks.
- **Inactive accounts are rejected by the `web` middleware group.** DEF-013 adds
  `EnsureUserIsActive` to that group so normal pages and Livewire updates both
  check account state. Livewire's update route already uses `web` directly
  (`HandleRequests.php`), so this middleware does not need
  `Livewire::addPersistentMiddleware()`. Admin deactivation also rotates the
  remember token and removes database sessions; the middleware catches any
  inactive session that remains.
- **Every Livewire action authorises for itself.** `user-manager`'s `create()` and `toggle()` now
  call `abort_unless(...->canManageUsers(), 403)`. Blade `@if` blocks are presentation, never the
  control. Follow this in every new component.
- **Eloquent global scopes do not re-apply to Livewire-restored models.** Livewire restores public
  Eloquent model properties through `newQueryForRestoration()`, which runs an **unscoped** query —
  so during `/livewire/update` a global scope is NOT automatically applied again. A component whose
  public model property is protected by a global scope must re-resolve that model under the current
  user's scopes during hydration (and 404 when it is no longer visible). `agreement-show` does this
  in `boot()` and is the reference implementation; follow it in any future component holding a
  scoped model.
- **The Agreement Register is role-gated** (`role:admin,legal,viewer,requester` for list/detail;
  `role:admin,legal` for create/edit), nested inside the `auth` group so guests still redirect to
  `/login` rather than getting a 403. Requesting Staff and Viewer remain read-only and the pending
  global scope hides records still in internal Legal vetting from both roles.
- **`documents` disk** added for P&C files: private, `serve => false`, `throw => true`, driver from
  `DOCUMENTS_DISK_DRIVER`. `serve => false` is load-bearing — `serve => true` registers
  `/storage/{path}` routes that hand files to anyone with a signed URL, with no per-user check and
  no audit entry. Never call `Storage::url()`/`temporaryUrl()` on it; downloads go through a
  controller that authorises, logs, then streams.
- **`<x-datetime>`** renders Malaysian time for audit timestamps. `config/app.php` timezone stays
  `UTC` deliberately — changing it would re-interpret every timestamp already in production and
  disturb the M5-8 boundary tests. Convert at display time only.

## Future considerations (not yet scoped/planned)

- PIC self-service view: a future idea where PICs (external stakeholders) could log in and see only
  their own agreements. Explicitly not built in M6-4; requires a separate decision and scope. Logged 2 Sep 2026.

- Archiving: `archived_at` and `archive_reason` are now genuinely populated for the first time in M8.
  `archive_reason` currently has two values: `expired` (automatic) and `terminated` (manual). A future
  `termination_basis` sub-field (breach / mutual agreement / termination for convenience / other) is
  deferred until Legal confirms this granularity is needed. Logged 7 Sep 2026.

- UI/UX polish and visual design pass — current M3 UI is functional but
  plain (default browser styling, minimal Tailwind). Worth a design pass
  once real users have used the MVP and given feedback on what's confusing
  or missing, rather than guessing now. Raised 24 Aug 2026.

## M6 decisions (2 Sep 2026) — implemented

All 11 M6 decisions finalized with Amir. Key changes from the original
architect recommendations:
- PIC (Decision 4): Option (c) IMPLEMENTED — PIC is a plain name string,
  NOT a User record. No login, no account, ever, for now. Deliberate
  scope boundary — PIC self-service login is a separate, unplanned
  future idea (see Future considerations below).
- Date merge (Decision 5): IMPLEMENTED as a real migration — the
  `effective_date` column has been dropped. Verified directly: all 10 live
  agreements on Laravel Cloud had matching `agreement_date`/`effective_date`
  values before the migration ran, and the column was removed only after a
  confirmed production backup (see handoff-m6c-date-merge.md).
- Timezone (Decision 7): fix to Asia/Kuala_Lumpur — outside the
  original six M6 items, but approved as a bonus fix.
- Other decisions (1, 2, 3, 5b, 6, 6a, 6b): all approved as originally
  recommended by Claude Code's first M6 plan draft.

Full context and reasoning for each decision was discussed in detail
in chat before this note was written. If docs/architecture-plan-m6.md
does not yet exist, the next step is: send Claude Code the full
decision list (see this note's summary above) and ask it to produce
docs/architecture-plan-m6.md reflecting these decisions, including a
proper schema-change specification for the revised PIC approach
(likely a new pic_name column replacing pic_user_id's role, since PIC
is no longer tied to the users table).
