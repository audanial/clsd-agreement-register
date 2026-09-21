<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DeactivateUser
{
    /**
     * Revoke remembered logins and database sessions when disabling an account.
     * The web middleware also rejects any inactive session that survives this purge.
     */
    public function __invoke(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $user->forceFill([
                'is_active' => false,
                'remember_token' => Str::random(60),
            ])->save();

            if (config('session.driver') === 'database') {
                DB::connection(config('session.connection'))
                    ->table(config('session.table', 'sessions'))
                    ->where('user_id', $user->getKey())
                    ->delete();
            }
        });
    }
}
