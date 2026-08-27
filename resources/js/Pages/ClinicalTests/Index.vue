<script setup>
import { ref, watch, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import LayoutInfoprodutor from '@/Layouts/LayoutInfoprodutor.vue';
import Button from '@/components/ui/Button.vue';
import ImportarTestesPanel from '@/components/clinical/ImportarTestesPanel.vue';
import {
    ClipboardList, Plus, Pencil, Trash2, X, Loader2,
    Search, Eye, EyeOff, ChevronDown, ChevronUp,
    CheckCircle2, Clock, Users, GripVertical,
    AlertCircle, ListChecks, Award, BrainCircuit,
} from 'lucide-vue-next';

defineOptions({ layout: LayoutInfoprodutor });

const props = defineProps({
    tests:  { type: Object, default: () => ({ data: [] }) },
    stats:  { type: Object, default: () => ({}) },
    q:      { type: String, default: null },
});

// ─── Busca
const search = ref(props.q ?? '');

// Importacao em massa: fechada por padrao para nao competir com a lista.
const mostrarImportacao = ref(false);
let searchTimer = null;
watch(search, (v) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get('/testes-clinicos', v ? { q: v } : {}, { preserveState: true, replace: true });
    }, 400);
});

// ─── Toast
const toast = ref(null);
function showToast(msg, type = 'success') {
    toast.value = { msg, type };
    setTimeout(() => toast.value = null, 4000);
}
function csrfToken() { return document.querySelector('meta[name="csrf-token"]')?.content ?? ''; }
const h = () => ({ 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' });

const CATEGORIES = [
    { value: 'tdah',   label: 'TDAH' },
    { value: 'tea',    label: 'TEA' },
    { value: 'ah_sd',  label: 'AH/SD' },
    { value: 'humor',  label: 'Humor' },
    { value: 'ansiedade', label: 'Ansiedade' },
    { value: 'geral',  label: 'Geral' },
];

// ─── Modal de Teste
const testModal    = ref(false);
const editingTest  = ref(null);
const savingTest   = ref(false);
const emptyTestForm = () => ({
    name: '', category: 'geral', description: '', instructions: '',
    estimated_minutes: 5, is_active: true,
});
const testForm = ref(emptyTestForm());

function openCreateTest() {
    editingTest.value = null;
    testForm.value = emptyTestForm();
    testModal.value = true;
}
function openEditTest(test) {
    editingTest.value = test;
    testForm.value = {
        name: test.name,
        category: test.category,
        description: test.description ?? '',
        instructions: test.instructions ?? '',
        estimated_minutes: test.estimated_minutes,
        is_active: test.is_active,
    };
    testModal.value = true;
}
function closeTestModal() { testModal.value = false; }

async function saveTest() {
    if (savingTest.value) return;
    savingTest.value = true;
    try {
        const url  = editingTest.value ? `/testes-clinicos/${editingTest.value.id}` : '/testes-clinicos';
        const meth = editingTest.value ? 'PUT' : 'POST';
        const res  = await fetch(url, {
            method: meth,
            headers: { ...h(), 'Content-Type': 'application/json' },
            body: JSON.stringify(testForm.value),
        });
        const data = await res.json();
        if (!res.ok) throw new Error(data.message ?? 'Erro ao salvar');
        showToast(editingTest.value ? 'Teste atualizado.' : 'Teste criado.');
        closeTestModal();
        router.reload({ preserveScroll: true });
    } catch (e) {
        showToast(e.message, 'error');
    } finally {
        savingTest.value = false;
    }
}

async function toggleActive(test) {
    try {
        await fetch(`/testes-clinicos/${test.id}/toggle-active`, { method: 'PUT', headers: h() });
        router.reload({ preserveScroll: true });
    } catch { showToast('Erro ao alterar status.', 'error'); }
}

async function deleteTest(test) {
    if (!confirm(`Excluir "${test.name}"? Todas as respostas serão apagadas.`)) return;
    try {
        const res = await fetch(`/testes-clinicos/${test.id}`, { method: 'DELETE', headers: h() });
        if (!res.ok) throw new Error();
        showToast('Teste excluído.');
        router.reload({ preserveScroll: true });
    } catch { showToast('Erro ao excluir.', 'error'); }
}

// ─── Painel de Perguntas
const questionsPanel  = ref(null); // test being edited
const questions       = ref([]);
const loadingQ        = ref(false);
const questionModal   = ref(false);
const editingQuestion = ref(null);
const savingQuestion  = ref(false);

const emptyQForm = () => ({
    text: '', type: 'scale', scale_min: 1, scale_max: 5,
    scale_labels: {}, options: [],
});
const qForm = ref(emptyQForm());

async function openQuestions(test) {
    questionsPanel.value = test;
    loadingQ.value = true;
    try {
        const res  = await fetch(`/testes-clinicos/${test.id}/perguntas`, { headers: h() });
        const data = await res.json();
        questions.value = data.questions ?? [];
    } finally {
        loadingQ.value = false;
    }
}
function closeQPanel() { questionsPanel.value = null; questions.value = []; }

function openCreateQ() {
    editingQuestion.value = null;
    qForm.value = emptyQForm();
    questionModal.value = true;
}
function openEditQ(q) {
    editingQuestion.value = q;
    qForm.value = {
        text: q.text, type: q.type,
        scale_min: q.scale_min, scale_max: q.scale_max,
        scale_labels: { ...(q.scale_labels ?? {}) },
        options: (q.options ?? []).map(o => ({ ...o })),
    };
    questionModal.value = true;
}
function closeQModal() { questionModal.value = false; }

async function saveQuestion() {
    if (savingQuestion.value || !questionsPanel.value) return;
    savingQuestion.value = true;
    try {
        const tid = questionsPanel.value.id;
        const url = editingQuestion.value
            ? `/testes-clinicos/${tid}/perguntas/${editingQuestion.value.id}`
            : `/testes-clinicos/${tid}/perguntas`;
        const res = await fetch(url, {
            method: editingQuestion.value ? 'PUT' : 'POST',
            headers: { ...h(), 'Content-Type': 'application/json' },
            body: JSON.stringify(qForm.value),
        });
        const data = await res.json();
        if (!res.ok) throw new Error(data.message ?? 'Erro ao salvar');
        if (editingQuestion.value) {
            const idx = questions.value.findIndex(q => q.id === editingQuestion.value.id);
            if (idx >= 0) questions.value[idx] = data.question;
        } else {
            questions.value.push(data.question);
        }
        showToast(editingQuestion.value ? 'Pergunta atualizada.' : 'Pergunta adicionada.');
        closeQModal();
    } catch (e) {
        showToast(e.message, 'error');
    } finally {
        savingQuestion.value = false;
    }
}

async function deleteQuestion(q) {
    if (!confirm('Excluir esta pergunta?')) return;
    try {
        await fetch(`/testes-clinicos/${questionsPanel.value.id}/perguntas/${q.id}`, { method: 'DELETE', headers: h() });
        questions.value = questions.value.filter(x => x.id !== q.id);
        showToast('Pergunta excluída.');
    } catch { showToast('Erro ao excluir.', 'error'); }
}

function addOption() { qForm.value.options.push({ text: '', value: qForm.value.options.length }); }
function removeOption(i) { qForm.value.options.splice(i, 1); }

// ─── Painel de Regras de Pontuação
const rulesPanel   = ref(null);
const rules        = ref([]);
const ruleModal    = ref(false);
const editingRule  = ref(null);
const savingRule   = ref(false);
const emptyRuleForm = () => ({ min_score: 0, max_score: 10, result_label: '', result_description: '', challenge_tags: [] });
const ruleForm     = ref(emptyRuleForm());
const newTagInput  = ref('');

async function openRules(test) {
    rulesPanel.value = test;
    const res = await fetch(`/testes-clinicos/${test.id}/perguntas`, { headers: h() });
    const res2 = await fetch(`/testes-clinicos/${test.id}/respostas`, { headers: h() });
    // Load scoring rules directly from test data via reload... let's just fetch inline
    // For now we load the test fresh to get rules
    const testRes = await fetch(`/testes-clinicos/${test.id}/perguntas`, { headers: h() }).catch(() => null);
    rules.value = test.scoring_rules ?? [];
    ruleModal.value = false;
}
function closeRulesPanel() { rulesPanel.value = null; rules.value = []; }
function openCreateRule() {
    editingRule.value = null;
    ruleForm.value = emptyRuleForm();
    ruleModal.value = true;
}
function openEditRule(r) {
    editingRule.value = r;
    ruleForm.value = { min_score: r.min_score, max_score: r.max_score, result_label: r.result_label, result_description: r.result_description ?? '', challenge_tags: [...(r.challenge_tags ?? [])] };
    ruleModal.value = true;
}
function addTag() {
    const t = newTagInput.value.trim().toLowerCase();
    if (t && !ruleForm.value.challenge_tags.includes(t)) {
        ruleForm.value.challenge_tags.push(t);
    }
    newTagInput.value = '';
}
function removeTag(i) { ruleForm.value.challenge_tags.splice(i, 1); }

async function saveRule() {
    if (savingRule.value || !rulesPanel.value) return;
    savingRule.value = true;
    try {
        const tid = rulesPanel.value.id;
        const url = editingRule.value
            ? `/testes-clinicos/${tid}/regras/${editingRule.value.id}`
            : `/testes-clinicos/${tid}/regras`;
        const res = await fetch(url, {
            method: editingRule.value ? 'PUT' : 'POST',
            headers: { ...h(), 'Content-Type': 'application/json' },
            body: JSON.stringify(ruleForm.value),
        });
        const data = await res.json();
        if (!res.ok) throw new Error(data.message ?? 'Erro ao salvar');
        if (editingRule.value) {
            const idx = rules.value.findIndex(r => r.id === editingRule.value.id);
            if (idx >= 0) rules.value[idx] = data.rule;
        } else {
            rules.value.push(data.rule);
        }
        showToast('Regra salva.');
        ruleModal.value = false;
    } catch (e) {
        showToast(e.message, 'error');
    } finally {
        savingRule.value = false;
    }
}

async function deleteRule(r) {
    if (!confirm('Excluir esta regra?')) return;
    try {
        await fetch(`/testes-clinicos/${rulesPanel.value.id}/regras/${r.id}`, { method: 'DELETE', headers: h() });
        rules.value = rules.value.filter(x => x.id !== r.id);
        showToast('Regra excluída.');
    } catch { showToast('Erro.', 'error'); }
}

// ─── Active panel toggle (questions | rules)
const activePanelFor  = ref(null); // test id
const activePanelType = ref(null); // 'questions' | 'rules'

async function togglePanel(test, type) {
    if (activePanelFor.value === test.id && activePanelType.value === type) {
        activePanelFor.value = null;
        activePanelType.value = null;
        return;
    }
    activePanelFor.value = test.id;
    activePanelType.value = type;
    if (type === 'questions') {
        loadingQ.value = true;
        questionsPanel.value = test;
        rulesPanel.value = null;
        try {
            const res = await fetch(`/testes-clinicos/${test.id}/perguntas`, { headers: h() });
            const data = await res.json();
            questions.value = data.questions ?? [];
        } finally { loadingQ.value = false; }
    } else {
        rulesPanel.value = test;
        questionsPanel.value = null;
        rules.value = test.scoring_rules ?? [];
    }
}
</script>

<template>
    <!-- Importacao em massa -->
    <div class="mb-6">
        <button type="button" @click="mostrarImportacao = !mostrarImportacao"
            class="inline-flex items-center gap-2 rounded-xl border border-zinc-200 px-4 py-2.5 text-sm font-medium text-zinc-700 transition hover:border-[var(--color-primary)] hover:text-[var(--color-primary)] dark:border-zinc-600 dark:text-zinc-200">
            {{ mostrarImportacao ? 'Fechar importacao' : 'Importar testes em massa' }}
        </button>
        <div v-if="mostrarImportacao" class="mt-4 rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800/50">
            <ImportarTestesPanel @importado="router.reload()" />
        </div>
    </div>

    <div class="p-4 lg:p-8 max-w-5xl mx-auto space-y-6">

        <!-- Toast -->
        <Transition enter-active-class="transition duration-200" enter-from-class="opacity-0 -translate-y-2" enter-to-class="opacity-100" leave-active-class="transition duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="toast" :class="['fixed top-4 right-4 z-50 flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium shadow-xl', toast.type === 'error' ? 'bg-red-600 text-white' : 'bg-emerald-600 text-white']">
                <CheckCircle2 v-if="toast.type !== 'error'" class="h-4 w-4" />
                <AlertCircle v-else class="h-4 w-4" />
                {{ toast.msg }}
            </div>
        </Transition>

        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-zinc-900 dark:text-white flex items-center gap-2">
                    <ClipboardList class="h-7 w-7 text-emerald-500" />
                    Testes Clínicos
                </h1>
                <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">Crie e gerencie os testes aplicados aos alunos</p>
            </div>
            <Button @click="openCreateTest">
                <Plus class="h-4 w-4 mr-1" />
                Novo Teste
            </Button>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-3 gap-4">
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-4 text-center">
                <p class="text-2xl font-bold text-zinc-900 dark:text-white">{{ stats.total ?? 0 }}</p>
                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">Total</p>
            </div>
            <div class="rounded-xl border border-emerald-200 dark:border-emerald-800/40 bg-emerald-50 dark:bg-emerald-950/20 p-4 text-center">
                <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ stats.ativos ?? 0 }}</p>
                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">Ativos</p>
            </div>
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-4 text-center">
                <p class="text-2xl font-bold text-zinc-400">{{ stats.inativos ?? 0 }}</p>
                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">Inativos</p>
            </div>
        </div>

        <!-- Busca -->
        <div class="relative">
            <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-zinc-400" />
            <input v-model="search" type="text" placeholder="Buscar testes…"
                class="w-full rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 pl-9 pr-4 py-2.5 text-sm text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500" />
        </div>

        <!-- Lista de testes -->
        <div class="space-y-3">
            <div v-if="!tests.data?.length" class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-10 text-center">
                <ClipboardList class="h-10 w-10 text-zinc-300 dark:text-zinc-600 mx-auto mb-3" />
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Nenhum teste criado ainda.</p>
                <Button class="mt-4" @click="openCreateTest"><Plus class="h-4 w-4 mr-1" />Criar primeiro teste</Button>
            </div>

            <div v-for="test in tests.data" :key="test.id"
                class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 overflow-hidden">

                <!-- Linha principal do teste -->
                <div class="flex items-center gap-3 px-4 py-3">
                    <GripVertical class="h-4 w-4 text-zinc-300 dark:text-zinc-600 flex-shrink-0" />
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <button type="button" class="font-semibold text-zinc-900 dark:text-zinc-100 text-sm truncate hover:text-emerald-600 dark:hover:text-emerald-400 transition text-left" @click="router.visit(`/testes-clinicos/${test.id}/editar`)">{{ test.name }}</button>
                            <span v-if="test.ai_context?.instructions" class="flex items-center gap-1 rounded-full bg-sky-100 dark:bg-sky-950/40 text-sky-600 dark:text-sky-400 px-2 py-0.5 text-xs font-medium" title="Instruções IA configuradas">
                                <BrainCircuit class="h-3 w-3" />IA
                            </span>
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="test.is_active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400' : 'bg-zinc-100 text-zinc-500 dark:bg-zinc-700 dark:text-zinc-400'">
                                {{ test.is_active ? 'Ativo' : 'Inativo' }}
                            </span>
                            <span class="rounded-full bg-blue-100 dark:bg-blue-950/40 text-blue-700 dark:text-blue-400 px-2 py-0.5 text-xs font-medium uppercase">{{ test.category }}</span>
                        </div>
                        <div class="flex items-center gap-3 mt-1 text-xs text-zinc-400">
                            <span class="flex items-center gap-1"><ListChecks class="h-3 w-3" />{{ test.questions_count }} perguntas</span>
                            <span class="flex items-center gap-1"><Users class="h-3 w-3" />{{ test.sessions_count }} respostas</span>
                            <span class="flex items-center gap-1"><Clock class="h-3 w-3" />{{ test.estimated_minutes }} min</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-1.5 flex-shrink-0">
                        <button type="button" :title="test.is_active ? 'Desativar' : 'Ativar'" class="rounded-lg p-1.5 text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-700 hover:text-zinc-700 dark:hover:text-zinc-200 transition" @click="toggleActive(test)">
                            <Eye v-if="test.is_active" class="h-4 w-4" />
                            <EyeOff v-else class="h-4 w-4" />
                        </button>
                        <button type="button" title="Editar" class="rounded-lg p-1.5 text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-700 hover:text-zinc-700 dark:hover:text-zinc-200 transition" @click="router.visit(`/testes-clinicos/${test.id}/editar`)">
                            <Pencil class="h-4 w-4" />
                        </button>
                        <button type="button" title="Excluir" class="rounded-lg p-1.5 text-zinc-400 hover:bg-red-50 dark:hover:bg-red-950/30 hover:text-red-500 transition" @click="deleteTest(test)">
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>
                </div>

                <!-- Botões expandir perguntas / regras / IA -->
                <div class="flex border-t border-zinc-100 dark:border-zinc-700">
                    <button type="button"
                        :class="['flex-1 flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-medium transition', activePanelFor === test.id && activePanelType === 'questions' ? 'bg-emerald-50 dark:bg-emerald-950/30 text-emerald-600 dark:text-emerald-400' : 'text-zinc-500 hover:bg-zinc-50 dark:hover:bg-zinc-700/50']"
                        @click="togglePanel(test, 'questions')">
                        <ListChecks class="h-3.5 w-3.5" />
                        Perguntas
                        <ChevronDown v-if="!(activePanelFor === test.id && activePanelType === 'questions')" class="h-3 w-3" />
                        <ChevronUp v-else class="h-3 w-3" />
                    </button>
                    <div class="w-px bg-zinc-100 dark:bg-zinc-700" />
                    <button type="button"
                        :class="['flex-1 flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-medium transition', activePanelFor === test.id && activePanelType === 'rules' ? 'bg-violet-50 dark:bg-violet-950/30 text-violet-600 dark:text-violet-400' : 'text-zinc-500 hover:bg-zinc-50 dark:hover:bg-zinc-700/50']"
                        @click="togglePanel(test, 'rules')">
                        <Award class="h-3.5 w-3.5" />
                        Pontuação & Tags
                        <ChevronDown v-if="!(activePanelFor === test.id && activePanelType === 'rules')" class="h-3 w-3" />
                        <ChevronUp v-else class="h-3 w-3" />
                    </button>
                </div>

                <!-- Painel de perguntas expandido -->
                <div v-if="activePanelFor === test.id && activePanelType === 'questions'" class="border-t border-zinc-100 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900/50 p-4 space-y-3">
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Perguntas</p>
                        <Button size="sm" @click="openCreateQ"><Plus class="h-3 w-3 mr-1" />Adicionar</Button>
                    </div>
                    <div v-if="loadingQ" class="flex justify-center py-6"><Loader2 class="h-5 w-5 animate-spin text-zinc-400" /></div>
                    <div v-else-if="!questions.length" class="text-xs text-zinc-400 text-center py-4">Nenhuma pergunta ainda.</div>
                    <div v-for="(q, qi) in questions" :key="q.id" class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3">
                        <div class="flex items-start gap-2">
                            <span class="flex-shrink-0 text-xs font-mono text-zinc-400 mt-0.5 w-5">{{ qi + 1 }}.</span>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-zinc-800 dark:text-zinc-200">{{ q.text }}</p>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="text-xs text-zinc-400 bg-zinc-100 dark:bg-zinc-700 rounded px-1.5 py-0.5">{{ q.type }}</span>
                                    <span v-if="q.type === 'scale'" class="text-xs text-zinc-400">{{ q.scale_min }}–{{ q.scale_max }}</span>
                                    <span v-if="q.options?.length" class="text-xs text-zinc-400">{{ q.options.length }} opções</span>
                                </div>
                            </div>
                            <div class="flex gap-1 flex-shrink-0">
                                <button type="button" class="p-1 text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200" @click="openEditQ(q)"><Pencil class="h-3.5 w-3.5" /></button>
                                <button type="button" class="p-1 text-zinc-400 hover:text-red-500" @click="deleteQuestion(q)"><Trash2 class="h-3.5 w-3.5" /></button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Painel de regras de pontuação expandido -->
                <div v-if="activePanelFor === test.id && activePanelType === 'rules'" class="border-t border-zinc-100 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900/50 p-4 space-y-3">
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Regras de Pontuação</p>
                        <Button size="sm" variant="outline" @click="openCreateRule"><Plus class="h-3 w-3 mr-1" />Adicionar</Button>
                    </div>
                    <div v-if="!rules.length" class="text-xs text-zinc-400 text-center py-4">Nenhuma regra cadastrada.<br><span class="text-zinc-300">Defina faixas de pontuação → resultado e tags de desafio.</span></div>
                    <div v-for="rule in rules" :key="rule.id" class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="font-mono text-xs bg-violet-100 dark:bg-violet-950/40 text-violet-700 dark:text-violet-400 rounded px-1.5 py-0.5">{{ rule.min_score }}–{{ rule.max_score }}</span>
                                    <span class="font-medium text-sm text-zinc-800 dark:text-zinc-200">{{ rule.result_label }}</span>
                                </div>
                                <p v-if="rule.result_description" class="text-xs text-zinc-400 mt-1">{{ rule.result_description }}</p>
                                <div v-if="rule.challenge_tags?.length" class="flex flex-wrap gap-1 mt-1.5">
                                    <span v-for="tag in rule.challenge_tags" :key="tag" class="rounded-full bg-emerald-100 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 px-2 py-0.5 text-xs">{{ tag }}</span>
                                </div>
                            </div>
                            <div class="flex gap-1 flex-shrink-0">
                                <button type="button" class="p-1 text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200" @click="openEditRule(rule)"><Pencil class="h-3.5 w-3.5" /></button>
                                <button type="button" class="p-1 text-zinc-400 hover:text-red-500" @click="deleteRule(rule)"><Trash2 class="h-3.5 w-3.5" /></button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Paginação -->
        <div v-if="tests.last_page > 1" class="flex justify-center gap-2">
            <button v-for="page in tests.last_page" :key="page" type="button"
                :class="['rounded-lg px-3 py-1.5 text-sm font-medium transition', page === tests.current_page ? 'bg-emerald-600 text-white' : 'bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-700']"
                @click="router.get('/testes-clinicos', { page, q: search || undefined })">
                {{ page }}
            </button>
        </div>
    </div>

    <!-- Modal: Criar / Editar Teste -->
    <Teleport to="body">
        <div v-if="testModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm" @click.self="closeTestModal">
            <div class="w-full max-w-lg rounded-2xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 shadow-2xl overflow-hidden">
                <div class="flex items-center justify-between px-6 py-4 border-b border-zinc-100 dark:border-zinc-800">
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ editingTest ? 'Editar Teste' : 'Novo Teste' }}</h2>
                    <button type="button" class="text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200" @click="closeTestModal"><X class="h-5 w-5" /></button>
                </div>
                <div class="p-6 space-y-4 max-h-[70vh] overflow-y-auto">
                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Nome *</label>
                        <input v-model="testForm.name" type="text" placeholder="Ex: Escala de Desatenção Adulto (ASRS)" class="w-full rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-sm text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Categoria *</label>
                        <select v-model="testForm.category" class="w-full rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-sm text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-emerald-500/30">
                            <option v-for="c in CATEGORIES" :key="c.value" :value="c.value">{{ c.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Tempo estimado (min)</label>
                        <input v-model.number="testForm.estimated_minutes" type="number" min="1" max="999" class="w-full rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-sm text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-emerald-500/30" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Descrição</label>
                        <textarea v-model="testForm.description" rows="2" placeholder="Breve descrição do objetivo do teste…" class="w-full rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-sm text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 resize-none" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Instruções para o aluno</label>
                        <textarea v-model="testForm.instructions" rows="3" placeholder="Instrução exibida antes de iniciar o teste…" class="w-full rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-sm text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 resize-none" />
                    </div>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input v-model="testForm.is_active" type="checkbox" class="rounded" />
                        <span class="text-sm text-zinc-700 dark:text-zinc-300">Ativo (visível para alunos)</span>
                    </label>
                </div>
                <div class="flex justify-end gap-2 px-6 py-4 border-t border-zinc-100 dark:border-zinc-800">
                    <Button variant="outline" @click="closeTestModal">Cancelar</Button>
                    <Button :disabled="savingTest" @click="saveTest">
                        <Loader2 v-if="savingTest" class="h-4 w-4 mr-1 animate-spin" />
                        {{ editingTest ? 'Salvar' : 'Criar Teste' }}
                    </Button>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- Modal: Criar / Editar Pergunta -->
    <Teleport to="body">
        <div v-if="questionModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm" @click.self="closeQModal">
            <div class="w-full max-w-lg rounded-2xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 shadow-2xl overflow-hidden">
                <div class="flex items-center justify-between px-6 py-4 border-b border-zinc-100 dark:border-zinc-800">
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ editingQuestion ? 'Editar Pergunta' : 'Nova Pergunta' }}</h2>
                    <button type="button" class="text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200" @click="closeQModal"><X class="h-5 w-5" /></button>
                </div>
                <div class="p-6 space-y-4 max-h-[70vh] overflow-y-auto">
                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Texto da pergunta *</label>
                        <textarea v-model="qForm.text" rows="3" placeholder="Ex: Com que frequência você tem dificuldade para manter o foco?" class="w-full rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-sm text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 resize-none" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Tipo *</label>
                        <select v-model="qForm.type" class="w-full rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-sm text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-emerald-500/30">
                            <option value="scale">Escala (1–N)</option>
                            <option value="boolean">Sim / Não</option>
                            <option value="single">Múltipla escolha (1 opção)</option>
                            <option value="multi">Múltipla escolha (várias opções)</option>
                        </select>
                    </div>
                    <!-- Escala -->
                    <div v-if="qForm.type === 'scale'" class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-zinc-500 mb-1">Mínimo</label>
                            <input v-model.number="qForm.scale_min" type="number" min="1" max="10" class="w-full rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/30" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-zinc-500 mb-1">Máximo</label>
                            <input v-model.number="qForm.scale_max" type="number" min="2" max="10" class="w-full rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/30" />
                        </div>
                    </div>
                    <!-- Opções -->
                    <div v-if="['single', 'multi'].includes(qForm.type)" class="space-y-2">
                        <div class="flex items-center justify-between">
                            <label class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Opções</label>
                            <button type="button" class="text-xs text-emerald-600 hover:underline" @click="addOption">+ Adicionar opção</button>
                        </div>
                        <div v-for="(opt, oi) in qForm.options" :key="oi" class="flex gap-2 items-center">
                            <input v-model="opt.text" type="text" :placeholder="`Opção ${oi + 1}`" class="flex-1 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/30" />
                            <input v-model.number="opt.value" type="number" placeholder="Val" class="w-16 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-2 py-2 text-sm text-center focus:outline-none focus:ring-2 focus:ring-emerald-500/30" />
                            <button type="button" class="text-zinc-400 hover:text-red-500" @click="removeOption(oi)"><X class="h-4 w-4" /></button>
                        </div>
                        <p v-if="!qForm.options.length" class="text-xs text-zinc-400">Nenhuma opção ainda.</p>
                    </div>
                </div>
                <div class="flex justify-end gap-2 px-6 py-4 border-t border-zinc-100 dark:border-zinc-800">
                    <Button variant="outline" @click="closeQModal">Cancelar</Button>
                    <Button :disabled="savingQuestion" @click="saveQuestion">
                        <Loader2 v-if="savingQuestion" class="h-4 w-4 mr-1 animate-spin" />
                        {{ editingQuestion ? 'Salvar' : 'Adicionar' }}
                    </Button>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- Modal: Criar / Editar Regra de Pontuação -->
    <Teleport to="body">
        <div v-if="ruleModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm" @click.self="ruleModal = false">
            <div class="w-full max-w-lg rounded-2xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 shadow-2xl overflow-hidden">
                <div class="flex items-center justify-between px-6 py-4 border-b border-zinc-100 dark:border-zinc-800">
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ editingRule ? 'Editar Regra' : 'Nova Regra de Pontuação' }}</h2>
                    <button type="button" class="text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200" @click="ruleModal = false"><X class="h-5 w-5" /></button>
                </div>
                <div class="p-6 space-y-4 max-h-[70vh] overflow-y-auto">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Pontuação mínima</label>
                            <input v-model.number="ruleForm.min_score" type="number" class="w-full rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500/30" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Pontuação máxima</label>
                            <input v-model.number="ruleForm.max_score" type="number" class="w-full rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500/30" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Rótulo do resultado *</label>
                        <input v-model="ruleForm.result_label" type="text" placeholder="Ex: Indicativo de TDAH — Atenção necessária" class="w-full rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500/30" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Descrição do resultado</label>
                        <textarea v-model="ruleForm.result_description" rows="3" placeholder="Explicação do que este resultado significa…" class="w-full rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500/30 resize-none" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">
                            Tags de Desafio geradas
                            <span class="text-xs font-normal text-zinc-400 ml-1">— adicionadas ao perfil do aluno</span>
                        </label>
                        <div class="flex gap-2">
                            <input v-model="newTagInput" type="text" placeholder="Ex: foco, impulsividade…" class="flex-1 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/30" @keydown.enter.prevent="addTag" />
                            <button type="button" class="rounded-xl bg-emerald-600 text-white px-4 py-2 text-sm font-medium hover:bg-emerald-700 transition" @click="addTag">+</button>
                        </div>
                        <div v-if="ruleForm.challenge_tags.length" class="flex flex-wrap gap-1.5 mt-2">
                            <span v-for="(tag, ti) in ruleForm.challenge_tags" :key="ti" class="flex items-center gap-1 rounded-full bg-emerald-100 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 px-2.5 py-1 text-xs font-medium">
                                {{ tag }}
                                <button type="button" class="hover:text-red-500" @click="removeTag(ti)"><X class="h-3 w-3" /></button>
                            </span>
                        </div>
                        <p v-else class="text-xs text-zinc-400 mt-1">Nenhuma tag adicionada.</p>
                    </div>
                </div>
                <div class="flex justify-end gap-2 px-6 py-4 border-t border-zinc-100 dark:border-zinc-800">
                    <Button variant="outline" @click="ruleModal = false">Cancelar</Button>
                    <Button :disabled="savingRule" @click="saveRule">
                        <Loader2 v-if="savingRule" class="h-4 w-4 mr-1 animate-spin" />
                        {{ editingRule ? 'Salvar' : 'Criar Regra' }}
                    </Button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
