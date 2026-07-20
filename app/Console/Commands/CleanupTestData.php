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
                            {--orders : Only clean unpaid orders}';

    protected $description = 'Soft-delete never-sold test products and unpaid test orders (dry run by default)';

    public function handle(): int
    {
        $onlyProducts = $this->option('products');
        $onlyOrders = $this->option('orders');
        $doProducts = $onlyProducts || ! $onlyOrders;
        $doOrders = $onlyOrders || ! $onlyProducts;
        $force = $this->option('force');

        if (! $force) {
            $this->warn('DRY RUN — nothing will be deleted. Re-run with --force to apply.');
        }

        $productCount = $doProducts ? $this->cleanProducts($force) : 0;
        $orderCount = $doOrders ? $this->cleanOrders($force) : 0;

        $this->newLine();
        $this->info($force
            ? "Supprimé : {$productCount} produit(s), {$orderCount} commande(s)."
            : "À supprimer : {$productCount} produit(s), {$orderCount} commande(s). Relancez avec --force.");

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
