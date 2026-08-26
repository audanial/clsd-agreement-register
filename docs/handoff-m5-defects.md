# OpenCode Go handoff — M5 defects: the expiry-boundary fix (M5-8)

> **Milestone:** M5, **first of three handoffs.** Order: **this → `handoff-m5a.md` → `handoff-m5b.md`.**
> **Implements:** Decisions M5-8 and M5-10 of `docs/architecture-plan-m5.md`, both approved 26 Aug 2026.
> **Precondition:** M1–M4 merged and green — verified 26 Aug 2026 at 114 tests, 296 assertions, clean at `a989414`.
> **Why it runs first:** E-7 asserts the corrected expiry behaviour, so the correction has to exist before the test can pass. M5a assumes DEF-005 and DEF-008 are already closed.
> **Status:** ready to send.
> **Paste the block below into OpenCode Go from the repo root.**

```text
Role: You are the SENIOR DEV on the CLSD Agreement Register (Laravel 13 +
Livewire 4, SQLite). Amir is the PM: implement exactly what the approved plan
says — do not redesign, do not add scope.

Context: Read AGENTS.md and docs/architecture-plan-m5.md, specifically the
section "M5-8, resolved — an agreement expires at the START of its expiry date".
That is a business decision made by the PM on 26 Aug 2026 after consulting the
actual Legal users. It is not open for reinterpretation.

This is a small, sharp handoff: four methods of one model, two tests, two
defect-log closures. It exists on its own because it is the only application-code
change in M5's documentation work, and because the two handoffs that follow
depend on it already being done.

THE RULE, stated once so every decision below follows from it:
An agreement expires at the START of its expiry_date. On the expiry_date itself
the agreement is ALREADY EXPIRED. It is not "still valid" and it is not
"expiring soon". More generally: a boundary date is crossed at the start of that
day, not the end.

Task:

1. app/Models/Agreement.php — the expired() scope. It currently reads:

     $query->whereNotNull('expiry_date')
         ->whereDate('expiry_date', '<', today());

   Change '<' to '<=' so the expiry date itself counts as expired. Leave the
   whereNotNull alone — a null expiry_date means indefinite, which is a domain
   rule from AGENTS.md and is not in scope here.

2. app/Models/Agreement.php — the expiringSoon() scope. It currently reads:

     $query->whereNotNull('expiry_date')
         ->whereBetween('expiry_date', [today(), today()->addDays($days)]);

   whereBetween is inclusive at both ends, so today is currently counted as
   "expiring soon". Under the rule above, today is expired, not expiring soon.
   The window must become exclusive at the near end and stay inclusive at the
   far end — (today, today+N]. Express that with two whereDate calls rather
   than whereBetween. Keep the $days parameter and its default of 90 exactly
   as they are; a caller passing a custom window must keep working.

3. isExpired() does not change BEHAVIOUR, but it does change form — see task 6.
   It currently reads:

     return $this->expiry_date !== null && $this->expiry_date->isPast();

   expiry_date is cast to 'date', so it is midnight on that day, and midnight
   today is already in the past. That answer is correct under the rule. What is
   wrong with it is not the answer but the reasoning: it is correct only because
   the current time is never exactly midnight. Task 6 makes it explicit. Do not
   touch this method in task 3; do it once, in task 6, so the diff reads as one
   deliberate change.

4. Write E-7 in tests/Feature/Agreement/BoundaryConditionsTest.php. The file
   already exists with one test in it. Name the new test exactly:

     test_expiry_today_behaviour_is_consistently_recorded

   That exact name matters: docs/qa/defect-log.md already cites it, and one of
   the things M5a fixes is that the QA documents cite tests that do not exist.
   Do not rename it to something you like better.

   Create ONE agreement with expiry_date = today(), then assert all three paths
   agree that it is expired:
     - $agreement->isExpired() is TRUE
     - Agreement::expired()->exists() is TRUE          <- was false before task 1
     - Agreement::expiringSoon()->exists() is FALSE    <- was true before task 2

   IMPORTANT — this test's character has changed and the difference is not
   cosmetic. It was specified in M4 as a CHARACTERISATION test that pinned a
   known inconsistency and was expected to fail once someone fixed it. It is now
   a CORRECTNESS test asserting the decided rule. Do not write a comment saying
   it is expected to fail when the semantics are decided — the semantics ARE
   decided and that comment would now be actively misleading. Write a comment
   naming Decision M5-8 and stating the rule instead.

   Add a second, small assertion in the same test or a sibling one: an agreement
   with expiry_date = today()->addDay() is NOT expired by any of the three
   paths and IS expiring soon. Without the far side of the boundary, task 2's
   change could be satisfied by an expiringSoon() scope that returns nothing at
   all.

5. Update the existing test in that file,
   test_has_stale_project_status_is_true_past_the_boundary. Do not change its
   assertions — they are correct. Change only its docblock. It currently says it
   is a characterisation test for DEF-008 documenting behaviour that is arguably
   wrong. Under M5-8's boundary rule, exactly-90-days IS stale and that is the
   decided behaviour. Rewrite the docblock to say so, naming M5-8.

6. Decision M5-10, approved by Amir on 26 Aug 2026. Make the two boundary
   comparisons day-based, so the decided rule holds deterministically rather
   than depending on what time it happens to be:

     hasStaleProjectStatus():
       $this->project_status_updated_at === null
           || $this->project_status_updated_at->startOfDay()
               ->lte(now()->subDays(self::STALE_AFTER_DAYS)->startOfDay())

     isExpired():
       $this->expiry_date !== null && $this->expiry_date->startOfDay()->lte(today())

   This changes NO behaviour and NO existing assertion. Both methods already
   return these answers — but only because real time elapses between storing a
   timestamp and reading it back, so an exactly-on-boundary value lands on the
   correct side by drift rather than by intent. The moment any test freezes the
   clock (freezeTime(), travelTo(), Carbon::setTestNow()), the boundary cases
   flip and the staleness test in this same file starts failing for reasons that
   will look supernatural. No test freezes time today. This closes that.

   Two details that are easy to get wrong:
   - Keep the null check in hasStaleProjectStatus(). A null
     project_status_updated_at is stale — that is a separate rule from the
     boundary and it must survive.
   - project_status_updated_at is cast to 'datetime' (a real time of day), which
     is exactly why startOfDay() is doing work there. expiry_date is cast to
     'date' and is already midnight, so startOfDay() on it is a no-op that
     states the intent; keep it anyway, because the pair should read the same
     way and a future cast change should not silently alter the meaning.

   Verify the determinism claim rather than trusting it: temporarily wrap the
   89/90/91 staleness test in a frozen clock ($this->freezeTime() or
   travelTo(now())), confirm it still passes with the new implementation, then
   REMOVE the freeze and leave the test as it was. Do not commit the freeze —
   it is a check on your change, not a new test.

7. docs/qa/defect-log.md — close both defects. Do not delete either entry.
   - DEF-005: status becomes Closed — fixed. Record the M5-8 decision as the
     resolution, describe the two scope changes as the fix, and name this test
     as the verification. Its current Verification section claims a
     characterisation test already passes; that was false when written and is
     the thing you are making true.
   - DEF-008: status becomes Closed — working as intended. The behaviour Amir
     found by hand is now the decided behaviour. Say that plainly: the report
     was correct, the analysis was correct, and the resolution was a business
     decision that made the existing behaviour right rather than a code fix.
     Do not quietly reword the original report to make it look less like a
     defect. Record task 6's change as a robustness fix under this entry, not
     as a behaviour change — the answers were already right; what changed is
     that they are now right on purpose.

8. docs/qa/traceability-matrix.md — update BR-05 (null/indefinite expiry
   semantics) and BR-23 (stale badge) to cite the tests as they now stand.
   Do not touch the four "pending user exercise" clauses or any other row;
   those belong to M5a and it will conflict with you.

9. Run `composer test`. Expect 115 (114 + E-7). Parse the laravel/pao JSON and
   report the result field and the exact count. If any existing test in
   AgreementScopesTest fails, STOP and report it rather than adjusting the test
   — all four expiry scope tests there were checked against these changes and
   should survive untouched, so a failure means one of the two scopes was
   changed differently than specified.

Do not change:
- Any migration, seeder, or factory. Especially not AgreementFactory's expired()
  and expiringSoon() states — they use subDays(1) and addDays(30), sit clear of
  the boundary, and are correct under the new rule.
- Any other model, or any other method of Agreement.php. Four methods are named
  in this prompt; every other line of that file stays as it is.
- Any Livewire component, route, or view. isExpired() has exactly one caller,
  ⚡agreement-show.blade.php line 124, and it needs no change.
- Any existing test's assertions, including the one in BoundaryConditionsTest.
  You are editing one docblock and adding one test.
- Anything belonging to M5a: README.md, AGENTS.md, docs/BUILD_PLAN.md,
  docs/HANDOVER.md, or any part of docs/qa/ beyond the two defect entries and
  the two matrix rows named above.

One thing to expect, so it does not look like a mistake:
NOTHING CHANGES IN THE BROWSER. expired() and expiringSoon() have no callers in
app/, routes/ or resources/views/ — they are model API used only by tests today.
The fix matters because they are the building blocks any future expiry report
will sit on, and shipping them with the wrong boundary means everything built on
them inherits it silently. But do not go looking for a visible difference, and
do not add a UI to demonstrate one.

Acceptance checks:
- `composer test` green; report the result field and the exact count (115).
- vendor\bin\pint on app/Models/Agreement.php and the test file.
- git status --short shows exactly: app/Models/Agreement.php,
  tests/Feature/Agreement/BoundaryConditionsTest.php, docs/qa/defect-log.md,
  docs/qa/traceability-matrix.md. Nothing else.
- The diff to app/Models/Agreement.php touches exactly four methods — expired(),
  expiringSoon(), isExpired(), hasStaleProjectStatus() — and nothing else in the
  file. If any other line moved, something was redesigned that should not have
  been.

After editing:
- Show the diff of app/Models/Agreement.php in full. It is small and Amir will
  read every line of it.
- Report exact test names and the count from the run.
- Confirm the frozen-clock check in task 6 passed, and that the freeze was
  removed again before you finished.
- Do not commit unless Amir says to.
```

