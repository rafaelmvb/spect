<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureInstalled;
use App\Models\ContentTag;
use App\Models\MemberLesson;
use App\Models\MemberLessonProgress;
use App\Models\MemberModule;
use App\Models\MemberSection;
use App\Models\Product;
use App\Models\User;
use App\Models\UserChallengeTag;
use App\Services\RecommendationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecomendacaoTest extends TestCase
{
    use RefreshDatabase;

    private User $aluno;

    private Product $produto;

    private MemberModule $modulo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(EnsureInstalled::class);

        $this->produto = $this->createTestProduct(['tenant_id' => 1, 'type' => Product::TYPE_AREA_MEMBROS]);
        $this->aluno = User::factory()->create(['role' => User::ROLE_ALUNO, 'tenant_id' => 1]);
        $this->aluno->products()->attach($this->produto->id);

        $secao = MemberSection::create([
            'product_id' => $this->produto->id,
            'title' => 'Seção',
            'position' => 1,
        ]);
        $this->modulo = MemberModule::create([
            'product_id' => $this->produto->id,
            'member_section_id' => $secao->id,
            'title' => 'Módulo',
            'position' => 1,
            'is_free' => true,
        ]);
    }

    private function aula(string $titulo, int $posicao, ?string $tag = null): MemberLesson
    {
        $aula = MemberLesson::create([
            'product_id' => $this->produto->id,
            'member_module_id' => $this->modulo->id,
            'title' => $titulo,
            'type' => 'text',
            'position' => $posicao,
        ]);

        if ($tag) {
            ContentTag::create([
                'tenant_id' => 1,
                'taggable_type' => 'member_lesson',
                'taggable_id' => (string) $aula->id,
                'tag' => $tag,
                'dimension' => ContentTag::DIM_CATEGORIA,
            ]);
        }

        return $aula;
    }

    private function tagDoAluno(string $tag, float $peso): void
    {
        UserChallengeTag::create([
            'user_id' => $this->aluno->id,
            'tenant_id' => 1,
            'tag' => $tag,
            'weight' => $peso,
            'source_type' => 'clinical_test',
        ]);
    }

    private function ordenar()
    {
        return app(RecommendationService::class)->ordenarAulas(
            $this->aluno,
            MemberLesson::orderBy('position')->get()
        );
    }

    public function test_sem_tags_mantem_a_ordem_do_curso(): void
    {
        $this->aula('Primeira', 1);
        $this->aula('Segunda', 2);
        $this->aula('Terceira', 3);

        // Sem perfil não há o que personalizar: a sequência do autor vale.
        $ordem = $this->ordenar()->pluck('title')->all();

        $this->assertSame(['Primeira', 'Segunda', 'Terceira'], $ordem);
    }

    public function test_aula_do_tema_relevante_sobe(): void
    {
        $this->aula('Aula sobre sono', 1, 'sono');
        $this->aula('Aula sobre foco', 2, 'foco');
        $this->aula('Aula sobre ansiedade', 3, 'ansiedade');

        $this->tagDoAluno('ansiedade', 1.0);

        $this->assertSame('Aula sobre ansiedade', $this->ordenar()->first()->title);
    }

    public function test_peso_maior_vence_peso_menor(): void
    {
        $this->aula('Sobre foco', 1, 'foco');
        $this->aula('Sobre ansiedade', 2, 'ansiedade');

        // Ansiedade está em segundo no curso, mas pesa mais no perfil.
        $this->tagDoAluno('foco', 0.3);
        $this->tagDoAluno('ansiedade', 0.9);

        $this->assertSame('Sobre ansiedade', $this->ordenar()->first()->title);
    }

    public function test_calibragem_do_consumo_muda_a_ordem(): void
    {
        $foco = $this->aula('Sobre foco', 1, 'foco');
        $this->aula('Sobre ansiedade', 2, 'ansiedade');

        $this->tagDoAluno('foco', 1.0);
        $this->tagDoAluno('ansiedade', 0.9);

        $this->assertSame('Sobre foco', $this->ordenar()->first()->title);

        // O aluno abandona foco várias vezes: o peso cai e a trilha reage.
        $pesos = app(\App\Services\TagWeightService::class);
        for ($i = 0; $i < 3; $i++) {
            $pesos->calibrarPorConsumo($this->aluno->id, 1, 'member_lesson', (string) $foco->id, 0.05);
        }

        $this->assertSame('Sobre ansiedade', $this->ordenar()->first()->title);
    }

    public function test_aula_concluida_desce_mas_nao_some(): void
    {
        $concluida = $this->aula('Já vista', 1, 'ansiedade');
        $this->aula('Ainda não vista', 2, 'ansiedade');

        $this->tagDoAluno('ansiedade', 1.0);

        MemberLessonProgress::create([
            'user_id' => $this->aluno->id,
            'member_lesson_id' => $concluida->id,
            'product_id' => $this->produto->id,
            'progress_percent' => 100,
            'completed_at' => now(),
        ]);

        $ordem = $this->ordenar()->pluck('title')->all();

        $this->assertSame('Ainda não vista', $ordem[0]);
        $this->assertContains('Já vista', $ordem, 'Reassistir precisa continuar possível.');
    }

    public function test_aula_comecada_e_nao_terminada_ganha_impulso(): void
    {
        $this->aula('Nunca aberta', 1, 'ansiedade');
        $comecada = $this->aula('Parou no meio', 2, 'ansiedade');

        $this->tagDoAluno('ansiedade', 1.0);

        MemberLessonProgress::create([
            'user_id' => $this->aluno->id,
            'member_lesson_id' => $comecada->id,
            'product_id' => $this->produto->id,
            'progress_percent' => 45,
        ]);

        $this->assertSame('Parou no meio', $this->ordenar()->first()->title);
    }

    public function test_aula_sem_tag_nao_some_da_trilha(): void
    {
        $this->aula('Com tema', 1, 'ansiedade');
        $this->aula('Sem tema', 2);

        $this->tagDoAluno('ansiedade', 1.0);

        $this->assertCount(2, $this->ordenar());
    }

    public function test_temas_em_destaque_vem_ordenados(): void
    {
        $this->tagDoAluno('menor', 0.2);
        $this->tagDoAluno('maior', 0.95);
        $this->tagDoAluno('medio', 0.6);

        $temas = app(RecommendationService::class)->temasEmDestaque($this->aluno, 2);

        $this->assertCount(2, $temas);
        $this->assertSame('maior', $temas[0]['tag']);
        $this->assertSame('medio', $temas[1]['tag']);
    }
}
