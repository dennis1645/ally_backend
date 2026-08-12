<?php

namespace App\Http\Controllers;

use App\Models\Scholarship;
use App\Models\UserMilestone;
use App\Models\MilestoneSubmission;
use App\Models\DocumentVault;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Services\AITimelineService;
use App\Services\GamificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Midtrans\Config;
use Midtrans\Snap;

class MilestoneController extends Controller
{
    protected $aiService;

    public function __construct(AITimelineService $aiService)
    {
        $this->aiService = $aiService;

        Config::$serverKey = env('MIDTRANS_SERVER_KEY', 'SB-Mid-server-zPZzeYWIuU8ckXT3gwASJ31c');
        Config::$isProduction = env('MIDTRANS_IS_PRODUCTION', false);
        Config::$isSanitized = true;
        Config::$is3ds = true;
    }

    private function checkPreviousMilestonesCompleted($user, $milestone)
    {
        return UserMilestone::where('user_id', $user->id)
            ->whereNull('parent_id') 
            ->where('step_order', '<', $milestone->step_order)
            ->where('is_mandatory', true) 
            ->where(function ($query) use ($milestone) {
                $query->where('scholarship_id', $milestone->scholarship_id)
                      ->orWhereNull('scholarship_id');
            })
            ->where('status', '!=', 'completed')
            ->orderBy('step_order', 'asc')
            ->first();
    }