---

## Notes for Amir

- **M5-10 is folded in as task 6, so this handoff now touches four methods rather than two.** Two of
  them change behaviour (the scopes); two change only reasoning (`isExpired()`,
  `hasStaleProjectStatus()`). The defect-log wording matters here: task 6 is a robustness fix, not a
  behaviour change, and DEF-008's entry should say so rather than implying the old answers were
  wrong.

- **The diff is the review.** Four methods, nothing else in the file. If any other line moved,
  something was redesigned — the acceptance checks say so explicitly, but it is worth your own eye.

- **Task 6 asks for a throwaway frozen-clock run** to prove the determinism claim rather than assert
  it, then requires the freeze to be removed. Worth confirming it isn't left in: a committed
  `freezeTime()` would quietly change what every other test in that file is exercising.

- **Expect no visible change.** Worth saying twice because it is the kind of thing that reads as
  "nothing happened" during a browser check. The two scopes have no callers yet; this is correctness
  work on the API a future expiry report will be built from.

- **This closes the last two deferred date defects.** After it lands, the only defects still open are
  DEF-003 (partner matching, Major, deliberately shipped open) and DEF-007 (concurrent edits, needs a
  schema column). That is a defensible list to hand over on.

- One thing the sequence buys you: `handoff-m5a.md` now assumes DEF-005 and DEF-008 are already
  closed. I have updated it accordingly. **Do not send M5a before this one.**
