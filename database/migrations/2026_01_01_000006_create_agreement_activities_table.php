<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agreement_activities', function (Blueprint $table) {
            $table->id();

            $table->foreignId('agreement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->enum('type', [
                'created',
                'updated',
                'status_changed',
                'file_uploaded',
                'file_removed',
                'comment',
                'archived',
                'restored',
            ]);

            // Human-readable line shown in the drawer, e.g.
            // "Document status changed from Awaiting Partner to Signed".
            $table->string('description');

            // Optional structured detail for status changes, so the log can be
            // rendered richly later without reparsing the description string.
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['agreement_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agreement_activities');
    }
};
