# CLSD Agreement Register — Architecture Plan for M3 (Vertical Slice)

> **Author:** Claude Code (Lead Architect) · **For:** Amir (PM) · **Date:** 20 Aug 2026
> **Status:** PROPOSAL — nothing implemented. Amir approves before OpenCode Go touches code.
> **Scope:** M3 ONLY — the three Livewire components that make the register usable.
> M4 (portfolio tests) and M5 (handover docs) are deliberately out of scope.
> **Companion docs:** `docs/architecture-plan.md` (M1+M2, approved and now implemented),
> `docs/BUILD_PLAN.md` Section 2 "M3" and Section 3 (field-level spec).

---

## Context

M1 and M2 are merged. The system now has identity (roles, login, middleware) and a domain model
(`Agreement` with the `pending` global scope welded into Eloquent). What it does not have is a
way for a human to use any of it — there are no routes past `/dashboard`, and no component has
ever been created.

M3 is the vertical slice: list, create/edit, and detail. When it lands, a `legal` user can
register an agreement end-to-end in a browser, and a `viewer` can find one without ever seeing
a `pending` row. This is the milestone that turns a schema into a tool.

The M2 work makes M3 unusually cheap in one specific way: **the access rule needs no work in the
view layer at all.** It is already enforced below every query the components will write.

---

## 1. Verified state (read on this repo, 20 Aug 2026)

Verified by reading every file, not by trusting the plan docs. `php artisan test` →
`{"tool":"phpunit","result":"passed","tests":57,"passed":57,"assertions":156}`.

### M1 and M2 are genuinely complete

| Checked | Result |
|---|---|
| `app/Models/Agreement.php` | All 19 `#[Fillable]` columns; `archived_at` correctly ABSENT. 10 casts (8 `date`, 2 `datetime`). 5 relations. 6 `#[Scope]` methods. `STALE_AFTER_DAYS = 90`, `year()` accessor, 4 boolean helpers. `SoftDeletes` + `HasFactory`. |
| `HidePendingFromNonLegalScope` | Attached via `#[ScopedBy]`. Uses `qualifyColumn()`. Early-returns for `null` user and for `canSeePending()`. Exactly as specified. |
| `User` | `is_active` fillable + cast to boolean. All 6 role helpers. `#[Scope] active()` present. |
| Auth | All 5 security-contract points implemented: `is_active` in the `Auth::attempt` credentials array, `session()->regenerate()`, POST-only logout with invalidate + regenerateToken, `throttle:5,1`, single generic `auth.failed`. |
| `bootstrap/app.php` | `role` alias registered AND `redirectGuestsTo('/login')` present. |
| Seeders | `CampusSeeder` (16), `CountrySeeder` (25, Malaysia the only domestic), `UserSeeder` (env-guarded). |
| Tests | 57 passing, including all 8 `PendingVisibilityTest` cases and the `withoutGlobalScope` proof. |

**No drift from `docs/architecture-plan.md` in the M1/M2 deliverables.** The implementation
matches the approved contract. Nothing needs re-doing.

### Gaps M3 inherits — none are M1/M2 defects, all are M3's problem to solve

**BLOCKER — the layout will render M3's pages blank.**
`resources/views/layouts/app.blade.php` uses `@yield('content')` / `@yield('title')`, written for
M1's `@extends`-style Blade views. Livewire 4 renders a full-page component into **`{{ $slot }}`**:
`PageComponentConfig::__construct` defaults `type = 'component'` and `slotOrSection = 'slot'`
(`vendor/livewire/livewire/src/Features/SupportPageComponents/PageComponentConfig.php`), and
`SupportPageComponents::renderContentsIntoLayout()` then renders via `@component`/`@slot`.
Livewire's own layout stub confirms the shape — it uses `{{ $slot }}` and `{{ $title ?? ... }}`.
`config('livewire.component_layout')` is `layouts::app`, which resolves through the `layouts`
component namespace to this exact file.

