# M9 — Custom dropdown component, bold display fix, Campus/Department rename

> **Status:** Final, ready for handoff drafting.
> **Milestone:** M9 — follow-up fix to M8-2 (bold didn't render), plus a small
> new reusable UI component.
> **Role:** Lead Architect (plan only, no implementation)
> **Date:** 7 Sep 2026

---

## Context

M8 shipped and was verified live, but Amir's own manual check (with
screenshots) found M8-2's bold-campus-code treatment did not actually render
in either the Register list or the Create form's Campus dropdown — the list
issue is a real regression to fix (the intended behavior never showed up at
all), and the dropdown issue is the exact known limitation OpenCode Go
itself flagged in its M8 handback report: HTML `<option>` elements do not
reliably support inline styling like bold text across browsers, no matter
what CSS is applied to them.

Rather than accept "no bold in the dropdown" as a permanent limitation,
Amir confirmed this matters enough to build a proper fix: a **custom-drawn
dropdown component** that the app fully controls the rendering of, replacing
the native `<select>` specifically where visual formatting (bold text) is
wanted. Scope grew naturally from "fix one dropdown" to "build one reusable
component, use it in three places" once the underlying cause (native
`<select>` styling limitations) was understood — building it once as a
shared component is the correct call architecturally, not scope creep.

This is the project's first custom-drawn dropdown; every dropdown before
this has been a plain native `<select>`. Worth treating this as a genuinely
new UI pattern, not a routine field tweak — get the interaction contract
right once, since M9-3 reuses it in three places immediately.

---

## 1. M9-1 — Fix: bold campus code in the Register list

### The problem

M8-2 specified bolding the campus code (e.g. "MFI") in the Register list's
Campus column, leaving the full institute name in normal weight. Amir's
screenshot after M8 shipped shows neither the code nor the name in bold —
the change did not take effect in the list view, despite the list view
being a plain `<td>` with no `<option>`-style limitation to blame.

### Fix

Re-check `resources/views/components/⚡agreements-index.blade.php`'s Campus
column markup directly against what M8-2 specified
(`<strong>{{ $campus->code }}</strong> — {{ $campus->name }}` or
equivalent). Do not assume the M8 handback report's claim that this was
done correctly — verify the actual current markup first, since the
screenshot evidence contradicts that claim. This may be a simple missed
line, a CSS specificity issue overriding the bold, or something else — the
implementer should identify the actual cause rather than blindly re-adding
`<strong>` tags that may already be present but not working.

### Files

- `resources/views/components/⚡agreements-index.blade.php`

### Tests

