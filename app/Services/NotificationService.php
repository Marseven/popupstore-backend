<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;

class NotificationService
{
    public function notifyAdmins(Notification $notification): void
    {
        $admins = User::with('pushSubscriptions')
            ->whereHas('role', function ($q) {
                $q->whereIn('slug', ['super_admin', 'manager']);
            })
            ->where('is_active', true)
            ->get();

        foreach ($admins as $admin) {
            $admin->notify($notification);
        }
    }

    /**
     * Notify the person who placed the order (guest or registered) on whatever
     * channels their contact details allow. Does nothing if no contact is known.
     */
    public function notifyOrderContact(Order $order, Notification $notification): void
    {
        $email = $order->guest_email ?: $order->user?->email;
        $phone = $order->guest_phone ?: ($order->shipping_phone ?: $order->user?->phone);

        $email = $email ?: null;
        $phone = $phone ?: null;

        if (! $email && ! $phone) {
            return;
        }

        NotificationFacade::route('mail', $email)
            ->route('sms', $phone)
            ->notify($notification);
    }
}
