<?php

declare(strict_types=1);

use App\Actions\Payments\HandleGatewayWebhook;
use App\Actions\Payments\InitialisePayment;
use App\Actions\Payments\SettlePayment;
use App\Domain\Payments\VerificationResult;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\CommerceSettingsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed([RoleSeeder::class, CommerceSettingsSeeder::class]);

    config()->set('payments.gateways.paystack.secret_key', 'sk_test_secret');
    config()->set('payments.gateways.paystack.public_key', 'pk_test');

    $this->customer = User::factory()->create(['email_verified_at' => now()]);
    $this->customer->assignRole('customer');

    $this->order = Order::factory()->for($this->customer)->create([
        'status' => OrderStatus::QuoteAccepted,
        'total_kobo' => 151_750_00,
        'currency_code' => 'NGN',
    ]);
});

it('asks the gateway for the amount the server calculated', function () {
    Http::fake(['api.paystack.co/*' => Http::response([
        'status' => true,
        'data' => ['reference' => 'ref_1', 'authorization_url' => 'https://checkout.paystack.com/ref_1'],
    ])]);

    $payment = app(InitialisePayment::class)->handle($this->order);

    expect((int) $payment->amount_kobo)->toBe(151_750_00)
        ->and($payment->status)->toBe('pending')
        ->and($payment->gateway)->toBe('paystack');

    Http::assertSent(fn ($request) => $request['amount'] === 151_750_00);
});

it('moves the order to awaiting payment when checkout starts', function () {
    Http::fake(['api.paystack.co/*' => Http::response([
        'status' => true, 'data' => ['reference' => 'ref_1', 'authorization_url' => 'https://x'],
    ])]);

    app(InitialisePayment::class)->handle($this->order);

    expect($this->order->fresh()->status)->toBe(OrderStatus::PaymentPending);
});

it('gives every attempt a distinct idempotency key', function () {
    Http::fake(['api.paystack.co/*' => Http::response([
        'status' => true, 'data' => ['reference' => 'ref', 'authorization_url' => 'https://x'],
    ])]);

    $first = app(InitialisePayment::class)->handle($this->order);
    $second = app(InitialisePayment::class)->handle($this->order->fresh());

    expect($first->idempotency_key)->not->toBe($second->idempotency_key);
});

it('charges a deposit percentage of the total when the customer chooses one', function () {
    Setting::put('pricing.allow_deposit', true);
    Setting::put('pricing.deposit_percent', 60);

    Http::fake(['api.paystack.co/*' => Http::response([
        'status' => true, 'data' => ['reference' => 'ref', 'authorization_url' => 'https://x'],
    ])]);

    $payment = app(InitialisePayment::class)->handle($this->order, 'deposit');

    // 60% of ₦151,750 is ₦91,050, computed in integer kobo.
    expect((int) $payment->amount_kobo)->toBe(91_050_00);
});

it('never asks for more than is still owed', function () {
    Setting::put('pricing.allow_deposit', true);
    Setting::put('pricing.deposit_percent', 60);

    $this->order->forceFill(['amount_paid_kobo' => 140_000_00])->save();

    Http::fake(['api.paystack.co/*' => Http::response([
        'status' => true, 'data' => ['reference' => 'ref', 'authorization_url' => 'https://x'],
    ])]);

    $payment = app(InitialisePayment::class)->handle($this->order, 'deposit');

    expect((int) $payment->amount_kobo)->toBe(11_750_00);
});

/*
|--------------------------------------------------------------------------
| Settlement
|--------------------------------------------------------------------------
*/

