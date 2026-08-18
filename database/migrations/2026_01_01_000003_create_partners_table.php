<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partners', function (Blueprint $table) {
            $table->id();

            // Full legal name as it appears on the agreement.
            $table->string('name');

            // Short form used in the register's "Pihak Kerjasama" column
            // (e.g. SYPTSB, UIN Malang, ENAC).
            $table->string('short_name', 100)->nullable();

            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partners');
    }
};
