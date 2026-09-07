# M7 — UAT feedback from Intan (architecture plan)

> **Status:** Final, ready for handoff drafting.
> **Milestone:** M7 — first round of user acceptance feedback after M6 shipped live.
> **Role:** Lead Architect (plan only, no implementation)
> **Date:** 4 Sep 2026

---

## Context

M1–M6c are complete, deployed, and verified against real production data on
Laravel Cloud. Amir asked Intan (Legal Executive, confirmed system owner) to
informally test the live system without detailed guidance — an unmoderated
user-acceptance pass. Her feedback is entirely cosmetic/UX, not a defect
report: nothing here is a bug, and nothing changes the core business rules
(pending-visibility scope, role permissions, activity logging) that M1–M6
established.

M7 has two categories of item:
- **Pure display/label changes** — no schema, no migration, low risk.
- **One real filtering feature** (Year) and **one real data-model decision**
  (Country simplification) that each need a specific implementation choice,
  both resolved below.

---

## 1. M7-1 — Year filter on the Register list

### The concept

`Agreement::year()` is already an **accessor** — a value computed from
`agreement_date` at read time, not a stored column (per AGENTS.md: "Year =
the year the agreement was made, derived from agreement_date, not typed
separately"). This is correct and stays correct. The task is adding a
**filter**, not a new column.

### Implementation

Do NOT filter in PHP by loading every agreement and checking `->year` in
code — this doesn't scale and bypasses the database entirely. Use
`whereYear('agreement_date', $year)` in the Livewire component's query
builder chain, alongside the existing `campus`/`type`/`documentStatus`/
`projectStatus` filters. This pushes the filtering to the database (both
SQLite and MySQL support `whereYear`), consistent with every other filter
already in `⚡agreements-index.blade.php`.

### Populating the dropdown's options

The Year filter's options must be the **distinct years actually present in
the data**, not a hardcoded range (e.g. "2020–2030") — a hardcoded range
would show years with zero agreements (confusing) or miss a year outside
the guessed range (broken) as soon as someone imports older historical data
or the system is still in use years from now.

Query: `Agreement::query()->selectRaw('DISTINCT YEAR(agreement_date) as
year')->whereNotNull('agreement_date')->orderByDesc('year')->pluck('year')`
— as a `#[Computed]` property, matching the existing pattern for
`campuses`/`partners`/`countries` dropdowns in the form component. Note
this must NOT go through the pending-visibility global scope filter
differently than the list itself — a viewer's Year dropdown should only
offer years that have at least one non-pending agreement, or a viewer could
infer a pending agreement exists in a year with otherwise no visible
agreements. Confirm this dropdown query respects
`HidePendingFromNonLegalScope` the same way the list's own query does (it
will, automatically, since `Agreement::query()` always carries the global
scope — but explicitly test this, it is the kind of leak M3's status-filter
guardrail already worried about once before).

### Placement decision (Decision M7-1, approved 4 Sep 2026)

Add the Year dropdown to the filter bar now. Do **not** remove the Project
Status dropdown as part of this handoff. Only remove Project Status later,
in a follow-up, if the filter row visibly overflows/wraps awkwardly once
Year is added — and that is Amir's visual judgment call after seeing it
rendered, not a decision to make blind now. The handoff below adds Year
alongside the existing five filters (Search, Campus, Type, Document Status,
Project Status) and stops there.

### Files

- `resources/views/components/⚡agreements-index.blade.php` (new filter
  control, new `#[Url]` public property `year`, new `#[Computed]`
  `availableYears` property, `updatingYear()` resets to page 1 matching the
  existing filter-reset pattern)

### Tests

- `test_year_filter_narrows_the_list_to_agreements_signed_in_that_year`
- `test_year_dropdown_options_are_distinct_years_present_in_the_data`
- `test_year_dropdown_options_respect_the_pending_visibility_scope_for_a_viewer`
- `test_changing_the_year_filter_resets_to_the_first_page`
- `test_an_agreement_with_a_null_agreement_date_is_excluded_from_every_year_filter_result`

**QA docs:** test-cases.md (next free TC-###); traceability-matrix.md (next
free BR-##, citing `whereYear` and the derived-year convention from
AGENTS.md).

---

## 2. M7-2 — List header styling (dark header, borders)

Pure visual change. No schema, no logic change, no new tests beyond a
lightweight render-smoke-test if useful — this is not business-rule-bearing
work, so do not over-test it.

### Decision M7-2a — colors

