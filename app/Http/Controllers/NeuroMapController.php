<?php

namespace App\Http\Controllers;

use App\Models\NeuroArea;
use App\Models\NeuroIndicator;
use App\Models\NeuroUserScore;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class NeuroMapController extends Controller
{
    private function tenantId(): ?int
    {
        return auth()->user()->tenant_id;
    }

    // ─── Página principal ─────────────────────────────────────────────────────

    public function index(): Response
    {
        $tenantId = $this->tenantId();

        $areas = NeuroArea::forTenant($tenantId)
            ->with(['indicators' => fn ($q) => $q->orderBy('position')])
            ->orderBy('position')
            ->get()
            ->map(fn ($a) => [
                'id'          => $a->id,
                'name'        => $a->name,
                'description' => $a->description,
                'color'       => $a->color,
                'icon'        => $a->icon,
                'position'    => $a->position,
                'is_active'   => $a->is_active,
                'indicators'  => $a->indicators->map(fn ($i) => [
                    'id'          => $i->id,
                    'name'        => $i->name,
                    'description' => $i->description,
                    'scale_min'   => $i->scale_min,
                    'scale_max'   => $i->scale_max,
                    'position'    => $i->position,
                    'is_active'   => $i->is_active,
                ])->values(),
            ]);

        $stats = [
            'areas'      => NeuroArea::forTenant($tenantId)->count(),
            'indicators' => NeuroIndicator::whereHas(
                'area', fn ($q) => $q->where('tenant_id', $tenantId)
            )->count(),
            'scores'     => NeuroUserScore::where('tenant_id', $tenantId)->count(),
            'users'      => NeuroUserScore::where('tenant_id', $tenantId)
                ->distinct('user_id')->count('user_id'),
        ];

        // Todos os alunos do tenant (para dropdown do modal "Lançar score")
        $students = User::where('role', User::ROLE_ALUNO)
            ->whereHas('products', fn ($q) => $q->where('products.tenant_id', $tenantId))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        // Usuários com pelo menos 1 score (para filtro do analytics)
        $usersWithScores = User::whereHas(
            'neuroScores', fn ($q) => $q->where('tenant_id', $tenantId)
        )->orderBy('name')->get(['id', 'name', 'email']);

        return Inertia::render('NeuroMap/Index', [
            'areas'          => $areas,
            'stats'          => $stats,
            'students'       => $students,
            'usersWithScores'=> $usersWithScores,
        ]);
    }

    // ─── CRUD Áreas ──────────────────────────────────────────────────────────

    public function storeArea(Request $request): JsonResponse
    {
        $v = $request->validate([
            'name'        => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'color'       => ['nullable', 'string', 'max:10'],
            'icon'        => ['nullable', 'string', 'max:50'],
            'is_active'   => ['nullable', 'boolean'],
        ]);

        $maxPos = NeuroArea::forTenant($this->tenantId())->max('position') ?? -1;

        $area = NeuroArea::create([
            ...$v,
            'tenant_id' => $this->tenantId(),
            'color'     => $v['color'] ?? '#6366f1',
            'icon'      => $v['icon'] ?? 'Brain',
            'is_active' => $v['is_active'] ?? true,
            'position'  => $maxPos + 1,
        ]);

        return response()->json(['success' => true, 'area' => $this->formatArea($area)]);
    }

    public function updateArea(Request $request, NeuroArea $area): JsonResponse
    {
        abort_if($area->tenant_id !== $this->tenantId(), 403);
        $v = $request->validate([
            'name'        => ['sometimes', 'required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'color'       => ['nullable', 'string', 'max:10'],
            'icon'        => ['nullable', 'string', 'max:50'],
            'is_active'   => ['nullable', 'boolean'],
        ]);
        $area->update($v);
        return response()->json(['success' => true, 'area' => $this->formatArea($area->fresh()->load('indicators'))]);
    }

    public function destroyArea(NeuroArea $area): JsonResponse
    {
        abort_if($area->tenant_id !== $this->tenantId(), 403);
        $area->delete();
        return response()->json(['success' => true]);
    }

    private function formatArea(NeuroArea $a): array
    {
        return [
            'id'          => $a->id,
            'name'        => $a->name,
            'description' => $a->description,
            'color'       => $a->color,
            'icon'        => $a->icon,
            'position'    => $a->position,
            'is_active'   => $a->is_active,
            'indicators'  => ($a->relationLoaded('indicators') ? $a->indicators : collect())->map(fn ($i) => [
                'id'          => $i->id,
                'name'        => $i->name,
                'description' => $i->description,
                'scale_min'   => $i->scale_min,
                'scale_max'   => $i->scale_max,
                'position'    => $i->position,
                'is_active'   => $i->is_active,
            ])->values()->toArray(),
        ];
    }

    // ─── CRUD Indicadores ─────────────────────────────────────────────────────

    public function storeIndicator(Request $request, NeuroArea $area): JsonResponse
    {
        abort_if($area->tenant_id !== $this->tenantId(), 403);
        $v = $request->validate([
            'name'        => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'scale_min'   => ['nullable', 'integer', 'min:0', 'max:100'],
            'scale_max'   => ['nullable', 'integer', 'min:1', 'max:100'],
            'is_active'   => ['nullable', 'boolean'],
        ]);

        $maxPos = $area->indicators()->max('position') ?? -1;
        $ind = $area->indicators()->create([
            ...$v,
            'scale_min' => $v['scale_min'] ?? 0,
            'scale_max' => $v['scale_max'] ?? 10,
            'is_active' => $v['is_active'] ?? true,
            'position'  => $maxPos + 1,
        ]);

        return response()->json(['success' => true, 'indicator' => [
            'id' => $ind->id, 'name' => $ind->name, 'description' => $ind->description,
            'scale_min' => $ind->scale_min, 'scale_max' => $ind->scale_max,
            'position' => $ind->position, 'is_active' => $ind->is_active,
        ]]);
    }

    public function updateIndicator(Request $request, NeuroArea $area, NeuroIndicator $indicator): JsonResponse
    {
        abort_if($area->tenant_id !== $this->tenantId(), 403);
        abort_if($indicator->neuro_area_id !== $area->id, 404);
        $v = $request->validate([
            'name'        => ['sometimes', 'required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'scale_min'   => ['nullable', 'integer', 'min:0', 'max:100'],
            'scale_max'   => ['nullable', 'integer', 'min:1', 'max:100'],
            'is_active'   => ['nullable', 'boolean'],
        ]);
        $indicator->update($v);
        return response()->json(['success' => true, 'indicator' => [
            'id' => $indicator->id, 'name' => $indicator->name, 'description' => $indicator->description,
            'scale_min' => $indicator->scale_min, 'scale_max' => $indicator->scale_max,
            'position' => $indicator->position, 'is_active' => $indicator->is_active,
        ]]);
    }

    public function destroyIndicator(NeuroArea $area, NeuroIndicator $indicator): JsonResponse
    {
        abort_if($area->tenant_id !== $this->tenantId(), 403);
        abort_if($indicator->neuro_area_id !== $area->id, 404);
        $indicator->delete();
        return response()->json(['success' => true]);
    }

    // ─── Analytics ────────────────────────────────────────────────────────────

    public function analytics(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId();
        $userId   = $request->query('user_id'); // null = todos
        $period   = (int) ($request->query('period', 90)); // dias

        $since = now()->subDays($period)->toDateString();

        // ─── Radar: média por área ──────────────────────────────────────────
        $areas = NeuroArea::forTenant($tenantId)
            ->where('is_active', true)
            ->with(['indicators' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('position')
            ->get();

        $radarLabels = [];
        $radarData   = [];

        foreach ($areas as $area) {
            if ($area->indicators->isEmpty()) continue;

            $indicatorIds = $area->indicators->pluck('id');

            $query = NeuroUserScore::where('tenant_id', $tenantId)
                ->whereIn('neuro_indicator_id', $indicatorIds)
                ->where('scored_at', '>=', $since);

            if ($userId) $query->where('user_id', $userId);

            // Média dos scores normalizada 0-100 por área
            $scores = $query->get();
            if ($scores->isEmpty()) {
                $radarLabels[] = $area->name;
                $radarData[]   = 0;
                continue;
            }

            // Normalizar cada score para 0-100 baseado no scale_min/max do indicador
            $normalized = $scores->map(function ($s) use ($area) {
                $ind = $area->indicators->firstWhere('id', $s->neuro_indicator_id);
                if (!$ind) return null;
                $range = $ind->scale_max - $ind->scale_min;
                if ($range <= 0) return 0;
                return round(((float) $s->value - $ind->scale_min) / $range * 100, 1);
            })->filter()->values();

            $radarLabels[] = $area->name;
            $radarData[]   = $normalized->isEmpty() ? 0 : round($normalized->avg(), 1);
        }

        // ─── Linha: evolução ao longo do tempo por área ──────────────────────
        $timelineData = [];

        foreach ($areas as $area) {
            if ($area->indicators->isEmpty()) continue;

            $indicatorIds = $area->indicators->pluck('id');

            $query = NeuroUserScore::where('tenant_id', $tenantId)
                ->whereIn('neuro_indicator_id', $indicatorIds)
                ->where('scored_at', '>=', $since)
                ->select('scored_at', 'neuro_indicator_id', 'value', 'user_id');

            if ($userId) $query->where('user_id', $userId);

            $scores = $query->get();

            // Agrupa por data, normaliza e faz média
            $byDate = $scores->groupBy(fn ($s) => $s->scored_at->format('Y-m-d'));
            $series = [];
            foreach ($byDate->sortKeys() as $date => $dayScores) {
                $normalized = $dayScores->map(function ($s) use ($area) {
                    $ind = $area->indicators->firstWhere('id', $s->neuro_indicator_id);
                    if (!$ind) return null;
                    $range = $ind->scale_max - $ind->scale_min;
                    if ($range <= 0) return 0;
                    return round(((float) $s->value - $ind->scale_min) / $range * 100, 1);
                })->filter()->values();
                $series[] = ['x' => $date, 'y' => $normalized->isEmpty() ? 0 : round($normalized->avg(), 1)];
            }

            if (!empty($series)) {
                $timelineData[] = [
                    'name'  => $area->name,
                    'color' => $area->color,
                    'data'  => $series,
                ];
            }
        }

        // ─── Scores recentes por usuário ─────────────────────────────────────
        $recentUsers = [];
        if (!$userId) {
            $recentUsers = NeuroUserScore::where('tenant_id', $tenantId)
                ->where('scored_at', '>=', now()->subDays(30)->toDateString())
                ->with('user:id,name,email')
                ->select('user_id', DB::raw('COUNT(*) as score_count'), DB::raw('MAX(scored_at) as last_scored'))
                ->groupBy('user_id')
                ->orderByDesc('last_scored')
                ->limit(10)
                ->get()
                ->map(fn ($r) => [
                    'user_id'     => $r->user_id,
                    'name'        => $r->user?->name,
                    'email'       => $r->user?->email,
                    'score_count' => $r->score_count,
                    'last_scored' => $r->last_scored,
                ]);
        }

        return response()->json([
            'radar'       => ['labels' => $radarLabels, 'data' => $radarData],
            'timeline'    => $timelineData,
            'recentUsers' => $recentUsers,
        ]);
    }

    // ─── Scores manuais (admin lança score p/ usuário) ─────────────────────

    public function storeScore(Request $request): JsonResponse
    {
        $v = $request->validate([
            'user_id'             => ['required', 'integer', 'exists:users,id'],
            'neuro_indicator_id'  => ['required', 'integer', 'exists:neuro_indicators,id'],
            'value'               => ['required', 'numeric', 'min:0'],
            'notes'               => ['nullable', 'string', 'max:1000'],
            'scored_at'           => ['nullable', 'date'],
        ]);

        // Verifica que o indicador pertence ao tenant
        $ind = NeuroIndicator::findOrFail($v['neuro_indicator_id']);
        abort_if($ind->area->tenant_id !== $this->tenantId(), 403);
        abort_if((float) $v['value'] < $ind->scale_min || (float) $v['value'] > $ind->scale_max, 422,
            "Valor fora da escala ({$ind->scale_min}–{$ind->scale_max}).");

        NeuroUserScore::updateOrCreate(
            [
                'user_id'            => $v['user_id'],
                'neuro_indicator_id' => $v['neuro_indicator_id'],
                'scored_at'          => $v['scored_at'] ?? today()->toDateString(),
            ],
            [
                'tenant_id' => $this->tenantId(),
                'value'     => $v['value'],
                'notes'     => $v['notes'] ?? null,
            ]
        );

        return response()->json(['success' => true]);
    }
}
