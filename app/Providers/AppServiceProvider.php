<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Mengambil URL Frontend dari file .env (dengan fallback http://localhost:3000 jika kosong)
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');

        // 1. Mengatur URL Reset Password agar mengarah ke Frontend (Biarkan jika ingin tetap ke frontend)
        ResetPassword::createUrlUsing(function ($notifiable, string $token) use ($frontendUrl) {
            return "{$frontendUrl}/reset-password?token={$token}&email=" . urlencode($notifiable->getEmailForPasswordReset());
        });

        // 2. VERIFIKASI EMAIL DIHAPUS DARI SINI
        // Biarkan Laravel menggunakan signed URL bawaannya yang murni menembak Backend API (APP_URL).
        // Nanti tugas AuthController@verifyEmail yang akan melakukan redirect ke frontend secara otomatis!
    }
}