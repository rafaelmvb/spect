<?php

namespace App\Http\Controllers;

use App\Models\NeuroArea;
use App\Models\NeuroUserScore;
use App\Services\MemberAreaResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MemberNeuroMapController extends Controller
{
    public function __construct(protected MemberAreaResolver $resolver) {}

    private function getProduct(Request $request)
    {
        return $request->attributes->get('member_area_product');
    }

    public function show(Request $request, string $slug): Response
    {
        $product  = $this->getProduct($request);
        $user     = $request->user();
        $tenantId = $product->tenant_id;

        $areas = NeuroArea::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->with(['indicators' => fn ($q) => $q->where('is_active', true)->orderBy('position')])
            ->orderBy('position')
            ->get();

        // Último score por indicador
        $latestScores = NeuroUserScore::where('user_id', $user->id)
            ->where('tenant_id', $tenantId)
            ->orderByDesc('scored_at')
            ->get()
            ->unique('neuro_indicator_id')
            ->keyBy('neuro_indicator_id');

        // Histórico (últimos 90 dias, agrupado por data)
        $history = NeuroUserScore::where('user_id', $user->id)
            ->where('tenant_id', $tenantId)
            ->where('scored_at', '>=', now()->subDays(90))
            ->orderBy('scored_at')
            ->get();

        $areasData = $areas->map(function (NeuroArea $area) use ($latestScores) {
            $indicators = $area->indicators->map(function ($ind) use ($latestScores) {
                $score = $latestScores->get($ind->id);
                $normalized = null;
                if ($score) {
                    $range = $ind->scale_max - $ind->scale_min;
                    $normalized = $range > 0
                        ? round(($score->value - $ind->scale_min) / $range * 100, 1)
                        : 50;
                }
                return [
                    'id'          => $ind->id,
                    'name'        => $ind->name,
                    'description' => $ind->description,
                    'scale_min'   => $ind->scale_min,
                    'scale_max'   => $ind->scale_max,
                    'value'       => $score?->value,
                    'normalized'  => $normalized,
                    'scored_at'   => $score?->scored_at?->format('d/m/Y'),
                    'notes'       => $score?->notes,
                ];
            })->values()->all();

            // Média normalizada da área
            $vals = collect($indicators)->pluck('normalized')->filter()->values();
            $areaAvg = $vals->count() > 0 ? round($vals->avg(), 1) : null;

            return [
                'id'          => $area->id,
                'name'        => $area->name,
                'description' => $area->description,
                'color'       => $area->color,
                'icon'        => $area->icon,
                'avg'         => $areaAvg,
                'indicators'  => $indicators,
            ];
        })->values()->all();

        // Timeline: média por área por data
        $timeline = [];
        foreach ($history->groupBy(fn ($s) => $s->scored_at->format('Y-m-d')) as $date => $scores) {
            $byArea = [];
            foreach ($areas as $area) {
                $areaIndicatorIds = $area->indicators->pluck('id')->all();
                $areaScores = $scores->whereIn('neuro_indicator_id', $areaIndicatorIds);
                if ($areaScores->isEmpty()) continue;
                $normalized = $areaScores->map(function ($s) use ($area) {
                    $ind = $area->indicators->firstWhere('id', $s->neuro_indicator_id);
                    if (! $ind) return null;
                    $range = $ind->scale_max - $ind->scale_min;
                    return $range > 0 ? ($s->value - $ind->scale_min) / $range * 100 : 50;
                })->filter();
                $byArea[$area->name] = round($normalized->avg(), 1);
            }
            $timeline[] = ['date' => $date, ...$byArea];
        }

        // Radar: avg por área (para ApexCharts)
        $radarSeries  = collect($areasData)->pluck('avg')->values()->all();
        $radarLabels  = collect($areasData)->pluck('name')->values()->all();
        $radarColors  = collect($areasData)->pluck('color')->values()->all();

        return Inertia::render('MemberAreaApp/MapaNeuro', [
            'product'       => ['id' => $product->id, 'name' => $product->name, 'slug' => $product->checkout_slug],
            'config'        => $product->member_area_config,
            'areas'         => $areasData,
            'radar_series'  => $radarSeries,
            'radar_labels'  => $radarLabels,
            'radar_colors'  => $radarColors,
            'timeline'      => $timeline,
            'has_data'      => ! empty(array_filter($radarSeries)),
            'base_url'      => $this->baseUrl($product, $request),
            'slug'          => $slug,
        ]);
    }

    private function baseUrl($product, Request $request): string
    {
        return $this->resolver->baseUrlForProduct($product)
            ?? url("/m/{$product->checkout_slug}");
    }
}
