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
use App\Http\Controllers\PaymentController; 
use App\Http\Controllers\AdminShopItemController; 
use App\Http\Controllers\AdminPracticeExamController;
use App\Http\Controllers\DailyDrillController;
use App\Http\Controllers\AdminBadgeController; 
use App\Http\Controllers\MentorDocumentController;
use App\Http\Controllers\BookmarkController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\UserDeepDiagnosticController;
use App\Http\Controllers\SupportTicketController;
use App\Http\Controllers\AdminFinanceController; // <-- IMPORT BARU

// Email Verification
Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->middleware(['signed'])
    ->name('verification.verify');

// Public Routes (Including Public Diagnostic Hook for Onboarding)
Route::middleware('throttle:60,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.reset'); 

    // Midtrans Webhook Callback (Publik, diakses langsung oleh server Midtrans)
    Route::post('/midtrans/webhook', [ShopController::class, 'webhook']);

    // ==========================================
    // RUTE PROXY REDIRECT MIDTRANS -> FRONTEND
    // ==========================================
    Route::get('/payment/return', [PaymentController::class, 'paymentReturn']); 

    // Diagnostic Assessment (Public Hook - Bisa diakses Guest maupun Logged In)
    Route::prefix('diagnostic')->group(function () {
        Route::get('/questions', [UserDiagnosticController::class, 'getQuestions']);
        Route::post('/submit', [UserDiagnosticController::class, 'submitAssessment']);
        // Endpoint my-result dipindahkan ke sini agar guest_token bisa lewat tanpa error 401/404
        Route::get('/my-result', [UserDiagnosticController::class, 'getMyAssessment']); 
    });
});

