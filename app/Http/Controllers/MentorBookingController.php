<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Models\User;
use App\Models\MentorAvailability;
use App\Models\ConsultationBooking;

class MentorBookingController extends Controller
{
    /**
     * 1. MENTEE MELAKUKAN BOOKING JADWAL KONSULTASI 
     * (Eksklusif fasilitas akun premium, memotong 1 Token Mentor)
     */
    public function bookSession(Request $request)
    {
        $request->validate([
            'availability_id' => 'required|exists:mentor_availabilities,id',
        ]);

        $authUser = Auth::user();

        // Cek apakah user adalah mentee (role user biasa)
        if ($authUser->role !== 'user') {
            return response()->json([
                'status' => 'error',
                'message' => 'Hanya mentee/user yang dapat melakukan booking konsultasi mentor.'
            ], 403);
        }

        // Cek apakah user sudah berstatus premium
        if (!$authUser->is_premium) {
            return response()->json([
                'status' => 'error',
                'message' => 'Fitur booking konsultasi mentor eksklusif untuk member premium. Silakan upgrade akun terlebih dahulu.'
            ], 403);
        }

        DB::beginTransaction();
        try {
            // Ambil data mentee dengan Pessimistic Lock
            $mentee = User::where('id', $authUser->id)->lockForUpdate()->first();

            if ($mentee->token_balance < 1) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Token mentor Anda habis atau tidak mencukupi. Silakan beli paket token di Shop terlebih dahulu.'
                ], 402); 
            }

            // Ambil slot waktu dengan Pessimistic Lock
            $availability = MentorAvailability::with('mentor')
                ->where('id', $request->availability_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($availability->is_booked) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Maaf, slot waktu konsultasi ini sudah dibooking oleh orang lain.'
                ], 400);
            }

            // Simpan Consultation Booking
            $booking = ConsultationBooking::create([
                'mentee_id' => $mentee->id,
                'mentor_id' => $availability->mentor_id,
                'availability_id' => $availability->id,
                'token_cost' => 1, 
                'session_status' => 'pending',
                'meeting_link' => null, // Akan diisi oleh mentor/admin nanti
            ]);

            // Tandai slot mentor sebagai ter-book
            $availability->update(['is_booked' => true]);

            // Potong saldo token mentee
            $mentee->decrement('token_balance', 1);

            DB::commit();

            // Kirim Email Notifikasi ke Mentor (Opsional/Background)
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

            // Load relasi untuk response yang lebih informatif di frontend
            $booking->load(['mentor:id,name', 'availability:id,available_date,start_time,end_time']);

            return response()->json([
                'status' => 'success',
                'message' => 'Booking jadwal konsultasi berhasil dibuat! 1 Token telah dipotong.',
                'data' => [
                    'booking_details' => $booking,
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

    /**
     * 2. MENTEE MELIHAT DAFTAR BOOKING MEREKA SENDIRI
     * (Menampilkan info mentor, jadwal, status, dan link gmeet)
     */
    public function getMyBookings(Request $request)
    {
        $user = Auth::user();

        // Ambil data booking milik mentee yang sedang login, beserta relasi mentor dan ketersediaan waktu
        $bookings = ConsultationBooking::with([
            'mentor:id,name,email,profile_picture_url', // Ambil data mentor yang diperlukan saja
            'availability:id,available_date,start_time,end_time' // Ambil data jadwal
        ])
        ->where('mentee_id', $user->id)
        ->orderBy('created_at', 'desc') // Urutkan dari yang terbaru dibuat
        ->get();

        if ($bookings->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Anda belum memiliki riwayat booking konsultasi.',
                'data' => []
            ], 200);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Berhasil mengambil data riwayat booking.',
            'data' => $bookings
        ], 200);
    }
}