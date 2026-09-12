<?php

declare(strict_types=1);

use App\Domain\Payments\ChargeIntent;
use App\Domain\Payments\GatewayManager;
use App\Domain\Payments\GatewayNotConfigured;
use App\Domain\Payments\Gateways\FlutterwaveGateway;
use App\Domain\Payments\Gateways\ManualGateway;
use App\Domain\Payments\Gateways\PaystackGateway;
use App\Support\Money;
use Illuminate\Support\Facades\Http;

function intent(string $currency = 'NGN', int $minor = 151_750_00): ChargeIntent
{
    return new ChargeIntent(
        reference: 'DZM-2609-A7K3',
        amount: Money::ofMinor(151_750_00),
        displayCurrency: $currency,
        displayAmountMinor: $minor,
        customerEmail: 'ibrahim@example.test',
        customerName: 'Ibrahim Sule',
        customerPhone: '+2348012345678',
        callbackUrl: 'https://dizzymali.test/payments/1/return',
    );
}

/*
|--------------------------------------------------------------------------
| Unconfigured gateways
|--------------------------------------------------------------------------
| Flutterwave has no credentials yet. It must behave as absent, not as broken,
| and must never silently appear to succeed.
*/

it('reports an unconfigured gateway as unconfigured', function () {
    $flutterwave = new FlutterwaveGateway(null, null, null);

    expect($flutterwave->isConfigured())->toBeFalse();
});

it('throws loudly rather than pretending to work without credentials', function () {
    (new FlutterwaveGateway(null, null, null))->initialise(intent());
})->throws(GatewayNotConfigured::class);

it('refuses to verify a webhook when it has no credentials', function () {
    $flutterwave = new FlutterwaveGateway(null, null, null);

    expect($flutterwave->parseWebhook('{"event":"charge.completed"}', ['verif-hash' => 'anything']))
        ->toBeNull();
});

