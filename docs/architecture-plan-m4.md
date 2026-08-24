# CLSD Agreement Register — Architecture Plan for M4 (QA Portfolio Evidence)

> **Author:** Claude Code (Lead Architect) · **For:** Amir (PM) · **Date:** 24 Aug 2026
> **Status:** APPROVED by Amir on 24 Aug 2026 — all 7 decisions resolved as recommended
> (Section 8). Handed to OpenCode Go via `docs/handoff-m4.md`. No decision here is reopenable.
> **Scope:** M4 ONLY — QA artifacts plus a small, targeted set of test corrections and additions.
> M5 (handover docs, README, Excel import) stays out of scope.
> **Companion docs:** `docs/architecture-plan.md` (M1+M2), `docs/architecture-plan-m3.md` (M3),
> `docs/BUILD_PLAN.md` Section 2 "M4".

---

## Context

M1, M2 and M3 are merged. The register works: a legal user can create, edit and track an
agreement end to end, and a viewer can never see a `pending` row. 111 automated tests pass.

M4 is different in kind from everything before it. It adds almost no application behaviour.
Its purpose is to turn work that has already been done into **evidence that it was done
properly** — the documents a QA role expects to see and that a passing test suite does not,
by itself, provide.

That distinction matters for how M4 is built. "Write more tests" is the wrong frame; the suite
is already reasonably thorough. The right frame is: **a test suite records what was checked, but
not what was decided, what was found, or what was consciously left alone.** A hiring manager
reading `AgreementFormTest.php` cannot tell that the date warning is deliberately not a
validation rule, that a real bug was found in manual testing and fixed, or that concurrent
editing was considered and knowingly deferred. Those are the artifacts M4 produces.

There is a second reason M4 earns its place, discovered while verifying the repo for this plan:
**reviewing the suite as a QA artifact found real problems in it.** Two tests assert something
weaker than their names claim, one approved decision is only half covered, and one is
implemented in a way that does not satisfy the requirement it was written for. None of these
would ever have surfaced from a green test run. Section 5 documents them, and they become the
defect log's most useful entries — because a defect log containing only bugs someone else found
is not evidence of QA skill.

---

## 1. Verified state (read on this repo, 24 Aug 2026)

Verified by reading the code and running the suite, not by trusting the plan docs.

```
{"tool":"phpunit","result":"passed","tests":111,"passed":111,"assertions":284,"duration_ms":8031}
```

### M1, M2 and M3 are complete

| Checked | Result |
|---|---|
| Commit history | M3 shipped as `18d7ad7`. M1 (`073f07a`) and M2 (`2b5f116`) before it. |
| M3 components | `⚡agreements-index`, `⚡agreement-form`, `⚡agreement-show` all present in `resources/views/components/`. |
| Badges | `badges/document-status`, `badges/project-status`, `badges/stale` — plain Blade, as approved. |
| `app/Actions/RecordAgreementActivity.php` | Present, invokable, writes `meta` as an array. |
| `database/seeders/StaffSeeder.php` | Present and registered (M3-3). |
| `Campus::active()` | Present (M3-2, the single authorized model edit). |
| Layout | Dual `{{ $slot }}` / `@yield` support, nav and flash region present (M3-1, M3-7). |
| Test count | 111 passing, 284 assertions, ~8s. |
| Working tree | Clean except `AGENTS.md`, which carries Amir's own uncommitted note about non-technical Legal staff and `user:create`. Not touched by this plan. |

### Drift from `docs/architecture-plan-m3.md` — one item, and it is an improvement

M3 shipped **two tests beyond the approved list**, both regression tests for the bug found in
manual testing:

- `test_status_change_activity_records_distinct_from_and_to_values` (detail page)
- `test_edit_form_status_change_records_distinct_from_and_to_values` (edit form)

The fix is visible in both components. `⚡agreement-show.blade.php:38` captures `$from` *before*
assigning the new value; `⚡agreement-form.blade.php:80` captures `$originalStatus` *before*
`fill()`. This is exactly the right response to a manual-testing find — fix, then pin it — and it
is the seed of `DEF-001`.

