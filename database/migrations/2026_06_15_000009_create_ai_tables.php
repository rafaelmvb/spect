<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Configuração de comportamento da IA por contexto
        Schema::create('ai_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            // chat | recommendations | journey | checkpoint | community | general
            $table->string('context', 40);
            $table->string('name');
            $table->text('system_prompt')->nullable();
            $table->string('model', 80)->default('claude-haiku-4-5-20251001');
            $table->decimal('temperature', 3, 2)->default(0.7);
            $table->unsignedSmallInteger('max_tokens')->default(1024);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'context']);
        });

        // Regras de automação disparadas por eventos
        Schema::create('ai_automations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->string('name');
            $table->text('description')->nullable();
            // Eventos: journey_step_completed | checkpoint_submitted | appointment_scheduled
            //          | appointment_completed | user_registered | neuro_score_added
            $table->string('trigger_event', 60);
            // Condições adicionais do gatilho (ex: journey_id, step_id, threshold)
            $table->json('trigger_conditions')->nullable();
            // Ações: send_notification | generate_insight | send_message | tag_user
            $table->string('action_type', 40);
            $table->json('action_config')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('executions_count')->default(0);
            $table->timestamp('last_executed_at')->nullable();
            $table->timestamps();
        });

        // Log de interações da IA (monitoramento)
        Schema::create('ai_interaction_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('context', 40)->nullable();
            $table->string('automation_id')->nullable(); // referência à automação que gerou
            $table->text('input_summary')->nullable();
            $table->text('output_summary')->nullable();
            $table->string('model', 80)->nullable();
            $table->unsignedInteger('tokens_used')->default(0);
            $table->unsignedInteger('latency_ms')->default(0);
            $table->string('status', 20)->default('success'); // success | error
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        // Insights gerados pela IA para usuários
        Schema::create('ai_insights', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // recommendation | warning | opportunity | milestone
            $table->string('type', 30)->default('recommendation');
            $table->string('title');
            $table->text('content');
            $table->json('metadata')->nullable(); // contexto: journeyId, stepId, etc
            $table->boolean('is_read')->default(false);
            $table->boolean('is_dismissed')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_insights');
        Schema::dropIfExists('ai_interaction_logs');
        Schema::dropIfExists('ai_automations');
        Schema::dropIfExists('ai_configs');
    }
};
