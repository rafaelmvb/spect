<?php

namespace App\Http\Controllers;

use App\Models\CheckoutSession;
use App\Models\CheckpointResponse;
use App\Models\JourneyStepProgress;
use App\Models\MemberActivityLog;
use App\Models\MemberLesson;
use App\Models\MemberLessonProgress;
use App\Models\NeuroUserScore;
use App\Models\Order;
use App\Models\Product;
use App\Models\Professional;
use App\Models\ProfessionalReview;
use App\Models\Appointment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\MetaCustomAudienceCsvService;
use App\Support\ReportingPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RelatoriosController extends Controller
{
    private const PERIODS = ['hoje', 'ontem', '7dias', 'mes', 'ano', 'total'];

    public function __construct(
        private MetaCustomAudienceCsvService $metaCustomAudienceCsv
    ) {}

    public function index(Request $request): Response
    {
        $period = $request->query('period', 'hoje');
        if (! in_array($period, self::PERIODS, true)) {
            $period = 'hoje';
        }

        $tenantId = auth()->user()->tenant_id;
        [$start, $end] = ReportingPeriod::boundsForDashboard($period);

        $ordersQuery = Order::forTenant($tenantId);
        ReportingPeriod::applyCreatedAtBounds($ordersQuery, $start, $end);

        $ordersCompleted = (clone $ordersQuery)->where('status', 'completed');
        $ordersRefunded = (clone $ordersQuery)->where('status', 'refunded');

        $receitaTotal = (float) $ordersCompleted->sum('amount');
        $quantidadeVendas = $ordersCompleted->count();
        $ticketMedio = $quantidadeVendas > 0 ? $receitaTotal / $quantidadeVendas : 0.0;
        $reembolsosCount = $ordersRefunded->count();
        $reembolsosTotal = (float) $ordersRefunded->sum('amount');

        $totalAlunos = User::where('role', User::ROLE_ALUNO)
            ->whereHas('products', fn ($q) => $tenantId === null ? $q->whereNull('tenant_id') : $q->where('tenant_id', $tenantId))
            ->count();
        $totalProdutos = Product::forTenant($tenantId)->count();

        $formasPagamento = (clone $ordersQuery)
            ->where('status', 'completed')
            ->selectRaw('gateway, SUM(amount) as total, COUNT(*) as quantidade')
            ->groupBy('gateway')
            ->get()
            ->map(function ($row) {
                $label = $this->gatewayLabel($row->gateway);

                return [
                    'metodo' => $row->gateway ?? 'outro',
                    'label' => $label,
                    'total' => (float) $row->total,
                    'quantidade' => (int) $row->quantidade,
                ];
            })
            ->values()
            ->all();

        $graficoReceita = $this->buildGraficoReceita($tenantId, $start, $end);

        $receitaPorProduto = Order::query()
            ->when($tenantId === null, fn ($q) => $q->whereNull('orders.tenant_id'), fn ($q) => $q->where('orders.tenant_id', $tenantId))
            ->where('orders.status', 'completed');
        if ($start && $end) {
            $receitaPorProduto->whereBetween('orders.created_at', [$start, $end]);
        } elseif ($start) {
            $receitaPorProduto->where('orders.created_at', '>=', $start);
        } elseif ($end) {
            $receitaPorProduto->where('orders.created_at', '<=', $end);
        }
        $receitaPorProduto = $receitaPorProduto
            ->join('products', 'orders.product_id', '=', 'products.id')
            ->selectRaw('products.id as product_id, products.name as product_name, SUM(orders.amount) as total, COUNT(*) as quantidade')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'product_id' => $r->product_id,
                'product_name' => $r->product_name,
                'total' => (float) $r->total,
                'quantidade' => (int) $r->quantidade,
            ])
            ->values()
            ->all();

        $sessionsQuery = CheckoutSession::forTenant($tenantId);
        ReportingPeriod::applyCreatedAtBounds($sessionsQuery, $start, $end);

        $abandonadosVisit = (clone $sessionsQuery)
            ->whereAbandonmentVisitEligible()
            ->count();

        $abandonadosForm = (clone $sessionsQuery)
            ->whereAbandonmentFormEligible()
            ->count();

        $converted = (clone $sessionsQuery)
            ->where('step', CheckoutSession::STEP_CONVERTED)
            ->count();

        $totalSessoesPeriodo = (clone $sessionsQuery)->count();

        $abandonadosTotal = $abandonadosVisit + $abandonadosForm;
        $taxaConversao = $totalSessoesPeriodo > 0
            ? round((float) $converted / $totalSessoesPeriodo * 100, 1)
            : 0.0;

        $abandonadosComEmail = CheckoutSession::forTenant($tenantId)
            ->whereAbandonmentFormEligible()
            ->whereNotNull('email')
            ->where('email', '!=', '');
        ReportingPeriod::applyCreatedAtBounds($abandonadosComEmail, $start, $end);
        $abandonadosComEmail = $abandonadosComEmail
            ->with('product:id,name')
            ->orderByDesc('updated_at')
            ->limit(20)
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'email' => $s->email,
                'name' => $s->name,
                'product_name' => $s->product?->name ?? '–',
                'updated_at' => $s->updated_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return Inertia::render('Relatorios/Index', [
            'period' => $period,
            'meta_export_products' => $this->metaCustomAudienceCsv->productsForExportDropdown($request->user()),
            'receita_total' => round($receitaTotal, 2),
            'quantidade_vendas' => $quantidadeVendas,
            'ticket_medio' => round($ticketMedio, 2),
            'total_alunos' => $totalAlunos,
            'total_produtos' => $totalProdutos,
            'formas_pagamento' => $formasPagamento,
            'grafico_receita' => $graficoReceita,
            'receita_por_produto' => $receitaPorProduto,
            'abandonados_visit' => $abandonadosVisit,
            'abandonados_form' => $abandonadosForm,
            'abandonados_total' => $abandonadosTotal,
            'taxa_conversao' => $taxaConversao,
            'abandonados_com_email' => $abandonadosComEmail,
            'reembolsos_count' => $reembolsosCount,
            'reembolsos_total' => round($reembolsosTotal, 2),
        ]);
    }

    public function exportMetaCompradores(Request $request): StreamedResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'string'],
        ]);

        return $this->metaCustomAudienceCsv->streamPurchasers($request->user(), $data['product_id']);
    }

    public function exportMetaAbandonos(Request $request): StreamedResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'string'],
        ]);

        return $this->metaCustomAudienceCsv->streamAbandonedEngaged($request->user(), $data['product_id']);
    }

    // ─── Vendas & Receita ─────────────────────────────────────────────────────
    public function vendas(Request $request): Response
    {
        $tenantId = auth()->user()->tenant_id;
        $period = $request->query('period', '30d');
        $allowedPeriods = ['7d', '30d', '90d', 'mes', 'ano', 'total'];
        if (! in_array($period, $allowedPeriods, true)) $period = '30d';

        [$start, $end] = $this->periodBounds($period);

        $base = Order::forTenant($tenantId)->where('status', 'completed');
        ReportingPeriod::applyCreatedAtBounds($base, $start, $end);

        // KPIs
        $receita = (float) (clone $base)->sum('amount');
        $vendas = (clone $base)->count();
        $ticket = $vendas > 0 ? round($receita / $vendas, 2) : 0;
        $reembolsos = (float) Order::forTenant($tenantId)->where('status', 'refunded')
            ->when($start, fn ($q) => $q->where('created_at', '>=', $start))
            ->when($end, fn ($q) => $q->where('created_at', '<=', $end))
            ->sum('amount');

        // Receita por dia
        $porDia = $this->buildGraficoReceita($tenantId, $start, $end);

        // Por gateway
        $porGateway = Order::query()
            ->when($tenantId === null, fn ($q) => $q->whereNull('orders.tenant_id'), fn ($q) => $q->where('orders.tenant_id', $tenantId))
            ->where('orders.status', 'completed')
            ->when($start, fn ($q) => $q->where('orders.created_at', '>=', $start))
            ->when($end, fn ($q) => $q->where('orders.created_at', '<=', $end))
            ->selectRaw('COALESCE(orders.gateway, "outro") as gateway, SUM(orders.amount) as total, COUNT(*) as qtd')
            ->groupBy('orders.gateway')->orderByDesc('total')->get()
            ->map(fn ($r) => ['gateway' => $r->gateway, 'label' => $this->gatewayLabel($r->gateway), 'total' => (float) $r->total, 'qtd' => (int) $r->qtd]);

        // Por produto top 10
        $porProduto = Order::query()
            ->when($tenantId === null, fn ($q) => $q->whereNull('orders.tenant_id'), fn ($q) => $q->where('orders.tenant_id', $tenantId))
            ->where('orders.status', 'completed')
            ->when($start, fn ($q) => $q->where('orders.created_at', '>=', $start))
            ->when($end, fn ($q) => $q->where('orders.created_at', '<=', $end))
            ->join('products', 'orders.product_id', '=', 'products.id')
            ->selectRaw('products.name as nome, SUM(orders.amount) as total, COUNT(*) as qtd')
            ->groupBy('products.id', 'products.name')->orderByDesc('total')->limit(10)->get()
            ->map(fn ($r) => ['nome' => $r->nome, 'total' => (float) $r->total, 'qtd' => (int) $r->qtd]);

        // Por cupom top 10
        $porCupom = (clone $base)
            ->whereNotNull('orders.coupon_code')->where('orders.coupon_code', '!=', '')
            ->selectRaw('orders.coupon_code, COUNT(*) as qtd, SUM(orders.amount) as total')
            ->groupBy('orders.coupon_code')->orderByDesc('qtd')->limit(10)->get()
            ->map(fn ($r) => ['cupom' => $r->coupon_code, 'qtd' => (int) $r->qtd, 'total' => (float) $r->total]);

        // Vendas por hora do dia
        $porHora = (clone $base)
            ->selectRaw('HOUR(orders.created_at) as hora, COUNT(*) as qtd')
            ->groupBy('hora')->orderBy('hora')->get()
            ->map(fn ($r) => ['hora' => (int) $r->hora, 'qtd' => (int) $r->qtd]);

        // Vendas por dia da semana
        $porDiaSemana = (clone $base)
            ->selectRaw('DAYOFWEEK(orders.created_at) as dow, COUNT(*) as qtd')
            ->groupBy('dow')->orderBy('dow')->get()
            ->map(fn ($r) => ['dow' => (int) $r->dow, 'qtd' => (int) $r->qtd]);

        return Inertia::render('Relatorios/Vendas', compact(
            'period', 'receita', 'vendas', 'ticket', 'reembolsos',
            'porDia', 'porGateway', 'porProduto', 'porCupom', 'porHora', 'porDiaSemana'
        ));
    }

    // ─── Alunos & Engajamento ─────────────────────────────────────────────────
    public function alunos(Request $request): Response
    {
        $tenantId = auth()->user()->tenant_id;

        // Total de alunos
        $totalAlunos = User::where('role', User::ROLE_ALUNO)
            ->whereHas('products', fn ($q) => $tenantId === null ? $q->whereNull('tenant_id') : $q->where('tenant_id', $tenantId))
            ->count();

        // Novos alunos por mês (últimos 6 meses)
        $novosPorMes = DB::table('product_user')
            ->join('products', 'products.id', '=', 'product_user.product_id')
            ->join('users', 'users.id', '=', 'product_user.user_id')
            ->where('products.tenant_id', $tenantId)
            ->where('users.role', 'aluno')
            ->where('product_user.created_at', '>=', now()->subMonths(6)->startOfMonth())
            ->selectRaw('DATE_FORMAT(product_user.created_at, "%Y-%m") as mes, COUNT(DISTINCT product_user.user_id) as total')
            ->groupBy('mes')->orderBy('mes')->get();

        // Progresso médio por produto
        $progressoPorProduto = DB::table('member_lesson_progress')
            ->join('member_lessons', 'member_lessons.id', '=', 'member_lesson_progress.member_lesson_id')
            ->join('products', 'products.id', '=', 'member_lessons.product_id')
            ->where('products.tenant_id', $tenantId)
            ->selectRaw('products.name as produto,
                COUNT(DISTINCT member_lesson_progress.user_id) as alunos_ativos,
                SUM(CASE WHEN member_lesson_progress.completed_at IS NOT NULL THEN 1 ELSE 0 END) as aulas_concluidas,
                COUNT(member_lesson_progress.id) as total_progresso')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('alunos_ativos')->limit(10)->get();

        // Alunos mais engajados (mais aulas concluídas)
        $topAlunos = DB::table('member_lesson_progress')
            ->join('users', 'users.id', '=', 'member_lesson_progress.user_id')
            ->join('member_lessons', 'member_lessons.id', '=', 'member_lesson_progress.member_lesson_id')
            ->join('products', 'products.id', '=', 'member_lessons.product_id')
            ->where('products.tenant_id', $tenantId)
            ->whereNotNull('member_lesson_progress.completed_at')
            ->selectRaw('users.name, users.email, COUNT(*) as aulas_concluidas, MAX(member_lesson_progress.updated_at) as ultimo_acesso')
            ->groupBy('users.id', 'users.name', 'users.email')
            ->orderByDesc('aulas_concluidas')->limit(15)->get();

        // Aulas mais populares
        $aulasPopulares = DB::table('member_lesson_progress')
            ->join('member_lessons', 'member_lessons.id', '=', 'member_lesson_progress.member_lesson_id')
            ->join('products', 'products.id', '=', 'member_lessons.product_id')
            ->where('products.tenant_id', $tenantId)
            ->selectRaw('member_lessons.title as aula, products.name as produto, COUNT(DISTINCT member_lesson_progress.user_id) as acessos, SUM(CASE WHEN member_lesson_progress.completed_at IS NOT NULL THEN 1 ELSE 0 END) as conclusoes')
            ->groupBy('member_lessons.id', 'member_lessons.title', 'products.id', 'products.name')
            ->orderByDesc('acessos')->limit(10)->get();

        // Alunos sem acesso (têm produto mas nunca abriram aula)
        $alunosComAcesso = DB::table('product_user')
            ->join('users', 'users.id', '=', 'product_user.user_id')
            ->join('products', 'products.id', '=', 'product_user.product_id')
            ->where('products.tenant_id', $tenantId)
            ->where('users.role', 'aluno')
            ->selectRaw('COUNT(DISTINCT product_user.user_id) as total')
            ->value('total') ?? 0;

        $alunosComProgresso = DB::table('member_lesson_progress')
            ->join('member_lessons', 'member_lessons.id', '=', 'member_lesson_progress.member_lesson_id')
            ->join('products', 'products.id', '=', 'member_lessons.product_id')
            ->where('products.tenant_id', $tenantId)
            ->distinct()->count('member_lesson_progress.user_id');

        $semAcesso = max(0, $alunosComAcesso - $alunosComProgresso);

        return Inertia::render('Relatorios/Alunos', compact(
            'totalAlunos', 'novosPorMes', 'progressoPorProduto', 'topAlunos', 'aulasPopulares', 'semAcesso'
        ));
    }

    // ─── Conversão & Funil ────────────────────────────────────────────────────
    public function conversao(Request $request): Response
    {
        $tenantId = auth()->user()->tenant_id;
        $period = $request->query('period', '30d');
        [$start, $end] = $this->periodBounds($period);

        // Sem JOIN → sem ambiguidade
        $sessBase = CheckoutSession::forTenant($tenantId);
        ReportingPeriod::applyCreatedAtBounds($sessBase, $start, $end);

        $totalSessoes = (clone $sessBase)->count();
        $visit        = (clone $sessBase)->where('step', 'visit')->count();
        $formStarted  = (clone $sessBase)->where('step', 'form_started')->count();
        $formFilled   = (clone $sessBase)->where('step', 'form_filled')->count();
        $converted    = (clone $sessBase)->where('step', 'converted')->count();

        $taxaConversao = $totalSessoes > 0 ? round($converted / $totalSessoes * 100, 2) : 0;
        $taxaForm      = $totalSessoes > 0 ? round(($formStarted + $formFilled + $converted) / $totalSessoes * 100, 2) : 0;

        // Conversão por produto — JOIN: qualificar tenant_id
        $porProduto = DB::table('checkout_sessions')
            ->join('products', 'products.id', '=', 'checkout_sessions.product_id')
            ->where('checkout_sessions.tenant_id', $tenantId)
            ->when($start, fn ($q) => $q->where('checkout_sessions.created_at', '>=', $start))
            ->when($end,   fn ($q) => $q->where('checkout_sessions.created_at', '<=', $end))
            ->selectRaw('products.name as produto,
                COUNT(*) as total,
                SUM(CASE WHEN checkout_sessions.step = "converted" THEN 1 ELSE 0 END) as convertidos')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('total')->limit(10)->get()
            ->map(fn ($r) => [
                'produto'    => $r->produto,
                'total'      => (int) $r->total,
                'convertidos'=> (int) $r->convertidos,
                'taxa'       => $r->total > 0 ? round($r->convertidos / $r->total * 100, 1) : 0,
            ]);

        // Origem do tráfego — sem JOIN, sem ambiguidade
        $porUtmSource = (clone $sessBase)
            ->whereNotNull('utm_source')->where('utm_source', '!=', '')
            ->selectRaw('checkout_sessions.utm_source, COUNT(*) as total,
                SUM(CASE WHEN checkout_sessions.step = "converted" THEN 1 ELSE 0 END) as convertidos')
            ->groupBy('checkout_sessions.utm_source')->orderByDesc('total')->limit(10)->get()
            ->map(fn ($r) => ['source' => $r->utm_source, 'total' => (int) $r->total, 'convertidos' => (int) $r->convertidos]);

        // Conversões por hora
        $porHora = (clone $sessBase)
            ->where('checkout_sessions.step', 'converted')
            ->selectRaw('HOUR(checkout_sessions.created_at) as hora, COUNT(*) as qtd')
            ->groupBy('hora')->orderBy('hora')->get()
            ->map(fn ($r) => ['hora' => (int) $r->hora, 'qtd' => (int) $r->qtd]);

        $abandonoVisit = (clone $sessBase)->whereAbandonmentVisitEligible()->count();
        $abandonoForm  = (clone $sessBase)->whereAbandonmentFormEligible()->count();

        return Inertia::render('Relatorios/Conversao', compact(
            'period', 'totalSessoes', 'visit', 'formStarted', 'formFilled', 'converted',
            'taxaConversao', 'taxaForm', 'porProduto', 'porUtmSource', 'porHora',
            'abandonoVisit', 'abandonoForm'
        ));
    }

    // ─── Performance de Produtos ──────────────────────────────────────────────
    public function produtos(Request $request): Response
    {
        $tenantId = auth()->user()->tenant_id;

        $produtos = Product::forTenant($tenantId)->get(['id', 'name', 'type', 'billing_type', 'price', 'is_active']);

        $resultado = $produtos->map(function ($produto) use ($tenantId) {
            $receita = Order::forTenant($tenantId)->where('product_id', $produto->id)->where('status', 'completed')->sum('amount');
            $vendas = Order::forTenant($tenantId)->where('product_id', $produto->id)->where('status', 'completed')->count();
            $reembolsos = Order::forTenant($tenantId)->where('product_id', $produto->id)->where('status', 'refunded')->count();
            $alunos = DB::table('product_user')->where('product_id', $produto->id)->count();

            $totalAulas = MemberLesson::where('product_id', $produto->id)->count();
            $alunosComProgresso = $totalAulas > 0
                ? DB::table('member_lesson_progress')
                    ->join('member_lessons', 'member_lessons.id', '=', 'member_lesson_progress.member_lesson_id')
                    ->where('member_lessons.product_id', $produto->id)
                    ->selectRaw('COUNT(DISTINCT member_lesson_progress.user_id) as cnt')
                    ->value('cnt') ?? 0
                : 0;

            $taxaEngajamento = $alunos > 0 ? round($alunosComProgresso / $alunos * 100, 1) : 0;
            $taxaReembolso = $vendas > 0 ? round($reembolsos / $vendas * 100, 1) : 0;

            return [
                'id' => $produto->id,
                'nome' => $produto->name,
                'tipo' => $produto->type,
                'billing' => $produto->billing_type,
                'preco' => (float) $produto->price,
                'ativo' => (bool) $produto->is_active,
                'receita' => round((float) $receita, 2),
                'vendas' => (int) $vendas,
                'reembolsos' => (int) $reembolsos,
                'alunos' => (int) $alunos,
                'taxa_engajamento' => $taxaEngajamento,
                'taxa_reembolso' => $taxaReembolso,
                'ticket_medio' => $vendas > 0 ? round((float) $receita / $vendas, 2) : 0,
            ];
        })->sortByDesc('receita')->values();

        // Receita total
        $receitaTotal = $resultado->sum('receita');

        return Inertia::render('Relatorios/Produtos', compact('resultado', 'receitaTotal'));
    }

    // ─── Assinaturas & MRR ────────────────────────────────────────────────────
    public function assinaturas(Request $request): Response
    {
        $tenantId = auth()->user()->tenant_id;

        $ativas = Subscription::where('tenant_id', $tenantId)->where('status', 'active')->count();
        $canceladas = Subscription::where('tenant_id', $tenantId)->where('status', 'cancelled')->count();
        $pastDue = Subscription::where('tenant_id', $tenantId)->where('status', 'past_due')->count();

        // MRR aproximado (soma dos preços dos planos de assinatura ativa)
        $mrr = DB::table('subscriptions')
            ->join('subscription_plans', 'subscription_plans.id', '=', 'subscriptions.subscription_plan_id')
            ->where('subscriptions.tenant_id', $tenantId)
            ->where('subscriptions.status', 'active')
            ->sum('subscription_plans.price');

        // Novas assinaturas por mês (últimos 6 meses)
        $novasPorMes = DB::table('subscriptions')
            ->where('subscriptions.tenant_id', $tenantId)
            ->where('subscriptions.created_at', '>=', now()->subMonths(6)->startOfMonth())
            ->selectRaw('DATE_FORMAT(subscriptions.created_at, "%Y-%m") as mes, subscriptions.status, COUNT(*) as total')
            ->groupBy('mes', 'subscriptions.status')->orderBy('mes')->get()
            ->groupBy('mes')->map(fn ($rows) => $rows->keyBy('status'));

        // Por produto (JOIN: qualificar para evitar ambiguidade em tenant_id)
        $porProduto = DB::table('subscriptions')
            ->join('products', 'products.id', '=', 'subscriptions.product_id')
            ->where('subscriptions.tenant_id', $tenantId)
            ->selectRaw('products.name as produto, subscriptions.status, COUNT(*) as total')
            ->groupBy('products.id', 'products.name', 'subscriptions.status')
            ->orderByDesc('total')->get()
            ->groupBy('produto');

        // Churn dos últimos 30 dias
        $churnRecente = Subscription::where('tenant_id', $tenantId)
            ->where('status', 'cancelled')
            ->where('updated_at', '>=', now()->subDays(30))
            ->count();

        // Renovações dos últimos 30 dias
        $renovacoesRecentes = Order::forTenant($tenantId)
            ->where('status', 'completed')
            ->where('is_renewal', true)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        return Inertia::render('Relatorios/Assinaturas', compact(
            'ativas', 'canceladas', 'pastDue', 'mrr',
            'novasPorMes', 'porProduto', 'churnRecente', 'renovacoesRecentes'
        ));
    }

    // ─── Exportações ──────────────────────────────────────────────────────────
    public function exportacoes(Request $request): Response
    {
        $tenantId = auth()->user()->tenant_id;
        $metaProducts = $this->metaCustomAudienceCsv->productsForExportDropdown($request->user());
        $produtos = Product::forTenant($tenantId)->get(['id', 'name']);

        return Inertia::render('Relatorios/Exportacoes', compact('metaProducts', 'produtos'));
    }

    public function exportVendas(Request $request): StreamedResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $period = $request->query('period', '30d');
        [$start, $end] = $this->periodBounds($period);

        $orders = Order::forTenant($tenantId)
            ->where('status', 'completed')
            ->with(['product:id,name', 'user:id,name'])
            ->when($start, fn ($q) => $q->where('created_at', '>=', $start))
            ->when($end, fn ($q) => $q->where('created_at', '<=', $end))
            ->orderByDesc('created_at')
            ->get();

        return response()->streamDownload(function () use ($orders) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Data', 'Produto', 'Email', 'Nome', 'Valor', 'Gateway', 'Cupom', 'ID']);
            foreach ($orders as $o) {
                fputcsv($out, [
                    $o->created_at->format('d/m/Y H:i'),
                    $o->product?->name ?? '',
                    $o->email ?? '',
                    $o->user?->name ?? '',
                    number_format((float) $o->amount, 2, ',', '.'),
                    $o->gateway ?? '',
                    $o->coupon_code ?? '',
                    $o->id,
                ]);
            }
            fclose($out);
        }, 'vendas-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv']);
    }

    public function exportAlunos(Request $request): StreamedResponse
    {
        $tenantId = auth()->user()->tenant_id;

        $alunos = DB::table('product_user')
            ->join('users', 'users.id', '=', 'product_user.user_id')
            ->join('products', 'products.id', '=', 'product_user.product_id')
            ->where('products.tenant_id', $tenantId)
            ->where('users.role', 'aluno')
            ->selectRaw('users.name, users.email, products.name as produto, product_user.created_at')
            ->orderByDesc('product_user.created_at')
            ->get();

        return response()->streamDownload(function () use ($alunos) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Nome', 'Email', 'Produto', 'Data de Acesso']);
            foreach ($alunos as $a) {
                fputcsv($out, [$a->name, $a->email, $a->produto, $a->created_at]);
            }
            fclose($out);
        }, 'alunos-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv']);
    }

    // ─── Helper: bounds por período ────────────────────────────────────────────
    private function periodBounds(string $period): array
    {
        $now = ReportingPeriod::now();
        return match ($period) {
            '7d'    => [$now->copy()->subDays(7)->startOfDay(), $now->copy()->endOfDay()],
            '30d'   => [$now->copy()->subDays(30)->startOfDay(), $now->copy()->endOfDay()],
            '90d'   => [$now->copy()->subDays(90)->startOfDay(), $now->copy()->endOfDay()],
            'mes'   => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'ano'   => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            default => [null, null],
        };
    }

    private function gatewayLabel(?string $gateway): string
    {
        if ($gateway === null || $gateway === '') {
            return 'Outro';
        }
        $g = strtolower($gateway);
        if (str_contains($g, 'pix') || in_array($g, ['spacepag'], true)) {
            return 'Pix';
        }
        if (str_contains($g, 'card') || str_contains($g, 'cartao') || str_contains($g, 'cartão') || str_contains($g, 'credito')) {
            return 'Cartão';
        }
        if (str_contains($g, 'boleto')) {
            return 'Boleto';
        }

        return ucfirst($gateway);
    }

    private function buildGraficoReceita(?int $tenantId, ?\Carbon\Carbon $start, ?\Carbon\Carbon $end): array
    {
        $query = Order::forTenant($tenantId)->where('status', 'completed');
        ReportingPeriod::applyCreatedAtBounds($query, $start, $end);

        $totalsByDate = [];
        $tz = ReportingPeriod::timezone();
        $query->select(['created_at', 'amount'])->orderBy('created_at')->chunk(500, function ($orders) use (&$totalsByDate, $tz) {
            foreach ($orders as $order) {
                $d = $order->created_at->timezone($tz)->format('Y-m-d');
                $totalsByDate[$d] = ($totalsByDate[$d] ?? 0.0) + (float) $order->amount;
            }
        });
        ksort($totalsByDate);

        $out = [];
        foreach ($totalsByDate as $data => $total) {
            $out[] = ['data' => $data, 'total' => round($total, 2)];
        }

        return $out;
    }

    // ─── Relatórios avançados ─────────────────────────────────────────────────

    public function engajamento(Request $request): Response
    {
        $tenantId = auth()->user()->tenant_id;
        $months   = max(1, min(12, (int) $request->query('months', 3)));
        $since    = now()->subMonths($months)->startOfMonth();

        // DAU por dia (últimos 30 dias)
        $dau = MemberActivityLog::whereHas('user', fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(created_at) as day, COUNT(DISTINCT user_id) as users')
            ->groupBy('day')->orderBy('day')
            ->get()->map(fn ($r) => ['day' => $r->day, 'users' => (int) $r->users]);

        // WAU por semana
        $wau = MemberActivityLog::whereHas('user', fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('created_at', '>=', $since)
            ->selectRaw('YEARWEEK(created_at,1) as week, COUNT(DISTINCT user_id) as users')
            ->groupBy('week')->orderBy('week')
            ->get()->map(fn ($r) => ['week' => (string) $r->week, 'users' => (int) $r->users]);

        // Aulas concluídas por semana
        $lessonsWeek = MemberLessonProgress::whereHas('user', fn ($q) => $q->where('tenant_id', $tenantId))
            ->whereNotNull('completed_at')->where('completed_at', '>=', $since)
            ->selectRaw('YEARWEEK(completed_at,1) as week, COUNT(*) as total')
            ->groupBy('week')->orderBy('week')
            ->get()->map(fn ($r) => ['week' => (string) $r->week, 'total' => (int) $r->total]);

        // Checkpoints por mês
        $checkpointsMes = CheckpointResponse::whereHas('user', fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('completed_at', '>=', $since)
            ->selectRaw("DATE_FORMAT(completed_at,'%Y-%m') as month, COUNT(*) as total")
            ->groupByRaw("DATE_FORMAT(completed_at,'%Y-%m')")->orderBy('month')
            ->get()->map(fn ($r) => ['month' => $r->month, 'total' => (int) $r->total]);

        // Jornadas concluídas (steps) por semana
        $journeysWeek = JourneyStepProgress::whereHas('user', fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('completed_at', '>=', $since)
            ->selectRaw('YEARWEEK(completed_at,1) as week, COUNT(*) as total')
            ->groupBy('week')->orderBy('week')
            ->get()->map(fn ($r) => ['week' => (string) $r->week, 'total' => (int) $r->total]);

        $stats = [
            'avg_dau'           => $dau->avg('users') ? round($dau->avg('users'), 1) : 0,
            'lessons_total'     => MemberLessonProgress::whereHas('user', fn ($q) => $q->where('tenant_id', $tenantId))->whereNotNull('completed_at')->where('completed_at', '>=', $since)->count(),
            'checkpoints_total' => CheckpointResponse::whereHas('user', fn ($q) => $q->where('tenant_id', $tenantId))->where('completed_at', '>=', $since)->count(),
            'journey_steps'     => JourneyStepProgress::whereHas('user', fn ($q) => $q->where('tenant_id', $tenantId))->where('completed_at', '>=', $since)->count(),
        ];

        return Inertia::render('Relatorios/Engajamento', [
            'dau'             => $dau,
            'wau'             => $wau,
            'lessons_week'    => $lessonsWeek,
            'checkpoints_mes' => $checkpointsMes,
            'journeys_week'   => $journeysWeek,
            'stats'           => $stats,
            'months'          => $months,
        ]);
    }

    public function retencao(Request $request): Response
    {
        $tenantId = auth()->user()->tenant_id;
        $cohorts  = (int) $request->query('cohorts', 6);

        // Cohort: alunos que se cadastraram em cada mês e quantos voltaram nos meses seguintes
        $cohortData = [];
        for ($i = $cohorts - 1; $i >= 0; $i--) {
            $cohortMonth = now()->subMonths($i)->startOfMonth();
            $end         = $cohortMonth->copy()->endOfMonth();

            $cohortUsers = User::where('tenant_id', $tenantId)
                ->where('role', User::ROLE_ALUNO)
                ->whereBetween('created_at', [$cohortMonth, $end])
                ->pluck('id');

            $label = $cohortMonth->format('Y-m');
            $size  = $cohortUsers->count();

            $retention = [];
            for ($j = 0; $j <= min($i, 5); $j++) {
                $retMonth = $cohortMonth->copy()->addMonths($j);
                $active   = MemberActivityLog::whereIn('user_id', $cohortUsers)
                    ->whereYear('created_at', $retMonth->year)
                    ->whereMonth('created_at', $retMonth->month)
                    ->distinct('user_id')->count('user_id');
                $retention[] = ['month' => $j, 'users' => $active, 'pct' => $size > 0 ? round($active / $size * 100, 1) : 0];
            }

            $cohortData[] = ['cohort' => $label, 'size' => $size, 'retention' => $retention];
        }

        // Alunos que não acessaram nos últimos 30 dias
        $churned = User::where('tenant_id', $tenantId)
            ->where('role', User::ROLE_ALUNO)
            ->whereDoesntHave('activityLogs', fn ($q) => $q->where('created_at', '>=', now()->subDays(30)))
            ->count();

        $totalStudents = User::where('tenant_id', $tenantId)->where('role', User::ROLE_ALUNO)->count();

        return Inertia::render('Relatorios/Retencao', [
            'cohorts'        => $cohortData,
            'churned'        => $churned,
            'total_students' => $totalStudents,
            'churn_rate'     => $totalStudents > 0 ? round($churned / $totalStudents * 100, 1) : 0,
        ]);
    }

    public function evolucaoEmocional(Request $request): Response
    {
        $tenantId = auth()->user()->tenant_id;
        $months   = max(1, min(12, (int) $request->query('months', 6)));
        $since    = now()->subMonths($months)->startOfMonth();

        // Scores NeuroMap: evolução por área ao longo do tempo
        $neuroEvolution = NeuroUserScore::whereHas('user', fn ($q) => $q->where('tenant_id', $tenantId))
            ->with('area:id,name,color')
            ->where('assessed_at', '>=', $since)
            ->selectRaw("area_id, DATE_FORMAT(assessed_at,'%Y-%m') as month, AVG(score) as avg_score, COUNT(*) as count")
            ->groupByRaw("area_id, DATE_FORMAT(assessed_at,'%Y-%m')")
            ->orderBy('month')
            ->get()
            ->groupBy('area_id')
            ->map(fn ($rows) => [
                'area'   => $rows->first()->area?->name ?? 'Área',
                'color'  => $rows->first()->area?->color ?? '#7c3aed',
                'points' => $rows->map(fn ($r) => ['month' => $r->month, 'avg' => round((float) $r->avg_score, 2), 'count' => (int) $r->count])->values(),
            ])->values();

        // Distribuição de scores atual por área
        $currentDistribution = NeuroUserScore::whereHas('user', fn ($q) => $q->where('tenant_id', $tenantId))
            ->with('area:id,name,color')
            ->whereIn('id', function ($sub) {
                $sub->from('neuro_user_scores as s2')
                    ->selectRaw('MAX(id)')
                    ->groupBy('user_id', 'area_id');
            })
            ->selectRaw('area_id, AVG(score) as avg_score, COUNT(*) as count')
            ->groupBy('area_id')
            ->with('area:id,name,color')
            ->get()
            ->map(fn ($r) => [
                'area'  => $r->area?->name ?? 'Área',
                'color' => $r->area?->color ?? '#7c3aed',
                'avg'   => round((float) $r->avg_score, 2),
                'count' => (int) $r->count,
            ]);

        // Checkpoints: taxa de conclusão por mês
        $checkpointTrend = CheckpointResponse::whereHas('user', fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('completed_at', '>=', $since)
            ->selectRaw("DATE_FORMAT(completed_at,'%Y-%m') as month, COUNT(*) as total")
            ->groupByRaw("DATE_FORMAT(completed_at,'%Y-%m')")->orderBy('month')
            ->get()->map(fn ($r) => ['month' => $r->month, 'total' => (int) $r->total]);

        return Inertia::render('Relatorios/EvolucaoEmocional', [
            'neuro_evolution'      => $neuroEvolution,
            'current_distribution' => $currentDistribution,
            'checkpoint_trend'     => $checkpointTrend,
            'months'               => $months,
        ]);
    }

    public function conteudos(Request $request): Response
    {
        $tenantId = auth()->user()->tenant_id;
        $months   = max(1, min(12, (int) $request->query('months', 3)));
        $since    = now()->subMonths($months)->startOfMonth();

        // Top aulas por conclusões
        $topLessons = MemberLessonProgress::whereHas('user', fn ($q) => $q->where('tenant_id', $tenantId))
            ->whereNotNull('completed_at')->where('completed_at', '>=', $since)
            ->selectRaw('member_lesson_id, COUNT(*) as completions')
            ->groupBy('member_lesson_id')
            ->with('lesson:id,title')
            ->orderByDesc('completions')
            ->limit(20)
            ->get()
            ->map(fn ($r) => ['lesson' => $r->lesson?->title ?? 'Aula', 'completions' => (int) $r->completions]);

        // Taxa de conclusão por produto
        $byProduct = MemberLessonProgress::whereHas('user', fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('completed_at', '>=', $since)
            ->join('products', 'member_lesson_progresses.product_id', '=', 'products.id')
            ->selectRaw('products.name as product_name, COUNT(*) as total, SUM(completed_at IS NOT NULL) as completed')
            ->groupBy('products.name')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'product'   => $r->product_name,
                'total'     => (int) $r->total,
                'completed' => (int) $r->completed,
                'rate'      => $r->total > 0 ? round($r->completed / $r->total * 100, 1) : 0,
            ]);

        // Aulas concluídas por dia
        $byDay = MemberLessonProgress::whereHas('user', fn ($q) => $q->where('tenant_id', $tenantId))
            ->whereNotNull('completed_at')->where('completed_at', '>=', $since)
            ->selectRaw('DATE(completed_at) as day, COUNT(*) as total')
            ->groupBy('day')->orderBy('day')
            ->get()->map(fn ($r) => ['day' => $r->day, 'total' => (int) $r->total]);

        return Inertia::render('Relatorios/Conteudos', [
            'top_lessons' => $topLessons,
            'by_product'  => $byProduct,
            'by_day'      => $byDay,
            'months'      => $months,
            'stats' => [
                'total_completions' => MemberLessonProgress::whereHas('user', fn ($q) => $q->where('tenant_id', $tenantId))->whereNotNull('completed_at')->where('completed_at', '>=', $since)->count(),
                'unique_learners'   => MemberLessonProgress::whereHas('user', fn ($q) => $q->where('tenant_id', $tenantId))->whereNotNull('completed_at')->where('completed_at', '>=', $since)->distinct('user_id')->count('user_id'),
                'total_lessons'     => MemberLesson::whereHas('product', fn ($q) => $q->where('tenant_id', $tenantId))->count(),
            ],
        ]);
    }

    public function profissionaisReport(Request $request): Response
    {
        $tenantId = auth()->user()->tenant_id;
        $months   = max(1, min(12, (int) $request->query('months', 3)));
        $since    = now()->subMonths($months)->startOfMonth();

        // Agendamentos por profissional
        $byProfessional = Appointment::forTenant($tenantId)
            ->where('created_at', '>=', $since)
            ->selectRaw('professional_id, status, COUNT(*) as total')
            ->groupBy('professional_id', 'status')
            ->with('professional:id,name')
            ->get()
            ->groupBy('professional_id')
            ->map(fn ($rows) => [
                'name'      => $rows->first()->professional?->name ?? 'Profissional',
                'total'     => $rows->sum('total'),
                'completed' => (int) ($rows->where('status', 'completed')->first()?->total ?? 0),
                'pending'   => (int) ($rows->where('status', 'pending')->first()?->total ?? 0),
                'cancelled' => (int) ($rows->where('status', 'cancelled')->first()?->total ?? 0),
            ])->values();

        // Avaliações médias por profissional
        $ratings = ProfessionalReview::whereHas('professional', fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('created_at', '>=', $since)
            ->selectRaw('professional_id, AVG(rating) as avg, COUNT(*) as total')
            ->groupBy('professional_id')
            ->with('professional:id,name')
            ->get()
            ->map(fn ($r) => ['name' => $r->professional?->name, 'avg' => round((float) $r->avg, 1), 'total' => (int) $r->total]);

        // Agendamentos por mês
        $byMonth = Appointment::forTenant($tenantId)
            ->where('created_at', '>=', $since)
            ->selectRaw("DATE_FORMAT(created_at,'%Y-%m') as month, COUNT(*) as total")
            ->groupByRaw("DATE_FORMAT(created_at,'%Y-%m')")->orderBy('month')
            ->get()->map(fn ($r) => ['month' => $r->month, 'total' => (int) $r->total]);

        return Inertia::render('Relatorios/Profissionais', [
            'by_professional' => $byProfessional,
            'ratings'         => $ratings,
            'by_month'        => $byMonth,
            'months'          => $months,
            'stats' => [
                'total_appointments' => Appointment::forTenant($tenantId)->where('created_at', '>=', $since)->count(),
                'completed'          => Appointment::forTenant($tenantId)->where('status', 'completed')->where('created_at', '>=', $since)->count(),
                'active_pros'        => Professional::forTenant($tenantId)->where('status', 'approved')->where('is_active', true)->count(),
                'avg_rating'         => ProfessionalReview::whereHas('professional', fn ($q) => $q->where('tenant_id', $tenantId))->avg('rating'),
            ],
        ]);
    }
}
