<?php

namespace App\Policies;

use App\Models\Presensi;
use App\Models\User;

class PresensiPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view_presensi');
    }

    public function view(User $user, Presensi $presensi): bool
    {
        return $user->hasPermission('view_presensi');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('edit_presensi');
    }

    public function update(User $user, Presensi $presensi): bool
    {
        return $user->hasPermission('edit_presensi');
    }

    public function delete(User $user, Presensi $presensi): bool
    {
        return $user->hasPermission('delete_presensi');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermission('delete_presensi');
    }
}
