<?php

declare(strict_types=1);

use App\Models\Address;
use App\Models\MeasurementProfile;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\CommerceSettingsSeeder;
use Database\Seeders\FabricSeeder;
use Database\Seeders\GarmentTypeSeeder;
use Database\Seeders\MeasurementFieldSeeder;
use Database\Seeders\RoleSeeder;

/*
|--------------------------------------------------------------------------
| Cross-customer denial
|--------------------------------------------------------------------------
| Measurements and customer photographs are personal data under the NDPA
| 2023. An IDOR here is a reportable breach, not a bug, so every one of
| these is a required test rather than a nice-to-have.
*/

beforeEach(function () {
    $this->seed([RoleSeeder::class, MeasurementFieldSeeder::class, GarmentTypeSeeder::class, FabricSeeder::class, CommerceSettingsSeeder::class]);

    $this->alice = User::factory()->create(['email_verified_at' => now()]);
    $this->alice->assignRole('customer');

    $this->mallory = User::factory()->create(['email_verified_at' => now()]);
    $this->mallory->assignRole('customer');
});

it('does not let one customer read another customer\'s order', function () {
    $order = Order::factory()->for($this->alice)->create();

    $this->actingAs($this->mallory)->get("/orders/{$order->id}")->assertForbidden();
});

it('does not let one customer change another customer\'s order', function () {
    $order = Order::factory()->for($this->alice)->create();

    $this->actingAs($this->mallory)
        ->patch("/order/{$order->id}", ['customer_notes' => 'hijacked'])
        ->assertForbidden();

    expect($order->fresh()->customer_notes)->toBeNull();
});

it('does not let one customer submit another customer\'s order', function () {
    $order = Order::factory()->for($this->alice)->create();

    $this->actingAs($this->mallory)->post("/order/{$order->id}/submit")->assertForbidden();
});

it('does not let one customer read another customer\'s measurements', function () {
    $profile = MeasurementProfile::factory()->for($this->alice)->create();

    $this->actingAs($this->mallory)
        ->patch("/measurements/{$profile->id}", [
            'name' => 'stolen', 'unit' => 'in', 'values' => ['chest' => 40],
        ])
        ->assertForbidden();
});

it('does not let one customer delete another customer\'s measurements', function () {
    $profile = MeasurementProfile::factory()->for($this->alice)->create();

    $this->actingAs($this->mallory)->delete("/measurements/{$profile->id}")->assertForbidden();

    expect(MeasurementProfile::find($profile->id))->not->toBeNull();
});

it('does not let one customer touch another customer\'s address', function () {
    $address = Address::factory()->for($this->alice)->create();

    $this->actingAs($this->mallory)
        ->patch("/addresses/{$address->id}", [
            'recipient_name' => 'Mallory', 'phone' => '+1', 'line_1' => 'x',
            'city' => 'x', 'country_code' => 'GB',
        ])
        ->assertForbidden();
});

it('rejects a measurement profile belonging to someone else at validation time', function () {
    $aliceProfile = MeasurementProfile::factory()->for($this->alice)->create();
    $order = Order::factory()->for($this->mallory)->create();

    $this->actingAs($this->mallory)
        ->patch("/order/{$order->id}", ['measurement_profile_id' => $aliceProfile->id])
        ->assertSessionHasErrors('measurement_profile_id');
});

it('rejects an address belonging to someone else at validation time', function () {
    $aliceAddress = Address::factory()->for($this->alice)->create();
    $order = Order::factory()->for($this->mallory)->create();

    $this->actingAs($this->mallory)
        ->patch("/order/{$order->id}", ['shipping_address_id' => $aliceAddress->id])
        ->assertSessionHasErrors('shipping_address_id');
});

it('keeps customers out of the admin panel entirely', function () {
    $this->actingAs($this->alice)->get('/admin')->assertForbidden();
    $this->actingAs($this->alice)->get('/admin/orders')->assertForbidden();
    $this->actingAs($this->alice)->get('/admin/customers')->assertForbidden();
    $this->actingAs($this->alice)->get('/admin/measurement-reviews')->assertForbidden();
});

it('does not let a customer advance their own order through the workshop', function () {
    $order = Order::factory()->for($this->alice)->create(['status' => 'paid']);

    $this->actingAs($this->alice)
        ->post("/admin/orders/{$order->id}/advance", ['status' => 'shipped'])
        ->assertForbidden();
});

it('lets staff read any order', function () {
    $staff = User::factory()->create(['email_verified_at' => now()]);
    $staff->assignRole('staff');

    $order = Order::factory()->for($this->alice)->create(['status' => 'submitted']);

    $this->actingAs($staff)->get("/admin/orders/{$order->id}")->assertOk();
});

it('does not let a tailor edit prices', function () {
    $tailor = User::factory()->create(['email_verified_at' => now()]);
    $tailor->assignRole('tailor');

    $this->actingAs($tailor)
        ->patch('/admin/garment-types/1', ['base_sewing_cost_naira' => 1])
        ->assertForbidden();
});

it('sends a signed-out visitor to the login page', function () {
    $this->get('/order')->assertRedirect('/login');
    $this->get('/orders')->assertRedirect('/login');
    $this->get('/measurements')->assertRedirect('/login');
});