**No negative drift.** Nothing in M1/M2/M3 needs re-doing.

---

## 2. What M4 is, and what it is not

**M4 delivers four documents and a small number of test changes.**

| M4 is | M4 is not |
|---|---|
| Formal test cases a non-developer can execute by hand | A rewrite of the automated suite |
| A defect register with real entries and real root causes | Hypothetical or invented example bugs |
| Traceability from business rule to test, so coverage is demonstrable | A coverage percentage |
| A written record of what was deliberately *not* tested, and why | Silence about the gaps |

The documents must be **honest**. A defect log listing only defects found by someone else, a
traceability matrix with no gaps, and a test-case document that maps every rule to a passing test
are all less credible than the real thing. The real thing includes Section 5's findings, and
saying so is the point.

---

## 3. The QA artifact set

All four live under a new `docs/qa/` directory. Markdown, so they diff in git and render on
GitHub — approved as Decision M4-6.

### 3.1 `docs/qa/test-plan.md`

The shortest of the four. Establishes the frame the other three assume.

- **Scope:** what is under test (the agreement register: auth, roles, agreement CRUD, the pending
  visibility rule, activity logging) and what is out (file uploads, Excel import, analytics — not
  built).
- **Approach:** automated feature tests via PHPUnit/Livewire, plus documented manual verification
  for anything the suite structurally cannot cover (browser rendering, real session behaviour).
- **Environment:** Laravel 13 · Livewire 4 · PHP 8.3 · SQLite; in-memory SQLite for tests per
  `phpunit.xml`; `laravel/pao` emits compact JSON under an AI agent.
- **Entry criteria:** milestone code merged, suite green.
- **Exit criteria:** every business rule in the traceability matrix maps to at least one executed
  test case; every open defect has a severity and a disposition.
- **Roles:** who wrote the tests, who executed the manual passes.

### 3.2 `docs/qa/test-cases.md`

The centrepiece. **Organised by business rule, not by file** — this is what makes it a QA
document rather than a rendering of the test suite.

Sections: Authentication & Session · Roles & Authorization · Pending Visibility (the core
business rule) · Agreement Creation & Validation · Dates & Expiry Semantics · Partner Management ·
Project Status & Staleness · Activity Logging · List, Search & Filtering · Data Integrity.

Every case in this shape:

```
### TC-018 — Viewer cannot see a pending agreement in the list

| | |
|---|---|
| **Feature area** | Pending Visibility |
| **Business rule** | BR-03 — `document_status = pending` means "in Legal vetting" and must never be visible to non-Legal users |
| **Priority** | Critical |
| **Type** | Automated + manual |
| **Preconditions** | A `viewer` user exists; one agreement with `document_status = pending` and one with `signed` exist |

**Steps**
1. Log in as the viewer.
2. Navigate to `/agreements`.
3. Observe the result list.

**Expected result**
Only the signed agreement is listed. The pending agreement does not appear, and the
pagination total does not count it.

**Automated by**
`tests/Feature/Agreement/AgreementsIndexTest.php::test_viewer_does_not_see_pending_agreements_in_the_list`
`tests/Feature/Agreement/PendingVisibilityTest.php::test_a_viewer_cannot_see_pending_agreements`

**Status** Pass (24 Aug 2026)
```

Roughly 60–70 cases. Not one per automated test — several tests collapse into one case, and a few
cases (browser rendering, session expiry) are manual-only and marked as such. **Cases with no
automated backing are marked "Manual only" with a reason**, because pretending otherwise is the
failure mode this document exists to avoid.

### 3.3 `docs/qa/defect-log.md`

A register, newest first, each entry complete. `DEF-001` is the real M3 bug and is written in
full as the reference entry:

