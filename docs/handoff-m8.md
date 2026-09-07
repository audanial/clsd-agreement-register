# OpenCode Go handoff — M8: type cleanup, archiving, display polish

> **Milestone:** M8 · **Implements:** `docs/architecture-plan-m8.md` (APPROVED, 7 Sep 2026)
> **Precondition:** M1–M7 merged and green. Verified 7 Sep 2026 at 167 tests passing.
> **Decisions:** M8-1 through M8-4, all approved by Amir on 7 Sep 2026. None is reopenable.
> **Nature of this milestone:** three small display/data changes plus one genuinely new feature (archiving — this does not exist in the app at all today, despite `archived_at` existing as a column since M2). No destructive migration, no production-data risk — the one new migration is a purely additive nullable column.
> **Paste the block below into OpenCode Go from the repo root.**

```text
Role: You are the SENIOR DEV on the CLSD Agreement Register (Laravel 13 +
Livewire 4, SQLite locally / MySQL on Laravel Cloud production). Amir is the
PM: implement exactly what the approved plan says — do not redesign, do not
add scope.

Context: Read AGENTS.md, docs/BUILD_PLAN.md, and docs/architecture-plan-m8.md
(APPROVED, 7 Sep 2026). That plan is the contract for this handoff. If
something it does not cover comes up, ask Amir — do not guess.

Precondition: M1-M7 must already be merged. `composer test` should be green
at 167 tests before you start. If it is not, stop and tell Amir.

IMPORTANT CONTEXT ON M8-3 (archiving): this is confirmed, by Amir directly
testing the live production app on 7 Sep 2026, to be entirely NEW
functionality. The archived_at column and notArchived()/archived() scopes
have existed since M2, and the M3 "Show Archived" filter checkbox has always
existed in the list view — but NOTHING in the current application ever sets
archived_at to anything but null. There is no archive button anywhere. Do
not assume any existing archive-related UI exists to extend; you are
building this from nothing.

This handoff has FOUR items. Build them in this order — M8-3 is
substantially larger than the other three and should be done LAST, since
items 1/2/4 are small and independent, and M8-3's automatic (scheduled)
path should be implemented and tested BEFORE its manual path, per the plan's
own sequencing note (the manual path's safety guarantee is easiest to verify
once the automatic path already exists and is tested).

═══════════════════════════════════════════════════════════════
M8-1 — Remove MOC from Agreement Type
═══════════════════════════════════════════════════════════════

This is a pure, zero-risk, forward-only change. Verified before this plan
was approved: a check against live production data confirmed ZERO existing
agreements are typed MOC. There is nothing to migrate or relabel.

1. resources/views/components/⚡agreement-form.blade.php: remove the
   `<option value="MOC">MOC</option>` line from the Type <select>. The
   remaining six options (LOI, NDA, MOA, MOU, SEA, ADDENDUM) stay exactly
   as they are, same order.

2. Update the validation rule from
   `'type' => ['required', 'in:LOI,NDA,MOA,MOU,SEA,MOC,ADDENDUM']`
   to
   `'type' => ['required', 'in:LOI,NDA,MOA,MOU,SEA,ADDENDUM']`

3. Grep the test suite for any reference to MOC before finishing:
     grep -rn "MOC" tests/
   If any existing test asserts MOC is a valid/accepted type, update or
   remove that specific assertion — do not leave a test passing against
   removed behaviour.

Note for context only, no code change needed: Legal's existing convention
of writing a bracketed subtype directly into the Title field (e.g. "MOA
(Memorandum of Cooperation) Awan Byte Technologies Sdn Bhd") continues
exactly as today — this was deliberately NOT turned into a structured form
field, per Decision M8-1 in the plan. Do not add any new field for this.

Tests:
- test_moc_is_not_an_option_in_the_type_dropdown
- test_type_validation_rejects_moc

═══════════════════════════════════════════════════════════════
M8-2 — Bold campus abbreviations
═══════════════════════════════════════════════════════════════

Pure display change. In both places campus code+name are rendered, bold
ONLY the code/abbreviation portion (e.g. "MIIT", "ACE", "CoRI", "MIDI"),
leaving the full institute name that follows in normal weight.

1. resources/views/components/⚡agreements-index.blade.php — the Campus
   column. Wrap the code portion in <strong> or a font-bold span.

2. resources/views/components/⚡agreement-form.blade.php — the Campus
   dropdown. Same treatment on the code portion of each <option>'s text.

   IMPORTANT: HTML <option> elements have limited, inconsistent style
   support across browsers — bold text inside <option> tags does not
   always render reliably. Test this specifically in whatever browser
   context you can verify. If bold does not visibly render inside the
   dropdown options, STOP and report this to Amir rather than shipping a
   dropdown where the requested change silently doesn't work — this is a
   known, real cross-browser limitation, not a simple CSS mistake to
   silently work around with an unrelated substitute.

No tests required — pure CSS/markup, no behaviour change. Confirm existing
campus-related tests (filtering, dropdown population) still pass unchanged.

═══════════════════════════════════════════════════════════════
M8-3 — Archiving: automatic (expiry) and manual (early termination)
═══════════════════════════════════════════════════════════════

--- Part A: schema ---

1. New migration:
   database/migrations/2026_09_0X_XXXXXX_add_archive_reason_to_agreements_table.php

     Schema::table('agreements', function (Blueprint $table) {
         $table->string('archive_reason')->nullable()->after('archived_at');
     });

   This is purely additive and non-destructive — no backup gate is required
   for this migration, unlike M6b/M6c. Run it normally.

2. app/Models/Agreement.php: add archive_reason to #[Fillable([...])]. No
   cast needed — plain string, matching document_status/project_status/etc.

--- Part B: the automatic path (build and test this BEFORE part C) ---

3. First, check whether this Laravel 13 project's task scheduler is
   configured in routes/console.php or app/Console/Kernel.php — this
   differs by how the project was originally scaffolded. Check which file
   exists/is in use before adding the scheduled entry; do not assume.

4. Create a new Artisan command (e.g.
   app/Console/Commands/ArchiveExpiredAgreements.php) that performs this
   exact idempotent update:

     Agreement::query()
         ->whereNull('archived_at')
         ->whereNotNull('expiry_date')
         ->whereDate('expiry_date', '<=', today())
         ->get()
         ->each(function (Agreement $agreement) {
             $agreement->update([
                 'archived_at' => now(),
                 'archive_reason' => 'expired',
             ]);

             app(RecordAgreementActivity::class)(
                 $agreement,
                 'archived',
                 'Automatically archived: expiry date passed',
                 ['reason' => 'expired']
             );
         });

   IMPORTANT DETAILS — do not deviate:
   - The boundary is `<=`, exactly matching the existing isExpired()/
     expired() scope's boundary from Decision M5-8. Do NOT use `<` — this
     was explicitly considered and rejected in the plan (it would create a
     window where isExpired() says true but the agreement isn't archived
     yet, with no functional reason for the gap).
   - whereNull('archived_at') is the critical safety condition — it makes
     this command safe to run repeatedly (already-archived agreements are
     never touched again) AND it is what protects a manually-terminated
     agreement (archive_reason = 'terminated') from ever being overwritten
     to 'expired' by this automatic job. Do not weaken or remove this
     condition.
   - Use ->get()->each(...) with individual ->update() calls PER AGREEMENT
     (not a single batch ->update() call) specifically because each
     archived agreement needs its OWN activity log entry — a batch update
     would archive correctly but leave no per-agreement audit trail. This
     is a deliberate deviation from a more "efficient" single-query update,
     justified by the activity-logging requirement.
   - Do NOT use DB::table('agreements') anywhere in this command — this
     violates the project's standing rule from M2/M3 that raw query
     builder must never touch the agreements table, since it bypasses
     Eloquent (and by extension the pending-visibility global scope,
     even though this specific query may not interact with that scope
     directly — the rule stays absolute, no exceptions).
   - Check RecordAgreementActivity's current constructor/invoke signature
     before assuming the exact call shape above is correct — verify against
     the actual file, this is illustrative of the intent, not a guaranteed
     exact signature match.

5. Schedule this command to run daily, shortly after midnight, in the
   Asia/Kuala_Lumpur timezone (matching the M6-7 timezone decision already
   applied elsewhere in this app). Add ->withoutOverlapping() as an
   additional safety guard against overlapping runs, even though the
   database condition above is the primary protection.

6. Write and verify these tests BEFORE moving to Part C:
   - test_scheduled_command_archives_agreements_past_their_expiry_date
   - test_scheduled_command_sets_archive_reason_to_expired
   - test_scheduled_command_does_not_touch_already_archived_agreements
     (run the command twice in the test; assert no duplicate activity rows
     and archived_at doesn't change on the second run)
   - test_scheduled_command_does_not_archive_an_agreement_with_a_null_expiry_date
   - test_scheduled_command_boundary_matches_is_expired (an agreement whose
     expiry_date is exactly today() gets archived by this command; one
     that expires tomorrow does not — mirror the existing M5-8 boundary
     test style from BoundaryConditionsTest.php)
   - test_scheduled_command_writes_an_activity_entry_per_archived_agreement

--- Part C: the manual path (build after Part B is tested and green) ---

7. resources/views/components/⚡agreement-show.blade.php: add an "Archive
   this agreement" action, visible only when auth()->user()->canWrite()
   (same permission boundary already enforced elsewhere in this file) AND
   the agreement is not already archived (archived_at is null).

   - Require a confirmation step before archiving (a simple confirm
     dialog or a two-step button is sufficient — this does not need to
     collect a reason from the user, since the reason is fixed to
     'terminated' for this manual path, per the plan's decision to defer
     termination_basis).
   - On confirm: set archived_at = now(), archive_reason = 'terminated'.
   - Write an activity entry via RecordAgreementActivity, same pattern as
     the automatic path, recording the acting user (already standard via
     the action's existing user_id capture — verify this, don't assume).
   - Display the archive_reason somewhere on the detail page when an
     agreement is archived (e.g. "Archived: Expired" or "Archived:
     Terminated"), so this is visible without inspecting raw data.

8. Tests:
   - test_legal_can_manually_archive_an_agreement_with_terminated_reason
   - test_viewer_cannot_manually_archive_an_agreement (403 or hidden
     control, matching how canWrite() is enforced elsewhere — check the
     existing pattern in this file for write-permission UI, e.g. M3's
     approach of hiding the control AND enforcing server-side)
   - test_an_already_archived_agreement_does_not_show_the_archive_action
   - test_manually_archived_agreement_is_excluded_from_the_scheduled_commands_update
     (create a terminated agreement whose expiry_date has ALSO passed; run
     the scheduled command; assert archive_reason is still 'terminated',
     not overwritten to 'expired' — this is the concrete proof that Part
     B's whereNull('archived_at') guard actually protects manual
     terminations)

--- Part D: verify the existing Show Archived filter now works ---

9. The list view's existing "Show Archived" checkbox filter (from M3) has
   always existed but has never had real data to show, since nothing has
   ever set archived_at until this handoff. Write one test confirming it
   now actually surfaces genuinely archived agreements:
   - test_show_archived_filter_now_surfaces_genuinely_archived_agreements

   Do NOT modify the filter's existing query logic unless you find it is
   actually broken when tested against real archived data — it was built
   correctly in M3 against a hypothetical; this handoff is likely just
   proving it already works, not fixing it. If it IS broken, report the
   specific issue to Amir before changing it, since a change here would be
   outside this handoff's stated scope.

═══════════════════════════════════════════════════════════════
M8-4 — Left/right outer border on the Register table
═══════════════════════════════════════════════════════════════

Pure CSS change. Add a vertical border on the outer left edge (left side
of the Title column, the table's first column) and outer right edge (right
side of the View/action column, the table's last column) in
⚡agreements-index.blade.php. This complements M7-2's existing internal
divide-x column dividers, which sit between columns but leave no visible
boundary at the table's own left/right edges.

Match the border weight/color already established by M7-2's dividers for
visual consistency — do not introduce a different border style.

No tests required — pure CSS. Confirm the page still renders without error
and the empty-state row (colspan="9", from M6a) is unaffected.

═══════════════════════════════════════════════════════════════

After all four items:

1. Run `composer test` — laravel/pao prints compact JSON when it detects an
   AI agent; parse the "result" field. Report the exact before/after count
   (started at 167).

2. Run `vendor\bin\pint` on every PHP file you touched.

3. docs/qa/test-cases.md and docs/qa/traceability-matrix.md: check the
   current highest TC-### and BR-## numbers before adding new entries.
   M8-3 needs several new entries given it's a genuinely new feature with
   two distinct business rules (auto-expiry archiving, manual-termination
   archiving). M8-1, M8-2, M8-4 do not need new QA doc entries — cosmetic/
   data changes with no new business rule to trace, same as M7's precedent
   for similar items.

4. AGENTS.md: add a Future Consideration note for `termination_basis` (a
   more granular breakdown of WHY a termination happened — breach, mutual
   agreement, etc.) — deferred, not built now, per the plan's explicit
   decision. Also add a note that the archived_at/archive_reason columns
   are now genuinely in use (M2 originally added archived_at with no
   feature using it; M8 is the first real feature to populate it).

Approved decisions relevant to this handoff (do not reopen):
- M8-1: MOC removed from the Type dropdown. Zero existing production data
  affected. No new structured "cooperation subtype" field — Legal's
  existing bracket-in-title convention continues unchanged.
- M8-2: bold campus CODE only, not the full name. Flag to Amir if <option>
  styling doesn't render reliably rather than silently working around it.
- M8-3: archive_reason has exactly two values: 'expired' (automatic) and
  'terminated' (manual). Do NOT add a 'cancelled' value — this was
  explicitly considered and rejected (termination is the correct general
  term for early ending of a valid agreement; cancellation is a distinct,
  narrower concept). The automatic trigger is a real daily SCHEDULED
  DATABASE WRITE, never a display-time/query-time-only filter. The
  boundary is `<=`, matching the existing isExpired() rule exactly — do
  not introduce a different boundary.
- termination_basis (breach/mutual/convenience breakdown) is explicitly
  OUT OF SCOPE for this handoff — deferred to AGENTS.md as a Future
  Consideration, not built now.

Do not change:
- Any existing migration. This handoff adds exactly ONE new migration
  (archive_reason), purely additive.
- notArchived() or any other existing scope on Agreement — these already
  work correctly; this handoff only starts genuinely populating the column
  they read.
- document_status or project_status logic — archiving is independent of
  both, per Amir's own framing from the original UAT note.
- The Agreement global scope (HidePendingFromNonLegalScope), the three-role
  enum, or anything from M1's auth.
- Anything outside the file list implied by the four items above.
- Do not run npm scripts (ignore-scripts=true) or touch vite.config.js.
- Do not use DB::table('agreements') anywhere, including inside the new
  scheduled command — this is an absolute, standing rule from M2/M3.

Do not build (out of scope for this handoff):
- termination_basis or any reason sub-field beyond the two archive_reason
  values specified.
- Any UI to un-archive/restore an agreement — not requested, not part of
  this plan.
- Any change to the Show Archived filter's existing query logic, unless
  you find it is genuinely broken when tested against real data (report
  first, don't fix silently).

Acceptance checks:
- `composer test` green (parse the JSON output; report the result field and
  the exact final count).
- `vendor\bin\pint` run on all PHP you touched.
- Manual: Type dropdown no longer offers MOC; campus abbreviations render
  bold in both the list and the form (flag if <option> bold doesn't work);
  an agreement with expiry_date in the past gets automatically archived
  with reason 'expired' after the scheduled command runs (test this
  manually by running the command directly via `php artisan` rather than
  waiting for the actual schedule); a legal user can manually archive an
  agreement via the new detail-page action, and it shows reason
  'terminated'; the Register table has visible left/right outer borders.
- `grep -rn "MOC" resources/views/` returns nothing (confirms M8-1 fully
  removed).

After editing:
- List every file created/modified.
- Report exact test names and results — before (167) and after count.
- Explicitly confirm: which scheduler configuration file this project uses
  (routes/console.php or Kernel.php), and where the new scheduled entry
  was added.
- Explicitly confirm: whether bold rendered correctly inside <option> tags,
  or whether this needs Amir's attention.
- Tell Amir what to manually check in the browser, in order matching the
  four items above, and specifically how to manually trigger the scheduled
  command for testing purposes (the exact `php artisan` command name).
- Do not commit unless Amir says to.
```

