<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    // Hanya owner dan developer yang boleh kelola akun admin
    private function hasSystemAccess(User $user): bool
    {
        return $user->isFullAccess();
    }

    public function viewAny(User $user): bool
    {
        return $this->hasSystemAccess($user);
    }

    public function view(User $user, User $model): bool
    {
        return $this->hasSystemAccess($user);
    }

    public function create(User $user): bool
    {
        return $this->hasSystemAccess($user);
    }

    public function update(User $user, User $model): bool
    {
        return $this->hasSystemAccess($user);
    }

    public function delete(User $user, User $model): bool
    {
        return $this->hasSystemAccess($user) && $user->id !== $model->id;
    }

    public function deleteAny(User $user): bool
    {
        return $this->hasSystemAccess($user);
    }
}
