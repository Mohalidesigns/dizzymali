<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Measurements\SaveMeasurementProfile;
use App\Actions\Orders\RecalculateQuote;
use App\Domain\Orders\OrderStateMachine;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Address;
use App\Models\FabricVariant;
use App\Models\GarmentOption;
use App\Models\GarmentType;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * A demo dataset you can actually navigate: staff accounts, three customers
 * with saved profiles, and orders sitting at every stage of the pipeline so
 * the admin kanban is not an empty grid on first login.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $profiles = app(SaveMeasurementProfile::class);
        $recalculate = app(RecalculateQuote::class);
        $states = app(OrderStateMachine::class);

        $admin = $this->user('Mohammed Ali', 'admin@dizzymali.test', UserRole::SuperAdmin, 'NG');
        $this->user('Workshop Staff', 'staff@dizzymali.test', UserRole::Staff, 'NG');
        $tailor = $this->user('Musa the Tailor', 'tailor@dizzymali.test', UserRole::Tailor, 'NG');

        $customers = [
            ['Adebayo Okonkwo', 'adebayo@example.test', 'NG', 'NGN', [
                'chest' => 42, 'shoulder' => 18.5, 'tommy' => 40, 'shirt_length' => 56,
                'neck' => 16, 'sleeve' => 25, 'round_sleeve' => 15,
                'waist' => 36, 'hip' => 40, 'thigh_lap' => 24, 'length' => 42, 'knee' => 18, 'foot' => 15,
            ], ['London', 'GB']],
            ['Ibrahim Sule', 'ibrahim@example.test', 'GB', 'GBP', [
                'chest' => 48, 'shoulder' => 20, 'tommy' => 46, 'shirt_length' => 58,
                'neck' => 17.5, 'sleeve' => 26, 'round_sleeve' => 17,
                'waist' => 42, 'hip' => 45, 'thigh_lap' => 27, 'length' => 43, 'knee' => 19, 'foot' => 16,
            ], ['Manchester', 'GB']],
            ['Yusuf Bello', 'yusuf@example.test', 'US', 'USD', [
                'chest' => 53, 'shoulder' => 21, 'tommy' => 52, 'shirt_length' => 60,
                'neck' => 18.5, 'sleeve' => 27, 'round_sleeve' => 19,
                'waist' => 46, 'hip' => 50, 'thigh_lap' => 30, 'length' => 44, 'knee' => 21, 'foot' => 17,
            ], ['Houston', 'US']],
        ];

        $garments = GarmentType::all()->keyBy('slug');
        $variants = FabricVariant::all()->values();
        $embroidery = GarmentOption::query()->where('slug', 'standard')->first();

        $stageLadder = [
            OrderStatus::Submitted,
            OrderStatus::QuoteAccepted,
            OrderStatus::PaymentPending,
            OrderStatus::Paid,
            OrderStatus::FabricSourced,
            OrderStatus::Cutting,
            OrderStatus::Sewing,
            OrderStatus::QualityCheck,
            OrderStatus::Ready,
            OrderStatus::Shipped,
            OrderStatus::Delivered,
        ];

        $ladderIndex = 0;

        foreach ($customers as [$name, $email, $country, $currency, $measurements, [$city, $addrCountry]]) {
            $customer = $this->user($name, $email, UserRole::Customer, $country, $currency);

            $profiles->handle($customer, [
                'name' => 'My usual fit',
                'unit' => 'in',
                'source' => 'manual',
                'is_default' => true,
                'values' => $measurements,
            ]);

            $address = Address::firstOrCreate(
                ['user_id' => $customer->id, 'line_1' => '12 Marina Road'],
                [
                    'label' => 'Home',
                    'recipient_name' => $name,
                    'phone' => '+2348000000001',
                    'city' => $city,
                    'country_code' => $addrCountry,
                    'is_default' => true,
                ],
            );

            $profile = $customer->defaultMeasurementProfile;

            foreach (['agbada', 'kaftan', 'danshiki', 'jalabiya'] as $g => $slug) {
                $garment = $garments->get($slug);

                if ($garment === null) {
                    continue;
                }

                $order = Order::create([
                    'user_id' => $customer->id,
                    'status' => OrderStatus::Draft,
                    'currency_code' => $currency,
                    'service_level' => $g % 3 === 0 ? 'express' : 'standard',
                    'shipping_address_id' => $address->id,
                    'shipping_address_snapshot' => $address->snapshot(),
                    'wizard_step' => 6,
                ]);

                $item = $order->items()->create([
                    'garment_type_id' => $garment->id,
                    'fabric_variant_id' => $variants[($g + $ladderIndex) % $variants->count()]->id,
                    'quantity' => 1,
                    'measurement_profile_id' => $profile?->id,
                    'measurement_snapshot' => $profile?->snapshot(),
                    'style_notes' => 'Please keep the sleeves a touch narrower than standard.',
                ]);

                if ($embroidery !== null && $g === 0) {
                    $item->options()->create([
                        'garment_option_id' => $embroidery->id,
                        'group_name' => 'Embroidery',
                        'option_name' => $embroidery->name,
                        'surcharge_kobo' => (int) $embroidery->surcharge_kobo,
                        'additional_yards' => $embroidery->additional_yards,
                    ]);
                }

                $recalculate->handle($order->refresh());

                // Walk it up the ladder so every stage has something in it.
                $target = $stageLadder[$ladderIndex % count($stageLadder)];
                $ladderIndex++;

                if ($g === 3) {
                    continue; // leave one as a live draft per customer
                }

                foreach ($stageLadder as $stage) {
                    if (! $states->canTransition($order->refresh(), $stage)) {
                        continue;
                    }

                    $states->transition($order, $stage, $admin, 'Seeded.');

                    if ($stage === $target) {
                        break;
                    }
                }

                if ($order->refresh()->status->isPaid()) {
                    $order->forceFill([
                        'amount_paid_kobo' => $order->total_kobo,
                        'placed_at' => now()->subDays(random_int(2, 20)),
                        'promised_at' => now()->addDays(random_int(3, 25)),
                    ])->save();

                    $order->payments()->create([
                        'gateway' => 'paystack',
                        'gateway_reference' => 'seed_'.$order->reference,
                        'idempotency_key' => 'seed-'.$order->id,
                        'status' => 'success',
                        'kind' => 'full',
                        'amount_kobo' => $order->total_kobo,
                        'currency_code' => $order->currency_code,
                        'charged_minor' => $order->display_total_minor,
                        'paid_at' => now()->subDays(random_int(2, 20)),
                    ]);
                }

                $order->items()->update(['tailor_id' => $tailor->id]);
            }
        }
    }

    private function user(string $name, string $email, UserRole $role, string $country, string $currency = 'NGN'): User
    {
        $user = User::firstOrCreate(['email' => $email], [
            'name' => $name,
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'country_code' => $country,
            'preferred_currency' => $currency,
            'unit_preference' => 'in',
            'privacy_consented_at' => now(),
        ]);

        $user->syncRoles([$role->value]);

        return $user;
    }
}
