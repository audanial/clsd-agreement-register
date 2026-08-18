<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agreement_files', function (Blueprint $table) {
            $table->id();

            $table->foreignId('agreement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();

            // One agreement accumulates several documents over its life:
            // the draft, the fully signed copy, amendments, extension letters.
            $table->enum('document_type', [
                'draft',
                'signed_copy',
                'amendment',
                'extension',
                'supporting',
                'other',
            ])->default('other');

            $table->string('original_filename');
            $table->string('storage_path');
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['agreement_id', 'document_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agreement_files');
    }
};
