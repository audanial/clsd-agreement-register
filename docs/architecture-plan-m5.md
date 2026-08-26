# Architecture plan — M5: Handover

> **Milestone:** M5 · **Implements:** Section 2 "M5 — Handover" of `docs/BUILD_PLAN.md`, plus the
> two "Future considerations" items in `AGENTS.md`.
> **Status:** APPROVED by Amir on 26 Aug 2026 — M5-1 through M5-7 resolved (Section 9), then M5-8
> resolved the same day. Handed to OpenCode Go as **three sequenced handoffs, in this order:**
> `docs/handoff-m5-defects.md` (the M5-8 expiry-boundary fix + E-7), then `docs/handoff-m5a.md`
> (documentation, QA corrections, E-5 and E-6), then `docs/handoff-m5b.md` (user management).
> **No decision in Sections 1–9 is reopenable.** M5-10 was approved the same day and is folded into
> the defects handoff, and **M5-9** was resolved the same day. **Every decision in this plan is now
> closed.** One item stays deliberately deferred rather than open: the backup admin account (M5-9).
> **Author:** Claude Code (Lead Architect) · **Date:** 26 Aug 2026
> **Precondition:** M1–M4 complete and green — verified below, not assumed.

---

## Context

M5 is the last MVP milestone and the only one whose deliverable is read by someone who is not a
developer. Its definition of done is the one stated at the top of `BUILD_PLAN.md` Section 2:
**colleagues can use it daily without you.** That phrasing matters for scope. A milestone that
produces two beautiful documents but still requires Amir to open a terminal every time Legal hires
a project owner has not met it.

That tension is the substance of Decision M5-2. Everything else in M5 is documentation, correction,
and disposition.

### Context change — 26 Aug 2026

Two things landed after this plan was drafted and before it was approved. Both change M5's substance,
not merely its proper nouns.

**Ms. Haniza departed Legal on 26 Aug 2026. Intan (Legal Executive) is the confirmed handover contact
and system owner.** Intan is also non-technical. Every document in this repo that defers a business
question to Ms. Haniza — the stale-day threshold, the expiry-date semantics, whether the 2022–24 data
is clean — is now deferring it to someone who has left. That is not a rename; it is a dead
dependency, and it is why Decision M5-8 exists.

**`AGENTS.md` gained a "Design philosophy" section.** Its operative test is: *"can Intan do this
without technical help? If the answer requires a terminal, a code editor, or developer knowledge,
it's a gap, not an acceptable tradeoff."* That elevates Decision M5-2 from a judgment call to a
requirement, and it is the standard the M5b handoff is written against. It also raises a question the
philosophy itself does not answer — which role Intan holds, given that user management is admin-only.
That is Decision M5-9.

**A principle for the Haniza references.** Historical records keep their text: `docs/handoff-m1.md`
through `handoff-m4.md`, `docs/architecture-plan.md`, `-m3.md` and `-m4.md` are dated artifacts of
what was known when they were written, and editing them to say "Intan" would falsify the record.
Living documents get corrected: `AGENTS.md`, `docs/BUILD_PLAN.md`, and `docs/qa/test-plan.md` describe
the present and must be accurate. This plan notes the supersession so the two sets do not appear to
disagree.

---

## 1. Verified current state (checked 26 Aug 2026 against the working tree)

`composer test` → `{"tool":"phpunit","result":"passed","tests":114,"passed":114,"assertions":296,"duration_ms":7986}`

Working tree clean at `a989414`. M1, M2, M3 and M4 are all merged. The application is complete for
the MVP scope: auth, roles, the pending global scope, the register list, create/edit, detail view,
activity logging, and the QA artifact set in `docs/qa/`.

Eleven findings follow. Three are confirmations, eight are drift the plan file or the QA artifacts
do not currently reflect.

### V-1 — The suite is green at 114 tests, not the 119 the M4 plan projected

`docs/architecture-plan-m4.md` Section 7 projected "111 → approximately **119**". Actual is 114.
The gap is not test failures; it is undelivered tests (V-2).

### V-2 — Three approved M4 tests were never written

Of the eight edge cases approved for M4 (E-1 … E-8), five were delivered and three were not:

| Case | Approved as | Delivered? |
|---|---|---|
| E-1 | Staleness at 89 / 90 / 91 days | ✅ `BoundaryConditionsTest::test_has_stale_project_status_is_true_past_the_boundary` |
| E-2 | Date warning text actually renders | ✅ folded into `AgreementFormTest::test_expiry_before_effective_shows_a_warning_but_still_saves` |
| E-3 | Stale badge **absent** on a fresh row | ✅ folded into `AgreementsIndexTest::test_stale_badge_is_shown_only_for_stale_rows` via a `substr_count` of 1 |
| E-4 | Acronym partner name characterisation | ✅ `AgreementFormTest::test_acronym_partner_name_does_not_trigger_similar_warning` |
| E-5 | Partner name of 1–2 characters | ❌ **not written** |
| E-6 | Partner with `country_id = null` renders | ❌ **not written** |
| E-7 | Agreement expiring **today** (three-path characterisation) | ❌ **not written** |
| E-8 | Viewer's paginator total excludes pending rows | ✅ `AgreementsIndexTest::test_viewer_pagination_total_excludes_pending_rows` |

111 + E-1 + E-4 + E-8 = 114. The arithmetic closes exactly; nothing else is missing.

E-2 and E-3 being folded into existing tests rather than added as new ones is fine — the assertions
are there and they bite. E-5, E-6 and E-7 are simply absent.

### V-3 — Two test names cited in `docs/qa/` do not exist anywhere in `tests/`

