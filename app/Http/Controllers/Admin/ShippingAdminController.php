<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Orders\OrderStateMachine;
use App\Enums\OrderStatus;
use App\Events\OrderShipped;
use App\Http\Controllers\Controller;
use App\Http\Resources\MoneyResource;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ShippingAdminController extends Controller
{
    /** Carrier tracking pages, so the customer gets a working link not a number to copy. */
    private const TRACKING_URLS = [
        'DHL' => 'https://www.dhl.com/ng-en/home/tracking.html?tracking-id=',
        'GIG' => 'https://giglogistics.com/track?waybill=',
        'Aramex' => 'https://www.aramex.com/us/en/track/results?ShipmentNumber=',
        'FedEx' => 'https://www.fedex.com/fedextrack/?trknbr=',
        'UPS' => 'https://www.ups.com/track?tracknum=',
    ];

    public function __construct(private readonly OrderStateMachine $states) {}

    public function index(): Response
    {
        $this->authorize('viewAny', ShippingZone::class);

        return Inertia::render('admin/Shipping', [
            'zones' => ShippingZone::with('rates')->orderBy('sort_order')->get()
                ->map(fn (ShippingZone $z) => [
                    'id' => $z->id,
                    'slug' => $z->slug,
                    'name' => $z->name,
                    'country_codes' => $z->country_codes,
                    'is_default' => $z->is_default,
                    'is_active' => $z->is_active,
                    'rates' => $z->rates->map(fn (ShippingRate $r) => [
                        'id' => $r->id,
                        'service_level' => $r->service_level,
                        'min_grams' => $r->min_grams,
                        'max_grams' => $r->max_grams,
                        'price' => MoneyResource::make((int) $r->price_kobo),
                        'transit_days_min' => $r->transit_days_min,
                        'transit_days_max' => $r->transit_days_max,
                        'is_active' => $r->is_active,
                    ])->values(),
                ]),
            'carriers' => array_keys(self::TRACKING_URLS),
        ]);
    }

    public function storeRate(Request $request, ShippingZone $shippingZone): RedirectResponse
    {
        $this->authorize('update', $shippingZone);

        $validated = $request->validate([
            'service_level' => ['required', Rule::in(['standard', 'express'])],
            'min_grams' => ['required', 'integer', 'min:0'],
            'max_grams' => ['required', 'integer', 'gt:min_grams'],
            'price_naira' => ['required', 'integer', 'min:0'],
            'transit_days_min' => ['required', 'integer', 'between:1,120'],
            'transit_days_max' => ['required', 'integer', 'gte:transit_days_min'],
        ]);

        $shippingZone->rates()->create([
            'service_level' => $validated['service_level'],
            'min_grams' => $validated['min_grams'],
            'max_grams' => $validated['max_grams'],
            'price_kobo' => $validated['price_naira'] * 100,
            'transit_days_min' => $validated['transit_days_min'],
            'transit_days_max' => $validated['transit_days_max'],
            'is_active' => true,
        ]);

        return back()->with('success', 'Rate added.');
    }

    public function updateRate(Request $request, ShippingRate $shippingRate): RedirectResponse
    {
        $this->authorize('update', $shippingRate);

        $validated = $request->validate([
            'price_naira' => ['sometimes', 'integer', 'min:0'],
            'transit_days_min' => ['sometimes', 'integer', 'between:1,120'],
            'transit_days_max' => ['sometimes', 'integer', 'between:1,180'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (isset($validated['price_naira'])) {
            $validated['price_kobo'] = $validated['price_naira'] * 100;
            unset($validated['price_naira']);
        }

        $shippingRate->update($validated);

        return back()->with('success', 'Rate updated.');
    }

    public function destroyRate(ShippingRate $shippingRate): RedirectResponse
    {
        $this->authorize('delete', $shippingRate);

        $shippingRate->delete();

        return back();
    }

    /** Record the parcel going out, and tell the customer. */
    public function ship(Request $request, Order $order): RedirectResponse
    {
        $this->authorize('manage', $order);

        $validated = $request->validate([
            'carrier' => ['required', 'string', 'max:40'],
            'tracking_number' => ['required', 'string', 'max:120'],
            'weight_grams' => ['sometimes', 'nullable', 'integer', 'between:1,50000'],
            // EU customers are asked for duty on delivery. Declaring correctly
            // is what stops a parcel being refused at the door.
            'hs_code' => ['sometimes', 'nullable', 'string', 'max:16'],
            'declared_value_naira' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        $shipment = Shipment::create([
            'order_id' => $order->id,
            'service_level' => $order->service_level,
            'carrier' => $validated['carrier'],
            'tracking_number' => $validated['tracking_number'],
            'tracking_url' => $this->trackingUrl($validated['carrier'], $validated['tracking_number']),
            'weight_grams' => $validated['weight_grams'] ?? null,
            'hs_code' => $validated['hs_code'] ?? null,
            'declared_value_kobo' => isset($validated['declared_value_naira'])
                ? $validated['declared_value_naira'] * 100
                : (int) $order->total_kobo,
            'notes' => $validated['notes'] ?? null,
            'shipped_at' => now(),
        ]);

        if ($this->states->canTransition($order, OrderStatus::Shipped)) {
            $this->states->transition($order, OrderStatus::Shipped, $request->user(), 'Handed to '.$validated['carrier'].'.');
        }

        OrderShipped::dispatch($order->refresh(), $shipment);

        return back()->with('success', 'Shipment recorded and the customer has been told.');
    }

    private function trackingUrl(string $carrier, string $number): ?string
    {
        $base = self::TRACKING_URLS[$carrier] ?? null;

        return $base === null ? null : $base.urlencode($number);
    }
}
