<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\DiagnosticAssessment;
use App\Models\UserMilestone;
use App\Services\GamificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
                'string',
                'min:12',
                'max:16',
                'unique:users,phone_number',
                function ($attribute, $value, $fail) {
                    // Daftar ekstensif kode negara di dunia
                    $validCountryCodes = [
                        '+1', '+7', '+20', '+27', '+31', '+32', '+33', '+34', '+36', '+39', '+40', 
                        '+41', '+43', '+44', '+45', '+46', '+47', '+48', '+49', '+51', '+52', '+54', 
                        '+55', '+56', '+57', '+58', '+60', '+61', '+62', '+63', '+64', '+65', '+66', 
                        '+81', '+82', '+84', '+86', '+90', '+91', '+92', '+93', '+94', '+95', '+98',
                        '+212', '+213', '+216', '+218', '+220', '+221', '+222', '+223', '+224', '+225',
                        '+226', '+227', '+228', '+229', '+230', '+231', '+232', '+233', '+234', '+236',
                        '+237', '+238', '+239', '+240', '+241', '+242', '+243', '+244', '+245', '+246',
                        '+248', '+249', '+250', '+251', '+252', '+253', '+254', '+255', '+256', '+257',
                        '+258', '+260', '+261', '+262', '+263', '+264', '+265', '+266', '+267', '+268',
                        '+269', '+290', '+291', '+297', '+298', '+299', '+350', '+351', '+352', '+353',
                        '+354', '+355', '+356', '+357', '+358', '+359', '+370', '+371', '+372', '+373',
                        '+374', '+375', '+376', '+377', '+378', '+379', '+380', '+381', '+382', '+385',
                        '+386', '+387', '+389', '+420', '+421', '+423', '+500', '+501', '+502', '+503',
                        '+504', '+505', '+506', '+507', '+508', '+509', '+590', '+591', '+592', '+593',
                        '+594', '+595', '+596', '+597', '+598', '+599', '+850', '+852', '+853', '+855',
                        '+856', '+880', '+886', '+960', '+961', '+962', '+963', '+964', '+965', '+966',
                        '+967', '+968', '+970', '+971', '+972', '+973', '+974', '+975', '+976', '+977',
                        '+992', '+993', '+994', '+995', '+996', '+998'
                    ];

                    $startsWithValidCode = false;
                    $coreNumber = '';

                    // Cek apakah diawali 0 atau kode negara
                    if (str_starts_with($value, '0')) {
                        $startsWithValidCode = true;
                        $coreNumber = substr($value, 1);
                    } else {
                        foreach ($validCountryCodes as $code) {
                            if (str_starts_with($value, $code)) {
                                $startsWithValidCode = true;
                                $coreNumber = substr($value, strlen($code));
                                break;
                            }
                        }
                    }

                    if (!$startsWithValidCode) {
                        $fail('Nomor telepon harus diawali dengan angka 0 atau kode negara yang valid (contoh: +62).');
                        return;
                    }

                    // Cek apakah sisanya benar-benar angka semua
                    if (!preg_match('/^[0-9]+$/', $coreNumber)) {
                        $fail('Format nomor telepon tidak valid. Hanya gunakan angka setelah prefix.');
                        return;
                    }

                    // Cek angka berulang identik (contoh: 000000000 atau 999999999)
                    if (preg_match('/^(.)\1+$/', $coreNumber)) {
                        $fail('Nomor telepon tidak valid karena angka berulang secara tidak wajar (contoh: 000000000).');
                    }
                }
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

        DB::beginTransaction(); 
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
                    $assessment->update([
                        'user_id' => $user->id,
                        'guest_token' => null
                    ]);

                    $user->update([
                        'readiness_score' => $assessment->overall_score
                    ]);

                    $isFirstMilestoneCompleted = true;
                }
            }

            // 3. GENERATE AUTO-MILESTONE (TASK AWAL) UNTUK USER BARU
            // Fase 1: is_discovered diset true agar langsung terbuka
            UserMilestone::create([
                'user_id' => $user->id,
                'task_name' => 'Fase 1: Self Reflection',
                'description' => 'Complete the initial diagnostic assessment to map your strengths, weaknesses, and readiness.',
                'step_order' => 1,
                'is_premium' => false, 
                'status' => $isFirstMilestoneCompleted ? 'completed' : 'pending',
                'completed_at' => $isFirstMilestoneCompleted ? now() : null,
                'start_date' => Carbon::now(),
                'target_date' => Carbon::now()->addDays(2),
                'source' => 'system',
                'is_mandatory' => true,
                'is_discovered' => true, // <-- SET TO TRUE
                'xp_reward' => 50
            ]);

            // Fase 2: is_discovered diset false agar user harus tap/reveal
            UserMilestone::create([
                'user_id' => $user->id,
                'task_name' => 'Fase 2: Target Scholarship',
                'description' => 'Set your academic goals and select the primary scholarship and university you want to aim for.',
                'step_order' => 2,
                'is_premium' => false,
                'status' => 'pending',
                'start_date' => Carbon::now()->addDays(2),
                'target_date' => Carbon::now()->addDays(5),
                'source' => 'system',
                'is_mandatory' => true,
                'is_discovered' => false, // <-- SET TO FALSE
                'xp_reward' => 100
            ]);

            // 4. BERIKAN REWARD XP JIKA BAWA GUEST TOKEN
            $gamificationData = null;
            if ($isFirstMilestoneCompleted) {
                $gamificationData = GamificationService::addXpAndCheckBadges($user, 50);
            }

            DB::commit();

            event(new Registered($user));
            $token = $user->createToken('auth_token')->plainTextToken;

            // Setup HTTP-Only Cookie
            $cookie = cookie(
                'auth_token', 
                $token, 
                60 * 24 * 7, // Kadaluarsa dalam 7 hari (dalam menit)
                '/', 
                null, 
                env('APP_ENV') !== 'local', // Secure flag aktif jika bukan di environment local
                true, // Fitur Keamanan XSS: HTTP-Only!
                false, 
                'Strict' // Proteksi CSRF
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Registration successful. Welcome to your scholarship journey!',
                'data' => [
                    'user' => $user,
                    'access_token' => $token, // Tetap di-pass dalam JSON sesuai permintaan
                    'token_type' => 'Bearer',
                    'gamification' => $gamificationData 
                ]
            ], 201)->withCookie($cookie);

        } catch (\Exception $e) {
            DB::rollBack();
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
        
        // Setup HTTP-Only Cookie setelah berhasil verifikasi dan login otomatis
        $cookie = cookie('auth_token', $token, 60 * 24 * 7, '/', null, env('APP_ENV') !== 'local', true, false, 'Strict');

        return redirect()->away($redirectUrl . '?token=' . $token . '&verified=true')->withCookie($cookie);
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

        // =====================================
        // IMPLEMENTASI HTTP-ONLY COOKIE LOGIN
        // =====================================
        $cookie = cookie(
            'auth_token', 
            $token, 
            60 * 24 * 7, // 7 hari
            '/', 
            null, 
            env('APP_ENV') !== 'local', // Secure flag (Hanya https jika bukan local)
            true, // HTTP-Only di-set TRUE
            false, 
            'Strict' // SameSite di-set Strict
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Login successful.',
            'data' => [
                'user' => $user,
                'access_token' => $token, // Sesuai permintaan, tetap terlampir di response
                'token_type' => 'Bearer'
            ]
        ], 200)->withCookie($cookie);
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
        // Hapus token akses yang saat ini berjalan
        $request->user()->currentAccessToken()->delete();

        // Lakukan pembersihan HTTP-Only Cookie saat logout
        $cookie = cookie()->forget('auth_token');

        return response()->json([
            'status' => 'success',
            'message' => 'Logout successful.',
            'data' => []
        ], 200)->withCookie($cookie);
    }
}