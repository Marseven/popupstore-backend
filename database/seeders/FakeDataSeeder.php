<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\CartItem;
use App\Models\Collection;
use App\Models\MediaContent;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductImage;
use App\Models\ProductStock;
use App\Models\Size;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class FakeDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Seeding users...');
        $users = $this->seedUsers();

        $this->command->info('Seeding categories...');
        $categories = $this->seedCategories();

        $this->command->info('Seeding collections...');
        $collections = $this->seedCollections();

        $this->command->info('Seeding media content...');
        $media = $this->seedMediaContent($collections);

        $this->command->info('Seeding products...');
        $products = $this->seedProducts($categories, $collections, $media);

        $this->command->info('Seeding orders...');
        $this->seedOrders($users, $products);

        $this->command->info('Seeding cart items...');
        $this->seedCartItems($users, $products);

        $this->command->info('Fake data seeded successfully!');
    }

    /**
     * Create users with various roles.
     */
    private function seedUsers(): array
    {
        // Manager (find by email or phone to handle rebrand)
        $managerData = [
            'role_id' => \App\Models\Role::where('slug', 'manager')->value('id') ?? 3,
            'first_name' => 'Jean',
            'last_name' => 'Mouloungui',
            'email' => 'manager@popupstore.ga',
            'phone' => '+24177000001',
            'password' => Hash::make('password'),
            'is_active' => true,
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
            'deleted_at' => null,
        ];
        $manager = User::withTrashed()
            ->where('email', 'manager@popupstore.ga')
            ->orWhere('phone', '+24177000001')
            ->first();
        if ($manager) {
            $manager->update($managerData);
        } else {
            $manager = User::create($managerData);
        }

        // Named customers (Gabonese names)
        $namedCustomers = [
            ['first_name' => 'Pamela', 'last_name' => 'Ndong', 'email' => 'pamela@gmail.com', 'phone' => '+24177100001'],
            ['first_name' => 'Kevin', 'last_name' => 'Obame', 'email' => 'kevin.obame@yahoo.fr', 'phone' => '+24166200002'],
            ['first_name' => 'Christelle', 'last_name' => 'Mba', 'email' => 'christelle.mba@gmail.com', 'phone' => '+24174300003'],
            ['first_name' => 'Brice', 'last_name' => 'Nzoghe', 'email' => 'brice.nzoghe@hotmail.com', 'phone' => '+24177400004'],
            ['first_name' => 'Ornella', 'last_name' => 'Ondo', 'email' => 'ornella.ondo@gmail.com', 'phone' => '+24166500005'],
            ['first_name' => 'Steeve', 'last_name' => 'Ntoutoume', 'email' => 'steeve.n@yahoo.fr', 'phone' => '+24177600006'],
            ['first_name' => 'Grace', 'last_name' => 'Bivigou', 'email' => 'grace.biv@gmail.com', 'phone' => '+24174700007'],
            ['first_name' => 'Yanick', 'last_name' => 'Essono', 'email' => 'yanick.essono@gmail.com', 'phone' => '+24177800008'],
            ['first_name' => 'Merveille', 'last_name' => 'Mboumba', 'email' => 'merveille.m@yahoo.fr', 'phone' => '+24166900009'],
            ['first_name' => 'Patrick', 'last_name' => 'Biyoghe', 'email' => 'patrick.b@gmail.com', 'phone' => '+24177000010'],
        ];

        $customerRoleId = \App\Models\Role::where('slug', 'customer')->value('id') ?? 2;
        $customers = [];
        foreach ($namedCustomers as $data) {
            $existing = User::withTrashed()
                ->where('email', $data['email'])
                ->orWhere('phone', $data['phone'])
                ->first();

            $userData = array_merge($data, [
                'role_id' => $customerRoleId,
                'password' => Hash::make('password'),
                'is_active' => true,
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
                'deleted_at' => null,
            ]);

            if ($existing) {
                $existing->update($userData);
                $customers[] = $existing;
            } else {
                $customers[] = User::create($userData);
            }
        }

        // Random extra customers — only create if we have fewer than 20 total customers
        $existingCustomerCount = User::where('role_id', $customerRoleId)->count();
        $randomCustomers = collect();
        if ($existingCustomerCount < 20) {
            $needed = 20 - $existingCustomerCount;
            $randomCustomers = User::factory()->count(max(0, $needed))->create();
        }

        return [
            'manager' => $manager,
            'customers' => collect($customers)->merge($randomCustomers),
        ];
    }

    /**
     * Create product categories.
     */
    private function seedCategories(): array
    {
        $categories = [
            [
                'name' => 'T-shirts',
                'slug' => 't-shirts',
                'description' => 'T-shirts graphiques avec designs exclusifs',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Hoodies',
                'slug' => 'hoodies',
                'description' => 'Sweats à capuche premium',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Casquettes',
                'slug' => 'casquettes',
                'description' => 'Casquettes et bobs streetwear',
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Accessoires',
                'slug' => 'accessoires',
                'description' => 'Sacs, bracelets, stickers et plus',
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'name' => 'Pantalons',
                'slug' => 'pantalons',
                'description' => 'Joggers et pantalons streetwear',
                'is_active' => true,
                'sort_order' => 5,
            ],
            [
                'name' => 'Vinyles & CD',
                'slug' => 'vinyles-cd',
                'description' => 'Supports physiques avec QR exclusif',
                'is_active' => true,
                'sort_order' => 6,
            ],
        ];

        $created = [];
        foreach ($categories as $cat) {
            $created[] = ProductCategory::updateOrCreate(['slug' => $cat['slug']], $cat);
        }

        return $created;
    }

    /**
     * Create collections.
     */
    private function seedCollections(): array
    {
        $collections = [
            [
                'name' => 'Afro Vibes Vol.1',
                'slug' => 'afro-vibes-vol1',
                'description' => 'Première collection inspirée des rythmes afro',
                'cover_image' => 'https://images.unsplash.com/photo-1516450360452-9312f5e86fc7?w=1200&h=600&fit=crop',
                'color_accent' => '#7c3aed',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Roots & Rhymes',
                'slug' => 'roots-and-rhymes',
                'description' => 'Retour aux sources avec du rap conscient',
                'cover_image' => 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=1200&h=600&fit=crop',
                'color_accent' => '#06b6d4',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Urban Gabon',
                'slug' => 'urban-gabon',
                'description' => 'Le Gabon urbain en son et en image',
                'cover_image' => 'https://images.unsplash.com/photo-1514565131-fce0801e5785?w=1200&h=600&fit=crop',
                'color_accent' => '#d97706',
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Nuit Tropicale',
                'slug' => 'nuit-tropicale',
                'description' => 'Sons nocturnes et ambiances tropicales',
                'cover_image' => 'https://images.unsplash.com/photo-1470225620780-dba8ba36b745?w=1200&h=600&fit=crop',
                'color_accent' => '#ec4899',
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'name' => 'Bantou Heritage',
                'slug' => 'bantou-heritage',
                'description' => 'Hommage aux traditions bantoues',
                'cover_image' => 'https://images.unsplash.com/photo-1590845947670-c009801ffa74?w=1200&h=600&fit=crop',
                'color_accent' => '#10b981',
                'is_active' => true,
                'sort_order' => 5,
            ],
        ];

        $created = [];
        foreach ($collections as $col) {
            $created[] = Collection::withTrashed()->updateOrCreate(['slug' => $col['slug']], array_merge($col, ['deleted_at' => null]));
        }

        return $created;
    }

    /**
     * Create media content linked to collections.
     */
    private function seedMediaContent(array $collections): array
    {
        $mediaItems = [
            // Afro Vibes Vol.1
            ['collection_index' => 0, 'title' => 'Libreville City Groove', 'type' => 'audio', 'duration' => 234],
            ['collection_index' => 0, 'title' => 'Danse Mandji', 'type' => 'audio', 'duration' => 198],
            ['collection_index' => 0, 'title' => 'Afro Vibes Mixtape', 'type' => 'video', 'duration' => 420],
            ['collection_index' => 0, 'title' => 'Sunset Oyem', 'type' => 'audio', 'duration' => 267],
            // Roots & Rhymes
            ['collection_index' => 1, 'title' => 'Paroles de Vieux', 'type' => 'audio', 'duration' => 312],
            ['collection_index' => 1, 'title' => 'Freestyle Akanda', 'type' => 'video', 'duration' => 345],
            ['collection_index' => 1, 'title' => 'Conscience Noire', 'type' => 'audio', 'duration' => 275],
            ['collection_index' => 1, 'title' => 'Les Racines Parlent', 'type' => 'audio', 'duration' => 289],
            // Urban Gabon
            ['collection_index' => 2, 'title' => 'PK Express', 'type' => 'audio', 'duration' => 203],
            ['collection_index' => 2, 'title' => 'Mbolo na Mbolo', 'type' => 'audio', 'duration' => 241],
            ['collection_index' => 2, 'title' => 'Documentaire: Sons du 241', 'type' => 'video', 'duration' => 1800],
            ['collection_index' => 2, 'title' => 'Nkembo Flow', 'type' => 'audio', 'duration' => 218],
            // Nuit Tropicale
            ['collection_index' => 3, 'title' => 'Minuit Libreville', 'type' => 'audio', 'duration' => 256],
            ['collection_index' => 3, 'title' => 'Tropical Session Live', 'type' => 'video', 'duration' => 2700],
            ['collection_index' => 3, 'title' => 'Bord de Mer', 'type' => 'audio', 'duration' => 223],
            // Bantou Heritage
            ['collection_index' => 4, 'title' => 'Tam-Tam Digital', 'type' => 'audio', 'duration' => 287],
            ['collection_index' => 4, 'title' => 'Mvet Moderne', 'type' => 'audio', 'duration' => 341],
            ['collection_index' => 4, 'title' => 'Heritage Clip Officiel', 'type' => 'video', 'duration' => 298],
            ['collection_index' => 4, 'title' => 'Ngombi Electro', 'type' => 'audio', 'duration' => 195],
            ['collection_index' => 4, 'title' => 'La Voix des Anciens', 'type' => 'audio', 'duration' => 362],
        ];

        $created = [];
        foreach ($mediaItems as $item) {
            $collection = $collections[$item['collection_index']];
            $slug = Str::slug($item['title']);

            $media = MediaContent::withTrashed()->updateOrCreate(
                ['slug' => $slug],
                [
                    'collection_id' => $collection->id,
                    'uuid' => Str::uuid()->toString(),
                    'title' => $item['title'],
                    'slug' => $slug,
                    'description' => "Contenu exclusif de la collection {$collection->name}.",
                    'type' => $item['type'],
                    'file_path' => "media/{$item['type']}s/{$slug}.mp" . ($item['type'] === 'video' ? '4' : '3'),
                    'file_size' => $item['type'] === 'video' ? rand(50000000, 500000000) : rand(3000000, 12000000),
                    'duration' => $item['duration'],
                    'play_count' => rand(0, 5000),
                    'is_active' => true,
                    'deleted_at' => null,
                ]
            );

            $created[] = $media;
        }

        return $created;
    }

    /**
     * Create products with images and stock.
     */
    private function seedProducts(array $categories, array $collections, array $media): array
    {
        $sizes = Size::all();

        // Product image URLs from Unsplash (free, no attribution required)
        $images = [
            // T-shirts (8)
            'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?w=600&h=600&fit=crop',
            'https://images.unsplash.com/photo-1583743814966-8936f5b7be1a?w=600&h=600&fit=crop',
            'https://images.unsplash.com/photo-1576566588028-4147f3842f27?w=600&h=600&fit=crop',
            'https://images.unsplash.com/photo-1618354691373-d851c5c3a990?w=600&h=600&fit=crop',
            'https://images.unsplash.com/photo-1529374255404-311a2a4f1fd9?w=600&h=600&fit=crop',
            'https://images.unsplash.com/photo-1503342217505-b0a15ec3261c?w=600&h=600&fit=crop',
            'https://images.unsplash.com/photo-1562157873-818bc0726f68?w=600&h=600&fit=crop',
            'https://images.unsplash.com/photo-1622445275463-afa2ab738c34?w=600&h=600&fit=crop',
            // Hoodies (4)
            'https://images.unsplash.com/photo-1556821840-3a63f95609a7?w=600&h=600&fit=crop',
            'https://images.unsplash.com/photo-1620799140408-edc6dcb6d633?w=600&h=600&fit=crop',
            'https://images.unsplash.com/photo-1578768079470-f4e27e38e7bb?w=600&h=600&fit=crop',
            'https://images.unsplash.com/photo-1614975059251-992f11792571?w=600&h=600&fit=crop',
            // Casquettes (4)
            'https://images.unsplash.com/photo-1588850561407-ed78c334e67a?w=600&h=600&fit=crop',
            'https://images.unsplash.com/photo-1534215754734-18e55d13e346?w=600&h=600&fit=crop',
            'https://images.unsplash.com/photo-1572307480813-ceb0e59d8325?w=600&h=600&fit=crop',
            'https://images.unsplash.com/photo-1521369909029-2afed882baee?w=600&h=600&fit=crop',
            // Accessoires (5)
            'https://images.unsplash.com/photo-1544816155-12df9643f363?w=600&h=600&fit=crop',
            'https://images.unsplash.com/photo-1573408301185-9146fe634ad0?w=600&h=600&fit=crop',
            'https://images.unsplash.com/photo-1572804500434-6a10e0e29900?w=600&h=600&fit=crop',
            'https://images.unsplash.com/photo-1514228742587-6b1558fcca3d?w=600&h=600&fit=crop',
            'https://images.unsplash.com/photo-1513364776144-60967b0f800f?w=600&h=600&fit=crop',
            // Pantalons (2)
            'https://images.unsplash.com/photo-1552902865-b72c031ac5ea?w=600&h=600&fit=crop',
            'https://images.unsplash.com/photo-1624378439575-d8705ad7ae80?w=600&h=600&fit=crop',
            // Vinyles & CD (3)
            'https://images.unsplash.com/photo-1539375665275-f9de415ef9ac?w=600&h=600&fit=crop',
            'https://images.unsplash.com/photo-1461360228754-6e81c478b882?w=600&h=600&fit=crop',
            'https://images.unsplash.com/photo-1603048588665-791ca8aea617?w=600&h=600&fit=crop',
        ];

        $products = [
            // T-shirts
            ['cat' => 0, 'col' => 0, 'name' => 'Tee Afro Vibes Classic', 'price' => 8500, 'compare' => 10000, 'featured' => true, 'img' => $images[0]],
            ['cat' => 0, 'col' => 1, 'name' => 'Tee Roots & Rhymes', 'price' => 9000, 'compare' => null, 'featured' => true, 'img' => $images[1]],
            ['cat' => 0, 'col' => 2, 'name' => 'Tee PK Express', 'price' => 7500, 'compare' => 9000, 'featured' => false, 'img' => $images[2]],
            ['cat' => 0, 'col' => 3, 'name' => 'Tee Nuit Tropicale', 'price' => 8500, 'compare' => null, 'featured' => true, 'img' => $images[3]],
            ['cat' => 0, 'col' => 4, 'name' => 'Tee Bantou Heritage', 'price' => 9500, 'compare' => 12000, 'featured' => true, 'img' => $images[4]],
            ['cat' => 0, 'col' => null, 'name' => 'Tee Popup Logo Noir', 'price' => 7000, 'compare' => null, 'featured' => true, 'img' => $images[5]],
            ['cat' => 0, 'col' => null, 'name' => 'Tee Popup Logo Blanc', 'price' => 7000, 'compare' => null, 'featured' => false, 'img' => $images[6]],
            ['cat' => 0, 'col' => 2, 'name' => 'Tee 241 Represent', 'price' => 8000, 'compare' => null, 'featured' => false, 'img' => $images[7]],
            // Hoodies
            ['cat' => 1, 'col' => 0, 'name' => 'Hoodie Afro Vibes', 'price' => 18000, 'compare' => 22000, 'featured' => true, 'img' => $images[8]],
            ['cat' => 1, 'col' => 1, 'name' => 'Hoodie Roots Noir', 'price' => 17500, 'compare' => null, 'featured' => false, 'img' => $images[9]],
            ['cat' => 1, 'col' => 4, 'name' => 'Hoodie Bantou Gold', 'price' => 19500, 'compare' => null, 'featured' => true, 'img' => $images[10]],
            ['cat' => 1, 'col' => null, 'name' => 'Hoodie Popup Classic', 'price' => 16000, 'compare' => null, 'featured' => false, 'img' => $images[11]],
            // Casquettes
            ['cat' => 2, 'col' => 0, 'name' => 'Cap Afro Vibes', 'price' => 5500, 'compare' => null, 'featured' => false, 'img' => $images[12]],
            ['cat' => 2, 'col' => 2, 'name' => 'Cap Urban Gabon Snapback', 'price' => 6000, 'compare' => 7500, 'featured' => true, 'img' => $images[13]],
            ['cat' => 2, 'col' => null, 'name' => 'Bob Popup Tropical', 'price' => 5000, 'compare' => null, 'featured' => false, 'img' => $images[14]],
            ['cat' => 2, 'col' => null, 'name' => 'Cap Popup Brodée', 'price' => 6500, 'compare' => null, 'featured' => false, 'img' => $images[15]],
            // Accessoires
            ['cat' => 3, 'col' => null, 'name' => 'Tote Bag Popup', 'price' => 4500, 'compare' => null, 'featured' => false, 'img' => $images[16]],
            ['cat' => 3, 'col' => 0, 'name' => 'Bracelet Afro Vibes', 'price' => 3000, 'compare' => null, 'featured' => false, 'img' => $images[17]],
            ['cat' => 3, 'col' => null, 'name' => 'Pack Stickers Popup (x10)', 'price' => 2000, 'compare' => null, 'featured' => false, 'img' => $images[18]],
            ['cat' => 3, 'col' => null, 'name' => 'Mug Popup Store', 'price' => 3500, 'compare' => null, 'featured' => false, 'img' => $images[19]],
            ['cat' => 3, 'col' => 4, 'name' => 'Poster Bantou Heritage A3', 'price' => 4000, 'compare' => 5000, 'featured' => false, 'img' => $images[20]],
            // Pantalons
            ['cat' => 4, 'col' => 0, 'name' => 'Jogger Afro Vibes', 'price' => 14000, 'compare' => null, 'featured' => false, 'img' => $images[21]],
            ['cat' => 4, 'col' => null, 'name' => 'Jogger Popup Classic Noir', 'price' => 12500, 'compare' => null, 'featured' => false, 'img' => $images[22]],
            // Vinyles & CD
            ['cat' => 5, 'col' => 0, 'name' => 'Vinyle Afro Vibes Vol.1', 'price' => 15000, 'compare' => null, 'featured' => true, 'img' => $images[23]],
            ['cat' => 5, 'col' => 1, 'name' => 'CD Roots & Rhymes', 'price' => 5000, 'compare' => null, 'featured' => false, 'img' => $images[24]],
            ['cat' => 5, 'col' => 4, 'name' => 'Vinyle Bantou Heritage', 'price' => 15000, 'compare' => 18000, 'featured' => false, 'img' => $images[25]],
        ];

        $created = [];
        $sortOrder = 1;

        foreach ($products as $p) {
            $slug = Str::slug($p['name']);
            $category = $categories[$p['cat']];
            $collection = $p['col'] !== null ? $collections[$p['col']] : null;

            // Link a random media from the same collection
            $mediaContentId = null;
            if ($collection) {
                $collectionMedia = collect($media)->filter(fn ($m) => $m->collection_id === $collection->id);
                if ($collectionMedia->isNotEmpty()) {
                    $mediaContentId = $collectionMedia->random()->id;
                }
            }

            $product = Product::withTrashed()->updateOrCreate(
                ['slug' => $slug],
                [
                    'category_id' => $category->id,
                    'collection_id' => $collection?->id,
                    'media_content_id' => $mediaContentId,
                    'sku' => 'POP-' . strtoupper(Str::random(6)),
                    'name' => $p['name'],
                    'slug' => $slug,
                    'description' => $this->generateProductDescription($p['name'], $category->name, $collection?->name),
                    'price' => $p['price'],
                    'compare_price' => $p['compare'],
                    'cost_price' => round($p['price'] * 0.4),
                    'is_active' => true,
                    'is_featured' => $p['featured'],
                    'sort_order' => $sortOrder++,
                    'deleted_at' => null,
                ]
            );

            // Product image (Unsplash)
            ProductImage::updateOrCreate(
                ['product_id' => $product->id, 'is_primary' => true],
                [
                    'path' => $p['img'],
                    'is_primary' => true,
                    'alt_text' => $p['name'],
                ]
            );

            // Stock per size (clothing items)
            if (in_array($p['cat'], [0, 1, 4])) { // T-shirts, Hoodies, Pantalons
                foreach ($sizes->whereNotIn('name', ['Unique']) as $size) {
                    ProductStock::updateOrCreate(
                        ['product_id' => $product->id, 'size_id' => $size->id],
                        [
                            'quantity' => rand(0, 50),
                            'low_stock_threshold' => 5,
                        ]
                    );
                }
            } elseif ($p['cat'] == 2) { // Casquettes
                $uniqueSize = $sizes->firstWhere('name', 'Unique');
                if ($uniqueSize) {
                    ProductStock::updateOrCreate(
                        ['product_id' => $product->id, 'size_id' => $uniqueSize->id],
                        [
                            'quantity' => rand(10, 80),
                            'low_stock_threshold' => 10,
                        ]
                    );
                }
            } else { // Accessoires, Vinyles
                $uniqueSize = $sizes->firstWhere('name', 'Unique');
                if ($uniqueSize) {
                    ProductStock::updateOrCreate(
                        ['product_id' => $product->id, 'size_id' => $uniqueSize->id],
                        [
                            'quantity' => rand(5, 100),
                            'low_stock_threshold' => 5,
                        ]
                    );
                }
            }

            $created[] = $product;
        }

        return $created;
    }

    /**
     * Create orders with items and payments.
     */
    private function seedOrders(array $users, array $products): void
    {
        // Skip if orders already exist (idempotent)
        if (Order::count() >= 35) {
            $this->command->info('  Orders already seeded, skipping.');
            return;
        }

        $customers = $users['customers'];
        $statuses = ['pending', 'paid', 'processing', 'shipped', 'delivered', 'cancelled'];
        $paymentStatuses = ['pending', 'success', 'success', 'success', 'success', 'failed'];
        $cities = ['Libreville', 'Port-Gentil', 'Franceville', 'Oyem', 'Moanda', 'Lambaréné'];
        $providers = ['airtel', 'moov'];

        for ($i = 0; $i < 35; $i++) {
            $customer = $customers->random();
            $statusIndex = array_rand($statuses);
            $status = $statuses[$statusIndex];
            $paymentStatus = $paymentStatuses[$statusIndex];
            $city = fake()->randomElement($cities);
            $provider = fake()->randomElement($providers);

            // Pick 1-4 random products
            $orderProducts = collect($products)->random(rand(1, 4));
            $subtotal = 0;
            $items = [];

            foreach ($orderProducts as $product) {
                $qty = rand(1, 3);
                $total = $product->price * $qty;
                $subtotal += $total;

                // Pick a size
                $stock = ProductStock::where('product_id', $product->id)->inRandomOrder()->first();

                $items[] = [
                    'product_id' => $product->id,
                    'size_id' => $stock?->size_id,
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                    'size_name' => $stock?->size?->name ?? 'Unique',
                    'unit_price' => $product->price,
                    'quantity' => $qty,
                    'total' => $total,
                    'media_content_id' => $product->media_content_id,
                ];
            }

            $shippingFee = $city === 'Libreville' ? 1000 : 2500;
            $orderTotal = $subtotal + $shippingFee;
            $createdAt = fake()->dateTimeBetween('-3 months', 'now');
            $paidAt = in_array($status, ['paid', 'processing', 'shipped', 'delivered']) ? $createdAt : null;

            $order = Order::create([
                'user_id' => $customer->id,
                'order_number' => 'POP-' . date('Ymd', $createdAt->getTimestamp()) . '-' . str_pad($i + 1, 4, '0', STR_PAD_LEFT),
                'status' => $status,
                'subtotal' => $subtotal,
                'shipping_fee' => $shippingFee,
                'discount' => 0,
                'total' => $orderTotal,
                'shipping_name' => $customer->first_name . ' ' . $customer->last_name,
                'shipping_phone' => $customer->phone,
                'shipping_address' => fake()->streetAddress(),
                'shipping_city' => $city,
                'payment_method' => 'mobile_money',
                'payment_provider' => $provider,
                'payment_phone' => $customer->phone,
                'payment_status' => $paymentStatus,
                'paid_at' => $paidAt,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            // Order items
            foreach ($items as $item) {
                $order->items()->create($item);
            }

            // Payment transaction
            $txStatus = $paymentStatus === 'success' ? 'success' : ($paymentStatus === 'failed' ? 'failed' : 'pending');

            PaymentTransaction::create([
                'order_id' => $order->id,
                'transaction_id' => $txStatus !== 'pending' ? 'TXN-' . strtoupper(Str::random(12)) : null,
                'provider' => $provider,
                'phone' => $customer->phone,
                'amount' => $orderTotal,
                'currency' => 'XAF',
                'status' => $txStatus,
                'initiated_at' => $createdAt,
                'completed_at' => $txStatus === 'success' ? $createdAt : null,
            ]);

            // Address for customer (if they don't have one)
            if ($customer->addresses()->count() === 0) {
                $quartiers = ['Akanda', 'Nzeng-Ayong', 'Owendo', 'Glass', 'Lalala', 'PK5', 'PK8', 'Cocotiers', 'Batterie IV', 'Nombakélé', 'Sotega', 'Centre-ville'];
                Address::create([
                    'user_id' => $customer->id,
                    'label' => 'Domicile',
                    'recipient_name' => $customer->first_name . ' ' . $customer->last_name,
                    'phone' => $customer->phone,
                    'address_line1' => fake()->streetAddress(),
                    'quartier' => fake()->randomElement($quartiers),
                    'city' => $city,
                    'is_default' => true,
                ]);
            }
        }
    }

    /**
     * Create some active cart items.
     */
    private function seedCartItems(array $users, array $products): void
    {
        $customers = $users['customers']->take(5);

        foreach ($customers as $customer) {
            $cartProducts = collect($products)->random(rand(1, 3));

            foreach ($cartProducts as $product) {
                $stock = ProductStock::where('product_id', $product->id)->inRandomOrder()->first();

                CartItem::updateOrCreate(
                    ['user_id' => $customer->id, 'product_id' => $product->id],
                    [
                        'size_id' => $stock?->size_id,
                        'quantity' => rand(1, 2),
                    ]
                );
            }
        }
    }

    /**
     * Generate a product description.
     */
    private function generateProductDescription(string $name, string $category, ?string $collection): string
    {
        $base = "Le {$name} est un produit exclusif Popup Store, catégorie {$category}.";

        if ($collection) {
            $base .= " Issu de la collection \"{$collection}\", chaque achat donne accès à du contenu multimédia exclusif via QR code.";
        } else {
            $base .= " Un essentiel de la marque Popup Store.";
        }

        $base .= " Qualité premium, édition limitée.";

        return $base;
    }
}
