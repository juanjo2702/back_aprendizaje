<?php

namespace App\Services;

use App\Models\AdminActivityLog;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PaymentSettlementService
{
    public function commissionPercentage(): float
    {
        return (float) PlatformSetting::getValue('finance.platform_commission_percentage', 20);
    }

    public function splitAmounts(float $amount): array
    {
        $commissionPercentage = $this->commissionPercentage();
        $platformFee = round($amount * ($commissionPercentage / 100), 2);

        return [
            'commission_percentage' => $commissionPercentage,
            'platform_fee_amount' => $platformFee,
            'instructor_amount' => round($amount - $platformFee, 2),
        ];
    }

    public function approve(
        Payment $payment,
        ?User $reviewer = null,
        ?string $notes = null,
        ?string $receiptPath = null,
        string $source = 'admin_manual'
    ): Payment {
        return DB::transaction(function () use ($payment, $reviewer, $notes, $receiptPath, $source) {
            $split = $this->splitAmounts((float) $payment->amount);

            $payment->forceFill([
                'status' => 'completed',
                'reviewed_by' => $reviewer?->id,
                'reviewed_at' => now(),
                'review_notes' => $notes,
                'receipt_path' => $receiptPath ?: $payment->receipt_path,
                'platform_fee_amount' => $payment->platform_fee_amount > 0 ? $payment->platform_fee_amount : $split['platform_fee_amount'],
                'instructor_amount' => $payment->instructor_amount > 0 ? $payment->instructor_amount : $split['instructor_amount'],
            ])->save();

            if ($payment->userCoupon && ! $payment->userCoupon->is_used) {
                $payment->userCoupon->forceFill([
                    'is_used' => true,
                    'used_at' => now(),
                    'payment_id' => $payment->id,
                ])->save();
            }

            $userItem = $payment->userCoupon?->userItem;
            if ($userItem) {
                $userItem->forceFill([
                    'is_used' => true,
                    'used_at' => now(),
                ])->save();
            }

            Enrollment::query()->firstOrCreate(
                [
                    'user_id' => $payment->user_id,
                    'course_id' => $payment->course_id,
                ],
                [
                    'progress' => 0,
                    'enrolled_at' => now(),
                ]
            );

            AdminActivityLog::record($reviewer, 'payment.approved', $payment, [
                'source' => $source,
                'notes' => $notes,
            ]);

            return $payment->fresh(['user:id,name,email', 'course:id,title,instructor_id', 'course.instructor:id,name,email', 'userCoupon']);
        });
    }

    public function reject(Payment $payment, ?User $reviewer = null, ?string $notes = null): Payment
    {
        $payment->forceFill([
            'status' => 'failed',
            'reviewed_by' => $reviewer?->id,
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ])->save();

        AdminActivityLog::record($reviewer, 'payment.rejected', $payment, [
            'notes' => $notes,
        ]);

        return $payment->fresh(['user:id,name,email', 'course:id,title,instructor_id', 'course.instructor:id,name,email']);
    }
}
