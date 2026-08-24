# OpenCode Go handoff — M4: QA portfolio evidence

> **Milestone:** M4 · **Implements:** Sections 3, 5, 6 and 7 of `docs/architecture-plan-m4.md`
> **Precondition:** M1, M2 and M3 merged. Verified green on 24 Aug 2026 —
> 111 tests, 284 assertions, commit `18d7ad7`.
> **Decisions:** all 7 M4 decisions approved by Amir on 24 Aug 2026. None is reopenable.
> **Nature of this milestone:** documentation and test coverage. **No application code changes.**
> **Paste the block below into OpenCode Go from the repo root.**

```text
Role: You are the SENIOR DEV on the CLSD Agreement Register (Laravel 13 +
Livewire 4, SQLite). Amir is the PM: implement exactly what the approved plan
says — do not redesign, do not add scope.

Context: Read AGENTS.md, docs/BUILD_PLAN.md, and docs/architecture-plan-m4.md
(the APPROVED M4 plan, approved 24 Aug 2026). That plan is the contract. All
seven of its decisions are settled — do not reopen them. Read
docs/architecture-plan.md and docs/architecture-plan-m3.md as reference: they
hold the decisions (D1-D10, M3-1 to M3-7) that the traceability matrix maps to.

Numbering used below: D6 etc. means docs/architecture-plan.md. M3-4 etc. means
docs/architecture-plan-m3.md. M4-1 etc. means docs/architecture-plan-m4.md.
F-1 to F-5 are findings in Section 5 of the M4 plan; DEF-001 to DEF-007 are the
defect-log entries they become; E-1 to E-14 are the edge cases in Section 6.

Precondition: M1, M2 and M3 must be merged and `composer test` green at 111
tests. If the count differs, stop and tell Amir before changing anything.

THIS MILESTONE IS DIFFERENT FROM M1/M2/M3. It produces QA artifacts — documents
that make already-completed work demonstrable to someone assessing testing
skill. It changes NO application code. If a task here appears to require an
application change, that is a defect discovery: log it and raise it with Amir.
Do not fix it inside M4.

Task:

1. Create docs/qa/ and the four artifacts described in Section 3 of the M4 plan.
   Follow the shapes shown there — they are specifications, not suggestions.

   a. docs/qa/test-plan.md — scope, approach, environment, entry/exit criteria,
      roles. The shortest document; it establishes the frame the others assume.

   b. docs/qa/test-cases.md — roughly 60-70 formal cases. ORGANISED BY BUSINESS
      RULE, NOT BY TEST FILE. That reorganisation is the whole point: a document
      that walks the test files in order is just a rendering of the suite.
      Use the ten sections named in Section 3.2 and the exact TC-### table
      format shown there: feature area, business rule, priority, type,
      preconditions, numbered steps, expected result, automated-by, status.
      - Several automated tests may collapse into one case. That is correct.
      - A case with no automated backing is marked "Manual only" WITH A REASON.
        Never imply automation that does not exist.
      - Steps must be executable by a non-developer reading them cold.

   c. docs/qa/defect-log.md — DEF-001 through DEF-007 (M4-4). Define the
      severity scale (Critical/Major/Minor/Trivial) once at the top.
      - DEF-001 is the real bug Amir found in M3 manual testing. Section 3.3 of
        the M4 plan contains the full text — use it. Do not invent details, do
        not soften the "why the existing tests missed it" paragraph.
      - DEF-002 to DEF-006 come from findings F-1 to F-5 in Section 5.
      - DEF-007 is E-9, concurrent edits, Status: Accepted — deferred (M4-5).
      - Every entry: ID, description, steps to reproduce, expected vs actual,
        severity, priority, root cause, fix (or the reason for deferring),
        verification, status.
      - EVERY ENTRY IS REAL. No hypothetical or illustrative bugs. A fabricated
        entry in a portfolio artifact is worse than a short log.

   d. docs/qa/traceability-matrix.md — one row per business rule: rule ID,
      statement, source, test cases, automated tests, status. Roughly 20-25
      rules drawn from AGENTS.md "Domain rules baked into the schema", the
      2026_* migration comments, and decisions D1-D10 / M3-1 to M3-7.
      - The Status column MUST be allowed to say "Partial" or "Not covered",
        linked to the relevant defect. BR-09 (the date warning, D3) is
        "Partial - see DEF-002" until F-1's test lands, then "Fully covered".
      - Every automated test named in this matrix must actually exist. Verify
        by grepping the test files; a matrix pointing at a test that does not
        exist is worse than no matrix.

   e. docs/qa/README.md — one page: what each artifact is, who it is for, how to
      run the suite, and how to keep the artifacts current when code changes.

2. Fix the two tests that assert less than their names claim (M4-1). Amir
   explicitly approved changing these existing assertions; it is the exception
   his standing "do not change existing tests" rule allows for.

   a. AgreementsIndexTest::test_stale_badge_is_shown_only_for_stale_rows
      (line ~159) — F-3 / DEF-004. Today it creates agreements TITLED "Stale"
      and "Fresh" and asserts assertSee('Stale'). The badge's own text is the
      literal word "Stale", so the assertion is satisfied by the row title. The
      test passes whether the badge renders on every row, one row, or none.
      Fix: assert on something that distinguishes the badge from the title —
      the badge's title attribute text ("Project status last updated" /
      "Project status never updated") is the natural choice — AND add the
      missing negative assertion that the fresh row does NOT carry the badge.
      Rename the fixture titles so a title can never satisfy a badge assertion.

   b. AgreementFormTest::test_expiry_before_effective_shows_a_warning_but_still_saves
      (line ~114) — F-1 / DEF-002. It asserts the save. It never asserts the
      warning. If dateWarning returned null unconditionally, all 111 tests would
      still pass and D3's user-facing half would be silently gone.
      Fix: assert the warning text is present before calling save() — via
      assertSet('dateWarning', ...) or assertSee on the rendered amber text —
      keeping the existing save assertions intact.

   VERIFY BOTH THE HARD WAY: before committing, temporarily break the behaviour
   (make dateWarning always return null; remove the badge from the row
   template), confirm the corrected test now FAILS, then restore. A test that
   passes against broken behaviour is exactly the bug you are fixing. Restore
   the application code afterwards — M4 ships zero application changes.

3. Create tests/Feature/Agreement/BoundaryConditionsTest.php with the boundary
   and null-path cases from Section 6:
   - E-1: staleness at 89, exactly 90, and 91 days. hasStaleProjectStatus()
     uses lt(now()->subDays(STALE_AFTER_DAYS)) — a STRICT comparison, so exactly
     90 days is NOT stale. Assert that explicitly; it is the behaviour most
     likely to shift when Ms. Haniza confirms the threshold. Derive the days
     from Agreement::STALE_AFTER_DAYS, never from a hardcoded 90.
   - E-5: a partner name of 1-2 characters produces no similar-partner warning.
     The `< 3` floor at ⚡agreement-form.blade.php:221 is deliberate but
     undocumented and untested.
   - E-6: a partner with country_id = null renders correctly on both the list
     and the detail page. Quick-created partners leave country null, since the
     field is optional. Untested null-render paths are where blank cells and
     "attempt to read property on null" live.
   - E-7: an agreement whose expiry_date is TODAY. This is a CHARACTERISATION
     test for DEF-005: assert what the code does now, across all three paths —
     isExpired() says expired, the expired() scope says not expired,
     expiringSoon() includes it. Comment in the test body that this pins a known
     inconsistency awaiting a business decision, referencing DEF-005, so nobody
     later reads the assertions as the intended semantics.

4. Add to AgreementFormTest:
   - E-4: the acronym case for DEF-003. Create a partner named
     "Universiti Teknologi MARA", type "UiTM" as the new partner name, and
     assert the similar-partner warning does NOT list it. This is a
     CHARACTERISATION test: it pins a known limitation, and it is EXPECTED to
     fail if M5 improves the matching, which is the signal it exists to give.
     Say so in a comment in the test body, referencing DEF-003.

5. Add to AgreementsIndexTest:
   - E-8: a viewer's PAGINATION TOTAL excludes pending rows. M2 proved this at
     relation-count level; the paginator is the surface that would actually leak
     a hidden row's existence to a viewer. Create enough rows to paginate.

6. Run `composer test` — laravel/pao prints compact JSON when it detects an AI
   agent; parse the "result" field. Expect approximately 119 passing.

Approved M4 decisions (do not reopen):
- M4-1: the two weak tests in step 2 ARE approved for correction. This is the
  only permission M4 grants to touch existing tests, and it does not extend to
  any other test's assertions.
- M4-2: the similar-partner acronym gap is logged as DEF-003 and pinned with a
  characterisation test. Do NOT fix the matcher — that is M5. Do NOT amend
  M3-4's wording to match the code.
- M4-3: the expiry-today inconsistency is logged as DEF-005 and pinned. Do NOT
  change isExpired(), expired() or expiringSoon().
- M4-4: the defect log carries DEF-001 through DEF-007.
- M4-5: concurrent edits are DEF-007, Accepted — deferred, with optimistic
  locking named as the fix. No schema column.
- M4-6: Markdown in docs/qa/. No CSV, no second format.
- M4-7: no screenshots in M4.

Do not change (this is the whole point of M4):
- ANY application file. No app/, no database/migrations/, no database/seeders/,
  no routes/, no resources/views/. If `git status` shows one at the end, M4 has
  overrun and you must revert it. The only exception is the TEMPORARY breakage
  in step 2's verification, which must be restored before you finish.
- Any existing test's assertions other than the two named in step 2.
- Any migration, model, seeder, component or business rule.

Do not build (that is M5):
- README or handover documentation, a web form for creating users, Excel
  import, file upload UI, dashboard analytics.
- The fixes deferred by M4-2, M4-3 and M4-5.

Two things that decide whether these artifacts are worth anything:
- HONESTY. The traceability matrix is allowed to show gaps; the defect log is
  allowed to show deferred entries; the test-case document is allowed to say
  "Manual only". A matrix with no gaps, a log with no judgment calls, and a
  case list with no manual entries all read as fiction to anyone who has done
  QA work. Section 5's findings are in the artifacts precisely BECAUSE they are
  unflattering to the suite.
- ACCURACY OF CROSS-REFERENCES. Every test name written into test-cases.md and
  traceability-matrix.md must exist, spelled exactly. Verify with
  `grep -rn "public function test" tests/` before you finish, not by memory.

Acceptance checks:
- `composer test` green at approximately 119 tests (parse the JSON output;
  report the result field).
- `vendor\bin\pint` run on every PHP file you touched — test files only.
- `git status --short` shows ONLY docs/qa/*, docs/architecture-plan-m4.md-
  adjacent docs, and the three test files. NO application file.
- Every test name cited in docs/qa/ resolves to a real test.
- Every test case in test-cases.md is either mapped to a named automated test
  or explicitly marked "Manual only" with a reason.
- defect-log.md contains DEF-001 through DEF-007, each with a severity, a root
  cause, and a disposition of Closed, Deferred or Accepted.
- The four tests from steps 2-4 that pin or protect behaviour (E-2, E-3, E-4,
  E-7) each demonstrably fail against broken/old behaviour and pass now.

After editing:
- List every file created/modified.
- Report exact test names and results, and the before/after test count.
- Confirm explicitly that no application file was changed.
- Tell Amir which documents to read first and in what order.
- Do not commit unless Amir says to.
```

