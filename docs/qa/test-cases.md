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
| **Business rule** | BR-01 — Users have exactly one of four roles: `admin`, `legal`, `viewer`, or `requester` |
| **Priority** | High |
| **Type** | Automated |
| **Preconditions** | None; tests use the `User` factory |

**Steps**
1. Create users with `role = admin`, `role = legal`, `role = viewer`, and `role = requester`.
2. Call the role helpers (`isAdmin()`, `isLegal()`, `isViewer()`, `isRequester()`) on each.
3. Attempt to mass-assign `role` and `is_active` during creation.

**Expected result**
- Role helpers return `true` only for the matching role.
- `role` and `is_active` are both mass-assignable.

**Automated by**
`UserRoleTest::test_role_helpers_reflect_the_role_column`  
`UserRoleTest::test_role_is_mass_assignable`  
`UserRoleTest::test_is_active_is_mass_assignable_and_cast_to_boolean`
`RequesterRoleTest::test_requester_role_persists_and_helpers_report_correctly`

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

### TC-025 — Campus dropdown lists only active campuses; PIC autocomplete offers distinct existing names

| | |
|---|---|
| **Feature area** | Agreement Creation & Validation |
| **Business rule** | BR-12 — Campus dropdown lists only active campuses; the PIC field offers autocomplete over distinct existing `pic_name` values |
| **Priority** | Medium |
| **Type** | Automated |
| **Preconditions** | One active campus, one inactive campus, and agreements with duplicate and null `pic_name` values exist |

**Steps**
1. Open the create form.
2. Inspect the campus dropdown.
3. Inspect the PIC "Existing PIC" options.

**Expected result**
- Active campuses are present; inactive campuses are absent.
- PIC options contain each distinct non-null `pic_name` exactly once; nulls are omitted.

**Automated by**
`AgreementFormTest::test_campus_dropdown_only_lists_active_campuses`  
`AgreementFormTest::test_existing_pic_mode_offers_previously_used_names`  
`AgreementFormTest::test_pic_autocomplete_only_returns_distinct_non_null_names`

**Status** Pass (3 Sep 2026)

---

### TC-026 — Edit form retains an already-assigned PIC name

| | |
|---|---|
| **Feature area** | Agreement Creation & Validation |
| **Business rule** | BR-11 — An already-assigned PIC name remains visible in the edit form |
| **Priority** | Medium |
| **Type** | Automated |
| **Preconditions** | An agreement has `pic_name` set |

**Steps**
1. Open the edit form for that agreement.

**Expected result**
The PIC name is still shown/selected in Existing PIC mode.

**Automated by**
`AgreementFormTest::test_edit_form_retains_an_existing_pic_name`

**Status** Pass (3 Sep 2026)

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

### TC-030 — Expiry before Date Signed warns but still saves

| | |
|---|---|
| **Feature area** | Dates & Expiry Semantics |
| **Business rule** | BR-09 — Soft warning, not a hard rule |
| **Priority** | High |
| **Type** | Automated |
| **Preconditions** | A legal user is logged in; a partner and campus exist |

**Steps**
1. Enter `agreement_date = 2026-01-15` and `expiry_date = 2026-01-01`.
2. Assert the warning text is shown before saving.
3. Save the form.

**Expected result**
Warning text reads "Expiry date is earlier than the date signed. Save anyway if that matches the document."; the agreement is saved and the user is redirected.

**Automated by**
`AgreementFormTest::test_expiry_before_date_signed_warns_but_still_saves`

**Status** Pass (3 Sep 2026)

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
1. Enter only `agreement_date`.
2. Assert `dateWarning` is null.

**Expected result**
No warning is shown; the form still saves.

**Automated by**
`AgreementFormTest::test_no_warning_when_only_one_date_is_present`

**Status** Pass (3 Sep 2026)

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

### TC-034 — Partner, campus, PIC name, files and activities resolve correctly

| | |
|---|---|
| **Feature area** | Partner Management |
| **Business rule** | BR-18 — Model relations are wired correctly; `pic_name` is a plain string attribute |
| **Priority** | High |
| **Type** | Automated |
| **Preconditions** | An agreement exists |

**Steps**
1. Access `$agreement->partner`, `$agreement->campus`, `$agreement->pic_name`, `$agreement->files`, `$agreement->activities`.
2. Attempt to access the removed `$agreement->pic` relation.

**Expected result**
- Partner and campus relations return the expected models; files and activities return collections.
- `pic_name` is a plain string and is mass-assignable.
- `$agreement->pic` no longer exists as a relation.

**Automated by**
`AgreementRelationsTest::test_partner_campus_and_pic_relations_resolve`  
`AgreementRelationsTest::test_pic_name_is_fillable_and_saves_as_a_plain_string`  
`AgreementRelationsTest::test_pic_relation_no_longer_exists_on_the_model`  
`AgreementRelationsTest::test_files_and_activities_relations_resolve`

**Status** Pass (3 Sep 2026)

---

### TC-035 — PIC may be null

| | |
|---|---|
| **Feature area** | Partner Management |
| **Business rule** | BR-11 — Historical rows may have no PIC |
| **Priority** | Medium |
| **Type** | Automated |
| **Preconditions** | An agreement with `pic_name = null` exists |

**Steps**
1. Render the agreement detail page.

**Expected result**
The PIC renders as `—` without error.

**Automated by**
`AgreementShowTest::test_editing_an_agreement_with_a_null_pic_name_renders_a_dash`  
`BoundaryConditionsTest::test_null_pic_renders_as_em_dash_and_row_still_renders`

**Status** Pass (3 Sep 2026)

---

### TC-036 — Agreement with null PIC renders on list and detail

| | |
|---|---|
| **Feature area** | Partner Management |
| **Business rule** | BR-11 — Historical rows may have no PIC; rendering must not break |
| **Priority** | Medium |
| **Type** | Automated |
| **Preconditions** | An agreement with `pic_name = null` exists |

**Steps**
1. Render the agreement detail page.
2. Render the agreements list.

**Expected result**
- The detail page renders successfully and shows `—` in the PIC line.
- The list row renders successfully and still shows the agreement.

**Automated by**
`BoundaryConditionsTest::test_null_pic_renders_as_em_dash_and_row_still_renders`

**Status** Pass (3 Sep 2026)

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

---

## 11. User Management

### TC-058 — Only admins can reach the user-management page

