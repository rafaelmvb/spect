<?php

namespace App\Services;

use App\Models\Order;
use App\Support\ReportingPeriod;
use Illuminate\Support\Facades\Cache;

class SalesAchievementsService
{
    /**
     * Este total é lido nos shared props do Inertia, ou seja, em toda página do
     * painel e em todo reload parcial. Sem cache, cada navegação disparava um
     * SUM sobre a tabela inteira de pedidos.
     *
     * A chave carrega o mesmo token de invalidação do dashboard, então uma venda
     * concluída derruba o cache na hora (ver InvalidateDashboardCacheOnOrderCompleted).
     */
    public function getValidSalesTotal(?int $tenantId): float
    {
        $chave = sprintf(
            'sales_achievements_total:%s:%s',
            $tenantId ?? 'global',
            ReportingPeriod::dashboardBustToken($tenantId)
        );

        return (float) Cache::remember($chave, now()->addMinutes(10), fn (): float => $this->calcularTotalValido($tenantId));
    }

    private function calcularTotalValido(?int $tenantId): float
    {
        return (float) Order::forTenant($tenantId)
            ->where('status', 'completed')
            ->where(function ($q) {
                $q->where('approved_manually', false)
                    ->orWhereNull('approved_manually');
            })
            ->whereNotNull('gateway')
            ->where('gateway', '!=', 'manual')
            ->sum('amount');
    }

    /**
     * @return array{total_valid_sales: float, current_achievement: array|null, next_achievement: array|null, progress_percent: float, achievements: array}
     */
    public function getProgressForTenant(?int $tenantId): array
    {
        $total = $this->getValidSalesTotal($tenantId);
        $achievements = config('conquistas.achievements', []);

        $current = null;
        $next = null;
        $progressPercent = 0.0;

        $result = [];
        foreach ($achievements as $i => $a) {
            $unlocked = $total >= $a['threshold'];
            $result[] = [
                'threshold' => $a['threshold'],
                'slug' => $a['slug'],
                'name' => $a['name'],
                'image' => $a['image'],
                'unlocked' => $unlocked,
            ];

            if ($unlocked) {
                $current = $a;
            } elseif ($next === null) {
                $next = $a;
            }
        }

        if ($next !== null) {
            $prevThreshold = $current['threshold'] ?? 0;
            $range = $next['threshold'] - $prevThreshold;
            $progress = $total - $prevThreshold;
            $progressPercent = $range > 0 ? min(100, max(0, ($progress / $range) * 100)) : 0;
        } elseif ($current !== null) {
            $progressPercent = 100;
            $next = null;
        }

        return [
            'total_valid_sales' => $total,
            'current_achievement' => $current,
            'next_achievement' => $next,
            'progress_percent' => round($progressPercent, 1),
            'achievements' => $result,
        ];
    }

    public function getAchievementBySlug(string $slug): ?array
    {
        $achievements = config('conquistas.achievements', []);
        foreach ($achievements as $a) {
            if (($a['slug'] ?? '') === $slug) {
                return $a;
            }
        }
        return null;
    }

    public function getValidSlugs(): array
    {
        $achievements = config('conquistas.achievements', []);
        return array_column($achievements, 'slug');
    }
}
