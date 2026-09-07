# OpenCode Go handoff — M7: UAT feedback from Intan

> **Milestone:** M7 · **Implements:** `docs/architecture-plan-m7.md` (APPROVED, 4 Sep 2026)
> **Precondition:** M1–M6c merged and green. Verified 3 Sep 2026 at 155 tests passing after the M6a fix pass.
> **Decisions:** M7-1 through M7-6, all approved by Amir on 4 Sep 2026. None is reopenable.
> **Nature of this milestone:** UAT feedback from Intan (Legal Executive) after live use. All cosmetic/label/filter changes plus two small, purely additive seeder changes. NO destructive migration, NO production-data risk — unlike M6, this handoff does not require a backup gate.
> **Paste the block below into OpenCode Go from the repo root.**

```text
Role: You are the SENIOR DEV on the CLSD Agreement Register (Laravel 13 +
Livewire 4, SQLite). Amir is the PM: implement exactly what the approved plan
says — do not redesign, do not add scope.

Context: Read AGENTS.md, docs/BUILD_PLAN.md, and docs/architecture-plan-m7.md
(APPROVED, 4 Sep 2026). That plan is the contract for this handoff. If
something it does not cover comes up, ask Amir — do not guess.

Precondition: M1-M6c must already be merged. `composer test` should be green
at 155 tests before you start. If it is not, stop and tell Amir.

This is UAT feedback, not a defect report. Nothing here is a bug fix —
everything is cosmetic, a label change, a new filter, or a new seeded row.
Do not treat any of these as an opportunity to "also fix" something you
notice along the way; if you spot something unrelated that looks wrong,
stop and report it to Amir rather than including it in this handoff.

This handoff has SIX independent items. Build them in this order — the
order matters only in that M7-3's label check should happen after M7-1/M7-2
are done, since all three touch the same header row:

═══════════════════════════════════════════════════════════════
M7-1 — Year filter on the Register list
═══════════════════════════════════════════════════════════════

Add a Year filter to ⚡agreements-index.blade.php's filter bar, alongside
the existing Search/Campus/Type/Document Status/Project Status filters.
Do NOT remove Project Status — that only happens later, in a follow-up, if
Amir decides the row is too cramped after seeing this rendered. Just add
Year as a sixth filter for now.

1. New public #[Url] property: `year` (string, default empty, same pattern
   as the existing `campus`/`type`/`documentStatus` properties).

2. New #[Computed] property `availableYears`:

     Agreement::query()
         ->selectRaw('DISTINCT YEAR(agreement_date) as year')
         ->whereNotNull('agreement_date')
         ->orderByDesc('year')
         ->pluck('year')

   This MUST go through Agreement::query() (not DB::table) so it
   automatically respects HidePendingFromNonLegalScope — a viewer's Year
   dropdown must only offer years that have at least one NON-PENDING
   agreement. Verify this explicitly with a test (see below); do not just
   assume the scope applies correctly without checking.

3. Filter application in the query builder: add
   `->when($this->year, fn ($q) => $q->whereYear('agreement_date', $this->year))`
   alongside the existing `->when(...)` filters for campus/type/etc. Do NOT
   load all agreements into PHP and filter by ->year in application code —
   the whole point is pushing this to the database via whereYear().

4. Add `updatingYear()` that resets pagination to page 1, matching the
   existing `updatingCampus()`/`updatingType()`/etc. pattern already in
   this component.

5. Add the Year <select> to the filter bar markup, populated from
   $this->availableYears, matching the existing filter controls'
   wire:model.live style and Tailwind classes.

Tests (add to AgreementsIndexTest.php):
- test_year_filter_narrows_the_list_to_agreements_signed_in_that_year
- test_year_dropdown_options_are_distinct_years_present_in_the_data
- test_year_dropdown_options_respect_the_pending_visibility_scope_for_a_viewer
  (create a pending agreement in a year with no other agreements; assert a
  viewer's availableYears does NOT include that year, but a legal user's
  does)
- test_changing_the_year_filter_resets_to_the_first_page
- test_an_agreement_with_a_null_agreement_date_is_excluded_from_every_year_filter_result

═══════════════════════════════════════════════════════════════
M7-2 — List header styling (dark header, borders)
═══════════════════════════════════════════════════════════════

Colors, sourced from UniKL's official Corporate Identity 2026 brand guide
(NOT a placeholder, NOT invented — use exactly these):
- Header background: #293D7A (UniKL primary dark blue)
- Header text: #FFFFFF (white, for contrast against the dark background)

1. Check tailwind.config.js's current state before deciding implementation
   approach. If this project's Tailwind setup already has a pattern for
   custom named colors in the config, add `unikl-blue: '#293D7A'` there and
   use `bg-unikl-blue`. If not, use an arbitrary-value class directly:
   `bg-[#293D7A]`. Match whichever approach is more consistent with how
   this codebase already handles Tailwind — do not introduce a new pattern
   if a simpler one-off arbitrary class is more consistent with the
   existing file.

2. Apply the dark background + white text to the <thead> row in
   ⚡agreements-index.blade.php. Every header <th> text must be legible
   white-on-dark-blue — check this renders correctly, this is a real
   accessibility/contrast requirement, not just a color swap.

3. Horizontal row dividers: the table currently uses `divide-y
   divide-gray-200` (or similar) between agreement rows. Darken this to a
   more visible shade — divide-gray-400 or similar. Pick a shade that is
   visibly darker than the current one when rendered; "slightly darker"
   that's imperceptible does not satisfy Intan's actual request.

4. Vertical column dividers: currently absent. Add visible vertical lines
   between every column — either `divide-x` at the row/cell level or
   `border-r` per cell. Choose whichever Tailwind mechanism produces a
   clean, consistent line between all columns including the header row,
   not just the body rows.

This is a pure CSS/markup change. No new tests required beyond confirming
the page still renders without error — do not invent visual-regression
tests for this. Run the EXISTING AgreementsIndexTest suite and confirm
nothing that was passing before now fails (a broken <thead> structure could
break existing tests that assert on header text, for example — check for
that specifically).

═══════════════════════════════════════════════════════════════
M7-3 — Column label renames
═══════════════════════════════════════════════════════════════

In ⚡agreements-index.blade.php's <thead>, change the header <th> TEXT only:
- "Status" → "Document Status"
- "Project" → "Project Status"

Do NOT touch: the underlying document_status/project_status field names,
any filter variable name, any query logic, any test assertion that checks
BEHAVIOR (only update a test assertion if it specifically asserts the OLD
literal header text "Status" or "Project" — grep for these first).

    grep -n "'Status'\|'Project'" tests/Feature/Agreement/AgreementsIndexTest.php

If any hits are asserting the exact old header text, update those specific
assertions to the new text. Do not touch anything else in that test file
for this item.

═══════════════════════════════════════════════════════════════
M7-4 — Sector label: "Industri" → "Industry" (DISPLAY ONLY)
═══════════════════════════════════════════════════════════════

In ⚡agreement-form.blade.php's Sector <select>, change ONLY the displayed
text of the industri option from "Industri" to "Industry". The option's
value attribute STAYS exactly `value="industri"` — do not change it to
"industry". Do NOT touch:
- The validation rule (`'sector' => ['nullable', 'in:academic,industri']`
  stays exactly as-is, still checking for the string 'industri')
- Any model code
- Any migration or existing agreement's stored sector value

This is confirmed by Amir as label-only (Decision M7-4) specifically to
avoid touching stored data for zero functional benefit. If you find
yourself wanting to add a migration or change the validation rule for this
item, STOP — that is explicitly out of scope and was a rejected option.

If any existing test asserts the visible text "Industri" appears on the
page, update that one assertion to "Industry". Do not add a new test for a
label rename — there is no new behavior to test.

═══════════════════════════════════════════════════════════════
M7-5 — Country dropdown simplified to Local / International
═══════════════════════════════════════════════════════════════

This applies ONLY to the Partner quick-create flow's country control inside
⚡agreement-form.blade.php ("New partner" mode) — not to any other country
usage elsewhere in the app, and it does NOT reduce the countries table
itself.

1. First, check database/migrations for the countries table's schema —
   specifically whether iso_code is nullable — before deciding how to seed
   the new placeholder row. Do not assume; verify.

2. database/seeders/CountrySeeder.php: add ONE new row, following the
   seeder's existing idempotent updateOrInsert pattern exactly (same style
   already used for the 25 real countries and Malaysia's is_domestic flag):
     - name: "International"
     - iso_code: null if the column allows it; otherwise a sensible
       placeholder value consistent with how CampusSeeder's TBD row
       handles a similar situation — check TBD's actual column values
       first, don't invent a new pattern
     - is_domestic: false

   Do NOT touch, remove, or modify any of the 25 existing real country
   rows. This is purely additive.

3. In ⚡agreement-form.blade.php's New Partner mode: replace the full
   country <select> (currently listing all 25+ countries) with a two-option
   control offering "Local" and "International". On submit, resolve:
   - "Local" → the existing Malaysia country_id (query
     Country::where('is_domestic', true)->first(), do not hardcode an ID)
   - "International" → the new "International" placeholder row's
     country_id (query Country::where('name', 'International')->first(),
     do not hardcode an ID)

   Implementer's choice whether this is a <select> with two options or a
   radio pair — match whichever style is more consistent with this file's
   existing partnerMode/picMode radio-toggle pattern, since that is already
   the established convention in this exact component for binary choices.

4. Existing partners' country_id values must be completely unaffected by
   this change — this only changes what the CREATE form offers going
   forward, nothing about already-saved data.

Tests (add to AgreementFormTest.php or a new PartnerCountryTest.php,
whichever fits the existing test organization better):
- test_selecting_local_resolves_the_new_partners_country_to_malaysia
- test_selecting_international_resolves_the_new_partners_country_to_the_placeholder_row
- test_country_seeder_is_still_idempotent_with_the_new_row (run the seeder
  twice in the test, assert no duplicate "International" row — mirror
  CountrySeederTest's existing idempotency test exactly)
- test_existing_partners_country_id_is_unaffected_by_this_change

═══════════════════════════════════════════════════════════════
M7-6 — Three new campuses
═══════════════════════════════════════════════════════════════

database/seeders/CampusSeeder.php: add three new rows via the seeder's
existing idempotent updateOrInsert pattern, matching the exact code/name
structure of every existing campus row:

- code: "MCI", name: "UniKL Malaysia China Institute"
- code: "CIL", name: "Centre for Industrial Linkages"
- code: "CoRI", name: "Centre for Research and Innovation"

Spelling is confirmed: "Centre" (British English), not "Center" — matches
every other seeded institute's naming convention. Use exactly this spelling.

Do not touch any existing campus row, including TBD.

Tests (add to a CampusSeederTest.php if one exists, or create one following
CountrySeederTest's structure if not):
- test_campus_seeder_includes_the_three_new_campuses
- test_campus_seeder_is_still_idempotent_with_the_new_rows (run twice,
  assert no duplicates)
- test_new_campuses_appear_in_the_agreement_forms_campus_dropdown

═══════════════════════════════════════════════════════════════

After all six items:

1. Run `composer test` — laravel/pao prints compact JSON when it detects an
   AI agent; parse the "result" field. Report the exact before/after count
   (started at 155; report the final number).

2. Run `vendor\bin\pint` on every PHP file you touched.

3. docs/qa/test-cases.md and docs/qa/traceability-matrix.md: check the
   current highest TC-### and BR-## numbers before adding new entries — do
   not assume any specific numbers are free. Add entries for M7-1 (Year
   filter) and M7-5 (country simplification) — these are the two items with
   genuine new business-rule/filter behavior worth documenting. M7-2, M7-3,
   M7-4, M7-6 do not need new QA doc entries — they are cosmetic/data
   additions with no new business rule to trace.

Approved decisions relevant to this handoff (do not reopen):
- M7-1: Year filter added alongside existing filters, NOT replacing Project
  Status. Filtering via whereYear() on agreement_date, not a stored column.
- M7-2: Header colors are #293D7A background / #FFFFFF text, sourced from
  UniKL's official brand guide — not a placeholder, do not substitute a
  different color.
- M7-4: Sector label change is DISPLAY ONLY. Stored value stays 'industri'.
  This was explicitly considered and rejected as a data migration — do not
  revisit.
- M7-5: Country simplification applies to the partner quick-create UI only.
  The countries table keeps all 25 real countries; only one new
  "International" placeholder row is added, following the same pattern as
  the TBD campus row.
- M7-6: Campus spelling is "Centre", confirmed.

Do not change:
- Any migration. This handoff adds zero migrations — everything schema-
  related here is a new SEEDED ROW via existing seeders, not a schema
  change.
- The Agreement global scope (HidePendingFromNonLegalScope), the three-role
  enum, or anything from M1's auth.
- Any of the 25 existing real countries in CountrySeeder.
- Any existing campus row, including TBD.
- The sector validation rule or any stored sector value.
- Anything outside the file list implied by the six items above.
- Do not run npm scripts (ignore-scripts=true) or touch vite.config.js.

Do not build (out of scope for this handoff):
- Removing the Project Status filter — that is a follow-up decision Amir
  makes after seeing the Year filter rendered, not part of this handoff.
- Any change to the countries table's overall structure or row count beyond
  the one new placeholder row.
- Any fix for something you notice that isn't one of the six items above —
  report it instead.

Acceptance checks:
- `composer test` green (parse the JSON output; report the result field and
  the exact final count).
- `vendor\bin\pint` run on all PHP you touched.
- Manual: the list view shows a Year dropdown that actually narrows results;
  the header row is dark blue with white text and visible column/row
  dividers; header labels read "Document Status" and "Project Status"; the
  Sector dropdown shows "Industry"; the New Partner country control offers
  only Local/International and correctly resolves to Malaysia or the new
  placeholder row; the Campus dropdown includes MCI, CIL, and CoRI.
- `grep -n "value=\"industri\"" resources/views/components/⚡agreement-form.blade.php`
  still shows the value attribute unchanged — confirms M7-4 stayed
  display-only.

After editing:
- List every file created/modified.
- Report exact test names and results — before (155) and after count.
- Confirm explicitly that no migration was added — this handoff should be
  entirely seeders + views.
- Tell Amir what to manually check in the browser, in order matching the
  six items above.
- Do not commit unless Amir says to.
```

---

## Notes for Amir

- **No backup gate needed this time.** Unlike M6b/M6c, nothing here is destructive or touches existing production rows — the two "data" changes (M7-5, M7-6) are both purely additive new seeded rows, following the exact same safe pattern your `TBD` campus and existing `CountrySeeder` rows already use. Still worth a quick look at the diff before pushing, as always, but there's no equivalent of the M6c backup ritual required here.

- **M7-2's colors are sourced from a real document, not guessed** — UniKL's own "Corporate Identity 2026" brand guide, fetched directly from `unikl.edu.my`. Worth a 10-second gut check yourself when you see it rendered: does `#293D7A` look like the blue you'd expect from UniKL's actual materials? If something looks off, it's a five-minute swap, not a structural problem.

- **M7-1's viewer-scope test is the one item in this handoff that actually protects a real security property** (the same "don't leak which pending rows exist" concern M3's status-filter guardrail worried about originally) — worth reading that specific test yourself when it comes back, even though everything else in this handoff is low-stakes cosmetics.

- **This is a single handoff, not split like M6.** There's no schema risk to isolate here, so unlike M6's three-way split, everything ships together. If OpenCode Go's context or turn count gets long working through all six items in one session, that's a sign to consider splitting a *future* multi-item handoff, not a signal something went wrong with this one specifically.
