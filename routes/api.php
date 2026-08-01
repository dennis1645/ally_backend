<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;

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
    // Auth & Keamanan
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/change-password', [AuthController::class, 'changePassword']); // <-- Route baru untuk ubah password
    
    // Profile
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::post('/update-profile', [ProfileController::class, 'update']); 
    
    // Nanti rute beasiswa, dll taruh di sini ke bawah
});