| | |
|---|---|
| **Feature area** | User Management |
| **Business rule** | BR-26 — User management is admin-only |
| **Priority** | High |
| **Type** | Automated |
| **Preconditions** | One admin, one legal, and one viewer user exist |

**Steps**
1. Request `/users` as each user.

**Expected result**
Admin gets a 200 response; legal and viewer get 403.

**Automated by**
`UserManagementTest::test_admin_can_reach_user_management_page`  
`UserManagementTest::test_legal_user_gets_403_on_user_management_page`  
`UserManagementTest::test_viewer_gets_403_on_user_management_page`

**Status** Pass (27 Aug 2026)

---

### TC-059 — Creating a user persists the correct role and active state

| | |
|---|---|
| **Feature area** | User Management |
| **Business rule** | BR-26 — Admins can create users |
| **Priority** | High |
| **Type** | Automated |
| **Preconditions** | An admin is logged in |

**Steps**
1. Submit the create-user form with name, email, role, and password.

**Expected result**
A user row is created with the supplied name, email, and role, and `is_active = true`.

**Automated by**
`UserManagementTest::test_creating_a_user_persists_the_correct_role_and_active_state`

**Status** Pass (27 Aug 2026)

---

### TC-060 — Duplicate email is rejected when creating a user

| | |
|---|---|
| **Feature area** | User Management |
| **Business rule** | BR-26 — Email addresses must be unique |
| **Priority** | High |
| **Type** | Automated |
| **Preconditions** | A user with the target email already exists; an admin is logged in |

**Steps**
1. Submit the create-user form with an email already in use.

**Expected result**
The form returns a validation error on the email field and no second user is created.

**Automated by**
`UserManagementTest::test_duplicate_email_is_rejected`

**Status** Pass (27 Aug 2026)

---

### TC-061 — An admin cannot deactivate their own account

| | |
|---|---|
| **Feature area** | User Management |
| **Business rule** | BR-26 — Self-deactivation is blocked to prevent lockout |
| **Priority** | Critical |
| **Type** | Automated |
| **Preconditions** | An admin is logged in |

**Steps**
1. Attempt to deactivate the acting admin from the user list.

**Expected result**
The database still shows `is_active = true` for the admin.

**Automated by**
`UserManagementTest::test_admin_cannot_deactivate_their_own_account`

**Status** Pass (27 Aug 2026)

---

### TC-063 — A user deactivated through the UI cannot log in

| | |
|---|---|
| **Feature area** | User Management |
| **Business rule** | BR-02 / BR-26 — Only active users can log in |
| **Priority** | High |
| **Type** | Automated |
| **Preconditions** | An active user exists; an admin is logged in |

**Steps**
1. Deactivate the user via the user-management page.
2. Log out and attempt to log in as the deactivated user.

**Expected result**
Login is rejected with the generic `auth.failed` message.

**Automated by**
`UserManagementTest::test_deactivated_user_cannot_log_in`

**Status** Pass (27 Aug 2026)

---

## 12. PIC as a plain name (M6-4)

### TC-067 — PIC is captured as a plain name string, not a User record

| | |
|---|---|
| **Feature area** | Agreement Creation & Validation |
| **Business rule** | BR-30 — PIC is a plain name string on agreements; there is no backing `users` or `pics` table |
| **Priority** | High |
| **Type** | Automated |
| **Preconditions** | A legal user is logged in; at least one agreement with a PIC name already exists |

**Steps**
1. Create a new agreement in "New PIC" mode, type a name, and save.
2. Create a second agreement in "Existing PIC" mode.
3. Inspect the agreement detail page and the model for the removed `pic()` relation.

**Expected result**
- The typed name is saved directly to `agreements.pic_name`.
- Existing PIC mode offers the previously used name as an option.
- The detail page renders the PIC name (or `—` if null).
- The `Agreement` model no longer has a `pic()` relation.

**Automated by**
`AgreementFormTest::test_new_pic_mode_requires_a_name`  
`AgreementFormTest::test_existing_pic_mode_offers_previously_used_names`  
`AgreementFormTest::test_pic_mode_radios_are_live_bound`  
`AgreementRelationsTest::test_pic_name_is_fillable_and_saves_as_a_plain_string`  
`AgreementRelationsTest::test_pic_relation_no_longer_exists_on_the_model`  
`AgreementShowTest::test_editing_an_agreement_with_a_null_pic_name_renders_a_dash`

**Status** Pass (3 Sep 2026)

---

## 13. Date Signed merge (M6-5)

### TC-068 — Agreement Date and Effective Date merge into a single Date Signed field

| | |
|---|---|
| **Feature area** | Dates & Expiry Semantics |
| **Business rule** | BR-31 — `effective_date` is dropped; the UI field "Date Signed" is backed by `agreement_date` |
| **Priority** | High |
| **Type** | Automated |
| **Preconditions** | An agreement exists with `agreement_date` set |

**Steps**
1. Open the create/edit form and the agreement detail page.
2. Inspect the agreements table schema and the factory definition.
3. Enter an expiry earlier than the date signed and attempt to save.

**Expected result**
- The form shows a single "Date Signed" input wired to `agreement_date`.
- The detail page shows one "Date Signed" row; no "Effective date" row exists.
- `effective_date` is not present in the `agreements` table schema or factory.
- The soft warning still appears and save still succeeds when expiry precedes date signed.

**Automated by**
`AgreementFormTest::test_editing_an_agreement_hydrates_date_signed_from_agreement_date`  
`AgreementFormTest::test_expiry_before_date_signed_warns_but_still_saves`  
`AgreementFormTest::test_no_warning_when_only_one_date_is_present`  
`AgreementShowTest::test_detail_page_shows_one_date_signed_row`  
`AgreementCastsTest::test_year_accessor_still_derives_from_agreement_date`  
`AgreementCastsTest::test_effective_date_column_no_longer_exists`  
`AgreementCastsTest::test_agreement_factory_does_not_reference_effective_date`

**Status** Pass (3 Sep 2026)

---

## 14. M6-1 — List view column changes

### TC-064 — List columns render in the agreed order with Type removed, scope truncated, PIC rendered, and search unchanged

| | |
|---|---|
| **Feature area** | List, Search & Filtering |
| **Business rule** | BR-27 — The list shows Title, Partner, Duration, Scope, Status, Project, PIC, Campus, and the action column; the Type column is removed but the Type filter remains |
| **Priority** | High |
| **Type** | Automated |
| **Preconditions** | Authenticated user; agreements with and without PIC/scope exist |

