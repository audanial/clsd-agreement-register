# M6 — Post-deployment usability fixes (architecture plan, FINAL)

> **Status:** Final. Supersedes the reconstructed first draft. All decisions
> below are Amir-approved as of 2 Sep 2026. This revision exists specifically
> to fully specify M6-4 (PIC, Option c) and M6-5 (Date Signed merge, Option b)
> as real schema changes, and to define the handoff split.
> **Milestone:** M6 — Post-deployment UX corrections, one defect, two schema changes
> **Role:** Lead Architect (plan only, no implementation)
> **Original draft:** 2 Sep 2026 · **This revision:** 2 Sep 2026

---

## Context

M1–M5b are complete and deployed to Laravel Cloud. Amir populated the live
system with real historical agreements by hand. During that exercise Amir and
Intan (Legal Executive, confirmed system owner) surfaced six usability
problems and one genuine defect. This is the first round of feedback from the
people who actually operate the register — exactly the input the AGENTS.md
design philosophy says should drive decisions ("can Intan do this without
technical help?").

M6 is mostly a UI/UX milestone, but two of its seven items (M6-4, M6-5) are
now confirmed schema changes against a live production database with real
data. It does not touch the pending global scope, role-based access control,
or activity logging.

---

## 1. Verified current state (checked 2 Sep 2026 against the working tree)

### M1–M5b confirmed complete

| Claim | Verified |
|---|---|
| composer test green | ✅ 125 tests, 323 assertions — matches the M5b commit message exactly |
| Laravel / Livewire versions | ✅ laravel/framework v13.25.0, livewire/livewire v4.4.0 |
| Livewire 4 single-file components | ✅ 4 ⚡-prefixed files in resources/views/components/, no app/Livewire/ directory |
| Agreement model | ✅ #[Fillable] attribute config, #[ScopedBy(HidePendingFromNonLegalScope::class)], casts, 6 #[Scope] methods, year accessor |
| Routes | ✅ Route::livewire(...) for index / create / edit / show / users |
| QA artifacts | ✅ test-cases.md (TC-001...TC-063), traceability-matrix.md (BR-01...BR-26), defect-log.md (DEF-001...DEF-010) |
| M5b user management | ✅ ⚡user-manager.blade.php exists, admin-gated, 8 tests in UserManagementTest |

### Findings that shape this plan

**F1 — The app never renders MM/DD/YYYY anywhere.** Every date render is
already `->format('d M Y')` ("18 Mar 2022"). The MM/DD/YYYY Intan saw came
from native `<input type="date">` widgets, whose displayed format is chosen
by browser/OS locale and cannot be overridden by web code. The underlying
HTML value is always `yyyy-mm-dd`. This reframes M6-6 (see Section 7).

**F2 — M6-3's root cause is confirmed, not guessed.** See Section 4.

**F3 — Search does not cover scope.** `⚡agreements-index.blade.php:46-52`
searches `title` and `partner.name` only. Truncating the Scope column in
M6-1 cannot break search-by-scope, because no such behavior exists to break.

**F4 — RESOLVED.** The 10 live Laravel Cloud agreements were unavailable
locally at original-draft time (local SQLite has 0 agreement rows). Amir has
since manually verified all 10 live rows directly on Laravel Cloud: zero
mismatches between `agreement_date` and `effective_date`, and all durations
are whole years. This resolves both M6-2's rounding question and M6-5's
data-safety question.

**F5 — An unused `signed_date` column already exists.**
`create_agreements_table` defines `signed_date`, and it is in the
`#[Fillable]` list, but no form writes it and no view reads it. Naming the
merged M6-5 field "Date Signed" collides with it in name only — see
Decision 5b.

**F6 — The users migration already anticipates non-logging-in PICs.**
`2026_01_01_000007_add_role_to_users_table.php` carries the comment: "PICs
are UniKL staff outside Legal; they appear in the PIC dropdown but don't
necessarily log in on day one." This finding originally supported linking
PIC to a placeholder `User` row (Option b). Amir's final decision (Option c
— plain name, no `User` record at all) supersedes that reasoning: PIC will
not use the `users` table in any form.

