# OpenCode Go handoff — M9: custom dropdown, bold fix, label rename

> **Milestone:** M9 · **Implements:** `docs/architecture-plan-m9.md` (APPROVED, 7 Sep 2026)
> **Precondition:** M1–M8 merged and green. Verified 7 Sep 2026 at 180 tests passing.
> **Decisions:** M9-1 through M9-3, all approved by Amir on 7 Sep 2026. None is reopenable.
> **Nature of this milestone:** a bug fix (M8-2's bold didn't render in the list), a label rename, and one new reusable UI component used in three places. No schema, no migration, no production-data risk.
> **Paste the block below into OpenCode Go from the repo root.**

```text
Role: You are the SENIOR DEV on the CLSD Agreement Register (Laravel 13 +
Livewire 4, SQLite locally / MySQL on Laravel Cloud production). Amir is the
PM: implement exactly what the approved plan says — do not redesign, do not
add scope.

Context: Read AGENTS.md, docs/BUILD_PLAN.md, and docs/architecture-plan-m9.md
(APPROVED, 7 Sep 2026). That plan is the contract for this handoff. If
something it does not cover comes up, ask Amir — do not guess.

Precondition: M1-M8 must already be merged. `composer test` should be green
at 180 tests before you start. If it is not, stop and tell Amir.

IMPORTANT CONTEXT: M8-2 (bold campus codes) was reported as complete in the
M8 handoff, but Amir's own manual verification (with screenshots) after M8
shipped showed the bold styling did NOT actually render in either the
Register list or the Create form's Campus dropdown. This handoff both fixes
that regression (M9-1) and solves its root cause properly for the dropdown
specifically (M9-3) — native HTML <option> elements cannot reliably render
inline styling like bold text; this is a real browser/HTML limitation, not
a CSS mistake, and the fix for the dropdown is a custom-drawn component, not
a different CSS selector.

Build the three items in this order — M9-1 and M9-2 are small and
independent; M9-3 is the bulk of the work and should be fully verified in
all three of its target locations before considering the handoff complete.

═══════════════════════════════════════════════════════════════
M9-1 — Fix: bold campus code in the Register list
═══════════════════════════════════════════════════════════════

1. Open resources/views/components/⚡agreements-index.blade.php and find the
   Campus column's current markup. Do NOT assume the <strong> tag from M8-2
   is already there and just isn't rendering for some CSS reason — actually
   read the current file content first. It's possible the M8 handback
   report's claim was inaccurate, or the tag is present but something else
   (e.g. Tailwind's Preflight/base styles resetting font-weight, or a
   typo in the class name if font-bold was used instead of <strong>) is
   overriding it. Identify the ACTUAL cause before fixing it — do not
   blindly re-add markup that may already exist.

2. Fix it so the campus CODE only (e.g. "MFI", not the full "Malaysia
   France Institute") renders visibly bold in the rendered HTML, with the
   full name in normal weight immediately after it, matching the existing
   "{{ $campus->code }} — {{ $campus->name }}" pattern already established
   elsewhere in this codebase (e.g. the current dropdown's option text
   format).

Tests:
- test_campus_code_renders_bold_in_the_register_list
  This MUST assert on the actual rendered HTML tag, not just that the text
  "MFI" is present somewhere on the page — e.g.
  $this->assertStringContainsString('<strong>MFI</strong>', $html) or
  equivalent, using Livewire::test(...)->html() the same way other markup-
  specific tests in this codebase already do (see the M6a handoff's
  wire:model.live regression tests for the pattern of asserting on raw
  markup rather than just visible text).

═══════════════════════════════════════════════════════════════
M9-2 — Rename "Campus" to "Campus / Department"
═══════════════════════════════════════════════════════════════

Label/text change only. Do NOT touch the campuses table, the Campus model
class name, the campus_id column, or any internal code reference to
"campus" — those all stay exactly as they are. This changes only what
label text a human sees.

1. resources/views/components/⚡agreements-index.blade.php: the list
   column header text "Campus" becomes "Campus / Department".

2. resources/views/components/⚡agreement-form.blade.php: the form field
   label "Campus" becomes "Campus / Department".

3. resources/views/components/⚡agreement-show.blade.php: check if "Campus"
   appears as a label on the detail page; if so, same rename. If the
   current file doesn't label it that way (e.g. it's unlabeled or
   differently worded), leave it as-is and note this in your report rather
   than inventing a label that wasn't there before.

Tests:
- Update or add a test confirming the list header and form label both read
  "Campus / Department". If any EXISTING test asserts the old text
  "Campus" as an exact header/label match, update that specific assertion
  — grep first:
    grep -rn "'Campus'" tests/Feature/Agreement/
  Only change assertions that specifically check this exact label text; do
  not touch anything else in those test files.

═══════════════════════════════════════════════════════════════
M9-3 — A reusable custom dropdown component
═══════════════════════════════════════════════════════════════

--- Step 0: verify your tooling assumption before building ---

Check whether Alpine.js is actually available in this project before
assuming it. AGENTS.md's stack description confirms Livewire 4 + Tailwind 4
+ Vite, but does NOT explicitly confirm Alpine.js is set up, even though
Alpine commonly pairs with Livewire. Check package.json and grep existing
Blade files for x-data or similar Alpine directives:
    grep -rn "x-data\|x-show\|x-on:" resources/views/ | head -5
If Alpine is already in use elsewhere in this codebase, build the dropdown
using Alpine for its open/close/click-outside state, consistent with
existing patterns. If Alpine is NOT present at all in this project, either
(a) add it properly (check if it's already an npm dependency even if
unused, or whether it needs installing — remember .npmrc sets
ignore-scripts=true, so any new dependency must not require a postinstall
script), or (b) build the open/close interaction using a Livewire-only
approach (a public boolean property toggled by wire:click, with the
dropdown's visibility controlled by a Blade @if). Choose whichever fits
this specific codebase's actual current state — report which path you took
and why in your handback summary, since this is a real judgment call this
plan intentionally leaves to you rather than guessing at Alpine's presence.

--- Step 1: build the component ---

Create a new reusable Blade component, e.g.
resources/views/components/dropdown-select.blade.php (or a name consistent
with this project's existing component-naming conventions — check what
other shared components like agreement-duration.blade.php or date.blade.php
are named and follow that pattern).

The component's external contract, which the three call sites below all
need to work with:
- Accepts a list of options to display.
- Accepts the currently-selected value (needed for edit mode, where a
  value is already set and must show as pre-selected when the dropdown is
  first rendered).
- Binds the selection to a Livewire property, consistent with how
  wire:model/wire:model.live already work on every other field in this
  form.
- Supports per-option custom markup, NOT just plain text — because
  Campus/Department needs bold-code-plus-name, while Partner and PIC need
  plain text only. Do not hardcode one rendering style into the component;
  give call sites a way to control how each option's content renders (e.g.
  a Blade slot per option, or passing pre-rendered HTML strings for each
  option — implementer's choice of exact mechanism).

Interaction requirements:
- Click to open, click an option to select and close. NO search/type-to-
  filter — this was explicitly decided against; do not add a text input
  inside this component.
- Click-outside-to-close.
- Escape key closes it.
- These baseline UX behaviors are expected even though not explicitly
  itemized in the original request, because a dropdown that can only be
  closed by selecting something is worse than the native <select> it's
  replacing.

--- Step 2: use it in three places ---

2a. Campus/Department field in ⚡agreement-form.blade.php: replace the
    native <select> with the new component. Each option renders as
    <strong>{{ $campus->code }}</strong> — {{ $campus->name }}. This field
    has no existing/new toggle — it's always a single fixed-list picker,
    the simplest of the three replacements.

2b. Partner "Existing partner" picker in the same file: replace the native
    <select> (currently inside the @if ($partnerMode === 'existing') block)
    with the new component. Options render as PLAIN partner names, no bold
    — Amir explicitly confirmed no special formatting is wanted here, this
    is for visual/interaction consistency only.

    CRITICAL: do NOT touch the partnerMode radio toggle itself, and do NOT
    touch the "New partner" mode's plain text input (newPartnerName) or
    anything in that branch. Only the "Existing" side's picker mechanism
    changes.

2c. PIC "Existing PIC" picker in the same file: same treatment as Partner
    — replace the native <select> inside the picMode === 'existing' branch
    with the new component, plain names, no bold.

    CRITICAL: do NOT touch the picMode radio toggle itself, and do NOT
    touch the "New PIC" mode's plain text input (pic_name) or anything in
    that branch. Only the "Existing" side's picker mechanism changes.

--- Step 3: tests ---

- test_campus_department_dropdown_renders_bold_codes (HTML-content
  assertion on the rendered <strong> tag, same style as M9-1's test)
- test_selecting_a_campus_from_the_custom_dropdown_sets_campus_id
- test_selecting_an_existing_partner_from_the_custom_dropdown_sets_partner_id
- test_selecting_an_existing_pic_from_the_custom_dropdown_sets_pic_name
- test_new_partner_mode_still_shows_a_plain_text_input (REGRESSION GUARD —
  confirms the existing/new toggle and the New-mode text input are
  completely untouched by this change)
- test_new_pic_mode_still_shows_a_plain_text_input (same, for PIC)
- test_editing_an_agreement_preserves_its_existing_campus_selection_in_the_custom_dropdown
  (open the edit form for an agreement that already has a campus_id set;
  confirm the custom dropdown shows that campus as pre-selected, not blank
  — this is the kind of thing that's easy to get wrong when swapping a
  native <select>'s built-in "selected" attribute behavior for a custom
  component)

Also re-run the EXISTING partner-mode and PIC-mode tests from
AgreementFormTest.php (the M6-3 wire:model.live regression tests, the M6b
PIC mode tests) and confirm they still pass — these should be unaffected
since this handoff explicitly does not touch the toggle mechanism, but
confirm rather than assume.

═══════════════════════════════════════════════════════════════

After all three items:

1. Run `composer test` — laravel/pao prints compact JSON when it detects an
   AI agent; parse the "result" field. Report the exact before/after count
   (started at 180).

2. Run `vendor\bin\pint` on every PHP/Blade file you touched.

3. docs/qa/test-cases.md and docs/qa/traceability-matrix.md: check the
   current highest TC-### and BR-## numbers before adding new entries. Add
   an entry for the new custom-dropdown UI pattern as a business/UI rule
   worth tracing (e.g. "custom-rendered dropdowns used wherever per-option
   formatting is required, since native <option> styling is unsupported by
   browsers").

Approved decisions relevant to this handoff (do not reopen):
- M9-1: this is a bug fix to something M8-2 was supposed to already do.
  Find the actual cause, don't just re-apply markup blindly.
- M9-2: label-only rename. No schema/model/column name changes anywhere.
- M9-3: exactly three call sites (Campus/Department, Partner-existing,
  PIC-existing). No search/filter behavior. No changes to the existing/new
  toggle mechanism or the "new" text inputs in Partner/PIC. Bold formatting
  ONLY for Campus/Department's code portion — Partner and PIC options stay
  plain text.

Do not change:
- Any migration or schema. This handoff has zero database changes.
- The partnerMode/picMode toggle logic, or the "New partner"/"New PIC" text
  input fields — only the "Existing" side's picker mechanism changes.
- Any other field in the form beyond Campus/Department, Partner-existing,
  and PIC-existing.
- The Agreement global scope, the three-role enum, or anything from M1's
  auth.
- Do not run npm scripts (ignore-scripts=true) or touch vite.config.js,
  UNLESS Step 0 determines Alpine.js genuinely needs to be added as a
  dependency — if so, verify it can be added without requiring a
  postinstall script, given ignore-scripts=true is a hard project
  constraint, and report explicitly what you did here since this touches a
  standing project rule.

Acceptance checks:
- `composer test` green (parse the JSON output; report the result field and
  the exact final count).
- `vendor\bin\pint` run on all PHP/Blade you touched.
- Manual: Register list shows campus codes in visible bold; Create form's
  Campus/Department field is a custom dropdown showing bold codes; Partner
  and PIC "existing" pickers are also custom dropdowns (plain text, no
  bold) but otherwise behave identically to before; the existing/new toggle
  for both Partner and PIC still works exactly as before, including the
  M6-3 defect-fix behavior (switching to "New" mode reveals the text input
  immediately, no submit required); editing an agreement that already has
  a campus assigned shows it correctly pre-selected in the new dropdown.
- `grep -n "Campus / Department" resources/views/components/⚡agreements-index.blade.php resources/views/components/⚡agreement-form.blade.php`
  shows the renamed label in both files.

After editing:
- List every file created/modified.
- Report exact test names and results — before (180) and after count.
- Explicitly state whether Alpine.js was already present or needed to be
  added, and if added, confirm it did not require a postinstall script.
- Explicitly state what the actual root cause of M9-1's bug was (not just
  that it's fixed now).
- Tell Amir what to manually check in the browser, in order matching the
  three items above, and specifically to test the edit-mode pre-selection
  case for Campus/Department since that's the easiest thing to get subtly
  wrong in this kind of component swap.
- Do not commit unless Amir says to.
```