Consequence: the chrome renders, the component body is discarded. **No exception, no warning —
just an empty page.** This must be fixed before the first component is written, or the senior dev
will lose hours to it. See Open Decision 1.

**`Campus` has no `active()` scope.** The field spec calls for "Active campuses only".
`User::active()` exists (M1 added it); `Campus` does not have the equivalent. See Open Decision 2.

**PIC dropdown has no realistic data.** `UserSeeder` creates `admin@unikl.edu.my` and
`legal@unikl.edu.my` only — both Legal-side. A PIC is UniKL staff *outside* Legal. The dropdown
will show two wrong names in dev. See Open Decision 3.

**`partners.name` is indexed, not unique** (`2026_01_01_000003`, `$table->index('name')`).
The field spec's inline "add new partner" path will happily create "UiTM" three times. See Open
Decision 4.

**The layout has no nav and no flash-message region.** M3 adds three pages with no way to
reach them, and saves would give no confirmation. Small additions, but they are M3's job.

**`EnsureUserHasRole` aborts 403 when `$user === null`.** Harmless as used (`auth` always runs
first), but if `role:` is ever applied without `auth`, guests get a 403 instead of a login
redirect. Worth knowing; no change proposed.

### One subtlety that would quietly defeat Decision 4

The field spec says updating `project_status` also stamps `project_status_updated_at`. If the
form stamps on **every** save instead of only when the value actually changed, the staleness
clock resets on every edit, `hasStaleProjectStatus()` never returns true, and the badge you
accepted visible-risk for becomes decorative. **The stamp must be guarded by
`$agreement->isDirty('project_status')`.** This is called out again in Section 4 and has a
dedicated test.

---

## 2. Components

Livewire 4 single-file components, `⚡`-prefixed, in `resources/views/components/` (the first
entry of `livewire.component_locations`, which is where `make:livewire` writes and where the
Finder looks). The `⚡` is stripped for naming — `⚡agreements-index.blade.php` is referenced as
`agreements-index` (`vendor/livewire/livewire/src/Finder/Finder.php:109-112`).

Create with `php artisan make:livewire AgreementsIndex` — the repo's `make_command` config is
already `type: 'sfc', emoji: true`, so the default output is correct. **Do not scaffold
`app/Livewire/*.php`.**

### 2.1 `⚡agreements-index` — the list

Responsible for: finding an agreement. Nothing else — it neither writes nor mutates.

- `use WithPagination;` (ships with Livewire 4, `src/WithPagination.php`)
- Public state, all `#[Url]` so filters survive a refresh and are shareable:
  `$search`, `$campus`, `$type`, `$documentStatus`, `$projectStatus`, `$showArchived = false`
- `updating*()` resets to page 1 on any filter change
- Query: `Agreement::query()->with(['partner', 'campus', 'pic'])` — **eager loading is required**;
  the list renders partner and campus per row and will N+1 without it
- `notArchived()` applied by default; `archived()` when `$showArchived` (Decision 7 — archive
  filtering is always explicit, never a second global scope)
- Search spans `title` and the related `partner.name` (see Section 3 for the join caveat)
- Renders: title, type, partner, campus, document status badge, project status badge, stale
  badge, expiry (or "Indefinite"), and a link to detail
- Create button rendered only when `auth()->user()->canWrite()`

### 2.2 `⚡agreement-form` — create and edit

Responsible for: validated writes. One component serves both routes; `mount(?Agreement $agreement = null)`
decides the mode.

- Public properties for each spec field (Section 4), plus quick-create fields
  `$newPartnerName`, `$newPartnerShortName`, `$newPartnerCountryId`, and a `$partnerMode` toggle
- `save()` validates, resolves/creates the partner, stamps `project_status_updated_at` **only
  when `project_status` is dirty**, persists, records activities, flashes, and redirects to the
  detail page
- A computed `dateWarning` property drives the soft warning (Decision 3) — advisory text, never
  a validation failure
- Dropdown data as computed properties so they are queried once per render:
  campuses (active only), active users for PIC, partners, countries