// Protected Routes (Requires Login)
Route::middleware('auth:sanctum')->group(function () {
    // Auth & Profile
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::post('/update-profile', [ProfileController::class, 'update']); 

    // Bookmark System
    Route::prefix('bookmarks')->group(function () {
        Route::get('/', [BookmarkController::class, 'index']); // Get list bookmark
        Route::post('/toggle', [BookmarkController::class, 'toggleBookmark']); // Add / Remove bookmark
    });

    Route::prefix('deep-diagnostic')->group(function () {
        Route::get('/questions', [UserDeepDiagnosticController::class, 'getQuestions']);
        Route::post('/submit', [UserDeepDiagnosticController::class, 'submitAssessment']);
        Route::get('/my-result', [UserDeepDiagnosticController::class, 'getMyAssessment']); 
        Route::post('/choose-recommendation', [UserDeepDiagnosticController::class, 'chooseRecommendation']);
    });
    
    // Endpoint Khusus Setup/Update Profil Akademik & Target (Task 1.5 Milestone 2)
    Route::post('/profile/academic-target', [ProfileController::class, 'update']);
    
    // University Module
    Route::get('/universities', [UniversityController::class, 'index']);
    Route::get('/universities/{id}', [UniversityController::class, 'show']);
    
    // Scholarship Module
    Route::get('/scholarships', [ScholarshipController::class, 'index']);
    Route::get('/scholarships/{id}', [ScholarshipController::class, 'show']);

    // ==========================================
    // CUSTOMER SUPPORT / TICKETING (USER SIDE)
    // ==========================================
    Route::prefix('support')->group(function () {
        Route::get('/my-tickets', [SupportTicketController::class, 'myTickets']);
        Route::get('/my-tickets/{id}', [SupportTicketController::class, 'showMyTicket']); // Lihat detail tiket user
        Route::post('/submit', [SupportTicketController::class, 'submitTicket']);
    });

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

    // Signed Route untuk Preview Dokumen Vault yang Terenkripsi oleh Mentor
    Route::get('/document/download/{documentVault}', function (\Illuminate\Http\Request $request, \App\Models\DocumentVault $documentVault) {
        if (! $request->hasValidSignature()) {
            abort(401, 'URL dokumen telah kadaluarsa atau tidak valid.');
        }

        // Cek apakah file fisik terenkripsi ada di storage local
        if (! \Illuminate\Support\Facades\Storage::disk('local')->exists($documentVault->file_path)) {
            abort(404, 'File fisik dokumen tidak ditemukan.');
        }

        try {
            // Ambil konten terenkripsi lalu dekripsi on-the-fly
            $encryptedContent = \Illuminate\Support\Facades\Storage::disk('local')->get($documentVault->file_path);
            $decryptedContent = \Illuminate\Support\Facades\Crypt::decryptString($encryptedContent);

            // Kirim response inline agar bisa di-preview langsung (misal di iframe browser)
            return response($decryptedContent, 200)
                ->header('Content-Type', $documentVault->mime_type)
                ->header('Content-Disposition', 'inline; filename="' . $documentVault->file_name . '"');

        } catch (\Exception $e) {
            abort(500, 'Gagal memproses dan mendekripsi dokumen.');
        }
    })->name('document.download');

    // Daily Drill (Micro-learning) Routes
    Route::prefix('daily-drills')->group(function () {
        Route::get('/generate', [DailyDrillController::class, 'generateDrill']);
        Route::post('/submit', [DailyDrillController::class, 'submitDrill']);
        Route::get('/history', [DailyDrillController::class, 'history']);
        Route::get('/{id}', [DailyDrillController::class, 'show']);
    });

    // User Milestone & AI Timeline Routes
    Route::prefix('milestones')->group(function () {
        Route::get('/', [MilestoneController::class, 'getTimeline']);
        Route::post('/generate', [MilestoneController::class, 'generateTimeline']);
        Route::patch('/{id}/in-progress', [MilestoneController::class, 'startTask']); 
        Route::patch('/{id}/complete', [MilestoneController::class, 'completeTask']);   
        Route::patch('/{id}/discover', [MilestoneController::class, 'markAsDiscovered']); 
        Route::post('/{id}/submit', [MilestoneController::class, 'submitTask']);
        Route::get('/{id}/submission', [MilestoneController::class, 'getTaskSubmission']);
        Route::get('/{parentMilestoneId}/action-plans', [MentorPortalController::class, 'getActionPlansByParent']);
    });

    // Action Plans Management
    Route::prefix('action-plans')->group(function () {
        Route::match(['patch', 'post'], '/{id}/complete', [MentorPortalController::class, 'completeActionPlan']);
        Route::get('/parent/{parentMilestoneId}', [MentorPortalController::class, 'getActionPlansByParent']);
    });

    // Shop & Monetization (Premium & Tokens)
    Route::prefix('shop')->group(function () {
        Route::get('/items', [ShopController::class, 'index']);      // Melihat daftar paket (Premium / Token)
        Route::post('/checkout', [ShopController::class, 'checkout']); // Membeli paket dan mendapatkan Snap Token
    });

    // ==========================================
    // UPGRADE PREMIUM (MIDTRANS)
    // ==========================================
    Route::post('/upgrade-premium', [PaymentController::class, 'upgradeToPremium']); 

    // Transaction History & Status
    Route::prefix('transactions')->group(function () {
        Route::get('/', [TransactionController::class, 'index']); // Riwayat semua transaksi
        Route::get('/{identifier}', [TransactionController::class, 'show']); // Cek status 1 transaksi
    });

    // MENTOR DOCUMENTS: Akses Dokumen Mentor via Tautan (VIEW & HONEYPOT)
    Route::get('/documents/view/{share_token}', [MentorDocumentController::class, 'viewSharedDocument']);
    Route::get('/documents/download/{share_token}', [MentorDocumentController::class, 'downloadTrap']); // Rute Jebakan (Honeypot)
    
    // AI Mentor Chatbot
    Route::prefix('ai-mentor')->group(function () {
        Route::post('/chat', [AIMentorChatController::class, 'sendMessage']);
    });

    // ==========================================
    // MENTOR BOOKING (SISI MENTEE)
    // ==========================================
    Route::get('/mentor/availability', [MentorBookingController::class, 'getMentorAvailability']);
    Route::post('/mentor/book', [MentorBookingController::class, 'bookSession']);
    Route::get('/my-bookings', [MentorBookingController::class, 'getMyBookings']);
    Route::get('/my-bookings/reschedule-popups', [MentorBookingController::class, 'getReschedulePopups']);
    Route::patch('/my-bookings/{bookingId}/acknowledge-reschedule', [MentorBookingController::class, 'acknowledgeReschedule']);
    
    // [BARU] Mentee memberikan review ke Mentor
    Route::post('/my-bookings/{bookingId}/review', [MentorBookingController::class, 'submitReview']);
    
    // ==========================================
    // MENTOR MODULE (SISI MENTOR ONLY)
    // ==========================================
    Route::middleware('role:mentor')->prefix('mentor')->group(function () {
        // Dashboard & Finansial Mentor
        Route::get('/dashboard/stats', [MentorPortalController::class, 'getDashboardStats']);
        Route::get('/invoices', [MentorPortalController::class, 'getEarningInvoices']);
        
        // Manual Complete Session (Wajib Upload Bukti Sesi) & Cairkan Dana
        Route::match(['patch', 'post'], '/bookings/{bookingId}/complete', [MentorPortalController::class, 'completeBooking']);

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

        // Mentor memberikan review/catatan evaluasi ke Mentee
        Route::post('/bookings/{bookingId}/review', [MentorPortalController::class, 'submitMenteeReview']);

        // Custom Action Plan Generation (Pasca-Konsultasi)
        Route::post('/bookings/{bookingId}/action-plans', [MentorPortalController::class, 'storeActionPlan']);

        // Audit & Review Tugas / Submission Mentee (Approve / Revisi)
        Route::get('/submissions', [MentorPortalController::class, 'getMenteeSubmissions']);
        Route::post('/submissions/{submissionId}/review', [MentorPortalController::class, 'reviewSubmission']);

        // Mentor Document Sharing
        Route::get('/documents', [MentorDocumentController::class, 'index']);
        Route::post('/documents', [MentorDocumentController::class, 'store']);
        Route::delete('/documents/{id}', [MentorDocumentController::class, 'destroy']);
    });

    // ADMIN MODULE (Admin Only)
    Route::middleware('role:admin')->prefix('admin')->group(function () {

        Route::get('/dashboard/stats', [AdminDashboardController::class, 'getDashboardStats']);
        
        // ==========================================
        // [BARU] MANAJEMEN KEUANGAN MENTOR (ADMIN SIDE)
        // ==========================================
        Route::prefix('finances')->group(function () {
            Route::get('/mentors', [AdminFinanceController::class, 'getMentorFinances']);
            Route::patch('/mentors/{id}/rate', [AdminFinanceController::class, 'updateMentorRate']);
            Route::post('/mentors/{id}/payout', [AdminFinanceController::class, 'processPayout']);
            Route::get('/consultations', [AdminFinanceController::class, 'getConsultationProofs']);
            Route::patch('/consultations/{bookingId}/verify-proof', [AdminFinanceController::class, 'verifyConsultationProof']);
        });

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
        Route::delete('/diagnostic-questions/clear-all', [AdminDiagnosticController::class, 'destroyAll']); 
        Route::put('/diagnostic-questions/{id}', [AdminDiagnosticController::class, 'update']);
        Route::delete('/diagnostic-questions/{id}', [AdminDiagnosticController::class, 'destroy']);

        // Shop Item Management
        Route::get('/shop-items', [AdminShopItemController::class, 'index']);
        Route::post('/shop-items', [AdminShopItemController::class, 'store']);
        Route::get('/shop-items/{id}', [AdminShopItemController::class, 'show']);
        Route::put('/shop-items/{id}', [AdminShopItemController::class, 'update']);
        Route::delete('/shop-items/{id}', [AdminShopItemController::class, 'destroy']);

        // Practice Exams Management (Latihan Bahasa Inggris)
        Route::get('/practice-exams', [AdminPracticeExamController::class, 'index']);
        Route::get('/practice-exams/{id}', [AdminPracticeExamController::class, 'showExam']);
        Route::post('/practice-exams/import', [AdminPracticeExamController::class, 'importExcel']);
        Route::put('/practice-exams/{id}', [AdminPracticeExamController::class, 'updateExam']);
        Route::delete('/practice-exams/clear-all', [AdminPracticeExamController::class, 'destroyAll']);
        Route::delete('/practice-exams/{id}', [AdminPracticeExamController::class, 'destroyExam']);
        Route::put('/practice-questions/{id}', [AdminPracticeExamController::class, 'updateQuestion']);
        Route::delete('/practice-questions/{id}', [AdminPracticeExamController::class, 'destroyQuestion']);

        // Badge / Gamification Management
        Route::get('/badges', [AdminBadgeController::class, 'index']);
        Route::post('/badges', [AdminBadgeController::class, 'store']);
        Route::get('/badges/{id}', [AdminBadgeController::class, 'show']);
        Route::put('/badges/{id}', [AdminBadgeController::class, 'update']);
        Route::delete('/badges/{id}', [AdminBadgeController::class, 'destroy']);
        
        // ==========================================
        // CUSTOMER SUPPORT / TICKETING (ADMIN SIDE)
        // ==========================================
        Route::prefix('support')->group(function () {
            Route::get('/stats', [SupportTicketController::class, 'adminStats']); // Laporan Statistik
            Route::get('/tickets', [SupportTicketController::class, 'adminIndex']); // GET ALL
            Route::get('/tickets/{id}', [SupportTicketController::class, 'adminShow']); // Lihat detail 1 tiket
            Route::post('/tickets/{id}/reply', [SupportTicketController::class, 'adminReply']);
            Route::patch('/tickets/{id}/resolve', [SupportTicketController::class, 'adminResolve']);
        });

    });
});