<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinical_test_scoring_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinical_test_id')->constrained('clinical_tests')->cascadeOnDelete();
            $table->smallInteger('min_score');
            $table->smallInteger('max_score');
            $table->string('result_label');
            $table->text('result_description')->nullable();
            // Tags geradas no perfil do aluno ao atingir esse range
            $table->json('challenge_tags')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_test_scoring_rules');
    }
};
