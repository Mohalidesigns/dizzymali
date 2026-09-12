<?php

declare(strict_types=1);

namespace App\Domain\Payments;

use Illuminate\Support\Collection;

/**
 * Picks a gateway, and knows which ones are actually usable today.
 *
 * Paystack is primary. Flutterwave is the fallback for currencies or cards
 * Paystack will not take — and, right now, is present but unconfigured, which
 * this class treats as "not available" rather than "broken".
 */
class GatewayManager
{
    /** @param  array<string,PaymentGateway>  $gateways */
    public function __construct(
        private readonly array $gateways,
        private readonly string $primary = 'paystack',
        private readonly string $fallback = 'flutterwave',
    ) {}

    public function get(string $name): PaymentGateway
    {
        return $this->gateways[$name]
            ?? throw new PaymentFailed("There is no payment gateway called \"{$name}\".");
    }

    public function has(string $name): bool
    {
        return isset($this->gateways[$name]);
    }

    /**
     * The gateway to use for a given currency, or null if none can take it.
     *
     * Callers must handle null rather than assuming a gateway exists — during
     * setup, and any time keys are rotated badly, none will.
     */
    public function forCurrency(string $currency, ?string $preferred = null): ?PaymentGateway
    {
        $order = array_values(array_unique(array_filter([
            $preferred,
            $this->primary,
            $this->fallback,
        ])));

        foreach ($order as $name) {
            $gateway = $this->gateways[$name] ?? null;

            if ($gateway !== null && $gateway->isConfigured() && $gateway->supports($currency)) {
                return $gateway;
            }
        }

        return null;
    }

    /** @return Collection<int,PaymentGateway> Every gateway that could take money right now. */
    public function available(): Collection
    {
        return collect($this->gateways)
            ->filter(fn (PaymentGateway $g) => $g->isConfigured())
            ->values();
    }

    /**
     * Status of every gateway, for the admin screen. Being unconfigured is a
     * normal state to be shown plainly, not an error to be hidden.
     *
     * @return list<array{name:string,configured:bool,is_primary:bool,is_fallback:bool}>
     */
    public function status(): array
    {
        return collect($this->gateways)
            ->map(fn (PaymentGateway $g) => [
                'name' => $g->name(),
                'configured' => $g->isConfigured(),
                'is_primary' => $g->name() === $this->primary,
                'is_fallback' => $g->name() === $this->fallback,
            ])
            ->values()
            ->all();
    }

    /** @return Collection<int,PaymentGateway> Gateways that can verify a webhook signature. */
    public function webhookCapable(): Collection
    {
        return $this->available();
    }
}
