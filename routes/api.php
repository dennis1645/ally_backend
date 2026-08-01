<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AdminController; // <-- Pastikan import AdminController

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
    
    // ==========================================
    // ADMIN MODULE (User Management)
    // URL dibedakan secara eksplisit agar tidak tertukar
    // DILINDUNGI MIDDLEWARE ROLE:ADMIN (User biasa akan ditolak)
    // ==========================================
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/get-users', [AdminController::class, 'index']); // Mengambil semua data user
        Route::get('/get-user-detail/{id}', [AdminController::class, 'show']); // Mengambil 1 spesifik user
        
        Route::post('/create-user', [AdminController::class, 'store']); // Membuat user/mentor baru
        
        // Menggunakan POST atau PUT/PATCH untuk update (Sesuai style /update-profile)
        Route::put('/update-user/{id}', [AdminController::class, 'update']); // Update data diri/email user
        Route::put('/update-user-password/{id}', [AdminController::class, 'updatePassword']); // Admin paksa ubah password user
        Route::put('/toggle-user-status/{id}', [AdminController::class, 'toggleStatus']); // Suspend atau aktifkan user
        
        Route::delete('/delete-user/{id}', [AdminController::class, 'destroy']); // Hapus user (Soft delete & email refund)
        Route::post('/restore-user/{id}', [AdminController::class, 'restore']);
    });

    // Nanti rute beasiswa, dll taruh di sini ke bawah
});