<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\UniversityController;
use App\Http\Controllers\ScholarshipController;
use App\Http\Controllers\DailyJournalController;
use App\Http\Controllers\DocumentVaultController;
use App\Http\Controllers\AdminDiagnosticController;
use App\Http\Controllers\UserDiagnosticController; 
use App\Http\Controllers\MilestoneController;
use App\Http\Controllers\AIMentorChatController;
use App\Http\Controllers\MentorPortalController;
use App\Http\Controllers\MentorBookingController;
use App\Http\Controllers\ShopController; 
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\AdminShopItemController; 

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

    // Midtrans Webhook Callback (Publik, diakses langsung oleh server Midtrans)
    Route::post('/midtrans/webhook', [ShopController::class, 'webhook']);
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

    // USER MODULE (Productivity, Journaling & Vault)
    Route::prefix('journals')->group(function () {
        Route::get('/', [DailyJournalController::class, 'index']);
        Route::post('/', [DailyJournalController::class, 'store']); // Create or Update today's journal
        Route::get('/{id}', [DailyJournalController::class, 'show']);
        Route::put('/{id}', [DailyJournalController::class, 'update']);
        Route::delete('/{id}', [DailyJournalController::class, 'destroy']);
    });
    
    // Document Vault (Brankas Penyimpanan Terenkripsi)
    Route::apiResource('vault', DocumentVaultController::class)->except(['update']);

    // Diagnostic Assessment (User Side)
    Route::prefix('diagnostic')->group(function () {
        Route::get('/questions', [UserDiagnosticController::class, 'getQuestions']);
        Route::post('/submit', [UserDiagnosticController::class, 'submitAssessment']);
        Route::get('/my-result', [UserDiagnosticController::class, 'getMyAssessment']);
    });

    // User Milestone & AI Timeline Routes
    Route::prefix('milestones')->group(function () {
        Route::get('/', [MilestoneController::class, 'getTimeline']);
        Route::post('/generate', [MilestoneController::class, 'generateTimeline']);
        Route::patch('/{id}/in-progress', [MilestoneController::class, 'startTask']); 
        Route::patch('/{id}/complete', [MilestoneController::class, 'completeTask']);     
    });

    // Shop & Monetization (Premium & Tokens)
    Route::prefix('shop')->group(function () {
        Route::get('/items', [ShopController::class, 'index']);       // Melihat daftar paket (Premium / Token)
        Route::post('/checkout', [ShopController::class, 'checkout']); // Membeli paket dan mendapatkan Snap Token
    });

    // Transaction History & Status
    Route::prefix('transactions')->group(function () {
        Route::get('/', [TransactionController::class, 'index']); // Riwayat semua transaksi
        Route::get('/{identifier}', [TransactionController::class, 'show']); // Cek status 1 transaksi
    });

    // AI Mentor Chatbot
    Route::prefix('ai-mentor')->group(function () {
        Route::post('/chat', [AIMentorChatController::class, 'sendMessage']);
    });

    // Mentor Booking
    Route::post('/mentor/book', [MentorBookingController::class, 'bookSession']);
    
    // MENTOR MODULE (Mentor Only)
    Route::middleware('role:mentor')->prefix('mentor')->group(function () {
        // Multi-Mentee Dashboard
        Route::get('/mentees', [MentorPortalController::class, 'getMenteeList']);
        
        // Pre-Session Dossier & Pre-Read
        Route::get('/dossier/{bookingId}', [MentorPortalController::class, 'getPreSessionDossier']);
        
        // Calendar & Availability Management
        Route::get('/availabilities', [MentorPortalController::class, 'getMyAvailabilities']);
        Route::post('/availabilities', [MentorPortalController::class, 'storeAvailability']);
        
        // Consultation Booking Actions (Confirm, Reject, Reschedule)
        Route::patch('/bookings/{bookingId}/confirm', [MentorPortalController::class, 'confirmBooking']);
        Route::patch('/bookings/{bookingId}/reject', [MentorPortalController::class, 'rejectBooking']);
        Route::patch('/bookings/{bookingId}/reschedule', [MentorPortalController::class, 'rescheduleBooking']);

        // Custom Action Plan Generation (Pasca-Konsultasi)
        Route::post('/bookings/{bookingId}/action-plans', [MentorPortalController::class, 'storeActionPlan']);
    });

    // ADMIN MODULE (Admin Only)
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
        
        // Diagnostic Assessment Management (Initial Assessment)
        Route::get('/diagnostic-questions', [AdminDiagnosticController::class, 'index']);
        Route::post('/diagnostic-questions/import', [AdminDiagnosticController::class, 'importExcel']);
        Route::post('/diagnostic-questions', [AdminDiagnosticController::class, 'store']);
        
        // Hapus semua data (Di atas rute {id} agar tidak terbaca sebagai parameter ID)
        Route::delete('/diagnostic-questions/clear-all', [AdminDiagnosticController::class, 'destroyAll']); 
        
        // Rute dengan parameter {id}
        Route::put('/diagnostic-questions/{id}', [AdminDiagnosticController::class, 'update']);
        Route::delete('/diagnostic-questions/{id}', [AdminDiagnosticController::class, 'destroy']);

        // --- TAMBAHAN: Shop Item Management ---
        Route::get('/shop-items', [AdminShopItemController::class, 'index']);
        Route::post('/shop-items', [AdminShopItemController::class, 'store']);
        Route::get('/shop-items/{id}', [AdminShopItemController::class, 'show']);
        Route::put('/shop-items/{id}', [AdminShopItemController::class, 'update']);
        Route::delete('/shop-items/{id}', [AdminShopItemController::class, 'destroy']);
        
    });
});