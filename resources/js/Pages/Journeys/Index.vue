<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import LayoutInfoprodutor from '@/Layouts/LayoutInfoprodutor.vue';
import Button from '@/components/ui/Button.vue';
import {
    MapPin, Plus, Pencil, Trash2, X, Loader2, Search,
    Eye, EyeOff, PlayCircle, HelpCircle, MessageSquare,
    ChevronUp, ChevronDown, Settings,
} from 'lucide-vue-next';

defineOptions({ layout: LayoutInfoprodutor });

const props = defineProps({
    journeys: { type: Object, default: () => ({ data: [] }) },
    stats:    { type: Object, default: () => ({}) },
    q:        { type: String, default: null },
});

// ─── Busca ────────────────────────────────────────────────────────────────────
const search = ref(props.q ?? '');
let searchTimer = null;
watch(search, (v) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get('/jornadas', v ? { q: v } : {}, { preserveState: true, replace: true });
    }, 400);
});

// ─── Toast ────────────────────────────────────────────────────────────────────
const toast = ref(null);
function showToast(msg, type = 'success') {
    toast.value = { msg, type };
    setTimeout(() => (toast.value = null), 4000);
}
function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}
const h = () => ({ 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' });

// ─── Jornada modal ────────────────────────────────────────────────────────────
const journeyModal  = ref(false);
const editingJourney = ref(null);
const savingJourney  = ref(false);
const coverPreview   = ref('');
const coverInputRef  = ref(null);

const emptyJourneyForm = () => ({ name: '', description: '', is_active: true, cover: null });
const journeyForm = ref(emptyJourneyForm());

function openCreateJourney() {
    editingJourney.value = null;
    journeyForm.value    = emptyJourneyForm();
    coverPreview.value   = '';
    journeyModal.value   = true;
}

function openEditJourney(j) {
    editingJourney.value = j;
    journeyForm.value    = { name: j.name ?? '', description: j.description ?? '', is_active: j.is_active, cover: null };
    coverPreview.value   = j.cover_url ?? '';
    journeyModal.value   = true;
}

function onCover(e) {
    const f = e.target?.files?.[0];
    if (!f) return;
    journeyForm.value.cover = f;
    coverPreview.value = URL.createObjectURL(f);
}
function clearCover() {
    journeyForm.value.cover = null;
    coverPreview.value = '';
    if (coverInputRef.value) coverInputRef.value.value = '';
}

async function saveJourney() {
    if (!journeyForm.value.name.trim()) { showToast('Nome é obrigatório.', 'error'); return; }
    savingJourney.value = true;
    try {
        const fd = new FormData();
        fd.append('name', journeyForm.value.name);
        if (journeyForm.value.description) fd.append('description', journeyForm.value.description);
        fd.append('is_active', journeyForm.value.is_active ? '1' : '0');
        if (journeyForm.value.cover) fd.append('cover', journeyForm.value.cover);

        if (editingJourney.value) {
            fd.append('_method', 'PUT');
            await window.axios.post(`/jornadas/${editingJourney.value.id}`, fd, { headers: h() });
            showToast('Jornada atualizada.');
        } else {
            await window.axios.post('/jornadas', fd, { headers: h() });
            showToast('Jornada criada.');
        }
        journeyModal.value = false;
        router.reload({ only: ['journeys', 'stats'] });
    } catch (e) {
        showToast(e.response?.data?.message ?? 'Erro ao salvar.', 'error');
    } finally {
        savingJourney.value = false;
    }
}

async function toggleJourney(j) {
    try {
        await window.axios.put(`/jornadas/${j.id}/toggle-active`, {}, { headers: h() });
        router.reload({ only: ['journeys', 'stats'] });
    } catch { showToast('Erro.', 'error'); }
}

async function destroyJourney(j) {
    if (!confirm(`Excluir "${j.name}"?`)) return;
    try {
        await window.axios.delete(`/jornadas/${j.id}`, { headers: h() });
        showToast('Jornada excluída.');
        router.reload({ only: ['journeys', 'stats'] });
    } catch { showToast('Erro ao excluir.', 'error'); }
}

// ─── Gerenciador de etapas ────────────────────────────────────────────────────
const stepManagerOpen = ref(false);
const managerJourney  = ref(null);
const steps           = ref([]);
const loadingSteps    = ref(false);

// Step form
const editingStep      = ref(null);
const stepForm         = ref({ title: '', description: '', unlock_type: 'livre' });
const stepFormVisible  = ref(false);
const savingStep       = ref(false);

// Item form
const itemFormStepId  = ref(null);
const editingItem     = ref(null);
const itemFormVisible = ref(false);
const savingItem      = ref(false);
const itemType        = ref('video');
const itemTitle       = ref('');
const itemVideoUrl    = ref('');
const itemQuestion    = ref('');
const itemOptions     = ref([{ text: '', is_correct: true }, { text: '', is_correct: false }]);
const itemExplanation = ref('');
const itemPlaceholder = ref('');

async function openStepManager(j) {
    managerJourney.value  = j;
    steps.value           = [];
    stepFormVisible.value = false;
    itemFormVisible.value = false;
    stepManagerOpen.value = true;
    loadingSteps.value    = true;
    try {
        const r = await window.axios.get(`/jornadas/${j.id}`, { headers: { Accept: 'application/json' } });
        steps.value = r.data.journey.steps ?? [];
    } catch { showToast('Erro ao carregar etapas.', 'error'); }
    loadingSteps.value = false;
}

function closeStepManager() {
    stepManagerOpen.value = false;
    managerJourney.value  = null;
    steps.value           = [];
    stepFormVisible.value = false;
    itemFormVisible.value = false;
}

// ─── Step CRUD ─────────────────────────────────────────────────────────────
function openAddStep() {
    editingStep.value    = null;
    stepForm.value       = { title: '', description: '', unlock_type: 'livre' };
    stepFormVisible.value = true;
    itemFormVisible.value = false;
}
function openEditStep(s) {
    editingStep.value    = s;
    stepForm.value       = { title: s.title, description: s.description ?? '', unlock_type: s.unlock_type ?? 'livre' };
    stepFormVisible.value = true;
    itemFormVisible.value = false;
}
function cancelStepForm() { stepFormVisible.value = false; editingStep.value = null; }

async function saveStep() {
    if (!stepForm.value.title.trim()) { showToast('Título é obrigatório.', 'error'); return; }
    savingStep.value = true;
    try {
        const payload = { ...stepForm.value };
        let r;
        if (editingStep.value) {
            r = await window.axios.post(`/jornadas/${managerJourney.value.id}/steps/${editingStep.value.id}`,
                { ...payload, _method: 'PUT' }, { headers: h() });
            const idx = steps.value.findIndex((x) => x.id === editingStep.value.id);
            if (idx >= 0) steps.value[idx] = r.data.step;
            showToast('Etapa atualizada.');
        } else {
            r = await window.axios.post(`/jornadas/${managerJourney.value.id}/steps`, payload, { headers: h() });
            steps.value.push(r.data.step);
            showToast('Etapa criada.');
        }
        stepFormVisible.value = false;
        editingStep.value     = null;
    } catch (e) {
        showToast(e.response?.data?.message ?? 'Erro ao salvar etapa.', 'error');
    } finally {
        savingStep.value = false;
    }
}

async function deleteStep(s) {
    if (!confirm(`Excluir etapa "${s.title}"?`)) return;
    try {
        await window.axios.delete(`/jornadas/${managerJourney.value.id}/steps/${s.id}`, { headers: h() });
        steps.value = steps.value.filter((x) => x.id !== s.id);
        showToast('Etapa excluída.');
    } catch { showToast('Erro ao excluir etapa.', 'error'); }
}

async function moveStep(idx, dir) {
    const arr    = [...steps.value];
    const newIdx = idx + dir;
    if (newIdx < 0 || newIdx >= arr.length) return;
    [arr[idx], arr[newIdx]] = [arr[newIdx], arr[idx]];
    steps.value = arr;
    try {
        await window.axios.post(`/jornadas/${managerJourney.value.id}/steps/reorder`,
            { ids: arr.map((s) => s.id) }, { headers: h() });
    } catch { showToast('Erro ao reordenar.', 'error'); }
}

// ─── Item CRUD ─────────────────────────────────────────────────────────────
function openAddItem(stepId) {
    itemFormStepId.value  = stepId;
    editingItem.value     = null;
    itemType.value        = 'video';
    itemTitle.value       = '';
    itemVideoUrl.value    = '';
    itemQuestion.value    = '';
    itemOptions.value     = [{ text: '', is_correct: true }, { text: '', is_correct: false }];
    itemExplanation.value = '';
    itemPlaceholder.value = '';
    itemFormVisible.value = true;
    stepFormVisible.value = false;
}

function openEditItem(stepId, item) {
    itemFormStepId.value  = stepId;
    editingItem.value     = item;
    itemType.value        = item.type;
    itemTitle.value       = item.title ?? '';
    itemVideoUrl.value    = item.type === 'video'     ? (item.data?.url         ?? '') : '';
    itemQuestion.value    = item.type !== 'video'     ? (item.data?.question    ?? '') : '';
    itemOptions.value     = item.type === 'quiz'      ? (item.data?.options ?? []).map((o) => ({ ...o })) : [{ text: '', is_correct: true }, { text: '', is_correct: false }];
    itemExplanation.value = item.type === 'quiz'      ? (item.data?.explanation  ?? '') : '';
    itemPlaceholder.value = item.type === 'question'  ? (item.data?.placeholder  ?? '') : '';
    itemFormVisible.value = true;
    stepFormVisible.value = false;
}

function cancelItemForm() {
    itemFormVisible.value = false;
    editingItem.value     = null;
    itemFormStepId.value  = null;
}

function addOption()          { itemOptions.value.push({ text: '', is_correct: false }); }
function removeOption(idx)    { if (itemOptions.value.length > 2) itemOptions.value.splice(idx, 1); }
function setCorrect(idx)      { itemOptions.value.forEach((o, i) => (o.is_correct = i === idx)); }

function buildItemData() {
    if (itemType.value === 'video')    return { url: itemVideoUrl.value.trim() };
    if (itemType.value === 'quiz')     return { question: itemQuestion.value.trim(), options: itemOptions.value.map((o) => ({ text: o.text.trim(), is_correct: o.is_correct })), explanation: itemExplanation.value.trim() || null };
    if (itemType.value === 'question') return { question: itemQuestion.value.trim(), placeholder: itemPlaceholder.value.trim() || null };
    return {};
}

async function saveItem() {
    if (itemType.value === 'video' && !itemVideoUrl.value.trim()) { showToast('URL do vídeo é obrigatória.', 'error'); return; }
    if (itemType.value !== 'video' && !itemQuestion.value.trim()) { showToast('Enunciado é obrigatório.', 'error'); return; }
    if (itemType.value === 'quiz') {
        if (itemOptions.value.some((o) => !o.text.trim())) { showToast('Preencha todas as opções.', 'error'); return; }
        if (!itemOptions.value.some((o) => o.is_correct))  { showToast('Marque a opção correta.', 'error'); return; }
    }

    savingItem.value = true;
    const stepId  = itemFormStepId.value;
    const payload = { type: itemType.value, title: itemTitle.value.trim() || null, data: buildItemData() };

    try {
        let r;
        if (editingItem.value) {
            r = await window.axios.post(
                `/jornadas/${managerJourney.value.id}/steps/${stepId}/items/${editingItem.value.id}`,
                { ...payload, _method: 'PUT' }, { headers: h() });
            const step = steps.value.find((s) => s.id === stepId);
            if (step) {
                const idx = (step.items ?? []).findIndex((i) => i.id === editingItem.value.id);
                if (idx >= 0) step.items[idx] = r.data.item;
            }
            showToast('Item atualizado.');
        } else {
            r = await window.axios.post(
                `/jornadas/${managerJourney.value.id}/steps/${stepId}/items`, payload, { headers: h() });
            const step = steps.value.find((s) => s.id === stepId);
            if (step) step.items = [...(step.items ?? []), r.data.item];
            showToast('Item adicionado.');
        }
        itemFormVisible.value = false;
        editingItem.value     = null;
        itemFormStepId.value  = null;
    } catch (e) {
        showToast(e.response?.data?.message ?? 'Erro ao salvar item.', 'error');
    } finally {
        savingItem.value = false;
    }
}

async function deleteItem(step, item) {
    if (!confirm('Excluir este item?')) return;
    try {
        await window.axios.delete(
            `/jornadas/${managerJourney.value.id}/steps/${step.id}/items/${item.id}`, { headers: h() });
        step.items = (step.items ?? []).filter((i) => i.id !== item.id);
        showToast('Item excluído.');
    } catch { showToast('Erro ao excluir item.', 'error'); }
}

const typeLabels = { video: 'Vídeo', quiz: 'Quiz', question: 'Pergunta aberta' };
const typeIcon   = { video: PlayCircle, quiz: HelpCircle, question: MessageSquare };
const typeColor  = { video: 'text-emerald-500', quiz: 'text-purple-500', question: 'text-sky-500' };
</script>

<template>
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-100 dark:bg-indigo-900/30">
                    <MapPin class="h-5 w-5 text-indigo-600 dark:text-indigo-400" />
                </div>
                <div>
                    <h1 class="text-xl font-bold text-zinc-900 dark:text-white">Jornadas</h1>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">Caminhos de aprendizado com vídeos, quizzes e perguntas.</p>
                </div>
            </div>
            <Button @click="openCreateJourney"><Plus class="mr-2 h-4 w-4" /> Nova jornada</Button>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-3 gap-4">
            <div v-for="(val, key) in { Total: stats.total ?? 0, Ativas: stats.ativas ?? 0, Inativas: stats.inativas ?? 0 }" :key="key"
                class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
                <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ key }}</p>
                <p class="mt-1 text-2xl font-bold tabular-nums" :class="key === 'Ativas' ? 'text-indigo-600 dark:text-indigo-400' : key === 'Inativas' ? 'text-zinc-400' : 'text-zinc-900 dark:text-white'">{{ val }}</p>
            </div>
        </div>

        <!-- Busca -->
        <div class="relative max-w-sm">
            <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" />
            <input v-model="search" type="text" placeholder="Buscar jornadas..."
                class="w-full rounded-xl border border-zinc-200 bg-white py-2.5 pl-9 pr-4 text-sm text-zinc-900 placeholder-zinc-400 focus:border-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:placeholder-zinc-500" />
        </div>

        <!-- Lista vazia -->
        <div v-if="!journeys.data?.length"
            class="flex flex-col items-center justify-center gap-3 rounded-2xl border-2 border-dashed border-zinc-200 py-20 dark:border-zinc-700">
            <MapPin class="h-12 w-12 text-zinc-300 dark:text-zinc-600" />
            <p class="text-zinc-400 dark:text-zinc-500">{{ search ? 'Nenhuma jornada encontrada.' : 'Nenhuma jornada criada ainda.' }}</p>
            <Button v-if="!search" variant="outline" @click="openCreateJourney"><Plus class="mr-2 h-4 w-4" /> Criar primeira</Button>
        </div>

        <!-- Cards -->
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <div v-for="j in journeys.data" :key="j.id"
                class="group relative overflow-hidden rounded-2xl border bg-white transition hover:shadow-md dark:bg-zinc-800/60 border-zinc-200 dark:border-zinc-700">
                <!-- Capa -->
                <div class="relative h-32 overflow-hidden bg-gradient-to-br from-indigo-100 to-purple-100 dark:from-indigo-900/30 dark:to-purple-900/30">
                    <img v-if="j.cover_url" :src="j.cover_url" class="h-full w-full object-cover" />
                    <div v-else class="flex h-full items-center justify-center">
                        <MapPin class="h-10 w-10 text-indigo-300 dark:text-indigo-700" />
                    </div>
                    <div class="absolute bottom-2 left-2 flex gap-1.5">
                        <span class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase shadow-sm"
                            :class="j.is_active ? 'bg-emerald-500 text-white' : 'bg-zinc-600 text-zinc-200'">
                            {{ j.is_active ? 'Ativa' : 'Inativa' }}
                        </span>
                        <span class="rounded-full bg-black/50 px-2 py-0.5 text-[10px] font-semibold text-white shadow-sm">
                            {{ j.steps_count ?? 0 }} etapa{{ (j.steps_count ?? 0) !== 1 ? 's' : '' }}
                        </span>
                    </div>
                </div>

                <!-- Info -->
                <div class="p-4">
                    <h3 class="truncate font-semibold text-zinc-900 dark:text-white">{{ j.name }}</h3>
                    <p v-if="j.description" class="mt-1 line-clamp-2 text-xs text-zinc-500">{{ j.description }}</p>

                    <div class="mt-3 flex items-center justify-between gap-1.5">
                        <!-- Etapas button -->
                        <button type="button"
                            class="flex items-center gap-1.5 rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-100 dark:border-indigo-800/50 dark:bg-indigo-900/20 dark:text-indigo-300 dark:hover:bg-indigo-900/40"
                            @click="openStepManager(j)">
                            <Settings class="h-3.5 w-3.5" /> Etapas
                        </button>
                        <div class="flex gap-1">
                            <button type="button"
                                class="rounded-lg border border-zinc-200 p-1.5 text-zinc-500 hover:bg-zinc-100 dark:border-zinc-700 dark:hover:bg-zinc-700"
                                :title="j.is_active ? 'Desativar' : 'Ativar'" @click="toggleJourney(j)">
                                <EyeOff v-if="j.is_active" class="h-3.5 w-3.5" />
                                <Eye v-else class="h-3.5 w-3.5 text-emerald-500" />
                            </button>
                            <button type="button"
                                class="rounded-lg border border-zinc-200 p-1.5 text-zinc-500 hover:bg-zinc-100 dark:border-zinc-700 dark:hover:bg-zinc-700"
                                @click="openEditJourney(j)">
                                <Pencil class="h-3.5 w-3.5" />
                            </button>
                            <button type="button"
                                class="rounded-lg border border-red-200 p-1.5 text-red-500 hover:bg-red-50 dark:border-red-900/50 dark:hover:bg-red-900/20"
                                @click="destroyJourney(j)">
                                <Trash2 class="h-3.5 w-3.5" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Paginação -->
        <div v-if="journeys.last_page > 1" class="flex items-center justify-center gap-2">
            <a v-for="link in journeys.links" :key="link.label" :href="link.url ?? '#'"
                class="rounded-lg border px-3 py-1.5 text-sm"
                :class="link.active ? 'border-indigo-500 bg-indigo-50 font-semibold text-indigo-700' : link.url ? 'border-zinc-200 text-zinc-600 hover:bg-zinc-50' : 'cursor-default border-transparent text-zinc-300'"
                v-html="link.label" />
        </div>
    </div>

    <!-- ─── Modal Jornada ────────────────────────────────────────────────────── -->
    <Teleport to="body">
        <div v-if="journeyModal" class="fixed inset-0 z-[200000] flex items-center justify-center bg-black/50 p-4" @click.self="journeyModal = false">
            <div class="w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-zinc-900">
                <div class="flex items-center justify-between border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                    <h3 class="font-semibold text-zinc-900 dark:text-white">{{ editingJourney ? 'Editar jornada' : 'Nova jornada' }}</h3>
                    <button type="button" class="rounded-lg p-1 text-zinc-400 hover:text-zinc-600" @click="journeyModal = false"><X class="h-4 w-4" /></button>
                </div>

                <div class="max-h-[70vh] space-y-4 overflow-y-auto p-5">
                    <!-- Capa -->
                    <div>
                        <label class="mb-1 block text-xs font-medium text-zinc-500">Imagem de capa</label>
                        <div class="relative overflow-hidden rounded-xl border-2 border-dashed border-zinc-200 dark:border-zinc-700" :class="coverPreview ? 'h-36' : 'h-24'">
                            <img v-if="coverPreview" :src="coverPreview" class="h-full w-full object-cover" />
                            <label v-if="!coverPreview" class="flex h-full cursor-pointer flex-col items-center justify-center gap-1 text-zinc-400 hover:text-zinc-600">
                                <Plus class="h-6 w-6" />
                                <span class="text-xs">Adicionar capa</span>
                                <input ref="coverInputRef" type="file" accept="image/*" class="hidden" @change="onCover" />
                            </label>
                            <button v-if="coverPreview" type="button" class="absolute right-2 top-2 rounded-full bg-black/50 p-1 text-white" @click="clearCover"><X class="h-3.5 w-3.5" /></button>
                        </div>
                    </div>
                    <!-- Nome -->
                    <div>
                        <label class="mb-1 block text-xs font-medium text-zinc-500">Nome *</label>
                        <input v-model="journeyForm.name" type="text" placeholder="Nome da jornada"
                            class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm text-zinc-900 focus:border-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                    </div>
                    <!-- Descrição -->
                    <div>
                        <label class="mb-1 block text-xs font-medium text-zinc-500">Descrição</label>
                        <textarea v-model="journeyForm.description" rows="3" placeholder="Descreva o objetivo desta jornada..."
                            class="w-full resize-none rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm text-zinc-900 focus:border-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                    </div>
                    <!-- Status -->
                    <label class="flex cursor-pointer items-center gap-3">
                        <button type="button"
                            class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                            :class="journeyForm.is_active ? 'bg-indigo-600' : 'bg-zinc-300 dark:bg-zinc-600'"
                            @click="journeyForm.is_active = !journeyForm.is_active">
                            <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"
                                :class="journeyForm.is_active ? 'translate-x-6' : 'translate-x-1'" />
                        </button>
                        <span class="text-sm text-zinc-700 dark:text-zinc-300">{{ journeyForm.is_active ? 'Ativa' : 'Inativa' }}</span>
                    </label>
                </div>

                <div class="flex justify-end gap-2 border-t border-zinc-200 px-5 py-3 dark:border-zinc-700">
                    <Button variant="outline" @click="journeyModal = false">Cancelar</Button>
                    <Button :disabled="savingJourney" @click="saveJourney">
                        <Loader2 v-if="savingJourney" class="mr-2 h-3.5 w-3.5 animate-spin" />
                        {{ editingJourney ? 'Salvar' : 'Criar jornada' }}
                    </Button>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- ─── Gerenciador de etapas ─────────────────────────────────────────────── -->
    <Teleport to="body">
        <div v-if="stepManagerOpen" class="fixed inset-0 z-[200001] flex items-stretch justify-end bg-black/50">
            <!-- Drawer direita -->
            <div class="flex h-full w-full max-w-2xl flex-col bg-white shadow-2xl dark:bg-zinc-900">
                <!-- Header fixo -->
                <div class="flex shrink-0 items-center justify-between border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-zinc-400">Jornada</p>
                        <h3 class="font-semibold text-zinc-900 dark:text-white">{{ managerJourney?.name }}</h3>
                    </div>
                    <button type="button" class="rounded-lg p-1 text-zinc-400 hover:text-zinc-600" @click="closeStepManager"><X class="h-5 w-5" /></button>
                </div>

                <!-- Corpo com scroll -->
                <div class="flex-1 space-y-4 overflow-y-auto p-5">

                    <!-- Loading -->
                    <div v-if="loadingSteps" class="flex items-center justify-center py-12">
                        <Loader2 class="h-6 w-6 animate-spin text-indigo-500" />
                    </div>

                    <template v-else>
                        <!-- Botão nova etapa -->
                        <div class="flex justify-between">
                            <Button variant="outline" size="sm" @click="openAddStep">
                                <Plus class="mr-1.5 h-3.5 w-3.5" /> Nova etapa
                            </Button>
                        </div>

                        <!-- Form nova/editar etapa -->
                        <div v-if="stepFormVisible" class="rounded-xl border border-indigo-200 bg-indigo-50 p-4 dark:border-indigo-800/50 dark:bg-indigo-900/20">
                            <p class="mb-3 text-sm font-semibold text-indigo-700 dark:text-indigo-300">{{ editingStep ? 'Editar etapa' : 'Nova etapa' }}</p>
                            <div class="space-y-3">
                                <input v-model="stepForm.title" type="text" placeholder="Título da etapa *"
                                    class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                                <textarea v-model="stepForm.description" rows="2" placeholder="Descrição (opcional)"
                                    class="w-full resize-none rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                                <select v-model="stepForm.unlock_type"
                                    class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-indigo-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white">
                                    <option value="livre">Livre (qualquer ordem)</option>
                                    <option value="sequencial">Sequencial (completa a anterior primeiro)</option>
                                    <option value="manual">Manual (desbloqueio manual)</option>
                                </select>
                                <div class="flex gap-2">
                                    <Button size="sm" :disabled="savingStep" @click="saveStep">
                                        <Loader2 v-if="savingStep" class="mr-1.5 h-3 w-3 animate-spin" />
                                        {{ editingStep ? 'Salvar' : 'Criar etapa' }}
                                    </Button>
                                    <Button size="sm" variant="outline" @click="cancelStepForm">Cancelar</Button>
                                </div>
                            </div>
                        </div>

                        <!-- Form novo/editar item -->
                        <div v-if="itemFormVisible" class="rounded-xl border border-purple-200 bg-purple-50 p-4 dark:border-purple-800/50 dark:bg-purple-900/20">
                            <p class="mb-3 text-sm font-semibold text-purple-700 dark:text-purple-300">{{ editingItem ? 'Editar item' : 'Novo item' }}</p>

                            <!-- Tipo -->
                            <div class="mb-3 flex gap-2">
                                <button v-for="(lbl, tp) in typeLabels" :key="tp" type="button"
                                    class="flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-medium transition"
                                    :class="itemType === tp
                                        ? 'border-purple-500 bg-purple-500 text-white'
                                        : 'border-zinc-200 bg-white text-zinc-600 hover:border-zinc-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300'"
                                    @click="itemType = tp">
                                    <component :is="typeIcon[tp]" class="h-3.5 w-3.5" />
                                    {{ lbl }}
                                </button>
                            </div>

                            <div class="space-y-3">
                                <!-- Título opcional -->
                                <input v-model="itemTitle" type="text" placeholder="Título do item (opcional)"
                                    class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-purple-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />

                                <!-- Video fields -->
                                <template v-if="itemType === 'video'">
                                    <input v-model="itemVideoUrl" type="url" placeholder="URL do vídeo (YouTube, Vimeo ou direto) *"
                                        class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-purple-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                                </template>

                                <!-- Quiz fields -->
                                <template v-if="itemType === 'quiz'">
                                    <textarea v-model="itemQuestion" rows="2" placeholder="Enunciado da pergunta *"
                                        class="w-full resize-none rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-purple-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />

                                    <div class="space-y-2">
                                        <p class="text-xs font-medium text-zinc-500">Opções (marque a correta)</p>
                                        <div v-for="(opt, idx) in itemOptions" :key="idx" class="flex items-center gap-2">
                                            <button type="button"
                                                class="h-5 w-5 shrink-0 rounded-full border-2 transition"
                                                :class="opt.is_correct ? 'border-emerald-500 bg-emerald-500' : 'border-zinc-300 bg-white dark:border-zinc-600 dark:bg-zinc-800'"
                                                @click="setCorrect(idx)" />
                                            <input v-model="opt.text" type="text" :placeholder="`Opção ${idx + 1}`"
                                                class="flex-1 rounded-lg border border-zinc-200 bg-white px-3 py-1.5 text-sm text-zinc-900 focus:border-purple-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                                            <button v-if="itemOptions.length > 2" type="button" class="text-red-400 hover:text-red-600" @click="removeOption(idx)">
                                                <X class="h-4 w-4" />
                                            </button>
                                        </div>
                                        <button v-if="itemOptions.length < 5" type="button"
                                            class="text-xs font-medium text-purple-600 hover:text-purple-800 dark:text-purple-400"
                                            @click="addOption">+ Adicionar opção</button>
                                    </div>

                                    <textarea v-model="itemExplanation" rows="2" placeholder="Explicação após resposta (opcional)"
                                        class="w-full resize-none rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-purple-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                                </template>

                                <!-- Pergunta aberta fields -->
                                <template v-if="itemType === 'question'">
                                    <textarea v-model="itemQuestion" rows="2" placeholder="Enunciado da pergunta *"
                                        class="w-full resize-none rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-purple-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                                    <input v-model="itemPlaceholder" type="text" placeholder="Texto placeholder da resposta (opcional)"
                                        class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-purple-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                                </template>

                                <div class="flex gap-2">
                                    <Button size="sm" :disabled="savingItem" @click="saveItem">
                                        <Loader2 v-if="savingItem" class="mr-1.5 h-3 w-3 animate-spin" />
                                        {{ editingItem ? 'Salvar' : 'Adicionar' }}
                                    </Button>
                                    <Button size="sm" variant="outline" @click="cancelItemForm">Cancelar</Button>
                                </div>
                            </div>
                        </div>

                        <!-- Lista de etapas -->
                        <div v-if="steps.length === 0 && !stepFormVisible" class="rounded-xl border-2 border-dashed border-zinc-200 py-12 text-center text-sm text-zinc-400 dark:border-zinc-700">
                            Nenhuma etapa criada. Clique em "Nova etapa" para começar.
                        </div>

                        <div v-for="(step, idx) in steps" :key="step.id"
                            class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/50">
                            <!-- Step header -->
                            <div class="flex items-center gap-2 border-b border-zinc-100 bg-zinc-50 px-4 py-3 dark:border-zinc-700 dark:bg-zinc-800">
                                <div class="flex flex-1 items-center gap-2 overflow-hidden">
                                    <span class="shrink-0 rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-bold text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">
                                        {{ idx + 1 }}
                                    </span>
                                    <span class="truncate font-medium text-zinc-800 dark:text-white">{{ step.title }}</span>
                                    <span class="shrink-0 rounded-full bg-zinc-100 px-1.5 py-0.5 text-[10px] text-zinc-500 dark:bg-zinc-700 dark:text-zinc-400">
                                        {{ step.unlock_type }}
                                    </span>
                                </div>
                                <div class="flex shrink-0 items-center gap-1">
                                    <button type="button" class="rounded p-1 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-600 dark:hover:bg-zinc-700" :disabled="idx === 0" @click="moveStep(idx, -1)"><ChevronUp class="h-4 w-4" /></button>
                                    <button type="button" class="rounded p-1 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-600 dark:hover:bg-zinc-700" :disabled="idx === steps.length - 1" @click="moveStep(idx, 1)"><ChevronDown class="h-4 w-4" /></button>
                                    <button type="button" class="rounded p-1 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-600 dark:hover:bg-zinc-700" @click="openEditStep(step)"><Pencil class="h-3.5 w-3.5" /></button>
                                    <button type="button" class="rounded p-1 text-red-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20" @click="deleteStep(step)"><Trash2 class="h-3.5 w-3.5" /></button>
                                </div>
                            </div>

                            <!-- Step description -->
                            <p v-if="step.description" class="px-4 pt-3 text-xs text-zinc-500 dark:text-zinc-400">{{ step.description }}</p>

                            <!-- Items list -->
                            <div class="space-y-1 p-3">
                                <div v-if="!step.items?.length" class="text-center py-3 text-xs text-zinc-400">
                                    Nenhum item ainda.
                                </div>
                                <div v-for="item in step.items" :key="item.id"
                                    class="flex items-center gap-2 rounded-lg border border-zinc-100 bg-white px-3 py-2 dark:border-zinc-700 dark:bg-zinc-800">
                                    <component :is="typeIcon[item.type]" class="h-4 w-4 shrink-0" :class="typeColor[item.type]" />
                                    <div class="flex-1 overflow-hidden">
                                        <p class="truncate text-sm font-medium text-zinc-800 dark:text-white">
                                            {{ item.title || typeLabels[item.type] }}
                                        </p>
                                        <p class="text-xs text-zinc-400">{{ typeLabels[item.type] }}</p>
                                    </div>
                                    <div class="flex shrink-0 gap-1">
                                        <button type="button" class="rounded p-1 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-600 dark:hover:bg-zinc-700" @click="openEditItem(step.id, item)">
                                            <Pencil class="h-3.5 w-3.5" />
                                        </button>
                                        <button type="button" class="rounded p-1 text-red-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20" @click="deleteItem(step, item)">
                                            <Trash2 class="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Add item buttons -->
                            <div class="flex gap-2 border-t border-zinc-100 px-3 py-2 dark:border-zinc-700">
                                <button v-for="(lbl, tp) in typeLabels" :key="tp" type="button"
                                    class="flex items-center gap-1 rounded-lg border border-zinc-200 bg-white px-2.5 py-1 text-xs font-medium text-zinc-600 hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-400 dark:hover:bg-indigo-900/20"
                                    @click="openAddItem(step.id); itemType = tp">
                                    <component :is="typeIcon[tp]" class="h-3 w-3" :class="typeColor[tp]" />
                                    + {{ lbl }}
                                </button>
                            </div>
                        </div>
                    </template>
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
