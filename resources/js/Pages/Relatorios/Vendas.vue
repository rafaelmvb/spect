<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import RelatoriosLayout from '@/Layouts/RelatoriosLayout.vue';
import VueApexCharts from 'vue3-apexcharts';
import { TrendingUp, ShoppingCart, CreditCard, RotateCcw, Tag } from 'lucide-vue-next';

defineOptions({ layout: RelatoriosLayout });

const props = defineProps({
    period: String,
    receita: Number,
    vendas: Number,
    ticket: Number,
    reembolsos: Number,
    porDia: Array,
    porGateway: Array,
    porProduto: Array,
    porCupom: Array,
    porHora: Array,
    porDiaSemana: Array,
});

const periods = [
    { value: '7d', label: '7 dias' },
    { value: '30d', label: '30 dias' },
    { value: '90d', label: '90 dias' },
    { value: 'mes', label: 'Este mês' },
    { value: 'ano', label: 'Este ano' },
    { value: 'total', label: 'Total' },
];

function selectPeriod(p) {
    router.get('/relatorios/vendas', { period: p }, { preserveScroll: true });
}

function fmt(v) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v ?? 0);
}

const diasSemana = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

// Gráfico receita por dia
const chartReceita = computed(() => ({
    chart: { type: 'area', height: 260, toolbar: { show: false }, background: 'transparent' },
    stroke: { curve: 'smooth', width: 2 },
    fill: { type: 'gradient', gradient: { opacityFrom: 0.4, opacityTo: 0.05 } },
    colors: ['#10b981'],
    xaxis: { categories: props.porDia.map(d => d.data), labels: { style: { colors: '#9ca3af' }, rotate: -30 } },
    yaxis: { labels: { formatter: v => 'R$ ' + v.toFixed(0), style: { colors: '#9ca3af' } } },
    tooltip: { y: { formatter: v => fmt(v) } },
    grid: { borderColor: '#374151' },
    theme: { mode: 'dark' },
}));

const seriesReceita = computed(() => [{ name: 'Receita', data: props.porDia.map(d => d.total) }]);

// Gráfico por hora
const chartHora = computed(() => ({
    chart: { type: 'bar', height: 200, toolbar: { show: false }, background: 'transparent' },
    colors: ['#6366f1'],
    xaxis: { categories: Array.from({ length: 24 }, (_, i) => i + 'h'), labels: { style: { colors: '#9ca3af' } } },
    yaxis: { labels: { style: { colors: '#9ca3af' } } },
    grid: { borderColor: '#374151' },
    theme: { mode: 'dark' },
    plotOptions: { bar: { borderRadius: 4 } },
    tooltip: { y: { formatter: v => v + ' vendas' } },
}));

const horaData = computed(() => {
    const arr = Array(24).fill(0);
    props.porHora.forEach(h => { arr[h.hora] = h.qtd; });
    return [{ name: 'Vendas', data: arr }];
});

// Gráfico dia da semana
const chartDow = computed(() => ({
    chart: { type: 'bar', height: 200, toolbar: { show: false }, background: 'transparent' },
    colors: ['#f59e0b'],
    xaxis: { categories: diasSemana, labels: { style: { colors: '#9ca3af' } } },
    yaxis: { labels: { style: { colors: '#9ca3af' } } },
    grid: { borderColor: '#374151' },
    theme: { mode: 'dark' },
    plotOptions: { bar: { borderRadius: 4 } },
}));

const dowData = computed(() => {
    const arr = Array(7).fill(0);
    props.porDiaSemana.forEach(d => { arr[(d.dow - 1 + 7) % 7] = d.qtd; });
    return [{ name: 'Vendas', data: arr }];
});
</script>