### 2.3 `⚡agreement-show` — detail, status change, activity feed

Responsible for: reading one agreement and changing its status.

- `mount(Agreement $agreement)` — implicit route binding. **This is where the global scope earns
  its keep:** binding resolves via `resolveRouteBinding()` → `newQuery()`, so a viewer requesting
  a pending agreement's URL gets `ModelNotFoundException` → **404, automatically**
  (`vendor/livewire/livewire/src/Drawer/ImplicitRouteBinding.php:141-165`). No guard to write,
  and a 404 rather than a 403 is correct — a 403 would confirm the record exists.
- Full field display; `expiry_date === null` renders **"Indefinite"**, never blank
- Status-change controls rendered only when `canWrite()`; each change writes an activity row
- Activity feed: `$agreement->activities()->with('user')->latest()->get()` — ordering applied
  here at the call site, because the relation is deliberately unordered on the model

### 2.4 Shared badges — plain Blade anonymous components, not Livewire

`resources/views/components/badges/*.blade.php`, used as `<x-badges.stale />`. Presentational
only, no state, so making them Livewire components would add a network round-trip for nothing.
They coexist with the `⚡` files in the same directory — Livewire only resolves what is requested
by name, so there is no collision.

---

## 3. The global scope and the list view

**Confirmed: the list view needs no special handling.** `#[ScopedBy]` on the model means
`Agreement::query()` in `⚡agreements-index` is already filtered before the component sees a row.
`paginate()`, `count()`, `with()`, and relation traversal all inherit it. A viewer's list simply
does not contain pending rows, and the pagination total is correct because the scope is applied
before counting. This was proven in M2 by `test_pending_rows_are_excluded_from_relation_counts_for_a_viewer`.

**Where M3 could still leak it — four places to police:**

1. **`DB::table('agreements')` — banned, permanently.** Raw query builder bypasses Eloquent and
   takes the scope with it. This includes any "total agreements" statistic, any hand-written
   aggregate, and any `DB::select()`. If a count is needed, it goes through `Agreement::query()`.
2. **`withoutGlobalScope()` must not appear in any component.** It exists in exactly one test
   and belongs nowhere else.
3. **A `join()` for partner search.** `$model->qualifyColumn()` in the scope produces
   `agreements.document_status`, so a join is safe from ambiguity — but the *safer* pattern is
   `whereHas('partner', fn ($q) => $q->where('name', 'like', ...))`, which needs no join at all.
   **Recommended: use `whereHas`, not `join`.** If a join is ever added for performance, the
   scope still holds, but every added `where` must be table-qualified.
4. **The status filter dropdown is a subtle leak of a different kind.** The rows are hidden,
   but offering a viewer a "Pending" filter option tells them the state exists and that rows are
   being withheld. **The filter's options must be built from the user's role** — `canSeePending()`
   decides whether `pending` is in the list. Same for any status-count summary.

---

## 4. Validation rules for `⚡agreement-form`

Per the field spec in `docs/BUILD_PLAN.md` Section 3.

| Field | Rule |
|---|---|
| `title` | `required, string, max:255` |
| `type` | `required, in:LOI,NDA,MOA,MOU,SEA,MOC,ADDENDUM` |
| `partner_id` | `required_if:partnerMode,existing`, `nullable`, `exists:partners,id` |
| `newPartnerName` | `required_if:partnerMode,new`, `nullable`, `string`, `max:255` |
| `newPartnerShortName` | `nullable, string, max:100` |
| `newPartnerCountryId` | `nullable, exists:countries,id` |
| `campus_id` | `required, exists:campuses,id` |
| `pic_user_id` | `nullable, exists:users,id` |
| `sector` | `nullable, in:academic,industri` |
| `agreement_date` | `nullable, date` |
| `effective_date` | `nullable, date` |
| `expiry_date` | `nullable, date` ← **no `after:` rule; see below** |
| `document_status` | `required, in:pending,awaiting_partner,signed` ← **three values, see below** |
| `project_status` | `required, in:not_started,ongoing,stalled,completed` |
| `scope` | `nullable, string` |
| `notes` | `nullable, string` |

