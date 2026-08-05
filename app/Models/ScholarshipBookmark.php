<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScholarshipBookmark extends Model
{
    use HasFactory;

    /**
     * Field yang diizinkan untuk mass-assignment.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'scholarship_name', // Sesuai dengan migration, atau ganti jadi 'scholarship_id' jika nanti nyambung ke tabel beasiswa khusus
    ];

    /**
     * Relasi balik ke User (Setiap bookmark dimiliki oleh 1 user)
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}