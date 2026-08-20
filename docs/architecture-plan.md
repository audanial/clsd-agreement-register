# CLSD Agreement Register — Architecture Plan for M1 + M2

> **Author:** Claude Code (Lead Architect) · **For:** Amir (PM) · **Date:** 20 Aug 2026
> **Status:** **APPROVED by Amir, 20 Aug 2026.** Decisions 6, 7, 8, 9 and 10 approved as
> recommended. This document is now the implementation contract for OpenCode Go (M1, then M2).
> Nothing is implemented yet. Decisions 2, 3 and 5 remain open — none of them block M1 or M2.

---

## Context

The register has a solid schema (10 migrations, business rules written into the migration
comments) and five working models, but **no authentication and no `Agreement` model**. That
ordering is not accidental: the single rule that defines this system — *`document_status =
pending` is Legal-only* — cannot exist without roles, and cannot be enforced reliably without
a global scope on a real `Agreement` model. M1 builds the identity layer; M2 builds the model
and welds that rule into Eloquent so no future feature can forget it. Everything else (list
UI, forms, activity feed) is M3+ and is deliberately not planned here.

I verified the repo myself rather than trusting `docs/BUILD_PLAN.md`. **Two of the plan file's
headline items are already stale**, and I found five gaps the plan file does not mention — one
of which (Laravel 13 returns a blank `401` instead of redirecting guests to `/login`) would
have shipped a broken login experience.

---

## 1. Verified current state (read on this repo, 20 Aug 2026)

Framework versions confirmed from `vendor/composer/installed.json`:
`laravel/framework v13.25.0` · `livewire/livewire v4.4.0` · `laravel/pao v1.1.4` · PHP `^8.3`.

### What actually exists

| Area | Verified |
|---|---|
| Migrations | 10 files, all present, business-rule comments intact. `agreements` has 8 `date()` columns, 2 `timestamp()` columns (`project_status_updated_at`, `archived_at`), `softDeletes()`, 5 indexes. |
| `users` schema | Stock table **+** `2026_01_01_000007`: `role` enum(`admin`,`legal`,`viewer`) default `viewer`, indexed; `is_active` boolean default `true`. |
| Models | `Campus`, `Country`, `Partner`, `AgreementFile`, `AgreementActivity`, `User` — all real, with `#[Fillable]`, casts, relations. |
| `Agreement` model | **Empty stub.** Literally `class Agreement extends Model { // }`. No `SoftDeletes` despite `deleted_at` on the table. |
| `CampusSeeder` | 16 rows, idempotent `updateOrInsert` on `code`. Correct. |
| `DatabaseSeeder` | Calls **only** `CampusSeeder`. |
| `routes/web.php` | One route: `GET /` → `welcome` view. |
| `bootstrap/app.php` | `withMiddleware` closure is **empty**. No aliases, no guest redirect. |
| `app/Http/Controllers/` | `Controller.php` base class only. |
| Views | `welcome.blade.php`; `resources/views/components/` exists but is **empty**; **no `resources/views/layouts/`**. |
| Tests | Stock `ExampleTest` ×2. `tests/TestCase.php` is bare (no `RefreshDatabase`). |
| Build | `public/build/manifest.json` **exists**, `node_modules/` present → `@vite` resolves, current tests are green without touching npm. |

### Drift from `docs/BUILD_PLAN.md` — things the plan file gets wrong

1. **🐛 "Two bugs found in review" (Section 1) are ALREADY FIXED** — commit `f426497`.
   `User` is `#[Fillable(['name','email','password','role'])]` and `UserFactory` has
   `admin()` / `legal()` / `viewer()` states. **Delete this section from the build plan.**
2. **The `is_active` half of the M1 bullet is still open.** `users.is_active` exists in the
   schema but is **not** in `User`'s `#[Fillable]`, **not** cast to boolean, and has no
   factory state. This is the residue of that fix.
3. **`UserFactory::definition()` sets no `role` key** — `User::factory()->create()` gets the
   DB default (`viewer`). That is the right *behaviour*, but it is implicit. Make it explicit.
