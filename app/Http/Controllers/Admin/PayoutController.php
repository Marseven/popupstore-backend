<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PayoutStatus;
use App\Http\Controllers\Controller;
use App\Models\PayoutEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayoutController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 20), 100);

        $payouts = PayoutEntry::with(['collection:id,name', 'revenueShare:id,beneficiary_label,payout_phone,payout_provider', 'order:id,order_number'])
            ->when($request->filled('collection_id'), fn ($q) => $q->where('collection_id', $request->integer('collection_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json($payouts);
    }

    public function markPaid(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'payout_reference' => 'nullable|string|max:255',
        ]);

        $payout = PayoutEntry::findOrFail($id);

        if ($payout->status === PayoutStatus::Paid) {
            return response()->json(['message' => 'Ce reversement est déjà payé'], 422);
        }

        $payout->update([
            'status' => PayoutStatus::Paid,
            'paid_at' => now(),
            'payout_reference' => $validated['payout_reference'] ?? null,
        ]);

        return response()->json(['payout' => $payout->fresh()]);
    }
}
