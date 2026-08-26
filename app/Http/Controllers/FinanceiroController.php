<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Subscription;
use App\Models\ProfessionalCommission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class FinanceiroController extends Controller
{
    private function tenantId(): ?int
    {
        return auth()->user()->tenant_id;
    }

    public function dashboard(Request $request): Response
    {
        $tenantId = $this->tenantId();
        $months   = max(1, min(24, (int) $request->query('months', 12)));

        // Receita por mês (últimos N meses)
        $revenueByMonth = Order::forTenant($tenantId)
            ->where('status', 'paid')
            ->where('created_at', '>=', now()->subMonths($months)->startOfMonth())
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, SUM(amount) as total, COUNT(*) as count")
            ->groupByRaw("DATE_FORMAT(created_at, '%Y-%m')")
            ->orderBy('month')
            ->get()
            ->map(fn ($r) => ['month' => $r->month, 'total' => (float) $r->total, 'count' => (int) $r->count]);

        // MRR: soma das assinaturas ativas (simplificado: plano mensal × preço)
        $mrr = Subscription::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->join('subscription_plans', 'subscriptions.plan_id', '=', 'subscription_plans.id')
            ->selectRaw('SUM(subscription_plans.price) as mrr')
            ->value('mrr') ?? 0;

        $arr = (float) $mrr * 12;

        // Receita total histórica
        $totalRevenue = Order::forTenant($tenantId)->where('status', 'paid')->sum('amount');
        $totalOrders  = Order::forTenant($tenantId)->where('status', 'paid')->count();

        // Receita por produto (top 10)
        $byProduct = Order::forTenant($tenantId)
            ->where('status', 'paid')
            ->join('products', 'orders.product_id', '=', 'products.id')
            ->selectRaw('products.name as product_name, SUM(orders.amount) as total, COUNT(*) as count')
            ->groupBy('products.name')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn ($r) => ['name' => $r->product_name, 'total' => (float) $r->total, 'count' => (int) $r->count]);

        // Receita por método de pagamento
        $byMethod = Order::forTenant($tenantId)
            ->where('status', 'paid')
            ->selectRaw('payment_method, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('payment_method')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => ['method' => $r->payment_method, 'total' => (float) $r->total, 'count' => (int) $r->count]);

        // Reembolsos no período
        $refunds = Order::forTenant($tenantId)
            ->where('status', 'refunded')
            ->where('created_at', '>=', now()->subMonths($months)->startOfMonth())
            ->selectRaw('SUM(amount) as total, COUNT(*) as count')
            ->first();

        // Este mês vs mês anterior
        $thisMonth = Order::forTenant($tenantId)
            ->where('status', 'paid')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('amount');

        $lastMonth = Order::forTenant($tenantId)
            ->where('status', 'paid')
            ->whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)
            ->sum('amount');

        $monthGrowth = $lastMonth > 0
            ? round((($thisMonth - $lastMonth) / $lastMonth) * 100, 1)
            : 0;

        // Comissões pendentes
        $commissionsPending = ProfessionalCommission::forTenant($tenantId)
            ->where('status', 'pending')
            ->sum('commission_amount');

        return Inertia::render('Financeiro/Dashboard', [
            'revenue_by_month'    => $revenueByMonth,
            'by_product'          => $byProduct,
            'by_method'           => $byMethod,
            'stats' => [
                'mrr'                  => (float) $mrr,
                'arr'                  => (float) $arr,
                'total_revenue'        => (float) $totalRevenue,
                'total_orders'         => (int) $totalOrders,
                'this_month'           => (float) $thisMonth,
                'last_month'           => (float) $lastMonth,
                'month_growth'         => $monthGrowth,
                'refunds_total'        => (float) ($refunds?->total ?? 0),
                'refunds_count'        => (int) ($refunds?->count ?? 0),
                'commissions_pending'  => (float) $commissionsPending,
                'active_subscriptions' => Subscription::where('tenant_id', $tenantId)->where('status', 'active')->count(),
            ],
            'months' => $months,
        ]);
    }
}
