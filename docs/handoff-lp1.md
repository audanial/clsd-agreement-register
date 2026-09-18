# LP1 — Legal Submission Portal Completion Handoff

> **Prepared:** 17 Sep 2026
> **Updated:** 18 Sep 2026
> **Status:** LP1, the DEF-014 remediation, and the LP1 Amendment 2 role walkthrough are verified locally. The working tree is ready for Amir's final diff review; no production action has been taken.
> **Governing plan:** `docs/architecture-plan-lp1.md`, including Amendments 1 and 2.
> **Scope:** LP1-A and LP1-B only. Document handling and Legal review actions remain outside LP1.

## Outcome

LP1 now provides the first usable Legal Submission Portal workflow:

- Requesting Staff can create a submission, see only their own submissions, and open their own read-only submission detail.
- Legal and Admin users share one queue and can open every submission.
- Viewer users have no portal access.
- Every new submission starts as `pending` and receives a `submission_created` activity in the same database transaction.
- Portal pages and Livewire update requests enforce the same role and record-level authorization rules.
- Requester-entered content is treated as Private & Confidential and is escaped when rendered.

LP1 does not provide uploads, review actions, comments, revision requests, notifications, status controls, or Agreement Register linking. Those remain later portal milestones.

## Verification evidence — 18 Sep 2026

### Automated checks

- Full suite: **343 tests, 1,138 assertions** (18 Sep 2026), including the DEF-014 stale-snapshot regression and the hostile URL-backed pending-filter tests.
- Pint formatting check: passed (18 Sep 2026).
- Git whitespace/error check: passed (18 Sep 2026).

### Browser walkthrough

The local application was exercised through the real browser against the development SQLite database.

| Role / state | Check | Result |
| --- | --- | --- |
| Requesting Staff | Navigation shows **Register** and **My Submissions**; dashboard shows **Create Submission**, **My Submissions**, and **Open register** | Pass — 18 Sep 2026 |
| Requesting Staff | Submitted a fictional MOU request for the active MIIT campus | Pass — 17 Sep 2026 |
| Requesting Staff | Redirected to the new detail page with success message, `pending` status, Malaysian timestamp, confidentiality notice, and initial activity | Pass — 17 Sep 2026 |
| Requesting Staff | Own queue contained submission `#1`; its detail retained the confidentiality notice, pending status, initial activity, and read-only controls | Pass — re-verified 18 Sep 2026 |
| Requesting Staff | Register hid pending agreement `#2`, omitted the Pending filter and create action, returned `404` for `/agreements/2`, rendered agreement `#1` read-only, and returned `403` for create/edit URLs | Pass — 18 Sep 2026 |
| Different requester | Own queue omitted submission `#1`; direct access to `/submissions/1` returned `404 Not Found` with no submission content | Pass — re-verified 18 Sep 2026 |
| Legal | Navigation showed **Submission Queue** and **Register**; pending agreement `#2` remained visible; shared queue showed submission `#1` and requester identity | Pass — re-verified 18 Sep 2026 |
| Legal | Submission detail showed requester identity and the initial activity; no mutation controls were present | Pass — re-verified 18 Sep 2026 |
| Admin | Shared queue showed the submission and requester identity | Pass — 17 Sep 2026; not repeated on 18 Sep |
| Admin | Direct access to `/submissions/create` returned `403 Forbidden` | Pass — 17 Sep 2026; not repeated on 18 Sep |
| Viewer | Register remained available and read-only; pending agreement `#2` returned `404`; portal navigation was absent and direct portal access returned `403 Forbidden` | Pass — 18 Sep 2026 |
| Guest | Agreement Register, submission list, and submission-create URLs redirected to `/login` | Pass — 18 Sep 2026 |
| Browser runtime | No console errors or warnings during the original requester workflow | Pass — 17 Sep 2026; console not re-inspected on 18 Sep |

For the 18 Sep confidentiality check, Legal temporarily changed agreement `#2` from `awaiting_partner` to `pending`. Requesting Staff and Viewer were denied as expected while Legal retained access. Amir restored agreement `#2` to `awaiting_partner` after the walkthrough.

