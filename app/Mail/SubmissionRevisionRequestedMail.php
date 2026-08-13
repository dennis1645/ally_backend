<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\User;
use App\Models\UserMilestone;

class SubmissionRevisionRequestedMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $mentee;
    public UserMilestone $milestone;
    public string $feedback;
    public string $mentorName;

    public function __construct(User $mentee, UserMilestone $milestone, string $feedback, string $mentorName)
    {
        $this->mentee = $mentee;
        $this->milestone = $milestone;
        $this->feedback = $feedback;
        $this->mentorName = $mentorName;
    }

    public function build()
    {
        return $this->subject("[ALLY] Permintaan Revisi Tugas: {$this->milestone->task_name}")
                    ->view('emails.submission_revision_requested');
    }
}
