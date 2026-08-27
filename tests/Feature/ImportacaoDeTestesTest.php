<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureInstalled;
use App\Models\ClinicalTest;
use App\Models\User;
use App\Services\ClinicalTestImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ImportacaoDeTestesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private ClinicalTestImportService $importador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(EnsureInstalled::class);

        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'tenant_id' => 1]);
        $this->importador = app(ClinicalTestImportService::class);
    }

    private function csv(string $conteudo): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('acervo.csv', $conteudo);
    }

    private function arquivoJson(array $dados): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('acervo.json', json_encode($dados));
    }

    // ---------- CSV ----------

    public function test_csv_agrupa_perguntas_do_mesmo_teste(): void
    {
        $csv = <<<'CSV'
        teste,categoria,pergunta,tipo,escala_min,escala_max
        Escala de Foco,atencao,Perco o foco facilmente,scale,1,5
        Escala de Foco,,Esqueço compromissos,scale,1,5
        Escala de Sono,sono,Demoro para dormir,scale,1,5
        CSV;

        $resultado = $this->importador->conferir($csv, 'csv');

        $this->assertTrue($resultado['ok'], implode(' | ', $resultado['erros']));
        $this->assertSame(2, $resultado['resumo']['testes']);
        $this->assertSame(3, $resultado['resumo']['questoes']);
        $this->assertCount(2, $resultado['testes'][0]['questoes']);
    }

    public function test_csv_aceita_ponto_e_virgula(): void
    {
        // Excel em português salva com ponto e vírgula.
        $csv = "teste;categoria;pergunta\nEscala;geral;Uma pergunta";

        $resultado = $this->importador->conferir($csv, 'csv');

        $this->assertTrue($resultado['ok'], implode(' | ', $resultado['erros']));
        $this->assertSame('Escala', $resultado['testes'][0]['nome']);
    }

    public function test_csv_le_opcoes_com_valor(): void
    {
        $csv = "teste,pergunta,tipo,opcoes\nEscala,Uma pergunta,single,\"Nunca=0|Às vezes=1|Sempre=2\"";

        $resultado = $this->importador->conferir($csv, 'csv');

        $opcoes = $resultado['testes'][0]['questoes'][0]['opcoes'];
        $this->assertCount(3, $opcoes);
        $this->assertSame('Às vezes', $opcoes[1]['texto']);
        $this->assertSame(2, $opcoes[2]['valor']);
    }

    public function test_csv_nao_duplica_a_faixa_repetida_em_cada_linha(): void
    {
        $csv = <<<'CSV'
        teste,pergunta,faixa_min,faixa_max,faixa_resultado
        Escala,Pergunta um,0,10,Sinais leves
        Escala,Pergunta dois,0,10,Sinais leves
        CSV;

        $resultado = $this->importador->conferir($csv, 'csv');

        $this->assertCount(1, $resultado['testes'][0]['faixas']);
    }

    public function test_csv_marca_rastreio_infantil(): void
    {
        $csv = "teste,pergunta,infantil\nRastreio,A criança dorme mal,sim";

        $resultado = $this->importador->conferir($csv, 'csv');

        $this->assertTrue($resultado['testes'][0]['infantil']);
    }

    public function test_csv_sem_coluna_obrigatoria_explica_o_que_falta(): void
    {
        $resultado = $this->importador->conferir("teste,categoria\nEscala,geral", 'csv');

        $this->assertFalse($resultado['ok']);
        $this->assertStringContainsString('pergunta', $resultado['erros'][0]);
    }

    // ---------- JSON ----------

    public function test_json_com_estrutura_completa(): void
    {
        $dados = [[
            'nome' => 'Escala Completa',
            'categoria' => 'ansiedade',
            'minutos' => 12,
            'questoes' => [
                ['texto' => 'Pergunta um', 'tipo' => 'scale', 'escala_min' => 1, 'escala_max' => 5],
                ['texto' => 'Pergunta dois', 'tipo' => 'single', 'opcoes' => [
                    ['texto' => 'Não', 'valor' => 0],
                    ['texto' => 'Sim', 'valor' => 1],
                ]],
            ],
            'faixas' => [
                ['min' => 0, 'max' => 5, 'resultado' => 'Leve', 'tags' => ['ansiedade']],
            ],
        ]];

        $resultado = $this->importador->conferir(json_encode($dados), 'json');

        $this->assertTrue($resultado['ok'], implode(' | ', $resultado['erros']));
        $this->assertSame(2, $resultado['resumo']['questoes']);
        $this->assertSame(1, $resultado['resumo']['faixas']);
    }

    public function test_json_invalido_avisa_em_vez_de_quebrar(): void
    {
        $resultado = $this->importador->conferir('{isso nao e json', 'json');

        $this->assertFalse($resultado['ok']);
        $this->assertStringContainsString('JSON válido', $resultado['erros'][0]);
    }

    // ---------- Validação ----------

    public function test_recusa_teste_sem_pergunta(): void
    {
        $resultado = $this->importador->conferir(json_encode([['nome' => 'Vazio']]), 'json');

        $this->assertFalse($resultado['ok']);
        $this->assertStringContainsString('não tem nenhuma pergunta', implode(' ', $resultado['erros']));
    }

    public function test_recusa_pergunta_de_escolha_sem_opcoes(): void
    {
        $dados = [['nome' => 'Escala', 'questoes' => [['texto' => 'Pergunta', 'tipo' => 'single']]]];

        $resultado = $this->importador->conferir(json_encode($dados), 'json');

        $this->assertFalse($resultado['ok']);
        $this->assertStringContainsString('não tem opções de resposta', implode(' ', $resultado['erros']));
    }

    public function test_recusa_escala_invertida(): void
    {
        $dados = [['nome' => 'Escala', 'questoes' => [
            ['texto' => 'Pergunta', 'tipo' => 'scale', 'escala_min' => 5, 'escala_max' => 1],
        ]]];

        $resultado = $this->importador->conferir(json_encode($dados), 'json');

        $this->assertFalse($resultado['ok']);
        $this->assertStringContainsString('precisa ser maior', implode(' ', $resultado['erros']));
    }

    public function test_erro_nomeia_o_teste_com_problema(): void
    {
        $dados = [
            ['nome' => 'Teste Bom', 'questoes' => [['texto' => 'ok']]],
            ['nome' => 'Teste Ruim', 'questoes' => []],
        ];

        $resultado = $this->importador->conferir(json_encode($dados), 'json');

        $this->assertStringContainsString('"Teste Ruim"', implode(' ', $resultado['erros']));
    }

    // ---------- Gravação ----------

    public function test_importa_e_grava_a_estrutura_completa(): void
    {
        $dados = [[
            'nome' => 'Escala Gravada',
            'categoria' => 'foco',
            'questoes' => [
                ['texto' => 'Pergunta um', 'tipo' => 'single', 'opcoes' => [
                    ['texto' => 'Não', 'valor' => 0],
                    ['texto' => 'Sim', 'valor' => 3],
                ]],
            ],
            'faixas' => [['min' => 0, 'max' => 3, 'resultado' => 'Leve', 'tags' => ['foco']]],
        ]];

        $this->actingAs($this->admin)
            ->postJson('/testes-clinicos/importar', ['arquivo' => $this->arquivoJson($dados)])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $teste = ClinicalTest::where('name', 'Escala Gravada')->first();
        $this->assertNotNull($teste);
        $this->assertSame(1, $teste->tenant_id);
        $this->assertCount(1, $teste->questions);
        $this->assertCount(2, $teste->questions->first()->options);
        $this->assertSame(3, $teste->questions->first()->options->last()->value);
        $this->assertCount(1, $teste->scoringRules);
        $this->assertSame(['foco'], $teste->scoringRules->first()->challenge_tags);
    }

    public function test_por_padrao_nao_sobrescreve_teste_existente(): void
    {
        ClinicalTest::create(['tenant_id' => 1, 'name' => 'Escala X', 'category' => 'original', 'is_active' => true]);

        $dados = [['nome' => 'Escala X', 'categoria' => 'nova', 'questoes' => [['texto' => 'p']]]];

        $this->actingAs($this->admin)
            ->postJson('/testes-clinicos/importar', ['arquivo' => $this->arquivoJson($dados)])
            ->assertOk();

        $this->assertSame('original', ClinicalTest::where('name', 'Escala X')->first()->category);
        $this->assertSame(1, ClinicalTest::where('name', 'Escala X')->count());
    }

    public function test_substituir_recria_a_estrutura(): void
    {
        $existente = ClinicalTest::create(['tenant_id' => 1, 'name' => 'Escala X', 'category' => 'original', 'is_active' => true]);
        $existente->questions()->create(['text' => 'Pergunta antiga', 'type' => 'scale', 'position' => 1]);

        $dados = [['nome' => 'Escala X', 'categoria' => 'nova', 'questoes' => [['texto' => 'Pergunta nova']]]];

        $this->actingAs($this->admin)
            ->postJson('/testes-clinicos/importar', ['arquivo' => $this->arquivoJson($dados), 'substituir' => true])
            ->assertOk();

        $teste = ClinicalTest::where('name', 'Escala X')->first();
        $this->assertSame('nova', $teste->category);
        $this->assertCount(1, $teste->questions);
        $this->assertSame('Pergunta nova', $teste->questions->first()->text);
    }

    public function test_arquivo_com_erro_nao_grava_nada(): void
    {
        $dados = [
            ['nome' => 'Bom', 'questoes' => [['texto' => 'ok']]],
            ['nome' => 'Ruim', 'questoes' => []],
        ];

        $this->actingAs($this->admin)
            ->postJson('/testes-clinicos/importar', ['arquivo' => $this->arquivoJson($dados)])
            ->assertStatus(422);

        // Nem o teste válido entra: ou o arquivo todo passa, ou nada.
        $this->assertSame(0, ClinicalTest::count());
    }

    public function test_conferir_nao_grava(): void
    {
        $dados = [['nome' => 'Só conferindo', 'questoes' => [['texto' => 'p']]]];

        $this->actingAs($this->admin)
            ->postJson('/testes-clinicos/importar/conferir', ['arquivo' => $this->arquivoJson($dados)])
            ->assertOk()
            ->assertJsonPath('resumo.testes', 1);

        $this->assertSame(0, ClinicalTest::count());
    }

    public function test_modelo_csv_e_valido_para_o_proprio_importador(): void
    {
        $modelo = $this->importador->modeloCsv();

        $resultado = $this->importador->conferir($modelo, 'csv');

        $this->assertTrue($resultado['ok'], 'O modelo oferecido não passa na própria validação: '.implode(' | ', $resultado['erros']));
        $this->assertSame(2, $resultado['resumo']['testes']);
    }

    public function test_recusa_arquivo_de_outro_tipo(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/testes-clinicos/importar', [
                'arquivo' => UploadedFile::fake()->create('planilha.xlsx', 100),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('arquivo');
    }

    public function test_aluno_nao_importa_testes(): void
    {
        $aluno = User::factory()->create(['role' => User::ROLE_ALUNO, 'tenant_id' => 1]);

        $this->actingAs($aluno)
            ->postJson('/testes-clinicos/importar', [
                'arquivo' => $this->arquivoJson([['nome' => 'X', 'questoes' => [['texto' => 'p']]]]),
            ])
            ->assertStatus(403);

        $this->assertSame(0, ClinicalTest::count());
    }
}
