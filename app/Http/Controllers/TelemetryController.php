<?php

namespace App\Http\Controllers;

use App\Services\TagWeightService;
use App\Services\TelemetryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Recebe eventos de comportamento do navegador.
 *
 * Aceita só os eventos da allowlist e grava sob identificador técnico — o corpo
 * da requisição nunca vira dado cadastral em event_logs.
 */
class TelemetryController extends Controller
{
    public function __construct(
        private readonly TelemetryService $telemetria,
        private readonly TagWeightService $pesos,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validado = $request->validate([
            'event' => ['required', 'string', 'in:'.implode(',', TelemetryService::EVENTOS_DO_CLIENTE)],
            'subject_type' => ['nullable', 'string', 'max:40'],
            'subject_id' => ['nullable', 'string', 'max:64'],
            'position' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'duration' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'value' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'session_token' => ['nullable', 'string', 'size:32', 'alpha_num'],
            'context' => ['nullable', 'array'],
        ]);

        $user = $request->user();

        $this->telemetria->registrar($user, $validado['event'], $validado);

        // Sair de um conteúdo é o momento em que dá para dizer se ele engajou.
        $encerraConsumo = in_array($validado['event'], [
            TelemetryService::LESSON_ABANDON,
            TelemetryService::LESSON_COMPLETE,
        ], true);

        if ($encerraConsumo && ! empty($validado['subject_type']) && ! empty($validado['subject_id'])) {
            $this->pesos->calibrarPorConsumo(
                (int) $user->id,
                $user->tenant_id,
                $validado['subject_type'],
                (string) $validado['subject_id'],
                $this->progressoDe($validado),
            );
        }

        // 202: o cliente dispara e segue; nada aqui deve segurar a navegação.
        return response()->json(['ok' => true], 202);
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    private function progressoDe(array $dados): float
    {
        if (isset($dados['value'])) {
            return max(0.0, min(1.0, ((float) $dados['value']) / 100));
        }

        $duracao = (int) ($dados['duration'] ?? 0);
        if ($duracao <= 0) {
            return 0.0;
        }

        return max(0.0, min(1.0, ((int) ($dados['position'] ?? 0)) / $duracao));
    }
}