Cross-checked every `test_*` name appearing in `docs/qa/*.md` against every `public function test_*`
in `tests/`:

```
test_expiry_today_behaviour_is_consistently_recorded    cited by DEF-005 as its verification
test_staleness_boundary_at_ninety_days_is_not_stale     cited by DEF-006 as its fix
```

Both defect entries therefore claim a verification that did not happen. DEF-005 states
*"Characterisation test passes and documents the known inconsistency"* — there is no such test.
DEF-006 is marked **Closed** on the strength of a test that was never written.

This is the single most damaging finding in the set. The QA artifacts are portfolio evidence; a
reviewer who greps two test names and finds nothing stops trusting the other ninety-seven.

### V-4 — DEF-006 and DEF-008 contradict each other in the same log

DEF-006 (**Closed**) says of the 90-day boundary: *"Actual: Behaviour was correct but untested."*

DEF-008 (**Accepted**), written later by Amir after actually exercising the boundary, says the
behaviour is **not** correct — exactly 90 days is already treated as stale, reproducibly.

DEF-008 is right; `BoundaryConditionsTest` asserts `assertTrue` on the 90-day case and passes.
DEF-006 must be superseded, not left standing as a Closed entry that the log itself disproves.

### V-5 — Two artifacts state a test count that was never true

`docs/qa/test-plan.md` §5 exit criteria: *"`composer test` is green at approximately 119 tests."*
`docs/qa/README.md` sample output: `{"tool":"phpunit","result":"passed","tests":119,...}`

Actual: 114. Both were written from the M4 projection rather than from a run.

### V-6 — The matrix carries four unwritten cases on rows marked "Fully covered"

`traceability-matrix.md` rows BR-05, BR-10, BR-18 and BR-23 each append a clause of the form
*"TC-0NN pending user exercise"* — TC-033, TC-024, TC-036 and TC-039 respectively. BR-10 is honestly
marked **Partial**. The other three are marked **Fully covered** while naming a case that does not
exist.

BR-23 is the sharpest instance: its "pending user exercise" (the staleness boundary) has since been
done, and it produced DEF-008 — a defect. The row still reads *Fully covered* with no reference to it.

### V-7 — `AGENTS.md` line 3 is stale

> *"Early stage: schema and seeders exist, but routes/controllers/Livewire components are not built
> yet (`routes/web.php` is still the default welcome page; README is the stock Laravel one)."*

The first half has been false since M3 — `routes/web.php` has nine routes and three `⚡` components
exist. The second half is true, and is precisely what M5 fixes. This is the first file every future
agent reads; leaving it describing a pre-M1 repo is a live source of wrong assumptions.

### V-8 — `README.md` is the untouched stock Laravel file

58 lines, zero project content. Confirms the M5 requirement as written.

### V-9 — A fresh clone cannot be logged into

`composer setup` runs install → `.env` → `key:generate` → `migrate --force` → `npm install` →
`npm run build`. It does **not** run `php artisan db:seed`.

`UserSeeder` and `StaffSeeder` both open with `if (! App::environment('local','testing')) return;`.
`CampusSeeder` and `CountrySeeder` are unguarded but are only invoked through `DatabaseSeeder`.

So a fresh clone lands on a login page with no accounts, no campuses and no countries — and on a
production-like environment, seeding would not create accounts even if run. The only path to the
first account anywhere is `php artisan user:create`. That is a correct security posture, and it is
undocumented. The README must state it explicitly, including the production case.

Two further setup facts a successor will hit and must be warned about:
- `npm run build` inside `composer setup` fetches the Instrument Sans font over the network
  (`vite.config.js`). Offline setup fails at that step, and the failure looks unrelated to fonts.
- `.env.example` ships `APP_NAME=Laravel`.

### V-10 — There is no user-management UI

`app/` contains no admin controller or component; `routes/web.php` has no admin route. Adding a
person to the register — including a PIC who will never log in — requires shell access to run
`php artisan user:create`. The `AGENTS.md` future-consideration item is fully open.

One schema fact that shapes the options: `users.password` is **NOT NULL** (`0001_01_01_000000`), and
no mail transport is configured. Any create-user path must therefore set an initial password
directly; there is no invite-link option without new infrastructure.

### V-11 — DEF-008's provenance is genuine, and worth protecting

DEF-008 was found by Amir, by hand, while writing `BoundaryConditionsTest.php` as a learning
exercise — not by an agent reviewing its own output. Together with DEF-001 (found in the M3 browser
walkthrough) that gives the defect log two entries with real human discovery provenance. That is the
most credible thing in the QA set and the narrative M5's documentation should preserve rather than
flatten.

---

## 2. What M5 is, and what it is not

**M5 is:**
- A README that a competent developer who has never seen this repo can follow to a running app.
- A one-page note that a non-technical Legal officer can read and act on.
- Correction of the QA artifacts so every claim in them is true.
- An explicit, recorded disposition for every open defect — including the ones that stay open.

**M5 is not:**
- A UI/UX pass. `AGENTS.md` logs that as future work pending real-user feedback, and Amir's brief
  restates it. No restyling, no layout changes, no component rewrites.
- A feature milestone — with one possible bounded exception, Decision M5-2.
- The Excel import. See Section 7 and Decision M5-1.

---

## 3. Deliverable 1 — README rewrite

Full replacement of `README.md`. Audience: a developer inheriting the repo, and Amir on a machine
he has not set up before. Proposed sections, in order:

1. **What this is** — two paragraphs. The register, who uses it, the one rule that matters
   (`pending` is invisible to non-Legal users).
