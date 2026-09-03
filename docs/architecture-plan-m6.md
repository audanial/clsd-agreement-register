# M6 — Post-deployment usability fixes (architecture plan)

> **NOTE:** This file was reconstructed from chat history because the original
> Claude Code session drafted this plan but was never given final approval to
> write it to disk. The plan content below (Sections 1-11) is the FIRST DRAFT
> as originally proposed. See the very end of this document for Amir's FINAL
> APPROVED decisions, which supersede several recommendations below —
> most notably Decision 4 (PIC), which changed from the recommended Option (b)
> to Option (c) after further discussion with Amir.

> **Milestone:** M6 — Post-deployment UX corrections and one defect
> **Role:** Lead Architect (plan only, no implementation)
> **Date of original draft:** 2 Sep 2026
> **Status:** Superseded by final decisions at the bottom of this file.

---

## Context

M1-M5b are complete and deployed to Laravel Cloud. Amir populated the live
system with real historical agreements by hand. During that exercise Amir and
Intan (Legal Executive, confirmed system owner) hit six concrete problems: five
usability gaps and one genuine defect.

This is not scope creep and not a redesign. It is the first round of
feedback from the people who actually have to operate the register, which is
exactly the input the M5 design philosophy in AGENTS.md says should drive
decisions ("can Intan do this without technical help?"). docs/BUILD_PLAN.md
should record M6 as a legitimate new phase after M5.

M6 is a UI/UX milestone with one schema question. It does not touch the
pending global scope, role-based access control, or activity logging.

---

## 1. Verified current state (checked 2 Sep 2026 against the working tree)

Everything below was read directly, not taken from the plan files.

### M1-M5b confirmed complete

| Claim | Verified |
|---|---|
| composer test green | ✅ 125 tests, 323 assertions — matches the M5b commit message exactly |
| Laravel / Livewire versions | ✅ laravel/framework v13.25.0, livewire/livewire v4.4.0 |
| Livewire 4 single-file components | ✅ 4 ⚡-prefixed files in resources/views/components/, no app/Livewire/ directory |
| Agreement model | ✅ #[Fillable] attribute config, #[ScopedBy(HidePendingFromNonLegalScope::class)], casts, 6 #[Scope] methods, year accessor |
| Routes | ✅ Route::livewire(...) for index / create / edit / show / users |
| QA artifacts | ✅ test-cases.md (TC-001...TC-063), traceability-matrix.md (BR-01...BR-26), defect-log.md (DEF-001...DEF-010) |
| M5b user management | ✅ ⚡user-manager.blade.php exists, admin-gated, 8 tests in UserManagementTest |

### Findings that change the shape of this plan

**F1 — The app never renders MM/DD/YYYY anywhere.**
Every date render in the application is already `->format('d M Y')` → "18 Mar 2022".
A grep over resources/ returns the complete set:

| File | Line | What renders | Format |
|---|---|---|---|
| ⚡agreements-index.blade.php | 201 | expiry date | d M Y |
| ⚡agreement-show.blade.php | 109 | agreement date | d M Y |
| ⚡agreement-show.blade.php | 114 | effective date | d M Y |
| ⚡agreement-show.blade.php | 123 | expiry date | d M Y |
| ⚡agreement-show.blade.php | 205 | activity feed | diffForHumans() — no numeric date |
| badges/stale.blade.php | 6 | stale tooltip | diffForHumans() — no numeric date |
| ⚡agreement-form.blade.php | 62-64 | form state hydration | Y-m-d — required wire format, must not change |

dashboard.blade.php, layouts/app.blade.php, auth/login.blade.php and
⚡user-manager.blade.php render no dates at all.

So the MM/DD/YYYY Intan saw came from the three native `<input type="date">`
widgets (form lines 343, 349, 355), whose displayed format is chosen by the
browser from OS/browser locale — a Windows machine set to English (United
States) shows mm/dd/yyyy. The HTML value is always yyyy-mm-dd regardless;
only the presentation differs, and a web page cannot override it via CSS or
any attribute. This reframes M6-6 substantially (see Section 7).

**F2 — M6-3's root cause is confirmed, not guessed.** See Section 4.

**F3 — Search does not cover scope.** ⚡agreements-index.blade.php:46-52
searches title and partner.name only. Truncating the Scope column cannot
break search-by-scope, because no such behavior exists.

**F4 — The 10 historical agreements are not available locally.**
database/database.sqlite contains 0 agreements. The real data lives only on
Laravel Cloud. Original draft could not verify whether any historical row had
agreement_date ≠ effective_date, or whether any duration was a non-whole number
of years. Both M6-2 and M6-5 depended on that.

