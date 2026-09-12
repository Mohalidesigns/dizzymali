<?php

declare(strict_types=1);

use App\Enums\CustomerStage;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\OrderStageChangedNotification;
use App\Notifications\OrderSubmittedNotification;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed([RoleSeeder::class]);

    $this->customer = User::factory()->create([
        'email_verified_at' => now(),
        'phone' => '08012345678',
        'whatsapp_opt_in' => true,
    ]);
    $this->customer->assignRole('customer');

    $this->order = Order::factory()->for($this->customer)->create([
        'status' => OrderStatus::Submitted,
        'total_kobo' => 151_750_00,
    ]);
});

it('sends over email and in-app while WhatsApp is on hold', function () {
    config()->set('notifications.channels.whatsapp.enabled', false);

    $channels = (new OrderSubmittedNotification($this->order))->via($this->customer);

    expect($channels)->toContain('mail')
        ->and($channels)->toContain('database')
        ->and($channels)->not->toContain(WhatsAppChannel::class);
});

it('adds WhatsApp to the same notification the moment it is switched on', function () {
    config()->set('notifications.channels.whatsapp.enabled', true);

    $channels = (new OrderSubmittedNotification($this->order))->via($this->customer);

    expect($channels)->toContain(WhatsAppChannel::class);
});

it('respects a channel being switched off for everything', function () {
    config()->set('notifications.channels.mail.enabled', false);

    expect((new OrderSubmittedNotification($this->order))->via($this->customer))
        ->not->toContain('mail');
});

it('reads its channels from config, so the matrix is editable without a deploy', function () {
    config()->set('notifications.matrix.order_submitted', ['database']);

    expect((new OrderSubmittedNotification($this->order))->via($this->customer))
        ->toBe(['database']);
});

it('carries the order into the in-app payload', function () {
    $payload = (new OrderSubmittedNotification($this->order))->toArray($this->customer);

    expect($payload['reference'])->toBe($this->order->reference)
        ->and($payload['order_id'])->toBe($this->order->id)
        ->and($payload['url'])->toContain((string) $this->order->id);
});

it('writes an email a person would actually want to read', function () {
    $mail = (new OrderSubmittedNotification($this->order))->toMail($this->customer);

    expect($mail->subject)->toContain($this->order->reference);
});

it('builds a WhatsApp message from a template key, not a provider template name', function () {
    $message = (new OrderSubmittedNotification($this->order))->toWhatsApp($this->customer);

    expect($message->templateKey)->toBe('order_submitted')
        ->and($message->normalisedTo())->toBe('2348012345678')
        ->and($message->parameters)->toContain($this->order->reference);
});

it('does not message a customer who never opted in to WhatsApp', function () {
    config()->set('notifications.channels.whatsapp.enabled', true);

    $noConsent = User::factory()->create(['whatsapp_opt_in' => false, 'phone' => '08012345678']);
    $noConsent->assignRole('customer');

    $sent = false;
    $driver = new class($sent) implements App\Domain\Messaging\WhatsAppDriver
    {
        public function __construct(private bool &$sent) {}

        public function name(): string
        {
            return 'spy';
        }

        public function isConfigured(): bool
        {
            return true;
        }

        public function send(App\Domain\Messaging\WhatsAppMessage $message): bool
        {
            $this->sent = true;

            return true;
        }
    };

    (new WhatsAppChannel($driver))->send($noConsent, new OrderSubmittedNotification($this->order));

    expect($sent)->toBeFalse();
});

it('does not nag about moves the customer cannot see', function () {
    // "Fabric sourced" and "cutting" are one stage to the customer. Nobody
    // wants four texts about one garment being cut.
    expect(OrderStatus::FabricSourced->customerStage())
        ->toBe(OrderStatus::Cutting->customerStage())
        ->toBe(CustomerStage::FabricAndCutting);
});

it('describes each stage in words rather than internal status names', function () {
    $notification = new OrderStageChangedNotification($this->order, CustomerStage::Sewing);

    expect($notification->title())->toBe('Sewing')
        ->and($notification->body())->toBe('Your garment is on the machine.');
});