it('marks the order paid when the amount checks out', function () {
    $payment = makePaymentFor($this->order, 151_750_00);

    $settled = app(SettlePayment::class)->handle($payment, new VerificationResult(
        successful: true,
        gatewayReference: 'ref_1',
        amountMinor: 151_750_00,
        currency: 'NGN',
    ));

    expect($settled)->toBeTrue()
        ->and($payment->fresh()->status)->toBe('success')
        ->and($this->order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and((int) $this->order->fresh()->amount_paid_kobo)->toBe(151_750_00);
});

it('refuses to settle when the gateway reports a different amount', function () {
    $payment = makePaymentFor($this->order, 151_750_00);

    // A forged or replayed webhook claiming ₦1 was paid.
    $settled = app(SettlePayment::class)->handle($payment, new VerificationResult(
        successful: true,
        gatewayReference: 'ref_1',
        amountMinor: 100,
        currency: 'NGN',
    ));

    expect($settled)->toBeFalse()
        ->and($payment->fresh()->status)->toBe('failed')
        ->and($this->order->fresh()->status)->not->toBe(OrderStatus::Paid)
        ->and((int) $this->order->fresh()->amount_paid_kobo)->toBe(0);
});

it('refuses to settle when the currency does not match', function () {
    $payment = makePaymentFor($this->order, 151_750_00);

    $settled = app(SettlePayment::class)->handle($payment, new VerificationResult(
        successful: true,
        gatewayReference: 'ref_1',
        amountMinor: 151_750_00,
        currency: 'GBP',
    ));

    expect($settled)->toBeFalse();
});

it('refuses to settle an amount larger than we asked for', function () {
    $payment = makePaymentFor($this->order, 151_750_00);

    $settled = app(SettlePayment::class)->handle($payment, new VerificationResult(
        successful: true,
        gatewayReference: 'ref_1',
        amountMinor: 999_999_00,
        currency: 'NGN',
    ));

    expect($settled)->toBeFalse();
});

it('is idempotent — settling twice does not double the amount paid', function () {
    $payment = makePaymentFor($this->order, 151_750_00);

    $result = new VerificationResult(true, 'ref_1', 151_750_00, 'NGN');

    app(SettlePayment::class)->handle($payment, $result);
    app(SettlePayment::class)->handle($payment->fresh(), $result);

    expect((int) $this->order->fresh()->amount_paid_kobo)->toBe(151_750_00)
        ->and($this->order->fresh()->payments()->where('status', 'success')->count())->toBe(1);
});

it('leaves the order unpaid when a deposit only part-settles it', function () {
    $payment = makePaymentFor($this->order, 91_050_00, 'deposit');

    app(SettlePayment::class)->handle($payment, new VerificationResult(true, 'ref_1', 91_050_00, 'NGN'));

    $order = $this->order->fresh();

    expect((int) $order->amount_paid_kobo)->toBe(91_050_00)
        ->and($order->isFullyPaid())->toBeFalse()
        ->and($order->status)->not->toBe(OrderStatus::Paid)
        ->and($order->balanceDue()->minor)->toBe(60_700_00);
});

it('records a bank transfer through the same settlement path', function () {
    $staff = User::factory()->create(['email_verified_at' => now()]);
    $staff->assignRole('staff');

    app(SettlePayment::class)->manual($this->order, 151_750_00, $staff, 'TRF-9921');

    $order = $this->order->fresh();

    expect($order->status)->toBe(OrderStatus::Paid)
        ->and($order->payments()->where('gateway', 'manual')->where('status', 'success')->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Webhooks over HTTP
|--------------------------------------------------------------------------
*/

it('accepts a correctly signed webhook and settles the payment', function () {
    $payment = makePaymentFor($this->order, 151_750_00, reference: 'ref_hook');

    Http::fake(['api.paystack.co/transaction/verify/*' => Http::response([
        'status' => true,
        'data' => ['status' => 'success', 'reference' => 'ref_hook', 'amount' => 151_750_00, 'currency' => 'NGN'],
    ])]);

    $payload = json_encode([
        'event' => 'charge.success',
        'data' => ['reference' => 'ref_hook', 'amount' => 151_750_00, 'currency' => 'NGN'],
    ], JSON_THROW_ON_ERROR);

    $this->call('POST', '/webhooks/paystack', [], [], [], [
        'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $payload, 'sk_test_secret'),
        'CONTENT_TYPE' => 'application/json',
    ], $payload)->assertOk();

    expect($payment->fresh()->status)->toBe('success')
        ->and($this->order->fresh()->status)->toBe(OrderStatus::Paid);
});

it('turns away a webhook with a forged signature', function () {
    $payment = makePaymentFor($this->order, 151_750_00, reference: 'ref_hook');

    $payload = json_encode([
        'event' => 'charge.success',
        'data' => ['reference' => 'ref_hook', 'amount' => 151_750_00, 'currency' => 'NGN'],
    ], JSON_THROW_ON_ERROR);

    $this->call('POST', '/webhooks/paystack', [], [], [], [
        'HTTP_X_PAYSTACK_SIGNATURE' => 'definitely-not-right',
        'CONTENT_TYPE' => 'application/json',
    ], $payload)->assertStatus(401);

    expect($payment->fresh()->status)->toBe('pending')
        ->and($this->order->fresh()->status)->not->toBe(OrderStatus::Paid);
});

it('does not need a CSRF token, because a gateway has no session', function () {
    $this->call('POST', '/webhooks/paystack', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
    ], '{}')->assertStatus(401); // rejected on signature, not on CSRF

    expect(true)->toBeTrue();
});

it('ignores a webhook for a payment we have never heard of', function () {
    $payload = json_encode([
        'event' => 'charge.success',
        'data' => ['reference' => 'someone-elses-reference', 'amount' => 1, 'currency' => 'NGN'],
    ], JSON_THROW_ON_ERROR);

    $accepted = app(HandleGatewayWebhook::class)->handle(
        'paystack',
        $payload,
        ['x-paystack-signature' => hash_hmac('sha512', $payload, 'sk_test_secret')],
    );

    // Accepted so the gateway stops retrying, but nothing was settled.
    expect($accepted)->toBeTrue()
        ->and($this->order->fresh()->status)->not->toBe(OrderStatus::Paid);
});

/*
|--------------------------------------------------------------------------
| Authorisation
|--------------------------------------------------------------------------
*/

it('does not let one customer pay for another customer\'s order', function () {
    $mallory = User::factory()->create(['email_verified_at' => now()]);
    $mallory->assignRole('customer');

    $this->actingAs($mallory)->get("/checkout/{$this->order->id}")->assertForbidden();
    $this->actingAs($mallory)->post("/checkout/{$this->order->id}/pay")->assertForbidden();
});

it('keeps customers out of the payments admin', function () {
    $this->actingAs($this->customer)->get('/admin/payments')->assertForbidden();
});

/** Build a pending payment row against the order under test. */
function makePaymentFor(Order $order, int $amountKobo, string $kind = 'full', string $reference = 'ref_1'): Payment
{
    return $order->payments()->create([
        'gateway' => 'paystack',
        'gateway_reference' => $reference,
        'idempotency_key' => 'test-'.uniqid('', true),
        'status' => 'pending',
        'kind' => $kind,
        'amount_kobo' => $amountKobo,
        'currency_code' => 'NGN',
        'charged_minor' => $amountKobo,
    ]);
}
