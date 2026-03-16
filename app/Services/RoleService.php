<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class RoleService
{
    protected const PROTECTED_SLUGS = ['super_admin', 'customer'];

    public function list(array $params): LengthAwarePaginator
    {
        $query = Role::withCount(['permissions', 'users']);

        if (!empty($params['search'])) {
            $search = $params['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $perPage = min($params['per_page'] ?? 15, 50);

        return $query->orderBy('id')->paginate($perPage);
    }

    public function find(int $id): Role
    {
        return Role::with('permissions')
            ->withCount('users')
            ->findOrFail($id);
    }

    public function create(array $data): Role
    {
        $role = Role::create([
            'name' => $data['name'],
            'slug' => Str::slug($data['name'], '_'),
            'description' => $data['description'] ?? null,
        ]);

        if (!empty($data['permissions'])) {
            $role->permissions()->sync($data['permissions']);
        }

        return $role->load('permissions')->loadCount('users');
    }

    public function update(int $id, array $data): Role
    {
        $role = Role::findOrFail($id);

        $updateData = [];
        if (isset($data['description'])) {
            $updateData['description'] = $data['description'];
        }

        // Only update name/slug if not a protected role
        if (isset($data['name']) && !in_array($role->slug, self::PROTECTED_SLUGS)) {
            $updateData['name'] = $data['name'];
            $updateData['slug'] = Str::slug($data['name'], '_');
        }

        if (!empty($updateData)) {
            $role->update($updateData);
        }

        if (array_key_exists('permissions', $data)) {
            $role->permissions()->sync($data['permissions'] ?? []);
        }

        return $role->fresh()->load('permissions')->loadCount('users');
    }

    public function delete(int $id): void
    {
        $role = Role::findOrFail($id);

        if (in_array($role->slug, self::PROTECTED_SLUGS)) {
            abort(422, 'Ce rôle système ne peut pas être supprimé');
        }

        // Reassign users to customer role
        $customerRole = Role::where('slug', 'customer')->firstOrFail();
        User::where('role_id', $role->id)->update(['role_id' => $customerRole->id]);

        $role->delete();
    }

    public function isProtected(Role $role): bool
    {
        return in_array($role->slug, self::PROTECTED_SLUGS);
    }
}
