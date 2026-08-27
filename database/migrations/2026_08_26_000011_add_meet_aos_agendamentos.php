<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sala de video do agendamento, criada pela Meet REST API.
 *
 * meet_space_name e o identificador do espaco na API ("spaces/xxx"); e por ele
 * que se busca o registro da conferencia e, quando o plano do Workspace
 * permitir, a transcricao da sessao.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('meet_space_name')->nullable()->after('status');
            $table->string('meet_uri')->nullable()->after('meet_space_name');
            $table->string('meet_code', 30)->nullable()->after('meet_uri');
            $table->timestamp('meet_created_at')->nullable()->after('meet_code');

            // Consentimento do paciente para gravacao e transcricao. Sem ele a
            // transcricao nao pode ser buscada (escopo, Parte 04 par. 5).
            $table->timestamp('recording_consent_at')->nullable()->after('meet_created_at');

            $table->index('meet_space_name');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex(['meet_space_name']);
            $table->dropColumn([
                'meet_space_name', 'meet_uri', 'meet_code',
                'meet_created_at', 'recording_consent_at',
            ]);
        });
    }
};
