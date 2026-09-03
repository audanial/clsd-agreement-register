# CLSD Agreement Register

Internal UniKL system for tracking legal agreements (LOI, NDA, MOA, MOU, SEA, MOC, ADDENDUM) with partners, per campus.

The register is used by Legal staff to record and monitor agreements, and by other UniKL staff to look them up. The single most important rule is: **agreements with `document_status = pending` are still in Legal vetting and are invisible to anyone who is not Legal or Admin.** This is enforced below every query, not in the UI layer.

---

## Requirements

- PHP 8.3+
- Composer
- Node.js 20+
- SQLite extension enabled

Or use [Laravel Herd](https://herd.laravel.com), which bundles all of the above.

---

## Setup

1. Clone the repo.
2. Run the first-time setup script:

   ```bash
   composer setup
   ```

   This installs PHP and JavaScript dependencies, creates `.env`, generates the application key, runs migrations, and builds the frontend assets.

   **What `composer setup` does not do**

   - It does **not** create `database/database.sqlite` if the file is missing. Create it by hand first:

     ```bash
     New-Item database\database.sqlite -ItemType File   # PowerShell
     touch database/database.sqlite                     # Git Bash / WSL / macOS
     ```

   - It does **not** seed the database. On a development machine, run:

     ```bash
     php artisan db:seed
     ```

     This creates the default campuses, countries, and dev-only `admin`/`legal` accounts.

   On any non-development environment, **do not run `db:seed`**. The seeders that create accounts are guarded to `local`/`testing` by design. Create the first account with:

   ```bash
   php artisan user:create --email=you@unikl.edu.my --role=admin
   ```

3. **Offline setup warning.** `composer setup` runs `npm run build`, which fetches the "Instrument Sans" font from Bunny Fonts over the network (`vite.config.js`). If you are offline, this step fails with an error that does not mention fonts. Connect to the internet for the first build.

4. `.env.example` ships with `APP_NAME=Laravel`. Update `APP_NAME` in `.env` to something meaningful if you care what appears in the browser tab and emails.

---

## Running it

Start the development environment:

```bash
composer dev
```

This runs `php artisan serve`, `php artisan queue:listen`, and `npm run dev` in one terminal UI. The queue listener matters because `QUEUE_CONNECTION=database`; anything that dispatches a job will sit in the `jobs` table until the listener is running.

The app will be available at `http://localhost:8000`. Guests are redirected to `/login`.

Dev accounts seeded by `DatabaseSeeder`:

| Email | Role | Password |
|---|---|---|
| `admin@unikl.edu.my` | admin | `password` (or the value of `DEV_USER_PASSWORD`) |
| `legal@unikl.edu.my` | legal | `password` (or the value of `DEV_USER_PASSWORD`) |

---

## Roles

| Role | Can do |
|---|---|
| **admin** | Everything, including creating and deactivating users. |
| **legal** | Create, edit, and change the status of agreements. Sees `pending` agreements. Cannot manage users. |
| **viewer** | Read only. Cannot see `pending` agreements or reach create/edit routes. |

A **PIC** (project owner) is just a user, usually with the `viewer` role. They do **not** need to log in to appear in the agreement form's PIC dropdown; they only need an active user record.

**Admin is granted on trust and system ownership, not on organisational rank.** Seniority is not, by itself, a reason to hold an admin account. The principle is recorded here so it survives any change of personnel.

---

## Managing users

Admin users can add and deactivate people from the web UI:

1. Click **Users** in the top navigation.
2. Fill in **Name**, **Email**, **Role**, and an **Initial password**.
3. Click **Create user**.

The new user can then be selected as the PIC in the agreement form. Deactivating a user removes them from the PIC dropdown but keeps them visible on any agreement they were already assigned to.

An admin cannot deactivate their own account from the UI. If the only admin is unavailable, use the command line:

```bash
php artisan user:create --email=you@unikl.edu.my --role=admin
```

Reset a user's password:

```bash
php artisan user:password someone@unikl.edu.my
```

Both `UserSeeder` and `StaffSeeder` are guarded to `local`/`testing` environments. On production or staging, `user:create` is the only way to create the first account.

---

## Running the tests

```bash
composer test
```

This clears the config cache and runs PHPUnit. Because `laravel/pao` is installed, the output is compact JSON when an AI agent runs it:

```json
{"tool":"phpunit","result":"passed","tests":117,"passed":117,...}
```

Parse the `result` field. If it says `passed`, the suite is green. Human-run PHPUnit shows normal pretty output; the acceptance criterion is the same.

Format PHP changes:

```bash
vendor\bin\pint
```

---

## How the code is laid out

- `app/Models/` — Eloquent models. `Agreement` carries the `HidePendingFromNonLegalScope` global scope, which is the core access rule.
- `app/Actions/` — Small invokable classes shared by multiple entry points (e.g., activity logging).
- `resources/views/components/⚡*.blade.php` — **Livewire 4 single-file components.** The filename starts with a literal `⚡` emoji. These are anonymous classes with the Blade template in the same file. `php artisan make:livewire Foo` creates `resources/views/components/⚡foo.blade.php`. Do not expect `app/Livewire/Foo.php`.
- `resources/views/components/badges/` — Plain Blade anonymous components for status badges.
- `routes/web.php` — Routes grouped by middleware; Livewire full-page components are registered with `Route::livewire(...)`.
- `database/migrations/` — Schema. The `2026_*` migrations contain comments that explain business rules; treat them as the design doc.
- `database/seeders/` — Seeders. `CampusSeeder` and `CountrySeeder` are unguarded and run through `DatabaseSeeder`; `UserSeeder` and `StaffSeeder` are dev-only.
- `tests/Feature/` — Feature tests, organised by area. Every feature test class uses `RefreshDatabase`.
- `docs/` — Architecture plans, handoffs, QA artifacts, and the non-technical handover note.

---

## Where the documents live

| File | What it is |
|---|---|
| `AGENTS.md` | Agent-facing conventions, domain rules, and design philosophy. Read this first if you are working on the code. |
| `docs/BUILD_PLAN.md` | Original MVP milestone plan and field-level form spec. |
| `docs/architecture-plan.md` | Approved architecture for M1 + M2. |
| `docs/architecture-plan-m3.md` | Approved architecture for M3 (vertical slice). |
| `docs/architecture-plan-m4.md` | Approved architecture for M4 (QA portfolio evidence). |
| `docs/architecture-plan-m5.md` | Approved architecture for M5 (handover). |
| `docs/qa/` | Test plan, test cases, defect log, and traceability matrix. |
| `docs/HANDOVER.md` | One-page note for the Legal system owner (Intan). Skeleton with headings; Amir writes the prose. |

---

## What is deliberately not built

These are out of scope for the MVP, not oversights:

- **File upload UI.** The `agreement_files` table exists, but there is no UI for attaching documents.
- **Excel / CSV import or export.** Historical data will be entered by hand in M5 rather than imported from a spreadsheet nobody has reviewed.
- **Dashboard analytics.** No charts, reports, or statistics screens.
- **Archive / restore UX.** Archiving is a data state (`archived_at`); there is no dedicated archive workflow.
- **Email / password reset flows.** Password resets are handled by the `user:password` artisan command.
- **Approval workflow engine.** The workflow is tracked by nullable date columns on `agreements`.
- **`spatie/laravel-permission`.** The three-role enum is sufficient.

Known limitations that may bite users:

- The similar-partner warning uses substring matching. It will miss acronym-vs-full-name pairs such as "UiTM" vs "Universiti Teknologi MARA".
- Concurrent edits are unguarded. Two users editing the same agreement at the same time will overwrite each other; last write wins.
- Date inputs use the browser's native date picker, which displays dates in your browser/OS locale (for example, MM/DD/YYYY on some machines). This cannot be overridden by web code. The value stored is always `YYYY-MM-DD`, and the text beneath each input confirms the selected date as `18 Mar 2022` so it can be read unambiguously.
