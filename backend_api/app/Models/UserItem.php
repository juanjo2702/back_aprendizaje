<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'shop_item_id',
        'purchase_id',
        'item_type',
        'is_equipped',
        'is_used',
        'metadata',
        'acquired_at',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'is_equipped' => 'boolean',
            'is_used' => 'boolean',
            'metadata' => 'array',
            'acquired_at' => 'datetime',
            'used_at' => 'datetime',
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

    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }

    public function coupon()
    {
        return $this->hasOne(UserCoupon::class);
    }
}
