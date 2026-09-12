<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Currency;
use App\Models\FxRate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Pulls today's FX rates.
 *
 * The default driver is `manual`, which does nothing and says so — rates stay
 * exactly as the admin set them. Point FX_DRIVER at a provider and give it an
 * endpoint to switch on the daily refresh.
 *
 * Whatever this writes, it never touches an order that has already been placed:
 * `fx_rate_used` is frozen at submission, so a customer quoted £180 pays £180.
 */
class RefreshFxRates extends Command
{
    protected $signature = 'fx:refresh {--force : Write rates even if today already has one}';

    protected $description = 'Refresh foreign exchange rates from the configured provider';

    public function handle(): int
    {
        $driver = (string) config('services.fx.driver', 'manual');

        if ($driver === 'manual') {
            $this->info('FX driver is "manual" — rates are whatever the admin set. Nothing to do.');

            return self::SUCCESS;
        }

        $endpoint = config('services.fx.endpoint');

        if (! filled($endpoint)) {
            $this->error('FX_ENDPOINT is not set. Rates were not refreshed.');

            return self::FAILURE;
        }

        $currencies = Currency::query()->where('is_active', true)->where('is_base', false)->get();

        if ($currencies->isEmpty()) {
            $this->info('No non-base currencies are active.');

            return self::SUCCESS;
        }

        $response = Http::acceptJson()->timeout(20)->get((string) $endpoint, [
            'base' => 'NGN',
            'symbols' => $currencies->pluck('code')->implode(','),
            'access_key' => config('services.fx.key'),
        ]);

        if ($response->failed()) {
            $this->error('The FX provider did not respond: '.$response->status());

            return self::FAILURE;
        }

        /** @var array<string,mixed> $rates */
        $rates = (array) $response->json('rates', []);
        $written = 0;

        foreach ($currencies as $currency) {
            $rate = $rates[$currency->code] ?? null;

            if (! is_numeric($rate) || (float) $rate <= 0) {
                $this->warn("No usable rate returned for {$currency->code} — keeping the existing one.");

                continue;
            }

            // The admin's margin is a commercial decision, not the provider's.
            // Carry the existing one forward rather than resetting it to zero.
            $existing = $currency->currentRate;
            $margin = $existing === null
                ? config('services.fx.default_margin_percent', 8)
                : $existing->margin_percent;

            FxRate::create([
                'currency_id' => $currency->id,
                'rate' => (float) $rate,
                'margin_percent' => $margin,
                'source' => $driver,
                'effective_at' => now(),
            ]);

            $written++;
        }

        $this->info("Refreshed {$written} rate(s).");

        return self::SUCCESS;
    }
}
