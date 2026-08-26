<script setup>
import { ref } from 'vue';
import LayoutInfoprodutor from '@/Layouts/LayoutInfoprodutor.vue';
import { Check, DollarSign, Clock, CheckCheck, X, Loader2, ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { router } from '@inertiajs/vue3';

defineOptions({ layout: LayoutInfoprodutor });

const props = defineProps({
    commissions:     { type: Object, required: true },
    stats:           { type: Object, default: () => ({}) },
    by_professional: { type: Array, default: () => [] },
    status_filter:   { type: String, default: 'all' },
});

const toast        = ref(null);
const paidModal    = ref(null);
const paidNotes    = ref('');
const processing   = ref(null);

function fmt(v) {
    return Number(v || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function csrf() { return document.querySelector('meta[name="csrf-token"]')?.content ?? ''; }
function setStatus(s) { router.visit('/financeiro/comissoes?status=' + s, { preserveScroll: true }); }
function goPage(url) { if (url) router.visit(url, { preserveScroll: true }); }

function showToast(msg, type = 'success') {
    toast.value = { msg, type };
    setTimeout(() => toast.value = null, 4000);
}

async function approve(id) {
    processing.value = id;
    try {
        await window.axios.post(`/financeiro/comissoes/${id}/aprovar`, {}, { headers: { 'X-CSRF-TOKEN': csrf() } });
        showToast('Comissão aprovada.');
        router.reload({ only: ['commissions', 'stats'] });
    } catch (e) {
        showToast(e.response?.data?.message ?? 'Erro.', 'error');
    } finally {
        processing.value = null;
    }
}

async function markPaid() {
    if (! paidModal.value) return;
    processing.value = paidModal.value.id;
    try {
        await window.axios.post(`/financeiro/comissoes/${paidModal.value.id}/pago`, { payment_notes: paidNotes.value }, { headers: { 'X-CSRF-TOKEN': csrf() } });
        showToast('Repasse marcado como pago.');
        paidModal.value = null;
        router.reload({ only: ['commissions', 'stats', 'by_professional'] });
    } catch (e) {
        showToast(e.response?.data?.message ?? 'Erro.', 'error');
    } finally {
        processing.value = null;
    }
}

const STATUS_COLORS = {
    pending:  'bg-amber-100 text-amber-700',
    approved: 'bg-blue-100 text-blue-700',
    paid:     'bg-emerald-100 text-emerald-700',
};
const STATUS_LABELS = { pending: 'Pendente', approved: 'Aprovado', paid: 'Pago' };
</script>

<template>
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Comissões e Repasses</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">Gerencie os repasses para profissionais.</p>
        </div>

        <!-- Toast -->
        <div v-if="toast" class="rounded-xl px-4 py-3 text-sm font-medium"
            :class="toast.type === 'error' ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200'">
            {{ toast.msg }}
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60 p-4">
                <p class="text-xs font-medium text-zinc-500 uppercase tracking-wide">Total bruto</p>
                <p class="text-xl font-bold text-zinc-900 dark:text-white mt-1">{{ fmt(stats.total_gross) }}</p>
            </div>
            <div class="rounded-2xl border border-amber-100 bg-amber-50/50 dark:border-amber-900/20 p-4">
                <p class="text-xs font-medium text-amber-600 uppercase tracking-wide">Pendente</p>
                <p class="text-xl font-bold text-amber-700 mt-1">{{ fmt(stats.total_pending) }}</p>
                <p class="text-xs text-amber-400 mt-0.5">{{ stats.count_pending }} comissões</p>
            </div>
            <div class="rounded-2xl border border-blue-100 bg-blue-50/50 dark:border-blue-900/20 p-4">
                <p class="text-xs font-medium text-blue-600 uppercase tracking-wide">Aprovado</p>
                <p class="text-xl font-bold text-blue-700 mt-1">{{ fmt(stats.total_approved) }}</p>
            </div>
            <div class="rounded-2xl border border-emerald-100 bg-emerald-50/50 dark:border-emerald-900/20 p-4">
                <p class="text-xs font-medium text-emerald-600 uppercase tracking-wide">Pago</p>
                <p class="text-xl font-bold text-emerald-700 mt-1">{{ fmt(stats.total_paid) }}</p>
            </div>
        </div>

        <!-- Sumário por profissional -->
        <div v-if="by_professional.length" class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60 overflow-hidden">
            <div class="px-5 py-4 border-b border-zinc-100 dark:border-zinc-700">
                <h2 class="font-semibold text-zinc-900 dark:text-white">Por profissional</h2>
            </div>
            <table class="min-w-full divide-y divide-zinc-100 dark:divide-zinc-700">
                <thead class="bg-zinc-50 dark:bg-zinc-800">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-zinc-500">Profissional</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-zinc-500">Atendimentos</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-zinc-500">A repassar</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-zinc-500">Pago</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700 bg-white dark:bg-zinc-800/60">
                    <tr v-for="p in by_professional" :key="p.professional_id">
                        <td class="px-4 py-3 text-sm font-medium text-zinc-900 dark:text-white">{{ p.professional_name }}</td>
                        <td class="px-4 py-3 text-sm text-zinc-600 dark:text-zinc-400 text-right">{{ p.total }}</td>
                        <td class="px-4 py-3 text-sm font-semibold text-amber-700 text-right">{{ fmt(p.pending) }}</td>
                        <td class="px-4 py-3 text-sm font-semibold text-emerald-700 text-right">{{ fmt(p.paid) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Filtros + Lista de comissões -->
        <div class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60 overflow-hidden">
            <div class="flex items-center gap-2 px-5 py-4 border-b border-zinc-100 dark:border-zinc-700 flex-wrap">
                <button v-for="s in ['all','pending','approved','paid']" :key="s" @click="setStatus(s)"
                    class="px-3 py-1 rounded-lg text-xs font-medium border transition"
                    :class="status_filter === s ? 'bg-violet-600 text-white border-violet-600' : 'border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-300'">
                    {{ s === 'all' ? 'Todos' : STATUS_LABELS[s] }}
                </button>
            </div>

            <div v-if="commissions.data?.length" class="divide-y divide-zinc-100 dark:divide-zinc-700">
                <div v-for="c in commissions.data" :key="c.id" class="px-5 py-4 flex items-center gap-4 flex-wrap">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-zinc-900 dark:text-white truncate">{{ c.professional_name }}</p>
                        <p class="text-xs text-zinc-500 mt-0.5">
                            {{ c.client_name }} · {{ c.appointment_date }} {{ c.appointment_time }}
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-bold text-zinc-900 dark:text-white">{{ fmt(c.commission_amount) }}</p>
                        <p class="text-xs text-zinc-400">{{ c.commission_percent }}% de {{ fmt(c.amount_gross) }}</p>
                    </div>
                    <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold shrink-0" :class="STATUS_COLORS[c.status]">
                        {{ STATUS_LABELS[c.status] }}
                    </span>
                    <div class="flex gap-2 shrink-0">
                        <button v-if="c.status === 'pending'" @click="approve(c.id)" :disabled="processing === c.id"
                            class="flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-blue-600 border border-blue-200 hover:bg-blue-50 disabled:opacity-60">
                            <Loader2 v-if="processing === c.id" class="h-3 w-3 animate-spin" />
                            <Check v-else class="h-3 w-3" /> Aprovar
                        </button>
                        <button v-if="c.status === 'approved'" @click="paidModal = c; paidNotes = ''"
                            class="flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-emerald-600 border border-emerald-200 hover:bg-emerald-50">
                            <DollarSign class="h-3 w-3" /> Marcar pago
                        </button>
                    </div>
                </div>
            </div>
            <div v-else class="py-16 text-center text-zinc-400 text-sm">Nenhuma comissão encontrada.</div>

            <!-- Paginação -->
            <div v-if="commissions.last_page > 1" class="flex items-center justify-between px-5 py-4 border-t border-zinc-100 dark:border-zinc-700">
                <p class="text-xs text-zinc-500">{{ commissions.from }}–{{ commissions.to }} de {{ commissions.total }}</p>
                <div class="flex gap-2">
                    <button @click="goPage(commissions.prev_page_url)" :disabled="!commissions.prev_page_url" class="rounded-lg p-1.5 border border-zinc-200 dark:border-zinc-700 disabled:opacity-40">
                        <ChevronLeft class="h-4 w-4" />
                    </button>
                    <button @click="goPage(commissions.next_page_url)" :disabled="!commissions.next_page_url" class="rounded-lg p-1.5 border border-zinc-200 dark:border-zinc-700 disabled:opacity-40">
                        <ChevronRight class="h-4 w-4" />
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: marcar como pago -->
    <Teleport to="body">
        <div v-if="paidModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40" @click.self="paidModal = null">
            <div class="w-full max-w-sm rounded-2xl bg-white dark:bg-zinc-900 shadow-2xl">
                <div class="flex items-center justify-between px-5 py-4 border-b border-zinc-100 dark:border-zinc-800">
                    <h2 class="font-semibold text-zinc-900 dark:text-white">Confirmar pagamento</h2>
                    <button @click="paidModal = null" class="rounded-lg p-1 text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800"><X class="h-5 w-5" /></button>
                </div>
                <div class="p-5 space-y-3">
                    <p class="text-sm text-zinc-600 dark:text-zinc-300">
                        Confirmar repasse de <strong>{{ fmt(paidModal.commission_amount) }}</strong> para <strong>{{ paidModal.professional_name }}</strong>?
                    </p>
                    <div>
                        <label class="block text-xs font-medium text-zinc-500 mb-1">Observação (opcional)</label>
                        <textarea v-model="paidNotes" rows="2" class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                    </div>
                </div>
                <div class="flex justify-end gap-2 px-5 pb-5">
                    <button @click="paidModal = null" class="rounded-xl px-4 py-2 text-sm text-zinc-600 hover:bg-zinc-100 dark:hover:bg-zinc-800 dark:text-zinc-300">Cancelar</button>
                    <button @click="markPaid" :disabled="processing !== null"
                        class="flex items-center gap-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60 px-4 py-2 text-sm font-semibold text-white">
                        <Loader2 v-if="processing" class="h-4 w-4 animate-spin" />
                        <CheckCheck v-else class="h-4 w-4" />
                        Confirmar pagamento
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
