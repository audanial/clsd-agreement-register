# LP2 Documents Implementation Plan

> **For agentic workers:** Use `superpowers:executing-plans` for native execution or `superpowers:subagent-driven-development` if Amir chooses delegation. Implement task by task with checkbox tracking. Do not select an execution method or start product code before Amir reviews this plan.

**Goal:** Requesting Staff submit one complete, private document package in one session; Legal/Admin inspect and download preserved Version 1 files.

**Architecture:** A user-bound form ticket and encrypted upload receipts identify private temporary files. One trusted creation action validates the whole package, saves permanent files, and atomically creates submission/slots/versions/audit, with compensating cleanup and duplicate-safe retries. Downloads pass through application authorisation and audit; scheduled cleanup removes abandoned or unreferenced objects only.

**Tech Stack:** Existing Laravel 13, Livewire 4 single-file components, Tailwind CSS 4, Vite, PHPUnit, local SQLite and production MySQL 8.4. Private local development storage; Laravel Cloud private S3-compatible Object Storage in production.

**Spec:** `docs/architecture-plan-lp2.md` (approved by Amir, 5 October 2026), together with `docs/design/unikl-agreement-management-v1-spec.md` and `AGENTS.md`.

**Status:** Written for implementation review on 5 October 2026. No product code, tests, working-database migrations, dependency installs, commits, Cloud resources or deployment were performed during planning.

**Shared baseline, 8 October 2026:** Included as execution guidance derived from the approved architecture. This documentation-only baseline contains no implementation or verification evidence. Local development progress is tracked separately. Architecture and binding requirements remain authoritative; publication of this plan does not approve implementation, paid provisioning or deployment.

## Global Constraints

- "Files remain temporary until **Submit to Legal**." No saved drafts, official temporary-upload rows, form-resume route or pre-submission permanent history.
- "LP2 creates **Version 1 — Submitted for Review** (`submitted_for_review`) in every applicable slot."
- Academic / Industry / Business / Commercial; exactly two Academic documents and six for the other categories. Use **Memo (For CEO)** everywhere the memo slot appears.
- New types NDA/MOA/MOU/ADDENDUM only; preserve historical LOI/SEA/null and all existing LP1 rows.
- "`submitted_at` is non-nullable and stays non-nullable."
- Submitted files remain locked in LP2. No review actions, clarification, Conversation, stamping, signing, Register outcome or role realignment.
- Requester owns submission access; Viewer has no portal access; Legal/Admin retain submitted-record access. All current Register role/visibility rules continue.
- "No `Storage::url()` or `temporaryUrl()` for document access." Private visibility and no public serving on document disks.
- Distinct local/cloud disk identities saved on every version; omit unsupported R2 per-object ACL write options.
- "20,480 KB (20 MiB)" is the implementation limit for the advertised 20 MB. One file per temporary-upload request.
- PHP attribute model configuration; commented migrations; required history FKs restrict deletion; optional actor FK nulling preserves attribution snapshot.
- All Livewire components remain emoji-free in `resources/views/livewire/`; anonymous Blade components remain in `resources/views/components/`.
- "Use `<x-datetime>` for audit timestamps in Malaysian time; keep application timezone UTC."
- Production copy remains concise and action-led. Prototype HTML/scripts/assets never enter production.
- Use existing PHPUnit and in-memory SQLite; parse PAO JSON. Format changed PHP. No JS test framework is added.
- No commit, push, working-database migration, real-data test, paid provisioning or deployment without Amir's separate instruction. Isolated PHPUnit test migrations are required verification.
- No production secrets are read into output or committed. Do not change `.npmrc`, the font-fetch behaviour or unrelated business rules.

## Review Focus

1. A lost successful response followed by a retry after temp-file cleanup must open the same owned submission (Task 4).
2. A copied valid upload receipt from another user/form/slot must never expose or adopt that file (Tasks 2–3).
3. Switching Industry ↔ Business preserves selections; a failed replacement preserves the previous valid selection (Task 5).
4. Genuine ZIP-detected DOCX must work while renamed archives and macros fail, including unusual filenames (Task 2).
5. Expired-object cleanup must leave referenced historical versions and recently written in-flight objects intact (Task 7).

## Repository baseline and file ownership

The existing `CreateSubmission` has one input parameter and immediately creates metadata-only pending requests. Its LP1 tests must be adapted to the new boundary without weakening ownership/inactivity assertions. The Submission factory remains historical by default. `resources/js/app.js` is currently empty apart from a comment; Livewire supplies Alpine through the existing layout, which already has a CSRF meta tag and Vite/Livewire scripts.

The S3 adapter is not directly required in `composer.json` and is absent from the lockfile's installed `packages` entries as checked on 5 October. Add only the named adapter dependency in Task 2. Dependency `suggest`/`conflict` text mentioning a name does not prove installation.

| Task | Deliverable | Main owners |
|---|---|---|
| 1 | Schema, models, checklist, historical compatibility | migrations, Submission/document models, checklist, factories |
| 2 | Real-file validator, private disks, encrypted tickets/receipts | validation rule, receipt support, config, fixture helper |
| 3 | Authenticated temporary upload/remove endpoints | temporary controller and routes |
| 4 | Whole-package creation and duplicate-safe retries | CreateSubmission, intake validation, digest/storage helpers |
| 5 | One-session upload form and classification behaviour | submission-form, upload JS, app entry point |
| 6 | Locked workspace and private audited downloads | submission-show/index, download controller/policy |
| 7 | Abandonment and orphan recovery | maintenance command and schedule |
| 8 | Whole-flow verification, QA/UAT, release handoff | tests, QA docs, handoff |

Paths below are relative to the repository root. New classes named here are part of this plan; none exists merely because it is referenced.

## Task 1 — Intake schema, document models and checklist

**Files**

- Create: `database/migrations/2026_10_05_000001_extend_submissions_for_document_intake.php`.
- Create: `database/migrations/2026_10_05_000002_create_submission_document_slots_table.php`.
- Create: `database/migrations/2026_10_05_000003_create_submission_document_versions_table.php`.
- Create: `app/Models/SubmissionDocumentSlot.php`, `app/Models/SubmissionDocumentVersion.php`.
- Create: `app/Support/SubmissionChecklist.php`.
- Create: `database/factories/SubmissionDocumentSlotFactory.php`, `SubmissionDocumentVersionFactory.php`.
- Modify: `app/Models/Submission.php`, `database/factories/SubmissionFactory.php`.
- Test: `tests/Feature/Submission/SubmissionDocumentSchemaTest.php`, `SubmissionChecklistTest.php`; extend existing `SubmissionSchemaTest.php` and `SubmissionRelationsTest.php`.

**Interfaces**

- `SubmissionChecklist::requiredKeys(string $category, string $location): array` returns keys in display order; reject unknown category/location.
- `SubmissionChecklist::label(string $key): string` returns approved display wording; reject unknown key.
- Submission: `documentSlots(): HasMany`; slot: `submission(): BelongsTo`, `versions(): HasMany`, `currentVersion(): ?SubmissionDocumentVersion`; version: `slot(): BelongsTo`, `uploader(): BelongsTo`.
- `Submission::INTAKE_AGREEMENT_TYPES = ['NDA', 'MOA', 'MOU', 'ADDENDUM']`; retain existing historical `AGREEMENT_TYPES`.
- Factory `documentIntake(): static` sets modern classifications/type and version 2, but is not a production creation bypass; document factory relationships are explicit.

