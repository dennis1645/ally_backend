<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\DiagnosticAssessment;
use App\Models\UserMilestone;
use App\Services\GamificationService; // Tambahkan import ini
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB; // Tambahkan import DB
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Carbon\Carbon;

class AuthController extends Controller
{
    // Register function
    public function register(Request $request)
    {
        $request->validate([
            'name' => [
                'required',
                'string',
                'min:4',
                'regex:/^[a-zA-Z\s\.]+$/',
                function ($attribute, $value, $fail) {
                    $trimmed = trim(str_replace(' ', '', $value));
                    if (empty($trimmed) || preg_match('/^\.+$/', $trimmed)) {
                        $fail('Name cannot be empty or contain only dots.');
                    }
                }
            ],
            'email' => [
                'required',
                'email:rfc,dns',
                'ends_with:@gmail.com',
                'unique:users,email',
                function ($attribute, $value, $fail) {
                    $username = explode('@', $value)[0];
                    
                    if (strlen($username) < 3) {
                        $fail('Email username must be at least 3 characters.');
                    }
                    if (preg_match('/^[0-9\.]+$/', $username)) {
                        $fail('Email username must contain letters.');
                    }
                    if (preg_match('/[^a-zA-Z0-9\.]/', $username)) {
                        $fail('Invalid email format. Only dots are allowed.');
                    }
                }
            ],
            'phone_number' => [
                'required',
                'numeric',
                'regex:/^[0-9]+$/',
                'unique:users,phone_number'
            ],
            'password' => [
                'required',
                'string',
                'confirmed',
                PasswordRule::min(8)
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
                function ($attribute, $value, $fail) use ($request) {
                    if ($request->name) {
                        $nameParts = explode(' ', strtolower($request->name));
                        foreach ($nameParts as $part) {
                            if (strlen($part) >= 3 && str_contains(strtolower($value), $part)) {
                                $fail('Password must not contain parts of your name.');
                            }
                        }
                    }
                }
            ],
            'guest_token' => 'nullable|string'
        ]);

        DB::beginTransaction(); // Mulai transaksi database
        try {
            // 1. Buat User Baru
            $user = User::create([
                'name' => $request->name,
                'email' => strtolower($request->email),
                'phone_number' => $request->phone_number,
                'password' => Hash::make($request->password),
                'role' => 'user',
                'status' => 'active',
            ]);

            $isFirstMilestoneCompleted = false;

            // 2. KLAIM HASIL ASESMEN GUEST (Jika guest_token ada)
            if ($request->filled('guest_token')) {
                $assessment = DiagnosticAssessment::where('guest_token', $request->guest_token)->first();
                
                if ($assessment) {
                    // Assign asesmen ke user ini dan hapus token anonimnya
                    $assessment->update([
                        'user_id' => $user->id,
                        'guest_token' => null
                    ]);

                    // Update profil readiness_score user sesuai hasil asesmen
                    $user->update([
                        'readiness_score' => $assessment->overall_score
                    ]);

                    $isFirstMilestoneCompleted = true;
                }
            }

            // 3. GENERATE AUTO-MILESTONE (TASK AWAL) UNTUK USER BARU
            
            // Milestone 1: Self Reflection
            UserMilestone::create([
                'user_id' => $user->id,
                'task_name' => 'Fase 1: Self Reflection',
                'description' => 'Complete the initial diagnostic assessment to map your strengths, weaknesses, and readiness.',
                'step_order' => 1,
                'is_premium' => false, 
                'status' => $isFirstMilestoneCompleted ? 'completed' : 'pending',
                'completed_at' => $isFirstMilestoneCompleted ? now() : null,
                'target_deadline' => Carbon::now()->addDays(2),
                'source' => 'system',
                'is_mandatory' => true,
                'xp_reward' => 50
            ]);

            // Milestone 2: Target Scholarship
            UserMilestone::create([
                'user_id' => $user->id,
                'task_name' => 'Fase 2: Target Scholarship',
                'description' => 'Set your academic goals and select the primary scholarship and university you want to aim for.',
                'step_order' => 2,
                'is_premium' => false,
                'status' => 'pending',
                'target_deadline' => Carbon::now()->addDays(5),
                'source' => 'system',
                'is_mandatory' => true,
                'xp_reward' => 100
            ]);

            // Milestone 3: Reveal Your Mentor
            UserMilestone::create([
                'user_id' => $user->id,
                'task_name' => 'Fase 3: Reveal Your Mentor',
                'description' => 'Unlock and meet your dedicated AI/human mentor to guide your personalized scholarship journey.',
                'step_order' => 3,
                'is_premium' => false,
                'status' => 'pending',
                'target_deadline' => Carbon::now()->addDays(7),
                'source' => 'system',
                'is_mandatory' => true,
                'xp_reward' => 150
            ]);

            // 4. BERIKAN REWARD XP JIKA BAWA GUEST TOKEN
            $gamificationData = null;
            if ($isFirstMilestoneCompleted) {
                // Beri reward 50 XP sesuai dengan xp_reward dari Fase 1
                $gamificationData = GamificationService::addXpAndCheckBadges($user, 50);
            }

            DB::commit(); // Simpan semua perubahan secara permanen

            // Trigger Event & Buat Token (Dilakukan di luar DB transaction agar lebih aman)
            event(new Registered($user));
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'status' => 'success',
                'message' => 'Registration successful. Welcome to your scholarship journey!',
                'data' => [
                    'user' => $user,
                    'access_token' => $token,
                    'token_type' => 'Bearer',
                    'gamification' => $gamificationData // Kirim data naik level/badge ke frontend jika ada
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack(); // Batalkan semua jika ada gagal di tengah jalan
            return response()->json([
                'status' => 'error', 
                'message' => 'Registration failed: ' . $e->getMessage()
            ], 500);
        }
    }

    // Verify Email Function
    public function verifyEmail(Request $request, $id, $hash)
    {
        $user = User::find($id);
        
        $redirectUrl = env('FRONTEND_URL', 'http://localhost:5173/profile');

        if (!$user) {
            return redirect()->away($redirectUrl . '?error=user_not_found');
        }

        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return redirect()->away($redirectUrl . '?error=invalid_link');
        }

        if (!$user->hasVerifiedEmail()) {
            if ($user->markEmailAsVerified()) {
                event(new Verified($user));
            }
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return redirect()->away($redirectUrl . '?token=' . $token . '&verified=true');
    }

    // Login function
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email|max:255',
            'password' => 'required|string',
        ]);

        $throttleKey = Str::transliterate(Str::lower($request->input('email')) . '|' . $request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return response()->json([
                'status' => 'error',
                'message' => "Too many login attempts. Please try again in {$seconds} seconds.",
                'data' => []
            ], 429);
        }

        $credentials = $request->only('email', 'password');

        if (!Auth::attempt($credentials)) {
            RateLimiter::hit($throttleKey, 60);

            return response()->json([
                'status' => 'error',
                'message' => 'Invalid email or password.',
                'data' => []
            ], 401);
        }

        RateLimiter::clear($throttleKey);

        $user = User::where('email', $request->email)->first();

        if ($user->isSuspended()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Your account is suspended. Please contact the administrator.',
                'data' => []
            ], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Login successful.',
            'data' => [
                'user' => $user,
                'access_token' => $token,
                'token_type' => 'Bearer'
            ]
        ], 200);
    }

    // Forgot password function
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => [
                'required',
                'string',
                'email:rfc,dns',
                'regex:/^[a-zA-Z0-9\.]+@gmail\.com$/'
            ],
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'status' => 'success',
                'message' => 'If your email is registered, we have sent a password reset link.',
                'data' => []
            ], 200);
        }

        $status = Password::broker()->sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'status' => 'success',
                'message' => 'Password reset link has been successfully sent to your email.',
                'data' => []
            ], 200);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'System is currently busy. Failed to send reset email, please try again later.',
            'data' => []
        ], 500);
    }

    // Reset password function
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required',
            'password' => [
                'required',
                'string',
                'confirmed',
                PasswordRule::min(8)
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
            ],
        ]);

        $status = Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->setRememberToken(Str::random(60));

                $user->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'status' => 'success',
                'message' => 'Password has been successfully reset. Please log in with your new password.',
                'data' => []
            ], 200);
        }

        return response()->json([
            'status' => 'error',
            'message' => __($status),
            'data' => []
        ], 400);
    }

    // Change password function
    public function changePassword(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'current_password' => ['required', 'string'],
            'password' => [
                'required',
                'string',
                'confirmed',
                'different:current_password', 
                PasswordRule::min(8)
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
                function ($attribute, $value, $fail) use ($user) {
                    if ($user->name) {
                        $nameParts = explode(' ', strtolower($user->name));
                        foreach ($nameParts as $part) {
                            if (strlen($part) >= 3 && str_contains(strtolower($value), $part)) {
                                $fail('Password must not contain parts of your name.');
                            }
                        }
                    }
                }
            ],
        ], [
            'password.different' => 'The new password must be different from the current password.'
        ]);

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'The provided current password does not match our records.',
                'data' => []
            ], 400);
        }

        $user->update([
            'password' => Hash::make($request->password)
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Password has been successfully changed.',
            'data' => []
        ], 200);
    }

    // Logout function
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Logout successful.',
            'data' => []
        ], 200);
    }
}