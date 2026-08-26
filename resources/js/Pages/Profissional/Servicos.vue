<script setup>
import { ref, reactive } from 'vue';
import LayoutProfissional from '@/Layouts/LayoutProfissional.vue';
import { Plus, Pencil, Trash2, Loader2, X, Clock, DollarSign, Briefcase } from 'lucide-vue-next';

defineOptions({ layout: LayoutProfissional });

const props = defineProps({
    services: { type: Array, default: () => [] },
});

const items   = ref([...props.services]);
const modal   = ref(false);
const delId   = ref(null);
const saving  = ref(false);
const toast   = ref(null);
const editing = ref(null);

const blank = () => ({ name: '', description: '', price: '', duration_minutes: 60, is_active: true });
const form  = reactive(blank());

function openNew() {
    editing.value = null;
    Object.assign(form, blank());
    modal.value = true;
}

function openEdit(s) {
    editing.value = s.id;
    Object.assign(form, {
        name:             s.name,
        description:      s.description ?? '',
        price:            s.price ?? '',
        duration_minutes: s.duration_minutes ?? 60,
        is_active:        s.is_active ?? true,
    });
    modal.value = true;
}

function closeModal() { modal.value = false; }

function csrf() { return document.querySelector('meta[name="csrf-token"]')?.content ?? ''; }

async function save() {
    saving.value = true;
    try {
        const url     = editing.value ? `/p/servicos/${editing.value}` : '/p/servicos';
        const payload = { name: form.name, description: form.description, price: form.price, duration_minutes: form.duration_minutes, is_active: form.is_active };
        const res     = await window.axios.post(url, payload, { headers: { 'X-CSRF-TOKEN': csrf() } });
        if (res.data.success) {
            if (editing.value) {
                const idx = items.value.findIndex(s => s.id === editing.value);
                if (idx >= 0) items.value[idx] = res.data.service;
            } else {
                items.value.unshift(res.data.service);
            }
            closeModal();
            showToast(editing.value ? 'Serviço atualizado.' : 'Serviço criado.');
        }
    } catch (e) {
        showToast(e.response?.data?.message ?? 'Erro ao salvar.', 'error');
    } finally {
        saving.value = false;
    }
}

async function destroy(id) {
    if (! confirm('Excluir este serviço?')) return;
    try {
        await window.axios.delete(`/p/servicos/${id}`, { headers: { 'X-CSRF-TOKEN': csrf() } });
        items.value = items.value.filter(s => s.id !== id);
        showToast('Serviço excluído.');
    } catch {
        showToast('Erro ao excluir.', 'error');
    }
}

function showToast(msg, type = 'success') {
    toast.value = { msg, type };
    setTimeout(() => toast.value = null, 4000);
}

function fmtPrice(v) { return v ? `R$ ${Number(v).toFixed(2).replace('.', ',')}` : '—'; }
function fmtDur(m) { return m >= 60 ? `${Math.floor(m / 60)}h${m % 60 ? ` ${m % 60}min` : ''}` : `${m}min`; }
</script>