2. **Requirements** — PHP 8.3+, Composer, Node 20+; or Laravel Herd, which bundles all three.
3. **Setup** — `composer setup`, then the two things it does *not* do: create the SQLite file if
   absent, and seed. Then `php artisan db:seed` for a dev machine, or `php artisan user:create` for
   anything else. Warn about the font fetch (V-9) and `APP_NAME`.
4. **Running it** — `composer dev`, the local URL, what the TUI is running and why the queue matters.
5. **Roles** — a three-row table in plain language, plus the sentence that a PIC does not need to log
   in to be selectable.
6. **Managing users** — `user:create` and `user:password` with real invocations and their options,
   and the note that both seeders are dev-only by design. If Decision M5-2 lands, this section leads
   with the web form and keeps the commands as the fallback.
7. **Running the tests** — `composer test`, and the `laravel/pao` JSON note, since the first run will
   otherwise look broken.
8. **How the code is laid out** — a short orientation. The Livewire 4 single-file `⚡` convention gets
   its own paragraph; a successor who runs `make:livewire` expecting `app/Livewire/*.php` and gets an
   emoji-prefixed Blade file will assume the repo is broken.
9. **Where the documents live** — `AGENTS.md`, `docs/BUILD_PLAN.md`, the four architecture plans,
   `docs/qa/`, `docs/HANDOVER.md`.
10. **What is deliberately not built** — the `BUILD_PLAN` out-of-scope list, plus the known
    limitations from Section 6 below, each with a one-line reason.

**The setup section is only real once someone has executed it on a clean clone.** See Section 5.

---

## 4. Deliverable 2 — the handover note

`docs/HANDOVER.md`. Audience: **Intan (Legal Executive)**, the confirmed system owner. Assume no
technical vocabulary, no GitHub account, and that this is printed or pasted into an email.

**One page is a hard constraint, not a target.** A two-page handover note is not read.

Proposed skeleton — headings and the question each answers, prose left to Amir (Decision M5-3):

| Section | The question it answers |
|---|---|
| What this system is | "What am I looking at?" |
| Who can do what | "What can I do, and what can my colleague do?" |
| The one rule to know | "Why can't my colleague see that agreement?" |
| Getting in | "What's the address and do I have an account?" |
| Adding a person | "A new project owner joined — what do I do?" |
| What it does not do | "Can it email reminders / hold the signed PDFs / import the old spreadsheet?" |
| What is known to be imperfect | "Is this a bug or is it me?" |
| When something looks wrong | "Who do I contact?" |
| Where the technical detail lives | For whoever comes after, not for her |

Two of those need care:

**"What is known to be imperfect"** is the honest translation of Section 6's deferred defects into
plain language — no defect IDs, no severities. *"If you type a partner's short name, the duplicate
warning may not spot the long-name version of the same partner"* is useful. *"DEF-003, Major,
deferred"* is not.

**"When something looks wrong"** is the section that decides whether this document is worth writing.
Per Decision M5-7 it carries both a generic escalation path and a line naming Intan directly, in
plain language.

One wrinkle to resolve while writing it: the note is addressed *to* Intan and also names her as the
person to contact. Both are correct, for different readers — she is the owner, and anyone else who
picks the document up needs to know that. Write the contact section so it reads correctly to a
colleague reading over her shoulder, not only to her. If that proves awkward in one paragraph, split
it: "who owns this system" and "what to do when it looks wrong".

---

## 5. Who writes what

Amir's brief asks this explicitly, so it gets its own section rather than a footnote.

### Amir writes — audience judgment, not mechanics

**`docs/HANDOVER.md` prose.** This is the clearest case in the milestone. The note's quality is
entirely a function of knowing the reader: how much Intan wants to know, which words land and which
cause anxiety, what she will actually do when something breaks, and what political framing the
"not built" list needs so it reads as scope rather than as failure. None of that is in the repo, so
neither an agent nor I can supply it — we can only produce something that *sounds* right, which is
worse than a plainly-written page from someone who knows her.

Amir also owns the contact/escalation section outright (M5-7) and the plain-language wording of the
known limitations, because those are commitments about people and support, not descriptions of code.

An agent may produce the **skeleton** — the headings above, with one-line prompts under each — for
Amir to write into. That is scaffolding, not drafting.

### OpenCode Go drafts, Amir reviews — mechanical and conventional

- **README sections 2–9.** Setup, commands, roles table, test instructions, code orientation. These
  follow well-established convention, are verifiable against the repo, and have a right answer. This
  is exactly the work an agent is good at.
- **The QA artifact corrections (Section 6).** Cross-reference accuracy: reconciling names, counts
  and statuses against `grep`. Mechanical, tedious, and unforgiving of inattention.
- **The three missing tests** E-5, E-6, E-7, if Decision M5-5 approves them.
- **The user-management component and its tests**, if Decision M5-2 approves them.

### Amir verifies by execution — cannot be delegated

- **The README setup path, run on a clean clone**, ideally on the home PC mentioned in `BUILD_PLAN`
  §1. An agent can confirm a README is *plausible*; only a clean-machine run proves it. Every
  README-quality problem worth catching — a missing step, a wrong order, a command that needs a
  privilege the reader doesn't have — surfaces only this way.
- **The handover note read cold**, ideally by a non-technical colleague who is not Intan — someone
  with no stake in it will stumble on the sentences she would politely read past.
- **The 5–10 historical agreements entered by hand** (Section 7). This is the widest-coverage manual
  test in the project and it cannot be delegated: it needs real collaboration data and judgment about
  what "believable" looks like.

### Claude Code

Neither. This plan, the M5 handoff prompt, and a review of the diff before commit.

---

## 6. Defect dispositions

