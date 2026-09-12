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

    /** Transcribing an uploaded measurement sheet is a staff action. */
    public function review(User $user, MeasurementProfile $profile): bool
    {
        return $user->isBackOffice();
    }
}
