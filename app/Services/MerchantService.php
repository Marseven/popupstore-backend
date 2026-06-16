<?php

namespace App\Services;

use App\Enums\CollectionType;
use App\Enums\MerchantStatus;
use App\Exceptions\BusinessException;
use App\Models\Collection;
use App\Models\MerchantProfile;
use App\Models\Order;
use App\Models\PayoutEntry;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Owner-scoping for merchants. Every merchant-facing query MUST go through one
 * of the scoped* builders here — they constrain to collections the user owns,
 * with the same rigor as the cart dual-mode invariants. A missing filter is a
 * data-isolation breach, not a cosmetic bug.
 */
class MerchantService
{
    /** IDs of the collections owned by this merchant. */
    public function ownedCollectionIds(User $user): array
    {
        return $user->ownedCollections()->pluck('id')->all();
    }

    /** Products that belong to the merchant's collections. */
    public function scopedProducts(User $user): Builder
    {
        return Product::whereIn('collection_id', $this->ownedCollectionIds($user));
    }

    /** A single product the merchant owns, or fail (404) — never another merchant's. */
    public function findOwnedProduct(User $user, int $productId): Product
    {
        return $this->scopedProducts($user)->findOrFail($productId);
    }

    /** Orders containing at least one of the merchant's products. */
    public function scopedOrders(User $user): Builder
    {
        $collectionIds = $this->ownedCollectionIds($user);

        return Order::whereHas('items.product', function ($q) use ($collectionIds) {
            $q->whereIn('collection_id', $collectionIds);
        });
    }

    /** Payout entries for the merchant's collections. */
    public function scopedPayouts(User $user): Builder
    {
        return PayoutEntry::whereIn('collection_id', $this->ownedCollectionIds($user));
    }

    public function apply(User $user, array $data): MerchantProfile
    {
        if ($user->merchantProfile()->exists()) {
            throw new BusinessException('Une demande marchand existe déjà pour ce compte.', 'MERCHANT_ALREADY_APPLIED', 409);
        }

        return $user->merchantProfile()->create([
            'business_name' => $data['business_name'],
            'rccm_nif' => $data['rccm_nif'] ?? null,
            'payout_phone' => $data['payout_phone'],
            'payout_provider' => $data['payout_provider'],
            'status' => MerchantStatus::Pending,
        ]);
    }

    /**
     * Approve a merchant: assign the merchant role and give them a partner
     * collection to manage. Idempotent on the collection (won't duplicate).
     */
    public function approve(MerchantProfile $profile): MerchantProfile
    {
        return DB::transaction(function () use ($profile) {
            $merchantRole = Role::firstOrCreate(
                ['slug' => 'merchant'],
                ['name' => 'Marchand', 'description' => 'Marchand partenaire (self-service)']
            );

            $user = $profile->user;
            $user->update(['role_id' => $merchantRole->id]);

            if (! $user->ownedCollections()->exists()) {
                Collection::create([
                    'name' => $profile->business_name,
                    'slug' => Str::slug($profile->business_name).'-'.Str::random(6),
                    'type' => CollectionType::Partner,
                    'owner_user_id' => $user->id,
                    'is_active' => true,
                ]);
            }

            $profile->update(['status' => MerchantStatus::Approved, 'approved_at' => now()]);

            return $profile->fresh();
        });
    }

    public function suspend(MerchantProfile $profile): MerchantProfile
    {
        $profile->update(['status' => MerchantStatus::Suspended]);

        return $profile->fresh();
    }
}
