<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\MentorMatchingService;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class MentorMatchingController extends Controller
{
    protected $matchingService;

    public function __construct(MentorMatchingService $matchingService)
    {
        $this->matchingService = $matchingService;
    }

    /**
     * Matches Mentee with AI-recommended Mentors and automatically assigns top mentor to mentee profile.
     * Endpoint: POST /api/mentor/match
     */
    public function matchMentors(Request $request)
    {
        $user = Auth::guard('sanctum')->user() ?? Auth::user();

        // Ambil payload dari body request (opsional/custom)
        $customPayload = $request->all();

        $matchedMentors = $this->matchingService->matchMentors($user, $customPayload);

        $assignedMentorData = null;

        // Otomatis assign mentor teratas (1st match) ke assigned_mentor_id user yang sedang login
        if ($user && !empty($matchedMentors)) {
            $topMentor = $matchedMentors[0];
            $topLocalMentorId = $topMentor['local_mentor_id'] ?? null;

            if ($topLocalMentorId) {
                $user->update([
                    'assigned_mentor_id' => $topLocalMentorId
                ]);

                $assignedMentorData = User::with('mentorProfile')->find($topLocalMentorId);
            }
        }

        return response()->json([
            'status'             => 'success',
            'message'            => 'Mentor berorientasi beasiswa terbaik berhasil dicocokkan dan di-assign ke profil Anda.',
            'assigned_mentor_id' => $user ? $user->assigned_mentor_id : ($assignedMentorData ? $assignedMentorData->id : null),
            'assigned_mentor'    => $assignedMentorData ? $assignedMentorData->makeHidden(['password']) : null,
            'count'              => count($matchedMentors),
            'data'               => $matchedMentors
        ], 200);
    }
}
