<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\ConsultationBooking;

class AdminFinanceController extends Controller
{
    /**
     * 1. GET ALL MENTOR FINANCES
     * Menampilkan daftar mentor beserta informasi keuangan mereka.
     */
    public function getMentorFinances(Request $request)
    {
        $admin = Auth::user();
        if ($admin->role !== 'admin') {
            return response()->json(['status' => 'error', 'message' => 'Akses ditolak.'], 403);
        }

        $mentors = User::where('role', 'mentor')
            ->select(
                'id', 
                'name', 
                'email', 
                'session_rate', 
                'earning_balance', 
                'bank_name', 
                'bank_account_number', 
                'bank_account_name'
            )
            ->get()
            ->map(function ($mentor) {
                // Kalkulasi total historis pendapatan yang pernah dihasilkan mentor (total all-time)
                $totalHistoricalEarned = ConsultationBooking::where('mentor_id', $mentor->id)
                    ->where('session_status', 'completed')
                    ->sum('mentor_earned_fee');

                // Kalkulasi total sesi yang sudah diselesaikan
                $totalCompletedSessions = ConsultationBooking::where('mentor_id', $mentor->id)
                    ->where('session_status', 'completed')
                    ->count();

                return [
                    'mentor_id' => $mentor->id,
                    'name' => $mentor->name,
                    'email' => $mentor->email,
                    'session_rate' => (float) $mentor->session_rate,
                    'current_balance' => (float) $mentor->earning_balance,
                    'total_historical_earned' => (float) $totalHistoricalEarned,
                    'total_completed_sessions' => $totalCompletedSessions,
                    'bank_details' => [
                        'bank_name' => $mentor->bank_name ?? 'Belum diatur',
                        'account_number' => $mentor->bank_account_number ?? 'Belum diatur',
                        'account_name' => $mentor->bank_account_name ?? 'Belum diatur',
                    ]
                ];
            });

        return response()->json([
            'status' => 'success',
            'message' => 'Data keuangan mentor berhasil dimuat.',
            'data' => $mentors
        ], 200);
    }

    /**
     * 2. UPDATE MENTOR RATE
     * Mengatur tarif per sesi (session_rate) untuk mentor tertentu.
     */
    public function updateMentorRate(Request $request, $id)
    {
        $admin = Auth::user();
        if ($admin->role !== 'admin') {
            return response()->json(['status' => 'error', 'message' => 'Akses ditolak.'], 403);
        }

        $request->validate([
            'session_rate' => 'required|numeric|min:0',
        ]);

        $mentor = User::where('id', $id)->where('role', 'mentor')->first();

        if (!$mentor) {
            return response()->json(['status' => 'error', 'message' => 'Mentor tidak ditemukan.'], 404);
        }

        $mentor->update([
            'session_rate' => $request->session_rate
        ]);

        return response()->json([
            'status' => 'success',
            'message' => "Tarif mentor {$mentor->name} berhasil diperbarui menjadi Rp " . number_format($request->session_rate, 0, ',', '.'),
            'data' => [
                'mentor_id' => $mentor->id,
                'name' => $mentor->name,
                'new_session_rate' => (float) $mentor->session_rate
            ]
        ], 200);
    }

    /**
     * 3. PROCESS PAYOUT / WITHDRAWAL
     * Mencatat pencairan dana ke mentor (mengurangi saldo dompet mentor).
     */
    public function processPayout(Request $request, $id)
    {
        $admin = Auth::user();
        if ($admin->role !== 'admin') {
            return response()->json(['status' => 'error', 'message' => 'Akses ditolak.'], 403);
        }

        $request->validate([
            'amount' => 'required|numeric|min:1',
            'notes' => 'nullable|string|max:255'
        ]);

        $payoutAmount = $request->amount;

        DB::beginTransaction();
        try {
            $mentor = User::where('id', $id)->where('role', 'mentor')->lockForUpdate()->first();

            if (!$mentor) {
                return response()->json(['status' => 'error', 'message' => 'Mentor tidak ditemukan.'], 404);
            }

            if ($mentor->earning_balance < $payoutAmount) {
                return response()->json([
                    'status' => 'error', 
                    'message' => "Saldo tidak mencukupi. Saldo saat ini: Rp " . number_format($mentor->earning_balance, 0, ',', '.')
                ], 400);
            }

            // Kurangi saldo mentor
            $mentor->decrement('earning_balance', $payoutAmount);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => "Pencairan dana sebesar Rp " . number_format($payoutAmount, 0, ',', '.') . " untuk {$mentor->name} berhasil diproses.",
                'data' => [
                    'mentor_id' => $mentor->id,
                    'name' => $mentor->name,
                    'payout_amount' => (float) $payoutAmount,
                    'remaining_balance' => (float) $mentor->earning_balance,
                    'notes' => $request->notes ?? 'Pencairan manual oleh Admin'
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Gagal memproses pencairan: ' . $e->getMessage()], 500);
        }
    }
}