> **RESOLVED since original draft:** Amir manually checked all 10 live
> agreements. Zero mismatches between agreement_date and effective_date.
> See final decisions at bottom.

**F5 — An unused signed_date column already exists.**
create_agreements_table line 46 defines signed_date, and it is in the
#[Fillable] list — but no form writes it and no view reads it. Naming the
merged M6-5 field "Date Signed" collides with it.

**F6 — The users migration already anticipates non-logging-in PICs.**
2026_01_01_000007_add_role_to_users_table.php carries the comment:
"PICs are UniKL staff outside Legal; they appear in the PIC dropdown but
don't necessarily log in on day one."

> **NOTE:** This finding informed the original Decision 4 recommendation
> (Option b), but Amir's final decision (Option c — plain name, no User
> record at all) supersedes this reasoning. See final decisions.

**F7 — users.email is unique() NOT NULL and users.password is NOT NULL.**
There is no password-reset route in routes/web.php (only login/logout).

**F8 — config/app.php sets 'timezone' => 'UTC'. Malaysia is UTC+8.**
Date-only columns are unaffected, but diffForHumans() on created_at and
project_status_updated_at can read up to 8 hours off for a Malaysian user.

---

## 2. M6-1 — List view column changes

New column order: Title, Partner, Duration, Scope, Status, Project, PIC, Campus.

Changes from today's header (Title, Type, Partner, Campus, Status, Project, Expiry, ⌀):
- Type column removed — the title conventionally states the type ("LOI Awan Byte...").
- Expiry column replaced by Duration (M6-2), which folds the range in.
- Scope added as a truncated excerpt.
- PIC added.
- Campus moves to the end.

**Decisions baked in:**
- Truncation length: 80 characters, via Str::limit($agreement->scope, 80).
- The `<td>` also gets `title="{{ $agreement->scope }}"` so hovering shows the
  full text without leaving the list. Full text remains on the detail page.
- Null scope renders "—".
- The Type filter in the filter bar stays. Only the column is removed.

**Easy-to-miss detail:** The empty-state row is `colspan="8"` (line 209). The
new table has 9 columns. It must become `colspan="9"`.

**Files:** resources/views/components/⚡agreements-index.blade.php

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

Target rendering:
```
18 Mar 2022 – 18 Mar 2027
        (5 years)
```

Range start is the merged Date Signed field (M6-5), backed by agreement_date.
Range end is expiry_date. The year count is calculated from the two dates —
derived, never stored.

**Where the logic lives:** App\Models\Agreement gets two new methods:
- durationInMonths(): ?int
- durationLabel(): ?string

Plus a Blade component resources/views/components/agreement-duration.blade.php
shared between index and detail.

These are methods only — no #[Fillable], casts(), or migration change.

**Edge cases:**
| Case | Render |
|---|---|
| expiry_date null | "18 Mar 2022 – Indefinite", no year count |
| Start date null, expiry set | "— – 18 Mar 2027", no year count |
| Both null | "—" |
| Expiry before start | Render range as stored; suppress count rather than print negative |

**Partial years — Decision 2:** recommended never round — state exact
years/months.

**Files:**
- app/Models/Agreement.php
- resources/views/components/agreement-duration.blade.php (new)
- resources/views/components/⚡agreements-index.blade.php
- resources/views/components/⚡agreement-show.blade.php (optional, recommend for consistency)

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

**Severity:** Major.

**Root cause — confirmed, not assumed:**
1. ⚡agreement-form.blade.php:271, 275 — both partner-mode radios bound
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

**Why the obvious regression test wouldn't catch it:** Livewire::test()
always performs a server round trip in the test harness — the bug lives in
the browser transport, which the test harness bypasses. The real regression
test must assert the DIRECTIVE in rendered markup, not just behavior.

