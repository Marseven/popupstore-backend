<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->slug === 'super_admin';
    }

    public function view(User $user, User $model): bool
    {
        return $user->role->slug === 'super_admin' || $user->id === $model->id;
    }

    public function update(User $user, User $model): bool
    {
        if ($user->role->slug === 'super_admin') {
            return true;
        }

        return $user->id === $model->id;
    }

    public function delete(User $user, User $model): bool
    {
        return $user->role->slug === 'super_admin' && $user->id !== $model->id;
    }
}
