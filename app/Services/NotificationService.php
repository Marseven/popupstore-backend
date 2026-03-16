<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Notifications\Notification;

class NotificationService
{
    public function notifyAdmins(Notification $notification): void
    {
        $admins = User::whereHas('role', function ($q) {
            $q->whereIn('slug', ['super_admin', 'manager']);
        })->where('is_active', true)->get();

        foreach ($admins as $admin) {
            $admin->notify($notification);
        }
    }
}
