<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureInstalled;
use App\Models\ChildProfile;
use App\Models\ClinicalTest;
use App\Models\ClinicalTestSession;
use App\Models\Product;
use App\Models\User;
use App\Models\UserChallengeTag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RastreioInfantilTest extends TestCase
{
    use RefreshDatabase;

    private User $responsavel;

    private Product $produto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(EnsureInstalled::class);

        $this->produto = $this->createTestProduct(['tenant_id' => 1, 'type' => Product::TYPE_AREA_MEMBROS]);
        $this->responsavel = User::factory()->create(['role' => User::ROLE_ALUNO, 'tenant_id' => 1]);
        $this->responsavel->products()->attach($this->produto->id);
    }

    private function criarPerfil(string $nome, string $vinculo = 'mae'): ChildProfile
    {
        return ChildProfile::create([
            'guardian_user_id' => $this->responsavel->id,
            'tenant_id' => 1,
            'name' => $nome,
            'birth_date' => now()->subYears(8)->format('Y-m-d'),
            'relationship' => $vinculo,
        ]);
    }

    private function criarTeste(bool $infantil): ClinicalTest
    {
        return ClinicalTest::create([
            'tenant_id' => 1,
            'name' => $infantil ? 'Rastreio infantil' : 'Autoavaliação',
            'category' => 'geral',
            'is_active' => true,
            'is_child_screening' => $infantil,
        ]);
    }

    // ---------- CRUD de perfis ----------

    public function test_responsavel_cadastra_perfil_infantil(): void
    {
        $this->actingAs($this->responsavel)
            ->postJson('/m/perfis-infantis', [
                'name' => 'Joana',
                'birth_date' => now()->subYears(7)->format('Y-m-d'),
                'relationship' => 'mae',
            ])
            ->assertCreated()
            ->assertJsonPath('perfil.name', 'Joana')
            ->assertJsonPath('perfil.idade', 7);

        $this->assertSame(1, ChildProfile::count());
    }

    public function test_vinculo_outro_exige_descricao(): void
    {
        $this->actingAs($this->responsavel)
            ->postJson('/m/perfis-infantis', [
                'name' => 'Joana',
                'relationship' => 'outro',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('relationship_other');
    }

    public function test_data_de_nascimento_no_futuro_e_recusada(): void
    {
        $this->actingAs($this->responsavel)
            ->postJson('/m/perfis-infantis', [
                'name' => 'Joana',
                'birth_date' => now()->addYear()->format('Y-m-d'),
                'relationship' => 'pai',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('birth_date');
    }

    public function test_responsavel_nao_alcanca_perfil_de_outra_familia(): void
    {
        $outroResponsavel = User::factory()->create(['role' => User::ROLE_ALUNO, 'tenant_id' => 1]);
        $outroResponsavel->products()->attach($this->produto->id);
        $perfilAlheio = $this->criarPerfil('Filho de outra pessoa');

        $this->actingAs($outroResponsavel)
            ->putJson("/m/perfis-infantis/{$perfilAlheio->id}", [
                'name' => 'Renomeado',
                'relationship' => 'pai',
            ])
            ->assertStatus(404);

        $this->assertSame('Filho de outra pessoa', $perfilAlheio->fresh()->name);
    }

    public function test_excluir_perfil_leva_os_rastreios_junto(): void
    {
        $perfil = $this->criarPerfil('Joana');
        $teste = $this->criarTeste(true);
        ClinicalTestSession::create([
            'user_id' => $this->responsavel->id,
            'child_profile_id' => $perfil->id,
            'clinical_test_id' => $teste->id,
            'product_id' => $this->produto->id,
            'status' => 'completed',
        ]);

        $this->actingAs($this->responsavel)
            ->deleteJson("/m/perfis-infantis/{$perfil->id}")
            ->assertOk();

        $this->assertSame(0, ChildProfile::count());
        $this->assertSame(0, ClinicalTestSession::where('child_profile_id', $perfil->id)->count());
    }

    // ---------- Aplicação do rastreio ----------

    public function test_rastreio_infantil_exige_escolher_a_crianca(): void
    {
        $teste = $this->criarTeste(true);

        $this->actingAs($this->responsavel)
            ->postJson("/m/testes/{$teste->id}/iniciar", [])
            ->assertStatus(422)
            ->assertJsonPath('requires_child_profile', true);

        $this->assertSame(0, ClinicalTestSession::count());
    }

    public function test_nao_aplica_rastreio_para_crianca_de_outra_familia(): void
    {
        $teste = $this->criarTeste(true);
        $perfilAlheio = $this->criarPerfil('Filho de outra pessoa');

        $intruso = User::factory()->create(['role' => User::ROLE_ALUNO, 'tenant_id' => 1]);
        $intruso->products()->attach($this->produto->id);

        $this->actingAs($intruso)
            ->postJson("/m/testes/{$teste->id}/iniciar", ['child_profile_id' => $perfilAlheio->id])
            ->assertStatus(422);
    }

    public function test_teste_comum_segue_como_autorrelato(): void
    {
        $teste = $this->criarTeste(false);

        $this->actingAs($this->responsavel)
            ->postJson("/m/testes/{$teste->id}/iniciar", [])
            ->assertOk()
            ->assertJsonPath('child_profile_id', null);

        $this->assertSame(0, (int) ClinicalTestSession::first()->child_profile_id);
    }

    public function test_registra_o_vinculo_de_quem_respondeu(): void
    {
        $teste = $this->criarTeste(true);
        $perfil = $this->criarPerfil('Joana', 'avo');

        $this->actingAs($this->responsavel)
            ->postJson("/m/testes/{$teste->id}/iniciar", ['child_profile_id' => $perfil->id])
            ->assertOk();

        $sessao = ClinicalTestSession::first();
        $this->assertSame($perfil->id, (int) $sessao->child_profile_id);
        $this->assertSame('avo', $sessao->respondent_relationship);
    }

    // ---------- Isolamento entre irmãos ----------

    public function test_mesmo_teste_pode_ser_aplicado_a_dois_filhos(): void
    {
        $teste = $this->criarTeste(true);
        $joana = $this->criarPerfil('Joana');
        $pedro = $this->criarPerfil('Pedro');

        $this->actingAs($this->responsavel)
            ->postJson("/m/testes/{$teste->id}/iniciar", ['child_profile_id' => $joana->id])
            ->assertOk();

        $this->actingAs($this->responsavel)
            ->postJson("/m/testes/{$teste->id}/iniciar", ['child_profile_id' => $pedro->id])
            ->assertOk();

        // A chave antiga (user_id + clinical_test_id) impediria a segunda.
        $this->assertSame(2, ClinicalTestSession::count());
        $this->assertSame(1, ClinicalTestSession::where('child_profile_id', $joana->id)->count());
        $this->assertSame(1, ClinicalTestSession::where('child_profile_id', $pedro->id)->count());
    }

    public function test_autorrelato_e_rastreio_do_filho_convivem(): void
    {
        $teste = $this->criarTeste(true);
        $perfil = $this->criarPerfil('Joana');

        ClinicalTestSession::create([
            'user_id' => $this->responsavel->id,
            'child_profile_id' => 0,
            'clinical_test_id' => $teste->id,
            'product_id' => $this->produto->id,
            'status' => 'completed',
        ]);

        $this->actingAs($this->responsavel)
            ->postJson("/m/testes/{$teste->id}/iniciar", ['child_profile_id' => $perfil->id])
            ->assertOk();

        $this->assertSame(2, ClinicalTestSession::count());
    }

    // ---------- O resultado da criança não vira perfil do adulto ----------

    public function test_tags_do_rastreio_infantil_nao_vao_para_o_responsavel(): void
    {
        $teste = $this->criarTeste(true);
        $perfil = $this->criarPerfil('Joana');

        $questao = $teste->questions()->create([
            'text' => 'A criança se distrai com facilidade?',
            'type' => 'scale',
            'position' => 1,
        ]);
        $teste->scoringRules()->create([
            'min_score' => 0,
            'max_score' => 100,
            'result_label' => 'Sinais presentes',
            'challenge_tags' => ['atencao-infantil'],
        ]);

        $iniciar = $this->actingAs($this->responsavel)
            ->postJson("/m/testes/{$teste->id}/iniciar", ['child_profile_id' => $perfil->id])
            ->assertOk();
        $sessionId = $iniciar->json('session_id');

        $this->actingAs($this->responsavel)->postJson("/m/testes/{$teste->id}/responder", [
            'session_id' => $sessionId,
            'question_id' => $questao->id,
            'answer' => 3,
        ])->assertOk();

        $this->actingAs($this->responsavel)
            ->postJson("/m/testes/{$teste->id}/concluir", ['session_id' => $sessionId])
            ->assertOk();

        // O resultado fica na sessão da criança...
        $sessao = ClinicalTestSession::find($sessionId);
        $this->assertSame('completed', $sessao->status);
        $this->assertContains('atencao-infantil', $sessao->challenge_tags ?? []);

        // ...e não contamina a trilha nem o Mentor de IA do adulto.
        $this->assertSame(
            0,
            UserChallengeTag::where('user_id', $this->responsavel->id)->count(),
            'A tag do rastreio do filho foi parar no perfil do responsável.'
        );
    }

    public function test_autorrelato_continua_alimentando_o_perfil_de_quem_respondeu(): void
    {
        $teste = $this->criarTeste(false);

        $questao = $teste->questions()->create([
            'text' => 'Você se distrai com facilidade?',
            'type' => 'scale',
            'position' => 1,
        ]);
        $teste->scoringRules()->create([
            'min_score' => 0,
            'max_score' => 100,
            'result_label' => 'Sinais presentes',
            'challenge_tags' => ['atencao'],
        ]);

        $sessionId = $this->actingAs($this->responsavel)
            ->postJson("/m/testes/{$teste->id}/iniciar", [])
            ->json('session_id');

        $this->actingAs($this->responsavel)->postJson("/m/testes/{$teste->id}/responder", [
            'session_id' => $sessionId,
            'question_id' => $questao->id,
            'answer' => 3,
        ]);

        $this->actingAs($this->responsavel)
            ->postJson("/m/testes/{$teste->id}/concluir", ['session_id' => $sessionId])
            ->assertOk();

        $this->assertSame(1, UserChallengeTag::where('user_id', $this->responsavel->id)->count());
    }

    // ---------- Telemetria segregada ----------

    public function test_hash_da_crianca_difere_do_hash_do_responsavel(): void
    {
        $perfil = $this->criarPerfil('Joana');

        $this->assertNotSame(
            \App\Services\TelemetryService::subjectHash((int) $this->responsavel->id),
            $perfil->subjectHash(),
            'Eventos da criança precisam ser segregados dos do responsável.'
        );
    }
}