4. **"Repo state at plan time: 2 commits" (Section header) is stale** — 7 commits now.
5. **`document_status` is a 4-value enum, not "3 states + derived expired".** The migration
   stores `expired` as a real status *and* `expiry_date` exists with the comment "Drives
   Expiring Soon + Expired". Two sources of truth for the same fact. → **Open Decision 6.**

### Gaps the plan file never mentions (found by reading the framework, not the docs)

6. **🔴 Laravel 13 does NOT redirect guests to `/login` by default.**
   `Authenticate::redirectTo()` returns `null` unless `redirectUsing()` is set
   (`Illuminate/Auth/Middleware/Authenticate.php:114-119`), and
   `Handler::unauthenticated()` then returns **`response()->noContent(401)`** — a blank page
   (`Foundation/Exceptions/Handler.php:851-853`). `$middleware->redirectGuestsTo('/login')`
   in `bootstrap/app.php` is **mandatory**, not optional.
7. **🔴 No layout view exists.** Livewire 4's `component_layout` default is `layouts::app` →
   `resources/views/layouts/app.blade.php` (`vendor/livewire/livewire/config/livewire.php:47`,
   layouts namespace registered at line 33). Every full-page `⚡` component in M3 will fail
   until this file exists. It belongs in M1.
8. **🟡 `tests/Feature/ExampleTest.php` will break in M1.** It asserts `GET /` → `200`. Once
   `/` redirects to the dashboard/login it returns `302`. Must be replaced, not left to rot.
9. **🟡 `DatabaseSeeder` has dead code**: unused `use App\Models\User;` and a same-namespace
   `use Database\Seeders\CampusSeeder;`. Pint's Laravel preset will strip both; indentation of
   the `$this->call([...])` array is also off. Free cleanup when the user seeder is added.
10. **🟢 The attribute-config convention extends further than the plan assumes.** This exact
    framework build ships `Illuminate\Database\Eloquent\Attributes\{Scope, ScopedBy, UsePolicy,
    UseFactory, Boot, ObservedBy}`. `Model.php:2022` confirms `#[Scope]` works on any
    non-private method. So M2's global scope and query scopes can be written *in the repo's
    own idiom* rather than as `booted()` + `scopeFoo()` legacy style.

### Confirmed accurate in the plan file (no change needed)

`CountrySeeder` genuinely does not exist → `countries` is empty → partner dropdowns would be
empty. `Agreement` is genuinely an empty stub. There is genuinely zero auth. The `laravel/pao
v1.1.4` note matches `composer.lock` (accepted as verified fact; not re-tested).

**No migration or seeded data is modified anywhere in this plan.** `campus_id` stays NOT NULL,
the TBD campus stays, `expiry_date IS NULL` keeps meaning *indefinite*, archive-not-delete
stays, the three-role enum stays, no `spatie/laravel-permission`.

---

## 2. M1 — Auth + roles

### 2.1 Audit of the auth decision (Decision 1 is LOCKED — this is justification, not a reopening)

| Option | Files added | What you must strip | Real cost |
|---|---|---|---|
| **laravel/breeze** | ~30: 7 auth controllers, `routes/auth.php`, ~12 Blade views, profile controller + views, `LoginRequest`, layout/nav components, 7 test files | registration route+view, password reset (**`MAIL_MAILER` is unconfigured — a reset flow that silently fails is worse than none**), email verification, password confirmation, profile deletion | `breeze:install` **rewrites `resources/css/app.css`, `resources/js/app.js`, `package.json` and the vite input list** — a direct collision with the Tailwind 4 + `bunny('Instrument Sans')` setup that is explicitly off-limits. You then delete ~60% of what it installed and must audit the rest. Highest risk, lowest fit. |
| **laravel/fortify** | ~4 + `config/fortify.php` + a service provider | registration/2FA/reset feature flags, all toggled off in config | Headless: you still write every view. Adds a feature-flag indirection layer for an app with three roles and one login form. |
| **minimal custom** ✅ | **7** (1 controller, 1 middleware, 2 commands, 2 views, 1 layout) | nothing | Uses the *same* framework primitives Breeze does (`Auth::attempt`, `session()->regenerate()`, `throttle`). Security parity is fully achievable — see the contract below. Every line is one Amir can read. |

