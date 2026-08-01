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
        'stock_quantity',
        'image_url',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price_rupiah' => 'decimal:2',
            'price_xp' => 'integer',
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