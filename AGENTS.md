# AGENTS.md

Internal UniKL "CLSD Agreement Register" — tracks legal agreements (LOI/NDA/MOA/MOU/SEA/MOC/ADDENDUM) with partners, per campus. Laravel 13 + Livewire 4 + Tailwind CSS 4 + Vite, SQLite at `database/database.sqlite`. Early stage: schema and seeders exist, but routes/controllers/Livewire components are not built yet (`routes/web.php` is still the default welcome page; README is the stock Laravel one).

## Commands

- `composer setup` — first-time setup: composer install, `.env`, `key:generate`, migrate, `npm install --ignore-scripts`, `vite build`.
- `composer dev` — one TUI running `php artisan serve` + `queue:listen` + `npm run dev` together (queue matters: `QUEUE_CONNECTION=database`).
- `composer test` — clears config cache, then `php artisan test`. Tests use in-memory SQLite (`phpunit.xml`); no external services needed.
- `vendor\bin\pint` — formatter (Laravel preset, no `pint.json`). Run it on PHP changes.
- No CI workflows, no JS tests, no type checker configured.

## Gotchas

- `laravel/pao` is installed: when it detects an AI agent, `php artisan test` prints compact JSON (`{"tool":"phpunit","result":"passed",...}`) instead of the usual pretty output. Parse the JSON; don't re-run expecting normal output.
- Livewire 4, not 3: `php artisan make:livewire Foo` creates a **single-file component** at `resources/views/components/⚡foo.blade.php` (anonymous class + Blade in one file, with a literal `⚡` emoji prefix in the filename). Use `--mfc` or `--class` only if the class+view split is explicitly wanted. Do not scaffold `app/Livewire/*.php` by habit.
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
- User roles are a plain enum `admin|legal|viewer` on `users.role`. The migration explicitly says NOT to add spatie/laravel-permission unless per-action granularity becomes necessary.
- `agreements.expiry_date = null` means indefinite/until-completion, not missing data. Most date columns are nullable because historical imports lack them.
- `php artisan db:seed` runs only `CampusSeeder` (idempotent `updateOrInsert`): 12 UniKL institutes + central units + `TBD`. `countries.is_domestic` drives the Dalam/Luar Negara display.

## Decisions & rationale (why, not just what)

- Document status is 3 states (pending/awaiting_partner/signed) + expired,
  not 4 with MIA — senior exec confirmed MIA isn't tracked. UniKL always
  signs before an agreement leaves Legal, so the only "unsigned" state that
  matters is the partner's signature.
- `pending` = internal vetting (board/CEO/VC approval + Ms. Haniza's
  signature) — hidden from non-Legal users via a global scope. Not yet
  implemented; still needs building.
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

## Future considerations (not yet scoped/planned)

- Non-technical Legal staff cannot use `php artisan user:create` to add new
  PICs (project owners). Before full handover (M5), need either: (a) a
  simple admin-only "create user" web form, or (b) written step-by-step
  instructions with screenshots for running the command via whatever server
  access method Legal will have. Raised 21 Aug 2026.

- UI/UX polish and visual design pass — current M3 UI is functional but
  plain (default browser styling, minimal Tailwind). Worth a design pass
  once real users have used the MVP and given feedback on what's confusing
  or missing, rather than guessing now. Raised 24 Aug 2026.