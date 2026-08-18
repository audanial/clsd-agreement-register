<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Three roles cover the current need. A dedicated roles/permissions
            // package (e.g. spatie/laravel-permission) is worth adopting if this
            // ever needs per-action granularity — but don't reach for it yet.
            $table->enum('role', ['admin', 'legal', 'viewer'])
                ->default('viewer')
                ->after('email');

            // PICs are UniKL staff outside Legal; they appear in the PIC dropdown
            // but don't necessarily log in on day one.
            $table->boolean('is_active')->default(true)->after('role');

            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn(['role', 'is_active']);
        });
    }
};
