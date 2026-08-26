<script setup>
import { computed } from 'vue';
import RelatoriosLayout from '@/Layouts/RelatoriosLayout.vue';
import VueApexCharts from 'vue3-apexcharts';
import { Users, BookOpen, TrendingUp, AlertCircle } from 'lucide-vue-next';

defineOptions({ layout: RelatoriosLayout });

const props = defineProps({
    totalAlunos: Number,
    novosPorMes: Array,
    progressoPorProduto: Array,
    topAlunos: Array,
    aulasPopulares: Array,
    semAcesso: Number,
});

function fmt(v) {
    return new Intl.NumberFormat('pt-BR').format(v ?? 0);
}
function fmtDate(d) {
    if (!d) return '';
    return new Date(d).toLocaleDateString('pt-BR');
}

const chartNovos = computed(() => ({
    chart: { type: 'bar', height: 200, toolbar: { show: false }, background: 'transparent' },
    colors: ['#6366f1'],
    xaxis: { categories: props.novosPorMes.map(m => m.mes), labels: { style: { colors: '#9ca3af' } } },
    yaxis: { labels: { style: { colors: '#9ca3af' } } },
    grid: { borderColor: '#374151' },
    theme: { mode: 'dark' },
    plotOptions: { bar: { borderRadius: 4 } },
    tooltip: { y: { formatter: v => v + ' alunos' } },
}));

const seriesNovos = computed(() => [{ name: 'Novos alunos', data: props.novosPorMes.map(m => m.total) }]);
</script>

<template>
    <div class="space-y-6">
        <h1 class="text-xl font-bold text-zinc-900 dark:text-white">Alunos & Engajamento</h1>

        <!-- KPIs -->
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-800/50">
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Total de alunos</p>
                <p class="mt-2 text-2xl font-bold text-zinc-900 dark:text-white">{{ fmt(totalAlunos) }}</p>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-800/50">
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Nunca acessaram</p>
                <p class="mt-2 text-2xl font-bold text-red-600 dark:text-red-400">{{ fmt(semAcesso) }}</p>
                <p class="text-xs text-zinc-400">{{ totalAlunos > 0 ? ((semAcesso / totalAlunos) * 100).toFixed(1) : 0 }}% do total</p>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-800/50">
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Engajados</p>
                <p class="mt-2 text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ fmt(totalAlunos - semAcesso) }}</p>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-800/50">
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Taxa de engajamento</p>
                <p class="mt-2 text-2xl font-bold text-violet-600 dark:text-violet-400">
                    {{ totalAlunos > 0 ? (((totalAlunos - semAcesso) / totalAlunos) * 100).toFixed(1) : 0 }}%
                </p>
            </div>
        </div>

        <!-- Novos por mês -->
        <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-800/50">
            <h2 class="mb-4 font-semibold text-zinc-800 dark:text-white">Novos alunos por mês (últimos 6 meses)</h2>
            <VueApexCharts v-if="novosPorMes.length" :options="chartNovos" :series="seriesNovos" height="200" />
            <p v-else class="text-sm text-zinc-400">Sem dados.</p>
        </div>

        <!-- Progresso por produto -->
        <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-800/50">
            <h2 class="mb-4 font-semibold text-zinc-800 dark:text-white">Engajamento por produto</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700">
                            <th class="pb-2 text-left font-medium text-zinc-500">Produto</th>
                            <th class="pb-2 text-right font-medium text-zinc-500">Alunos ativos</th>
                            <th class="pb-2 text-right font-medium text-zinc-500">Aulas concluídas</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700/50">
                        <tr v-for="p in progressoPorProduto" :key="p.produto">
                            <td class="py-2.5 font-medium text-zinc-800 dark:text-zinc-200">{{ p.produto }}</td>
                            <td class="py-2.5 text-right text-zinc-600 dark:text-zinc-400">{{ fmt(p.alunos_ativos) }}</td>
                            <td class="py-2.5 text-right text-zinc-600 dark:text-zinc-400">{{ fmt(p.aulas_concluidas) }}</td>
                        </tr>
                        <tr v-if="!progressoPorProduto.length">
                            <td colspan="3" class="py-4 text-center text-zinc-400">Sem dados de progresso.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <!-- Top alunos -->
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-800/50">
                <h2 class="mb-4 font-semibold text-zinc-800 dark:text-white">🏆 Alunos mais engajados</h2>
                <div class="space-y-2">
                    <div v-for="(a, i) in topAlunos" :key="a.email" class="flex items-center gap-3 text-sm">
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-zinc-100 text-xs font-bold text-zinc-500 dark:bg-zinc-700">{{ i + 1 }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium text-zinc-800 dark:text-zinc-200">{{ a.name }}</p>
                            <p class="truncate text-xs text-zinc-400">{{ a.email }}</p>
                        </div>
                        <span class="shrink-0 text-xs font-semibold text-emerald-600 dark:text-emerald-400">{{ a.aulas_concluidas }} aulas</span>
                    </div>
                    <p v-if="!topAlunos.length" class="text-sm text-zinc-400">Sem dados.</p>
                </div>
            </div>

            <!-- Aulas populares -->
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-800/50">
                <h2 class="mb-4 font-semibold text-zinc-800 dark:text-white">🎯 Aulas mais acessadas</h2>
                <div class="space-y-2">
                    <div v-for="(a, i) in aulasPopulares" :key="i" class="flex items-start gap-3 text-sm">
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-zinc-100 text-xs font-bold text-zinc-500 dark:bg-zinc-700">{{ i + 1 }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium text-zinc-800 dark:text-zinc-200">{{ a.aula }}</p>
                            <p class="truncate text-xs text-zinc-400">{{ a.produto }}</p>
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="text-xs font-semibold text-violet-600 dark:text-violet-400">{{ a.acessos }} acessos</p>
                            <p class="text-xs text-zinc-400">{{ a.conclusoes }} concluídas</p>
                        </div>
                    </div>
                    <p v-if="!aulasPopulares.length" class="text-sm text-zinc-400">Sem dados.</p>
                </div>
            </div>
        </div>

        <!-- Alerta alunos sem acesso -->
        <div v-if="semAcesso > 0" class="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-5 dark:border-amber-800/40 dark:bg-amber-900/20">
            <AlertCircle class="h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400" />
            <div>
                <p class="font-semibold text-amber-800 dark:text-amber-200">{{ fmt(semAcesso) }} alunos nunca acessaram a trilha</p>
                <p class="mt-1 text-sm text-amber-700 dark:text-amber-300">
                    Considere enviar uma campanha de WhatsApp, SMS ou e-mail para reengajá-los.
                </p>
            </div>
        </div>
    </div>
</template>
