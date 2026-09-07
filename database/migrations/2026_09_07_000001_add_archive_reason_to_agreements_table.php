<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agreements', function (Blueprint $table) {
            // Distinguishes automatic expiry archiving from manual early termination.
            // Two values: 'expired' and 'terminated'. A future termination_basis
            // sub-field may be added if Legal needs to record why a termination
            // happened (breach, mutual agreement, etc.).
            $table->string('archive_reason')->nullable()->after('archived_at');
        });
    }

    public function down(): void
    {
        Schema::table('agreements', function (Blueprint $table) {
            $table->dropColumn('archive_reason');
        });
    }
};
