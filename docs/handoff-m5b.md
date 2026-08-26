# OpenCode Go handoff — M5b: admin-only user management

> **Milestone:** M5, **third of three handoffs.** Order: `handoff-m5-defects.md` → `handoff-m5a.md` → **this**. · **Implements:** Decision M5-2 of `docs/architecture-plan-m5.md`
> **Precondition:** `docs/handoff-m5-defects.md` and `docs/handoff-m5a.md` both complete, merged, and green at 117 tests. **Do not send this until M5a has landed** — the sequencing is Decision M5-2's "Sequencing, approved", and it exists so that if this feature grows, the documentation deliverable is already safe.
> **Status:** hold until M5a is merged.
> **Paste the block below into OpenCode Go from the repo root.**

```text
Role: You are the SENIOR DEV on the CLSD Agreement Register (Laravel 13 +
Livewire 4, SQLite). Amir is the PM: implement exactly what the approved plan
says — do not redesign, do not add scope.

Context: Read AGENTS.md (especially the "Design philosophy" section),
docs/architecture-plan-m5.md (APPROVED 26 Aug 2026, Decision M5-2 and the
"Decision M5-2, at length" section), and the README section on managing users
that M5a wrote. The architecture plan is the contract.

Why this feature exists, because it changes how you should resolve ambiguity:
Amir hands this system to Intan, a non-technical Legal Executive, and then stops
being available. AGENTS.md states the test every decision here is measured
against — "can Intan do this without technical help? If the answer requires a
terminal, a code editor, or developer knowledge, it's a gap, not an acceptable
tradeoff." Today, adding a person to the register requires shell access to run
`php artisan user:create`. That is the last hard dependency on a developer, and
adding a PIC is not a rare admin event — it is how a project owner becomes
selectable in the agreement form at all. When something below is ambiguous,
resolve it toward "Intan can do this alone", not toward "it's more flexible".

Task:

1. Create the component with `php artisan make:livewire UserManager`. Livewire 4
   is configured for single-file components: this produces
   resources/views/components/⚡user-manager.blade.php, an anonymous class plus
   Blade in ONE file with a literal ⚡ in the filename. Do not scaffold
   app/Livewire/*.php. Do not use --class or --mfc.

   Exactly three capabilities. Nothing else:
   - LIST users: name, email, role, active state. Order however reads best;
     paginate only if you also write the test for it.
   - CREATE a user: name, email, role, initial password. Validation:
     name required, max 255; email required, valid, unique on users.email;
     role required and in admin|legal|viewer; password required, min 8.
     is_active is true on create.
   - TOGGLE is_active on an existing user.

   Explicitly NOT in scope, do not build any of these: editing an existing
   user's name, email or role; deleting users (deactivate instead — this repo
   archives, it never deletes); password reset from the UI (`user:password`
   already owns that); bulk operations; search; invite emails.

2. The self-deactivation guard. An admin must NOT be able to deactivate their
   own account. On a system whose entire premise is that no terminal is
   available, an admin who deactivates themselves has locked the organisation
   out of user management with no recovery path. Enforce it in BOTH layers:
   disable the control on the acting user's own row AND reject it server-side.
   Rendering alone is not enforcement — same rule M3 applied to write
   permissions. Role editing is out of scope, so this is the only lockout path
   that exists, which is why one guard is enough.

3. Password handling. users.password is NOT NULL and there is no mail transport
   configured, so there is no invite-link option and you must not add one. The
   admin types an initial password and communicates it out of band. The User
   model casts password to 'hashed', so assign the plain value and let the cast
   hash it — do not call Hash::make yourself, and do not double-hash.

4. Route. One route, inside a role:admin middleware group, in routes/web.php:

     Route::middleware('role:admin')->group(function () {
         Route::livewire('/users', 'user-manager')->name('users.index');
     });

   It goes inside the existing auth group. Admin only — legal must get 403.
   The User model already has canManageUsers(), which returns isAdmin(); it has
   had no caller until now. Use it for the rendering check so the rule lives in
   one place.

5. Nav. One link in resources/views/layouts/app.blade.php, rendered only for
   admins (@if (auth()->user()->canManageUsers())). One link. Do not restyle
   the nav, do not restructure the layout, do not touch anything else in that
   file. UI/UX polish is logged in AGENTS.md as future work and is explicitly
   not this milestone.

6. Tests — create tests/Feature/Admin/UserManagementTest.php, 8 tests:
   - an admin can reach /users
   - a legal user gets 403 on /users
   - a viewer gets 403 on /users
   - creating a user persists the correct role and an active state
   - a duplicate email is rejected
   - an admin cannot deactivate their own account (assert the DATABASE state,
     not just the response — this is the guard that matters)
   - deactivating a user removes them from the PIC dropdown on the agreement
     form, but an already-assigned inactive PIC still appears on the edit form
     for an agreement they are assigned to. AgreementFormTest already covers the
     second half in test_edit_form_retains_an_inactive_pic_already_assigned —
     read it first and make your test complement it, not duplicate it.
   - a deactivated user cannot log in (LoginTest already has
     test_inactive_user_cannot_login; this one asserts the same rule reached
     through the new form, i.e. deactivate via the component, then attempt
     login)

7. Update the docs M5a wrote. Both are now partly out of date, by design:
   - README.md "Managing users": lead with the web form as the normal path,
     and keep `user:create` / `user:password` documented as the fallback for
     the first account on a new deployment and for when the app will not boot.
     Both seeders are still local/testing-only; that sentence stays true.
   - docs/HANDOVER.md: its "Adding a person" heading is now answerable. Fill in
     ONLY the factual steps — where the link is, what the fields are, what
     happens to someone who is deactivated. Amir still writes the prose of every
     other section. Do not touch them.
   - docs/qa/test-cases.md: add cases for the new rule, in the existing format.
   - docs/qa/traceability-matrix.md: add BR-26 for admin-only user management,
     citing the tests by their real names.

8. Run `composer test`. Expect 125 (117 + 8). Parse the laravel/pao JSON and
   report the result field and the exact count. Then update the count in
   docs/qa/test-plan.md and docs/qa/README.md — from the run, not from this
   prompt. Re-run the cross-reference check from M5a and confirm it still
   prints nothing.

Approved decisions (do not reopen):
- M5-2 (c): build the minimal form AND keep the artisan commands documented.
  The commands are not being replaced — they are the only path to the first
  admin on a fresh deployment, since UserSeeder and StaffSeeder are both
  guarded to local/testing.
- M5-9 (resolved 26 Aug 2026): user management stays admin-only and
  canManageUsers() is UNCHANGED. Intan — the non-technical Legal Executive this
  feature is built for — is given an admin account. That is an account decision,
  not a code decision. If it seems odd that a Legal user cannot manage users,
  the answer is the account, never the permission: widening canManageUsers()
  would let any Legal user mint admin accounts, which destroys the security
  argument that made a web form better than shell access in the first place.
  Do not change canManageUsers(). Do not add a role check anywhere that treats
  legal as equivalent to admin.
- There is exactly ONE admin for now, by decision, and the artisan commands stay
  documented as the recovery path if that admin is unavailable. This is why
  M5-2 was approved as (c) — form AND commands — rather than (a). Do not
  deprecate, hide, or remove user:create and user:password.

Do not change:
- Any migration. This feature deliberately needs none — that is what keeps it
  inside M5's constraints. If you find yourself wanting a column, stop and ask
  Amir.
- Any model. Including User: canManageUsers(), the active() scope, the casts and
  the #[Fillable] list are all already correct for this feature.
- Any seeder. UserSeeder and StaffSeeder stay dev-only.
- The Agreement global scope, the three-role enum, or anything from M1's auth.
- Existing tests' assertions. You are adding a test file, not revising others.
- npm behaviour or vite.config.js.
- The visual design of anything that already exists. One nav link is the entire
  permitted UI change outside the new component.

Acceptance checks:
- `composer test` green; report the result field and the exact count.
- `vendor\bin\pint` on every PHP file you touched.
- The M5a cross-reference check still prints nothing.
- `git status --short` shows only: the new ⚡user-manager component, the new test
  file, routes/web.php, resources/views/layouts/app.blade.php, README.md,
  docs/HANDOVER.md, docs/qa/test-cases.md, docs/qa/traceability-matrix.md,
  docs/qa/test-plan.md, docs/qa/README.md. Nothing else.
- Manual: log in as admin, add a user, see them in the PIC dropdown on the
  agreement form, deactivate them, see them disappear from the dropdown, and
  confirm your own row's deactivate control is disabled.

After editing:
- List every file created/modified.
- Report exact test names and the count from the run.
- Tell Amir exactly what to click through in the browser, in order.
- Do not commit unless Amir says to.
```

