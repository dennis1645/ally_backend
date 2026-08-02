<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\UniversityController;

// Verifikasi email
Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->middleware(['signed'])
    ->name('verification.verify');

// Public Routes
Route::middleware('throttle:60,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.reset'); 
});

// Protected Routes (Wajib Login)
Route::middleware('auth:sanctum')->group(function () {
    // Auth & Profile
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::post('/update-profile', [ProfileController::class, 'update']); 
    
    // ==========================================
    // UNIVERSITY MODULE (Bisa diakses Admin & User)
    // ==========================================
    Route::get('/universities', [UniversityController::class, 'index']);
    Route::get('/universities/{id}', [UniversityController::class, 'show']);
    
    // ==========================================
    // ADMIN MODULE (Hanya Admin)
    // ==========================================
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        
        // User Management
        Route::get('/get-users', [AdminController::class, 'index']); 
        Route::get('/get-user-detail/{id}', [AdminController::class, 'show']); 
        Route::post('/create-user', [AdminController::class, 'store']); 
        Route::put('/update-user/{id}', [AdminController::class, 'update']); 
        Route::put('/update-user-password/{id}', [AdminController::class, 'updatePassword']); 
        Route::put('/toggle-user-status/{id}', [AdminController::class, 'toggleStatus']); 
        Route::delete('/delete-user/{id}', [AdminController::class, 'destroy']); 
        Route::post('/restore-user/{id}', [AdminController::class, 'restore']);
        
        // University Management (CRUD)
        Route::post('/create-university', [UniversityController::class, 'store']); 
        // Menggunakan POST karena upload file (gambar) via API tidak terbaca di PUT/PATCH
        Route::post('/update-university/{id}', [UniversityController::class, 'update']); 
        Route::delete('/delete-university/{id}', [UniversityController::class, 'destroy']); 
        Route::post('/restore-university/{id}', [UniversityController::class, 'restore']);
        
    });
});