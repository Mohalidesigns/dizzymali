<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Shared policy for catalogue records — garment types, fabrics, variants,
 * options, yardage rules, currencies, shipping. Anyone may read the catalogue;
 * only back-office staff may change it, and only admins may change prices.
 */
class CatalogueAdminPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user): bool
    {
        return $user->isAdmin();
    }
}
