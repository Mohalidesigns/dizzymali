<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Order;
use App\Models\OrderProgressPhoto;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProgressPhotoAdded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Order $order,
        public readonly OrderProgressPhoto $photo,
    ) {}
}