`max:100` on `short_name` and `max:255` on `title` mirror the migration's column widths.

### Decision 6 has a direct consequence here

`document_status` offers **three** options, not four. Decision 6 resolved that `expiry_date` is
authoritative and the application **never writes `document_status = 'expired'`** — that enum value
is legacy-import-only. Putting "Expired" in the dropdown would contradict an approved decision and
create the drift the decision exists to prevent. Expiry is *displayed* (derived from
`expiry_date` via `isExpired()`), never *selected*.

### Decision 3 — soft warning, not a hard rule

`expiry_date >= effective_date` is **not** a validation rule. Historical rows may legitimately
violate it, and a hard rule would make correct data unsaveable — at which point people invent
dates to get past the form, which corrupts the register worse than the inconsistency does.

Implementation: a computed property, not a validator.

```php
public function getDateWarningProperty(): ?string
{
    if (! $this->effective_date || ! $this->expiry_date) {
        return null;
    }

    return $this->expiry_date < $this->effective_date
        ? 'Expiry date is earlier than the effective date. Save anyway if that matches the document.'
        : null;
}
```

Rendered as amber inline text beside the expiry field. It never blocks `save()`. The wording
matters: it tells the user the document itself is the authority, not the form.

### `project_status_updated_at` — the guarded stamp

```php
$agreement->fill($validated);

if ($agreement->isDirty('project_status')) {
    $agreement->project_status_updated_at = now();
}

$agreement->save();
```

`fill()` then check `isDirty()` then `save()`. Stamping unconditionally resets the staleness clock
on every unrelated edit and silently kills the Decision 4 badge. `project_status_updated_at` stays
out of the validated array — it is derived, never user-supplied, even though it is fillable.

### Null expiry stays meaningful

An empty expiry field must persist as `NULL`, not `''` or today's date. `NULL` means indefinite /
until completion — a domain fact, not missing data. The form binds it as a nullable date and the
detail view renders "Indefinite".

---

## 5. The stale-status badge

`Agreement::hasStaleProjectStatus()` already exists from M2 — M3 only renders it.

```blade
@if ($agreement->hasStaleProjectStatus())
    <x-badges.stale :since="$agreement->project_status_updated_at" />
@endif
```

- **List:** a small amber chip next to the project-status badge.
- **Detail:** a fuller line — "Project status last updated 137 days ago" — or "never updated"
  when `project_status_updated_at` is `NULL`, which `hasStaleProjectStatus()` also treats as
  stale. That null case is the one most likely to be forgotten in the template.
- **No N+1 risk:** it is a pure PHP method on an already-loaded model, issuing no query. Safe in
  a paginated loop.
- The threshold lives in `Agreement::STALE_AFTER_DAYS`. When Ms. Haniza confirms the number, it
  is a one-line change in the model and **nothing in M3 needs touching** — do not hardcode 90 in
  any template.

---

## 6. Who can write — Decision 2

**Treated as locked:** `legal` + `admin` may create and edit; `viewer` is strictly read-only.
`User::canWrite()` already returns `isAdmin() || isLegal()`, so the rule is encoded once and M3 is
simply the first place it binds. Amir's prompt states it directly; this plan records it in writing.

Enforced in **two layers, both required**:

1. **Route middleware** — `role:admin,legal` on the create and edit routes. This is the real
   security boundary. A viewer who types the URL gets a 403.
2. **Conditional rendering** — `@if (auth()->user()->canWrite())` around create/edit/status
   controls. This is UX, not security: it stops viewers being shown buttons that would 403 them.

Never rely on layer 2 alone. Both are tested separately.

---

## 7. Activity logging on status change

A status change writes one `AgreementActivity` row per changed status field:

