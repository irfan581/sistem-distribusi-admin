<?php

namespace App\Policies;

use App\Models\SalesVisitTarget;
use App\Models\User;

class VisitTargetPolicy
{
    // manage_pengaturan tetap diterima supaya role lama yang memakainya tidak kehilangan akses.
    public function viewAny(User $user): bool
    {
        return $user->isOwner()
            || $user->hasRoleOrChildOf('boss')
            || $user->hasPermission('view_target_kunjungan')
            || $user->hasPermission('manage_target_kunjungan')
            || $user->hasPermission('manage_pengaturan');
    }

    public function view(User $user, SalesVisitTarget $target): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->isOwner()
            || $user->hasRoleOrChildOf('boss')
            || $user->hasPermission('manage_target_kunjungan')
            || $user->hasPermission('manage_pengaturan');
    }

    public function update(User $user, SalesVisitTarget $target): bool
    {
        return $this->create($user);
    }

    public function bulkUpdate(User $user): bool
    {
        return $user->isOwner()
            || $user->hasRoleOrChildOf('boss')
            || $user->hasPermission('manage_target_kunjungan');
    }

    public function viewHistory(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function reset(User $user): bool
    {
        return $this->bulkUpdate($user);
    }
}