Nothing gets silently dropped. Every open entry gets an M5 disposition, and the disposition is
recorded in `docs/qa/defect-log.md` whether it is "fixed" or "still open".

| ID | Severity | Current status | Proposed M5 disposition |
|---|---|---|---|
| DEF-001 | Major | Closed | Unchanged. Fixed and regression-tested in M3. |
| DEF-002 | Major | Closed | Unchanged. |
| DEF-003 | Major | Accepted — deferred | **Re-defer**, and surface in the handover note's plain-language limitations. Decision M5-4(a). |
| DEF-004 | Major | Closed | Unchanged. |
| DEF-005 | Minor | Accepted — deferred | **CLOSE — fixed.** M5-8 decided the semantics; the `expired()` and `expiringSoon()` scopes are corrected and E-7 verifies all three paths agree. Supersedes the M5-4(b) re-deferral. |
| DEF-006 | Minor | **Closed — wrongly** | **Supersede.** Reopen as superseded by DEF-008; correct the fix line, which names a test that does not exist. Decision M5-4(c). |
| DEF-007 | Minor | Accepted — deferred | **Re-defer** unchanged. Needs a schema column; out of scope for a handover milestone. |
| DEF-008 | Minor | Accepted | **CLOSE — working as intended.** M5-8's boundary rule makes exactly-90-days stale, which is what the code already does. Its characterisation test becomes a correctness test. See M5-10 for making it deterministic. |
| DEF-009 | — | *new* | **QA artifacts cite tests that do not exist** (V-3). Log it, fix it, close it in M5. |
| DEF-010 | — | *new* | **Traceability matrix claims full coverage for unwritten cases** (V-6). Log it, fix it, close it in M5. |

Three points worth stating rather than burying.

**DEF-003 ships open at Major severity.** That is deliberate and it should look deliberate. The
alternative — quietly downgrading it to Minor so the log looks tidier at handover — is severity
laundering, and a defect log that does it is worth nothing. The user-facing consequence is a
duplicate partner row, which is a data-hygiene annoyance rather than a data-loss event; the fix needs
real partner-name data to tune against, and that data arrives with the historical import, if it ever
does. Recording a PM decision to ship a known Major defect is exactly what the log is for.

**DEF-005 and DEF-008 were the same question, and it has been answered.** Both asked where a date
boundary falls — does an agreement expire at the start or the end of its expiry date, and is
"90 days stale" reached on day 90 or day 91? Neither was a code problem; both were business rules
nobody had decided, deferred to Ms. Haniza, who then left.

**M5-8 resolves both**, and both close in M5 rather than being re-deferred. The blocker was never the
code; it was the missing decision, and the decision turned out to cost two lines. That is worth
noting for the next time something is deferred "pending confirmation": the confirmation was cheap and
the deferral was expensive, and the gap between them was four months of the constant being described
in three documents as provisional.

**DEF-009 and DEF-010 are defects in the QA work itself.** Logging them is not self-flagellation —
it is the strongest possible demonstration that the log is used honestly, including against its own
author. They are also the two findings most likely to be caught by an outside reviewer, so having
found them first is worth more than never having had them.

---

## 7. Excel import — deferred; historical data entered by hand instead

`BUILD_PLAN.md` places Excel import in two separate places, and both point the same way. Section 2
M5 lists it as *"(Deferred) … **only if explicitly requested**; it's a data-cleaning project, not a
coding one."* Section 2's 🚫 block lists it under "Explicitly OUT of MVP scope."

Nobody has requested it. The 2022–2024 spreadsheet has not been seen, and the schema already carries
the scars of anticipating it — most date columns are nullable specifically because historical rows
lack them. Building an importer against a file nobody has looked at means guessing at the column
names, the date formats, the partner-name spellings, and the campus attributions, then discovering
during a live import that every guess needs revisiting.

There is also a sequencing argument. An import lands hundreds of rows whose partner names are exactly
the near-duplicates DEF-003 does not catch. Importing before fixing the matcher converts a latent
defect into a populated database of duplicates.

**Decision M5-1 — approved (c):** defer, and make the deferral visible in three places — the handover
note's "what it does not do", the README's not-built list, and this plan.

The original recommendation carried one action item: ask Ms. Haniza for a sample export. That route
closed on 26 Aug. If pricing the import ever matters, the question now goes to Intan as *"does the
2022–2024 collaboration spreadsheet still exist, and can Legal send a copy?"* — a records question
she can answer, unlike a schema question. It is not an M5 blocker either way.

### The M5-1 addendum — 5–10 historical agreements entered by hand

Approved as a separate M5 task alongside the deferral. Amir registers 5–10 realistic historical
agreements through the actual UI, using real 2022–2026 collaboration data with identifying details
altered. It serves two purposes at once, and the second is the one that is easy to undersell.

**It populates the system with believable content before handover.** An empty register does not
demonstrate anything, and a register full of `Agreement::factory()` noise demonstrates less than
nothing to a Legal user opening it for the first time.

**It is the widest-coverage manual test in the project.** Ten real registrations exercise the create
form, partner quick-create, the campus and PIC dropdowns, date handling, the activity feed, the list
filters and the detail view against data that was not designed to make them pass — which is precisely
the condition none of the 114 automated tests run under.

**Sequencing: this comes after M5b, not during M5a.** Both `UserSeeder` and `StaffSeeder` are guarded
to `local`/`testing` (V-9), so on the handover environment the PIC dropdown is empty until real users
exist. Entering ten agreements before there is anyone to assign as PIC either leaves `pic_user_id`
null on all of them — untested path, unbelievable content — or forces the terminal workaround M5b
exists to remove. Build the user form, create the real people, then enter the agreements.

