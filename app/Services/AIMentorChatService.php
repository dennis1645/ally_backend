<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserMilestone;
use App\Models\DocumentVault;
use App\Models\DiagnosticAssessment;
use App\Models\ActionPlan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIMentorChatService
{
    /**
     * Sends chat message to Google Gemini AI Chatbot with enriched mentee context in ENGLISH.
     *
     * @param User $user
     * @param string $userMessage
     * @return array
     */
    public function chat(User $user, string $userMessage): array
    {
        // 1. ACADEMIC & USER PROFILE CONTEXT
        $readinessScore = (int) ($user->readiness_score ?? 0);
        $primaryTarget  = $user->primary_scholarship_target ?? 'Not specified yet';
        $gpa            = $user->gpa ?? 'Not provided';
        $undergradMajor = $user->undergraduate_major ?? 'Not provided';
        $targetMajor    = $user->target_major ?? 'Not provided';
        $membership     = $user->is_premium ? 'Premium Member' : 'Free Member';
        $xpPoints       = (int) ($user->xp_points ?? 0);
        $userLevel      = (int) ($user->level ?? 1);
        $currentStreak  = (int) ($user->current_streak ?? 0);

        // Assigned Mentor Details
        $assignedMentor = $user->assignedMentor;
        $mentorName     = $assignedMentor ? $assignedMentor->name : 'Not assigned yet';

        // 2. MILESTONE & TIMELINE JOURNEY CONTEXT (INCL. BRANCHING ACTION PLANS)
        $milestones      = UserMilestone::where('user_id', $user->id)->get();
        $totalMilestones = $milestones->count();

        $completedTasks  = $milestones->where('status', 'completed')->pluck('task_name')->toArray();
        $inProgressTasks = $milestones->where('status', 'in_progress')->pluck('task_name')->toArray();
        $pendingTasks    = $milestones->where('status', 'pending')->pluck('task_name')->take(5)->toArray();

        // Mentor Assigned Action Plan Tasks (source = 'mentor')
        $mentorTasks = $milestones->where('source', 'mentor')->map(function($m) {
            return "{$m->task_name} (Deadline: {$m->target_date}, Status: {$m->status})";
        })->toArray();

        $completedCount  = count($completedTasks);
        $inProgressCount = count($inProgressTasks);
        $pendingCount    = $milestones->where('status', 'pending')->count();

        $completedTasksStr  = empty($completedTasks) ? 'None' : implode(', ', array_slice($completedTasks, 0, 5));
        $inProgressTasksStr = empty($inProgressTasks) ? 'No active task' : implode(', ', $inProgressTasks);
        $pendingTasksStr    = empty($pendingTasks) ? 'No pending task' : implode(', ', $pendingTasks);
        $mentorTasksStr     = empty($mentorTasks) ? 'No mentor action plans assigned yet' : implode('; ', $mentorTasks);

        // 3. DOCUMENT VAULT STATUS CONTEXT
        $uploadedDocs = DocumentVault::where('user_id', $user->id)->pluck('file_type')->unique()->toArray();
        $allRequiredDocs = ['cv', 'transcript', 'certificate', 'essay', 'loa'];
        $missingDocs = array_diff($allRequiredDocs, $uploadedDocs);

        $uploadedDocsStr = empty($uploadedDocs) ? 'None in Vault' : implode(', ', array_map('strtoupper', $uploadedDocs));
        $missingDocsStr  = empty($missingDocs) ? 'All essential documents uploaded!' : implode(', ', array_map('strtoupper', $missingDocs));

        // 4. DIAGNOSTIC AI ASSESSMENTS CONTEXT (ASSESSMENT 1 & DEEP ASSESSMENT 2)
        $assessment1 = DiagnosticAssessment::where('user_id', $user->id)
            ->where('assessment_type', 'assessment_1')
            ->first();

        $assessment2 = DiagnosticAssessment::with('recommendedScholarship')
            ->where('user_id', $user->id)
            ->where('assessment_type', 'assessment_2')
            ->first();

        $diagnosticReason = $assessment2->reason ?? ($assessment1->reason ?? 'Initial diagnostic completed');
        $recommendedScholarshipName = $assessment2 && $assessment2->recommendedScholarship 
            ? $assessment2->recommendedScholarship->name 
            : 'Pending AI Matcher evaluation';

        $strengthsList  = $assessment2->strengths_mapping ?? ($assessment1->strengths_mapping ?? []);
        $weaknessesList = $assessment2->improvements_mapping ?? ($assessment1->improvements_mapping ?? []);

        $strengthsStr  = is_array($strengthsList) && !empty($strengthsList) ? implode(', ', $strengthsList) : 'Academic potential and motivation';
        $weaknessesStr = is_array($weaknessesList) && !empty($weaknessesList) ? implode(', ', $weaknessesList) : 'Document preparation and essay evidence gathering';

        // 5. COMPOSE HIGHLY PERSONALIZED ENRICHED SYSTEM PROMPT IN ENGLISH
        $systemPrompt = "You are the official AI Mentor & Scholarship Advisor on the ALLY Platform.
Your persona is an elite, world-class scholarship consultant who is warm, encouraging, highly tactical, strategic, and deeply knowledgeable about global higher-education scholarships (e.g., LPDP, Chevening, MEXT, AAS, Fulbright, KGSP).

CRITICAL DIRECTIVE: YOU MUST RESPOND STRICTLY AND ENTIRELY IN ENGLISH.

[MENTEE PERSONALIZED PROFILE & ACADEMIC DOSSIER]
- Mentee Name: {$user->name} ({$user->email})
- Membership Status: {$membership}
- Level / Total XP: Level {$userLevel} ({$xpPoints} XP, {$currentStreak}-Day Streak)
- GPA / Undergraduate Major: {$gpa} / {$undergradMajor}
- Target Master/PhD Major: {$targetMajor}
- Primary Target Scholarship: {$primaryTarget}
- Overall Readiness Score: {$readinessScore}% (Out of 100%)
- Assigned Human Mentor: {$mentorName}

[JOURNEY TIMELINE & BRANCHING MILESTONES]
- Milestone Summary: Total {$totalMilestones} Tasks (Completed: {$completedCount}, In-Progress: {$inProgressCount}, Pending: {$pendingCount})
- Completed Milestone Tasks: [{$completedTasksStr}]
- Active Current Milestone Task(s): [{$inProgressTasksStr}]
- Upcoming Milestone Tasks: [{$pendingTasksStr}]
- Mentor Action Plans & Branching Tasks: [{$mentorTasksStr}]

[DOCUMENT VAULT STATUS]
- Uploaded Documents: [{$uploadedDocsStr}]
- Missing Required Documents: [{$missingDocsStr}]

[AI DIAGNOSTIC ASSESSMENT & GAP ANALYSIS]
- AI Assessment Feedback/Advice: {$diagnosticReason}
- AI Recommended Scholarship Match: {$recommendedScholarshipName}
- Identified Key Strengths: [{$strengthsStr}]
- Areas for Improvement / Gap Analysis: [{$weaknessesStr}]

[YOUR TRIPLE-ROLE RESPONSIBILITIES AS AI MENTOR]
1. PERSONALIZED ADVISOR: Provide tailored, actionable advice using {$user->name}'s exact profile data, readiness score ({$readinessScore}%), active milestone task, and missing vault documents.
2. JOURNEY & ROADMAP EXPLORER: Guide {$user->name} on what step to take next in their milestone timeline and mentor action plans.
3. SCHOLARSHIP FAQ EXPERT: Answer any question regarding scholarship requirements, essay strategies, interview preparation, LoA acquisition, and application deadlines with crystal-clear expert authority.

[RESPONSE STYLING & TONE]
- Respond in clear, inspiring, professional English.
- Use elegant Markdown formatting (headers, bold highlights, bullet points).
- Address {$user->name} warmly by name.

[MENTEE'S QUESTION / MESSAGE]
{$userMessage}";

        // 6. EXECUTE GEMINI API HTTP REQUEST (v1beta gemini-2.5-flash)
        $geminiApiKey = env('GEMINI_API_KEY');
        $aiReply = null;

        if ($geminiApiKey) {
            try {
                $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . trim($geminiApiKey);

                $payload = [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $systemPrompt]
                            ]
                        ]
                    ],
                    'generationConfig' => [
                        'temperature'     => 0.7,
                        'maxOutputTokens' => 1200,
                    ]
                ];

                Log::info("Sending Chat Request to Gemini API (gemini-2.5-flash)...");

                $response = Http::withHeaders([
                    'Content-Type' => 'application/json'
                ])->timeout(30)->post($apiUrl, $payload);

                if ($response->successful()) {
                    $resData = $response->json();
                    $aiReply = $resData['candidates'][0]['content']['parts'][0]['text'] ?? null;
                } else {
                    Log::error("Gemini API Error Status {$response->status()}: " . $response->body());
                }
            } catch (\Exception $e) {
                Log::error("Gemini API Exception: " . $e->getMessage());
            }
        }

        // 7. ENGLISH FALLBACK MESSAGE IF GEMINI API IS UNREACHABLE
        if (!$aiReply) {
            $aiReply = "Hello **{$user->name}**! I am your AI Mentor on ALLY. Your scholarship readiness is currently at **{$readinessScore}%** for your target **{$primaryTarget}**.\n\n" .
                "Your primary focus right now is completing your active task: **{$inProgressTasksStr}**." .
                ($missingDocsStr !== 'All essential documents uploaded!' ? "\n\nAlso, don't forget to upload your missing documents: **{$missingDocsStr}** to your Document Vault." : "") .
                "\n\nHow can I assist you with your application journey today?";
        }

        return [
            'user_message' => $userMessage,
            'ai_response'  => $aiReply,
            'context_summary' => [
                'user_name'                  => $user->name,
                'readiness_score'            => $readinessScore,
                'primary_scholarship_target' => $primaryTarget,
                'in_progress_tasks'          => $inProgressTasks,
                'mentor_action_plans'        => $mentorTasks,
                'uploaded_documents'         => $uploadedDocs,
                'missing_documents'          => array_values($missingDocs),
                'language'                   => 'English',
            ]
        ];
    }
}