- `test_campus_code_renders_bold_in_the_register_list` — an HTML-content
  assertion (e.g. `assertSee('<strong>MFI</strong>', false)` or equivalent,
  checking the raw rendered tag is actually present, not just checking the
  text "MFI" appears without confirming it's wrapped)

---

## 2. M9-2 — Rename "Campus" to "Campus / Department"

### Decision (resolved 7 Sep 2026)

MCI, CIL, and CoRI (added in M7-6) are departments/centres, not physical
campuses, and the existing label "Campus" understates what the dropdown
actually contains now that non-campus entries are mixed into the same list.
Rename the label everywhere it appears as user-facing text.

**Note — this is a label/UI-text change only.** The underlying `campuses`
table name, the `Campus` model class, the `campus_id` column, and every
internal reference in code stay exactly as they are. Renaming the actual
table/model/column would be a much larger, unnecessary change for a
display-only naming clarification — do not touch schema or model naming for
this item.

### Files

- `resources/views/components/⚡agreements-index.blade.php` (list column
  header: "Campus" → "Campus / Department")
- `resources/views/components/⚡agreement-form.blade.php` (form field label:
  "Campus" → "Campus / Department")
- `resources/views/components/⚡agreement-show.blade.php` (detail page field
  label, if "Campus" appears there as a label — check current markup)

### Tests

- `test_the_list_and_form_both_label_the_field_campus_slash_department`
  (or split into two if that reads more naturally against the existing
  test file structure)

---

## 3. M9-3 — A reusable custom dropdown component

### The underlying problem, precisely

Native `<select>`/`<option>` rendering is controlled by the browser/OS, not
by the application's own CSS — this is why M8-2's `<strong>` tags inside
`<option>` elements did not visibly render despite being present in the
markup (confirmed directly by Amir's screenshot). This is a genuine,
well-known HTML limitation, not a CSS mistake to fix with a different
selector or `!important`.

### Decision (resolved 7 Sep 2026)

Build **one reusable Blade/Livewire component** for a custom, app-drawn,
click-to-open/click-to-select dropdown, and use it in exactly three places:

1. **Campus/Department** picker (Create/Edit form) — options rendered as
   `**{code}** — {name}` (bold code, normal-weight name).
2. **Partner "Existing partner"** picker — options rendered as plain
   partner names, no bold (there is no code-like field to distinguish;
   Amir confirmed this is purely for interaction/visual consistency, not
   because a bold treatment is wanted here).
3. **PIC "Existing PIC"** picker — same as Partner: plain names, no bold,
   consistency only.

**Explicitly decided: no search/type-to-filter.** Amir confirmed the
component should behave like the current native dropdowns —
click to open, click an option to select, nothing more. Do not add a text
input or filtering behavior; that would be new functionality beyond what
was asked for, and Campus/Department's ~20 options is not so many that
click-to-scan is a real usability problem.

**Explicitly decided: this does NOT change the existing/new toggle
mechanism anywhere.** Partner and PIC's `partnerMode`/`picMode` radio
toggles (existing vs. new) are completely untouched by this milestone. The
custom dropdown component only replaces what happens on the "existing"
side of each toggle — the "new" side's plain text input
(`newPartnerName`/`pic_name`) stays exactly as it is today, unchanged. This
is purely a visual/interaction swap of the picker mechanism, not a change
to the form's overall structure or data flow.

### Component design

Recommended structure (implementer has latitude on exact internal
mechanics, but the external contract below is fixed):

- A new Blade component, e.g.
  `resources/views/components/dropdown-select.blade.php`, accepting:
  - A collection/array of options, each with at minimum a `value` and a way
    to render its display content (plain text for Partner/PIC, or
    bold-code-plus-name markup for Campus/Department — the component should
    accept a way to pass custom per-option markup rather than being
    hardcoded to one rendering style, since it's reused three different
    ways)
  - The currently-selected value (for edit mode, where a value is already
    set)
  - A Livewire property name to bind the selection to, consistent with how
    `wire:model`/`wire:model.live` already works elsewhere in this form
- Click-to-open behavior: likely Alpine.js (already available per the TALL
  stack, per AGENTS.md's stack description — `Tailwind CSS 4 + Vite`,
  Livewire's typical pairing with Alpine for this kind of client-side
  open/close state), OR a Livewire-native approach if Alpine isn't already
  used elsewhere in this codebase. **Check whether Alpine.js is already
  present in this project's actual dependencies/usage before assuming it is
  available** — the AGENTS.md stack line doesn't explicitly confirm Alpine
  the way it confirms Livewire/Tailwind; verify against `package.json` and
  existing Blade files before building on an assumption.
- Click-outside-to-close and basic keyboard accessibility (Escape to close,
  at minimum) are reasonable baseline expectations for any custom dropdown,
  even though not explicitly requested — a dropdown that can only be closed
  by selecting an option is a worse experience than what it's replacing.

### Files

- `resources/views/components/dropdown-select.blade.php` (new, or similar
  name — implementer's choice of exact filename, consistent with this
  project's existing component-naming conventions)
- `resources/views/components/⚡agreement-form.blade.php` (Campus/Department
  field, Partner "existing" picker, PIC "existing" picker — all three
  replaced with the new component; the "new" text inputs and the
  existing/new radio toggles themselves are NOT touched)

### Tests

- `test_campus_department_dropdown_renders_bold_codes` (HTML-content
  assertion, same style as M9-1's test)
- `test_selecting_a_campus_from_the_custom_dropdown_sets_campus_id`
  (behavioral — confirms the swap didn't break the actual data binding)
- `test_selecting_an_existing_partner_from_the_custom_dropdown_sets_partner_id`
- `test_selecting_an_existing_pic_from_the_custom_dropdown_sets_pic_name`
- `test_new_partner_mode_still_shows_a_plain_text_input` (regression guard —
  confirms the toggle/new-entry flow is untouched)
- `test_new_pic_mode_still_shows_a_plain_text_input` (regression guard)
- `test_editing_an_agreement_preserves_its_existing_campus_selection_in_the_custom_dropdown`
  (edit mode pre-selection works correctly)

**QA docs:** test-cases.md (next free TC-###); traceability-matrix.md (next
free BR-##, noting this as a UI-pattern rule: "custom-rendered dropdowns
used wherever per-option formatting is required, since native `<option>`
styling is unsupported").

---

## 4. Handoff structure

All three items are display/interaction-only — no schema, no migration, no
production-data risk. M9-3 is the substantial item (a new reusable
component used in three places) but carries no data risk, only
implementation complexity.

**Recommendation: one single handoff**, `docs/handoff-m9.md`, covering all
three items, same reasoning as M7 and M8 — nothing here needs risk
isolation via a multi-handoff split.

Sequencing within the handoff: M9-1 (the list bug fix) and M9-2 (the label
rename) are small and independent — do these first, quickly. M9-3 (the new
component) is the bulk of the work and should be verified working in all
three of its target locations before the handoff is considered complete,
since a component that works in isolation but breaks Campus/Department's
edit-mode pre-selection (or similar) would be a regression against
already-working functionality.
