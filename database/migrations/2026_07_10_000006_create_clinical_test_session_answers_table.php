<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinical_test_session_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinical_test_session_id')
                ->constrained('clinical_test_sessions')
                ->cascadeOnDelete();
            $table->foreignId('clinical_test_question_id')
                ->constrained('clinical_test_questions')
                ->cascadeOnDelete();
            // Para scale/boolean: número. Para single: [option_id]. Para multi: [id1, id2]
            $table->json('answer');
            $table->timestamps();
            $table->unique(
                ['clinical_test_session_id', 'clinical_test_question_id'],
                'uq_session_question'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_test_session_answers');
    }
};
