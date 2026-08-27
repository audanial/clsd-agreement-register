# CLSD Agreement Register — QA Artifacts

This directory contains the QA portfolio evidence produced in **Milestone M4**. The artifacts are plain Markdown so they diff in git and render on GitHub.

---

## What each artifact is

| File | Audience | Purpose |
|---|---|---|
| `test-plan.md` | QA lead, PM, future tester | Frame for the whole verification effort: scope, approach, environment, entry/exit criteria, roles. |
| `test-cases.md` | Non-developer testers, QA reviewers, auditors | Executable cases organised by business rule. Cases are mapped to automated tests or explicitly marked **Manual only** with a reason. |
| `defect-log.md` | PM, QA lead, senior dev | Register of real defects found during M3 and the M4 review, with severity, root cause, fix or deferral rationale, and verification. |
| `traceability-matrix.md` | QA reviewers, auditors | Business rule → test case → automated test → status. Status is allowed to be **Partial** or **Not covered** when a gap is documented. |
| `README.md` | Anyone picking up the repo | This file. How to run the suite and how to keep the artifacts current. |

---

## How to run the test suite

```bash
composer test
```

`laravel/pao` detects an AI agent and emits compact JSON. Parse the `result` field:

```json
{"tool":"phpunit","result":"passed","tests":125,"passed":125,...}
```

If you are not an AI agent, PHPUnit renders normal pretty output; the acceptance criterion is the same: the `result` field must be `passed`.

To check formatting on test files only:

```bash
vendor\bin\pint tests/
```

---

## How to keep these artifacts current

When application code changes in M5 or later:

1. **Add or rename a test?** Update `test-cases.md` and `traceability-matrix.md`. Every automated test named in those documents must still exist.
2. **Change a business rule?** Update the rule statement in `traceability-matrix.md`, the affected cases in `test-cases.md`, and add a defect entry to `defect-log.md` if the change reveals a gap.
3. **Close a deferred defect (DEF-003, DEF-007)?** Move its status from `Deferred` to `Closed` and record the verification test.
4. **Add a manual-only case?** State the reason explicitly. Do not mark a case automated unless a test file actually covers it.
5. **Never let the matrix claim "Fully covered" for a rule that has no passing test.** A matrix with gaps is more credible than a matrix that is fiction.

---

## Reading order

1. `test-plan.md` — establishes the frame.
2. `traceability-matrix.md` — maps business rules to coverage; shows where the gaps are.
3. `test-cases.md` — the executable detail behind each rule.
4. `defect-log.md` — the findings and judgment calls that explain why some rules are only partially covered.
