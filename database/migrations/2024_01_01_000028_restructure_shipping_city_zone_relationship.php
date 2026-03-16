<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Clear seed data before restructuring
        DB::table('shipping_cities')->delete();
        DB::table('shipping_zones')->delete();

        // Remove FK and shipping_zone_id from cities (cities become the parent)
        Schema::table('shipping_cities', function (Blueprint $table) {
            $table->dropForeign(['shipping_zone_id']);
            $table->dropColumn('shipping_zone_id');
            $table->unsignedInteger('sort_order')->default(0)->after('is_active');
        });

        // Add shipping_city_id FK to zones (zones become the child)
        Schema::table('shipping_zones', function (Blueprint $table) {
            $table->foreignId('shipping_city_id')->after('id')
                ->constrained('shipping_cities')->cascadeOnDelete();
        });

        // Add shipping_zone column to orders for display
        Schema::table('orders', function (Blueprint $table) {
            $table->string('shipping_zone', 100)->nullable()->after('shipping_quartier');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('shipping_zone');
        });

        Schema::table('shipping_zones', function (Blueprint $table) {
            $table->dropForeign(['shipping_city_id']);
            $table->dropColumn('shipping_city_id');
        });

        Schema::table('shipping_cities', function (Blueprint $table) {
            $table->dropColumn('sort_order');
            $table->foreignId('shipping_zone_id')->constrained()->cascadeOnDelete();
        });
    }
};
