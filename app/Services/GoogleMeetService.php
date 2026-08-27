<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\GoogleMeetCredential;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Teleconsulta pelo Google Meet.
 *
 * A sala é criada pela Meet REST API em nome do profissional, que conecta a
 * própria conta Google. A plataforma nunca guarda a senha dele — só os tokens
 * OAuth, cifrados.
 *
 * Limite conhecido: o Meet não pode ser embutido em iframe. A consulta abre em
 * meet.google.com, não dentro do painel.
 */
class GoogleMeetService
{
    private const OAUTH_AUTH = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const OAUTH_TOKEN = 'https://oauth2.googleapis.com/token';

    private const MEET_API = 'https://meet.googleapis.com/v2';

    private const USERINFO = 'https://www.googleapis.com/oauth2/v2/userinfo';

    /**
     * meetings.space.created — criar e ler os espaços criados pela aplicação.
     * meetings.space.readonly — ler o registro da conferência e a transcrição.
     *
     * @var list<string>
     */
    private const SCOPES = [
        'https://www.googleapis.com/auth/meetings.space.created',
        'https://www.googleapis.com/auth/meetings.space.readonly',
        'https://www.googleapis.com/auth/userinfo.email',
    ];

    public function configurado(): bool
    {
        return $this->clientId() !== '' && $this->clientSecret() !== '';
    }

    private function clientId(): string
    {
        return (string) config('services.google_meet.client_id', '');
    }

    private function clientSecret(): string
    {
        return (string) config('services.google_meet.client_secret', '');
    }

    private function redirectUri(): string
    {
        $configurado = (string) config('services.google_meet.redirect', '');

        return $configurado !== '' ? $configurado : url('/p/meet/callback');
    }

    // ---------------------------------------------------------------- OAuth

    public function urlDeAutorizacao(string $state): string
    {
        return self::OAUTH_AUTH.'?'.http_build_query([
            'client_id' => $this->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => implode(' ', self::SCOPES),
            // offline + consent garantem o refresh_token, que só vem no
            // primeiro consentimento de cada conta.
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ]);
    }

    /**
     * Troca o código do callback pelos tokens e guarda a credencial.
     */
    public function conectarConta(User $user, string $code): GoogleMeetCredential
    {
        $resposta = Http::asForm()->post(self::OAUTH_TOKEN, [
            'code' => $code,
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'redirect_uri' => $this->redirectUri(),
            'grant_type' => 'authorization_code',
        ]);

        if (! $resposta->successful()) {
            throw new RuntimeException('O Google recusou a autorização: '.$resposta->body());
        }

        $dados = $resposta->json();

        $credencial = GoogleMeetCredential::firstOrNew(['user_id' => $user->id]);
        $credencial->tenant_id = $user->tenant_id;
        $credencial->scopes = implode(' ', self::SCOPES);
        $credencial->save();

        $credencial->setTokens(
            $dados['access_token'] ?? '',
            $dados['refresh_token'] ?? null,
            isset($dados['expires_in']) ? (int) $dados['expires_in'] : null,
        );

        $credencial->google_email = $this->emailDaConta($credencial->accessTokenPlain());
        $credencial->save();

        return $credencial;
    }

    private function emailDaConta(?string $accessToken): ?string
    {
        if (! $accessToken) {
            return null;
        }

        $resposta = Http::withToken($accessToken)->get(self::USERINFO);

        return $resposta->successful() ? ($resposta->json('email') ?? null) : null;
    }

    /**
     * Token válido, renovando quando necessário.
     */
    public function accessTokenValido(GoogleMeetCredential $credencial): ?string
    {
        if (! $credencial->expirou()) {
            return $credencial->accessTokenPlain();
        }

        $refresh = $credencial->refreshTokenPlain();
        if (! $refresh) {
            $credencial->registrarFalha('Sem refresh token: o profissional precisa reconectar a conta Google.');

            return null;
        }

        $resposta = Http::asForm()->post(self::OAUTH_TOKEN, [
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'refresh_token' => $refresh,
            'grant_type' => 'refresh_token',
        ]);

        if (! $resposta->successful()) {
            $credencial->registrarFalha('Falha ao renovar o acesso: '.$resposta->status());

            return null;
        }

        $dados = $resposta->json();
        $credencial->setTokens(
            $dados['access_token'] ?? '',
            $dados['refresh_token'] ?? null,
            isset($dados['expires_in']) ? (int) $dados['expires_in'] : null,
        );

        return $credencial->accessTokenPlain();
    }