**Steps**
1. Render the agreements index.
2. Inspect the table header order.
3. Inspect the Type filter in the filter bar.
4. Inspect the Scope cell and its `title` attribute.
5. Inspect the PIC column.
6. Search for a term that appears in a title, a partner name, and only a scope.

**Expected result**
- Header order is Title, Partner, Duration, Scope, Status, Project, PIC, Campus, then the action column.
- No Type column appears in the table; the Type filter still appears above the table.
- Scope is truncated to 80 characters in the list, with the full text available on hover and on the detail page; null scope renders `—`.
- PIC renders `pic_name`, or `—` when unset.
- Search still matches title and partner name only; scope-only matches are not returned.

**Automated by**
`AgreementsIndexTest::test_type_column_is_not_rendered_in_the_list`  
`AgreementsIndexTest::test_columns_render_in_the_agreed_order`  
`AgreementsIndexTest::test_scope_is_truncated_in_the_list`  
`AgreementsIndexTest::test_full_scope_remains_available_on_the_detail_page`  
`AgreementsIndexTest::test_null_scope_renders_as_a_dash`  
`AgreementsIndexTest::test_pic_column_shows_the_pic_name_and_a_dash_when_unset`  
`AgreementsIndexTest::test_search_still_matches_title_and_partner_only`

**Status** Pass (3 Sep 2026)

---

## 15. M6-2 — Duration column format

### TC-065 — Duration renders the date range and an exact year/month count

| | |
|---|---|
| **Feature area** | Dates & Expiry Semantics |
| **Business rule** | BR-28 — Duration shows the Date Signed–expiry range and an exact, never-rounded year/month count |
| **Priority** | High |
| **Type** | Automated |
| **Preconditions** | Agreements with whole-year, partial-year, sub-year, and sub-month durations exist |

**Steps**
1. Inspect `durationInMonths()` and `durationLabel()` on each agreement.
2. Render the duration component.

**Expected result**
- Whole-year durations render as `(N years)`.
- Partial-year durations render as `(N years, M months)`.
- Sub-year durations render as `(M months)`.
- Sub-month durations render as `(less than a month)`.
- Null expiry renders `Indefinite` with no count.
- Null start date renders `— – <expiry>` with no count.
- Expiry before start renders the stored range with no count.
- Leap-year boundaries are handled correctly.

**Automated by**
`AgreementDurationTest::test_whole_year_duration_renders_as_years`  
`AgreementDurationTest::test_partial_year_duration_renders_years_and_months`  
`AgreementDurationTest::test_sub_year_duration_renders_months_only`  
`AgreementDurationTest::test_sub_month_duration_renders_less_than_a_month`  
`AgreementDurationTest::test_null_expiry_yields_no_duration_and_stays_indefinite`  
`AgreementDurationTest::test_null_start_date_yields_no_duration`  
`AgreementDurationTest::test_expiry_before_start_does_not_produce_a_negative_duration`  
`AgreementDurationTest::test_leap_year_boundary_is_handled`

**Status** Pass (3 Sep 2026)

---

## 16. M6-3 — Partner-mode live binding

### TC-066 — Partner-mode radios are live bound and switch the form inline

| | |
|---|---|
| **Feature area** | Agreement Creation & Validation |
| **Business rule** | BR-29 — Conditional form sections driven by a server-rendered `@if` must be bound with `wire:model.live` |
| **Priority** | Critical |
| **Type** | Automated |
| **Preconditions** | A legal user is logged in |

**Steps**
1. Inspect the rendered markup for the partner-mode radios.
2. Switch to New partner.
3. Switch back to Existing partner.

**Expected result**
- Both radios are bound with `wire:model.live="partnerMode"`.
- Switching to New partner reveals the partner name fields immediately.
- Switching back restores the partner dropdown.

**Automated by**
`AgreementFormTest::test_partner_mode_radios_are_live_bound`  
`AgreementFormTest::test_switching_to_new_partner_mode_reveals_the_partner_name_fields`  
`AgreementFormTest::test_switching_back_to_existing_restores_the_partner_dropdown`

**Status** Pass (3 Sep 2026)

---

## 17. M6-6 — Date formatting

### TC-069 — Date component renders d M Y and inputs carry a DD/MM/YYYY hint

| | |
|---|---|
| **Feature area** | Dates & Expiry Semantics |
| **Business rule** | BR-32 — Every date renders as `d M Y`; date inputs carry a `(DD/MM/YYYY)` hint and a confirmation line |
| **Priority** | High |
| **Type** | Automated |
| **Preconditions** | None |

**Steps**
1. Render the date component with a date and with null.
2. Inspect all view files for month-first formats.
3. Render the agreement form and inspect the date inputs.

**Expected result**
- The component renders `18 Mar 2022` for a date and `—` for null.
- No view contains a month-first date format.
- Both date inputs carry the `(DD/MM/YYYY)` hint.
- The inputs still use the `Y-m-d` wire format.

 **Automated by**
 `DateFormattingTest::test_the_date_component_renders_day_month_year`  
 `DateFormattingTest::test_the_date_component_renders_a_dash_for_null`  
 `DateFormattingTest::test_no_view_renders_a_month_first_date_format`  
 `DateFormattingTest::test_date_inputs_carry_a_dd_mm_yyyy_hint`  
 `DateFormattingTest::test_form_date_inputs_still_use_the_y_m_d_wire_format`

 **Status** Pass (3 Sep 2026)

---

## 18. M7-1 — Year filter on the Register list

### TC-070 — Year filter narrows the list and respects pending visibility

| | |
|---|---|
| **Feature area** | List, Search & Filtering |
| **Business rule** | BR-33 — `year` is derived from `agreement_date`; the Year filter uses `whereYear()` and the dropdown options respect the pending-visibility scope |
| **Priority** | Medium |
| **Type** | Automated |
| **Preconditions** | Signed and pending agreements with distinct `agreement_date` years exist |

**Steps**
1. Render the agreements index with a Year filter value.
2. Inspect the Year dropdown options as a viewer and as a legal user.
3. Create an agreement with a null `agreement_date`.

**Expected result**
- Only agreements signed in the selected year are shown.
- The dropdown lists distinct years present in visible agreements.
- A viewer does not see a year that only contains pending agreements; a legal user does.
- Agreements with a null date are excluded from every year filter result.

