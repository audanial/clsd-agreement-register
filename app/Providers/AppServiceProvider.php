<?php

namespace App\Providers;

use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Livewire only re-runs a fixed allow-list of middleware on /livewire/update
        // requests (see Livewire\Mechanisms\PersistentMiddleware\PersistentMiddleware).
        // Our `role:` middleware is NOT on that list, so without this a route guarded
        // only by `role:admin` would protect the initial page load and nothing else —
        // every subsequent Livewire action would be authorised by the component alone.
        //
        // This is defence in depth, NOT a licence to skip in-method checks: every
        // component action that reads or mutates must still authorise for itself.
        Livewire::addPersistentMiddleware(EnsureUserHasRole::class);
    }
}
