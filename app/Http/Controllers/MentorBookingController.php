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
use App\Models\SessionReview;
use App\Models\MentorProfile;

class MentorBookingController extends Controller
{
    /**
     * 0. MENTEE MELIHAT JADWAL KETERSEDIAAN MENTOR
     */
    public function getMentorAvailability(Request $request)
    {
        $mentee = Auth::user();

        // 1. Tentukan ID mentor yang akan dicek.
        // Jika ada input 'mentor_id' dari request, gunakan itu. 
        // Jika tidak ada, gunakan 'assigned_mentor_id' milik mentee.
        $targetMentorId = $request->query('mentor_id') ?? $mentee->assigned_mentor_id;

        if (!$targetMentorId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Silakan tentukan mentor_id yang ingin dicek, atau pastikan Anda sudah memiliki mentor yang ditugaskan (assigned mentor).'
            ], 400);
        }

        // 2. Pastikan target user tersebut memang benar-benar seorang mentor
        $mentor = User::where('id', $targetMentorId)
            ->where('role', 'mentor')
            ->first();

        if (!$mentor) {
            return response()->json([
                'status' => 'error',
                'message' => 'Mentor tidak ditemukan atau pengguna tersebut bukan seorang mentor.'
            ], 404);
        }

        // 3. Ambil jadwal mentor yang belum dibooking dan belum lewat batas hari ini
        $availabilities = MentorAvailability::where('mentor_id', $mentor->id)
            ->where('is_booked', false)
            ->where('available_date', '>=', now()->toDateString())
            ->orderBy('available_date', 'asc')
            ->orderBy('start_time', 'asc')
            ->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Berhasil mengambil jadwal ketersediaan mentor.',
            'data' => [
                'mentor' => [
                    'id' => $mentor->id,
                    'name' => $mentor->name,
                    'profile_picture_url' => $mentor->profile_picture_url,
                    'headline' => $mentor->headline,
                ],
                'availabilities' => $availabilities
            ]
        ], 200);
    }

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

            $mentor = $availability->mentor;

            // =========================================================
            // PERBAIKAN: Menyimpan 'mentor_earned_fee' saat booking
            // Gaji dikunci dari data 'session_rate' mentor saat ini.
            // =========================================================
            $booking = ConsultationBooking::create([
                'mentee_id' => $mentee->id,
                'mentor_id' => $mentor->id,
                'availability_id' => $availability->id,
                'token_cost' => 1, 
                'mentor_earned_fee' => $mentor->session_rate ?? 0, // <-- MENGUNCI GAJI MENTOR
                'session_status' => 'pending',
                'meeting_link' => null, 
            ]);

            $availability->update(['is_booked' => true]);
            $mentee->decrement('token_balance', 1);

            DB::commit();

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
     * 2. MENTEE MELIHAT DAFTAR BOOKING MEREKA SENDIRI + INDIKATOR POP-UP RESCHEDULE
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

        // Cari booking yang di-reschedule dan belum di-acknowledge (untuk pemicu Pop-Up di Dashboard Mentee)
        $unacknowledgedReschedules = $bookings->filter(function ($b) {
            return $b->is_rescheduled && !$b->reschedule_acknowledged;
        })->map(function ($b) {
            return [
                'booking_id'        => $b->id,
                'mentor_name'       => $b->mentor->name ?? 'Mentor',
                'rescheduled_by'    => $b->rescheduled_by,
                'reschedule_reason' => $b->reschedule_reason ?? 'Penyesuaian jadwal dari mentor.',
                'new_schedule'      => [
                    'date'       => $b->availability->available_date ?? null,
                    'start_time' => $b->availability->start_time ?? null,
                    'end_time'   => $b->availability->end_time ?? null,
                ]
            ];
        })->values();

        return response()->json([
            'status'                         => 'success',
            'message'                        => 'Berhasil mengambil data riwayat booking.',
            'has_reschedule_notifications'   => $unacknowledgedReschedules->isNotEmpty(),
            'reschedule_popups'              => $unacknowledgedReschedules,
            'data'                           => $bookings
        ], 200);
    }

    /**
     * [BARU] 2B. CUKUP AMBIL DASHBOARD POP-UP NOTIFICATION UNTUK MENTEE
     */
    public function getReschedulePopups(Request $request)
    {
        $user = Auth::user();

        $popups = ConsultationBooking::with([
            'mentor:id,name,email,profile_picture_url',
            'availability:id,available_date,start_time,end_time'
        ])
        ->where('mentee_id', $user->id)
        ->where('is_rescheduled', true)
        ->where('reschedule_acknowledged', false)
        ->get()
        ->map(function ($b) {
            return [
                'booking_id'        => $b->id,
                'mentor_name'       => $b->mentor->name ?? 'Mentor',
                'rescheduled_by'    => $b->rescheduled_by,
                'reschedule_reason' => $b->reschedule_reason ?? 'Penyesuaian jadwal dari mentor.',
                'meeting_link'      => $b->meeting_link,
                'new_schedule'      => [
                    'date'       => $b->availability->available_date ?? null,
                    'start_time' => $b->availability->start_time ?? null,
                    'end_time'   => $b->availability->end_time ?? null,
                ]
            ];
        });

        return response()->json([
            'status'     => 'success',
            'show_popup' => $popups->isNotEmpty(),
            'popups'     => $popups
        ], 200);
    }

    /**
     * [BARU] 2C. MENTEE MENUTUP / MENGAKUI POP-UP RESCHEDULE
     */
    public function acknowledgeReschedule(Request $request, $bookingId)
    {
        $user = Auth::user();

        $booking = ConsultationBooking::where('id', $bookingId)
            ->where('mentee_id', $user->id)
            ->first();

        if (!$booking) {
            return response()->json(['status' => 'error', 'message' => 'Booking tidak ditemukan.'], 404);
        }

        $booking->update([
            'reschedule_acknowledged' => true
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Notifikasi pop-up reschedule telah di-acknowledge.',
            'data'    => [
                'booking_id'              => $booking->id,
                'reschedule_acknowledged' => true
            ]
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