<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('guest_phone', 20)->nullable()->after('user_id');
            $table->string('guest_email')->nullable()->after('guest_phone');
            $table->string('session_id', 100)->nullable()->after('guest_email');

            $table->index(['guest_phone', 'order_number']);
            $table->index('session_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['guest_phone', 'order_number']);
            $table->dropIndex(['session_id']);
            $table->dropColumn(['guest_phone', 'guest_email', 'session_id']);
        });
    }
};