**F7 — `users.email` is `unique() NOT NULL`, `users.password` is `NOT NULL`.**
No password-reset route exists. Not directly relevant now that PIC is
staying off the `users` table, but confirms Option (b)'s placeholder-email
approach would have carried real friction (synthesized emails, no reset
path) that Option (c) avoids entirely.

**F8 — `config/app.php` sets `'timezone' => 'UTC'`. Malaysia is UTC+8.**
Date-only columns are unaffected, but `diffForHumans()` on `created_at` and
`project_status_updated_at` can read up to 8 hours off for a Malaysian user.

---

## 2. M6-1 — List view column changes

**Approved as originally drafted. No changes in this revision.**

New column order: Title, Partner, Duration, Scope, Status, Project, PIC, Campus.

Changes from today's header (Title, Type, Partner, Campus, Status, Project, Expiry, ⌀):
- Type column removed — the title conventionally states the type ("LOI Awan Byte...").
- Expiry column replaced by Duration (M6-2), which folds the range in.
- Scope added as a truncated excerpt.
- PIC added — renders `pic_name` (see M6-4) or "—" if unset.
- Campus moves to the end.

**Decisions baked in:**
- Truncation length: 80 characters, via `Str::limit($agreement->scope, 80)`.
- The `<td>` also gets `title="{{ $agreement->scope }}"` so hovering shows
  the full text without leaving the list. Full text remains on the detail page.
- Null scope renders "—".
- The Type filter in the filter bar stays. Only the column is removed.

**Easy-to-miss detail:** The empty-state row is `colspan="8"` (line 209). The
new table has 9 columns. It must become `colspan="9"`.

**Files:** `resources/views/components/⚡agreements-index.blade.php`

**Tests (AgreementsIndexTest.php):**
- test_type_column_is_not_rendered_in_the_list
- test_columns_render_in_the_agreed_order
- test_scope_is_truncated_in_the_list
- test_full_scope_remains_available_on_the_detail_page
- test_null_scope_renders_as_a_dash
- test_pic_column_shows_the_pic_name_and_a_dash_when_unset
- test_search_still_matches_title_and_partner_only (F3 regression guard)

**QA docs:** test-cases.md TC-064; traceability-matrix.md BR-27.

---

## 3. M6-2 — Duration column format

**Approved as originally drafted, with F4 now resolved. No changes in this revision.**

Target rendering:
```
18 Mar 2022 – 18 Mar 2027
        (5 years)
```

Range start is the merged Date Signed field (M6-5), backed by
`agreement_date`. Range end is `expiry_date`. The year count is calculated
from the two dates — derived, never stored.

**Where the logic lives:** `App\Models\Agreement` gets two new methods:
- `durationInMonths(): ?int`
- `durationLabel(): ?string`

Plus a Blade component `resources/views/components/agreement-duration.blade.php`
shared between index and detail.

These are methods only — no `#[Fillable]`, `casts()`, or migration change.

**Edge cases:**

| Case | Render |
|---|---|
| expiry_date null | "18 Mar 2022 – Indefinite", no year count |
| Start date null, expiry set | "— – 18 Mar 2027", no year count |
| Both null | "—" |
| Expiry before start | Render range as stored; suppress count rather than print negative |

**Decision 2 — Partial years: never round, show exact years/months.**
Now confirmed low-stakes: all 10 live agreements have whole-year durations
today, so this displays as "(N years)" in practice at launch. The
partial-year logic still needs to exist and be tested, because new
agreements entered from today onward are not guaranteed to land on
whole-year boundaries.

**Files:**
- `app/Models/Agreement.php`
- `resources/views/components/agreement-duration.blade.php` (new)
- `resources/views/components/⚡agreements-index.blade.php`
- `resources/views/components/⚡agreement-show.blade.php` (recommended, for consistency)

