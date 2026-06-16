<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('campaign_teams')->cascadeOnDelete();
            $table->string('type', 30); // catalog | exclusive_tracks | finale_ticket
            $table->string('code')->unique();
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamps();

            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_entitlements');
    }
};
