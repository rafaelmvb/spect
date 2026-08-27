<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conversas do Copiloto Clinico, uma linha por paciente/profissional.
 *
 * Escopo, Parte 04 par. 8.2: o processamento ocorre em ambiente isolado e as
 * informacoes sao visualizadas exclusivamente pelo profissional responsavel
 * pelo paciente. Por isso a chave e o par (profissional, paciente): a conversa
 * de um profissional sobre um caso nunca aparece para outro.
 *
 * A tabela tambem serve de trilha de auditoria: com dado clinico em jogo,
 * precisa ficar registrado quem perguntou o que, e quando.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinical_copilot_messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('professional_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('patient_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();

            // user | assistant
            $table->string('role', 12);
            $table->text('content');

            // Rastro de custo e de qual modelo respondeu.
            $table->string('model', 60)->nullable();
            $table->unsignedInteger('tokens')->nullable();

            $table->timestamps();

            $table->index(['professional_user_id', 'patient_user_id', 'created_at'], 'idx_copiloto_conversa');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_copilot_messages');
    }
};
