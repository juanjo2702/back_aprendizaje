<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Payment;
use App\Services\PaymentSettlementService;
use Illuminate\Http\Request;
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

        $transactionId = 'TXN-'.strtoupper(Str::random(12));

        // URL del webhook de pago (mock) al que apuntará el QR
        $paymentUrl = env('APP_URL')."/api/payments/webhook?transaction_id={$transactionId}";

        // Generar QR en base64 SVG
        $qrImage = QrCode::format('svg')->size(300)->generate($paymentUrl);
        $qrBase64 = 'data:image/svg+xml;base64,'.base64_encode($qrImage);

        // Si el precio es 0, no generamos QR, creamos inscripción directo (o redirigimos).
        // Para este escenario asumimos cursos de pago.

        $split = $this->paymentSettlementService->splitAmounts((float) $course->price);

        $payment = Payment::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'amount' => $course->price,
            'status' => 'pending',
            'payment_method' => 'qr_manual',
            'provider' => 'bolivia_qr',
            'qr_data' => $paymentUrl,
            'transaction_id' => $transactionId,
            'platform_fee_amount' => $split['platform_fee_amount'],
            'instructor_amount' => $split['instructor_amount'],
        ]);

        return response()->json([
            'transaction_id' => $transactionId,
            'qr_code' => $qrBase64,
            'amount' => $course->price,
            'status' => 'pending',
            'payment' => $payment,
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
