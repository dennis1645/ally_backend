<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
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

        // 1. Mengatur URL Reset Password agar mengarah ke Frontend
        ResetPassword::createUrlUsing(function ($notifiable, string $token) use ($frontendUrl) {
            return "{$frontendUrl}/reset-password?token={$token}&email=" . urlencode($notifiable->getEmailForPasswordReset());
        });

        // 2. (Opsional) Mengatur URL Verifikasi Email agar mengarah ke Frontend
        VerifyEmail::createUrlUsing(function ($notifiable) use ($frontendUrl) {
            // Membuat signed URL sementara dari backend
            $backendUrl = URL::temporarySignedRoute(
                'verification.verify',
                now()->addMinutes(60),
                ['id' => $notifiable->getKey(), 'hash' => sha1($notifiable->getEmailForVerification())]
            );

            // Mengganti domain backend (APP_URL) dengan domain frontend dari .env
            $appUrl = config('app.url', 'http://localhost:8000');
            
            return str_replace($appUrl, $frontendUrl, $backendUrl);
        });
    }
}