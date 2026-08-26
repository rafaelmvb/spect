<script setup>
import { computed } from 'vue';
import LayoutProfissional from '@/Layouts/LayoutProfissional.vue';
import VueApexCharts from 'vue3-apexcharts';
import { DollarSign, Clock, CheckCheck, TrendingUp } from 'lucide-vue-next';

defineOptions({ layout: LayoutProfissional });

const props = defineProps({
    commissions:  { type: Array, default: () => [] },
    by_month:     { type: Array, default: () => [] },
    stats:        { type: Object, default: () => ({}) },
});

function fmt(v) {
    return Number(v || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

const byMonthOptions = computed(() => ({
    chart:  { type: 'area', toolbar: { show: false }, background: 'transparent' },
    xaxis:  { categories: props.by_month.map(r => r.month) },
    yaxis:  { labels: { formatter: v => 'R$' + Number(v).toLocaleString('pt-BR') } },
    colors: ['#7c3aed'],
    fill:   { type: 'gradient', gradient: { opacityFrom: 0.35, opacityTo: 0.05 } },
    stroke: { width: 2, curve: 'smooth' },
    dataLabels: { enabled: false },
    tooltip: { y: { formatter: v => fmt(v) } },
}));

const byMonthSeries = computed(() => [{ name: 'Comissão', data: props.by_month.map(r => r.total) }]);

const STATUS_LABELS = { pending: 'Aguardando', approved: 'Aprovado', paid: 'Pago' };
const STATUS_COLORS = {
    pending:  'bg-amber-100 text-amber-700',
    approved: 'bg-blue-100 text-blue-700',
    paid:     'bg-emerald-100 text-emerald-700',
};
</script>

<template>
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Financeiro</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">Seus repasses e comissões.</p>
        </div>

        <!-- Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="rounded-2xl border border-violet-100 bg-violet-50/50 dark:border-violet-900/20 dark:bg-violet-900/10 p-4">
                <div class="flex items-center gap-2 mb-2">
                    <TrendingUp class="h-4 w-4 text-violet-600" />
                    <p class="text-xs font-medium text-violet-600 uppercase tracking-wide">Total ganho</p>
                </div>
                <p class="text-2xl font-bold text-violet-700">{{ fmt(stats.total_earned) }}</p>
                <p class="text-xs text-violet-400 mt-0.5">{{ stats.total_appointments }} atendimentos</p>
            </div>

            <div class="rounded-2xl border border-amber-100 bg-amber-50/50 dark:border-amber-900/20 dark:bg-amber-900/10 p-4">
                <div class="flex items-center gap-2 mb-2">
                    <Clock class="h-4 w-4 text-amber-600" />
                    <p class="text-xs font-medium text-amber-600 uppercase tracking-wide">Pendente</p>
                </div>
                <p class="text-2xl font-bold text-amber-700">{{ fmt(stats.total_pending) }}</p>
                <p class="text-xs text-amber-400 mt-0.5">{{ stats.count_pending }} a receber</p>
            </div>

            <div class="rounded-2xl border border-blue-100 bg-blue-50/50 dark:border-blue-900/20 dark:bg-blue-900/10 p-4">
                <div class="flex items-center gap-2 mb-2">
                    <DollarSign class="h-4 w-4 text-blue-600" />
                    <p class="text-xs font-medium text-blue-600 uppercase tracking-wide">Aprovado</p>
                </div>
                <p class="text-2xl font-bold text-blue-700">{{ fmt(stats.total_approved) }}</p>
                <p class="text-xs text-blue-400 mt-0.5">Aguardando repasse</p>
            </div>

            <div class="rounded-2xl border border-emerald-100 bg-emerald-50/50 dark:border-emerald-900/20 dark:bg-emerald-900/10 p-4">
                <div class="flex items-center gap-2 mb-2">
                    <CheckCheck class="h-4 w-4 text-emerald-600" />
                    <p class="text-xs font-medium text-emerald-600 uppercase tracking-wide">Recebido</p>
                </div>
                <p class="text-2xl font-bold text-emerald-700">{{ fmt(stats.total_paid) }}</p>
                <p class="text-xs text-emerald-400 mt-0.5">Já transferido</p>
            </div>
        </div>

        <!-- Gráfico por mês -->
        <div v-if="by_month.length" class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60 p-5">
            <h2 class="font-semibold text-zinc-900 dark:text-white mb-4">Comissões por mês</h2>
            <VueApexCharts type="area" height="220" :options="byMonthOptions" :series="byMonthSeries" />
        </div>

        <!-- Info de comissão -->
        <div class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60 p-5 flex items-center gap-3">
            <div class="h-10 w-10 rounded-xl bg-violet-100 dark:bg-violet-900/30 flex items-center justify-center shrink-0">
                <DollarSign class="h-5 w-5 text-violet-600" />
            </div>
            <div>
                <p class="text-sm font-semibold text-zinc-900 dark:text-white">Sua comissão: {{ stats.commission_percent ?? '—' }}%</p>
                <p class="text-xs text-zinc-500 mt-0.5">Percentual sobre o valor bruto de cada atendimento.</p>
            </div>
        </div>

        <!-- Lista de comissões -->
        <div class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60 overflow-hidden">
            <div class="px-5 py-4 border-b border-zinc-100 dark:border-zinc-700">
                <h2 class="font-semibold text-zinc-900 dark:text-white">Histórico de repasses</h2>
            </div>

            <div v-if="commissions.length" class="divide-y divide-zinc-100 dark:divide-zinc-700">
                <div v-for="c in commissions" :key="c.id" class="flex items-center gap-4 px-5 py-4 flex-wrap">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-zinc-900 dark:text-white truncate">{{ c.client_name }}</p>
                        <p class="text-xs text-zinc-500 mt-0.5">{{ c.appointment_date }} {{ c.appointment_time }}</p>
                    </div>
                    <div class="text-right shrink-0">
                        <p class="text-sm font-bold text-emerald-600">{{ fmt(c.commission_amount) }}</p>
                        <p class="text-xs text-zinc-400">{{ c.commission_percent }}% de {{ fmt(c.amount_gross) }}</p>
                    </div>
                    <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold shrink-0" :class="STATUS_COLORS[c.status]">
                        {{ STATUS_LABELS[c.status] }}
                    </span>
                </div>
            </div>
            <div v-else class="py-16 text-center text-zinc-400 text-sm">
                Nenhum repasse encontrado.
            </div>
        </div>
    </div>
</template>
