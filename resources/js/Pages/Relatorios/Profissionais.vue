<script setup>
import { computed } from 'vue';
import LayoutInfoprodutor from '@/Layouts/LayoutInfoprodutor.vue';
import VueApexCharts from 'vue3-apexcharts';
import { Star } from 'lucide-vue-next';
import { router } from '@inertiajs/vue3';

defineOptions({ layout: LayoutInfoprodutor });

const props = defineProps({
    by_professional: { type: Array, default: () => [] },
    by_month:        { type: Array, default: () => [] },
    stats:           { type: Object, default: () => ({}) },
    months:          { type: Number, default: 3 },
});

function setMonths(m) { router.visit('/relatorios/profissionais?months=' + m, { preserveScroll: true }); }

const byMonthOptions = computed(() => ({
    chart:  { type: 'bar', toolbar: { show: false }, background: 'transparent', stacked: true },
    xaxis:  { categories: props.by_month.map(r => r.month) },
    colors: ['#10b981','#3b82f6','#f59e0b','#ef4444'],
    plotOptions: { bar: { borderRadius: 0, columnWidth: '50%' } },
    dataLabels: { enabled: false },
    legend: { position: 'top' },
}));

const STATUS_LABELS = { confirmed: 'Confirmado', completed: 'Concluído', pending: 'Pendente', cancelled: 'Cancelado' };

const byMonthSeries = computed(() => {
    const statuses = ['confirmed','completed','pending','cancelled'];
    return statuses.map(s => ({
        name: STATUS_LABELS[s],
        data: props.by_month.map(r => r[s] ?? 0),
    }));
});

function fmtRating(v) { return v ? Number(v).toFixed(1) : '—'; }
function statusColor(s) {
    return { confirmed: 'text-blue-600', completed: 'text-emerald-600', pending: 'text-amber-600', cancelled: 'text-red-500' }[s] ?? '';
}
</script>

<template>
    <div class="space-y-6">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div>
                <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Profissionais</h1>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Atendimentos, avaliações e desempenho.</p>
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
                <p class="text-2xl font-bold text-violet-600">{{ stats.total_appointments ?? 0 }}</p>
                <p class="text-xs text-zinc-500 mt-1">Atendimentos totais</p>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60 p-4 text-center">
                <p class="text-2xl font-bold text-emerald-600">{{ stats.completed ?? 0 }}</p>
                <p class="text-xs text-zinc-500 mt-1">Concluídos</p>
            </div>
            <div class="rounded-2xl border border-red-100 bg-red-50/50 p-4 text-center">
                <p class="text-2xl font-bold text-red-500">{{ stats.cancelled ?? 0 }}</p>
                <p class="text-xs text-zinc-500 mt-1">Cancelados</p>
            </div>
            <div class="rounded-2xl border border-amber-100 bg-amber-50/50 p-4 text-center">
                <p class="text-2xl font-bold text-amber-700 flex items-center justify-center gap-1">
                    <Star class="h-5 w-5 fill-amber-400 text-amber-400" />
                    {{ fmtRating(stats.avg_rating) }}
                </p>
                <p class="text-xs text-zinc-500 mt-1">Avaliação média</p>
            </div>
        </div>

        <!-- Gráfico por mês -->
        <div v-if="by_month.length" class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60 p-5">
            <h2 class="font-semibold text-zinc-900 dark:text-white mb-4">Atendimentos por mês</h2>
            <VueApexCharts type="bar" height="260" :options="byMonthOptions" :series="byMonthSeries" />
        </div>

        <!-- Tabela por profissional -->
        <div v-if="by_professional.length" class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60 overflow-x-auto">
            <div class="px-5 py-4 border-b border-zinc-100 dark:border-zinc-700">
                <h2 class="font-semibold text-zinc-900 dark:text-white">Por profissional</h2>
            </div>
            <table class="min-w-full divide-y divide-zinc-100 dark:divide-zinc-700">
                <thead class="bg-zinc-50 dark:bg-zinc-800">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-zinc-500">Profissional</th>
                        <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wide text-zinc-500">Total</th>
                        <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wide text-zinc-500">Concluídos</th>
                        <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wide text-zinc-500">Pendentes</th>
                        <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wide text-zinc-500">Cancelados</th>
                        <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wide text-zinc-500">Avaliação</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700 bg-white dark:bg-zinc-800/60">
                    <tr v-for="p in by_professional" :key="p.professional_id">
                        <td class="px-4 py-3">
                            <p class="text-sm font-semibold text-zinc-900 dark:text-white">{{ p.professional_name }}</p>
                        </td>
                        <td class="px-4 py-3 text-center text-sm font-bold text-zinc-900 dark:text-white">{{ p.total }}</td>
                        <td class="px-4 py-3 text-center text-sm text-emerald-600 font-semibold">{{ p.completed }}</td>
                        <td class="px-4 py-3 text-center text-sm text-amber-600">{{ p.pending }}</td>
                        <td class="px-4 py-3 text-center text-sm text-red-500">{{ p.cancelled }}</td>
                        <td class="px-4 py-3 text-center">
                            <span v-if="p.avg_rating" class="flex items-center justify-center gap-1 text-sm font-semibold text-amber-700">
                                <Star class="h-3.5 w-3.5 fill-amber-400 text-amber-400" />
                                {{ fmtRating(p.avg_rating) }}
                                <span class="text-xs font-normal text-zinc-400">({{ p.reviews }})</span>
                            </span>
                            <span v-else class="text-zinc-300 text-sm">—</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div v-else class="py-16 text-center text-zinc-400 text-sm rounded-2xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800/60">
            Nenhum atendimento no período.
        </div>
    </div>
</template>
