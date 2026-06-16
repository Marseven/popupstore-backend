<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('team_code')->unique();
            $table->string('producer_name')->nullable();
            $table->string('artist_name')->nullable();
            $table->string('color_accent')->nullable();
            $table->string('qr_code_path')->nullable();
            $table->unsignedInteger('points_total')->default(0); // denormalised for fast leaderboard
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('campaign_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_teams');
    }
};
