<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revenue_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_id')->constrained('collections')->cascadeOnDelete();
            $table->string('beneficiary_label');
            $table->string('payout_phone', 20);
            $table->string('payout_provider', 20); // airtelmoney | moovmoney4
            $table->decimal('percentage', 5, 2);
            $table->timestamps();
            $table->softDeletes();

            $table->index('collection_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revenue_shares');
    }
};
