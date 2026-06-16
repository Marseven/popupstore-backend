<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'campaign_team_id')) {
                $table->foreignId('campaign_team_id')->nullable()->after('collection_id')
                    ->constrained('campaign_teams')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'campaign_team_id')) {
                $table->dropConstrainedForeignId('campaign_team_id');
            }
        });
    }
};
