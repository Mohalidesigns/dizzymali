<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\OrderPaid;
use App\Events\OrderShipped;
use App\Events\OrderStageChanged;
use App\Events\OrderSubmitted;
use App\Events\ProgressPhotoAdded;
use App\Listeners\SendOrderNotifications;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(OrderSubmitted::class, [SendOrderNotifications::class, 'handleSubmitted']);
        Event::listen(OrderPaid::class, [SendOrderNotifications::class, 'handlePaid']);
        Event::listen(OrderStageChanged::class, [SendOrderNotifications::class, 'handleStageChanged']);
        Event::listen(ProgressPhotoAdded::class, [SendOrderNotifications::class, 'handleProgressPhoto']);
        Event::listen(OrderShipped::class, [SendOrderNotifications::class, 'handleShipped']);
    }
}
