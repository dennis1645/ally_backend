<?php

namespace App\Http\Controllers;

use App\Models\Scholarship;
use App\Models\UserMilestone;
use App\Services\AITimelineService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class MilestoneController extends Controller
{
    protected $aiService;

    public function __construct(AITimelineService $aiService)
    {
        $this->aiService = $aiService;
    }

    public function generateTimeline(Request $request)
    {
        $request->validate([
            'scholarship_id' => 'required|exists:scholarships,id',
        ]);

        $user = Auth::user();
        
        $scholarship = Scholarship::with('universities')->findOrFail($request->scholarship_id);

        $deadline = Carbon::parse($scholarship->deadline_date);
        $sisa_hari = Carbon::now()->diffInDays($deadline, false);

        if ($sisa_hari <= 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Deadline beasiswa sudah terlewat, tidak bisa membuat timeline.'
            ], 400);
        }

        $uploadedDocs = DB::table('document_vaults')
            ->where('user_id', $user->id)
            ->pluck('file_type')
            ->unique()
            ->toArray();

        $uploadedDocsStr = empty($uploadedDocs) 
            ? 'KOSONG (Belum ada dokumen satupun yang diunggah)' 
            : implode(', ', $uploadedDocs);

        $payload = [
            'user_profile' => 'Skor Readiness: ' . $user->readiness_score,
            'scholarship_name' => $scholarship->name,
            'days_remaining' => $sisa_hari,
            'current_date' => Carbon::now()->format('Y-m-d'),
            'deadline_date' => $deadline->format('Y-m-d'),
            'uploaded_docs' => $uploadedDocsStr,
            'is_crash_course' => $sisa_hari < 30 ? true : false
        ];

        $aiResponse = $this->aiService->generate($payload);

        if (!$aiResponse || !isset($aiResponse['milestones'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memproses timeline dari AI. Pastikan AI berjalan dan merespons JSON yang valid.'
            ], 500);
        }

        $firstUniversityId = $scholarship->universities->first()->id ?? null;

        DB::beginTransaction();
        try {
            UserMilestone::where('user_id', $user->id)
                ->where('scholarship_id', $scholarship->id)
                ->delete();

            $milestonesToInsert = [];
            foreach ($aiResponse['milestones'] as $index => $task) {
                $step_order = $index + 1;
                
                $milestonesToInsert[] = [
                    'user_id'         => $user->id,
                    'scholarship_id'  => $scholarship->id,
                    'university_id'   => $firstUniversityId,
                    'task_name'       => $task['task_name'],
                    'description'     => $task['description'] ?? null,
                    'step_order'      => $step_order,
                    'is_premium'      => $step_order > 3 ? true : false, 
                    'target_deadline' => Carbon::parse($task['target_deadline'])->format('Y-m-d'),
                    'status'          => 'pending',
                    'source'          => 'system',
                    'is_mandatory'    => $task['is_mandatory'] ?? true,
                    'xp_reward'       => $task['xp_reward'] ?? 50,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ];
            }

            UserMilestone::insert($milestonesToInsert);
            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Timeline AI berhasil dibuat dan disimpan ke database!',
                'data' => $milestonesToInsert
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat menyimpan ke database.', 
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * FITUR BARU: User mengubah status task menjadi in_progress (Tanpa menambah XP)
     */
    public function startTask(Request $request, $id)
    {
        $user = Auth::user();

        $milestone = UserMilestone::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$milestone) {
            return response()->json([
                'status' => 'error',
                'message' => 'Milestone tidak ditemukan atau bukan milik Anda.'
            ], 404);
        }

        // Validasi Freemium
        if ($milestone->is_premium && !$user->is_premium) {
            return response()->json([
                'status' => 'error',
                'message' => 'Task ini terkunci khusus member premium. Silakan upgrade akun terlebih dahulu.'
            ], 403);
        }

        if ($milestone->status === 'completed') {
            return response()->json([
                'status' => 'error',
                'message' => 'Task ini sudah selesai dan tidak dapat diubah ke in_progress.'
            ], 400);
        }

        if ($milestone->status === 'in_progress') {
            return response()->json([
                'status' => 'error',
                'message' => 'Task ini sudah berstatus in_progress.'
            ], 400);
        }

        $milestone->status = 'in_progress';
        $milestone->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Status task berhasil diubah menjadi in_progress.',
            'data' => [
                'milestone_id' => $milestone->id,
                'task_name' => $milestone->task_name,
                'status' => $milestone->status
            ]
        ], 200);
    }

    /**
     * User menandai task milestone selesai (XP bertambah & Validasi Freemium)
     */
    public function completeTask(Request $request, $id)
    {
        $user = Auth::user();

        $milestone = UserMilestone::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$milestone) {
            return response()->json([
                'status' => 'error',
                'message' => 'Milestone tidak ditemukan atau bukan milik Anda.'
            ], 404);
        }

        if ($milestone->is_premium && !$user->is_premium) {
            return response()->json([
                'status' => 'error',
                'message' => 'Task ini terkunci khusus member premium. Silakan upgrade akun terlebih dahulu untuk menyelesaikannya.'
            ], 403);
        }

        if ($milestone->status === 'completed') {
            return response()->json([
                'status' => 'error',
                'message' => 'Task ini sudah diselesaikan sebelumnya.'
            ], 400);
        }

        DB::beginTransaction();
        try {
            // 1. Ubah status task menjadi completed
            $milestone->status = 'completed';
            $milestone->completed_at = now();
            $milestone->save();

            // 2. Tambahkan XP reward ke akun user (Hanya saat completed)
            $user->xp_points += $milestone->xp_reward;
            $user->save();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Selamat! Task berhasil diselesaikan dan XP bertambah.',
                'data' => [
                    'milestone_id' => $milestone->id,
                    'task_name' => $milestone->task_name,
                    'status' => $milestone->status,
                    'completed_at' => $milestone->completed_at,
                    'earned_xp' => $milestone->xp_reward,
                    'total_user_xp' => $user->xp_points
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan sistem saat memperbarui task.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}