> # CLSD Agreement Register — Handover Note
>
> **For:** Intan (Legal Executive), confirmed system owner as of 26 Aug 2026  
> **Format:** This must fit on one page. Export to PDF for Intan. (Decision M5-6)
> **Content:** Headings and prompts only — Amir writes all prose. (Decision M5-3)

---

## What this system is

> Answers: "What am I looking at?"

## Who can do what

> Answers: "What can I do, and what can my colleague do?"

## The one rule to know

> Answers: "Why can't my colleague see that agreement?"

## Getting in

> Answers: "What's the address and do I have an account?"

## Adding a person

> Answers: "A new project owner joined — what do I do?"

1. Log in as an admin user.
2. Click **Users** in the top navigation.
3. Fill in **Name**, **Email**, **Role**, and an **Initial password**.
   - Use **viewer** for project owners who only need to appear in the PIC dropdown.
   - Use **legal** for Legal staff who will create or edit agreements.
   - Use **admin** only for the system owner; it can create and deactivate other accounts.
4. Click **Create user**. The person can now be selected as PIC on an agreement.

To deactivate someone, click **Deactivate** on their row. They disappear from the PIC dropdown, but any agreement already assigned to them keeps their name. You cannot deactivate your own account from the web UI; if you are locked out, a developer with server access must run `php artisan user:create` to make a new admin.

## What it does not do

> Answers: "Can it email reminders / hold the signed PDFs / import the old spreadsheet?"

## What is known to be imperfect

> Answers: "Is this a bug or is it me?"

## When something looks wrong

> Answers: "Who do I contact?"

## Where the technical detail lives

> For whoever comes after, not for Intan.
