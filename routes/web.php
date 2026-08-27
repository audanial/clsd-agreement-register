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

    Route::livewire('/agreements', 'agreements-index')->name('agreements.index');

    Route::middleware('role:admin,legal')->group(function () {
        Route::livewire('/agreements/create', 'agreement-form')->name('agreements.create');
        Route::livewire('/agreements/{agreement}/edit', 'agreement-form')->name('agreements.edit');
    });

    Route::livewire('/agreements/{agreement}', 'agreement-show')->name('agreements.show');
});
