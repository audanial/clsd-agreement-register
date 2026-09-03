<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Decision 4 (Option c): PIC becomes a plain name string on agreements.
     * pic_user_id is kept as an unused nullable column; zero live rows have
     * a non-null value, so no data needs to be backfilled.
     */
    public function up(): void
    {
        Schema::table('agreements', function (Blueprint $table) {
            $table->string('pic_name')->nullable()->after('pic_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('agreements', function (Blueprint $table) {
            $table->dropColumn('pic_name');
        });
    }
};
