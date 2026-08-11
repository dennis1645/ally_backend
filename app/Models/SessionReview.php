<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SessionReview extends Model
{
    use HasFactory;

    /**
     * Nama tabel jika tidak mengikuti standar penamaan jamak (opsional, tapi bagus untuk dipastikan).
     */
    protected $table = 'session_reviews';

    /**
     * Kolom-kolom yang diizinkan untuk diisi secara massal (Mass Assignment).
     */
    protected $fillable = [
        'booking_id',
        'reviewer_id',
        'reviewee_id',
        'rating',
        'feedback',
    ];

    /**
     * Konversi tipe data otomatis.
     */
    protected $casts = [
        'rating' => 'integer',
    ];

    // ==========================================
    // RELASI MODEL
    // ==========================================

    /**
     * Relasi ke sesi konsultasi (ConsultationBooking).
     */
    public function booking()
    {
        return $this->belongsTo(ConsultationBooking::class, 'booking_id');
    }

    /**
     * Relasi ke User yang memberikan review (Bisa Mentee atau Mentor).
     */
    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /**
     * Relasi ke User yang menerima review/dinilai (Bisa Mentor atau Mentee).
     */
    public function reviewee()
    {
        return $this->belongsTo(User::class, 'reviewee_id');
    }
}