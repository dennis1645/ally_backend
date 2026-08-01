<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        // Pastikan user sudah login (biasanya sudah di-handle oleh auth:sanctum, tapi untuk jaga-jaga)
        if (! $request->user()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized. Silakan login terlebih dahulu.'
            ], 401);
        }

        // Cek apakah role user ada di dalam daftar role yang diizinkan
        if (! in_array($request->user()->role, $roles)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Forbidden. Kamu tidak memiliki akses ke endpoint ini.'
            ], 403);
        }

        return $next($request);
    }
}