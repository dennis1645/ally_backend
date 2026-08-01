<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Auth\Notifications\ResetPassword;

class CustomResetPassword extends ResetPassword
{
    use Queueable;

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable)
    {
        // Ambil URL Frontend dari .env (fallback ke http://localhost:3000 jika kosong)
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');

        // Buat URL yang mengarah ke halaman Frontend (React/Vue/Next.js)
        $url = "{$frontendUrl}/reset-password?token=" . $this->token . '&email=' . urlencode($notifiable->getEmailForPasswordReset());

        return (new MailMessage)
            ->subject('Reset Password Anda - ALLY Scholarship Platform')
            ->view('emails.reset_password', [
                'url' => $url, 
                'user' => $notifiable
            ]);
    }
}