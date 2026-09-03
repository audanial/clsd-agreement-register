<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Decision 5 (Option b): merge agreement_date + effective_date into
     * "Date Signed", backed solely by agreement_date. F4 confirmed all live
     * rows already have matching values, so no data transformation is needed.
     */
    public function up(): void
    {
        Schema::table('agreements', function (Blueprint $table) {
            $table->dropColumn('effective_date');
        });
    }

    /**
     * Rolling back recreates the column and copies the current agreement_date
     * values into it. This is NOT a full restoration of the original
     * effective_date data — dropping a column is destructive, and the original
     * values are unrecoverable from the schema alone.
     */
    public function down(): void
    {
        Schema::table('agreements', function (Blueprint $table) {
            $table->date('effective_date')->nullable();
        });

        DB::table('agreements')->update([
            'effective_date' => DB::raw('agreement_date'),
        ]);
    }
};
