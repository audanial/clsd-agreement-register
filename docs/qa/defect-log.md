> # CLSD Agreement Register — Defect Log
>
> **Milestone:** M4 (QA Portfolio Evidence)  
> **Severity scale**
> - **Critical** — Data loss, security breach, or complete inability to use a core feature.
> - **Major** — Wrong data or behaviour in a feature users rely on; silent failure that misleads users.
> - **Minor** — Inconsistency, missing boundary coverage, or UX gap that does not block daily use.
> - **Trivial** — Cosmetic issue or documentation typo with no functional impact.

---

## DEF-008 — `hasStaleProjectStatus()` treats exactly 90 days as stale, not just 91+

| | |
|---|---|
| **Reported by** | Amir (writing BoundaryConditionsTest.php as a learning exercise) |
| **Date found** | 24 Aug 2026 |
| **Component** | `app/Models/Agreement.php` — `hasStaleProjectStatus()` |
| **Severity** | Minor |
| **Priority** | Low — cosmetic boundary imprecision, not a functional break |
| **Status** | Accepted — documented, not fixed in M4 |

**Description**
`STALE_AFTER_DAYS` is `90`, and the intent (per the model's naming and the M2/M3 architecture
plans) is that an agreement becomes stale only *after* 90 days have passed — i.e., exactly 90
days ago should still count as "not stale," with 91+ being the first genuinely stale day.

In practice, an agreement whose `project_status_updated_at` is set to exactly
`now()->subDays(90)` is *already* treated as stale by `hasStaleProjectStatus()`.

**Steps to reproduce**
1. Create an agreement with `project_status_updated_at` set to `now()->subDays(90)`.
2. Call `$agreement->hasStaleProjectStatus()`.
3. Observe the result.

**Expected**
`false` — exactly 90 days should not yet be stale.

**Actual**
`true` — exactly 90 days is already treated as stale.

**Root cause**
The check is:
```php
$this->project_status_updated_at->lt(now()->subDays(self::STALE_AFTER_DAYS));
```
This compares the stored timestamp against `now()->subDays(90)` **computed at the moment the
check runs**, not at the moment the timestamp was originally set. Since some amount of real
time (even milliseconds) always passes between when a record's timestamp is set and when
`hasStaleProjectStatus()` is later called on it, the freshly-computed "90 days ago" boundary
has always moved slightly forward by the time the comparison happens. This makes the stored
timestamp always evaluate as "less than" (i.e., older than) the boundary, even when it was
set to exactly 90 days at creation time.

**Verification**
Confirmed reproducible — the boundary test
(`tests/Feature/Agreement/BoundaryConditionsTest.php::test_has_stale_project_status_is_true_past_the_boundary`)
fails consistently on the 90-day case across multiple runs, not a one-off timing fluke.

