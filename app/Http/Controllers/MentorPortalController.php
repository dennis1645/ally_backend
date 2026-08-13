<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL; 
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\Models\MentorAvailability;
use App\Models\ConsultationBooking;
use App\Models\ActionPlan;
use App\Models\UserMilestone;
use App\Models\MilestoneSubmission;
use App\Models\DocumentVault;
use App\Models\SessionReview; 
use App\Services\GamificationService; 

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

        // 1. Total Mentee Unik yang ditangani (baik dari assigned_mentor_id maupun booking)
        $assignedMenteeIds = User::where('assigned_mentor_id', $mentor->id)->pluck('id')->toArray();
        $bookedMenteeIds   = ConsultationBooking::where('mentor_id', $mentor->id)->pluck('mentee_id')->toArray();
        $totalMentees = count(array_unique(array_merge($assignedMenteeIds, $bookedMenteeIds)));

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

        // Ambil mentee yang di-assign langsung via AI Matcher/profile (assigned_mentor_id) DAN yang melakukan booking sesi
        $assignedMenteeIds = User::where('assigned_mentor_id', $mentor->id)->pluck('id')->toArray();
        $bookedMenteeIds   = ConsultationBooking::where('mentor_id', $mentor->id)->pluck('mentee_id')->toArray();
        $menteeIds         = array_values(array_unique(array_merge($assignedMenteeIds, $bookedMenteeIds)));

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
                        'milestone_id'    => $m->id,
                        'parent_id'       => $m->parent_id, 
                        'task_name'       => $m->task_name,
                        'description'     => $m->description,
                        'status'          => $m->status,
                        'target_date'     => $m->target_date ?? $m->target_deadline,
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
     * 3. CALENDAR MANAGEMENT: Mentor Mengatur Slot Waktu Luang (Dukung Single & Bulk Batch Slots)
     */
    public function storeAvailability(Request $request)
    {
        $mentor = Auth::user();

        if ($mentor->role !== 'mentor') {
            return response()->json(['status' => 'error', 'message' => 'Akses ditolak.'], 403);
        }

        // Normalisasi input: Dukung bulk array (availabilities/slots) maupun single object
        $rawSlots = $request->input('availabilities') ?? $request->input('slots');

        if (empty($rawSlots)) {
            $rawSlots = [
                [
                    'available_date' => $request->input('available_date'),
                    'start_time'     => $request->input('start_time'),
                    'end_time'       => $request->input('end_time'),
                ]
            ];
        }

        $validatedSlots = [];
        foreach ($rawSlots as $index => $slot) {
            $date  = trim($slot['available_date'] ?? '');
            $start = trim($slot['start_time'] ?? '');
            $end   = trim($slot['end_time'] ?? '');

            if (empty($date) || empty($start) || empty($end)) {
                return response()->json([
                    'status'  => 'error',
                    'message' => "Format jadwal tidak lengkap pada slot ke-" . ($index + 1) . ". Pastikan available_date, start_time, dan end_time terisi."
                ], 422);
            }

            // Normalisasi jam format H:i (misal "09:00" -> "09:00:00")
            $startTimeFormatted = strlen($start) == 5 ? $start . ':00' : $start;
            $endTimeFormatted   = strlen($end) == 5 ? $end . ':00' : $end;

            $validatedSlots[] = [
                'mentor_id'      => $mentor->id,
                'available_date' => $date,
                'start_time'     => $startTimeFormatted,
                'end_time'       => $endTimeFormatted,
                'is_booked'      => false,
            ];
        }

        DB::beginTransaction();
        try {
            $createdSlots = [];
            foreach ($validatedSlots as $slotData) {
                // Hindari duplikasi slot yang persis sama
                $existing = MentorAvailability::where('mentor_id', $mentor->id)
                    ->where('available_date', $slotData['available_date'])
                    ->where('start_time', $slotData['start_time'])
                    ->where('end_time', $slotData['end_time'])
                    ->first();

                if (!$existing) {
                    $createdSlots[] = MentorAvailability::create($slotData);
                } else {
                    $createdSlots[] = $existing;
                }
            }

            DB::commit();

            return response()->json([
                'status'  => 'success',
                'message' => count($createdSlots) . ' slot waktu luang berhasil disimpan.',
                'data'    => $createdSlots
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Gagal menyimpan slot ketersediaan: ' . $e->getMessage()], 500);
        }
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
     * 4B. MENTOR MENOLAK BOOKING KONSULTASI
     */
    public function rejectBooking(Request $request, $bookingId)
    {
        $mentor = Auth::user();

        if ($mentor->role !== 'mentor') {
            return response()->json(['status' => 'error', 'message' => 'Akses ditolak.'], 403);
        }

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
     * 4C. MENTOR RESCHEDULE BOOKING (Otomatis Buat Slot Baru/Pilih Slot + Pemicu Pop-Up Mentee)
     */
    public function rescheduleBooking(Request $request, $bookingId)
    {
        $mentor = Auth::user();

        if ($mentor->role !== 'mentor') {
            return response()->json(['status' => 'error', 'message' => 'Akses ditolak.'], 403);
        }

        $booking = ConsultationBooking::with(['mentee', 'availability'])
            ->where('id', $bookingId)
            ->where('mentor_id', $mentor->id)
            ->first();

        if (!$booking) {
            return response()->json(['status' => 'error', 'message' => 'Booking tidak ditemukan.'], 404);
        }

        $reason = $request->input('reason') ?? $request->input('reschedule_reason') ?? 'Penyesuaian jadwal dari mentor.';

        DB::beginTransaction();
        try {
            $newAvailability = null;

            // Pilihan A: Menggunakan ID slot yang sudah ada (new_availability_id)
            if ($request->filled('new_availability_id')) {
                $newAvailability = MentorAvailability::where('id', $request->new_availability_id)
                    ->where('mentor_id', $mentor->id)
                    ->first();

                if (!$newAvailability || ($newAvailability->is_booked && $newAvailability->id !== $booking->availability_id)) {
                    return response()->json(['status' => 'error', 'message' => 'Slot jadwal baru tidak valid atau sudah dibooking.'], 400);
                }
            } 
            // Pilihan B: Mentor langsung membuat jam & tanggal baru secara instan saat reschedule
            elseif ($request->filled('available_date') && $request->filled('start_time') && $request->filled('end_time')) {
                $start = strlen($request->start_time) == 5 ? $request->start_time . ':00' : $request->start_time;
                $end   = strlen($request->end_time) == 5 ? $request->end_time . ':00' : $request->end_time;

                $newAvailability = MentorAvailability::create([
                    'mentor_id'      => $mentor->id,
                    'available_date' => $request->available_date,
                    'start_time'     => $start,
                    'end_time'       => $end,
                    'is_booked'      => true,
                ]);
            } else {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Harap sertakan new_availability_id atau tentukan available_date, start_time, dan end_time baru.'
                ], 422);
            }

            // Unbook availability lama jika beda dengan slot baru
            if ($booking->availability && $booking->availability_id !== $newAvailability->id) {
                $booking->availability->update(['is_booked' => false]);
            }

            // Update slot baru menjadi booked
            $newAvailability->update(['is_booked' => true]);

            // Update booking data dengan status reschedule & flag notifikasi pop-up mentee
            $booking->update([
                'availability_id'        => $newAvailability->id,
                'session_status'         => 'confirmed',
                'is_rescheduled'         => true,
                'rescheduled_by'         => 'mentor',
                'reschedule_reason'      => $reason,
                'reschedule_acknowledged' => false, // Mentee akan mendapatkan notifikasi pop-up di dashboard!
            ]);

            DB::commit();

            // Kirim notifikasi email ke mentee
            try {
                $mentee = $booking->mentee;
                if ($mentee && $mentee->email) {
                    Mail::send('emails.consultation_status', [
                        'menteeName'  => $mentee->name,
                        'mentorName'  => $mentor->name,
                        'status'      => 'rescheduled',
                        'date'        => $newAvailability->available_date,
                        'startTime'   => $newAvailability->start_time,
                        'endTime'     => $newAvailability->end_time,
                        'meetingLink' => $booking->meeting_link,
                        'reason'      => $reason
                    ], function ($message) use ($mentee) {
                        $message->to($mentee->email)->subject('Pemberitahuan Reschedule Jadwal Konsultasi - Platform Beasiswa');
                    });
                }
            } catch (\Exception $mailEx) {
                Log::warning('Gagal kirim email reschedule: ' . $mailEx->getMessage());
            }

            return response()->json([
                'status'  => 'success',
                'message' => 'Jadwal konsultasi berhasil di-reschedule. Notifikasi pop-up telah diaktifkan untuk mentee.',
                'data'    => [
                    'booking'               => $booking->load(['availability', 'mentee:id,name,email']),
                    'reschedule_reason'     => $reason,
                    'pop_up_indicator'     => true,
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Gagal reschedule: ' . $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // [BARU] 4D. MENTOR MENYELESAIKAN SESI (WAJIB UPLOAD BUKTI SESI + TRIGGER PEMBAYARAN)
    // =========================================================================
    public function completeBooking(Request $request, $bookingId)
    {
        $mentor = Auth::user();

        if ($mentor->role !== 'mentor') {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak.',
                'data' => null
            ], 403);
        }

        $booking = ConsultationBooking::where('id', $bookingId)->where('mentor_id', $mentor->id)->first();

        if (!$booking) {
            return response()->json([
                'status' => 'error',
                'message' => 'Booking tidak ditemukan.',
                'data' => null
            ], 404);
        }

        if ($booking->session_status === 'completed') {
            return response()->json([
                'status' => 'error',
                'message' => 'Sesi ini sudah ditandai selesai sebelumnya.',
                'data' => null
            ], 400);
        }

        if ($booking->session_status !== 'confirmed') {
            return response()->json([
                'status' => 'error',
                'message' => 'Hanya sesi yang dikonfirmasi (confirmed) yang bisa diselesaikan.',
                'data' => null
            ], 400);
        }

        // Cari file upload dari beberapa key alternatif (session_proof, proof_image, proof, file)
        $fileKey = null;
        foreach (['session_proof', 'proof_image', 'proof', 'file'] as $key) {
            if ($request->hasFile($key)) {
                $fileKey = $key;
                break;
            }
        }

        if (!$fileKey) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bukti sesi konsultasi (session_proof) wajib diunggah.',
                'data' => null
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            $fileKey => 'required|image|mimes:jpg,jpeg,png|max:5120',
        ], [
            "{$fileKey}.required" => 'Bukti sesi konsultasi wajib diunggah.',
            "{$fileKey}.image" => 'Bukti sesi harus berupa file gambar.',
            "{$fileKey}.mimes" => 'Format gambar bukti sesi hanya boleh JPG, JPEG, atau PNG.',
            "{$fileKey}.max" => 'Ukuran gambar bukti sesi maksimal 5MB (5120 KB).',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
                'data' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Upload berkas bukti sesi ke storage/app/public/session_proofs
            $proofFile = $request->file($fileKey);
            $proofPath = $proofFile->store('session_proofs', 'public');
            $proofUrl = Storage::url($proofPath);

            // 1. Ubah status sesi menjadi Selesai & simpan bukti (menunggu verifikasi admin)
            $booking->update([
                'session_status' => 'completed',
                'session_proof' => $proofPath,
                'proof_status' => 'pending',
            ]);

            // 2. Lock & Increment saldo dompet Mentor
            $lockedMentor = User::where('id', $mentor->id)->lockForUpdate()->first();
            $lockedMentor->increment('earning_balance', $booking->mentor_earned_fee);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Sesi berhasil diselesaikan! Bukti sesi telah diunggah dan dana Rp ' . number_format($booking->mentor_earned_fee, 0, ',', '.') . ' telah ditambahkan ke dompet Anda.',
                'data' => [
                    'booking_id' => $booking->id,
                    'session_status' => $booking->session_status,
                    'session_proof' => $proofPath,
                    'session_proof_url' => asset($proofUrl),
                    'earned_fee' => (float) $booking->mentor_earned_fee,
                    'new_earning_balance' => (float) $lockedMentor->earning_balance
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menyelesaikan sesi: ' . $e->getMessage(),
                'data' => null
            ], 500);
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

        $booking = ConsultationBooking::where('id', $bookingId)
            ->where('mentor_id', $mentor->id)
            ->first();

        if (!$booking) {
            return response()->json(['status' => 'error', 'message' => 'Booking tidak ditemukan.'], 404);
        }

        if (!in_array($booking->session_status, ['confirmed', 'completed'])) {
            return response()->json([
                'status'  => 'error', 
                'message' => 'Hanya bisa memberikan Action Plan pada sesi yang sudah disetujui (confirmed/completed).'
            ], 400);
        }

        // Normalisasi input: Dukung pengiriman array bulk (action_plans/tasks) maupun objek tunggal
        $rawPlans = $request->input('action_plans') ?? $request->input('tasks');
        
        if (empty($rawPlans)) {
            // Format tunggal (single task)
            $rawPlans = [
                [
                    'task_title'       => $request->input('task_title') ?? $request->input('title') ?? $request->input('task_name'),
                    'task_description' => $request->input('task_description') ?? $request->input('description'),
                    'mentor_note'      => $request->input('mentor_note') ?? $request->input('note'),
                    'deadline'         => $request->input('deadline') ?? $request->input('target_date'),
                ]
            ];
        }

        // Validasi setiap item action plan
        $validatedTasks = [];
        foreach ($rawPlans as $index => $item) {
            $taskTitle       = trim($item['task_title'] ?? $item['title'] ?? $item['task_name'] ?? '');
            $taskDescription = trim($item['task_description'] ?? $item['description'] ?? '');
            $mentorNote      = trim($item['mentor_note'] ?? $item['note'] ?? '');
            $deadline        = trim($item['deadline'] ?? $item['target_date'] ?? '');

            if (empty($taskTitle)) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Judul tugas (task_title) wajib diisi pada item ke-" . ($index + 1) . "."
                ], 422);
            }

            if (empty($deadline)) {
                $deadline = now()->addDays(7)->toDateString(); // Default 7 hari ke depan jika kosong
            }

            $validatedTasks[] = [
                'task_title'       => $taskTitle,
                'task_description' => $taskDescription,
                'mentor_note'      => $mentorNote,
                'deadline'         => $deadline,
            ];
        }

        // Cek parent milestone jika diberikan (Task Branching)
        $inheritedScholarshipId = null;
        $isDiscovered = true;
        $parentMilestoneId = $request->input('parent_milestone_id');

        if ($parentMilestoneId) {
            $parentMilestone = UserMilestone::where('id', $parentMilestoneId)
                ->where('user_id', $booking->mentee_id)
                ->first();

            if ($parentMilestone) {
                $inheritedScholarshipId = $parentMilestone->scholarship_id;
                $isDiscovered = (bool) $parentMilestone->is_discovered;
            }
        }

        DB::beginTransaction();
        try {
            $createdActionPlans = [];
            $createdMilestones  = [];

            foreach ($validatedTasks as $taskData) {
                // 1. Simpan record ActionPlan
                $actionPlan = ActionPlan::create([
                    'booking_id'       => $booking->id,
                    'mentee_id'        => $booking->mentee_id,
                    'task_title'       => $taskData['task_title'],
                    'task_description' => $taskData['task_description'],
                    'mentor_note'      => $taskData['mentor_note'],
                    'deadline'         => $taskData['deadline'],
                    'is_completed'     => false,
                ]);

                // 2. Buat UserMilestone baru di timeline mentee
                $fullDescription = $taskData['task_description'];
                if (!empty($taskData['mentor_note'])) {
                    $fullDescription .= ($fullDescription ? "\n\nCatatan Mentor: " : "Catatan Mentor: ") . $taskData['mentor_note'];
                }

                $milestone = UserMilestone::create([
                    'user_id'        => $booking->mentee_id,
                    'parent_id'      => $parentMilestoneId ?: null,
                    'scholarship_id' => $inheritedScholarshipId,
                    'task_name'      => '[Action Plan Mentor]: ' . $taskData['task_title'],
                    'description'    => $fullDescription ?: 'Tugas tambahan dari hasil sesi mentoring 1-on-1.',
                    'start_date'     => now()->toDateString(),
                    'target_date'    => $taskData['deadline'],
                    'status'         => 'pending',
                    'source'         => 'mentor',
                    'is_mandatory'   => true,
                    'is_discovered'  => $isDiscovered,
                    'xp_reward'      => 50,
                ]);

                $createdActionPlans[] = $actionPlan;
                $createdMilestones[]  = $milestone;
            }

            DB::commit();

            return response()->json([
                'status'  => 'success',
                'message' => count($createdActionPlans) . ' Action Plan berhasil dibuat untuk mentee.',
                'data'    => [
                    'action_plans'    => $createdActionPlans,
                    'user_milestones' => $createdMilestones,
                ],
                'branch_info' => $parentMilestoneId 
                    ? 'Tugas ditambahkan ke cabang milestone ID: ' . $parentMilestoneId 
                    : 'Tugas ditambahkan sebagai milestone utama baru.'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Gagal membuat Action Plan: ' . $e->getMessage()], 500);
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

    /**
     * AMBIL DAFTAR PENGIRIMAN TUGAS MENTEE (MENTOR AUDIT & REVIEW QUEUE)
     * Mentor dapat melihat tugas/dokumen/refleksi teks yang dikirim mentee.
     */
    public function getMenteeSubmissions(Request $request)
    {
        $mentor = Auth::user();
        if (!in_array($mentor->role, ['mentor', 'admin'])) {
            return response()->json(['status' => 'error', 'message' => 'Akses ditolak.'], 403);
        }

        // Ambil mentee ID yang di-assign langsung via AI Matcher (assigned_mentor_id) DAN dari booking sesi
        $assignedMenteeIds = User::where('assigned_mentor_id', $mentor->id)->pluck('id')->toArray();
        $bookedMenteeIds   = ConsultationBooking::where('mentor_id', $mentor->id)->pluck('mentee_id')->toArray();
        $menteeIds         = array_values(array_unique(array_merge($assignedMenteeIds, $bookedMenteeIds)));

        $query = MilestoneSubmission::with(['milestone', 'user:id,name,email,profile_picture_url', 'documentVault']);

        if ($mentor->role !== 'admin') {
            $query->whereIn('user_id', $menteeIds);
        }

        // Filter status peninjauan (pending, approved, revision_requested)
        if ($request->has('review_status') && in_array($request->review_status, ['pending', 'approved', 'revision_requested'])) {
            $query->where('review_status', $request->review_status);
        }

        $submissions = $query->orderBy('updated_at', 'desc')->get()->map(function ($sub) {
            return [
                'submission_id'     => $sub->id,
                'milestone_id'      => $sub->user_milestone_id,
                'task_name'         => $sub->milestone->task_name ?? null,
                'task_description'  => $sub->milestone->description ?? null,
                'mentee' => [
                    'id'    => $sub->user->id ?? null,
                    'name'  => $sub->user->name ?? null,
                    'email' => $sub->user->email ?? null,
                ],
                'submission_type'   => $sub->submission_type,
                'text_response'     => $sub->text_response,
                'file_name'         => $sub->file_name,
                'file_url'          => $sub->file_path ? asset(Storage::url($sub->file_path)) : null,
                'document_vault'    => $sub->documentVault,
                'review_status'     => $sub->review_status,
                'mentor_feedback'   => $sub->mentor_feedback,
                'rating'            => $sub->rating,
                'submitted_at'      => $sub->created_at->format('Y-m-d H:i:s'),
                'updated_at'        => $sub->updated_at->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar pengiriman tugas mentee berhasil dimuat.',
            'data' => $submissions
        ], 200);
    }

    /**
     * MENILAI & MEMVERIFIKASI PENGIRIMAN TUGAS MENTEE (APPROVE ATAU REVISI)
     * Jika disetujui (approved): Mentee dapat XP reward, task selesai, feedback tersimpan.
     * Jika revisi (revision_requested): Mentee dapat catatan revisi & task kembali ke status in_progress.
     */
    public function reviewSubmission(Request $request, $submissionId)
    {
        $mentor = Auth::user();
        if (!in_array($mentor->role, ['mentor', 'admin'])) {
            return response()->json(['status' => 'error', 'message' => 'Akses ditolak.'], 403);
        }

        $request->validate([
            'status'   => 'required|in:approved,revision_requested',
            'feedback' => 'required|string|max:1000',
            'rating'   => 'nullable|integer|min:1|max:5',
        ]);

        DB::beginTransaction();
        try {
            $submission = MilestoneSubmission::with(['milestone', 'user', 'documentVault'])->where('id', $submissionId)->first();

            if (!$submission) {
                return response()->json(['status' => 'error', 'message' => 'Pengiriman tugas tidak ditemukan.'], 404);
            }

            $milestone = $submission->milestone;
            $mentee = $submission->user;

            if ($request->status === 'approved') {
                $xpReward = $milestone->xp_reward > 0 ? $milestone->xp_reward : 50;

                $submission->update([
                    'review_status'   => 'approved',
                    'mentor_feedback' => $request->feedback,
                    'rating'          => $request->rating,
                    'reviewed_by'     => $mentor->id,
                    'reviewed_at'     => now(),
                    'xp_awarded'      => $xpReward,
                ]);

                // Set milestone status completed & is_discovered true
                $milestone->update([
                    'status'        => 'completed',
                    'is_discovered' => true,
                    'completed_at'  => now(),
                ]);

                // Tambahkan XP ke akun mentee
                if ($mentee) {
                    $mentee->increment('xp_points', $xpReward);
                }

                // Update status document vault jika berkas diunggah
                if ($submission->document_vault_id) {
                    DocumentVault::where('id', $submission->document_vault_id)->update(['status' => 'mentor_reviewed']);
                }

                // Cek hierarki subtask -> checkpoint -> valley
                if ($milestone->parent_id) {
                    $checkpoint = UserMilestone::find($milestone->parent_id);
                    if ($checkpoint) {
                        $allTasksDone = UserMilestone::where('parent_id', $checkpoint->id)->where('status', '!=', 'completed')->doesntExist();
                        if ($allTasksDone) {
                            $checkpoint->update(['status' => 'completed', 'is_discovered' => true, 'completed_at' => now()]);

                            if ($checkpoint->parent_id) {
                                $valley = UserMilestone::find($checkpoint->parent_id);
                                if ($valley) {
                                    $allCheckpointsDone = UserMilestone::where('parent_id', $valley->id)->where('status', '!=', 'completed')->doesntExist();
                                    if ($allCheckpointsDone) {
                                        $valley->update(['status' => 'completed', 'is_discovered' => true, 'completed_at' => now()]);
                                    }
                                }
                            }
                        }
                    }
                }

                // Update skor readiness mentee secara berkala & batasi max 100
                if ($mentee) {
                    GamificationService::updateReadinessScore($mentee);
                }

                DB::commit();

                // Kirim email kelulusan/persetujuan tugas ke mentee
                try {
                    if ($mentee && !empty($mentee->email)) {
                        Mail::to($mentee->email)->send(new \App\Mail\SubmissionApprovedMail($mentee, $milestone, $request->feedback ?? '', $mentor->name, $xpReward));
                        Log::info("📧 Email notifikasi tugas DISETUJUI berhasil dikirim ke mentee: {$mentee->email}");
                    }
                } catch (\Exception $mailEx) {
                    Log::warning("Gagal mengirim email persetujuan ke mentee: " . $mailEx->getMessage());
                }

                return response()->json([
                    'status' => 'success',
                    'message' => "Tugas mentee '{$milestone->task_name}' DISETUJUI! Mentee mendapatkan {$xpReward} XP dan feedback telah dikirim.",
                    'data' => [
                        'submission_id'          => $submission->id,
                        'review_status'          => 'approved',
                        'mentor_feedback'        => $submission->mentor_feedback,
                        'xp_awarded'             => $xpReward,
                        'mentee_total_xp'        => (int) ($mentee->xp_points ?? 0),
                        'updated_readiness_score' => (int) ($mentee->fresh()->readiness_score ?? 0),
                    ]
                ], 200);

            } else { // revision_requested
                $submission->update([
                    'review_status'   => 'revision_requested',
                    'mentor_feedback' => $request->feedback,
                    'rating'          => $request->rating,
                    'reviewed_by'     => $mentor->id,
                    'reviewed_at'     => now(),
                ]);

                // Kembalikan status milestone ke in_progress agar mentee dapat memperbaiki
                $milestone->update(['status' => 'in_progress']);

                DB::commit();

                // Kirim notifikasi email permintaan revisi ke mentee
                try {
                    if ($mentee && !empty($mentee->email)) {
                        Mail::to($mentee->email)->send(new \App\Mail\SubmissionRevisionRequestedMail($mentee, $milestone, $request->feedback, $mentor->name));
                        Log::info("📧 Email notifikasi revisi berhasil dikirim ke mentee: {$mentee->email}");
                    }
                } catch (\Exception $mailEx) {
                    Log::warning("Gagal mengirim email notifikasi revisi ke mentee: " . $mailEx->getMessage());
                }

                return response()->json([
                    'status' => 'success',
                    'message' => "Permintaan revisi untuk tugas '{$milestone->task_name}' telah dikirimkan ke mentee beserta catatan feedback.",
                    'data' => [
                        'submission_id'   => $submission->id,
                        'review_status'   => 'revision_requested',
                        'mentor_feedback' => $submission->mentor_feedback,
                    ]
                ], 200);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Gagal menilai tugas mentee: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Selesaikan Action Plan Mentor (Complete Action Plan)
     */
    public function completeActionPlan(Request $request, $id)
    {
        $user = Auth::user();

        // Cari ActionPlan berdasarkan ID
        $actionPlan = ActionPlan::with(['booking', 'mentee'])->find($id);

        if (!$actionPlan) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Action Plan tidak ditemukan.'
            ], 404);
        }

        // Verifikasi hak akses (mentee pemilik atau mentor pembuat)
        if ($user->id !== $actionPlan->mentee_id && $user->id !== $actionPlan->booking->mentor_id) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Akses ditolak. Anda tidak berhak mengubah Action Plan ini.'
            ], 403);
        }

        DB::beginTransaction();
        try {
            // 1. Set is_completed = true pada ActionPlan
            $actionPlan->update([
                'is_completed' => true
            ]);

            // 2. Cari & selesaikan UserMilestone terkait di timeline mentee
            $milestone = UserMilestone::where('user_id', $actionPlan->mentee_id)
                ->where('source', 'mentor')
                ->where('task_name', 'LIKE', '%' . $actionPlan->task_title . '%')
                ->first();

            if ($milestone) {
                $milestone->update([
                    'status'        => 'completed',
                    'is_discovered' => true,
                    'completed_at'  => now(),
                ]);

                // Tambahkan XP ke mentee
                User::where('id', $actionPlan->mentee_id)->increment('xp_points', $milestone->xp_reward ?? 50);

                // Cek hierarki auto-complete parent jika ada
                if ($milestone->parent_id) {
                    $checkpoint = UserMilestone::find($milestone->parent_id);
                    if ($checkpoint) {
                        $allTasksDone = UserMilestone::where('parent_id', $checkpoint->id)->where('status', '!=', 'completed')->doesntExist();
                        if ($allTasksDone) {
                            $checkpoint->update(['status' => 'completed', 'is_discovered' => true, 'completed_at' => now()]);

                            if ($checkpoint->parent_id) {
                                $valley = UserMilestone::find($checkpoint->parent_id);
                                if ($valley) {
                                    $allCheckpointsDone = UserMilestone::where('parent_id', $valley->id)->where('status', '!=', 'completed')->doesntExist();
                                    if ($allCheckpointsDone) {
                                        $valley->update(['status' => 'completed', 'is_discovered' => true, 'completed_at' => now()]);
                                    }
                                }
                            }
                        }
                    }
                }
            }

            // 3. Recalculate readiness score mentee
            $mentee = User::find($actionPlan->mentee_id);
            if ($mentee) {
                \App\Services\GamificationService::updateReadinessScore($mentee);
            }

            DB::commit();

            return response()->json([
                'status'  => 'success',
                'message' => 'Action Plan berhasil ditandai selesai!',
                'data'    => [
                    'action_plan'      => $actionPlan,
                    'linked_milestone' => $milestone,
                    'readiness_score'  => $mentee ? (int) $mentee->readiness_score : null
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Cek seluruh task Action Plan dari mentor di cabang/parent milestone tertentu
     */
    public function getActionPlansByParent(Request $request, $parentMilestoneId)
    {
        // Cari parent milestone
        $parentMilestone = UserMilestone::find($parentMilestoneId);

        if (!$parentMilestone) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Parent milestone tidak ditemukan.'
            ], 404);
        }

        // Ambil seluruh child milestones buatan mentor di bawah parent ini
        $childMentorMilestones = UserMilestone::where('parent_id', $parentMilestoneId)
            ->where('source', 'mentor')
            ->get();

        // Cari ActionPlan records yang terhubung ke mentee
        $actionPlans = ActionPlan::with('booking.mentor:id,name,email,profile_picture_url')
            ->where('mentee_id', $parentMilestone->user_id)
            ->get();

        // Filter / Map ActionPlan ke child milestones
        $result = $childMentorMilestones->map(function ($milestone) use ($actionPlans) {
            // Match action plan berdasarkan task_title
            $cleanTitle = str_replace('[Action Plan Mentor]: ', '', $milestone->task_name);
            $matchedPlan = $actionPlans->first(function ($plan) use ($cleanTitle) {
                return trim($plan->task_title) === trim($cleanTitle);
            });

            return [
                'milestone_id'     => $milestone->id,
                'parent_id'        => $milestone->parent_id,
                'action_plan_id'   => $matchedPlan->id ?? null,
                'booking_id'       => $matchedPlan->booking_id ?? null,
                'mentor'           => $matchedPlan->booking->mentor ?? null,
                'task_title'       => $cleanTitle,
                'task_description' => $milestone->description,
                'mentor_note'      => $matchedPlan->mentor_note ?? null,
                'deadline'         => $milestone->target_date,
                'status'           => $milestone->status,
                'is_completed'     => $milestone->status === 'completed' || ($matchedPlan && $matchedPlan->is_completed),
                'is_discovered'    => (bool) $milestone->is_discovered,
                'xp_reward'        => $milestone->xp_reward,
                'created_at'       => $milestone->created_at->toIso8601String(),
            ];
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Daftar Action Plan mentor untuk cabang milestone ini berhasil diambil.',
            'data'    => [
                'parent_milestone' => [
                    'id'            => $parentMilestone->id,
                    'task_name'     => $parentMilestone->task_name,
                    'description'   => $parentMilestone->description,
                    'status'        => $parentMilestone->status,
                    'is_discovered' => (bool) $parentMilestone->is_discovered,
                ],
                'total_tasks'     => $result->count(),
                'completed_tasks' => $result->where('is_completed', true)->count(),
                'action_plans'    => $result->values()
            ]
        ], 200);
    }
}