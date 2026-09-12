<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Domain\Orders\OrderStateMachine;
use App\Domain\Payments\ChargeIntent;
use App\Domain\Payments\ChargeSession;
use App\Domain\Payments\GatewayManager;
use App\Domain\Payments\PaymentFailed;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Setting;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Starts a payment attempt.
 *
 * The amount is taken from the order, which the server calculated. Whatever the
 * browser thinks the total is has no bearing on what we ask the gateway for.
 */
class InitialisePayment
{
    public function __construct(
        private readonly GatewayManager $gateways,
        private readonly OrderStateMachine $states,
    ) {}

    public function handle(Order $order, string $kind = 'full', ?string $preferredGateway = null): Payment
    {
        $order->loadMissing('user');

        $amount = $this->amountFor($order, $kind);

        if ($amount->isZero()) {
            throw new PaymentFailed('There is nothing left to pay on this order.');
        }

        $currency = strtoupper((string) $order->currency_code);
        $gateway = $this->gateways->forCurrency($currency, $preferredGateway);

        if ($gateway === null) {
            throw new PaymentFailed(
                "No configured payment gateway can charge in {$currency} at the moment. "
                .'Please choose another currency, or pay by bank transfer.',
            );
        }

        $displayMinor = $this->displayMinorFor($order, $amount, $currency);

        return DB::transaction(function () use ($order, $kind, $amount, $currency, $displayMinor, $gateway) {
            // One idempotency key per attempt. A customer double-clicking "Pay"
            // gets two rows, not one row charged twice — and a webhook replay
            // matches an existing row instead of creating a second.
            $idempotencyKey = (string) Str::uuid();

            $payment = $order->payments()->create([
                'gateway' => $gateway->name(),
                'idempotency_key' => $idempotencyKey,
                'status' => 'pending',
                'kind' => $kind,
                'amount_kobo' => $amount->minor,
                'currency_code' => $currency,
                'charged_minor' => $displayMinor,
                'fx_rate_used' => $order->fx_rate_used,
            ]);

            $session = $this->startSession($gateway->name(), $order, $payment, $amount, $currency, $displayMinor);

            $payment->forceFill([
                'gateway_reference' => $session->gatewayReference,
                'gateway_payload' => ['initialise' => $session->raw, 'redirect_url' => $session->redirectUrl],
            ])->save();

            if ($this->states->canTransition($order, OrderStatus::PaymentPending)) {
                $this->states->transition(
                    $order,
                    OrderStatus::PaymentPending,
                    $order->user,
                    'Checkout started.',
                    customerVisible: false,
                );
            }

            return $payment->refresh();
        });
    }

    private function startSession(
        string $gatewayName,
        Order $order,
        Payment $payment,
        Money $amount,
        string $currency,
        int $displayMinor,
    ): ChargeSession {
        $user = $order->user;

        return $this->gateways->get($gatewayName)->initialise(new ChargeIntent(
            reference: (string) $order->reference,
            amount: $amount,
            displayCurrency: $currency,
            displayAmountMinor: $displayMinor,
            customerEmail: $user === null ? '' : (string) $user->email,
            customerName: $user === null ? '' : (string) $user->name,
            customerPhone: $user?->phone,
            callbackUrl: route('payments.return', $payment),
            metadata: ['payment_id' => $payment->id, 'kind' => $payment->kind],
        ));
    }

    /**
     * A deposit is a percentage of the total; a balance is whatever is left.
     * Both are computed in integer kobo from the order, never from the client.
     */
    private function amountFor(Order $order, string $kind): Money
    {
        $total = $order->total();
        $paid = Money::ofMinor((int) $order->amount_paid_kobo);
        $outstanding = $total->minus($paid)->atLeastZero();

        if ($kind !== 'deposit') {
            return $outstanding;
        }

        if (! (bool) Setting::get('pricing.allow_deposit', false)) {
            return $outstanding;
        }

        $percent = (int) ($order->deposit_percent ?? Setting::get('pricing.deposit_percent', 60));
        $deposit = $total->percentageBasisPoints($percent * 100);

        // Never ask for more than is actually owed.
        return $deposit->greaterThan($outstanding) ? $outstanding : $deposit;
    }

    /**
     * What the customer is charged, in their own currency.
     *
     * The FX rate was frozen onto the order at submission, so this figure
     * cannot drift between the quote they accepted and the card being charged.
     */
    private function displayMinorFor(Order $order, Money $amount, string $currency): int
    {
        if ($currency === 'NGN' || $order->fx_rate_used === null) {
            return $amount->minor;
        }

        $rate1e8 = (int) round(((float) $order->fx_rate_used) * 100_000_000);
        $marginBp = (int) round(((float) ($order->fx_margin_percent ?? 0)) * 100);

        return $amount
            ->convertTo($currency, $rate1e8)
            ->plusBasisPoints($marginBp)
            ->roundUpToNearest(100)
            ->minor;
    }
}