```php
AgreementActivity::create([
    'agreement_id' => $agreement->id,
    'user_id'      => auth()->id(),
    'type'         => 'status_changed',
    'description'  => 'Document status changed from Awaiting Partner to Signed',
    'meta'         => ['field' => 'document_status', 'from' => 'awaiting_partner', 'to' => 'signed'],
]);
```

`meta` is already cast to `array` on the model — pass a PHP array, not JSON. `description` is the
human line for the feed; `meta` is the structured record so the log can be re-rendered later
without reparsing prose. Both are required by the migration comment.

**Where the logic lives:** a small invokable action, `app/Actions/RecordAgreementActivity.php`.
It has two call sites (form save, detail status change) and putting it on the `Agreement` model
would mean editing M2 business logic, which this milestone is explicitly barred from doing. A new
action class is additive and independently testable. See Open Decision 5 for the alternative.

**Guards:**
- Only write when the value **actually changed** — no activity row for a save that touched only
  the notes field.
- `document_status` and `project_status` changes get **separate rows**, each with its own `meta.field`.
- `user_id` is the acting user, `nullable` in the schema for system-generated rows.

---

## 8. Files

### Create
```
resources/views/components/⚡agreements-index.blade.php
resources/views/components/⚡agreement-form.blade.php
resources/views/components/⚡agreement-show.blade.php
resources/views/components/badges/document-status.blade.php
resources/views/components/badges/project-status.blade.php
resources/views/components/badges/stale.blade.php
app/Actions/RecordAgreementActivity.php
tests/Feature/Agreement/AgreementsIndexTest.php
tests/Feature/Agreement/AgreementFormTest.php
tests/Feature/Agreement/AgreementShowTest.php
tests/Feature/Agreement/AgreementActivityLogTest.php
tests/Feature/Agreement/AgreementAccessControlTest.php
```

### Modify
```
resources/views/layouts/app.blade.php     (slot support — the blocker; plus nav + flash region)
routes/web.php                            (four Route::livewire entries)
resources/views/dashboard.blade.php       (link into the register)
```

### Conditional on approval
```
app/Models/Campus.php                     (add #[Scope] active() — Open Decision 2, NEEDS AMIR APPROVAL)
database/seeders/StaffSeeder.php          (dev PIC accounts — Open Decision 3)
```

### Routes — order matters

```php
Route::middleware('auth')->group(function () {
    Route::livewire('/agreements', 'agreements-index')->name('agreements.index');

    Route::middleware('role:admin,legal')->group(function () {
        Route::livewire('/agreements/create', 'agreement-form')->name('agreements.create');
        Route::livewire('/agreements/{agreement}/edit', 'agreement-form')->name('agreements.edit');
    });

    Route::livewire('/agreements/{agreement}', 'agreement-show')->name('agreements.show');
});
```

**`/agreements/create` MUST be declared before `/agreements/{agreement}`.** Reversed, Laravel
matches `create` as an agreement ID, implicit binding fails, and the create page 404s — a
confusing failure that looks like a broken component.

---

## 9. Tests

`tests/Feature/Agreement/AgreementsIndexTest.php`
- `test_index_renders_for_an_authenticated_user`
- `test_guests_are_redirected_to_login`
- `test_viewer_does_not_see_pending_agreements_in_the_list` ← **the M3 port of the business rule**
- `test_legal_sees_pending_agreements_in_the_list`
- `test_pending_is_not_offered_as_a_status_filter_to_a_viewer` ← Section 3, leak #4
- `test_search_matches_title`
- `test_search_matches_partner_name`
- `test_filters_by_campus_type_document_status_and_project_status`
- `test_changing_a_filter_resets_to_the_first_page`
- `test_archived_agreements_are_excluded_by_default`
- `test_archived_agreements_appear_when_the_archived_filter_is_on`
- `test_stale_badge_is_shown_only_for_stale_rows`
- `test_viewer_does_not_see_the_create_button`

