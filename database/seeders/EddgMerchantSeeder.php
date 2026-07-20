<?php

namespace Database\Seeders;

use App\Enums\CollectionType;
use App\Enums\MerchantStatus;
use App\Models\Collection;
use App\Models\MediaContent;
use App\Models\MerchantProfile;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\RevenueShare;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Seeds the EDDG merchant from the signed protocole d'accord + kit marchand
 * (90 % merchant / 10 % platform, collection EDDG.2512).
 *
 * Idempotent — safe to re-run. Media files and product images still have to be
 * uploaded through the admin (this only creates the records to attach them to).
 */
class EddgMerchantSeeder extends Seeder
{
    public function run(): void
    {
        $merchantRole = Role::firstOrCreate(
            ['slug' => 'merchant'],
            ['name' => 'Marchand', 'description' => 'Marchand partenaire (self-service)']
        );

        // 1. Merchant account
        $user = User::withTrashed()->firstOrNew(['email' => 'edfile96@gmail.com']);
        $user->fill([
            'first_name' => 'Eddy-Gérard',
            'last_name' => 'Adembe',
            'phone' => '077080996',
            'role_id' => $merchantRole->id,
            'is_active' => true,
        ]);
        if (! $user->exists) {
            $user->password = Hash::make(Str::random(16)); // merchant resets via "mot de passe oublié"
        }
        $user->deleted_at = null;
        $user->save();

        // 2. Merchant profile (approved — contract signed 09/07/2026)
        MerchantProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'business_name' => 'EDDG',
                'rccm_nif' => null,
                'payout_phone' => '074737974',
                'payout_provider' => 'airtelmoney',
                'status' => MerchantStatus::Approved,
                'approved_at' => now(),
            ]
        );

        // 3. Collection / univers
        $collection = Collection::updateOrCreate(
            ['slug' => 'eddg-2512'],
            [
                'name' => 'EDDG.2512',
                'description' => 'Juste comme vous.',
                'type' => CollectionType::Partner,
                'owner_user_id' => $user->id,
                'is_active' => true,
                'sort_order' => 0,
            ]
        );

        // 4. Revenue split — 90 % merchant, platform keeps the remaining 10 %
        RevenueShare::updateOrCreate(
            ['collection_id' => $collection->id, 'beneficiary_label' => 'EDDG'],
            [
                'payout_phone' => '074737974',
                'payout_provider' => 'airtelmoney',
                'percentage' => 90,
            ]
        );

        // 5. Exclusive content unlocked by the product QR (1 audio + 1 video).
        //    file_path is a placeholder — upload the real files in Admin → Médias.
        $audio = MediaContent::updateOrCreate(
            ['slug' => 'eddg-juste-comme-vous'],
            [
                'collection_id' => $collection->id,
                'title' => 'EDDG — Juste comme vous',
                'description' => 'Titre exclusif EDDG.',
                'type' => 'audio',
                'file_path' => 'media/eddg/juste-comme-vous.mp3',
                'is_active' => true,
            ]
        );

        $video = MediaContent::updateOrCreate(
            ['slug' => 'eddg-video-descriptive'],
            [
                'collection_id' => $collection->id,
                'title' => 'EDDG — Vidéo descriptive',
                'description' => 'Présentation de la marque EDDG.',
                'type' => 'video',
                'file_path' => 'media/eddg/presentation.mp4',
                'is_active' => true,
            ]
        );

        $brand = 'EDDG est une marque gabonaise qui centralise son image de marque sur la symbolique '
            .'représentative des masques comme identité culturelle à forte valeur ajoutée.';

        // 6. Products
        $tshirt = $this->product([
            'slug' => 'eddg-t-shirt-i-love-gabon',
            'sku' => 'EDDG-TS-001',
            'name' => 'T-shirt EDDG — I Love Gabon',
            'description' => $brand.' Tee-shirt confectionné à la demande.',
            'category_id' => 1, // T-shirts
            'collection_id' => $collection->id,
            'price' => 12000,
            'colors' => ['Bleu', 'Blanc', 'Noir', 'Rouge'],
            'is_featured' => true,
        ], [1, 2, 3, 4, 5, 6]); // XS → XXL

        $cap = $this->product([
            'slug' => 'eddg-casquette',
            'sku' => 'EDDG-CAP-001',
            'name' => 'Casquette EDDG',
            'description' => $brand,
            'category_id' => 3, // Casquettes
            'collection_id' => $collection->id,
            'price' => 5000,
            'colors' => ['Blanc', 'Noir'],
            'is_featured' => false,
        ], [3, 4]); // M, L

        // 7. One QR per product unlocks both media
        foreach ([$tshirt, $cap] as $product) {
            $product->mediaContents()->sync([
                $audio->id => ['sort_order' => 0],
                $video->id => ['sort_order' => 1],
            ]);
        }

        $this->command?->info('EDDG merchant seeded: collection EDDG.2512, 2 products, 90/10 split.');
        $this->command?->warn('À faire dans l\'admin : uploader les fichiers audio/vidéo et les images produits.');
    }

    /** @param  array<int>  $sizeIds */
    private function product(array $attributes, array $sizeIds): Product
    {
        $slug = $attributes['slug'];
        unset($attributes['slug']);

        $product = Product::withTrashed()->firstOrNew(['slug' => $slug]);
        $product->fill([...$attributes, 'slug' => $slug, 'is_active' => true]);
        $product->deleted_at = null;
        $product->save();

        foreach ($sizeIds as $sizeId) {
            ProductStock::updateOrCreate(
                ['product_id' => $product->id, 'size_id' => $sizeId],
                ['quantity' => 50, 'low_stock_threshold' => 5]
            );
        }

        return $product;
    }
}