it('becomes usable the moment credentials are supplied', function () {
    $configured = new FlutterwaveGateway('sk_test', 'pk_test', 'hash_test');

    expect($configured->isConfigured())->toBeTrue()
        ->and($configured->supports('GBP'))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Webhook signatures
|--------------------------------------------------------------------------
| A webhook is an unauthenticated request from the open internet. The
| signature check is the only thing between it and an order marked paid.
*/

it('accepts a Paystack webhook with a valid signature', function () {
    $secret = 'sk_test_secret';
    $payload = json_encode(['event' => 'charge.success', 'data' => [
        'reference' => 'dzm-2609-a7k3-abcd1234', 'amount' => 15_175_000, 'currency' => 'NGN',
    ]], JSON_THROW_ON_ERROR);

    $gateway = new PaystackGateway($secret, 'pk_test');

    $event = $gateway->parseWebhook($payload, [
        'x-paystack-signature' => hash_hmac('sha512', $payload, $secret),
    ]);

    expect($event)->not->toBeNull()
        ->and($event->isSuccessfulCharge())->toBeTrue()
        ->and($event->gatewayReference)->toBe('dzm-2609-a7k3-abcd1234')
        ->and($event->amountMinor)->toBe(15_175_000);
});

it('rejects a Paystack webhook whose signature does not match', function () {
    $payload = '{"event":"charge.success","data":{"reference":"forged","amount":1}}';

    $gateway = new PaystackGateway('sk_test_secret', 'pk_test');

    expect($gateway->parseWebhook($payload, ['x-paystack-signature' => 'not-the-signature']))
        ->toBeNull();
});

it('rejects a Paystack webhook with no signature at all', function () {
    $gateway = new PaystackGateway('sk_test_secret', 'pk_test');

    expect($gateway->parseWebhook('{"event":"charge.success"}', []))->toBeNull();
});

it('rejects a signature computed over a different body', function () {
    $secret = 'sk_test_secret';
    $honest = '{"event":"charge.success","data":{"amount":100}}';
    $tampered = '{"event":"charge.success","data":{"amount":99999999}}';

    $gateway = new PaystackGateway($secret, 'pk_test');

    expect($gateway->parseWebhook($tampered, [
        'x-paystack-signature' => hash_hmac('sha512', $honest, $secret),
    ]))->toBeNull();
});

it('accepts a Flutterwave webhook with the right shared secret', function () {
    $gateway = new FlutterwaveGateway('sk', 'pk', 'the-secret-hash');

    $event = $gateway->parseWebhook(
        json_encode(['event' => 'charge.completed', 'data' => [
            'tx_ref' => 'dzm-2609-a7k3-abcd1234', 'amount' => '159.87', 'currency' => 'GBP',
        ]], JSON_THROW_ON_ERROR),
        ['verif-hash' => 'the-secret-hash'],
    );

    expect($event)->not->toBeNull()
        ->and($event->isSuccessfulCharge())->toBeTrue()
        // "159.87" must arrive as 15987 minor units, with no float in between.
        ->and($event->amountMinor)->toBe(15_987);
});

it('rejects a Flutterwave webhook with the wrong secret', function () {
    $gateway = new FlutterwaveGateway('sk', 'pk', 'the-secret-hash');

    expect($gateway->parseWebhook('{"event":"charge.completed"}', ['verif-hash' => 'guessed']))
        ->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Choosing a gateway
|--------------------------------------------------------------------------
*/

it('skips an unconfigured gateway when choosing one', function () {
    $manager = new GatewayManager([
        'paystack' => new PaystackGateway(null, null),          // no keys
        'flutterwave' => new FlutterwaveGateway('sk', 'pk', 'h'), // configured
        'manual' => new ManualGateway,
    ]);

    expect($manager->forCurrency('NGN')?->name())->toBe('flutterwave');
});

it('returns null rather than a broken gateway when none can take the currency', function () {
    $manager = new GatewayManager([
        'paystack' => new PaystackGateway(null, null),
        'flutterwave' => new FlutterwaveGateway(null, null, null),
    ]);

    expect($manager->forCurrency('GBP'))->toBeNull();
});

it('prefers the primary gateway when both are configured', function () {
    $manager = new GatewayManager([
        'paystack' => new PaystackGateway('sk', 'pk'),
        'flutterwave' => new FlutterwaveGateway('sk', 'pk', 'h'),
    ]);

    expect($manager->forCurrency('NGN')?->name())->toBe('paystack');
});

it('falls back to Flutterwave for a currency Paystack will not take', function () {
    $manager = new GatewayManager([
        'paystack' => new PaystackGateway('sk', 'pk'),
        'flutterwave' => new FlutterwaveGateway('sk', 'pk', 'h'),
    ]);

    // Paystack does not settle GBP; Flutterwave does.
    expect($manager->forCurrency('GBP')?->name())->toBe('flutterwave');
});

it('reports gateway status for the admin, unconfigured included', function () {
    $manager = new GatewayManager([
        'paystack' => new PaystackGateway('sk', 'pk'),
        'flutterwave' => new FlutterwaveGateway(null, null, null),
    ]);

    expect($manager->status())->toBe([
        ['name' => 'paystack', 'configured' => true, 'is_primary' => true, 'is_fallback' => false],
        ['name' => 'flutterwave', 'configured' => false, 'is_primary' => false, 'is_fallback' => true],
    ]);
});

it('never settles a bank transfer automatically', function () {
    expect((new ManualGateway)->verify('anything')->successful)->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Talking to Paystack
|--------------------------------------------------------------------------
*/

it('sends the server-calculated amount to Paystack, in minor units', function () {
    Http::fake([
        'api.paystack.co/transaction/initialize' => Http::response([
            'status' => true,
            'data' => ['reference' => 'ref_123', 'authorization_url' => 'https://checkout.paystack.com/ref_123'],
        ]),
    ]);

    $session = (new PaystackGateway('sk_test', 'pk_test'))->initialise(intent('NGN', 151_750_00));

    expect($session->redirectUrl)->toBe('https://checkout.paystack.com/ref_123');

    Http::assertSent(function ($request) {
        return $request['amount'] === 151_750_00
            && $request['currency'] === 'NGN'
            && $request['metadata']['order_reference'] === 'DZM-2609-A7K3';
    });
});

it('reads a successful Paystack verification', function () {
    Http::fake([
        'api.paystack.co/transaction/verify/*' => Http::response([
            'status' => true,
            'data' => ['status' => 'success', 'reference' => 'ref_123', 'amount' => 151_750_00, 'currency' => 'NGN'],
        ]),
    ]);

    $result = (new PaystackGateway('sk_test', 'pk_test'))->verify('ref_123');

    expect($result->successful)->toBeTrue()
        ->and($result->amountMinor)->toBe(151_750_00);
});

it('treats a declined Paystack transaction as unsuccessful', function () {
    Http::fake([
        'api.paystack.co/transaction/verify/*' => Http::response([
            'status' => true,
            'data' => ['status' => 'failed', 'reference' => 'ref_123', 'amount' => 0, 'currency' => 'NGN', 'gateway_response' => 'Declined'],
        ]),
    ]);

    $result = (new PaystackGateway('sk_test', 'pk_test'))->verify('ref_123');

    expect($result->successful)->toBeFalse()
        ->and($result->failureReason)->toBe('Declined');
});

it('gives each attempt its own reference so a retry cannot collide', function () {
    Http::fake([
        'api.paystack.co/transaction/initialize' => Http::response([
            'status' => true,
            'data' => ['reference' => 'ref', 'authorization_url' => 'https://x'],
        ]),
    ]);

    $gateway = new PaystackGateway('sk_test', 'pk_test');
    $gateway->initialise(intent());
    $gateway->initialise(intent());

    $references = [];
    Http::assertSentCount(2);
    Http::recorded(function ($request) use (&$references) {
        $references[] = $request['reference'];

        return true;
    });

    expect($references[0])->not->toBe($references[1]);
});
