<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\User;
use App\Models\UserMilestone;
use App\Models\MilestoneSubmission;

class TaskResubmittedMentorMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $mentor;
    public User $mentee;
    public UserMilestone $milestone;
    public MilestoneSubmission $submission;

    public function __construct(User $mentor, User $mentee, UserMilestone $milestone, MilestoneSubmission $submission)
    {
        $this->mentor = $mentor;
        $this->mentee = $mentee;
        $this->milestone = $milestone;
        $this->submission = $submission;
    }

    public function build()
    {
        return $this->subject("[ALLY] Mentee Mengirimkan Revisi Tugas: {$this->milestone->task_name}")
                    ->view('emails.task_resubmitted_mentor');
    }
}
