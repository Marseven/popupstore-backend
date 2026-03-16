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
        // Find existing admin by email or phone (handles rebrand from SMAK)
        $admin = User::withTrashed()
            ->where('email', 'admin@popupstore.ga')
            ->orWhere('phone', '+24107000000')
            ->first();

        $data = [
            'role_id' => 1,
            'first_name' => 'Admin',
            'last_name' => 'Popup Store',
            'email' => 'admin@popupstore.ga',
            'phone' => '+24107000000',
            'password' => Hash::make('password'),
            'is_active' => true,
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
            'deleted_at' => null,
        ];

        if ($admin) {
            $admin->update($data);
        } else {
            User::create($data);
        }
    }
}
