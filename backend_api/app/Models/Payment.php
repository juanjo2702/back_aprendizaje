<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'user_id',
        'course_id',
        'amount',
        'status',
        'payment_method',
        'provider',
        'qr_data',
        'transaction_id',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'receipt_path',
        'platform_fee_amount',
        'instructor_amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'platform_fee_amount' => 'decimal:2',
            'instructor_amount' => 'decimal:2',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
