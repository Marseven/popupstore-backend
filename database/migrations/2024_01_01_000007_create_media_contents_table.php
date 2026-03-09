<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('media_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_id')->nullable()->constrained('collections')->nullOnDelete();
            $table->char('uuid', 36)->unique();
            $table->string('title', 255);
            $table->string('slug', 255)->unique();
            $table->text('description')->nullable();
            $table->enum('type', ['audio', 'video']);
            $table->string('file_path', 500);
            $table->bigInteger('file_size')->unsigned()->nullable();
            $table->unsignedInteger('duration')->nullable()->comment('Duration in seconds');
            $table->string('thumbnail', 255)->nullable();
            $table->string('qr_code_path', 255)->nullable();
            $table->string('qr_code_url', 500)->nullable();
            $table->unsignedInteger('play_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('uuid');
            $table->index('slug');
            $table->index('type');
            $table->index('collection_id');
            $table->index('is_active');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_contents');
    }
};
