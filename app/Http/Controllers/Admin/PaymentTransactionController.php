<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentTransactionController extends Controller
{
    /**
     * Paginated list of transactions with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = PaymentTransaction::with('order:id,order_number,shipping_name');

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Search by order number or phone
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('phone', 'like', "%{$search}%")
                    ->orWhereHas('order', function ($oq) use ($search) {
                        $oq->where('order_number', 'like', "%{$search}%");
                    });
            });
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('initiated_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('initiated_at', '<=', $request->date_to);
        }

        $perPage = min($request->get('per_page', 20), 50);
        $transactions = $query->orderBy('initiated_at', 'desc')->paginate($perPage);

        return response()->json($transactions);
    }
}
