<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinical_test_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('clinical_test_id')->constrained('clinical_tests')->cascadeOnDelete();
            $table->char('product_id', 36)->nullable()->index();
            // in_progress | completed
            $table->string('status', 20)->default('in_progress');
            $table->smallInteger('score')->nullable();
            $table->string('result_label')->nullable();
            $table->json('challenge_tags')->nullable();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            // Uma sessão ativa por usuário por teste (ao reiniciar cria nova após deletar)
            $table->unique(['user_id', 'clinical_test_id'], 'uq_user_test_session');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_test_sessions');
    }
};
