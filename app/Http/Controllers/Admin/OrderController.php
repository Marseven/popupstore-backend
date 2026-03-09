<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /**
     * Paginated list of orders with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Order::with(['user', 'items']);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by payment status
        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        // Search by order number or customer name
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('shipping_name', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    });
            });
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $perPage = min($request->get('per_page', 15), 50);
        $orders = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($orders);
    }

    /**
     * Get order with items, user, and transactions.
     */
    public function show(int $id): JsonResponse
    {
        $order = Order::with(['items', 'user', 'transactions'])
            ->findOrFail($id);

        return response()->json([
            'order' => $order,
        ]);
    }

    /**
     * Update order status with validation of status transitions.
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $order = Order::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|string|in:pending,paid,processing,shipped,delivered,cancelled',
        ]);

        $allowedTransitions = [
            'pending' => ['paid', 'processing', 'cancelled'],
            'paid' => ['processing', 'cancelled'],
            'processing' => ['shipped', 'cancelled'],
            'shipped' => ['delivered'],
            'delivered' => [],
            'cancelled' => [],
        ];

        $currentStatus = $order->status;
        $newStatus = $validated['status'];

        if (!isset($allowedTransitions[$currentStatus]) || !in_array($newStatus, $allowedTransitions[$currentStatus])) {
            return response()->json([
                'message' => "Transition de statut invalide : de '{$currentStatus}' vers '{$newStatus}'",
                'allowed_transitions' => $allowedTransitions[$currentStatus] ?? [],
            ], 422);
        }

        $order->update(['status' => $newStatus]);

        return response()->json([
            'message' => 'Statut de la commande mis à jour',
            'order' => $order->fresh()->load(['items', 'user']),
        ]);
    }

    /**
     * Add admin note to order.
     */
    public function addNote(Request $request, int $id): JsonResponse
    {
        $order = Order::findOrFail($id);

        $validated = $request->validate([
            'note' => 'required|string|max:2000',
        ]);

        // Append to existing notes
        $existingNotes = $order->admin_notes ?? '';
        $timestamp = now()->format('Y-m-d H:i');
        $newNote = "[{$timestamp}] {$validated['note']}";

        $order->update([
            'admin_notes' => $existingNotes
                ? $existingNotes . "\n" . $newNote
                : $newNote,
        ]);

        return response()->json([
            'message' => 'Note ajoutée avec succès',
            'order' => $order->fresh(),
        ]);
    }
}
