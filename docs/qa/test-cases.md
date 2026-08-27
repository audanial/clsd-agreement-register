> # CLSD Agreement Register — Test Cases
>
> **Milestone:** M4 (QA Portfolio Evidence)  
> **Organisation:** By business-rule area, not by test file. Several automated tests may collapse into one case. Cases with no automated backing are marked **Manual only** with a reason.

---

## 1. Authentication & Session

### TC-001 — Role helpers and mass assignment behave correctly

| | |
|---|---|
| **Feature area** | Authentication & Session |
| **Business rule** | BR-01 — Users have exactly one of three roles: `admin`, `legal`, or `viewer` |
| **Priority** | High |
| **Type** | Automated |
| **Preconditions** | None; tests use the `User` factory |

**Steps**
1. Create users with `role = admin`, `role = legal`, and `role = viewer`.
2. Call `isAdmin()`, `isLegal()`, and `isViewer()` on each.
3. Attempt to mass-assign `role` and `is_active` during creation.

**Expected result**
- Role helpers return `true` only for the matching role.
- `role` and `is_active` are both mass-assignable.

**Automated by**
`UserRoleTest::test_role_helpers_reflect_the_role_column`  
`UserRoleTest::test_role_is_mass_assignable`  
`UserRoleTest::test_is_active_is_mass_assignable_and_cast_to_boolean`

**Status** Pass (24 Aug 2026)

---

### TC-002 — Only active users can log in

| | |
|---|---|
| **Feature area** | Authentication & Session |
| **Business rule** | BR-02 — Only active users (`is_active = true`) can log in |
| **Priority** | Critical |
| **Type** | Automated |
| **Preconditions** | One active user and one inactive user exist |

**Steps**
1. Submit valid credentials for the active user.
2. Submit valid credentials for the inactive user.

**Expected result**
Active user logs in; inactive user is rejected with the generic `auth.failed` message.

**Automated by**
`LoginTest::test_user_can_login_with_valid_credentials`  
`LoginTest::test_inactive_user_cannot_login`

**Status** Pass (24 Aug 2026)

---

### TC-003 — Login failure does not reveal whether an email exists

| | |
|---|---|
| **Feature area** | Authentication & Session |
| **Business rule** | BR-02 — Security contract: generic failure message |
| **Priority** | High |
| **Type** | Automated |
| **Preconditions** | One valid user exists |

**Steps**
1. Submit a known email with a wrong password.
2. Submit an unknown email with any password.

**Expected result**
Both attempts return the same single-field error message; no indication of which email exists.

**Automated by**
`LoginTest::test_login_error_does_not_reveal_whether_the_email_exists`

**Status** Pass (24 Aug 2026)

---

### TC-004 — Session is regenerated on login and invalidated on logout

| | |
|---|---|
| **Feature area** | Authentication & Session |
| **Business rule** | BR-02 — Session fixation defence |
| **Priority** | High |
| **Type** | Automated |
| **Preconditions** | A valid user exists |

**Steps**
1. Log in and assert the session ID changed.
2. Log out via POST and assert the session is invalidated.
3. Attempt to access a protected route via GET to `/logout`.

**Expected result**
Session ID changes on login; logout invalidates the session; GET logout is not allowed.

**Automated by**
`LoginTest::test_session_is_regenerated_on_login`  
`LogoutTest::test_authenticated_user_can_logout`  
`LogoutTest::test_logout_is_not_reachable_via_get`

**Status** Pass (24 Aug 2026)

---

### TC-005 — Login rate limiting activates after five failed attempts

| | |
|---|---|
| **Feature area** | Authentication & Session |
| **Business rule** | BR-02 — Rate limiting on the login endpoint |
| **Priority** | High |
| **Type** | Automated |
| **Preconditions** | A valid user exists |

**Steps**
1. Submit five failed login attempts.
2. Submit a sixth failed attempt.

**Expected result**
The sixth attempt returns HTTP 429 Too Many Requests.

**Automated by**
`LoginTest::test_login_is_rate_limited_after_five_failed_attempts`

**Status** Pass (24 Aug 2026)

---

### TC-006 — Registration route is not available

| | |
|---|---|
| **Feature area** | Authentication & Session |
| **Business rule** | BR-02 — No public registration; admin creates users |
| **Priority** | High |
| **Type** | Automated |
| **Preconditions** | Application is running |

**Steps**
1. Send `GET /register`.

**Expected result**
HTTP 404 Not Found.

**Automated by**
`RegistrationDisabledTest::test_registration_route_does_not_exist`

**Status** Pass (24 Aug 2026)

---

## 2. Roles & Authorization

### TC-007 — Role middleware enforces admin-only routes

| | |
|---|---|
| **Feature area** | Roles & Authorization |
| **Business rule** | BR-01 — Role-based access control |
| **Priority** | High |
| **Type** | Automated |
| **Preconditions** | Admin, legal, and viewer users exist |

**Steps**
1. As admin, access an admin-only route.
2. As legal, access the same route.
3. As viewer, access the same route.

**Expected result**
Admin succeeds; legal and viewer receive HTTP 403.

**Automated by**
`RoleMiddlewareTest::test_admin_can_access_an_admin_only_route`  
`RoleMiddlewareTest::test_legal_is_forbidden_from_an_admin_only_route`  
`RoleMiddlewareTest::test_viewer_is_forbidden_from_an_admin_only_route`

