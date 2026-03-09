<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MediaContent;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    /**
     * Return dashboard stats.
     */
    public function index(): JsonResponse
    {
        $totalOrders = Order::count();
        $revenue = Order::where('payment_status', 'success')->sum('total');
        $totalProducts = Product::count();
        $totalUsers = User::count();
        $totalMedia = MediaContent::count();

        $recentOrders = Order::with('user')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $lowStockAlerts = ProductStock::with(['product', 'size'])
            ->whereColumn('quantity', '<=', 'low_stock_threshold')
            ->get();

        $ordersByStatus = Order::selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return response()->json([
            'stats' => [
                'total_orders' => $totalOrders,
                'revenue' => round((float) $revenue, 2),
                'total_products' => $totalProducts,
                'total_users' => $totalUsers,
                'total_media' => $totalMedia,
            ],
            'recent_orders' => $recentOrders,
            'low_stock_alerts' => $lowStockAlerts,
            'orders_by_status' => $ordersByStatus,
        ]);
    }
}