```
## DEF-001 — Activity log records the new status as both the "from" and "to" value

| | |
|---|---|
| **Reported by** | Amir (manual testing, M3 acceptance walkthrough) |
| **Date found** | 21 Aug 2026 |
| **Component** | Activity logging — `⚡agreement-show`, `⚡agreement-form` |
| **Severity** | Major — the audit trail was silently wrong |
| **Priority** | High — fix before M3 sign-off |
| **Status** | Closed — fixed and verified |

**Description**
Changing an agreement's status wrote an activity row whose `from` value equalled its `to`
value, producing feed entries reading "Document status changed from signed to signed". The
row was written and attributed correctly; only the previous value was wrong.

**Steps to reproduce**
1. Log in as a legal user.
2. Open an agreement whose `document_status` is `pending`.
3. Change the document status to `signed` and save.
4. Read the activity feed.

**Expected** "Document status changed from pending to signed", and
`meta = {"field":"document_status","from":"pending","to":"signed"}`.

**Actual** "Document status changed from signed to signed", with `meta.from` equal to
`meta.to`.

**Root cause**
The previous value was read from the model *after* the model had already been mutated. In
`⚡agreement-show`, the new value was assigned to `$this->agreement->$field` before `$from`
was captured; in `⚡agreement-form`, the status was read after `fill()` had overwritten it. In
both cases the "original" read returned the new value.

**Fix** (commit `18d7ad7`)
Capture the original value before mutation. `⚡agreement-show.blade.php:38` reads `$from`
before the assignment; `⚡agreement-form.blade.php:80` snapshots `$originalStatus` before
`fill()`.

**Verification**
Two regression tests, one per entry point, asserting `from !== to` and the exact description
text: `AgreementActivityLogTest::test_status_change_activity_records_distinct_from_and_to_values`
and `::test_edit_form_status_change_records_distinct_from_and_to_values`. Re-verified manually
in the browser.

**Why the existing tests missed it**
`test_meta_records_the_field_and_the_from_and_to_values` asserted that `meta` contained the
keys and that `to` was correct, but never asserted that `from` differed from `to`. An
assertion on presence is not an assertion on correctness — the lesson that produced the
regression tests above.
```

`DEF-002` onward come from Section 5. Each gets the same treatment. Severity uses a stated scale
(Critical / Major / Minor / Trivial) defined once at the top of the file.

### 3.4 `docs/qa/traceability-matrix.md`

Business rule → test case → automated test → status. One row per rule, with the rule's source
cited so it is clear these are not invented after the fact.

```
| Rule | Statement | Source | Test cases | Automated tests | Status |
|---|---|---|---|---|---|
| BR-03 | `pending` is never visible to non-Legal users | AGENTS.md; migration comment | TC-018, TC-019, TC-020, TC-021 | 8 in `PendingVisibilityTest`, 3 in `AgreementsIndexTest`, 2 in `AgreementShowTest` | Fully covered |
| BR-09 | An expiry earlier than the effective date warns but never blocks the save | D3, `architecture-plan.md` | TC-034, TC-035 | `AgreementFormTest::test_expiry_before_effective_shows_a_warning_but_still_saves` | **Partial — see DEF-002** |
```

Roughly 20–25 rules drawn from `AGENTS.md` "Domain rules baked into the schema", the migration
comments, and the approved decisions D1–D10 and M3-1–M3-7. **The `Status` column is allowed to say
"Partial" or "Not covered", with a link to the defect or to the documented-and-accepted entry in
Section 6.** A matrix with no gaps in it is a matrix nobody checked.

### 3.5 `docs/qa/README.md`

One page: what each document is, who it is for, how to re-run the suite, and how to keep the
artifacts current when code changes. Prevents the set from silently rotting after M4.

---

## 4. Decision coverage audit

Amir asked specifically whether D3, D6, D7, M3-4, M3-6 and the guarded stamp are explicitly
covered. Verified test by test:

