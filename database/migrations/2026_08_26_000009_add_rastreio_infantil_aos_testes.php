<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca o teste como rastreio infantil e liga a sessao ao perfil da crianca.
 *
 * Escopo, Parte 03 par. 7: "marcacao do teste como infantil e definicao de que
 * o respondente obrigatorio e o pai, a mae ou o responsavel".
 * Escopo, Parte 01: "o resultado fica salvo no perfil da crianca e informa
 * claramente quem respondeu e qual e o vinculo com ela".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('clinical_tests', 'is_child_screening')) {
            Schema::table('clinical_tests', function (Blueprint $table) {
                $table->boolean('is_child_screening')->default(false)->after('is_active');
            });
        }

        if (! Schema::hasColumn('clinical_test_sessions', 'child_profile_id')) {
            Schema::table('clinical_test_sessions', function (Blueprint $table) {
                /*
                 * 0 = autorrelato do proprio usuario. Nao e nullable de proposito:
                 * MySQL trata NULLs como distintos numa unique, e a chave abaixo
                 * precisa impedir duas sessoes do mesmo teste para a mesma crianca.
                 */
                $table->unsignedBigInteger('child_profile_id')->default(0)->after('user_id');

                // Vinculo de quem respondeu, congelado no momento da aplicacao: se o
                // perfil for editado depois, o relatorio antigo continua fiel.
                $table->string('respondent_relationship', 30)->nullable()->after('child_profile_id');
            });
        }

        /*
         * A chave antiga so permitia uma sessao por usuario/teste, o que impediria
         * o responsavel de aplicar o mesmo rastreio a dois filhos.
         *
         * A ordem importa: no MySQL o indice uq_user_test_session sustenta a
         * foreign key de user_id, e dropa-lo antes de existir substituto devolve
         * "Cannot drop index: needed in a foreign key constraint". Criando a nova
         * unique primeiro — que tambem comeca por user_id — a FK passa a se apoiar
         * nela e o indice antigo fica livre para sair.
         */
        Schema::table('clinical_test_sessions', function (Blueprint $table) {
            $table->unique(['user_id', 'clinical_test_id', 'child_profile_id'], 'uq_user_test_child_session');
        });

        Schema::table('clinical_test_sessions', function (Blueprint $table) {
            $table->dropUnique('uq_user_test_session');
            $table->index('child_profile_id');
        });
    }

    public function down(): void
    {
        // Mesmo cuidado na volta: recria a chave antiga antes de remover a nova.
        Schema::table('clinical_test_sessions', function (Blueprint $table) {
            $table->unique(['user_id', 'clinical_test_id'], 'uq_user_test_session');
        });

        Schema::table('clinical_test_sessions', function (Blueprint $table) {
            $table->dropUnique('uq_user_test_child_session');
            $table->dropIndex(['child_profile_id']);
            $table->dropColumn(['child_profile_id', 'respondent_relationship']);
        });

        Schema::table('clinical_tests', function (Blueprint $table) {
            $table->dropColumn('is_child_screening');
        });
    }
};
