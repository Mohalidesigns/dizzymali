<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isBackOffice();
    }

    public function view(User $user, Payment $payment): bool
    {
        return $payment->order->user_id === $user->id || $user->isBackOffice();
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function refund(User $user, Payment $payment): bool
    {
        return $user->isAdmin();
    }
}
