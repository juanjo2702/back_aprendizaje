<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payout extends Model
{
    protected $fillable = [
        'instructor_id',
        'approved_by',
        'gross_amount',
        'platform_fee_amount',
        'net_amount',
        'currency',
        'status',
        'has_open_disputes',
        'dispute_notes',
        'admin_notes',
        'metadata',
        'requested_at',
        'reviewed_at',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'gross_amount' => 'decimal:2',
            'platform_fee_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'has_open_disputes' => 'boolean',
            'metadata' => 'array',
            'requested_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function instructor()
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
