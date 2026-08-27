<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Perfis infantis sob a conta de um responsavel.
 *
 * Escopo, Parte 01: "o pai, a mae ou o responsavel cria um perfil para cada
 * filho, informando nome, idade e vinculo" e "uma mesma conta de responsavel
 * pode cadastrar e acompanhar varios perfis infantis, sem misturar respostas
 * ou relatorios".
 *
 * A crianca nao tem conta propria: nao ha login, e-mail nem senha. O perfil
 * existe apenas sob o responsavel, e some junto com a conta dele.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('child_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guardian_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();

            $table->string('name', 120);
            $table->date('birth_date')->nullable();

            // mae | pai | avo | responsavel_legal | outro
            $table->string('relationship', 30);
            $table->string('relationship_other', 60)->nullable();

            $table->timestamps();

            $table->index(['guardian_user_id', 'tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('child_profiles');
    }
};
