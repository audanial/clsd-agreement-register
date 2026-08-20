# OpenCode Go handoff — M1: Auth + roles

> **Milestone:** M1 · **Implements:** Section 2 of `docs/architecture-plan.md`
> **Precondition:** none — M1 is the first build milestone.
> **Status:** handed to OpenCode Go on 20 Aug 2026. Backfilled here for the record.
> **Paste the block below into OpenCode Go from the repo root.**

```text
Role: You are the SENIOR DEV on the CLSD Agreement Register (Laravel 13 +
Livewire 4, SQLite). Amir is the PM: implement exactly what the approved plan
says — do not redesign, do not add scope.

Context: Read AGENTS.md, docs/BUILD_PLAN.md (the MVP milestones), and
docs/architecture-plan.md (the APPROVED architecture, dated 20 Aug 2026). The
architecture plan is the contract. If a decision in it is missing, ask Amir —
do not guess.

Task: Implement milestone M1 (auth + roles) end-to-end, exactly per Section 2
of docs/architecture-plan.md:
1. Section 2.3 — User model: add `is_active` to #[Fillable] and cast it to
   boolean; add isAdmin/isLegal/isViewer/canSeePending/canWrite/canManageUsers
   and the #[Scope] active() method. NOTE: the `role` fillable bug and the
   UserFactory role states are ALREADY FIXED in commit f426497 — do not redo
   them.
2. Section 2.2 — implement all FIVE points of the security contract verbatim
   (is_active folded into Auth::attempt credentials; session()->regenerate()
   on login; POST logout with invalidate + regenerateToken; throttle:5,1 on
   POST /login; one generic auth.failed message). No /register route, ever.
3. Section 2.4 — create and modify exactly the files listed there, nothing
   else. Two things are easy to miss and are load-bearing:
   - bootstrap/app.php needs BOTH $middleware->alias(['role' => ...]) AND
     $middleware->redirectGuestsTo('/login'). Without the second, Laravel 13
     returns a blank 401 instead of redirecting — see gap #6 in Section 1.
   - resources/views/layouts/app.blade.php must exist (Livewire 4's
     component_layout default is layouts::app) — see gap #7.
   Also replace tests/Feature/ExampleTest.php, which asserts GET / == 200 and
   will now fail (gap #8), and drop the two dead imports in DatabaseSeeder.
4. Section 2.4 — user:create and user:password artisan commands (Decision 10
   approved: no mail-based reset). Passwords via secret() prompts only.
5. Section 2.4 — UserSeeder: one admin + one legal, idempotent updateOrCreate
   keyed on email, guarded by App::environment('local','testing').
6. Section 2.5 — write every test listed there. The role-visibility test for
   `pending` belongs to M2, not M1 — do not write it yet.
7. Run `composer test` — laravel/pao prints JSON when it detects an AI agent;
   parse the "result" field. All tests must pass.

Approved decisions relevant to M1 (do not reopen):
- D1: minimal custom auth. No Breeze, no Fortify, no registration.
- D9: the login form is plain Blade + AuthenticatedSessionController, NOT a
  Livewire component. The ⚡-SFC-only convention resumes at M3.
- D10: password resets are the user:password command.

Do not change:
- Any migration or seeder (beyond registering UserSeeder in DatabaseSeeder).
- Schema constraints, the TBD campus behavior, or the document_status meaning.
- The Agreement model — that is M2. Leave the stub alone.
- Anything outside the M1 file list in Section 2.4 of the architecture plan.
- Do not run npm scripts (ignore-scripts=true) or touch vite.config.js.

Acceptance checks:
- `composer test` green (parse the JSON output; report the result field).
- `vendor\bin\pint` run on all PHP you touched.
- The manual browser checks in Section 5 "M1 done when" of the architecture
  plan.

After editing:
- List every file created/modified.
- Report exact test names and results.
- Tell Amir what to manually check in the browser.
- Do not commit unless Amir says to.
```