**Status** Pass (24 Aug 2026)

---

### TC-008 — Legal and viewer can both reach the dashboard

| | |
|---|---|
| **Feature area** | Roles & Authorization |
| **Business rule** | BR-01 — Dashboard is accessible to all authenticated roles |
| **Priority** | Medium |
| **Type** | Automated |
| **Preconditions** | Legal and viewer users exist |

**Steps**
1. Log in as legal and request `/dashboard`.
2. Log in as viewer and request `/dashboard`.

**Expected result**
Both requests return HTTP 200.

**Automated by**
`RoleMiddlewareTest::test_legal_and_viewer_can_both_reach_the_dashboard`

**Status** Pass (24 Aug 2026)

---

### TC-009 — Guests are redirected to login from protected routes

| | |
|---|---|
| **Feature area** | Roles & Authorization |
| **Business rule** | BR-02 — Unauthenticated users must not reach protected pages |
| **Priority** | Critical |
| **Type** | Automated |
| **Preconditions** | No authenticated session |

**Steps**
1. Request `/dashboard` as a guest.
2. Request `/agreements` as a guest.

**Expected result**
Both requests redirect to `/login` (not a blank 401).

**Automated by**
`LoginTest::test_dashboard_redirects_guests_to_login`  
`RoleMiddlewareTest::test_guests_are_redirected_to_login`  
`AgreementsIndexTest::test_guests_are_redirected_to_login`  
`AgreementAccessControlTest::test_guests_are_redirected_from_every_agreement_route`

**Status** Pass (24 Aug 2026)

---

### TC-010 — Admin CLI commands manage users correctly

| | |
|---|---|
| **Feature area** | Roles & Authorization |
| **Business rule** | BR-01 / BR-02 — User creation and password reset are command-driven |
| **Priority** | High |
| **Type** | Automated |
| **Preconditions** | None; commands are auto-discovered |

**Steps**
1. Run `user:create` with a valid role and email.
2. Run `user:create` with an invalid role.
3. Run `user:create` with a duplicate email.
4. Run `user:password` for an existing user; log in with the new password.
5. Run `user:password` for an unknown email.

**Expected result**
Valid creation succeeds; invalid role is rejected; duplicate email is rejected; password reset works and invalidates the old password; unknown email fails cleanly.

**Automated by**
`CreateUserCommandTest::test_command_creates_a_user_with_the_given_role`  
`CreateUserCommandTest::test_command_rejects_an_invalid_role`  
`CreateUserCommandTest::test_command_rejects_a_duplicate_email`  
`ResetUserPasswordCommandTest::test_command_changes_the_password_and_the_user_can_login_with_it`  
`ResetUserPasswordCommandTest::test_command_fails_for_an_unknown_email`  
`ResetUserPasswordCommandTest::test_old_password_no_longer_works_after_a_reset`

**Status** Pass (24 Aug 2026)

---

## 3. Pending Visibility

### TC-011 — Viewer cannot see pending agreements

| | |
|---|---|
| **Feature area** | Pending Visibility |
| **Business rule** | BR-03 — `document_status = pending` is never visible to non-Legal users |
| **Priority** | Critical |
| **Type** | Automated |
| **Preconditions** | One pending and two signed agreements exist |

**Steps**
1. Log in as a viewer.
2. Query `Agreement::count()` and list agreements.

**Expected result**
Count is 2; the pending agreement's ID is absent from the collection.

**Automated by**
`PendingVisibilityTest::test_viewer_cannot_see_pending_agreements`

**Status** Pass (24 Aug 2026)

---

### TC-012 — Legal and admin can see pending agreements

| | |
|---|---|
| **Feature area** | Pending Visibility |
| **Business rule** | BR-03 — `pending` is visible to admin and legal |
| **Priority** | Critical |
| **Type** | Automated |
| **Preconditions** | One pending and two signed agreements exist |

**Steps**
1. Log in as legal; assert count is 3 and the pending row is present.
2. Log in as admin; assert count is 3 and the pending row is present.

**Automated by**
`PendingVisibilityTest::test_legal_can_see_pending_agreements`  
`PendingVisibilityTest::test_admin_can_see_pending_agreements`

**Status** Pass (24 Aug 2026)

---

### TC-013 — Viewer cannot reach a pending agreement by URL or relation

| | |
|---|---|
| **Feature area** | Pending Visibility |
| **Business rule** | BR-03 / BR-20 — Global scope applies to binding and relations |
| **Priority** | Critical |
| **Type** | Automated |
| **Preconditions** | A pending agreement exists with a known campus and partner |

**Steps**
1. As viewer, call `Agreement::findOrFail($pendingId)`.
2. As viewer, load the agreement's campus and partner and inspect their `agreements` relations.
3. As viewer, navigate directly to `/agreements/{pendingId}`.

**Expected result**
`findOrFail` throws `ModelNotFoundException`; relations exclude the pending row; direct URL returns 404.

**Automated by**
`PendingVisibilityTest::test_viewer_gets_model_not_found_for_a_pending_agreement_by_id`  
`PendingVisibilityTest::test_viewer_cannot_reach_pending_agreements_through_a_relation`  
`AgreementShowTest::test_viewer_gets_404_for_a_pending_agreement`

**Status** Pass (24 Aug 2026)

---

### TC-014 — Pending rows are excluded from relation counts for a viewer

