<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\AIMentorChatService;

class AIMentorChatController extends Controller
{
    protected $chatService;

    public function __construct(AIMentorChatService $chatService)
    {
        $this->chatService = $chatService;
    }

    public function sendMessage(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:1000',
        ]);

        $user = Auth::user();
        $userMessage = $request->input('message');

        // Panggil service AI Mentor dengan membawa konteks lengkap user & Gemini API
        $chatData = $this->chatService->chat($user, $userMessage);

        return response()->json([
            'status' => 'success',
            'message' => 'AI Mentor response generated successfully.',
            'data' => [
                'sender'          => 'ai_mentor',
                'user_message'    => $chatData['user_message'],
                'ai_response'     => $chatData['ai_response'],
                'context_summary' => $chatData['context_summary'],
                'timestamp'       => now()->toIso8601String()
            ]
        ], 200);
    }
}