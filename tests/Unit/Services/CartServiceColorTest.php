<?php

namespace Tests\Unit\Services;

use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Role;
use App\Models\Size;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartServiceColorTest extends TestCase
{
    use RefreshDatabase;

    private CartService $cart;

    protected function setUp(): void
    {
        parent::setUp();
        Role::factory()->customer()->create();
        $this->cart = new CartService;
    }

    private function teeWithColors(): array
    {
        $product = Product::factory()->create(['is_active' => true, 'colors' => ['Bleu', 'Noir']]);
        $size = Size::factory()->create(['name' => 'M']);
        ProductStock::factory()->create(['product_id' => $product->id, 'size_id' => $size->id, 'quantity' => 50]);

        return [$product, $size];
    }

    public function test_same_size_different_colour_are_separate_lines(): void
    {
        [$product, $size] = $this->teeWithColors();

        $this->cart->addItem(null, 'sess', $product->id, $size->id, 1, 'Bleu');
        $this->cart->addItem(null, 'sess', $product->id, $size->id, 1, 'Noir');

        $this->assertDatabaseCount('cart_items', 2);
    }

    public function test_same_size_same_colour_merges_quantity(): void
    {
        [$product, $size] = $this->teeWithColors();

        $this->cart->addItem(null, 'sess', $product->id, $size->id, 1, 'Bleu');
        $item = $this->cart->addItem(null, 'sess', $product->id, $size->id, 2, 'Bleu');

        $this->assertDatabaseCount('cart_items', 1);
        $this->assertEquals(3, $item->quantity);
    }

    public function test_rejects_colour_not_offered_by_product(): void
    {
        [$product, $size] = $this->teeWithColors();

        $this->expectExceptionMessage("Cette couleur n'est pas disponible");
        $this->cart->addItem(null, 'sess', $product->id, $size->id, 1, 'Rose');
    }

    public function test_colour_is_stored_on_the_line(): void
    {
        [$product, $size] = $this->teeWithColors();

        $item = $this->cart->addItem(null, 'sess', $product->id, $size->id, 1, 'Noir');

        $this->assertSame('Noir', $item->color);
    }
}
