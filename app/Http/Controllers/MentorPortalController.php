<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL; 
use App\Models\User;
use App\Models\MentorAvailability;
use App\Models\ConsultationBooking;
use App\Models\ActionPlan;
use App\Models\UserMilestone;
use App\Models\SessionReview; 

class MentorPortalController extends Controller
{
    // =========================================================================
    // [BARU] 0. DASHBOARD MENTOR (Statistik & Saldo Pendapatan)
    // =========================================================================
    public function getDashboardStats(Request $request)
    {
        $mentor = Auth::user();

        if ($mentor->role !== 'mentor') {
            return response()->json(['status' => 'error', 'message' => 'Akses ditolak.'], 403);
        }

        // 1. Total Mentee Unik yang ditangani
        $totalMentees = ConsultationBooking::where('mentor_id', $mentor->id)
            ->distinct('mentee_id')
            ->count('mentee_id');

        // 2. Total Sesi Selesai
        $completedSessions = ConsultationBooking::where('mentor_id', $mentor->id)
            ->where('session_status', 'completed')
            ->count();

        // 3. Total Sesi Menunggu (Confirmed)
        $upcomingSessionsCount = ConsultationBooking::where('mentor_id', $mentor->id)
            ->where('session_status', 'confirmed')
            ->count();

        // 4. Saldo Pendapatan Terkini (Dompet Mentor)
        $earningBalance = $mentor->earning_balance;

        // 5. 5 Jadwal Terdekat
        $upcomingBookings = ConsultationBooking::with(['mentee:id,name,email,profile_picture_url', 'availability'])
            ->where('mentor_id', $mentor->id)
            ->where('session_status', 'confirmed')
            ->whereHas('availability', function($q) {
                $q->where('available_date', '>=', now()->toDateString());
            })
            // Sorting berdasarkan relasi tanggal dari tabel availability
            ->join('mentor_availabilities', 'consultation_bookings.availability_id', '=', 'mentor_availabilities.id')
            ->orderBy('mentor_availabilities.available_date', 'asc')
            ->orderBy('mentor_availabilities.start_time', 'asc')
            ->select('consultation_bookings.*') // Hindari collision kolom ID
            ->take(5)
            ->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Data dashboard mentor berhasil dimuat.',
            'data' => [
                'statistics' => [
                    'total_mentees' => $totalMentees,
                    'completed_sessions' => $completedSessions,
                    'upcoming_sessions' => $upcomingSessionsCount,
                    'earning_balance' => (float) $earningBalance,
                ],
                'upcoming_schedules' => $upcomingBookings
            ]
        ], 200);
    }

    // =========================================================================
    // [BARU] 0.5. RIWAYAT INVOICE & PENDAPATAN MENTOR
    // =========================================================================
    public function getEarningInvoices(Request $request)
    {
        $mentor = Auth::user();

        if ($mentor->role !== 'mentor') {
            return response()->json(['status' => 'error', 'message' => 'Akses ditolak.'], 403);
        }

        // Ambil histori sesi yang sudah selesai dan menghasilkan uang
        $invoices = ConsultationBooking::with(['mentee:id,name', 'availability'])
            ->where('mentor_id', $mentor->id)
            ->where('session_status', 'completed')
            ->orderBy('updated_at', 'desc')
            ->get()
            ->map(function ($booking) {
                return [
                    // Generate Nomor Invoice Unik
                    'invoice_id' => 'INV-MNT-' . date('Ym', strtotime($booking->updated_at)) . '-' . str_pad($booking->id, 5, '0', STR_PAD_LEFT),
                    'booking_id' => $booking->id,
                    'mentee_name' => $booking->mentee->name,
                    'consultation_date' => $booking->availability->available_date ?? null,
                    'time_slot' => ($booking->availability->start_time ?? '') . ' - ' . ($booking->availability->end_time ?? ''),
                    'earned_fee' => (float) $booking->mentor_earned_fee,
                    'payment_status' => 'Paid (Added to Balance)',
                    'completed_at' => $booking->updated_at->format('Y-m-d H:i:s')
                ];
            });

        return response()->json([
            'status' => 'success',
            'message' => 'Berhasil memuat daftar invoice pendapatan.',
            'data' => [
                'current_earning_balance' => (float) $mentor->earning_balance,
                'total_invoices' => $invoices->count(),
                'history' => $invoices
            ]
        ], 200);
    }

    /**
     * 1. MULTI-MENTEE DASHBOARD
     */
    public function getMenteeList(Request $request)
    {
        $mentor = Auth::user();

        if ($mentor->role !== 'mentor') {
            return response()->json(['status' => 'error', 'message' => 'Akses ditolak.'], 403);
        }

        $menteeIds = ConsultationBooking::where('mentor_id', $mentor->id)
            ->pluck('mentee_id')
            ->unique();

        $mentees = User::whereIn('id', $menteeIds)
            ->with(['milestones', 'documents', 'scholarships']) 
            ->get()
            ->map(function ($mentee) {
                $totalTasks = $mentee->milestones->count();
                $completedTasks = $mentee->milestones->where('status', 'completed')->count();
                $progressPercentage = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 2) : 0;

                return [
                    'mentee_id' => $mentee->id,
                    'name' => $mentee->name,
                    'email' => $mentee->email,
                    'phone_number' => $mentee->phone_number,
                    'target_scholarship' => $mentee->scholarships->first()?->name ?? 'Belum ditentukan',
                    'target_country' => $mentee->scholarships->first()?->country ?? 'Belum ditentukan',
                    'readiness_score' => $mentee->readiness_score,
                    'total_xp' => $mentee->xp_points,
                    'progress_summary' => [
                        'total_tasks' => $totalTasks,
                        'completed_tasks' => $completedTasks,
                        'progress_percentage' => $progressPercentage . '%'
                    ],
                    'uploaded_documents_count' => $mentee->documents->count()
                ];
            });

        return response()->json([
            'status' => 'success',
            'message' => 'Berhasil mengambil daftar mentee.',
            'data' => $mentees
        ], 200);
    }

    /**
     * 2. PRE-SESSION DOSSIER & PRE-READ
     */
    public function getPreSessionDossier($bookingId)
    {
        $mentor = Auth::user();

        if ($mentor->role !== 'mentor') {
            return response()->json(['status' => 'error', 'message' => 'Akses ditolak.'], 403);
        }

        $booking = ConsultationBooking::with([
                'mentee.milestones', 
                'mentee.documents', 
                'mentee.scholarships',
                'mentee.diagnosticAssessment', 
                'availability'
            ])
            ->where('id', $bookingId)
            ->where('mentor_id', $mentor->id)
            ->first();

        if (!$booking) {
            return response()->json(['status' => 'error', 'message' => 'Jadwal konsultasi tidak ditemukan atau bukan hak akses Anda.'], 404);
        }

        $mentee = $booking->mentee;

        return response()->json([
            'status' => 'success',
            'message' => 'Pre-session dossier & detail mentee berhasil dimuat.',
            'data' => [
                'booking_id' => $booking->id,
                'session_status' => $booking->session_status,
                'meeting_link' => $booking->meeting_link ?? 'Belum ada link meeting (Belum dikonfirmasi)',
                'token_cost' => $booking->token_cost,
                'mentor_earned_fee' => (float) $booking->mentor_earned_fee,
                'scheduled_at' => [
                    'date' => $booking->availability->available_date ?? null,
                    'start_time' => $booking->availability->start_time ?? null,
                    'end_time' => $booking->availability->end_time ?? null,
                ],
                'mentee_profile' => [
                    'id' => $mentee->id,
                    'name' => $mentee->name,
                    'email' => $mentee->email,
                    'phone_number' => $mentee->phone_number,
                    'gender' => $mentee->gender,
                    'headline' => $mentee->headline,
                    'bio' => $mentee->bio,
                    'profile_picture_url' => $mentee->profile_picture_url,
                    'readiness_score' => $mentee->readiness_score,
                    'xp_points' => $mentee->xp_points,
                    'target_scholarship' => $mentee->scholarships->first()?->name ?? 'Belum ditentukan',
                    'target_country' => $mentee->scholarships->first()?->country ?? 'Belum ditentukan',
                ],
                'assessment_gap_analysis' => $mentee->diagnosticAssessment ? [
                    'overall_score' => $mentee->diagnosticAssessment->overall_score ?? 0,
                    'academic_score' => $mentee->diagnosticAssessment->academic_score ?? 0,
                    'language_score' => $mentee->diagnosticAssessment->language_score ?? 0,
                    'weaknesses_mapping' => $mentee->diagnosticAssessment->weaknesses_mapping ?? [],
                    'strengths_mapping' => $mentee->diagnosticAssessment->strengths_mapping ?? [],
                ] : null,
                'milestones_progress' => $mentee->milestones->map(function ($m) {
                    return [
                        'milestone_id' => $m->id,
                        'parent_id' => $m->parent_id, 
                        'task_name' => $m->task_name,
                        'description' => $m->description,
                        'status' => $m->status,
                        'target_deadline' => $m->target_deadline,
                    ];
                }),
                'document_vault_pre_read' => $mentee->documents->map(function ($doc) {
                    return [
                        'document_id' => $doc->id,
                        'file_name' => $doc->file_name,
                        'file_type' => $doc->file_type,
                        'status' => $doc->status,
                        'preview_url' => URL::temporarySignedRoute(
                            'document.download', now()->addMinutes(60), ['documentVault' => $doc->id]
                        ),
                    ];
                }),
            ]
        ], 200);
    }

    /**
     * 3. CALENDAR MANAGEMENT: Mentor Mengatur Slot Waktu Luang
     */
    public function storeAvailability(Request $request)
    {
        $mentor = Auth::user();

        if ($mentor->role !== 'mentor') {
            return response()->json(['status' => 'error', 'message' => 'Akses ditolak.'], 403);
        }

        $request->validate([
            'available_date' => 'required|date|after_or_equal:today',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
        ]);

        $availability = MentorAvailability::create([
            'mentor_id' => $mentor->id,
            'available_date' => $request->available_date,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'is_booked' => false,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Slot waktu luang berhasil ditambahkan.',
            'data' => $availability
        ], 201);
    }

    public function getMyAvailabilities(Request $request)
    {
        $mentor = Auth::user();

        if ($mentor->role !== 'mentor') {
            return response()->json(['status' => 'error', 'message' => 'Akses ditolak.'], 403);
        }

        $slots = MentorAvailability::where('mentor_id', $mentor->id)
            ->orderBy('available_date', 'asc')
            ->orderBy('start_time', 'asc')
            ->get();

        return response()->json(['status' => 'success', 'data' => $slots], 200);
    }

    /**
     * 4A. MENTOR MENG-ACC/KONFIRMASI BOOKING
     */
    public function confirmBooking(Request $request, $bookingId)
    {
        $mentor = Auth::user();

        if ($mentor->role !== 'mentor') {
            return response()->json(['status' => 'error', 'message' => 'Akses ditolak.'], 403);
        }

        $request->validate([
            'meeting_link' => 'required|url',
        ]);

        $booking = ConsultationBooking::with(['mentee', 'availability'])
            ->where('id', $bookingId)
            ->where('mentor_id', $mentor->id)
            ->first();

        if (!$booking) {
            return response()->json(['status' => 'error', 'message' => 'Booking tidak ditemukan.'], 404);
        }

        DB::beginTransaction();
        try {
            $booking->update([
                'session_status' => 'confirmed',
                'meeting_link' => $request->meeting_link,
            ]);

            $booking->availability->update(['is_booked' => true]);
            DB::commit();

            $mentee = $booking->mentee;
            $slot = $booking->availability;

            try {
                Mail::send('emails.consultation_status', [
                    'menteeName' => $mentee->name,
                    'mentorName' => $mentor->name,
                    'status' => 'confirmed',
                    'date' => $slot->available_date,
                    'startTime' => $slot->start_time,
                    'endTime' => $slot->end_time,
                    'meetingLink' => $request->meeting_link,
                    'reason' => null
                ], function ($message) use ($mentee) {
                    $message->to($mentee->email)->subject('Jadwal Konsultasi Disetujui! - Platform Beasiswa');
                });
            } catch (\Exception $mailEx) {
                Log::warning('Gagal kirim email: ' . $mailEx->getMessage());
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Sesi konsultasi berhasil dikonfirmasi.',
                'data' => $booking
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * 4B. MENTOR MENOLAK (REJECT) BOOKING
     */
    public function rejectBooking(Request $request, $bookingId)
    {
        $mentor = Auth::user();

        if ($mentor->role !== 'mentor') {
            return response()->json(['status' => 'error', 'message' => 'Akses ditolak.'], 403);
        }

        $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        $booking = ConsultationBooking::with(['mentee', 'availability'])
            ->where('id', $bookingId)
            ->where('mentor_id', $mentor->id)
            ->first();

        if (!$booking) {
            return response()->json(['status' => 'error', 'message' => 'Booking tidak ditemukan.'], 404);
        }

        DB::beginTransaction();
        try {
            $booking->update(['session_status' => 'cancelled']);
            
            if ($booking->availability) {
                $booking->availability->delete();
            }

            // Kembalikan token ke mentee
            $booking->mentee->increment('token_balance', 1);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Sesi ditolak dan token dikembalikan ke mentee.',
                'data' => $booking
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * 4C. MENTOR RESCHEDULE BOOKING
     */
    public function rescheduleBooking(Request $request, $bookingId)
    {
        $mentor = Auth::user();
        $request->validate([
            'new_availability_id' => 'required|exists:mentor_availabilities,id',
            'reason' => 'required|string|max:255',
        ]);

        $booking = ConsultationBooking::with(['mentee', 'availability'])
            ->where('id', $bookingId)->where('mentor_id', $mentor->id)->first();

        if (!$booking) return response()->json(['status' => 'error', 'message' => 'Booking tidak ditemukan.'], 404);

        $newAvailability = MentorAvailability::where('id', $request->new_availability_id)
            ->where('mentor_id', $mentor->id)->first();

        if (!$newAvailability || $newAvailability->is_booked) {
            return response()->json(['status' => 'error', 'message' => 'Slot jadwal baru tidak valid/sudah dibooking.'], 400);
        }

        DB::beginTransaction();
        try {
            $booking->availability->update(['is_booked' => false]);
            $booking->update([
                'availability_id' => $newAvailability->id,
                'session_status' => 'confirmed' 
            ]);
            $newAvailability->update(['is_booked' => true]);
            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Jadwal konsultasi berhasil di-reschedule.',
                'data' => $booking->load('availability')
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // [BARU] 4D. MENTOR MENYELESAIKAN SESI (TRIGGER PEMBAYARAN)
    // =========================================================================
    public function completeBooking(Request $request, $bookingId)
    {
        $mentor = Auth::user();

        if ($mentor->role !== 'mentor') {
            return response()->json(['status' => 'error', 'message' => 'Akses ditolak.'], 403);
        }

        $booking = ConsultationBooking::where('id', $bookingId)->where('mentor_id', $mentor->id)->first();

        if (!$booking) {
            return response()->json(['status' => 'error', 'message' => 'Booking tidak ditemukan.'], 404);
        }

        if ($booking->session_status === 'completed') {
            return response()->json(['status' => 'error', 'message' => 'Sesi ini sudah ditandai selesai sebelumnya.'], 400);
        }

        if ($booking->session_status !== 'confirmed') {
            return response()->json(['status' => 'error', 'message' => 'Hanya sesi yang dikonfirmasi yang bisa diselesaikan.'], 400);
        }

        DB::beginTransaction();
        try {
            // 1. Ubah status sesi menjadi Selesai
            $booking->update(['session_status' => 'completed']);

            // 2. Lock & Increment saldo dompet Mentor
            $lockedMentor = User::where('id', $mentor->id)->lockForUpdate()->first();
            $lockedMentor->increment('earning_balance', $booking->mentor_earned_fee);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Sesi berhasil diselesaikan! Dana Rp ' . number_format($booking->mentor_earned_fee, 0, ',', '.') . ' telah ditambahkan ke dompet Anda.',
                'data' => [
                    'booking_id' => $booking->id,
                    'earned_fee' => (float) $booking->mentor_earned_fee,
                    'new_earning_balance' => (float) $lockedMentor->earning_balance
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Gagal menyelesaikan sesi: ' . $e->getMessage()], 500);
        }
    }

    /**
     * 5. CUSTOM ACTION PLAN MANAGEMENT (TASK BRANCHING)
     */
    public function storeActionPlan(Request $request, $bookingId)
    {
        $mentor = Auth::user();

        if ($mentor->role !== 'mentor') {
            return response()->json(['status' => 'error', 'message' => 'Akses ditolak.'], 403);
        }

        $request->validate([
            'task_description' => 'required|string|max:255',
            'deadline' => 'required|date|after_or_equal:today',
            'parent_milestone_id' => 'nullable|exists:user_milestones,id' 
        ]);

        $booking = ConsultationBooking::where('id', $bookingId)
            ->where('mentor_id', $mentor->id)
            ->first();

        if (!$booking) {
            return response()->json(['status' => 'error', 'message' => 'Booking tidak ditemukan.'], 404);
        }

        if (!in_array($booking->session_status, ['confirmed', 'completed'])) {
            return response()->json([
                'status' => 'error', 
                'message' => 'Hanya bisa memberikan Action Plan pada sesi yang sudah disetujui (confirmed/completed).'
            ], 400);
        }

        DB::beginTransaction();
        try {
            $actionPlan = ActionPlan::create([
                'booking_id' => $booking->id,
                'mentee_id' => $booking->mentee_id,
                'task_description' => $request->task_description,
                'deadline' => $request->deadline,
                'is_completed' => false,
            ]);

            $inheritedScholarshipId = null;
            if ($request->filled('parent_milestone_id')) {
                $parentMilestone = UserMilestone::where('id', $request->parent_milestone_id)
                    ->where('user_id', $booking->mentee_id)
                    ->first();
                
                if ($parentMilestone) {
                    $inheritedScholarshipId = $parentMilestone->scholarship_id;
                }
            }

            UserMilestone::create([
                'user_id' => $booking->mentee_id,
                'parent_id' => $request->parent_milestone_id ?? null, 
                'scholarship_id' => $inheritedScholarshipId,          
                'task_name' => '[Action Plan Mentor]: ' . $request->task_description,
                'description' => 'Tugas tambahan dari hasil sesi mentoring 1-on-1.',
                'target_deadline' => $request->deadline,
                'status' => 'pending',
                'source' => 'mentor',
                'is_mandatory' => true,
                'xp_reward' => 50,
            ]);

            // Jika Action Plan dibuat, dan sesi belum "completed", otomatis selesaikan sesi dan cairkan dana!
            if ($booking->session_status !== 'completed') {
                $booking->update(['session_status' => 'completed']);
                
                $lockedMentor = User::where('id', $mentor->id)->lockForUpdate()->first();
                $lockedMentor->increment('earning_balance', $booking->mentor_earned_fee);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Action plan berhasil dibuat dan sesi otomatis ditandai selesai.',
                'data' => $actionPlan,
                'branch_info' => $request->filled('parent_milestone_id') ? 'Ditambahkan sebagai sub-task dari milestone ID: ' . $request->parent_milestone_id : 'Ditambahkan sebagai tugas utama baru.'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * 6. MENTOR MEMBERIKAN EVALUASI / CATATAN UNTUK MENTEE (REVIEW SISI MENTOR)
     */
    public function submitMenteeReview(Request $request, $bookingId)
    {
        $mentor = Auth::user();

        if ($mentor->role !== 'mentor') {
            return response()->json(['status' => 'error', 'message' => 'Akses ditolak.'], 403);
        }

        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'feedback' => 'nullable|string|max:1000',
        ]);

        $booking = ConsultationBooking::where('id', $bookingId)
            ->where('mentor_id', $mentor->id)
            ->first();

        if (!$booking) {
            return response()->json(['status' => 'error', 'message' => 'Booking tidak ditemukan.'], 404);
        }

        if (!in_array($booking->session_status, ['confirmed', 'completed'])) {
            return response()->json(['status' => 'error', 'message' => 'Hanya sesi yang sudah disetujui atau selesai yang bisa diberikan evaluasi.'], 400);
        }

        $existingReview = SessionReview::where('booking_id', $booking->id)
            ->where('reviewer_id', $mentor->id)
            ->first();

        if ($existingReview) {
            return response()->json(['status' => 'error', 'message' => 'Anda sudah memberikan evaluasi untuk mentee di sesi ini.'], 400);
        }

        try {
            $review = SessionReview::create([
                'booking_id'  => $booking->id,
                'reviewer_id' => $mentor->id,      
                'reviewee_id' => $booking->mentee_id, 
                'rating'      => $request->rating,
                'feedback'    => $request->feedback,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Evaluasi dan catatan untuk mentee berhasil disimpan.',
                'data' => $review
            ], 201);

        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Gagal menyimpan evaluasi: ' . $e->getMessage()], 500);
        }
    }
}