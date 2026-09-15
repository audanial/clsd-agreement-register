<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submission_activities', function (Blueprint $table) {
            $table->id();

            // Deliberate exception to the repository's cascade-owned-children
            // convention. Agreement uses soft deletes, so cascading its
            // activities is safe; Submission has no soft deletes and its audit
            // history must never be erased by an ordinary delete. Hard deletion
            // of a submission that has activity rows is therefore restricted.
            $table->foreignId('submission_id')->constrained()->restrictOnDelete();

            // The actor may be deleted later; the description and timestamps
            // remain as the audit trail.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('type');
            $table->string('description');
            $table->json('meta')->nullable();

            $table->timestamps();

            // Activity feed for one submission, ordered by time.
            $table->index(['submission_id', 'created_at']);

            // SQLite explicit index for the nullable actor FK.
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_activities');
    }
};
