<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journeys', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->text('description')->nullable();
            $table->string('cover')->nullable();
            $table->boolean('is_active')->default(true);
            $table->smallInteger('position')->default(0);
            // IDs dos produtos que liberam acesso a esta jornada (JSON array de UUIDs)
            $table->json('product_ids')->nullable();
            $table->timestamps();
        });

        Schema::create('journey_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journey_id')->constrained('journeys')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('cover')->nullable();
            $table->smallInteger('position')->default(0);
            // livre = sempre disponível; sequencial = precisa completar a anterior; manual = admin desbloqueia
            $table->string('unlock_type', 20)->default('sequencial');
            $table->smallInteger('unlock_after_days')->nullable();
            $table->timestamps();
        });

        Schema::create('journey_step_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journey_step_id')->constrained('journey_steps')->cascadeOnDelete();
            // content_type: 'product' | 'section' | 'module'
            $table->string('content_type', 30);
            // content_id: UUID (produto) ou bigint (section/module) — armazenado como string
            $table->string('content_id', 50);
            $table->string('content_title')->nullable();
            $table->smallInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journey_step_contents');
        Schema::dropIfExists('journey_steps');
        Schema::dropIfExists('journeys');
    }
};
