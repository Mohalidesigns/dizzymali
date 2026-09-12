<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Actions\Payments\InitialisePayment;
use App\Actions\Payments\SettlePayment;
use App\Domain\Payments\GatewayManager;
use App\Domain\Payments\PaymentFailed;
use App\Http\Controllers\Controller;
use App\Http\Resources\MoneyResource;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly GatewayManager $gateways,
        private readonly InitialisePayment $initialise,
        private readonly SettlePayment $settle,
    ) {}

    public function show(Order $order): Response
    {
        $this->authorize('pay', $order);

        $order->load(['items.garmentType', 'items.fabricVariant.fabric']);

        $currency = strtoupper((string) $order->currency_code);
        $gateway = $this->gateways->forCurrency($currency);
        $total = $order->total();
        $depositPercent = (int) Setting::get('pricing.deposit_percent', 60);

        return Inertia::render('storefront/Checkout', [
            'order' => new OrderResource($order),

            // Shown plainly rather than hidden: if no gateway can take this
            // currency the customer needs to know that now, not after typing
            // a card number.
            'gateway' => $gateway === null ? null : [
                'name' => $gateway->name(),
                'label' => ucfirst($gateway->name()),
            ],

            'deposit' => (bool) Setting::get('pricing.allow_deposit', false) ? [
                'percent' => $depositPercent,
                'amount' => MoneyResource::make($total->percentageBasisPoints($depositPercent * 100)->minor),
                'balance' => MoneyResource::make(
                    $total->minus($total->percentageBasisPoints($depositPercent * 100))->minor,
                ),
            ] : null,

            'bankTransfer' => config('payments.bank_transfer.enabled') ? [
                'account_name' => config('payments.bank_transfer.account_name'),
                'account_number' => config('payments.bank_transfer.account_number'),
                'bank_name' => config('payments.bank_transfer.bank_name'),
            ] : null,

            'balanceDue' => MoneyResource::make($order->balanceDue()->minor),
        ]);
    }

    public function pay(Request $request, Order $order): RedirectResponse
    {
        $this->authorize('pay', $order);

        $validated = $request->validate([
            'kind' => ['sometimes', Rule::in(['full', 'deposit'])],
            'gateway' => ['sometimes', Rule::in(['paystack', 'flutterwave', 'manual'])],
        ]);

        try {
            $payment = $this->initialise->handle(
                $order,
                $validated['kind'] ?? 'full',
                $validated['gateway'] ?? null,
            );
        } catch (PaymentFailed $e) {
            return back()->withErrors(['payment' => $e->getMessage()]);
        }

        $payload = $payment->gateway_payload;
        $redirect = is_array($payload) && filled($payload['redirect_url'] ?? null)
            ? (string) $payload['redirect_url']
            : route('orders.show', $order);

        if ($payment->gateway === 'manual') {
            return redirect()
                ->route('orders.show', $order)
                ->with('success', 'Transfer details are on your order page. We will confirm as soon as it lands.');
        }

        // Leaving our domain for the gateway's hosted checkout. No card data
        // ever reaches this server.
        return redirect()->away($redirect);
    }

    /**
     * Where the gateway sends the customer back to.
     *
     * This is a hint, not a source of truth — anyone can visit it. The outcome
     * comes from re-verifying against the gateway's API.
     */
    public function return(Request $request, Payment $payment): RedirectResponse
    {
        $this->authorize('view', $payment);

        $order = $payment->order;

        if ($payment->gateway === 'manual' || $payment->gateway_reference === null) {
            return redirect()->route('orders.show', $order);
        }

        $gateway = $this->gateways->get($payment->gateway);
        $settled = $this->settle->handle($payment, $gateway->verify($payment->gateway_reference));

        return redirect()
            ->route('orders.show', $order)
            ->with(
                $settled ? 'success' : 'error',
                $settled
                    ? 'Payment received. We will confirm your measurements before cutting.'
                    : 'That payment did not go through. Nothing has been charged — you can try again.',
            );
    }
}
