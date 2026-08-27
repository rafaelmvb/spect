<?php

namespace App\Services;

use App\Models\ContentTag;
use App\Models\UserChallengeTag;
use Illuminate\Support\Facades\DB;

/**
 * Calibra o peso das tags do aluno pelo consumo real.
 *
 * Escopo, Parte 02 § 4: "se o resultado indica interesse em Foco, mas o usuário
 * abandona esses conteúdos e consome Ansiedade até o fim, o peso das tags é
 * reajustado."
 *
 * O teste atribui peso máximo. A partir daí o comportamento manda: concluir
 * sobe, abandonar cedo desce. O ajuste é pequeno de propósito — um abandono não
 * apaga um resultado clínico, mas uma sequência deles muda a ordem da trilha.
 */
class TagWeightService
{
    /** Quanto uma conclusão soma. */
    private const REFORCO = 0.06;

    /** Quanto um abandono precoce subtrai. */
    private const PENALIDADE = 0.10;

    /** Piso: a tag perde prioridade mas não some — veio de um teste. */
    private const PESO_MINIMO = 0.15;

    private const PESO_MAXIMO = 1.0;

    /**
     * Fração do conteúdo abaixo da qual a saída conta como abandono.
     * O escopo trata 70% como consumo efetivo (Parte 01, player).
     */
    private const LIMIAR_ABANDONO = 0.30;

    /**
     * Ajusta as tags do aluno após ele consumir um conteúdo.
     *
     * @param  float  $progresso  0..1 do conteúdo consumido
     */
    public function calibrarPorConsumo(int $userId, ?int $tenantId, string $tipoConteudo, string $conteudoId, float $progresso): void
    {
        $tagsDoConteudo = ContentTag::doConteudo($tipoConteudo, $conteudoId)
            ->daDimensao(ContentTag::DIM_CATEGORIA)
            ->pluck('tag')
            ->all();

        if ($tagsDoConteudo === []) {
            return;
        }

        $delta = $this->deltaPara($progresso);
        if ($delta === 0.0) {
            return;
        }

        DB::transaction(function () use ($userId, $tenantId, $tagsDoConteudo, $delta) {
            foreach ($tagsDoConteudo as $tag) {
                $registro = UserChallengeTag::where('user_id', $userId)
                    ->where('tag', $tag)
                    ->first();

                // Só calibra tag que a pessoa já tem: o peso nasce de um teste,
                // não de ter assistido a um vídeo uma vez.
                if (! $registro) {
                    continue;
                }

                $novo = max(self::PESO_MINIMO, min(self::PESO_MAXIMO, (float) $registro->weight + $delta));

                $registro->update([
                    'weight' => round($novo, 3),
                    'weight_updated_at' => now(),
                ]);
            }
        });
    }

    /**
     * Conclusão reforça; saída antes do limiar penaliza; o meio não diz nada.
     */
    private function deltaPara(float $progresso): float
    {
        if ($progresso >= 0.7) {
            return self::REFORCO;
        }

        if ($progresso <= self::LIMIAR_ABANDONO) {
            return -self::PENALIDADE;
        }

        return 0.0;
    }

    /**
     * Tags do aluno em ordem de relevância atual — é o que a trilha usa para
     * decidir o que destacar.
     *
     * @return array<string, float>
     */
    public function pesosDoUsuario(int $userId): array
    {
        return UserChallengeTag::where('user_id', $userId)
            ->orderByDesc('weight')
            ->pluck('weight', 'tag')
            ->map(fn ($p) => (float) $p)
            ->all();
    }
}
