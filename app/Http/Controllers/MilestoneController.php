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

        // Konfigurasi Midtrans
        Config::$serverKey = env('MIDTRANS_SERVER_KEY', 'SB-Mid-server-zPZzeYWIuU8ckXT3gwASJ31c');
        Config::$isProduction = env('MIDTRANS_IS_PRODUCTION', false);
        Config::$isSanitized = true;
        Config::$is3ds = true;
    }

    /**
     * GET: Mengambil daftar semua milestone / task timeline milik user (Mendukung Task Branching)
     */
    public function getTimeline(Request $request)
    {
        $request->validate([
            'scholarship_id' => 'required|exists:scholarships,id',
        ]);

        $user = Auth::user();
        $scholarshipId = $request->scholarship_id;

        // MENGAMBIL TUGAS UTAMA (ROOT) BESERTA CABANGNYA (SUB-TASK)
        $milestones = UserMilestone::where('user_id', $user->id)
            ->where('scholarship_id', $scholarshipId)
            ->whereNull('parent_id') // WAJIB: Hanya ambil tugas utama (bukan cabang)
            ->with(['subTasks' => function($query) {
                // Urutkan tugas cabang (dari mentor) berdasarkan deadline terdekat
                $query->orderBy('target_deadline', 'asc');
            }])
            ->orderBy('step_order', 'asc')
            ->orderBy('target_deadline', 'asc')
            ->get();

        if ($milestones->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Belum ada timeline yang digenerate untuk beasiswa ini.'
            ], 404);
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
            // Hapus timeline lama untuk beasiswa ini (opsional tergantung flow aplikasi kamu)
            UserMilestone::where('user_id', $user->id)
                ->where('scholarship_id', $scholarship->id)
                ->delete();

            $milestonesToInsert = [];
            foreach ($aiResponse['milestones'] as $index => $task) {
                $step_order = $index + 1;
                
                $milestonesToInsert[] = [
                    'user_id'         => $user->id,
                    'parent_id'       => null, // Secara default AI meng-generate tugas utama
                    'scholarship_id'  => $scholarship->id,
                    'university_id'   => $firstUniversityId,
                    'task_name'       => $task['task_name'],
                    'description'     => $task['description'] ?? null,
                    'step_order'      => $step_order,
                    'is_premium'      => $task['is_premium'] ?? ($step_order > 3 ? true : false), 
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
     * Helper untuk membuat transaksi Midtrans Premium Unlock
     */
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

        $transactionDetail = TransactionDetail::create([
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

        $transaction->update([
            'payment_url' => $paymentUrl
        ]);

        return [
            'order_id' => $orderId,
            'gross_amount' => $grossAmount,
            'payment_url' => $paymentUrl
        ];
    }

    /**
     * User mengubah status task menjadi in_progress
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

        if ($milestone->is_premium && !$user->is_premium) {
            try {
                $paymentInfo = $this->createPremiumUnlockTransaction($user);
                return response()->json([
                    'status' => 'payment_required',
                    'message' => 'Task ini terkunci khusus member premium. Silakan selesaikan pembayaran untuk mengaktifkannya.',
                    'data' => $paymentInfo
                ], 402);
            } catch (\Exception $e) {
                Log::error('Midtrans Premium Unlock Error: ' . $e->getMessage());
                return response()->json([
                    'status' => 'error',
                    'message' => 'Gagal membuat transaksi pembayaran premium.',
                    'error' => $e->getMessage()
                ], 500);
            }
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
     * User menandai task milestone selesai
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
            try {
                $paymentInfo = $this->createPremiumUnlockTransaction($user);
                return response()->json([
                    'status' => 'payment_required',
                    'message' => 'Task ini terkunci khusus member premium. Silakan selesaikan pembayaran untuk mengaktifkannya.',
                    'data' => $paymentInfo
                ], 402);
            } catch (\Exception $e) {
                Log::error('Midtrans Premium Unlock Error: ' . $e->getMessage());
                return response()->json([
                    'status' => 'error',
                    'message' => 'Gagal membuat transaksi pembayaran premium.',
                    'error' => $e->getMessage()
                ], 500);
            }
        }

        if ($milestone->status === 'completed') {
            return response()->json([
                'status' => 'error',
                'message' => 'Task ini sudah diselesaikan sebelumnya.'
            ], 400);
        }

        DB::beginTransaction();
        try {
            $milestone->status = 'completed';
            $milestone->completed_at = now();
            $milestone->save();

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