**Conclusion: the locked decision holds.** For an internal, invite-only, three-role app with no
mail server, Breeze is net-negative — it adds surface area you delete and rewrites build files
you've frozen.

### 2.2 Security contract (non-negotiable — the senior dev must implement all five)

Minimal auth is only as safe as Breeze if it does what Breeze's `LoginRequest` does:

1. **`Auth::attempt(['email' => …, 'password' => …, 'is_active' => true], $remember)`** —
   folding `is_active` into the credentials array means a deactivated staff member cannot log
   in at all, enforced by the user provider's query rather than by an `if` you might forget.
2. **`$request->session()->regenerate()` immediately after a successful attempt** — session
   fixation defence.
3. **Logout = `Auth::logout()` → `session()->invalidate()` → `session()->regenerateToken()`**,
   as a `POST` route with CSRF. Never a `GET`.
4. **Rate limiting**: `->middleware('throttle:5,1')` on `POST /login`.
5. **Generic failure message** — one `ValidationException::withMessages(['email' =>
   __('auth.failed')])` for both "no such user" and "wrong password". No user enumeration.
   Never hand-roll a credential check; never compare hashes manually.

Plus: **no `/register` route is ever defined**, and a test asserts it 404s.

### 2.3 Role model

Roles live in one place so M2's global scope has a single seam to depend on:

