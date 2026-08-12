<?php

namespace App\Http\Controllers;

use App\Models\EssayAssessment;
use App\Services\EssayAssessmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class EssayAssessmentController extends Controller
{
    protected $essayService;

    public function __construct(EssayAssessmentService $essayService)
    {
        $this->essayService = $essayService;
    }

    /**
     * POST /api/essay/assess
     * Mengunggah berkas esai (PDF/DOCX/TXT) atau teks esai untuk dinilai oleh AI.
     */
    public function assess(Request $request)
    {
        $user = Auth::guard('sanctum')->user() ?? Auth::user();

        $validator = Validator::make($request->all(), [
            'essay_file'         => 'nullable|file|mimes:pdf,doc,docx,txt|max:10240',
            'essay_text'         => 'nullable|string|min:20',
            'essay_type'         => 'nullable|string|in:storytelling,motivation,leadership,impact,scholarship_alignment,clarity,general',
            'title'              => 'nullable|string|max:255',
            'user_milestone_id'  => 'nullable|exists:user_milestones,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validasi gagal. Harap periksa berkas atau teks esai Anda.',
                'errors'  => $validator->errors()
            ], 422);
        }

        if (!$request->hasFile('essay_file') && empty(trim($request->input('essay_text', '')))) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Harap unggah berkas esai (PDF/DOCX/TXT) atau masukkan teks esai untuk dianalisis.'
            ], 422);
        }

        try {
            $result = $this->essayService->assessEssay($user, $request->all(), $request->file('essay_file'));

            return response()->json([
                'status'  => 'success',
                'message' => 'Analisis asesmen esai berhasil dilakukan oleh AI.',
                'data'    => [
                    'score'                 => $result['assessment']->score,
                    'overall_score'         => $result['assessment']->overall_score,
                    'categories'            => $result['assessment']->categories,
                    'strengths'             => $result['assessment']->strengths,
                    'weaknesses'            => $result['assessment']->weaknesses,
                    'recommendations'       => $result['assessment']->recommendations,
                    'assessment_id'         => $result['assessment']->id,
                    'essay_type'            => $result['assessment']->essay_type,
                    'title'                 => $result['assessment']->title,
                    'file_path'             => $result['assessment']->file_path,
                    'token_cost'            => $result['assessment']->token_cost,
                    'remaining_token'       => $result['remaining_token'],
                    'remaining_daily_quota' => $result['remaining_daily_quota'],
                    'milestone_completed'   => $result['milestone_completed'],
                    'gamification'          => $result['gamification'],
                    'created_at'            => $result['assessment']->created_at,
                ]
            ], 200);

        } catch (\Exception $e) {
            $statusCode = in_array($e->getCode(), [400, 402, 422, 429]) ? $e->getCode() : 500;
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage()
            ], $statusCode);
        }
    }

    /**
     * GET /api/essay/history
     * Menampilkan riwayat hasil asesmen esai milik user yang sedang login.
     */
    public function history(Request $request)
    {
        $user = Auth::guard('sanctum')->user() ?? Auth::user();

        $todayCount = EssayAssessment::where('user_id', $user->id)
            ->whereDate('created_at', now()->toDateString())
            ->count();

        $history = EssayAssessment::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 10));

        return response()->json([
            'status'  => 'success',
            'message' => 'Riwayat asesmen esai berhasil diambil.',
            'meta'    => [
                'token_balance'         => $user->token_balance,
                'today_usage_count'     => $todayCount,
                'remaining_daily_quota' => max(0, 3 - $todayCount),
            ],
            'data'    => $history
        ], 200);
    }

    /**
     * GET /api/essay/{id}
     * Menampilkan rincian detail 1 hasil asesmen esai.
     */
    public function show(Request $request, $id)
    {
        $user = Auth::guard('sanctum')->user() ?? Auth::user();

        $assessment = EssayAssessment::where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (!$assessment) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Data hasil asesmen esai tidak ditemukan.'
            ], 404);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Detail asesmen esai berhasil diambil.',
            'data'    => $assessment
        ], 200);
    }
}
