# LP0 — Legal Submission Portal Foundation & Hardening

> **Status:** Deployed to production and verified.
> **Commit:** `df6dbb1` — `LP0: harden access and prepare submission portal`
> **Production deployment:** 14 Sep 2026
> **Scope:** Foundation and security hardening only. The Legal Submission Portal itself has not yet been built.

## What LP0 delivered

- Added the internal `requester` role for future non-Legal agreement requestors.
- Restricted the Agreement Register to `admin`, `legal`, and `viewer` roles.
- Protected Livewire user-management actions with server-side authorisation and made the role middleware persistent for Livewire updates.
- Added the private `documents` disk configuration for future P&C uploads. It is not publicly served and is not used for uploads in LP0.
- Moved Livewire components into `resources/views/livewire/` with conventional, emoji-free filenames.
- Added Malaysian-time formatting for future audit timestamps without changing the application's UTC storage timezone.

## What LP0 did not deliver

- No `submissions` table, portal pages, document upload, document download, revision workflow, messages, internal Legal notes, audit-history records, email notifications, or Agreement Register integration.
- No production document storage has been configured. The `documents` disk remains preparation only.

## Production deployment evidence

| Check | Result |
|---|---|
| Laravel Cloud deployment | `df6dbb1` active and successful |
| Migration status | `2026_09_10_000001_add_requester_role_to_users_table` ran successfully |
| `users.role` after migration | `varchar(255) NOT NULL DEFAULT 'viewer'` |
| Role index | Present (`MUL`) |
| Existing user data | Unchanged: 2 admin, 1 legal |
| Managed backup | Laravel Cloud MySQL daily snapshots; no manual snapshot control was exposed in the dashboard |

## Production security verification

A temporary requester account was created, used only for this test, then deactivated. The following passed:

1. The requester dashboard loaded.
2. The Register navigation link and dashboard Register button were absent.
3. Direct requests to `/agreements`, `/agreements/1`, and `/users` each returned `403 Forbidden`.
4. After deactivation, the temporary requester account could not log in.

## Local verification evidence

- SQLite migration dry-run against a copy of the development database preserved users, user IDs, foreign keys, indexes, and related row counts.
- Development migration completed with no SQLite foreign-key or integrity violations.
- Browser walkthrough passed for admin, Legal, viewer, and requester paths.
- `composer test`: 222 tests passed, 600 assertions.

## Follow-up completed — Requesting Staff wording

The internal role value remains `requester`. The agreed user-facing label is **Requesting Staff**.

The wording-only follow-up was tested locally (`composer test`: 224 tests passed), then deployed to production in commit `988e91c` on 14 Sep 2026. Production verification confirmed that the role selector and the temporary inactive test account both display **Requesting Staff**.