- [ ] **1. Write schema/checklist failing tests.** Include the leading-FK-index check used in `SubmissionSchemaTest`, non-null submitted timestamp, default historical version 1, unique slot/version/idempotency keys, preserved FKs, integer/array casts and history deletion restrictions. Pin checklist behaviour:

```php
public function test_business_commercial_uses_industry_slots(): void
{
    $checklist = app(\App\Support\SubmissionChecklist::class);
    foreach (['local', 'international'] as $location) {
        $this->assertSame(
            $checklist->requiredKeys('industry', $location),
            $checklist->requiredKeys('business_commercial', $location),
        );
        $this->assertCount(6, $checklist->requiredKeys('industry', $location));
    }
    $this->assertSame('Memo (For CEO)', $checklist->label('memo'));
    $this->assertSame(['agreement', 'requisition_form'],
        $checklist->requiredKeys('academic', 'local'));
}
```

- [ ] **2. Run failing tests.** `php artisan test --filter="SubmissionDocumentSchemaTest|SubmissionChecklistTest"`; expected missing classes/columns, not unrelated setup failure.
- [ ] **3. Implement the three schema changes and models.** Keep all applied migrations intact. Submission extension:

```php
Schema::table('submissions', function (Blueprint $table) {
    $table->unsignedSmallInteger('intake_version')->default(1);
    $table->string('engagement_category')->nullable();
    $table->string('partner_location')->nullable();
    $table->string('moa_subtype')->nullable();
    $table->foreignId('original_agreement_id')->nullable()
        ->constrained('agreements')->restrictOnDelete();
    $table->index('original_agreement_id');
    $table->boolean('original_agreement_not_found')->default(false);
    $table->json('original_agreement_details')->nullable();
    $table->uuid('submission_key')->nullable();
    $table->string('intake_digest', 64)->nullable();
    $table->unique(['created_by', 'submission_key']);
});
```

Slots: id, required restricted `submission_id`, key string, `is_required` boolean, nullable unsigned `current_version_number`, timestamps, unique submission/key. Versions: id, restricted `slot_id`, unsigned version, nullable/null-on-delete `uploaded_by` plus index, uploader_name, disk string, unique path string, safe original_filename, mime_type, unsignedBigInteger size_bytes, SHA-256 string(64), lifecycle_label and created_at only; unique slot/version. Comment why history does not cascade-delete. Resolve current version under its slot and number; do not add a circular FK. No persisted version update/delete application API.

Each document model uses HasFactory and the house attribute configuration. Factory defaults: slot owns a historical Submission factory row, `key=agreement`, required=true, pointer=1; version owns a slot factory row, version=1, requester uploader with fictional name snapshot, `disk=documents_local`, random `lp2/permanent/{uuid}/agreement/{uuid}.pdf`, original_filename=fictional.pdf, mime_type=application/pdf, size_bytes=100, SHA-256 of fictional bytes, lifecycle_label=submitted_for_review, created_at=now. Factories create metadata only; tests explicitly put matching bytes on a fake disk when testing reads/hashes. Factory-generated objects are not actual upload evidence.

Implement the checklist with one authoritative mapping:

```php
$base = ['agreement', 'requisition_form'];
return match ($category) {
    'academic' => $base,
    'industry', 'business_commercial' => [
        'agreement', 'memo', 'requisition_form', 'due_diligence_form',
        'company_profile', $location === 'local'
            ? 'corporate_registration_local' : 'corporate_registration_international',
    ],
};
```

Validate category/location before the match. Use the labels in architecture S5; models use `#[Fillable]` for validated editable fields only. Trusted submission fields stay non-fillable.

- [ ] **4. Verify populated historical migration behaviour.** In an isolated test database, migrate only through LP1, insert fictional LOI/SEA/null-type rows plus activity, then apply these three migrations and compare every old field/activity/index/FK. Never roll back the real development DB. Test two null legacy submission keys are allowed and duplicate non-null same-owner keys fail.
- [ ] **5. Run focused tests, format changed PHP, review this slice.** `php artisan test --filter="SubmissionDocumentSchemaTest|SubmissionChecklistTest|SubmissionSchemaTest|SubmissionRelationsTest"`. Report PAO JSON and changed-file diff; keep changes uncommitted.

## Task 2 — Private storage, authentic files and user-bound upload receipts

**Files**

- Create: `config/submissions.php`, `app/Rules/SubmissionDocumentFile.php`, `app/Support/SubmissionUploadReceipts.php`.
- Create: `tests/Support/SubmissionDocumentFixtures.php` and small fictional valid/malformed PDF/DOCX fixtures in `tests/Fixtures/submissions/`.
- Modify: `config/filesystems.php`, `.env.example`; `composer.json`/`composer.lock` only for the named cloud adapter if missing.
- Test: `SubmissionDocumentValidationTest.php`, `SubmissionUploadReceiptsTest.php`, `SubmissionDocumentStorageTest.php` in `tests/Feature/Submission/`.

**Interfaces**

- `SubmissionDocumentFile implements ValidationRule`: `validate(string $attribute, mixed $value, Closure $fail): void` for UploadedFile; `inspectPath(string $path, string $originalName): array` returns `original_filename`, `mime_type`, `size_bytes`, `sha256` or throws ValidationException on `file`.
- `SubmissionUploadReceipts::issueFormTicket(): string`; `verifyFormTicket(string $ticket): array` returns authenticated `{user_id, form_id, expires_at}`.
- `issueReceipt(string $ticket, string $slotKey, array $metadata): string`; `verifyReceipt(string $receipt, string $ticket, string $slotKey): array`. Metadata contains allow-listed disk, generated path and the four inspector fields; service validates structure and actor/form/slot/expiry, not only encryption.
- Receipts/tickets resolve a freshly loaded current authenticated active requester internally. No caller-supplied actor changes authority.
- Fixture helper: `pdf(string $name = 'fictional.pdf'): UploadedFile`, `docx(string $name = 'fictional.docx'): UploadedFile`; generated files contain no real matter. Tests own and clean any scratch paths.

- [ ] **1. Write failing content and receipt tests.** Valid real files are positive controls; extension/MIME strings alone are not fixtures. Cover empty/truncated files, renamed ZIP/exe, `.doc`/`.docm`, embedded `vbaProject.bin`/macro Word content types, real ZIP-detected DOCX, 20 MiB and one-byte-over, traversal/control characters, malformed XML/package, invalid ticket encryption, copied user/form/slot receipt and expiry. Receipt example:

```php
public function test_receipt_from_another_form_is_rejected(): void
{
    $user = \App\Models\User::factory()->requester()->create();
    $this->actingAs($user->fresh());
    $receipts = app(\App\Support\SubmissionUploadReceipts::class);
    $first = $receipts->issueFormTicket();
    $second = $receipts->issueFormTicket();
    $receipt = $receipts->issueReceipt($first, 'agreement', [
        'disk' => 'documents_local',
        'path' => 'lp2/temporary/'.\Illuminate\Support\Str::uuid().'.pdf',
        'original_filename' => 'fictional.pdf', 'mime_type' => 'application/pdf',
        'size_bytes' => 100, 'sha256' => str_repeat('a', 64),
    ]);
    $this->expectException(\Illuminate\Validation\ValidationException::class);
    $receipts->verifyReceipt($receipt, $second, 'agreement');
}
```

