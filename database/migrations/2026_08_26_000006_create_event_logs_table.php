<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Telemetria comportamental, separada dos dados cadastrais.
 *
 * Escopo, Parte 02 par. 5 e 6: os eventos ficam em tabela independente, com
 * identificador tecnico e sem ligacao direta com nome, e-mail ou CPF.
 *
 * Nao ha foreign key para users de proposito. O vinculo com a pessoa e
 * `subject_hash`, um HMAC do id com a APP_KEY: estavel o bastante para agregar
 * o comportamento de um mesmo usuario, e inutil para quem so tenha o banco.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_logs', function (Blueprint $table) {
            $table->id();

            $table->char('subject_hash', 64)->index();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();

            // Ex.: lesson.pause, lesson.abandon, test.abandon, content.scroll
            $table->string('event', 60);

            // A que o evento se refere: 'lesson', 'clinical_test', 'journey'...
            $table->string('subject_type', 40)->nullable();
            $table->string('subject_id', 64)->nullable();

            // Posicao no conteudo (segundo do video, percentual do texto).
            $table->unsignedInteger('position')->nullable();
            $table->unsignedInteger('duration')->nullable();

            // Numeros agregaveis sem abrir o JSON.
            $table->decimal('value', 10, 2)->nullable();

            $table->json('context')->nullable();

            // Sessao efemera do navegador: agrupa eventos de uma visita sem
            // identificar a pessoa.
            $table->char('session_token', 32)->nullable()->index();

            $table->timestamp('occurred_at')->index();

            $table->index(['event', 'occurred_at']);
            $table->index(['subject_type', 'subject_id']);
            $table->index(['tenant_id', 'event', 'occurred_at'], 'event_logs_tenant_event_time_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_logs');
    }
};
