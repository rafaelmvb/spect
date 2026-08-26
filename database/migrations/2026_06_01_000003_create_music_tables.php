<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('music_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('tenant_id')->nullable()->index();
            $table->string('name', 255);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('music_tracks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('music_category_id')->constrained('music_categories')->cascadeOnDelete();
            $table->unsignedInteger('tenant_id')->nullable()->index();
            $table->string('title', 255);
            $table->string('file_path', 500);
            $table->boolean('is_active')->default(true);
            $table->boolean('available_to_all')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('music_track_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('music_track_id')->constrained('music_tracks')->cascadeOnDelete();
            $table->char('product_id', 36);
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->unique(['music_track_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('music_track_product');
        Schema::dropIfExists('music_tracks');
        Schema::dropIfExists('music_categories');
    }
};