The browser was also checked at the available narrow/mobile viewport on 17 Sep 2026. Dashboard, form, list, and detail content remained usable with no blank page or error overlay; the narrow/mobile check was not repeated on 18 Sep.

## Local verification records

Only fictional development data was used:

- requester: `requester@unikl.edu.my` (`Test Requester`);
- second requester: `other.requester@unikl.edu.my` (`Other Test Requester`), created locally for the ownership-denial check; and
- submission `#1`: `LP1 browser verification — 17 Sep 2026`, partner `Fictional Partner Berhad`.

These are development-database records only and are not part of the Git diff or any production deployment.

## DEF-014 remediation status (18 Sep 2026)

A confirmed Major-severity, High-priority issue (DEF-014, see `docs/qa/defect-log.md`) was found during the Amendment 2 remediation review: a stale, legitimately obtained Livewire snapshot could restore and render an agreement after Legal changed it from a visible status to `document_status = pending`, because Livewire restores public model properties through an unscoped query on `/livewire/update`.

The remediation is implemented in code:

- `agreement-show` re-resolves its hydrated Agreement under the current user's scopes in `boot()` and returns `404` when the record is no longer visible (Admin and Legal keep full pending access).
- The route-group comment in `routes/web.php` now describes the real three-part mutation/visibility boundary.
- Regression coverage has been authored: `AgreementShowTest::test_a_stale_snapshot_cannot_render_an_agreement_after_it_becomes_pending` (Requester and Viewer over a real mounted component, a direct `DB::table` status change, and a `$refresh` update expecting `assertNotFound()`), plus hostile URL-backed pending-filter tests in `AgreementsIndexTest`.

The authorised full-suite run passed on 18 Sep 2026 with 343 tests and 1,138 assertions. This verifies the DEF-014 remediation and its regression coverage. Amir's 18 Sep browser walkthrough also confirmed Requester and Viewer pending-record hiding, Requester read-only access, and Legal's retained access.

## Security review conclusion

No blocker was found in the submission workflow or its confidentiality controls. After the original walkthrough, Amir clarified that Requesting Staff must also have read-only Agreement Register access. That Amendment 2 correction now passes both automated verification and Amir's manual browser walkthrough: Requester and Viewer retain read-only Register access, pending agreements stay hidden, and mutations remain Admin/Legal only.

- Route middleware gates portal roles and remains nested inside `auth`, preserving guest redirects.
- Each Livewire component authorizes again on every request, including hydrated update requests.
- Requester list ownership is applied in the database query before pagination and counting.
- Cross-requester detail denial uses `404`, not `403`, to avoid confirming that another request exists.
- Submission creation accepts no caller-supplied owner, status, timestamp, or agreement link.
- Validation and the initial audit event remain inside the trusted creation boundary and transaction.
- The LP1 detail page is read-only and exposes no update or delete route.

## Release boundary and next steps

No production action has been taken. The LP1-B changes remain uncommitted in the working tree.

LP1 Amendment 2 (`docs/handoff-lp1-register-access.md`) and the DEF-014 remediation now pass the authorised automated suite and Amir's manual role walkthrough. The next step is Amir's final review of the complete LP1 diff.

After Amir approves the corrected diff, release in this order:

1. commit the complete LP1 implementation and documentation;
2. push the commit;
3. deploy through the existing Laravel Cloud process;
4. confirm that the two already-committed LP1 migrations (`2026_09_14_000001_create_submissions_table` and `2026_09_14_000002_create_submission_activities_table`) were applied during deployment;
5. perform the production smoke test using approved test accounts; and
6. record the production evidence in this handoff afterward.

The migrations reach production only through the deployed commit, so they cannot be run, and the portal cannot be smoke-tested, before steps 1 to 3. This matches the LP0 release (`docs/handoff-lp0.md`), where the requester-role migration ran as part of the deployment of commit `df6dbb1` and production verification followed. Both LP1 migrations are additive `CREATE TABLE` migrations, so no destructive-migration backup gate applies.

If the diff changes before release, rerun Pint and the full automated suite and update this evidence where the observed result changes.