- [ ] **2. Run failing tests.** `php artisan test --filter="SubmissionDocumentValidationTest|SubmissionUploadReceiptsTest|SubmissionDocumentStorageTest"`.
- [ ] **3. Configure named private storage and bounds.** Config includes:

```php
return [
    'disk' => env('SUBMISSION_DOCUMENTS_DISK', 'documents_local'),
    'allowed_disks' => ['documents_local', 'documents_cloud'],
    'max_bytes' => 20 * 1024 * 1024,
    'ticket_lifetime_seconds' => 24 * 60 * 60,
    'temporary_prefix' => 'lp2/temporary',
    'permanent_prefix' => 'lp2/permanent',
    'orphan_grace_seconds' => 24 * 60 * 60,
    'maintenance_disks' => array_values(array_filter(explode(',',
        env('SUBMISSION_MAINTENANCE_DISKS', 'documents_local')))),
];
```

`documents_local`: local, dedicated private root, visibility private, serve false, throw true. `documents_cloud`: s3, DOCUMENTS_AWS_* environment credentials, private bucket, throw true; omit per-object visibility/ACL write options for R2. Preserve existing disk names/options used elsewhere. `.env.example` contains placeholders only and describes explicit Cloud binding to these variables. Livewire's generic 12 MB path is not used by the dedicated transport; do not change global Livewire upload settings for unrelated components. Our endpoints enforce 20 MB directly.

Set maintenance disks explicitly for the environment: local development uses documents_local; production uses documents_cloud. During a storage migration, list all genuinely configured retained identities whose generated namespaces require maintenance, never an unconfigured credentialless cloud disk. Validate this list against allowed_disks.

If absent, the only added package is `league/flysystem-aws-s3-v3:^3.0`, using Composer's documented install command during authorised implementation. Inspect the resulting dependency diff and run normal checks; sandbox network failure requires the normal tool escalation. Cloud credentials and runtime ZIP support are release prerequisites, not reasons to skip tests or weaken validation.

- [ ] **4. Implement the validator and fixture inspector.** Use actual filesize, finfo, hash_file and normalised last extension. Reject over-limit/zero bytes before package inspection. Sanitize the display filename separately from generated storage keys. PDF must have a PDF header/type and terminal EOF marker, rejecting obvious truncation; do not claim exhaustive PDF/malware inspection. DOCX must be a ZIP with `[Content_Types].xml` and `word/document.xml`, standard non-macro Word main content type, well-formed required XML, and no macro entry/type. Use LIBXML_NONET, reject DTD/entity declarations, never extract paths to a served directory. Bound inspection: at most 2,000 entries, 200 MiB declared uncompressed total, 1 MiB content-types XML and 64 MiB main document XML; record these technical resource limits in help/handoff if representative UAT needs adjustment. Rejected package throws a field validation error rather than a raw ZIP exception.

Fixture DOCX construction must use real OOXML:

```php
$zip = new \ZipArchive;
$zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
$zip->addFromString('[Content_Types].xml',
    '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'.
    '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'.
    '<Default Extension="xml" ContentType="application/xml"/>'.
    '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'.
    '</Types>');
$zip->addFromString('_rels/.rels',
    '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.
    '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'.
    '</Relationships>');
$zip->addFromString('word/document.xml',
    '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Fictional document</w:t></w:r></w:p></w:body></w:document>');
$zip->close();
```

PDF fixtures contain a real fictional one-page PDF with correct object/xref offsets, not a fake MIME wrapper. Build the content once and use correct offsets:

```php
$stream = "BT /F1 12 Tf 72 720 Td (Fictional document) Tj ET\n";
$objects = [
    '<< /Type /Catalog /Pages 2 0 R >>',
    '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
    '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
    '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
    '<< /Length '.strlen($stream).">>\nstream\n".$stream.'endstream',
];
$bytes = "%PDF-1.4\n";
$offsets = [];
foreach ($objects as $index => $object) {
    $offsets[] = strlen($bytes);
    $bytes .= ($index + 1)." 0 obj\n".$object."\nendobj\n";
}
$xref = strlen($bytes);
$bytes .= "xref\n0 6\n0000000000 65535 f \n";
foreach ($offsets as $offset) { $bytes .= sprintf("%010d 00000 n \n", $offset); }
$bytes .= "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
file_put_contents($path, $bytes);
return new \Illuminate\Http\UploadedFile($path, $name, 'application/pdf', null, true);
```

Fixture helper owns an isolated test temp directory, registers created paths for cleanup, and wraps generated DOCX analogously with test UploadedFile. For size-boundary PDF fixtures, pad within legal PDF comments before the terminal EOF, keeping the document recognisable. Explicit ZipArchive open/add/close failure is a fixture/setup failure; do not skip the format assertions because ZIP support is missing.

- [ ] **5. Implement encrypted receipts.** Use Crypt::encryptString JSON and Crypt::decryptString with a versioned payload kind (`lp2_form`/`lp2_upload`). UUID form_id is issued on each new mount. Validate expiry against now, exact current user, matching form_id and exact slot key; validate disk allow-list and generated prefix. Reject malformed/cross-owner references with a generic field error and no content. Encryption keys/paths never appear in rendered metadata or logs visible to staff.

```php
$actor = auth()->user()?->fresh();
abort_unless($actor !== null, 403);
Gate::forUser($actor)->authorize('create', Submission::class);
return Crypt::encryptString(json_encode([
    'kind' => 'lp2_form', 'version' => 1, 'user_id' => $actor->id,
    'form_id' => (string) Str::uuid(),
    'expires_at' => now()->timestamp + config('submissions.ticket_lifetime_seconds'),
], JSON_THROW_ON_ERROR));
```

On verification, catch decrypt/JSON failures as generic ValidationException; require kind/version and typed fields before reading keys. Form user_id must strictly match the refreshed actor ID and expires_at must exceed current timestamp. Receipt payload adds kind=lp2_upload, slot_key and inspector metadata; require its form_id/user_id/expiry match the verified form ticket, allowed slot/disk, safe filename, positive size within max_bytes, lowercase 64-hex hash and generated temporary namespace. Do not issue a receipt for an arbitrary caller path outside that namespace.

- [ ] **6. Run focused tests, format and review.** Confirm invalid receipts write nothing and positive local-disk checks disable public serving. Cloud SDK private behaviour needs the later integration check; a fake disk is not production proof.

## Task 3 — Authenticated temporary upload and removal

**Files**

- Create: `app/Http/Controllers/SubmissionTemporaryUploadController.php`.
- Modify: `routes/web.php`.
- Test: `tests/Feature/Submission/SubmissionTemporaryUploadTest.php`.

**Interfaces**

