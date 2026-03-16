<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(private DashboardService $dashboardService) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'stats' => $this->dashboardService->getStats(),
            'recent_orders' => $this->dashboardService->getRecentOrders(),
            'low_stock_alerts' => $this->dashboardService->getLowStockAlerts(),
            'orders_by_status' => $this->dashboardService->getOrdersByStatus(),
        ]);
    }
}
