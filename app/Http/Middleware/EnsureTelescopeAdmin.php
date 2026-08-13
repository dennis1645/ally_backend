<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureTelescopeAdmin
{
    /**
     * Handle an incoming request for Telescope.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('web')->user() ?? Auth::guard('sanctum')->user();

        $isAdmin = $user && (strtolower($user->email) === 'juna.admin@gmail.com' || $user->role === 'admin');

        if (!$isAdmin) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized Telescope Access.'
                ], 403);
            }

            return redirect()->route('telescope.login');
        }

        return $next($request);
    }
}
