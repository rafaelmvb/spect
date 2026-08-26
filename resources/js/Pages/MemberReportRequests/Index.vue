<script setup>
import { ref } from 'vue';
import axios from 'axios';
import LayoutInfoprodutor from '@/Layouts/LayoutInfoprodutor.vue';
import { CheckCircle2, Clock, Settings2, ToggleLeft, ToggleRight, FileText, ExternalLink, XCircle, Trash2 } from 'lucide-vue-next';

defineOptions({ layout: LayoutInfoprodutor });

const props = defineProps({
    requests:     { type: Array,   default: () => [] },
    auto_approve: { type: Boolean, default: false },
});

const requests    = ref(props.requests.map(r => ({ ...r })));
const autoApprove = ref(props.auto_approve);
const savingSettings = ref(false);
const approvingId     = ref(null);
const disapprovingId  = ref(null);
const deletingId      = ref(null);

async function toggleAutoApprove() {
    autoApprove.value = !autoApprove.value;
    savingSettings.value = true;
    try {
        await axios.post('/member-reports/settings', { auto_approve: autoApprove.value });
    } catch {
        autoApprove.value = !autoApprove.value; // rollback
        alert('Erro ao salvar configuração.');
    } finally {
        savingSettings.value = false;
    }
}

async function approve(request) {
    if (approvingId.value) return;
    approvingId.value = request.id;
    try {
        const { data } = await axios.post(`/member-reports/${request.id}/approve`);
        const idx = requests.value.findIndex(r => r.id === request.id);
        if (idx !== -1) {
            requests.value[idx].status      = 'approved';
            requests.value[idx].approved_at = data.approved_at;
            requests.value[idx].insight_id  = data.insight_id ?? null;
        }
    } catch {
        alert('Erro ao aprovar.');
    } finally {
        approvingId.value = null;
    }
}

const pending  = () => requests.value.filter(r => r.status === 'pending');
const approved = () => requests.value.filter(r => r.status === 'approved');

async function deleteRequest(request) {
    if (deletingId.value) return;
    if (!confirm(`Apagar o relatório de ${request.user?.name || 'este aluno'}? O aluno poderá solicitar novamente.`)) return;
    deletingId.value = request.id;
    try {
        await axios.delete(`/member-reports/${request.id}`);
        requests.value = requests.value.filter(r => r.id !== request.id);
    } catch {
        alert('Erro ao apagar.');
    } finally {
        deletingId.value = null;
    }
}

async function disapprove(request) {
    if (disapprovingId.value) return;
    disapprovingId.value = request.id;
    try {
        await axios.post(`/member-reports/${request.id}/disapprove`);
        const idx = requests.value.findIndex(r => r.id === request.id);
        if (idx !== -1) {
            requests.value[idx].status      = 'pending';
            requests.value[idx].approved_at = null;
            requests.value[idx].insight_id  = null;
        }
    } catch {
        alert('Erro ao desaprovar.');
    } finally {
        disapprovingId.value = null;
    }
}
</script>

