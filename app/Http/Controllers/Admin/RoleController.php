<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\RoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function __construct(private RoleService $roleService) {}

    public function index(Request $request): JsonResponse
    {
        $roles = $this->roleService->list($request->all());

        return response()->json($roles);
    }

    public function show(int $id): JsonResponse
    {
        $role = $this->roleService->find($id);

        return response()->json(['role' => $role]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:roles,name',
            'description' => 'nullable|string|max:255',
            'permissions' => 'nullable|array',
            'permissions.*' => 'integer|exists:permissions,id',
        ]);

        $role = $this->roleService->create($validated);

        return response()->json([
            'message' => 'Rôle créé avec succès',
            'role' => $role,
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:100|unique:roles,name,' . $id,
            'description' => 'nullable|string|max:255',
            'permissions' => 'nullable|array',
            'permissions.*' => 'integer|exists:permissions,id',
        ]);

        $role = $this->roleService->update($id, $validated);

        return response()->json([
            'message' => 'Rôle mis à jour avec succès',
            'role' => $role,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->roleService->delete($id);

        return response()->json([
            'message' => 'Rôle supprimé avec succès',
        ]);
    }
}
