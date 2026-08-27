<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Moderacao graduada: hoje so existia banimento permanente.
 *
 * Escopo, Parte 03 par. 4: advertencia, suspensao temporaria por periodo
 * definido (7 ou 30 dias) e banimento definitivo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_bans', function (Blueprint $table) {
            // NULL = permanente. Com data, a restricao cai sozinha no vencimento.
            $table->timestamp('expires_at')->nullable()->after('reason');

            // ban | suspension | warning
            $table->string('kind', 20)->default('ban')->after('expires_at');

            $table->index(['user_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::table('community_bans', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'expires_at']);
            $table->dropColumn(['expires_at', 'kind']);
        });
    }
};
