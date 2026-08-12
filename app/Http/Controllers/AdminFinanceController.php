<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

    /**
     * 4. GET CONSULTATION PROOFS & DATA (ADMIN AUDIT)
     * Menampilkan daftar sesi konsultasi yang memiliki foto bukti pelaksanaan.
     */
    public function getConsultationProofs(Request $request)
    {
        $admin = Auth::user();
        if ($admin->role !== 'admin') {
            return response()->json(['status' => 'error', 'message' => 'Akses ditolak.'], 403);
        }

        $query = ConsultationBooking::with([
            'mentor:id,name,email,session_rate,earning_balance', 
            'mentee:id,name,email', 
            'availability'
        ])->where(function($q) {
            $q->whereNotNull('session_proof')
              ->orWhere('session_status', 'completed');
        });

        // Filter berdasarkan proof_status (pending, approved, rejected)
        if ($request->has('proof_status') && in_array($request->proof_status, ['pending', 'approved', 'rejected'])) {
            $query->where('proof_status', $request->proof_status);
        }

        // Filter berdasarkan mentor_id
        if ($request->has('mentor_id')) {
            $query->where('mentor_id', $request->mentor_id);
        }

        $consultations = $query->orderBy('updated_at', 'desc')->get()->map(function ($booking) {
            return [
                'booking_id' => $booking->id,
                'session_status' => $booking->session_status,
                'proof_status' => $booking->proof_status ?? 'pending',
                'proof_review_notes' => $booking->proof_review_notes,
                'session_proof' => $booking->session_proof,
                'session_proof_url' => $booking->session_proof ? asset(Storage::url($booking->session_proof)) : null,
                'meeting_link' => $booking->meeting_link,
                'mentor_earned_fee' => (float) $booking->mentor_earned_fee,
                'scheduled_date' => $booking->availability->available_date ?? null,
                'time_slot' => ($booking->availability->start_time ?? '') . ' - ' . ($booking->availability->end_time ?? ''),
                'mentor' => [
                    'id' => $booking->mentor->id ?? null,
                    'name' => $booking->mentor->name ?? null,
                    'email' => $booking->mentor->email ?? null,
                    'current_balance' => (float) ($booking->mentor->earning_balance ?? 0),
                ],
                'mentee' => [
                    'id' => $booking->mentee->id ?? null,
                    'name' => $booking->mentee->name ?? null,
                    'email' => $booking->mentee->email ?? null,
                ],
                'completed_at' => $booking->updated_at->format('Y-m-d H:i:s')
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar bukti dan data konsultasi mentor berhasil dimuat.',
            'data' => $consultations
        ], 200);
    }

    /**
     * 5. VERIFY CONSULTATION PROOF (APPROVE / REJECT WITHHOLD PAYOUT)
     * Admin memeriksa keabsahan foto bukti sesi. Jika ditolak, bayaran mentor ditangguhkan/dikurangi.
     */
    public function verifyConsultationProof(Request $request, $bookingId)
    {
        $admin = Auth::user();
        if ($admin->role !== 'admin') {
            return response()->json(['status' => 'error', 'message' => 'Akses ditolak.'], 403);
        }

        $request->validate([
            'status' => 'required|in:approved,rejected',
            'admin_notes' => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();
        try {
            $booking = ConsultationBooking::with(['mentor'])->where('id', $bookingId)->first();

            if (!$booking) {
                return response()->json(['status' => 'error', 'message' => 'Data konsultasi tidak ditemukan.'], 404);
            }

            $oldProofStatus = $booking->proof_status ?? 'pending';
            $newProofStatus = $request->status;
            $fee = (float) $booking->mentor_earned_fee;

            $mentor = User::where('id', $booking->mentor_id)->lockForUpdate()->first();

            if ($newProofStatus === 'rejected') {
                // Jika sebelumnya belum ditolak (misal pending atau approved), kurangi saldo dompet mentor (tangguhkan bayaran)
                if ($oldProofStatus !== 'rejected' && $mentor && $fee > 0) {
                    $mentor->decrement('earning_balance', $fee);
                }

                $booking->update([
                    'proof_status' => 'rejected',
                    'proof_review_notes' => $request->admin_notes ?? 'Bukti sesi tidak valid / ditolak oleh Admin. Bayaran ditangguhkan.',
                ]);

                DB::commit();

                return response()->json([
                    'status' => 'success',
                    'message' => "Bukti sesi konsultasi (ID: {$booking->id}) dinyatakan TIDAK VALID. Bayaran mentor sebesar Rp " . number_format($fee, 0, ',', '.') . " berhasil ditangguhkan.",
                    'data' => [
                        'booking_id' => $booking->id,
                        'proof_status' => 'rejected',
                        'proof_review_notes' => $booking->proof_review_notes,
                        'mentor_id' => $mentor->id ?? null,
                        'mentor_name' => $mentor->name ?? null,
                        'updated_earning_balance' => (float) ($mentor->earning_balance ?? 0)
                    ]
                ], 200);

            } else { // approved
                // Jika sebelumnya sempat ditolak, kembalikan saldo ke dompet mentor
                if ($oldProofStatus === 'rejected' && $mentor && $fee > 0) {
                    $mentor->increment('earning_balance', $fee);
                }

                $booking->update([
                    'proof_status' => 'approved',
                    'proof_review_notes' => $request->admin_notes ?? 'Bukti sesi valid & telah disetujui Admin.',
                ]);

                DB::commit();

                return response()->json([
                    'status' => 'success',
                    'message' => "Bukti sesi konsultasi (ID: {$booking->id}) dinyatakan VALID dan disetujui. Bayaran mentor Rp " . number_format($fee, 0, ',', '.') . " siap dicairkan.",
                    'data' => [
                        'booking_id' => $booking->id,
                        'proof_status' => 'approved',
                        'proof_review_notes' => $booking->proof_review_notes,
                        'mentor_id' => $mentor->id ?? null,
                        'mentor_name' => $mentor->name ?? null,
                        'updated_earning_balance' => (float) ($mentor->earning_balance ?? 0)
                    ]
                ], 200);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Gagal memverifikasi bukti sesi: ' . $e->getMessage()], 500);
        }
    }
}