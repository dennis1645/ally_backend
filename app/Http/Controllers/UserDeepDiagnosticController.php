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
use Illuminate\Support\Str;

class UserDeepDiagnosticController extends Controller
{
    protected $aiDiagnosticService;

    // Inject AI Service melalui Constructor
    public function __construct(AIDiagnosticService $aiDiagnosticService)
    {
        $this->aiDiagnosticService = $aiDiagnosticService;
    }

    /**
     * 1. Menampilkan soal khusus untuk Deep Assessment (Assessment 2)
     */
    public function getQuestions(Request $request)
    {
        // PERBAIKAN: Sesuaikan dengan string yang ada di database phpMyAdmin
        $assessmentType = 'assessment_2';

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
     * 2. Submit jawaban Deep Assessment (Hanya untuk User Login)
     */
    public function submitAssessment(Request $request)
    {
        $user = Auth::guard('sanctum')->user(); 

        if (!$user) {
            return response()->json([
                'status' => 'error', 
                'message' => 'Unauthorized. Please login to complete the deep assessment.'
            ], 401);
        }

        $rules = [
            'answers' => 'required|array',
            'answers.*.question_id' => 'required|exists:diagnostic_questions,id',
            'answers.*.option_id' => 'nullable|exists:diagnostic_options,id',
            'answers.*.text_value' => 'nullable|string'
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $keyMap = [
            2  => 'q2_master_motivation',
            3  => 'q3_master_plan_clarity',
            4  => 'q4_academic_experience',
            5  => 'q5_academic_achievement_description',
            6  => 'q6_field_project_experience',
            7  => 'q7_leadership_experience',
            8  => 'q8_leadership_responsibility',
            9  => 'q9_leadership_impact',
            10 => 'q10_career_goal',
            11 => 'q11_career_contribution_area',
            12 => 'q12_target_countries',
            13 => 'q13_scholarship_type',
            14 => 'q14_scholarship_priority',
            15 => 'q15_cv_strength',
            16 => 'q16_essay_readiness',
            17 => 'q17_recommendation_availability',
            18 => 'q18_preparation_time',
            19 => 'q19_application_deadline_target',
            20 => 'q20_support_needed',
        ];

        $userAnswersForAI = [];

        foreach ($request->answers as $answer) {
            $question = DiagnosticQuestion::find($answer['question_id']);
            
            if (!$question) continue;

            $answerText = '';
            if (!empty($answer['option_id'])) {
                $option = DiagnosticOption::find($answer['option_id']);
                $answerText = $option ? $option->option_text : '';
            } elseif (!empty($answer['text_value'])) {
                $answerText = $answer['text_value'];
            }

            $questionKey = $keyMap[$question->order_number] ?? ('q' . $question->order_number . '_' . Str::slug($question->question_text, '_'));

            if (isset($userAnswersForAI[$questionKey])) {
                $userAnswersForAI[$questionKey] .= ', ' . $answerText;
            } else {
                $userAnswersForAI[$questionKey] = $answerText;
            }
        }

        $userDataForAI = [
            'gpa' => $user->gpa,
            'undergraduate_major' => $user->undergraduate_major,
            'target_major' => $user->target_major,
            'scholarship_target' => $user->primary_scholarship_target,
        ];

        // PERBAIKAN: Ubah menjadi assessment_2
        $aiResponse = $this->aiDiagnosticService->generateAnalysis(
            $userAnswersForAI, 
            $userDataForAI, 
            'assessment_2'
        );

        $aiData = $aiResponse['data']['assessment'] ?? $aiResponse['data'] ?? $aiResponse;

        if (!$aiResponse || empty($aiData) || (isset($aiResponse['status']) && $aiResponse['status'] !== 'success')) {
            $aiData = [
                'revised_percentage' => 0,
                'suggestion' => 'Gagal terhubung ke server AI saat menganalisis. Silakan coba lagi.'
            ];
        }

        DB::beginTransaction();
        try {
            $assessmentData = [
                'assessment_type' => 'assessment_2', // PERBAIKAN: Ubah menjadi assessment_2
                'user_id' => $user->id,
                'guest_token' => null,
                'readiness_percentage' => $aiData['revised_percentage'] ?? $user->readiness_score ?? 0,
                'reason' => $aiData['suggestion'] ?? null, 
                'readiness_level' => null,
                'academic_score' => 0,
                'scholarship_goal_score' => 0,
                'leadership_score' => 0,
                'achievements_score' => 0,
                'english_score' => 0,
                'application_score' => 0,
                'strengths_mapping' => [],
                'improvements_mapping' => [],
            ];

            $assessment = DiagnosticAssessment::updateOrCreate(
                [
                    'user_id' => $user->id, 
                    'assessment_type' => 'assessment_2' // PERBAIKAN: Ubah menjadi assessment_2
                ],
                $assessmentData
            );

            $user->update([
                'readiness_score' => $aiData['revised_percentage'] ?? $user->readiness_score
            ]);

            $xpReward = 100; 
            $gamificationData = GamificationService::addXpAndCheckBadges($user, $xpReward);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Deep assessment submitted and analyzed by AI successfully.',
                'data' => [
                    'assessment' => $assessment,
                    'user_profile' => $user->fresh()->makeHidden(['password']),
                    'gamification' => $gamificationData 
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Failed to process deep assessment: ' . $e->getMessage()], 500);
        }
    }

    /**
     * 3. Menampilkan hasil Deep Assessment milik User
     */
    public function getMyAssessment(Request $request)
    {
        $user = Auth::guard('sanctum')->user();
        
        $assessment = DiagnosticAssessment::where('user_id', $user->id)
            ->where('assessment_type', 'assessment_2') // PERBAIKAN: Ubah menjadi assessment_2
            ->first();

        if (!$assessment) {
            return response()->json([
                'status' => 'error',
                'message' => 'Deep assessment result not found.'
            ], 404);
        }

        $formattedData = [
            'id'                 => $assessment->id,
            'user_id'            => $assessment->user_id,
            'assessment_type'    => $assessment->assessment_type,
            'revised_percentage' => $assessment->readiness_percentage, 
            'suggestion'         => $assessment->reason,               
            'created_at'         => $assessment->created_at,
            'updated_at'         => $assessment->updated_at,
        ];

        return response()->json([
            'status' => 'success',
            'message' => 'Deep assessment result retrieved successfully.',
            'data' => $formattedData
        ]);
    }
}