- `store(Request $request): JsonResponse`: POST `/submissions/intake/uploads`, name `submissions.intake.uploads.store`. Input `form_ticket`, `slot_key`, `engagement_category`, `partner_location`, `file`.
- `destroy(Request $request): JsonResponse`: DELETE same path, name `submissions.intake.uploads.destroy`. Input `form_ticket`, `slot_key`, `receipt`; returns 204.
- Store response `{receipt, document: {original_filename, mime_type, size_bytes}}`. Do not return object path, bucket credentials or readable URL.
- Consumes Task 1 checklist and Task 2 inspector/receipts/config. Produces only a temporary private object and authenticated receipt, no official rows.

- [ ] **1. Write failing HTTP tests.** Owner upload is a positive control; guest, Viewer, Legal/Admin, inactive requester, mismatched ticket actor, invalid slot/classification/file and expired ticket are denied. Foreign/form/slot removal is denied with original bytes preserved. Assert all official row tables remain empty throughout.

```php
public function test_upload_creates_no_official_submission(): void
{
    \Illuminate\Support\Facades\Storage::fake('documents_local');
    $this->actingAs(\App\Models\User::factory()->requester()->create()->fresh());
    $ticket = app(\App\Support\SubmissionUploadReceipts::class)->issueFormTicket();
    $this->postJson(route('submissions.intake.uploads.store'), [
        'form_ticket' => $ticket, 'slot_key' => 'agreement',
        'engagement_category' => 'academic', 'partner_location' => 'local',
        'file' => \Tests\Support\SubmissionDocumentFixtures::pdf(),
    ])->assertOk()->assertJsonStructure(['receipt', 'document']);
    $this->assertDatabaseCount('submissions', 0);
    $this->assertDatabaseCount('submission_document_slots', 0);
    $this->assertDatabaseCount('submission_document_versions', 0);
    $this->assertDatabaseCount('submission_activities', 0);
}
```

- [ ] **2. Run failing tests.** `php artisan test --filter=SubmissionTemporaryUploadTest`.
- [ ] **3. Add requester-only routes inside auth/web.** Place before the dynamic detail route for clarity; preserve existing route role groups:

```php
Route::middleware(['role:requester', 'throttle:30,1'])->group(function () {
    Route::post('/submissions/intake/uploads', [SubmissionTemporaryUploadController::class, 'store'])
        ->name('submissions.intake.uploads.store');
    Route::delete('/submissions/intake/uploads', [SubmissionTemporaryUploadController::class, 'destroy'])
        ->name('submissions.intake.uploads.destroy');
});
```

Controller independently authorises `create` on Submission, refetching current user state before writes. Validate the form ticket and slot membership in trusted `requiredKeys`; validate one file and write a random key under `lp2/temporary/{form_uuid}/{object_uuid}.{validated_extension}` with the configured allow-listed disk. Compute metadata from actual bytes. If store/receipt issuance fails, remove only the new object, log cleanup failure internally, return a safe 503 JSON message. Invalid input returns 422; no raw filesystem paths.

Destroy verifies current actor/ticket/receipt before deleting only its authenticated temp key; a missing already-removed object is a successful no-op. It may not remove permanent keys. A storage deletion failure returns a safe retry message and leaves scheduled cleanup to recover it.

- [ ] **4. Run focused tests and verify transport protection.** Standard test mode bypasses CSRF, so assert middleware presence and test actual missing/valid CSRF requests in the isolated browser session in Task 8. Do not present ordinary postJson as proof of CSRF enforcement. Include upload/write failure and two successive users with copied receipts. Format/review; no public preview endpoint is added.

## Task 4 — Atomic package creation and retry recovery

**Files**

- Create: `app/Support/SubmissionIntake.php`, `app/Support/SubmissionPackageDigest.php`, `app/Actions/StoreSubmissionPackage.php`.
- Modify: `app/Actions/CreateSubmission.php`, `app/Models/SubmissionActivity.php`.
- Test: `tests/Feature/Submission/SubmissionPackageCreationTest.php`, `SubmissionRetryTest.php`.
- Modify tests: `SubmissionCreationTest.php`, `SubmissionValidationTest.php` to use complete modern intake/receipts/tickets while retaining denied-role and injected-field controls.

**Interfaces**

- `SubmissionIntake::validate(array $input, bool $checkOriginalVisibility = true): array`: returns only validated/normalised fields; role authority remains in caller.
- `SubmissionPackageDigest::make(array $validated, array $metadataBySlot): string`: deterministic SHA-256 over key-sorted fields and per-slot safe filename/type/bytes/hash, not random object path or receipt ciphertext.
- `StoreSubmissionPackage::__invoke(array $verifiedUploads): array`: stores inspected bytes under fresh permanent attempt UUID, returns metadata keyed by slot; `cleanup(array $storedFiles): void` removes only those new objects, logs failures. It never modifies old versions. Stage cloud streams through bounded temporary scratch files and clean scratch in finally.
- `CreateSubmission::__invoke(array $input, array $uploadReceipts, string $formTicket): Submission`: derives current actor and trusted state internally.
- Activity constants: existing `TYPE_SUBMISSION_CREATED`, new `TYPE_DOCUMENT_UPLOADED = 'document_uploaded'`, `TYPE_DOCUMENT_DOWNLOADED = 'document_downloaded'` (consumed by Task 6).

- [ ] **1. Write failing creation/retry tests.** Missing/extra/wrong-slot/foreign receipts fail with no rows or permanent files; temporary originals remain usable on failure. Test every category/location/type, optional MOA subtype, Addendum select/fallback exclusivity, hidden original ID, inactive/TBD campus, injected owner/status/link/time/disk/version values, and storage/audit/DB failure. Lost-success regression:

```php
public function test_retry_after_temp_cleanup_returns_same_submission(): void
{
    [$input, $receipts, $ticket] = $this->package('academic', 'local');
    $first = app(\App\Actions\CreateSubmission::class)($input, $receipts, $ticket);
    \Illuminate\Support\Facades\Storage::disk('documents_local')
        ->deleteDirectory(config('submissions.temporary_prefix'));
    $events = \App\Models\SubmissionActivity::count();
    $second = app(\App\Actions\CreateSubmission::class)($input, $receipts, $ticket);
    $this->assertSame($first->id, $second->id);
    $this->assertDatabaseCount('submissions', 1);
    $this->assertDatabaseCount('submission_document_versions', 2);
    $this->assertSame($events, \App\Models\SubmissionActivity::count());
}
```

Use this helper in the creation/retry test class (fake the private disk once in setUp, not again during a retry). It calls the real Task 3 endpoint; there is no alternate production creation API:

```php
private function package(string $category, string $location): array
{
    if (auth()->user() === null) {
        $this->actingAs(\App\Models\User::factory()->requester()->create()->fresh());
    }
    $campus = \App\Models\Campus::firstOrCreate(['code' => 'TEST'], [
        'name' => 'Fictional Test Campus', 'is_institute' => false,
        'sort_order' => 1, 'is_active' => true,
    ]);
    $input = [
        'title' => 'Fictional submission', 'campus_id' => $campus->id,
        'partner_name' => 'Fictional Partner', 'purpose' => 'Fictional collaboration',
        'agreement_type' => 'MOU', 'engagement_category' => $category,
        'partner_location' => $location,
    ];
    $ticket = app(\App\Support\SubmissionUploadReceipts::class)->issueFormTicket();
    $receipts = [];
    foreach (app(\App\Support\SubmissionChecklist::class)->requiredKeys($category, $location) as $key) {
        $receipts[$key] = $this->postJson(route('submissions.intake.uploads.store'), [
            'form_ticket' => $ticket, 'slot_key' => $key,
            'engagement_category' => $category, 'partner_location' => $location,
            'file' => \Tests\Support\SubmissionDocumentFixtures::pdf($key.'.pdf'),
        ])->assertOk()->json('receipt');
    }
    return [$input, $receipts, $ticket];
}
```