**Files:**
- resources/views/components/⚡agreement-form.blade.php (lines 271, 275)
- tests/Feature/Agreement/AgreementFormTest.php
- docs/qa/defect-log.md (DEF-011)
- docs/qa/test-cases.md (TC-066)
- docs/qa/traceability-matrix.md (BR-29 — written as a convention: "conditional
  form sections driven by a server-rendered @if must be bound with
  wire:model.live")

**Tests:**
- test_partner_mode_radios_are_live_bound (the actual guard — asserts markup)
- test_switching_to_new_partner_mode_reveals_the_partner_name_fields
- test_switching_back_to_existing_restores_the_partner_dropdown

---

## 5. M6-4 — PIC gets the same Existing / New toggle as Partner

> **SUPERSEDED — see final approved decisions at bottom of this document.**
> Original recommendation below (Option b) was NOT approved. Amir chose
> Option (c) instead after discussion.

Original text preserved for reference:

Mirror the partner UX: a picMode radio pair (existing / new). Recommendation
was Option (b): create a real User with role=viewer, is_active=false, and a
synthesized placeholder email (pic-<slug>-<n>@placeholder.invalid, RFC
2606-reserved, can never resolve). This required amending BR-12 (Decision 4a)
to include inactive placeholder PICs in the dropdown.

Full names requirement (Decision 4b) — recommended NOT hard-validating for a
space (mononymous names are common in Malaysia); label field clearly instead.
This part of the reasoning still applies under the final Option (c) approach.

---

## 6. M6-5 — Merge agreement_date + effective_date into "Date Signed"

⚠ Touches schema previously treated as frozen since M2 — needed explicit sign-off.

**Options considered:**
- (a) UI-only merge, keep both columns — zero risk, recommended default
- (b) Drop effective_date via real migration — risk depends on whether any
  historical row has differing values (F4, unresolved at draft time)
- (c) Keep both, stop writing effective_date — rejected, worst of both
- (d) Drop agreement_date instead — rejected, breaks year accessor and indexes

> **RESOLVED since original draft:** Amir verified all 10 live agreements
> have matching agreement_date/effective_date. Zero risk confirmed.
> Final decision: Option (b) APPROVED — actually drop effective_date.
> See final decisions at bottom.

**Decision 5b (naming, given F5's unused signed_date column):**
Recommended: label the UI field "Date signed", backed by agreement_date;
leave signed_date untouched and documented as known dead schema for future
cleanup. — APPROVED as recommended.

**Knock-on:** dateWarning() (line 207-216) compares effective_date to
expiry_date; must be rewritten to compare the merged date to expiry_date.
Reworks BR-09; behavior (soft warning, never blocks) unchanged.

**Files:**
- resources/views/components/⚡agreement-form.blade.php
- resources/views/components/⚡agreement-show.blade.php
- docs/qa/test-cases.md (update TC-030, add TC-068)
- docs/qa/traceability-matrix.md (reword BR-09, add BR-31)
- AGENTS.md (record the merge decision)
- docs/BUILD_PLAN.md (add M6)

**Tests:**
- test_date_signed_writes_to_both_agreement_date_and_effective_date (N/A
  under final Option b — effective_date is being dropped, not written to)
- test_editing_an_agreement_hydrates_date_signed_from_agreement_date
- test_expiry_before_date_signed_warns_but_still_saves (renamed)
- test_year_accessor_still_derives_from_the_merged_date
- test_detail_page_shows_one_date_signed_row

> **NOTE:** Since the final decision is Option (b), not (a), the actual
> migration and #[Fillable]/casts() changes needed for a real column drop
> must be specified fresh — this was not detailed in the original draft,
> which only fully specified Option (a). This is a gap for the plan revision
> to fill in properly, including a backup step before the migration runs on
> production.

---

## 7. M6-6 — DD/MM/YYYY throughout

**(a) Date INPUT** — the actual source of Intan's confusion. Native
`<input type="date">` widgets display in browser/OS locale; cannot be
overridden by any web code. Recommended: keep native inputs, add
"(DD/MM/YYYY)" label hint, echo the selected value in unambiguous d M Y form
beneath each input. — APPROVED.

**(b) Date DISPLAY** — already correct everywhere (F1). Recommended: keep
d M Y format (e.g. "18 Mar 2022") — safer than numeric format since an
alphabetic month cannot be misread in any locale. — APPROVED, keep as-is.

Introduce a single source of truth regardless:
resources/views/components/date.blade.php (`<x-date :value="..."/>`), so
format is enforced in one place, not hoped for across six call sites.

**Files:**
- resources/views/components/date.blade.php (new)
- resources/views/components/⚡agreements-index.blade.php
- resources/views/components/⚡agreement-show.blade.php
- resources/views/components/agreement-duration.blade.php
- resources/views/components/⚡agreement-form.blade.php (label hints only,
  lines 62-64 keep Y-m-d wire format — must not change)
- docs/HANDOVER.md, README.md (note browser-locale behavior)

**Tests (DateFormattingTest.php, new):**
- test_the_date_component_renders_day_month_year
- test_the_date_component_renders_a_dash_for_null
- test_no_view_renders_a_month_first_date_format (greps for m/d, n/j patterns)
- test_date_inputs_carry_a_dd_mm_yyyy_hint
- test_form_date_inputs_still_use_the_y_m_d_wire_format

**QA docs:** test-cases.md TC-069; traceability-matrix.md BR-32.

---

## 8. Original open decisions (for reference — see final answers at bottom)

- Decision 1 — Type filter survives Type column removal? Recommended (a) keep filter.
- Decision 2 — Duration partial-year handling? Recommended (a) never round.
- Decision 3 — Scope truncation length? Recommended (a) 80 characters.
- Decision 4 — What does "New PIC" create? Recommended (b) — SUPERSEDED, Amir chose (c).
- Decision 4a — Amend BR-12? Only relevant under (b) — MOOT under final (c).
- Decision 4b — Enforce full names by validation? Recommended (a) label + placeholder only.
- Decision 5 — UI-only merge or drop column? Recommended (a) for M6, defer (b) —
  SUPERSEDED, Amir approved (b) now after verifying live data.
- Decision 5b — Naming given signed_date exists? Recommended (a) — APPROVED as recommended.
- Decision 6 — resolvePartnerId() return type cleanup? Optional. — Amir: fix now.
- Decision 6a — Native inputs or JS datepicker? Recommended (a) native + label. — APPROVED.
- Decision 6b — Keep "18 Mar 2022" or switch to "18/03/2022"? Recommended (a) keep. — APPROVED.
- Decision 7 — Timezone fix (outside original 6 items)? Recommended (a) fix,
  with Amir's approval. — Amir: fix now.

---

# FINAL APPROVED DECISIONS (Amir, 2 Sep 2026)

These supersede any conflicting recommendation above.

| # | Decision | Final Answer |
|---|---|---|
| 1 | Type filter | **(a)** Keep filter, remove only the column |
| 2 | Duration rounding | **(a)** Never round — show exact years/months. Confirmed: all 10 live agreements currently have whole-year durations, so this displays cleanly as "(N years)" in practice today. |
| 3 | Scope truncation | **(a)** 80 characters |
| 4 | New PIC creation | **CHANGED — Option (c)**: PIC is a plain name value, NOT a User record. No login, no account, no email, nothing tied to the users/login table. PIC works exactly like Partner structurally — existing/new toggle, "new" just captures a name string. Reasoning: PICs are external stakeholders; the system does not yet support any non-Legal-staff login or self-service view, and Amir does not want PIC records structured in a way that leans toward that future without an explicit, separate decision to build it. Logged as a "Future consideration" in AGENTS.md (PIC self-service view), not built now. |
| 4a | BR-12 amendment | **MOOT** — no longer applicable, since Decision 4 no longer touches the users table |
| 4b | Full names, validation style | Reasoning still applies under Option (c): label + placeholder only, do not hard-validate for a space (mononymous names are common in Malaysia) |
| 5 | Date merge | **CHANGED — Option (b) APPROVED**: Actually drop the effective_date column via a real migration. Verified directly against live Laravel Cloud data: all 10 existing agreements have agreement_date exactly equal to effective_date, zero mismatches, confirmed by Amir manually. Requires a database backup step before the migration runs against production. |
| 5b | Naming, given signed_date exists | **(a)** UI field labeled "Date Signed," backed by agreement_date. Unused signed_date column left as-is, documented as known dead schema for future cleanup. |
| 6 | resolvePartnerId() return type | **Fix now** — tighten to ?int while already in that file for other M6 changes |
| 6a | Date inputs | **(a)** Keep native date picker, add "(DD/MM/YYYY)" label + confirmation text showing selected date in unambiguous d M Y form beneath each input |
| 6b | Date display everywhere else | **(a)** Keep "18 Mar 2022" format — already correct, genuinely safer than numeric format regardless of locale |
| 7 | Timezone | **Fix now** — set APP_TIMEZONE to Asia/Kuala_Lumpur. Re-check M5-8 boundary tests (the 89/90/91-day staleness test, expiry-today characterisation test) still pass, since they reason about "the start of the day." |

## What needs fresh specification in the plan revision (not fully covered in the original draft)

1. **M6-4, fully rewritten for Option (c):** Exact schema change needed —
   likely a new nullable `pic_name` string column on `agreements`, replacing
   or alongside `pic_user_id`. Specify whether `pic_user_id` should be
   dropped, kept nullable and unused, or something else. Confirm impact on
   BR-12, BR-18, the `pic()` relation, and any existing tests referencing
   `pic_user_id` or the PIC dropdown sourcing from `users`.

2. **M6-5, fully specified for Option (b):** The actual migration to drop
   `effective_date`, plus every place `#[Fillable]`, `casts()`, the factory,
   `AgreementCastsTest`, and any other reference to `effective_date` needs
   updating. Include a database backup step before the migration runs
   against the live Laravel Cloud database.

3. **Handoff structure:** Given M6-4 and M6-5 now BOTH involve real schema
   changes on a live production database (not just M6-5 as in the original
   draft), assess whether to split implementation into multiple handoffs —
   similar to the M5 pattern (handoff-m5-defects.md, handoff-m5a.md,
   handoff-m5b.md) — isolating schema-changing work from the safer,
   UI-only items (M6-1, M6-2, M6-3, M6-6).