**Automated by**
`AgreementsIndexTest::test_year_filter_narrows_the_list_to_agreements_signed_in_that_year`  
`AgreementsIndexTest::test_year_dropdown_options_are_distinct_years_present_in_the_data`  
`AgreementsIndexTest::test_year_dropdown_options_respect_the_pending_visibility_scope_for_a_viewer`  
`AgreementsIndexTest::test_changing_the_year_filter_resets_to_the_first_page`  
`AgreementsIndexTest::test_an_agreement_with_a_null_agreement_date_is_excluded_from_every_year_filter_result`

**Status** Pass (7 Sep 2026)

---

## 19. M7-5 — Country dropdown simplified to Local / International

### TC-071 — Partner quick-create country control offers Local / International and resolves correctly

| | |
|---|---|
| **Feature area** | Agreement Creation & Validation |
| **Business rule** | BR-34 — The partner quick-create flow offers only Local/International; Local resolves to Malaysia and International resolves to a seeded placeholder row |
| **Priority** | Medium |
| **Type** | Automated |
| **Preconditions** | `CountrySeeder` has run; the International placeholder row exists |

**Steps**
1. Create a new partner selecting "Local".
2. Create a new partner selecting "International".
3. Run `CountrySeeder` twice.
4. Create an agreement using an existing partner.

**Expected result**
- "Local" resolves to the Malaysia row's `country_id`.
- "International" resolves to the placeholder "International" row's `country_id`.
- Running the seeder twice does not duplicate the "International" row.
- Existing partners' `country_id` values are unchanged.

 **Automated by**
 `PartnerCountryTest::test_selecting_local_resolves_the_new_partners_country_to_malaysia`  
 `PartnerCountryTest::test_selecting_international_resolves_the_new_partners_country_to_the_placeholder_row`  
 `PartnerCountryTest::test_country_seeder_is_still_idempotent_with_the_new_row`  
 `PartnerCountryTest::test_existing_partners_country_id_is_unaffected_by_this_change`

 **Status** Pass (7 Sep 2026)

---

## 20. M8-3 — Archiving: automatic (expiry) and manual (early termination)

### TC-072 — Expired agreements are automatically archived by the scheduled command

| | |
|---|---|
| **Feature area** | Archiving |
| **Business rule** | BR-35 — A daily scheduled command archives agreements whose `expiry_date` has passed, setting `archive_reason = 'expired'` and writing one activity entry per agreement |
| **Priority** | High |
| **Type** | Automated |
| **Preconditions** | Agreements with past, future, null, and today's `expiry_date` exist; some are already archived |

**Steps**
1. Run `agreements:archive-expired`.
2. Inspect archived agreements, their `archive_reason`, and activity feed entries.
3. Run the command a second time.

**Expected result**
- Agreements with `expiry_date <= today()` and no `archived_at` are archived with reason `expired`.
- An activity entry of type `archived` is written per newly archived agreement.
- Already-archived agreements are untouched on the second run (no duplicate activities, no reason change).
- Agreements with a null `expiry_date` are never archived by the command.

**Automated by**
`ArchiveExpiredAgreementsTest::test_scheduled_command_archives_agreements_past_their_expiry_date`  
`ArchiveExpiredAgreementsTest::test_scheduled_command_sets_archive_reason_to_expired`  
`ArchiveExpiredAgreementsTest::test_scheduled_command_does_not_touch_already_archived_agreements`  
`ArchiveExpiredAgreementsTest::test_scheduled_command_does_not_archive_an_agreement_with_a_null_expiry_date`  
`ArchiveExpiredAgreementsTest::test_scheduled_command_boundary_matches_is_expired`  
`ArchiveExpiredAgreementsTest::test_scheduled_command_writes_an_activity_entry_per_archived_agreement`

**Status** Pass (7 Sep 2026)

---

### TC-073 — Legal and admin can manually archive an agreement as terminated

| | |
|---|---|
| **Feature area** | Archiving |
| **Business rule** | BR-36 — Users with write access can manually archive an agreement, setting `archive_reason = 'terminated'`; viewers cannot; already-archived agreements hide the action |
| **Priority** | High |
| **Type** | Automated |
| **Preconditions** | Active and archived agreements exist; admin/legal/viewer users exist |

**Steps**
1. As legal, call the archive action on an active agreement.
2. As viewer, attempt the same action.
3. Render the detail page for an already-archived agreement.
4. Create a manually-terminated agreement whose `expiry_date` has also passed, then run the scheduled command.

**Expected result**
- Legal action sets `archived_at` and `archive_reason = 'terminated'` and writes an activity entry.
- Viewer action is rejected (403) and leaves the agreement unarchived.
- The archive action is hidden for already-archived agreements.
- A manually-terminated agreement is protected from being relabeled `expired` by the scheduled command.

**Automated by**
`AgreementShowTest::test_legal_can_manually_archive_an_agreement_with_terminated_reason`  
`AgreementShowTest::test_viewer_cannot_manually_archive_an_agreement`  
`AgreementShowTest::test_an_already_archived_agreement_does_not_show_the_archive_action`  
`AgreementShowTest::test_manually_archived_agreement_is_excluded_from_the_scheduled_commands_update`

**Status** Pass (7 Sep 2026)

---

### TC-074 — The Show Archived filter surfaces genuinely archived agreements

| | |
|---|---|
| **Feature area** | List, Search & Filtering |
| **Business rule** | BR-06 — The existing `Show Archived` filter toggles the `archived()` / `notArchived()` scopes |
| **Priority** | Medium |
| **Type** | Automated |
| **Preconditions** | One active and one archived agreement exist |

**Steps**
1. Render the agreements index with `showArchived = true`.

**Expected result**
- The archived agreement is shown; the active agreement is not.

**Automated by**
`AgreementsIndexTest::test_show_archived_filter_now_surfaces_genuinely_archived_agreements`

**Status** Pass (7 Sep 2026)

---

## 21. M9 — Custom dropdown component, bold fix, Campus/Department rename

### TC-075 — Campus code renders visibly bold in the Register list

| | |
|---|---|
| **Feature area** | List, Search & Filtering |
| **Business rule** | BR-37 — Campus code is emphasised in the Register list so owners can be scanned quickly |
| **Priority** | High |
| **Type** | Automated |
| **Preconditions** | An agreement exists with a campus assigned |

