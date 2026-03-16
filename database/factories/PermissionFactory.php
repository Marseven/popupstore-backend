<?php

namespace Database\Factories;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Permission>
 */
class PermissionFactory extends Factory
{
    protected $model = Permission::class;

    public function definition(): array
    {
        $modules = ['products', 'orders', 'users', 'media', 'settings'];
        $actions = ['view', 'create', 'update', 'delete'];
        $module = fake()->randomElement($modules);
        $action = fake()->randomElement($actions);

        return [
            'name' => ucfirst($action) . ' ' . ucfirst($module),
            'slug' => $action . '_' . $module,
            'module' => $module,
            'description' => "Can {$action} {$module}",
        ];
    }
}
