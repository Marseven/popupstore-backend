<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@popupstore.ga'],
            [
                'role_id' => 1,
                'first_name' => 'Admin',
                'last_name' => 'Popup Store',
                'email' => 'admin@popupstore.ga',
                'phone' => '+24107000000',
                'password' => Hash::make('password'),
                'is_active' => true,
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
            ]
        );
    }
}
