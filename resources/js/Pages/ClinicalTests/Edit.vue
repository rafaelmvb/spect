<script setup>
import { ref, reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import LayoutInfoprodutor from '@/Layouts/LayoutInfoprodutor.vue';
import Button from '@/components/ui/Button.vue';
import {
    ArrowLeft, ClipboardList, ListChecks, Award, BrainCircuit,
    Plus, Pencil, Trash2, X, Loader2, CheckCircle2, AlertCircle,
    Upload, FileText, Image, Link2, Check, Save,
} from 'lucide-vue-next';

defineOptions({ layout: LayoutInfoprodutor });

const props = defineProps({
    test:      { type: Object, required: true },
    questions: { type: Array, default: () => [] },
    rules:     { type: Array, default: () => [] },
});

// ─── Toast
const toast = ref(null);
function showToast(msg, type = 'success') {
    toast.value = { msg, type };
    setTimeout(() => { toast.value = null; }, 4000);
}

// ─── Tab
const tab = ref('geral');
const TABS = [
    { id: 'geral',          label: 'Geral',           icon: ClipboardList },
    { id: 'perguntas',      label: 'Perguntas',        icon: ListChecks },
    { id: 'pontuacao',      label: 'Pontuação & Tags', icon: Award },
    { id: 'instrucoes_ia',  label: 'Instruções IA',    icon: BrainCircuit },
];

// ─── Tab Geral: formulário do teste
const form = reactive({
    name:               props.test.name ?? '',
    category:           props.test.category ?? 'geral',
    description:        props.test.description ?? '',
    instructions:       props.test.instructions ?? '',
    estimated_minutes:  props.test.estimated_minutes ?? 5,
    is_active:          props.test.is_active ?? true,
});
const savingForm  = ref(false);
const formSaved   = ref(false);

const CATEGORIES = [
    { value: 'tdah',      label: 'TDAH' },
    { value: 'tea',       label: 'TEA' },
    { value: 'ah_sd',     label: 'AH/SD' },
    { value: 'humor',     label: 'Humor' },
    { value: 'ansiedade', label: 'Ansiedade' },
    { value: 'geral',     label: 'Geral' },
];

async function saveForm() {
    if (savingForm.value) return;
    savingForm.value = true;
    try {
        await axios.put(`/testes-clinicos/${props.test.id}`, form);
        formSaved.value = true;
        setTimeout(() => { formSaved.value = false; }, 2000);
        showToast('Teste atualizado.');
    } catch (e) {
        showToast(e.response?.data?.message ?? 'Erro ao salvar.', 'error');
    } finally {
        savingForm.value = false;
    }
}

// ─── Tab Perguntas
const questions    = ref([...props.questions]);
const loadingQ     = ref(false);
const questionModal = ref(false);
const editingQ     = ref(null);
const savingQ      = ref(false);

const emptyQForm = () => ({
    text: '', type: 'scale', scale_min: 1, scale_max: 5,
    scale_labels: {}, options: [],
});
const qForm = ref(emptyQForm());

function openCreateQ() {
    editingQ.value = null;
    qForm.value = emptyQForm();
    questionModal.value = true;
}
function openEditQ(q) {
    editingQ.value = q;
    qForm.value = {
        text: q.text, type: q.type,
        scale_min: q.scale_min, scale_max: q.scale_max,
        scale_labels: { ...(q.scale_labels ?? {}) },
        options: (q.options ?? []).map(o => ({ ...o })),
    };
    questionModal.value = true;
}

async function saveQuestion() {
    if (savingQ.value) return;
    savingQ.value = true;
    try {
        const url = editingQ.value
            ? `/testes-clinicos/${props.test.id}/perguntas/${editingQ.value.id}`
            : `/testes-clinicos/${props.test.id}/perguntas`;
        const { data } = await axios({ method: editingQ.value ? 'put' : 'post', url, data: qForm.value });
        if (editingQ.value) {
            const idx = questions.value.findIndex(q => q.id === editingQ.value.id);
            if (idx >= 0) questions.value[idx] = data.question;
        } else {
            questions.value.push(data.question);
        }
        showToast(editingQ.value ? 'Pergunta atualizada.' : 'Pergunta adicionada.');
        questionModal.value = false;
    } catch (e) {
        showToast(e.response?.data?.message ?? 'Erro ao salvar.', 'error');
    } finally {
        savingQ.value = false;
    }
}

async function deleteQuestion(q) {
    if (!confirm('Excluir esta pergunta?')) return;
    await axios.delete(`/testes-clinicos/${props.test.id}/perguntas/${q.id}`);
    questions.value = questions.value.filter(x => x.id !== q.id);
    showToast('Pergunta excluída.');
}

function addOption() { qForm.value.options.push({ text: '', value: qForm.value.options.length }); }
function removeOption(i) { qForm.value.options.splice(i, 1); }

// ─── Tab Pontuação
const rules       = ref([...props.rules]);
const ruleModal   = ref(false);
const editingRule = ref(null);
const savingRule  = ref(false);
const newTagInput = ref('');

const emptyRuleForm = () => ({ min_score: 0, max_score: 10, result_label: '', result_description: '', challenge_tags: [] });
const ruleForm = ref(emptyRuleForm());

function openCreateRule() {
    editingRule.value = null;
    ruleForm.value = emptyRuleForm();
    newTagInput.value = '';
    ruleModal.value = true;
}
function openEditRule(r) {
    editingRule.value = r;
    ruleForm.value = {
        min_score: r.min_score, max_score: r.max_score,
        result_label: r.result_label, result_description: r.result_description ?? '',
        challenge_tags: [...(r.challenge_tags ?? [])],
    };
    newTagInput.value = '';
    ruleModal.value = true;
}

function addTag() {
    const t = newTagInput.value.trim().toLowerCase();
    if (t && !ruleForm.value.challenge_tags.includes(t)) ruleForm.value.challenge_tags.push(t);
    newTagInput.value = '';
}
function removeTag(i) { ruleForm.value.challenge_tags.splice(i, 1); }

async function saveRule() {
    if (savingRule.value) return;
    savingRule.value = true;
    try {
        const url = editingRule.value
            ? `/testes-clinicos/${props.test.id}/regras/${editingRule.value.id}`
            : `/testes-clinicos/${props.test.id}/regras`;
        const { data } = await axios({ method: editingRule.value ? 'put' : 'post', url, data: ruleForm.value });
        if (editingRule.value) {
            const idx = rules.value.findIndex(r => r.id === editingRule.value.id);
            if (idx >= 0) rules.value[idx] = data.rule;
        } else {
            rules.value.push(data.rule);
        }
        showToast('Regra salva.');
        ruleModal.value = false;
    } catch (e) {
        showToast(e.response?.data?.message ?? 'Erro ao salvar.', 'error');
    } finally {
        savingRule.value = false;
    }
}

async function deleteRule(r) {
    if (!confirm('Excluir esta regra?')) return;
    await axios.delete(`/testes-clinicos/${props.test.id}/regras/${r.id}`);
    rules.value = rules.value.filter(x => x.id !== r.id);
    showToast('Regra excluída.');
}

// ─── Tab Instruções IA
const aiContext = reactive({
    instructions: props.test.ai_context?.instructions ?? '',
    files:        [...(props.test.ai_context?.files ?? [])],
});
const aiSaving    = ref(false);
const aiSaved     = ref(false);
const aiUploading = ref(false);

async function saveAiInstructions() {
    aiSaving.value = true;
    try {
        await axios.put(`/testes-clinicos/${props.test.id}/ai-context`, {
            instructions: aiContext.instructions,
        });
        aiSaved.value = true;
        setTimeout(() => { aiSaved.value = false; }, 2000);
        showToast('Instruções salvas.');
    } catch {
        showToast('Erro ao salvar.', 'error');
    } finally {
        aiSaving.value = false;
    }
}

async function uploadAiFile(event) {
    const file = event.target.files?.[0];
    if (!file) return;
    event.target.value = '';
    aiUploading.value = true;
    try {
        const fd = new FormData();
        fd.append('file', file);
        const { data } = await axios.post(`/testes-clinicos/${props.test.id}/ai-context/upload`, fd);
        aiContext.files.push(data.file);
        showToast('Arquivo enviado.');
    } catch (e) {
        showToast(e.response?.data?.message ?? 'Erro no upload.', 'error');
    } finally {
        aiUploading.value = false;
    }
}

async function deleteAiFile(fileId) {
    if (!confirm('Remover este arquivo?')) return;
    await axios.delete(`/testes-clinicos/${props.test.id}/ai-context/files/${fileId}`);
    const idx = aiContext.files.findIndex(f => f.id === fileId);
    if (idx !== -1) aiContext.files.splice(idx, 1);
    showToast('Arquivo removido.');
}

function formatBytes(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}
</script>

<template>
    <div class="p-4 lg:p-8 max-w-5xl mx-auto">

        <!-- Toast -->
        <Transition enter-active-class="transition duration-200" enter-from-class="opacity-0 -translate-y-2" enter-to-class="opacity-100" leave-active-class="transition duration-150" leave-to-class="opacity-0">
            <div v-if="toast" :class="['fixed top-4 right-4 z-50 flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium shadow-xl', toast.type === 'error' ? 'bg-red-600 text-white' : 'bg-emerald-600 text-white']">
                <CheckCircle2 v-if="toast.type !== 'error'" class="h-4 w-4" />
                <AlertCircle v-else class="h-4 w-4" />
                {{ toast.msg }}
            </div>
        </Transition>

        <!-- Header -->
        <div class="flex items-center gap-3 mb-6">
            <button type="button" class="rounded-lg p-1.5 text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-700 hover:text-zinc-700 dark:hover:text-zinc-200 transition" @click="router.visit('/testes-clinicos')">
                <ArrowLeft class="h-5 w-5" />
            </button>
            <div class="flex-1 min-w-0">
                <p class="text-xs text-zinc-400 mb-0.5">Testes Clínicos</p>
                <h1 class="text-lg font-bold text-zinc-900 dark:text-white truncate">{{ form.name || 'Editar Teste' }}</h1>
            </div>
            <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="form.is_active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400' : 'bg-zinc-100 text-zinc-500 dark:bg-zinc-700 dark:text-zinc-400'">
                {{ form.is_active ? 'Ativo' : 'Inativo' }}
            </span>
        </div>

        <!-- Layout: sidebar + conteúdo -->
        <div class="flex flex-col lg:flex-row gap-6">

            <!-- Sidebar de tabs -->
            <aside class="lg:w-52 shrink-0">
                <nav class="flex lg:flex-col gap-1 overflow-x-auto lg:overflow-visible rounded-xl bg-zinc-100 dark:bg-zinc-800/50 p-1.5">
                    <button v-for="t in TABS" :key="t.id" type="button"
                        :class="['flex items-center gap-2.5 rounded-lg px-3 py-2.5 text-sm font-medium transition whitespace-nowrap w-full', tab === t.id ? 'bg-white dark:bg-zinc-700 text-emerald-600 dark:text-emerald-400 shadow-sm' : 'text-zinc-600 dark:text-zinc-400 hover:bg-white/60 dark:hover:bg-zinc-700/60']"
                        @click="tab = t.id">
                        <component :is="t.icon" class="h-4 w-4 shrink-0" />
                        {{ t.label }}
                        <span v-if="t.id === 'instrucoes_ia' && aiContext.instructions" class="ml-auto w-2 h-2 rounded-full bg-sky-500 shrink-0" />
                    </button>
                </nav>
            </aside>

            <!-- Conteúdo -->
            <div class="flex-1 min-w-0 space-y-6">

                <!-- ── Tab: Geral ── -->
                <template v-if="tab === 'geral'">
                    <div class="rounded-2xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 overflow-hidden">
                        <div class="border-b border-zinc-100 dark:border-zinc-700 px-6 py-4">
                            <h2 class="text-base font-semibold text-zinc-900 dark:text-white">Informações do Teste</h2>
                        </div>
                        <div class="p-6 space-y-5">
                            <div>
                                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1.5">Nome *</label>
                                <input v-model="form.name" type="text" placeholder="Ex: Escala de Desatenção Adulto (ASRS)"
                                    class="w-full rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900 px-3.5 py-2.5 text-sm text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500" />
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1.5">Categoria *</label>
                                    <select v-model="form.category"
                                        class="w-full rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900 px-3.5 py-2.5 text-sm text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-emerald-500/30">
                                        <option v-for="c in CATEGORIES" :key="c.value" :value="c.value">{{ c.label }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1.5">Tempo estimado (min)</label>
                                    <input v-model.number="form.estimated_minutes" type="number" min="1" max="999"
                                        class="w-full rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900 px-3.5 py-2.5 text-sm text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-emerald-500/30" />
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1.5">Descrição</label>
                                <textarea v-model="form.description" rows="3" placeholder="Breve descrição do objetivo do teste…"
                                    class="w-full rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900 px-3.5 py-2.5 text-sm text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 resize-none" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1.5">Instruções para o aluno</label>
                                <textarea v-model="form.instructions" rows="4" placeholder="Texto exibido ao aluno antes de iniciar o teste…"
                                    class="w-full rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900 px-3.5 py-2.5 text-sm text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 resize-none" />
                            </div>
                            <label class="flex items-center gap-2.5 cursor-pointer">
                                <input v-model="form.is_active" type="checkbox" class="rounded accent-emerald-500 w-4 h-4" />
                                <span class="text-sm text-zinc-700 dark:text-zinc-300">Ativo (visível para alunos)</span>
                            </label>
                        </div>
                        <div class="flex items-center justify-end gap-3 border-t border-zinc-100 dark:border-zinc-700 px-6 py-4">
                            <span v-if="formSaved" class="flex items-center gap-1.5 text-sm text-emerald-600 dark:text-emerald-400">
                                <Check class="h-4 w-4" />Salvo
                            </span>
                            <Button :disabled="savingForm" @click="saveForm">
                                <Loader2 v-if="savingForm" class="h-4 w-4 mr-1.5 animate-spin" />
                                <Save v-else class="h-4 w-4 mr-1.5" />
                                {{ savingForm ? 'Salvando…' : 'Salvar alterações' }}
                            </Button>
                        </div>
                    </div>
                </template>

                <!-- ── Tab: Perguntas ── -->
                <template v-if="tab === 'perguntas'">
                    <div class="rounded-2xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 overflow-hidden">
                        <div class="flex items-center justify-between border-b border-zinc-100 dark:border-zinc-700 px-6 py-4">
                            <div>
                                <h2 class="text-base font-semibold text-zinc-900 dark:text-white">Perguntas</h2>
                                <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-0.5">{{ questions.length }} pergunta{{ questions.length !== 1 ? 's' : '' }}</p>
                            </div>
                            <Button @click="openCreateQ"><Plus class="h-4 w-4 mr-1" />Adicionar</Button>
                        </div>
                        <div class="p-4 space-y-2">
                            <div v-if="!questions.length" class="text-center py-10 text-sm text-zinc-400">
                                Nenhuma pergunta ainda. Clique em "Adicionar" para criar a primeira.
                            </div>
                            <div v-for="(q, qi) in questions" :key="q.id"
                                class="flex items-start gap-3 rounded-xl border border-zinc-100 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900 p-4">
                                <span class="shrink-0 text-xs font-mono text-zinc-400 mt-0.5 w-5">{{ qi + 1 }}.</span>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm text-zinc-800 dark:text-zinc-200 leading-snug">{{ q.text }}</p>
                                    <div class="flex items-center gap-2 mt-1.5">
                                        <span class="text-xs text-zinc-400 bg-zinc-200 dark:bg-zinc-700 rounded px-1.5 py-0.5">{{ q.type }}</span>
                                        <span v-if="q.type === 'scale'" class="text-xs text-zinc-400">{{ q.scale_min }}–{{ q.scale_max }}</span>
                                        <span v-if="q.options?.length" class="text-xs text-zinc-400">{{ q.options.length }} opções</span>
                                    </div>
                                </div>
                                <div class="flex gap-1 shrink-0">
                                    <button type="button" class="p-1.5 rounded-lg text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 hover:bg-zinc-200 dark:hover:bg-zinc-700 transition" @click="openEditQ(q)"><Pencil class="h-3.5 w-3.5" /></button>
                                    <button type="button" class="p-1.5 rounded-lg text-zinc-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-950/30 transition" @click="deleteQuestion(q)"><Trash2 class="h-3.5 w-3.5" /></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- ── Tab: Pontuação & Tags ── -->
                <template v-if="tab === 'pontuacao'">
                    <div class="rounded-2xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 overflow-hidden">
                        <div class="flex items-center justify-between border-b border-zinc-100 dark:border-zinc-700 px-6 py-4">
                            <div>
                                <h2 class="text-base font-semibold text-zinc-900 dark:text-white">Regras de Pontuação</h2>
                                <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-0.5">Faixas de score → resultado e tags de desafio</p>
                            </div>
                            <Button variant="outline" @click="openCreateRule"><Plus class="h-4 w-4 mr-1" />Adicionar</Button>
                        </div>
                        <div class="p-4 space-y-2">
                            <div v-if="!rules.length" class="text-center py-10 text-sm text-zinc-400">
                                Nenhuma regra cadastrada.<br>
                                <span class="text-zinc-300 dark:text-zinc-600">Defina faixas de pontuação, o rótulo do resultado e as tags geradas.</span>
                            </div>
                            <div v-for="r in rules" :key="r.id"
                                class="flex items-start gap-3 rounded-xl border border-zinc-100 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900 p-4">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="font-mono text-xs bg-violet-100 dark:bg-violet-950/40 text-violet-700 dark:text-violet-400 rounded px-2 py-0.5">{{ r.min_score }}–{{ r.max_score }}</span>
                                        <span class="font-semibold text-sm text-zinc-800 dark:text-zinc-200">{{ r.result_label }}</span>
                                    </div>
                                    <p v-if="r.result_description" class="text-xs text-zinc-400 mt-1 line-clamp-2">{{ r.result_description }}</p>
                                    <div v-if="r.challenge_tags?.length" class="flex flex-wrap gap-1 mt-1.5">
                                        <span v-for="tag in r.challenge_tags" :key="tag" class="rounded-full bg-emerald-100 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 px-2 py-0.5 text-xs">{{ tag }}</span>
                                    </div>
                                </div>
                                <div class="flex gap-1 shrink-0">
                                    <button type="button" class="p-1.5 rounded-lg text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 hover:bg-zinc-200 dark:hover:bg-zinc-700 transition" @click="openEditRule(r)"><Pencil class="h-3.5 w-3.5" /></button>
                                    <button type="button" class="p-1.5 rounded-lg text-zinc-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-950/30 transition" @click="deleteRule(r)"><Trash2 class="h-3.5 w-3.5" /></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- ── Tab: Instruções IA ── -->
                <template v-if="tab === 'instrucoes_ia'">
                    <div class="space-y-6">

                        <!-- Instruções de texto -->
                        <div class="rounded-2xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 overflow-hidden">
                            <div class="border-b border-zinc-100 dark:border-zinc-700 px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <BrainCircuit class="h-4 w-4 text-sky-500" />
                                    <h2 class="text-base font-semibold text-zinc-900 dark:text-white">Instruções para a IA</h2>
                                </div>
                                <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">
                                    Descreva o que este teste mede, como interpretar as pontuações e o que a IA deve focar ao gerar o relatório do aluno.
                                </p>
                            </div>
                            <div class="p-6">
                                <textarea v-model="aiContext.instructions" rows="10"
                                    placeholder="Ex.: Este é o ASRS-v1.1, rastreio de TDAH em adultos. Pontuação ≥ 24 nos itens A (Q1–Q6) indica forte indicativo de desatenção. Ao gerar o relatório, foque nos padrões de desatenção vs. hiperatividade/impulsividade, use exemplos práticos do cotidiano e recomende avaliação neuropsicológica quando o escore for elevado…"
                                    class="w-full resize-y rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900 px-4 py-3 text-sm text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500" />
                                <div class="mt-3 flex items-center justify-end gap-3">
                                    <span v-if="aiSaved" class="flex items-center gap-1.5 text-sm text-emerald-600 dark:text-emerald-400">
                                        <Check class="h-4 w-4" />Salvo
                                    </span>
                                    <Button :disabled="aiSaving" @click="saveAiInstructions">
                                        <Loader2 v-if="aiSaving" class="h-4 w-4 mr-1.5 animate-spin" />
                                        {{ aiSaving ? 'Salvando…' : 'Salvar instruções' }}
                                    </Button>
                                </div>
                                <p class="mt-3 text-xs text-zinc-400 dark:text-zinc-500">
                                    Quando preenchido, o aluno verá o botão <strong class="text-zinc-600 dark:text-zinc-300">"Gerar Relatório IA"</strong> após concluir o teste.
                                </p>
                            </div>
                        </div>

                        <!-- Upload de arquivos de referência -->
                        <div class="rounded-2xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 overflow-hidden">
                            <div class="border-b border-zinc-100 dark:border-zinc-700 px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <FileText class="h-4 w-4 text-sky-500" />
                                    <h2 class="text-base font-semibold text-zinc-900 dark:text-white">Arquivos e Imagens de Referência</h2>
                                </div>
                                <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">
                                    PDFs, documentos e imagens que a IA poderá usar como contexto adicional ao gerar relatórios deste teste.
                                </p>
                            </div>
                            <div class="p-6 space-y-4">
                                <!-- Zona de upload -->
                                <label class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-zinc-300 dark:border-zinc-600 bg-zinc-50 dark:bg-zinc-900 px-6 py-8 text-center transition hover:border-sky-400 hover:bg-sky-50 dark:hover:border-sky-500 dark:hover:bg-sky-950/10">
                                    <input type="file" class="sr-only" accept=".pdf,.doc,.docx,.txt,.png,.jpg,.jpeg,.gif,.webp" :disabled="aiUploading" @change="uploadAiFile" />
                                    <Upload v-if="!aiUploading" class="h-8 w-8 text-zinc-400 dark:text-zinc-500" />
                                    <Loader2 v-else class="h-8 w-8 animate-spin text-sky-500" />
                                    <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                        {{ aiUploading ? 'Enviando…' : 'Clique para enviar um arquivo' }}
                                    </span>
                                    <span class="text-xs text-zinc-400 dark:text-zinc-500">PDF, DOC, DOCX, TXT, PNG, JPG, WEBP · Máx 10 MB</span>
                                </label>

                                <!-- Lista de arquivos -->
                                <div v-if="aiContext.files.length" class="space-y-2">
                                    <div v-for="f in aiContext.files" :key="f.id"
                                        class="flex items-center gap-3 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900 px-4 py-3">
                                        <Image v-if="f.type === 'image'" class="h-5 w-5 shrink-0 text-sky-400" />
                                        <FileText v-else class="h-5 w-5 shrink-0 text-zinc-400" />
                                        <div class="flex-1 min-w-0">
                                            <p class="truncate text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ f.name }}</p>
                                            <p class="text-xs text-zinc-400 dark:text-zinc-500">{{ formatBytes(f.size) }}</p>
                                        </div>
                                        <a v-if="f.url" :href="f.url" target="_blank"
                                            class="shrink-0 rounded-lg p-1.5 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300 hover:bg-zinc-200 dark:hover:bg-zinc-700 transition" title="Ver arquivo">
                                            <Link2 class="h-4 w-4" />
                                        </a>
                                        <button type="button"
                                            class="shrink-0 rounded-lg p-1.5 text-zinc-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-950/30 transition" title="Remover"
                                            @click="deleteAiFile(f.id)">
                                            <Trash2 class="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>
                                <p v-else class="text-center text-sm text-zinc-400 dark:text-zinc-500">Nenhum arquivo enviado ainda.</p>
                            </div>
                        </div>

                    </div>
                </template>

            </div>
        </div>
    </div>

    <!-- Modal: Criar / Editar Pergunta -->
    <Teleport to="body">
        <div v-if="questionModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm" @click.self="questionModal = false">
            <div class="w-full max-w-lg rounded-2xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 shadow-2xl overflow-hidden">
                <div class="flex items-center justify-between px-6 py-4 border-b border-zinc-100 dark:border-zinc-800">
                    <h2 class="text-base font-semibold text-zinc-900 dark:text-white">{{ editingQ ? 'Editar Pergunta' : 'Nova Pergunta' }}</h2>
                    <button type="button" class="text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200" @click="questionModal = false"><X class="h-5 w-5" /></button>
                </div>
                <div class="p-6 space-y-4 max-h-[70vh] overflow-y-auto">
                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Texto da pergunta *</label>
                        <textarea v-model="qForm.text" rows="3" placeholder="Ex: Com que frequência você tem dificuldade para manter o foco?"
                            class="w-full rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-sm text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 resize-none" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Tipo *</label>
                        <select v-model="qForm.type" class="w-full rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-sm text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-2 focus:ring-emerald-500/30">
                            <option value="scale">Escala (1–N)</option>
                            <option value="boolean">Sim / Não</option>
                            <option value="single">Múltipla escolha (1 opção)</option>
                            <option value="multi">Múltipla escolha (várias opções)</option>
                            <option value="text">Resposta em texto livre</option>
                        </select>
                    </div>
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
                    <Button variant="outline" @click="questionModal = false">Cancelar</Button>
                    <Button :disabled="savingQ" @click="saveQuestion">
                        <Loader2 v-if="savingQ" class="h-4 w-4 mr-1 animate-spin" />
                        {{ editingQ ? 'Salvar' : 'Adicionar' }}
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
                    <h2 class="text-base font-semibold text-zinc-900 dark:text-white">{{ editingRule ? 'Editar Regra' : 'Nova Regra de Pontuação' }}</h2>
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
                        <input v-model="ruleForm.result_label" type="text" placeholder="Ex: Indicativo de TDAH — Atenção necessária"
                            class="w-full rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500/30" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">Descrição do resultado</label>
                        <textarea v-model="ruleForm.result_description" rows="3" placeholder="Explicação exibida ao aluno ao concluir o teste…"
                            class="w-full rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500/30 resize-none" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">
                            Tags de Desafio
                            <span class="text-xs font-normal text-zinc-400 ml-1">— adicionadas ao perfil do aluno</span>
                        </label>
                        <div class="flex gap-2">
                            <input v-model="newTagInput" type="text" placeholder="Ex: foco, impulsividade…"
                                class="flex-1 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/30"
                                @keydown.enter.prevent="addTag" />
                            <button type="button" class="rounded-xl bg-emerald-600 text-white px-4 py-2 text-sm font-medium hover:bg-emerald-700 transition" @click="addTag">+</button>
                        </div>
                        <div v-if="ruleForm.challenge_tags.length" class="flex flex-wrap gap-1.5 mt-2">
                            <span v-for="(tag, ti) in ruleForm.challenge_tags" :key="ti"
                                class="flex items-center gap-1 rounded-full bg-emerald-100 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 px-2.5 py-1 text-xs font-medium">
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
