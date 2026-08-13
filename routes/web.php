<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TelescopeAuthController;

Route::get('/', function () {
    return view('welcome');
});

// Telescope Admin Authentication Routes
Route::get('/telescope-login', [TelescopeAuthController::class, 'showLoginForm'])->name('telescope.login');
Route::post('/telescope-login', [TelescopeAuthController::class, 'login']);
Route::post('/telescope-logout', [TelescopeAuthController::class, 'logout'])->name('telescope.logout');