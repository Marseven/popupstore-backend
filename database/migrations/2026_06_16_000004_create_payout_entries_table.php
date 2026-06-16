<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payout_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('collection_id')->constrained('collections')->cascadeOnDelete();
            $table->foreignId('revenue_share_id')->nullable()->constrained('revenue_shares')->nullOnDelete();
            $table->unsignedInteger('gross_amount');      // collection gross in this order (XAF, integer)
            $table->unsignedInteger('commission_amount'); // withheld from gross for this line
            $table->unsignedInteger('net_amount');        // payable to the beneficiary (XAF, integer)
            $table->string('status', 20)->default('pending'); // pending | paid | cancelled
            $table->timestamp('paid_at')->nullable();
            $table->string('payout_reference')->nullable();
            $table->timestamps();

            $table->index(['collection_id', 'status']);
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_entries');
    }
};
