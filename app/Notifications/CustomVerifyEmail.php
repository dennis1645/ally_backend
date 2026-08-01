<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Auth\Notifications\VerifyEmail; // Ubah ke VerifyEmail

class CustomVerifyEmail extends VerifyEmail
{
    use Queueable;

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable)
    {
        // Menggunakan fungsi bawaan Laravel untuk men-generate link validasi
        $verificationUrl = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('Verifikasi Email Anda - ALLY Scholarship Platform')
            ->view('emails.verify', [
                'url' => $verificationUrl, 
                'user' => $notifiable
            ]);
    }
}