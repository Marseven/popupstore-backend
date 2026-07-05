<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Available colours for made-to-order items, e.g. ["Bleu","Blanc","Noir","Rouge"].
            if (! Schema::hasColumn('products', 'colors')) {
                $table->json('colors')->nullable()->after('media_content_id');
            }
        });

        Schema::table('cart_items', function (Blueprint $table) {
            // Chosen colour for this line (nullable — products without colours ignore it).
            if (! Schema::hasColumn('cart_items', 'color')) {
                $table->string('color', 50)->nullable()->after('size_id');
            }
        });

        Schema::table('order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('order_items', 'color_name')) {
                $table->string('color_name', 50)->nullable()->after('size_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'colors')) {
                $table->dropColumn('colors');
            }
        });
        Schema::table('cart_items', function (Blueprint $table) {
            if (Schema::hasColumn('cart_items', 'color')) {
                $table->dropColumn('color');
            }
        });
        Schema::table('order_items', function (Blueprint $table) {
            if (Schema::hasColumn('order_items', 'color_name')) {
                $table->dropColumn('color_name');
            }
        });
    }
};
