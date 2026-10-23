<?php

namespace App\Policies;

use App\Models\User;
use App\Models\DaftarMuat;

class DaftarMuatPolicy
{
    public function create(User $user): bool
    {
        return $user->hasPermission('create_pengiriman');
    }

    public function update(User $user, DaftarMuat $daftarMuat): bool
    {
        return $user->hasPermission('edit_pengiriman');
    }

    public function delete(User $user, DaftarMuat $daftarMuat): bool
    {
        return $user->hasPermission('delete_pengiriman');
    }
}
