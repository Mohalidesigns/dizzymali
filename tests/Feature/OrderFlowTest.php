<?php

declare(strict_types=1);

use App\Actions\Measurements\SaveMeasurementProfile;
use App\Actions\Orders\RecalculateQuote;
use App\Domain\Orders\OrderStateMachine;
use App\Enums\OrderStatus;
use App\Exceptions\IllegalOrderTransition;
use App\Models\Address;
use App\Models\FabricVariant;
use App\Models\GarmentType;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\CommerceSettingsSeeder;
use Database\Seeders\FabricSeeder;
use Database\Seeders\GarmentOptionSeeder;
use Database\Seeders\GarmentTypeSeeder;
use Database\Seeders\MeasurementFieldSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed([
        RoleSeeder::class, MeasurementFieldSeeder::class, GarmentTypeSeeder::class,
        FabricSeeder::class, GarmentOptionSeeder::class, CommerceSettingsSeeder::class,
    ]);

    $this->customer = User::factory()->create([
        'email_verified_at' => now(),
        'country_code' => 'GB',
        'preferred_currency' => 'NGN',
    ]);
    $this->customer->assignRole('customer');

    $this->profile = app(SaveMeasurementProfile::class)->handle($this->customer, [
        'name' => 'My fit',
        'unit' => 'in',
        'is_default' => true,
        'values' => [
            'chest' => 42, 'shoulder' => 18.5, 'tommy' => 40, 'shirt_length' => 56,
            'neck' => 16, 'sleeve' => 25, 'round_sleeve' => 15,
            'waist' => 36, 'hip' => 40, 'thigh_lap' => 24, 'length' => 42,
            'knee' => 18, 'foot' => 15,
        ],
    ]);

    $this->address = Address::factory()->for($this->customer)->create(['country_code' => 'GB']);
});

it('creates a draft the first time a customer opens the wizard', function () {
    $this->actingAs($this->customer)->get('/order')->assertOk();

    expect(Order::where('user_id', $this->customer->id)->drafts()->count())->toBe(1);
});

it('prices the draft on the server as the customer moves through the wizard', function () {
    $agbada = GarmentType::where('slug', 'agbada')->firstOrFail();
    $variant = FabricVariant::where('sku', 'ATIKU-LINEN-NAVY')->firstOrFail();

    $this->actingAs($this->customer)->get('/order');
    $order = Order::where('user_id', $this->customer->id)->drafts()->firstOrFail();

    $this->actingAs($this->customer)->patch("/order/{$order->id}", ['garment_type_id' => $agbada->id]);
    $this->actingAs($this->customer)->patch("/order/{$order->id}", ['fabric_variant_id' => $variant->id]);
    $this->actingAs($this->customer)->patch("/order/{$order->id}", ['measurement_profile_id' => $this->profile->id]);

    $order->refresh();

    // 5 yd base x N15,000 = N75,000 fabric, N45,000 sewing.
    expect((int) $order->fabric_total_kobo)->toBe(75_000_00)
        ->and((int) $order->sewing_total_kobo)->toBe(45_000_00)
        ->and((int) $order->subtotal_kobo)->toBe(120_000_00)
        // Shipping to GB is charged from the zone table, not from the client.
        ->and((int) $order->shipping_kobo)->toBeGreaterThan(0);
});

it('never trusts a price sent by the client', function () {
    $agbada = GarmentType::where('slug', 'agbada')->firstOrFail();

    $this->actingAs($this->customer)->get('/order');
    $order = Order::where('user_id', $this->customer->id)->drafts()->firstOrFail();

    $this->actingAs($this->customer)->patch("/order/{$order->id}", [
        'garment_type_id' => $agbada->id,
        'total_kobo' => 1,
        'subtotal_kobo' => 1,
        'sewing_total_kobo' => 1,
    ]);

    expect((int) $order->fresh()->sewing_total_kobo)->toBe(45_000_00);
});

it('refuses to submit an order with no fabric or measurements', function () {
    $agbada = GarmentType::where('slug', 'agbada')->firstOrFail();

    $this->actingAs($this->customer)->get('/order');
    $order = Order::where('user_id', $this->customer->id)->drafts()->firstOrFail();
    $this->actingAs($this->customer)->patch("/order/{$order->id}", ['garment_type_id' => $agbada->id]);

    $this->actingAs($this->customer)
        ->post("/order/{$order->id}/submit")
        ->assertSessionHasErrors('order');

    expect($order->fresh()->status)->toBe(OrderStatus::Draft);
});

