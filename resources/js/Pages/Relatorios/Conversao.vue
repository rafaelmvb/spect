<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import RelatoriosLayout from '@/Layouts/RelatoriosLayout.vue';
import VueApexCharts from 'vue3-apexcharts';
import { MousePointerClick, TrendingUp, AlertCircle } from 'lucide-vue-next';

defineOptions({ layout: RelatoriosLayout });

const props = defineProps({
    period: String,
    totalSessoes: Number,
    visit: Number,
    formStarted: Number,
    formFilled: Number,
    converted: Number,
    taxaConversao: Number,
    taxaForm: Number,
    porProduto: Array,
    porUtmSource: Array,
    porHora: Array,
    abandonoVisit: Number,
    abandonoForm: Number,
});

const periods = [
    { value: '7d', label: '7 dias' },
    { value: '30d', label: '30 dias' },
    { value: '90d', label: '90 dias' },
    { value: 'mes', label: 'Este mês' },
    { value: 'ano', label: 'Este ano' },
];

function selectPeriod(p) {
    router.get('/relatorios/conversao', { period: p }, { preserveScroll: true });
}

function fmt(v) { return new Intl.NumberFormat('pt-BR').format(v ?? 0); }

// Funil
const chartFunil = computed(() => ({
    chart: { type: 'bar', height: 240, toolbar: { show: false }, background: 'transparent' },
    colors: ['#6366f1', '#8b5cf6', '#a78bfa', '#10b981'],
    plotOptions: { bar: { horizontal: true, borderRadius: 4, dataLabels: { position: 'right' } } },
    xaxis: { categories: ['Visitas', 'Form iniciado', 'Form preenchido', 'Convertidos'], labels: { style: { colors: '#9ca3af' } } },
    yaxis: { labels: { style: { colors: '#9ca3af' } } },
    grid: { borderColor: '#374151' },
    theme: { mode: 'dark' },
    dataLabels: { enabled: true, style: { colors: ['#fff'] } },
    tooltip: { y: { formatter: v => fmt(v) + ' sessões' } },
}));

const seriesFunil = computed(() => [{
    name: 'Sessões',
    data: [props.totalSessoes, props.formStarted + props.formFilled + props.converted, props.formFilled + props.converted, props.converted],
}]);

// Por hora
const chartHora = computed(() => ({
    chart: { type: 'bar', height: 200, toolbar: { show: false }, background: 'transparent' },
    colors: ['#10b981'],
    xaxis: { categories: Array.from({ length: 24 }, (_, i) => i + 'h'), labels: { style: { colors: '#9ca3af' }, rotate: -45 } },
    yaxis: { labels: { style: { colors: '#9ca3af' } } },
    grid: { borderColor: '#374151' },
    theme: { mode: 'dark' },
    plotOptions: { bar: { borderRadius: 3 } },
    tooltip: { y: { formatter: v => v + ' conversões' } },
}));

const horaData = computed(() => {
    const arr = Array(24).fill(0);
    props.porHora.forEach(h => { arr[h.hora] = h.qtd; });
    return [{ name: 'Conversões', data: arr }];
});
</script>

<template>
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-bold text-zinc-900 dark:text-white">Conversão & Funil</h1>
            <div class="flex flex-wrap gap-1">
                <button v-for="p in periods" :key="p.value" type="button"
                    class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
                    :class="period === p.value ? 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' : 'text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-700'"
                    @click="selectPeriod(p.value)">{{ p.label }}</button>
            </div>
        </div>

        <!-- KPIs -->
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-800/50">
                <p class="text-sm text-zinc-500">Total de sessões</p>
                <p class="mt-2 text-2xl font-bold text-zinc-900 dark:text-white">{{ fmt(totalSessoes) }}</p>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-800/50">
                <p class="text-sm text-zinc-500">Convertidos</p>
                <p class="mt-2 text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ fmt(converted) }}</p>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-800/50">
                <p class="text-sm text-zinc-500">Taxa de conversão</p>
                <p class="mt-2 text-2xl font-bold text-violet-600 dark:text-violet-400">{{ taxaConversao }}%</p>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-800/50">
                <p class="text-sm text-zinc-500">Abandonos</p>
                <p class="mt-2 text-2xl font-bold text-red-600 dark:text-red-400">{{ fmt(abandonoVisit + abandonoForm) }}</p>
            </div>
        </div>

        <!-- Funil -->
        <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-800/50">
            <h2 class="mb-4 font-semibold text-zinc-800 dark:text-white">Funil de checkout</h2>
            <VueApexCharts :options="chartFunil" :series="seriesFunil" height="240" />
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <!-- Por produto -->
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-800/50">
                <h2 class="mb-4 font-semibold text-zinc-800 dark:text-white">Taxa de conversão por produto</h2>
                <div class="space-y-3">
                    <div v-for="p in porProduto" :key="p.produto">
                        <div class="flex items-center justify-between text-sm">
                            <span class="min-w-0 truncate text-zinc-700 dark:text-zinc-300">{{ p.produto }}</span>
                            <span class="shrink-0 font-semibold" :class="p.taxa >= 5 ? 'text-emerald-600' : 'text-amber-600'">{{ p.taxa }}%</span>
                        </div>
                        <div class="mt-1 h-1.5 w-full rounded-full bg-zinc-100 dark:bg-zinc-700">
                            <div class="h-1.5 rounded-full bg-emerald-500 transition-all" :style="{ width: Math.min(p.taxa * 2, 100) + '%' }" />
                        </div>
                        <p class="mt-0.5 text-xs text-zinc-400">{{ fmt(p.convertidos) }} / {{ fmt(p.total) }} sessões</p>
                    </div>
                    <p v-if="!porProduto.length" class="text-sm text-zinc-400">Sem dados.</p>
                </div>
            </div>

            <!-- Por UTM source -->
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-800/50">
                <h2 class="mb-4 font-semibold text-zinc-800 dark:text-white">Origem do tráfego (UTM Source)</h2>
                <div class="divide-y divide-zinc-100 dark:divide-zinc-700">
                    <div v-for="s in porUtmSource" :key="s.source" class="flex items-center justify-between py-2 text-sm">
                        <span class="font-mono text-zinc-700 dark:text-zinc-300">{{ s.source }}</span>
                        <div class="flex items-center gap-3">
                            <span class="text-zinc-400">{{ fmt(s.total) }} sessões</span>
                            <span class="font-semibold text-emerald-600 dark:text-emerald-400">{{ fmt(s.convertidos) }} conv.</span>
                        </div>
                    </div>
                    <p v-if="!porUtmSource.length" class="py-2 text-sm text-zinc-400">Sem dados de UTM.</p>
                </div>
            </div>
        </div>

        <!-- Por hora -->
        <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-800/50">
            <h2 class="mb-4 font-semibold text-zinc-800 dark:text-white">Conversões por hora do dia</h2>
            <VueApexCharts :options="chartHora" :series="horaData" height="200" />
        </div>
    </div>
</template>