Add changed-title or changed-file same-ticket rejection with original rows/hashes unchanged, expiry boundary, different actor replay and unique-constraint race controls.

- [ ] **2. Run failing tests.** `php artisan test --filter="SubmissionPackageCreationTest|SubmissionRetryTest"`.
- [ ] **3. Implement authoritative intake validation.** Preserve 255/5,000 existing text/campus rules; require new type/category/location enums. Trim strings and normalise inapplicable fields to null/false. MOA subtype max 255. Addendum requires existing scoped original ID XOR not-found details; fallback fields title/partner/approximate_date/campus_department max 255 each and notes nullable max 2,000, amendment purpose uses required `purpose`. Validate unknown fallback keys away. Existing-record selection query respects pending scope and includes normally accessible archived rows; never unscoped. A changed visibility gets a generic original-selection error.

First validate syntax/normalise with `checkOriginalVisibility=false` for duplicate comparison. On genuinely new creation, call with true and validate active campus/current original visibility again before transaction commit. Returning an existing owned submission does not re-disclose a currently hidden original agreement through a lookup.

```php
$validated = Validator::make($normalised, [
    'title' => ['required', 'string', 'max:255'],
    'campus_id' => ['required', 'integer', Rule::exists('campuses', 'id')
        ->where('is_active', true)->whereNot('code', 'TBD')],
    'partner_name' => ['required', 'string', 'max:255'],
    'purpose' => ['required', 'string', 'max:5000'],
    'agreement_type' => ['required', Rule::in(Submission::INTAKE_AGREEMENT_TYPES)],
    'engagement_category' => ['required', Rule::in(['academic', 'industry', 'business_commercial'])],
    'partner_location' => ['required', Rule::in(['local', 'international'])],
    'moa_subtype' => ['nullable', 'string', 'max:255'],
    'original_agreement_id' => ['nullable', 'integer'],
    'original_agreement_not_found' => ['required', 'boolean'],
    'original_agreement_details' => ['nullable', 'array:title,partner,approximate_date,campus_department,notes'],
])->validate();
```

Before this validator normalise absent not-found to false, absent/inapplicable subtype/details/ID to null; only clear original fields when type is not ADDENDUM. For ADDENDUM explicitly enforce XOR, the four required fallback strings/max lengths and optional notes limit, returning field-specific errors. In existing-record mode use a scoped `Agreement::query()->find(...)` when checkOriginalVisibility=true; absence yields a generic field error. Validate the same field shapes and lengths on duplicate comparison without exposing a currently hidden original row.

- [ ] **4. Implement receipt verification, digest and duplicate lookup before object reads.** Authenticate/authorise first. Verify form ticket and every receipt, exact required set and matching actor/form/slot. Compute digest from authenticated receipt metadata. Existing `(created_by, submission_key)` + same digest returns existing Submission; different digest errors. Do not read expired/removed temp objects merely to recover an already successful matching request.

```php
$existing = Submission::query()->where('created_by', $actor->id)
    ->where('submission_key', $form['form_id'])->first();
if ($existing !== null) {
    if (! hash_equals($existing->intake_digest, $digest)) {
        throw ValidationException::withMessages([
            'submission' => 'This form was already submitted. Open your submission or start a new form.',
        ]);
    }
    return $existing;
}
```

- [ ] **5. Implement permanent storage and one database transaction.** For new creation, read each actual temp object, inspect bytes again, compare actual hash/size/type/name with receipt metadata and digest; refuse missing/changed content. Do not rely on a receipt hash as proof the object is unchanged. Write only fresh permanent keys `lp2/permanent/{attempt_uuid}/{slot_key}/{object_uuid}.{extension}` and check every write. Track new objects incrementally so the third-file failure removes the first two. No write overwrites a known key.

```php
return DB::transaction(function () use ($actor, $validated, $stored, $form, $digest) {
    $submission = new Submission;
    $submission->fill($validated);
    $submission->created_by = $actor->id;
    $submission->status = Submission::STATUS_PENDING;
    $submission->submitted_at = now();
    $submission->intake_version = 2;
    $submission->submission_key = $form['form_id'];
    $submission->intake_digest = $digest;
    $submission->save();
    $versionIds = [];
    foreach ($stored as $key => $metadata) {
        $slot = $submission->documentSlots()->create([
            'key' => $key, 'is_required' => true,
        ]);
        $version = $slot->versions()->create([
            ...$metadata, 'version_number' => 1,
            'uploaded_by' => $actor->id, 'uploader_name' => $actor->name,
            'lifecycle_label' => 'submitted_for_review', 'created_at' => now(),
        ]);
        $slot->current_version_number = 1;
        $slot->save();
        $versionIds[$key] = $version->id;
    }
    app(RecordSubmissionActivity::class)($submission, $actor,
        SubmissionActivity::TYPE_SUBMISSION_CREATED, 'Submission created', [
            'engagement_category' => $validated['engagement_category'],
            'partner_location' => $validated['partner_location'],
            'version_ids' => $versionIds,
        ]);
    foreach ($versionIds as $key => $id) {
        app(RecordSubmissionActivity::class)($submission, $actor,
            SubmissionActivity::TYPE_DOCUMENT_UPLOADED, 'Document uploaded', [
                'slot_key' => $key, 'version_id' => $id,
                'lifecycle_label' => 'submitted_for_review',
            ]);
    }
    return $submission;
});
```

For each slot create restricted child, create Version 1 with `submitted_for_review`, authenticated uploader snapshot and metadata, then set pointer. Record all audit in the same transaction using existing recorder. Recheck fresh actor and current intake references before saves. On failure cleanup only newly written permanent objects and rethrow a safe mapped error; preserve temporary objects. After commit, best-effort remove temp originals; cleanup failure cannot turn committed success into an error/duplicate. Scheduled maintenance recovers remnants.

- [ ] **6. Implement concurrent-race recovery.** Catch only recognised unique-key violation on the specific owner/submission-key constraint. Outside the rolled-back transaction, reload owned row and compare digest. Same digest returns it after removing the losing attempt's objects; different digest rejects. A generic DB exception is not evidence of a duplicate and must propagate after compensation. Use real constraint tests plus an injected competing-create hook/test double to deterministically exercise the collision branch; verify losing object cleanup and no duplicated events. Production MySQL concurrency is a release check, not claimed from one in-memory connection.

- [ ] **7. Run creation/validation regression suites, format, review.** Update old tests requiring one reflection parameter and nullable new type only where LP2 deliberately changes that boundary; preserve historical factory/read tests. Focused filter `SubmissionPackageCreationTest|SubmissionRetryTest|SubmissionCreationTest|SubmissionValidationTest`. All invalid writes must assert no official rows/permanent objects with corresponding positive controls.

