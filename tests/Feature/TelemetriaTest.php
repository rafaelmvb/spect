<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureInstalled;
use App\Models\ContentTag;
use App\Models\EventLog;
use App\Models\Product;
use App\Models\User;
use App\Models\UserChallengeTag;
use App\Services\TagWeightService;
use App\Services\TelemetryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TelemetriaTest extends TestCase
{
    use RefreshDatabase;

    private User $aluno;

    private Product $produto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(EnsureInstalled::class);

        $this->produto = $this->createTestProduct(['tenant_id' => 1, 'type' => Product::TYPE_AREA_MEMBROS]);
        $this->aluno = User::factory()->create(['role' => User::ROLE_ALUNO, 'tenant_id' => 1]);
        $this->aluno->products()->attach($this->produto->id);
    }

    // ---------- Anonimato ----------

    public function test_evento_nao_guarda_dado_cadastral(): void
    {
        app(TelemetryService::class)->registrar($this->aluno, TelemetryService::LESSON_PAUSE, [
            'subject_type' => 'member_lesson',
            'subject_id' => '42',
            'position' => 130,
            'duration' => 600,
        ]);

        $log = EventLog::first();
        $bruto = json_encode($log->getAttributes());

        $this->assertStringNotContainsString($this->aluno->email, $bruto);
        $this->assertStringNotContainsString($this->aluno->name, $bruto);

        // A tabela não tem coluna de usuário: o elo é só o hash.
        $this->assertArrayNotHasKey('user_id', $log->getAttributes());
        $this->assertNotSame((string) $this->aluno->id, $log->subject_hash);

        // Sem a APP_KEY o hash não se reproduz.
        $this->assertNotSame(hash('sha256', (string) $this->aluno->id), $log->subject_hash);
    }

    public function test_hash_e_estavel_para_a_mesma_pessoa_e_diferente_entre_pessoas(): void
    {
        $outro = User::factory()->create(['role' => User::ROLE_ALUNO, 'tenant_id' => 1]);

        $meu = TelemetryService::subjectHash((int) $this->aluno->id);

        $this->assertSame($meu, TelemetryService::subjectHash((int) $this->aluno->id));
        $this->assertNotSame($meu, TelemetryService::subjectHash((int) $outro->id));
        $this->assertSame(64, strlen($meu));
    }

    public function test_contexto_descarta_chave_fora_da_allowlist(): void
    {
        app(TelemetryService::class)->registrar($this->aluno, TelemetryService::SEARCH, [
            'context' => [
                'formato' => 'video',
                'email' => 'vazou@test.com',
                'cpf' => '00000000000',
            ],
        ]);

        $contexto = EventLog::first()->context;

        $this->assertSame(['formato' => 'video'], $contexto);
    }

    // ---------- Endpoint ----------

    public function test_endpoint_grava_evento_valido(): void
    {
        $this->actingAs($this->aluno)
            ->postJson('/m/telemetria', [
                'event' => TelemetryService::LESSON_PAUSE,
                'subject_type' => 'member_lesson',
                'subject_id' => '7',
                'position' => 45,
                'duration' => 300,
            ])
            ->assertStatus(202);

        $this->assertSame(1, EventLog::count());
        $this->assertSame(45, EventLog::first()->position);
    }

    public function test_endpoint_recusa_evento_fora_da_allowlist(): void
    {
        $this->actingAs($this->aluno)
            ->postJson('/m/telemetria', ['event' => 'evento.inventado'])
            ->assertStatus(422);

        $this->assertSame(0, EventLog::count());
    }

    public function test_endpoint_exige_sessao(): void
    {
        // O middleware auth redireciona o visitante para o login.
        $this->post('/m/telemetria', ['event' => TelemetryService::LESSON_PAUSE])
            ->assertRedirect(); // destino do redirect e detalhe do middleware

        $this->assertSame(0, EventLog::count());
    }

    // ---------- Calibragem de peso ----------

    private function tagDoAluno(string $tag, float $peso = 1.0): UserChallengeTag
    {
        return UserChallengeTag::create([
            'user_id' => $this->aluno->id,
            'tenant_id' => 1,
            'tag' => $tag,
            'weight' => $peso,
            'source_type' => 'clinical_test',
        ]);
    }

    private function tagDoConteudo(string $conteudoId, string $tag): ContentTag
    {
        return ContentTag::create([
            'tenant_id' => 1,
            'taggable_type' => 'member_lesson',
            'taggable_id' => $conteudoId,
            'tag' => $tag,
            'dimension' => ContentTag::DIM_CATEGORIA,
        ]);
    }

    public function test_tag_nasce_com_peso_maximo(): void
    {
        $tag = $this->tagDoAluno('ansiedade');

        $this->assertSame(1.0, $tag->fresh()->weight);
    }

    public function test_conclusao_reforca_o_peso(): void
    {
        $this->tagDoAluno('ansiedade', 0.5);
        $this->tagDoConteudo('10', 'ansiedade');

        app(TagWeightService::class)->calibrarPorConsumo($this->aluno->id, 1, 'member_lesson', '10', 0.95);

        $this->assertGreaterThan(0.5, UserChallengeTag::where('tag', 'ansiedade')->first()->weight);
    }

    public function test_abandono_precoce_penaliza_o_peso(): void
    {
        $this->tagDoAluno('foco', 1.0);
        $this->tagDoConteudo('11', 'foco');

        app(TagWeightService::class)->calibrarPorConsumo($this->aluno->id, 1, 'member_lesson', '11', 0.1);

        $this->assertLessThan(1.0, UserChallengeTag::where('tag', 'foco')->first()->weight);
    }

    public function test_consumo_parcial_nao_mexe_no_peso(): void
    {
        $this->tagDoAluno('autoestima', 0.7);
        $this->tagDoConteudo('12', 'autoestima');

        app(TagWeightService::class)->calibrarPorConsumo($this->aluno->id, 1, 'member_lesson', '12', 0.5);

        $this->assertSame(0.7, UserChallengeTag::where('tag', 'autoestima')->first()->weight);
    }

    public function test_peso_tem_piso_e_teto(): void
    {
        $this->tagDoAluno('procrastinacao', 0.18);
        $this->tagDoConteudo('13', 'procrastinacao');
        $servico = app(TagWeightService::class);

        // Vários abandonos seguidos não zeram uma tag que veio de teste clínico.
        for ($i = 0; $i < 10; $i++) {
            $servico->calibrarPorConsumo($this->aluno->id, 1, 'member_lesson', '13', 0.05);
        }
        $this->assertGreaterThanOrEqual(0.15, UserChallengeTag::where('tag', 'procrastinacao')->first()->weight);

        for ($i = 0; $i < 40; $i++) {
            $servico->calibrarPorConsumo($this->aluno->id, 1, 'member_lesson', '13', 1.0);
        }
        $this->assertLessThanOrEqual(1.0, UserChallengeTag::where('tag', 'procrastinacao')->first()->weight);
    }

    public function test_nao_cria_tag_que_o_aluno_nao_tinha(): void
    {
        // A tag do conteúdo não existe no perfil: assistir não deve inventá-la.
        $this->tagDoConteudo('14', 'tag-que-nao-e-do-aluno');

        app(TagWeightService::class)->calibrarPorConsumo($this->aluno->id, 1, 'member_lesson', '14', 1.0);

        $this->assertSame(0, UserChallengeTag::where('tag', 'tag-que-nao-e-do-aluno')->count());
    }

    public function test_calibragem_a_partir_do_endpoint(): void
    {
        $this->tagDoAluno('ansiedade', 0.5);
        $this->tagDoConteudo('20', 'ansiedade');

        $this->actingAs($this->aluno)
            ->postJson('/m/telemetria', [
                'event' => TelemetryService::LESSON_COMPLETE,
                'subject_type' => 'member_lesson',
                'subject_id' => '20',
                'position' => 290,
                'duration' => 300,
            ])
            ->assertStatus(202);

        $this->assertGreaterThan(0.5, UserChallengeTag::where('tag', 'ansiedade')->first()->weight);
    }

    public function test_pesos_do_usuario_vem_ordenados(): void
    {
        $this->tagDoAluno('baixa', 0.3);
        $this->tagDoAluno('alta', 0.9);

        $pesos = app(TagWeightService::class)->pesosDoUsuario($this->aluno->id);

        $this->assertSame(['alta', 'baixa'], array_keys($pesos));
    }

    public function test_concluir_aula_registra_evento_e_calibra(): void
    {
        $secao = \App\Models\MemberSection::create([
            'product_id' => $this->produto->id,
            'title' => 'Secao',
            'position' => 1,
        ]);
        $modulo = \App\Models\MemberModule::create([
            'product_id' => $this->produto->id,
            'member_section_id' => $secao->id,
            'title' => 'Modulo',
            'position' => 1,
            'is_free' => true,
        ]);
        $aula = \App\Models\MemberLesson::create([
            'product_id' => $this->produto->id,
            'member_module_id' => $modulo->id,
            'title' => 'Aula sobre ansiedade',
            'type' => 'text',
            'position' => 1,
        ]);

        $this->tagDoAluno('ansiedade', 0.5);
        $this->tagDoConteudo((string) $aula->id, 'ansiedade');

        $this->actingAs($this->aluno)
            ->post("/m/aula/{$aula->id}/complete")
            ->assertSuccessful();

        // O evento entrou em event_logs...
        $this->assertSame(1, EventLog::doEvento(TelemetryService::LESSON_COMPLETE)->count());

        // ...e o peso da tag subiu pelo consumo real.
        $this->assertGreaterThan(0.5, UserChallengeTag::where('tag', 'ansiedade')->first()->weight);
    }
}
