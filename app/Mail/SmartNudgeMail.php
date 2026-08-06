<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SmartNudgeMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $context; // 'H-3', 'H-1', 'Hari H', atau 'H+1'
    public $itemName; // Nama tugas/beasiswa/milestone
    public $itemType; // 'Milestone', 'Subtask', 'Beasiswa'

    public function __construct($user, $context, $itemName, $itemType)
    {
        $this->user = $user;
        $this->context = $context;
        $this->itemName = $itemName;
        $this->itemType = $itemType;
    }

    public function build()
    {
        $subject = "[Pengingat] {$this->itemType}: {$this->itemName} ({$this->context})";

        return $this->subject($subject)
                    ->view('emails.smart_nudge'); // Pastikan membuat view blade-nya
    }
}