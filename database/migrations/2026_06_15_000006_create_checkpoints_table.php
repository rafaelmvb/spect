<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkpoints', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->string('title');
            $table->text('description')->nullable();
            // Etapa de jornada associada (opcional)
            $table->foreignId('journey_step_id')->nullable()->constrained('journey_steps')->nullOnDelete();
            // Se true, completar o checkpoint libera a próxima etapa
            $table->boolean('unlock_next_step')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('checkpoint_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checkpoint_id')->constrained('checkpoints')->cascadeOnDelete();
            $table->string('label');
            // text | textarea | radio | checkbox | scale | date
            $table->string('type', 20)->default('text');
            // Opções para radio/checkbox; config de escala p/ scale
            $table->json('options')->nullable();
            $table->boolean('required')->default(false);
            $table->smallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('checkpoint_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checkpoint_id')->constrained('checkpoints')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['checkpoint_id', 'user_id']);
        });

        Schema::create('checkpoint_response_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checkpoint_response_id')->constrained('checkpoint_responses')->cascadeOnDelete();
            $table->foreignId('checkpoint_question_id')->constrained('checkpoint_questions')->cascadeOnDelete();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkpoint_response_answers');
        Schema::dropIfExists('checkpoint_responses');
        Schema::dropIfExists('checkpoint_questions');
        Schema::dropIfExists('checkpoints');
    }
};
