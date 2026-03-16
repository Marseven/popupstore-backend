<?php

namespace App\Services;

use App\Models\MediaContent;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class DashboardService
{
    public function getStats(): array
    {
        return Cache::remember('dashboard.stats', 300, function () {
            return [
                'total_orders' => Order::count(),
                'revenue' => round((float) Order::where('payment_status', 'success')->sum('total'), 2),
                'total_products' => Product::count(),
                'total_users' => User::count(),
                'total_media' => MediaContent::count(),
            ];
        });
    }

    public function getRecentOrders(int $limit = 5): \Illuminate\Database\Eloquent\Collection
    {
        return Order::with('user')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getLowStockAlerts(int $limit = 20): \Illuminate\Database\Eloquent\Collection
    {
        return ProductStock::with(['product', 'size'])
            ->whereColumn('quantity', '<=', 'low_stock_threshold')
            ->limit($limit)
            ->get();
    }

    public function getOrdersByStatus(): \Illuminate\Support\Collection
    {
        return Order::selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');
    }
}
