<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureInstalled;
use App\Models\User;
use App\Support\StorageVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ArquivoRestritoTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $arquivosReais = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(EnsureInstalled::class);
        Storage::fake('public');
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        foreach ($this->arquivosReais as $caminho) {
            @unlink($caminho);
            @rmdir(dirname($caminho));
        }
        $this->arquivosReais = [];

        parent::tearDown();
    }

    public function test_classificacao_de_visibilidade(): void
    {
        $this->assertTrue(StorageVisibility::isRestrito('member-area/abc/aula.pdf'));
        $this->assertTrue(StorageVisibility::isRestrito('music/1/faixa.mp3'));
        $this->assertTrue(StorageVisibility::isRestrito('community-stories/abc/x.mp4'));

        // Precisam continuar anônimos: checkout e a tela de login da área.
        $this->assertFalse(StorageVisibility::isRestrito('checkout/abc/capa.png'));
        $this->assertFalse(StorageVisibility::isRestrito('member-area-logos/logo.png'));
        $this->assertFalse(StorageVisibility::isRestrito('avatars/3/foto.png'));
        $this->assertFalse(StorageVisibility::isRestrito('products/capa.png'));
        $this->assertFalse(StorageVisibility::isRestrito('banners/1/b.png'));
    }

    public function test_extrai_o_dono_do_caminho(): void
    {
        $this->assertSame('produto-x', StorageVisibility::donoDoCaminho('member-area/produto-x/aula.pdf'));
        $this->assertSame('7', StorageVisibility::donoDoCaminho('music/7/faixa.mp3'));
        $this->assertNull(StorageVisibility::donoDoCaminho('checkout/abc/capa.png'));
    }

    public function test_visitante_anonimo_e_barrado(): void
    {
        Storage::disk('local')->put('member-area/p1/aula.pdf', 'conteudo pago');

        $this->get('/arquivo/member-area/p1/aula.pdf')->assertRedirect('/login');
    }

    public function test_aluno_sem_o_produto_recebe_403(): void
    {
        $produto = $this->createTestProduct(['tenant_id' => 1]);
        Storage::disk('local')->put("member-area/{$produto->id}/aula.pdf", 'conteudo pago');

        $estranho = User::factory()->create(['role' => User::ROLE_ALUNO, 'tenant_id' => 1]);

        $this->actingAs($estranho)
            ->get("/arquivo/member-area/{$produto->id}/aula.pdf")
            ->assertStatus(403);
    }

    public function test_aluno_com_o_produto_recebe_o_arquivo(): void
    {
        $produto = $this->createTestProduct(['tenant_id' => 1]);
        Storage::disk('local')->put("member-area/{$produto->id}/aula.pdf", 'conteudo pago');

        $aluno = User::factory()->create(['role' => User::ROLE_ALUNO, 'tenant_id' => 1]);
        $aluno->products()->attach($produto->id);

        $resposta = $this->actingAs($aluno)->get("/arquivo/member-area/{$produto->id}/aula.pdf");

        $resposta->assertOk();
        $this->assertSame('conteudo pago', $resposta->streamedContent());
    }

    public function test_admin_do_tenant_recebe_o_arquivo(): void
    {
        $produto = $this->createTestProduct(['tenant_id' => 1]);
        Storage::disk('local')->put("member-area/{$produto->id}/aula.pdf", 'conteudo pago');

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'tenant_id' => 1]);

        $this->actingAs($admin)
            ->get("/arquivo/member-area/{$produto->id}/aula.pdf")
            ->assertOk();
    }

    public function test_conteudo_por_tenant_nao_vaza_para_outro_tenant(): void
    {
        Storage::disk('local')->put('music/1/faixa.mp3', 'audio');

        $deOutroTenant = User::factory()->create(['role' => User::ROLE_ALUNO, 'tenant_id' => 2]);
        $this->actingAs($deOutroTenant)->get('/arquivo/music/1/faixa.mp3')->assertStatus(403);

        $doTenant = User::factory()->create(['role' => User::ROLE_ALUNO, 'tenant_id' => 1]);
        $this->actingAs($doTenant)->get('/arquivo/music/1/faixa.mp3')->assertOk();
    }

    public function test_anexo_clinico_so_para_usuario_do_painel(): void
    {
        Storage::disk('local')->put('clinical-test-ai-context/9/laudo.pdf', 'dado clinico');

        $aluno = User::factory()->create(['role' => User::ROLE_ALUNO, 'tenant_id' => 1]);
        $this->actingAs($aluno)->get('/arquivo/clinical-test-ai-context/9/laudo.pdf')->assertStatus(403);

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'tenant_id' => 1]);
        $this->actingAs($admin)->get('/arquivo/clinical-test-ai-context/9/laudo.pdf')->assertOk();
    }

    /**
     * StorageServeController lê storage_path('app/public') direto, então
     * Storage::fake não o alcança: estes dois escrevem no caminho real.
     *
     * @return string caminho absoluto criado
     */
    private function criarArquivoRealEmPublic(string $relativo, string $conteudo): string
    {
        $absoluto = storage_path('app/public/'.$relativo);
        @mkdir(dirname($absoluto), 0755, true);
        file_put_contents($absoluto, $conteudo);
        $this->arquivosReais[] = $absoluto;

        return $absoluto;
    }

    public function test_rota_publica_redireciona_conteudo_restrito(): void
    {
        // URL /storage/... gravada no banco antes da separacao: precisa cair na
        // rota autenticada em vez de entregar o arquivo.
        $this->criarArquivoRealEmPublic('member-area/p1-teste/aula.pdf', 'conteudo pago');

        $this->get('/storage/member-area/p1-teste/aula.pdf')
            ->assertRedirect('/arquivo/member-area/p1-teste/aula.pdf');
    }

    public function test_redirecionado_de_storage_ainda_precisa_de_sessao(): void
    {
        $this->criarArquivoRealEmPublic('member-area/p1-teste/aula.pdf', 'conteudo pago');

        $this->followingRedirects()
            ->get('/storage/member-area/p1-teste/aula.pdf')
            ->assertSee('login', false);
    }

    public function test_rota_publica_segue_servindo_o_que_e_publico(): void
    {
        $this->criarArquivoRealEmPublic('checkout/p1-teste/capa.png', 'imagem');

        // Sem sessão: o checkout depende disso.
        $resposta = $this->get('/storage/checkout/p1-teste/capa.png');

        $resposta->assertOk();
        // response()->file() devolve BinaryFileResponse, nao streamed.
        $this->assertSame('imagem', file_get_contents($resposta->baseResponse->getFile()->getPathname()));
    }

    public function test_arquivo_legado_em_public_ainda_e_servido_pela_rota_restrita(): void
    {
        $produto = $this->createTestProduct(['tenant_id' => 1]);
        // Ainda não passou por storage:mover-restritos.
        Storage::disk('public')->put("member-area/{$produto->id}/aula.pdf", 'conteudo legado');

        $aluno = User::factory()->create(['role' => User::ROLE_ALUNO, 'tenant_id' => 1]);
        $aluno->products()->attach($produto->id);

        $resposta = $this->actingAs($aluno)->get("/arquivo/member-area/{$produto->id}/aula.pdf");

        $resposta->assertOk();
        $this->assertSame('conteudo legado', $resposta->streamedContent());
    }

    public function test_caminho_publico_nao_e_servido_pela_rota_restrita(): void
    {
        Storage::disk('public')->put('checkout/p1/capa.png', 'imagem');

        $user = User::factory()->create(['role' => User::ROLE_ALUNO, 'tenant_id' => 1]);

        $this->actingAs($user)->get('/arquivo/checkout/p1/capa.png')->assertStatus(404);
    }

    public function test_path_traversal_e_barrado(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN, 'tenant_id' => 1]);

        $this->actingAs($user)
            ->get('/arquivo/member-area/..%2F..%2F.env')
            ->assertStatus(404);
    }

    public function test_comando_move_arquivos_para_o_disco_restrito(): void
    {
        Storage::disk('public')->put('member-area/p1/aula.pdf', 'conteudo pago');
        Storage::disk('public')->put('checkout/p1/capa.png', 'imagem');

        $this->artisan('storage:mover-restritos')->assertExitCode(0);

        Storage::disk('local')->assertExists('member-area/p1/aula.pdf');
        Storage::disk('public')->assertMissing('member-area/p1/aula.pdf');

        // Público não pode ser movido.
        Storage::disk('public')->assertExists('checkout/p1/capa.png');
    }

    public function test_comando_dry_run_nao_move_nada(): void
    {
        Storage::disk('public')->put('member-area/p1/aula.pdf', 'conteudo pago');

        $this->artisan('storage:mover-restritos', ['--dry-run' => true])->assertExitCode(0);

        Storage::disk('public')->assertExists('member-area/p1/aula.pdf');
        Storage::disk('local')->assertMissing('member-area/p1/aula.pdf');
    }
}
