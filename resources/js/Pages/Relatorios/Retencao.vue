<script setup>
import LayoutInfoprodutor from '@/Layouts/LayoutInfoprodutor.vue';
import { router } from '@inertiajs/vue3';

defineOptions({ layout: LayoutInfoprodutor });

const props = defineProps({
    cohort:       { type: Array, default: () => [] },
    stats:        { type: Object, default: () => ({}) },
    months:       { type: Number, default: 6 },
});

function setMonths(m) { router.visit('/relatorios/retencao?months=' + m, { preserveScroll: true }); }

function pct(v) { return v !== null && v !== undefined ? Math.round(v) + '%' : '—'; }

function retentionColor(v) {
    if (v === null || v === undefined) return '';
    if (v >= 80) return 'bg-emerald-100 text-emerald-800';
    if (v >= 60) return 'bg-blue-100 text-blue-700';
    if (v >= 40) return 'bg-amber-100 text-amber-700';
    return 'bg-red-100 text-red-700';
}
</script>

<template>
    <div class="space-y-6">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div>
                <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Retenção</h1>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Análise de coorte por mês de entrada.</p>
            </div>
            <div class="flex gap-2">
                <button v-for="m in [3,6,9,12]" :key="m" @click="setMonths(m)"
                    class="px-3 py-1.5 rounded-lg text-sm font-medium border transition"
                    :class="months === m ? 'bg-violet-600 text-white border-violet-600' : 'border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-300 hover:bg-zinc-50'">
                    {{ m }}m
                </button>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60 p-4 text-center">
                <p class="text-2xl font-bold text-violet-600">{{ pct(stats.avg_retention_m1) }}</p>
                <p class="text-xs text-zinc-500 mt-1">Retenção M1 média</p>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60 p-4 text-center">
                <p class="text-2xl font-bold text-emerald-600">{{ pct(stats.avg_retention_m3) }}</p>
                <p class="text-xs text-zinc-500 mt-1">Retenção M3 média</p>
            </div>
            <div class="rounded-2xl border border-red-100 bg-red-50/50 p-4 text-center">
                <p class="text-2xl font-bold text-red-600">{{ stats.churned_total ?? 0 }}</p>
                <p class="text-xs text-zinc-500 mt-1">Cancelamentos totais</p>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60 p-4 text-center">
                <p class="text-2xl font-bold text-amber-600">{{ pct(stats.avg_churn_rate) }}</p>
                <p class="text-xs text-zinc-500 mt-1">Churn rate médio</p>
            </div>
        </div>

        <!-- Tabela de coorte -->
        <div v-if="cohort.length" class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60 overflow-x-auto">
            <div class="px-5 py-4 border-b border-zinc-100 dark:border-zinc-700">
                <h2 class="font-semibold text-zinc-900 dark:text-white">Tabela de coorte</h2>
                <p class="text-xs text-zinc-400 mt-0.5">% de usuários ainda ativos N meses após entrada.</p>
            </div>
            <table class="min-w-full text-sm">
                <thead class="bg-zinc-50 dark:bg-zinc-800">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-zinc-500">Coorte</th>
                        <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wide text-zinc-500">Entradas</th>
                        <th v-for="n in (cohort[0]?.retention ?? []).length" :key="n"
                            class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wide text-zinc-500">
                            M{{ n }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700">
                    <tr v-for="row in cohort" :key="row.cohort">
                        <td class="px-4 py-3 font-medium text-zinc-900 dark:text-white">{{ row.cohort }}</td>
                        <td class="px-4 py-3 text-center text-zinc-600 dark:text-zinc-400">{{ row.entries }}</td>
                        <td v-for="(val, i) in row.retention" :key="i" class="px-2 py-2 text-center">
                            <span v-if="val !== null" class="inline-block rounded-lg px-2 py-1 text-xs font-semibold" :class="retentionColor(val)">
                                {{ pct(val) }}
                            </span>
                            <span v-else class="text-zinc-300 text-xs">—</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div v-else class="py-16 text-center text-zinc-400 text-sm rounded-2xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800/60">
            Dados insuficientes para gerar tabela de coorte.
        </div>
    </div>
</template>