## Notes for Amir

- **This milestone has no browser output and almost no code.** The deliverable is `docs/qa/`.
  The right way to review it is to read the four documents as an outsider would — can someone who
  has never seen this repo execute TC-018 by hand, and does the traceability matrix let them check
  a claim rather than take one?
- **The defect log is the artifact that will carry the most weight.** DEF-001 is the strongest
  entry because it is a complete loop: found in manual testing, root-caused, fixed, and pinned by
  two regression tests. DEF-002 through DEF-006 are the second-strongest, because they show a
  suite being audited rather than trusted. Watch for OpenCode Go softening the "why the existing
  tests missed it" paragraph — that sentence is the one doing the work.
- **Step 2's temporary-breakage check is the only risky instruction in this prompt**, because it
  asks for an application file to be broken on purpose and then restored. Confirm at the end that
  `git status` is clean of application files; the acceptance checks require it, but it is worth
  your own look.
- **Two tests here are designed to fail later.** E-4 (acronym matching) and E-7 (expiry today) pin
  known-wrong behaviour on purpose. If M5 fixes either, those tests go red — that is the intended
  alarm, not a regression. Both carry comments saying so, and both are linked to defect IDs.
- **M5's shape is now visible from the deferred items:** the similar-partner matcher (DEF-003), the
  expiry-day semantics (DEF-005), optimistic locking (DEF-007), the user-creation web form from
  your own `AGENTS.md` note, and the handover documentation with screenshots that M4-7 pushed
  there. That is a coherent milestone, not a leftovers pile.