| Decision | Verdict | Evidence |
|---|---|---|
| **D6** — never write `document_status = 'expired'` | **Explicit** | `AgreementFormTest::test_expired_is_not_an_option_for_document_status` asserts a validation error on the value. The three-option rule is enforced at `⚡agreement-form.blade.php:129`. |
| **D7** — `notArchived()` is local, not a second global scope | **Explicit** | `AgreementScopesTest::test_not_archived_excludes_archived_rows` and `::test_archived_returns_only_archived_rows` prove both directions; `AgreementsIndexTest` proves the list applies it by default and lifts it on demand. A bare `Agreement::query()` returning archived rows is what makes it local, and the archived-by-default index test depends on exactly that. |
| **M3-6** — log `created` + `status_changed`, never `updated` | **Explicit, both directions** | `test_creating_an_agreement_writes_a_created_activity` and `test_editing_an_ordinary_field_writes_no_updated_activity`. The negative test is the valuable half. |
| **Guarded stamp** | **Explicit** | `AgreementFormTest::test_saving_without_changing_project_status_does_not_restamp` (line 205) with a 10-day-old timestamp, plus the positive `test_changing_project_status_stamps_project_status_updated_at`. |
| **D3** — soft date warning | **⚠️ Half covered — see F-1** | The "still saves" half is tested. The "shows a warning" half is not asserted anywhere. |
| **M3-4** — similar-partner warning | **⚠️ Covered, but not for the case the decision was made for — see F-2** | Tested with a literal substring, which is not the duplicate the decision cited. |

---

## 5. Findings from this review

Five findings, discovered by reading the suite as a QA artifact rather than running it. Each
becomes a defect-log entry.

### F-1 → `DEF-002` · The date-warning requirement is only half tested · **Major**

`AgreementFormTest::test_expiry_before_effective_shows_a_warning_but_still_saves` (line 114) sets
an expiry earlier than the effective date, calls `save()`, and asserts the redirect and the
database row. **It never asserts that the warning appears.**
`test_no_warning_when_only_one_date_is_present` asserts `dateWarning` is `null` — absence only.

Consequence: if `dateWarning` (`⚡agreement-form.blade.php:207`) returned `null` unconditionally,
**all 111 tests would still pass**, and D3's user-facing half would be silently gone. The feature
currently works; the test does not protect it.

Fix: add an assertion on the warning text to the existing test, or add a paired test. This
requires touching an existing test's assertions, which Amir approved as Decision M4-1.

### F-2 → `DEF-003` · Similar-partner matching does not satisfy the requirement it was written for · **Major**

Decision M3-4 rejected exact-match dedup in these words: *"exact-match dedup (a) misses 'UiTM' vs
'Universiti Teknologi MARA', which is the duplicate that actually happens in this register."*

The implementation (`⚡agreement-form.blade.php:225`) is
`whereRaw('LOWER(name) like ?', ['%'.mb_strtolower($this->newPartnerName).'%'])` — substring
matching, which **also misses that exact case**. `%uitm%` does not match "universiti teknologi
mara" in either direction. The test
(`AgreementFormTest::test_similar_partner_name_shows_a_warning`, line 273) uses
`"Teknologi MARA"` against `"Universiti Teknologi MARA"` — a literal substring — so it passes
while the motivating case remains unhandled.

This is a genuine requirement-versus-implementation gap, and a good portfolio entry precisely
because it was found by re-reading the requirement, not by running anything. The fix is feature
work (acronym/token matching, or a curated alias list) and belongs in M5 — deferred there by Decision M4-2.

Also unhandled, worth documenting in the same entry: names differing only in punctuation or
spacing ("UniKL" / "Uni-KL" / "Uni KL"), and the deliberate `< 3` character floor at line 221,
which means a genuine two-letter partner name ("UM") never triggers a warning.

### F-3 → `DEF-004` · A test that cannot fail · **Major**

`AgreementsIndexTest::test_stale_badge_is_shown_only_for_stale_rows` (line 159) creates one
agreement titled `"Fresh"` and one titled `"Stale"`, then asserts `assertSee('Stale')` and
`assertSee('Fresh')`.

