<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\PracticeExam;
use App\Models\PracticeQuestion;
use App\Models\PracticeOption;
use Maatwebsite\Excel\Facades\Excel; // Pastikan package maatwebsite/excel sudah terinstall
use Maatwebsite\Excel\Concerns\ToArray;

class AdminPracticeExamController extends Controller
{
    /**
     * Get All Practice Exams
     */
    public function index()
    {
        $exams = PracticeExam::withCount('questions')->orderBy('created_at', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Practice exams retrieved successfully.',
            'data' => $exams
        ], 200);
    }

    /**
     * Get Single Exam Detail with Questions & Options
     */
    public function showExam($id)
    {
        $exam = PracticeExam::with('questions.options')->find($id);

        if (!$exam) {
            return response()->json([
                'status' => 'error',
                'message' => 'Practice exam not found.',
                'data' => null
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Practice exam details retrieved successfully.',
            'data' => $exam
        ], 200);
    }

    /**
     * BULK IMPORT via Excel (.xls, .xlsx, .csv)
     */
    public function importExcel(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|mimes:xls,xlsx,csv|max:5120', // Max 5MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid file format. Please upload .xls, .xlsx, or .csv.',
                'data' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Menggunakan anonymous class untuk membaca array dari Excel tanpa perlu membuat file Import terpisah
            $importClass = new class implements ToArray {
                public function array(array $array) {}
            };

            $data = Excel::toArray($importClass, $request->file('file'));
            $rows = $data[0]; // Ambil sheet pertama

            $importedCount = 0;

            foreach ($rows as $index => $row) {
                if ($index === 0) continue; // Skip baris pertama (Header)

                // Skip baris jika judul exam atau pertanyaan kosong
                if (empty($row[0]) || empty($row[6])) continue; 

                // 1. Create atau Get Exam
                $exam = PracticeExam::firstOrCreate(
                    ['title' => $row[0]], 
                    [
                        'type' => strtolower($row[1]) ?? 'other',
                        'duration_minutes' => (int) $row[2] ?? 60,
                        'description' => 'Imported from Excel',
                        'is_active' => true,
                    ]
                );

                // 2. Create Question
                $questionType = strtolower($row[7]) ?? 'multiple_choice';
                $question = PracticeQuestion::create([
                    'practice_exam_id' => $exam->id,
                    'section' => strtolower($row[3]) ?? 'reading',
                    'context_text' => $row[4] ?? null,
                    'audio_url' => $row[5] ?? null,
                    'question_text' => $row[6],
                    'question_type' => $questionType,
                    'score_weight' => (int) $row[8] ?? 1,
                ]);

                // 3. Create Options (Jika tipenya Multiple Choice)
                if ($questionType === 'multiple_choice') {
                    $correctAnswer = strtoupper($row[13] ?? 'A');
                    
                    $optionsData = [
                        'A' => $row[9] ?? null,
                        'B' => $row[10] ?? null,
                        'C' => $row[11] ?? null,
                        'D' => $row[12] ?? null,
                    ];

                    foreach ($optionsData as $key => $optionText) {
                        if (!empty($optionText)) {
                            PracticeOption::create([
                                'practice_question_id' => $question->id,
                                'option_text' => $optionText,
                                'is_correct' => ($key === $correctAnswer) ? true : false,
                            ]);
                        }
                    }
                }

                $importedCount++;
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => "Successfully imported {$importedCount} questions.",
                'data' => null
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to import data. Please check your Excel format.',
                'data' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * UPDATE SINGLE EXAM (Header)
     */
    public function updateExam(Request $request, $id)
    {
        $exam = PracticeExam::find($id);

        if (!$exam) {
            return response()->json(['status' => 'error', 'message' => 'Exam not found.', 'data' => null], 404);
        }

        $exam->update($request->only(['title', 'type', 'description', 'duration_minutes', 'is_active']));

        return response()->json([
            'status' => 'success',
            'message' => 'Practice exam updated successfully.',
            'data' => $exam
        ], 200);
    }

    /**
     * UPDATE SINGLE QUESTION
     */
    public function updateQuestion(Request $request, $id)
    {
        $question = PracticeQuestion::find($id);

        if (!$question) {
            return response()->json(['status' => 'error', 'message' => 'Question not found.', 'data' => null], 404);
        }

        $question->update($request->only([
            'section', 'context_text', 'audio_url', 'question_text', 'question_type', 'score_weight'
        ]));

        return response()->json([
            'status' => 'success',
            'message' => 'Question updated successfully.',
            'data' => $question
        ], 200);
    }

    /**
     * DELETE SINGLE QUESTION
     */
    public function destroyQuestion($id)
    {
        $question = PracticeQuestion::find($id);

        if (!$question) {
            return response()->json(['status' => 'error', 'message' => 'Question not found.', 'data' => null], 404);
        }

        $question->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Question deleted successfully.',
            'data' => null
        ], 200);
    }

    /**
     * DELETE SINGLE EXAM
     */
    public function destroyExam($id)
    {
        $exam = PracticeExam::find($id);

        if (!$exam) {
            return response()->json(['status' => 'error', 'message' => 'Exam not found.', 'data' => null], 404);
        }

        // Ini akan otomatis menghapus Question dan Option karena setting 'cascadeOnDelete' di migration
        $exam->delete(); 

        return response()->json([
            'status' => 'success',
            'message' => 'Exam and all its questions deleted successfully.',
            'data' => null
        ], 200);
    }

    /**
     * DELETE ALL EXAMS (Clear All)
     */
    public function destroyAll()
    {
        try {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            PracticeExam::truncate();
            PracticeQuestion::truncate();
            PracticeOption::truncate();
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');

            return response()->json([
                'status' => 'success',
                'message' => 'All practice exams and questions have been permanently deleted.',
                'data' => null
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete data.',
                'data' => $e->getMessage()
            ], 500);
        }
    }
}