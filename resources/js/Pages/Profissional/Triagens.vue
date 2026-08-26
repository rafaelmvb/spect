<script setup>
import { ref, computed, reactive } from 'vue';
import LayoutProfissional from '@/Layouts/LayoutProfissional.vue';
import {
    ClipboardList, Clock, Search, Plus, Pencil, Trash2,
    ChevronDown, ChevronUp, X, Check, Loader2, Send,
    ListOrdered, ToggleLeft, CheckSquare,
} from 'lucide-vue-next';

defineOptions({ layout: LayoutProfissional });

const props = defineProps({
    tests:    { type: Array, default: () => [] },
    patients: { type: Array, default: () => [] },
});

const CATEGORIES = {
    tdah: 'TDAH', tea: 'TEA', ah_sd: 'AH/SD',
    humor: 'Humor', ansiedade: 'Ansiedade', geral: 'Geral',
};

const QUESTION_TYPES = [
    { value: 'scale',   label: 'Escala numérica', icon: ListOrdered },
    { value: 'boolean', label: 'Sim / Não',        icon: ToggleLeft },
    { value: 'single',  label: 'Escolha única',    icon: Check },
    { value: 'multi',   label: 'Múltipla escolha', icon: CheckSquare },
];

function csrf() { return document.querySelector('meta[name="csrf-token"]')?.content ?? ''; }
function headers(method = 'POST') {
    return { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), Accept: 'application/json', 'X-HTTP-Method-Override': method === 'DELETE' || method === 'PUT' ? method : undefined };
}
async function api(url, method, body) {
    const res = await fetch(url, {
        method: method === 'PUT' || method === 'DELETE' ? 'POST' : method,
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), Accept: 'application/json', ...(method === 'PUT' || method === 'DELETE' ? { 'X-HTTP-Method-Override': method } : {}) },
        body: body ? JSON.stringify(body) : undefined,
    });
    // Use _method override instead
    return res;
}

// Usa Laravel _method spoofing
async function apiMethod(url, method, body) {
    const payload = method === 'PUT' || method === 'DELETE' ? { ...body, _method: method } : body;
    const res = await fetch(url, {
        method: method === 'GET' ? 'GET' : 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
        body: payload ? JSON.stringify(payload) : undefined,
    });
    return res;
}

// ── Lista de testes (reativa) ─────────────────────────────────────────────
const tests = ref([...props.tests]);
const search = ref('');
const expandedId = ref(null);

const filtered = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return tests.value;
    return tests.value.filter(t => t.name.toLowerCase().includes(q) || (CATEGORIES[t.category] ?? '').toLowerCase().includes(q));
});

// ── Modal de teste (criar / editar) ──────────────────────────────────────
const showTestModal  = ref(false);
const testForm = reactive({ id: null, name: '', category: 'geral', description: '', instructions: '', estimated_minutes: 5, is_active: true });
const testSaving = ref(false);
const testError  = ref('');

function openCreateTest() {
    Object.assign(testForm, { id: null, name: '', category: 'geral', description: '', instructions: '', estimated_minutes: 5, is_active: true });
    testError.value = '';
    showTestModal.value = true;
}

function openEditTest(t) {
    Object.assign(testForm, { id: t.id, name: t.name, category: t.category, description: t.description ?? '', instructions: t.instructions ?? '', estimated_minutes: t.estimated_minutes, is_active: t.is_active });
    testError.value = '';
    showTestModal.value = true;
}

async function saveTest() {
    testSaving.value = true;
    testError.value = '';
    try {
        const isEdit = !!testForm.id;
        const url    = isEdit ? `/p/triagens/${testForm.id}` : '/p/triagens';
        const method = isEdit ? 'PUT' : 'POST';
        const res    = await apiMethod(url, method, { ...testForm });
        const data   = await res.json();
        if (!res.ok) { testError.value = data.message ?? 'Erro ao salvar.'; return; }
        if (isEdit) {
            const idx = tests.value.findIndex(t => t.id === testForm.id);
            if (idx >= 0) tests.value.splice(idx, 1, { ...tests.value[idx], ...data });
        } else {
            tests.value.push({ ...data, questions: [], scoring_rules: [] });
        }
        showTestModal.value = false;
    } finally {
        testSaving.value = false;
    }
}

