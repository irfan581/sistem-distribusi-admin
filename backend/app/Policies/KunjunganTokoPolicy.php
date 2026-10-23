<?php

namespace App\Policies;

use App\Models\User;
use App\Models\KunjunganToko;

class KunjunganTokoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view_kunjungan');
    }

    public function view(User $user, KunjunganToko $kunjunganToko): bool
    {
        return $user->hasPermission('view_kunjungan');
    }

    public function update(User $user, KunjunganToko $kunjunganToko): bool
    {
        return $user->hasPermission('edit_kunjungan');
    }

    public function delete(User $user, KunjunganToko $kunjunganToko): bool
    {
        return $user->hasPermission('delete_kunjungan');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasPermission('delete_kunjungan');
    }
}
