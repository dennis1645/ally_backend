<?php

namespace App\Http\Controllers;

use App\Models\DiagnosticQuestion;
use App\Models\DiagnosticOption;
use App\Models\DiagnosticAssessment;
use App\Models\UserMilestone; // <-- TAMBAHAN: Import Model UserMilestone
use App\Models\User;
use App\Models\Scholarship;
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
        // Sesuaikan dengan string yang ada di database phpMyAdmin
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

        // Memanggil AI Service untuk Analisis
        $aiResponse = $this->aiDiagnosticService->generateAnalysis(
            $userAnswersForAI, 
            $userDataForAI, 
            'assessment_2'
        );

        // Extract data asesmen dari struktur nested respons Llama AI
        $aiAssessmentData = $aiResponse['data']['assessment'] ?? $aiResponse['data'] ?? $aiResponse;

        if (!$aiResponse || empty($aiAssessmentData) || (isset($aiResponse['status']) && $aiResponse['status'] !== 'success' && $aiResponse['status'] !== 200)) {
            $aiAssessmentData = [
                'revised_percentage' => 0,
                'suggestion'         => 'Gagal terhubung ke server AI saat menganalisis. Silakan coba lagi.'
            ];
        }

        // Extract rekomendasi beasiswa dari AI (kunci: beasiswa_recomendation / scholarship_recommendation)
        $recommendedScholarshipId = null;
        $rawRec = $aiAssessmentData['beasiswa_recomendation']
            ?? $aiResponse['data']['beasiswa_recomendation']
            ?? $aiResponse['beasiswa_recomendation'] 
            ?? $aiAssessmentData['beasiswa_recommendation'] 
            ?? $aiAssessmentData['scholarship_recommendation'] 
            ?? $aiAssessmentData['recommended_scholarship_id'] 
            ?? null;

        if ($rawRec) {
            $scholarshipName = null;
            $possibleNumericId = null;

            if (is_array($rawRec)) {
                // Ambil nama dari metadata.name, name, title, atau parse dari text
                $scholarshipName = $rawRec['metadata']['name'] 
                    ?? $rawRec['name'] 
                    ?? $rawRec['title'] 
                    ?? null;

                if (!$scholarshipName && !empty($rawRec['text'])) {
                    // Contoh text: "Scholarship Name:\nLPDP Scholarship\n..."
                    if (preg_match('/Scholarship Name:\s*([^\n\r]+)/i', $rawRec['text'], $matches)) {
                        $scholarshipName = trim($matches[1]);
                    }
                }

                // Cek jika ID AI kebetulan numerik yang merujuk ke database kita
                if (isset($rawRec['id']) && is_numeric($rawRec['id'])) {
                    $possibleNumericId = (int) $rawRec['id'];
                }
            } elseif (is_numeric($rawRec)) {
                $possibleNumericId = (int) $rawRec;
            } elseif (is_string($rawRec)) {
                $scholarshipName = $rawRec;
            }

            // A. Pertama, coba cari di DB kita berdasarkan ID numerik (jika ada)
            if ($possibleNumericId) {
                $foundScholarship = Scholarship::find($possibleNumericId);
                if ($foundScholarship) {
                    $recommendedScholarshipId = $foundScholarship->id;
                }
            }

            // B. Jika belum ketemu, cari di DB kita berdasarkan NAMA beasiswa (Scholarship Matcher ke DB lokal)
            if (!$recommendedScholarshipId && $scholarshipName) {
                $cleanName = trim($scholarshipName);

                // 1. Exact match
                $foundScholarship = Scholarship::where('name', $cleanName)->first();

                // 2. Fuzzy match (%Nama%)
                if (!$foundScholarship) {
                    $foundScholarship = Scholarship::where('name', 'LIKE', '%' . $cleanName . '%')->first();
                }

                // 3. Match kata utama (misal "LPDP" dari "LPDP Scholarship")
                if (!$foundScholarship) {
                    $firstWord = strtok($cleanName, " ");
                    if (strlen($firstWord) >= 3) {
                        $foundScholarship = Scholarship::where('name', 'LIKE', '%' . $firstWord . '%')->first();
                    }
                }

                if ($foundScholarship) {
                    $recommendedScholarshipId = $foundScholarship->id;
                }
            }

            // C. Fallback: Jika di DB lokal belum ada beasiswa yang cocok sama sekali, gunakan beasiswa pertama di database sebagai default
            if (!$recommendedScholarshipId) {
                $defaultScholarship = Scholarship::first();
                if ($defaultScholarship) {
                    $recommendedScholarshipId = $defaultScholarship->id;
                }
            }
        }

        DB::beginTransaction();
        try {
            $assessmentData = [
                'assessment_type'            => 'assessment_2', 
                'user_id'                    => $user->id,
                'guest_token'                => null,
                'recommended_scholarship_id' => $recommendedScholarshipId,
                'raw_answers'                => json_encode($userAnswersForAI), 
                'readiness_percentage'       => $aiAssessmentData['revised_percentage'] ?? $user->readiness_score ?? 0,
                'reason'                     => $aiAssessmentData['suggestion'] ?? null, 
                'readiness_level'            => null,
                'academic_score'             => 0,
                'scholarship_goal_score'     => 0,
                'leadership_score'           => 0,
                'achievements_score'         => 0,
                'english_score'              => 0,
                'application_score'          => 0,
                'strengths_mapping'          => [],
                'improvements_mapping'       => [],
            ];

            $assessment = DiagnosticAssessment::updateOrCreate(
                [
                    'user_id'         => $user->id, 
                    'assessment_type' => 'assessment_2' 
                ],
                $assessmentData
            );

            $user->update([
                'readiness_score' => $aiAssessmentData['revised_percentage'] ?? $user->readiness_score
            ]);

            // ====================================================
            // TAMBAHAN BARU: Auto-Complete Milestone Fase 2
            // ====================================================
            // Cari milestone dasar milik user yang berada di urutan ke-2 (Fase 2)
            $fase2Milestone = UserMilestone::where('user_id', $user->id)
                ->whereNull('scholarship_id') // Milestone onboarding/fase awal biasanya tidak terikat beasiswa spesifik
                ->where('step_order', 2)      // Mencari Fase 2
                ->first();

            // Jika Fase 2 ditemukan dan belum selesai, otomatis selesaikan!
            if ($fase2Milestone && $fase2Milestone->status !== 'completed') {
                $fase2Milestone->update([
                    'status'        => 'completed',
                    'is_discovered' => true,
                    'completed_at'  => now()
                ]);
                
                // Tambahkan XP Reward dari Fase 2 ke poin user
                $user->xp_points += $fase2Milestone->xp_reward;
                $user->save();
            }
            // ====================================================

            $xpReward = 100; // Reward untuk mengerjakan Deep Assessment
            $gamificationData = GamificationService::addXpAndCheckBadges($user, $xpReward);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Deep assessment submitted and analyzed by AI successfully. Milestone Fase 2 Auto-Completed!',
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
        
        $assessment = DiagnosticAssessment::with(['recommendedScholarship'])
            ->where('user_id', $user->id)
            ->where('assessment_type', 'assessment_2') 
            ->first();

        if (!$assessment) {
            return response()->json([
                'status' => 'error',
                'message' => 'Deep assessment result not found.'
            ], 404);
        }

        $formattedData = [
            'id'                         => $assessment->id,
            'user_id'                    => $assessment->user_id,
            'assessment_type'            => $assessment->assessment_type,
            'revised_percentage'         => $assessment->readiness_percentage, 
            'suggestion'                 => $assessment->reason,
            'recommended_scholarship_id' => $assessment->recommended_scholarship_id,
            'beasiswa_recomendation'     => $assessment->recommendedScholarship ? [
                'id'               => $assessment->recommendedScholarship->id,
                'name'             => $assessment->recommendedScholarship->name,
                'provider_country' => $assessment->recommendedScholarship->provider_country,
                'funding_type'     => $assessment->recommendedScholarship->funding_type,
                'deadline_date'    => $assessment->recommendedScholarship->deadline_date,
                'image_url'        => $assessment->recommendedScholarship->image_url,
            ] : null,
            'created_at'                 => $assessment->created_at,
            'updated_at'                 => $assessment->updated_at,
        ];

        return response()->json([
            'status' => 'success',
            'message' => 'Deep assessment result retrieved successfully.',
            'data' => $formattedData
        ]);
    }

    /**
     * 4. SETUJU ATAU TOLAK REKOMENDASI BEASISWA DARI AI
     * Jika setuju (accept: true): Simpan beasiswa rekomendasi AI ke tabel user_scholarships & update target beasiswa user.
     * Jika tidak setuju (accept: false): Kosongkan recommended_scholarship_id menjadi null (user milih mandiri).
     */
    public function chooseRecommendation(Request $request)
    {
        $user = Auth::guard('sanctum')->user();

        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized.'], 401);
        }

        $request->validate([
            'accept'         => 'required|boolean',
            'scholarship_id' => 'nullable|exists:scholarships,id',
        ]);

        $assessment = DiagnosticAssessment::where('user_id', $user->id)
            ->where('assessment_type', 'assessment_2')
            ->first();

        if (!$assessment) {
            return response()->json(['status' => 'error', 'message' => 'Data hasil Assessment 2 belum ditemukan.'], 404);
        }

        DB::beginTransaction();
        try {
            if ($request->accept) {
                $targetScholarshipId = $request->scholarship_id ?? $assessment->recommended_scholarship_id;

                if (!$targetScholarshipId) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Tidak ada rekomendasi beasiswa dari AI untuk disetujui. Silakan tentukan ID beasiswa.'
                    ], 400);
                }

                $scholarship = Scholarship::find($targetScholarshipId);

                if (!$scholarship) {
                    return response()->json(['status' => 'error', 'message' => 'Data beasiswa tidak ditemukan.'], 404);
                }

                // 1. Simpan ke tabel user_scholarships
                DB::table('user_scholarships')->updateOrInsert(
                    ['user_id' => $user->id],
                    [
                        'scholarship_id' => $scholarship->id,
                        'created_at'     => now(),
                        'updated_at'     => now(),
                    ]
                );

                // 2. Update target beasiswa utama di profil user
                $user->update([
                    'primary_scholarship_target' => $scholarship->name
                ]);

                // 3. Update status rekomendasi di assessment
                $assessment->update([
                    'recommended_scholarship_id' => $scholarship->id
                ]);

                DB::commit();

                return response()->json([
                    'status' => 'success',
                    'message' => "Rekomendasi beasiswa '{$scholarship->name}' berhasil disetujui dan disimpan sebagai target beasiswa Anda!",
                    'data' => [
                        'user_id'               => $user->id,
                        'primary_target'        => $scholarship->name,
                        'scholarship_detail'    => $scholarship
                    ]
                ], 200);

            } else { // decline
                // Jika user menolak rekomendasi AI -> set recommended_scholarship_id menjadi null
                $assessment->update([
                    'recommended_scholarship_id' => null
                ]);

                DB::commit();

                return response()->json([
                    'status' => 'success',
                    'message' => 'Rekomendasi beasiswa ditolak. Anda dapat memilih target beasiswa secara mandiri dari katalog.',
                    'data' => [
                        'user_id'                    => $user->id,
                        'recommended_scholarship_id' => null
                    ]
                ], 200);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Gagal memproses rekomendasi beasiswa: ' . $e->getMessage()], 500);
        }
    }
}