## Task 5 — One-session requester form and upload UI

**Files**

- Modify: `resources/views/livewire/submissions/submission-form.blade.php`, `resources/js/app.js`.
- Create: `resources/js/submission-uploads.js`.
- Modify test: `tests/Feature/Submission/SubmissionFormTest.php`; create `SubmissionIntakeFormTest.php`.

**Interfaces**

- Component public locked `formTicket` is issued server-side in mount, never regenerated in boot/update. `boot()` continues create authorisation on every request.
- Fields add category/location/moa subtype/conditional original fields; `uploadReceipts` maps trusted slot keys to encrypted references. `save(): void` calls Task 4 with current validated form values, receipt map and ticket and redirects after success.
- Server actions `acceptUpload(string $slotKey, string $receipt): void`, `removeSelection(string $slotKey): void`, `applyClassification(string $category, string $location, bool $confirmed): void` reauthorise and verify exact scope. Server computed checklist/readiness uses Task 1, not a browser-supplied count.
- JS registers `Alpine.data('submissionUploads', ...)` on `alpine:init` using Livewire's bundled Alpine. Each instance has `upload(slotKey, file)`, `remove(slotKey)` and `pendingCount`; no separate Alpine package/start call.

- [ ] **1. Write failing component tests.** Three category labels, four new types and blank prompt; subtype conditional display; Addendum modes; exact Memo wording/readiness; a forged receipt cannot make readiness complete; all actions reauthorise; new mount has a fresh ticket and empty receipts, update keeps ticket. Switching Industry/Business preserves six authenticated references; Local/International clears only changed corporate slot with confirmation. Add explicit review-focus test for an upload error leaving an old receipt intact.

```php
public function test_new_form_does_not_restore_unsubmitted_selections(): void
{
    $this->actingAs(\App\Models\User::factory()->requester()->create()->fresh());
    $first = \Livewire\Livewire::test('submissions.submission-form');
    $ticket = $first->get('formTicket');
    $second = \Livewire\Livewire::test('submissions.submission-form');
    $this->assertNotSame($ticket, $second->get('formTicket'));
    $second->assertSet('uploadReceipts', []);
    $this->assertDatabaseCount('submissions', 0);
}
```

- [ ] **2. Run failing tests.** `php artisan test --filter="SubmissionIntakeFormTest|SubmissionFormTest"`.
- [ ] **3. Implement ticket/receipt state and trusted form actions.** Use `#[Locked]` on formTicket. Do not trust public receipt properties, filename metadata, readiness booleans or selected original model references; verify again within every server action and Task 4. Never store unsubmitted form values in sessions/database/localStorage. Original agreement picker query uses the existing authorised scope with limited fields, pagination and no foreign pending rows; on selected original becoming hidden require reselection/not-found. No external partner or Viewer portal controls.

```php
#[\Livewire\Attributes\Locked]
public string $formTicket;
public array $uploadReceipts = [];
public string $engagement_category = '';
public string $partner_location = '';

public function mount(): void
{
    $this->formTicket = app(\App\Support\SubmissionUploadReceipts::class)->issueFormTicket();
}

public function acceptUpload(string $slotKey, string $receipt): void
{
    Gate::authorize('create', Submission::class);
    $keys = app(\App\Support\SubmissionChecklist::class)
        ->requiredKeys($this->engagement_category, $this->partner_location);
    abort_unless(in_array($slotKey, $keys, true), 404);
    app(\App\Support\SubmissionUploadReceipts::class)
        ->verifyReceipt($receipt, $this->formTicket, $slotKey);
    $this->uploadReceipts[$slotKey] = $receipt;
}
```

Boot/create authorisation stays on every request. Add public optional moa_subtype string, nullable original_agreement_id integer, original_agreement_not_found bool and original_agreement_details array using the names in Task 4. `applyClassification` validates both new values, computes old/new key sets, requires confirmed=true if clearing any selected receipt, then sets classification and retains only shared applicable keys. `removeSelection` verifies its existing receipt for the current form/actor/slot before unsetting; file removal goes through Task 3's endpoint. Server displays filename/type/size only from verified receipt metadata.

Classification change is staged until the user confirms clearing obsolete selected requirements. Industry/Business shared keys keep exact references. Local/International resets corporate reference. Clear inapplicable MOA/Addendum fields. Do not delete an old temporary selection until replacement is validated and accepted; rejected replacement only changes its displayed error. If removing an old object fails, clear only the intended selection and let scheduled cleanup retry, without corrupting another file.

- [ ] **4. Implement one-file-at-a-time upload JS and concise rows.** Use queued XMLHttpRequest for real progress, CSRF header from the existing meta tag and Accept application/json. Read current category/location/ticket when queued work starts; bind the completion to the same still-applicable slot/classification or discard and retire its new receipt. Prevent late upload responses from restoring a removed requirement. Keep successful prior receipt until new success. Example transport:

```js
const data = new FormData();
data.append('form_ticket', ticket);
data.append('slot_key', slotKey);
data.append('engagement_category', category);
data.append('partner_location', location);
data.append('file', file);
const request = new XMLHttpRequest();
request.open('POST', uploadUrl);
request.setRequestHeader('Accept', 'application/json');
request.setRequestHeader('X-CSRF-TOKEN', document.querySelector('meta[name="csrf-token"]').content);
request.upload.onprogress = (event) => {
    if (event.lengthComputable) states[slotKey].progress = Math.round(event.loaded / event.total * 100);
};
request.send(data);
```

Wire load/error/abort handlers explicitly: 200 verifies/stores receipt through component action; 422 inline file errors; 401/403/419 end interaction with login/session message; 413 says file/request too large; network/503 provides retry preserving earlier selections. Parse JSON defensively so a proxy HTML failure never crashes the form. File controls show label/short purpose, PDF/DOCX 20 MB, selected safe name/size, state and Replace/Remove. Disable submit while pending uploads; final server validation remains authoritative. No inline document preview/public URL. Alert unfinished-page departure without adding restore/autosave.

- [ ] **5. Implement readiness and complete submission.** Missing base/conditional fields and required valid receipt slots appear once in readiness. `save()` submits only applicable whitelisted fields. Map ValidationException to field/readiness errors; storage failure to a concise retry alert. On success flash Submission sent to Legal and navigate to the returned owned detail. Repeat success recovery uses the same ticket. Audit/version labels begin after official creation.

```php
$submission = app(\App\Actions\CreateSubmission::class)([
    'title' => $this->title, 'campus_id' => $this->campus_id,
    'partner_name' => $this->partner_name, 'purpose' => $this->purpose,
    'agreement_type' => $this->agreement_type,
    'engagement_category' => $this->engagement_category,
    'partner_location' => $this->partner_location, 'moa_subtype' => $this->moa_subtype,
    'original_agreement_id' => $this->original_agreement_id,
    'original_agreement_not_found' => $this->original_agreement_not_found,
    'original_agreement_details' => $this->original_agreement_details,
], $this->uploadReceipts, $this->formTicket);
session()->flash('status', 'Submission sent to Legal.');
$this->redirectRoute('submissions.show', $submission, navigate: true);
```

