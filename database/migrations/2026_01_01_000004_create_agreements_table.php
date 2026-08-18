<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agreements', function (Blueprint $table) {
            $table->id();

            $table->string('title');
            $table->enum('type', ['LOI', 'NDA', 'MOA', 'MOU', 'SEA', 'MOC', 'ADDENDUM']);

            $table->foreignId('partner_id')->constrained()->restrictOnDelete();

            // One campus per agreement (confirmed with Legal). The TBD campus record
            // covers historical rows where ownership was never recorded.
            $table->foreignId('campus_id')->constrained()->restrictOnDelete();

            // Person In Charge: the UniKL staff member bridging Legal and the project
            // team. Nullable because historical imports won't have one.
            $table->foreignId('pic_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->enum('sector', ['academic', 'industri'])->nullable();

            // --- Dates -------------------------------------------------------
            // agreement_date is "the year the agreement was made"; Year is derived
            // from it rather than typed separately.
            $table->date('agreement_date')->nullable();
            $table->date('effective_date')->nullable();

            // Null = indefinite / until completion. Drives Expiring Soon + Expired.
            $table->date('expiry_date')->nullable();

            // --- Vetting milestones (internal, Legal-only visibility) ---------
            $table->date('received_from_po_at')->nullable();
            $table->date('board_approved_at')->nullable();
            $table->date('signed_by_unikl_at')->nullable();

            // When it left Legal for the partner. Powers "sitting with partner for N days".
            $table->date('sent_to_partner_at')->nullable();

            $table->date('signed_date')->nullable();

            // --- Status ------------------------------------------------------
            // pending is Legal-only: still in vetting / awaiting board + senior exec
            // signature. Hidden from non-Legal users via a global scope.
            $table->enum('document_status', [
                'pending',
                'awaiting_partner',
                'signed',
                'expired',
            ])->default('pending');

            $table->enum('project_status', [
                'not_started',
                'ongoing',
                'stalled',
                'completed',
            ])->default('not_started');

            // Project status is maintained by the PIC/project owner, not Legal.
            // This timestamp powers the "stale status" flag on the dashboard.
            $table->timestamp('project_status_updated_at')->nullable();

            $table->text('scope')->nullable();
            $table->text('notes')->nullable();

            // --- Lifecycle ---------------------------------------------------
            // Archived = closed but still findable (people ask about expired
            // agreements; other campuses may want to revive them).
            $table->timestamp('archived_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('document_status');
            $table->index('project_status');
            $table->index('expiry_date');
            $table->index('agreement_date');
            $table->index('archived_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agreements');
    }
};
