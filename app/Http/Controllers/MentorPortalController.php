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

class MentorPortalController extends Controller
{
    /**
     * 1. MULTI-MENTEE DASHBOARD
     */
    public function getMenteeList(Request $request)
    {
        $mentor = Auth::user();

        if ($mentor->role !== 'mentor') {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak. Fitur ini khusus untuk Mentor.'
            ], 403);
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
                    'target_scholarship' => $mentee->scholarships->first()->name ?? 'Belum ditentukan',
                    'target_country' => $mentee->scholarships->first()->country ?? 'Belum ditentukan',
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
     * 2. PRE-SESSION DOSSIER & PRE-READ (Detail Lengkap Mentee & Meeting Link)
     */
    public function getPreSessionDossier($bookingId)
    {
        $mentor = Auth::user();

        if ($mentor->role !== 'mentor') {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak. Fitur ini khusus untuk Mentor.'
            ], 403);
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
            return response()->json([
                'status' => 'error',
                'message' => 'Jadwal konsultasi tidak ditemukan atau bukan hak akses Anda.'
            ], 404);
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
                    'target_scholarship' => $mentee->scholarships->first()->name ?? 'Belum ditentukan',
                    'target_country' => $mentee->scholarships->first()->country ?? 'Belum ditentukan',
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
                        // Menghasilkan Temporary Signed URL yang otomatis mendekripsi file saat dibuka
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
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak. Fitur ini khusus untuk Mentor.'
            ], 403);
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
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak.'
            ], 403);
        }

        $slots = MentorAvailability::where('mentor_id', $mentor->id)
            ->orderBy('available_date', 'asc')
            ->orderBy('start_time', 'asc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $slots
        ], 200);
    }

    /**
     * 4A. MENTOR MENG-ACC/KONFIRMASI BOOKING & KIRIM MEETING LINK
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
                    $message->to($mentee->email)
                            ->subject('Jadwal Konsultasi Disetujui! - Platform Beasiswa');
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
     * 4B. MENTOR MENOLAK (REJECT) BOOKING DAN MENGHAPUS SLOT AVAILABILITY
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

            // REFUND TOKEN KE MENTEE 
            $booking->mentee->increment('token_balance', 1);

            DB::commit();

            $mentee = $booking->mentee;
            $slot = $booking->availability;

            try {
                Mail::send('emails.consultation_status', [
                    'menteeName' => $mentee->name,
                    'mentorName' => $mentor->name,
                    'status' => 'cancelled',
                    'date' => $slot->available_date ?? '-',
                    'startTime' => $slot->start_time ?? '-',
                    'endTime' => $slot->end_time ?? '-',
                    'meetingLink' => null,
                    'reason' => $request->reason
                ], function ($message) use ($mentee) {
                    $message->to($mentee->email)
                            ->subject('Mohon Maaf, Jadwal Konsultasi Ditolak - Platform Beasiswa');
                });
            } catch (\Exception $mailEx) {
                Log::warning('Gagal kirim email: ' . $mailEx->getMessage());
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Sesi konsultasi ditolak, slot dihapus, dan 1 Token telah dikembalikan ke mentee.',
                'data' => $booking
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * 4C. MENTOR RESCHEDULE BOOKING KE SLOT BARU DENGAN ALASAN
     */
    public function rescheduleBooking(Request $request, $bookingId)
    {
        $mentor = Auth::user();

        if ($mentor->role !== 'mentor') {
            return response()->json(['status' => 'error', 'message' => 'Akses ditolak.'], 403);
        }

        $request->validate([
            'new_availability_id' => 'required|exists:mentor_availabilities,id',
            'reason' => 'required|string|max:255',
        ]);

        $booking = ConsultationBooking::with(['mentee', 'availability'])
            ->where('id', $bookingId)
            ->where('mentor_id', $mentor->id)
            ->first();

        if (!$booking) {
            return response()->json(['status' => 'error', 'message' => 'Booking tidak ditemukan.'], 404);
        }

        $newAvailability = MentorAvailability::where('id', $request->new_availability_id)
            ->where('mentor_id', $mentor->id)
            ->first();

        if (!$newAvailability || $newAvailability->is_booked) {
            return response()->json(['status' => 'error', 'message' => 'Slot jadwal baru tidak valid atau sudah dibooking.'], 400);
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

            $mentee = $booking->mentee;

            try {
                Mail::send('emails.consultation_status', [
                    'menteeName' => $mentee->name,
                    'mentorName' => $mentor->name,
                    'status' => 'rescheduled',
                    'date' => $newAvailability->available_date,
                    'startTime' => $newAvailability->start_time,
                    'endTime' => $newAvailability->end_time,
                    'meetingLink' => $booking->meeting_link,
                    'reason' => $request->reason
                ], function ($message) use ($mentee) {
                    $message->to($mentee->email)
                            ->subject('Perubahan Jadwal (Reschedule) Konsultasi - Platform Beasiswa');
                });
            } catch (\Exception $mailEx) {
                Log::warning('Gagal kirim email: ' . $mailEx->getMessage());
            }

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

    /**
     * 5. CUSTOM ACTION PLAN MANAGEMENT
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
        ]);

        $booking = ConsultationBooking::where('id', $bookingId)
            ->where('mentor_id', $mentor->id)
            ->first();

        if (!$booking) {
            return response()->json(['status' => 'error', 'message' => 'Booking tidak ditemukan.'], 404);
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

            \App\Models\UserMilestone::create([
                'user_id' => $booking->mentee_id,
                'scholarship_id' => null,
                'task_name' => '[Action Plan Mentor]: ' . $request->task_description,
                'description' => 'Tugas tambahan dari hasil sesi mentoring 1-on-1.',
                'target_deadline' => $request->deadline,
                'status' => 'pending',
                'source' => 'mentor',
                'is_mandatory' => true,
                'xp_reward' => 50,
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Action plan berhasil dibuat.',
                'data' => $actionPlan
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}