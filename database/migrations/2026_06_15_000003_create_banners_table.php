<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->string('title', 255)->nullable();
            $table->string('subtitle', 500)->nullable();
            $table->string('image')->nullable();          // path no storage
            $table->string('link', 500)->nullable();
            $table->string('button_text', 100)->nullable();
            $table->string('target', 30)->default('member_area'); // member_area | dashboard | both
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
