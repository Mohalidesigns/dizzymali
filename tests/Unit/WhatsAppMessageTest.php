<?php

declare(strict_types=1);

use App\Domain\Messaging\Drivers\LogWhatsAppDriver;
use App\Domain\Messaging\WhatsAppMessage;

it('normalises a Nigerian number written the way people write it', function () {
    $message = new WhatsAppMessage('08012345678', 'order_submitted');

    expect($message->normalisedTo())->toBe('2348012345678');
});

it('strips punctuation from an international number', function () {
    expect((new WhatsAppMessage('+44 (0) 7700 900123', 'x'))->normalisedTo())
        ->toBe('4407700900123');
});

it('leaves an already-normalised number alone', function () {
    expect((new WhatsAppMessage('2348012345678', 'x'))->normalisedTo())
        ->toBe('2348012345678');
});

it('refers to a template by key, not by the name Meta approved', function () {
    // The codebase never hard-codes a provider's template name; that mapping
    // lives in config so approval is a .env change.
    $message = new WhatsAppMessage('2348012345678', 'payment_received', ['Ibrahim', '₦151,750.00']);

    expect($message->templateKey)->toBe('payment_received')
        ->and($message->parameters)->toBe(['Ibrahim', '₦151,750.00']);
});

it('reports the log driver as usable, so the path stays exercised while on hold', function () {
    $driver = new LogWhatsAppDriver;

    expect($driver->isConfigured())->toBeTrue()
        ->and($driver->send(new WhatsAppMessage('08012345678', 'order_submitted')))->toBeTrue();
});
