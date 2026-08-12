<?php

namespace App\Http\Controllers;

use App\Models\Scholarship;
use App\Models\UserMilestone;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Services\AITimelineService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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
                    'is_user_premium' => (bool) $user->is_premium,
                    'milestones' => []
                ]
            ], 200);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Berhasil mengambil daftar timeline milestone.',
            'data' => [
                'is_user_premium' => (bool) $user->is_premium,
                'milestones' => $milestones
            ]
        ], 200);
    }

    public function generateTimeline(Request $request)
    {
        $request->validate([
            'scholarship_id' => 'required|exists:scholarships,id',
        ]);

        $user = Auth::user();
        
        $existingMilestone = UserMilestone::where('user_id', $user->id)
            ->whereNotNull('scholarship_id')
            ->whereNull('parent_id')
            ->latest('created_at')
            ->first();

        if ($existingMilestone) {
            $activeScholarship = Scholarship::find($existingMilestone->scholarship_id);
            
            if ($activeScholarship) {
                $activeDeadline = Carbon::parse($activeScholarship->deadline_date);
                
                if (Carbon::now()->lessThan($activeDeadline)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Anda hanya bisa melakukan generate 1 kali. Anda sudah memiliki timeline aktif untuk beasiswa "' . $activeScholarship->name . '". Silakan tunggu sampai masa pendaftaran beasiswa tersebut habis pada ' . $activeDeadline->format('d M Y') . ' sebelum menargetkan beasiswa lain.',
                    ], 403); 
                }
            }
        }

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
            UserMilestone::where('user_id', $user->id)
                ->whereNotNull('scholarship_id')
                ->delete();

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
            $milestone->completed_at = now();
            $milestone->save();

            $user->xp_points += $milestone->xp_reward;
            $user->save();

            if ($milestone->parent_id) {
                $checkpoint = UserMilestone::find($milestone->parent_id);
                $allTasksDone = UserMilestone::where('parent_id', $checkpoint->id)->where('status', '!=', 'completed')->doesntExist();
                
                if ($allTasksDone) {
                    $checkpoint->status = 'completed';
                    $checkpoint->completed_at = now();
                    $checkpoint->save();

                    if ($checkpoint->parent_id) {
                        $valley = UserMilestone::find($checkpoint->parent_id);
                        $allCheckpointsDone = UserMilestone::where('parent_id', $valley->id)->where('status', '!=', 'completed')->doesntExist();
                        
                        if ($allCheckpointsDone) {
                            $valley->status = 'completed';
                            $valley->completed_at = now();
                            $valley->save();
                        }
                    }
                }
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Task berhasil diselesaikan!',
                'data' => [
                    'milestone_id' => $milestone->id,
                    'status' => $milestone->status,
                    'earned_xp' => $milestone->xp_reward,
                    'total_user_xp' => $user->xp_points
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

        if ($milestone->is_discovered) {
            return response()->json([
                'status' => 'success', 
                'message' => 'Milestone sudah ditandai discovered sebelumnya.', 
                'data' => $milestone
            ], 200);
        }

        $milestone->is_discovered = true;
        $milestone->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Milestone berhasil ditandai sebagai discovered.',
            'data' => $milestone
        ], 200);
    }
}