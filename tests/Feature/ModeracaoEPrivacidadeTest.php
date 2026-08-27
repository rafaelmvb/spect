<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureInstalled;
use App\Models\ChildProfile;
use App\Models\CommunityBan;
use App\Models\DailyMoodCheckin;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ModeracaoEPrivacidadeTest extends TestCase
{
    use RefreshDatabase;

    private User $aluno;

    private Product $produto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(EnsureInstalled::class);

        $this->produto = $this->createTestProduct(['tenant_id' => 1, 'type' => Product::TYPE_AREA_MEMBROS]);
        $this->aluno = User::factory()->create([
            'role' => User::ROLE_ALUNO,
            'tenant_id' => 1,
            'password' => Hash::make('senha-do-aluno'),
        ]);
        $this->aluno->products()->attach($this->produto->id);
    }

    private function restricao(string $kind, ?string $expiraEm): CommunityBan
    {
        return CommunityBan::create([
            'tenant_id' => 1,
            'user_id' => $this->aluno->id,
            'product_id' => $this->produto->id,
            'reason' => 'teste',
            'kind' => $kind,
            'expires_at' => $expiraEm ? now()->modify($expiraEm) : null,
            'banned_by' => 1,
        ]);
    }

    // ---------- Moderação graduada ----------

    public function test_banimento_permanente_continua_bloqueando(): void
    {
        $this->restricao(CommunityBan::KIND_BAN, null);

        $this->assertTrue(CommunityBan::isBanned(1, $this->aluno->id, $this->produto->id));
    }

    public function test_suspensao_vigente_bloqueia(): void
    {
        $this->restricao(CommunityBan::KIND_SUSPENSION, '+7 days');

        $this->assertTrue(CommunityBan::isBanned(1, $this->aluno->id, $this->produto->id));
    }

    public function test_suspensao_vencida_deixa_de_bloquear_sozinha(): void
    {
        $this->restricao(CommunityBan::KIND_SUSPENSION, '-1 day');

        // Não precisa rodar rotina nenhuma para devolver o acesso.
        $this->assertFalse(CommunityBan::isBanned(1, $this->aluno->id, $this->produto->id));
    }

    public function test_advertencia_nao_impede_publicar(): void
    {
        $this->restricao(CommunityBan::KIND_WARNING, null);

        $this->assertFalse(CommunityBan::isBanned(1, $this->aluno->id, $this->produto->id));
    }

    public function test_descricao_da_suspensao_diz_ate_quando(): void
    {
        // Uma restrição por pessoa/produto: a tabela tem unique nesse trio.
        $suspensao = $this->restricao(CommunityBan::KIND_SUSPENSION, '+30 days');

        $this->assertStringContainsString('Suspenso até', $suspensao->descricao());
    }

    public function test_descricao_da_advertencia(): void
    {
        $this->assertSame('Advertência', $this->restricao(CommunityBan::KIND_WARNING, null)->descricao());
    }

    public function test_descricao_do_banimento(): void
    {
        $this->assertSame('Banido', $this->restricao(CommunityBan::KIND_BAN, null)->descricao());
    }

    // ---------- Exportação ----------

    public function test_aluno_exporta_os_proprios_dados(): void
    {
        DailyMoodCheckin::create([
            'user_id' => $this->aluno->id,
            'product_id' => $this->produto->id,
            'mood' => 'bem',
            'checkin_date' => now()->format('Y-m-d'),
        ]);

        $resposta = $this->actingAs($this->aluno)->get('/m/privacidade/exportar');
        $resposta->assertOk();

        $conteudo = json_decode($resposta->streamedContent(), true);

        $this->assertSame($this->aluno->email, $conteudo['conta']['email']);
        $this->assertCount(1, $conteudo['humor']);
        $this->assertArrayHasKey('testes_respondidos', $conteudo);
        $this->assertArrayHasKey('profissionais_autorizados', $conteudo);
    }

    public function test_exportacao_nao_traz_dado_de_outra_pessoa(): void
    {
        $outro = User::factory()->create(['role' => User::ROLE_ALUNO, 'tenant_id' => 1]);
        DailyMoodCheckin::create([
            'user_id' => $outro->id,
            'product_id' => $this->produto->id,
            'mood' => 'humor-de-outra-pessoa',
            'checkin_date' => now()->format('Y-m-d'),
        ]);

        $resposta = $this->actingAs($this->aluno)->get('/m/privacidade/exportar');

        $this->assertStringNotContainsString('humor-de-outra-pessoa', $resposta->streamedContent());
    }

    // ---------- Exclusão ----------

    public function test_exclusao_exige_senha_correta(): void
    {
        $this->actingAs($this->aluno)
            ->deleteJson('/m/privacidade/conta', ['senha' => 'errada', 'confirmacao' => 'EXCLUIR'])
            ->assertStatus(422);

        $this->assertNotNull(User::find($this->aluno->id));
    }

    public function test_exclusao_exige_a_palavra_de_confirmacao(): void
    {
        $this->actingAs($this->aluno)
            ->deleteJson('/m/privacidade/conta', ['senha' => 'senha-do-aluno', 'confirmacao' => 'sim'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('confirmacao');

        $this->assertNotNull(User::find($this->aluno->id));
    }

    public function test_exclusao_apaga_conta_e_historico(): void
    {
        DailyMoodCheckin::create([
            'user_id' => $this->aluno->id,
            'product_id' => $this->produto->id,
            'mood' => 'bem',
            'checkin_date' => now()->format('Y-m-d'),
        ]);
        ChildProfile::create([
            'guardian_user_id' => $this->aluno->id,
            'tenant_id' => 1,
            'name' => 'Filho',
            'relationship' => 'mae',
        ]);

        $id = $this->aluno->id;

        $this->actingAs($this->aluno)
            ->deleteJson('/m/privacidade/conta', ['senha' => 'senha-do-aluno', 'confirmacao' => 'EXCLUIR'])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertNull(User::find($id));
        $this->assertSame(0, DailyMoodCheckin::where('user_id', $id)->count());
        $this->assertSame(0, ChildProfile::where('guardian_user_id', $id)->count());
    }

    public function test_conta_com_acesso_ao_painel_nao_se_exclui_por_aqui(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'tenant_id' => 1,
            'password' => Hash::make('senha-admin'),
        ]);
        $admin->products()->attach($this->produto->id);

        $this->actingAs($admin)
            ->deleteJson('/m/privacidade/conta', ['senha' => 'senha-admin', 'confirmacao' => 'EXCLUIR'])
            ->assertStatus(422);

        $this->assertNotNull(User::find($admin->id));
    }
}