---

## Notes for Amir

- **M9-1 is worth a genuinely close look, not just a re-check.** OpenCode Go's M8 report claimed the bold campus code was implemented, and your screenshot showed it wasn't rendering at all — that's a real discrepancy between what was reported and what actually shipped. Worth asking specifically what the root cause turned out to be when this comes back, not just confirming the fix works now, since understanding *why* the discrepancy happened is useful for trusting future reports.

- **The edit-mode pre-selection test for Campus/Department is the one detail most likely to get subtly wrong** in a custom dropdown swap — native `<select>` elements handle "this option should show as already selected" completely automatically via the `selected` HTML attribute; a custom component has to replicate that behavior deliberately. Worth testing this yourself specifically: open an existing agreement that already has a campus, click Edit, and confirm the dropdown shows the right campus already chosen rather than defaulting to blank or the first option in the list.

- **The Alpine.js question is a real unknown**, not a formality — this project's stack has never explicitly confirmed Alpine is set up, despite it being part of your original "TALL stack" naming. Whatever OpenCode Go finds and does here is worth reading in its report, since it's the first time this specific tooling question has come up in six-plus milestones.

- **This is a good one to watch for scope creep on your own end too** — it would be easy, once a nice custom dropdown exists, to think "why not use it everywhere" (Type, Sector, Document Status, etc.). The plan deliberately limits this to three specific places for a specific reason (bold formatting need, or explicit visual-consistency ask) — worth resisting the urge to expand this further without going through the same scoping discipline as everything else, even though it might look tempting once you see the first one working.
