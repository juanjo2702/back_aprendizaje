<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'shop_item_id',
        'cost_coins',
        'status',
        'metadata',
        'purchased_at',
        'consumed_at',
    ];

    protected function casts(): array
    {
        return [
            'cost_coins' => 'integer',
            'metadata' => 'array',
            'purchased_at' => 'datetime',
            'consumed_at' => 'datetime',
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
}