`app/Models/User.php` gains
- `#[Fillable([... , 'is_active'])]` — add `is_active` (gap #2)
- casts: `'is_active' => 'boolean'`
- `isAdmin(): bool` / `isLegal(): bool` / `isViewer(): bool`
- `canSeePending(): bool` → `$this->isAdmin() || $this->isLegal()` ← **M2's global scope calls
  exactly this method and nothing else**
- `canWrite(): bool` → `$this->isAdmin() || $this->isLegal()` (per Decision 2)
- `canManageUsers(): bool` → `$this->isAdmin()`
- `#[Scope] protected function active(Builder $q)` → `$q->where('is_active', true)` (the PIC
  dropdown in M3 needs it; four lines now saves a round-trip later)

Route-level gating uses a middleware alias `role:admin` / `role:admin,legal`. **No
`AgreementPolicy` in M1** — policies authorize *actions on records*, and there are no actions
until M3. Adding one now would be scaffolding with nothing to guard.

### 2.4 Files for M1

**Create**
```
app/Http/Controllers/Auth/AuthenticatedSessionController.php
app/Http/Middleware/EnsureUserHasRole.php
app/Console/Commands/CreateUserCommand.php
app/Console/Commands/ResetUserPasswordCommand.php
database/seeders/UserSeeder.php
resources/views/layouts/app.blade.php
resources/views/auth/login.blade.php
resources/views/dashboard.blade.php
tests/Feature/Auth/LoginTest.php
tests/Feature/Auth/LogoutTest.php
tests/Feature/Auth/RegistrationDisabledTest.php
tests/Feature/Auth/RoleMiddlewareTest.php
tests/Feature/Console/CreateUserCommandTest.php
tests/Feature/Console/ResetUserPasswordCommandTest.php
tests/Unit/UserRoleTest.php
```

**Modify**
```
app/Models/User.php
bootstrap/app.php
routes/web.php
database/factories/UserFactory.php
database/seeders/DatabaseSeeder.php
tests/Feature/ExampleTest.php
```

Notes on each modification:
- `bootstrap/app.php` → inside `withMiddleware`: `$middleware->alias(['role' =>
  EnsureUserHasRole::class]);` **and** `$middleware->redirectGuestsTo('/login');` (gap #6 —
  without the second line guests get a blank 401).
- `routes/web.php` → `Route::redirect('/', '/dashboard')`; a `guest` group with
  `GET /login` (named `login`) and `POST /login` (`throttle:5,1`); an `auth` group with
  `POST /logout` and `GET /dashboard`. `/dashboard` is a placeholder view showing name + role
  so M1 is verifiable end-to-end before M3 exists.
- `UserFactory` → add `'role' => 'viewer'` to `definition()` (explicit over implicit) and an
  `inactive()` state so the `is_active` login test has something to build.
- `DatabaseSeeder` → register `UserSeeder`; drop the two dead imports; fix indentation.
- `ExampleTest` → replace `GET / == 200` with `test_root_redirects_guests_to_login` (gap #8).
- **No command registration needed**: `withRouting(commands: routes/console.php)` triggers
  `withCommands([app_path('Console/Commands')])`
  (`Foundation/Configuration/ApplicationBuilder.php:179-181`), so `user:create` auto-discovers.

`CreateUserCommand` signature: `user:create {--name=} {--email=} {--role=viewer}`, password via
`secret()` prompt (never a CLI argument — it lands in shell history), role validated against
`['admin','legal','viewer']`, email validated + unique. The `'password' => 'hashed'` cast on
`User` handles hashing; assign the plain value.

`ResetUserPasswordCommand` (**approved Decision 10** — the support path, since `MAIL_MAILER` is
unconfigured): signature `user:password {email}`, password via `secret()` prompt with
confirmation, fails cleanly if the email is unknown. Same `hashed` cast handles hashing. It must
**also invalidate existing sessions** for that user — the simplest correct way is
`$user->setRememberToken(Str::random(60))` plus a note in the M5 handover that the user is
logged out everywhere. No mail, no tokens, no `password_reset_tokens` usage.

`UserSeeder` seeds one `admin` + one `legal` dev account with `updateOrCreate` keyed on email
(CampusSeeder's idempotency style), **guarded by `App::environment('local', 'testing')`**.
Known-password accounts must never reach a real deployment — `user:create` is the production
path. Dev password from `env('DEV_USER_PASSWORD', 'password')`.

### 2.5 Tests for M1

`tests/Unit/UserRoleTest.php` (needs DB → uses `RefreshDatabase`)
- `test_role_helpers_reflect_the_role_column` — admin/legal/viewer × isAdmin/isLegal/isViewer
- `test_can_see_pending_is_true_for_admin_and_legal_and_false_for_viewer`
- `test_role_is_mass_assignable`
- `test_is_active_is_mass_assignable_and_cast_to_boolean`
- `test_active_scope_excludes_inactive_users`

`tests/Feature/Auth/LoginTest.php`
- `test_login_screen_can_be_rendered`
- `test_user_can_login_with_valid_credentials`
- `test_user_cannot_login_with_an_invalid_password`
- `test_login_error_does_not_reveal_whether_the_email_exists` (same message both ways)
- `test_inactive_user_cannot_login`
- `test_session_is_regenerated_on_login`
- `test_login_is_rate_limited_after_five_failed_attempts` (asserts 429)

`tests/Feature/Auth/LogoutTest.php`
- `test_authenticated_user_can_logout`
- `test_logout_is_not_reachable_via_get`

`tests/Feature/Auth/RegistrationDisabledTest.php`
- `test_registration_route_does_not_exist` (`GET /register` → 404)

`tests/Feature/Auth/RoleMiddlewareTest.php`
- `test_guests_are_redirected_to_login` ← **fails without gap #6's fix; that's the point**
- `test_admin_can_access_an_admin_only_route`
- `test_legal_is_forbidden_from_an_admin_only_route` (403)
- `test_viewer_is_forbidden_from_an_admin_only_route` (403)
- `test_legal_and_viewer_can_both_reach_the_dashboard`

`tests/Feature/Console/CreateUserCommandTest.php`
- `test_command_creates_a_user_with_the_given_role`
- `test_command_rejects_an_invalid_role`
- `test_command_rejects_a_duplicate_email`

`tests/Feature/Console/ResetUserPasswordCommandTest.php`
- `test_command_changes_the_password_and_the_user_can_login_with_it`
- `test_command_fails_for_an_unknown_email`
- `test_old_password_no_longer_works_after_a_reset`

Every Feature test uses `use RefreshDatabase;` per-class (leave `tests/TestCase.php` bare —
that is stock Laravel 13 and the Unit suite must not pay for a DB migration).

---

## 3. M2 — `Agreement` model + the pending global scope

### 3.1 `app/Models/Agreement.php`

Class attributes (repo idiom, all verified available in Laravel 13.25):
```
#[Fillable([...])]
#[ScopedBy(HidePendingFromNonLegalScope::class)]
use SoftDeletes;
```

**`#[Fillable]`** — 19 columns:
`title`, `type`, `partner_id`, `campus_id`, `pic_user_id`, `sector`,
`agreement_date`, `effective_date`, `expiry_date`,
`received_from_po_at`, `board_approved_at`, `signed_by_unikl_at`, `sent_to_partner_at`, `signed_date`,
`document_status`, `project_status`, `project_status_updated_at`,
`scope`, `notes`.

**`archived_at` is deliberately excluded from `#[Fillable]`.** Archiving is a deliberate act
with an audit-trail obligation, not a form field — it gets an explicit `archive()` method in
M3. Mass-assigning it from request data is exactly how records go missing.

**`casts()`** — mirrors the column types exactly (8 `date`, 2 `datetime`):
```
agreement_date, effective_date, expiry_date,
received_from_po_at, board_approved_at, signed_by_unikl_at,
sent_to_partner_at, signed_date          => 'date'
project_status_updated_at, archived_at   => 'datetime'
```
`date` yields a Carbon at midnight, which is what date-only comparisons want; the two real
timestamps keep their time component. Enum columns stay **plain strings** — no PHP enum casts
(locked convention).

**Relations**
| Method | Type | Target |
|---|---|---|
| `partner()` | `BelongsTo` | `Partner::class` |
| `campus()` | `BelongsTo` | `Campus::class` |
| `pic()` | `BelongsTo` | `User::class, 'pic_user_id'` |
| `files()` | `HasMany` | `AgreementFile::class` |
| `activities()` | `HasMany` | `AgreementActivity::class` |

`activities()` stays an unordered relation — ordering is a view concern; baking `latest()` into
the relation silently breaks any future aggregate over it.

**Query scopes** — written as `#[Scope]` attribute methods (`Model.php:2022` confirms
non-private methods with the attribute resolve; called as `Agreement::notArchived()`):

| Scope | Body | Why |
|---|---|---|
| `notArchived()` | `whereNull('archived_at')` | default list filter |
| `archived()` | `whereNotNull('archived_at')` | the inverse; M3's archive view |
| `status($s)` | `whereIn('document_status', (array) $s)` | accepts one or many |
| `projectStatus($s)` | `whereIn('project_status', (array) $s)` | same shape |
| `expiringSoon($days = 90)` | `whereNotNull('expiry_date')->whereBetween('expiry_date', [today(), today()->addDays($days)])` | **`whereNotNull` is load-bearing** — null = indefinite and must never surface as "expiring" |
| `expired()` | `whereNotNull('expiry_date')->whereDate('expiry_date', '<', today())` | same null guard; see Decision 6 |

**Helpers**
- `const STALE_AFTER_DAYS = 90;` — one constant, so Ms. Haniza's confirmation is a one-line edit
- `year(): Attribute` (get-only) → `$this->agreement_date?->year` — the derived year, per the
  locked "no separate year field" rule
- `isIndefinite(): bool` → `is_null($this->expiry_date)`
- `isExpired(): bool` → `$this->expiry_date !== null && $this->expiry_date->isPast()`
- `isArchived(): bool` → `$this->archived_at !== null`
- `hasStaleProjectStatus(): bool` → `project_status_updated_at === null || ->lt(now()->subDays(self::STALE_AFTER_DAYS))` — the M3 badge's predicate, defined once here and tested in M2

**Not in M2** (M3 work): `archive()`, `unarchive()`, activity logging, status-transition rules.

### 3.2 The global scope — `app/Models/Scopes/HidePendingFromNonLegalScope.php`

```php
namespace App\Models\Scopes;

class HidePendingFromNonLegalScope implements \Illuminate\Database\Eloquent\Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        // No authenticated user = console, seeders, queued jobs. Every route that
        // touches agreements sits behind `auth`, so a guest never reaches this data.
        if ($user === null || $user->canSeePending()) {
            return;
        }

        $builder->where($model->qualifyColumn('document_status'), '!=', 'pending');
    }
}
```

**Where it lives, and why that makes it app-wide.** It is attached to the *model*, via
`#[ScopedBy(...)]` on `Agreement` — not to a controller, a base query, or a repository.
Eloquent applies registered global scopes inside `Model::newQuery()`, so it is present on
`Agreement::all()`, `::find()`, `::paginate()`, every relation traversal (`$campus->agreements`,
`$partner->agreements`), every eager load, and every `withCount`. There is no code path a
future developer can take that forgets it. `#[ScopedBy]` over a `booted()` override because
it matches the repo's existing attribute-config convention and is declarative at the top of
the file where a reviewer will actually see it.

**Details that matter:**
- `qualifyColumn()` produces `agreements.document_status`, so the scope survives joins to
  `partners` or `campuses` (both of which M3's search will need). A bare column name would
  throw "ambiguous column" the first time someone joins.
- `!=` is safe here: the migration declares `document_status` NOT NULL with default `pending`,
  so there is no NULL-comparison trap.
- **A viewer requesting a pending agreement by ID gets a 404, not a 403.** That is correct —
  a 403 confirms the record exists. Don't "improve" it later.
- **The single escape hatch** is `Agreement::withoutGlobalScope(HidePendingFromNonLegalScope::class)`.
  It must appear in **zero** application files. It appears only in the test that proves the
  scope — not the seed data — is what hides the row.
- **The one way to leak this rule is `DB::table('agreements')`**, which bypasses Eloquent
  entirely. Rule for the whole project: no raw query builder against `agreements`. Ever.
- `Auth::user()` runs at query-build time (after middleware), and the resolved user is cached
  per request by the guard, so this costs no extra queries.
- Admin sees pending too (`canSeePending()` = admin || legal), matching M1's "admin =
  everything". The rule is stated once, in `User::canSeePending()`.

### 3.3 `database/seeders/CountrySeeder.php`

Idempotent `updateOrInsert` keyed on `name`, matching `CampusSeeder`'s style. Malaysia with
`is_domestic = true`, everything else `false`. ISO 3166-1 alpha-2 in `iso_code` (the column is
`char(2)` unique nullable). Coverage aimed at the register's actual partner base: Malaysia,
Indonesia, Singapore, Thailand, Brunei, Vietnam, Philippines, Japan, South Korea, China,
India, Pakistan, Bangladesh, France, Spain, Italy, Germany, Netherlands, United Kingdom,
Türkiye, Egypt, Saudi Arabia, United Arab Emirates, Australia, United States. Registered in
`DatabaseSeeder::run()` alongside `CampusSeeder`.

### 3.4 Files for M2

**Create**
```
app/Models/Scopes/HidePendingFromNonLegalScope.php
database/seeders/CountrySeeder.php
database/factories/PartnerFactory.php
database/factories/AgreementFactory.php
tests/Feature/Agreement/PendingVisibilityTest.php
tests/Feature/Agreement/AgreementScopesTest.php
tests/Feature/Agreement/AgreementRelationsTest.php
tests/Feature/Agreement/AgreementCastsTest.php
tests/Feature/Seeders/CountrySeederTest.php
```

**Modify**
```
app/Models/Agreement.php
app/Models/Partner.php          (add `use HasFactory;`)
database/seeders/DatabaseSeeder.php
```

Per **approved Decision 8**, `AgreementFactory` and `PartnerFactory` move forward from M4 into
M2, and both models take `use HasFactory;` (`Agreement` gets it as part of its rewrite). The
"no `HasFactory` before its factory exists" convention still holds — the factories now exist.

`AgreementFactory` defaults must not fight the schema: `document_status` defaults to `pending`
(the DB default, and the state the visibility test needs), `campus_id` resolves to a real
campus (`Campus::factory()` does not exist — use `Campus::firstWhere('code', 'TBD')` seeded via
`CampusSeeder`, or a `Campus::firstOrCreate`), `partner_id` uses `Partner::factory()`,
`pic_user_id` is **null by default** (historical rows have none), and `expiry_date` is null by
default (indefinite). States to define: `pending()`, `awaitingPartner()`, `signed()`,
`archived()`, `expiringSoon()`, `expired()`, `staleProjectStatus()`.

### 3.5 Tests for M2

**`tests/Feature/Agreement/PendingVisibilityTest.php` — the business-rule test.** This is the
port of the one rule that defines the system, and the M4 portfolio evidence:
- `test_viewer_cannot_see_pending_agreements` — seed 1 `pending` + 2 `signed`, act as a
  `viewer`, assert `Agreement::count() === 2` and the pending ID is absent
- `test_legal_can_see_pending_agreements` — same data, act as `legal`, assert count is 3 and
  the pending row is present
- `test_admin_can_see_pending_agreements` — same, count 3
- `test_viewer_gets_model_not_found_for_a_pending_agreement_by_id` —
  `Agreement::findOrFail($pendingId)` throws for a viewer, resolves for legal
- `test_viewer_cannot_reach_pending_agreements_through_a_relation` —
  `$campus->agreements` and `$partner->agreements` are both filtered (proves it's on the model,
  not on a query helper)
- `test_pending_rows_are_excluded_from_relation_counts_for_a_viewer` —
  `Campus::withCount('agreements')`
- `test_unauthenticated_context_is_not_scoped` — no `actingAs`; console/seeder path sees all 3
- `test_without_global_scope_reveals_pending_rows` — proves the scope, not the fixture data,
  is doing the hiding

**`tests/Feature/Agreement/AgreementScopesTest.php`**
- `test_not_archived_excludes_archived_rows` / `test_archived_returns_only_archived_rows`
- `test_expiring_soon_excludes_agreements_with_a_null_expiry_date` ← **null = indefinite**
- `test_expiring_soon_excludes_already_expired_agreements`
- `test_expiring_soon_respects_a_custom_day_window`
- `test_expired_excludes_agreements_with_a_null_expiry_date`
- `test_status_scope_accepts_a_string_and_an_array`
- `test_project_status_scope_filters_correctly`
- (all run as `legal` so the global scope doesn't confound the assertion)

**`tests/Feature/Agreement/AgreementRelationsTest.php`**
- `test_partner_campus_and_pic_relations_resolve`
- `test_pic_relation_uses_the_pic_user_id_column`
- `test_pic_may_be_null` (historical rows)
- `test_files_and_activities_relations_resolve`

**`tests/Feature/Agreement/AgreementCastsTest.php`**
- `test_all_date_columns_cast_to_carbon`
- `test_project_status_updated_at_and_archived_at_cast_to_datetime`
- `test_null_expiry_date_stays_null_and_is_indefinite`
- `test_year_is_derived_from_agreement_date_and_is_null_when_the_date_is_null`
- `test_has_stale_project_status_is_true_past_the_threshold_and_when_never_set`
- `test_archived_at_is_not_mass_assignable`

**`tests/Feature/Seeders/CountrySeederTest.php`**
- `test_seeder_is_idempotent_when_run_twice`
- `test_malaysia_is_the_only_domestic_country`

---

## 4. Open decisions for Amir

### 4.1 Decided — implement exactly this

| # | Decision | Resolution | Effect on M1/M2 |
|---|---|---|---|
| **1** | Auth approach | **LOCKED before this plan: minimal custom auth.** No Breeze, no registration, no email verification. Section 2.1 is an audit confirming it. | All of Section 2 |
| **4** | PIC project-status edits | **LOCKED before this plan:** Legal updates on behalf; the stale-status flag ships in the MVP. | `Agreement::STALE_AFTER_DAYS` + `hasStaleProjectStatus()` land in M2; the badge renders in M3 |
| **6** | "Expired" — derived or stored? | ✅ **Approved 20 Aug: `expiry_date` is authoritative.** `document_status = 'expired'` is treated as legacy-import-only and **is never written by the application**. | `expired()` scope stays `whereNotNull('expiry_date')->whereDate('expiry_date','<',today())`. **No schema change** — the enum value simply stays unused. |
| **7** | `notArchived()` — global or local? | ✅ **Approved 20 Aug: local scope**, applied explicitly by list queries. | Exactly **one** global scope exists on `Agreement`: `HidePendingFromNonLegalScope`. Archive filtering is always explicit. |
| **8** | Factory timing for M2 tests | ✅ **Approved 20 Aug: move `AgreementFactory` + `PartnerFactory` forward into M2**, overriding the "factories in M4" convention. | `use HasFactory;` added to `Agreement` and `Partner` in M2; see Section 3.4 for factory defaults |
| **9** | Login form implementation | ✅ **Approved 20 Aug: plain Blade + `AuthenticatedSessionController`.** | The Livewire-SFC-only convention resumes at M3; M1 adds no `⚡` components |
| **10** | Password resets without mail | ✅ **Approved 20 Aug: `user:password {email}` artisan command.** | Adds `app/Console/Commands/ResetUserPasswordCommand.php` + `tests/Feature/Console/ResetUserPasswordCommandTest.php` to M1's file list |

### 4.2 Still open — neither blocks M1 or M2

| # | Decision | Options | **Recommendation** |
|---|---|---|---|
| **2** | Who may create/edit agreements? | (a) legal + admin write, viewer read-only · (b) admin only · (c) viewer may edit project_status | **(a)** — matches your lean and the M1 role table. `User::canWrite()` encodes it in one place in M1, so whichever way you go, widening it later is a one-line change. Only *binds* at M3, when there are forms to guard. |
| **3** | Date validation strictness on historical data | (a) hard rule `expiry_date >= effective_date` · (b) soft inline warning · (c) no check | **(b)** — a hard rule makes correct historical rows unsaveable and people will fake dates to get past it, corrupting the data worse than the inconsistency does. Needed at M3 (form validation), not before. Revisit once Ms. Haniza confirms the 2022–24 data is clean. |
| **5** | File uploads in MVP | (a) defer to phase 2 · (b) build now | **(a) defer** — schema and models are already ready, so adding it later is additive, not a refactor. Upload UI drags in storage config, size/MIME policy and a retention question nobody has answered. |

---

## 5. Verification

**M1 done when:**
1. `composer test` green. `laravel/pao` prints compact JSON — parse `"result"`, do not re-run
   expecting pretty output.
2. `vendor\bin\pint` clean on every touched PHP file.
3. Manual browser check: `php artisan migrate:fresh --seed` → visit `/` → redirected to
   `/login` (**not** a blank 401 — that's gap #6) → log in as the seeded admin → dashboard
   shows name + role → logout returns to `/login` → `/dashboard` while logged out redirects
   to `/login` → `/register` is a 404 → six wrong passwords in a row gives a 429.
4. `php artisan user:create --email=x@unikl.edu.my --role=legal` creates a user that can log in.
5. `php artisan user:password x@unikl.edu.my` changes that user's password; the old one no
   longer works and the new one does.

**M2 done when:**
1. `composer test` green, including all eight `PendingVisibilityTest` cases.
2. `php artisan migrate:fresh --seed` → `countries` populated, Malaysia domestic.
3. `php artisan tinker` spot check — the rule, by hand:
   ```
   Auth::login(User::where('role','viewer')->first());  Agreement::count();   // excludes pending
   Auth::login(User::where('role','legal')->first());   Agreement::count();   // includes pending
   ```
4. `vendor\bin\pint` clean.

**Not built in M1+M2** (deliberately): agreement list/form/detail UI, activity logging, archive
UX, the stale badge's rendering, file upload, dashboard analytics, CSV/Excel. M3 is not planned
until M1 and M2 are approved and merged.
