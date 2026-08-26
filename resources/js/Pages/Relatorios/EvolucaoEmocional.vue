<script setup>
import { computed } from 'vue';
import LayoutInfoprodutor from '@/Layouts/LayoutInfoprodutor.vue';
import VueApexCharts from 'vue3-apexcharts';
import { router } from '@inertiajs/vue3';

defineOptions({ layout: LayoutInfoprodutor });

const props = defineProps({
    by_area:          { type: Array, default: () => [] },
    current:          { type: Array, default: () => [] },
    checkpoint_trend: { type: Array, default: () => [] },
    stats:            { type: Object, default: () => ({}) },
    months:           { type: Number, default: 6 },
});

function setMonths(m) { router.visit('/relatorios/evolucao-emocional?months=' + m, { preserveScroll: true }); }

const evolutionOptions = computed(() => ({
    chart:  { type: 'line', toolbar: { show: false }, background: 'transparent' },
    stroke: { curve: 'smooth', width: 2 },
    xaxis:  { type: 'datetime', labels: { format: 'MMM yy' } },
    yaxis:  { min: 0, max: 10, title: { text: 'Score médio' } },
    legend: { position: 'top' },
    colors: ['#7c3aed','#10b981','#f59e0b','#3b82f6','#ef4444','#8b5cf6'],
    dataLabels: { enabled: false },
    tooltip: { x: { format: 'dd/MM/yyyy' } },
}));

const evolutionSeries = computed(() =>
    props.by_area.map(a => ({
        name: a.area,
        data: a.points.map(p => [new Date(p.month + '-01').getTime(), p.avg]),
    }))
);

const radarOptions = computed(() => ({
    chart:  { type: 'radar', toolbar: { show: false }, background: 'transparent' },
    xaxis:  { categories: props.current.map(c => c.area) },
    yaxis:  { show: false, min: 0, max: 10 },
    colors: ['#7c3aed'],
    fill:   { opacity: 0.2 },
    markers: { size: 4 },
}));

const radarSeries = computed(() => [{ name: 'Score médio', data: props.current.map(c => c.avg) }]);

const checkpointOptions = computed(() => ({
    chart:  { type: 'bar', toolbar: { show: false }, background: 'transparent' },
    xaxis:  { categories: props.checkpoint_trend.map(r => r.month) },
    colors: ['#10b981'],
    plotOptions: { bar: { borderRadius: 4, columnWidth: '50%' } },
    dataLabels: { enabled: false },
}));

const checkpointSeries = computed(() => [{ name: 'Checkpoints', data: props.checkpoint_trend.map(r => r.total) }]);
</script>

<template>
    <div class="space-y-6">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div>
                <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Evolução Emocional</h1>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Scores NeuroMap e checkpoints ao longo do tempo.</p>
            </div>
            <div class="flex gap-2">
                <button v-for="m in [3,6,12]" :key="m" @click="setMonths(m)"
                    class="px-3 py-1.5 rounded-lg text-sm font-medium border transition"
                    :class="months === m ? 'bg-violet-600 text-white border-violet-600' : 'border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-300 hover:bg-zinc-50'">
                    {{ m }}m
                </button>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60 p-4 text-center">
                <p class="text-2xl font-bold text-violet-600">{{ stats.users_assessed ?? 0 }}</p>
                <p class="text-xs text-zinc-500 mt-1">Usuários avaliados</p>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60 p-4 text-center">
                <p class="text-2xl font-bold text-emerald-600">{{ stats.avg_global_score ?? '—' }}</p>
                <p class="text-xs text-zinc-500 mt-1">Score global médio</p>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60 p-4 text-center">
                <p class="text-2xl font-bold text-amber-600">{{ stats.area_highest ?? '—' }}</p>
                <p class="text-xs text-zinc-500 mt-1">Área mais forte</p>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60 p-4 text-center">
                <p class="text-2xl font-bold text-red-500">{{ stats.area_lowest ?? '—' }}</p>
                <p class="text-xs text-zinc-500 mt-1">Área mais fraca</p>
            </div>
        </div>

        <!-- Evolução por área -->
        <div v-if="by_area.length" class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60 p-5">
            <h2 class="font-semibold text-zinc-900 dark:text-white mb-4">Score médio por área ao longo do tempo</h2>
            <VueApexCharts type="line" height="300" :options="evolutionOptions" :series="evolutionSeries" />
        </div>

        <div class="grid md:grid-cols-2 gap-4">
            <!-- Radar atual -->
            <div v-if="current.length" class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60 p-5">
                <h2 class="font-semibold text-zinc-900 dark:text-white mb-4">Perfil atual da turma</h2>
                <VueApexCharts type="radar" height="280" :options="radarOptions" :series="radarSeries" />
            </div>

            <!-- Checkpoints por mês -->
            <div v-if="checkpoint_trend.length" class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60 p-5">
                <h2 class="font-semibold text-zinc-900 dark:text-white mb-4">Checkpoints respondidos por mês</h2>
                <VueApexCharts type="bar" height="280" :options="checkpointOptions" :series="checkpointSeries" />
            </div>
        </div>

        <!-- Tabela por área -->
        <div v-if="current.length" class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60 overflow-hidden">
            <div class="px-5 py-4 border-b border-zinc-100 dark:border-zinc-700">
                <h2 class="font-semibold text-zinc-900 dark:text-white">Score atual por área</h2>
            </div>
            <div class="divide-y divide-zinc-100 dark:divide-zinc-700">
                <div v-for="a in current.slice().sort((x,y) => y.avg - x.avg)" :key="a.area" class="flex items-center gap-4 px-5 py-3">
                    <span class="text-sm font-medium text-zinc-900 dark:text-white w-40 truncate">{{ a.area }}</span>
                    <div class="flex-1 bg-zinc-100 dark:bg-zinc-700 rounded-full h-2.5">
                        <div class="h-2.5 rounded-full bg-violet-600 transition-all" :style="`width:${(a.avg / 10) * 100}%`" />
                    </div>
                    <span class="text-sm font-bold text-zinc-900 dark:text-white w-14 text-right">{{ Number(a.avg).toFixed(1) }}</span>
                    <span class="text-xs text-zinc-400 w-20 text-right">{{ a.users }} usuários</span>
                </div>
            </div>
        </div>
    </div>
</template>