`tests/Feature/Agreement/AgreementFormTest.php`
- `test_legal_can_create_an_agreement`
- `test_admin_can_create_an_agreement`
- `test_required_fields_are_enforced`
- `test_type_must_be_one_of_the_allowed_values`
- `test_sector_must_be_academic_or_industri`
- `test_expired_is_not_an_option_for_document_status` ← **Decision 6**
- `test_expiry_before_effective_shows_a_warning_but_still_saves` ← **Decision 3**
- `test_no_warning_when_only_one_date_is_present`
- `test_empty_expiry_date_persists_as_null`
- `test_changing_project_status_stamps_project_status_updated_at`
- `test_saving_without_changing_project_status_does_not_restamp` ← **protects Decision 4**
- `test_partner_quick_create_creates_a_partner_and_links_it`
- `test_partner_quick_create_requires_a_name`
- `test_legal_can_edit_an_existing_agreement`
- `test_pic_dropdown_only_lists_active_users`
- `test_edit_form_retains_an_inactive_pic_already_assigned` ← see Open Decision 3
- `test_campus_dropdown_only_lists_active_campuses`

`tests/Feature/Agreement/AgreementShowTest.php`
- `test_legal_can_view_a_pending_agreement`
- `test_viewer_gets_404_for_a_pending_agreement` ← **global scope via route binding**
- `test_viewer_can_view_a_signed_agreement`
- `test_indefinite_expiry_renders_as_indefinite_not_blank`
- `test_activity_feed_renders_newest_first`
- `test_stale_badge_appears_on_detail_for_a_stale_agreement`
- `test_never_updated_project_status_renders_as_stale`
- `test_viewer_does_not_see_status_change_controls`

`tests/Feature/Agreement/AgreementActivityLogTest.php`
- `test_document_status_change_writes_a_status_changed_activity`
- `test_meta_records_the_field_and_the_from_and_to_values`
- `test_project_status_change_writes_its_own_activity_row`
- `test_no_activity_is_written_when_no_status_changed`
- `test_activity_records_the_acting_user`

`tests/Feature/Agreement/AgreementAccessControlTest.php`
- `test_viewer_gets_403_on_the_create_route`
- `test_viewer_gets_403_on_the_edit_route`
- `test_legal_can_reach_create_and_edit`
- `test_admin_can_reach_create_and_edit`
- `test_guests_are_redirected_from_every_agreement_route`

All Feature tests use `use RefreshDatabase;` per class, matching the existing suite. Component
tests use `Livewire::test('agreements-index', [...])` for state assertions and
`$this->actingAs($user)->get(route(...))` for routing/middleware assertions.

**Explicitly not in M3:** the M4 "tests as portfolio evidence" packaging, README/handover work,
Excel import. Those stay in M4/M5.

---

## 10. Open decisions

