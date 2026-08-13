<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\User;
use App\Models\UserMilestone;

class SubmissionApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $mentee;
    public UserMilestone $milestone;
    public string $feedback;
    public string $mentorName;
    public int $xpAwarded;

    public function __construct(User $mentee, UserMilestone $milestone, string $feedback, string $mentorName, int $xpAwarded = 50)
    {
        $this->mentee = $mentee;
        $this->milestone = $milestone;
        $this->feedback = $feedback;
        $this->mentorName = $mentorName;
        $this->xpAwarded = $xpAwarded;
    }

    public function build()
    {
        return $this->subject("[ALLY] Selamat! Tugas '{$this->milestone->task_name}' Disetujui Mentor")
                    ->view('emails.submission_approved');
    }
}
