<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * O vinculo profissional-paciente era criado direto com status 'active', sem
 * nenhum aceite. O escopo exige convite, aceite do paciente ou responsavel e
 * possibilidade de revogacao.
 *
 * Vinculos que ja existem continuam ativos: revoga-los em massa interromperia
 * acompanhamento em curso. Mas ficam com responded_at nulo, o que os distingue
 * de quem de fato aceitou — e permite pedir a confirmacao depois.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('professional_patient_links', function (Blueprint $table) {
            $table->timestamp('requested_at')->nullable()->after('status');
            $table->timestamp('responded_at')->nullable()->after('requested_at');
        });

        // Marca a origem dos vinculos anteriores: ativos, porem sem aceite registrado.
        DB::table('professional_patient_links')
            ->whereNull('requested_at')
            ->update(['requested_at' => DB::raw('created_at')]);

        // O default de 'pending' fica no model (ProfessionalPatientLink::$attributes):
        // mexer no default da coluna exigiria SQL especifico de cada banco e
        // quebraria o SQLite usado nos testes.
    }

    public function down(): void
    {
        Schema::table('professional_patient_links', function (Blueprint $table) {
            $table->dropColumn(['requested_at', 'responded_at']);
        });
    }
};
