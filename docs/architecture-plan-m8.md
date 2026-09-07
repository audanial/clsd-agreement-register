# M8 — Agreement type cleanup, archiving, and display polish (architecture plan)

> **Status:** Final, ready for handoff drafting.
> **Milestone:** M8 — follow-up feedback and one genuinely new feature (archiving).
> **Role:** Lead Architect (plan only, no implementation)
> **Date:** 7 Sep 2026

---

## Context

M1–M7 are complete, deployed, and verified against real production data on
Laravel Cloud. M8 gathers a second round of feedback plus one substantial new
feature: **archiving**, which — on inspection of the live codebase — turns
out to not exist as a usable feature at all yet, despite `archived_at`
existing as a column since M2 and being referenced in M2/M3's planning
documents as "future work." Amir confirmed directly (checked in the live
`legal`-role UI, 7 Sep 2026) that no archive action currently exists anywhere
for Legal to use.

Three of M8's four items are small, low-risk, display/data changes. The
fourth (archiving) is genuinely new functionality with a real design behind
it, arrived at through several rounds of grounding against actual historical
data (a 470-row, 2022–2026 Excel export of UniKL's collaboration history)
and a live production data check, plus a deliberate second-opinion review on
two sub-decisions.

---

## 1. M8-1 — Agreement Type: remove MOC

### Decision (resolved 7 Sep 2026)

Reduce the Type dropdown from 7 options to 6: **LOI, MOA, MOU, NDA, SEA,
ADDENDUM.** `MOC` is removed as a selectable type going forward.

### Grounding — verified, not assumed

Two checks were run before approving this, rather than trusting the
"MOC is basically MOA" framing at face value:

- **A 470-title sample** from UniKL's actual 2022–2026 collaboration
  records showed exactly **one** title ever used `MOC` as its type prefix
  (`"MOC (Memorandum of Cooperation) University of Colma"`, from the 2022
  list) — confirming MOC was never a meaningfully used category in
  practice.
- **A live production check**, run directly against the Laravel Cloud
  database via `Agreement::withoutGlobalScope(...)->where('type', 'MOC')`,
  returned **zero rows.** The 2022–2026 spreadsheet data was never imported
  into the live CLSD system — production only contains agreements entered
  manually since deployment, none typed MOC.

**Conclusion: this is a pure, zero-risk, forward-only change.** No existing
production row needs to be touched, relabeled, or migrated.

### The bracketed-subtype convention (Scenario A, confirmed against real data)

Separately, Amir raised whether the system should gain a structured
"cooperation subtype" field (e.g., so picking `MOA` reveals a second field
for "Memorandum of Cooperation," "Collaboration Agreement," etc., which the
system would then assemble into the title automatically) versus leaving this
as Legal's own established title-writing convention (typing the whole
bracketed detail into the existing free-text Title field by hand, e.g.
`"MOA (Memorandum of Cooperation) Awan Byte Technologies Sdn Bhd"`).

**Resolved: no new field.** The same 470-title sample showed **58 titles
(≈12%) already use this exact bracket convention**, but the actual subtype
values are highly varied and mostly one-off — 14+ distinct subtypes across
58 occurrences ("SEA," "Erasmus+," "Memorandum of Cooperation," "Tripartite
Agreement," "Confidentiality Agreement," several single-occurrence phrases,
one nearly a full sentence naming four organizations). This is free-form
descriptive text Legal already writes correctly and consistently — not
categorical data suited to a fixed dropdown. Forcing it into a structured
field would either require constant option additions for one-off cases, or
Legal picking "Other" and typing free text anyway, which just re-implements
today's convention with more steps. **No code change beyond removing MOC**
— Legal continues typing the bracketed convention into Title exactly as
today. This should be noted in `docs/HANDOVER.md` as a documented
convention for Intan, not enforced by the system.

### Implementation

- `resources/views/components/⚡agreement-form.blade.php`: remove the
  `<option value="MOC">MOC</option>` line from the Type `<select>`.
- Validation rule: `'type' => ['required', 'in:LOI,NDA,MOA,MOU,SEA,ADDENDUM']`
  (MOC removed from the `in:` list).

### Files

- `resources/views/components/⚡agreement-form.blade.php`

### Tests