async function deleteTest(t) {
    if (!confirm(`Excluir "${t.name}"? Esta ação remove todas as questões e respostas associadas.`)) return;
    const res = await apiMethod(`/p/triagens/${t.id}`, 'DELETE', {});
    if (res.ok) {
        tests.value = tests.value.filter(x => x.id !== t.id);
        if (expandedId.value === t.id) expandedId.value = null;
    }
}

// ── Questões ─────────────────────────────────────────────────────────────
// Questões são carregadas lazily quando o profissional expande o painel
const questionsMap  = ref({});   // testId → array de questões
const loadingQMap   = ref({});

async function loadQuestions(testId) {
    if (questionsMap.value[testId]) return;
    loadingQMap.value[testId] = true;
    try {
        const res  = await fetch(`/p/triagens/${testId}/questoes`, { headers: { Accept: 'application/json' } });
        const data = await res.json();
        questionsMap.value[testId] = data;
    } finally {
        loadingQMap.value[testId] = false;
    }
}

function toggleExpand(t) {
    if (expandedId.value === t.id) { expandedId.value = null; return; }
    expandedId.value = t.id;
    loadQuestions(t.id);
}

// ── Modal de questão ──────────────────────────────────────────────────────
const showQModal  = ref(false);
const qForm = reactive({ testId: null, text: '', type: 'scale', scale_min: 0, scale_max: 4, scale_labels: ['Nunca', 'Raramente', 'Às vezes', 'Frequentemente', 'Muito frequentemente'], options: [] });
const qSaving = ref(false);
const qError  = ref('');

function openAddQuestion(testId) {
    Object.assign(qForm, { testId, text: '', type: 'scale', scale_min: 0, scale_max: 4, scale_labels: ['Nunca', 'Raramente', 'Às vezes', 'Frequentemente', 'Muito frequentemente'], options: [] });
    qError.value = '';
    showQModal.value = true;
}

function addOption() { qForm.options.push({ text: '', value: qForm.options.length }); }
function removeOption(i) { qForm.options.splice(i, 1); }

function onQTypeChange() {
    if (qForm.type === 'scale') {
        qForm.scale_min = 0; qForm.scale_max = 4;
        qForm.scale_labels = ['Nunca', 'Raramente', 'Às vezes', 'Frequentemente', 'Muito frequentemente'];
        qForm.options = [];
    } else if (qForm.type === 'boolean') {
        qForm.options = [];
    } else {
        qForm.scale_min = null; qForm.scale_max = null; qForm.scale_labels = [];
        if (!qForm.options.length) { qForm.options = [{ text: '', value: 1 }, { text: '', value: 0 }]; }
    }
}