**Steps**
1. Render the agreements index.
2. Inspect the Campus/Department cell for the agreement.

**Expected result**
The campus code is wrapped in a `<strong class="font-bold">` tag; the full institute name is in normal weight.

**Automated by**
`AgreementsIndexTest::test_campus_code_renders_bold_in_the_register_list`

**Status** Pass (7 Sep 2026)

---

### TC-076 — The campus owner field is labelled "Campus / Department" everywhere

| | |
|---|---|
| **Feature area** | Agreement Creation & Validation / List, Search & Filtering |
| **Business rule** | BR-38 — User-facing label reflects that the list now includes departments/centres, not only physical campuses |
| **Priority** | Medium |
| **Type** | Automated |
| **Preconditions** | None |

**Steps**
1. Render the agreements index (header and filter).
2. Render the create/edit agreement form.
3. Render the agreement detail page.

**Expected result**
All visible labels that previously read "Campus" now read "Campus / Department". Internal table/model/column names are unchanged.

**Automated by**
`AgreementsIndexTest::test_campus_department_header_and_filter_label_are_renamed`
`AgreementFormTest::test_campus_field_label_reads_campus_slash_department`

**Status** Pass (7 Sep 2026)

---

### TC-077 — A custom Livewire-only dropdown replaces native selects where per-option formatting is needed

| | |
|---|---|
| **Feature area** | Agreement Creation & Validation |
| **Business rule** | BR-39 — Custom-rendered dropdowns are used wherever per-option formatting is required, because native `<option>` styling is not reliably supported by browsers |
| **Priority** | High |
| **Type** | Automated |
| **Preconditions** | Partners, campuses, and agreements with PIC names exist; Alpine.js is not used in this project |

**Steps**
1. Render the create/edit form.
2. Open each custom dropdown (Campus/Department, Partner-existing, PIC-existing).
3. Select options in each dropdown.
4. Switch Partner and PIC to "New" mode and back to "Existing".
5. Open the edit form for an agreement with a campus and PIC already set.

**Expected result**
- Campus/Department options render the code in bold and the name in normal weight.
- Partner and PIC options render as plain text.
- Selecting an option sets the bound Livewire property and the form still saves correctly.
- Existing/new toggles are unaffected.
- Edit mode pre-selects the existing campus and PIC values.
- Dropdowns can be opened/closed and support click-outside and Escape-to-close without Alpine.js.

**Automated by**
`AgreementFormTest::test_campus_dropdown_option_renders_code_in_bold`
`AgreementFormTest::test_selecting_campus_via_dropdown_sets_value`
`AgreementFormTest::test_selecting_partner_via_dropdown_sets_value`
`AgreementFormTest::test_selecting_existing_pic_via_dropdown_sets_value`
`AgreementFormTest::test_selecting_no_pic_via_dropdown_clears_pic_name`
`AgreementFormTest::test_dropdown_open_state_can_be_toggled_and_closed`
`AgreementFormTest::test_dropdown_component_has_click_outside_backdrop_when_open`
`AgreementFormTest::test_dropdown_component_uses_escape_key_handler`
`AgreementFormTest::test_alpine_js_is_not_used_for_dropdowns`
`AgreementFormTest::test_switching_back_to_existing_restores_the_partner_dropdown`
`AgreementFormTest::test_switching_to_new_partner_mode_reveals_the_partner_name_fields`
`AgreementFormTest::test_edit_form_retains_an_existing_pic_name`
`CampusSeederTest::test_new_campuses_appear_in_the_agreement_forms_campus_dropdown`

**Status** Pass (7 Sep 2026)

---

## 22. LP0 — Legal Submission Portal Foundation & Hardening

### TC-078 — Requesters use the Agreement Register as a read-only reference

| | |
|---|---|
| **Feature area** | Access control |
| **Business rule** | BR-40 — A requester uses the Agreement Register as a read-only reference (LP1 Amendment 2, 17 Sep 2026): the register list and non-pending detail routes are reachable; create/edit routes and every mutation remain denied; navigation shows Register alongside My Submissions |
| **Priority** | Critical |
| **Type** | Automated |
| **Preconditions** | An active requester account; one signed agreement |

**Steps**
1. Sign in as a requester.
2. Confirm Register navigation is shown alongside My Submissions.
3. Request `/agreements` and `/agreements/{signed agreement}` directly.
4. Request `/agreements/create` and `/agreements/{agreement}/edit` directly.

**Expected result**
Register navigation is present, the register list and non-pending detail return `200 OK`, and each create/edit route returns `403 Forbidden`.

**Automated by**
`RequesterRoleTest::test_requester_can_open_the_agreements_index`
`RequesterRoleTest::test_requester_can_open_a_non_pending_agreement_show_page`
`RequesterRoleTest::test_requester_is_forbidden_from_agreement_create_and_edit`
`RequesterRoleTest::test_the_register_nav_link_is_shown_to_a_requester`
`AgreementAccessControlTest::test_requester_can_reach_the_register_list_and_a_non_pending_detail`

**Historical note**
The pre-amendment rule (requesters fully blocked from the register) passed production verification on 14 Sep 2026 using a temporary requester account; `/agreements`, `/agreements/1`, and `/users` each returned `403 Forbidden`. That rule was superseded by LP1 Amendment 2 on 17 Sep 2026. Pending-agreement confidentiality for requesters is proven separately in TC-089.

**Status** Pass (17 Sep 2026)

---

### TC-079 — User management actions are authorised server-side

| | |
|---|---|
| **Feature area** | Access control |
| **Business rule** | BR-41 — Only an admin can create, activate, or deactivate users |
| **Priority** | Critical |
| **Type** | Automated |
| **Preconditions** | Admin, Legal, viewer, and requester test users |

**Steps**
1. Attempt to create a user through the Livewire user-management component as each non-admin role.
2. Attempt to activate or deactivate another user as each non-admin role.
3. Repeat the actions as an admin.

**Expected result**
Non-admin actions have no effect. Admin actions complete successfully. Route middleware and each mutating component action enforce the rule.

**Automated by**
`LivewireRoleEnforcementTest`
`UserManagementTest`

**Status** Pass (10 Sep 2026)

---

### TC-080 — A deactivated requester cannot log in

| | |
|---|---|
| **Feature area** | Authentication & Session |
| **Business rule** | BR-42 — Deactivation blocks access for every role, including requester |
| **Priority** | High |
| **Type** | Automated and manual production verification |
| **Preconditions** | A requester account exists and can log in |

