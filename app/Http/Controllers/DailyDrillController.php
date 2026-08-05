<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\PracticeQuestion;
use App\Models\PracticeOption;
use App\Models\DailyDrill;
use App\Models\DailyDrillAnswer;
use App\Services\GamificationService; // Import Service Gamifikasi
use Carbon\Carbon;

class DailyDrillController extends Controller
{
    /**
     * GENERATE DRILL QUESTIONS
     * Mengambil 10-15 soal secara acak. User bisa mengirimkan parameter
     * 'type' (toefl/ielts) dan 'section' (reading/listening) untuk filter.
     */
    public function generateDrill(Request $request)
    {
        // Validasi input filter dari user
        $validator = Validator::make($request->all(), [
            'type' => 'nullable|string|in:toefl,ielts,other',
            'section' => 'nullable|string',
            'limit' => 'nullable|integer|min:5|max:20'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid filter parameters.',
                'data' => $validator->errors()
            ], 422);
        }

        $limit = $request->input('limit', 10); // Default 10 soal per drill harian

        // Query dasar mengambil soal beserta opsi jawaban 
        // (Is_correct disembunyikan agar user tidak bisa nge-cheat lewat inspect element API)
        $query = PracticeQuestion::with(['options' => function($q) {
            $q->select('id', 'practice_question_id', 'option_text'); 
        }]);

        // Filter berdasarkan Tipe Ujian (TOEFL/IELTS) dengan relasi ke tabel exams
        if ($request->has('type')) {
            $query->whereHas('exam', function ($q) use ($request) {
                $q->where('type', $request->type)->where('is_active', true);
            });
        }

        // Filter berdasarkan Section (Reading/Listening/dll)
        if ($request->has('section')) {
            $query->where('section', $request->section);
        }

        // Ambil data acak
        $questions = $query->inRandomOrder()->limit($limit)->get();

        if ($questions->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No questions found for the selected criteria.',
                'data' => null
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Daily drill questions generated successfully.',
            'data' => $questions
        ], 200);
    }

    /**
     * SUBMIT DRILL ANSWERS
     * Mengirimkan jawaban user, mengkalkulasi skor, dan menyimpan feedback.
     */
    public function submitDrill(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'answers' => 'required|array',
            'answers.*.question_id' => 'required|exists:practice_questions,id',
            'answers.*.selected_option_id' => 'nullable|exists:practice_options,id',
            'difficulty_feedback' => 'nullable|in:too_easy,good,too_hard',
            'feedback_note' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid submission data.',
                'data' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $user = Auth::user();
            $answersData = $request->input('answers');
            
            $totalQuestions = count($answersData);
            $correctAnswersCount = 0;
            $totalScore = 0;

            // Buat record Daily Drill
            $dailyDrill = DailyDrill::create([
                'user_id' => $user->id,
                'drill_date' => Carbon::now()->toDateString(),
                'total_questions' => $totalQuestions,
                'difficulty_feedback' => $request->input('difficulty_feedback'),
                'feedback_note' => $request->input('feedback_note'),
            ]);

            // Proses setiap jawaban
            foreach ($answersData as $answer) {
                $questionId = $answer['question_id'];
                $selectedOptionId = $answer['selected_option_id'];
                $isCorrect = false;

                $question = PracticeQuestion::find($questionId);

                // Cek kebenaran jawaban jika user memilih opsi
                if ($selectedOptionId) {
                    $option = PracticeOption::where('id', $selectedOptionId)
                                            ->where('practice_question_id', $questionId)
                                            ->first();
                    
                    if ($option && $option->is_correct) {
                        $isCorrect = true;
                        $correctAnswersCount++;
                        $totalScore += $question->score_weight; // Tambahkan bobot skor jika benar
                    }
                }

                // Simpan detail jawaban user
                DailyDrillAnswer::create([
                    'daily_drill_id' => $dailyDrill->id,
                    'practice_question_id' => $questionId,
                    'selected_option_id' => $selectedOptionId,
                    'is_correct' => $isCorrect,
                ]);
            }

            // Hitung XP (Contoh: 10 XP per jawaban benar + 20 XP bonus karena selesai)
            $xpEarned = ($correctAnswersCount * 10) + 20;

            // Update skor akhir dan XP di tabel utama
            $dailyDrill->update([
                'correct_answers' => $correctAnswersCount,
                'total_score' => $totalScore,
                'xp_earned' => $xpEarned,
            ]);

            // Panggil Gamification Service untuk menambahkan XP ke user dan cek badge baru
            $gamificationResult = GamificationService::addXpAndCheckBadges($user, $xpEarned);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Daily drill submitted successfully.',
                'data' => [
                    'drill_id' => $dailyDrill->id,
                    'total_questions' => $totalQuestions,
                    'correct_answers' => $correctAnswersCount,
                    'total_score' => $totalScore,
                    'xp_earned' => $xpEarned,
                    'feedback_logged' => $request->input('difficulty_feedback'),
                    'gamification' => $gamificationResult // Mengirim status level & badge ke frontend
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to submit daily drill.',
                'data' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET USER DRILL HISTORY
     * Melihat riwayat skor dan drill yang pernah dikerjakan user.
     */
    public function history()
    {
        $user = Auth::user();
        $history = DailyDrill::where('user_id', $user->id)
                             ->orderBy('created_at', 'desc')
                             ->get();

        return response()->json([
            'status' => 'success',
            'message' => 'User drill history retrieved successfully.',
            'data' => $history
        ], 200);
    }

    /**
     * GET SPECIFIC DRILL DETAIL
     * Melihat detail jawaban (benar/salah) dari satu sesi drill tertentu.
     */
    public function show($id)
    {
        $user = Auth::user();
        $drill = DailyDrill::with(['answers.question', 'answers.selectedOption'])
                           ->where('user_id', $user->id)
                           ->where('id', $id)
                           ->first();

        if (!$drill) {
            return response()->json([
                'status' => 'error',
                'message' => 'Drill record not found or unauthorized.',
                'data' => null
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Drill detail retrieved successfully.',
            'data' => $drill
        ], 200);
    }
}