<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\UniversityController;
use App\Http\Controllers\ScholarshipController;
use App\Http\Controllers\DailyJournalController;

// Email Verification
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

// Protected Routes (Requires Login)
Route::middleware('auth:sanctum')->group(function () {
    // Auth & Profile
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::post('/update-profile', [ProfileController::class, 'update']); 
    
    // University Module
    Route::get('/universities', [UniversityController::class, 'index']);
    Route::get('/universities/{id}', [UniversityController::class, 'show']);
    
    // Scholarship Module
    Route::get('/scholarships', [ScholarshipController::class, 'index']);
    Route::get('/scholarships/{id}', [ScholarshipController::class, 'show']);

    // ==========================================
    // USER MODULE (Productivity & Journaling)
    // ==========================================
    Route::prefix('journals')->group(function () {
        Route::get('/', [DailyJournalController::class, 'index']);
        Route::post('/', [DailyJournalController::class, 'store']); // Create or Update today's journal
        Route::get('/{id}', [DailyJournalController::class, 'show']);
        Route::put('/{id}', [DailyJournalController::class, 'update']);
        Route::delete('/{id}', [DailyJournalController::class, 'destroy']);
    });
    
    // ==========================================
    // MENTOR MODULE (Mentor Only)
    // ==========================================
    Route::middleware('role:mentor')->prefix('mentor')->group(function () {
        // Mentor Dashboard & Scheduling routes will be added here
    });

    // ==========================================
    // ADMIN MODULE (Admin Only)
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
        
        // University Management
        Route::post('/create-university', [UniversityController::class, 'store']); 
        Route::post('/update-university/{id}', [UniversityController::class, 'update']); 
        Route::delete('/delete-university/{id}', [UniversityController::class, 'destroy']); 
        Route::post('/restore-university/{id}', [UniversityController::class, 'restore']);
        
        // Scholarship Management
        Route::post('/create-scholarship', [ScholarshipController::class, 'store']); 
        Route::post('/update-scholarship/{id}', [ScholarshipController::class, 'update']); 
        Route::delete('/delete-scholarship/{id}', [ScholarshipController::class, 'destroy']); 
        Route::post('/restore-scholarship/{id}', [ScholarshipController::class, 'restore']);
        
    });
});