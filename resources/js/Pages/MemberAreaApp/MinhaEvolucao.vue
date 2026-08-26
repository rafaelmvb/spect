<script setup>
import { computed } from 'vue';
import VueApexCharts from 'vue3-apexcharts';

const props = defineProps({
    slug:              { type: String, required: true },
    neuro_scores:      { type: Array, default: () => [] },
    latest_scores:     { type: Array, default: () => [] },
    journey_progress:  { type: Array, default: () => [] },
    checkpoints_month: { type: Array, default: () => [] },
    lessons_week:      { type: Array, default: () => [] },
    activity_heatmap:  { type: Array, default: () => [] },
    stats:             { type: Object, default: () => ({}) },
});

// Gráfico: Scores NeuroMap ao longo do tempo
const neuroChartOptions = computed(() => ({
    chart:  { type: 'line', toolbar: { show: false }, background: 'transparent' },
    stroke: { curve: 'smooth', width: 2 },
    xaxis:  { type: 'datetime', labels: { format: 'MMM yy' } },
    yaxis:  { min: 0, max: 10, title: { text: 'Score' } },
    legend: { position: 'top' },
    colors: props.neuro_scores.map(s => s.color),
    tooltip: { x: { format: 'dd/MM/yyyy' } },
    theme: { mode: 'light' },
}));

const neuroSeries = computed(() =>
    props.neuro_scores.map(s => ({
        name: s.area,
        data: s.points.map(p => [new Date(p.date).getTime(), p.score]),
    }))
);

// Gráfico: Checkpoints por mês
const checkpointsOptions = computed(() => ({
    chart:  { type: 'bar', toolbar: { show: false }, background: 'transparent' },
    xaxis:  { categories: props.checkpoints_month.map(r => r.month) },
    colors: ['#7c3aed'],
    plotOptions: { bar: { borderRadius: 6, columnWidth: '50%' } },
    dataLabels: { enabled: false },
}));

const checkpointsSeries = computed(() => [{
    name: 'Checkpoints', data: props.checkpoints_month.map(r => r.total),
}]);

// Gráfico: Aulas por semana
const lessonsOptions = computed(() => ({
    chart:  { type: 'area', toolbar: { show: false }, background: 'transparent' },
    xaxis:  { categories: props.lessons_week.map(r => `S${r.week.slice(-2)}`) },
    colors: ['#10b981'],
    fill:   { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05 } },
    stroke: { width: 2, curve: 'smooth' },
    dataLabels: { enabled: false },
}));

const lessonsSeries = computed(() => [{
    name: 'Aulas concluídas', data: props.lessons_week.map(r => r.total),
}]);

// Radar: scores atuais por área
const radarOptions = computed(() => ({
    chart:  { type: 'radar', toolbar: { show: false }, background: 'transparent' },
    xaxis:  { categories: props.latest_scores.map(s => s.area) },
    yaxis:  { show: false, min: 0, max: 10 },
    colors: ['#7c3aed'],
    fill:   { opacity: 0.2 },
    markers: { size: 4 },
    plotOptions: { radar: { polygons: { connectorColors: '#e5e7eb' } } },
}));

const radarSeries = computed(() => [{
    name: 'Score atual', data: props.latest_scores.map(s => s.score),
}]);
</script>