    public function getTimeline(Request $request)
    {
        $request->validate([
            'scholarship_id' => 'nullable|exists:scholarships,id',
        ]);

        $user = Auth::user();
        $scholarshipId = $request->scholarship_id; 

        // ====================================================================
        // PERBAIKAN: Mengubah target_deadline menjadi target_date di orderBy
        // ====================================================================
        $query = UserMilestone::where('user_id', $user->id)
            ->whereNull('parent_id')
            ->with(['subTasks' => function ($q) {
                $q->orderBy('target_date', 'asc') // <-- DIGANTI DI SINI
                  ->with('subTasks'); 
            }])
            ->orderBy('step_order', 'asc')
            ->orderBy('target_date', 'asc'); // <-- DIGANTI DI SINI

        if ($scholarshipId) {
            $query->where(function ($q) use ($scholarshipId) {
                $q->where('scholarship_id', $scholarshipId)
                  ->orWhereNull('scholarship_id');
            });
        } else {
            $query->whereNull('scholarship_id');
        }

        $milestones = $query->get();

        if ($milestones->isEmpty()) {
            return response()->json([
                'status' => 'success', 
                'message' => 'Belum ada timeline yang ditemukan.',
                'data' => [
                    'is_user_premium'            => (bool) $user->is_premium,
                    'readiness_score'            => (int) ($user->readiness_score ?? 0),
                    'primary_scholarship_target' => $user->primary_scholarship_target,
                    'milestones'                 => []
                ]
            ], 200);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Berhasil mengambil daftar timeline milestone.',
            'data' => [
                'is_user_premium'            => (bool) $user->is_premium,
                'readiness_score'            => (int) ($user->readiness_score ?? 0),
                'primary_scholarship_target' => $user->primary_scholarship_target,
                'milestones'                 => $milestones
            ]
        ], 200);
    }

    public function generateTimeline(Request $request)
    {
        $request->validate([
            'scholarship_id' => 'required|exists:scholarships,id',
        ]);

        $user = Auth::user();
        
        $scholarship = Scholarship::with('universities')->findOrFail($request->scholarship_id);
        $deadline = Carbon::parse($scholarship->deadline_date);
        
        if (Carbon::now()->greaterThanOrEqualTo($deadline)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Deadline beasiswa sudah terlewat, tidak bisa membuat timeline.'
            ], 400);
        }

        $assessment = DB::table('diagnostic_assessments')
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->first();

        $answersData = ($assessment && isset($assessment->raw_answers))
            ? json_decode($assessment->raw_answers, true)
            : (object) []; 

        $readinessScore = $user->readiness_score ?? 0;
        
        $payload = [
            'studentId' => "student-readiness-{$readinessScore}-{$user->id}",
            'answers'   => $answersData,
            'uploads'   => (object) [], 
            'scholarship' => [
                'id'   => "sch-{$scholarship->id}",
                'name' => $scholarship->name,
                'deadline' => [
                    'application_period' => $deadline->format('Y-m-d')
                ]
            ]
        ];

        $aiResponse = $this->aiService->generate($payload);

        $journeyData = $aiResponse['body']['journey'] ?? $aiResponse['journey'] ?? null;

        if (!$journeyData || !isset($journeyData['valleys'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memproses timeline dari AI. Pastikan AI berjalan dan merespons JSON yang valid.'
            ], 500);
        }

        $firstUniversityId = $scholarship->universities->first()->id ?? null;

        $baseStepOrder = UserMilestone::where('user_id', $user->id)
            ->whereNull('scholarship_id')
            ->whereNull('parent_id')
            ->max('step_order') ?? 3;

        DB::beginTransaction();
        try {
            // 1. Cari semua ID milestone beasiswa lama user untuk membersihkan pengiriman jawaban
            $oldMilestoneIds = UserMilestone::where('user_id', $user->id)
                ->whereNotNull('scholarship_id')
                ->pluck('id');

            if ($oldMilestoneIds->isNotEmpty()) {
                MilestoneSubmission::whereIn('user_milestone_id', $oldMilestoneIds)->delete();
                UserMilestone::whereIn('id', $oldMilestoneIds)->delete();
            }

            // 2. Sync target beasiswa di profil user & pivot user_scholarships
            $user->update([
                'primary_scholarship_target' => $scholarship->name
            ]);

            DB::table('user_scholarships')->updateOrInsert(
                ['user_id' => $user->id],
                [
                    'scholarship_id' => $scholarship->id,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]
            );

            $valleys = $journeyData['valleys'];
            
            // ====================================================================
            // PERBAIKAN: Menarik Global Start Date & Target Date dari Root Timeline
            // ====================================================================
            $globalStartDate = isset($journeyData['timeline']['start_date']) 
                ? Carbon::parse($journeyData['timeline']['start_date'])->format('Y-m-d') 
                : now()->format('Y-m-d');
                
            $globalTargetDate = isset($journeyData['timeline']['target_date']) 
                ? Carbon::parse($journeyData['timeline']['target_date'])->format('Y-m-d') 
                : $deadline->format('Y-m-d');

            foreach ($valleys as $vIndex => $valley) {
                $step_order = $baseStepOrder + $vIndex + 1; 

                $valleyName = $valley['name'] ?? $valley['title'] ?? 'Fase ' . ($vIndex + 1);
                $valleyObjective = $valley['objective'] ?? $valley['description'] ?? 'Fase perjalanan: ' . $valleyName;

                $valleyModel = UserMilestone::create([
                    'user_id'         => $user->id,
                    'parent_id'       => null, 
                    'scholarship_id'  => $scholarship->id,
                    'university_id'   => $firstUniversityId,
                    'task_name'       => $valleyName,
                    'description'     => $valleyObjective,
                    'step_order'      => $step_order,
                    'is_premium'      => true, 
                    'start_date'      => $globalStartDate,  // <-- DATA BARU
                    'target_date'     => $globalTargetDate, // <-- DATA BARU
                    'status'          => 'pending',
                    'source'          => 'system',
                    'is_mandatory'    => true,
                    'is_discovered'   => false, 
                    'xp_reward'       => 0, 
                ]);

                foreach ($valley['checkpoints'] ?? [] as $checkpoint) {
                    // Checkpoint jarang punya timeline, kita fallback ke tanggal global
                    $cpStartDate = isset($checkpoint['timeline']['start_date']) ? Carbon::parse($checkpoint['timeline']['start_date'])->format('Y-m-d') : $globalStartDate;
                    $cpTargetDate = isset($checkpoint['timeline']['target_date']) ? Carbon::parse($checkpoint['timeline']['target_date'])->format('Y-m-d') : $globalTargetDate;
                    
                    $cpName = $checkpoint['title'] ?? $checkpoint['name'] ?? 'Tahapan';

                    $checkpointModel = UserMilestone::create([
                        'user_id'         => $user->id,
                        'parent_id'       => $valleyModel->id, 
                        'scholarship_id'  => $scholarship->id,
                        'university_id'   => $firstUniversityId,
                        'task_name'       => $cpName,
                        'description'     => $checkpoint['description'] ?? null,
                        'step_order'      => $step_order,
                        'is_premium'      => true, 
                        'start_date'      => $cpStartDate,  // <-- DATA BARU
                        'target_date'     => $cpTargetDate, // <-- DATA BARU
                        'status'          => 'pending',
                        'source'          => 'system',
                        'is_mandatory'    => true,
                        'is_discovered'   => false, 
                        'xp_reward'       => $checkpoint['xp_reward'] ?? 50,
                    ]);

                    foreach ($checkpoint['tasks'] ?? [] as $task) {
                        $taskName = $task['title'] ?? $task['name'] ?? 'Tugas';
                        
                        // Menarik Tanggal Spesifik dari setiap Task!
                        $tStartDate = isset($task['timeline']['start_date']) 
                            ? Carbon::parse($task['timeline']['start_date'])->format('Y-m-d') 
                            : $cpStartDate;

                        $tTargetDate = isset($task['timeline']['target_date']) 
                            ? Carbon::parse($task['timeline']['target_date'])->format('Y-m-d') 
                            : $cpTargetDate;
                        
                        UserMilestone::create([
                            'user_id'         => $user->id,
                            'parent_id'       => $checkpointModel->id, 
                            'scholarship_id'  => $scholarship->id,
                            'university_id'   => $firstUniversityId,
                            'task_name'       => $taskName,
                            'description'     => $task['description'] ?? null,
                            'step_order'      => $step_order,
                            'is_premium'      => true, 
                            'start_date'      => $tStartDate,  // <-- DATA BARU
                            'target_date'     => $tTargetDate, // <-- DATA BARU
                            'status'          => 'pending',
                            'source'          => 'system',
                            'is_mandatory'    => $task['is_mandatory'] ?? true,
                            'is_discovered'   => false, 
                            'xp_reward'       => 20, 
                        ]);
                    }
                }
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Timeline AI terstruktur berhasil dibuat dan disimpan ke database!',
                'data' => $journeyData 
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat menyimpan hierarki ke database.', 
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function createPremiumUnlockTransaction($user)
    {
        $orderId = 'PREMIUM-UNLOCK-' . time() . '-' . rand(100, 999);
        $grossAmount = 150000;

        $transaction = Transaction::create([
            'user_id' => $user->id,
            'midtrans_order_id' => $orderId,
            'transaction_type' => 'premium_unlock',
            'gross_amount' => $grossAmount,
            'payment_status' => 'pending',
        ]);

        TransactionDetail::create([
            'transaction_id' => $transaction->id,
            'shop_item_id' => null,
            'price' => $grossAmount,
        ]);

        $params = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => (int) $grossAmount,
            ],
            'customer_details' => [
                'first_name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone_number ?? '08123456789',
            ],
            'item_details' => [
                [
                    'id' => 'PREMIUM-UPGRADE',
                    'price' => (int) $grossAmount,
                    'quantity' => 1,
                    'name' => 'Upgrade Akun Premium & Unlock Milestone Eksklusif',
                ]
            ]
        ];

        $paymentUrl = Snap::createTransaction($params)->redirect_url;
        $transaction->update(['payment_url' => $paymentUrl]);

        return [
            'order_id' => $orderId,
            'gross_amount' => $grossAmount,
            'payment_url' => $paymentUrl
        ];
    }

    public function startTask(Request $request, $id)
    {
        $user = Auth::user();
        $milestone = UserMilestone::where('id', $id)->where('user_id', $user->id)->first();

        if (!$milestone) return response()->json(['status' => 'error', 'message' => 'Not found.'], 404);
        if ($milestone->status === 'completed') return response()->json(['status' => 'error', 'message' => 'Already completed.'], 400);
        if ($milestone->status === 'in_progress') return response()->json(['status' => 'error', 'message' => 'Already in_progress.'], 400);

        $previousIncomplete = $this->checkPreviousMilestonesCompleted($user, $milestone);
        if ($previousIncomplete) {
            return response()->json([
                'status' => 'error',
                'message' => "Selesaikan fase sebelumnya ('{$previousIncomplete->task_name}') terlebih dahulu."
            ], 400);
        }

        if ($milestone->is_premium && !$user->is_premium) {
            try {
                $paymentInfo = $this->createPremiumUnlockTransaction($user);
                return response()->json([
                    'status' => 'payment_required',
                    'message' => 'Task terkunci (Premium).',
                    'data' => $paymentInfo
                ], 402);
            } catch (\Exception $e) {
                return response()->json(['status' => 'error', 'message' => 'Gagal transaksi.', 'error' => $e->getMessage()], 500);
            }
        }

        $milestone->status = 'in_progress';
        $milestone->save();

        return response()->json(['status' => 'success', 'data' => $milestone], 200);
    }

    public function completeTask(Request $request, $id)
    {
        $user = Auth::user();
        $milestone = UserMilestone::where('id', $id)->where('user_id', $user->id)->first();

        if (!$milestone) return response()->json(['status' => 'error', 'message' => 'Not found.'], 404);
        if ($milestone->status === 'completed') return response()->json(['status' => 'error', 'message' => 'Already completed.'], 400);

        $previousIncomplete = $this->checkPreviousMilestonesCompleted($user, $milestone);
        if ($previousIncomplete) {
            return response()->json([
                'status' => 'error',
                'message' => "Selesaikan fase sebelumnya ('{$previousIncomplete->task_name}') terlebih dahulu."
            ], 400);
        }

        if ($milestone->is_premium && !$user->is_premium) {
            try {
                $paymentInfo = $this->createPremiumUnlockTransaction($user);
                return response()->json([
                    'status' => 'payment_required',
                    'message' => 'Task terkunci (Premium).',
                    'data' => $paymentInfo
                ], 402);
            } catch (\Exception $e) {
                return response()->json(['status' => 'error', 'message' => 'Gagal transaksi.', 'error' => $e->getMessage()], 500);
            }
        }

        DB::beginTransaction();
        try {
            $milestone->status = 'completed';
            $milestone->is_discovered = true;
            $milestone->completed_at = now();
            $milestone->save();

            // Kaskade status discovered ke subtasks (jika ada)
            $this->cascadeDiscoveredStatus($milestone->id, true);

            $user->xp_points += $milestone->xp_reward;
            $user->save();

            if ($milestone->parent_id) {
                $checkpoint = UserMilestone::find($milestone->parent_id);
                $allTasksDone = UserMilestone::where('parent_id', $checkpoint->id)->where('status', '!=', 'completed')->doesntExist();
                
                if ($allTasksDone) {
                    $checkpoint->status = 'completed';
                    $checkpoint->is_discovered = true;
                    $checkpoint->completed_at = now();
                    $checkpoint->save();
                    $this->cascadeDiscoveredStatus($checkpoint->id, true);

                    if ($checkpoint->parent_id) {
                        $valley = UserMilestone::find($checkpoint->parent_id);
                        $allCheckpointsDone = UserMilestone::where('parent_id', $valley->id)->where('status', '!=', 'completed')->doesntExist();
                        
                        if ($allCheckpointsDone) {
                            $valley->status = 'completed';
                            $valley->is_discovered = true;
                            $valley->completed_at = now();
                            $valley->save();
                            $this->cascadeDiscoveredStatus($valley->id, true);
                        }
                    }
                }
            }

            // Update skor readiness user secara berkala & batasi max 100
            $updatedReadinessScore = GamificationService::updateReadinessScore($user);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Task berhasil diselesaikan!',
                'data' => [
                    'milestone_id'           => $milestone->id,
                    'status'                 => $milestone->status,
                    'earned_xp'              => $milestone->xp_reward,
                    'total_user_xp'          => $user->xp_points,
                    'updated_readiness_score' => $updatedReadinessScore
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Terjadi kesalahan sistem.', 'error' => $e->getMessage()], 500);
        }
    }

    public function markAsDiscovered(Request $request, $id)
    {
        $user = Auth::user();
        
        $milestone = UserMilestone::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$milestone) {
            return response()->json([
                'status' => 'error', 
                'message' => 'Milestone tidak ditemukan.'
            ], 404);
        }

        DB::beginTransaction();
        try {
            $milestone->is_discovered = true;
            $milestone->save();

            // Kaskade is_discovered = true ke semua anak (checkpoint & task)
            $this->cascadeDiscoveredStatus($milestone->id, true);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Milestone dan seluruh sub-task berhasil ditandai sebagai discovered.',
                'data' => $milestone->fresh(['subTasks'])
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Gagal mengubah status discovered: ' . $e->getMessage()], 500);
        }
    }

    private function cascadeDiscoveredStatus($parentId, $status)
    {
        $children = UserMilestone::where('parent_id', $parentId)->get();
        foreach ($children as $child) {
            $child->is_discovered = $status;
            $child->save();
            $this->cascadeDiscoveredStatus($child->id, $status);
        }
    }

    /**
     * SUBMIT JAWABAN / DOKUMEN FASE MILESTONE TASK (MENTEE)
     * Mentee menjawab task menggunakan teks, unggah berkas (otomatis tersimpan ke Document Vault), atau keduanya.
     */
    public function submitTask(Request $request, $id)
    {
        $user = Auth::user();

        $milestone = UserMilestone::where('id', $id)->where('user_id', $user->id)->first();

        if (!$milestone) {
            return response()->json([
                'status' => 'error',
                'message' => 'Task milestone tidak ditemukan.'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'text_response' => 'nullable|string',
            'file'          => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png,zip,rar|max:10240',
            'file_type'     => 'nullable|in:cv,transcript,certificate,essay,loa,other',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
                'data' => $validator->errors()
            ], 422);
        }

        if (!$request->filled('text_response') && !$request->hasFile('file')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Mohon isi jawaban teks atau unggah berkas dokumen.'
            ], 422);
        }

        DB::beginTransaction();
        try {
            $documentVaultId = null;
            $filePath = null;
            $fileName = null;

            // Jika mentee mengunggah berkas dokumen -> Otomatis masuk ke Document Vault!
            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $fileName = $file->getClientOriginalName();
                $filePath = $file->store('vault_documents', 'public');

                $vault = DocumentVault::create([
                    'user_id'        => $user->id,
                    'scholarship_id' => $milestone->scholarship_id,
                    'university_id'  => $milestone->university_id,
                    'file_name'      => $fileName,
                    'file_path'      => $filePath,
                    'mime_type'      => $file->getClientMimeType(),
                    'file_size'      => $file->getSize(),
                    'file_type'      => $request->file_type ?? 'essay',
                    'status'         => 'uploaded',
                    'is_encrypted'   => true,
                ]);

                $documentVaultId = $vault->id;
            }

            $submissionType = ($request->hasFile('file') && $request->filled('text_response'))
                ? 'both'
                : ($request->hasFile('file') ? 'document' : 'text');

            // Simpan / update pengiriman jawaban task
            $submission = MilestoneSubmission::updateOrCreate(
                [
                    'user_milestone_id' => $milestone->id,
                    'user_id'           => $user->id,
                ],
                [
                    'document_vault_id' => $documentVaultId,
                    'submission_type'   => $submissionType,
                    'text_response'     => $request->text_response,
                    'file_path'         => $filePath,
                    'file_name'         => $fileName,
                    'review_status'     => 'pending',
                    'mentor_feedback'   => null,
                ]
            );

            // Ubah status milestone menjadi in_progress (menunggu peninjauan mentor)
            $milestone->update(['status' => 'in_progress']);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Jawaban task berhasil dikirim! Dokumen otomatis tersimpan di Document Vault dan menunggu peninjauan mentor.',
                'data' => [
                    'submission_id'     => $submission->id,
                    'milestone_id'      => $milestone->id,
                    'submission_type'   => $submission->submission_type,
                    'text_response'     => $submission->text_response,
                    'file_name'         => $submission->file_name,
                    'file_url'          => $submission->file_path ? asset(Storage::url($submission->file_path)) : null,
                    'document_vault_id' => $submission->document_vault_id,
                    'review_status'     => $submission->review_status,
                    'submitted_at'      => $submission->updated_at->format('Y-m-d H:i:s')
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengunggah jawaban task: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET DETAIL JAWABAN / SUBMISSION MENTEE UNTUK TASK MILESTONE
     */
    public function getTaskSubmission(Request $request, $id)
    {
        $user = Auth::user();

        $submission = MilestoneSubmission::with(['documentVault', 'reviewer:id,name,email'])
            ->where('user_milestone_id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$submission) {
            return response()->json([
                'status' => 'success',
                'message' => 'Belum ada pengiriman jawaban untuk task ini.',
                'data' => null
            ], 200);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Detail pengiriman jawaban task berhasil dimuat.',
            'data' => [
                'submission_id'     => $submission->id,
                'user_milestone_id' => $submission->user_milestone_id,
                'submission_type'   => $submission->submission_type,
                'text_response'     => $submission->text_response,
                'file_name'         => $submission->file_name,
                'file_url'          => $submission->file_path ? asset(Storage::url($submission->file_path)) : null,
                'document_vault'    => $submission->documentVault,
                'review_status'     => $submission->review_status,
                'mentor_feedback'   => $submission->mentor_feedback,
                'rating'            => $submission->rating,
                'xp_awarded'        => $submission->xp_awarded,
                'reviewed_by'       => $submission->reviewer->name ?? null,
                'reviewed_at'       => $submission->reviewed_at ? $submission->reviewed_at->format('Y-m-d H:i:s') : null,
            ]
        ], 200);
    }
}