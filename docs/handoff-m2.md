# OpenCode Go handoff — M2: Agreement model + pending global scope

> **Milestone:** M2 · **Implements:** Section 3 of `docs/architecture-plan.md`
> **Precondition:** M1 must be merged. The global scope calls `User::canSeePending()`, which
> ships in M1 — without it, every `Agreement` query fatals.
> **Paste the block below into OpenCode Go from the repo root.**

```text
Role: You are the SENIOR DEV on the CLSD Agreement Register (Laravel 13 +
Livewire 4, SQLite). Amir is the PM: implement exactly what the approved plan
says — do not redesign, do not add scope.

Context: Read AGENTS.md, docs/BUILD_PLAN.md (the MVP milestones), and
docs/architecture-plan.md (the APPROVED architecture, dated 20 Aug 2026). The
architecture plan is the contract. If a decision in it is missing, ask Amir —
do not guess.

Precondition: M1 (auth + roles) must already be merged. This milestone depends
on User::canSeePending() existing. If it does not, stop and tell Amir.

Task: Implement milestone M2 (Agreement model + the pending global scope)
end-to-end, exactly per Section 3 of docs/architecture-plan.md:

1. Section 3.1 — rewrite app/Models/Agreement.php (currently an empty stub):
   - #[Fillable] with the 19 columns listed. archived_at is DELIBERATELY
     EXCLUDED — archiving is an explicit action in M3, not a form field.
   - casts(): the 8 date columns => 'date', project_status_updated_at and
     archived_at => 'datetime'. Enum columns stay PLAIN STRINGS — no PHP enum
     casts, that is a locked convention.
   - use SoftDeletes (the table has deleted_at; the stub never had the trait).
   - The 5 relations: partner(), campus(), pic() (User via pic_user_id),
     files(), activities(). Leave activities() unordered.
   - The 6 query scopes as #[Scope] attribute methods (Laravel 13 idiom,
     matching the repo's #[Fillable]/#[Hidden] style) — NOT legacy scopeFoo().
   - const STALE_AFTER_DAYS = 90, the year() accessor, and the 4 boolean
     helpers (isIndefinite, isExpired, isArchived, hasStaleProjectStatus).

2. Section 3.2 — create app/Models/Scopes/HidePendingFromNonLegalScope.php and
   attach it with #[ScopedBy(...)] on the Agreement class. Implement it exactly
   as written in the plan. Four things are load-bearing:
   - #[ScopedBy] on the model — never a booted() override, never a controller
     helper, never a base query. Attaching it to the model is the whole point:
     it then applies to find(), paginate(), relations, eager loads and
     withCount, and no future code path can forget it.
   - Use $model->qualifyColumn('document_status'), not a bare column name, or
     it throws "ambiguous column" the first time M3 joins partners/campuses.
   - Return early when Auth::user() is null (console, seeders, queued jobs) and
     when the user passes canSeePending(). Do not re-derive the role rule —
     call canSeePending() and nothing else.
   - A viewer fetching a pending agreement by ID gets a 404, not a 403. That is
     correct and deliberate: a 403 confirms the record exists.

3. Section 3.3 — create database/seeders/CountrySeeder.php: idempotent
   updateOrInsert keyed on `name`, matching CampusSeeder's exact style, the 25
   countries listed, ISO 3166-1 alpha-2 in iso_code, Malaysia the ONLY row with
   is_domestic = true. Register it in DatabaseSeeder::run() alongside
   CampusSeeder.

4. Section 3.4 — create AgreementFactory and PartnerFactory and add
   `use HasFactory;` to Agreement and Partner. Factory defaults must not fight
   the schema: document_status defaults to 'pending', pic_user_id is NULL by
   default, expiry_date is NULL by default (indefinite). Campus::factory() does
   NOT exist — resolve a real campus (Campus::firstWhere('code','TBD') from
   CampusSeeder, or firstOrCreate). Define the 7 states listed in the plan.

5. Section 3.5 — write every test listed. PendingVisibilityTest is the whole
   reason this milestone exists; all 8 of its cases are required, including
   test_unauthenticated_context_is_not_scoped and
   test_without_global_scope_reveals_pending_rows.

6. Run `composer test` — laravel/pao prints JSON when it detects an AI agent;
   parse the "result" field. All tests must pass.

Approved decisions relevant to M2 (do not reopen):
- D6: expiry_date is authoritative for "expired". The application NEVER writes
  document_status = 'expired' — that enum value stays unused (legacy imports
  only). expired() is whereNotNull('expiry_date')->whereDate(... '<' today()).
- D7: notArchived() is a LOCAL scope applied explicitly by list queries. Do NOT
  make it a second global scope. Exactly ONE global scope exists on Agreement:
  HidePendingFromNonLegalScope.
- D8: AgreementFactory and PartnerFactory move forward from M4 into M2. This
  overrides the "no factories before M4" convention — Amir approved it.

Two schema facts that are easy to get wrong:
- expiry_date IS NULL means indefinite / until completion, NOT missing data.
  Both expiringSoon() and expired() MUST guard with whereNotNull('expiry_date')
  or indefinite agreements will show up as expiring/expired.
- document_status is NOT NULL with a 'pending' default, so `!=` in the scope is
  safe — there is no NULL-comparison trap.

Do not change:
- Any migration. Not one line, not a comment. The migrations are the design doc.
- CampusSeeder or any seeded campus data. campus_id stays NOT NULL, TBD stays.
- The three-role enum. Do not add spatie/laravel-permission.
- app/Models/User.php or anything from M1 — that milestone is done.
- Anything outside the M2 file list in Section 3.4 of the architecture plan.
- Do not run npm scripts (ignore-scripts=true) or touch vite.config.js.

Do not build (this is M3, explicitly out of scope for M2):
- archive() / unarchive() methods, activity logging, status-transition rules.
- Any Livewire component, controller, route or view. M2 is model layer only.

Two rules that protect the business rule — treat these as permanent:
- NEVER use DB::table('agreements') anywhere in the app. Raw query builder
  bypasses Eloquent and takes the pending scope with it.
- withoutGlobalScope(HidePendingFromNonLegalScope::class) must appear in ZERO
  application files. It appears only in
  test_without_global_scope_reveals_pending_rows.

Acceptance checks:
- `composer test` green (parse the JSON output; report the result field).
- `vendor\bin\pint` run on all PHP you touched.
- `php artisan migrate:fresh --seed` → countries populated, Malaysia the only
  domestic row.
- The tinker spot check from Section 5 of the architecture plan: logging in as
  a viewer vs a legal user changes Agreement::count().

After editing:
- List every file created/modified.
- Report exact test names and results.
- Tell Amir what to manually check (tinker, not the browser — M2 has no UI).
- Do not commit unless Amir says to.
```

## Notes for Amir

- **M2 has no browser-visible output.** The M1 prompt ended with "what to manually check in the
  browser"; M2 is model-layer only, so the manual check is the tinker two-liner from Section 5
  of the architecture plan.
- **The `AgreementFactory` → campus dependency is the likeliest stumble.** `Campus::factory()`
  does not exist, so any test using `Agreement::factory()` needs `CampusSeeder` to have run (or
  a `firstOrCreate` fallback inside the factory). It is called out in the prompt, but it is the
  one thing most likely to produce a confusing FK failure on first run.
