<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:5,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::middleware('role:admin')->group(function () {
        Route::livewire('/users', 'user-manager')->name('users.index');
    });

    // The Agreement Register is a read-only reference for all authenticated
    // staff roles: admin, legal, viewer, and — since LP1 Amendment 2 (17 Sep
    // 2026) — requester (Requesting Staff). Its security boundary has three
    // parts:
    //   1. Agreement create/edit routes are restricted to admin and legal by
    //      the nested role:admin,legal group below.
    //   2. Archive and status mutations live inside the shared agreement-show
    //      component (registered in this outer read-access group) and are
    //      protected by their own in-method canWrite() authorization — not by
    //      this route group.
    //   3. Record visibility is protected by the pending-agreement global scope
    //      on every query, plus agreement-show's boot() scoped re-resolution
    //      on /livewire/update (Livewire restores models unscoped).
    // Every future Livewire mutation must authorize for itself in its action
    // method; route middleware and Blade visibility are not sufficient.
    // Nested inside the `auth` group on purpose: `auth` runs first, so guests
    // are still redirected to /login rather than getting a 403 from
    // EnsureUserHasRole.
    Route::middleware('role:admin,legal,viewer,requester')->group(function () {
        Route::livewire('/agreements', 'agreements-index')->name('agreements.index');

        Route::middleware('role:admin,legal')->group(function () {
            Route::livewire('/agreements/create', 'agreement-form')->name('agreements.create');
            Route::livewire('/agreements/{agreement}/edit', 'agreement-form')->name('agreements.edit');
        });

        Route::livewire('/agreements/{agreement}', 'agreement-show')->name('agreements.show');
    });

    // The Legal Submission Portal. Requesters (the interface says "Requesting
    // Staff") see their own submissions; admin and legal share one queue. The
    // role gate is nested inside `auth` for the same reason as the register
    // above: guests still redirect to /login rather than getting a 403.
    // /submissions/create is declared before the dynamic {submission} route so
    // the word "create" is never swallowed by the binding.
    Route::middleware('role:admin,legal,requester')->group(function () {
        Route::livewire('/submissions', 'submissions.submissions-index')->name('submissions.index');

        Route::middleware('role:requester')->group(function () {
            Route::livewire('/submissions/create', 'submissions.submission-form')->name('submissions.create');
        });

        Route::livewire('/submissions/{submission}', 'submissions.submission-show')->name('submissions.show');
    });
});
