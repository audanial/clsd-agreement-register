# OpenCode Go handoff — M3: the vertical slice (list, form, detail)

> **Milestone:** M3 · **Implements:** Sections 2–9 of `docs/architecture-plan-m3.md`
> **Precondition:** M1 and M2 must be merged. Verified green on 20 Aug 2026 —
> 57 tests, 156 assertions, no drift from `docs/architecture-plan.md`.
> **Decisions:** all 7 M3 decisions approved by Amir on 21 Aug 2026. None is reopenable.
> **Paste the block below into OpenCode Go from the repo root.**

```text
Role: You are the SENIOR DEV on the CLSD Agreement Register (Laravel 13 +
Livewire 4, SQLite). Amir is the PM: implement exactly what the approved plan
says — do not redesign, do not add scope.

Context: Read AGENTS.md, docs/BUILD_PLAN.md (the MVP milestones, Section 3 has
the field-level spec), and docs/architecture-plan-m3.md (the APPROVED M3
architecture, approved 21 Aug 2026). That plan is the contract. All seven of its
decisions are settled — do not reopen them. If something it does not cover comes
up, ask Amir; do not guess.

Numbering: docs/architecture-plan.md has Decisions 1-10; the M3 plan has its own,
written M3-1 to M3-7. In this prompt a bare "D6" means the M1/M2 plan.

Precondition: M1 (auth + roles) and M2 (Agreement model + pending global scope)
must already be merged. `composer test` should be green at 57 tests before you
start. If it is not, stop and tell Amir.

Task: Implement milestone M3 — the vertical slice that makes the register usable
in a browser. Build it in this order; step 1 is deliberately first.

1. FIX THE LAYOUT FIRST, AND PROVE IT (M3-1 + M3-7).
   resources/views/layouts/app.blade.php currently uses @yield('content') and
   @yield('title'), written for M1's @extends-style Blade views. Livewire 4
   renders a full-page component into {{ $slot }}, NOT into @yield — see
   PageComponentConfig (type='component', slotOrSection='slot') and
   SupportPageComponents::renderContentsIntoLayout(). The chrome would render and
   the component body would be silently discarded: no exception, no warning, just
   an empty page.
   - Add {{ $slot ?? '' }} alongside @yield('content'), and {{ $title ?? '' }}
     alongside @yield('title'). Support BOTH. Do not convert M1's login or
     dashboard views — they work, and touching them buys nothing.
   - Add minimal nav (Register / Dashboard / Log out) and a session('status')
     flash region. Roughly 15 lines. Not a design project.
   - THEN PROVE IT: scaffold one throwaway component, add a route, load it in a
     browser, confirm the body actually appears. Do this BEFORE writing the three
     real components. If you skip this and a component renders blank later, this
     is why — and you will have three of them to debug at once.

2. Section 2.1 — ⚡agreements-index (the list). Create it with
   `php artisan make:livewire AgreementsIndex`; the repo's make_command config is
   already sfc + emoji, so the default output is right. DO NOT scaffold
   app/Livewire/*.php.
   - use WithPagination. Public #[Url] state: search, campus, type,
     documentStatus, projectStatus, showArchived (default false).
   - updating*() resets to page 1 on any filter change.
   - Agreement::query()->with(['partner','campus','pic']) — the eager load is
     REQUIRED; the list renders partner and campus per row and N+1s without it.
   - notArchived() by default; archived rows only when showArchived is on.
   - Search spans title and partner.name via whereHas('partner', ...). Do NOT
     join. See the guardrails below.
   - Renders title, type, partner, campus, document-status badge,
     project-status badge, stale badge, expiry (or "Indefinite"), detail link.
   - Create button only when auth()->user()->canWrite().

3. Section 2.2 — ⚡agreement-form (create AND edit, one component).
   mount(?Agreement $agreement = null) decides the mode.
   - A public property per field in the Section 4 table, plus quick-create fields
     newPartnerName, newPartnerShortName, newPartnerCountryId and a partnerMode
     toggle.
   - Validation rules EXACTLY as the Section 4 table lists them.
   - save() validates, resolves or creates the partner, stamps
     project_status_updated_at ONLY when project_status is dirty, persists,
     records activities, flashes, redirects to the detail page.
   - Computed dateWarning property for the soft date warning (D3) and a computed
     similar-partner warning for M3-4. Both advisory. Neither ever blocks save().
   - Dropdown data as computed properties so each is queried once per render:
     campuses (active only), active users for PIC, partners, countries.

4. Section 2.3 — ⚡agreement-show (detail + status change + activity feed).
   - mount(Agreement $agreement) via implicit route binding. Binding resolves
     through resolveRouteBinding() -> newQuery(), so the M2 global scope applies
     and a viewer hitting a pending agreement's URL gets a 404 automatically.
     Write NO guard for this. A 404 rather than a 403 is deliberate: a 403 would
     confirm the record exists.
   - Full field display. expiry_date === null renders "Indefinite", never blank.
   - Status-change controls only when canWrite(); each change writes an activity.
   - Feed: $agreement->activities()->with('user')->latest()->get() — the ordering
     goes at the call site, because the relation is deliberately unordered on the
     model.

5. Section 2.4 — the three badges as PLAIN BLADE anonymous components in
   resources/views/components/badges/, used as <x-badges.stale />. They are
   presentational with no state; making them Livewire adds a network round-trip
   for nothing. They coexist fine with the ⚡ files in the same directory.

6. Section 7 — app/Actions/RecordAgreementActivity.php, an invokable class
   (M3-5). Logs `created` on create and `status_changed` on each status change.
   It NEVER writes `updated` rows for ordinary field edits (M3-6).
   - One row per changed status field: document_status and project_status changes
     get separate rows, each with its own meta.field.
   - Only write when the value ACTUALLY changed.
   - meta is already cast to array on the model — pass a PHP array, not JSON.
   - description is the human line for the feed; meta is the structured record.
     Both are required by the migration comment.
   - user_id is the acting user.

7. Section 8 — routes/web.php, four Route::livewire entries inside the auth
   group, with role:admin,legal on create and edit. Copy the block from the plan.
   /agreements/create MUST be declared BEFORE /agreements/{agreement}. Reversed,
   Laravel matches "create" as an agreement ID, implicit binding fails, and the
   create page 404s — a confusing failure that looks like a broken component.
   Also add a link into the register from resources/views/dashboard.blade.php.

8. The two approved additions — Amir signed both off, they are NOT scope creep:
   - app/Models/Campus.php: add #[Scope] active() mirroring User::active()
     exactly (M3-2). This is the ONLY authorized model edit in M3. Nothing else
     in that file changes, and the permission does NOT extend to Agreement.
   - database/seeders/StaffSeeder.php: a NEW dev-only seeder, ~5 viewer-role
     staff, env-guarded with App::environment('local','testing') exactly like
     UserSeeder (M3-3). Register it in DatabaseSeeder::run(). Do not edit
     UserSeeder itself.

9. Section 9 — write every test listed, roughly 50 across five files in
   tests/Feature/Agreement/. use RefreshDatabase per class, matching the existing
   suite. Livewire::test('agreements-index', [...]) for state assertions;
   $this->actingAs($user)->get(route(...)) for routing and middleware assertions.

10. Run `composer test` — laravel/pao prints compact JSON when it detects an AI
    agent; parse the "result" field, do not re-run expecting pretty output.
    Everything must pass: the ~57 existing tests plus the new ones.

Approved M3 decisions (do not reopen):
- M3-1: layout supports both {{ $slot }} and @yield. M1's views stay untouched.
- M3-2: Campus gets #[Scope] active(). Authorized model edit, Campus.php only.
- M3-3: new dev-only StaffSeeder for PIC data. UserSeeder is not modified.
- M3-4: similar-partner warning, non-blocking. No unique index, no schema change.
- M3-5: activity writes live in app/Actions/RecordAgreementActivity.php.
- M3-6: log `created` + `status_changed` only. Never `updated`.
- M3-7: minimal nav + flash region in the layout, now, not in M5.

Carried decisions from the M1/M2 plan that bind M3:
- D2: legal + admin can create and edit; viewer is strictly read-only.
- D3: expiry-before-effective is a SOFT WARNING, never a validation rule.
  Historical rows legitimately violate it, and a hard rule makes correct data
  unsaveable — at which point people invent dates and corrupt the register worse
  than the inconsistency does.
- D4: staleness threshold lives in Agreement::STALE_AFTER_DAYS.
- D6: expiry_date is authoritative. The app NEVER writes
  document_status = 'expired'. Expiry is DISPLAYED, never SELECTED.
- D7: notArchived() is a local scope applied explicitly. Exactly ONE global scope
  exists on Agreement.

Guardrails — each of these is a way to silently break something already paid for:
- NEVER use DB::table('agreements'). Raw query builder bypasses Eloquent and
  takes the pending scope with it. This includes any count, statistic or
  DB::select(). Counts go through Agreement::query().
- withoutGlobalScope(...) must appear in ZERO application files. It exists in
  exactly one M2 test and belongs nowhere else.
- Partner search uses whereHas('partner', ...), not join(). qualifyColumn() in
  the scope makes a join safe from ambiguity, but whereHas needs no join at all.
- THE STATUS FILTER'S OPTIONS ARE BUILT FROM canSeePending(). Hiding pending rows
  but still offering a viewer a "Pending" filter option tells them rows are being
  withheld. Same for any status-count summary. This is a real leak of the exact
  fact the whole scope exists to hide.
- The document_status dropdown has THREE options: pending, awaiting_partner,
  signed. Not four. "Expired" in the dropdown would contradict D6.
- The project_status_updated_at stamp MUST be guarded:
      $agreement->fill($validated);
      if ($agreement->isDirty('project_status')) {
          $agreement->project_status_updated_at = now();
      }
      $agreement->save();
  Stamping unconditionally resets the staleness clock on every unrelated edit,
  hasStaleProjectStatus() never returns true, and the stale badge becomes
  decorative. project_status_updated_at stays OUT of the validated array — it is
  derived, never user-supplied, even though it is fillable.
- An empty expiry field persists as NULL, not '' and not today. NULL means
  indefinite / until completion — a domain fact, not missing data. The detail
  page renders "Indefinite", never a blank cell.
- hasStaleProjectStatus() also treats a NULL timestamp as stale. The detail
  template must handle "never updated" — that is the case most likely to be
  forgotten.
- Never hardcode 90 anywhere. Read Agreement::STALE_AFTER_DAYS. When Ms. Haniza
  confirms the number it must be a one-line model change with nothing in M3 to
  touch.
- Write permission is enforced in TWO layers and both are required: route
  middleware (role:admin,legal — the real security boundary) AND conditional
  rendering (@if (auth()->user()->canWrite()) — UX only, so viewers are not shown
  buttons that would 403 them). Never rely on the rendering alone.
- The edit form must keep an already-assigned PIC visible even if that user is
  now inactive, or editing an old agreement silently clears its PIC.

Do not change:
- Any migration. Not one line, not a comment. The migrations are the design doc.
- CampusSeeder, CountrySeeder, UserSeeder, or any seeded data. campus_id stays
  NOT NULL, TBD stays.
- The Agreement model — its global scope, casts, fillable list and helpers are
  frozen. M3 is the view/component layer on top of what M2 built.
- Anything from M1's auth or roles logic. The three-role enum stays; do not add
  spatie/laravel-permission.
- app/Models/Campus.php beyond adding the active() scope. That is the single
  authorized model edit.
- Anything outside the M3 file list in Section 8 of docs/architecture-plan-m3.md.
- Do not run npm scripts (ignore-scripts=true) or touch vite.config.js.

Do not build (deliberately out of scope for M3):
- File upload UI, dashboard analytics, archive/restore UX, notifications, CSV
  export, Excel import, approval workflow engine.
- The M4 "tests as portfolio evidence" packaging and the M5 handover docs.
- PHP enum casts for document_status/project_status/type/sector — locked
  convention, they stay plain strings.

Acceptance checks:
- `composer test` green (parse the JSON output; report the result field).
- `vendor\bin\pint` run on every PHP file you touched.
- `grep -rn "DB::table('agreements')\|withoutGlobalScope" app/ resources/`
  returns NOTHING.
- The full browser walkthrough in Section 11 of docs/architecture-plan-m3.md,
  as both a legal user and a viewer. The viewer checks are the important half:
  no pending rows in the list, "Pending" absent from the status filter, 404 on a
  pending agreement's URL, 403 on /agreements/create.

After editing:
- List every file created/modified.
- Report exact test names and results.
- Walk Amir through what to check in the browser — M3 is the first milestone with
  visible output, so this matters more than in M1/M2.
- Do not commit unless Amir says to.
```

## Notes for Amir

- **M3 is the first milestone you can actually look at.** M1 was checkable only by logging in and
  M2 only through tinker. The Section 11 browser walkthrough is the real acceptance test here —
  the suite passing is necessary but not sufficient.
- **The layout blocker is the highest-risk item, which is why it is step 1 with its own proof
  step.** The failure mode is a blank page with no error message, and it would look like a broken
  Livewire component rather than a layout problem. If OpenCode Go reports "the component renders
  nothing", this is the first thing to check.
- **`Campus.php` is the one authorized model edit.** If OpenCode Go stops and queries it against
  the "do not change" list, that is correct behaviour — confirm M3-2 and let it continue.
- **One manual check catches a bug nothing else will:** open a stale agreement, edit an unrelated
  field, save, and confirm the stale badge is *still there*. A naive implementation that stamps
  `project_status_updated_at` on every save passes every other check in the walkthrough and every
  test except `test_saving_without_changing_project_status_does_not_restamp`.
