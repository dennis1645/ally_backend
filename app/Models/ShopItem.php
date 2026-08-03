<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShopItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'item_type',
        'description',
        'price_rupiah',
        'price_xp',
        'token_reward',     // Ditambahkan
        'duration_days',    // Ditambahkan
        'stock_quantity',
        'image_url',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price_rupiah' => 'decimal:2',
            'price_xp' => 'integer',
            'token_reward' => 'integer',    // Ditambahkan
            'duration_days' => 'integer',   // Ditambahkan
            'stock_quantity' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    // ==========================================
    // RELATIONS
    // ==========================================
    public function transactionDetails()
    {
        return $this->hasMany(TransactionDetail::class);
    }
}