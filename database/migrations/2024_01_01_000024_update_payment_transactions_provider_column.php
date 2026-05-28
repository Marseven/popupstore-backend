<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MODIFY/ENUM are MySQL-only; SQLite (test DB) stores the column as TEXT already.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // Convert enum('airtel','moov') → varchar(50) nullable
        DB::statement("ALTER TABLE payment_transactions MODIFY provider VARCHAR(50) NULL");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // Revert to enum (existing rows with unknown values will be set to 'airtel')
        DB::statement("ALTER TABLE payment_transactions MODIFY provider ENUM('airtel','moov') NOT NULL DEFAULT 'airtel'");
    }
};