| | |
|---|---|
| **Feature area** | Pending Visibility |
| **Business rule** | BR-03 — Scope applies to `withCount` and aggregates |
| **Priority** | High |
| **Type** | Automated |
| **Preconditions** | A campus has one pending and one signed agreement |

**Steps**
1. As viewer, load the campus with `withCount('agreements')`.

**Expected result**
The count is 1, not 2.

**Automated by**
`PendingVisibilityTest::test_pending_rows_are_excluded_from_relation_counts_for_a_viewer`

**Status** Pass (24 Aug 2026)

---

### TC-015 — Unauthenticated context and explicit scope removal

| | |
|---|---|
| **Feature area** | Pending Visibility |
| **Business rule** | BR-03 — Scope is auth-aware; `withoutGlobalScope` proves the mechanism |
| **Priority** | Medium |
| **Type** | Automated |
| **Preconditions** | Pending agreements exist |

**Steps**
1. Without `actingAs`, query `Agreement::count()`.
2. Without `actingAs`, query `Agreement::withoutGlobalScope(HidePendingFromNonLegalScope::class)->count()`.

**Expected result**
Unauthenticated query sees all rows; explicit scope removal reveals pending rows.

**Automated by**
`PendingVisibilityTest::test_unauthenticated_context_is_not_scoped`  
`PendingVisibilityTest::test_without_global_scope_reveals_pending_rows`

**Status** Pass (24 Aug 2026)

---

### TC-016 — Pending is not offered as a status filter to a viewer

| | |
|---|---|
| **Feature area** | Pending Visibility |
| **Business rule** | BR-22 — Filter options are role-aware |
| **Priority** | Medium |
| **Type** | Automated |
| **Preconditions** | A viewer user exists |

**Steps**
1. Log in as viewer.
2. Render the agreements index.

**Expected result**
The document-status filter does not contain a `pending` option or the word "Pending".

**Automated by**
`AgreementsIndexTest::test_pending_is_not_offered_as_a_status_filter_to_a_viewer`

**Status** Pass (24 Aug 2026)

---

## 4. Agreement Creation & Validation

### TC-017 — Legal and admin can create a valid agreement

| | |
|---|---|
| **Feature area** | Agreement Creation & Validation |
| **Business rule** | BR-04 / BR-21 — Valid users can create agreements; TBD campus is available |
| **Priority** | Critical |
| **Type** | Automated |
| **Preconditions** | A partner and the TBD campus exist |

**Steps**
1. Log in as legal and submit the create form with all required fields.
2. Log in as admin and submit the create form with a signed status.

**Expected result**
Both agreements are persisted; redirect goes to the new agreement's detail page.

**Automated by**
`AgreementFormTest::test_legal_can_create_an_agreement`  
`AgreementFormTest::test_admin_can_create_an_agreement`

**Status** Pass (24 Aug 2026)

---

### TC-018 — Required fields and allowed-value rules are enforced

| | |
|---|---|
| **Feature area** | Agreement Creation & Validation |
| **Business rule** | BR-07 — Fields are validated; `expired` is not writable |
| **Priority** | High |
| **Type** | Automated |
| **Preconditions** | A legal user is logged in |

**Steps**
1. Submit an empty form.
2. Submit `type = INVALID`.
3. Submit `sector = other`.
4. Submit `document_status = expired`.

**Expected result**
Validation errors for the relevant fields in each case.

**Automated by**
`AgreementFormTest::test_required_fields_are_enforced`  
`AgreementFormTest::test_type_must_be_one_of_the_allowed_values`  
`AgreementFormTest::test_sector_must_be_academic_or_industri`  
`AgreementFormTest::test_expired_is_not_an_option_for_document_status`

**Status** Pass (24 Aug 2026)

---

### TC-019 — Existing agreement can be edited

| | |
|---|---|
| **Feature area** | Agreement Creation & Validation |
| **Business rule** | BR-21 — Legal/admin may edit agreements |
| **Priority** | High |
| **Type** | Automated |
| **Preconditions** | An agreement exists |

**Steps**
1. Log in as legal.
2. Open the edit form for an existing agreement.
3. Change the title and save.

**Expected result**
The agreement is updated in the database; the user is redirected.

**Automated by**
`AgreementFormTest::test_legal_can_edit_an_existing_agreement`

**Status** Pass (24 Aug 2026)

---

### TC-020 — Partner quick-create creates and links a partner

| | |
|---|---|
| **Feature area** | Agreement Creation & Validation |
| **Business rule** | BR-10 — Partners can be created inline; `country_id` is optional |
| **Priority** | High |
| **Type** | Automated |
| **Preconditions** | A legal user is logged in; at least one country exists |

**Steps**
1. Select "New partner" mode.
2. Enter a legal name, short name, and optionally a country.
3. Save the agreement.

**Expected result**
A new partner row is created and linked to the new agreement.

**Automated by**
`AgreementFormTest::test_partner_quick_create_creates_a_partner_and_links_it`

**Status** Pass (24 Aug 2026)

---

### TC-021 — Partner quick-create requires a name

| | |
|---|---|
| **Feature area** | Agreement Creation & Validation |
| **Business rule** | BR-10 — New partner name is required |
| **Priority** | Medium |
| **Type** | Automated |
| **Preconditions** | A legal user is logged in |

**Steps**
1. Select "New partner" mode.
2. Leave the name empty and attempt to save.

**Expected result**
Validation error on `newPartnerName`.