<template>
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-bold text-zinc-900 dark:text-white">Vendas & Receita</h1>
            <div class="flex flex-wrap gap-1">
                <button v-for="p in periods" :key="p.value" type="button"
                    class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
                    :class="period === p.value ? 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' : 'text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-700 dark:hover:text-zinc-200'"
                    @click="selectPeriod(p.value)">
                    {{ p.label }}
                </button>
            </div>
        </div>

        <!-- KPIs -->
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div v-for="kpi in [
                { icon: CreditCard, label: 'Receita', value: fmt(receita), color: 'emerald' },
                { icon: ShoppingCart, label: 'Vendas', value: vendas, color: 'blue' },
                { icon: TrendingUp, label: 'Ticket Médio', value: fmt(ticket), color: 'violet' },
                { icon: RotateCcw, label: 'Reembolsado', value: fmt(reembolsos), color: 'red' },
            ]" :key="kpi.label"
                class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-800/50">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ kpi.label }}</p>
                    <component :is="kpi.icon" class="h-4 w-4 text-zinc-400" />
                </div>
                <p class="mt-2 text-2xl font-bold text-zinc-900 dark:text-white">{{ kpi.value }}</p>
            </div>
        </div>

        <!-- Gráfico receita -->
        <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-800/50">
            <h2 class="mb-4 font-semibold text-zinc-800 dark:text-white">Receita por dia</h2>
            <VueApexCharts :options="chartReceita" :series="seriesReceita" height="260" />
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <!-- Por hora -->
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-800/50">
                <h2 class="mb-4 font-semibold text-zinc-800 dark:text-white">Vendas por hora do dia</h2>
                <VueApexCharts :options="chartHora" :series="horaData" height="200" />
            </div>
            <!-- Por dia da semana -->
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-800/50">
                <h2 class="mb-4 font-semibold text-zinc-800 dark:text-white">Vendas por dia da semana</h2>
                <VueApexCharts :options="chartDow" :series="dowData" height="200" />
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <!-- Por produto -->
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-800/50">
                <h2 class="mb-4 font-semibold text-zinc-800 dark:text-white">Top 10 Produtos</h2>
                <div class="space-y-2">
                    <div v-for="p in porProduto" :key="p.nome" class="flex items-center justify-between gap-2 text-sm">
                        <span class="min-w-0 truncate text-zinc-700 dark:text-zinc-300">{{ p.nome }}</span>
                        <div class="flex shrink-0 items-center gap-3">
                            <span class="text-xs text-zinc-400">{{ p.qtd }} vendas</span>
                            <span class="font-semibold text-zinc-900 dark:text-white">{{ fmt(p.total) }}</span>
                        </div>
                    </div>
                    <p v-if="!porProduto.length" class="text-sm text-zinc-400">Sem dados.</p>
                </div>
            </div>

            <!-- Por gateway -->
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-800/50">
                <h2 class="mb-4 font-semibold text-zinc-800 dark:text-white">Por forma de pagamento</h2>
                <div class="space-y-3">
                    <div v-for="g in porGateway" :key="g.gateway" class="flex items-center justify-between text-sm">
                        <span class="text-zinc-700 dark:text-zinc-300">{{ g.label }}</span>
                        <div class="flex items-center gap-3">
                            <span class="text-xs text-zinc-400">{{ g.qtd }}x</span>
                            <span class="font-semibold text-zinc-900 dark:text-white">{{ fmt(g.total) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cupons -->
        <div v-if="porCupom.length" class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-800/50">
            <h2 class="mb-4 flex items-center gap-2 font-semibold text-zinc-800 dark:text-white">
                <Tag class="h-4 w-4" /> Cupons mais usados
            </h2>
            <div class="divide-y divide-zinc-100 dark:divide-zinc-700">
                <div v-for="c in porCupom" :key="c.cupom" class="flex items-center justify-between py-2.5 text-sm">
                    <span class="font-mono text-zinc-700 dark:text-zinc-300">{{ c.cupom }}</span>
                    <div class="flex items-center gap-4">
                        <span class="text-zinc-400">{{ c.qtd }}x</span>
                        <span class="font-semibold text-zinc-900 dark:text-white">{{ fmt(c.total) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
