<?php

namespace Tests\Feature;

use App\Events\OrderCompleted;
use App\Jobs\SendAccessEmailJob;
use App\Listeners\SendAccessEmailOnOrderCompleted;
use App\Models\Order;
use App\Models\User;
use App\Support\QueueHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class EnvioAssincronoDeAcessoTest extends TestCase
{
    use RefreshDatabase;

    private function pedidoConcluido(): Order
    {
        $user = User::factory()->create(['role' => User::ROLE_ALUNO, 'tenant_id' => 1]);
        $produto = $this->createTestProduct(['tenant_id' => 1]);

        return Order::create([
            'tenant_id' => 1,
            'user_id' => $user->id,
            'product_id' => $produto->id,
            'status' => 'completed',
            'amount' => 10,
            'email' => 'comprador@test.com',
            'gateway' => 'stripe',
            'gateway_id' => 'ch_envio_'.uniqid(),
        ]);
    }

    private function simularWorkerAtivo(): void
    {
        // O phpunit roda com QUEUE_CONNECTION=sync, e em sync não existe worker
        // por definição — daí precisar de uma conexão real além do heartbeat.
        config(['queue.default' => 'database']);
        Cache::put('queue_heartbeat', now()->toIso8601String(), now()->addMinutes(10));
    }

    private function simularSemWorker(): void
    {
        config(['queue.default' => 'database']);
        Cache::forget('queue_heartbeat');
    }

    public function test_sem_heartbeat_a_fila_e_considerada_parada(): void
    {
        $this->simularSemWorker();

        $this->assertFalse(QueueHealth::workerAtivo());
        $this->assertTrue(QueueHealth::precisaRodarSincrono());
    }

    public function test_heartbeat_recente_indica_worker_ativo(): void
    {
        $this->simularWorkerAtivo();

        $this->assertTrue(QueueHealth::workerAtivo());
        $this->assertFalse(QueueHealth::precisaRodarSincrono());
    }

    public function test_heartbeat_velho_nao_conta(): void
    {
        config(['queue.default' => 'database']);
        Cache::put('queue_heartbeat', now()->subMinutes(30)->toIso8601String(), now()->addMinutes(60));

        $this->assertFalse(QueueHealth::workerAtivo());
    }

    public function test_conexao_sync_nunca_conta_como_worker(): void
    {
        config(['queue.default' => 'sync']);
        Cache::put('queue_heartbeat', now()->toIso8601String(), now()->addMinutes(10));

        $this->assertFalse(QueueHealth::workerAtivo());
    }

    public function test_com_worker_o_email_e_enfileirado(): void
    {
        Bus::fake();
        $this->simularWorkerAtivo();

        $pedido = $this->pedidoConcluido();
        app(SendAccessEmailOnOrderCompleted::class)->handle(new OrderCompleted($pedido));

        Bus::assertDispatched(
            SendAccessEmailJob::class,
            fn (SendAccessEmailJob $job) => $job->orderId === $pedido->id
        );
    }

    public function test_sem_worker_o_email_sai_na_hora(): void
    {
        Bus::fake();
        $this->simularSemWorker();

        $pedido = $this->pedidoConcluido();
        app(SendAccessEmailOnOrderCompleted::class)->handle(new OrderCompleted($pedido));

        // Sem worker o job roda inline em vez de esperar por quem não vai rodar.
        Bus::assertDispatchedSync(SendAccessEmailJob::class);
    }

    public function test_falha_no_envio_nao_propaga_para_o_webhook(): void
    {
        $this->simularSemWorker();
        $pedido = $this->pedidoConcluido();

        // Serviço quebrado: o listener precisa engolir para não devolver 500
        // ao gateway e provocar retentativa de um pagamento já confirmado.
        $this->mock(\App\Services\AccessEmailService::class, function ($mock) {
            $mock->shouldReceive('getAccessDataForOrder')->andThrow(new \RuntimeException('SMTP fora do ar'));
        });

        app(SendAccessEmailOnOrderCompleted::class)->handle(new OrderCompleted($pedido));

        $this->assertSame('completed', $pedido->fresh()->status);
    }

    public function test_job_lida_com_pedido_inexistente(): void
    {
        $job = new SendAccessEmailJob(999999);

        $job->handle(app(\App\Services\AccessEmailService::class));

        $this->assertTrue(true, 'Job não pode explodir com pedido removido.');
    }
}
