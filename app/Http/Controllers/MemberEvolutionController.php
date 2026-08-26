<?php

namespace App\Http\Controllers;

use App\Models\CheckpointResponse;
use App\Models\JourneyStepProgress;
use App\Models\MemberActivityLog;
use App\Models\MemberLessonProgress;
use App\Models\NeuroUserScore;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MemberEvolutionController extends Controller
{
    public function show(Request $request, string $slug): Response
    {
        $user      = auth()->user();
        $product   = $request->attributes->get('member_area_product');
        $productId = $product?->id ?? session('member_area_product_id');

        // Scores NeuroMap por área ao longo do tempo (últimos 6 meses)
        $neuroScores = NeuroUserScore::where('user_id', $user->id)
            ->with('area:id,name,color')
            ->where('assessed_at', '>=', now()->subMonths(6))
            ->orderBy('assessed_at')
            ->get()
            ->groupBy('area_id')
            ->map(fn ($scores, $areaId) => [
                'area'   => $scores->first()->area?->name ?? 'Área',
                'color'  => $scores->first()->area?->color ?? '#7c3aed',
                'points' => $scores->map(fn ($s) => [
                    'date'  => $s->assessed_at->format('Y-m-d'),
                    'score' => (float) $s->score,
                ])->values(),
            ])->values();

        // Último score por área
        $latestScores = NeuroUserScore::where('user_id', $user->id)
            ->with('area:id,name,color')
            ->orderByDesc('assessed_at')
            ->get()
            ->groupBy('area_id')
            ->map(fn ($g) => [
                'area'  => $g->first()->area?->name,
                'color' => $g->first()->area?->color ?? '#7c3aed',
                'score' => (float) $g->first()->score,
                'date'  => $g->first()->assessed_at->format('d/m/Y'),
            ])->values();

        // Progresso em jornadas
        $journeyProgress = JourneyStepProgress::where('user_id', $user->id)
            ->selectRaw('journey_id, COUNT(*) as completed_steps')
            ->groupBy('journey_id')
            ->with('journey:id,name')
            ->get()
            ->map(fn ($r) => [
                'journey' => $r->journey?->name ?? 'Jornada',
                'steps'   => (int) $r->completed_steps,
            ]);

        // Checkpoints respondidos por mês
        $checkpointsByMonth = CheckpointResponse::where('user_id', $user->id)
            ->where('completed_at', '>=', now()->subMonths(6))
            ->selectRaw("DATE_FORMAT(completed_at, '%Y-%m') as month, COUNT(*) as total")
            ->groupByRaw("DATE_FORMAT(completed_at, '%Y-%m')")
            ->orderBy('month')
            ->get()
            ->map(fn ($r) => ['month' => $r->month, 'total' => (int) $r->total]);

        // Aulas concluídas por semana (últimas 12 semanas)
        $lessonsByWeek = MemberLessonProgress::where('user_id', $user->id)
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', now()->subWeeks(12))
            ->selectRaw("YEARWEEK(completed_at, 1) as week, COUNT(*) as total")
            ->groupBy('week')
            ->orderBy('week')
            ->get()
            ->map(fn ($r) => ['week' => (string) $r->week, 'total' => (int) $r->total]);

        // Atividade por dia (heatmap — últimos 90 dias)
        $activityHeatmap = MemberActivityLog::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subDays(90))
            ->selectRaw("DATE(created_at) as day, COUNT(*) as total")
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->map(fn ($r) => ['day' => $r->day, 'total' => (int) $r->total]);

        // Totais gerais
        $stats = [
            'lessons_completed'    => MemberLessonProgress::where('user_id', $user->id)->whereNotNull('completed_at')->count(),
            'journeys_active'      => JourneyStepProgress::where('user_id', $user->id)->distinct('journey_id')->count('journey_id'),
            'checkpoints_done'     => CheckpointResponse::where('user_id', $user->id)->count(),
            'days_active'          => MemberActivityLog::where('user_id', $user->id)->selectRaw('COUNT(DISTINCT DATE(created_at)) as days')->value('days') ?? 0,
            'avg_neuro_score'      => $latestScores->avg('score') ? round($latestScores->avg('score'), 1) : null,
        ];

        return Inertia::render('MemberAreaApp/MinhaEvolucao', [
            'product'            => ['id' => $product?->id, 'name' => $product?->name ?? ''],
            'config'             => $product?->member_area_config ?? [],
            'slug'               => $slug,
            'neuro_scores'       => $neuroScores,
            'latest_scores'      => $latestScores,
            'journey_progress'   => $journeyProgress,
            'checkpoints_month'  => $checkpointsByMonth,
            'lessons_week'       => $lessonsByWeek,
            'activity_heatmap'   => $activityHeatmap,
            'stats'              => $stats,
        ]);
    }
}
