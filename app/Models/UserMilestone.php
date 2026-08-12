<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserMilestone extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id',      // Ditambahkan untuk fitur branching (sub-task)
        'user_id',
        'scholarship_id',
        'university_id',
        'task_name',
        'description',
        'step_order',     
        'is_premium',     
        'start_date',
        'target_date',
        'status',
        'completed_at',
        'source',
        'is_mandatory',
        'is_discovered',  
        'xp_reward',
    ];

    protected $casts = [
        'is_premium'    => 'boolean',
        'is_mandatory'  => 'boolean',
        'is_discovered' => 'boolean',
        'start_date'    => 'date',
        'target_date'   => 'date',
        'completed_at'  => 'datetime',
    ];

    // ==========================================
    // RELASI UNTUK FITUR BRANCHING (SUB-TASK)
    // ==========================================

    /**
     * Mendapatkan task utama (parent) jika task ini adalah sebuah cabang (sub-task)
     */
    public function parent()
    {
        return $this->belongsTo(UserMilestone::class, 'parent_id');
    }

    /**
     * Mendapatkan semua cabang/sub-task dari task utama ini
     */
    public function subTasks()
    {
        return $this->hasMany(UserMilestone::class, 'parent_id');
    }

    // ==========================================
    // RELASI UMUM
    // ==========================================

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scholarship()
    {
        return $this->belongsTo(Scholarship::class);
    }

    public function university()
    {
        return $this->belongsTo(University::class);
    }

    public function submissions()
    {
        return $this->hasMany(MilestoneSubmission::class, 'user_milestone_id');
    }

    public function latestSubmission()
    {
        return $this->hasOne(MilestoneSubmission::class, 'user_milestone_id')->latestOfMany();
    }
}