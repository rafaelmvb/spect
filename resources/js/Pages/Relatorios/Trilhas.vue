<script setup>
import { ref } from 'vue';
import LayoutInfoprodutor from '@/Layouts/LayoutInfoprodutor.vue';
import { FileText, RefreshCw, Loader2, ChevronRight, CheckCircle, Clock, XCircle } from 'lucide-vue-next';
import { router } from '@inertiajs/vue3';

defineOptions({ layout: LayoutInfoprodutor });

const props = defineProps({
    journeys:   { type: Array, default: () => [] },
    reports:    { type: Array, default: () => [] },
    journey_id: { type: Number, default: null },
});

const toast      = ref(null);
const generating = ref(null);

function csrf() { return document.querySelector('meta[name="csrf-token"]')?.content ?? ''; }

function setJourney(id) {
    router.visit('/relatorios/trilhas?journey_id=' + id, { preserveScroll: true });
}

function showToast(msg, type = 'success') {
    toast.value = { msg, type };
    setTimeout(() => toast.value = null, 4000);
}

async function generate(row) {
    generating.value = row.user_id;
    try {
        await window.axios.post('/relatorios/trilhas/gerar', {
            user_id:    row.user_id,
            journey_id: props.journey_id,
        }, { headers: { 'X-CSRF-TOKEN': csrf() } });
        showToast(`Relatório de ${row.user_name} gerado.`);
        router.reload({ only: ['reports'] });
    } catch (e) {
        showToast(e.response?.data?.message ?? 'Erro ao gerar.', 'error');
    } finally {
        generating.value = null;
    }
}

function openReport(row) {
    if (row.report_id) router.visit('/relatorios/trilhas/' + row.report_id);
}

const STATUS_COLORS = {
    draft:     'bg-zinc-100 text-zinc-500',
    ready:     'bg-blue-100 text-blue-700',
    published: 'bg-emerald-100 text-emerald-700',
};
const STATUS_LABELS = { draft: 'Rascunho', ready: 'Pronto', published: 'Publicado' };
</script>

<template>
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Relatórios de Trilha</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">Gere e publique relatórios individuais por aluno.</p>
        </div>

        <!-- Toast -->
        <div v-if="toast" class="rounded-xl px-4 py-3 text-sm font-medium border"
            :class="toast.type === 'error' ? 'bg-red-50 text-red-700 border-red-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200'">
            {{ toast.msg }}
        </div>

        <!-- Filtro de jornada -->
        <div class="flex flex-wrap gap-2">
            <button v-for="j in journeys" :key="j.id" @click="setJourney(j.id)"
                class="px-3 py-1.5 rounded-xl text-sm font-medium border transition"
                :class="journey_id === j.id
                    ? 'bg-violet-600 text-white border-violet-600'
                    : 'border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-800'">
                {{ j.name }}
                <span class="ml-1 text-xs opacity-60">({{ j.total_steps }} etapas)</span>
            </button>
        </div>

        <!-- Tabela -->
        <div v-if="reports.length" class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60 overflow-hidden">
            <table class="min-w-full divide-y divide-zinc-100 dark:divide-zinc-700">
                <thead class="bg-zinc-50 dark:bg-zinc-800">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-zinc-500">Aluno</th>
                        <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wide text-zinc-500">Progresso</th>
                        <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wide text-zinc-500">Relatório</th>
                        <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wide text-zinc-500">Gerado em</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700 bg-white dark:bg-zinc-800/60">
                    <tr v-for="row in reports" :key="row.user_id">
                        <td class="px-4 py-3">
                            <p class="text-sm font-semibold text-zinc-900 dark:text-white">{{ row.user_name }}</p>
                            <p class="text-xs text-zinc-500">{{ row.user_email }}</p>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <div class="flex flex-col items-center gap-1">
                                <span class="text-sm font-bold" :class="row.pct === 100 ? 'text-emerald-600' : 'text-zinc-900 dark:text-white'">
                                    {{ row.pct }}%
                                </span>
                                <div class="w-20 bg-zinc-100 dark:bg-zinc-700 rounded-full h-1.5">
                                    <div class="h-1.5 rounded-full transition-all"
                                        :class="row.pct === 100 ? 'bg-emerald-500' : 'bg-violet-500'"
                                        :style="`width:${row.pct}%`" />
                                </div>
                                <span class="text-[10px] text-zinc-400">{{ row.completed_steps }}/{{ row.total_steps }} etapas</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span v-if="row.report_status" class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                :class="STATUS_COLORS[row.report_status]">
                                {{ STATUS_LABELS[row.report_status] }}
                            </span>
                            <span v-else class="text-xs text-zinc-400">—</span>
                        </td>
                        <td class="px-4 py-3 text-center text-xs text-zinc-500">
                            {{ row.report_date ?? '—' }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-2">
                                <button @click="generate(row)" :disabled="generating === row.user_id"
                                    class="flex items-center gap-1.5 rounded-xl border border-violet-200 text-violet-700 hover:bg-violet-50 dark:border-violet-800 dark:text-violet-300 px-3 py-1.5 text-xs font-semibold transition disabled:opacity-50">
                                    <Loader2 v-if="generating === row.user_id" class="h-3.5 w-3.5 animate-spin" />
                                    <RefreshCw v-else class="h-3.5 w-3.5" />
                                    {{ row.report_id ? 'Regenerar' : 'Gerar' }}
                                </button>
                                <button v-if="row.report_id" @click="openReport(row)"
                                    class="flex items-center gap-1 rounded-xl border border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-700 px-3 py-1.5 text-xs font-semibold transition">
                                    <FileText class="h-3.5 w-3.5" />
                                    Ver
                                    <ChevronRight class="h-3 w-3" />
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-else-if="journey_id" class="py-16 text-center text-zinc-400 text-sm rounded-2xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800/60">
            Nenhum aluno iniciou esta trilha ainda.
        </div>

        <div v-else class="py-16 text-center text-zinc-400 text-sm rounded-2xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800/60">
            Selecione uma trilha acima para ver os alunos.
        </div>
    </div>
</template>