The stale badge's rendered text (`badges/stale.blade.php`) is the literal word **"Stale"** — so
the assertion is satisfied by the row *title*, not the badge. There is no negative assertion on
the fresh row. **The test passes whether the badge renders on every row, on one row, or on
none.** Its name claims "only for stale rows"; it verifies neither half.

Fix: assert on something distinguishing (the badge's `title` attribute text, e.g. "Project status
last updated", or a count of the badge markup) and add the negative assertion. Requires touching
an existing test, approved as Decision M4-1.

### F-4 → `DEF-005` · One agreement, three answers about expiry · **Minor**

For an agreement whose `expiry_date` is **today**:

| Code path | Answer | Why |
|---|---|---|
| `Agreement::isExpired()` (line 124) | **Expired** | `expiry_date->isPast()` on a `date` cast is midnight today, so it is "past" from 00:00:01 |
| `Agreement::expired()` scope (line 105) | **Not expired** | `whereDate('expiry_date', '<', today())` |
| `Agreement::expiringSoon()` scope (line 97) | **Expiring soon** | `whereBetween('expiry_date', [today(), …])` includes today |

The badge on the detail page and a future "expired agreements" report would disagree about the
same row on its expiry day. Low impact today because no list filters on `expired()` yet — which
is exactly why it should be recorded now, before M5 builds something that does.

Deciding *which* answer is right is a business question (does an agreement expire at the start or
the end of its expiry date?) and the fix touches model logic M4 is barred from changing. Approved
approach (Decision M4-3): document it, pin current behaviour with a characterisation test, and defer
the fix to M5.

### F-5 → `DEF-006` · No boundary test on the staleness threshold · **Minor**

`AgreementFactory::staleProjectStatus()` uses `STALE_AFTER_DAYS + 1`; the only other timestamps in
the suite are 10 and 30 days. `hasStaleProjectStatus()` uses `lt(now()->subDays(90))` — a strict
comparison, so **exactly 90 days is not stale**. Nothing tests 89, 90 or 91.

This is the classic off-by-one surface, and `STALE_AFTER_DAYS` is explicitly expected to change
when Ms. Haniza confirms the number. Cheap to cover, and it is the test most likely to catch a
regression when that value moves. Add it — Section 6.

---

## 6. Edge cases — add, or document and accept

Amir asked which edge cases a thorough QA review would flag, which are worth testing inside M4,
and which are reasonable to leave documented. Both columns are answered, with the reason stated,
because "we chose not to test this because X" is itself an artifact.

### Worth adding as tests in M4

| # | Edge case | Why it earns a test |
|---|---|---|
| E-1 | Staleness at 89 / exactly 90 / 91 days | F-5. Strict `lt` boundary on a value expected to change. |
| E-2 | The date warning's **text actually renders** | F-1. Closes the D3 gap; three lines. |
| E-3 | Stale badge **absent** on a fresh row | F-3. The missing negative assertion. |
| E-4 | Acronym partner name ("UiTM" vs "Universiti Teknologi MARA") | F-2. A characterisation test that pins today's behaviour and fails loudly if M5 fixes it — which is the point. |
| E-5 | Partner name of 1–2 characters | The `< 3` floor at line 221 is deliberate but undocumented and untested. |
| E-6 | Partner with `country_id = null` renders on the list and detail | `PartnerFactory` may always set a country; a real quick-created partner leaves it null, since the field is optional. Untested null-render paths are where blank cells and `Attempt to read property on null` live. |
| E-7 | Agreement expiring **today** | F-4. Characterisation test across all three code paths, so the inconsistency is recorded in executable form. |
| E-8 | A viewer's pagination total excludes pending rows | Proven at relation-count level in M2, not at the paginator. The count is what leaks a hidden row's existence. |

Eight tests, one new file plus small additions. This is deliberately modest: M4's value is in the
documents, and a large batch of new tests would dilute rather than strengthen it.

### Documented, deliberately not tested

| # | Edge case | Reason for accepting |
|---|---|---|
| E-9 | **Concurrent edits** — two users editing one agreement; last write wins, and the loser's activity row records a "from" value that was never on screen | Real, but the fix is optimistic locking, which needs a schema column M4 is barred from adding. Single small internal team; the window is minutes per year. Documented in the defect log as **DEF-007, Status: Accepted — deferred**, with the fix named so M5 can price it. |
| E-10 | Session expiry mid-edit — form contents lost on re-login | Standard Laravel behaviour; no custom code owns it. Not a defect in this system. |
| E-11 | Very large result sets / pagination performance | No production data volume yet, and the eager loading that would matter is already in place and reviewed. Re-assess after the Excel import lands in M5. |
| E-12 | SQLite-vs-MySQL behavioural differences (`whereRaw LOWER(...)`, `meta->field` JSON paths) | The app runs on SQLite by decision. Flagged because those two constructs are the ones that would need re-verification if the database ever changes. |
| E-13 | Browser/JS-level Livewire behaviour (`wire:model` debounce, `navigate:true` history) | Livewire's own responsibility; testing it tests the framework. Covered by the manual walkthrough instead. |
| E-14 | File upload paths (`agreement_files`) | Table and relation exist, no UI. Nothing to test until M5 builds it. |

---

## 7. Files

### Create — QA artifacts
```
docs/qa/README.md                 index; what each artifact is and how to keep it current
docs/qa/test-plan.md              scope, approach, environment, entry/exit criteria, roles
docs/qa/test-cases.md             ~60-70 formal cases, organised by business rule
docs/qa/defect-log.md             DEF-001 (real M3 bug) + DEF-002..DEF-007 from Section 5/6
docs/qa/traceability-matrix.md    business rule -> test case -> automated test -> status
```

### Create — tests
```
tests/Feature/Agreement/BoundaryConditionsTest.php    E-1, E-5, E-6, E-7 (boundary + null-path cases)
```

### Modify — tests only, no application code
```
tests/Feature/Agreement/AgreementFormTest.php      add E-2 (warning text), E-4 (acronym characterisation)
tests/Feature/Agreement/AgreementsIndexTest.php    fix F-3's assertion, add E-3 negative case, add E-8
```

### Not modified
```
Every application file. Every migration. Every model. Every seeder.
```

M4 changes **no application code at all.** If implementing these tests appears to require an
application change, that is a defect discovery — log it and bring it to Amir, do not fix it
inside M4.

### Expected test count after M4

111 → approximately **119**, plus two corrected assertions.

---

## 8. Decisions — all approved 24 Aug 2026

Amir approved every recommendation as written. The options are kept on the record so the rejected
alternatives and their reasons survive; **none of these is reopenable during M4.** Decision M4-1 is
the one that grants a permission the milestone brief otherwise withholds — read it before touching
any existing test.

| # | Question | Options considered | **Approved 24 Aug 2026** |
|---|---|---|---|
| **M4-1** | F-1 and F-3 require changing assertions in two existing, currently-passing tests. Amir's brief bars this "unless a genuine gap is found and approved". | (a) approve both corrections and log them as DEF-002 / DEF-004 · (b) leave the tests as they are and add new tests alongside · (c) leave and document only | **(a) — approved.** This is precisely the exception the brief anticipated. Both tests assert something weaker than their names promise, and one cannot fail. Option (b) leaves a misleading test in the suite next to a correct one, which is worse than either fixing or removing it. For a QA portfolio the strongest possible story is "I audited my own suite, found two tests that proved nothing, and fixed them" — that is a more valuable artifact than a suite that was never questioned. |
| **M4-2** | F-2: the similar-partner matcher does not catch the case Decision M3-4 was written for. | (a) log as DEF-003, add a characterisation test pinning current behaviour, defer the fix to M5 · (b) fix the matching now (token/acronym matching or an alias table) · (c) amend M3-4's rationale to describe what was actually built | **(a) — approved.** (b) is feature work and M4 is explicitly not feature work; the current behaviour is a genuine improvement over nothing, and the gap is a limitation rather than a break. (c) is the option to avoid on principle: rewriting the requirement to match the implementation is how traceability becomes fiction. Recording the gap honestly is worth more than closing it silently. |
| **M4-3** | F-4: `isExpired()`, `expired()` and `expiringSoon()` disagree about an agreement expiring today. | (a) log as DEF-005, pin all three behaviours with a characterisation test, defer · (b) fix now — pick one semantic and align all three · (c) document without a test | **(a) — approved.** The fix needs a business answer (does an agreement expire at the start or the end of its expiry date?) and touches model logic M4 cannot change. The characterisation test is the valuable part: it makes the inconsistency executable, so whoever resolves it in M5 gets an immediate red-to-green signal. |
| **M4-4** | How much of the defect log is Amir's find versus this review's. | (a) DEF-001 (Amir, manual testing) plus DEF-002..DEF-007 from Section 5/6 · (b) DEF-001 only, keeping the log strictly to bugs found in testing | **(a) — approved.** A log with one entry reads as a template. Six entries with distinct severities, root causes and dispositions (Closed / Deferred / Accepted) shows the full lifecycle, including the judgment calls about what *not* to fix. DEF-001 stays the headline because it was found in manual testing and fixed — the complete loop. |
| **M4-5** | E-9 (concurrent edits) has no coverage and no guard. | (a) document as DEF-007 "Accepted — deferred", naming optimistic locking as the fix · (b) add a schema column and implement it · (c) omit it | **(a) — approved.** (b) is a migration, which M4 is barred from touching, for a risk that is real but rare in a single-team internal app. (c) is the wrong instinct: a QA reviewer who spotted the race and wrote down why it was accepted demonstrates more than one who never mentions it. |
| **M4-6** | Format for the test-case document. | (a) Markdown tables in `docs/qa/` · (b) CSV/Excel for import into a test-management tool · (c) both, with the CSV generated | **(a) — approved.** Diffs in git, renders on GitHub, and reviews in a browser without downloading anything. (c) creates two sources of truth that drift. If a specific employer asks for a spreadsheet, exporting from (a) later is trivial. |
| **M4-7** | Should `docs/qa/` include screenshots from the manual walkthrough? | (a) no — text only · (b) yes, a handful for the pending-visibility and stale-badge cases | **(a) — approved for M4.** Screenshots date quickly, bloat the repo, and none of the artifacts depend on them. Revisit in M5, where a handover document for non-technical Legal staff is exactly the place screenshots earn their keep (and where Amir's own `AGENTS.md` note about `user:create` already points). |

---

## 9. Verification

**M4 done when:**

1. `composer test` green at approximately 119 tests. `laravel/pao` prints compact JSON — parse the
   `result` field; do not re-run expecting pretty output.
2. `vendor\bin\pint` clean on every touched PHP file. (Only test files should appear.)
3. `git status` shows **no application file modified** — no `app/`, no `database/migrations/`,
   no `resources/views/components/`. If any appears, M4 has exceeded its scope.
4. Every business rule in `traceability-matrix.md` resolves: each named automated test exists and
   is spelled correctly. A matrix pointing at a test that does not exist is worse than no matrix.
   A grep of the test names in the matrix against `grep -rn "public function test" tests/` should
   return a hit for every one.
5. Every test case in `test-cases.md` is either mapped to a named automated test or explicitly
   marked "Manual only" with a reason. No case is silently unmapped.
6. `defect-log.md` contains DEF-001 through DEF-007, each with severity, root cause, and a
   disposition of Closed, Deferred, or Accepted. No entry is hypothetical.
7. The four new tests derived from Section 5 (E-2, E-3, E-4, E-7) each **fail before their fix or
   characterisation and pass after** — worth confirming individually, since a test that passes
   against broken behaviour is what F-1 and F-3 were.

**Not built in M4** (deliberately): README/handover documentation, Excel import, file upload UI,
dashboard analytics, the fixes deferred by Decisions M4-2, M4-3 and M4-5. Those are M5, unplanned
until M4 is approved and merged.
