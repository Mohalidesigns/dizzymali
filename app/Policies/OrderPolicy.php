<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;

/**
 * The single most likely real breach in a system like this is a customer
 * changing an ID in a URL and reading someone else's order — which carries
 * their measurements, their photographs and their address. Every method here
 * has a denial test.
 */
class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // Scoped to the user's own orders in the query, not here.
    }

    public function view(User $user, Order $order): bool
    {
        return $this->owns($user, $order) || $user->isBackOffice();
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Order $order): bool
    {
        if ($user->isBackOffice()) {
            return true;
        }

        // A customer may only edit their own order, and only before it is cut.
        return $this->owns($user, $order) && $order->isEditable();
    }

    public function delete(User $user, Order $order): bool
    {
        return $this->owns($user, $order) && $order->status === OrderStatus::Draft;
    }

    public function submit(User $user, Order $order): bool
    {
        return $this->owns($user, $order) && $order->isEditable();
    }

    public function pay(User $user, Order $order): bool
    {
        return $this->owns($user, $order);
    }

    public function cancel(User $user, Order $order): bool
    {
        return ($this->owns($user, $order) && $order->isEditable()) || $user->isAdmin();
    }

    /** Advancing the workshop pipeline is staff work, never the customer's. */
    public function advanceStage(User $user, Order $order): bool
    {
        return $user->isBackOffice();
    }

    public function manage(User $user, Order $order): bool
    {
        return $user->isBackOffice();
    }

    private function owns(User $user, Order $order): bool
    {
        return $order->user_id === $user->id;
    }
}
