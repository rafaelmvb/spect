<?php

namespace App\Services;

use App\Models\EventLog;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Registra comportamento de uso sem guardar quem é a pessoa.
 *
 * O elo com o usuário é `subject_hash`: um HMAC-SHA256 do id com a APP_KEY.
 * É determinístico, então dá para agregar tudo que uma mesma pessoa fez; e é
 * de mão única, então um dump da tabela não diz de quem é o comportamento.
 *
 * Escopo, Parte 02 § 5: "cliques, scroll, retenção e tempo de tela são
 * armazenados em tabelas de eventos com identificadores técnicos, sem ligação
 * direta com nome, e-mail ou CPF."
 */
class TelemetryService
{
    /** Player: pausou o conteúdo. */
    public const LESSON_PAUSE = 'lesson.pause';

    /** Player: saiu antes do fim. */
    public const LESSON_ABANDON = 'lesson.abandon';

    /** Player: retomou de onde parou. */
    public const LESSON_RESUME = 'lesson.resume';

    /** Player: chegou ao fim. */
    public const LESSON_COMPLETE = 'lesson.complete';

    /** Texto: profundidade de leitura em percentual. */
    public const CONTENT_SCROLL = 'content.scroll';

    /** Teste: interrompeu antes de concluir. */
    public const TEST_ABANDON = 'test.abandon';

    /** Busca na trilha. */
    public const SEARCH = 'content.search';

    /**
     * Eventos aceitos vindos do navegador. Fora desta lista, nada é gravado —
     * o endpoint é público para qualquer aluno autenticado.
     *
     * @var list<string>
     */
    public const EVENTOS_DO_CLIENTE = [
        self::LESSON_PAUSE,
        self::LESSON_ABANDON,
        self::LESSON_RESUME,
        self::LESSON_COMPLETE,
        self::CONTENT_SCROLL,
        self::TEST_ABANDON,
        self::SEARCH,
    ];

    /**
     * Identificador técnico da pessoa. Sem a APP_KEY não volta para o id.
     */
    public static function subjectHash(int $userId): string
    {
        return hash_hmac('sha256', 'subject:'.$userId, (string) config('app.key'));
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    public function registrar(User $user, string $evento, array $dados = []): ?EventLog
    {
        try {
            return EventLog::create([
                'subject_hash' => self::subjectHash((int) $user->id),
                'tenant_id' => $user->tenant_id,
                'event' => $evento,
                'subject_type' => $dados['subject_type'] ?? null,
                'subject_id' => isset($dados['subject_id']) ? (string) $dados['subject_id'] : null,
                'position' => isset($dados['position']) ? max(0, (int) $dados['position']) : null,
                'duration' => isset($dados['duration']) ? max(0, (int) $dados['duration']) : null,
                'value' => isset($dados['value']) ? (float) $dados['value'] : null,
                'context' => $this->sanitizarContexto($dados['context'] ?? null),
                'session_token' => $dados['session_token'] ?? null,
                'occurred_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Telemetria não pode derrubar a navegação de quem está estudando.
            Log::warning('TelemetryService: falha ao registrar evento.', [
                'event' => $evento,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * O contexto vem do navegador: aceita só escalares e chaves conhecidas,
     * para não virar porta de entrada de dado pessoal na tabela anônima.
     *
     * @param  mixed  $context
     * @return array<string, scalar>|null
     */
    private function sanitizarContexto(mixed $context): ?array
    {
        if (! is_array($context) || $context === []) {
            return null;
        }

        $permitidas = ['formato', 'origem', 'motivo', 'termo_hash', 'dispositivo', 'tag'];

        $limpo = [];
        foreach ($permitidas as $chave) {
            if (! array_key_exists($chave, $context)) {
                continue;
            }
            $valor = $context[$chave];
            if (is_scalar($valor)) {
                $limpo[$chave] = is_string($valor) ? mb_substr($valor, 0, 120) : $valor;
            }
        }

        return $limpo === [] ? null : $limpo;
    }
}
