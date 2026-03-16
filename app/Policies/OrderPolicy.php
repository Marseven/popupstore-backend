<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        if ($user->role->slug === 'super_admin' || $user->role->slug === 'manager') {
            return true;
        }

        return $order->user_id === $user->id;
    }

    public function create(?User $user): bool
    {
        return true;
    }

    public function update(User $user, Order $order): bool
    {
        return $user->role->slug === 'super_admin' || $user->role->slug === 'manager';
    }

    public function delete(User $user, Order $order): bool
    {
        return $user->role->slug === 'super_admin';
    }
}
