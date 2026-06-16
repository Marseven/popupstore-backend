<?php

namespace App\Services;

use App\Enums\CollectionType;
use App\Enums\PayoutStatus;
use App\Exceptions\BusinessException;
use App\Models\Collection;
use App\Models\Order;
use App\Models\PayoutEntry;

class RevenueSplitService
{
    /**
     * Validate that beneficiary percentages don't exceed 100%.
     * The platform implicitly keeps (100 − sum).
     */
    public function validateShares(Collection $collection): void
    {
        $sum = (float) $collection->revenueShares()->sum('percentage');

        if ($sum > 100.0) {
            throw new BusinessException(
                "La somme des parts ({$sum}%) dépasse 100%.",
                'REVENUE_SHARE_OVER_100'
            );
        }
    }

    /**
     * Record payout entries for every partner-collection sale in the order.
     *
     * Money is XAF integers: net = floor(gross × pct / 100). The flooring
     * remainder is retained by the platform (commission = gross − net), so the
     * sum of beneficiary nets never exceeds the gross — deterministic, no cents.
     *
     * Idempotent: if any payout entry already exists for the order, do nothing.
     */
    public function recordForOrder(Order $order): void
    {
        if (PayoutEntry::where('order_id', $order->id)->exists()) {
            return;
        }

        // Gross per partner collection = sum of its order-item totals in this order.
        $order->loadMissing('items.product.collection.revenueShares');

        $grossByCollection = [];
        foreach ($order->items as $item) {
            $collection = $item->product?->collection;
            if (! $collection || $collection->type !== CollectionType::Partner) {
                continue;
            }
            $grossByCollection[$collection->id] = ($grossByCollection[$collection->id] ?? 0)
                + (int) round((float) $item->total);
        }

        foreach ($grossByCollection as $collectionId => $gross) {
            $collection = Collection::with('revenueShares')->find($collectionId);
            if (! $collection) {
                continue;
            }

            foreach ($collection->revenueShares as $share) {
                $net = intdiv($gross * (int) round((float) $share->percentage * 100), 10000);
                PayoutEntry::create([
                    'order_id' => $order->id,
                    'collection_id' => $collection->id,
                    'revenue_share_id' => $share->id,
                    'gross_amount' => $gross,
                    'commission_amount' => $gross - $net,
                    'net_amount' => $net,
                    'status' => PayoutStatus::Pending,
                ]);
            }
        }
    }
}
