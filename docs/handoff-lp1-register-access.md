# LP1 Amendment 2 — Requesting Staff Register Access: OpenCode Go Handoff

> **Audience:** OpenCode Go (Senior Developer)
> **Prepared:** 17 Sep 2026
> **Status:** Implemented and verified locally on 18 Sep 2026. Retained as the historical implementation brief for LP1 Amendment 2; the final verification evidence is recorded in `docs/handoff-lp1.md`.
> **Governing decision:** `docs/architecture-plan-lp1.md` S21. If this handoff appears to conflict with S21, stop and ask; do not choose.
> **Nature:** Small authorization correction. No schema change, migration, new role, or new feature workflow.

## Objective

Allow an authenticated `requester` user to use the Agreement Register as a read-only reference while preserving every existing confidentiality and mutation boundary.

The corrected role contract is:

| Role | Agreement Register | Submission Portal |
|---|---|---|
| Requesting Staff | Read-only; non-pending agreements only | Create and view own submissions only |
| Viewer | Read-only; non-pending agreements only | No access |
| Legal | Full working access, including pending | View all submissions |
| Admin | Full access, including pending | View all submissions |

The Register currently contains agreement years 2022–2026, but this implementation must not hard-code that range. Future years continue to derive from `agreement_date`.

## Required reading

1. `AGENTS.md`, especially the requester rule, pending global scope, and defence-in-depth rules.
2. `docs/architecture-plan-lp1.md` S21.
3. `app/Models/User.php`: `canAccessRegister()`, `canWrite()`, and `canSeePending()`.
4. `routes/web.php`: Agreement Register read and write route groups.
5. `app/Models/Scopes/HidePendingFromNonLegalScope.php` and `app/Models/Agreement.php`: existing pending visibility behavior.
6. Existing access and display tests under `tests/Feature/Agreement/` and `tests/Feature/Submission/PortalNavigationTest.php`.

## Required implementation

### 1. Register capability

Update `User::canAccessRegister()` so the explicit allow-list contains:

```text
admin, legal, viewer, requester
```

Do not implement this as a deny-list. A future unknown role must remain denied by default.

Do not change:

- `canWrite()` — it remains Admin/Legal only;
- `canSeePending()` — it remains Admin/Legal only; or
- any Submission policy or portal capability.

### 2. Routes

Update only the Agreement Register list/detail route group to allow:

```text
role:admin,legal,viewer,requester
```

The nested create/edit group remains exactly:

```text
role:admin,legal
```

Keep the Register group nested inside `auth`, so guests still redirect to `/login`.

### 3. Navigation and dashboard

Requesting Staff must see all three relevant entry points:

- **My Submissions** in the global navigation;
- **Register** in the global navigation; and
- the dashboard's **Create Submission**, **My Submissions**, and **Open register** actions.

The current dashboard uses `@unless (canAccessRegister())` around the requester portal block. Once requester gains Register access that condition becomes false and would accidentally hide the portal actions. Replace that condition with an explicit requester check or an equally clear role-appropriate structure.

Blade visibility is presentation only. Route middleware and existing in-component write checks remain the authorization boundary.

### 4. Pending confidentiality

Do not change the pending global scope. Prove that Requesting Staff receive the same pending visibility boundary as Viewer:

- no pending agreement row;
- no pending status filter option;
- pagination totals exclude pending rows;
- year options do not leak a year represented only by pending agreements;
- search/filter results do not return pending agreements; and
- direct detail access to a pending agreement returns `404`.

This is Agreement `document_status = pending` (internal Legal vetting), not Submission `status = pending` (a request awaiting Legal).

### 5. Read-only boundary

Prove that Requesting Staff:

- can open the Agreement Register index;
- can open a non-pending agreement detail;
- receive `403` for Agreement create and edit routes;
- see no create, edit, archive, or status-changing control; and
- cannot invoke a Livewire mutation by replaying or crafting a component update.

Reuse the existing Viewer/read-only protections. Do not add requester-specific mutation code when the existing `canWrite()` boundary already expresses the rule.

### 6. Submission isolation regression

The Agreement Register change must not affect the portal:

- Requesting Staff still see only their own submissions;
- cross-requester direct access remains `404` with no content exposure;
- Viewer still receives `403` for every portal route; and
- Legal/Admin still share the all-submissions queue.

## Expected files

Application changes should normally be limited to:

- `app/Models/User.php`
- `routes/web.php`
- `resources/views/dashboard.blade.php`

The global layout may need only a comment adjustment because it already renders **Register** through `canAccessRegister()`. Do not rewrite it unnecessarily.

Test and QA changes should normally be limited to the relevant files among:

- `tests/Unit/UserRoleTest.php`
- `tests/Feature/Agreement/AgreementAccessControlTest.php`
- `tests/Feature/Agreement/AgreementsIndexTest.php`
- `tests/Feature/Agreement/AgreementShowTest.php`
- `tests/Feature/Submission/PortalNavigationTest.php`
- `docs/qa/test-cases.md`
- `docs/qa/traceability-matrix.md`

If another file is necessary, explain why before editing it. Do not touch migrations, schema, seeders, factories, Agreement scopes, Submission policy, or portal components.

## Acceptance checks

1. Requesting Staff can reach Agreement list and non-pending detail routes.
2. Requesting Staff see **Register** without losing **My Submissions**.
3. The Requesting Staff dashboard keeps both portal actions and adds **Open register**.
4. Requesting Staff remain unable to create, edit, archive, or change an Agreement.
5. Requesting Staff cannot see or infer pending agreements through rows, totals, filters, year options, search, direct routes, or Livewire updates.
6. Viewer Register behavior remains unchanged and Viewer still has no portal access.
7. Requester submission ownership remains unchanged.
8. Legal/Admin behavior remains unchanged.
9. Guests still redirect to login.
10. No year range is hard-coded.
11. QA cases and traceability reflect the corrected access matrix.
12. Pint passes on touched PHP files and the full automated suite passes.

## Handback (completed)

This section records the handback the implementer was asked to return. It is a historical checklist, not an outstanding instruction. The final automated and manual verification evidence — including the Pint result, the full-suite PHPUnit counts, and Amir's 18 Sep 2026 role walkthrough — is recorded in `docs/handoff-lp1.md`, which is authoritative for those results.

The implementer was asked to return:

1. the exact files changed and why;
2. the exact Pint result;
3. the compact PHPUnit JSON result, including test and assertion counts;
4. confirmation that no migration or schema file changed;
5. a concise manual walkthrough for Amir covering Requesting Staff, Viewer, Legal, and guest; and
6. any deviation from this handoff.

The implementer was instructed not to commit, push, migrate production, deploy, or open a browser. Amir performed the manual browser walkthrough on 18 Sep 2026; its results, and the release steps, are recorded in `docs/handoff-lp1.md`.
