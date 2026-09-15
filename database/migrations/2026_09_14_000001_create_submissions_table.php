<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();

            // Permanent owner. The owner is set once by the trusted creation
            // boundary and never changes, so deletion of a user is prevented
            // rather than cascaded.
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();

            // Originating campus / department. New submissions must identify a
            // real, active campus; the TBD catch-all is excluded by application
            // validation because it exists only for incomplete historical data.
            $table->foreignId('campus_id')->constrained()->restrictOnDelete();

            // Dormant V2 preparation field. The Agreement Register link is not
            // exposed, read, or written by any LP1 code; it exists only to
            // avoid a later migration once Legal converts a submission into an
            // agreement.
            $table->foreignId('agreement_id')->nullable()->constrained()->nullOnDelete();

            $table->string('title');
            $table->string('partner_name');

            // Nullable when the requester is not sure; Legal classifies later.
            // Plain string, not a database enum, so new types do not require a
            // table rebuild on SQLite.
            $table->string('agreement_type')->nullable();

            $table->text('purpose');

            // Plain string for the same reason as agreement_type. All new
            // submissions start as pending in LP1.
            $table->string('status')->default('pending');

            // Business timestamp for submission; matches created_at in LP1
            // because there is no draft state.
            $table->timestamp('submitted_at');

            $table->timestamps();

            // "My Submissions" list for requesters.
            $table->index(['created_by', 'created_at']);

            // Shared Legal / Admin queue.
            $table->index(['status', 'created_at']);

            // SQLite does not automatically index foreign-key columns, so each
            // FK that is not already the leading column of a composite index
            // needs its own index for production-like behaviour on SQLite.
            $table->index('campus_id');
            $table->index('agreement_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submissions');
    }
};
