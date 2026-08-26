# OpenCode Go handoff — M5a: documentation, QA corrections, two tests

> **Milestone:** M5, **second of three handoffs.** Order: `handoff-m5-defects.md` → **this** → `handoff-m5b.md`.
> **Implements:** Sections 3–8 of `docs/architecture-plan-m5.md`
> **Precondition:** `docs/handoff-m5-defects.md` complete, merged, and green at **115** tests. This handoff assumes DEF-005 and DEF-008 are already closed and that E-7 already exists. **Do not send it first.**
> **Followed by:** `docs/handoff-m5b.md` (user-management form). Do not start M5b work here.
> **Status:** hold until the defects handoff is merged.
> **Paste the block below into OpenCode Go from the repo root.**

```text
Role: You are the SENIOR DEV on the CLSD Agreement Register (Laravel 13 +
Livewire 4, SQLite). Amir is the PM: implement exactly what the approved plan
says — do not redesign, do not add scope.

Context: Read AGENTS.md (including the new "Design philosophy" section),
docs/BUILD_PLAN.md, and docs/architecture-plan-m5.md (APPROVED 26 Aug 2026).
The architecture plan is the contract. Decisions M5-1 through M5-7 are settled
and are not reopenable. If something it does not cover comes up, ask Amir — do
not guess.

This handoff is not like M1-M3. It changes NO application code — the one
application change M5 contains was made by the preceding defects handoff and is
already merged. This handoff's job is to make the repo's documentation true,
hand a non-technical Legal user a way in, and write two tests that were approved
in M4 and never delivered. If a task here looks like it needs an application
change, that is a defect discovery: log it and bring it to Amir. It is not a
licence to fix it.

A second thing to know before you start. In M4 the QA artifacts were written
partly from projections instead of from runs, and they now contain claims that
are false — test names that do not exist, a defect closed on a test that was
never written, a test count nobody ever saw. You are repairing that. So: never
write a number into a document that you have not just read off a real command's
output, and never write a test name into a document without grepping for it
first. That is the entire lesson of this handoff.

Task, in this order:

1. Rewrite README.md completely. It is still the stock Laravel file. Audience:
   a developer who has never seen this repo, and Amir on a machine he has not
   set up before. Sections, per Section 3 of the plan:
   - What this is (the register, who uses it, and the one rule that matters:
     document_status=pending is invisible to non-Legal users).
   - Requirements: PHP 8.3+, Composer, Node 20+, or Laravel Herd.
   - Setup: `composer setup`, then the two things it does NOT do — it does not
     seed, and it does not create database/database.sqlite if absent. Then
     `php artisan db:seed` on a dev machine, or `php artisan user:create` for
     anything else. Warn that `composer setup` runs `npm run build`, which
     fetches the Instrument Sans font over the network (vite.config.js), so an
     offline setup fails at a step whose error message does not mention fonts.
     Note that .env.example ships APP_NAME=Laravel.
   - Running it: `composer dev`, the local URL, and why the queue matters.
   - Roles: a three-row table in plain language, plus the sentence that a PIC
     does not need to log in to be selectable in the agreement form. Add one
     short paragraph recording Decision M5-9's principle: admin is granted on
     trust and system ownership, NOT on organisational rank, and seniority is
     not a reason to hold it. Write the principle, not the current name — the
     assignment will change, the rule should not. This is here because "the most
     senior person should obviously have the highest access" is the default
     assumption everywhere, and it is how admin ends up with someone who has no
     reason to hold it once the people who decided this have moved on.
   - Managing users: `user:create` and `user:password` with real invocations
     and their options. State plainly that UserSeeder and StaffSeeder are BOTH
     guarded to local/testing by design, so on any other environment the only
     path to the first account is `user:create`. (M5b will add a web form; this
     section gets rewritten then. Do not pre-announce it.)
   - Running the tests: `composer test`, and the laravel/pao JSON note — the
     first run otherwise looks broken.
   - How the code is laid out. Give the Livewire 4 single-file `⚡` convention
     its own paragraph: someone who runs make:livewire expecting
     app/Livewire/*.php and gets an emoji-prefixed Blade file will assume the
     repo is broken.
   - Where the documents live: AGENTS.md, docs/BUILD_PLAN.md, the architecture
     plans, docs/qa/, docs/HANDOVER.md.
   - What is deliberately not built: the BUILD_PLAN out-of-scope list, plus the
     known limitations from the defect log, each with a one-line reason.

2. Create docs/HANDOVER.md as a SKELETON ONLY. Headings, plus one line under
   each saying what question that section answers. No prose, no filler, no
   placeholder sentences that read like content. Amir writes every word of the
   note itself — this is Decision M5-3 and it is not a matter of taste: the
   reader is one specific non-technical person and you do not know her. The
   headings, from Section 4 of the plan:
     What this system is / Who can do what / The one rule to know / Getting in /
     Adding a person / What it does not do / What is known to be imperfect /
     When something looks wrong / Where the technical detail lives
   Add a note at the top that the file must fit on one page and is exported to
   PDF for Intan (Decision M5-6).

3. Correct docs/qa/. Every change here is a factual repair; keep the existing
   voice and structure.
   a. defect-log.md, DEF-005 and DEF-008: BOTH WERE ALREADY CLOSED by the
      preceding handoff (docs/handoff-m5-defects.md), which implemented Decision
      M5-8. Read them, confirm they are closed and that
      test_expiry_today_behaviour_is_consistently_recorded exists, and then
      LEAVE THEM ALONE. They are listed here only so you do not "helpfully"
      re-open or re-word them. If either is still marked deferred, the defects
      handoff did not land and you should stop and tell Amir.
   b. defect-log.md, DEF-006: it is marked Closed, its Fix names
      "test_staleness_boundary_at_ninety_days_is_not_stale" (which does not
      exist), and its Actual says the 90-day behaviour "was correct but
      untested". DEF-008 — found by Amir by hand — proves it is not correct.
      Supersede DEF-006 by DEF-008: change its status, correct the false test
      name, and correct the Actual. Do not delete it. A superseded entry that
      shows how the mistake was caught is worth more than a tidy log.
   c. defect-log.md: add DEF-009 (QA artifacts cited two tests that do not
      exist) and DEF-010 (traceability matrix claimed "Fully covered" for cases
      that were never written). Both are defects in the QA work itself. Give
      them the same treatment as any other entry — description, steps to
      reproduce, root cause, fix, verification — and close them in this
      milestone, since this handoff is the fix.
   d. defect-log.md: re-defer DEF-003 and DEF-007 explicitly — they are the only
      two defects still open after the defects handoff, and the handover note
      has to describe both in plain language. DEF-003 keeps severity Major. Do
      NOT downgrade it. Shipping a known Major defect is a recorded PM decision,
      and a log that quietly relabels defects at handover time is worthless.
   e. traceability-matrix.md: remove all four "TC-0NN pending user exercise"
      clauses (rows BR-05, BR-10, BR-18, BR-23). Correct BR-05, BR-18 and BR-23,
      which claim "Fully covered" while naming a case that does not exist. Point
      rows at the tests written in task 4 where they now apply. NOTE: the
      defects handoff already updated BR-05 and BR-23 to cite E-7 and the
      staleness test — build on what is there, do not revert it.
   f. test-plan.md: the exit criteria say "green at approximately 119 tests".
      Replace with the number `composer test` actually prints at the end of this
      handoff. In the Roles table, replace the Ms. Haniza row with Intan (Legal
      Executive, confirmed system owner as of 26 Aug 2026) and state her actual
      role — she is the system owner and handover recipient, NOT the person who
      will confirm business thresholds. That was Ms. Haniza's role and it has
      moved to Amir under Decision M5-8.
   g. docs/qa/README.md: its sample JSON output says tests:119. Same fix.
   h. test-cases.md: reconcile TC-024, TC-033, TC-036 and TC-039 with reality —
      they are referenced as pending exercises. Two of them are covered by the
      tests in task 4; say so. Any that remain uncovered are marked as gaps with
      the reason, not left implying coverage.

4. Add TWO tests to tests/Feature/Agreement/BoundaryConditionsTest.php. These
   were approved in M4 and never written. The file already has two tests in it —
   the DEF-008 staleness boundary and E-7 from the defects handoff. Leave both
   alone. (E-7 was originally part of this handoff and has moved; if it is not
   in the file, the defects handoff did not land — stop and tell Amir.)
   - E-5, the similar-partner length floor. ⚡agreement-form's similarPartners()
     returns an empty collection when mb_strlen(newPartnerName) < 3. That floor
     is deliberate and completely untested. Assert that a 2-character input
     returns no suggestions even when a partner whose name contains those two
     characters exists, and that a 3-character input does search. Test the
     Livewire computed property, not the model.
   - E-6, the null-PIC display contract. NOTE: this differs from the M4 wording
     on purpose — read the "Specification note on E-6" in the architecture plan
     before writing it. Short version: the approved version tested a null
     country_id, but PartnerFactory already sets country_id to null and no view
     renders country at all, so that test could not fail. What is genuinely
     unasserted is that an agreement with pic_user_id = null renders as an
     em dash "—" on the detail view and its row still renders in the list.
     Write that.

5. Update AGENTS.md.
   - Line 3 is stale and has been since M3: it says routes/controllers/Livewire
     components "are not built yet" and that routes/web.php "is still the
     default welcome page". Nine routes and three ⚡ components exist. Rewrite
     that sentence to describe the repo as it is. It is the first thing every
     future agent reads.
   - Under "Future considerations", record that the user-management form is
     approved and being built in M5b, so the item is resolved rather than open.
     Leave the UI/UX polish item exactly as it is — it is future work, not M5.
   - Do not touch the new "Design philosophy" section. Amir wrote it.

6. Update docs/BUILD_PLAN.md. Tick the M1-M5 checkboxes that are genuinely
   done. Record the Excel-import deferral (Decision M5-1) against Section 2 M5.
   Add a note that its "Ms. Haniza to confirm" clauses — the stale-day
   threshold in M3 and Open Decision 3's data-cleanliness question — are
   superseded by open Decision M5-8.

7. Run `composer test`. Expect 117 (115 + the two from task 4). Parse the
   laravel/pao JSON and report the result field and the exact count. THEN go
   back and put that number into test-plan.md and docs/qa/README.md. In that
   order — the number comes from the run, not from this prompt.

Approved decisions relevant to M5a (do not reopen):
- M5-1 (c): Excel import deferred. Do not build it, do not spec it.
- M5-3 (a): Amir writes the handover note. You supply headings only.
- M5-4: DEF-005 corrected, DEF-006 superseded by DEF-008, DEF-003 stays Major.
- M5-5 (a): write E-5 and E-6 for real. E-6 is amended per the plan. E-7 was
  written by the preceding defects handoff.
- M5-8: the expiry-boundary semantics are DECIDED and already implemented.
  DEF-005 and DEF-008 are closed. Do not reopen, re-word, or "improve" either.
- M5-6 (c): docs/HANDOVER.md is the Markdown source of truth; a PDF export goes
  to Intan. You produce only the Markdown.
- M5-7 (c): the handover note's contact section names Intan directly alongside a
  generic escalation path. Amir writes it; your skeleton just leaves room.

Historical documents vs living documents — this matters for task 3 and 6:
Ms. Haniza is named in docs/handoff-m1..m4.md, docs/architecture-plan.md,
-m3.md and -m4.md. Those are dated records of what was known when they were
written. DO NOT edit them. Editing a historical record to say "Intan" falsifies
it. Only AGENTS.md, docs/BUILD_PLAN.md and docs/qa/test-plan.md describe the
present and get corrected.

Do not change (this is the whole point of M5a):
- Any application file. app/, routes/, resources/views/, database/migrations/,
  database/seeders/, database/factories/. Zero changes. `git status` at the end
  must show no file from any of those directories.
- Any existing test's assertions. M4's audit is finished and its corrections are
  committed. You are ADDING two tests, not revising others. Both existing tests
  in BoundaryConditionsTest.php stay exactly as they are — the staleness
  boundary and E-7, both correct as of the defects handoff.
- Any migration, model, seeder, or the Agreement global scope.
- npm behaviour (.npmrc ignore-scripts=true) or vite.config.js.
- Anything in M5b's scope: no user-management component, no new routes, no nav
  changes. That is a separate handoff.

Two things decide whether this work is worth anything:

HONESTY. The traceability matrix is allowed to say "Partial" and "Not covered".
The defect log is allowed to carry open, deferred and superseded entries. The
test-case document is allowed to say "Manual only" with a reason. A matrix with
no gaps reads as fiction to anyone who has done QA work, and this repo's QA
artifacts are portfolio evidence — they will be read by people looking for
exactly that. DEF-009 and DEF-010 exist because the log is used honestly against
its own author. Do not soften them.

ACCURACY OF CROSS-REFERENCES. Every test name you write into a document must be
verified against the code, not recalled. Before you finish, run:

  comm -23 \
    <(grep -rhoE "test_[a-z0-9_]+" docs/qa/*.md | sort -u) \
    <(grep -rhoE "function test_[a-z0-9_]+" tests/ | sed 's/.*function //' | sort -u)

It must print nothing. Anything it prints is a test cited in the QA docs that
does not exist — which is DEF-009, the defect you are here to close. Any
equivalent check is fine; empty output is the requirement.

Acceptance checks:
- `composer test` green; report the result field and the exact test count.
- The cross-reference check above prints nothing.
- `vendor\bin\pint` run on the test file you touched.
- `git status --short` shows changes ONLY in README.md, AGENTS.md, docs/ and
  tests/Feature/Agreement/BoundaryConditionsTest.php.
- No document contains the number 119 as a test count.
- docs/HANDOVER.md contains headings and prompts, and no prose.

After editing:
- List every file created/modified.
- Report the exact test count from the run, and the names of the two new tests.
- Report the output of the cross-reference check.
- List anything you found that looks like a defect but that you did not fix,
  so Amir can decide.
- Do not commit unless Amir says to.
```

---

## Notes for Amir

- **The E-6 change is deliberate and is explained in the plan, not just here.** The M4-approved
  version tested a null `country_id`, but `PartnerFactory` already defaults it to null and no view
  renders country at all — so the test could not have failed. It is redirected to the null-PIC
  em-dash contract, which can. If you would rather it were dropped entirely, say so before sending
  this: the count becomes 116 and BR-18 gains a documented non-gap.

- **Task 7 is deliberately out of order** — run the suite, then write the number down. That
  inversion is the direct fix for how the artifacts came to claim 119 tests that never existed.

- **The one thing to check yourself** is `git status` at the end. M5a must not touch a single
  application file, and the acceptance checks say so twice, but it is the constraint most likely to
  be violated by good intentions. The likeliest slip now is the reverse of the old one: having just
  seen the defects handoff change `Agreement.php`, a senior dev may read that as permission to keep
  going.

- **DEF-003 stays Major.** If OpenCode Go proposes downgrading it to Minor "since it's deferred
  anyway", that is the one push-back to reject outright.

- After this lands, `docs/HANDOVER.md` is yours to write. It is the deliverable with the shortest
  text and the longest reach.