**Steps**
1. Deactivate the requester account as an admin.
2. Attempt to log in using that account.

**Expected result**
Login is rejected with the generic authentication failure response.

**Automated by**
`UserManagementTest::test_deactivated_user_cannot_log_in`

**Production verification**
Passed 14 Sep 2026 using the temporary requester account from TC-078.

**Status** Pass (14 Sep 2026)

---

## 23. LP1 — Legal Submission Portal

> LP1-B (15 Sep 2026) exposed the LP1-A submission foundation through three Livewire pages (`resources/views/livewire/submissions/`), three named routes, role-aware navigation, and requester dashboard entry points. The CLSD prototype (see `docs/design/README.md`) supplied the visual language only; no depicted future feature was implemented.

### TC-081 — Portal route access control

| | |
|---|---|
| **Feature area** | Access control |
| **Business rule** | BR-43 / BR-46 / BR-45 — Only Requesting Staff create; viewers have no portal access; Legal/Admin read but cannot create |
| **Priority** | Critical |
| **Type** | Automated |
| **Preconditions** | Test users for every role; one factory-created submission |

**Steps**
1. Request `/submissions`, `/submissions/create`, and `/submissions/{submission}` as a guest.
2. Repeat as an active viewer.
3. Repeat as active Legal and active Admin.
4. Repeat as active Requesting Staff, using their own submission for the detail route.
5. Probe for update/delete routes: check the route collection and issue `PUT`, `DELETE`, and `POST` verbs against the portal paths.

**Expected result**
Guests are redirected to `/login`. Viewers receive `403` on every portal route. Legal and Admin receive `200` for index and show but `403` for create. Requesting Staff receive `200` for index, create, and their own detail. No update or delete route exists; non-GET verbs return `405 Method Not Allowed`.

**Automated by**
`PortalAccessTest::test_guests_are_redirected_to_login_for_every_portal_route`
`PortalAccessTest::test_viewer_receives_403_for_every_portal_route`
`PortalAccessTest::test_legal_and_admin_can_access_index_and_show_but_not_create`
`PortalAccessTest::test_requesting_staff_can_access_index_create_and_their_own_detail`
`PortalAccessTest::test_inactive_admin_and_legal_are_denied_the_index`
`PortalAccessTest::test_inactive_admin_and_legal_are_denied_the_show_route`
`PortalAccessTest::test_no_update_or_delete_routes_exist`

**Status** Pass (15 Sep 2026)

---

### TC-082 — Requester queue isolation and the shared Legal/Admin queue

| | |
|---|---|
| **Feature area** | Privacy & list behaviour |
| **Business rule** | BR-44 / BR-45 — Requesters see only their own submissions; Legal/Admin see one shared queue with requester identity |
| **Priority** | Critical |
| **Type** | Automated |
| **Preconditions** | Two requester accounts with separate submissions |

**Steps**
1. As Requesting Staff, render the queue with own and foreign submissions present.
2. Create 17 own and 3 foreign submissions and inspect the paginator total and page contents.
3. Give a foreign submission a `submission_created` activity with a distinctive description and a distinctive actor name, then render the queue as an unrelated requester.
4. Render the queue as active Legal and as active Admin.
5. Render the queue with no submissions for each side.

**Expected result**
The requester sees only their own rows; a foreign submission's title, requester identity, activity description, and actor name never appear, and no "Requested by" column renders. The paginator total is computed from the ownership-scoped query (17, not 20), and page 1 never contains a foreign row. Legal and Admin see every submission, the "Submission Queue" heading, and the requester name. Both sides get an appropriate empty state; only Requesting Staff see the "New Submission" action.

**Automated by**
`SubmissionsIndexTest::test_requester_list_contains_only_their_own_records`
`SubmissionsIndexTest::test_requester_pagination_counts_exclude_other_requesters_records`
`SubmissionsIndexTest::test_requester_output_excludes_another_requesters_title_identity_and_activity`
`SubmissionsIndexTest::test_legal_and_admin_see_all_submissions`
`SubmissionsIndexTest::test_legal_and_admin_queue_identifies_the_requester`
`SubmissionsIndexTest::test_null_agreement_type_renders_as_not_sure`
`SubmissionsIndexTest::test_requester_sees_the_create_action_and_legal_does_not`
`SubmissionsIndexTest::test_index_renders_an_appropriate_empty_state`
`SubmissionsIndexTest::test_legal_queue_renders_an_appropriate_empty_state`

**Status** Pass (15 Sep 2026)

---

### TC-083 — Submission form integrity and trusted creation

| | |
|---|---|
| **Feature area** | Creation |
| **Business rule** | BR-48 / BR-49 / BR-50 — Active non-TBD campuses; six approved types plus "Not sure"; no protected controls; creation via the trusted boundary |
| **Priority** | Critical |
| **Type** | Automated |
| **Preconditions** | An active Requesting Staff user; active, inactive, and `TBD` campuses |

**Steps**
1. Open the form and inspect the campus dropdown.
2. Extract the option values of the `agreement_type` select and compare them with the exact expected ordered list.
3. Submit a valid form through Livewire.
4. Submit with "Not sure" selected.
5. Inspect the rendered HTML for ownership, status, timestamp, and agreement-link controls and for draft affordances.
6. Confirm the Private & Confidential notice renders.

**Expected result**
The campus dropdown contains only active campuses and excludes `TBD`. The `agreement_type` select offers exactly — in order — the blank "Not sure" option, then LOI, NDA, MOA, MOU, SEA, and ADDENDUM; a missing, duplicate, reordered, or seventh option (such as `MOC`) fails the test. A valid submission creates exactly one submission and one `submission_created` activity and redirects to the detail page; "Not sure" persists `agreement_type` as `null`. No protected control or draft action exists on the form.

**Automated by**
`SubmissionFormTest::test_form_lists_only_active_non_tbd_campuses`
`SubmissionFormTest::test_agreement_type_select_offers_exactly_the_seven_options_in_order`
`SubmissionFormTest::test_valid_livewire_submission_creates_one_submission_and_one_activity_and_redirects`
`SubmissionFormTest::test_not_sure_persists_as_null`
`SubmissionFormTest::test_form_exposes_no_protected_ownership_status_timestamp_or_agreement_controls`
`SubmissionFormTest::test_form_shows_the_private_and_confidential_notice`