| # | Decision | Options | **Recommendation** |
|---|---|---|---|
| **1** | The layout renders `@yield('content')`, Livewire renders into `{{ $slot }}`. M3's pages will be blank until this is resolved. | (a) support both in one file — add `{{ $slot ?? '' }}` alongside `@yield('content')`, and `{{ $title ?? '' }}` alongside `@yield('title')` · (b) convert M1's login + dashboard views to `<x-layouts.app>` component syntax and make the layout slot-only · (c) add a second layout `layouts/page.blade.php` for Livewire and point components at it | **(a)** — one line, zero risk to working M1 views, and both rendering paths work from a single layout. (b) is cleaner long-term but edits M1's shipped views for no functional gain. (c) means two layouts drifting apart. Revisit (b) in M5 if the dual-mode file starts to look odd. |
| **2** | The field spec wants "active campuses only", but `Campus` has no `active()` scope (`User` does). | (a) add `#[Scope] active()` to `Campus`, mirroring `User::active()` exactly · (b) inline `where('is_active', true)` in the components | **(a)** — four lines, mirrors the existing `User` idiom, and keeps the rule in one place instead of repeated in two components. **NEEDS AMIR APPROVAL: this touches a model, which M3's brief bars.** It is additive (a new query scope, no change to existing behaviour), but it is your call. If you'd rather hold the line, (b) works and costs nothing but duplication. |
| **3** | PIC dropdown data: `UserSeeder` provides only `admin@` and `legal@`, both Legal-side. A real PIC is non-Legal staff. | (a) accept it — admin creates real staff with `php artisan user:create` · (b) add a dev-only `StaffSeeder` with ~5 `viewer`-role staff, env-guarded like `UserSeeder` · (c) extend `UserSeeder` | **(b)** — a new file, so nothing existing is modified, and the form is actually demonstrable in dev. (c) would edit a seeder the brief protects. Related: the **edit form must keep an already-assigned PIC visible even if that user is now inactive**, or editing an old agreement silently clears its PIC. That behaviour has its own test regardless of which option you pick. |
| **4** | Partner quick-create can produce duplicates — `partners.name` is indexed, not unique. | (a) `firstOrCreate` on exact name — silently reuses an exact match · (b) live "similar partner exists" warning listing near-matches, user decides · (c) accept duplicates; clean up later | **(b)** — exact-match dedup (a) misses "UiTM" vs "Universiti Teknologi MARA", which is the duplicate that actually happens in this register. A warning surfaces it without blocking a genuinely new partner. **No schema change either way** — adding a unique index would need approval and would break historical imports. |
| **5** | Where the activity-write logic lives. | (a) `app/Actions/RecordAgreementActivity.php` invokable class · (b) inline in both components · (c) a method on `Agreement` | **(a)** — two call sites, independently testable, and it avoids (c) which would mean editing M2 model logic that this milestone is barred from touching. (b) duplicates the from→to diffing in two places, which is where it will eventually drift. |
| **6** | Should creation write an activity row too? The enum already has `created` and `updated` types. | (a) log `created` on create and `status_changed` on status changes; skip `updated` for ordinary field edits · (b) status changes only · (c) log everything including `updated` | **(a)** — an activity feed that starts blank until someone changes a status looks broken. Logging every field edit (c) makes the feed noisy enough that Legal stops reading it, which defeats the audit trail. |
| **7** | The layout has no nav and no flash-message region; M3 adds three unreachable pages with silent saves. | (a) minimal nav (Register / Dashboard / Log out) + a `session('status')` flash block in the layout · (b) leave it; add navigation in M5 | **(a)** — without it the milestone's own definition of done ("a legal user can register an agreement end-to-end") can't be demonstrated in a browser. It is ~15 lines in the layout, not a design project. |

---

## 11. Verification

**M3 done when:**

1. `composer test` green. `laravel/pao` prints compact JSON — parse the `result` field; do not
   re-run expecting pretty output. Expect ~57 existing + ~50 new tests.
2. `vendor\bin\pint` clean on every touched PHP file.
3. `php artisan migrate:fresh --seed`, then in the browser:
   - Log in as `legal@unikl.edu.my` → nav shows the register → `/agreements` lists rows
   - Create an agreement end-to-end, including one via the inline "add new partner" path
   - Set `effective_date` after `expiry_date` → **amber warning appears and the record still saves**
   - Open the detail page → change document status → the activity feed shows the change with
     from→to, attributed to the legal user
   - An agreement with `project_status_updated_at` older than 90 days shows the stale badge;
     edit an unrelated field, save, and confirm **the badge is still there** (the guarded stamp)
   - An agreement with no expiry date renders "Indefinite", not a blank cell
4. Log in as a `viewer` (create one with `php artisan user:create --role=viewer`):
   - The list shows no pending rows, and **"Pending" is absent from the status filter**
   - Navigating directly to a pending agreement's URL returns **404**
   - No create/edit buttons anywhere; typing `/agreements/create` returns **403**
5. `grep -rn "DB::table('agreements')\|withoutGlobalScope" app/ resources/` returns **nothing**.

**Not built in M3** (deliberately): file upload UI, dashboard analytics, archive/restore UX,
notifications, CSV export, Excel import, approval workflow engine. M4 and M5 are unplanned until
M3 is approved and merged.
