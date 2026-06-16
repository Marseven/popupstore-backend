<?php

namespace Tests\Unit\Services;

use App\Exceptions\BusinessException;
use App\Models\Collection;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PayoutEntry;
use App\Models\Product;
use App\Models\RevenueShare;
use App\Models\Role;
use App\Services\RevenueSplitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RevenueSplitServiceTest extends TestCase
{
    use RefreshDatabase;

    private RevenueSplitService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Role::factory()->customer()->create();
        $this->service = new RevenueSplitService;
    }

    /** Build a paid order with one item of $total in a partner collection holding $shares. */
    private function orderInPartnerCollection(int $itemTotal, array $sharePercentages): array
    {
        $collection = Collection::factory()->partner()->create();
        foreach ($sharePercentages as $pct) {
            RevenueShare::factory()->create(['collection_id' => $collection->id, 'percentage' => $pct]);
        }
        $product = Product::factory()->create(['collection_id' => $collection->id]);
        $order = Order::factory()->create();
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'total' => $itemTotal,
            'unit_price' => $itemTotal,
            'quantity' => 1,
        ]);

        return [$order, $collection];
    }

    public function test_validate_shares_throws_when_sum_exceeds_100(): void
    {
        $collection = Collection::factory()->partner()->create();
        RevenueShare::factory()->create(['collection_id' => $collection->id, 'percentage' => 70]);
        RevenueShare::factory()->create(['collection_id' => $collection->id, 'percentage' => 40]);

        $this->expectException(BusinessException::class);
        $this->service->validateShares($collection->fresh());
    }

    public function test_validate_shares_allows_sum_at_100(): void
    {
        $collection = Collection::factory()->partner()->create();
        RevenueShare::factory()->create(['collection_id' => $collection->id, 'percentage' => 60]);
        RevenueShare::factory()->create(['collection_id' => $collection->id, 'percentage' => 40]);

        $this->service->validateShares($collection->fresh());
        $this->assertTrue(true); // no exception
    }

    public function test_split_is_exact_integer_for_clean_division(): void
    {
        [$order] = $this->orderInPartnerCollection(250, [70]);

        $this->service->recordForOrder($order);

        $entry = PayoutEntry::where('order_id', $order->id)->firstOrFail();
        $this->assertSame(250, $entry->gross_amount);
        $this->assertSame(175, $entry->net_amount);     // 70% of 250
        $this->assertSame(75, $entry->commission_amount); // remainder to platform
    }

    public function test_rounding_remainder_favours_the_platform(): void
    {
        // 70% of 255 = 178.5 → beneficiary floored to 178, platform keeps 77.
        [$order] = $this->orderInPartnerCollection(255, [70]);

        $this->service->recordForOrder($order);

        $entry = PayoutEntry::where('order_id', $order->id)->firstOrFail();
        $this->assertSame(178, $entry->net_amount);
        $this->assertSame(77, $entry->commission_amount);
        $this->assertSame($entry->gross_amount, $entry->net_amount + $entry->commission_amount);
    }

    public function test_multiple_beneficiaries_never_exceed_gross(): void
    {
        [$order] = $this->orderInPartnerCollection(1000, [33.33, 33.33, 33.33]);

        $this->service->recordForOrder($order);

        $nets = PayoutEntry::where('order_id', $order->id)->sum('net_amount');
        $this->assertLessThanOrEqual(1000, $nets);
        $this->assertGreaterThan(0, $nets);
    }

    public function test_record_is_idempotent_per_order(): void
    {
        [$order] = $this->orderInPartnerCollection(250, [70]);

        $this->service->recordForOrder($order);
        $this->service->recordForOrder($order->fresh());

        $this->assertSame(1, PayoutEntry::where('order_id', $order->id)->count());
    }

    public function test_shop_collection_produces_no_payout(): void
    {
        $collection = Collection::factory()->create(); // default type shop
        RevenueShare::factory()->create(['collection_id' => $collection->id, 'percentage' => 70]);
        $product = Product::factory()->create(['collection_id' => $collection->id]);
        $order = Order::factory()->create();
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'total' => 250,
            'quantity' => 1,
        ]);

        $this->service->recordForOrder($order);

        $this->assertSame(0, PayoutEntry::where('order_id', $order->id)->count());
    }
}
