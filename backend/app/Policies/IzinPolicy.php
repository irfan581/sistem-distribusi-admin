<?php

namespace App\Policies;

use App\Models\Izin;
use App\Models\User;

class IzinPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view_izin');
    }

    public function view(User $user, Izin $izin): bool
    {
        return $user->hasPermission('view_izin');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('view_izin');
    }

    public function update(User $user, Izin $izin): bool
    {
        return $user->hasPermission('approve_izin');
    }

    public function delete(User $user, Izin $izin): bool
    {
        return $user->hasPermission('delete_izin');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermission('delete_izin');
    }
}
