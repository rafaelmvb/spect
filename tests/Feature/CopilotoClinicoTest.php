<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureInstalled;
use App\Models\ClinicalCopilotMessage;
use App\Models\ClinicalTest;
use App\Models\ClinicalTestSession;
use App\Models\Product;
use App\Models\Professional;
use App\Models\ProfessionalClinicalNote;
use App\Models\ProfessionalPatientLink;
use App\Models\ProfessionalTestAssignment;
use App\Models\User;
use App\Services\AiService;
use App\Services\ClinicalCopilotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CopilotoClinicoTest extends TestCase
{
    use RefreshDatabase;

    private User $profissional;

    private User $paciente;

    private Product $produto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(EnsureInstalled::class);

        $this->produto = $this->createTestProduct(['tenant_id' => 1, 'type' => Product::TYPE_AREA_MEMBROS]);

        $this->profissional = User::factory()->create(['role' => User::ROLE_PROFISSIONAL, 'tenant_id' => 1]);
        Professional::create([
            'tenant_id' => 1,
            'user_id' => $this->profissional->id,
            'name' => 'Dra. Teste',
            'email' => 'dra@test.com',
            'is_active' => true,
            'status' => 'approved',
        ]);

        $this->paciente = User::factory()->create(['role' => User::ROLE_ALUNO, 'tenant_id' => 1]);
        $this->paciente->products()->attach($this->produto->id);
    }

    private function vincular(string $status = ProfessionalPatientLink::STATUS_ACTIVE): ProfessionalPatientLink
    {
        return ProfessionalPatientLink::create([
            'professional_user_id' => $this->profissional->id,
            'patient_user_id' => $this->paciente->id,
            'product_id' => $this->produto->id,
            'status' => $status,
            'requested_at' => now(),
            'responded_at' => now(),
        ]);
    }

    /**
     * Troca a IA por um dublê que devolve o prompt recebido, para inspecionar
     * exatamente o que foi enviado ao modelo.
     */
    private function capturarPrompt(): object
    {
        $capturado = new class
        {
            public string $system = '';
        };

        $mock = Mockery::mock(AiService::class);
        $mock->shouldReceive('available')->andReturn(true);
        $mock->shouldReceive('complete')->andReturnUsing(function (array $mensagens) use ($capturado) {
            $capturado->system = $mensagens[0]['content'] ?? '';

            return 'Resposta do copiloto.';
        });
        $this->app->instance(AiService::class, $mock);

        return $capturado;
    }

    private function testeConcluido(string $nome, string $resultado, bool $atribuido): ClinicalTest
    {
        $teste = ClinicalTest::create([
            'tenant_id' => 1,
            'name' => $nome,
            'category' => 'geral',
            'is_active' => true,
        ]);

        if ($atribuido) {
            ProfessionalTestAssignment::create([
                'professional_user_id' => $this->profissional->id,
                'patient_user_id' => $this->paciente->id,
                'clinical_test_id' => $teste->id,
                'product_id' => $this->produto->id,
                'status' => 'completed',
                'assigned_at' => now(),
            ]);
        }

        ClinicalTestSession::create([
            'user_id' => $this->paciente->id,
            'clinical_test_id' => $teste->id,
            'product_id' => $this->produto->id,
            'status' => 'completed',
            'result_label' => $resultado,
            'completed_at' => now(),
        ]);

        return $teste;
    }

    // ---------- Autorização ----------

    public function test_sem_vinculo_ativo_nao_responde(): void
    {
        $this->capturarPrompt();
        $this->vincular(ProfessionalPatientLink::STATUS_PENDING);

        $this->actingAs($this->profissional)
            ->postJson("/p/meus-pacientes/{$this->paciente->id}/copiloto", ['pergunta' => 'Como está o caso?'])
            ->assertStatus(403);

        $this->assertSame(0, ClinicalCopilotMessage::count());
    }

    public function test_vinculo_revogado_fecha_o_copiloto(): void
    {
        $this->capturarPrompt();
        $vinculo = $this->vincular();

        $this->actingAs($this->profissional)
            ->postJson("/p/meus-pacientes/{$this->paciente->id}/copiloto", ['pergunta' => 'Como está o caso?'])
            ->assertOk();

        $vinculo->update(['status' => ProfessionalPatientLink::STATUS_REVOKED]);

        $this->actingAs($this->profissional)
            ->postJson("/p/meus-pacientes/{$this->paciente->id}/copiloto", ['pergunta' => 'E agora?'])
            ->assertStatus(403);
    }

    public function test_outro_profissional_nao_le_a_conversa(): void
    {
        $this->capturarPrompt();
        $this->vincular();

        $this->actingAs($this->profissional)
            ->postJson("/p/meus-pacientes/{$this->paciente->id}/copiloto", ['pergunta' => 'Segredo do caso'])
            ->assertOk();

        $outro = User::factory()->create(['role' => User::ROLE_PROFISSIONAL, 'tenant_id' => 1]);
        Professional::create([
            'tenant_id' => 1, 'user_id' => $outro->id, 'name' => 'Outro', 'email' => 'o@t.com',
            'is_active' => true, 'status' => 'approved',
        ]);
        ProfessionalPatientLink::create([
            'professional_user_id' => $outro->id,
            'patient_user_id' => $this->paciente->id,
            'product_id' => $this->produto->id,
            'status' => ProfessionalPatientLink::STATUS_ACTIVE,
            'requested_at' => now(), 'responded_at' => now(),
        ]);

        // O outro profissional atende a mesma pessoa, mas a conversa é de quem perguntou.
        $resposta = $this->actingAs($outro)
            ->getJson("/p/meus-pacientes/{$this->paciente->id}/copiloto")
            ->assertOk();

        $this->assertCount(0, $resposta->json('mensagens'));
    }

    // ---------- O dossiê não vaza o que não deve ----------

    public function test_dossie_traz_so_o_teste_que_o_profissional_aplicou(): void
    {
        $capturado = $this->capturarPrompt();
        $this->vincular();

        $this->testeConcluido('Escala aplicada pela profissional', 'Resultado do teste aplicado', atribuido: true);
        $this->testeConcluido('Teste que o paciente fez sozinho', 'Resultado privado do paciente', atribuido: false);

        $this->actingAs($this->profissional)
            ->postJson("/p/meus-pacientes/{$this->paciente->id}/copiloto", ['pergunta' => 'Resuma os testes.'])
            ->assertOk();

        $this->assertStringContainsString('Resultado do teste aplicado', $capturado->system);
        $this->assertStringNotContainsString(
            'Resultado privado do paciente',
            $capturado->system,
            'O dossiê enviado à IA incluiu um teste que o paciente respondeu por conta própria.'
        );
    }

    public function test_dossie_traz_so_as_notas_do_proprio_profissional(): void
    {
        $capturado = $this->capturarPrompt();
        $this->vincular();

        ProfessionalClinicalNote::create([
            'professional_user_id' => $this->profissional->id,
            'patient_user_id' => $this->paciente->id,
            'product_id' => $this->produto->id,
            'note' => 'Minha anotacao sobre o caso',
        ]);

        $outro = User::factory()->create(['role' => User::ROLE_PROFISSIONAL, 'tenant_id' => 1]);
        ProfessionalClinicalNote::create([
            'professional_user_id' => $outro->id,
            'patient_user_id' => $this->paciente->id,
            'product_id' => $this->produto->id,
            'note' => 'Anotacao sigilosa de outro profissional',
        ]);

        $this->actingAs($this->profissional)
            ->postJson("/p/meus-pacientes/{$this->paciente->id}/copiloto", ['pergunta' => 'O que anotei?'])
            ->assertOk();

        $this->assertStringContainsString('Minha anotacao sobre o caso', $capturado->system);
        $this->assertStringNotContainsString(
            'Anotacao sigilosa de outro profissional',
            $capturado->system,
            'A nota privada de outro profissional entrou no dossiê.'
        );
    }

    public function test_prompt_proibe_inventar_o_que_nao_esta_no_dossie(): void
    {
        $capturado = $this->capturarPrompt();
        $this->vincular();

        $this->actingAs($this->profissional)
            ->postJson("/p/meus-pacientes/{$this->paciente->id}/copiloto", ['pergunta' => 'E aí?'])
            ->assertOk();

        $this->assertStringContainsString('não consta no material disponível', $capturado->system);
        $this->assertStringContainsString('não emita diagnóstico', $capturado->system);
    }

    // ---------- Conversa ----------

    public function test_pergunta_e_resposta_ficam_registradas(): void
    {
        $this->capturarPrompt();
        $this->vincular();

        $this->actingAs($this->profissional)
            ->postJson("/p/meus-pacientes/{$this->paciente->id}/copiloto", [
                'pergunta' => 'Quais gatilhos apareceram nas últimas sessões?',
            ])
            ->assertOk()
            ->assertJsonPath('resposta.content', 'Resposta do copiloto.');

        $this->assertSame(2, ClinicalCopilotMessage::count());
        $this->assertSame('user', ClinicalCopilotMessage::orderBy('id')->first()->role);
        $this->assertSame('assistant', ClinicalCopilotMessage::orderByDesc('id')->first()->role);
    }

    public function test_historico_volta_na_ordem_da_conversa(): void
    {
        $this->capturarPrompt();
        $this->vincular();

        foreach (['Primeira pergunta', 'Segunda pergunta'] as $pergunta) {
            $this->actingAs($this->profissional)
                ->postJson("/p/meus-pacientes/{$this->paciente->id}/copiloto", ['pergunta' => $pergunta])
                ->assertOk();
        }

        $mensagens = $this->actingAs($this->profissional)
            ->getJson("/p/meus-pacientes/{$this->paciente->id}/copiloto")
            ->json('mensagens');

        $this->assertCount(4, $mensagens);
        $this->assertSame('Primeira pergunta', $mensagens[0]['content']);
        $this->assertSame('Segunda pergunta', $mensagens[2]['content']);
    }

    public function test_pergunta_vazia_e_recusada(): void
    {
        $this->capturarPrompt();
        $this->vincular();

        $this->actingAs($this->profissional)
            ->postJson("/p/meus-pacientes/{$this->paciente->id}/copiloto", ['pergunta' => 'ab'])
            ->assertStatus(422);
    }

    public function test_limpar_apaga_so_a_conversa_de_quem_pediu(): void
    {
        $this->capturarPrompt();
        $this->vincular();

        $this->actingAs($this->profissional)
            ->postJson("/p/meus-pacientes/{$this->paciente->id}/copiloto", ['pergunta' => 'Uma pergunta'])
            ->assertOk();

        $outroPaciente = User::factory()->create(['role' => User::ROLE_ALUNO, 'tenant_id' => 1]);
        ClinicalCopilotMessage::create([
            'professional_user_id' => $this->profissional->id,
            'patient_user_id' => $outroPaciente->id,
            'tenant_id' => 1,
            'role' => 'user',
            'content' => 'Conversa sobre outro paciente',
        ]);

        $this->actingAs($this->profissional)
            ->deleteJson("/p/meus-pacientes/{$this->paciente->id}/copiloto")
            ->assertOk();

        $this->assertSame(0, ClinicalCopilotMessage::daConversa($this->profissional->id, $this->paciente->id)->count());
        $this->assertSame(1, ClinicalCopilotMessage::daConversa($this->profissional->id, $outroPaciente->id)->count());
    }

    public function test_sem_ia_configurada_avisa_em_vez_de_quebrar(): void
    {
        $mock = Mockery::mock(AiService::class);
        $mock->shouldReceive('available')->andReturn(false);
        $this->app->instance(AiService::class, $mock);

        $this->vincular();

        $this->actingAs($this->profissional)
            ->postJson("/p/meus-pacientes/{$this->paciente->id}/copiloto", ['pergunta' => 'Alguma coisa'])
            ->assertStatus(422)
            ->assertJsonPath('ok', false);
    }

    public function test_servico_recusa_paciente_sem_vinculo(): void
    {
        $this->expectException(\RuntimeException::class);

        app(ClinicalCopilotService::class)->vinculoAtivo($this->profissional, $this->paciente);
    }
}