async function saveQuestion() {
    qSaving.value = true;
    qError.value  = '';
    try {
        const body = {
            text:         qForm.text,
            type:         qForm.type,
            scale_min:    qForm.type === 'scale' ? qForm.scale_min : null,
            scale_max:    qForm.type === 'scale' ? qForm.scale_max : null,
            scale_labels: qForm.type === 'scale' ? qForm.scale_labels : null,
            options:      ['single', 'multi'].includes(qForm.type) ? qForm.options : [],
        };
        const res  = await fetch(`/p/triagens/${qForm.testId}/questoes`, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' }, body: JSON.stringify(body) });
        const data = await res.json();
        if (!res.ok) { qError.value = data.message ?? 'Erro ao salvar questão.'; return; }
        if (!questionsMap.value[qForm.testId]) questionsMap.value[qForm.testId] = [];
        questionsMap.value[qForm.testId].push(data);
        // atualiza contador no card
        const idx = tests.value.findIndex(t => t.id === qForm.testId);
        if (idx >= 0) tests.value[idx].questions_count = (tests.value[idx].questions_count ?? 0) + 1;
        showQModal.value = false;
    } finally {
        qSaving.value = false;
    }
}

async function deleteQuestion(testId, qId) {
    const res = await apiMethod(`/p/triagens/${testId}/questoes/${qId}`, 'DELETE', {});
    if (res.ok) {
        questionsMap.value[testId] = questionsMap.value[testId].filter(q => q.id !== qId);
        const idx = tests.value.findIndex(t => t.id === testId);
        if (idx >= 0) tests.value[idx].questions_count = Math.max(0, (tests.value[idx].questions_count ?? 1) - 1);
    }
}

// ── Labels da escala como array editável ─────────────────────────────────
const scaleLabelsText = computed({
    get: () => (qForm.scale_labels ?? []).join('\n'),
    set: (val) => { qForm.scale_labels = val.split('\n').map(s => s.trim()).filter(Boolean); },
});

// ── Modal de envio para paciente ─────────────────────────────────────────
const showSendModal     = ref(false);
const sendTest          = ref(null);
const sendPatientId     = ref('');
const sendLoading       = ref(false);
const sendError         = ref('');
const sendSuccess       = ref('');

function openSend(test) {
    sendTest.value      = test;
    sendPatientId.value = props.patients[0]?.id ?? '';
    sendError.value     = '';
    sendSuccess.value   = '';
    showSendModal.value = true;
}

async function confirmSend() {
    if (!sendPatientId.value || !sendTest.value) return;
    sendLoading.value = true;
    sendError.value   = '';
    sendSuccess.value = '';
    try {
        const res  = await fetch('/p/triagens/enviar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
            body: JSON.stringify({ test_id: sendTest.value.id, patient_id: Number(sendPatientId.value) }),
        });
        const data = await res.json();
        if (!res.ok) { sendError.value = data.message ?? 'Erro ao enviar.'; return; }
        sendSuccess.value = data.message ?? 'Teste enviado!';
    } finally {
        sendLoading.value = false;
    }
}
</script>

