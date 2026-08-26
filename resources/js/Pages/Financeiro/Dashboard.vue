<script setup>
import { ref, computed } from 'vue';
import LayoutInfoprodutor from '@/Layouts/LayoutInfoprodutor.vue';
import VueApexCharts from 'vue3-apexcharts';
import { TrendingUp, TrendingDown, DollarSign, CreditCard, Users, RefreshCw } from 'lucide-vue-next';
import { router } from '@inertiajs/vue3';

defineOptions({ layout: LayoutInfoprodutor });

const props = defineProps({
    revenue_by_month:     { type: Array, default: () => [] },
    by_product:           { type: Array, default: () => [] },
    by_method:            { type: Array, default: () => [] },
    stats:                { type: Object, default: () => ({}) },
    months:               { type: Number, default: 12 },
});

function fmt(v) {
    return Number(v || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

// Gráfico: Receita por mês
const revenueOptions = computed(() => ({
    chart:  { type: 'area', toolbar: { show: false }, background: 'transparent' },
    xaxis:  { categories: props.revenue_by_month.map(r => r.month) },
    yaxis:  { labels: { formatter: v => 'R$' + Number(v).toLocaleString('pt-BR') } },
    colors: ['#7c3aed'],
    fill:   { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0.05 } },
    stroke: { width: 2, curve: 'smooth' },
    dataLabels: { enabled: false },
    tooltip: { y: { formatter: v => fmt(v) } },
}));

const revenueSeries = computed(() => [{
    name: 'Receita', data: props.revenue_by_month.map(r => r.total),
}]);

// Gráfico: Receita por produto (donut)
const productDonutOptions = computed(() => ({
    chart:  { type: 'donut', background: 'transparent' },
    labels: props.by_product.map(p => p.name),
    colors: ['#7c3aed','#10b981','#f59e0b','#3b82f6','#ef4444','#8b5cf6','#06b6d4','#f97316'],
    legend: { position: 'bottom' },
    dataLabels: { enabled: false },
    plotOptions: { pie: { donut: { size: '65%' } } },
    tooltip: { y: { formatter: v => fmt(v) } },
}));

const productDonutSeries = computed(() => props.by_product.map(p => p.total));

// Método de pagamento
const methodOptions = computed(() => ({
    chart:  { type: 'bar', toolbar: { show: false }, background: 'transparent', horizontal: true },
    xaxis:  { labels: { formatter: v => 'R$' + Number(v).toLocaleString('pt-BR') } },
    colors: ['#7c3aed'],
    plotOptions: { bar: { borderRadius: 4, horizontal: true } },
    dataLabels: { enabled: false },
    tooltip: { y: { formatter: v => fmt(v) } },
}));

const methodSeries = computed(() => [{
    name: 'Receita',
    data: props.by_method.map(m => ({ x: m.method?.toUpperCase() ?? '?', y: m.total })),
}]);

function setMonths(m) {
    router.visit('/financeiro?months=' + m, { preserveScroll: true });
}
</script>

<template>
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div>
                <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Dashboard Financeiro</h1>
                <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-0.5">Visão completa da receita da plataforma.</p>
            </div>
            <div class="flex gap-2">
                <button v-for="m in [3,6,12,24]" :key="m" @click="setMonths(m)"
                    class="px-3 py-1.5 rounded-lg text-sm font-medium border transition"
                    :class="months === m ? 'bg-violet-600 text-white border-violet-600' : 'border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-800'">
                    {{ m }}m
                </button>
            </div>
        </div>

        <!-- Cards principais -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60 p-4">
                <p class="text-xs font-medium text-zinc-500 uppercase tracking-wide">MRR</p>
                <p class="text-2xl font-bold text-violet-600 mt-1">{{ fmt(stats.mrr) }}</p>
                <p class="text-xs text-zinc-400 mt-0.5">{{ stats.active_subscriptions }} assinaturas ativas</p>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60 p-4">
                <p class="text-xs font-medium text-zinc-500 uppercase tracking-wide">ARR</p>
                <p class="text-2xl font-bold text-emerald-600 mt-1">{{ fmt(stats.arr) }}</p>
                <p class="text-xs text-zinc-400 mt-0.5">Projeção anual</p>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60 p-4">
                <p class="text-xs font-medium text-zinc-500 uppercase tracking-wide">Este mês</p>
                <p class="text-2xl font-bold text-zinc-900 dark:text-white mt-1">{{ fmt(stats.this_month) }}</p>
                <p class="text-xs mt-0.5" :class="stats.month_growth >= 0 ? 'text-emerald-600' : 'text-red-500'">
                    <span>{{ stats.month_growth >= 0 ? '↑' : '↓' }} {{ Math.abs(stats.month_growth) }}%</span>
                    <span class="text-zinc-400 ml-1">vs mês anterior</span>
                </p>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60 p-4">
                <p class="text-xs font-medium text-zinc-500 uppercase tracking-wide">Total histórico</p>
                <p class="text-2xl font-bold text-zinc-900 dark:text-white mt-1">{{ fmt(stats.total_revenue) }}</p>
                <p class="text-xs text-zinc-400 mt-0.5">{{ stats.total_orders }} pedidos</p>
            </div>
        </div>

        <!-- Cards secundários -->
        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
            <div class="rounded-2xl border border-red-100 bg-red-50/50 dark:border-red-900/20 dark:bg-red-900/10 p-4">
                <p class="text-xs font-medium text-red-500 uppercase tracking-wide">Reembolsos</p>
                <p class="text-xl font-bold text-red-600 mt-1">{{ fmt(stats.refunds_total) }}</p>
                <p class="text-xs text-red-400 mt-0.5">{{ stats.refunds_count }} pedidos</p>
            </div>
            <div class="rounded-2xl border border-amber-100 bg-amber-50/50 dark:border-amber-900/20 dark:bg-amber-900/10 p-4">
                <p class="text-xs font-medium text-amber-600 uppercase tracking-wide">Comissões pendentes</p>
                <p class="text-xl font-bold text-amber-700 mt-1">{{ fmt(stats.commissions_pending) }}</p>
                <a href="/financeiro/comissoes" class="text-xs text-amber-600 hover:underline mt-0.5 block">Ver comissões →</a>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60 p-4">
                <p class="text-xs font-medium text-zinc-500 uppercase tracking-wide">Ticket médio</p>
                <p class="text-xl font-bold text-zinc-900 dark:text-white mt-1">
                    {{ stats.total_orders > 0 ? fmt(stats.total_revenue / stats.total_orders) : '—' }}
                </p>
                <p class="text-xs text-zinc-400 mt-0.5">por pedido</p>
            </div>
        </div>

        <!-- Gráfico receita por mês -->
        <div v-if="revenue_by_month.length" class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60 p-5">
            <h2 class="font-semibold text-zinc-900 dark:text-white mb-4">Receita por mês</h2>
            <VueApexCharts type="area" height="280" :options="revenueOptions" :series="revenueSeries" />
        </div>

        <!-- Produto e método -->
        <div class="grid md:grid-cols-2 gap-4">
            <div v-if="by_product.length" class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60 p-5">
                <h2 class="font-semibold text-zinc-900 dark:text-white mb-4">Receita por produto</h2>
                <VueApexCharts type="donut" height="260" :options="productDonutOptions" :series="productDonutSeries" />
            </div>
            <div v-if="by_method.length" class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60 p-5">
                <h2 class="font-semibold text-zinc-900 dark:text-white mb-4">Por método de pagamento</h2>
                <VueApexCharts type="bar" height="260" :options="methodOptions" :series="methodSeries" />
            </div>
        </div>

        <!-- Tabela top produtos -->
        <div v-if="by_product.length" class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60 overflow-hidden">
            <div class="px-5 py-4 border-b border-zinc-100 dark:border-zinc-700">
                <h2 class="font-semibold text-zinc-900 dark:text-white">Top produtos</h2>
            </div>
            <table class="min-w-full divide-y divide-zinc-100 dark:divide-zinc-700">
                <thead class="bg-zinc-50 dark:bg-zinc-800">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-zinc-500">Produto</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-zinc-500">Pedidos</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-zinc-500">Receita</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700 bg-white dark:bg-zinc-800/60">
                    <tr v-for="p in by_product" :key="p.name">
                        <td class="px-4 py-3 text-sm text-zinc-900 dark:text-white">{{ p.name }}</td>
                        <td class="px-4 py-3 text-sm text-zinc-600 dark:text-zinc-400 text-right">{{ p.count }}</td>
                        <td class="px-4 py-3 text-sm font-semibold text-zinc-900 dark:text-white text-right">{{ fmt(p.total) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
