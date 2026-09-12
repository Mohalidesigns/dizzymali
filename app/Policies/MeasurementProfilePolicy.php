<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\MeasurementProfile;
use App\Models\User;

/** Body measurements are personal data under the NDPA 2023. Treat accordingly. */
class MeasurementProfilePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, MeasurementProfile $profile): bool
    {
        return $profile->user_id === $user->id || $user->isBackOffice();
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, MeasurementProfile $profile): bool
    {
        return $profile->user_id === $user->id || $user->isBackOffice();
    }

    public function delete(User $user, MeasurementProfile $profile): bool
    {
        return $profile->user_id === $user->id;
    }

    /**
     * Seeing that a review queue exists is a staff action.
     *
     * Separate from review() because Gate calls the ability with no model when
     * it is authorised against the class, and a method that requires one then
     * throws ArgumentCountError rather than denying.
     */
    public function reviewAny(User $user): bool
    {
        return $user->isBackOffice();
    }

    /** Transcribing an uploaded measurement sheet is a staff action. */
    public function review(User $user, MeasurementProfile $profile): bool
    {
        return $user->isBackOffice();
    }
}
