<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('professionals', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('tenant_id')->index();
            $table->string('website', 255)->nullable()->after('phone');
            $table->text('experience')->nullable()->after('bio');
            $table->json('social_links')->nullable()->after('experience');
            $table->unsignedSmallInteger('position')->default(0)->after('is_active');
        });

        Schema::create('professional_services', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('professional_id')->index();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->unsignedSmallInteger('duration_minutes')->default(60);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->foreign('professional_id')->references('id')->on('professionals')->cascadeOnDelete();
        });

        Schema::create('professional_portfolio_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('professional_id')->index();
            $table->enum('type', ['image', 'video'])->default('image');
            $table->string('path')->nullable();
            $table->string('video_url', 500)->nullable();
            $table->string('title', 255)->nullable();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->foreign('professional_id')->references('id')->on('professionals')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('professional_portfolio_items');
        Schema::dropIfExists('professional_services');
        Schema::table('professionals', function (Blueprint $table) {
            $table->dropColumn(['user_id', 'website', 'experience', 'social_links', 'position']);
        });
    }
};