**Automated by**
`AgreementFormTest::test_partner_quick_create_requires_a_name`

**Status** Pass (24 Aug 2026)

---

### TC-022 — Similar-partner warning surfaces near-matches and does not block save

| | |
|---|---|
| **Feature area** | Agreement Creation & Validation |
| **Business rule** | BR-10 — Warning is advisory, non-blocking |
| **Priority** | High |
| **Type** | Automated |
| **Preconditions** | A partner named "Universiti Teknologi MARA" exists |

**Steps**
1. Start creating a new partner named "Teknologi MARA".
2. Observe the warning.
3. Save anyway.

**Expected result**
The existing partner is listed in the warning; the new partner "Teknologi MARA" is still created.

**Automated by**
`AgreementFormTest::test_similar_partner_name_shows_a_warning`  
`AgreementFormTest::test_similar_partner_warning_does_not_block_creation`

**Status** Pass (24 Aug 2026)

---

### TC-023 — Acronym partner name does not trigger the similar-partner warning

| | |
|---|---|
| **Feature area** | Agreement Creation & Validation |
| **Business rule** | BR-10 — Known limitation of the current matcher (DEF-003) |
| **Priority** | Medium |
| **Type** | Automated (characterisation) |
| **Preconditions** | A partner named "Universiti Teknologi MARA" exists |

**Steps**
1. Start creating a new partner named "UiTM".

**Expected result**
No warning is shown. *This pins current behaviour per DEF-003; the test is expected to fail if M5 improves the matcher.*

**Automated by**
`AgreementFormTest::test_acronym_partner_name_does_not_trigger_similar_warning`

**Status** Pass (24 Aug 2026)

---

### TC-024 — Very short partner names produce no similar-partner warning

| | |
|---|---|
| **Feature area** | Agreement Creation & Validation |
| **Business rule** | BR-10 — Deliberate `< 3` character floor |
| **Priority** | Low |
| **Type** | Automated (boundary) |
| **Preconditions** | A partner with a name longer than two characters exists |

**Steps**
1. Open the create form in "new partner" mode.
2. Type a one-character name and inspect `similarPartners()`.
3. Type a two-character name and inspect `similarPartners()`.
4. Type a three-character substring of the existing partner's name and inspect `similarPartners()`.

**Expected result**
- One- and two-character inputs return an empty collection and do not render the warning.
- The three-character input returns the matching partner and renders the warning.

**Automated by**
`BoundaryConditionsTest::test_similar_partners_returns_empty_for_short_input`

**Status** Pass (27 Aug 2026)

---

### TC-025 — PIC and campus dropdowns list only active entries

| | |
|---|---|
| **Feature area** | Agreement Creation & Validation |
| **Business rule** | BR-12 — Only active campuses and active users appear in dropdowns |
| **Priority** | Medium |
| **Type** | Automated |
| **Preconditions** | One active user, one inactive user, one active campus, one inactive campus exist |

**Steps**
1. Open the create form.
2. Inspect the PIC and campus dropdowns.

**Expected result**
Active entries are present; inactive entries are absent.

**Automated by**
`AgreementFormTest::test_pic_dropdown_only_lists_active_users`  
`AgreementFormTest::test_campus_dropdown_only_lists_active_campuses`

**Status** Pass (24 Aug 2026)

---

### TC-026 — Edit form retains an inactive PIC already assigned to the agreement

| | |
|---|---|
| **Feature area** | Agreement Creation & Validation |
| **Business rule** | BR-11 — Inactive PIC remains visible in edit form if already assigned |
| **Priority** | Medium |
| **Type** | Automated |
| **Preconditions** | An agreement is assigned to an inactive user |

**Steps**
1. Open the edit form for that agreement.

**Expected result**
The inactive PIC's name is still shown/selected.

**Automated by**
`AgreementFormTest::test_edit_form_retains_an_inactive_pic_already_assigned`

**Status** Pass (24 Aug 2026)

---

## 5. Dates & Expiry Semantics

### TC-027 — Date columns cast correctly and archived_at is guarded

| | |
|---|---|
| **Feature area** | Dates & Expiry Semantics |
| **Business rule** | BR-18 — Model casts match schema; `archived_at` is not mass-assignable |
| **Priority** | High |
| **Type** | Automated |
| **Preconditions** | None; tests instantiate an `Agreement` |

**Steps**
1. Set every date column and assert each is a `Carbon` instance.
2. Set `project_status_updated_at` and `archived_at` and assert `Carbon` with time.
3. Attempt to mass-assign `archived_at`.

**Expected result**
Date columns cast to `Carbon`; datetime columns include time; `archived_at` is not mass-assignable.

**Automated by**
`AgreementCastsTest::test_all_date_columns_cast_to_carbon`  
`AgreementCastsTest::test_project_status_updated_at_and_archived_at_cast_to_datetime`  
`AgreementCastsTest::test_archived_at_is_not_mass_assignable`

**Status** Pass (24 Aug 2026)

---

### TC-028 — Null expiry date stays null and means indefinite

| | |
|---|---|
| **Feature area** | Dates & Expiry Semantics |
| **Business rule** | BR-05 — `expiry_date = null` means indefinite |
| **Priority** | High |
| **Type** | Automated |
| **Preconditions** | A legal user is logged in |

**Steps**
1. Create an agreement with `expiry_date` left empty.
2. Inspect the saved row and the detail page.