**What to watch while doing it** — this is the observation list, not a test script. Record anything
surprising as a defect rather than fixing it in flight:

- Does the similar-partner warning fire on the *real* partner-name variations in the data? DEF-003
  predicts it will miss acronym-vs-full-name pairs. Confirming that against real names, rather than
  against a test fixture, is worth more than the characterisation test.
- Does an agreement with no expiry date read as "Indefinite" everywhere, never as a blank cell?
- Does the activity feed read correctly in plain English to someone who was not watching it being
  written?
- Do the stale badges appear where the real dates say they should?
- Was any field needed that does not exist, or any field required that the real data does not have?
  That is the single most valuable thing this exercise can surface, and it will not come from a test.

**On altered identifying details:** correct call, and worth stating in the handover note so nobody
later mistakes the seeded history for the authoritative record. The register is internal, but a
dataset that is realistic-but-altered has to be labelled as such or it will eventually be cited as
fact.

---

## 8. Files

Split across **three** handoffs, in this order: **defects → M5a → M5b.** The defect fix runs first
because E-7 must assert the corrected expiry behaviour, and that behaviour has to exist before the
test can pass.

### M5-defects — modify (the only application code in M5 outside M5b)

```
app/Models/Agreement.php                            expired() scope: '<' → '<=' (M5-8)
                                                    expiringSoon() scope: exclude today (M5-8)
                                                    hasStaleProjectStatus() + isExpired(): day-based
                                                    comparison (M5-10) — four methods, no others
tests/Feature/Agreement/BoundaryConditionsTest.php  add E-7 as a CORRECTNESS test; retitle the
                                                    existing DEF-008 test's comment from
                                                    characterisation to decided-rule
docs/qa/defect-log.md                               close DEF-005 (fixed) and DEF-008 (as intended)
docs/qa/traceability-matrix.md                      BR-05 and BR-23 point at the new coverage
```

### M5a — create

```
docs/HANDOVER.md                                    headings + one-line prompts ONLY.
                                                    Amir writes every word of prose (M5-3).
```

### M5a — modify, documentation

```
README.md                                           full rewrite (Section 3)
AGENTS.md                                           fix the stale line 3 (V-7); update Future considerations
                                                    to record the M5-2 outcome; keep the UI/UX note as future work
docs/qa/defect-log.md                               correct DEF-005's false verification line; supersede
                                                    DEF-006 with DEF-008; add DEF-009, DEF-010;
                                                    re-defer DEF-003/005/007/008 with M5-8 as the new rationale
docs/qa/traceability-matrix.md                      remove the four "pending user exercise" clauses;
                                                    correct BR-05, BR-18, BR-23 statuses; add DEF-008 to BR-23
docs/qa/test-plan.md                                exit criteria: 119 → actual count; Roles table:
                                                    Ms. Haniza → Intan, with her actual role stated
docs/qa/README.md                                   sample JSON: 119 → actual count
docs/qa/test-cases.md                               reconcile TC-024, TC-033, TC-036, TC-039 with reality
docs/BUILD_PLAN.md                                  tick M1–M5; record the Excel-import deferral against §2;
                                                    note that its "Ms. Haniza to confirm" clauses are
                                                    superseded by M5-8
```

### M5a — modify, tests

```
tests/Feature/Agreement/BoundaryConditionsTest.php  add E-5 (1–2 char partner floor) and
                                                    E-6 (null PIC renders as an em dash — amended,
                                                    see the specification note below).
                                                    E-7 has moved to the defects handoff.
```

### M5b — create

```
resources/views/components/⚡user-manager.blade.php  Livewire 4 SFC: list, create, activate/deactivate
tests/Feature/Admin/UserManagementTest.php          8 tests (Section 9, M5-2)
```

### M5b — modify

```
routes/web.php                                      one route inside a role:admin group
resources/views/layouts/app.blade.php               one nav link, rendered only for admins
README.md                                           "Managing users" section leads with the form,
                                                    keeps the artisan commands as the fallback
docs/HANDOVER.md                                    "Adding a person" section becomes answerable
docs/qa/test-cases.md                               new cases for the new rule
docs/qa/traceability-matrix.md                      new row BR-26
```

### Not modified, in either handoff

```
Every migration. Every seeder. The Agreement global scope. The three-role enum.
vite.config.js and npm behaviour.
Every model EXCEPT the four methods of app/Models/Agreement.php named above,
which are M5-8 and M5-10 and belong to the defects handoff alone.
Existing tests' assertions — M4's audit is finished and its corrections are committed.
Historical records: docs/handoff-m1..m4.md, docs/architecture-plan.md, -m3.md, -m4.md.
```

### Expected test count

- After M5-defects: **115** (114 + E-7).
- After M5a: **117** (115 + E-5 + E-6).
- After M5b: **125** (117 + 8).

Both numbers must be recorded from a run, never projected. Projecting the count is how the M4
artifacts came to claim 119 (V-1, V-5).

---

## 9. Decisions — M5-1 to M5-7 approved 26 Aug 2026

Amir approved every recommendation as written. The options are kept on the record so the rejected
alternatives and their reasons survive; **none of M5-1 through M5-7 is reopenable during M5.**

