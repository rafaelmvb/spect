<script setup>
import { ref, watch, computed } from 'vue';
import { X, Pencil, Trash2, Package, Loader2, ClipboardList, ChevronDown, ChevronUp, ShieldOff, ShieldCheck, Plus } from 'lucide-vue-next';
import axios from 'axios';
import Button from '@/components/ui/Button.vue';
import Checkbox from '@/components/ui/Checkbox.vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    aluno: { type: Object, default: null },
    produtos: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'updated', 'deleted']);

const editing = ref(false);
const saving = ref(false);
const blocking = ref(false);
const form = ref({
    name: '',
    email: '',
    password: '',
    product_ids: [],
});
const removingProductId = ref(null);
const addingProductId   = ref(null);
const showAddDropdown   = ref(false);
const deleting = ref(false);

const availableProducts = computed(() => {
    const currentIds = new Set((props.aluno?.products ?? []).map(p => p.id));
    return props.produtos.filter(p => !currentIds.has(p.id));
});
const toast = ref({ message: null, type: null });
const quizResponses = ref([]);
const quizLoading = ref(false);
const quizLoaded = ref(false);
const expandedQuiz = ref(null);

watch(
    () => props.aluno,
    (a) => {
        if (a) {
            form.value = {
                name: a.name ?? '',
                email: a.email ?? '',
                password: '',
                product_ids: (a.products ?? []).map((p) => p.id),
            };
        }
        editing.value = false;
        showAddDropdown.value = false;
        quizResponses.value = [];
        quizLoaded.value = false;
        expandedQuiz.value = null;
    },
    { immediate: true }
);

async function loadQuizResponses() {
    if (!props.aluno || quizLoaded.value) return;
    quizLoading.value = true;
    try {
        const { data } = await axios.get(`/produtos/alunos/${props.aluno.id}/quiz-responses`);
        quizResponses.value = data.quiz_responses ?? [];
        quizLoaded.value = true;
    } catch {
        quizResponses.value = [];
    } finally {
        quizLoading.value = false;
    }
}

function close() {
    emit('close');
}

function startEdit() {
    editing.value = true;
}

function cancelEdit() {
    editing.value = false;
    if (props.aluno) {
        form.value = {
            name: props.aluno.name ?? '',
            email: props.aluno.email ?? '',
            password: '',
            product_ids: (props.aluno.products ?? []).map((p) => p.id),
        };
    }
}

async function save() {
    if (!props.aluno) return;
    saving.value = true;
    try {
        const { data } = await axios.put(`/produtos/alunos/${props.aluno.id}`, {
            name: form.value.name,
            email: form.value.email,
            password: form.value.password || undefined,
            product_ids: form.value.product_ids,
        });
        showToast(data.message ?? 'Aluno atualizado.', 'success');
        editing.value = false;
        emit('updated', data.aluno);
    } catch (err) {
        showToast(
            err.response?.data?.message ?? 'Erro ao atualizar. Tente novamente.',
            'error'
        );
    } finally {
        saving.value = false;
    }
}

async function addProduct(produto) {
    if (!props.aluno) return;
    addingProductId.value = produto.id;
    showAddDropdown.value = false;
    try {
        const { data } = await axios.post(`/produtos/alunos/${props.aluno.id}/produtos/${produto.id}`);
        showToast(data.message ?? 'Trilha adicionada.', 'success');
        emit('updated', { ...props.aluno, products: data.products, products_count: data.products.length });
    } catch (err) {
        showToast(err.response?.data?.message ?? 'Erro ao adicionar trilha.', 'error');
    } finally {
        addingProductId.value = null;
    }
}

async function removeProduct(produtoId) {
    if (!props.aluno) return;
    removingProductId.value = produtoId;
    try {
        const { data } = await axios.delete(
            `/produtos/alunos/${props.aluno.id}/produtos/${produtoId}`
        );
        showToast(data.message ?? 'Acesso removido.', 'success');
        emit('updated', {
            ...props.aluno,
            products_count: data.products_count ?? 0,
            products: (props.aluno.products ?? []).filter((p) => p.id !== produtoId),
        });
    } catch (err) {
        showToast(
            err.response?.data?.message ?? 'Erro ao remover acesso.',
            'error'
        );
    } finally {
        removingProductId.value = null;
    }
}

