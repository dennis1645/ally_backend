<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'shop_item_id',
        'price',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function shopItem()
    {
        return $this->belongsTo(ShopItem::class);
    }

    // Jika detail transaksi ini dipakai untuk bayar booking mentor
    public function consultationBooking()
    {
        return $this->hasOne(ConsultationBooking::class);
    }
}