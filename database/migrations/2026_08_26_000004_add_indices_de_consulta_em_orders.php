<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A tabela orders so tinha indice em tenant_id (e nas FKs).
 *
 * - (gateway, gateway_id): todo webhook de pagamento faz
 *   where('gateway', ...)->where('gateway_id', ...) para achar o pedido.
 *   Sem indice, cada notificacao de gateway varria a tabela inteira.
 * - (tenant_id, status, created_at): dashboard, relatorios e a listagem de
 *   vendas filtram por status dentro de um periodo.
 * - (email): busca de pedido por e-mail do comprador no painel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['gateway', 'gateway_id'], 'orders_gateway_gateway_id_index');
            $table->index(['tenant_id', 'status', 'created_at'], 'orders_tenant_status_created_index');
            $table->index('email', 'orders_email_index');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_gateway_gateway_id_index');
            $table->dropIndex('orders_tenant_status_created_index');
            $table->dropIndex('orders_email_index');
        });
    }
};
