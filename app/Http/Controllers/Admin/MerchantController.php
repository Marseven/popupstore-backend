<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MerchantProfile;
use App\Services\MerchantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MerchantController extends Controller
{
    public function __construct(private MerchantService $merchants) {}

    public function index(Request $request): JsonResponse
    {
        $profiles = MerchantProfile::with('user:id,first_name,last_name,email')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('created_at')
            ->paginate(min((int) $request->get('per_page', 20), 50));

        return response()->json($profiles);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'business_name' => 'required|string|max:255',
            'rccm_nif' => 'nullable|string|max:100',
            'payout_phone' => 'required|string|max:20',
            'payout_provider' => 'required|string|in:airtelmoney,moovmoney4',
        ]);

        $profile = $this->merchants->adminOnboard($validated['email'], $validated);

        return response()->json(['merchant_profile' => $profile->load('user:id,first_name,last_name,email')], 201);
    }

    public function approve(int $id): JsonResponse
    {
        $profile = MerchantProfile::findOrFail($id);

        return response()->json(['merchant_profile' => $this->merchants->approve($profile)]);
    }

    public function suspend(int $id): JsonResponse
    {
        $profile = MerchantProfile::findOrFail($id);

        return response()->json(['merchant_profile' => $this->merchants->suspend($profile)]);
    }

    public function destroy(int $id): JsonResponse
    {
        $profile = MerchantProfile::findOrFail($id);
        $profile->delete(); // soft delete — keeps the user account and their collection

        return response()->json(['message' => 'Marchand supprimé']);
    }
}