- `test_moc_is_not_an_option_in_the_type_dropdown`
- `test_type_validation_rejects_moc`
- Grep existing tests for any reference to `'MOC'` as a valid type before
  finishing — update or remove any test that assumed MOC was accepted.

---

## 2. M8-2 — Bold campus abbreviations

### Decision (resolved 7 Sep 2026)

In both the Register list (Campus column) and the Create/Edit form's Campus
dropdown, the campus **code/abbreviation** (e.g. "MIIT," "ACE," "CoRI,"
"MIDI") renders in **bold**, distinguishing it from the full institute name
that follows.

Pure display change. No schema, no logic. Matches the existing
`{{ $campus->code }} — {{ $campus->name }}` pattern already established in
the dropdown (per `handoff-m6b-pic.md`'s campus rendering and M7's dropdown
work) — only the `code` portion gains a `font-bold` (or `<strong>`) wrapper.

### Files

- `resources/views/components/⚡agreements-index.blade.php` (Campus column)
- `resources/views/components/⚡agreement-form.blade.php` (Campus dropdown —
  note: HTML `<option>` elements have limited style support in some
  browsers; if bold doesn't render reliably inside `<option>` tags,
  implementer should flag this to Amir rather than silently shipping a
  dropdown where the bold doesn't actually show, since `<option>` styling
  is a genuine, well-known cross-browser limitation, not a straightforward
  CSS class application)

### Tests

None required — pure CSS/markup, no behavior change. Confirm existing
functional tests (campus filtering, dropdown population) are unaffected.

---

## 3. M8-3 — Archiving: automatic (expiry) and manual (early termination)

### Context: this is new functionality, not a fix

Verified directly in the live application (7 Sep 2026, logged in as
`legal`): **no archive action exists anywhere in the current UI.** The
`archived_at` column and `notArchived()`/`archived()` scopes have existed
since M2, and M3's planning explicitly deferred "archive()/unarchive()
methods, activity logging, status-transition rules" as out of scope at that
time. That deferred work was never picked up in a later milestone. M8
builds it now, for the first time, with two distinct triggers rather than
the single manual button originally sketched in early planning.

### The business need, confirmed by Amir

Two genuinely different situations both need to result in an agreement
being archived:

1. **Natural expiry** — the agreement reaches its `expiry_date` and simply
   runs its course. Automatic, no human action needed.
