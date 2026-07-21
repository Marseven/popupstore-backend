<?php

namespace App\Console\Commands;

use App\Enums\CollectionType;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Console\Command;

/**
 * Removes leftover test data: catalogue products that never sold and orders
 * that were never paid.
 *
 * Dry-run by default — nothing is touched until --force is passed.
 * Products belonging to a partner (merchant) collection are always protected,
 * so a real merchant's freshly-added catalogue is never wiped.
 */
class CleanupTestData extends Command
{
    protected $signature = 'popup:cleanup
                            {--force : Actually delete (default is a dry run)}
                            {--products : Only clean products}
                            {--orders : Only clean unpaid orders}
                            {--keep-only= : Strict mode — comma-separated slugs/SKUs to keep. Every OTHER product is removed, along with any order that contains none of the kept products. Merchant (partner) products are always protected.}';

    protected $description = 'Soft-delete test products/orders (dry run by default). Use --keep-only to keep just a shortlist.';

    public function handle(): int
    {
        $force = $this->option('force');

        if (! $force) {
            $this->warn('DRY RUN — nothing will be deleted. Re-run with --force to apply.');
        }

        // Strict mode: keep only the named products (+ merchant products) and their orders.
        if ($keepOnly = $this->option('keep-only')) {
            $keep = array_filter(array_map('trim', explode(',', $keepOnly)));

            return $this->keepOnly($keep, $force);
        }

        $onlyProducts = $this->option('products');
        $onlyOrders = $this->option('orders');
        $doProducts = $onlyProducts || ! $onlyOrders;
        $doOrders = $onlyOrders || ! $onlyProducts;

        $productCount = $doProducts ? $this->cleanProducts($force) : 0;
        $orderCount = $doOrders ? $this->cleanOrders($force) : 0;

        $this->newLine();
        $this->info($force
            ? "Supprimé : {$productCount} produit(s), {$orderCount} commande(s)."
            : "À supprimer : {$productCount} produit(s), {$orderCount} commande(s). Relancez avec --force.");

        return self::SUCCESS;
    }

    /**
     * Strict mode: keep only the named products (plus every merchant/partner
     * product) and the orders that contain at least one of them. Everything
     * else — products and orders alike — is soft-deleted.
     */
    private function keepOnly(array $keep, bool $force): int
    {
        $keptIds = Product::query()
            ->where(fn ($q) => $q->whereIn('slug', $keep)->orWhereIn('sku', $keep))
            ->orWhereHas('collection', fn ($q) => $q->where('type', CollectionType::Partner->value))
            ->pluck('id');

        if ($keptIds->isEmpty()) {
            $this->error('Aucun produit ne correspond à --keep-only ('.implode(', ', $keep).'). Abandon — rien n\'a été touché.');

            return self::FAILURE;
        }

        $kept = Product::whereIn('id', $keptIds)->get(['id', 'name', 'sku']);
        $this->newLine();
        $this->info("Produits CONSERVÉS ({$kept->count()}) :");
        foreach ($kept as $p) {
            $this->line("  ✓ [{$p->sku}] {$p->name}");
        }

        $products = Product::whereNotIn('id', $keptIds)->get(['id', 'name', 'sku']);
        $orders = Order::whereDoesntHave('items', fn ($q) => $q->whereIn('product_id', $keptIds))
            ->get(['id', 'order_number', 'payment_status', 'total']);

        $this->newLine();
        $this->line("Produits à SUPPRIMER ({$products->count()}) :");
        foreach ($products as $p) {
            $this->line("  - [{$p->sku}] {$p->name}");
        }

        $this->newLine();
        $this->line("Commandes à SUPPRIMER ({$orders->count()}) — aucune ne contient un produit conservé :");
        foreach ($orders as $o) {
            $this->line("  - {$o->order_number} ({$o->payment_status}, {$o->total} XAF)");
        }

        if ($force) {
            foreach ($products as $p) {
                $p->stocks()->delete();
                $p->cartItems()->delete();
                $p->delete();
            }
            foreach ($orders as $o) {
                $o->delete();
            }
        }

        $this->newLine();
        $this->info($force
            ? "Supprimé : {$products->count()} produit(s), {$orders->count()} commande(s)."
            : "À supprimer : {$products->count()} produit(s), {$orders->count()} commande(s). Relancez avec --force.");

        return self::SUCCESS;
    }

    /** Products with zero order items, excluding real merchants' (partner) collections. */
    private function cleanProducts(bool $force): int
    {
        $products = Product::doesntHave('orderItems')
            ->whereDoesntHave('collection', fn ($q) => $q->where('type', CollectionType::Partner->value))
            ->get(['id', 'name', 'sku']);

        if ($products->isEmpty()) {
            $this->line('Produits sans commande : aucun.');

            return 0;
        }

        $this->newLine();
        $this->line("Produits jamais vendus ({$products->count()}) :");
        foreach ($products as $p) {
            $this->line("  - [{$p->sku}] {$p->name}");
        }

        if ($force) {
            foreach ($products as $p) {
                $p->stocks()->delete();
                $p->cartItems()->delete();
                $p->delete(); // soft delete
            }
        }

        return $products->count();
    }

    /** Orders that were never paid (abandoned checkouts / test runs). */
    private function cleanOrders(bool $force): int
    {
        $orders = Order::where('payment_status', '!=', 'success')
            ->get(['id', 'order_number', 'payment_status', 'total']);

        if ($orders->isEmpty()) {
            $this->line('Commandes non payées : aucune.');

            return 0;
        }

        $this->newLine();
        $this->line("Commandes non payées ({$orders->count()}) :");
        foreach ($orders as $o) {
            $this->line("  - {$o->order_number} ({$o->payment_status}, {$o->total} XAF)");
        }

        if ($force) {
            foreach ($orders as $o) {
                $o->delete(); // soft delete — items keep the historical snapshot
            }
        }

        return $orders->count();
    }
}
