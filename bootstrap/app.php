<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Auth\AuthenticationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        
        // 1. TAMBAHKAN INI: Kecualikan auth_token dari Enkripsi Laravel
        $middleware->encryptCookies(except: [
            'auth_token',
        ]);

        // 2. Mendaftarkan alias middleware kamu
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
        ]);
        
        // 3. Pasang jembatan Header kamu
        $middleware->api(prepend: [
            \App\Http\Middleware\AddAuthTokenHeader::class,
        ]);
        
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Memaksa response JSON untuk semua rute API
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Menangkap error dari auth:sanctum dan mengubah responnya
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized. Silakan login terlebih dahulu.'
                ], 401);
            }
        });
    })->create();