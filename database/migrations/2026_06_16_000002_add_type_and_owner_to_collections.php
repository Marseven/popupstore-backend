<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            // shop (default) | partner | campaign — gates conditional behaviour.
            // Plain string (not enum) to keep the sqlite test schema free of CHECK
            // constraints and aligned with prod.
            if (! Schema::hasColumn('collections', 'type')) {
                $table->string('type', 20)->default('shop')->index()->after('slug');
            }
            if (! Schema::hasColumn('collections', 'owner_user_id')) {
                $table->foreignId('owner_user_id')->nullable()->after('type')
                    ->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            if (Schema::hasColumn('collections', 'owner_user_id')) {
                $table->dropConstrainedForeignId('owner_user_id');
            }
            if (Schema::hasColumn('collections', 'type')) {
                $table->dropColumn('type');
            }
        });
    }
};
