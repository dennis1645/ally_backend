<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MilestoneSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_milestone_id',
        'user_id',
        'document_vault_id',
        'submission_type',
        'text_response',
        'file_path',
        'file_name',
        'review_status',
        'mentor_feedback',
        'rating',
        'reviewed_by',
        'reviewed_at',
        'xp_awarded',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'rating' => 'integer',
        'xp_awarded' => 'integer',
    ];

    public function milestone()
    {
        return $this->belongsTo(UserMilestone::class, 'user_milestone_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function documentVault()
    {
        return $this->belongsTo(DocumentVault::class, 'document_vault_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