---

## Notes for Amir

- **Send this only after M5a is merged and green.** That ordering is the decision, not a preference.
  If this feature grows in the doing, the README and the handover note are already safe on the other
  side of a commit.

- **The self-deactivation guard is the one test worth checking yourself.** Log in as admin, look at
  your own row, confirm the control is disabled — then confirm the server rejects it too if the
  control is re-enabled in devtools. A rendering-only guard passes a click-through and fails the day
  it matters.

- **Create Intan's admin account on handover day, not before, and not as an afterthought.** M5-9 is
  settled, but it is an account action rather than a code action, which is exactly the kind of thing
  that gets left undone because no test fails without it. Until it exists she cannot open the page
  this handoff builds.

- **The single-admin risk is accepted, not overlooked.** With the backup admin deferred, user
  management stops working while Intan is unavailable. The reason that is tolerable is that
  `user:create` still works for whoever has server access — the fallback is "needs a developer again
  for a while", not "unrecoverable". Worth making sure the handover note says that in plain words,
  since Intan is the person who would otherwise assume the system is broken.

- **Then the hand-entered agreements.** Section 7 of the plan puts the 5–10 historical registrations
  after this handoff for a concrete reason: until real users exist, the PIC dropdown is empty on the
  handover environment and you would be entering ten agreements with no PIC assigned. Build the form,
  create the real people, then enter the data — and log anything surprising as a defect rather than
  fixing it while you type.