<template>
    <div class="max-w-3xl mx-auto">
        <!-- Cabeçalho -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Triagens e Testes</h1>
                <p class="text-sm text-zinc-500 mt-1">Crie e gerencie seus instrumentos de rastreio.</p>
            </div>
            <button type="button" @click="openCreateTest"
                class="flex items-center gap-2 rounded-xl bg-violet-600 hover:bg-violet-700 px-4 py-2.5 text-sm font-semibold text-white transition">
                <Plus class="h-4 w-4" />Novo Teste
            </button>
        </div>

        <!-- Busca -->
        <div class="relative mb-5">
            <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-zinc-400" />
            <input v-model="search" type="text" placeholder="Buscar teste..."
                class="w-full pl-9 pr-4 py-2.5 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-sm text-zinc-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-violet-500" />
        </div>

        <!-- Vazio -->
        <div v-if="!filtered.length" class="text-center py-16 text-zinc-400">
            <ClipboardList class="h-10 w-10 mx-auto mb-3 opacity-40" />
            <p class="text-sm">Nenhum teste ainda. Crie o primeiro!</p>
        </div>

        <!-- Cards de testes -->
        <div v-for="test in filtered" :key="test.id" class="mb-3 rounded-2xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 overflow-hidden">

            <!-- Linha principal -->
            <div class="flex items-center gap-3 p-4">
                <button type="button" @click="toggleExpand(test)" class="flex-1 text-left min-w-0">
                    <div class="flex items-center gap-2 flex-wrap mb-0.5">
                        <span class="font-semibold text-zinc-900 dark:text-white">{{ test.name }}</span>
                        <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-violet-100 dark:bg-violet-900/30 text-violet-700 dark:text-violet-300">
                            {{ CATEGORIES[test.category] ?? test.category }}
                        </span>
                        <span v-if="!test.is_active" class="text-xs font-semibold px-2 py-0.5 rounded-full bg-zinc-100 dark:bg-zinc-800 text-zinc-400">Inativo</span>
                    </div>
                    <div class="flex gap-4 text-xs text-zinc-400">
                        <span class="flex items-center gap-1"><Clock class="h-3 w-3" />{{ test.estimated_minutes }} min</span>
                        <span>{{ test.questions_count }} questões</span>
                        <span>{{ test.sessions_count }} aplicações</span>
                    </div>
                </button>
                <button v-if="props.patients.length" type="button" @click="openSend(test)"
                    class="flex items-center gap-1.5 rounded-lg bg-violet-100 dark:bg-violet-900/30 text-violet-700 dark:text-violet-300 px-3 py-1.5 text-xs font-semibold hover:bg-violet-200 dark:hover:bg-violet-800/40 transition">
                    <Send class="h-3.5 w-3.5" />Enviar
                </button>
                <button type="button" @click="openEditTest(test)" class="p-2 rounded-lg text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition">
                    <Pencil class="h-4 w-4" />
                </button>
                <button type="button" @click="deleteTest(test)" class="p-2 rounded-lg text-zinc-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition">
                    <Trash2 class="h-4 w-4" />
                </button>
                <button type="button" @click="toggleExpand(test)" class="p-2 rounded-lg text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition">
                    <ChevronUp v-if="expandedId === test.id" class="h-4 w-4" />
                    <ChevronDown v-else class="h-4 w-4" />
                </button>
            </div>

            <!-- Painel de questões expandido -->
            <div v-if="expandedId === test.id" class="border-t border-zinc-100 dark:border-zinc-800 px-4 pb-4 pt-3">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-xs font-bold uppercase tracking-widest text-zinc-400">Questões</p>
                    <button type="button" @click="openAddQuestion(test.id)"
                        class="flex items-center gap-1.5 rounded-lg bg-violet-100 dark:bg-violet-900/30 text-violet-700 dark:text-violet-300 px-3 py-1.5 text-xs font-semibold hover:bg-violet-200 dark:hover:bg-violet-800/40 transition">
                        <Plus class="h-3 w-3" />Adicionar questão
                    </button>
                </div>

                <div v-if="loadingQMap[test.id]" class="text-xs text-zinc-400 py-2">Carregando…</div>
                <div v-else-if="!questionsMap[test.id]?.length" class="text-xs text-zinc-400 py-2">Nenhuma questão ainda.</div>

                <div v-for="(q, qi) in questionsMap[test.id]" :key="q.id"
                    class="flex items-start gap-3 py-2.5 border-b border-zinc-50 dark:border-zinc-800 last:border-0">
                    <span class="text-xs font-bold text-zinc-400 mt-0.5 w-5 shrink-0">{{ qi + 1 }}.</span>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm text-zinc-800 dark:text-zinc-200">{{ q.text }}</p>
                        <span class="text-[11px] text-zinc-400">{{ QUESTION_TYPES.find(t => t.value === q.type)?.label ?? q.type }}</span>
                        <template v-if="q.type === 'scale'">
                            <span class="text-[11px] text-zinc-400"> · {{ q.scale_min }}–{{ q.scale_max }}</span>
                        </template>
                        <template v-if="q.options?.length">
                            <div class="flex flex-wrap gap-1 mt-1">
                                <span v-for="opt in q.options" :key="opt.id" class="text-[11px] px-1.5 py-0.5 rounded bg-zinc-100 dark:bg-zinc-800 text-zinc-500">{{ opt.text }}</span>
                            </div>
                        </template>
                    </div>
                    <button type="button" @click="deleteQuestion(test.id, q.id)"
                        class="p-1.5 rounded text-zinc-300 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition shrink-0">
                        <Trash2 class="h-3.5 w-3.5" />
                    </button>
                </div>
            </div>
        </div>

        <!-- ════════════════════════ MODAL: CRIAR / EDITAR TESTE ══════════════════ -->
        <div v-if="showTestModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-lg rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                <div class="flex items-center justify-between px-6 py-4 border-b border-zinc-100 dark:border-zinc-800">
                    <h2 class="text-lg font-bold text-zinc-900 dark:text-white">{{ testForm.id ? 'Editar Teste' : 'Novo Teste' }}</h2>
                    <button type="button" @click="showTestModal = false" class="p-1 rounded text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200">
                        <X class="h-5 w-5" />
                    </button>
                </div>
                <div class="px-6 py-5 space-y-4 max-h-[70vh] overflow-y-auto">
                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Nome do teste *</label>
                        <input v-model="testForm.name" type="text" placeholder="Ex: Rastreio de Atenção"
                            class="w-full rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Categoria *</label>
                            <select v-model="testForm.category"
                                class="w-full rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
                                <option v-for="(label, val) in CATEGORIES" :key="val" :value="val">{{ label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Tempo estimado (min) *</label>
                            <input v-model.number="testForm.estimated_minutes" type="number" min="1" max="120"
                                class="w-full rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Descrição</label>
                        <textarea v-model="testForm.description" rows="2" placeholder="Breve descrição do teste..."
                            class="w-full rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 px-3 py-2.5 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-violet-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Instruções para o paciente</label>
                        <textarea v-model="testForm.instructions" rows="3" placeholder="Instruções exibidas antes do teste..."
                            class="w-full rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 px-3 py-2.5 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-violet-500" />
                    </div>
                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input type="checkbox" v-model="testForm.is_active" class="rounded" />
                        <span class="text-sm text-zinc-700 dark:text-zinc-300">Ativo (visível para pacientes)</span>
                    </label>
                    <p v-if="testError" class="text-sm text-red-500">{{ testError }}</p>
                </div>
                <div class="flex gap-3 px-6 py-4 border-t border-zinc-100 dark:border-zinc-800">
                    <button type="button" @click="showTestModal = false"
                        class="flex-1 rounded-xl border border-zinc-200 dark:border-zinc-700 py-2.5 text-sm font-semibold text-zinc-600 dark:text-zinc-400">
                        Cancelar
                    </button>
                    <button type="button" @click="saveTest" :disabled="!testForm.name || testSaving"
                        class="flex-1 flex items-center justify-center gap-2 rounded-xl bg-violet-600 hover:bg-violet-700 disabled:opacity-50 py-2.5 text-sm font-semibold text-white">
                        <Loader2 v-if="testSaving" class="h-4 w-4 animate-spin" />
                        {{ testSaving ? 'Salvando…' : 'Salvar' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- ════════════════════════ MODAL: ADICIONAR QUESTÃO ══════════════════ -->
        <div v-if="showQModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-lg rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                <div class="flex items-center justify-between px-6 py-4 border-b border-zinc-100 dark:border-zinc-800">
                    <h2 class="text-lg font-bold text-zinc-900 dark:text-white">Nova Questão</h2>
                    <button type="button" @click="showQModal = false" class="p-1 rounded text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200">
                        <X class="h-5 w-5" />
                    </button>
                </div>
                <div class="px-6 py-5 space-y-4 max-h-[70vh] overflow-y-auto">
                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Texto da questão *</label>
                        <textarea v-model="qForm.text" rows="3" placeholder="Ex: Com que frequência você sente dificuldade para se concentrar?"
                            class="w-full rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 px-3 py-2.5 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-violet-500" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">Tipo de resposta *</label>
                        <div class="grid grid-cols-2 gap-2">
                            <button v-for="t in QUESTION_TYPES" :key="t.value" type="button"
                                @click="qForm.type = t.value; onQTypeChange()"
                                :class="['flex items-center gap-2 rounded-xl border px-3 py-2.5 text-sm font-medium transition', qForm.type === t.value ? 'border-violet-500 bg-violet-50 dark:bg-violet-900/30 text-violet-700 dark:text-violet-300' : 'border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-400 hover:border-violet-300']">
                                <component :is="t.icon" class="h-4 w-4" />{{ t.label }}
                            </button>
                        </div>
                    </div>

                    <!-- Configuração de escala -->
                    <template v-if="qForm.type === 'scale'">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-zinc-500 mb-1">Mínimo</label>
                                <input v-model.number="qForm.scale_min" type="number" class="w-full rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-zinc-500 mb-1">Máximo</label>
                                <input v-model.number="qForm.scale_max" type="number" class="w-full rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-zinc-500 mb-1">Labels (um por linha, do menor ao maior)</label>
                            <textarea v-model="scaleLabelsText" rows="5"
                                class="w-full rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 px-3 py-2 text-sm font-mono resize-none focus:outline-none focus:ring-2 focus:ring-violet-500"
                                placeholder="Nunca&#10;Raramente&#10;Às vezes&#10;Frequentemente&#10;Muito frequentemente" />
                        </div>
                    </template>

                    <!-- Opções para single / multi -->
                    <template v-if="['single', 'multi'].includes(qForm.type)">
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <label class="text-xs font-medium text-zinc-500">Opções de resposta</label>
                                <button type="button" @click="addOption" class="text-xs text-violet-600 font-semibold">+ Adicionar</button>
                            </div>
                            <div v-for="(opt, i) in qForm.options" :key="i" class="flex gap-2 mb-2">
                                <input v-model="opt.text" type="text" :placeholder="`Opção ${i + 1}`"
                                    class="flex-1 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                                <input v-model.number="opt.value" type="number" placeholder="Valor"
                                    class="w-20 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500" />
                                <button type="button" @click="removeOption(i)" class="p-2 text-zinc-300 hover:text-red-500 transition"><X class="h-4 w-4" /></button>
                            </div>
                        </div>
                    </template>

                    <p v-if="qError" class="text-sm text-red-500">{{ qError }}</p>
                </div>
                <div class="flex gap-3 px-6 py-4 border-t border-zinc-100 dark:border-zinc-800">
                    <button type="button" @click="showQModal = false"
                        class="flex-1 rounded-xl border border-zinc-200 dark:border-zinc-700 py-2.5 text-sm font-semibold text-zinc-600 dark:text-zinc-400">
                        Cancelar
                    </button>
                    <button type="button" @click="saveQuestion" :disabled="!qForm.text || qSaving"
                        class="flex-1 flex items-center justify-center gap-2 rounded-xl bg-violet-600 hover:bg-violet-700 disabled:opacity-50 py-2.5 text-sm font-semibold text-white">
                        <Loader2 v-if="qSaving" class="h-4 w-4 animate-spin" />
                        {{ qSaving ? 'Salvando…' : 'Adicionar' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- ════════════════════════ MODAL: ENVIAR PARA PACIENTE ══════════════════ -->
        <div v-if="showSendModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-sm rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                <div class="flex items-center justify-between px-6 py-4 border-b border-zinc-100 dark:border-zinc-800">
                    <h2 class="text-lg font-bold text-zinc-900 dark:text-white">Enviar Triagem</h2>
                    <button type="button" @click="showSendModal = false" class="p-1 rounded text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200">
                        <X class="h-5 w-5" />
                    </button>
                </div>
                <div class="px-6 py-5 space-y-4">
                    <p class="text-sm text-zinc-500">Teste: <strong class="text-zinc-700 dark:text-zinc-300">{{ sendTest?.name }}</strong></p>

                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Paciente *</label>
                        <select v-model="sendPatientId"
                            class="w-full rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">
                            <option value="" disabled>Selecione um paciente</option>
                            <option v-for="p in props.patients" :key="p.id" :value="p.id">{{ p.name }} — {{ p.email }}</option>
                        </select>
                        <p v-if="!props.patients.length" class="text-xs text-zinc-400 mt-1">
                            Nenhum paciente vinculado. Vincule pacientes em "Meus Pacientes".
                        </p>
                    </div>

                    <p v-if="sendError"   class="text-sm text-red-500">{{ sendError }}</p>
                    <p v-if="sendSuccess" class="text-sm text-green-600 dark:text-green-400">{{ sendSuccess }}</p>
                </div>
                <div class="flex gap-3 px-6 py-4 border-t border-zinc-100 dark:border-zinc-800">
                    <button type="button" @click="showSendModal = false"
                        class="flex-1 rounded-xl border border-zinc-200 dark:border-zinc-700 py-2.5 text-sm font-semibold text-zinc-600 dark:text-zinc-400">
                        Fechar
                    </button>
                    <button type="button" @click="confirmSend" :disabled="!sendPatientId || sendLoading || !!sendSuccess"
                        class="flex-1 flex items-center justify-center gap-2 rounded-xl bg-violet-600 hover:bg-violet-700 disabled:opacity-50 py-2.5 text-sm font-semibold text-white">
                        <Loader2 v-if="sendLoading" class="h-4 w-4 animate-spin" />
                        <Send v-else class="h-4 w-4" />
                        {{ sendLoading ? 'Enviando…' : sendSuccess ? 'Enviado!' : 'Enviar' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
