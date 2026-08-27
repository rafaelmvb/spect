<?php

namespace App\Services;

use App\Models\ContentTag;
use App\Models\MemberLesson;
use App\Models\MemberLessonProgress;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Reordena a trilha pela relevância atual das tags do aluno.
 *
 * Escopo, Parte 02 § 4: "Continuar Trilha e os destaques da Aba Trilha mudam de
 * ordem conforme a relevância atual das tags."
 *
 * Fecha o ciclo que TagWeightService abriu: o teste atribui os pesos, o consumo
 * real os calibra, e aqui eles finalmente mudam o que o aluno vê primeiro.
 */
class RecommendationService
{
    /** Peso do casamento de tags contra os outros critérios. */
    private const PESO_RELEVANCIA = 10.0;

    /** Conteúdo já concluído desce, mas não some: reassistir é legítimo. */
    private const PENALIDADE_CONCLUIDO = -4.0;

    /** Empurrãozinho para quem já começou e parou no meio. */
    private const BONUS_EM_ANDAMENTO = 3.0;

    public function __construct(private readonly TagWeightService $pesos) {}

    /**
     * Ordena aulas para este aluno, da mais relevante para a menos.
     *
     * @param  Collection<int, MemberLesson>  $aulas
     * @return Collection<int, MemberLesson>
     */
    public function ordenarAulas(User $user, Collection $aulas): Collection
    {
        if ($aulas->isEmpty()) {
            return $aulas;
        }

        $pesosDoAluno = $this->pesos->pesosDoUsuario((int) $user->id);

        // Sem tags no perfil não há o que personalizar: mantém a ordem do curso,
        // que é a sequência que o autor pensou.
        if ($pesosDoAluno === []) {
            return $aulas;
        }

        $tagsPorAula = $this->tagsPorAula($aulas->pluck('id')->all());
        $progresso = $this->progressoPorAula($user, $aulas->pluck('id')->all());

        return $aulas
            ->map(function (MemberLesson $aula) use ($pesosDoAluno, $tagsPorAula, $progresso) {
                $aula->relevancia = $this->pontuar($aula, $pesosDoAluno, $tagsPorAula, $progresso);

                return $aula;
            })
            ->sortByDesc('relevancia')
            ->values();
    }

    /**
     * As tags mais fortes do aluno agora, para explicar a ordem na interface.
     *
     * @return list<array{tag: string, peso: float}>
     */
    public function temasEmDestaque(User $user, int $quantos = 3): array
    {
        return collect($this->pesos->pesosDoUsuario((int) $user->id))
            ->take($quantos)
            ->map(fn (float $peso, string $tag) => ['tag' => $tag, 'peso' => round($peso, 2)])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, float>  $pesosDoAluno
     * @param  array<int, list<string>>  $tagsPorAula
     * @param  array<int, array{concluida: bool, percentual: int}>  $progresso
     */
    private function pontuar(MemberLesson $aula, array $pesosDoAluno, array $tagsPorAula, array $progresso): float
    {
        $nota = 0.0;

        // Casamento de tema: soma o peso de cada tag em comum.
        foreach ($tagsPorAula[$aula->id] ?? [] as $tag) {
            $nota += ($pesosDoAluno[$tag] ?? 0.0) * self::PESO_RELEVANCIA;
        }

        $estado = $progresso[$aula->id] ?? null;
        if ($estado !== null) {
            if ($estado['concluida']) {
                $nota += self::PENALIDADE_CONCLUIDO;
            } elseif ($estado['percentual'] > 0) {
                $nota += self::BONUS_EM_ANDAMENTO;
            }
        }

        // Desempate pela ordem do curso: sem isso, aulas sem tag embaralham.
        $nota -= ((int) $aula->position) * 0.01;

        return $nota;
    }

    /**
     * @param  list<int>  $aulaIds
     * @return array<int, list<string>>
     */
    private function tagsPorAula(array $aulaIds): array
    {
        return ContentTag::where('taggable_type', 'member_lesson')
            ->whereIn('taggable_id', array_map('strval', $aulaIds))
            ->daDimensao(ContentTag::DIM_CATEGORIA)
            ->get()
            ->groupBy('taggable_id')
            ->map(fn ($linhas) => $linhas->pluck('tag')->all())
            ->mapWithKeys(fn ($tags, $id) => [(int) $id => $tags])
            ->all();
    }

    /**
     * @param  list<int>  $aulaIds
     * @return array<int, array{concluida: bool, percentual: int}>
     */
    private function progressoPorAula(User $user, array $aulaIds): array
    {
        return MemberLessonProgress::where('user_id', $user->id)
            ->whereIn('member_lesson_id', $aulaIds)
            ->get()
            ->mapWithKeys(fn (MemberLessonProgress $p) => [
                (int) $p->member_lesson_id => [
                    'concluida' => $p->completed_at !== null,
                    'percentual' => (int) ($p->progress_percent ?? 0),
                ],
            ])
            ->all();
    }
}
