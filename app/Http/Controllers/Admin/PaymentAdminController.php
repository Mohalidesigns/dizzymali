<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Payments\SettlePayment;
use App\Domain\Payments\GatewayManager;
use App\Http\Controllers\Controller;
use App\Http\Resources\MoneyResource;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentAdminController extends Controller
{
    public function __construct(
        private readonly GatewayManager $gateways,
        private readonly SettlePayment $settle,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Payment::class);

        $query = Payment::query()->with(['order:id,reference,user_id,total_kobo', 'order.user:id,name,email']);

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($gateway = $request->string('gateway')->toString()) {
            $query->where('gateway', $gateway);
        }

        if ($search = $request->string('search')->toString()) {
            $query->where(fn ($q) => $q
                ->where('gateway_reference', 'like', "%{$search}%")
                ->orWhereHas('order', fn ($o) => $o->where('reference', 'like', "%{$search}%")));
        }

        return Inertia::render('admin/Payments', [
            'payments' => $query->latest()->paginate(30)->withQueryString()
                ->through(fn (Payment $p): array => $this->row($p)),

            // Being unconfigured is a normal state to surface, not hide.
            'gatewayStatus' => $this->gateways->status(),
            'filters' => $request->only(['status', 'gateway', 'search']),
        ]);
    }

    /** @return array<string,mixed> */
    private function row(Payment $payment): array
    {
        $order = $payment->order;

        return [
            'id' => $payment->id,
            'order_id' => $payment->order_id,
            'order_reference' => $order->reference,
            'customer' => $order->user?->name,
            'gateway' => $payment->gateway,
            'gateway_reference' => $payment->gateway_reference,
            'status' => $payment->status,
            'kind' => $payment->kind,
            'amount' => MoneyResource::make((int) $payment->amount_kobo),
            'charged' => $payment->charged_minor === null
                ? null
                : MoneyResource::make((int) $payment->charged_minor, (string) $payment->currency_code),
            'paid_at' => $payment->paid_at?->toIso8601String(),
            'created_at' => $payment->created_at?->toIso8601String(),
        ];
    }

    /** Record a bank transfer that has landed. */
    public function recordManual(Request $request, Order $order): RedirectResponse
    {
        $this->authorize('manage', $order);

        $validated = $request->validate([
            // Typed in whole naira by a human, stored as kobo from here on.
            'amount_naira' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'reference' => ['sometimes', 'nullable', 'string', 'max:120'],
        ]);

        $this->settle->manual(
            $order,
            $validated['amount_naira'] * 100,
            $request->user(),
            $validated['reference'] ?? null,
        );

        return back()->with('success', 'Payment recorded.');
    }

    public function refund(Request $request, Payment $payment): RedirectResponse
    {
        $this->authorize('refund', $payment);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        // Deliberately a record, not an API call. Refunds are initiated in the
        // gateway dashboard by a person; what belongs here is the audit trail
        // and the effect on the order's balance.
        $payment->forceFill([
            'status' => 'refunded',
            'gateway_payload' => array_merge((array) $payment->gateway_payload, [
                'refund' => [
                    'reason' => $validated['reason'],
                    'recorded_by' => $request->user()->id,
                    'recorded_at' => now()->toIso8601String(),
                ],
            ]),
        ])->save();

        $order = $payment->order;
        $settled = (int) $order->payments()->where('status', 'success')->sum('amount_kobo');
        $order->forceFill(['amount_paid_kobo' => $settled])->save();

        return back()->with('success', 'Refund recorded against this order.');
    }
}
