<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CampaignStatus;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CampaignController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $campaigns = Campaign::withCount('teams')
            ->orderByDesc('created_at')
            ->paginate(min((int) $request->get('per_page', 20), 50));

        return response()->json($campaigns);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'sales_goal' => 'nullable|integer|min:0',
            'settings' => 'nullable|array',
        ]);

        $campaign = Campaign::create([
            ...$validated,
            'slug' => Str::slug($validated['name']).'-'.Str::random(6),
            'status' => CampaignStatus::Draft,
        ]);

        return response()->json(['campaign' => $campaign], 201);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json([
            'campaign' => Campaign::with('teams')->findOrFail($id),
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $campaign = Campaign::findOrFail($id);

        $campaign->update($request->validate([
            'name' => 'sometimes|string|max:255',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'status' => 'sometimes|in:draft,active,closed',
            'sales_goal' => 'nullable|integer|min:0',
            'settings' => 'nullable|array',
        ]));

        return response()->json(['campaign' => $campaign->fresh()]);
    }

    public function close(int $id): JsonResponse
    {
        $campaign = Campaign::findOrFail($id);
        $campaign->update(['status' => CampaignStatus::Closed]);

        return response()->json(['campaign' => $campaign->fresh()]);
    }

    public function storeTeam(Request $request, int $id): JsonResponse
    {
        $campaign = Campaign::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'team_code' => 'required|string|max:50|unique:campaign_teams,team_code',
            'producer_name' => 'nullable|string|max:255',
            'artist_name' => 'nullable|string|max:255',
            'color_accent' => 'nullable|string|max:20',
            'sort_order' => 'nullable|integer',
        ]);

        $team = $campaign->teams()->create([
            ...$validated,
            'slug' => Str::slug($validated['name']),
        ]);

        return response()->json(['team' => $team], 201);
    }
}
