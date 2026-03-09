<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $modules = [
            'products' => [
                'view' => 'Voir les produits',
                'create' => 'Créer un produit',
                'edit' => 'Modifier un produit',
                'delete' => 'Supprimer un produit',
                'manage_stock' => 'Gérer le stock des produits',
            ],
            'orders' => [
                'view' => 'Voir les commandes',
                'create' => 'Créer une commande',
                'edit' => 'Modifier une commande',
                'update_status' => 'Mettre à jour le statut d\'une commande',
                'cancel' => 'Annuler une commande',
                'refund' => 'Rembourser une commande',
            ],
            'media' => [
                'view' => 'Voir les médias',
                'create' => 'Créer un média',
                'edit' => 'Modifier un média',
                'delete' => 'Supprimer un média',
                'upload' => 'Uploader un média',
            ],
            'users' => [
                'view' => 'Voir les utilisateurs',
                'create' => 'Créer un utilisateur',
                'edit' => 'Modifier un utilisateur',
                'delete' => 'Supprimer un utilisateur',
                'manage_roles' => 'Gérer les rôles des utilisateurs',
            ],
            'settings' => [
                'view' => 'Voir les paramètres',
                'edit' => 'Modifier les paramètres',
            ],
            'dashboard' => [
                'view' => 'Voir le tableau de bord',
                'view_stats' => 'Voir les statistiques',
            ],
        ];

        foreach ($modules as $module => $actions) {
            foreach ($actions as $action => $description) {
                Permission::updateOrCreate(
                    ['slug' => "{$module}.{$action}"],
                    [
                        'name' => "{$module}.{$action}",
                        'module' => $module,
                        'description' => $description,
                    ]
                );
            }
        }

        // Assign permissions to roles
        $this->assignPermissionsToRoles();
    }

    /**
     * Assign permissions to roles.
     */
    private function assignPermissionsToRoles(): void
    {
        // Super Admin gets ALL permissions
        $superAdmin = Role::where('slug', 'super_admin')->first();
        if ($superAdmin) {
            $allPermissions = Permission::pluck('id')->toArray();
            $superAdmin->permissions()->sync($allPermissions);
        }

        // Manager gets specific permissions
        $manager = Role::where('slug', 'manager')->first();
        if ($manager) {
            $managerPermissions = Permission::where(function ($query) {
                // All products permissions
                $query->where('module', 'products')
                    // Specific orders permissions
                    ->orWhere(function ($q) {
                        $q->where('module', 'orders')
                          ->whereIn('slug', [
                              'orders.view',
                              'orders.edit',
                              'orders.update_status',
                          ]);
                    })
                    // All media permissions
                    ->orWhere('module', 'media')
                    // Specific dashboard permissions
                    ->orWhere(function ($q) {
                        $q->where('module', 'dashboard')
                          ->whereIn('slug', [
                              'dashboard.view',
                              'dashboard.view_stats',
                          ]);
                    });
            })->pluck('id')->toArray();

            $manager->permissions()->sync($managerPermissions);
        }

        // Customer gets no permissions (they use the public API)
        $customer = Role::where('slug', 'customer')->first();
        if ($customer) {
            $customer->permissions()->sync([]);
        }
    }
}
