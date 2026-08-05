<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PracticeAttempt extends Model
{
    use HasFactory;

    protected $table = 'practice_attempts';

    protected $fillable = [
        'user_id',
        'practice_exam_id',
        'started_at',
        'completed_at',
        'total_score',
        'status',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'total_score' => 'decimal:2',
    ];

    /**
     * Relasi: Riwayat percobaan ini dilakukan oleh satu user.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relasi: Riwayat percobaan ini untuk satu ujian tertentu.
     */
    public function exam()
    {
        return $this->belongsTo(PracticeExam::class, 'practice_exam_id');
    }
}