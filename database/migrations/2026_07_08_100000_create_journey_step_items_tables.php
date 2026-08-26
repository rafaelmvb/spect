<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journey_step_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journey_step_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // video, quiz, question
            $table->string('title')->nullable();
            $table->json('data');
            $table->smallInteger('position')->default(0);
            $table->timestamps();
            $table->index('journey_step_id');
        });

        Schema::create('journey_step_item_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journey_step_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('answer')->nullable();
            $table->boolean('is_correct')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['journey_step_item_id', 'user_id']);
            $table->index(['user_id', 'journey_step_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journey_step_item_responses');
        Schema::dropIfExists('journey_step_items');
    }
};
