> # CLSD Agreement Register — Handover Note
>
> **For:** Intan (Legal Executive), confirmed system owner as of 26 Aug 2026  
> **Format:** This must fit on one page. Export to PDF for Intan. (Decision M5-6)
> **Content:** Headings and prompts only — Amir writes all prose. (Decision M5-3)

---

## What this system is

This is the agreement register system, where legal staff can track their agreements conveniently without having to manually check through Excel.

## Who can do what

Admin can manage users — create new accounts, edit or deactivate them.
Regular staff (legal) can enter new agreements and edit existing ones.
Viewer role can only view agreements, and use search and filter — that's it.

## The one rule to know

Agreements marked as "pending" can only be seen by legal staff (admin and regular staff), not by viewers. Pending means the agreement hasn't been vetted or checked by legal yet — so it's kept hidden from viewers until it's ready.

## Getting in

**Live URL**
https://clsd-agreement-register-production-mn9rqh.laravel.cloud

**Accounts**
amir.umar@t.unikl.edu.my — Admin
intan.maisara@unikl.edu.my — Admin

Your login password will be given to you separately (not written in this document).

## Adding a person

1. Log in as an admin user.
2. Click **Users** in the top navigation.
3. Fill in **Name**, **Email**, **Role**, and an **Initial password**.
   - Use **viewer** for project owners who only need to appear in the PIC dropdown.
   - Use **legal** for Legal staff who will create or edit agreements.
   - Use **admin** only for the system owner; it can create and deactivate other accounts.
4. Click **Create user**. The person can now be selected as PIC on an agreement.

To deactivate someone, click **Deactivate** on their row. They disappear from the PIC dropdown, but any agreement already assigned to them keeps their name. You cannot deactivate your own account from the web UI; if you are locked out, a developer with server access (Amir, or whoever provides technical support going forward) must run `php artisan user:create` to make a new admin.

## What it does not do

This system does not currently:
- Store the actual signed PDF documents — you'll still need to keep those separately
- Automatically import the old Excel records — anything in the system was entered manually
- Send email reminders when something needs attention — you'll need to check the system yourself
- Let people sign up on their own — only an admin can create new accounts

## What is known to be imperfect

A couple of small things to be aware of — these are known, not something you did wrong:

- **The "similar partner" warning isn't perfect.** It catches some duplicate partner names (like typing the same name twice), but it won't alwayscatch things like short forms or abbreviations (e.g. "UiTM" won't be flagged against "Universiti Teknologi MARA"). If you're not sure whether a partner already exists, it's worth double-checking the list yourself before adding a new one.

- **If two people edit the same agreement at the exact same time**, the person who saves last will overwrite the other person's changes without a warning. This is rare in practice, but worth keeping in mind — try to avoid editing the same agreement as someone else at the same time.

- **Date boxes may show the day and month in a different order depending on your browser or computer settings.** This is a browser behaviour, not a bug in the system, and it cannot be changed from the web page. The line under each date box always shows the date you picked in a clear format like “18 Mar 2022”, so you can check it before saving.

## When something looks wrong

While Amir is with the Legal Unit, reach out to him directly first.

After Amir leaves, there is no dedicated technical support arranged yet for this system. If something needs fixing, this should be raised with[your supervisor / department head] to decide the right next step —whether that's engaging UniKL's IT department formally, or anotherarrangement

## Where the technical detail lives

## Where the technical detail lives

- **GitHub repository** — https://github.com/audanial/clsd-agreement-register — the full source code and history.
- **README.md** — how to set up and run the project.
- **AGENTS.md** — the reasoning behind key decisions (why things were built a certain way).
- **docs/BUILD_PLAN.md** — the overall project roadmap and what was intentionally left out.
- **docs/architecture-plan*.md** — detailed planning notes for each phase of development.
- **docs/qa/** — what has been tested, what hasn't, and known issues.
- **tests/** — the automated tests; running them shows what the system is expected to do.
