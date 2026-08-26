<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinical_test_question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinical_test_question_id')
                ->constrained('clinical_test_questions')
                ->cascadeOnDelete();
            $table->string('text');
            $table->smallInteger('value')->default(0);
            $table->smallInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_test_question_options');
    }
};
