> # CLSD Agreement Register — Handover Note
>
> **For:** Intan (Legal Executive), confirmed system owner as of 26 Aug 2026  
> **Format:** This must fit on one page. Export to PDF for Intan. (Decision M5-6)
> **Content:** Written and maintained by Amir. (Decision M5-3)

---

## What this system is

This is the agreement register system, where legal staff can track their agreements conveniently without having to manually check through Excel.
It also has a Legal Submission Portal where Requesting Staff can send new agreement requests to Legal.

## Who can do what

Admin can create, deactivate and activate user accounts, as well as do Legal work.
Regular Legal staff can enter and edit agreements and view the shared submission queue. The queue is read-only for now, so Legal cannot take review actions there yet.
Viewer can search and read the Agreement Register but cannot change agreements or use the Submission Portal.
Requesting Staff can submit agreement requests and see only their own submissions. They can also read the Agreement Register but cannot change agreements.

## The one rule to know

Agreements marked as "pending" can only be seen by Legal staff and Admin, not by Viewer or Requesting Staff. Pending means the agreement is still being checked by Legal, so it stays hidden until it is ready.

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
    - Use **viewer** for someone who only needs to read the Agreement Register.
    - Use **Requesting Staff** for someone who needs to submit agreement requests to Legal.
    - Use **legal** for Legal staff who will create or edit agreements.
    - Use **admin** only for the system owner; it can create and deactivate other accounts.
4. Click **Create user**.

A PIC does not need a user account. Legal can choose an existing PIC name or type a new name directly on the agreement form.

To deactivate someone's account, click **Deactivate** on their row. Their access ends on their next request, and an old Remember me login will not restore it. To give them access again, click **Activate**. You cannot deactivate your own account from the web UI. If the Admin accounts become unusable, a developer with server access must run `php artisan user:create` to create a new Admin account.

## What it does not do

This system does not currently:
- Store the actual signed PDF documents — you'll still need to keep those separately
- Automatically import the old Excel records — anything in the system was entered manually
- Send email reminders when something needs attention — you'll need to check the system yourself
- Let people sign up on their own — only an admin can create new accounts
- Accept document attachments or let Legal request revisions, change submission status or send messages through the Submission Portal

## What is known to be imperfect

A few known limitations to keep in mind:

- The similar-partner warning may miss abbreviated names. Check the partner list before adding a new one.
- If two people edit the same agreement, the last save wins. Coordinate with each other before editing.
- Date boxes follow your browser’s format. Check the clearly written date beneath each box before saving.

## When something looks wrong

While Amir is with the Legal Unit, reach out to him directly first.

After Amir leaves the Legal Unit, report any problem to your supervisor or department head. They can decide whether to ask UniKL IT for help or arrange other technical support.

## Where the technical detail lives

The source code, setup instructions and test records are available at https://github.com/audanial/clsd-agreement-register.