| # | Question | Options considered | **Approved 26 Aug 2026** |
|---|---|---|---|
| **M5-1** | Excel import — build now or defer? | (a) Build it in M5. (b) Defer, and write a scoping doc for it. (c) Defer, documented in the handover note and README only; request a sample file from Legal. | **(c) — approved,** with an addendum: Amir registers 5–10 realistic historical agreements by hand through the UI (Section 7). `BUILD_PLAN` says "only if explicitly requested" and nobody has requested it. Importing before DEF-003 is fixed would bulk-load exactly the duplicates the matcher misses. |
| **M5-2** | PIC self-service — build an admin-only user form, or document the command? | (a) Build a minimal admin-only user manager: list, create, activate/deactivate. (b) Write step-by-step instructions with screenshots for `php artisan user:create`. (c) Build (a), and keep the commands documented as the fallback. | **(c) — approved, and now required rather than optional.** `AGENTS.md`'s design philosophy makes "can Intan do this without a terminal?" the standard; (b) fails it outright. Sequenced as a separate handoff after the documentation, per "Sequencing, if approved" below. |
| **M5-3** | Who writes the handover note? | (a) Amir writes it; an agent supplies only the heading skeleton. (b) OpenCode Go drafts it; Amir edits. (c) Amir writes the audience-facing sections; an agent drafts the factual ones. | **(a) — approved.** An agent draft of a note for one specific non-technical person reads plausibly and gets the reader wrong, which is harder to repair than a blank page. |
| **M5-4** | Deferred-defect dispositions. | Per Section 6: (a) DEF-003 re-defer at Major, surfaced in plain language. (b) DEF-005 re-defer, but write the E-7 test it already claims and correct the false verification. (c) DEF-006 superseded by DEF-008. Alternative for each: fix in M5, or downgrade severity. | **All three as tabled — approved. DEF-003's severity is explicitly not downgraded.** (b) is load-bearing: the test is what makes DEF-005's existing text true. |
| **M5-5** | The three undelivered M4 tests — write them, or document their absence? | (a) Write E-5, E-6, E-7 → 117 tests, and every artifact claim becomes true. (b) Amend the artifacts to record them as known gaps. (c) Write E-7 only; document E-5 and E-6 as gaps. | **(a) — approved.** Already approved once in M4; three small tests; E-6 covers a genuine `Attempt to read property on null` path. Writing them is cheaper than explaining their absence. |
| **M5-6** | Handover note format and location. | (a) `docs/HANDOVER.md` only. (b) A Word/PDF one-pager outside the repo. (c) Both — Markdown in the repo as the source of truth, exported to PDF. | **(c) — approved.** Markdown in the repo is versioned and travels with the code; the PDF is what Intan actually opens. Keep them in sync by regenerating, never by editing the PDF. |
| **M5-7** | The "when something looks wrong" section — what does it say? | (a) Name a specific successor and their contact details. (b) Describe the escalation path generically (UniKL IT → the repo → this document). (c) Both — the generic path plus a named contact line. | **(c) — approved,** naming Intan directly, in plain non-technical language. A named person alone leaves a dead end if they move on; a generic path alone tells an anxious reader nothing. |

### Decisions arising after approval — M5-8, M5-9 and M5-10

All three come from the 26 Aug context change and were resolved the same day. They are recorded here
with their options intact rather than buried in prose, so that the rejected alternatives survive
alongside the choices.

| # | Question | Options | Resolution |
|---|---|---|---|
| **M5-8** | ~~Who decides the date-boundary semantics?~~ | — | **RESOLVED 26 Aug 2026 — see below. Fixed in M5, not deferred to M6.** |
| **M5-9** | User management is admin-only (`canManageUsers()` returns `isAdmin()`). Intan is Legal. Which account does she hold? | (a) Intan holds an `admin` account; `canManageUsers()` is unchanged. (b) Widen `canManageUsers()` to include `legal`. (c) Split it — `legal` may create and deactivate `viewer` (PIC) accounts only; `admin` manages all roles. | **(a) — RESOLVED 26 Aug 2026. Intan gets an `admin` account; no other Legal staff get admin regardless of seniority.** The backup admin is deliberately deferred — see below. (b) erases the only distinction between `admin` and `legal` and lets any Legal user mint admins, destroying the security argument that made the web form preferable to shell access. (c) is more precise but adds branching logic for a team this size. (a) needs no code: M5b's tests still assert `legal` gets 403. |

### M5-9, resolved — admin is granted on trust, not on rank

**PM decision, 26 Aug 2026.** Intan holds the `admin` account as system owner. **No other Legal staff
get admin regardless of organisational seniority** — admin access follows trust and system ownership,
not rank.

That sentence is the reason this decision is written down rather than just enacted. "The most senior
person available should obviously have the highest access" is the default assumption in most
organisations, and it is how an account gets handed to a manager who has no reason to hold it, six
months after everyone who understood the reasoning has moved on. Both the README's roles section and
the handover note should state the principle, not just the current assignment.

**The backup admin is deliberately deferred.** Candidates named and rejected for now: Amir, whose
involvement is ending and who is therefore a transitional measure at best; and the Assistant Manager,
who started 3 Aug 2026 and has not yet built the tenure the principle above requires. Neither is a
"no", both are a "not yet".

**The accepted risk, stated precisely so it is not a surprise later.** A single admin is a single
point of failure for user management. If Intan is unavailable, nobody can add or deactivate a user
through the web form — the exact dependency M5-2 exists to remove. The mitigation is not that the
risk is small; it is that the failure is **recoverable and temporary**: `php artisan user:create`
still works for whoever has server access, so the fallback is "this needs a developer again for a
while", not "this is unrecoverable". That is a reasonable trade against handing admin to someone who
has not earned it, and it is why keeping the artisan commands documented (Decision M5-2's "(c)", not
"(a)") turns out to be load-bearing rather than belt-and-braces.

Revisit when the Assistant Manager has tenure, or sooner if Intan takes extended leave.

