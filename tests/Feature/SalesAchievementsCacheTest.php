<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Services\SalesAchievementsService;
use App\Support\ReportingPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SalesAchievementsCacheTest extends TestCase
{
    use RefreshDatabase;

    private function criarPedidoPago(int $tenantId, float $valor): void
    {
        $dono = User::factory()->create(['role' => User::ROLE_ADMIN, 'tenant_id' => $tenantId]);
        $produto = $this->createTestProduct(['tenant_id' => $tenantId, 'price' => $valor]);

        Order::create([
            'tenant_id' => $tenantId,
            'user_id' => $dono->id,
            'product_id' => $produto->id,
            'status' => 'completed',
            'amount' => $valor,
            'gateway' => 'stripe',
            'gateway_id' => 'ch_'.$tenantId.'_'.(int) $valor,
            'approved_manually' => false,
        ]);
    }

    public function test_soma_apenas_vendas_validas(): void
    {
        $this->criarPedidoPago(1, 100.0);
        $this->criarPedidoPago(1, 50.0);

        $total = app(SalesAchievementsService::class)->getValidSalesTotal(1);

        $this->assertSame(150.0, $total);
    }

    public function test_segunda_chamada_nao_refaz_a_query(): void
    {
        $this->criarPedidoPago(1, 100.0);
        $service = app(SalesAchievementsService::class);

        $this->assertSame(100.0, $service->getValidSalesTotal(1));

        DB::enableQueryLog();
        $service->getValidSalesTotal(1);
        $consultas = DB::getQueryLog();
        DB::disableQueryLog();

        $somas = array_filter($consultas, fn ($c) => str_contains(strtolower($c['query']), 'sum('));
        $this->assertCount(0, $somas, 'O SUM foi refeito apesar do cache.');
    }

    public function test_venda_nova_invalida_o_cache(): void
    {
        $this->criarPedidoPago(1, 100.0);
        $service = app(SalesAchievementsService::class);
        $this->assertSame(100.0, $service->getValidSalesTotal(1));

        $this->criarPedidoPago(1, 40.0);
        // É o que InvalidateDashboardCacheOnOrderCompleted faz ao concluir a venda.
        ReportingPeriod::bustDashboardCache(1);

        $this->assertSame(140.0, $service->getValidSalesTotal(1));
    }

    public function test_cache_nao_vaza_entre_tenants(): void
    {
        $this->criarPedidoPago(1, 100.0);
        $this->criarPedidoPago(2, 999.0);

        $service = app(SalesAchievementsService::class);

        $this->assertSame(100.0, $service->getValidSalesTotal(1));
        $this->assertSame(999.0, $service->getValidSalesTotal(2));
    }

    public function test_progresso_usa_o_total_cacheado(): void
    {
        $this->criarPedidoPago(1, 100.0);

        $progresso = app(SalesAchievementsService::class)->getProgressForTenant(1);

        $this->assertSame(100.0, $progresso['total_valid_sales']);
        $this->assertArrayHasKey('achievements', $progresso);
        $this->assertArrayHasKey('progress_percent', $progresso);
    }
}
