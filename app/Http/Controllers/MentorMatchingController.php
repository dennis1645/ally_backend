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

        // Otomatis assign mentor teratas (1st match) yang belum mencapai kuota 5 mentee
        if ($user && !empty($matchedMentors)) {
            foreach ($matchedMentors as $mentorCandidate) {
                $candidateId = $mentorCandidate['local_mentor_id'] ?? null;
                if ($candidateId) {
                    $assignedIds = User::where('assigned_mentor_id', $candidateId)->pluck('id')->toArray();
                    $bookedIds   = \App\Models\ConsultationBooking::where('mentor_id', $candidateId)->pluck('mentee_id')->toArray();
                    $uniqueMenteeCount = count(array_unique(array_merge($assignedIds, $bookedIds)));

                    // Jika kandidat ini adalah mentor mentee saat ini OR jumlah menteenya < 5
                    if ($user->assigned_mentor_id == $candidateId || $uniqueMenteeCount < 5) {
                        $user->update([
                            'assigned_mentor_id' => $candidateId
                        ]);

                        $assignedMentorData = User::with('mentorProfile')->find($candidateId);
                        break;
                    }
                }
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