"UniKL theme colors" is not yet a concrete value anyone can implement
against. Before this goes into a handoff, Amir needs to supply (or approve
a specific) hex/Tailwind value — e.g. UniKL's official brand blue/maroon,
however it's officially defined. **Open item, needs Amir's input before
handoff, not a default the implementer should invent.** Recommend: Amir
either provides the hex code(s) directly, or approves a close Tailwind
utility (e.g. `bg-slate-800` for a dark neutral header) as an acceptable
placeholder if the exact UniKL brand color isn't readily available today —
better to ship a sensible dark header now and swap the exact hex later than
block M7-2 entirely on brand-guideline lookup.

### Scope of the visual change

- Header row (`<thead>`): darker background (per Decision M7-2a), header
  text likely needs a lighter/white color for contrast against a dark
  background — this is a real accessibility consideration (contrast ratio),
  not just aesthetics; do not ship white-on-light-gray or dark-on-dark by
  accident.
- Horizontal row dividers: darken `divide-y divide-gray-200` (current) to a
  more visible shade, e.g. `divide-gray-400` or similar — exact shade is
  Amir's call at review time, not a hard spec here.
- Vertical column dividers: currently absent (`<table>` has no vertical
  border utility applied). Add `divide-x` on the row/cell level, or
  per-cell `border-r` — implementer's choice of Tailwind mechanism, but the
  visual result must be a visible vertical line between every column, not
  just some.

### Files

- `resources/views/components/⚡agreements-index.blade.php`

### Tests

None required beyond confirming the page still renders without error and
existing functional tests (search, filters, pagination, role visibility)
are unaffected — this is a pure CSS/markup change with no new business
logic. Do not invent visual-regression tests for a one-person internal tool
unless Amir specifically wants that investment (out of scope by default).

---

## 3. M7-3 — Column label renames

Pure label change, zero logic impact. "Status" → "Document Status", "Project"
→ "Project Status" in the `<th>` header cells only. The underlying
`document_status` / `project_status` field names, filter variable names, and
all business logic stay exactly as they are — only the human-readable header
text changes.

### Files

- `resources/views/components/⚡agreements-index.blade.php` (header `<th>`
  text only)

### Tests

None needed — no behavior to test, only a label string. If M7-1's or M7-2's
tests happen to assert on rendered header text elsewhere, verify those
assertions aren't accidentally broken by this rename (a quick grep, not a
new test).

---

## 4. M7-4 — Sector label: "Industri" → "Industry" (display only)

**Decision M7-4, approved 4 Sep 2026: label-only change. The stored value
stays `industri`.** Considered and rejected: changing the stored value to
`industry` (would require a migration touching existing agreement rows,
updating the validation rule's `in:academic,industri` list, and touching any
test asserting the literal string) for zero functional benefit — nobody
reads the raw database value directly, only the rendered label. This mirrors
AGENTS.md's own "enum columns stay plain strings, deferred as a low-risk
refactor" philosophy: don't refactor stored values without a concrete reason
tied to actual behavior, and there isn't one here.

### Implementation

In `⚡agreement-form.blade.php`'s Sector `<select>`, the option keeps
`value="industri"` but the displayed text changes from "Industri" to
"Industry". Do not touch the validation rule
(`'sector' => ['nullable', 'in:academic,industri']` stays exactly as-is —
the stored value is still `industri`), the model, or any migration.

### Files

- `resources/views/components/⚡agreement-form.blade.php` (option label
  text only)

### Tests

None needed — this is a label string with no behavior change. If an
existing test asserts on the visible text "Industri" anywhere, update that
one assertion to "Industry"; do not add a new test for a label rename.

---

## 5. M7-5 — Country dropdown simplified to Local / International

### Decision M7-5, approved 4 Sep 2026

The **Partner quick-create country dropdown** (in `⚡agreement-form.blade.php`,
the "New partner" mode) is simplified from the full 25-country picker to two
choices: **Local** and **International**.

This is a real, if small, data-model decision — not a pure label change —
because `partners.country_id` is a foreign key to `countries.id`, and
"International" is not itself a valid country. The resolution follows the
same pattern already established by the `TBD` campus row (a seeded
catch-all placeholder for "don't need to specify the real value"):

- **"Local"** resolves to the existing Malaysia row in `countries` (already
  seeded, already `is_domestic = true` — no new row needed).
- **"International"** resolves to **one new seeded placeholder country
  row** — e.g. `name = "International"`, `iso_code` left null or a
  placeholder value (confirm `iso_code`'s nullability before assuming;
  check the `countries` migration), `is_domestic = false`. This new row is
  added via `CountrySeeder`, following its existing idempotent
  `updateOrInsert` pattern — the same seeder that already seeds the 25 real
  countries and Malaysia's `is_domestic` flag.

