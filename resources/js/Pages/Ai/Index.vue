<script setup>
import { ref, computed } from 'vue';
import LayoutInfoprodutor from '@/Layouts/LayoutInfoprodutor.vue';
import Button from '@/components/ui/Button.vue';
import { Sparkles, Plus, Pencil, Trash2, X, Loader2, Save, MessageSquare, FileText } from 'lucide-vue-next';

defineOptions({ layout: LayoutInfoprodutor });

const props = defineProps({
    prompts: { type: Array, default: () => [] },
});

const localPrompts = ref([...props.prompts]);

// ─── Toast ────────────────────────────────────────────────────────────────────
const toast = ref(null);
function showToast(msg, type = 'success') {
    toast.value = { msg, type };
    setTimeout(() => toast.value = null, 3500);
}

function csrfToken() { return document.querySelector('meta[name="csrf-token"]')?.content ?? ''; }
const h = () => ({ 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' });

// ─── Modal ────────────────────────────────────────────────────────────────────
const modalOpen    = ref(false);
const editingId    = ref(null);
const saving       = ref(false);
const form         = ref({ type: 'chat', content: '' });

function openNew() {
    editingId.value = null;
    form.value = { type: 'chat', content: '' };
    modalOpen.value = true;
}

function openEdit(p) {
    editingId.value = p.id;
    form.value = { type: p.type, content: p.content };
    modalOpen.value = true;
}

function closeModal() {
    modalOpen.value = false;
}

async function savePrompt() {
    if (!form.value.content.trim()) {
        showToast('O conteúdo do prompt é obrigatório.', 'error');
        return;
    }
    saving.value = true;
    try {
        if (editingId.value) {
            const { data } = await window.axios.put(`/ia/prompts/${editingId.value}`, { content: form.value.content }, { headers: h() });
            const idx = localPrompts.value.findIndex(p => p.id === editingId.value);
            if (idx >= 0) localPrompts.value[idx] = data.prompt;
            showToast('Prompt atualizado.');
        } else {
            const { data } = await window.axios.post('/ia/prompts', form.value, { headers: h() });
            localPrompts.value.push(data.prompt);
            showToast('Prompt salvo.');
        }
        modalOpen.value = false;
    } catch (e) {
        showToast(e.response?.data?.message ?? 'Erro ao salvar.', 'error');
    } finally { saving.value = false; }
}

const deletingId = ref(null);
async function deletePrompt(p) {
    if (!confirm('Excluir este prompt?')) return;
    deletingId.value = p.id;
    try {
        await window.axios.delete(`/ia/prompts/${p.id}`, { headers: h() });
        localPrompts.value = localPrompts.value.filter(x => x.id !== p.id);
        showToast('Prompt excluído.');
    } catch { showToast('Erro ao excluir.', 'error'); } finally { deletingId.value = null; }
}

const chatPrompts   = computed(() => localPrompts.value.filter(p => p.type === 'chat'));
const reportPrompts = computed(() => localPrompts.value.filter(p => p.type === 'report'));
</script>

<template>
    <div class="space-y-6">
        <!-- Toast -->
        <Transition enter-active-class="transition duration-200" enter-from-class="opacity-0 -translate-y-2" enter-to-class="opacity-100 translate-y-0">
            <div v-if="toast" class="fixed right-6 top-20 z-[300000] flex items-center gap-2 rounded-xl px-4 py-3 text-sm font-medium shadow-lg"
                :class="toast.type === 'error' ? 'bg-red-600 text-white' : 'bg-emerald-600 text-white'">
                {{ toast.msg }}
            </div>
        </Transition>

        <!-- Header -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-purple-100 dark:bg-purple-900/30">
                    <Sparkles class="h-5 w-5 text-purple-600 dark:text-purple-400" />
                </div>
                <div>
                    <h1 class="text-xl font-bold text-zinc-900 dark:text-white">IA Contextual</h1>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">Prompts personalizados adicionados ao comportamento base da IA.</p>
                </div>
            </div>
            <Button @click="openNew">
                <Plus class="mr-2 h-4 w-4" /> Novo prompt
            </Button>
        </div>

        <!-- Estado vazio -->
        <div v-if="!localPrompts.length"
            class="flex flex-col items-center gap-4 rounded-2xl border-2 border-dashed border-zinc-200 py-20 text-center dark:border-zinc-700">
            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-purple-100 dark:bg-purple-900/20">
                <Sparkles class="h-7 w-7 text-purple-400" />
            </div>
            <div>
                <p class="font-semibold text-zinc-700 dark:text-zinc-300">Nenhum prompt cadastrado</p>
                <p class="mt-1 text-sm text-zinc-400">Adicione contexto personalizado para o chat ou os relatórios avançados.</p>
            </div>
            <Button variant="outline" @click="openNew"><Plus class="mr-2 h-4 w-4" /> Novo prompt</Button>
        </div>

        <!-- Seção Chat -->
        <div v-if="chatPrompts.length" class="space-y-3">
            <div class="flex items-center gap-2">
                <MessageSquare class="h-4 w-4 text-blue-500" />
                <h2 class="font-semibold text-sm text-zinc-700 dark:text-zinc-300">Chat / Assistente virtual</h2>
                <span class="rounded-full bg-blue-100 px-2 py-0.5 text-[10px] font-bold text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">
                    {{ chatPrompts.length }}
                </span>
            </div>
            <div v-for="p in chatPrompts" :key="p.id"
                class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
                <div class="flex items-start gap-3">
                    <div class="flex-1 min-w-0">
                        <p class="whitespace-pre-wrap text-sm text-zinc-700 dark:text-zinc-300 leading-relaxed">{{ p.content }}</p>
                    </div>
                    <div class="flex shrink-0 gap-1">
                        <button type="button" title="Editar"
                            class="rounded-lg p-1.5 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-700"
                            @click="openEdit(p)">
                            <Pencil class="h-4 w-4" />
                        </button>
                        <button type="button" title="Excluir"
                            class="rounded-lg p-1.5 text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20"
                            :disabled="deletingId === p.id"
                            @click="deletePrompt(p)">
                            <Loader2 v-if="deletingId === p.id" class="h-4 w-4 animate-spin" />
                            <Trash2 v-else class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Seção Relatórios -->
        <div v-if="reportPrompts.length" class="space-y-3">
            <div class="flex items-center gap-2">
                <FileText class="h-4 w-4 text-purple-500" />
                <h2 class="font-semibold text-sm text-zinc-700 dark:text-zinc-300">Relatórios avançados de alunos</h2>
                <span class="rounded-full bg-purple-100 px-2 py-0.5 text-[10px] font-bold text-purple-700 dark:bg-purple-900/30 dark:text-purple-300">
                    {{ reportPrompts.length }}
                </span>
            </div>
            <div v-for="p in reportPrompts" :key="p.id"
                class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
                <div class="flex items-start gap-3">
                    <div class="flex-1 min-w-0">
                        <p class="whitespace-pre-wrap text-sm text-zinc-700 dark:text-zinc-300 leading-relaxed">{{ p.content }}</p>
                    </div>
                    <div class="flex shrink-0 gap-1">
                        <button type="button" title="Editar"
                            class="rounded-lg p-1.5 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-700"
                            @click="openEdit(p)">
                            <Pencil class="h-4 w-4" />
                        </button>
                        <button type="button" title="Excluir"
                            class="rounded-lg p-1.5 text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20"
                            :disabled="deletingId === p.id"
                            @click="deletePrompt(p)">
                            <Loader2 v-if="deletingId === p.id" class="h-4 w-4 animate-spin" />
                            <Trash2 v-else class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ─── Modal ──────────────────────────────────────────────────────────────── -->
    <Teleport to="body">
        <div v-if="modalOpen"
            class="fixed inset-0 z-[200000] flex items-center justify-center bg-black/50 p-4"
            @click.self="closeModal">
            <div class="w-full max-w-xl overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-zinc-900">
                <!-- Header modal -->
                <div class="flex items-center justify-between border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                    <h3 class="font-semibold text-zinc-900 dark:text-white">
                        {{ editingId ? 'Editar prompt' : 'Novo prompt' }}
                    </h3>
                    <button type="button" class="rounded-lg p-1 text-zinc-400 hover:text-zinc-600" @click="closeModal">
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <!-- Body modal -->
                <div class="space-y-4 p-5">
                    <!-- Tipo (só na criação) -->
                    <div v-if="!editingId">
                        <label class="mb-2 block text-xs font-medium text-zinc-500">Este prompt é para:</label>
                        <div class="grid grid-cols-2 gap-2">
                            <button type="button"
                                class="flex items-center gap-2 rounded-xl border-2 px-4 py-3 text-sm font-medium transition-all"
                                :class="form.type === 'chat'
                                    ? 'border-blue-500 bg-blue-50 text-blue-700 dark:bg-blue-900/20 dark:text-blue-300'
                                    : 'border-zinc-200 text-zinc-600 hover:border-zinc-300 dark:border-zinc-700 dark:text-zinc-400'"
                                @click="form.type = 'chat'">
                                <MessageSquare class="h-4 w-4" />
                                Chat / Assistente
                            </button>
                            <button type="button"
                                class="flex items-center gap-2 rounded-xl border-2 px-4 py-3 text-sm font-medium transition-all"
                                :class="form.type === 'report'
                                    ? 'border-purple-500 bg-purple-50 text-purple-700 dark:bg-purple-900/20 dark:text-purple-300'
                                    : 'border-zinc-200 text-zinc-600 hover:border-zinc-300 dark:border-zinc-700 dark:text-zinc-400'"
                                @click="form.type = 'report'">
                                <FileText class="h-4 w-4" />
                                Relatórios avançados
                            </button>
                        </div>
                    </div>

                    <!-- Textarea -->
                    <div>
                        <label class="mb-1 block text-xs font-medium text-zinc-500">Conteúdo do prompt</label>
                        <textarea v-model="form.content" rows="8" autofocus
                            placeholder="Ex: Nossa clínica atende adolescentes entre 14 e 22 anos. As recomendações devem sempre considerar o contexto acadêmico..."
                            class="w-full resize-none rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm leading-relaxed text-zinc-800 placeholder-zinc-400 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200" />
                        <p class="mt-1 text-[10px] text-zinc-400">{{ form.content.length }}/5000 caracteres</p>
                    </div>
                </div>

                <!-- Footer modal -->
                <div class="flex justify-end gap-2 border-t border-zinc-200 px-5 py-3 dark:border-zinc-700">
                    <Button variant="outline" @click="closeModal">Cancelar</Button>
                    <Button :disabled="saving || !form.content.trim()" @click="savePrompt">
                        <Loader2 v-if="saving" class="mr-2 h-3.5 w-3.5 animate-spin" />
                        <Save v-else class="mr-2 h-3.5 w-3.5" />
                        {{ editingId ? 'Salvar alterações' : 'Salvar prompt' }}
                    </Button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