**Status** Pass (15 Sep 2026)

---

### TC-084 — Form validation maps to field errors and writes nothing

| | |
|---|---|
| **Feature area** | Creation & validation |
| **Business rule** | BR-48 / BR-49 / BR-50 — Rejected form input reports the correct field and writes neither table |
| **Priority** | High |
| **Type** | Automated |
| **Preconditions** | An active Requesting Staff user; active and inactive campuses |

**Steps**
1. Submit the form with an empty title, an empty partner name, a 5,001-character purpose, `MOC`, and an inactive campus in turn.
2. Submit with each of `title`, `partner_name`, and `purpose` blank in turn, expecting that field's own error.

**Expected result**
Each rejection maps to errors on exactly its expected field — empty title → `title`, empty partner name → `partner_name`, overlong purpose → `purpose`, `MOC` → `agreement_type`, inactive campus → `campus_id` — and both the `submissions` and `submission_activities` tables remain empty.

**Automated by**
`SubmissionFormTest::test_invalid_form_data_reports_the_field_and_writes_neither_table`
`SubmissionFormTest::test_missing_required_fields_report_their_own_field`

**Status** Pass (15 Sep 2026)

---

### TC-085 — Livewire re-authorization on update requests

| | |
|---|---|
| **Feature area** | Access control |
| **Business rule** | BR-47 / BR-51 — A deactivated, unauthorized, or different user cannot reuse an existing component snapshot |
| **Priority** | Critical |
| **Type** | Automated |
| **Preconditions** | A legitimate owner snapshot and an unauthorized session |