**What does NOT change:** the underlying `countries` table keeps all 25 real
countries — this is a UI simplification for the quick-create flow only, not
a schema reduction. A partner's `country_id` can still, in principle, point
at any real country row if one was set some other way (e.g. a future import)
— the simplified dropdown only affects what options this *specific* form
control offers going forward. Existing partners' `country_id` values are
untouched; this handoff does not migrate or reassign any existing partner's
country.

### Why this is the right level of change

Rejected alternative: collapsing the `countries` table itself to just two
rows (Local/International) and dropping the other 23. Rejected because it
destroys real data for no benefit — nothing requires deleting the specific
country data, and a future need (reporting by actual country, a partner
whose specific country matters again) would have no way to recover it. The
seeded placeholder-row approach gets Intan the simple two-choice form she
asked for while leaving the door open and costing nothing.

### Files

- `database/seeders/CountrySeeder.php` (add the new `"International"`
  placeholder row, idempotent per the seeder's existing style)
- `resources/views/components/⚡agreement-form.blade.php` (New Partner
  mode's country control: replace the full country `<select>` with a
  two-option Local/International control, resolving to the correct
  `country_id` on submit — implementer's choice whether this is a `<select>`
  with two options or a radio pair, matching whichever existing pattern in
  this file reads more consistently, e.g. the partnerMode/picMode radio
  style already established)

### Tests

- `test_selecting_local_resolves_the_new_partners_country_to_malaysia`
- `test_selecting_international_resolves_the_new_partners_country_to_the_placeholder_row`
- `test_country_seeder_is_still_idempotent_with_the_new_row` (run the seeder
  twice, confirm no duplicate "International" row — matches
  `CountrySeederTest`'s existing idempotency test pattern)
- `test_existing_partners_country_id_is_unaffected_by_this_change` (a
  regression guard — confirms this handoff didn't accidentally touch
  existing partner rows)

**QA docs:** test-cases.md (next free TC-###); traceability-matrix.md (next
free BR-##).

---

## 6. M7-6 — Three new campuses

Straightforward seeder addition, same pattern as every existing campus row.

### Decision M7-6, approved 4 Sep 2026

Add three new campus rows via `CampusSeeder`'s existing idempotent
`updateOrInsert` pattern (per AGENTS.md: "`php artisan db:seed` runs only
`CampusSeeder`... 12 UniKL institutes + central units + `TBD`"):

- **MCI** — UniKL Malaysia China Institute
- **CIL** — Centre for Industrial Linkages
- **CoRI** — Centre for Research and Innovation

Note: Amir's message said "Center" for CoRI; UniKL's actual institutional
naming convention (matching MCI's and every other seeded institute's
British-English "Centre") is very likely "Centre" — confirm the correct
spelling with Amir or UniKL's own materials before seeding, since this
becomes a permanent, user-facing dropdown label that's awkward to silently
"fix" later without it looking like a second unexplained change.

### Files

- `database/seeders/CampusSeeder.php` (three new rows, idempotent, matching
  existing `code`/`name` structure exactly)

### Tests

- `test_campus_seeder_includes_the_three_new_campuses`
- `test_campus_seeder_is_still_idempotent_with_the_new_rows` (run twice,
  confirm no duplicates — matches the existing seeder test pattern)
- `test_new_campuses_appear_in_the_agreement_forms_campus_dropdown`

**QA docs:** traceability-matrix.md — no new business rule really, this is
data, but worth a one-line note in test-cases.md confirming the dropdown
reflects the current full campus list.

---

## 7. Open items requiring Amir's input before handoff drafting

Two items in this plan are intentionally left as decisions for Amir rather
than filled in by the architect, because guessing would risk a permanent,
user-facing, awkward-to-silently-fix mistake:

1. **M7-2a — the exact UniKL theme color(s)** for the header background (and
   confirm the header text color provides adequate contrast against it).
2. **M7-6 — "Centre" vs "Center" spelling** for CoRI, matching UniKL's actual
   institutional convention.

Everything else in this plan is ready to go into a handoff once these two are
confirmed.

---

## 8. Handoff structure

Unlike M6, nothing here touches production data or requires a backup gate —
M7-5 and M7-6 add new seeded rows (safe, idempotent, purely additive,
exactly like every prior seeder addition in this project's history), and
M7-1 through M7-4 are read-only display/filter changes. There is no
destructive migration anywhere in M7.

**Recommendation: one single handoff**, `docs/handoff-m7.md`, covering all
six items. Unlike M6's three-way split (which existed specifically to
isolate two genuinely risky schema migrations from each other and from
UI-only work), M7 has no comparable risk to isolate — splitting it into
multiple handoffs here would just be process overhead with no safety
benefit. All six items can be built, tested, and reviewed together in one
pass.
