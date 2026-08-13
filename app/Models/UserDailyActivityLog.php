<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserDailyActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'activity_date',
        'status',
        'activity_type'
    ];

    protected $casts = [
        'activity_date' => 'date:Y-m-d',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
