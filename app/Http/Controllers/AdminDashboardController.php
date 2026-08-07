<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AdminDashboardController extends Controller
{
    /**
     * Mendapatkan statistik data super komprehensif untuk seluruh halaman Admin Dashboard
     */
    public function getDashboardStats(Request $request)
    {
        try {
            $now = Carbon::now();

            // ==========================================
            // 1. OVERVIEW (Admin Control Center Dashboard)
            // ==========================================
            $totalUsers = DB::table('users')->where('role', 'user')->whereNull('deleted_at')->count();
            $activeMentors = DB::table('users')->where('role', 'mentor')->where('status', 'active')->whereNull('deleted_at')->count();
            $openScholarships = DB::table('scholarships')->where('status', 'published')->whereNull('deleted_at')->count();
            $pendingPayments = DB::table('transactions')->where('payment_status', 'pending')->count();
            
            $recentUsers = DB::table('users')
                ->whereNull('deleted_at')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(['id', 'name', 'email', 'role', 'status', 'created_at']);

            // ==========================================
            // 2. USER MANAGEMENT
            // ==========================================
            $totalAllUsers = DB::table('users')->whereNull('deleted_at')->count();
            $activeUsers = DB::table('users')->where('status', 'active')->whereNull('deleted_at')->count();
            $suspendedUsers = DB::table('users')->where('status', 'suspended')->whereNull('deleted_at')->count();

            // ==========================================
            // 3. FINANCE OVERVIEW
            // ==========================================
            $monthlyRevenue = DB::table('transactions')
                ->where('payment_status', 'success')
                ->whereMonth('created_at', $now->month)
                ->whereYear('created_at', $now->year)
                ->sum('gross_amount');
                
            $totalPremiumUsers = DB::table('users')->where('is_premium', true)->whereNull('deleted_at')->count();
            $newRegistrations = DB::table('users')
                ->whereMonth('created_at', $now->month)
                ->whereYear('created_at', $now->year)
                ->whereNull('deleted_at')
                ->count();
            $transactionCount = DB::table('transactions')->count();

            // ==========================================
            // 4. UNIVERSITY MANAGEMENT
            // ==========================================
            $totalUniversities = DB::table('universities')->whereNull('deleted_at')->count();
            $countriesCovered = DB::table('universities')->whereNull('deleted_at')->distinct('country')->count('country');
            $archivedUniversities = DB::table('universities')->whereNotNull('deleted_at')->count(); // Soft deleted

            // ==========================================
            // 5. SCHOLARSHIP MANAGEMENT
            // ==========================================
            $totalActivePrograms = DB::table('scholarships')->whereNull('deleted_at')->count();
            
            // Beasiswa yang belum lewat deadline atau tidak punya deadline tetap dianggap 'Open'
            $openApplications = DB::table('scholarships')
                ->whereNull('deleted_at')
                ->where(function($query) use ($now) {
                    $query->where('deadline_date', '>=', $now->toDateString())
                          ->orWhereNull('deadline_date');
                })->count();
            
            $fullyFundedCount = DB::table('scholarships')->where('funding_type', 'fully_funded')->whereNull('deleted_at')->count();
            $fullyFundedRatio = $totalActivePrograms > 0 ? round(($fullyFundedCount / $totalActivePrograms) * 100) : 0;

            // ==========================================
            // 6. INITIAL ASSESSMENT MANAGEMENT
            // ==========================================
            $totalDiagnosticQuestions = DB::table('diagnostic_questions')->count();

            // ==========================================
            // 7. ITEM SHOP MANAGEMENT
            // ==========================================
            $totalActiveCatalog = DB::table('shop_items')->where('is_active', true)->count();
            $topUpPackages = DB::table('shop_items')->where('item_type', 'token_package')->where('is_active', true)->count();
            
            // Total item yang berhasil dibeli dari detail transaksi (yang berstatus success)
            $totalPurchasedItems = DB::table('transaction_details')
                ->join('transactions', 'transaction_details.transaction_id', '=', 'transactions.id')
                ->where('transactions.payment_status', 'success')
                ->count();

            // ==========================================
            // 8. PRACTICE EXAM & QUIZ MANAGEMENT
            // ==========================================
            $totalPracticeExams = DB::table('practice_exams')->where('is_active', true)->count();
            $questionBankSize = DB::table('practice_questions')->count();
            $totalTestAttempts = DB::table('practice_attempts')->count();

            // ==========================================
            // 9. BADGE & GAMIFICATION MANAGEMENT
            // ==========================================
            $totalActiveBadges = DB::table('badges')->count();
            $badgesUnlocked = DB::table('user_badges')->count();

            // ==========================================
            // PENGGABUNGAN DATA (JSON BUILDER)
            // ==========================================
            return response()->json([
                'status' => 'success',
                'message' => 'Admin dashboard statistics retrieved successfully.',
                'data' => [
                    'overview' => [
                        'total_users' => $totalUsers,
                        'active_mentors' => $activeMentors,
                        'open_scholarships' => $openScholarships,
                        'pending_payments' => $pendingPayments,
                        'recent_users' => $recentUsers
                    ],
                    'user_management' => [
                        'total_users' => $totalAllUsers,
                        'active_users' => $activeUsers,
                        'suspended_users' => $suspendedUsers,
                    ],
                    'finance' => [
                        'monthly_revenue' => (float) $monthlyRevenue,
                        'premium_users' => $totalPremiumUsers,
                        'registered_users' => $newRegistrations,
                        'transaction_count' => $transactionCount
                    ],
                    'university' => [
                        'total_universities' => $totalUniversities,
                        'countries_covered' => $countriesCovered,
                        'archived_universities' => $archivedUniversities
                    ],
                    'scholarship' => [
                        'total_active_programs' => $totalActivePrograms,
                        'open_applications' => $openApplications,
                        'fully_funded_ratio' => $fullyFundedRatio,
                        'fully_funded_count' => $fullyFundedCount
                    ],
                    'assessment' => [
                        'total_diagnostic_questions' => $totalDiagnosticQuestions
                    ],
                    'shop' => [
                        'total_active_catalog' => $totalActiveCatalog,
                        'top_up_packages' => $topUpPackages,
                        'total_purchased' => $totalPurchasedItems
                    ],
                    'practice' => [
                        'total_practice_exams' => $totalPracticeExams,
                        'question_bank_size' => $questionBankSize,
                        'total_test_attempts' => $totalTestAttempts
                    ],
                    'gamification' => [
                        'total_active_badges' => $totalActiveBadges,
                        'badges_unlocked' => $badgesUnlocked
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Admin Dashboard Stats Error: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve dashboard statistics.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}