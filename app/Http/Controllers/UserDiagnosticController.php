<?php

namespace App\Http\Controllers;

use App\Models\DiagnosticQuestion;
use App\Models\DiagnosticOption;
use App\Models\DiagnosticAssessment;
use App\Models\User;
use App\Services\GamificationService;
use App\Services\AIDiagnosticService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class UserDiagnosticController extends Controller
{
    protected $aiDiagnosticService;

    // Inject AI Service melalui Constructor
    public function __construct(AIDiagnosticService $aiDiagnosticService)
    {
        $this->aiDiagnosticService = $aiDiagnosticService;
    }

    /**
     * 1. Menampilkan soal kepada User berdasarkan assessment_type dengan sistem Pagination (5 soal per halaman)
     */
    public function getQuestions(Request $request)
    {
        $assessmentType = $request->query('assessment_type', 'initial_diagnostic');

        $questions = DiagnosticQuestion::where('is_active', true)
            ->where('assessment_type', $assessmentType)
            ->with(['options' => function($query) {
                $query->select('id', 'diagnostic_question_id', 'option_text');
            }])
            ->orderBy('order_number', 'asc')
            ->paginate(5);

        return response()->json([
            'status' => 'success',
            'data' => $questions
        ]);
    }

    /**
     * 2. Submit jawaban asesmen (Mendukung Guest / Onboarding & Authenticated / Initial Diagnostic)
     */
    public function submitAssessment(Request $request)
    {
        $user = Auth::guard('sanctum')->user() ?? Auth::user(); 

        $rules = [
            'assessment_type' => 'required|string|in:onboarding,initial_diagnostic',
            'answers' => 'required|array',
            'answers.*.question_id' => 'required|exists:diagnostic_questions,id',
            'answers.*.option_id' => 'required|exists:diagnostic_options,id',
        ];

        if (!$user) {
            $rules['guest_token'] = 'required|string';
        } else {
            $rules['gpa'] = 'nullable|numeric|between:0.00,4.00';
            $rules['undergraduate_major'] = 'nullable|string|max:255';
            $rules['target_major'] = 'nullable|string|max:255';
            $rules['primary_scholarship_target'] = 'nullable|string|max:255';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        // Mapping key persis seperti format yang diminta oleh AI
        $keyMap = [
            1  => 'q1_current_status',
            2  => 'q2_gpa_range',
            3  => 'q3_undergraduate_field',
            4  => 'q4_master_interest',
            5  => 'q5_scholarship_direction',
            6  => 'q6_application_timeline',
            7  => 'q7_leadership_experience',
            8  => 'q8_impact_experience',
            9  => 'q9_recognized_programs',
            10 => 'q10_achievements',
            11 => 'q11_skill_profile',
            12 => 'q12_english_certificate',
            13 => 'q13_storytelling_confidence',
            14 => 'q14_cv_status',
            15 => 'q15_essay_status',
            16 => 'q16_application_knowledge',
            17 => 'q17_previous_application',
            18 => 'q18_rejection_analysis',
            19 => 'q19_biggest_challenge',
            20 => 'q20_cv_upload',
        ];

        $userAnswersForAI = [];

        foreach ($request->answers as $answer) {
            $question = DiagnosticQuestion::find($answer['question_id']);
            $option = DiagnosticOption::find($answer['option_id']);

            if (!$option || $option->diagnostic_question_id !== $question->id) {
                continue;
            }

            $questionKey = $keyMap[$question->order_number] ?? ('q' . $question->order_number);
            $userAnswersForAI[$questionKey] = $option->option_text;
        }

        $userDataForAI = [
            'gpa' => $request->gpa ?? ($user ? $user->gpa : null),
            'undergraduate_major' => $request->undergraduate_major ?? ($user ? $user->undergraduate_major : null),
            'target_major' => $request->target_major ?? ($user ? $user->target_major : null),
            'scholarship_target' => $request->primary_scholarship_target ?? ($user ? $user->primary_scholarship_target : null),
        ];

        $aiResponse = $this->aiDiagnosticService->generateAnalysis(
            $userAnswersForAI, 
            $userDataForAI, 
            $request->assessment_type
        );

        // Extract data asesmen dari struktur nested respons AI secara fleksibel
        $aiData = $aiResponse['data']['assessment'] ?? $aiResponse['data'] ?? $aiResponse ?? [];

        // Ekstraksi nilai readiness percentage secara fleksibel dari berbagai variasi key AI
        $readinessPercentage = (int) (
            $aiData['readiness_percentage'] 
            ?? $aiData['readiness_score'] 
            ?? $aiData['overall_score'] 
            ?? $aiData['score'] 
            ?? $aiData['readiness'] 
            ?? $aiResponse['data']['readiness_percentage'] 
            ?? $aiResponse['data']['readiness_score'] 
            ?? $aiResponse['data']['overall_score'] 
            ?? $aiResponse['readiness_percentage'] 
            ?? $aiResponse['readiness_score'] 
            ?? $aiResponse['overall_score'] 
            ?? 0
        );

        $academicScore        = (int) ($aiData['academic_score'] ?? $aiData['categories']['academic'] ?? 0);
        $scholarshipGoalScore = (int) ($aiData['scholarship_goal_score'] ?? $aiData['categories']['scholarship_goal'] ?? $aiData['categories']['scholarship'] ?? 0);
        $leadershipScore      = (int) ($aiData['leadership_score'] ?? $aiData['categories']['leadership'] ?? 0);
        $achievementsScore    = (int) ($aiData['achievements_score'] ?? $aiData['categories']['achievements'] ?? 0);
        $englishScore         = (int) ($aiData['english_score'] ?? $aiData['categories']['english'] ?? $aiData['categories']['language'] ?? 0);
        $applicationScore     = (int) ($aiData['application_score'] ?? $aiData['categories']['application'] ?? 0);

        // Jika readinessPercentage masih 0 tapi skor kategori ada, hitung rerata dari skor kategori
        if ($readinessPercentage === 0) {
            $categorySum = $academicScore + $scholarshipGoalScore + $leadershipScore + $achievementsScore + $englishScore + $applicationScore;
            if ($categorySum > 0) {
                $readinessPercentage = (int) round($categorySum / 6);
            }
        }

        // Fallback jika API AI gagal total
        if (!$aiResponse || empty($aiData) || (isset($aiResponse['status']) && $aiResponse['status'] !== 'success' && $aiResponse['status'] !== 200)) {
            if ($readinessPercentage === 0) {
                $readinessPercentage = 50; // Default fallback score agar pengguna mendapatkan angka baseline yang valid
            }
            $aiData = array_merge([
                'readiness_percentage' => $readinessPercentage,
                'readiness_level'      => 'Foundation Phase',
                'reason'               => 'Analisis asesmen berhasil diproses.',
                'academic_score'       => $academicScore ?: 50,
                'scholarship_goal_score' => $scholarshipGoalScore ?: 50, 
                'leadership_score'     => $leadershipScore ?: 50,
                'achievements_score'   => $achievementsScore ?: 50, 
                'english_score'        => $englishScore ?: 50, 
                'application_score'    => $applicationScore ?: 50,
                'strengths_mapping'    => ['Memiliki komitmen untuk melanjutkan studi lanjut.'],
                'improvements_mapping' => ['Perlu mempersiapkan sertifikasi bahasa dan dokumen pendukung.'],
            ], $aiData);
        }

        DB::beginTransaction();
        try {
            // Siapkan payload data untuk tabel diagnostic_assessments
            $assessmentData = [
                'assessment_type'        => $request->assessment_type,
                'readiness_percentage'   => $readinessPercentage,
                'readiness_level'        => $aiData['readiness_level'] ?? 'Foundation Phase',
                'reason'                 => $aiData['reason'] ?? null, 
                'academic_score'         => $academicScore,
                'scholarship_goal_score' => $scholarshipGoalScore,
                'leadership_score'       => $leadershipScore,
                'achievements_score'     => $achievementsScore,
                'english_score'          => $englishScore,
                'application_score'      => $applicationScore,
                'strengths_mapping'      => $aiData['strengths_mapping'] ?? $aiData['strengths'] ?? [],
                'improvements_mapping'   => $aiData['improvements_mapping'] ?? $aiData['improvements'] ?? [],
                'raw_answers'            => json_encode($userAnswersForAI),
            ];

            if ($user) {
                $assessmentData['user_id'] = $user->id;
                $assessmentData['guest_token'] = null;

                $assessment = DiagnosticAssessment::updateOrCreate(
                    [
                        'user_id' => $user->id, 
                        'assessment_type' => $request->assessment_type
                    ],
                    $assessmentData
                );
            } else {
                $assessmentData['user_id'] = null;
                $assessmentData['guest_token'] = $request->guest_token;

                $assessment = DiagnosticAssessment::updateOrCreate(
                    [
                        'guest_token' => $request->guest_token, 
                        'assessment_type' => $request->assessment_type
                    ],
                    $assessmentData
                );
            }

            $gamificationData = null;

            if ($user) {
                $userUpdateData = [
                    'readiness_score' => $readinessPercentage
                ];

                if ($request->filled('gpa')) {
                    $userUpdateData['gpa'] = $request->gpa;
                }
                if ($request->filled('undergraduate_major')) {
                    $userUpdateData['undergraduate_major'] = $request->undergraduate_major;
                }
                if ($request->filled('target_major')) {
                    $userUpdateData['target_major'] = $request->target_major;
                }
                if ($request->filled('primary_scholarship_target')) {
                    $userUpdateData['primary_scholarship_target'] = $request->primary_scholarship_target;
                }

                // Update persentase readiness score di profil user
                $user->update($userUpdateData);

                $xpReward = 50; 
                $gamificationData = GamificationService::addXpAndCheckBadges($user, $xpReward);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Assessment submitted and analyzed by AI successfully.',
                'data' => [
                    'assessment' => $assessment,
                    'user_profile' => $user ? $user->fresh()->makeHidden(['password']) : null,
                    'gamification' => $gamificationData 
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Failed to process assessment: ' . $e->getMessage()], 500);
        }
    }

    /**
     * 3. Menampilkan hasil asesmen milik User (Mendukung User Login atau Guest via guest_token)
     */
    public function getMyAssessment(Request $request)
    {
        $user = Auth::guard('sanctum')->user() ?? Auth::user();
        $assessmentType = $request->query('assessment_type', 'initial_diagnostic');
        $guestToken = $request->query('guest_token'); 

        $assessment = null;

        if (!$user && !$guestToken) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized. Please login or provide a valid guest_token.'
            ], 401);
        }

        if ($guestToken) {
            $assessment = DiagnosticAssessment::where('guest_token', $guestToken)
                ->where('assessment_type', $assessmentType)
                ->first();
        }

        if (!$assessment && $user) {
            $assessment = DiagnosticAssessment::where('user_id', $user->id)
                ->where('assessment_type', $assessmentType)
                ->first();
        }

        if (!$assessment) {
            return response()->json([
                'status' => 'error',
                'message' => 'Assessment result not found.'
            ], 404);
        }

        // Pastikan readiness_score user di profil sinkron dengan persentase hasil asesmen
        if ($user && $assessment->readiness_percentage > 0) {
            if ($user->readiness_score != $assessment->readiness_percentage) {
                $user->update(['readiness_score' => $assessment->readiness_percentage]);
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Assessment result retrieved successfully.',
            'data' => $assessment
        ]);
    }
}