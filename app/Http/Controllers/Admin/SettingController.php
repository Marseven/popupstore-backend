<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    /**
     * Return all settings grouped by group_name.
     */
    public function index(): JsonResponse
    {
        $settings = Setting::all()
            ->groupBy('group_name')
            ->map(fn ($group) => $group->pluck('value', 'key'));

        return response()->json(['settings' => $settings]);
    }

    /**
     * Bulk update settings from key-value pairs.
     */
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'settings' => 'required|array',
            'settings.*.key' => 'required|string',
            'settings.*.value' => 'nullable|string',
        ]);

        foreach ($request->settings as $item) {
            $setting = Setting::where('key', $item['key'])->first();
            if ($setting) {
                $setting->update(['value' => $item['value'] ?? '']);
            }
        }

        return response()->json(['message' => 'Paramètres mis à jour avec succès']);
    }

    /**
     * Upload store logo.
     */
    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => 'required|image|max:2048',
        ]);

        // Delete old logo if exists
        $currentLogo = Setting::get('store_logo');
        if ($currentLogo) {
            Storage::disk('public')->delete($currentLogo);
        }

        $path = $request->file('logo')->store('settings', 'public');

        Setting::set('store_logo', $path, 'string', 'general');

        return response()->json([
            'message' => 'Logo mis à jour avec succès',
            'path' => $path,
        ]);
    }
}