**Expected result**
Database stores `NULL`; detail page shows "Indefinite", not a blank or default date.

**Automated by**
`AgreementCastsTest::test_null_expiry_date_stays_null_and_is_indefinite`  
`AgreementFormTest::test_empty_expiry_date_persists_as_null`  
`AgreementShowTest::test_indefinite_expiry_renders_as_indefinite_not_blank`

**Status** Pass (24 Aug 2026)

---

### TC-029 — Year is derived from agreement_date

| | |
|---|---|
| **Feature area** | Dates & Expiry Semantics |
| **Business rule** | BR-16 — `year` is derived from `agreement_date` |
| **Priority** | Medium |
| **Type** | Automated |
| **Preconditions** | None |

**Steps**
1. Create an agreement with `agreement_date = 2024-03-15`.
2. Read the `year` attribute.
3. Create an agreement with `agreement_date = null`.

**Expected result**
`year` is 2024 when a date is present and `null` when it is absent.

**Automated by**
`AgreementCastsTest::test_year_is_derived_from_agreement_date_and_is_null_when_the_date_is_null`

**Status** Pass (24 Aug 2026)

---

### TC-030 — Expiry before effective date warns but still saves

| | |
|---|---|
| **Feature area** | Dates & Expiry Semantics |
| **Business rule** | BR-09 — Soft warning, not a hard rule |
| **Priority** | High |
| **Type** | Automated |
| **Preconditions** | A legal user is logged in; a partner and campus exist |

**Steps**
1. Enter `effective_date = 2026-01-15` and `expiry_date = 2026-01-01`.
2. Assert the warning text is shown before saving.
3. Save the form.

**Expected result**
Warning text reads "Expiry date is earlier than the effective date. Save anyway if that matches the document."; the agreement is saved and the user is redirected.

**Automated by**
`AgreementFormTest::test_expiry_before_effective_shows_a_warning_but_still_saves`

**Status** Pass (24 Aug 2026)

---

### TC-031 — No date warning when only one date is present

| | |
|---|---|
| **Feature area** | Dates & Expiry Semantics |
| **Business rule** | BR-09 — Warning requires both dates |
| **Priority** | Medium |
| **Type** | Automated |
| **Preconditions** | A legal user is logged in; a partner and campus exist |

**Steps**
1. Enter only `effective_date`.
2. Assert `dateWarning` is null.

**Expected result**
No warning is shown; the form still saves.

**Automated by**
`AgreementFormTest::test_no_warning_when_only_one_date_is_present`

**Status** Pass (24 Aug 2026)

---

### TC-032 — Expiring-soon and expired scopes exclude null expiry

| | |
|---|---|
| **Feature area** | Dates & Expiry Semantics |
| **Business rule** | BR-06 / BR-18 — Null expiry is indefinite, not expiring or expired |
| **Priority** | High |
| **Type** | Automated |
| **Preconditions** | Agreements with null, future, expiring-soon, and past expiry dates exist |

**Steps**
1. Call `Agreement::expiringSoon()->count()`.
2. Call `Agreement::expired()->count()`.

**Expected result**
Neither scope includes the null-expiry agreement.

**Automated by**
`AgreementScopesTest::test_expiring_soon_excludes_agreements_with_a_null_expiry_date`  
`AgreementScopesTest::test_expired_excludes_agreements_with_a_null_expiry_date`  
`AgreementScopesTest::test_expiring_soon_excludes_already_expired_agreements`

**Status** Pass (24 Aug 2026)

---

### TC-033 — Expiry-today behaviour is recorded

| | |
|---|---|
| **Feature area** | Dates & Expiry Semantics |
| **Business rule** | BR-05 / BR-06 — Agreement expires at the start of its expiry date (M5-8) |
| **Priority** | Medium |
| **Type** | Automated (characterisation) |
| **Preconditions** | Agreements with `expiry_date = today()` and `expiry_date = tomorrow()` exist |

**Steps**
1. Call `isExpired()` on each.
2. Call `Agreement::expired()->exists()` for each.
3. Call `Agreement::expiringSoon()->exists()` for each.

**Expected result**
- For today: all three paths report **expired**.
- For tomorrow: `isExpired()` and `expired()` are `false`; `expiringSoon()` is `true`.

**Automated by**
`BoundaryConditionsTest::test_expiry_today_behaviour_is_consistently_recorded`

**Status** Pass (27 Aug 2026)

---

## 6. Partner Management

### TC-034 — Partner, campus, PIC, files and activities relations resolve

| | |
|---|---|
| **Feature area** | Partner Management |
| **Business rule** | BR-18 — Model relations are wired correctly |
| **Priority** | High |
| **Type** | Automated |
| **Preconditions** | An agreement with related records exists |

**Steps**
1. Access `$agreement->partner`, `$agreement->campus`, `$agreement->pic`, `$agreement->files`, `$agreement->activities`.

**Expected result**
All relations return the expected models or collections; `pic` resolves via `pic_user_id`.

**Automated by**
`AgreementRelationsTest::test_partner_campus_and_pic_relations_resolve`  
`AgreementRelationsTest::test_pic_relation_uses_the_pic_user_id_column`  
`AgreementRelationsTest::test_files_and_activities_relations_resolve`

**Status** Pass (24 Aug 2026)

---

### TC-035 — PIC and partner may be null

