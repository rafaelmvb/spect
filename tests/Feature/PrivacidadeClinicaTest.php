<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureInstalled;
use App\Models\ClinicalTest;
use App\Models\ClinicalTestSession;
use App\Models\Product;
use App\Models\Professional;
use App\Models\ProfessionalPatientLink;
use App\Models\ProfessionalTestAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivacidadeClinicaTest extends TestCase
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
            'specialty' => 'Psicologia',
            'registration_type' => 'CRP',
            'registration_number' => '12345/SP',
            'is_active' => true,
            'status' => 'approved',
        ]);

        $this->paciente = User::factory()->create(['role' => User::ROLE_ALUNO, 'tenant_id' => 1]);
        $this->paciente->products()->attach($this->produto->id);
    }

    private function criarTeste(string $nome): ClinicalTest
    {
        return ClinicalTest::create([
            'tenant_id' => 1,
            'name' => $nome,
            'category' => 'geral',
            'is_active' => true,
        ]);
    }

    private function concluirTeste(ClinicalTest $teste, string $resultado): ClinicalTestSession
    {
        return ClinicalTestSession::create([
            'user_id' => $this->paciente->id,
            'clinical_test_id' => $teste->id,
            'product_id' => $this->produto->id,
            'status' => 'completed',
            'score' => 10,
            'result_label' => $resultado,
            'completed_at' => now(),
        ]);
    }

    // ---------- Vazamento de resultados ----------

    public function test_ficha_mostra_apenas_teste_que_o_profissional_enviou(): void
    {
        $enviado = $this->criarTeste('Enviado pela profissional');
        $porContaPropria = $this->criarTeste('Respondido sozinho pelo aluno');

        ProfessionalTestAssignment::create([
            'professional_user_id' => $this->profissional->id,
            'patient_user_id' => $this->paciente->id,
            'clinical_test_id' => $enviado->id,
            'product_id' => $this->produto->id,
            'status' => 'completed',
            'assigned_at' => now(),
        ]);

        $this->concluirTeste($enviado, 'Resultado do enviado');
        $this->concluirTeste($porContaPropria, 'Resultado privado do aluno');

        ProfessionalPatientLink::create([
            'professional_user_id' => $this->profissional->id,
            'patient_user_id' => $this->paciente->id,
            'product_id' => $this->produto->id,
            'status' => ProfessionalPatientLink::STATUS_ACTIVE,
            'requested_at' => now(),
            'responded_at' => now(),
        ]);

        $resposta = $this->actingAs($this->profissional)->get('/p/meus-pacientes');
        $resposta->assertOk();

        $conteudo = $resposta->getContent();
        $this->assertStringContainsString('Resultado do enviado', $conteudo);
        $this->assertStringNotContainsString(
            'Resultado privado do aluno',
            $conteudo,
            'Vazou para o profissional um teste que o aluno respondeu por conta própria.'
        );
    }

    // ---------- Consentimento do vínculo ----------

    public function test_vinculo_nasce_pendente(): void
    {
        $link = ProfessionalPatientLink::convidar(
            $this->profissional->id,
            $this->paciente->id,
            (string) $this->produto->id
        );

        $this->assertSame(ProfessionalPatientLink::STATUS_PENDING, $link->status);
        $this->assertFalse($link->estaAtivo());
        $this->assertNotNull($link->requested_at);
        $this->assertNull($link->responded_at);
    }

    public function test_convite_pendente_nao_da_acesso_a_ficha(): void
    {
        ProfessionalPatientLink::convidar(
            $this->profissional->id,
            $this->paciente->id,
            (string) $this->produto->id
        );

        $resposta = $this->actingAs($this->profissional)->get('/p/meus-pacientes');
        $resposta->assertOk();

        $this->assertStringNotContainsString(
            $this->paciente->email,
            $resposta->getContent(),
            'Paciente apareceu na lista sem ter aceitado o vínculo.'
        );
    }

    public function test_aluno_aceita_e_o_profissional_passa_a_ver(): void
    {
        $link = ProfessionalPatientLink::convidar(
            $this->profissional->id,
            $this->paciente->id,
            (string) $this->produto->id
        );

        $this->actingAs($this->paciente)
            ->postJson("/m/profissionais/vinculo/{$link->id}", ['acao' => 'aceitar'])
            ->assertOk()
            ->assertJson(['ok' => true, 'status' => ProfessionalPatientLink::STATUS_ACTIVE]);

        $link->refresh();
        $this->assertTrue($link->estaAtivo());
        $this->assertNotNull($link->responded_at);

        $resposta = $this->actingAs($this->profissional)->get('/p/meus-pacientes');
        $this->assertStringContainsString($this->paciente->email, $resposta->getContent());
    }

    public function test_aluno_revoga_e_o_acesso_fecha(): void
    {
        $link = ProfessionalPatientLink::create([
            'professional_user_id' => $this->profissional->id,
            'patient_user_id' => $this->paciente->id,
            'product_id' => $this->produto->id,
            'status' => ProfessionalPatientLink::STATUS_ACTIVE,
            'requested_at' => now(),
            'responded_at' => now(),
        ]);

        $this->actingAs($this->paciente)
            ->postJson("/m/profissionais/vinculo/{$link->id}", ['acao' => 'revogar'])
            ->assertOk();

        $this->assertSame(ProfessionalPatientLink::STATUS_REVOKED, $link->fresh()->status);

        $resposta = $this->actingAs($this->profissional)->get('/p/meus-pacientes');
        $this->assertStringNotContainsString($this->paciente->email, $resposta->getContent());
    }

    public function test_aluno_recusa_o_convite(): void
    {
        $link = ProfessionalPatientLink::convidar(
            $this->profissional->id,
            $this->paciente->id,
            (string) $this->produto->id
        );

        $this->actingAs($this->paciente)
            ->postJson("/m/profissionais/vinculo/{$link->id}", ['acao' => 'recusar'])
            ->assertOk();

        $this->assertSame(ProfessionalPatientLink::STATUS_DECLINED, $link->fresh()->status);
    }

    public function test_convite_recusado_nao_volta_a_pendente_sozinho(): void
    {
        $link = ProfessionalPatientLink::convidar(
            $this->profissional->id,
            $this->paciente->id,
            (string) $this->produto->id
        );
        $link->update(['status' => ProfessionalPatientLink::STATUS_DECLINED, 'responded_at' => now()]);

        // Profissional tenta convidar de novo.
        $mesmo = ProfessionalPatientLink::convidar(
            $this->profissional->id,
            $this->paciente->id,
            (string) $this->produto->id
        );

        $this->assertSame($link->id, $mesmo->id);
        $this->assertSame(ProfessionalPatientLink::STATUS_DECLINED, $mesmo->status);
    }

    public function test_aluno_nao_responde_vinculo_de_outra_pessoa(): void
    {
        $outro = User::factory()->create(['role' => User::ROLE_ALUNO, 'tenant_id' => 1]);
        $outro->products()->attach($this->produto->id);

        $link = ProfessionalPatientLink::convidar(
            $this->profissional->id,
            $this->paciente->id,
            (string) $this->produto->id
        );

        $this->actingAs($outro)
            ->postJson("/m/profissionais/vinculo/{$link->id}", ['acao' => 'aceitar'])
            ->assertStatus(404);

        $this->assertSame(ProfessionalPatientLink::STATUS_PENDING, $link->fresh()->status);
    }

    public function test_nao_aceita_convite_ja_respondido(): void
    {
        $link = ProfessionalPatientLink::create([
            'professional_user_id' => $this->profissional->id,
            'patient_user_id' => $this->paciente->id,
            'product_id' => $this->produto->id,
            'status' => ProfessionalPatientLink::STATUS_REVOKED,
            'requested_at' => now(),
            'responded_at' => now(),
        ]);

        $this->actingAs($this->paciente)
            ->postJson("/m/profissionais/vinculo/{$link->id}", ['acao' => 'aceitar'])
            ->assertStatus(422);
    }
}
