<script setup>
import { computed } from 'vue';
import RelatoriosLayout from '@/Layouts/RelatoriosLayout.vue';
import VueApexCharts from 'vue3-apexcharts';
import { RefreshCw, TrendingDown, AlertCircle } from 'lucide-vue-next';

defineOptions({ layout: RelatoriosLayout });

const props = defineProps({
    ativas: Number,
    canceladas: Number,
    pastDue: Number,
    mrr: Number,
    novasPorMes: Object,
    porProduto: Object,
    churnRecente: Number,
    renovacoesRecentes: Number,
});

function fmt(v) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v ?? 0);
}
function fmtN(v) { return new Intl.NumberFormat('pt-BR').format(v ?? 0); }

const meses = Object.keys(props.novasPorMes ?? {});

const chartSubs = computed(() => ({
    chart: { type: 'bar', height: 220, stacked: true, toolbar: { show: false }, background: 'transparent' },
    colors: ['#10b981', '#ef4444', '#f59e0b'],
    xaxis: { categories: meses, labels: { style: { colors: '#9ca3af' } } },
    yaxis: { labels: { style: { colors: '#9ca3af' } } },
    grid: { borderColor: '#374151' },
    theme: { mode: 'dark' },
    legend: { labels: { colors: '#9ca3af' } },
    tooltip: { y: { formatter: v => fmtN(v) + ' assinaturas' } },
}));

const seriesSubs = computed(() => {
    const ativas = meses.map(m => props.novasPorMes[m]?.active?.total ?? 0);
    const canceladas = meses.map(m => props.novasPorMes[m]?.cancelled?.total ?? 0);
    const pastDue = meses.map(m => props.novasPorMes[m]?.past_due?.total ?? 0);
    return [
        { name: 'Ativas', data: ativas },
        { name: 'Canceladas', data: canceladas },
        { name: 'Em atraso', data: pastDue },
    ];
});

const produtosArray = computed(() => Object.entries(props.porProduto ?? {}).map(([produto, rows]) => {
    const rowsArr = rows.values ? [...rows.values()] : Object.values(rows);
    const ativo = rowsArr.find(r => r.status === 'active')?.total ?? 0;
    const cancelado = rowsArr.find(r => r.status === 'cancelled')?.total ?? 0;
    return { produto, ativo, cancelado, total: ativo + cancelado };
}).sort((a, b) => b.total - a.total));

const churnRate = computed(() => {
    if (!props.ativas && !props.canceladas) return 0;
    return ((props.canceladas / (props.ativas + props.canceladas)) * 100).toFixed(1);
});
</script>

<template>
    <div class="space-y-6">
        <h1 class="text-xl font-bold text-zinc-900 dark:text-white">Assinaturas & MRR</h1>

        <!-- KPIs -->
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-800/50">
                <p class="text-sm text-zinc-500">MRR (Estimado)</p>
                <p class="mt-2 text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ fmt(mrr) }}</p>
                <p class="text-xs text-zinc-400">Receita mensal recorrente</p>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-800/50">
                <p class="text-sm text-zinc-500">Assinantes ativos</p>
                <p class="mt-2 text-2xl font-bold text-zinc-900 dark:text-white">{{ fmtN(ativas) }}</p>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-800/50">
                <p class="text-sm text-zinc-500">Churn rate</p>
                <p class="mt-2 text-2xl font-bold" :class="Number(churnRate) > 10 ? 'text-red-600 dark:text-red-400' : 'text-amber-600 dark:text-amber-400'">
                    {{ churnRate }}%
                </p>
                <p class="text-xs text-zinc-400">{{ fmtN(canceladas) }} canceladas</p>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-800/50">
                <p class="text-sm text-zinc-500">Renovações (30d)</p>
                <p class="mt-2 text-2xl font-bold text-violet-600 dark:text-violet-400">{{ fmtN(renovacoesRecentes) }}</p>
                <p class="text-xs text-zinc-400">Cancelamentos 30d: {{ fmtN(churnRecente) }}</p>
            </div>
        </div>

        <!-- Gráfico -->
        <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-800/50">
            <h2 class="mb-4 font-semibold text-zinc-800 dark:text-white">Novas assinaturas por mês (últimos 6 meses)</h2>
            <VueApexCharts v-if="meses.length" :options="chartSubs" :series="seriesSubs" height="220" />
            <p v-else class="text-sm text-zinc-400">Sem dados de assinaturas.</p>
        </div>

        <!-- Por produto -->
        <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-800/50">
            <h2 class="mb-4 font-semibold text-zinc-800 dark:text-white">Assinaturas por produto</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b border-zinc-200 dark:border-zinc-700">
                        <tr>
                            <th class="pb-2 text-left font-medium text-zinc-500">Produto</th>
                            <th class="pb-2 text-right font-medium text-zinc-500">Ativas</th>
                            <th class="pb-2 text-right font-medium text-zinc-500">Canceladas</th>
                            <th class="pb-2 text-right font-medium text-zinc-500">Retenção</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700/50">
                        <tr v-for="p in produtosArray" :key="p.produto">
                            <td class="py-2.5 font-medium text-zinc-800 dark:text-zinc-200">{{ p.produto }}</td>
                            <td class="py-2.5 text-right text-emerald-600 dark:text-emerald-400">{{ fmtN(p.ativo) }}</td>
                            <td class="py-2.5 text-right text-red-500 dark:text-red-400">{{ fmtN(p.cancelado) }}</td>
                            <td class="py-2.5 text-right font-semibold" :class="p.total > 0 && (p.ativo/p.total) >= 0.8 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600'">
                                {{ p.total > 0 ? ((p.ativo / p.total) * 100).toFixed(1) : 0 }}%
                            </td>
                        </tr>
                        <tr v-if="!produtosArray.length">
                            <td colspan="4" class="py-6 text-center text-zinc-400">Sem assinaturas registradas.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Alerta past_due -->
        <div v-if="pastDue > 0" class="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-5 dark:border-amber-800/40 dark:bg-amber-900/20">
            <AlertCircle class="h-5 w-5 shrink-0 text-amber-600" />
            <div>
                <p class="font-semibold text-amber-800 dark:text-amber-200">{{ fmtN(pastDue) }} assinatura(s) em atraso</p>
                <p class="mt-1 text-sm text-amber-700 dark:text-amber-300">
                    Considere enviar um lembrete de pagamento via WhatsApp ou e-mail.
                </p>
            </div>
        </div>
    </div>
</template>
