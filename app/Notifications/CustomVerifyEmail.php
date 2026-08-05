<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

class CustomVerifyEmail extends BaseVerifyEmail
{
    use Queueable;

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable)
    {
        // Paksa generate signed URL secara eksplisit menggunakan backend route name
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(60),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );

        return (new MailMessage)
            ->subject('Verifikasi Email Anda - ALLY Scholarship Platform')
            ->view('emails.verify', [
                'url' => $verificationUrl, 
                'user' => $notifiable
            ]);
    }
}