- [ ] **6. Run focused tests and browser interactions.** No JS test framework is introduced. Browser check classification warning/cancel, queued/late responses, failed replacement, network retry, duplicate click, no resume and keyboard controls in Task 8. Build assets during implementation, format changed PHP and review. Vite may require approved network access for the font.

## Task 6 — Locked workspace, version history and audited downloads

**Files**

- Create: `app/Http/Controllers/SubmissionDocumentDownloadController.php`.
- Modify: `app/Policies/SubmissionPolicy.php`, `routes/web.php`, `resources/views/livewire/submissions/submission-show.blade.php`, `submissions-index.blade.php`.
- Test: `SubmissionDocumentDownloadTest.php`, `SubmissionDocumentWorkspaceTest.php`; update existing `SubmissionShowTest.php`, `SubmissionsIndexTest.php`, `PortalAccessTest.php` when assertions legitimately change.

**Interfaces**

- Invokable download controller: `__invoke(Submission $submission, int $version): StreamedResponse`. Route `/submissions/{submission}/documents/{version}/download`, `submissions.documents.download`, inside existing portal auth/role group.
- Policy `downloadDocuments(User $user, Submission $submission): Response` follows existing active view rule; no upload/update permission is added.
- Workspace computed document slots eagerly load versions/uploader; currentVersion uses Task 1 relation. Old/current downloads remain distinct and authorised.

- [ ] **1. Write failing download/workspace tests with correct-byte positive controls.** Owner, Legal and Admin get bytes/attachment/one audit; other requester/wrong parent gets 404 with no foreign data; guest redirects and Viewer denied; inactive refused; unknown version/missing object and audit failure send no bytes. Safe headers, newline/traversal filename, cloud-disk identity after default switch, and immutable old version download are covered. Fixture may manually create version 2 to prove history reading; no LP2 replacement action is exposed.

```php
public function test_version_from_another_parent_never_streams(): void
{
    \Illuminate\Support\Facades\Storage::fake('documents_local');
    $owner = \App\Models\User::factory()->requester()->create();
    $this->actingAs($owner->fresh());
    $owned = \App\Models\Submission::factory()->create(['created_by' => $owner->id]);
    $foreignVersion = \App\Models\SubmissionDocumentVersion::factory()->create([
        'original_filename' => 'Fictional Foreign Filename.pdf',
    ]);
    \Illuminate\Support\Facades\Storage::disk($foreignVersion->disk)
        ->put($foreignVersion->path, 'Fictional foreign bytes');
    $before = \App\Models\SubmissionActivity::count();
    $this->get(route('submissions.documents.download', [
        'submission' => $owned->id, 'version' => $foreignVersion->id,
    ]))->assertNotFound()->assertDontSee('Fictional Foreign Filename');
    $this->assertSame($before, \App\Models\SubmissionActivity::count());
}
```

For positive downloads write real fixture bytes and matching hash/size metadata on the fake disk. Never infer role safety from hidden buttons alone.

- [ ] **2. Run failing tests.** `php artisan test --filter="SubmissionDocumentDownloadTest|SubmissionDocumentWorkspaceTest"`.
- [ ] **3. Implement parent-constrained downloads and safe streaming.** Authorise current actor, query version through this submission's slot IDs; check saved disk allow-list and generated permanent prefix; open `readStream` before recording initiation event. Missing/unreadable -> safe 404 plus internal operational log, no success event. Record document_downloaded with slot/version/lifecycle before returning bytes; close stream if audit throws. Stream as attachment using sanitised filename and type with private,no-store and nosniff. Ensure stream closes in callback finally; audit means initiation, not confirmed client receipt.

```php
$stream = Storage::disk($version->disk)->readStream($version->path);
if (! is_resource($stream)) {
    abort(404);
}
try {
    app(RecordSubmissionActivity::class)($submission, $actor,
        SubmissionActivity::TYPE_DOCUMENT_DOWNLOADED, 'Document downloaded', [
            'slot_id' => $version->slot_id, 'version_id' => $version->id,
            'lifecycle_label' => $version->lifecycle_label,
        ]);
} catch (\Throwable $exception) {
    fclose($stream);
    throw $exception;
}
return response()->streamDownload(function () use ($stream) {
    try { fpassthru($stream); } finally { fclose($stream); }
}, $version->original_filename, [
    'Content-Type' => $version->mime_type,
    'Cache-Control' => 'private, no-store',
    'X-Content-Type-Options' => 'nosniff',
]);
```

- [ ] **4. Implement locked workspace and compatible lists.** In show boot reload Submission by ID before Gate::authorize, never rely on restored model state; mount independently authorises. Render Version 1 Submitted for Review, safe metadata, current/expandable historical downloads and concise Locked while Legal reviews; `<x-datetime>` timestamps. Historical intake_version 1 gets compatibility note without checklist backfill/upload control. Pending displays Pending Legal Review and Waiting for Legal. Keep index owner query before pagination/counts and its submitted-date order; no draft rows or Register access change.
- [ ] **5. Run focused/portal regression tests, format and review.** Include a mounted component followed by actor role/inactivity change and malicious `$refresh`; no foreign content. Every attempted post-submit mutation remains denied; no accidental method is exposed just because it existed in a trait.

## Task 7 — Safe temporary and orphan cleanup

**Files**

- Create: `app/Console/Commands/CleanupSubmissionDocumentFiles.php`.
- Modify: `routes/console.php`.
- Test: `tests/Feature/Submission/SubmissionDocumentCleanupTest.php`.

**Interfaces**

- Command `submissions:cleanup-documents {--dry-run}` operates only the explicitly configured `maintenance_disks` subset of allow-listed document disks and `lp2/temporary` / `lp2/permanent` prefixes.
- Temporary candidates expire after 24 hours; permanent candidates require no version-row reference and age over the 24-hour grace period. Use provider lastModified; if unavailable/future/invalid skip and log instead of guessing.
- Schedule hourly with `withoutOverlapping()`; production scheduler operation must be verified at release. No real cleanup is run during planning.

- [ ] **1. Write failing cleanup tests.** Expired temp/unreferenced permanent objects eligible; referenced Version 1/2 survive even old; recent/in-flight object survives; unrelated prefix/disk untouched; dry run leaves all bytes; list/delete failure logged and subsequent items handled; new reference inserted between scan and delete protects object. Review-focus example:

```php
public function test_old_referenced_version_is_never_cleaned(): void
{
    \Illuminate\Support\Facades\Storage::fake('documents_local');
    config(['submissions.maintenance_disks' => ['documents_local']]);
    $version = \App\Models\SubmissionDocumentVersion::factory()->create();
    $disk = $version->disk;
    $path = $version->path;
    \Illuminate\Support\Facades\Storage::disk($disk)->put($path, 'Fictional bytes');
    touch(\Illuminate\Support\Facades\Storage::disk($disk)->path($path), now()->subDays(2)->timestamp);
    $this->artisan('submissions:cleanup-documents')->assertSuccessful();
    \Illuminate\Support\Facades\Storage::disk($disk)->assertExists($path);
    $this->assertDatabaseHas('submission_document_versions', ['id' => $version->id]);
}
```

Implement test time/lastModified using controlled fakes/adapters/test mocks, not sleeps. Fixtures always use faked private disks; touch only paths returned by the fake disk, never actual production paths.

