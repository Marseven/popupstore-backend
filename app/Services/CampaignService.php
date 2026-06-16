<?php

namespace App\Services;

use App\Enums\EntitlementType;
use App\Models\Campaign;
use App\Models\CampaignEntitlement;
use App\Models\CampaignPoint;
use App\Models\Order;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CampaignService
{
    /**
     * Award campaign points for each campaign-team product in the order.
     *
     * Idempotent per order_id. Awards nothing if the campaign is not Active or
     * the order falls outside the campaign window. Points rule comes from the
     * campaign settings: 'per_item' (× quantity, default) or 'per_amount' (item total).
     */
    public function awardPoints(Order $order): void
    {
        if (CampaignPoint::where('order_id', $order->id)->exists()) {
            return;
        }

        $order->loadMissing('items.product.campaignTeam.campaign');

        DB::transaction(function () use ($order) {
            foreach ($order->items as $item) {
                $team = $item->product?->campaignTeam;
                $campaign = $team?->campaign;

                if (! $team || ! $campaign || ! $campaign->isAcceptingPoints($order->created_at)) {
                    continue;
                }

                $points = $this->pointsForItem($campaign, $item);
                if ($points <= 0) {
                    continue;
                }

                CampaignPoint::create([
                    'campaign_id' => $campaign->id,
                    'team_id' => $team->id,
                    'order_id' => $order->id,
                    'points' => $points,
                ]);

                $team->increment('points_total', $points);
            }

            Cache::forget($this->leaderboardCacheKey($order));
        });
    }

    /**
     * Issue entitlements (unique codes) for each campaign-team product purchased.
     * Idempotent per order_id.
     */
    public function issueEntitlements(Order $order): void
    {
        if (CampaignEntitlement::where('order_id', $order->id)->exists()) {
            return;
        }

        $order->loadMissing('items.product.campaignTeam');

        $teams = $order->items
            ->map(fn ($item) => $item->product?->campaignTeam)
            ->filter()
            ->unique('id');

        foreach ($teams as $team) {
            CampaignEntitlement::create([
                'order_id' => $order->id,
                'team_id' => $team->id,
                'type' => EntitlementType::Catalog,
                'code' => $this->uniqueCode(),
            ]);
        }
    }

    /** Teams ranked by denormalised points_total (cached 30s). */
    public function leaderboard(Campaign $campaign): \Illuminate\Support\Collection
    {
        return Cache::remember("campaign:{$campaign->id}:leaderboard", 30, function () use ($campaign) {
            return $campaign->teams()
                ->orderByDesc('points_total')
                ->orderBy('sort_order')
                ->get(['id', 'name', 'slug', 'artist_name', 'producer_name', 'color_accent', 'points_total'])
                ->values();
        });
    }

    private function pointsForItem(Campaign $campaign, $item): int
    {
        $rule = $campaign->settings['points_rule'] ?? 'per_item';

        return match ($rule) {
            'per_amount' => (int) round((float) $item->total),
            default => (int) $item->quantity,
        };
    }

    private function uniqueCode(): string
    {
        do {
            $code = strtoupper(Str::random(10));
        } while (CampaignEntitlement::where('code', $code)->exists());

        return $code;
    }

    private function leaderboardCacheKey(Order $order): string
    {
        $campaignId = optional($order->items->first()?->product?->campaignTeam?->campaign)->id;

        return "campaign:{$campaignId}:leaderboard";
    }
}
