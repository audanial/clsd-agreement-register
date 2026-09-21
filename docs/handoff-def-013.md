# DEF-013 — Revoke access on account deactivation

> **Date:** 21 Sep 2026  
> **Status:** Implemented, verified, and reviewed locally; committed on `fix/def-013-deactivation-notice`. Not pushed or deployed.  
> **Scope:** Existing sessions, Livewire updates, Remember me, and the deactivation notice. No role or schema changes.

## What changed

- `EnsureUserIsActive` runs in the `web` middleware group. It passes guests and active users, but logs out an authenticated inactive user, invalidates their session, and redirects to login. Livewire's update route uses `web` directly, so the check also runs before a component update.
- Admin deactivation uses `DeactivateUser`: it sets `is_active = false`, rotates `remember_token`, and deletes only that user's rows from the configured database session connection and table. Reactivation remains a simple active-state change.
- A blocked correct-password login attempt explains deactivation and names CLSD Legal. Wrong passwords and unknown emails keep the generic error.
- The same notice is shown to surviving inactive sessions. Normal page redirects use a session flash. Real Livewire updates redirect to '/login?deactivated=1' because Livewire's background fetch follows the redirect and consumes a one-time flash before the visible page loads. The login page shows the notice while the marker is in the address. A failed login redirects to the clean `/login` (`redirectTo` in `AuthenticatedSessionController::store()`), so a wrong password afterwards shows only the normal credential error. Refreshing the marker address itself still shows the notice; this is accepted. The marker affects display only, never authentication. A session purged by Admin deactivation reaches a bare login page first; the explanation appears if the person then tries the correct password.

## Verification

- `composer test`: **passed, 356 tests, 1,213 assertions** on 21 Sep 2026. The previous recorded baseline was 343 tests and 1,138 assertions.
- Pint on all changed PHP and Blade files: **passed** on 21 Sep 2026.
- The new `InactiveUserAccessTest` covers a surviving session on a page request; a real HTTP Livewire update with a working active-user control; rotated and unrotated remember tokens; and positive/negative fresh-browser controls. `UserManagementTest` covers token rotation, session purge, configured session table, and reactivation. `LoginTest` covers correct and incorrect passwords for an inactive account. Existing portal route tests were updated for the new redirect and passed.
- In an isolated local SQLite browser session using fictional accounts, an out-of-band deactivation redirected a page reload to login with the notice. A Register search Livewire update navigated to `/login?deactivated=1` and visibly showed the notice without an error overlay. The first implementation lost the flash during Livewire's background redirect; the browser check exposed that, and the marker fix was retested successfully. Calling `DeactivateUser` against the fictional account removed access on the next page request; a later correct-password attempt showed the notice. The temporary browser database and server were removed/stopped after testing.

## Remaining release checks

1. **Completed 22 Sep 2026:** Reviewed the actual diff against the approved DEF-013 plan, including the Livewire notice marker and clean-login redirect. No actionable issues found.
2. **Completed 22 Sep 2026:** Amir performed the two-browser Admin UI walkthrough and fresh-browser Remember me check using local test accounts. Admin deactivation immediately removed the requester's existing access, the old remembered login could not restore access, correct credentials showed the deactivation notice, and reactivation restored normal access.
3. **Configuration verified 22 Sep 2026:** Production uses Laravel Cloud's injected `SESSION_DRIVER=cookie`. There is no configured database session connection or table to verify. Surviving cookie sessions are rejected by `EnsureUserIsActive` on their next request, and remember-token rotation prevents remembered-login restoration. Production smoke testing remains pending until after the normal release process.

## Deliberate limits

No migration, role change, device-management UI, deactivation audit event, or password reset flow was added. An old Remember me cookie may remain stored in a browser after server-side token rotation, but it cannot authenticate. Active users' sessions are unaffected by deployment. DEF-013 stays marked as locally fixed with review and production verification pending in `docs/qa/defect-log.md`.
