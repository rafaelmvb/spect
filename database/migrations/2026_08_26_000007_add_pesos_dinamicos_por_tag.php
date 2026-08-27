<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pesos dinamicos por tag, nos dois lados da recomendacao.
 *
 * Escopo, Parte 02 par. 6: "as tabelas de conteudos e usuarios devem aceitar
 * pesos dinamicos por tag" desde o primeiro dia — adicionar depois significa
 * comecar a calibrar do zero.
 *
 * Lado do usuario: user_challenge_tags ganha peso, que nasce no maximo quando
 * vem de um teste e e reajustado pelo consumo real (par. 4).
 * Lado do conteudo: nova tabela de tags por aula, com os tres niveis do par. 2.1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_challenge_tags', function (Blueprint $table) {
            // 0..1 — o teste atribui 1.0; o comportamento sobe ou desce a partir dai.
            $table->decimal('weight', 4, 3)->default(1.000)->after('tag');
            $table->timestamp('weight_updated_at')->nullable()->after('weight');
        });

        // Tags ja existentes vieram de teste concluido: peso maximo.
        DB::table('user_challenge_tags')->update(['weight' => 1.000]);

        Schema::create('content_tags', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();

            // 'member_lesson' | 'clinical_test' | 'journey'
            $table->string('taggable_type', 40);
            $table->string('taggable_id', 64);

            $table->string('tag', 100);

            // categoria principal | formato | nivel — os tres niveis do par. 2.1
            $table->string('dimension', 20)->default('categoria');

            $table->decimal('weight', 4, 3)->default(1.000);

            $table->timestamps();

            $table->unique(['taggable_type', 'taggable_id', 'tag', 'dimension'], 'uq_content_tag');
            $table->index(['tag', 'dimension']);
            $table->index(['taggable_type', 'taggable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_tags');

        Schema::table('user_challenge_tags', function (Blueprint $table) {
            $table->dropColumn(['weight', 'weight_updated_at']);
        });
    }
};