<template>
    <div class="p-6 space-y-6">

        <!-- Cabeçalho -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Relatórios de Alunos</h1>
                <p class="mt-1 text-sm text-zinc-500">Gerencie as solicitações de relatório de progresso</p>
            </div>

            <!-- Toggle aprovação automática -->
            <button
                type="button"
                :disabled="savingSettings"
                class="flex items-center gap-3 rounded-2xl border border-zinc-200 bg-white px-4 py-3 text-left shadow-sm transition hover:border-zinc-300 dark:border-zinc-700 dark:bg-zinc-800 dark:hover:border-zinc-600"
                @click="toggleAutoApprove"
            >
                <Settings2 class="h-5 w-5 shrink-0 text-zinc-500" />
                <div class="min-w-0">
                    <p class="text-sm font-medium text-zinc-800 dark:text-white">Aprovação automática</p>
                    <p class="text-xs text-zinc-500">{{ autoApprove ? 'Ativada — relatórios aprovados imediatamente' : 'Desativada — aprovação manual' }}</p>
                </div>
                <ToggleRight v-if="autoApprove" class="ml-2 h-6 w-6 shrink-0 text-emerald-500" />
                <ToggleLeft  v-else              class="ml-2 h-6 w-6 shrink-0 text-zinc-400" />
            </button>
        </div>

        <!-- Pendentes -->
        <section>
            <h2 class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-widest text-zinc-500">
                <Clock class="h-3.5 w-3.5" /> Pendentes ({{ pending().length }})
            </h2>

            <div v-if="pending().length" class="space-y-3">
                <div
                    v-for="req in pending()"
                    :key="req.id"
                    class="flex flex-col gap-4 rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between dark:border-zinc-700 dark:bg-zinc-800"
                >
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <FileText class="h-4 w-4 shrink-0 text-zinc-400" />
                            <span class="font-medium text-zinc-900 dark:text-white">{{ req.user?.name || '—' }}</span>
                            <span class="text-zinc-400">·</span>
                            <span class="truncate text-sm text-zinc-500">{{ req.user?.email }}</span>
                        </div>
                        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                            Curso: <strong class="text-zinc-800 dark:text-zinc-200">{{ req.product?.name || '—' }}</strong>
                        </p>
                        <p class="mt-0.5 text-xs text-zinc-400">Solicitado em {{ req.requested_at }}</p>
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        <button
                            type="button"
                            :disabled="approvingId === req.id"
                            class="flex items-center gap-2 rounded-xl bg-[var(--color-primary)] px-4 py-2 text-sm font-medium text-white transition hover:opacity-90 disabled:opacity-60"
                            @click="approve(req)"
                        >
                            <CheckCircle2 class="h-4 w-4" />
                            {{ approvingId === req.id ? 'Aprovando...' : 'Aprovar relatório' }}
                        </button>
                        <button
                            type="button"
                            :disabled="deletingId === req.id"
                            class="flex items-center gap-1.5 rounded-xl border border-red-200 px-3 py-2 text-sm font-medium text-red-500 transition hover:border-red-400 hover:bg-red-50 disabled:opacity-50 dark:border-red-800 dark:text-red-400 dark:hover:border-red-600 dark:hover:bg-red-950/30"
                            @click="deleteRequest(req)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </div>

            <div v-else class="rounded-2xl border-2 border-dashed border-zinc-200 py-10 text-center text-sm text-zinc-400 dark:border-zinc-700">
                Nenhuma solicitação pendente.
            </div>
        </section>

        <!-- Aprovados -->
        <section v-if="approved().length">
            <h2 class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-widest text-zinc-500">
                <CheckCircle2 class="h-3.5 w-3.5 text-emerald-500" /> Aprovados ({{ approved().length }})
            </h2>

            <div class="space-y-2">
                <div
                    v-for="req in approved()"
                    :key="req.id"
                    class="flex flex-col gap-2 rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-zinc-700 dark:bg-zinc-800/50"
                >
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ req.user?.name || '—' }}</span>
                            <span class="text-zinc-300 dark:text-zinc-600">·</span>
                            <span class="truncate text-sm text-zinc-500">{{ req.product?.name || '—' }}</span>
                        </div>
                        <p class="text-xs text-zinc-400">Solicitado {{ req.requested_at }} · Aprovado {{ req.approved_at }}</p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <a
                            v-if="req.insight_id"
                            :href="`/alunos/${req.user?.id}/relatorio/${req.insight_id}/imprimir`"
                            target="_blank"
                            class="flex items-center gap-1.5 rounded-lg border border-zinc-200 px-3 py-1.5 text-xs font-medium text-zinc-600 transition hover:border-zinc-400 hover:text-zinc-800 dark:border-zinc-700 dark:text-zinc-400 dark:hover:border-zinc-500 dark:hover:text-zinc-200"
                        >
                            <ExternalLink class="h-3.5 w-3.5" /> Ver relatório
                        </a>
                        <button
                            type="button"
                            :disabled="disapprovingId === req.id"
                            class="flex items-center gap-1.5 rounded-lg border border-red-200 px-3 py-1.5 text-xs font-medium text-red-500 transition hover:border-red-400 hover:bg-red-50 disabled:opacity-50 dark:border-red-800 dark:text-red-400 dark:hover:border-red-600 dark:hover:bg-red-950/30"
                            @click="disapprove(req)"
                        >
                            <XCircle class="h-3.5 w-3.5" />
                            {{ disapprovingId === req.id ? '...' : 'Desaprovar' }}
                        </button>
                        <button
                            type="button"
                            :disabled="deletingId === req.id"
                            title="Apagar relatório"
                            class="flex items-center gap-1.5 rounded-lg border border-red-200 px-2.5 py-1.5 text-xs font-medium text-red-500 transition hover:border-red-400 hover:bg-red-50 disabled:opacity-50 dark:border-red-800 dark:text-red-400 dark:hover:border-red-600 dark:hover:bg-red-950/30"
                            @click="deleteRequest(req)"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                        </button>
                        <span class="flex items-center gap-1.5 rounded-full bg-emerald-100 px-3 py-1 text-xs font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">
                            <CheckCircle2 class="h-3.5 w-3.5" /> Aprovado
                        </span>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