### M5-8, resolved — an agreement expires at the START of its expiry date

**PM decision, 26 Aug 2026, informed by Intan and Ms. Haniza as the actual Legal users.** On the
`expiry_date` itself the agreement is already **Expired** — not "still valid", not "expiring soon".

The underlying principle is that a boundary date is crossed at the *start* of that day, and it
settles DEF-005 and DEF-008 together:

| Path | Today | Under M5-8 | Change |
|---|---|---|---|
| `isExpired()` | expired | expired | none — already correct |
| `expired()` scope | not expired | **expired** | `whereDate('expiry_date', '<', today())` → `'<='` |
| `expiringSoon()` scope | included | **excluded** | range becomes `(today, today+N]` instead of `[today, today+N]` |
| `hasStaleProjectStatus()` at exactly 90 days | stale | stale | none behaviourally — but see M5-10 |

**DEF-005 closes as fixed. DEF-008 closes as working-as-intended** — the behaviour Amir found by hand
is now the decided behaviour, and its characterisation test becomes a correctness test.

**E-7 changes with it.** It was specified as a characterisation test pinning a known inconsistency.
There is no longer an inconsistency to pin: it becomes a correctness test asserting that all three
paths agree that an agreement expiring today is expired. Drop the "expected to fail when M5 fixes
this" comment — that comment would now be actively wrong, and DEF-004 is the standing reminder of
what a misleading test comment costs.

**This puts application code in M5.** Two scope methods in `app/Models/Agreement.php`. That is a
deliberate, approved exception to the milestone's documentation-only framing, and it is confined to
its own handoff (`docs/handoff-m5-defects.md`) which runs **before** M5a — because E-7 must assert
the corrected behaviour, and the corrected behaviour has to exist first.

**Two facts worth knowing before approving the diff.** Neither changes the decision; both change what
to expect from it.

*Nothing user-facing changes.* `expired()` and `expiringSoon()` have **no callers** anywhere in
`app/`, `routes/` or `resources/views/` — only `isExpired()` does, at `⚡agreement-show.blade.php:124`.
The scopes are model API exercised solely by tests today. The fix still matters: they are the
building blocks any future expiry report sits on, and shipping them with the wrong boundary means
whatever gets built on them inherits it silently. But the browser looks identical afterwards.

*No existing test breaks.* All four expiry scope tests in `AgreementScopesTest` use `subDays(1)`,
`addDays(30)` and `addDays(120)`. None sits on today, so all four survive both changes unchanged.

### M5-10 — the boundary comparisons are correct by accident · APPROVED 26 Aug 2026

Found while specifying the M5-8 fix. **Approved as (a): make both comparisons explicit and
deliberate rather than accidentally-correct-by-timing.** Folded into the same handoff, since it
touches the same two lines of thinking.

`hasStaleProjectStatus()` compares an instant against `now()->subDays(90)` **recomputed at check
time**. Exactly-90-days is reported stale only because real time elapses between storing the
timestamp and reading it — DEF-008's own root-cause section says as much. `isExpired()` has the same
shape: `expiry_date->isPast()` on a midnight-cast date is false at exactly midnight.

Under M5-8 both answers are now the *intended* ones, which is why this is not a defect. But they are
intended answers arrived at by microsecond drift, and no test currently freezes time. The day anyone
writes `freezeTime()` or `travelTo()` in a staleness or expiry test, the exactly-on-boundary case
flips and `BoundaryConditionsTest` fails for reasons that will look supernatural.

| Options | |
|---|---|
| (a) Make both comparisons day-based, so the decided rule holds deterministically | `project_status_updated_at->startOfDay()->lte(now()->subDays(self::STALE_AFTER_DAYS)->startOfDay())` and `expiry_date->startOfDay()->lte(today())`. Behaviour-preserving under M5-8; removes the time-freeze tripwire. |
| (b) Fix only `hasStaleProjectStatus()` | Amir's note says `isExpired()` needs no change, which is true behaviourally. |
| (c) Leave both; document the fragility | Costs nothing today, and detonates later in a way nobody will connect to this decision. |

**Approved: (a).** Two one-line changes in the file already being opened, both making an
already-approved rule explicit rather than emergent. It also makes E-7's "all three paths agree"
assertion true by construction instead of by luck — which is the whole point of writing E-7.

Note what this does **not** change: no behaviour, and no existing assertion. The staleness test's
89/90/91 cases return exactly what they return today; they simply stop depending on how long the
test took to run. `isExpired()` gains an explicit `startOfDay()->lte(today())` in place of an
`isPast()` whose correctness relied on the current time never being exactly midnight.

With M5-10 folded in, the defects handoff touches four methods of `app/Models/Agreement.php` and
nothing else in the application.

### Specification note on E-6 — the approved test has no target

Found while specifying the three tests for the M5a handoff, and recorded here rather than resolved
silently in the prompt.

E-6 was approved in M4 as *"Partner with `country_id = null` renders on the list and detail"*, with
the rationale: *"`PartnerFactory` may always set a country; a real quick-created partner leaves it
null."* Both halves are wrong against the current code:

- `PartnerFactory::definition()` sets `'country_id' => null`. It never sets a country. The null case
  is therefore already exercised by every existing test that renders a partner.
- No view dereferences `$partner->country` at all. `⚡agreement-show` renders
  `{{ $agreement->partner?->name }}`; `⚡agreements-index` renders the same. Country appears only as
  the quick-create dropdown's options. This is also why the matrix honestly marks BR-17
  **Not covered — no UI renders the country domestic flag yet**.

