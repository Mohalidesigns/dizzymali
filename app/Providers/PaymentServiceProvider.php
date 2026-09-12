<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Payments\GatewayManager;
use App\Domain\Payments\Gateways\FlutterwaveGateway;
use App\Domain\Payments\Gateways\ManualGateway;
use App\Domain\Payments\Gateways\PaystackGateway;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GatewayManager::class, function (Application $app): GatewayManager {
            /** @var array<string,mixed> $config */
            $config = $app['config']->get('payments');
            $timeout = (int) ($config['http_timeout'] ?? 20);

            $paystack = (array) ($config['gateways']['paystack'] ?? []);
            $flutterwave = (array) ($config['gateways']['flutterwave'] ?? []);

            return new GatewayManager(
                gateways: [
                    'paystack' => new PaystackGateway(
                        secretKey: $paystack['secret_key'] ?? null,
                        publicKey: $paystack['public_key'] ?? null,
                        baseUrl: (string) ($paystack['base_url'] ?? 'https://api.paystack.co'),
                        timeoutSeconds: $timeout,
                    ),
                    'flutterwave' => new FlutterwaveGateway(
                        secretKey: $flutterwave['secret_key'] ?? null,
                        publicKey: $flutterwave['public_key'] ?? null,
                        secretHash: $flutterwave['secret_hash'] ?? null,
                        baseUrl: (string) ($flutterwave['base_url'] ?? 'https://api.flutterwave.com/v3'),
                        timeoutSeconds: $timeout,
                    ),
                    'manual' => new ManualGateway,
                ],
                primary: (string) ($config['primary'] ?? 'paystack'),
                fallback: (string) ($config['fallback'] ?? 'flutterwave'),
            );
        });
    }
}
