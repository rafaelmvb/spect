<script setup>
import { computed } from 'vue';
import MemberAreaAppLayout from '@/Layouts/MemberAreaAppLayout.vue';
import VueApexCharts from 'vue3-apexcharts';
import { BrainCircuit, TrendingUp, Info } from 'lucide-vue-next';

defineOptions({ layout: MemberAreaAppLayout });

const props = defineProps({
    product:      { type: Object, required: true },
    areas:        { type: Array, default: () => [] },
    radar_series: { type: Array, default: () => [] },
    radar_labels: { type: Array, default: () => [] },
    radar_colors: { type: Array, default: () => [] },
    timeline:     { type: Array, default: () => [] },
    has_data:     { type: Boolean, default: false },
    base_url:     { type: String, default: '' },
    slug:         { type: String, default: '' },
});

const isDark = computed(() => document.documentElement.classList.contains('dark'));

const radarOptions = computed(() => ({
    chart: { type: 'radar', toolbar: { show: false }, background: 'transparent' },
    series: [{ name: 'Seu progresso', data: props.radar_series }],
    labels: props.radar_labels,
    colors: ['#8b5cf6'],
    fill: { opacity: 0.25 },
    stroke: { width: 2 },
    markers: { size: 4 },
    yaxis: { show: false, min: 0, max: 100 },
    xaxis: {
        labels: {
            style: { colors: isDark.value ? '#a1a1aa' : '#52525b', fontSize: '12px' },
        },
    },
    plotOptions: {
        radar: {
            polygons: {
                strokeColors: isDark.value ? '#3f3f46' : '#e4e4e7',
                fill: { colors: isDark.value ? ['#1c1c1e', '#27272a'] : ['#fafafa', '#f4f4f5'] },
            },
        },
    },
    tooltip: { y: { formatter: (v) => Math.round(v) + '%' } },
    theme: { mode: isDark.value ? 'dark' : 'light' },
}));

// Timeline: linha por área
const timelineCategories = computed(() => props.timeline.map(t => t.date));
const timelineSeries = computed(() => {
    if (!props.timeline.length) return [];
    const areaNames = props.areas.map(a => a.name);
    return areaNames.map((name, i) => ({
        name,
        data: props.timeline.map(t => t[name] ?? null),
        color: props.areas[i]?.color ?? '#8b5cf6',
    }));
});
const lineOptions = computed(() => ({
    chart: { type: 'line', toolbar: { show: false }, background: 'transparent' },
    stroke: { width: 2.5, curve: 'smooth' },
    markers: { size: 4 },
    xaxis: {
        categories: timelineCategories.value,
        labels: { formatter: (v) => v?.slice(5), style: { colors: isDark.value ? '#a1a1aa' : '#71717a' } },
    },
    yaxis: { min: 0, max: 100, labels: { formatter: v => Math.round(v) + '%' } },
    tooltip: { y: { formatter: v => (v ?? 0).toFixed(1) + '%' } },
    legend: { position: 'top' },
    theme: { mode: isDark.value ? 'dark' : 'light' },
    grid: { borderColor: isDark.value ? '#3f3f46' : '#e4e4e7' },
}));

function scoreColor(v) {
    if (v === null) return 'text-zinc-300 dark:text-zinc-600';
    if (v >= 75) return 'text-emerald-600 dark:text-emerald-400';
    if (v >= 40) return 'text-amber-500 dark:text-amber-400';
    return 'text-red-500 dark:text-red-400';
}

function barWidth(v) {
    return v !== null ? Math.max(4, v) + '%' : '0%';
}
</script>

<template>
    <div class="mx-auto max-w-3xl space-y-8 px-4 py-8">
        <!-- Header -->
        <div class="flex items-center gap-3">
            <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-purple-100 dark:bg-purple-900/30">
                <BrainCircuit class="h-6 w-6 text-purple-600 dark:text-purple-400" />
            </div>
            <div>
                <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Mapa Neurofuncional</h1>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Seu perfil emocional e comportamental personalizado.</p>
            </div>
        </div>

        <!-- Sem dados -->
        <div v-if="!has_data"
            class="flex flex-col items-center gap-4 rounded-2xl border-2 border-dashed border-zinc-200 py-16 text-center dark:border-zinc-700">
            <BrainCircuit class="h-12 w-12 text-zinc-200 dark:text-zinc-700" />
            <div>
                <p class="font-semibold text-zinc-600 dark:text-zinc-300">Mapa ainda não disponível</p>
                <p class="text-sm text-zinc-400">Seus indicadores serão preenchidos pelo profissional acompanhante.</p>
            </div>
        </div>

        <template v-else>
            <!-- Radar -->
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-800/50">
                <h2 class="mb-4 font-semibold text-zinc-900 dark:text-white">Visão geral por área</h2>
                <VueApexCharts type="radar" height="320" :options="radarOptions" :series="radarOptions.series" />
            </div>

            <!-- Indicadores por área -->
            <div class="space-y-4">
                <div v-for="area in areas" :key="area.id"
                    class="rounded-2xl border border-zinc-200 bg-white overflow-hidden dark:border-zinc-700 dark:bg-zinc-800/50">
                    <!-- Header da área -->
                    <div class="flex items-center gap-3 border-b border-zinc-100 px-4 py-3 dark:border-zinc-700"
                        :style="{ borderLeftColor: area.color, borderLeftWidth: '4px' }">
                        <div class="h-3 w-3 rounded-full" :style="{ background: area.color }" />
                        <h3 class="font-semibold text-zinc-900 dark:text-white">{{ area.name }}</h3>
                        <span v-if="area.avg !== null"
                            class="ml-auto text-sm font-bold"
                            :class="scoreColor(area.avg)">
                            {{ area.avg }}%
                        </span>
                        <span v-else class="ml-auto text-xs text-zinc-400">Sem dados</span>
                    </div>
                    <!-- Indicadores -->
                    <div class="divide-y divide-zinc-50 dark:divide-zinc-800">
                        <div v-for="ind in area.indicators" :key="ind.id" class="px-4 py-3">
                            <div class="flex items-center justify-between gap-2 mb-1.5">
                                <span class="text-sm text-zinc-700 dark:text-zinc-300">{{ ind.name }}</span>
                                <span class="text-sm font-bold tabular-nums" :class="scoreColor(ind.normalized)">
                                    {{ ind.normalized !== null ? ind.normalized + '%' : '—' }}
                                </span>
                            </div>
                            <div class="h-1.5 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-700">
                                <div class="h-full rounded-full transition-all duration-500"
                                    :style="{ width: barWidth(ind.normalized), background: area.color }" />
                            </div>
                            <div v-if="ind.notes" class="mt-1 text-xs text-zinc-400 italic">{{ ind.notes }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Timeline (evolução) -->
            <div v-if="timeline.length > 1" class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-800/50">
                <div class="mb-4 flex items-center gap-2">
                    <TrendingUp class="h-5 w-5 text-purple-500" />
                    <h2 class="font-semibold text-zinc-900 dark:text-white">Evolução ao longo do tempo</h2>
                </div>
                <VueApexCharts type="line" height="240" :options="lineOptions" :series="timelineSeries" />
            </div>

            <!-- Nota -->
            <div class="flex items-start gap-2 rounded-xl border border-zinc-100 bg-zinc-50 px-4 py-3 text-xs text-zinc-500 dark:border-zinc-800 dark:bg-zinc-800/30">
                <Info class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                Os valores são normalizados de 0% a 100% para facilitar a comparação entre indicadores com escalas diferentes.
            </div>
        </template>
    </div>
</template>