async function toggleBlock() {
    if (!props.aluno) return;
    const action = props.aluno.is_blocked ? 'desbloquear' : 'bloquear';
    if (!window.confirm(`Tem certeza que deseja ${action} este aluno?`)) return;
    blocking.value = true;
    try {
        const { data } = await window.axios.put(`/produtos/alunos/${props.aluno.id}/toggle-block`);
        showToast(data.message, 'success');
        emit('updated', { ...props.aluno, is_blocked: data.is_blocked, blocked_at: data.blocked_at });
    } catch (err) {
        showToast(err.response?.data?.message ?? 'Erro ao alterar bloqueio.', 'error');
    } finally {
        blocking.value = false;
    }
}

async function deleteAluno() {
    if (!props.aluno) return;
    if (!window.confirm('Tem certeza que deseja excluir este aluno? Esta ação não pode ser desfeita.')) {
        return;
    }
    deleting.value = true;
    try {
        await axios.delete(`/produtos/alunos/${props.aluno.id}`);
        showToast('Aluno excluído com sucesso.', 'success');
        close();
        emit('deleted', props.aluno.id);
    } catch (err) {
        showToast(
            err.response?.data?.message ?? 'Erro ao excluir.',
            'error'
        );
    } finally {
        deleting.value = false;
    }
}

function showToast(message, type) {
    toast.value = { message, type };
    setTimeout(() => {
        toast.value = { message: null, type: null };
    }, 4000);
}
</script>