<template>
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Serviços</h1>
                <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">Gerencie os serviços que você oferece.</p>
            </div>
            <button @click="openNew" class="flex items-center gap-2 rounded-xl bg-violet-600 hover:bg-violet-700 px-4 py-2 text-sm font-semibold text-white">
                <Plus class="h-4 w-4" /> Novo serviço
            </button>
        </div>

        <!-- Toast -->
        <div v-if="toast" class="rounded-xl px-4 py-3 text-sm font-medium"
            :class="toast.type === 'error' ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200'">
            {{ toast.msg }}
        </div>

        <!-- Lista -->
        <div v-if="items.length" class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4">
            <div v-for="s in items" :key="s.id"
                class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60 p-5 space-y-3">
                <div class="flex items-start justify-between gap-2">
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-zinc-900 dark:text-white truncate">{{ s.name }}</p>
                        <p v-if="s.description" class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5 line-clamp-2">{{ s.description }}</p>
                    </div>
                    <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold"
                        :class="s.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-zinc-100 text-zinc-500'">
                        {{ s.is_active ? 'Ativo' : 'Inativo' }}
                    </span>
                </div>
                <div class="flex gap-4 text-sm text-zinc-600 dark:text-zinc-400">
                    <span class="flex items-center gap-1"><DollarSign class="h-3.5 w-3.5" /> {{ fmtPrice(s.price) }}</span>
                    <span class="flex items-center gap-1"><Clock class="h-3.5 w-3.5" /> {{ fmtDur(s.duration_minutes) }}</span>
                </div>
                <div class="flex gap-2 pt-1">
                    <button @click="openEdit(s)" class="flex items-center gap-1 rounded-lg px-3 py-1.5 text-xs font-medium text-violet-600 hover:bg-violet-50 dark:hover:bg-violet-900/20 border border-violet-200 dark:border-violet-800">
                        <Pencil class="h-3 w-3" /> Editar
                    </button>
                    <button @click="destroy(s.id)" class="flex items-center gap-1 rounded-lg px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 border border-red-200 dark:border-red-800">
                        <Trash2 class="h-3 w-3" /> Excluir
                    </button>
                </div>
            </div>
        </div>
        <div v-else class="flex flex-col items-center gap-3 py-16 text-center">
            <Briefcase class="h-12 w-12 text-zinc-200 dark:text-zinc-700" />
            <p class="text-zinc-500 dark:text-zinc-400">Nenhum serviço cadastrado.</p>
            <button @click="openNew" class="text-sm text-violet-600 hover:underline dark:text-violet-400">Adicionar primeiro serviço</button>
        </div>
    </div>

    <!-- Modal -->
    <Teleport to="body">
        <div v-if="modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40">
            <div class="w-full max-w-md rounded-2xl bg-white dark:bg-zinc-900 shadow-2xl">
                <div class="flex items-center justify-between px-5 py-4 border-b border-zinc-100 dark:border-zinc-800">
                    <h2 class="font-semibold text-zinc-900 dark:text-white">{{ editing ? 'Editar serviço' : 'Novo serviço' }}</h2>
                    <button @click="closeModal" class="rounded-lg p-1 text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800"><X class="h-5 w-5" /></button>
                </div>
                <div class="p-5 space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-zinc-500 mb-1">Nome *</label>
                        <input v-model="form.name" type="text" class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm focus:border-violet-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-zinc-500 mb-1">Descrição</label>
                        <textarea v-model="form.description" rows="3" class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm focus:border-violet-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white resize-none" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-zinc-500 mb-1">Preço (R$)</label>
                            <input v-model="form.price" type="number" min="0" step="0.01" class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm focus:border-violet-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-zinc-500 mb-1">Duração (min)</label>
                            <input v-model="form.duration_minutes" type="number" min="15" step="15" class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm focus:border-violet-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                        </div>
                    </div>
                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input v-model="form.is_active" type="checkbox" class="rounded border-zinc-300" />
                        <span class="text-sm text-zinc-700 dark:text-zinc-300">Ativo (visível para clientes)</span>
                    </label>
                </div>
                <div class="flex justify-end gap-3 px-5 py-4 border-t border-zinc-100 dark:border-zinc-800">
                    <button @click="closeModal" class="rounded-xl px-4 py-2 text-sm font-medium text-zinc-600 hover:bg-zinc-100 dark:hover:bg-zinc-800 dark:text-zinc-300">Cancelar</button>
                    <button @click="save" :disabled="saving || !form.name"
                        class="flex items-center gap-2 rounded-xl bg-violet-600 hover:bg-violet-700 disabled:opacity-60 px-5 py-2 text-sm font-semibold text-white">
                        <Loader2 v-if="saving" class="h-4 w-4 animate-spin" />
                        {{ saving ? 'Salvando…' : 'Salvar' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
