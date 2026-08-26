<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_journey_unlocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('journey_id');
            $table->string('product_id')->nullable();
            $table->boolean('is_free')->default(true);
            $table->unsignedBigInteger('ai_insight_id')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'journey_id']);
            $table->index(['user_id', 'tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_journey_unlocks');
    }
};
