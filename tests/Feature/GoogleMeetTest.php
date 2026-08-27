<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureInstalled;
use App\Models\Appointment;
use App\Models\GoogleMeetCredential;
use App\Models\Professional;
use App\Models\User;
use App\Services\GoogleMeetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleMeetTest extends TestCase
{
    use RefreshDatabase;

    private User $profissional;

    private Professional $ficha;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(EnsureInstalled::class);

        config([
            'services.google_meet.client_id' => 'client-de-teste',
            'services.google_meet.client_secret' => 'segredo-de-teste',
            'services.google_meet.redirect' => 'https://spectra.test/p/meet/callback',
        ]);

        $this->profissional = User::factory()->create(['role' => User::ROLE_PROFISSIONAL, 'tenant_id' => 1]);
        $this->ficha = Professional::create([
            'tenant_id' => 1,
            'user_id' => $this->profissional->id,
            'name' => 'Dra. Teste',
            'email' => 'dra@test.com',
            'is_active' => true,
            'status' => 'approved',
        ]);
    }

    private function credencialConectada(?string $expiraEm = '+1 hour'): GoogleMeetCredential
    {
        $credencial = GoogleMeetCredential::create([
            'user_id' => $this->profissional->id,
            'tenant_id' => 1,
            'google_email' => 'dra@clinica.com',
        ]);
        $credencial->setTokens('token-valido', 'refresh-valido', 3600);

        if ($expiraEm === null) {
            $credencial->update(['expires_at' => now()->subHour()]);
        }

        return $credencial->fresh();
    }

    /** As rotas /m/ exigem area de membros: o paciente precisa ter o produto. */
    private function pacienteComAcesso(): User
    {
        $produto = $this->createTestProduct([
            'tenant_id' => 1,
            'type' => \App\Models\Product::TYPE_AREA_MEMBROS,
        ]);
        $paciente = User::factory()->create(['role' => User::ROLE_ALUNO, 'tenant_id' => 1]);
        $paciente->products()->attach($produto->id);

        return $paciente;
    }

    private function agendamento(): Appointment
    {
        $paciente = $this->pacienteComAcesso();

        return Appointment::create([
            'tenant_id' => 1,
            'professional_id' => $this->ficha->id,
            'user_id' => $paciente->id,
            'client_name' => 'Paciente Teste',
            'client_email' => 'paciente@test.com',
            'scheduled_date' => now()->addDay()->format('Y-m-d'),
            'scheduled_time' => '10:00',
            'status' => 'confirmed',
        ]);
    }

    // ---------- Tokens ----------

    public function test_tokens_ficam_cifrados_no_banco(): void
    {
        $credencial = $this->credencialConectada();

        $bruto = $credencial->getAttributes();
        $this->assertNotSame('token-valido', $bruto['access_token']);
        $this->assertNotSame('refresh-valido', $bruto['refresh_token']);

        $this->assertSame('token-valido', Crypt::decryptString($bruto['access_token']));
        $this->assertSame('token-valido', $credencial->accessTokenPlain());
    }

    public function test_reautorizacao_sem_refresh_token_nao_apaga_o_antigo(): void
    {
        $credencial = $this->credencialConectada();

        // O Google só devolve refresh_token no primeiro consentimento.
        $credencial->setTokens('novo-access', null, 3600);

        $this->assertSame('refresh-valido', $credencial->fresh()->refreshTokenPlain());
        $this->assertSame('novo-access', $credencial->fresh()->accessTokenPlain());
    }

    public function test_token_expirado_e_renovado_pelo_refresh(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'token-renovado',
                'expires_in' => 3600,
            ]),
        ]);

        $credencial = $this->credencialConectada(expiraEm: null);
        $this->assertTrue($credencial->expirou());

        $token = app(GoogleMeetService::class)->accessTokenValido($credencial);

        $this->assertSame('token-renovado', $token);
    }

    public function test_sem_refresh_token_pede_reconexao(): void
    {
        $credencial = GoogleMeetCredential::create([
            'user_id' => $this->profissional->id,
            'tenant_id' => 1,
            'expires_at' => now()->subHour(),
        ]);

        $this->assertNull(app(GoogleMeetService::class)->accessTokenValido($credencial));
        $this->assertStringContainsString('reconectar', $credencial->fresh()->last_error);
    }

    // ---------- OAuth ----------

    public function test_url_de_autorizacao_pede_acesso_offline(): void
    {
        $url = app(GoogleMeetService::class)->urlDeAutorizacao('estado-123');

        $this->assertStringContainsString('access_type=offline', $url);
        $this->assertStringContainsString('prompt=consent', $url);
        $this->assertStringContainsString('meetings.space.created', $url);
        $this->assertStringContainsString('state=estado-123', $url);
    }

    public function test_callback_recusa_state_que_nao_confere(): void
    {
        Http::fake();

        $this->actingAs($this->profissional)
            ->withSession(['google_meet_state' => 'o-certo'])
            ->get('/p/meet/callback?code=abc&state=o-errado')
            ->assertRedirect('/p/agenda')
            ->assertSessionHas('error');

        $this->assertSame(0, GoogleMeetCredential::count());
        Http::assertNothingSent();
    }

    public function test_callback_conecta_a_conta(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'token-novo',
                'refresh_token' => 'refresh-novo',
                'expires_in' => 3600,
            ]),
            'www.googleapis.com/oauth2/v2/userinfo' => Http::response(['email' => 'dra@clinica.com']),
        ]);

        $this->actingAs($this->profissional)
            ->withSession(['google_meet_state' => 'estado-ok'])
            ->get('/p/meet/callback?code=codigo-valido&state=estado-ok')
            ->assertRedirect('/p/agenda')
            ->assertSessionHas('success');

        $credencial = GoogleMeetCredential::where('user_id', $this->profissional->id)->first();
        $this->assertNotNull($credencial);
        $this->assertSame('dra@clinica.com', $credencial->google_email);
        $this->assertSame('refresh-novo', $credencial->refreshTokenPlain());
    }

    // ---------- Sala ----------

    public function test_cria_a_sala_do_agendamento(): void
    {
        Http::fake([
            'meet.googleapis.com/v2/spaces' => Http::response([
                'name' => 'spaces/abc123',
                'meetingUri' => 'https://meet.google.com/abc-defg-hij',
                'meetingCode' => 'abc-defg-hij',
            ]),
        ]);

        $this->credencialConectada();
        $appointment = $this->agendamento();

        $this->actingAs($this->profissional)
            ->postJson("/p/agenda/{$appointment->id}/sala")
            ->assertOk()
            ->assertJsonPath('meet_uri', 'https://meet.google.com/abc-defg-hij');

        $this->assertSame('spaces/abc123', $appointment->fresh()->meet_space_name);
    }

    public function test_nao_cria_sala_sem_conta_conectada(): void
    {
        Http::fake();
        $appointment = $this->agendamento();

        $this->actingAs($this->profissional)
            ->postJson("/p/agenda/{$appointment->id}/sala")
            ->assertStatus(422);

        $this->assertNull($appointment->fresh()->meet_uri);
    }

    public function test_criar_sala_e_idempotente(): void
    {
        Http::fake([
            'meet.googleapis.com/v2/spaces' => Http::response([
                'name' => 'spaces/abc123',
                'meetingUri' => 'https://meet.google.com/abc-defg-hij',
                'meetingCode' => 'abc-defg-hij',
            ]),
        ]);

        $this->credencialConectada();
        $appointment = $this->agendamento();

        $this->actingAs($this->profissional)->postJson("/p/agenda/{$appointment->id}/sala")->assertOk();
        $this->actingAs($this->profissional)->postJson("/p/agenda/{$appointment->id}/sala")->assertOk();

        // A segunda chamada devolve a sala existente em vez de criar outra.
        Http::assertSentCount(1);
    }

    public function test_profissional_nao_cria_sala_de_agenda_alheia(): void
    {
        Http::fake();
        $this->credencialConectada();
        $appointment = $this->agendamento();

        $outro = User::factory()->create(['role' => User::ROLE_PROFISSIONAL, 'tenant_id' => 1]);
        Professional::create([
            'tenant_id' => 1, 'user_id' => $outro->id, 'name' => 'Outro', 'email' => 'o@t.com',
            'is_active' => true, 'status' => 'approved',
        ]);

        $this->actingAs($outro)
            ->postJson("/p/agenda/{$appointment->id}/sala")
            ->assertStatus(404);
    }

    // ---------- Consentimento e transcrição ----------

    public function test_transcricao_exige_consentimento(): void
    {
        Http::fake();
        $this->credencialConectada();
        $appointment = $this->agendamento();
        $appointment->update(['meet_space_name' => 'spaces/abc123']);

        $this->actingAs($this->profissional)
            ->getJson("/p/agenda/{$appointment->id}/transcricao")
            ->assertStatus(422)
            ->assertJsonPath('status', 'sem_consentimento');

        Http::assertNothingSent();
    }

    public function test_paciente_autoriza_e_retira_a_gravacao(): void
    {
        $appointment = $this->agendamento();
        $paciente = User::find($appointment->user_id);

        $this->actingAs($paciente)
            ->postJson("/m/consultas/{$appointment->id}/consentimento", ['autoriza' => true])
            ->assertOk();
        $this->assertNotNull($appointment->fresh()->recording_consent_at);

        $this->actingAs($paciente)
            ->postJson("/m/consultas/{$appointment->id}/consentimento", ['autoriza' => false])
            ->assertOk();
        $this->assertNull($appointment->fresh()->recording_consent_at);
    }

    public function test_paciente_nao_autoriza_consulta_de_outra_pessoa(): void
    {
        $appointment = $this->agendamento();
        $estranho = $this->pacienteComAcesso();

        $this->actingAs($estranho)
            ->postJson("/m/consultas/{$appointment->id}/consentimento", ['autoriza' => true])
            ->assertStatus(404);

        $this->assertNull($appointment->fresh()->recording_consent_at);
    }

    public function test_avisa_quando_o_plano_nao_tem_transcricao(): void
    {
        Http::fake([
            'meet.googleapis.com/v2/conferenceRecords*' => Http::response([
                'conferenceRecords' => [['name' => 'conferenceRecords/xyz']],
            ]),
            'meet.googleapis.com/v2/conferenceRecords/xyz/transcripts' => Http::response(['transcripts' => []]),
        ]);

        $this->credencialConectada();
        $appointment = $this->agendamento();
        $appointment->update([
            'meet_space_name' => 'spaces/abc123',
            'recording_consent_at' => now(),
        ]);

        $this->actingAs($this->profissional)
            ->getJson("/p/agenda/{$appointment->id}/transcricao")
            ->assertStatus(422)
            ->assertJsonPath('status', 'sem_transcricao');
    }
}
