<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payout;
use App\Models\Payment;
use App\Services\PaymentSettlementService;
use Illuminate\Http\Request;

class AdminFinanceController extends Controller
{
    public function __construct(
        private readonly PaymentSettlementService $paymentSettlementService
    ) {
    }

    public function payments(Request $request)
    {
        $query = Payment::query()
            ->with([
                'user:id,name,email',
                'reviewer:id,name,email',
                'course:id,title,instructor_id',
                'course.instructor:id,name,email',
            ]);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('transaction_id', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($userQuery) => $userQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"))
                    ->orWhereHas('course', fn ($courseQuery) => $courseQuery->where('title', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->string('payment_method'));
        }

        $payments = $query->latest()->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => $payments->items(),
            'meta' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
            ],
            'summary' => [
                'pending_count' => Payment::query()->where('status', 'pending')->count(),
                'completed_this_month' => (float) Payment::query()
                    ->where('status', 'completed')
                    ->whereBetween('reviewed_at', [now()->startOfMonth(), now()->endOfMonth()])
                    ->sum('amount'),
                'platform_revenue_this_month' => (float) Payment::query()
                    ->where('status', 'completed')
                    ->whereBetween('reviewed_at', [now()->startOfMonth(), now()->endOfMonth()])
                    ->sum('platform_fee_amount'),
                'instructor_revenue_this_month' => (float) Payment::query()
                    ->where('status', 'completed')
                    ->whereBetween('reviewed_at', [now()->startOfMonth(), now()->endOfMonth()])
                    ->sum('instructor_amount'),
            ],
        ]);
    }

    public function confirmPayment(Request $request, Payment $payment)
    {
        $validated = $request->validate([
            'review_notes' => 'nullable|string|max:2000',
            'receipt_file' => 'nullable|file|mimes:jpg,jpeg,png,pdf,webp|max:10240',
        ]);

        $receiptPath = $request->hasFile('receipt_file')
            ? $request->file('receipt_file')->store('payment-receipts', 'local')
            : null;

        $payment = $this->paymentSettlementService->approve(
            $payment,
            $request->user(),
            $validated['review_notes'] ?? 'Pago QR confirmado manualmente por administración.',
            $receiptPath,
            'admin_manual'
        );

        return response()->json([
            'message' => 'Pago confirmado y curso desbloqueado correctamente.',
            'payment' => $payment,
        ]);
    }

    public function rejectPayment(Request $request, Payment $payment)
    {
        $validated = $request->validate([
            'review_notes' => 'required|string|max:2000',
        ]);

        $payment = $this->paymentSettlementService->reject(
            $payment,
            $request->user(),
            $validated['review_notes']
        );

        return response()->json([
            'message' => 'Pago rechazado correctamente.',
            'payment' => $payment,
        ]);
    }

    public function payouts(Request $request)
    {
        $query = Payout::query()
            ->with(['instructor:id,name,email', 'approver:id,name,email']);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $payouts = $query
            ->orderByRaw("FIELD(status, 'pending', 'approved', 'rejected', 'paid')")
            ->latest('requested_at')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => $payouts->items(),
            'meta' => [
                'current_page' => $payouts->currentPage(),
                'last_page' => $payouts->lastPage(),
                'per_page' => $payouts->perPage(),
                'total' => $payouts->total(),
            ],
            'summary' => [
                'pending_amount' => (float) Payout::query()->where('status', 'pending')->sum('net_amount'),
                'paid_this_month' => (float) Payout::query()
                    ->where('status', 'paid')
                    ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
                    ->sum('net_amount'),
            ],
        ]);
    }

    public function updatePayout(Request $request, Payout $payout)
    {
        $validated = $request->validate([
            'status' => 'required|in:approved,rejected,paid',
            'admin_notes' => 'nullable|string|max:2000',
        ]);

        if ($validated['status'] === 'approved' && $payout->has_open_disputes) {
            return response()->json([
                'message' => 'No se puede aprobar un retiro mientras existan disputas abiertas.',
            ], 422);
        }

        $payout->forceFill([
            'status' => $validated['status'],
            'approved_by' => $request->user()->id,
            'admin_notes' => $validated['admin_notes'] ?? $payout->admin_notes,
            'reviewed_at' => now(),
            'paid_at' => $validated['status'] === 'paid' ? now() : $payout->paid_at,
        ])->save();

        \App\Models\AdminActivityLog::record($request->user(), 'payout.status_changed', $payout, [
            'status' => $validated['status'],
            'admin_notes' => $validated['admin_notes'] ?? null,
        ]);

        return response()->json([
            'message' => 'Estado del retiro actualizado correctamente.',
            'payout' => $payout->fresh(['instructor:id,name,email', 'approver:id,name,email']),
        ]);
    }
}