| | |
|---|---|
| **Feature area** | Partner Management |
| **Business rule** | BR-11 — Historical rows may have no PIC |
| **Priority** | Medium |
| **Type** | Automated |
| **Preconditions** | An agreement with `pic_user_id = null` and `partner_id = null` exists |

**Steps**
1. Access `$agreement->pic` and `$agreement->partner`.

**Expected result**
Both return `null` without error.

**Automated by**
`AgreementRelationsTest::test_pic_may_be_null`

**Status** Pass (24 Aug 2026)

---

### TC-036 — Agreement with null PIC renders on list and detail

| | |
|---|---|
| **Feature area** | Partner Management |
| **Business rule** | BR-11 — Historical rows may have no PIC; rendering must not break |
| **Priority** | Medium |
| **Type** | Automated |
| **Preconditions** | An agreement with `pic_user_id = null` exists |

**Steps**
1. Render the agreement detail page.
2. Render the agreements list.

**Expected result**
- The detail page renders successfully and shows `—` in the PIC line.
- The list row renders successfully and still shows the agreement.

**Automated by**
`BoundaryConditionsTest::test_null_pic_renders_as_em_dash_and_row_still_renders`

**Status** Pass (27 Aug 2026)

---

### TC-037 — Country seeder is idempotent and Malaysia is the only domestic country

| | |
|---|---|
| **Feature area** | Partner Management |
| **Business rule** | BR-17 — `countries.is_domestic` is seeded correctly |
| **Priority** | Medium |
| **Type** | Automated |
| **Preconditions** | Fresh database |

**Steps**
1. Run `CountrySeeder` twice.
2. Assert only one row per country.
3. Assert Malaysia is the only country with `is_domestic = true`.

**Automated by**
`CountrySeederTest::test_seeder_is_idempotent_when_run_twice`  
`CountrySeederTest::test_malaysia_is_the_only_domestic_country`

**Status** Pass (24 Aug 2026)

---

## 7. Project Status & Staleness

### TC-038 — Staleness predicate is true past the threshold and when never set

| | |
|---|---|
| **Feature area** | Project Status & Staleness |
| **Business rule** | BR-23 — `hasStaleProjectStatus()` reflects the threshold and the null case |
| **Priority** | High |
| **Type** | Automated |
| **Preconditions** | Agreements with old, recent, and null `project_status_updated_at` exist |

**Steps**
1. Call `hasStaleProjectStatus()` on each.

**Expected result**
Old and null timestamps return `true`; a recent timestamp returns `false`.

**Automated by**
`AgreementCastsTest::test_has_stale_project_status_is_true_past_the_threshold_and_when_never_set`

**Status** Pass (24 Aug 2026)

---

### TC-039 — Staleness boundary at exactly STALE_AFTER_DAYS days

| | |
|---|---|
| **Feature area** | Project Status & Staleness |
| **Business rule** | BR-23 — `hasStaleProjectStatus()` crosses the boundary at the start of the day (M5-8; DEF-008) |
| **Priority** | Medium |
| **Type** | Automated (boundary) |
| **Preconditions** | None; derive days from `Agreement::STALE_AFTER_DAYS` |

**Steps**
1. Create agreements with `project_status_updated_at` at 89, exactly 90, and 91 days ago.
2. Call `hasStaleProjectStatus()` on each.

**Expected result**
89 days → `false`; exactly 90 days → `true`; 91 days → `true`.

**Automated by**
`BoundaryConditionsTest::test_has_stale_project_status_is_true_past_the_boundary`

**Status** Pass (27 Aug 2026)

---

### TC-040 — Stale badge renders on detail and list for stale rows only

| | |
|---|---|
| **Feature area** | Project Status & Staleness |
| **Business rule** | BR-23 — Badge renders when `hasStaleProjectStatus()` is true |
| **Priority** | High |
| **Type** | Automated |
| **Preconditions** | One fresh and one stale agreement exist |

**Steps**
1. Render the list and assert the stale row shows the badge title text ("Project status last updated..." / "Project status never updated") and the fresh row does not.
2. Render the detail page for the stale agreement and assert the badge is present.

**Expected result**
Badge appears only for stale rows; titles cannot masquerade as badges.

**Automated by**
`AgreementsIndexTest::test_stale_badge_is_shown_only_for_stale_rows`  
`AgreementShowTest::test_stale_badge_appears_on_detail_for_a_stale_agreement`  
`AgreementShowTest::test_never_updated_project_status_renders_as_stale`

**Status** Pass (24 Aug 2026)

---

### TC-041 — Editing an unrelated field does not clear the stale badge

| | |
|---|---|
| **Feature area** | Project Status & Staleness |
| **Business rule** | BR-08 — `project_status_updated_at` stamps only when `project_status` changes |
| **Priority** | High |
| **Type** | Automated |
| **Preconditions** | A stale agreement exists |

**Steps**
1. Edit only the notes field and save.

**Expected result**
`project_status_updated_at` is unchanged; the stale badge remains.

**Automated by**
`AgreementFormTest::test_saving_without_changing_project_status_does_not_restamp`

**Status** Pass (24 Aug 2026)

---

## 8. Activity Logging

### TC-042 — Creating an agreement writes a created activity

| | |
|---|---|
| **Feature area** | Activity Logging |
| **Business rule** | BR-13 — `created` row is written on creation |
| **Priority** | High |
| **Type** | Automated |
| **Preconditions** | A legal user is logged in |

**Steps**
1. Create a valid agreement.