---

## Notes for Amir

- **This is the first milestone where "no backup gate" genuinely means no backup gate, not just no schema risk.** M8-3 does add a real migration, but it's a single nullable column with zero destructive potential — worth a quick diff read before pushing, as always, but there's no equivalent of the M6c ritual required here.

- **The scheduled command is the one piece of this handoff you can't fully verify by clicking around the UI.** A scheduled task that runs at midnight isn't something you'll casually observe working. The prompt asks OpenCode Go to tell you the exact `php artisan` command name to manually trigger it — use that to actually test it yourself (create a test agreement with a past expiry date, run the command by hand, confirm it archives with the right reason) rather than just trusting "it's scheduled" and moving on.

- **Worth checking the `<option>` bold-styling flag specifically.** This is a known, real browser quirk — if OpenCode Go reports it didn't render, that's not a sign of a broken implementation, just a genuine HTML limitation worth a quick look together before deciding whether it matters enough to solve differently (e.g. a custom-styled dropdown component instead of a native `<select>`).

- **The `whereNull('archived_at')` protection for terminated agreements is the one test worth reading yourself**, even briefly — `test_manually_archived_agreement_is_excluded_from_the_scheduled_commands_update` is the actual proof that manual termination and automatic expiry-archiving coexist safely. If that test is weak or missing, the two archiving paths could silently clobber each other in a way that wouldn't show up until it actually happened on a real agreement.

- **Once this lands, archiving genuinely works for the first time** — `archived_at` has existed since M2 with nothing ever populating it. Worth noting in your own head (and maybe AGENTS.md) that this closes out a gap that's been sitting open, undocumented as a real gap, since the very beginning of the project.
