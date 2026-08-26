<script setup>
import { computed } from 'vue';
import LayoutInfoprodutor from '@/Layouts/LayoutInfoprodutor.vue';
import VueApexCharts from 'vue3-apexcharts';
import { router } from '@inertiajs/vue3';

defineOptions({ layout: LayoutInfoprodutor });

const props = defineProps({
    dau:             { type: Array, default: () => [] },
    wau:             { type: Array, default: () => [] },
    lessons_week:    { type: Array, default: () => [] },
    checkpoints_mes: { type: Array, default: () => [] },
    journeys_week:   { type: Array, default: () => [] },
    stats:           { type: Object, default: () => ({}) },
    months:          { type: Number, default: 3 },
});

const dauOptions = computed(() => ({
    chart:  { type: 'area', toolbar: { show: false }, background: 'transparent' },
    xaxis:  { categories: props.dau.map(r => r.day), labels: { rotate: -45, style: { fontSize: '10px' } } },
    colors: ['#7c3aed'],
    fill:   { type: 'gradient', gradient: { opacityFrom: 0.4, opacityTo: 0.05 } },
    stroke: { width: 2, curve: 'smooth' },
    dataLabels: { enabled: false },
    yaxis:  { title: { text: 'Usuários únicos' } },
}));
const dauSeries = computed(() => [{ name: 'Usuários ativos', data: props.dau.map(r => r.users) }]);

const lessonsOptions = computed(() => ({
    chart:  { type: 'bar', toolbar: { show: false }, background: 'transparent' },
    xaxis:  { categories: props.lessons_week.map(r => 'S' + r.week.slice(-2)) },
    colors: ['#10b981'],
    plotOptions: { bar: { borderRadius: 4, columnWidth: '50%' } },
    dataLabels: { enabled: false },
}));
const lessonsSeries = computed(() => [{ name: 'Aulas concluídas', data: props.lessons_week.map(r => r.total) }]);

const checkpointsOptions = computed(() => ({
    chart:  { type: 'bar', toolbar: { show: false }, background: 'transparent' },
    xaxis:  { categories: props.checkpoints_mes.map(r => r.month) },
    colors: ['#f59e0b'],
    plotOptions: { bar: { borderRadius: 4, columnWidth: '50%' } },
    dataLabels: { enabled: false },
}));
const checkpointsSeries = computed(() => [{ name: 'Checkpoints', data: props.checkpoints_mes.map(r => r.total) }]);

function setMonths(m) { router.visit('/relatorios/engajamento?months=' + m, { preserveScroll: true }); }
</script>

<template>
    <div class="space-y-6">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div>
                <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Engajamento</h1>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Atividade e uso da plataforma.</p>
            </div>
            <div class="flex gap-2">
                <button v-for="m in [1,3,6,12]" :key="m" @click="setMonths(m)"
                    class="px-3 py-1.5 rounded-lg text-sm font-medium border transition"
                    :class="months === m ? 'bg-violet-600 text-white border-violet-600' : 'border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-300 hover:bg-zinc-50'">
                    {{ m }}m
                </button>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60 p-4 text-center">
                <p class="text-2xl font-bold text-violet-600">{{ stats.avg_dau ?? 0 }}</p>
                <p class="text-xs text-zinc-500 mt-1">DAU médio (30d)</p>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60 p-4 text-center">
                <p class="text-2xl font-bold text-emerald-600">{{ stats.lessons_total ?? 0 }}</p>
                <p class="text-xs text-zinc-500 mt-1">Aulas concluídas</p>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60 p-4 text-center">
                <p class="text-2xl font-bold text-amber-600">{{ stats.checkpoints_total ?? 0 }}</p>
                <p class="text-xs text-zinc-500 mt-1">Checkpoints respondidos</p>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60 p-4 text-center">
                <p class="text-2xl font-bold text-blue-600">{{ stats.journey_steps ?? 0 }}</p>
                <p class="text-xs text-zinc-500 mt-1">Etapas de jornada</p>
            </div>
        </div>

        <div v-if="dau.length" class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60 p-5">
            <h2 class="font-semibold text-zinc-900 dark:text-white mb-4">Usuários ativos por dia (30d)</h2>
            <VueApexCharts type="area" height="220" :options="dauOptions" :series="dauSeries" />
        </div>

        <div class="grid md:grid-cols-2 gap-4">
            <div v-if="lessons_week.length" class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60 p-5">
                <h2 class="font-semibold text-zinc-900 dark:text-white mb-4">Aulas concluídas por semana</h2>
                <VueApexCharts type="bar" height="220" :options="lessonsOptions" :series="lessonsSeries" />
            </div>
            <div v-if="checkpoints_mes.length" class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60 p-5">
                <h2 class="font-semibold text-zinc-900 dark:text-white mb-4">Checkpoints por mês</h2>
                <VueApexCharts type="bar" height="220" :options="checkpointsOptions" :series="checkpointsSeries" />
            </div>
        </div>
    </div>
</template>
