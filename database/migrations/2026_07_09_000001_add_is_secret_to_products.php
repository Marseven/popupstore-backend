<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Secret products are hidden from the public catalogue and only
            // visible to authenticated customers.
            if (! Schema::hasColumn('products', 'is_secret')) {
                $table->boolean('is_secret')->default(false)->index()->after('is_featured');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'is_secret')) {
                $table->dropColumn('is_secret');
            }
        });
    }
};
