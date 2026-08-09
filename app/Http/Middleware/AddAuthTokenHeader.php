<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddAuthTokenHeader
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Jika ada cookie 'auth_token' TAPI header 'Authorization' belum ada
        if ($request->hasCookie('auth_token') && !$request->headers->has('Authorization')) {
            // Ambil token dari cookie
            $token = $request->cookie('auth_token');
            
            // Selipkan ke dalam Header Authorization secara dinamis
            $request->headers->set('Authorization', 'Bearer ' . $token);
        }

        return $next($request);
    }
}