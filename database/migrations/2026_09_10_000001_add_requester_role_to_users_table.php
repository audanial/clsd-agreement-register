<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Adds a fourth role, `requester`: non-Legal UniKL staff who submit agreement
        // requests through the Legal Submission Portal. A requester can see ONLY their
        // own submissions and has NO access to the Agreement Register at all — the
        // register routes are gated to admin/legal/viewer in routes/web.php.
        //
        // Why `string` instead of extending the enum:
        // On SQLite an enum compiles to `varchar check (role in (...))`, and a CHECK
        // constraint cannot be altered in place — the whole table has to be rebuilt
        // (see SQLiteGrammar::compileAlter). Dropping to a plain string makes THIS the
        // last rebuild the users table ever needs for a role change. Valid values are
        // enforced where they are actually checked anyway: the `in:` validation rule in
        // the user-manager component, and App\Http\Middleware\EnsureUserHasRole.
        //
        // Roles: admin | legal | viewer | requester
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('viewer')->change();
        });
    }

    public function down(): void
    {
        // Any requester would violate the restored 3-value CHECK constraint, so demote
        // them first. `viewer` is the safest landing spot: it cannot write anything,
        // and it is what StaffSeeder already uses for non-Legal staff.
        DB::table('users')->where('role', 'requester')->update(['role' => 'viewer']);

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'legal', 'viewer'])->default('viewer')->change();
        });
    }
};
