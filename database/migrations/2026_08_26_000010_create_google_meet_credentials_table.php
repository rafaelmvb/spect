<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Autorizacao do Google por profissional, para a teleconsulta pelo Meet.
 *
 * A Meet REST API cria o espaco em nome de alguem: cada profissional conecta a
 * propria conta Google e a sala nasce sob ela. Nao ha conta unica da plataforma
 * criando reuniao para todo mundo.
 *
 * Os tokens sao gravados cifrados (ver GoogleMeetCredential::setTokens).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_meet_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();

            $table->string('google_email')->nullable();

            // Cifrados com Crypt::encryptString.
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();

            $table->timestamp('expires_at')->nullable();
            $table->text('scopes')->nullable();

            $table->timestamp('last_error_at')->nullable();
            $table->string('last_error', 255)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_meet_credentials');
    }
};
