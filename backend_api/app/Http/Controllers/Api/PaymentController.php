<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Payment;
use App\Models\UserCoupon;
use App\Services\PaymentSettlementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentSettlementService $paymentSettlementService
    ) {
    }

    /**
     * Genera la intención de compra y el código QR.
     */
    public function createIntent(Request $request)
    {
        $request->validate([
            'course_id' => 'required|exists:courses,id',
            'coupon_code' => 'nullable|string|max:100',
        ]);

        $course = Course::findOrFail($request->course_id);
        $user = $request->user();

        // Verificar si ya está inscrito
        if ($user->enrollments()->where('course_id', $course->id)->exists()) {
            return response()->json(['message' => 'Ya estás inscrito en este curso.'], 400);
        }

        if ($user->current_level < (int) $course->minimum_level_required) {
            return response()->json([
                'message' => "Necesitas llegar al nivel {$course->minimum_level_required} para comprar este curso.",
                'required_level' => (int) $course->minimum_level_required,
                'user_level' => (int) $user->current_level,
            ], 403);
        }

        $originalAmount = round((float) $course->price, 2);
        $coupon = null;
        $couponCode = trim((string) $request->input('coupon_code', ''));
        $discountPercent = 0.0;
        $discountAmount = 0.0;
        $finalAmount = $originalAmount;

        if ($couponCode !== '') {
            $coupon = UserCoupon::query()
                ->where('user_id', $user->id)
                ->where('code', strtoupper($couponCode))
                ->with('userItem')
                ->first();

            if (! $coupon) {
                return response()->json(['message' => 'El cupón no existe o no pertenece a tu cuenta.'], 422);
            }

            if ($coupon->is_used) {
                return response()->json(['message' => 'Este cupón ya fue utilizado.'], 422);
            }

            $discountPercent = round((float) $coupon->discount_percent, 2);
            $discountAmount = round(min($originalAmount, $originalAmount * ($discountPercent / 100)), 2);
            $finalAmount = round(max(0, $originalAmount - $discountAmount), 2);
        }

        $transactionId = 'TXN-'.strtoupper(Str::random(12));
        $paymentUrl = env('APP_URL')."/api/payments/webhook?transaction_id={$transactionId}";
        $split = $this->paymentSettlementService->splitAmounts($finalAmount);

        $payment = DB::transaction(function () use ($user, $course, $finalAmount, $originalAmount, $transactionId, $paymentUrl, $split, $coupon, $discountPercent, $discountAmount) {
            $payment = Payment::create([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'amount' => $finalAmount,
                'original_amount' => $originalAmount,
                'status' => $finalAmount <= 0 ? 'completed' : 'pending',
                'payment_method' => $finalAmount <= 0 ? 'coupon' : 'qr_manual',
                'provider' => $finalAmount <= 0 ? 'internal_coupon' : 'bolivia_qr',
                'qr_data' => $finalAmount <= 0 ? null : $paymentUrl,
                'transaction_id' => $transactionId,
                'coupon_code' => $coupon?->code,
                'coupon_discount_percent' => $discountPercent,
                'coupon_discount_amount' => $discountAmount,
                'user_coupon_id' => $coupon?->id,
                'platform_fee_amount' => $split['platform_fee_amount'],
                'instructor_amount' => $split['instructor_amount'],
            ]);

            if ($coupon) {
                $coupon->forceFill([
                    'payment_id' => $payment->id,
                    'is_used' => true,
                    'used_at' => now(),
                ])->save();

                $userItem = $coupon->userItem;
                if ($userItem) {
                    $userItem->forceFill([
                        'is_used' => true,
                        'used_at' => now(),
                    ])->save();
                }
            }

            return $payment;
        });

        if ($finalAmount <= 0) {
            $completedPayment = $this->paymentSettlementService->approve(
                $payment,
                null,
                'Curso liberado automáticamente por cupón.',
                null,
                'coupon_auto'
            );

            return response()->json([
                'transaction_id' => $transactionId,
                'qr_code' => null,
                'amount' => $finalAmount,
                'original_amount' => $originalAmount,
                'discount_amount' => $discountAmount,
                'discount_percent' => $discountPercent,
                'status' => 'completed',
                'payment' => $completedPayment,
                'coupon' => $coupon ? [
                    'id' => $coupon->id,
                    'code' => $coupon->code,
                ] : null,
            ]);
        }

        $qrImage = QrCode::format('svg')->size(300)->generate($paymentUrl);
        $qrBase64 = 'data:image/svg+xml;base64,'.base64_encode($qrImage);

        return response()->json([
            'transaction_id' => $transactionId,
            'qr_code' => $qrBase64,
            'amount' => $finalAmount,
            'original_amount' => $originalAmount,
            'discount_amount' => $discountAmount,
            'discount_percent' => $discountPercent,
            'status' => 'pending',
            'payment' => $payment,
            'coupon' => $coupon ? [
                'id' => $coupon->id,
                'code' => $coupon->code,
            ] : null,
        ]);
    }

    /**
     * Endpoint para consultar el estado del pago (Polling desde UI).
     */
    public function checkStatus($transactionId)
    {
        $payment = Payment::where('transaction_id', $transactionId)->firstOrFail();

        return response()->json(['status' => $payment->status]);
    }

    /**
     * Endpoint Webhook / Mock que sella el pago y genera la inscripción.
     * En producción real, este endpoint recibiría el post del banco/billetera.
     */
    public function confirmMockPayment(Request $request)
    {
        $request->validate([
            'transaction_id' => 'required|exists:payments,transaction_id',
        ]);

        $payment = Payment::where('transaction_id', $request->transaction_id)->firstOrFail();

        if ($payment->status === 'completed') {
            return response()->json(['message' => 'Payment already completed.'], 400);
        }

        try {
            $payment = $this->paymentSettlementService->approve(
                $payment,
                null,
                'Pago confirmado por webhook QR.',
                null,
                'webhook_qr'
            );

            return response()->json(['message' => 'Payment successful', 'payment' => $payment]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Payment confirmation failed.'], 500);
        }
    }
}
