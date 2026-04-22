<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserCoupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'shop_item_id',
        'user_item_id',
        'payment_id',
        'code',
        'discount_percent',
        'is_used',
        'used_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'discount_percent' => 'decimal:2',
            'is_used' => 'boolean',
            'used_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function shopItem()
    {
        return $this->belongsTo(ShopItem::class);
    }

    public function userItem()
    {
        return $this->belongsTo(UserItem::class);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}
