<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class TelescopeAuthController extends Controller
{
    /**
     * Tampilkan halaman login khusus Telescope Admin
     */
    public function showLoginForm()
    {
        $user = Auth::guard('web')->user();

        if ($user && (strtolower($user->email) === 'juna.admin@gmail.com' || $user->role === 'admin')) {
            return redirect('/telescope');
        }

        return view('telescope.login');
    }

    /**
     * Proses autentikasi login Telescope Admin
     */
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $credentials = $request->only('email', 'password');

        if (!Auth::guard('web')->attempt($credentials, $request->boolean('remember'))) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Email atau password Admin tidak cocok.'
            ], 401);
        }

        $user = Auth::guard('web')->user();

        // Cek izin akses admin
        if (strtolower($user->email) !== 'juna.admin@gmail.com' && $user->role !== 'admin') {
            Auth::guard('web')->logout();
            return response()->json([
                'status'  => 'error',
                'message' => 'Akun Anda tidak memiliki hak akses Administrator Telescope.'
            ], 403);
        }

        return response()->json([
            'status'       => 'success',
            'message'      => "Selamat datang kembali, {$user->name}! Mengalihkan ke Dashboard Telescope...",
            'redirect_url' => url('/telescope')
        ], 200);
    }

    /**
     * Logout dari sesi Telescope Admin
     */
    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('telescope.login');
    }
}
