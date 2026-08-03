<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Models\MentorAvailability;
use App\Models\ConsultationBooking;

class MentorBookingController extends Controller
{
    /**
     * MENTEE MELAKUKAN BOOKING JADWAL KONSULTASI 
     * (Eksklusif fasilitas akun premium, memotong 1 Token Mentor)
     */
    public function bookSession(Request $request)
    {
        $request->validate([
            'availability_id' => 'required|exists:mentor_availabilities,id',
        ]);

        $mentee = Auth::user();

        // 1. Cek apakah user adalah mentee (role user biasa)
        if ($mentee->role !== 'user') {
            return response()->json([
                'status' => 'error',
                'message' => 'Hanya mentee/user yang dapat melakukan booking konsultasi mentor.'
            ], 403);
        }

        // 2. Cek apakah user sudah berstatus premium
        if (!$mentee->is_premium) {
            return response()->json([
                'status' => 'error',
                'message' => 'Fitur booking konsultasi mentor eksklusif untuk member premium. Silakan upgrade akun terlebih dahulu.'
            ], 403);
        }

        // 3. Cek apakah mentee memiliki token yang cukup
        if ($mentee->token_balance < 1) {
            return response()->json([
                'status' => 'error',
                'message' => 'Token mentor Anda habis atau tidak mencukupi. Silakan beli paket token di Shop terlebih dahulu.'
            ], 402); // 402 Payment Required
        }

        $availability = MentorAvailability::with('mentor')->findOrFail($request->availability_id);

        // 4. Cek apakah slot sudah dibooking orang lain
        if ($availability->is_booked) {
            return response()->json([
                'status' => 'error',
                'message' => 'Maaf, slot waktu konsultasi ini sudah dibooking oleh orang lain.'
            ], 400);
        }

        DB::beginTransaction();
        try {
            // 5. Simpan Consultation Booking dengan memotong token
            $booking = ConsultationBooking::create([
                'mentee_id' => $mentee->id,
                'mentor_id' => $availability->mentor_id,
                'availability_id' => $availability->id,
                'token_cost' => 1, // Menyimpan histori biaya token
                'session_status' => 'pending',
                'meeting_link' => null, 
            ]);

            // 6. Tandai slot mentor sebagai ter-book agar tidak bisa dipilih mentee lain
            $availability->update(['is_booked' => true]);

            // 7. Potong saldo token mentee (decrement menghindari race conditions)
            $mentee->decrement('token_balance', 1);

            DB::commit();

            // 8. Kirim Email Notifikasi ke Mentor secara otomatis
            $mentor = $availability->mentor;

            try {
                Mail::send('emails.mentor_booking_notification', [
                    'mentorName' => $mentor->name,
                    'menteeName' => $mentee->name,
                    'menteeEmail' => $mentee->email,
                    'date' => $availability->available_date,
                    'startTime' => $availability->start_time,
                    'endTime' => $availability->end_time,
                    'bookingId' => $booking->id
                ], function ($message) use ($mentor) {
                    $message->to($mentor->email)
                            ->subject('Permintaan Booking Jadwal Konsultasi Baru! - Platform Beasiswa');
                });
            } catch (\Exception $mailEx) {
                Log::warning('Gagal mengirim email notifikasi ke mentor: ' . $mailEx->getMessage());
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Booking jadwal konsultasi berhasil dibuat! 1 Token telah dipotong.',
                'data' => [
                    'booking_id' => $booking->id,
                    'mentor_id' => $booking->mentor_id,
                    'availability_id' => $booking->availability_id,
                    'session_status' => $booking->session_status,
                    'remaining_tokens' => $mentee->token_balance, 
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Mentor Booking Error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat memproses booking.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}