<template>
    <Teleport to="body">
        <div
            v-show="open"
            class="fixed inset-0 z-[100000] flex justify-end"
            aria-modal="true"
            role="dialog"
        >
            <div
                class="fixed inset-0 bg-zinc-900/50 dark:bg-zinc-950/60"
                aria-hidden="true"
                @click="close"
            />
            <aside
                class="relative flex h-full w-full max-w-md flex-col rounded-l-2xl bg-white shadow-2xl dark:bg-zinc-900"
            >
                <div class="flex items-center justify-between rounded-tl-2xl px-5 py-5">
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">
                        {{ editing ? 'Editar aluno' : 'Detalhes do aluno' }}
                    </h2>
                    <button
                        type="button"
                        class="rounded-lg p-2 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-800 dark:hover:text-zinc-300"
                        aria-label="Fechar"
                        @click="close"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <div v-if="!aluno" class="flex flex-1 items-center justify-center p-8">
                    <p class="text-sm text-zinc-500">Nenhum aluno selecionado.</p>
                </div>

                <div v-else class="flex flex-1 flex-col overflow-hidden">
                    <div class="flex-1 overflow-y-auto p-5">
                        <div v-if="!editing" class="space-y-5">
                            <!-- Badge de status -->
                            <div v-if="aluno.is_blocked" class="flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2 dark:border-red-900/50 dark:bg-red-900/20">
                                <ShieldOff class="h-4 w-4 shrink-0 text-red-600 dark:text-red-400" />
                                <span class="text-xs font-medium text-red-700 dark:text-red-300">
                                    Conta bloqueada
                                </span>
                            </div>
                            <div class="space-y-1">
                                <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                    Nome
                                </p>
                                <p class="text-sm text-zinc-900 dark:text-white">{{ aluno.name }}</p>
                            </div>
                            <div class="space-y-1">
                                <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                    E-mail
                                </p>
                                <p class="text-sm text-zinc-900 dark:text-white">{{ aluno.email }}</p>
                            </div>
                            <div class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                        Produtos com acesso
                                    </p>
                                    <div class="relative">
                                        <button
                                            v-if="availableProducts.length"
                                            type="button"
                                            class="flex items-center gap-1 rounded-lg border border-zinc-200 px-2 py-1 text-xs font-medium text-zinc-600 hover:border-violet-400 hover:text-violet-600 dark:border-zinc-700 dark:text-zinc-400 dark:hover:text-violet-400"
                                            @click="showAddDropdown = !showAddDropdown"
                                        >
                                            <Plus class="h-3.5 w-3.5" /> Adicionar
                                        </button>
                                        <div
                                            v-if="showAddDropdown"
                                            class="absolute right-0 top-full z-10 mt-1 w-56 rounded-xl border border-zinc-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-800"
                                        >
                                            <button
                                                v-for="p in availableProducts"
                                                :key="p.id"
                                                type="button"
                                                class="flex w-full items-center gap-2 px-3 py-2.5 text-left text-sm text-zinc-800 hover:bg-zinc-50 first:rounded-t-xl last:rounded-b-xl dark:text-zinc-200 dark:hover:bg-zinc-700/50"
                                                :disabled="addingProductId === p.id"
                                                @click="addProduct(p)"
                                            >
                                                <Package class="h-4 w-4 shrink-0 text-zinc-400" />
                                                {{ p.name }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div
                                    v-for="p in (aluno.products ?? [])"
                                    :key="p.id"
                                    class="flex items-center justify-between rounded-lg border border-zinc-200 bg-zinc-50 py-2 pl-3 pr-2 dark:border-zinc-700 dark:bg-zinc-800/50"
                                >
                                    <span class="flex items-center gap-2 text-sm text-zinc-900 dark:text-white">
                                        <Package class="h-4 w-4 text-zinc-500" />
                                        {{ p.name }}
                                    </span>
                                    <button
                                        type="button"
                                        class="rounded-lg px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20"
                                        :disabled="removingProductId === p.id"
                                        @click="removeProduct(p.id)"
                                    >
                                        {{ removingProductId === p.id ? 'Removendo...' : 'Remover' }}
                                    </button>
                                </div>
                                <p v-if="!aluno.products?.length" class="text-sm text-zinc-500">
                                    Nenhum produto
                                </p>
                            </div>
                            <!-- Quizzes respondidos -->
                            <div class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                        Quizzes respondidos
                                    </p>
                                    <button
                                        type="button"
                                        class="flex items-center gap-1 rounded-lg border border-zinc-200 px-2.5 py-1 text-xs font-medium text-zinc-600 hover:border-violet-400 hover:text-violet-600 dark:border-zinc-700 dark:text-zinc-400 dark:hover:text-violet-400"
                                        :disabled="quizLoading"
                                        @click="loadQuizResponses"
                                    >
                                        <ClipboardList class="h-3.5 w-3.5" />
                                        {{ quizLoading ? 'Carregando...' : quizLoaded ? 'Atualizar' : 'Ver respostas' }}
                                    </button>
                                </div>

                                <div v-if="quizLoaded">
                                    <p v-if="!quizResponses.length" class="text-sm text-zinc-400 dark:text-zinc-500">
                                        Nenhum quiz respondido.
                                    </p>
                                    <div v-else class="space-y-2">
                                        <div
                                            v-for="qr in quizResponses"
                                            :key="qr.id"
                                            class="rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden"
                                        >
                                            <!-- Header do quiz -->
                                            <button
                                                type="button"
                                                class="flex w-full items-center justify-between gap-2 px-3 py-2.5 text-left hover:bg-zinc-50 dark:hover:bg-zinc-800/50"
                                                @click="expandedQuiz = expandedQuiz === qr.id ? null : qr.id"
                                            >
                                                <div class="min-w-0">
                                                    <p class="truncate text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ qr.lesson_title }}</p>
                                                    <p class="text-xs text-zinc-400">{{ qr.responded_at }}</p>
                                                </div>
                                                <ChevronUp v-if="expandedQuiz === qr.id" class="h-4 w-4 shrink-0 text-zinc-400" />
                                                <ChevronDown v-else class="h-4 w-4 shrink-0 text-zinc-400" />
                                            </button>

                                            <!-- Respostas -->
                                            <div v-if="expandedQuiz === qr.id" class="divide-y divide-zinc-100 border-t border-zinc-100 dark:divide-zinc-800 dark:border-zinc-800">
                                                <div
                                                    v-for="(resp, ri) in qr.responses"
                                                    :key="ri"
                                                    class="flex items-start gap-3 px-3 py-2.5"
                                                >
                                                    <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-violet-100 text-xs font-bold text-violet-700 dark:bg-violet-900/40 dark:text-violet-300">
                                                        {{ resp.value }}
                                                    </span>
                                                    <div class="min-w-0">
                                                        <p class="text-xs text-zinc-700 dark:text-zinc-300">{{ resp.question_text }}</p>
                                                        <p v-if="resp.comment" class="mt-0.5 text-xs italic text-zinc-400">"{{ resp.comment }}"</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-col gap-2 pt-4">
                                <Button variant="outline" class="w-full justify-start" @click="startEdit">
                                    <Pencil class="h-4 w-4" />
                                    Editar
                                </Button>
                                <Button
                                    :variant="aluno.is_blocked ? 'outline' : 'outline'"
                                    :class="aluno.is_blocked
                                        ? 'w-full justify-start text-emerald-600 border-emerald-300 hover:bg-emerald-50 dark:text-emerald-400 dark:border-emerald-800 dark:hover:bg-emerald-900/20'
                                        : 'w-full justify-start text-amber-600 border-amber-300 hover:bg-amber-50 dark:text-amber-400 dark:border-amber-800 dark:hover:bg-amber-900/20'"
                                    :disabled="blocking"
                                    @click="toggleBlock"
                                >
                                    <Loader2 v-if="blocking" class="h-4 w-4 animate-spin" />
                                    <ShieldCheck v-else-if="aluno.is_blocked" class="h-4 w-4" />
                                    <ShieldOff v-else class="h-4 w-4" />
                                    {{ aluno.is_blocked ? 'Desbloquear conta' : 'Bloquear conta' }}
                                </Button>
                                <Button
                                    variant="destructive"
                                    class="w-full justify-start"
                                    :disabled="deleting"
                                    @click="deleteAluno"
                                >
                                    <Loader2 v-if="deleting" class="h-4 w-4 animate-spin" />
                                    <Trash2 v-else class="h-4 w-4" />
                                    Excluir aluno
                                </Button>
                            </div>
                        </div>

                        <div v-else class="space-y-5">
                            <div class="space-y-2">
                                <label class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                    Nome
                                </label>
                                <input
                                    v-model="form.name"
                                    type="text"
                                    class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                                    placeholder="Nome do aluno"
                                />
                            </div>
                            <div class="space-y-2">
                                <label class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                    E-mail
                                </label>
                                <input
                                    v-model="form.email"
                                    type="email"
                                    class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                                    placeholder="email@exemplo.com"
                                />
                            </div>
                            <div class="space-y-2">
                                <label class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                    Nova senha (deixe em branco para manter)
                                </label>
                                <input
                                    v-model="form.password"
                                    type="password"
                                    class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                                    placeholder="••••••••"
                                />
                            </div>
                            <div class="space-y-2">
                                <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                    Produtos com acesso
                                </p>
                                <div class="space-y-2 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                                    <label
                                        v-for="p in produtos"
                                        :key="p.id"
                                        class="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-left hover:bg-zinc-50 dark:hover:bg-zinc-800/50"
                                    >
                                        <span class="shrink-0 w-fit">
                                            <Checkbox
                                                :model-value="form.product_ids.includes(p.id)"
                                                @update:model-value="(v) => { if (v) form.product_ids = [...form.product_ids, p.id]; else form.product_ids = form.product_ids.filter(x => x !== p.id); }"
                                            />
                                        </span>
                                        <span class="flex-1 text-left text-sm text-zinc-900 dark:text-white">{{ p.name }}</span>
                                    </label>
                                    <p v-if="!produtos.length" class="text-sm text-zinc-500">
                                        Nenhum produto disponível
                                    </p>
                                </div>
                            </div>
                            <div class="flex gap-2 pt-4">
                                <Button
                                    variant="primary"
                                    class="flex-1"
                                    :disabled="saving"
                                    @click="save"
                                >
                                    <Loader2 v-if="saving" class="h-4 w-4 animate-spin" />
                                    Salvar
                                </Button>
                                <Button variant="outline" :disabled="saving" @click="cancelEdit">
                                    Cancelar
                                </Button>
                            </div>
                        </div>
                    </div>

                    <!-- Toast -->
                    <Transition
                        enter-active-class="transition duration-200 ease-out"
                        enter-from-class="translate-y-2 opacity-0"
                        enter-to-class="translate-y-0 opacity-100"
                        leave-active-class="transition duration-150 ease-in"
                        leave-from-class="translate-y-0 opacity-100"
                        leave-to-class="translate-y-2 opacity-0"
                    >
                        <div
                            v-if="toast.message"
                            role="alert"
                            :class="[
                                'mx-5 mb-5 rounded-xl border px-4 py-3 text-sm',
                                toast.type === 'error'
                                    ? 'border-red-200 bg-red-50 text-red-800 dark:border-red-900/50 dark:bg-red-900/20 dark:text-red-200'
                                    : 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-900/20 dark:text-emerald-200',
                            ]"
                        >
                            {{ toast.message }}
                        </div>
                    </Transition>
                </div>
            </aside>
        </div>
    </Teleport>
</template>