**Tests (AgreementDurationTest.php, new):**
- test_whole_year_duration_renders_as_years
- test_partial_year_duration_renders_years_and_months
- test_sub_year_duration_renders_months_only
- test_sub_month_duration_renders_less_than_a_month
- test_null_expiry_yields_no_duration_and_stays_indefinite
- test_null_start_date_yields_no_duration
- test_expiry_before_start_does_not_produce_a_negative_duration
- test_leap_year_boundary_is_handled

**QA docs:** test-cases.md TC-065; traceability-matrix.md BR-28.

---

## 4. M6-3 — DEFECT: new-partner fields do not appear inline

**Approved as originally drafted. No changes in this revision.**

**Severity:** Major.

**Root cause — confirmed, not assumed:**
1. `⚡agreement-form.blade.php:271, 275` — both partner-mode radios bound
   with `wire:model="partnerMode"`, no modifier.
2. Livewire 4.4.0's shipped code: with no modifiers, `isLive` is false,
   `hasLazyWithoutLive` is false, so `shouldSendNetwork` is false. Selecting
   the radio updates client-side state only — no network request.
3. Line 280 — the reveal is server-side `@if ($partnerMode === 'existing')`.
   With no round trip, the server never re-renders.
4. The next network request is form submit, which flushes the deferred
   value and triggers validation failure on the empty name field — THEN
   the fields appear, already carrying an error. This matches the reported
   symptom exactly.

**Fix:** Change both radios to `wire:model.live="partnerMode"`.

**Why the obvious regression test wouldn't catch it:** `Livewire::test()`
always performs a server round trip in the test harness — the bug lives in
the browser transport, which the test harness bypasses. The real regression
test must assert the DIRECTIVE in rendered markup, not just behavior.

