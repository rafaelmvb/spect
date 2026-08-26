<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('neuro_areas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('color', 10)->default('#6366f1'); // hex
            $table->string('icon', 50)->default('Brain');    // lucide icon name
            $table->smallInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('neuro_indicators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('neuro_area_id')->constrained('neuro_areas')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->tinyInteger('scale_min')->default(0);
            $table->tinyInteger('scale_max')->default(10);
            $table->smallInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('neuro_user_scores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('neuro_indicator_id')->constrained('neuro_indicators')->cascadeOnDelete();
            $table->decimal('value', 5, 2);
            $table->text('notes')->nullable();
            $table->date('scored_at');
            $table->timestamps();
            $table->unique(['user_id', 'neuro_indicator_id', 'scored_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('neuro_user_scores');
        Schema::dropIfExists('neuro_indicators');
        Schema::dropIfExists('neuro_areas');
    }
};
