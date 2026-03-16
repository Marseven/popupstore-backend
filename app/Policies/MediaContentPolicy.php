<?php

namespace App\Policies;

use App\Models\MediaContent;
use App\Models\User;

class MediaContentPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, MediaContent $mediaContent): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->role->slug === 'super_admin' || $user->role->slug === 'manager';
    }

    public function update(User $user, MediaContent $mediaContent): bool
    {
        return $user->role->slug === 'super_admin' || $user->role->slug === 'manager';
    }

    public function delete(User $user, MediaContent $mediaContent): bool
    {
        return $user->role->slug === 'super_admin' || $user->role->slug === 'manager';
    }
}
