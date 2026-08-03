<?php

namespace App\Http\Controllers;

use App\Models\DailyJournal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DailyJournalController extends Controller
{
    /**
     * Get a list of daily journals for the authenticated user.
     */
    public function index(Request $request)
    {
        $journals = DailyJournal::where('user_id', Auth::id())
            ->orderBy('date', 'desc')
            ->paginate($request->query('per_page', 10));

        return response()->json([
            'status' => 'success',
            'data' => $journals
        ]);
    }

    /**
     * Store or update a journal for a specific date (1 day = 1 journal).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date|before_or_equal:today',
            'reflection' => 'nullable|string',
            'mood' => 'nullable|string|max:255',
            'goals' => 'nullable|string',
            'achievements' => 'nullable|string',
            'challenges' => 'nullable|string',
            'progress_notes' => 'nullable|string',
            'blockers' => 'nullable|string',
        ]);

        // Anti-XSS: Clean HTML tags from string inputs
        foreach ($validated as $key => $value) {
            if (is_string($value) && $value !== null) {
                $validated[$key] = strip_tags($value);
            }
        }

        // updateOrCreate prevents duplicate journals on the same day
        $journal = DailyJournal::updateOrCreate(
            [
                'user_id' => Auth::id(),
                'date' => $validated['date']
            ],
            $validated
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Daily journal successfully created.',
            'data' => $journal
        ], 201);
    }

    /**
     * Display a specific journal (ensuring ownership).
     */
    public function show($id)
    {
        $journal = DailyJournal::where('user_id', Auth::id())->find($id);

        if (!$journal) {
            return response()->json([
                'status' => 'error',
                'message' => 'Journal not found or unauthorized access.'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $journal
        ]);
    }

    /**
     * Update an existing journal.
     */
    public function update(Request $request, $id)
    {
        $journal = DailyJournal::where('user_id', Auth::id())->find($id);

        if (!$journal) {
            return response()->json([
                'status' => 'error',
                'message' => 'Journal not found or unauthorized access.'
            ], 404);
        }

        $validated = $request->validate([
            'reflection' => 'nullable|string',
            'mood' => 'nullable|string|max:255',
            'goals' => 'nullable|string',
            'achievements' => 'nullable|string',
            'challenges' => 'nullable|string',
            'progress_notes' => 'nullable|string',
            'blockers' => 'nullable|string',
        ]);

        // Anti-XSS
        foreach ($validated as $key => $value) {
            if (is_string($value) && $value !== null) {
                $validated[$key] = strip_tags($value);
            }
        }

        $journal->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Daily journal successfully updated.',
            'data' => $journal
        ]);
    }

    /**
     * Delete a daily journal.
     */
    public function destroy($id)
    {
        $journal = DailyJournal::where('user_id', Auth::id())->find($id);

        if (!$journal) {
            return response()->json([
                'status' => 'error',
                'message' => 'Journal not found or unauthorized access.'
            ], 404);
        }

        $journal->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Daily journal successfully deleted.'
        ]);
    }
}