**Expected result**
Exactly one `AgreementActivity` row of type `created` exists for the agreement.

**Automated by**
`AgreementActivityLogTest::test_creating_an_agreement_writes_a_created_activity`

**Status** Pass (24 Aug 2026)

---

### TC-043 — Document and project status changes write separate status_changed rows

| | |
|---|---|
| **Feature area** | Activity Logging |
| **Business rule** | BR-14 — Status changes are logged with field, from, to, and acting user |
| **Priority** | High |
| **Type** | Automated |
| **Preconditions** | An agreement exists; a legal user is logged in |

**Steps**
1. Change `document_status` and save via the detail page.
2. Change `project_status` and save via the detail page.

**Expected result**
Two separate `status_changed` rows exist, each with correct `meta.field`, `meta.from`, `meta.to`, and `user_id`.

**Automated by**
`AgreementActivityLogTest::test_document_status_change_writes_a_status_changed_activity`  
`AgreementActivityLogTest::test_project_status_change_writes_its_own_activity_row`  
`AgreementActivityLogTest::test_meta_records_the_field_and_the_from_and_to_values`  
`AgreementActivityLogTest::test_activity_records_the_acting_user`

**Status** Pass (24 Aug 2026)

---

### TC-044 — From and to values differ on a real status change

| | |
|---|---|
| **Feature area** | Activity Logging |
| **Business rule** | BR-14 — The audit trail must record the actual transition (DEF-001) |
| **Priority** | Critical |
| **Type** | Automated |
| **Preconditions** | An agreement exists with `document_status = pending` and `project_status = not_started`; a legal user is logged in |

**Steps**
1. Change `document_status` from `pending` to `signed`.
2. Change `project_status` from `not_started` to `ongoing`.

**Expected result**
For each logged change, `meta.from !== meta.to`; descriptions contain the real transitions.

**Automated by**
`AgreementActivityLogTest::test_status_change_activity_records_distinct_from_and_to_values`  
`AgreementActivityLogTest::test_edit_form_status_change_records_distinct_from_and_to_values`

**Status** Pass (24 Aug 2026)

---

### TC-045 — No activity is written when no status changed

| | |
|---|---|
| **Feature area** | Activity Logging |
| **Business rule** | BR-13 — Activity is written only when a value actually changes |
| **Priority** | Medium |
| **Type** | Automated |
| **Preconditions** | An agreement exists; a legal user is logged in |

**Steps**
1. Open the detail page and submit the status form without changing either status.

**Expected result**
No new `status_changed` row is created.

**Automated by**
`AgreementActivityLogTest::test_no_activity_is_written_when_no_status_changed`

**Status** Pass (24 Aug 2026)

---

### TC-046 — Ordinary field edits write no updated activity

| | |
|---|---|
| **Feature area** | Activity Logging |
| **Business rule** | BR-13 — Ordinary edits do not log `updated` rows |
| **Priority** | High |
| **Type** | Automated |
| **Preconditions** | An agreement exists; a legal user is logged in |

**Steps**
1. Edit only the notes field and save.

**Expected result**
No `updated` activity row is created; the count of `status_changed` rows is unchanged.

**Automated by**
`AgreementActivityLogTest::test_editing_an_ordinary_field_writes_no_updated_activity`

**Status** Pass (24 Aug 2026)

---

## 9. List, Search & Filtering

### TC-047 — Index renders for authenticated users and redirects guests

| | |
|---|---|
| **Feature area** | List, Search & Filtering |
| **Business rule** | BR-02 — Authentication required; BR-21 — all authenticated roles may view list |
| **Priority** | High |
| **Type** | Automated |
| **Preconditions** | An authenticated user exists |

**Steps**
1. As an authenticated user, request `/agreements`.
2. As a guest, request `/agreements`.

**Expected result**
Authenticated user sees the register; guest is redirected to `/login`.

**Automated by**
`AgreementsIndexTest::test_index_renders_for_an_authenticated_user`  
`AgreementsIndexTest::test_guests_are_redirected_to_login`

**Status** Pass (24 Aug 2026)

---

### TC-048 — Search matches title and partner name

| | |
|---|---|
| **Feature area** | List, Search & Filtering |
| **Business rule** | BR-03 — List query returns visible rows; search spans title and partner |
| **Priority** | High |
| **Type** | Automated |
| **Preconditions** | Signed agreements with distinct titles and partners exist |

**Steps**
1. Search for a title substring.
2. Search for a partner name.

**Expected result**
Only matching agreements are returned.

**Automated by**
`AgreementsIndexTest::test_search_matches_title`  
`AgreementsIndexTest::test_search_matches_partner_name`

**Status** Pass (24 Aug 2026)

---

### TC-049 — Filters by campus, type, document status, and project status

| | |
|---|---|
| **Feature area** | List, Search & Filtering |
| **Business rule** | BR-03 / BR-22 — Filters reduce the result set; pending is hidden from viewers |
| **Priority** | High |
| **Type** | Automated |
| **Preconditions** | Signed and pending agreements with varied campuses, types, and statuses exist |

**Steps**
1. Apply campus, type, document status, and project status filters simultaneously.

**Expected result**
Only the agreement matching all filters is returned; pending rows are excluded even for filters that would otherwise match.

**Automated by**
`AgreementsIndexTest::test_filters_by_campus_type_document_status_and_project_status`

**Status** Pass (24 Aug 2026)

---