<template>
    <div class="space-y-6 px-4 py-6 max-w-5xl mx-auto">
        <div>
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Minha Evolução</h1>
            <p class="text-sm text-zinc-500 mt-1">Acompanhe seu progresso ao longo do tempo.</p>
        </div>

        <!-- Stats cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="rounded-2xl bg-white dark:bg-zinc-800 border border-zinc-100 dark:border-zinc-700 p-4 text-center">
                <p class="text-2xl font-bold text-violet-600">{{ stats.lessons_completed ?? 0 }}</p>
                <p class="text-xs text-zinc-500 mt-1">Aulas concluídas</p>
            </div>
            <div class="rounded-2xl bg-white dark:bg-zinc-800 border border-zinc-100 dark:border-zinc-700 p-4 text-center">
                <p class="text-2xl font-bold text-emerald-600">{{ stats.journeys_active ?? 0 }}</p>
                <p class="text-xs text-zinc-500 mt-1">Jornadas ativas</p>
            </div>
            <div class="rounded-2xl bg-white dark:bg-zinc-800 border border-zinc-100 dark:border-zinc-700 p-4 text-center">
                <p class="text-2xl font-bold text-blue-600">{{ stats.checkpoints_done ?? 0 }}</p>
                <p class="text-xs text-zinc-500 mt-1">Checkpoints respondidos</p>
            </div>
            <div class="rounded-2xl bg-white dark:bg-zinc-800 border border-zinc-100 dark:border-zinc-700 p-4 text-center">
                <p class="text-2xl font-bold text-amber-600">{{ stats.avg_neuro_score ?? '—' }}</p>
                <p class="text-xs text-zinc-500 mt-1">Score médio NeuroMap</p>
            </div>
        </div>

        <!-- NeuroMap evolution -->
        <div v-if="neuro_scores.length" class="rounded-2xl bg-white dark:bg-zinc-800 border border-zinc-100 dark:border-zinc-700 p-5">
            <h2 class="font-semibold text-zinc-900 dark:text-white mb-4">Evolução NeuroMap</h2>
            <VueApexCharts type="line" height="280" :options="neuroChartOptions" :series="neuroSeries" />
        </div>

        <!-- Radar atual + Checkpoints -->
        <div class="grid md:grid-cols-2 gap-4">
            <div v-if="latest_scores.length" class="rounded-2xl bg-white dark:bg-zinc-800 border border-zinc-100 dark:border-zinc-700 p-5">
                <h2 class="font-semibold text-zinc-900 dark:text-white mb-4">Scores atuais</h2>
                <VueApexCharts type="radar" height="260" :options="radarOptions" :series="radarSeries" />
            </div>
            <div v-if="checkpoints_month.length" class="rounded-2xl bg-white dark:bg-zinc-800 border border-zinc-100 dark:border-zinc-700 p-5">
                <h2 class="font-semibold text-zinc-900 dark:text-white mb-4">Checkpoints por mês</h2>
                <VueApexCharts type="bar" height="260" :options="checkpointsOptions" :series="checkpointsSeries" />
            </div>
        </div>

        <!-- Aulas por semana -->
        <div v-if="lessons_week.length" class="rounded-2xl bg-white dark:bg-zinc-800 border border-zinc-100 dark:border-zinc-700 p-5">
            <h2 class="font-semibold text-zinc-900 dark:text-white mb-4">Aulas concluídas por semana</h2>
            <VueApexCharts type="area" height="220" :options="lessonsOptions" :series="lessonsSeries" />
        </div>

        <!-- Jornadas -->
        <div v-if="journey_progress.length" class="rounded-2xl bg-white dark:bg-zinc-800 border border-zinc-100 dark:border-zinc-700 p-5">
            <h2 class="font-semibold text-zinc-900 dark:text-white mb-4">Progresso nas jornadas</h2>
            <div class="space-y-3">
                <div v-for="j in journey_progress" :key="j.journey" class="flex items-center gap-3">
                    <span class="text-sm text-zinc-700 dark:text-zinc-300 w-48 truncate">{{ j.journey }}</span>
                    <div class="flex-1 bg-zinc-100 dark:bg-zinc-700 rounded-full h-2">
                        <div class="h-2 rounded-full bg-violet-600 transition-all" :style="`width:${Math.min(j.steps * 10, 100)}%`" />
                    </div>
                    <span class="text-xs text-zinc-500 w-12 text-right">{{ j.steps }} etapas</span>
                </div>
            </div>
        </div>

        <!-- Estado vazio -->
        <div v-if="!neuro_scores.length && !lessons_week.length && !checkpoints_month.length"
            class="text-center py-16 text-zinc-400">
            <p class="text-lg font-medium">Ainda sem dados de evolução.</p>
            <p class="text-sm mt-1">Conclua aulas, checkpoints e avaliações para ver seu progresso aqui.</p>
        </div>
    </div>
</template>
