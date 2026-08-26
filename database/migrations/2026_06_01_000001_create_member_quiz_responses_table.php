<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_quiz_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained('member_lessons')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->char('product_id', 36)->index();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->json('responses'); // [{question_id, value, comment}]
            $table->timestamps();
            $table->unique(['lesson_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_quiz_responses');
    }
};