### TC-050 — Changing a filter resets pagination to page one

| | |
|---|---|
| **Feature area** | List, Search & Filtering |
| **Business rule** | BR-24 — Filter changes must not strand the user on an empty page |
| **Priority** | Medium |
| **Type** | Automated |
| **Preconditions** | Enough agreements exist to paginate |

**Steps**
1. Navigate to page 2.
2. Change the search term.

**Expected result**
The component returns to page 1.

**Automated by**
`AgreementsIndexTest::test_changing_a_filter_resets_to_the_first_page`

**Status** Pass (24 Aug 2026)

---

### TC-051 — Archived agreements are excluded by default and shown when filter is on

| | |
|---|---|
| **Feature area** | List, Search & Filtering |
| **Business rule** | BR-15 / BR-06 — Archive is a local scope; default list excludes archived rows |
| **Priority** | High |
| **Type** | Automated |
| **Preconditions** | One active and one archived agreement exist |

**Steps**
1. Render the default list.
2. Render the list with `showArchived = true`.

**Expected result**
Default list shows only the active row; archived view shows only the archived row.

**Automated by**
`AgreementsIndexTest::test_archived_agreements_are_excluded_by_default`  
`AgreementsIndexTest::test_archived_agreements_appear_when_the_archived_filter_is_on`

**Status** Pass (24 Aug 2026)

---

### TC-052 — Viewer does not see the create button

| | |
|---|---|
| **Feature area** | List, Search & Filtering |
| **Business rule** | BR-21 — Viewers have no write UI |
| **Priority** | Medium |
| **Type** | Automated |
| **Preconditions** | A viewer user exists |

**Steps**
1. Log in as viewer and render `/agreements`.

**Expected result**
The "Create agreement" button is absent.

**Automated by**
`AgreementsIndexTest::test_viewer_does_not_see_the_create_button`

**Status** Pass (24 Aug 2026)

---

### TC-053 — Viewer pagination total excludes pending rows

| | |
|---|---|
| **Feature area** | List, Search & Filtering |
| **Business rule** | BR-03 — Pagination count must not leak hidden rows |
| **Priority** | High |
| **Type** | Automated |
| **Preconditions** | Enough signed and pending agreements exist to paginate |

**Steps**
1. Log in as viewer.
2. Render `/agreements` with a page size smaller than the total row count.

**Expected result**
The pagination total reflects only signed rows; pending rows are not counted.

**Automated by**
`AgreementsIndexTest::test_viewer_pagination_total_excludes_pending_rows`

**Status** Pass (24 Aug 2026)

---

## 10. Data Integrity

### TC-054 — No raw `DB::table('agreements')` or `withoutGlobalScope` in application code

| | |
|---|---|
| **Feature area** | Data Integrity |
| **Business rule** | BR-19 — The pending global scope must not be bypassed in application code |
| **Priority** | Critical |
| **Type** | Manual only |
| **Preconditions** | Source code is available |

**Steps**
1. Run `grep -rn "DB::table('agreements')" app/ resources/`.
2. Run `grep -rn "withoutGlobalScope" app/ resources/`.

**Expected result**
Neither pattern appears outside tests.

**Automated by**
Manual code review; executed as part of M4 verification.

**Status** Pass (24 Aug 2026)

---

### TC-055 — Status and project-status scopes accept string and array arguments

| | |
|---|---|
| **Feature area** | Data Integrity |
| **Business rule** | BR-18 — Query scopes are reusable |
| **Priority** | Low |
| **Type** | Automated |
| **Preconditions** | Agreements with varied statuses exist |

**Steps**
1. Call `Agreement::status('signed')`.
2. Call `Agreement::status(['signed', 'pending'])`.
3. Call `Agreement::projectStatus('ongoing')`.

**Expected result**
Each call returns the correct filtered set.

**Automated by**
`AgreementScopesTest::test_status_scope_accepts_a_string_and_an_array`  
`AgreementScopesTest::test_project_status_scope_filters_correctly`

**Status** Pass (24 Aug 2026)

---

### TC-056 — Expiring-soon scope respects a custom day window

| | |
|---|---|
| **Feature area** | Data Integrity |
| **Business rule** | BR-18 — Scopes are parameterised where appropriate |
| **Priority** | Low |
| **Type** | Automated |
| **Preconditions** | Agreements expiring in 30 and 120 days exist |

**Steps**
1. Call `Agreement::expiringSoon(60)`.

**Expected result**
Only agreements expiring within 60 days are returned.

**Automated by**
`AgreementScopesTest::test_expiring_soon_respects_a_custom_day_window`

**Status** Pass (24 Aug 2026)

---

### TC-057 — Concurrent edits are an accepted risk

| | |
|---|---|
| **Feature area** | Data Integrity |
| **Business rule** | BR-25 — No optimistic locking in MVP (DEF-007) |
| **Priority** | Low |
| **Type** | Manual only |
| **Preconditions** | Two users with write access |

**Steps**
1. User A opens an agreement for edit.
2. User B opens the same agreement, edits, and saves.
3. User A saves without refreshing.

**Expected result**
User A's save overwrites User B's change. This is the accepted current behaviour; a fix is deferred to M5.

**Automated by**
Manual only — no automated test because the fix is deferred. Reason: implementing a guard requires a schema change (`lock_version` or compare-and-set), which M4 is barred from making.

**Status** Accepted — deferred (DEF-007)
