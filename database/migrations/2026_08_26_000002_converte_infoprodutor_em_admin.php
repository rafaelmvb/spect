<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * O Spectra deixou de ser SaaS: nao existem mais clientes hospedados no painel,
 * so a propria empresa. O papel 'infoprodutor' significava "dono de um tenant"
 * e perde o sentido — quem tinha esse papel vira admin.
 *
 * Antes da conversao, garante tenant_id preenchido (era descartado por mass
 * assignment; ver 2026_08_26_000001_backfill_users_tenant_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('role', 'infoprodutor')
            ->whereNull('tenant_id')
            ->update(['tenant_id' => DB::raw('id')]);

        DB::table('users')
            ->where('role', 'infoprodutor')
            ->update(['role' => 'admin']);
    }

    public function down(): void
    {
        // Sem rollback: depois da conversao nao ha como distinguir quem era
        // admin de origem de quem virou admin agora.
    }
};