**Steps**
1. As the owner, open the detail page; then switch to a different requester and trigger a Livewire update (`$refresh`), expecting `404 Not Found` (the policy's `denyAsNotFound`).
2. As the owner, open the detail page; deactivate the account in the database; re-authenticate the stored inactive state; then trigger a Livewire update, expecting `403 Forbidden`.
3. Repeat step 2 for inactive Admin and inactive Legal, expecting `403 Forbidden`.
4. As Requesting Staff, fill the form snapshot; switch to a Legal user and trigger the save action, expecting `403 Forbidden` and nothing written.
5. As Requesting Staff, replay the form snapshot under a Legal session and under a deactivated requester session, triggering a harmless update (`$refresh`) each time, expecting `403 Forbidden`.
6. Replay the index component snapshot as a viewer whose own `created_by` data would otherwise be exposed, and as an inactive requester, an inactive Admin, and an inactive Legal user, each expecting `403 Forbidden`.

**Expected result**
Every replayed snapshot is denied with the exact expected status (404 for cross-requester detail access, 403 for every other denial), and nothing is written. Each negative is paired with an in-test positive control (the legitimate user rendering or refreshing first).

**Automated by**
`SubmissionShowTest::test_cross_requester_denial_holds_on_a_real_livewire_update_request`
`SubmissionShowTest::test_an_inactive_requester_is_denied_on_the_livewire_update_path`
`SubmissionShowTest::test_inactive_admin_and_legal_are_denied_on_the_livewire_update_path`
`SubmissionShowTest::test_the_owner_can_still_refresh_their_own_component`
`SubmissionFormTest::test_a_legal_session_cannot_submit_through_a_replayed_form_snapshot`
`SubmissionFormTest::test_admin_legal_and_viewer_cannot_refresh_a_replayed_form_snapshot`
`SubmissionFormTest::test_an_inactive_requester_cannot_refresh_the_form_component`
`SubmissionsIndexTest::test_an_inactive_requester_is_denied_on_the_index_update_path`
`SubmissionsIndexTest::test_inactive_admin_and_legal_are_denied_on_the_index_update_path`
`SubmissionsIndexTest::test_a_viewer_cannot_render_the_list_through_a_livewire_update`

**Status** Pass (15 Sep 2026)

---

### TC-086 — Authorized read-only detail page

| | |
|---|---|
| **Feature area** | Privacy & display |
| **Business rule** | BR-44 / BR-45 — Requesters open only their own detail; Legal/Admin see requester identity; no mutation exists |
| **Priority** | Critical |
| **Type** | Automated |
| **Preconditions** | A submission with its initial `submission_created` activity |

**Steps**
1. As the owning Requesting Staff user, open the detail of a submission with deterministic data (title, partner, agreement type, purpose, campus) and a frozen server clock, then verify every displayed field.
2. Open it as Legal and as Admin and verify the additional requester-identity block.
3. Give a foreign submission a `submission_created` activity with a distinctive description and actor name, then open its detail URL as an unrelated requester.
4. Inspect the page for mutation controls and for the dormant agreement link.

**Expected result**
The owner sees the submission number (`#<id>`), title, campus code and name, partner, agreement type, purpose, Pending status, the submitted timestamp rendered in Malaysian time by `<x-datetime>` (a frozen 10:00 UTC moment displays as "14 Sep 2026, 6:00 PM"), the initial `submission_created` activity description, and the activity's actor name. Requesting Staff do not see "Requested by". Legal and Admin additionally see "Requested by" with the requester name and the "Created" timestamp. Another requester receives `404` with none of the foreign title, purpose, activity description, or actor name exposed, while the owner's own view still shows the activity (positive control). No edit, delete, archive, status, upload, message, note, or agreement-link control or route exists.

**Automated by**
`SubmissionShowTest::test_requesting_staff_can_open_their_own_submission`
`SubmissionShowTest::test_legal_and_admin_detail_identifies_the_requester`
`SubmissionShowTest::test_detail_displays_the_initial_activity_history`
`SubmissionShowTest::test_cross_requester_direct_access_returns_404_and_exposes_no_content`
`SubmissionShowTest::test_detail_is_read_only_with_no_mutation_controls`
`SubmissionShowTest::test_detail_does_not_expose_the_dormant_agreement_link`

**Status** Pass (15 Sep 2026)

---

### TC-087 — Requester-controlled HTML is escaped

| | |
|---|---|
| **Feature area** | Display security |
| **Business rule** | BR-52 — Requester-controlled values render through escaped Blade output only |
| **Priority** | High |
| **Type** | Automated |
| **Preconditions** | A submission whose title contains `<script>` and whose partner name contains an `onerror` payload |

**Steps**
1. Create such a submission and render the queue as its owner.
2. Render the detail page as its owner.

**Expected result**
Neither page outputs raw `<script>` or `<img src=x>` markup; the escaped entity form appears instead.

**Automated by**
`SubmissionsIndexTest::test_requester_controlled_html_is_escaped_in_the_list`
`SubmissionShowTest::test_requester_controlled_html_is_escaped_on_detail`

**Status** Pass (15 Sep 2026)

---

### TC-088 — Portal navigation and dashboard entry points

| | |
|---|---|
| **Feature area** | Navigation |
| **Business rule** | BR-44 / BR-46 / BR-40 — Role-aware portal labels; viewers see no portal link; requesters keep My Submissions and, since LP1 Amendment 2, also see Register |
| **Priority** | Medium |
| **Type** | Automated |
| **Preconditions** | One user per role |

**Steps**
1. Render the dashboard as each role and inspect the navigation.
2. As Requesting Staff, confirm the dashboard shows Create Submission, My Submissions, and Open register entry points.
3. Open `/submissions` as each role and check the heading.

**Expected result**
Requesting Staff see "My Submissions" and "Register" in navigation and the Create Submission, My Submissions, and Open register dashboard entry points, but not "Submission Queue". Admin and Legal see "Submission Queue" and keep Register. Viewers keep Register but get no portal link, and no non-requester dashboard shows the portal entry points. The index heading matches the role.

**Automated by**
`PortalNavigationTest::test_requester_navigation_says_my_submissions_and_hides_the_queue`
`PortalNavigationTest::test_admin_and_legal_navigation_says_submission_queue_and_keeps_register`
`PortalNavigationTest::test_viewer_navigation_shows_no_portal_link_but_keeps_register`
`PortalNavigationTest::test_requester_dashboard_links_to_create_submission_my_submissions_and_the_register`
`PortalNavigationTest::test_non_requester_dashboards_do_not_show_the_portal_entry_points`
`PortalNavigationTest::test_portal_index_heading_matches_the_role`

**Status** Pass (15 Sep 2026; re-verified 17 Sep 2026 after LP1 Amendment 2)

---

## 24. LP1 Amendment 2 — Requesting Staff Register access (17 Sep 2026)

> Amendment 2 gives Requesting Staff read-only access to the Agreement Register while preserving pending-agreement confidentiality, the read-only boundary, and submission-portal isolation. See `docs/architecture-plan-lp1.md` S21.

### TC-089 — Requesting Staff read-only Register access with full pending confidentiality

| | |
|---|---|
| **Feature area** | Access control / Pending Visibility / Read-only boundary |
| **Business rule** | BR-40 (amended) / BR-03 / BR-53 — Requesters may open the register list and non-pending detail records; pending agreements stay invisible to them through every channel; and no register mutation is reachable |
| **Priority** | Critical |
| **Type** | Automated and manual browser verification |
| **Preconditions** | Active signed and pending agreements; an active requester account |

**Steps**
1. Sign in as a requester and render the register list with signed and pending agreements present.
2. Create enough signed and pending rows to paginate and inspect the paginator total.
3. Inspect the document-status filter options, the year dropdown options, and search/filter results.
4. Request `/agreements/{pending agreement}` directly.
5. Render a signed agreement detail page and inspect it for create, edit, archive, and status-changing controls.
6. Invoke the archive and updateStatus actions through the detail component as a requester.
7. Request `/agreements/create` and `/agreements/{agreement}/edit` directly.

**Expected result**
The list shows signed rows only; the pagination total excludes pending rows; the document-status filter offers no `pending` option and no pending row appears under any offered filter or search; the year dropdown omits a year represented only by pending agreements; direct access to a pending agreement returns `404`; the detail page shows no mutation controls; and both Livewire mutation attempts return `403` with the agreement unchanged in the database. Create/edit routes return `403`.

**Automated by**
`AgreementsIndexTest::test_requester_does_not_see_pending_agreements_in_the_list`
`AgreementsIndexTest::test_requester_pagination_total_excludes_pending_rows`
`AgreementsIndexTest::test_pending_is_not_offered_as_a_status_filter_to_a_requester`
`AgreementsIndexTest::test_year_dropdown_options_respect_the_pending_visibility_scope_for_a_requester`
`AgreementsIndexTest::test_search_results_exclude_pending_agreements_for_a_requester`
`AgreementsIndexTest::test_document_status_filter_results_exclude_pending_agreements_for_a_requester`
`AgreementsIndexTest::test_a_pending_document_status_filter_value_cannot_reveal_pending_agreements`
`AgreementsIndexTest::test_a_pending_only_year_and_the_combined_filter_cannot_reveal_pending_agreements`
`AgreementsIndexTest::test_requester_does_not_see_the_create_button`
`AgreementShowTest::test_requester_can_view_a_signed_agreement`
`AgreementShowTest::test_requester_gets_404_for_a_pending_agreement`
`AgreementShowTest::test_requester_does_not_see_mutation_controls`
`AgreementShowTest::test_requester_cannot_manually_archive_an_agreement`
`AgreementShowTest::test_requester_cannot_change_status_through_a_livewire_update`
`AgreementShowTest::test_a_stale_snapshot_cannot_render_an_agreement_after_it_becomes_pending`
`AgreementAccessControlTest::test_requester_gets_403_on_the_create_route`
`AgreementAccessControlTest::test_requester_gets_403_on_the_edit_route`
`UserRoleTest::test_can_access_register_is_an_allow_list_of_all_four_roles`

**Verification status**
The two hostile URL-backed filter tests and the stale-snapshot Livewire update regression test (`AgreementShowTest::test_a_stale_snapshot_cannot_render_an_agreement_after_it_becomes_pending`, DEF-014) passed as part of the authorised full-suite run on 18 Sep 2026: 343 tests, 1,138 assertions.

**Manual verification**
Passed 18 Sep 2026 against the local development database. Legal temporarily moved agreement `#2` from `awaiting_partner` to `pending`. Requesting Staff could still open the Register and agreement `#1`, but agreement `#2` disappeared from the list and returned `404` by direct URL; Pending was absent from the filter; no create or mutation controls were exposed; and create/edit URLs returned `403`. Viewer retained read-only Register access, received `404` for agreement `#2`, and remained denied the portal. Legal retained pending-record and shared-queue access. Guest Register and portal URLs redirected to login. Agreement `#2` was restored to `awaiting_partner` after the walkthrough.

**Status** Pass (18 Sep 2026)