2. **Early termination** — Amir confirmed this is a real, recurring
   situation ("I have seen some agreements getting terminated because of
   breach of terms, etc."), where Legal proactively ends an agreement
   before its natural expiry date. This currently has **no path in the
   system at all** — not even a manual one.

### Decision — `archive_reason`, two values

A new column, `archive_reason`, distinguishes *why* an agreement was
archived — not just that it was. Two values:

- `expired` — set automatically when `expiry_date` passes.
- `terminated` — set manually by Legal, for early termination (breach or
  otherwise).

**Rejected alternative:** treating "terminated" and "cancelled" as two
separate values. Considered and rejected after review (both independently
by the architect and via a second-opinion check) — in standard contract
practice, "termination" is the correct general term for a valid agreement
ending early for any reason (breach, mutual agreement, termination for
convenience), while "cancelled" is a narrower, distinct concept typically
meaning an agreement is voided or was withdrawn before ever taking effect —
not a synonym for early termination. Using one ambiguous, competing value
risks a database field with unclear meaning, exactly the "table lies to
future readers" problem M6b's PIC decision worked through.

**Deferred, not built now:** a `termination_basis` sub-field (e.g., breach /
mutual_agreement / termination_for_convenience / other) for tracking *why*
a termination happened, once "it was terminated" alone is recorded. This is
a real, plausible future need, but not a current, confirmed one — recorded
as a Future Consideration in AGENTS.md, not built speculatively now. If
Legal later confirms this distinction matters in practice, it is a small,
additive migration at that point; nothing in this design blocks it.

### Decision — the automatic trigger mechanism

**A real, permanent database write, on a daily scheduled task — not a
display-time filter.**

Rejected alternative: filtering the list view at query time (e.g., "show
archived OR expired" without ever writing to the database). Rejected
because it would leave `archived_at` and `archive_reason` permanently null
for expired agreements — the record would only *behave* as archived when
someone happened to view the list, while the underlying data never actually
reflected that fact. This is the same "table lying to future readers"
problem as the PIC/`users` discussion, applied to a different column:
`archive_reason` would claim to be trustworthy, queryable data, but for
every automatically-archived agreement it would in fact be empty unless
someone happened to load the list first.

Confirmed via a deliberate second-opinion review that Laravel Cloud
supports scheduled/cron-style tasks for an application cluster, and that a
daily scheduled job is the architecturally correct choice for a fact that
must be true regardless of user activity — not something that should
depend on whether anyone happens to open the list page that day.

**Boundary condition:** `expiry_date <= today()` — deliberately matching
the existing `isExpired()`/`expired()` scope's boundary rule from Decision
M5-8 ("an agreement expires at the START of its expiry_date"), rather than
introducing a separate, inconsistent boundary for archiving specifically.
Using a different boundary here (e.g., waiting one extra day) was
considered and rejected — it would create a confusing window where
`isExpired()` already reports `true` but the agreement is not yet archived,
with no functional reason for the gap.

### Implementation — the scheduled command

A new Artisan command, run daily via Laravel's task scheduler, shortly
after midnight in `Asia/Kuala_Lumpur` (per the M6-7 timezone decision — the
scheduled task's timing should respect the same timezone convention already
established for the rest of the system's date handling).

The command performs one idempotent update:

```php
Agreement::query()
    ->whereNull('archived_at')
    ->whereNotNull('expiry_date')
    ->whereDate('expiry_date', '<=', today())
    ->update([
        'archived_at' => now(),
        'archive_reason' => 'expired',
    ]);
```

**Safety properties, worth stating explicitly:**
- `whereNull('archived_at')` makes this safe to run more than once — an
  already-archived agreement (whether auto- or manually-archived) is never
  touched again. This also means the scheduled job can never overwrite a
  `terminated` reason with `expired` — a manually terminated agreement
  already has `archived_at` set, so the condition excludes it.
- Use Laravel's `withoutOverlapping()` scheduler modifier as an additional
  guard against two overlapping runs, though the database condition above
  is the real protection, not the scheduler's own locking.
- This should be a direct `Eloquent::update()` batch query — NOT a loop
  over individual models calling `->save()` one at a time, and NOT
  `DB::table('agreements')->update(...)` either (the latter would bypass
  the pending-visibility global scope; while archiving logic arguably
  doesn't care about that particular scope, staying consistent with the
  project's established "never use DB::table('agreements') anywhere in the
  app" rule from M2/M3 avoids relitigating that guardrail here).

**Activity logging:** the automatic archive action should write an activity
feed entry (via the existing `RecordAgreementActivity` action from M3),
same as any other status-relevant change. Without this, `archived_at`
becomes a fact only visible by inspecting raw data — Intan looking at an
agreement's activity feed six months from now should be able to see *when
and why* it was archived, not just infer it from an absent explanation.
Since the scheduled command may process many agreements in one run, this
needs one activity row per archived agreement, not one row summarizing "N
agreements archived" — matching the existing one-row-per-change convention
established in M3.

### Implementation — the manual path

A new "Archive this agreement" action on the agreement detail page,
visible only to `legal`/`admin` (matching the existing `canWrite()`
permission boundary already enforced throughout the app), for agreements
not already archived.

- Clicking it should require Legal to confirm the action is for early
  termination — the UI does not need to ask for a *basis* (per the
  deferred `termination_basis` decision above), just confirm intent, since
  the reason value is fixed to `'terminated'` for this path.
- Sets `archived_at = now()` and `archive_reason = 'terminated'`.
- Writes an activity feed entry, same as the automatic path — this one
  should record which user performed the action (already standard practice
  per `RecordAgreementActivity`'s existing `user_id` capture).
- This is a genuinely new UI element — there is currently no archive
  control of any kind on the detail page to extend or modify.

### What does NOT change

- `notArchived()` and any existing scope referencing `archived_at` — these
  already exist and already behave correctly once `archived_at` starts
  being genuinely populated. No scope logic changes.
- The "Show Archived" filter checkbox in the list view (added in M3) — it
  already exists and already queries on `archived_at` being set. Once this
  milestone starts actually populating that column, the existing filter
  should simply start working as originally intended, with no changes
  needed to the filter itself.
- `document_status` and `project_status` are unrelated to this decision —
  an agreement can be archived regardless of its project status, per
  Intan's original UAT note from M7 scoping ("all that matters... is if
  its passed the expiry date or not" — now extended to include manual
  termination as a second, independent trigger).

### Files

- `database/migrations/2026_09_0X_XXXXXX_add_archive_reason_to_agreements_table.php`
  (new, purely additive nullable string column — no destructive change, no
  backup-gate ritual required, unlike M6's schema changes)
- `app/Console/Commands/` — new command (e.g. `ArchiveExpiredAgreements`)
- `routes/console.php` (or `app/Console/Kernel.php`, depending on this
  Laravel 13 project's established scheduling location — check which
  convention this codebase already uses before assuming)
- `app/Models/Agreement.php` (add `archive_reason` to `#[Fillable]`; no
  cast needed, plain string)
- `resources/views/components/⚡agreement-show.blade.php` (new "Archive"
  action/button, and display the archive reason if the agreement is
  archived)
- `app/Actions/RecordAgreementActivity.php` (if any change is needed to
  support an `archived` activity type — check its current shape before
  assuming a change is required)

### Tests

- `test_scheduled_command_archives_agreements_past_their_expiry_date`
- `test_scheduled_command_sets_archive_reason_to_expired`
- `test_scheduled_command_does_not_touch_already_archived_agreements`
  (idempotency)
- `test_scheduled_command_does_not_archive_an_agreement_with_a_null_expiry_date`
  (indefinite agreements are never auto-archived)
- `test_scheduled_command_boundary_matches_is_expired` (an agreement whose
  expiry_date is exactly today gets archived; one day later than today
  does not — mirrors the existing M5-8 boundary tests)
- `test_scheduled_command_writes_an_activity_entry_per_archived_agreement`
- `test_legal_can_manually_archive_an_agreement_with_terminated_reason`
- `test_viewer_cannot_manually_archive_an_agreement` (permission boundary,
  matching canWrite())
- `test_manually_archived_agreement_is_excluded_from_the_scheduled_commands_update`
  (confirms the whereNull('archived_at') guard actually protects a
  terminated agreement from being relabeled 'expired')
- `test_show_archived_filter_now_surfaces_genuinely_archived_agreements`
  (regression/integration check that M3's existing filter, previously
  untested against real data because nothing was ever archived, now works)

**QA docs:** test-cases.md (next free TC-###, several needed given this is
a real new feature); traceability-matrix.md (next free BR-##, for both the
auto-expiry rule and the manual-termination rule as distinct business
rules).

---

## 4. M8-4 — Left/right outer border on the Register table

### Decision (resolved 7 Sep 2026)

Add a vertical border on the table's outer left edge (left side of the
Title column) and outer right edge (right side of the View/action column).
This complements M7-2's internal `divide-x` column dividers, which exist
between columns but leave the table with no visible boundary marking where
it starts and ends horizontally.

Pure display change. No schema, no logic.

### Files

- `resources/views/components/⚡agreements-index.blade.php`

### Tests

None required — pure CSS. Confirm the page still renders without error and
the empty-state row (colspan="9", from M6a) is unaffected by the new
border.

---

## 5. Handoff structure

M8-1, M8-2, and M8-4 are all zero-risk, display/data-only changes with no
schema impact — the same category as most of M7. M8-3 (archiving) is the
one item with real new schema (a migration) and new scheduled infrastructure
(a console command + scheduler entry), though the migration itself is
purely additive (a new nullable column) and non-destructive — nothing like
M6b/M6c's backup-gate requirement applies here, since there is no existing
data at risk.

**Recommendation: one single handoff**, `docs/handoff-m8.md`, covering all
four items — following the same reasoning as M7's single-handoff structure.
There is no schema risk to isolate by splitting; M8-3 is larger and more
involved than the other three items, but it does not carry production-data
risk that would justify a separate, sequenced handoff the way M6b/M6c did.

One sequencing note worth stating in the handoff itself: M8-3's scheduled
command should be implemented and its logic verified via tests **before**
the manual-archive UI is built, since the manual path's safety guarantee
(`whereNull('archived_at')` protecting a `terminated` agreement from the
scheduled job) is easiest to verify correct if the automatic path already
exists and is tested first.