**Files:**
- `resources/views/components/⚡agreement-form.blade.php` (lines 271, 275)
- `tests/Feature/Agreement/AgreementFormTest.php`
- `docs/qa/defect-log.md` (DEF-011)
- `docs/qa/test-cases.md` (TC-066)
- `docs/qa/traceability-matrix.md` (BR-29 — written as a convention:
  "conditional form sections driven by a server-rendered @if must be bound
  with wire:model.live")

**Tests:**
- test_partner_mode_radios_are_live_bound (the actual guard — asserts markup)
- test_switching_to_new_partner_mode_reveals_the_partner_name_fields
- test_switching_back_to_existing_restores_the_partner_dropdown

---

## 5. M6-4 — PIC becomes a plain name field (FINAL — Option c)

> **This section fully replaces the original draft**, which specified
> Option (b) — a placeholder `User` record. Amir rejected that after
> discussion. Reasoning (from AGENTS.md's M6 note): PICs are external
> stakeholders; the system does not yet support any non-Legal-staff login or
> self-service view, and PIC records should not be structured in a way that
> leans toward that future without a separate, explicit decision to build
> it. A future "PIC self-service view" is logged as a Future Consideration,
> not built now.

### What changes structurally

PIC moves from a `belongsTo(User::class)` foreign key to a plain string,
architecturally mirroring how `partner_id` + the "existing/new" toggle works
for Partner — except PIC has no backing table at all. There is no
`pics` table, now or planned. "New PIC" simply captures a name string typed
into the form; "Existing PIC" offers autocomplete over distinct names
already used on other agreements (a query against `agreements.pic_name`,
not a foreign-key lookup).

### Schema change

New migration:
`database/migrations/2026_09_0X_XXXXXX_add_pic_name_to_agreements_table.php`

```php
Schema::table('agreements', function (Blueprint $table) {
    $table->string('pic_name')->nullable()->after('pic_user_id');
});
```

`pic_user_id` is **kept, nullable, and unused** — not dropped. Reasoning:

- `pic_user_id` was already nullable and already had zero non-null values
  in production (no agreement has ever had a real logged-in PIC; F6 confirms
  PICs were never expected to log in "on day one," and no login-capable PIC
  accounts exist).
- Dropping `pic_user_id` in the same migration as adding `pic_name` doubles
  the blast radius of a single migration for no safety benefit — F4 confirms
  it holds no live data to lose, so there is nothing gained by removing it
  under time pressure.
- The `users` table needs no migration at all under Option (c). Nothing
  about `role`, `is_active`, or any user-table column changes.

This is the opposite call from M6-5 (where dropping the column *is*
correct — see Section 6) because the two columns are in different states:
`effective_date` has 10 rows of real, redundant data; `pic_user_id` has
zero rows of any data. Dropping a column nobody has ever populated is
optional cleanup, not a live-data risk to resolve now — recorded as a
"Future consideration" in AGENTS.md rather than bundled into M6.

### Model changes

`app/Models/Agreement.php`:
- Add `pic_name` to `#[Fillable([...])]`.
- Remove the `pic()` `BelongsTo` relation, or leave it defined but entirely
  unreferenced by any view/query — **recommend removing it outright**, since
  a relation nothing calls is a trap for a future reader who assumes PIC
  still resolves through `users`. If OpenCode Go finds any other reference
  to `->pic` (eager loads, `with('pic')`, anything in
  `HidePendingFromNonLegalScope` or the other five `#[Scope]` methods),
  stop and report it — that is a decision for Amir, not an implementation
  judgment call.
- No cast changes: `pic_name` is a plain string, same as `title` or `scope`.

### View changes

- `⚡agreement-form.blade.php`: replace the PIC `<select>` (sourced from
  `User::query()->active()...`) with a picMode radio pair
  (existing/new) — exact same structural pattern as the partner toggle,
  including the `wire:model.live="picMode"` binding (per the M6-3 fix —
  do not reintroduce the un-live-bound bug on the new toggle).
  - "Existing" mode: a `<select>` or datalist sourced from
    `Agreement::query()->whereNotNull('pic_name')->distinct()->pluck('pic_name')`,
    not from `users`.
  - "New" mode: a plain text input, `pic_name`, required in that mode only.
- `⚡agreements-index.blade.php`: PIC column (added in M6-1) renders
  `$agreement->pic_name ?? '—'`.
- `⚡agreement-show.blade.php`: PIC detail row renders `pic_name` directly,
  no relation load.

### Decision 4b — full names, validation style (unchanged reasoning, now applied to a plain field)

Recommended: label + placeholder only ("Full name, e.g. Ahmad bin Osman"),
do **not** hard-validate for a space character. Mononymous names are common
in Malaysia; a regex requiring two words would reject valid names. This
applies identically whether PIC is a `User` or a string — the reasoning
carries over unchanged from the original draft.

### Decision 4a — MOOT

The original Decision 4a (amend BR-12 to include inactive placeholder PICs
in the dropdown) no longer applies. BR-12 should instead be reworded to
describe the new autocomplete-over-distinct-names behavior — see traceability
matrix update below.

### Data migration for existing agreements

All 10 live agreements currently have `pic_user_id = null` (F6/F4 confirm no
agreement has ever had a real PIC assignment). There is nothing to
backfill: the migration adds an empty nullable column, and every existing
row correctly shows PIC as "—" until Intan edits them.

### Files

- `database/migrations/2026_09_0X_XXXXXX_add_pic_name_to_agreements_table.php` (new)
- `app/Models/Agreement.php`
- `resources/views/components/⚡agreement-form.blade.php`
- `resources/views/components/⚡agreements-index.blade.php` (PIC column, shared with M6-1)
- `resources/views/components/⚡agreement-show.blade.php`
- `database/factories/AgreementFactory.php` (replace any `pic_user_id` faker
  call with a `pic_name` faker name, or leave null — check current factory
  content before assuming which)
- `docs/qa/test-cases.md` (update any TC referencing PIC-as-user; add new)
- `docs/qa/traceability-matrix.md` (reword BR-12; note BR-18 if it
  referenced `pic_user_id`)
- `AGENTS.md` (record Option (c) as the implemented decision; add "PIC
  self-service view" to Future Considerations)

### Tests

- `test_pic_name_is_fillable_and_saves_as_a_plain_string`
- `test_new_pic_mode_requires_a_name`
- `test_existing_pic_mode_offers_previously_used_names`
- `test_pic_autocomplete_only_returns_distinct_non_null_names`
- `test_pic_mode_radios_are_live_bound` (guards against repeating the M6-3 bug)
- `test_agreement_no_longer_has_a_pic_relation` (or, if the relation is kept
  but unused: `test_pic_relation_is_not_referenced_by_any_view_or_scope` —
  pick one, don't write both)
- `test_editing_an_agreement_with_a_null_pic_name_renders_a_dash`
- Update/remove any existing test asserting `pic()` resolves a `User` —
  grep `tests/` for `pic_user_id` and `->pic` before writing new tests, so
  nothing stale is left passing against removed behavior.

**QA docs:** test-cases.md TC-067 (renumber if TC-067 is taken by the time
this lands — check current test-cases.md); traceability-matrix.md: reword
BR-12, add BR-30 for the "no user record" rule as an explicit business rule.

---

## 6. M6-5 — Merge agreement_date + effective_date into "Date Signed" (FINAL — Option b)

> **This section fully replaces the original draft's Option (b) placeholder.**
> The original draft fully specified only Option (a) (UI-only merge, no
> schema change) and left Option (b) as a recommendation without an
> implementation spec, pending live-data verification. That verification is
> done (F4): all 10 live agreements have `agreement_date` exactly equal to
> `effective_date`. Option (b) is approved and specified below.

⚠ This touches schema previously treated as frozen since M2, and runs
against a live production database with real rows. Treat every step in this
section as sequential and non-optional — do not compress or skip the backup
step under time pressure.

### What changes structurally

`effective_date` is dropped entirely. `agreement_date` becomes the sole
backing column for the UI field labeled "Date Signed" (see Decision 5b for
why it isn't labeled "Agreement Date"). Every place that currently reads,
writes, casts, or validates `effective_date` must be found and updated —
not just the obvious form and show views.

### Pre-migration step — REQUIRED, not optional

Before the migration runs against the Laravel Cloud production database:

1. Take a full database backup or export. Confirm with Amir what backup
   mechanism Laravel Cloud provides (automatic snapshot vs. manual
   `php artisan db:show` / export) — **do not assume one exists without
   checking**, since this has not been verified in any prior plan.
2. Confirm the backup is restorable in principle before proceeding (even a
   manual `.sql` dump downloaded locally satisfies this — the point is a
   copy exists outside the live database).
3. Only after the backup is confirmed does the migration run.

This step is a hard gate. OpenCode Go should not run the migration against
production without Amir explicitly confirming the backup step is done —
this is a "needs Amir approval" checkpoint, not a task to complete silently
as part of the handoff.

### Migration

`database/migrations/2026_09_0X_XXXXXX_drop_effective_date_from_agreements_table.php`

```php
Schema::table('agreements', function (Blueprint $table) {
    $table->dropColumn('effective_date');
});
```

Straightforward drop — no data transformation needed, since F4 confirms
`agreement_date` already holds the same value on every existing row. There
is nothing to copy or backfill.

**No down() data restoration is possible** if this migration is rolled
back after being run — dropping a column is destructive. The migration's
`down()` should recreate the nullable column but cannot repopulate it
except by copying back from `agreement_date` (recommended: have `down()`
do exactly that — `$table->date('effective_date')->nullable()` followed by
`DB::table('agreements')->update(['effective_date' => DB::raw('agreement_date')])` —
so a rollback at least leaves the column populated rather than empty).

### Model changes

`app/Models/Agreement.php`:
- Remove `effective_date` from `#[Fillable([...])]`.
- Remove `effective_date` from `casts()`.
- Confirm the `year` accessor already derives from `agreement_date`, not
  `effective_date` (per AGENTS.md's existing rule — "Year = the year the
  agreement was made, derived from agreement_date"). This should already be
  correct; verify, don't assume.
- `dateWarning()` (currently lines 207-216, compares `effective_date` to
  `expiry_date`) must be rewritten to compare `agreement_date` to
  `expiry_date`. Behavior unchanged: soft warning, never blocks save. This
  reworks BR-09.

### View changes

- `⚡agreement-form.blade.php`: the two separate date inputs (Agreement
  Date, Effective Date) collapse into one input, labeled "Date Signed,"
  wired to `agreement_date`. Confirm lines 62-64's `Y-m-d` wire-format
  hydration logic is updated to only hydrate one field, not two.
- `⚡agreement-show.blade.php`: the two detail rows (lines 109, 114)
  collapse into one row, labeled "Date Signed."
- `⚡agreements-index.blade.php`: any reference to `effective_date` in the
  Duration column (M6-2) or elsewhere must read `agreement_date`.

### Decision 5b — naming (approved as originally recommended)

Label the UI field "Date Signed," backed by `agreement_date`. The existing
`signed_date` column (F5) is left untouched and undocumented in the UI —
it remains a known, harmless piece of dead schema. Do not attempt to
consolidate `signed_date` into this change; that is a separate, un-approved
cleanup with its own risk profile (unlike `effective_date`, `signed_date`'s
live-data state has not been checked).

### Files

- `database/migrations/2026_09_0X_XXXXXX_drop_effective_date_from_agreements_table.php` (new)
- `app/Models/Agreement.php`
- `resources/views/components/⚡agreement-form.blade.php`
- `resources/views/components/⚡agreement-show.blade.php`
- `resources/views/components/⚡agreements-index.blade.php` (if Duration
  column logic references `effective_date`)
- `database/factories/AgreementFactory.php` (remove `effective_date`)
- `tests/` — grep for `effective_date` across the whole test suite before
  writing new tests; multiple existing tests (`AgreementCastsTest` at
  minimum, per the original draft's own note) reference it directly and
  will fail after the column is dropped if not updated first
- `docs/qa/test-cases.md` (update TC-030, add new)
- `docs/qa/traceability-matrix.md` (reword BR-09, add BR-31)
- `AGENTS.md` (record the merge + column drop as implemented, not just decided)
- `docs/BUILD_PLAN.md` (add M6 to the milestone table)

### Tests

- `test_editing_an_agreement_hydrates_date_signed_from_agreement_date`
- `test_expiry_before_date_signed_warns_but_still_saves` (renamed from the
  original `effective_date`-based test)
- `test_year_accessor_still_derives_from_agreement_date`
- `test_detail_page_shows_one_date_signed_row`
- `test_effective_date_column_no_longer_exists` (schema assertion —
  `Schema::hasColumn('agreements', 'effective_date')` is false)
- `test_agreement_factory_does_not_reference_effective_date`
- Any pre-existing test asserting on `effective_date` (starting with
  `AgreementCastsTest`) must be updated or removed, not left to fail silently

**QA docs:** test-cases.md TC-068; traceability-matrix.md reword BR-09, add BR-31.

---

## 7. M6-6 — DD/MM/YYYY throughout

**Approved as originally drafted. No changes in this revision.**

**(a) Date INPUT** — the actual source of Intan's confusion. Native
`<input type="date">` widgets display in browser/OS locale; cannot be
overridden by any web code. Recommended: keep native inputs, add
"(DD/MM/YYYY)" label hint, echo the selected value in unambiguous d M Y form
beneath each input.

**(b) Date DISPLAY** — already correct everywhere (F1). Recommended: keep
d M Y format (e.g. "18 Mar 2022") — safer than numeric format since an
alphabetic month cannot be misread in any locale.

Introduce a single source of truth regardless:
`resources/views/components/date.blade.php` (`<x-date :value="..."/>`), so
format is enforced in one place, not hoped for across six call sites.

**Files:**
- `resources/views/components/date.blade.php` (new)
- `resources/views/components/⚡agreements-index.blade.php`
- `resources/views/components/⚡agreement-show.blade.php`
- `resources/views/components/agreement-duration.blade.php`
- `resources/views/components/⚡agreement-form.blade.php` (label hints only,
  lines 62-64 keep `Y-m-d` wire format — must not change, and per M6-5, now
  only apply to `agreement_date`)
- `docs/HANDOVER.md`, `README.md` (note browser-locale behavior)

**Tests (DateFormattingTest.php, new):**
- test_the_date_component_renders_day_month_year
- test_the_date_component_renders_a_dash_for_null
- test_no_view_renders_a_month_first_date_format (greps for m/d, n/j patterns)
- test_date_inputs_carry_a_dd_mm_yyyy_hint
- test_form_date_inputs_still_use_the_y_m_d_wire_format

**QA docs:** test-cases.md TC-069; traceability-matrix.md BR-32.

---

## 8. Final approved decisions (Amir, 2 Sep 2026)

| # | Decision | Final Answer |
|---|---|---|
| 1 | Type filter | **(a)** Keep filter, remove only the column |
| 2 | Duration rounding | **(a)** Never round — show exact years/months. All 10 live agreements currently have whole-year durations (confirmed via F4), so this displays as "(N years)" in practice today; partial-year logic still ships and is tested for future agreements. |
| 3 | Scope truncation | **(a)** 80 characters |
| 4 | New PIC creation | **Option (c)**: PIC is a plain name value, `pic_name` on `agreements`, NOT a User record. No login, no account, no email, nothing tied to `users`. Structurally mirrors Partner's existing/new toggle. `pic_user_id` stays as an unused nullable column (see Section 5 for why it isn't dropped). Logged as a Future Consideration in AGENTS.md (PIC self-service view), not built now. |
| 4a | BR-12 amendment | **MOOT** — no longer about placeholder users; BR-12 is instead reworded for the new autocomplete-over-`pic_name` behavior |
| 4b | Full names, validation style | Label + placeholder only, no hard validation for a space (mononymous names are common in Malaysia) |
| 5 | Date merge | **Option (b) APPROVED**: drop `effective_date` via a real migration. All 10 live agreements verified with `agreement_date` exactly equal to `effective_date`, zero mismatches. Requires a confirmed database backup before the migration runs against production — see Section 6's pre-migration step. |
| 5b | Naming, given signed_date exists | **(a)** UI field labeled "Date Signed," backed by `agreement_date`. Unused `signed_date` column left as-is, documented as known dead schema. |
| 6 | `resolvePartnerId()` return type | **Fix now** — tighten to `?int` while already in that file for other M6 changes |
| 6a | Date inputs | **(a)** Keep native date picker, add "(DD/MM/YYYY)" label + confirmation text in unambiguous d M Y form beneath each input |
| 6b | Date display everywhere else | **(a)** Keep "18 Mar 2022" format |
| 7 | Timezone | **Fix now** — set `APP_TIMEZONE` to `Asia/Kuala_Lumpur`. Re-check M5-8 boundary tests (89/90/91-day staleness, expiry-today characterisation) still pass, since they reason about "the start of the day." |

---

## 9. Handoff structure

M6-4 and M6-5 both now involve real schema changes on a live production
database with real data — this is new since the original draft, which only
treated M6-5 as schema-risk (M6-4 was still an open option at that point,
and could have landed as Option (a), no schema change, if Amir had picked
differently). Given that, this plan splits M6 into **three handoffs**,
following the M5 pattern (`handoff-m5-defects.md`, `handoff-m5a.md`,
`handoff-m5b.md`) of isolating risk rather than one pattern of isolating by
milestone number.

### `docs/handoff-m6a-ui.md` — safe, UI-only, no schema changes

**Scope:** M6-1 (list columns), M6-2 (duration format), M6-3 (partner-mode
defect fix), M6-6 (date format consistency).

**Why first:** Zero schema risk. These four items are independently
shippable, independently testable, and none of them depend on M6-4 or M6-5
being done first — except that M6-1's PIC column and M6-2's Duration
column both *reference* fields that M6-4/M6-5 will change the shape of.

**Sequencing dependency to flag explicitly:** M6-1's PIC column renders
`pic_name`, which does not exist until M6-4 ships. M6-2's Duration range
start is the merged Date Signed field, which does not exist as
single-sourced until M6-5 ships. Two options:

- **(i)** Sequence handoffs schema-first: M6-4 and M6-5 (handoff b) ship
  before M6-1/M6-2 (handoff a), so the UI work has real columns to render
  against from the start.
- **(ii)** Sequence UI-first with a stub: M6-1/M6-2 render against the
  *existing* columns (`pic_user_id`'s related user name, or a null-safe
  guard; `effective_date` for the range start) as a temporary bridge, then
  a small follow-up commit in handoff (b) repoints them.

**Recommendation: (i), schema-first.** Reason: M6-1 and M6-2 would
otherwise be built once, then partially rewritten within the same
milestone once M6-4/M6-5 land — extra churn for no benefit, since M6-4/M6-5
are already scoped, decided, and ready to implement. There is no dependency
in the other direction (M6-4/M6-5 do not need M6-1/M6-2 to exist first).
This reorders the handoff sequence below from the milestone numbering.

### `docs/handoff-m6b-pic.md` — M6-4, schema change, ships first

**Scope:** M6-4 only (PIC → `pic_name`).

**Why isolated and first:** Independent of every other M6 item — nothing
else in M6 depends on the PIC schema, and M6-1's PIC column depends on it.
Lower blast radius than M6-5 (adding a nullable column is non-destructive
and trivially reversible — a plain `dropColumn` in `down()` fully undoes
it, unlike M6-5 where `down()` cannot cleanly restore prior state). Shipping
the lower-risk schema change first also rehearses the migration-on-
production process (backup-check habits, verifying on Laravel Cloud) before
the higher-risk M6-5 migration runs.

**Gate before this handoff starts:** None beyond the standard approved-plan
gate — adding a nullable column carries no destructive risk, so this does
not need the same explicit pre-migration backup ritual as M6-5, though
taking one anyway costs little.

### `docs/handoff-m6c-date-merge.md` — M6-5, schema change, ships second, most sensitive

**Scope:** M6-5 only (drop `effective_date`).

**Why isolated and last:** The one genuinely destructive migration in M6 —
`dropColumn` cannot be trivially undone with full fidelity. Isolating it
means a bad outcome here doesn't block or entangle with M6-4's already-
shipped, independent change, and means the backup-and-verify ritual gets
full attention as its own handoff rather than being one step among several.

**Gate before this handoff starts:** The Section 6 pre-migration backup
step is confirmed complete by Amir. Do not combine this confirmation with
any other handoff's sign-off.

### Revised shipping order

1. `handoff-m6b-pic.md` (M6-4 — lower-risk schema change)
2. `handoff-m6c-date-merge.md` (M6-5 — higher-risk schema change, needs
   confirmed backup)
3. `handoff-m6a-ui.md` (M6-1, M6-2, M6-3, M6-6 — UI-only, built against the
   now-final schema from steps 1–2, so no rework)

This is a schema-first ordering, not a numeric one. `AGENTS.md` and
`docs/BUILD_PLAN.md` should record the M6 milestone as complete only once
all three handoffs are done and `composer test` is green after each.

### What does NOT need a separate handoff

M6-3 (the partner-mode defect fix) touches the same file
(`⚡agreement-form.blade.php`) as M6-4's new PIC toggle. Rather than
sequence them separately, both changes land in `handoff-m6a-ui.md` together
— they're both UI/JS-binding fixes to the same file with no schema
dependency between them, and splitting them further would fragment a
single-file change across two handoffs for no risk-isolation benefit.