<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'name' => 'Super Administrateur',
                'slug' => 'super_admin',
                'description' => 'Accès total à la plateforme',
            ],
            [
                'name' => 'Client',
                'slug' => 'customer',
                'description' => 'Client de la boutique',
            ],
            [
                'name' => 'Manager',
                'slug' => 'manager',
                'description' => 'Gestion des produits et commandes',
            ],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(
                ['slug' => $role['slug']],
                $role
            );
        }
    }
}
