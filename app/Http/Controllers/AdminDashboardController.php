<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminDashboardController extends Controller
{
    /**
     * Mendapatkan statistik data untuk Admin Dashboard
     */
    public function getDashboardStats(Request $request)
    {
        try {
            // 1. Total Revenue: Total gross_amount dari tabel transactions yang statusnya 'success'
            $totalRevenue = DB::table('transactions')
                ->where('payment_status', 'success')
                ->sum('gross_amount');

            // 2. Total User Join: Total pendaftar dengan role 'user'
            $totalUserJoin = User::where('role', 'user')->count();

            // 3. Total User Premium: Total user yang status is_premium = true
            $totalUserPremium = User::where('is_premium', true)->count();

            // 4. Total Scholarship: Menghitung jumlah target beasiswa yang unik dari inputan user
            // (Karena belum ada tabel master 'scholarships' di migrasi)
            $totalScholarship = DB::table('users')
                ->whereNotNull('primary_scholarship_target')
                ->distinct('primary_scholarship_target')
                ->count('primary_scholarship_target');

            // 5. Total University: Placeholder
            // (Karena belum ada tabel/kolom universitas pada migrasi saat ini)
            $totalUniversity = 0; 

            return response()->json([
                'status' => 'success',
                'message' => 'Admin dashboard statistics retrieved successfully.',
                'data' => [
                    'total_revenue'      => (float) $totalRevenue,
                    'total_user_join'    => $totalUserJoin,
                    'total_user_premium' => $totalUserPremium,
                    'total_scholarship'  => $totalScholarship,
                    'total_university'   => $totalUniversity,
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