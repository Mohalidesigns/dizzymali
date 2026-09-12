<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\FxRate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CurrencyAdminController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Currency::class);

        return Inertia::render('admin/Currencies', [
            'currencies' => Currency::with('currentRate')->orderBy('sort_order')->get()
                ->map(fn (Currency $c) => [
                    'id' => $c->id,
                    'code' => $c->code,
                    'name' => $c->name,
                    'symbol' => $c->symbol,
                    'decimals' => $c->decimals,
                    'rounding_minor' => $c->rounding_minor,
                    'is_base' => $c->is_base,
                    'is_active' => $c->is_active,
                    'rate' => $c->currentRate === null ? null : [
                        'rate' => (float) $c->currentRate->rate,
                        'margin_percent' => (float) $c->currentRate->margin_percent,
                        'source' => $c->currentRate->source,
                        'effective_at' => $c->currentRate->effective_at?->toDateTimeString(),
                        // How many naira make one unit — easier to sanity-check
                        // than a rate of 0.000512.
                        'naira_per_unit' => (float) $c->currentRate->rate > 0
                            ? round(1 / (float) $c->currentRate->rate, 2)
                            : null,
                    ],
                ]),
            'provider' => [
                'driver' => config('services.fx.driver', 'manual'),
                'configured' => filled(config('services.fx.endpoint')),
            ],
        ]);
    }

    public function storeRate(Request $request, Currency $currency): RedirectResponse
    {
        $this->authorize('update', $currency);

        $validated = $request->validate([
            // Entered the way a human thinks about it: naira per pound, not
            // pounds per naira. Inverted here, once, where it can be tested.
            'naira_per_unit' => ['required', 'numeric', 'min:0.0001', 'max:100000'],
            'margin_percent' => ['required', 'numeric', 'between:0,50'],
        ]);

        FxRate::create([
            'currency_id' => $currency->id,
            'rate' => 1 / (float) $validated['naira_per_unit'],
            'margin_percent' => $validated['margin_percent'],
            'source' => 'manual',
            'effective_at' => now(),
        ]);

        return back()->with('success', "Rate updated for {$currency->code}. Orders already placed keep the rate they were quoted at.");
    }

    public function update(Request $request, Currency $currency): RedirectResponse
    {
        $this->authorize('update', $currency);

        $currency->update($request->validate([
            'is_active' => ['sometimes', 'boolean'],
            'rounding_minor' => ['sometimes', 'integer', 'between:1,100000'],
            'sort_order' => ['sometimes', 'integer', 'between:0,999'],
        ]));

        return back()->with('success', 'Currency updated.');
    }
}
