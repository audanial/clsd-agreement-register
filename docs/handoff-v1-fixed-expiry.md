# V1 mockup review correction — fixed expiry

> **Date:** 28 Sep 2026
> **Status:** Implemented and verified locally; commit, deployment, and production walkthrough pending.
> **Source:** Intan and Siti's V1 workflow-mockup review, confirmed by Amir.

## Decision

Every newly created Agreement Register record requires a fixed **Expiry Date**. The labels remain
**Date Signed** and **Expiry Date**. Do not offer a **No fixed expiry** checkbox.

This is a creation rule, not a destructive historical-data rewrite:

- new manual and future guided Register records require an expiry date;
- an existing dated record cannot have its expiry cleared;
- an existing historical record whose expiry is already null remains editable and continues to
  mean indefinite/until completion;
- the database column remains nullable for those historical rows; and
- an expiry date in the past is valid historical input and follows the normal archive rules.

## Implementation

- `resources/views/livewire/agreement-form.blade.php` conditionally requires `expiry_date`: it is
  required for creation and for edits of records that already have a date, but remains nullable
  when editing a legacy-null record.
- The create form labels the field **Expiry Date * (DD/MM/YYYY)** and contains no indefinite option.
- Existing successful form tests now supply a fixed expiry date.
- QA documentation distinguishes the preserved historical-null semantics from the new creation
  requirement through BR-54 and TC-094.
- LP6 guided Register creation remains responsible for applying the same rule when implemented.

## Verification

- Test-first evidence: `test_new_agreement_requires_an_expiry_date` failed because the component
  had no expiry error, then passed after the conditional validation rule was added.
- Test-first evidence: `test_an_existing_expiry_date_cannot_be_cleared` failed because the
  component had no expiry error, then passed after dated edits were protected.
- Test-first evidence: the required-label test failed against the old label, then passed after the
  label was updated. Its first implementation exposed a Blade parse error; replacing the inline
  directive with a Blade echo expression resolved the root cause.
- Focused Agreement form suite: **43 tests, 115 assertions passed**.
- Full suite: **360 tests, 1,231 assertions passed**.
- Pint on every changed PHP and Blade file: **passed**.

## Release check

After deployment, Amir should open the Create Agreement form with fictional values, leave Expiry
Date empty, confirm that submission is blocked, and then cancel without creating a record. If a
dedicated production test agreement already exists, use it to confirm that a dated record cannot
have its Expiry Date cleared. Otherwise, do not create permanent test data or change real
historical data; the automated tests cover past-date acceptance and the edit paths.
