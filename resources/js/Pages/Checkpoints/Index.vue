<script setup>
import { ref, watch, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import LayoutInfoprodutor from '@/Layouts/LayoutInfoprodutor.vue';
import Button from '@/components/ui/Button.vue';
import {
    ClipboardCheck, Plus, Pencil, Trash2, X, Loader2,
    Search, Eye, EyeOff, GripVertical, BarChart3,
    Users, CheckCircle2, ChevronDown, ChevronUp,
    AlignLeft, AlignJustify, List, CheckSquare,
    Sliders, Calendar, PlusCircle, MoveUp, MoveDown,
    Lock
} from 'lucide-vue-next';

defineOptions({ layout: LayoutInfoprodutor });

const props = defineProps({
    checkpoints: { type: Object, default: () => ({ data: [] }) },
    stats:       { type: Object, default: () => ({}) },
    steps:       { type: Array, default: () => [] },
    q:           { type: String, default: null },
});

// ─── Busca ────────────────────────────────────────────────────────────────────
const search = ref(props.q ?? '');
let searchTimer = null;
watch(search, (v) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get('/checkpoints', v ? { q: v } : {}, { preserveState: true, replace: true });
    }, 400);
});

// ─── Toast ────────────────────────────────────────────────────────────────────
const toast = ref(null);
function showToast(msg, type = 'success') {
    toast.value = { msg, type };
    setTimeout(() => toast.value = null, 4000);
}
function csrfToken() { return document.querySelector('meta[name="csrf-token"]')?.content ?? ''; }
const h = () => ({ 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' });

// ─── Checkpoint modal ─────────────────────────────────────────────────────────
const cpModal     = ref(false);
const editingCp   = ref(null);
const savingCp    = ref(false);

const emptyCpForm = () => ({
    title: '', description: '', journey_step_id: '', unlock_next_step: false, is_active: true,
});
const cpForm = ref(emptyCpForm());

function openCreateCp() {
    editingCp.value = null;
    cpForm.value = emptyCpForm();
    cpModal.value = true;
}

function openEditCp(cp) {
    editingCp.value = cp;
    cpForm.value = {
        title:            cp.title ?? '',
        description:      cp.description ?? '',
        journey_step_id:  cp.journey_step_id ?? '',
        unlock_next_step: cp.unlock_next_step,
        is_active:        cp.is_active,
    };
    cpModal.value = true;
}

async function saveCp() {
    if (!cpForm.value.title.trim()) { showToast('Título é obrigatório.', 'error'); return; }
    savingCp.value = true;
    try {
        const payload = {
            ...cpForm.value,
            journey_step_id: cpForm.value.journey_step_id || null,
        };
        if (editingCp.value) {
            await window.axios.put(`/checkpoints/${editingCp.value.id}`, payload, { headers: h() });
            showToast('Checkpoint atualizado.');
        } else {
            await window.axios.post('/checkpoints', payload, { headers: h() });
            showToast('Checkpoint criado.');
        }
        cpModal.value = false;
        router.reload({ only: ['checkpoints', 'stats'] });
    } catch (e) {
        showToast(e.response?.data?.message ?? 'Erro ao salvar.', 'error');
    } finally {
        savingCp.value = false;
    }
}

async function toggleCp(cp) {
    try {
        await window.axios.put(`/checkpoints/${cp.id}/toggle-active`, {}, { headers: h() });
        router.reload({ only: ['checkpoints', 'stats'] });
    } catch { showToast('Erro.', 'error'); }
}

async function destroyCp(cp) {
    if (!confirm(`Excluir "${cp.title}"? Todas as respostas serão perdidas.`)) return;
    try {
        await window.axios.delete(`/checkpoints/${cp.id}`, { headers: h() });
        if (activeCheckpoint.value?.id === cp.id) closePanel();
        showToast('Checkpoint excluído.');
        router.reload({ only: ['checkpoints', 'stats'] });
    } catch { showToast('Erro.', 'error'); }
}

// ─── Painel lateral (form builder + respostas) ────────────────────────────────
const activeCheckpoint = ref(null);
const panelTab         = ref('questions'); // 'questions' | 'responses'
const loadingPanel     = ref(false);
const cpQuestions      = ref([]);
const cpResponses      = ref(null); // { responses, questions, analysis, total }

async function openPanel(cp, tab = 'questions') {
    activeCheckpoint.value = cp;
    panelTab.value = tab;
    loadingPanel.value = true;
    try {
        const { data } = await window.axios.get(`/checkpoints/${cp.id}`, { headers: h() });
        cpQuestions.value = data.checkpoint.questions ?? [];
    } catch { showToast('Erro ao carregar.', 'error'); }
    finally { loadingPanel.value = false; }

    if (tab === 'responses') await loadResponses(cp.id);
}

async function loadResponses(id) {
    try {
        const { data } = await window.axios.get(`/checkpoints/${id}/responses`, { headers: h() });
        cpResponses.value = data;
    } catch { cpResponses.value = null; }
}

async function switchTab(tab) {
    panelTab.value = tab;
    if (tab === 'responses' && !cpResponses.value) {
        await loadResponses(activeCheckpoint.value.id);
    }
}

function closePanel() {
    activeCheckpoint.value = null;
    cpQuestions.value = [];
    cpResponses.value = null;
    qModal.value = false;
}

// ─── Questões ─────────────────────────────────────────────────────────────────
const qModal    = ref(false);
const editingQ  = ref(null);
const savingQ   = ref(false);

const questionTypes = [
    { key: 'text',      label: 'Texto curto',    icon: AlignLeft },
    { key: 'textarea',  label: 'Texto longo',    icon: AlignJustify },
    { key: 'radio',     label: 'Escolha única',  icon: List },
    { key: 'checkbox',  label: 'Múltipla escolha', icon: CheckSquare },
    { key: 'scale',     label: 'Escala',         icon: Sliders },
    { key: 'date',      label: 'Data',           icon: Calendar },
];

const emptyQForm = () => ({
    label: '', type: 'text', options: [], required: false,
});
const qForm    = ref(emptyQForm());
const newOption = ref('');

function openCreateQ() {
    editingQ.value = null;
    qForm.value = emptyQForm();
    newOption.value = '';
    qModal.value = true;
}

function openEditQ(q) {
    editingQ.value = q;
    qForm.value = {
        label:    q.label,
        type:     q.type,
        options:  q.options ? [...q.options] : [],
        required: q.required,
    };
    newOption.value = '';
    qModal.value = true;
}

function addOption() {
    const v = newOption.value.trim();
    if (!v) return;
    qForm.value.options.push(v);
    newOption.value = '';
}

function removeOption(idx) {
    qForm.value.options.splice(idx, 1);
}

const needsOptions = computed(() => ['radio', 'checkbox'].includes(qForm.value.type));

async function saveQuestion() {
    if (!qForm.value.label.trim()) { showToast('Enunciado é obrigatório.', 'error'); return; }
    if (needsOptions.value && !qForm.value.options.length) {
        showToast('Adicione pelo menos uma opção.', 'error'); return;
    }
    savingQ.value = true;
    try {
        const payload = {
            label:    qForm.value.label,
            type:     qForm.value.type,
            required: qForm.value.required,
            options:  needsOptions.value ? qForm.value.options : null,
        };
        const cid = activeCheckpoint.value.id;

        if (editingQ.value) {
            const { data } = await window.axios.put(
                `/checkpoints/${cid}/questions/${editingQ.value.id}`,
                payload, { headers: h() }
            );
            const idx = cpQuestions.value.findIndex(q => q.id === editingQ.value.id);
            if (idx >= 0) cpQuestions.value[idx] = data.question;
            showToast('Questão atualizada.');
        } else {
            const { data } = await window.axios.post(
                `/checkpoints/${cid}/questions`,
                payload, { headers: h() }
            );
            cpQuestions.value.push(data.question);
            showToast('Questão adicionada.');
        }
        qModal.value = false;
        router.reload({ only: ['checkpoints'] });
    } catch (e) {
        showToast(e.response?.data?.message ?? 'Erro ao salvar.', 'error');
    } finally {
        savingQ.value = false;
    }
}

async function destroyQuestion(q) {
    if (!confirm(`Excluir questão "${q.label}"?`)) return;
    try {
        await window.axios.delete(
            `/checkpoints/${activeCheckpoint.value.id}/questions/${q.id}`,
            { headers: h() }
        );
        cpQuestions.value = cpQuestions.value.filter(x => x.id !== q.id);
        router.reload({ only: ['checkpoints'] });
        showToast('Questão excluída.');
    } catch { showToast('Erro.', 'error'); }
}

async function moveQuestion(q, dir) {
    const arr = cpQuestions.value;
    const idx = arr.findIndex(x => x.id === q.id);
    if (dir === 'up' && idx === 0) return;
    if (dir === 'down' && idx === arr.length - 1) return;
    const newArr = [...arr];
    const swapIdx = dir === 'up' ? idx - 1 : idx + 1;
    [newArr[idx], newArr[swapIdx]] = [newArr[swapIdx], newArr[idx]];
    cpQuestions.value = newArr;
    try {
        await window.axios.post(
            `/checkpoints/${activeCheckpoint.value.id}/questions/reorder`,
            { ids: newArr.map(x => x.id) },
            { headers: h() }
        );
    } catch { showToast('Erro ao reordenar.', 'error'); }
}

// ─── Helpers ─────────────────────────────────────────────────────────────────
const typeInfo = Object.fromEntries(questionTypes.map(t => [t.key, t]));

function questionTypeLabel(type) {
    return typeInfo[type]?.label ?? type;
}

function responseAnswerForQ(response, qId) {
    return response.answers?.find(a => a.checkpoint_question_id === qId)?.value ?? '—';
}

function fmtDate(d) {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('pt-BR', { day: '2-digit', month: 'short', year: 'numeric' });
}
</script>

<template>
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-violet-100 dark:bg-violet-900/30">
                    <ClipboardCheck class="h-5 w-5 text-violet-600 dark:text-violet-400" />
                </div>
                <div>
                    <h1 class="text-xl font-bold text-zinc-900 dark:text-white">Checkpoints</h1>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">Formulários de avaliação e progresso nas jornadas.</p>
                </div>
            </div>
            <Button @click="openCreateCp"><Plus class="mr-2 h-4 w-4" /> Novo checkpoint</Button>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-3 gap-4">
            <div class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
                <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Total</p>
                <p class="mt-1 text-2xl font-bold tabular-nums text-zinc-900 dark:text-white">{{ stats.total ?? 0 }}</p>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
                <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Ativos</p>
                <p class="mt-1 text-2xl font-bold tabular-nums text-violet-600 dark:text-violet-400">{{ stats.ativos ?? 0 }}</p>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
                <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Respostas</p>
                <p class="mt-1 text-2xl font-bold tabular-nums text-emerald-600 dark:text-emerald-400">{{ stats.respostas ?? 0 }}</p>
            </div>
        </div>

        <!-- Busca -->
        <div class="relative max-w-sm">
            <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" />
            <input v-model="search" type="text" placeholder="Buscar checkpoints..."
                class="w-full rounded-xl border border-zinc-200 bg-white py-2.5 pl-9 pr-4 text-sm text-zinc-900 placeholder-zinc-400 focus:border-violet-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:placeholder-zinc-500" />
        </div>

        <!-- Lista vazia -->
        <div v-if="!checkpoints.data?.length"
            class="flex flex-col items-center justify-center gap-3 rounded-2xl border-2 border-dashed border-zinc-200 py-20 dark:border-zinc-700">
            <ClipboardCheck class="h-12 w-12 text-zinc-300 dark:text-zinc-600" />
            <p class="text-zinc-400 dark:text-zinc-500">{{ search ? 'Nenhum checkpoint encontrado.' : 'Nenhum checkpoint criado ainda.' }}</p>
            <Button v-if="!search" variant="outline" @click="openCreateCp"><Plus class="mr-2 h-4 w-4" /> Criar primeiro</Button>
        </div>

        <!-- Tabela -->
        <div v-if="checkpoints.data?.length" class="hidden overflow-hidden rounded-2xl border border-zinc-200 dark:border-zinc-700 sm:block">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                <thead class="bg-zinc-50 dark:bg-zinc-800">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-zinc-500">Título</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-zinc-500">Etapa vinculada</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-zinc-500">Questões</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-zinc-500">Respostas</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-zinc-500">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 bg-white dark:divide-zinc-700 dark:bg-zinc-800/60">
                    <tr v-for="cp in checkpoints.data" :key="cp.id"
                        class="cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-700/50"
                        @click="openPanel(cp)">
                        <td class="px-4 py-3">
                            <div>
                                <p class="text-sm font-medium text-zinc-900 dark:text-white">{{ cp.title }}</p>
                                <p v-if="cp.unlock_next_step" class="mt-0.5 flex items-center gap-1 text-[11px] text-amber-600 dark:text-amber-400">
                                    <Lock class="h-3 w-3" /> Libera próxima etapa
                                </p>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm text-zinc-600 dark:text-zinc-300">
                            <span v-if="cp.journey_label" class="block text-xs text-zinc-400">{{ cp.journey_label }}</span>
                            <span v-if="cp.step_label">{{ cp.step_label }}</span>
                            <span v-else class="text-zinc-400">—</span>
                        </td>
                        <td class="px-4 py-3 text-sm tabular-nums text-zinc-700 dark:text-zinc-300">{{ cp.questions_count }}</td>
                        <td class="px-4 py-3 text-sm tabular-nums text-zinc-700 dark:text-zinc-300">{{ cp.responses_count }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold"
                                :class="cp.is_active
                                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'
                                    : 'bg-zinc-100 text-zinc-500 dark:bg-zinc-700 dark:text-zinc-400'">
                                {{ cp.is_active ? 'Ativo' : 'Inativo' }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1" @click.stop>
                                <button type="button" class="rounded-lg p-1.5 text-violet-500 hover:bg-violet-50 dark:hover:bg-violet-900/20"
                                    title="Respostas" @click="openPanel(cp, 'responses')">
                                    <BarChart3 class="h-4 w-4" />
                                </button>
                                <button type="button" class="rounded-lg p-1.5 text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-700"
                                    :title="cp.is_active ? 'Desativar' : 'Ativar'" @click="toggleCp(cp)">
                                    <EyeOff v-if="cp.is_active" class="h-4 w-4" />
                                    <Eye v-else class="h-4 w-4 text-emerald-500" />
                                </button>
                                <button type="button" class="rounded-lg p-1.5 text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-700"
                                    @click="openEditCp(cp)">
                                    <Pencil class="h-4 w-4" />
                                </button>
                                <button type="button" class="rounded-lg p-1.5 text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20"
                                    @click="destroyCp(cp)">
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Cards mobile -->
        <div v-if="checkpoints.data?.length" class="space-y-3 sm:hidden">
            <div v-for="cp in checkpoints.data" :key="cp.id"
                class="cursor-pointer rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800/50"
                @click="openPanel(cp)">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="font-medium text-zinc-900 dark:text-white">{{ cp.title }}</p>
                        <p v-if="cp.step_label" class="text-xs text-zinc-400 mt-0.5">{{ cp.step_label }}</p>
                    </div>
                    <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold"
                        :class="cp.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-zinc-100 text-zinc-500'">
                        {{ cp.is_active ? 'Ativo' : 'Inativo' }}
                    </span>
                </div>
                <div class="mt-2 flex gap-4 text-xs text-zinc-400">
                    <span>{{ cp.questions_count }} questões</span>
                    <span>{{ cp.responses_count }} respostas</span>
                </div>
            </div>
        </div>

        <!-- Paginação -->
        <div v-if="checkpoints.last_page > 1" class="flex items-center justify-center gap-2">
            <a v-for="link in checkpoints.links" :key="link.label" :href="link.url ?? '#'"
                class="rounded-lg border px-3 py-1.5 text-sm"
                :class="link.active
                    ? 'border-violet-500 bg-violet-50 font-semibold text-violet-700'
                    : link.url ? 'border-zinc-200 text-zinc-600 hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-400' : 'cursor-default border-transparent text-zinc-300'"
                v-html="link.label" />
        </div>
    </div>

    <!-- ─── Painel lateral ─────────────────────────────────────────────────────── -->
    <Teleport to="body">
        <div v-if="activeCheckpoint" class="fixed inset-0 z-[100000] flex justify-end" aria-modal="true">
            <div class="fixed inset-0 bg-zinc-900/40" @click="closePanel" />
            <aside class="relative flex h-full w-full max-w-xl flex-col bg-white shadow-2xl dark:bg-zinc-900 overflow-hidden">

                <!-- Header -->
                <div class="border-b border-zinc-100 px-5 py-4 dark:border-zinc-800">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-xs text-zinc-400 uppercase tracking-wide">Checkpoint</p>
                            <h2 class="font-semibold text-zinc-900 dark:text-white truncate">{{ activeCheckpoint.title }}</h2>
                            <p v-if="activeCheckpoint.step_label" class="text-xs text-zinc-500 mt-0.5">
                                {{ activeCheckpoint.journey_label }} → {{ activeCheckpoint.step_label }}
                            </p>
                        </div>
                        <button type="button" class="shrink-0 rounded-lg p-1.5 text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800"
                            @click="closePanel"><X class="h-5 w-5" /></button>
                    </div>
                    <!-- Tabs -->
                    <div class="mt-3 flex gap-1 rounded-xl bg-zinc-100 p-1 dark:bg-zinc-800">
                        <button type="button"
                            class="flex-1 rounded-lg py-1.5 text-sm font-medium transition"
                            :class="panelTab === 'questions'
                                ? 'bg-white text-zinc-900 shadow dark:bg-zinc-700 dark:text-white'
                                : 'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400'"
                            @click="switchTab('questions')">
                            Questões
                        </button>
                        <button type="button"
                            class="flex-1 rounded-lg py-1.5 text-sm font-medium transition"
                            :class="panelTab === 'responses'
                                ? 'bg-white text-zinc-900 shadow dark:bg-zinc-700 dark:text-white'
                                : 'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400'"
                            @click="switchTab('responses')">
                            Respostas
                            <span v-if="activeCheckpoint.responses_count" class="ml-1.5 inline-flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-violet-100 px-1 text-[10px] font-bold text-violet-700">
                                {{ activeCheckpoint.responses_count }}
                            </span>
                        </button>
                    </div>
                </div>

                <!-- Loading -->
                <div v-if="loadingPanel" class="flex flex-1 items-center justify-center">
                    <Loader2 class="h-7 w-7 animate-spin text-violet-500" />
                </div>

                <!-- ─── Tab Questões ───────────────────────────────────────────── -->
                <template v-else-if="panelTab === 'questions'">
                    <div class="flex items-center justify-between px-5 py-3 border-b border-zinc-100 dark:border-zinc-800">
                        <p class="text-sm text-zinc-500">{{ cpQuestions.length }} questão{{ cpQuestions.length !== 1 ? 'ões' : '' }}</p>
                        <Button size="sm" @click="openCreateQ"><Plus class="mr-1.5 h-3.5 w-3.5" /> Adicionar questão</Button>
                    </div>

                    <div v-if="!cpQuestions.length" class="flex flex-1 flex-col items-center justify-center gap-2 p-8 text-center">
                        <ClipboardCheck class="h-10 w-10 text-zinc-200 dark:text-zinc-700" />
                        <p class="text-sm text-zinc-400">Nenhuma questão. Adicione a primeira.</p>
                    </div>

                    <div v-else class="flex-1 overflow-y-auto p-4 space-y-2">
                        <div v-for="(q, idx) in cpQuestions" :key="q.id"
                            class="rounded-xl border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-800/50">
                            <div class="flex items-start gap-2">
                                <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-violet-100 text-[10px] font-bold text-violet-700 dark:bg-violet-900/40 dark:text-violet-300">
                                    {{ idx + 1 }}
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-zinc-900 dark:text-white">{{ q.label }}</p>
                                    <div class="mt-1 flex items-center gap-2">
                                        <span class="rounded-md bg-violet-100 px-1.5 py-0.5 text-[10px] font-semibold text-violet-700 dark:bg-violet-900/30 dark:text-violet-300">
                                            {{ questionTypeLabel(q.type) }}
                                        </span>
                                        <span v-if="q.required" class="text-[10px] text-red-500">Obrigatória</span>
                                    </div>
                                    <div v-if="q.options?.length" class="mt-1.5 flex flex-wrap gap-1">
                                        <span v-for="opt in q.options" :key="opt"
                                            class="rounded-full border border-zinc-200 px-2 py-0.5 text-[10px] text-zinc-500 dark:border-zinc-700">
                                            {{ opt }}
                                        </span>
                                    </div>
                                </div>
                                <div class="flex shrink-0 items-center gap-0.5">
                                    <button type="button" :disabled="idx === 0"
                                        class="rounded p-1 text-zinc-400 hover:text-zinc-600 disabled:opacity-30"
                                        @click="moveQuestion(q, 'up')"><MoveUp class="h-3.5 w-3.5" /></button>
                                    <button type="button" :disabled="idx === cpQuestions.length - 1"
                                        class="rounded p-1 text-zinc-400 hover:text-zinc-600 disabled:opacity-30"
                                        @click="moveQuestion(q, 'down')"><MoveDown class="h-3.5 w-3.5" /></button>
                                    <button type="button" class="rounded p-1 text-zinc-400 hover:text-zinc-600"
                                        @click="openEditQ(q)"><Pencil class="h-3.5 w-3.5" /></button>
                                    <button type="button" class="rounded p-1 text-red-400 hover:text-red-600"
                                        @click="destroyQuestion(q)"><Trash2 class="h-3.5 w-3.5" /></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- ─── Tab Respostas ──────────────────────────────────────────── -->
                <template v-else-if="panelTab === 'responses'">
                    <div v-if="!cpResponses" class="flex flex-1 items-center justify-center">
                        <Loader2 class="h-7 w-7 animate-spin text-violet-500" />
                    </div>
                    <div v-else class="flex-1 overflow-y-auto p-4 space-y-6">
                        <!-- Análise por pergunta -->
                        <div v-if="cpResponses.analysis?.length">
                            <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-zinc-400">Análise por questão</p>
                            <div class="space-y-4">
                                <div v-for="a in cpResponses.analysis" :key="a.id"
                                    class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                                    <p class="text-sm font-medium text-zinc-900 dark:text-white">{{ a.label }}</p>
                                    <p class="text-xs text-zinc-400 mt-0.5">{{ a.total }} respostas</p>

                                    <!-- Distribuição para radio/checkbox/scale -->
                                    <div v-if="a.distribution && Object.keys(a.distribution).length" class="mt-3 space-y-1.5">
                                        <div v-for="(count, val) in a.distribution" :key="val" class="flex items-center gap-2">
                                            <span class="w-28 truncate text-xs text-zinc-600 dark:text-zinc-400">{{ val }}</span>
                                            <div class="flex-1 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800" style="height:6px">
                                                <div class="h-full rounded-full bg-violet-500"
                                                    :style="`width:${a.total > 0 ? Math.round((count / a.total) * 100) : 0}%`" />
                                            </div>
                                            <span class="w-8 text-right text-xs tabular-nums text-zinc-500">{{ count }}</span>
                                        </div>
                                    </div>
                                    <p v-else-if="!a.distribution" class="mt-1 text-xs text-zinc-400">Questão aberta — ver respostas individuais abaixo.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Lista de respondentes -->
                        <div v-if="cpResponses.responses?.length">
                            <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-zinc-400">
                                Respondentes ({{ cpResponses.total }})
                            </p>
                            <div class="space-y-3">
                                <div v-for="r in cpResponses.responses" :key="r.id"
                                    class="rounded-xl border border-zinc-200 p-3 dark:border-zinc-700">
                                    <div class="flex items-center gap-2">
                                        <div class="flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-full bg-violet-100 dark:bg-violet-900/40">
                                            <img v-if="r.user?.avatar" :src="r.user.avatar" class="h-full w-full object-cover" />
                                            <span v-else class="text-xs font-bold text-violet-700 dark:text-violet-300">
                                                {{ r.user?.name?.[0] ?? '?' }}
                                            </span>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-medium text-zinc-900 dark:text-white truncate">{{ r.user?.name ?? 'Desconhecido' }}</p>
                                            <p class="text-xs text-zinc-400">{{ fmtDate(r.completed_at) }}</p>
                                        </div>
                                        <CheckCircle2 class="h-4 w-4 shrink-0 text-emerald-500" />
                                    </div>
                                    <!-- Respostas abertas -->
                                    <div v-if="r.answers?.length" class="mt-2 space-y-1 border-t border-zinc-100 pt-2 dark:border-zinc-800">
                                        <div v-for="a in r.answers" :key="a.id" class="text-xs">
                                            <span class="text-zinc-400">{{ cpResponses.questions?.find(q => q.id === a.checkpoint_question_id)?.label ?? '' }}: </span>
                                            <span class="text-zinc-700 dark:text-zinc-300">{{ a.value || '—' }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-else class="flex flex-col items-center gap-2 py-10 text-center">
                            <Users class="h-10 w-10 text-zinc-200 dark:text-zinc-700" />
                            <p class="text-sm text-zinc-400">Nenhuma resposta ainda.</p>
                        </div>
                    </div>
                </template>
            </aside>
        </div>
    </Teleport>

    <!-- ─── Modal Checkpoint ───────────────────────────────────────────────────── -->
    <Teleport to="body">
        <div v-if="cpModal" class="fixed inset-0 z-[200000] flex items-center justify-center bg-black/50 p-4" @click.self="cpModal = false">
            <div class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-zinc-900">
                <div class="flex items-center justify-between border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                    <h3 class="font-semibold text-zinc-900 dark:text-white">
                        {{ editingCp ? 'Editar checkpoint' : 'Novo checkpoint' }}
                    </h3>
                    <button type="button" class="rounded-lg p-1 text-zinc-400" @click="cpModal = false"><X class="h-4 w-4" /></button>
                </div>

                <div class="max-h-[65vh] space-y-4 overflow-y-auto p-5">
                    <!-- Título -->
                    <div>
                        <label class="mb-1 block text-xs font-medium text-zinc-500">Título *</label>
                        <input v-model="cpForm.title" type="text" placeholder="Nome do checkpoint"
                            class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm focus:border-violet-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                    </div>

                    <!-- Descrição -->
                    <div>
                        <label class="mb-1 block text-xs font-medium text-zinc-500">Descrição / Instrução</label>
                        <textarea v-model="cpForm.description" rows="3"
                            placeholder="Instruções exibidas ao aluno antes de responder..."
                            class="w-full resize-none rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm focus:border-violet-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                    </div>

                    <!-- Etapa de jornada -->
                    <div>
                        <label class="mb-1 block text-xs font-medium text-zinc-500">Etapa de jornada</label>
                        <select v-model="cpForm.journey_step_id"
                            class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm focus:border-violet-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white">
                            <option value="">Nenhuma (checkpoint avulso)</option>
                            <option v-for="s in steps" :key="s.id" :value="s.id">{{ s.label }}</option>
                        </select>
                    </div>

                    <!-- Desbloquear próxima etapa -->
                    <label v-if="cpForm.journey_step_id" class="flex cursor-pointer items-start gap-3">
                        <button type="button"
                            class="mt-0.5 relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition-colors"
                            :class="cpForm.unlock_next_step ? 'bg-amber-500' : 'bg-zinc-300 dark:bg-zinc-600'"
                            @click="cpForm.unlock_next_step = !cpForm.unlock_next_step">
                            <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow transition-transform"
                                :class="cpForm.unlock_next_step ? 'translate-x-4' : 'translate-x-1'" />
                        </button>
                        <div>
                            <p class="text-sm text-zinc-700 dark:text-zinc-300">Liberar próxima etapa ao completar</p>
                            <p class="text-xs text-zinc-400">Ao responder este checkpoint, a etapa seguinte da jornada é desbloqueada.</p>
                        </div>
                    </label>

                    <!-- Status -->
                    <label class="flex cursor-pointer items-center gap-3">
                        <button type="button"
                            class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                            :class="cpForm.is_active ? 'bg-violet-600' : 'bg-zinc-300 dark:bg-zinc-600'"
                            @click="cpForm.is_active = !cpForm.is_active">
                            <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"
                                :class="cpForm.is_active ? 'translate-x-6' : 'translate-x-1'" />
                        </button>
                        <span class="text-sm text-zinc-700 dark:text-zinc-300">{{ cpForm.is_active ? 'Ativo' : 'Inativo' }}</span>
                    </label>
                </div>

                <div class="flex justify-end gap-2 border-t border-zinc-200 px-5 py-3 dark:border-zinc-700">
                    <Button variant="outline" @click="cpModal = false">Cancelar</Button>
                    <Button :disabled="savingCp" @click="saveCp">
                        <Loader2 v-if="savingCp" class="mr-2 h-3.5 w-3.5 animate-spin" />
                        {{ editingCp ? 'Salvar' : 'Criar' }}
                    </Button>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- ─── Modal Questão ─────────────────────────────────────────────────────── -->
    <Teleport to="body">
        <div v-if="qModal" class="fixed inset-0 z-[300000] flex items-center justify-center bg-black/60 p-4" @click.self="qModal = false">
            <div class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-zinc-900">
                <div class="flex items-center justify-between border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                    <h3 class="font-semibold text-zinc-900 dark:text-white">
                        {{ editingQ ? 'Editar questão' : 'Nova questão' }}
                    </h3>
                    <button type="button" class="rounded-lg p-1 text-zinc-400" @click="qModal = false"><X class="h-4 w-4" /></button>
                </div>

                <div class="max-h-[70vh] space-y-4 overflow-y-auto p-5">
                    <!-- Enunciado -->
                    <div>
                        <label class="mb-1 block text-xs font-medium text-zinc-500">Enunciado *</label>
                        <textarea v-model="qForm.label" rows="2"
                            placeholder="Ex: Como você avalia seu estado emocional esta semana?"
                            class="w-full resize-none rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm focus:border-violet-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                    </div>

                    <!-- Tipo -->
                    <div>
                        <label class="mb-2 block text-xs font-medium text-zinc-500">Tipo de resposta</label>
                        <div class="grid grid-cols-3 gap-2">
                            <button v-for="t in questionTypes" :key="t.key" type="button"
                                class="flex flex-col items-center gap-1 rounded-xl border py-2.5 text-xs font-medium transition"
                                :class="qForm.type === t.key
                                    ? 'border-violet-500 bg-violet-50 text-violet-700 dark:bg-violet-900/20 dark:text-violet-300'
                                    : 'border-zinc-200 text-zinc-600 hover:border-zinc-300 dark:border-zinc-700 dark:text-zinc-400'"
                                @click="qForm.type = t.key; qForm.options = []">
                                <component :is="t.icon" class="h-4 w-4" />
                                {{ t.label }}
                            </button>
                        </div>
                    </div>

                    <!-- Opções (radio / checkbox) -->
                    <div v-if="needsOptions">
                        <label class="mb-2 block text-xs font-medium text-zinc-500">Opções de resposta</label>
                        <div class="space-y-1.5">
                            <div v-for="(opt, idx) in qForm.options" :key="idx"
                                class="flex items-center gap-2">
                                <span class="flex-1 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-1.5 text-sm text-zinc-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">{{ opt }}</span>
                                <button type="button" class="text-red-400 hover:text-red-600" @click="removeOption(idx)">
                                    <X class="h-4 w-4" />
                                </button>
                            </div>
                            <div class="flex gap-2">
                                <input v-model="newOption" type="text" placeholder="Nova opção..."
                                    class="flex-1 rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm focus:border-violet-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                                    @keydown.enter.prevent="addOption" />
                                <button type="button"
                                    class="flex items-center gap-1 rounded-xl border border-violet-300 px-3 py-2 text-xs font-medium text-violet-700 hover:bg-violet-50"
                                    @click="addOption">
                                    <PlusCircle class="h-3.5 w-3.5" /> Adicionar
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Obrigatória -->
                    <label class="flex cursor-pointer items-center gap-3">
                        <input type="checkbox" v-model="qForm.required"
                            class="h-4 w-4 rounded border-zinc-300 text-violet-600 focus:ring-violet-500" />
                        <span class="text-sm text-zinc-700 dark:text-zinc-300">Resposta obrigatória</span>
                    </label>
                </div>

                <div class="flex justify-end gap-2 border-t border-zinc-200 px-5 py-3 dark:border-zinc-700">
                    <Button variant="outline" @click="qModal = false">Cancelar</Button>
                    <Button :disabled="savingQ" @click="saveQuestion">
                        <Loader2 v-if="savingQ" class="mr-2 h-3.5 w-3.5 animate-spin" />
                        {{ editingQ ? 'Salvar' : 'Adicionar' }}
                    </Button>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- Toast -->
    <Teleport to="body">
        <Transition enter-active-class="transition duration-200" enter-from-class="translate-y-2 opacity-0"
            leave-active-class="transition duration-150" leave-to-class="translate-y-2 opacity-0">
            <div v-if="toast"
                class="fixed bottom-6 right-6 z-[99999] rounded-xl border px-4 py-3 text-sm shadow-lg"
                :class="toast.type === 'error'
                    ? 'border-red-200 bg-red-50 text-red-800 dark:border-red-900/50 dark:bg-red-900/20 dark:text-red-200'
                    : 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-900/20 dark:text-emerald-200'">
                {{ toast.msg }}
            </div>
        </Transition>
    </Teleport>
</template>
