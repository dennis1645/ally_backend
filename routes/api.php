<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

// Rute untuk menangkap klik link verifikasi dari email
// Terlindungi oleh middleware 'signed' untuk memastikan link valid dan tidak dimanipulasi
Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->middleware(['signed'])
    ->name('verification.verify');


// Public Routes (Tidak perlu token)
Route::middleware('throttle:60,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    
    // Route baru untuk submit password baru (Reset Password)
    // Diberi nama 'password.reset' agar template email bawaan Laravel bisa menemukannya jika digunakan
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.reset'); 
});


// Protected Routes (Wajib punya token Sanctum)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    // Nanti rute beasiswa, profile, dll taruh di sini
});