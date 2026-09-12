<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Messaging\Drivers\CloudApiWhatsAppDriver;
use App\Domain\Messaging\Drivers\LogWhatsAppDriver;
use App\Domain\Messaging\WhatsAppDriver;
use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\ServiceProvider;

class MessagingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WhatsAppDriver::class, function (Application $app): WhatsAppDriver {
            $config = (array) $app['config']->get('notifications.whatsapp', []);
            $name = (string) ($config['default'] ?? 'log');
            $driver = (array) ($config['drivers'][$name] ?? []);

            if ($name === 'cloud_api') {
                return new CloudApiWhatsAppDriver(
                    phoneNumberId: $driver['phone_number_id'] ?? null,
                    accessToken: $driver['access_token'] ?? null,
                    templateNames: (array) ($config['templates'] ?? []),
                    apiVersion: (string) ($driver['api_version'] ?? 'v21.0'),
                    defaultLocale: (string) ($driver['default_locale'] ?? 'en'),
                );
            }

            // Default and fallback: log the message rather than send it, so the
            // whole path stays exercised while WhatsApp is on hold.
            return new LogWhatsAppDriver((string) ($driver['channel'] ?? 'stack'));
        });
    }

    public function boot(): void
    {
        Notification::extend(
            WhatsAppChannel::class,
            fn (Application $app) => new WhatsAppChannel($app->make(WhatsAppDriver::class)),
        );
    }
}