- [ ] **2. Run failing tests.** `php artisan test --filter=SubmissionDocumentCleanupTest`.
- [ ] **3. Implement namespace/age/reference checks and dry run.** Enumerate bounded prefixes; do not recursively list arbitrary storage roots. Check each candidate's age and reference immediately before deleting. Permanent recovery queries exact `(disk,path)` against all versions, including old non-current ones. Never delete rows or apply record retention. Success/failure counts and generated keys go to internal logs; dry run prints counts without confidential filenames or credentials. Grace period outlasts any request; expired tickets prevent starting a new operation on an expired temp object. Future-clock or inconsistent timestamps skip safely.

```php
if ($modifiedAt >= now()->subSeconds(config('submissions.orphan_grace_seconds'))->timestamp) {
    continue;
}
if (SubmissionDocumentVersion::query()->where('disk', $diskName)->where('path', $path)->exists()) {
    continue;
}
if (! $this->option('dry-run')) {
    $disk->delete($path);
}
```

Recheck references directly at deletion, not only the original scan. Temp handling uses its separate prefix/age policy. Keep rollback cleanup focused on its own attempt; scheduled recovery handles only abandoned remnants.
- [ ] **4. Schedule and verify.** Add the command hourly without overlap alongside existing archive schedule; do not replace it. Run focused cleanup tests, format and review. Actual Cloud scheduler/storage evidence is Task 8 release work under separate authorisation.

```php
Schedule::command(\App\Console\Commands\CleanupSubmissionDocumentFiles::class)
    ->hourly()->withoutOverlapping();
```

## Task 8 — Whole-flow verification, UAT and release handoff

**Files**

- Create: `docs/handoff-lp2.md` with distinct implementation, local verification, UAT and production sections.
- Modify: `docs/qa/test-cases.md`, `traceability-matrix.md`, `test-plan.md`, `docs/implementation-plan-lp2.md` checkbox/evidence state.
- Test: new `tests/Feature/Submission/SubmissionIntakeJourneyTest.php`; retain all existing regression suites.

**Interfaces:** Consumes all preceding tasks; produces actual evidence and a reviewed implementation diff. This task does not itself permit production provisioning, paid resources, commits or deployment.

- [ ] **1. Write/run a complete failing journey test before closing integration gaps.** Create an Industry/Business package through real temporary HTTP endpoints, submit through the component, request detail and download as owner/Legal, deny another requester, then inspect storage/rows/audit. No submitted replacement method or incomplete metadata-only create path is usable. Test historical LP1 route and Register positive controls.

```php
$this->assertDatabaseHas('submissions', [
    'created_by' => $requester->id, 'status' => 'pending', 'intake_version' => 2,
]);
$this->assertDatabaseCount('submission_document_slots', 6);
$this->assertDatabaseCount('submission_document_versions', 6);
$this->assertSame(1, SubmissionActivity::where('type', 'submission_created')->count());
```

The complete test must first perform the endpoints/submit/download described, not fabricate only the final DB state. Audit counts distinguish six upload events and explicit download initiations. Fix discovered gaps only within this scope and repeat owning tests.

- [ ] **2. Run final automated checks.** Format all changed PHP/Blade files with Pint and run `composer test`; parse compact PAO JSON for result/counts. Run `npm run build` for JS/UI changes, requesting normal network escalation if the font build is blocked. Inspect complete tracked and untracked diff; no working DB, secrets, prototype import or out-of-scope feature is included. Record actual results once changes settle; do not invent totals in advance.

- [ ] **3. Perform an isolated local browser walkthrough with fictional accounts/files.** Cover all three categories/both locations, four types, MOA subtype and Addendum modes, correct checklist/labels, keyboard use, real progress, warn/cancel classification changes, late upload response, Replace/Remove failure, network retry/lost response, repeated click, no resume, locked workspace, old-version download, foreign parent/owner, deactivation during upload/submission/download, missing/valid CSRF, proxy HTML 413/503 handling, and private object URL/preview denial. Use an isolated database/environment rather than editing real development records. Record evidence and cleanup that test environment after use.

- [ ] **4. Record representative-file UAT.** Intan/Siti use approved fictional or sanitised scanned PDF/DOCX, especially above 12 MB and near 20 MB. Check practical terminology, clear errors, successful submission/download and no blocked confidential access. Track failures and retest fixes. No claim of UAT completion until their actual walkthrough occurs.

- [ ] **5. Prepare the concrete production configuration for Amir's review.** Reinspect deployed baseline and bucket absence/presence; show selected private bucket configuration and actual expected cost, named disk/env mapping, adapter/runtime capability, request-size limits, scheduler, backup and file-recovery arrangement. Do not create a resource merely because architecture mentions it. Verify Cloud's current docs before changing infrastructure. Document version-row identity preservation and private-download tests; no public/temporary read URLs.

- [ ] **6. Release only after the separate instruction.** Review implementation, backup production DB and document metadata/files recovery approach, commit/push/deploy through existing process, verify migrations and private storage binding, run authorised fictional upload/download smoke test, verify file availability after a deployment/replacement, check cleanup dry run/schedule and remove approved smoke artifacts. Record exact deployed commit, date, checks and limitations. Rollback must not drop legal history/document tables or repoint recorded disk identities. No production tests/migrations/provisioning are performed in the planning stage.

- [ ] **7. Hand back.** Provide complete diff, actual test/build/browser/UAT evidence, schema/storage prerequisites and remaining release actions. If UAT or infrastructure is outstanding, report it plainly. Keep existing Register access and later milestones untouched. Leave commits and production changes pending Amir's instruction.

## Spec coverage and review checkpoints

| Architecture requirement | Owning task |
|---|---|
| Three categories, two/six slots, new types, exact memo label | 1, 4, 5 |
| Historical preservation, non-null submitted time, FK/index rules | 1, 6 |
| Optional MOA and authorised Addendum intake | 4, 5 |
| Private distinct storage, real-file bounds, actor-bound receipts | 2, 3 |
| No saved drafts, temporary Replace/Remove, readiness/conflicts | 3, 5 |
| Atomic official package, Version 1, compensation, lost-response retries | 4 |
| Owner isolation, inactive users, audited parent-bound downloads | 3, 4, 6 |
| Post-submit lock, version history, concise copy/Malaysian time | 6 |
| Abandoned-temp/orphan recovery with preserved history | 7 |
| Automated evidence, UAT, private Cloud release prerequisites | 8 |

After Tasks 1, 3, 4 and 6, review the slice's diff and test evidence before proceeding. These are development checkpoints; they do not require reapproving the business design. No automatic commit is made. Required external review can be performed by Amir's preferred reviewer; do not message another chat or person without explicit instruction.

## Execution handoff

Architecture approval is already recorded. Next: Amir reviews this task plan and chooses **native execution in this chat** or **delegated implementation with independent task reviews**. Recommend native for the eight tightly connected tasks; it retains interface context and reduces repeated coordination. If delegation is selected, follow the relevant development/review skill and the user's choice. Neither choice authorises paid storage provisioning or production release.

Begin with Task 1 once the plan and execution method are approved. Planning-only files remain uncommitted until Amir instructs otherwise.
