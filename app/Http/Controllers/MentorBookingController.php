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
use App\Models\SessionReview; // <-- IMPORT BARU
use App\Models\MentorProfile; // <-- IMPORT BARU

class MentorBookingController extends Controller
{
    /**
     * 1. MENTEE MELAKUKAN BOOKING JADWAL KONSULTASI 
     */
    public function bookSession(Request $request)
    {
        $request->validate([
            'availability_id' => 'required|exists:mentor_availabilities,id',
        ]);

        $authUser = Auth::user();

        if ($authUser->role !== 'user') {
            return response()->json([
                'status' => 'error',
                'message' => 'Hanya mentee/user yang dapat melakukan booking konsultasi mentor.'
            ], 403);
        }

        if (!$authUser->is_premium) {
            return response()->json([
                'status' => 'error',
                'message' => 'Fitur booking konsultasi mentor eksklusif untuk member premium. Silakan upgrade akun terlebih dahulu.'
            ], 403);
        }

        DB::beginTransaction();
        try {
            $mentee = User::where('id', $authUser->id)->lockForUpdate()->first();

            if ($mentee->token_balance < 1) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Token mentor Anda habis atau tidak mencukupi. Silakan beli paket token di Shop terlebih dahulu.'
                ], 402); 
            }

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

            $booking = ConsultationBooking::create([
                'mentee_id' => $mentee->id,
                'mentor_id' => $availability->mentor_id,
                'availability_id' => $availability->id,
                'token_cost' => 1, 
                'session_status' => 'pending',
                'meeting_link' => null, 
            ]);

            $availability->update(['is_booked' => true]);
            $mentee->decrement('token_balance', 1);

            DB::commit();

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
     */
    public function getMyBookings(Request $request)
    {
        $user = Auth::user();

        $bookings = ConsultationBooking::with([
            'mentor:id,name,email,profile_picture_url', 
            'availability:id,available_date,start_time,end_time',
            'reviews' // Load ulasan jika sudah pernah diberikan
        ])
        ->where('mentee_id', $user->id)
        ->orderBy('created_at', 'desc') 
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

    /**
     * 3. MENTEE MEMBERIKAN RATING & FEEDBACK KE MENTOR
     */
    public function submitReview(Request $request, $bookingId)
    {
        $mentee = Auth::user();

        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'feedback' => 'nullable|string|max:1000',
        ]);

        $booking = ConsultationBooking::where('id', $bookingId)
            ->where('mentee_id', $mentee->id)
            ->first();

        if (!$booking) {
            return response()->json(['status' => 'error', 'message' => 'Booking tidak ditemukan.'], 404);
        }

        if ($booking->session_status !== 'completed') {
            return response()->json(['status' => 'error', 'message' => 'Hanya sesi yang sudah selesai (completed) yang bisa diberikan ulasan.'], 400);
        }

        // Cek agar user tidak spam review di sesi yang sama
        $existingReview = SessionReview::where('booking_id', $booking->id)
            ->where('reviewer_id', $mentee->id)
            ->first();

        if ($existingReview) {
            return response()->json(['status' => 'error', 'message' => 'Anda sudah memberikan ulasan untuk sesi ini.'], 400);
        }

        DB::beginTransaction();
        try {
            // 1. Simpan Ulasan
            $review = SessionReview::create([
                'booking_id'  => $booking->id,
                'reviewer_id' => $mentee->id,
                'reviewee_id' => $booking->mentor_id,
                'rating'      => $request->rating,
                'feedback'    => $request->feedback,
            ]);

            // 2. Kalkulasi Rata-Rata Rating Mentor & Update MentorProfile
            $averageRating = SessionReview::where('reviewee_id', $booking->mentor_id)->avg('rating');

            MentorProfile::where('user_id', $booking->mentor_id)->update([
                'rating' => round($averageRating, 2) // Dibulatkan 2 desimal (cth: 4.85)
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Ulasan berhasil dikirim! Terima kasih atas feedback Anda.',
                'data' => $review
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Gagal menyimpan ulasan: ' . $e->getMessage()], 500);
        }
    }
}