it('freezes the measurements onto the order at submission', function () {
    $order = completeDraft($this);

    $this->actingAs($this->customer)->post("/order/{$order->id}/submit");
    $order->refresh();

    $snapshot = $order->items->first()->measurement_snapshot;

    expect($order->status)->toBe(OrderStatus::Submitted)
        ->and($snapshot)->toBeArray()
        ->and((float) collect($snapshot['values'])->firstWhere('key', 'chest')['value_inches'])->toBe(42.0);

    // Now the customer edits their saved profile. The order must not move.
    app(SaveMeasurementProfile::class)->handle(
        $this->customer,
        ['name' => 'My fit', 'unit' => 'in', 'values' => ['chest' => 50]],
        $this->profile,
    );

    $after = $order->fresh()->items->first()->measurement_snapshot;

    expect((float) collect($after['values'])->firstWhere('key', 'chest')['value_inches'])->toBe(42.0);
});

it('freezes the garment and fabric prices at submission', function () {
    $order = completeDraft($this);
    $this->actingAs($this->customer)->post("/order/{$order->id}/submit");

    $before = (int) $order->fresh()->total_kobo;

    // The workshop doubles its sewing price the next morning.
    GarmentType::where('slug', 'agbada')->update(['base_sewing_cost_kobo' => 90_000_00]);
    FabricVariant::where('sku', 'ATIKU-LINEN-NAVY')->update(['price_per_yard_kobo' => 30_000_00]);

    app(RecalculateQuote::class)->handle($order->fresh());

    expect((int) $order->fresh()->total_kobo)->toBe($before);
});

it('sets a promised date from the garment lead time', function () {
    $order = completeDraft($this);
    $this->actingAs($this->customer)->post("/order/{$order->id}/submit");

    expect($order->fresh()->promised_at?->toDateString())
        ->toBe(now()->addDays(28)->toDateString());
});

it('records every stage change as an event', function () {
    $order = completeDraft($this);
    $this->actingAs($this->customer)->post("/order/{$order->id}/submit");

    $staff = User::factory()->create(['email_verified_at' => now()]);
    $staff->assignRole('staff');

    $machine = app(OrderStateMachine::class);
    $machine->transition($order->fresh(), OrderStatus::QuoteAccepted, $staff, 'Quote agreed on the phone.');

    $events = $order->fresh()->statusEvents;

    expect($events)->toHaveCount(2)
        ->and($events->first()->to_status)->toBe(OrderStatus::QuoteAccepted)
        ->and($events->first()->actor_id)->toBe($staff->id);
});

it('refuses an illegal stage jump', function () {
    $order = completeDraft($this);
    $this->actingAs($this->customer)->post("/order/{$order->id}/submit");

    app(OrderStateMachine::class)->transition($order->fresh(), OrderStatus::Shipped);
})->throws(IllegalOrderTransition::class);

it('will not let a customer edit an order once it is locked', function () {
    $order = completeDraft($this);
    $this->actingAs($this->customer)->post("/order/{$order->id}/submit");

    $order->fresh()->forceFill(['status' => OrderStatus::Cutting])->save();

    $this->actingAs($this->customer)
        ->patch("/order/{$order->id}", ['customer_notes' => 'change it'])
        ->assertForbidden();
});

/** Build a draft that is actually ready to submit. */
function completeDraft($test): Order
{
    $agbada = GarmentType::where('slug', 'agbada')->firstOrFail();
    $variant = FabricVariant::where('sku', 'ATIKU-LINEN-NAVY')->firstOrFail();

    $test->actingAs($test->customer)->get('/order');
    $order = Order::where('user_id', $test->customer->id)->drafts()->firstOrFail();

    $test->actingAs($test->customer)->patch("/order/{$order->id}", [
        'garment_type_id' => $agbada->id,
        'fabric_variant_id' => $variant->id,
        'measurement_profile_id' => $test->profile->id,
        'shipping_address_id' => $test->address->id,
    ]);

    return $order->fresh();
}
