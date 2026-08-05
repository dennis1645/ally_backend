<?php

namespace App\Http\Controllers;

use App\Models\DiagnosticQuestion;
use App\Models\DiagnosticOption;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\DiagnosticQuestionsImport; 

class AdminDiagnosticController extends Controller
{
    // Display all questions along with their options
    public function index()
    {
        $questions = DiagnosticQuestion::with('options')->orderBy('order_number', 'asc')->get();

        return response()->json([
            'status' => 'success',
            'data' => $questions
        ]);
    }

    // Bulk Upload Questions via Excel (.xls, .xlsx, .csv)
    public function importExcel(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:xls,xlsx,csv|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // DB Transaction is handled within the Import class to ensure atomic rollback
            Excel::import(new DiagnosticQuestionsImport, $request->file('file'));

            return response()->json([
                'status' => 'success',
                'message' => 'Assessment question bank imported successfully from Excel.'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to import file: ' . $e->getMessage()
            ], 500);
        }
    }

    // Add a single question manually
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'question_text' => 'required|string',
            // Tambahan: Validasi ketat agar kategori tidak typo dan sesuai dengan sistem scoring
            'category' => 'required|string|in:academic,goals,leadership_experience,language,application_readiness',
            'order_number' => 'nullable|integer',
            'options' => 'required|array|min:2',
            'options.*.option_text' => 'required|string',
            'options.*.score_weight' => 'required|integer',
            'options.*.weakness_tag' => 'nullable|string',
            'options.*.strength_tag' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            // 1. Create Question (Anti-XSS with strip_tags)
            $question = DiagnosticQuestion::create([
                'question_text' => strip_tags($request->question_text),
                'category' => strip_tags($request->category),
                'is_active' => true,
                'order_number' => $request->order_number ?? 0,
            ]);

            // 2. Create Answer Options
            foreach ($request->options as $opt) {
                DiagnosticOption::create([
                    'diagnostic_question_id' => $question->id,
                    'option_text' => strip_tags($opt['option_text']),
                    'score_weight' => $opt['score_weight'],
                    'weakness_tag' => isset($opt['weakness_tag']) ? strip_tags($opt['weakness_tag']) : null,
                    'strength_tag' => isset($opt['strength_tag']) ? strip_tags($opt['strength_tag']) : null,
                ]);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Question added successfully.',
                'data' => $question->load('options')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Failed to save: ' . $e->getMessage()], 500);
        }
    }

    // Update an existing question and its options
    public function update(Request $request, $id)
    {
        $question = DiagnosticQuestion::find($id);

        if (!$question) {
            return response()->json(['status' => 'error', 'message' => 'Question not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'question_text' => 'required|string',
            // Tambahan: Validasi ketat agar kategori tidak typo
            'category' => 'required|string|in:academic,goals,leadership_experience,language,application_readiness',
            'order_number' => 'nullable|integer',
            'options' => 'required|array|min:2',
            'options.*.option_text' => 'required|string',
            'options.*.score_weight' => 'required|integer',
            'options.*.weakness_tag' => 'nullable|string',
            'options.*.strength_tag' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            // 1. Update the main question
            $question->update([
                'question_text' => strip_tags($request->question_text),
                'category' => strip_tags($request->category),
                'order_number' => $request->order_number ?? $question->order_number,
            ]);

            // 2. Delete old options
            $question->options()->delete();

            // 3. Insert the new/updated options
            foreach ($request->options as $opt) {
                DiagnosticOption::create([
                    'diagnostic_question_id' => $question->id,
                    'option_text' => strip_tags($opt['option_text']),
                    'score_weight' => $opt['score_weight'],
                    'weakness_tag' => isset($opt['weakness_tag']) ? strip_tags($opt['weakness_tag']) : null,
                    'strength_tag' => isset($opt['strength_tag']) ? strip_tags($opt['strength_tag']) : null,
                ]);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Question updated successfully.',
                'data' => $question->load('options')
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Failed to update: ' . $e->getMessage()], 500);
        }
    }

    // Delete a single question (automatically deletes options due to cascadeOnDelete)
    public function destroy($id)
    {
        $question = DiagnosticQuestion::find($id);

        if (!$question) {
            return response()->json(['status' => 'error', 'message' => 'Question not found.'], 404);
        }

        $question->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Question and its options deleted successfully.'
        ]);
    }

    // Delete ALL questions
    public function destroyAll()
    {
        try {
            DiagnosticQuestion::query()->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'All questions have been cleared successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to clear questions: ' . $e->getMessage()
            ], 500);
        }
    }
}