Written as approved, E-6 would pass on an empty template and could not fail — the exact defect class
DEF-004 was raised for. Adding a test that cannot fail to a suite whose credibility was just repaired
would be a poor trade.

**Amended specification, same intent.** Every rendered relation is already null-safe
(`campus?->code`, `pic?->name ?? '—'`, `partner?->name`), so there is no crash path to cover. What is
genuinely unasserted is the *display contract* for a null PIC: the detail view promises an em dash,
and nothing checks it. E-6 becomes **"an agreement with `pic_user_id = null` renders as `—` on the
detail view and its row still renders in the list."** That is the same assertion class as the
already-covered "null expiry renders as Indefinite" (BR-05), it matches the "null PIC" edge case in
Amir's original M4 brief, and it can fail.

Count is unchanged at 117. If Amir prefers E-6 dropped outright rather than amended, the count
becomes 116 and BR-18 gains a documented non-gap; say so before sending the M5a handoff.

### Decision M5-2, at length

Approved as (c) and now a requirement. The argument is kept because the M5b handoff is written
against it, and because a senior dev who does not understand *why* a feature landed in a
documentation milestone will make the wrong call the first time a detail is ambiguous.

**The case for building it.** `BUILD_PLAN` defines MVP done as *"colleagues can use it daily without
you."* Adding a person is not a rare administrative event — it is how a PIC becomes selectable in the
agreement form at all, which is an ordinary part of registering an agreement. Today that requires
shell access on the server (V-9, V-10). Option (b) — written instructions with screenshots — assumes
Legal staff can get a terminal on the host, which for an internal UniKL deployment is unlikely and,
if arranged, means granting shell access to run a command that can mint `admin` accounts. That is a
worse security posture than a role-gated web form, not a lighter one.

**The case against.** It is a feature, and M5 is explicitly not a feature milestone. It adds a
component, a route, a nav item and tests to a milestone whose value is documentation. Scope added at
the end of a project is the scope most likely to arrive half-finished.

**Why the recommendation is (c) and not (a).** The commands should survive as the documented fallback
regardless: they work when the app cannot boot, they are the only path to the *first* admin on a new
deployment (both seeders are env-guarded), and they need no UI. The form is an addition for the
common case, not a replacement.

**What "minimal" means, precisely.** Three capabilities, no more: list users with role and active
state; create a user with name, email, role and an initial password; toggle `is_active`. Explicitly
excluded — editing an existing user's name or email, deleting users (deactivate instead, consistent
with archive-never-delete), password reset from the UI (`user:password` already owns that), and any
bulk operation.

**It needs no schema change**, which is what keeps it inside M5's constraints. `users.password` is
NOT NULL and no mail transport is configured (V-10), so the admin sets an initial password in the
form and communicates it out-of-band. There is no invite-link option without new infrastructure, and
adding that infrastructure would be a genuine scope breach.

**One guard the form must have that the command does not need.** An admin must not be able to
deactivate their own account. On a system whose whole point is that no terminal is available, an
admin who deactivates themselves has locked the organisation out of user management with no recovery
path short of the developer M5-2 exists to remove. The command has no such risk because anyone
running it already has a shell. Disable the toggle on the acting user's own row and reject it
server-side — rendering alone is not enforcement, the same rule M3 applied to write permissions.

Role editing is excluded, so this is the only lockout path that exists. That is why one guard covers
it.

**Tests it needs** (8): admin can reach the page; legal gets 403; viewer gets 403; creating a user
persists the correct role; duplicate email is rejected; an admin cannot deactivate their own account;
deactivating a user removes them from the PIC dropdown but leaves them visible on agreements they are
already assigned to (this is the one that interacts with existing behaviour —
`AgreementFormTest::test_edit_form_retains_an_inactive_pic_already_assigned` already pins the other
half); a deactivated user cannot log in.

**Sequencing — approved.** Documentation first, feature second, as two handoffs. If the form slips or
grows, M5's documentation deliverable still lands and the form becomes M6. If it is bundled into one
handoff and runs long, the README and handover note are what get rushed — which inverts the
milestone's priorities exactly.

---

## 10. M5 done when

1. `README.md` has been followed end-to-end on a clean clone by a human, on a machine that did not
   previously have the project, and the run ended at a login page with a working account.
2. `docs/HANDOVER.md` fits on one page, is written in Amir's own words, has been read cold by someone
   who did not build the system, and has been exported to PDF for Intan.
3. Every test name cited anywhere in `docs/qa/` resolves to a real `public function` in `tests/` —
   verified by re-running the V-3 cross-check, not by inspection.
4. The test count stated in `test-plan.md` and `docs/qa/README.md` matches the number `composer test`
   actually prints.
5. `traceability-matrix.md` contains no "pending user exercise" clause, and no row claims
   **Fully covered** while naming a case that does not exist.
6. Every defect in `defect-log.md` has a status that the rest of the log does not contradict.
7. `AGENTS.md` describes the repo as it is.
8. `composer test` is green at **115** after the defects handoff, **117** after M5a and **125** after
   M5b — each number recorded from a run, not projected.
8a. An agreement whose `expiry_date` is today reports **expired** from all three code paths, and the
   defect log shows DEF-005 and DEF-008 closed rather than deferred.
9. `vendor\bin\pint` clean on anything touched.
10. Intan holds a working account, has used the user-management form to add one person herself, and
    did not need a terminal, a developer, or Amir to do it. This is the only acceptance check that
    tests what the milestone is actually for.
11. 5–10 historical agreements are in the handover environment, entered by hand, with anything
    surprising from the observation list in Section 7 logged as a defect rather than fixed in flight.
12. Amir can name, without opening the repo, the three things this system deliberately does not do.