    // ----------------------------------------------------------------- Sala

    /**
     * Cria a sala do agendamento. Idempotente: se já existe, devolve a mesma.
     */
    public function criarSala(Appointment $appointment): ?Appointment
    {
        if ($appointment->meet_uri) {
            return $appointment;
        }

        $profissional = $appointment->professional?->user;
        if (! $profissional) {
            return null;
        }

        $credencial = GoogleMeetCredential::where('user_id', $profissional->id)->first();
        if (! $credencial) {
            return null;
        }

        $token = $this->accessTokenValido($credencial);
        if (! $token) {
            return null;
        }

        $resposta = Http::withToken($token)
            ->timeout(15)
            ->post(self::MEET_API.'/spaces', [
                'config' => [
                    // Só quem foi convidado entra direto; o resto bate na porta.
                    'accessType' => 'TRUSTED',
                    'entryPointAccess' => 'ALL',
                ],
            ]);

        if (! $resposta->successful()) {
            $credencial->registrarFalha('Falha ao criar a sala: '.$resposta->status().' '.$resposta->body());
            Log::warning('GoogleMeetService: nao foi possivel criar a sala.', [
                'appointment_id' => $appointment->id,
                'status' => $resposta->status(),
            ]);

            return null;
        }

        $espaco = $resposta->json();

        $appointment->update([
            'meet_space_name' => $espaco['name'] ?? null,
            'meet_uri' => $espaco['meetingUri'] ?? null,
            'meet_code' => $espaco['meetingCode'] ?? null,
            'meet_created_at' => now(),
        ]);

        return $appointment->fresh();
    }

    // ---------------------------------------------------------- Transcrição

    /**
     * Busca a transcrição da consulta.
     *
     * Só roda com consentimento registrado do paciente. Depende de o Workspace
     * do profissional ter transcrição habilitada — em plano sem o recurso, a
     * API responde sem transcrição alguma.
     *
     * @return array{status: string, message: string, entries?: list<array<string, mixed>>}
     */
    public function buscarTranscricao(Appointment $appointment): array
    {
        if (! $appointment->recording_consent_at) {
            return [
                'status' => 'sem_consentimento',
                'message' => 'O paciente não autorizou a gravação desta consulta.',
            ];
        }

        if (! $appointment->meet_space_name) {
            return ['status' => 'sem_sala', 'message' => 'Esta consulta não teve sala de vídeo.'];
        }

        $profissional = $appointment->professional?->user;
        $credencial = $profissional
            ? GoogleMeetCredential::where('user_id', $profissional->id)->first()
            : null;

        $token = $credencial ? $this->accessTokenValido($credencial) : null;
        if (! $token) {
            return ['status' => 'sem_conexao', 'message' => 'Reconecte a conta Google para buscar a transcrição.'];
        }

        $registros = Http::withToken($token)
            ->timeout(20)
            ->get(self::MEET_API.'/conferenceRecords', [
                'filter' => 'space.name = "'.$appointment->meet_space_name.'"',
            ]);

        if (! $registros->successful()) {
            return ['status' => 'erro', 'message' => 'O Google não respondeu: '.$registros->status()];
        }

        $conferencia = $registros->json('conferenceRecords.0.name');
        if (! $conferencia) {
            return ['status' => 'sem_registro', 'message' => 'Ainda não há registro desta conferência.'];
        }

        $transcricoes = Http::withToken($token)
            ->timeout(20)
            ->get(self::MEET_API.'/'.$conferencia.'/transcripts');

        $primeira = $transcricoes->successful() ? $transcricoes->json('transcripts.0.name') : null;
        if (! $primeira) {
            return [
                'status' => 'sem_transcricao',
                'message' => 'Nenhuma transcrição disponível. O plano do Google Workspace precisa ter transcrição habilitada e ela precisa ter sido ligada durante a consulta.',
            ];
        }

        $trechos = Http::withToken($token)
            ->timeout(30)
            ->get(self::MEET_API.'/'.$primeira.'/entries');

        if (! $trechos->successful()) {
            return ['status' => 'erro', 'message' => 'Falha ao ler a transcrição: '.$trechos->status()];
        }

        return [
            'status' => 'ok',
            'message' => 'Transcrição carregada.',
            'entries' => $trechos->json('transcriptEntries', []),
        ];
    }
}
