<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Activitylog\Models\Activity;

class ActivityPolicy
{
    /**
     * Hanya owner dan developer yang boleh melihat activity log.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role?->nama, ['owner', 'developer']);
    }

    public function view(User $user, Activity $activity): bool
    {
        return $this->viewAny($user);
    }
}
