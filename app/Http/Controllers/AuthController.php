<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Validation\Rules\Password as PasswordRule;

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
            ]
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => strtolower($request->email),
            'phone_number' => $request->phone_number,
            'password' => Hash::make($request->password),
            'role' => 'user',
            'status' => 'active',
        ]);

        event(new Registered($user));

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Registration successful. Please check your email for verification.',
            'data' => [
                'user' => $user,
                'access_token' => $token,
                'token_type' => 'Bearer'
            ]
        ], 201);
    }

    // Verify Email Function (SUDAH DIPERBARUI UNTUK AUTO-LOGIN SPA)
    public function verifyEmail(Request $request, $id, $hash)
    {
        $user = User::find($id);
        
        // Ambil URL dari .env, berikan fallback default
        $redirectUrl = env('FRONTEND_URL', 'http://localhost:5173/profile');

        // Jika user tidak ditemukan, arahkan ke frontend dengan pesan error
        if (!$user) {
            return redirect()->away($redirectUrl . '?error=user_not_found');
        }

        // Jika hash tidak valid
        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return redirect()->away($redirectUrl . '?error=invalid_link');
        }

        // Verifikasi email jika belum diverifikasi
        if (!$user->hasVerifiedEmail()) {
            if ($user->markEmailAsVerified()) {
                event(new Verified($user));
            }
        }

        // AUTO-LOGIN: Buatkan token Sanctum baru
        $token = $user->createToken('auth_token')->plainTextToken;

        // Redirect langsung ke frontend dengan membawa token & status di URL
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