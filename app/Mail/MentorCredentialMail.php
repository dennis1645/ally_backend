<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\User;

class MentorCredentialMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $mentor;
    public string $plainPassword;

    public function __construct(User $mentor, string $plainPassword = 'P@ssw0rd123')
    {
        $this->mentor = $mentor;
        $this->plainPassword = $plainPassword;
    }

    public function build()
    {
        return $this->subject('[ALLY] Akun Mentor Baru Anda Telah Dibuat')
                    ->view('emails.mentor_credential');
    }
}
