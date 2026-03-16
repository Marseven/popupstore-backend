<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Seed application settings.
     */
    public function run(): void
    {
        $settings = [
            // General
            ['key' => 'store_name', 'value' => 'Popup Store', 'type' => 'string', 'group_name' => 'general'],
            ['key' => 'store_description', 'value' => 'Merch exclusif avec contenu multimédia', 'type' => 'string', 'group_name' => 'general'],
            ['key' => 'store_logo', 'value' => '', 'type' => 'string', 'group_name' => 'general'],
            ['key' => 'currency', 'value' => 'XAF', 'type' => 'string', 'group_name' => 'general'],
            ['key' => 'tax_rate', 'value' => '0', 'type' => 'string', 'group_name' => 'general'],

            // Shipping
            ['key' => 'shipping_fee', 'value' => '2000', 'type' => 'string', 'group_name' => 'shipping'],
            ['key' => 'free_shipping_threshold', 'value' => '25000', 'type' => 'string', 'group_name' => 'shipping'],

            // Contact
            ['key' => 'store_phone', 'value' => '+241 00 00 00 00', 'type' => 'string', 'group_name' => 'contact'],
            ['key' => 'store_email', 'value' => 'contact@popupstore.ga', 'type' => 'string', 'group_name' => 'contact'],
            ['key' => 'store_address', 'value' => '', 'type' => 'string', 'group_name' => 'contact'],
            ['key' => 'store_city', 'value' => 'Libreville', 'type' => 'string', 'group_name' => 'contact'],

            // Social
            ['key' => 'social_instagram', 'value' => '', 'type' => 'string', 'group_name' => 'social'],
            ['key' => 'social_facebook', 'value' => '', 'type' => 'string', 'group_name' => 'social'],
            ['key' => 'social_tiktok', 'value' => '', 'type' => 'string', 'group_name' => 'social'],

            // Payment
            ['key' => 'payment_ebilling_active', 'value' => '1', 'type' => 'boolean', 'group_name' => 'payment'],
            ['key' => 'payment_cod_active', 'value' => '0', 'type' => 'boolean', 'group_name' => 'payment'],
            ['key' => 'ebilling_mode', 'value' => 'lab', 'type' => 'string', 'group_name' => 'payment'],
            ['key' => 'ebilling_username', 'value' => '', 'type' => 'string', 'group_name' => 'payment'],
            ['key' => 'ebilling_shared_key', 'value' => '', 'type' => 'string', 'group_name' => 'payment'],
            ['key' => 'ebilling_redirect_url', 'value' => 'https://popupstore.ga', 'type' => 'string', 'group_name' => 'payment'],

            // Advanced
            ['key' => 'maintenance_mode', 'value' => '0', 'type' => 'boolean', 'group_name' => 'advanced'],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
