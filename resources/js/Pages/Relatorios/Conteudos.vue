<script setup>
import { computed } from 'vue';
import LayoutInfoprodutor from '@/Layouts/LayoutInfoprodutor.vue';
import VueApexCharts from 'vue3-apexcharts';
import { router } from '@inertiajs/vue3';

defineOptions({ layout: LayoutInfoprodutor });

const props = defineProps({
    top_lessons:    { type: Array, default: () => [] },
    by_product:     { type: Array, default: () => [] },
    by_day:         { type: Array, default: () => [] },
    stats:          { type: Object, default: () => ({}) },
    months:         { type: Number, default: 3 },
});

function setMonths(m) { router.visit('/relatorios/conteudos?months=' + m, { preserveScroll: true }); }

const byDayOptions = computed(() => ({
    chart:  { type: 'area', toolbar: { show: false }, background: 'transparent' },
    xaxis:  { categories: props.by_day.map(r => r.day), labels: { rotate: -45, style: { fontSize: '10px' } } },
    colors: ['#7c3aed'],
    fill:   { type: 'gradient', gradient: { opacityFrom: 0.35, opacityTo: 0.05 } },
    stroke: { width: 2, curve: 'smooth' },
    dataLabels: { enabled: false },
}));
const byDaySeries = computed(() => [{ name: 'Conclusões', data: props.by_day.map(r => r.total) }]);

const byProductOptions = computed(() => ({
    chart:  { type: 'bar', toolbar: { show: false }, background: 'transparent' },
    xaxis:  { categories: props.by_product.map(r => r.name), labels: { style: { fontSize: '10px' } } },
    yaxis:  { max: 100, labels: { formatter: v => v + '%' } },
    colors: ['#10b981'],
    plotOptions: { bar: { borderRadius: 4, columnWidth: '50%' } },
    dataLabels: { enabled: false },
    tooltip:  { y: { formatter: v => v + '%' } },
}));
const byProductSeries = computed(() => [{ name: 'Taxa de conclusão', data: props.by_product.map(r => Math.round(r.completion_rate)) }]);
</script>

<template>
    <div class="space-y-6">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div>
                <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Conteúdos</h1>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Desempenho de aulas e taxas de conclusão.</p>
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
                <p class="text-2xl font-bold text-violet-600">{{ stats.total_completions ?? 0 }}</p>
                <p class="text-xs text-zinc-500 mt-1">Conclusões totais</p>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60 p-4 text-center">
                <p class="text-2xl font-bold text-emerald-600">{{ stats.unique_lessons ?? 0 }}</p>
                <p class="text-xs text-zinc-500 mt-1">Aulas distintas assistidas</p>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60 p-4 text-center">
                <p class="text-2xl font-bold text-amber-600">{{ stats.avg_completion_rate ? Math.round(stats.avg_completion_rate) + '%' : '—' }}</p>
                <p class="text-xs text-zinc-500 mt-1">Taxa média de conclusão</p>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60 p-4 text-center">
                <p class="text-2xl font-bold text-blue-600 truncate">{{ stats.top_lesson_title ?? '—' }}</p>
                <p class="text-xs text-zinc-500 mt-1">Aula mais assistida</p>
            </div>
        </div>

        <!-- Conclusões por dia -->
        <div v-if="by_day.length" class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60 p-5">
            <h2 class="font-semibold text-zinc-900 dark:text-white mb-4">Conclusões de aulas por dia</h2>
            <VueApexCharts type="area" height="220" :options="byDayOptions" :series="byDaySeries" />
        </div>

        <!-- Taxa de conclusão por produto -->
        <div v-if="by_product.length" class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60 p-5">
            <h2 class="font-semibold text-zinc-900 dark:text-white mb-4">Taxa de conclusão por produto</h2>
            <VueApexCharts type="bar" height="220" :options="byProductOptions" :series="byProductSeries" />
        </div>

        <!-- Top aulas -->
        <div v-if="top_lessons.length" class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60 overflow-hidden">
            <div class="px-5 py-4 border-b border-zinc-100 dark:border-zinc-700">
                <h2 class="font-semibold text-zinc-900 dark:text-white">Top aulas por conclusões</h2>
            </div>
            <div class="divide-y divide-zinc-100 dark:divide-zinc-700">
                <div v-for="(lesson, i) in top_lessons" :key="lesson.lesson_id" class="flex items-center gap-4 px-5 py-3">
                    <span class="text-xs font-bold text-zinc-400 w-6">{{ i + 1 }}</span>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-zinc-900 dark:text-white truncate">{{ lesson.title }}</p>
                        <p class="text-xs text-zinc-400">{{ lesson.product_name }}</p>
                    </div>
                    <div class="text-right shrink-0">
                        <p class="text-sm font-bold text-violet-600">{{ lesson.completions }}</p>
                        <p class="text-xs text-zinc-400">conclusões</p>
                    </div>
                    <div class="w-24 bg-zinc-100 dark:bg-zinc-700 rounded-full h-1.5 shrink-0">
                        <div class="h-1.5 rounded-full bg-violet-600"
                            :style="`width:${top_lessons[0].completions > 0 ? (lesson.completions / top_lessons[0].completions) * 100 : 0}%`" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
