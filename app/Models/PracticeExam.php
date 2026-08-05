<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PracticeExam extends Model
{
    use HasFactory;

    protected $table = 'practice_exams';

    protected $fillable = [
        'title',
        'type',
        'description',
        'duration_minutes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Relasi: Satu ujian memiliki banyak pertanyaan.
     */
    public function questions()
    {
        return $this->hasMany(PracticeQuestion::class, 'practice_exam_id');
    }

    /**
     * Relasi: Satu ujian bisa dikerjakan berkali-kali oleh banyak user.
     */
    public function attempts()
    {
        return $this->hasMany(PracticeAttempt::class, 'practice_exam_id');
    }
}