<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\OrderItem;
use App\Models\User;

class OrderItemPolicy
{
    public function view(User $user, OrderItem $item): bool
    {
        return $item->order->user_id === $user->id || $user->isBackOffice();
    }

    public function update(User $user, OrderItem $item): bool
    {
        if ($user->isBackOffice()) {
            return true;
        }

        return $item->order->user_id === $user->id && $item->order->isEditable();
    }

    public function delete(User $user, OrderItem $item): bool
    {
        return $item->order->user_id === $user->id && $item->order->isEditable();
    }
}
