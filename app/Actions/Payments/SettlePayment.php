<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Domain\Orders\OrderStateMachine;
use App\Domain\Payments\VerificationResult;
use App\Enums\OrderStatus;
use App\Events\OrderPaid;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Marks a payment settled, after checking that what the gateway collected is
 * what we asked for.
 *
 * Three things have to hold before a naira reaches the order:
 *   1. The gateway says the transaction succeeded.
 *   2. The amount matches the payment row, in the same currency.
 *   3. That payment row has not already been settled.
 *
 * Rule 2 is the one that matters. A webhook is an unauthenticated HTTP request
 * from the internet; signature verification proves it came from the gateway,
 * and this check proves it says what we expected it to say.
 */
class SettlePayment
{
    public function __construct(private readonly OrderStateMachine $states) {}

    public function handle(Payment $payment, VerificationResult $result, ?User $actor = null): bool
    {
        // Idempotent by design: webhooks retry, and a customer often lands on
        // the return URL at the same moment the webhook arrives.
        if ($payment->isSuccessful()) {
            return true;
        }

        if (! $result->successful) {
            $payment->forceFill([
                'status' => 'failed',
                'gateway_payload' => array_merge((array) $payment->gateway_payload, ['verify' => $result->raw]),
            ])->save();

            return false;
        }

        if (! $this->amountMatches($payment, $result)) {
            Log::warning('Payment amount mismatch — refusing to settle.', [
                'payment_id' => $payment->id,
                'expected_minor' => $payment->charged_minor ?? $payment->amount_kobo,
                'expected_currency' => $payment->currency_code,
                'reported_minor' => $result->amountMinor,
                'reported_currency' => $result->currency,
            ]);

            $payment->forceFill([
                'status' => 'failed',
                'gateway_payload' => array_merge((array) $payment->gateway_payload, [
                    'verify' => $result->raw,
                    'rejected_reason' => 'amount_mismatch',
                ]),
            ])->save();

            return false;
        }

        DB::transaction(function () use ($payment, $result, $actor) {
            $payment->forceFill([
                'status' => 'success',
                'gateway_reference' => $result->gatewayReference,
                'paid_at' => now(),
                'gateway_payload' => array_merge((array) $payment->gateway_payload, ['verify' => $result->raw]),
            ])->save();

            /** @var Order $order */
            $order = $payment->order()->lockForUpdate()->firstOrFail();

            $settled = (int) $order->payments()->where('status', 'success')->sum('amount_kobo');

            $order->forceFill(['amount_paid_kobo' => $settled])->save();

            if ($order->isFullyPaid()) {
                $this->markPaid($order, $actor);
            }

            OrderPaid::dispatch($order->refresh(), $payment);
        });

        return true;
    }

    /**
     * Record a bank transfer a staff member has confirmed landed.
     *
     * Deliberately routed through the same settlement path so it produces the
     * same audit trail, the same state transition and the same notifications as
     * a card payment.
     */
    public function manual(Order $order, int $amountKobo, User $actor, ?string $reference = null): Payment
    {
        $payment = $order->payments()->create([
            'gateway' => 'manual',
            'gateway_reference' => $reference,
            'idempotency_key' => 'manual-'.$order->id.'-'.now()->getTimestampMs(),
            'status' => 'pending',
            'kind' => 'full',
            'amount_kobo' => $amountKobo,
            'currency_code' => 'NGN',
            'charged_minor' => $amountKobo,
        ]);

        $this->handle($payment, new VerificationResult(
            successful: true,
            gatewayReference: (string) ($reference ?? $payment->idempotency_key),
            amountMinor: $amountKobo,
            currency: 'NGN',
            paidAt: now()->toIso8601String(),
            raw: ['recorded_by' => $actor->id, 'method' => 'bank_transfer'],
        ), $actor);

        return $payment->refresh();
    }

    /**
     * Move the order to paid, stepping through payment_pending if it has not
     * been there.
     *
     * Card checkout always passes through payment_pending on its way. A bank
     * transfer recorded by staff against an accepted quote has not, and
     * quote_accepted -> paid is not a legal jump — so we walk the one step
     * rather than either weakening the state machine or leaving the order
     * stuck with the money in the bank.
     */
    private function markPaid(Order $order, ?User $actor): void
    {
        if (! $order->status->canTransitionTo(OrderStatus::Paid)
            && $order->status->canTransitionTo(OrderStatus::PaymentPending)) {
            $order = $this->states->transition(
                $order,
                OrderStatus::PaymentPending,
                $actor,
                'Payment being recorded.',
                customerVisible: false,
            );
        }

        if ($this->states->canTransition($order, OrderStatus::Paid)) {
            $this->states->transition($order, OrderStatus::Paid, $actor, 'Payment received in full.');
        }
    }

    private function amountMatches(Payment $payment, VerificationResult $result): bool
    {
        $expectedCurrency = strtoupper((string) $payment->currency_code);

        if (strtoupper($result->currency) !== $expectedCurrency) {
            return false;
        }

        $expected = (int) ($payment->charged_minor ?? $payment->amount_kobo);

        // Exact match. Not "close enough", and not "at least" — a gateway
        // reporting more than we asked for is as wrong as one reporting less.
        return $result->amountMinor === $expected;
    }
}
