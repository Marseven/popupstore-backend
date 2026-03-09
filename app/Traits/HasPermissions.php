<?php

namespace App\Traits;

trait HasPermissions
{
    /**
     * Check if the user has a given role.
     *
     * @param  string|array  $role
     * @return bool
     */
    public function hasRole($role): bool
    {
        if (is_array($role)) {
            return in_array($this->role->slug, $role);
        }

        return $this->role->slug === $role;
    }

    /**
     * Check if the user has a given permission through their role.
     *
     * @param  string  $permission
     * @return bool
     */
    public function hasPermission($permission): bool
    {
        return $this->role->permissions->contains('slug', $permission);
    }

    /**
     * Check if the user is a super admin.
     *
     * @return bool
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    /**
     * Check if the user is a manager or super admin.
     *
     * @return bool
     */
    public function isManager(): bool
    {
        return $this->hasRole(['super_admin', 'manager']);
    }
}