**Why the existing tests missed it**
No existing test exercised the exact 90-day boundary before this one. The only prior coverage
(`UserRoleTest`/`AgreementCastsTest`'s stale-status test) used a "fresh" case at 30 days and a
"stale" case using the `staleProjectStatus()` factory state (which sets `STALE_AFTER_DAYS + 1`,
i.e. 91 days) — deliberately clear of the boundary, so the imprecision was never exercised.

**Disposition**
Not fixed in M4 — this is a one-day boundary imprecision with low real-world impact (nobody is
likely to act differently based on an agreement being flagged stale one day early), and fixing
it correctly would require deciding the exact intended semantics (should the comparison be
`<=` instead of `<`? Should it be based on calendar days rather than exact elapsed time?) —
a business/design question similar in nature to DEF-005's expiry-day ambiguity. Recommend
revisiting alongside DEF-005 in M5, since both stem from the same category of issue: date/time
boundary comparisons that are precise in code but ambiguous in intent.

---

## DEF-007 — Concurrent edits are unguarded (last write wins)

| | |
|---|---|
| **Reported by** | M4 architecture review (decision coverage audit) |
| **Date found** | 24 Aug 2026 |
| **Component** | Agreement edit / status-change flows |
| **Severity** | Minor |
| **Priority** | Low |
| **Status** | Accepted — deferred |

**Description**
Two users editing the same agreement can overwrite each other's changes. The activity log records a `from` value that the second user may never have seen on screen. There is no optimistic locking or timestamp check.

**Steps to reproduce**
1. User A opens agreement X for edit.
2. User B opens the same agreement and saves a change to the notes field.
3. User A saves a different change without refreshing.
4. User B's change is overwritten silently.

**Expected**
The second save is rejected or warned because the underlying record changed since the page was loaded.

**Actual**
The second save succeeds and overwrites the first.

**Root cause**
No concurrency-control mechanism exists. Adding one requires a schema change (e.g., `lock_version` integer or `updated_at` compare-and-set), which M4 is barred from making.

**Fix / disposition**
Deferred to M5. Recommended fix: optimistic locking with a `lock_version` column on `agreements` and a hidden field in the edit form. The risk is accepted because the user base is a single small internal team and the edit collision window is minutes per year.

**Verification**
Documented as an accepted risk. No automated test added because the fix is deferred; the gap is recorded in `traceability-matrix.md` (BR-25) and the test-plan risk register.

---

## DEF-006 — Staleness threshold has no boundary tests

| | |
|---|---|
| **Reported by** | M4 architecture review (finding F-5) |
| **Date found** | 24 Aug 2026 |
| **Component** | `Agreement::hasStaleProjectStatus()` |
| **Severity** | Minor |
| **Priority** | Medium |
| **Status** | Closed |

**Description**
`hasStaleProjectStatus()` uses a strict less-than comparison against `now()->subDays(STALE_AFTER_DAYS)`. Exactly `STALE_AFTER_DAYS` days is therefore **not** stale. The suite had no tests at 89, 90, or 91 days, so a change to the constant or a drift from strict to non-strict comparison would not be caught.

**Steps to reproduce**
1. Create an agreement with `project_status_updated_at` exactly `STALE_AFTER_DAYS` days ago.
2. Render the agreement list or detail page.

**Expected**
The stale badge is **not** shown at exactly 90 days; it **is** shown at 91 days.

**Actual**
Behaviour was correct but untested.

**Root cause**
Missing boundary test for the off-by-one surface.

**Fix**
Added `BoundaryConditionsTest::test_staleness_boundary_at_ninety_days_is_not_stale` covering 89, 90, and 91 days, deriving the threshold from `Agreement::STALE_AFTER_DAYS`.

**Verification**
`composer test` green; the test fails if the comparison is changed to `lte` or if the constant is moved.

---

## DEF-005 — One agreement, three answers about expiry on its expiry date

| | |
|---|---|
| **Reported by** | M4 architecture review (finding F-4) |
| **Date found** | 24 Aug 2026 |
| **Component** | `Agreement::isExpired()`, `Agreement::expired()` scope, `Agreement::expiringSoon()` scope |
| **Severity** | Minor |
| **Priority** | Low |
| **Status** | Accepted — deferred |

**Description**
For an agreement whose `expiry_date` is today, the code gives three different answers:

| Path | Answer | Why |
|---|---|---|
| `isExpired()` | Expired | `expiry_date->isPast()` on a `date` cast is midnight today, so it is "past" from 00:00:01 |
| `expired()` scope | Not expired | `whereDate('expiry_date', '<', today())` |
| `expiringSoon()` scope | Expiring soon | `whereBetween('expiry_date', [today(), …])` includes today |

**Steps to reproduce**
1. Create an agreement with `expiry_date = today()`.
2. Call `isExpired()`, `Agreement::expired()->exists()`, and `Agreement::expiringSoon()->exists()`.

**Expected**
A single, consistent answer across all three paths.

**Actual**
Three different answers.

**Root cause**
The business has not decided whether an agreement expires at the start or the end of its expiry date. Each path was written without reconciling against the others.

**Fix / disposition**
Deferred to M5 pending a business decision. The inconsistency is pinned by `BoundaryConditionsTest::test_expiry_today_behaviour_is_consistently_recorded`, a characterisation test that asserts the current behaviour and references this defect.

**Verification**
Characterisation test passes and documents the known inconsistency. The defect will be closed when the business rule is chosen and all three paths are aligned.

---

## DEF-004 — Stale-badge test cannot fail

| | |
|---|---|
| **Reported by** | M4 architecture review (finding F-3) |
| **Date found** | 24 Aug 2026 |
| **Component** | `tests/Feature/Agreement/AgreementsIndexTest.php`, `badges/stale.blade.php` |
| **Severity** | Major |
| **Priority** | High |
| **Status** | Closed |

**Description**
`AgreementsIndexTest::test_stale_badge_is_shown_only_for_stale_rows` created agreements titled `"Stale"` and `"Fresh"`, then asserted `assertSee('Stale')`. Because the badge text is the literal word "Stale", the assertion was satisfied by the row title rather than the badge. There was no negative assertion on the fresh row.

**Steps to reproduce**
1. Remove the stale badge from the list row template.
2. Run `composer test`.

**Expected**
The test should fail because the badge is absent.

**Actual**
The test passed because the word "Stale" still appeared as the agreement title.

**Root cause**
Test assertion was weaker than the test name claimed. The assertion target matched title text, not badge markup.

**Fix**
Updated `test_stale_badge_is_shown_only_for_stale_rows` to assert on the badge's `title` attribute text (`"Project status last updated"` / `"Project status never updated"`) and added a negative assertion that the fresh row does not carry that title text. Renamed fixture titles to `"Fresh Row"` and `"Stale Row"` so titles cannot satisfy badge assertions.

**Verification**
Confirmed the corrected test fails when the badge is removed from the template and passes when it is present. `composer test` green.

---

## DEF-003 — Similar-partner matching does not satisfy the requirement it was written for

| | |
|---|---|
| **Reported by** | M4 architecture review (finding F-2) |
| **Date found** | 24 Aug 2026 |
| **Component** | Partner quick-create, `⚡agreement-form.blade.php` similar-partner warning |
| **Severity** | Major |
| **Priority** | Medium |
| **Status** | Accepted — deferred |

**Description**
Decision M3-4 rejected exact-match dedup because it *"misses 'UiTM' vs 'Universiti Teknologi MARA', which is the duplicate that actually happens in this register."* The implementation uses substring matching (`LOWER(name) like '%{input}%'`). That also misses the exact case it was chosen for: `%uitm%` does not match "universiti teknologi mara" in either direction.

**Steps to reproduce**
1. Create a partner named "Universiti Teknologi MARA".
2. Start creating a new agreement.
3. In the new-partner field, type "UiTM".

**Expected**
The similar-partner warning lists "Universiti Teknologi MARA" because it is likely the same partner.

**Actual**
No warning is shown.

**Root cause**
Substring matching is insufficient for acronym/token overlap. The fix requires acronym matching, token normalisation, or a curated alias list.

**Fix / disposition**
Deferred to M5. This is feature work, and the current substring matching is still an improvement over no warning at all. A characterisation test (`AgreementFormTest::test_acronym_partner_name_does_not_trigger_similar_warning`) pins the current behaviour and will fail loudly when M5 improves the matcher — which is the signal it exists to give.

**Verification**
Characterisation test passes against current code. The test body references this defect and states that it is expected to fail if the matcher is improved.

---

## DEF-002 — Date-warning requirement is only half tested

| | |
|---|---|
| **Reported by** | M4 architecture review (finding F-1) |
| **Date found** | 24 Aug 2026 |
| **Component** | `tests/Feature/Agreement/AgreementFormTest.php`, `⚡agreement-form.blade.php` date warning |
| **Severity** | Major |
| **Priority** | High |
| **Status** | Closed |

**Description**
`AgreementFormTest::test_expiry_before_effective_shows_a_warning_but_still_saves` set an expiry earlier than the effective date, called `save()`, and asserted the redirect and database row. It never asserted that the warning appeared. If `dateWarning` returned `null` unconditionally, all tests would still pass.

**Steps to reproduce**
1. Make `dateWarning` always return `null`.
2. Run `composer test`.

**Expected**
The test named "shows a warning" should fail.

**Actual**
All tests passed.

**Root cause**
The test asserted the "still saves" half of Decision D3 but not the "shows a warning" half.

**Fix**
Updated `test_expiry_before_effective_shows_a_warning_but_still_saves` to assert the warning text is present before calling `save()`, using `assertSet('dateWarning', ...)` and `assertSee` on the rendered amber text. The existing save assertions remain intact.

**Verification**
Confirmed the corrected test fails when `dateWarning` returns `null` unconditionally and passes with the current implementation. `composer test` green.

---

## DEF-001 — Activity log records the new status as both the "from" and "to" value

| | |
|---|---|
| **Reported by** | Amir (manual testing, M3 acceptance walkthrough) |
| **Date found** | 21 Aug 2026 |
| **Component** | Activity logging — `⚡agreement-show`, `⚡agreement-form` |
| **Severity** | Major |
| **Priority** | High |
| **Status** | Closed — fixed and verified |

**Description**
Changing an agreement's status wrote an activity row whose `from` value equalled its `to` value, producing feed entries reading "Document status changed from signed to signed". The row was written and attributed correctly; only the previous value was wrong.

**Steps to reproduce**
1. Log in as a legal user.
2. Open an agreement whose `document_status` is `pending`.
3. Change the document status to `signed` and save.
4. Read the activity feed.

**Expected**
"Document status changed from pending to signed", and `meta = {"field":"document_status","from":"pending","to":"signed"}`.

**Actual**
"Document status changed from signed to signed", with `meta.from` equal to `meta.to`.

**Root cause**
The previous value was read from the model *after* the model had already been mutated. In `⚡agreement-show`, the new value was assigned to `$this->agreement->$field` before `$from` was captured; in `⚡agreement-form`, the status was read after `fill()` had overwritten it. In both cases the "original" read returned the new value.

**Fix** (commit `18d7ad7`)
Capture the original value before mutation. `⚡agreement-show.blade.php` reads `$from` before the assignment; `⚡agreement-form.blade.php` snapshots `$originalStatus` before `fill()`.

**Verification**
Two regression tests, one per entry point, asserting `from !== to` and the exact description text: `AgreementActivityLogTest::test_status_change_activity_records_distinct_from_and_to_values` and `::test_edit_form_status_change_records_distinct_from_and_to_values`. Re-verified manually in the browser.

**Why the existing tests missed it**
`test_meta_records_the_field_and_the_from_and_to_values` asserted that `meta` contained the keys and that `to` was correct, but never asserted that `from` differed from `to`. An assertion on presence is not an assertion on correctness — the lesson that produced the regression tests above.
