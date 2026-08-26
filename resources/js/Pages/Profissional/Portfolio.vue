<script setup>
import { ref } from 'vue';
import LayoutProfissional from '@/Layouts/LayoutProfissional.vue';
import { Plus, Trash2, Image as ImageIcon, Video, X, Loader2, Upload } from 'lucide-vue-next';

defineOptions({ layout: LayoutProfissional });

const props = defineProps({
    portfolio: { type: Array, default: () => [] },
});

const items  = ref([...props.portfolio]);
const modal  = ref(false);
const saving = ref(false);
const toast  = ref(null);

const form = ref({ type: 'image', title: '', description: '', video_url: '', file: null });
const preview = ref(null);
const fileRef = ref(null);

function openModal() {
    form.value = { type: 'image', title: '', description: '', video_url: '', file: null };
    preview.value = null;
    modal.value = true;
}

function closeModal() { modal.value = false; }

function onFile(e) {
    const f = e.target.files[0];
    if (! f) return;
    form.value.file = f;
    preview.value = URL.createObjectURL(f);
}

function csrf() { return document.querySelector('meta[name="csrf-token"]')?.content ?? ''; }

async function save() {
    saving.value = true;
    try {
        const fd = new FormData();
        fd.append('type', form.value.type);
        fd.append('title', form.value.title ?? '');
        fd.append('description', form.value.description ?? '');
        if (form.value.type === 'image' && form.value.file) {
            fd.append('file', form.value.file);
        } else {
            fd.append('video_url', form.value.video_url ?? '');
        }
        const res = await window.axios.post('/p/portfolio', fd, {
            headers: { 'X-CSRF-TOKEN': csrf(), 'Content-Type': 'multipart/form-data' },
        });
        if (res.data.success) {
            items.value.unshift(res.data.item);
            closeModal();
            showToast('Item adicionado ao portfólio.');
        }
    } catch (e) {
        showToast(e.response?.data?.message ?? 'Erro ao salvar.', 'error');
    } finally {
        saving.value = false;
    }
}

async function destroy(id) {
    if (! confirm('Remover este item do portfólio?')) return;
    try {
        await window.axios.delete(`/p/portfolio/${id}`, { headers: { 'X-CSRF-TOKEN': csrf() } });
        items.value = items.value.filter(i => i.id !== id);
        showToast('Item removido.');
    } catch {
        showToast('Erro ao remover.', 'error');
    }
}

function showToast(msg, type = 'success') {
    toast.value = { msg, type };
    setTimeout(() => toast.value = null, 4000);
}

function canSave() {
    if (! form.value) return false;
    if (form.value.type === 'image') return !! form.value.file;
    return !! form.value.video_url;
}

function embedUrl(url) {
    if (! url) return null;
    const yt = url.match(/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/))([^&?/]+)/);
    if (yt) return `https://www.youtube.com/embed/${yt[1]}`;
    return url;
}
</script>

<template>
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Portfólio</h1>
                <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">Mostre seu trabalho com imagens e vídeos.</p>
            </div>
            <button @click="openModal" class="flex items-center gap-2 rounded-xl bg-violet-600 hover:bg-violet-700 px-4 py-2 text-sm font-semibold text-white">
                <Plus class="h-4 w-4" /> Adicionar
            </button>
        </div>

        <!-- Toast -->
        <div v-if="toast" class="rounded-xl px-4 py-3 text-sm font-medium"
            :class="toast.type === 'error' ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200'">
            {{ toast.msg }}
        </div>

        <!-- Grid -->
        <div v-if="items.length" class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4">
            <div v-for="item in items" :key="item.id" class="group relative rounded-2xl overflow-hidden bg-zinc-100 dark:bg-zinc-800 aspect-square">
                <img v-if="item.type === 'image'" :src="item.url" :alt="item.title ?? ''" class="h-full w-full object-cover" />
                <iframe v-else-if="item.type === 'video'" :src="embedUrl(item.video_url)" class="h-full w-full" frameborder="0" allowfullscreen />
                <div class="absolute inset-0 bg-black/0 group-hover:bg-black/50 transition-colors flex flex-col justify-end p-3 opacity-0 group-hover:opacity-100">
                    <p v-if="item.title" class="text-xs font-semibold text-white line-clamp-1">{{ item.title }}</p>
                    <button @click="destroy(item.id)" class="mt-1 flex items-center gap-1 self-start rounded-lg bg-red-600 hover:bg-red-700 px-2 py-1 text-[10px] font-semibold text-white">
                        <Trash2 class="h-3 w-3" /> Remover
                    </button>
                </div>
                <span class="absolute top-2 left-2 rounded-full p-1.5 bg-black/40">
                    <ImageIcon v-if="item.type === 'image'" class="h-3 w-3 text-white" />
                    <Video v-else class="h-3 w-3 text-white" />
                </span>
            </div>
        </div>
        <div v-else class="flex flex-col items-center gap-3 py-16 text-center">
            <ImageIcon class="h-12 w-12 text-zinc-200 dark:text-zinc-700" />
            <p class="text-zinc-500 dark:text-zinc-400">Nenhum item no portfólio ainda.</p>
            <button @click="openModal" class="text-sm text-violet-600 hover:underline dark:text-violet-400">Adicionar primeiro item</button>
        </div>
    </div>

    <!-- Modal -->
    <Teleport to="body">
        <div v-if="modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40">
            <div class="w-full max-w-md rounded-2xl bg-white dark:bg-zinc-900 shadow-2xl">
                <div class="flex items-center justify-between px-5 py-4 border-b border-zinc-100 dark:border-zinc-800">
                    <h2 class="font-semibold text-zinc-900 dark:text-white">Adicionar ao portfólio</h2>
                    <button @click="closeModal" class="rounded-lg p-1 text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800"><X class="h-5 w-5" /></button>
                </div>
                <div class="p-5 space-y-4">
                    <!-- Tipo -->
                    <div class="flex gap-2">
                        <button @click="form.type = 'image'" :class="form.type === 'image' ? 'bg-violet-600 text-white' : 'border border-zinc-200 text-zinc-600 hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-300'"
                            class="flex-1 flex items-center justify-center gap-2 rounded-xl py-2 text-sm font-medium transition">
                            <ImageIcon class="h-4 w-4" /> Imagem
                        </button>
                        <button @click="form.type = 'video'" :class="form.type === 'video' ? 'bg-violet-600 text-white' : 'border border-zinc-200 text-zinc-600 hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-300'"
                            class="flex-1 flex items-center justify-center gap-2 rounded-xl py-2 text-sm font-medium transition">
                            <Video class="h-4 w-4" /> Vídeo
                        </button>
                    </div>

                    <!-- Upload imagem -->
                    <div v-if="form.type === 'image'">
                        <label class="block text-xs font-medium text-zinc-500 mb-1">Imagem *</label>
                        <div @click="fileRef?.click()" class="cursor-pointer rounded-xl border-2 border-dashed border-zinc-200 dark:border-zinc-700 hover:border-violet-400 transition aspect-video flex flex-col items-center justify-center gap-2 overflow-hidden">
                            <img v-if="preview" :src="preview" class="h-full w-full object-cover" />
                            <template v-else>
                                <Upload class="h-8 w-8 text-zinc-300" />
                                <p class="text-xs text-zinc-400">Clique para selecionar</p>
                            </template>
                        </div>
                        <input ref="fileRef" type="file" accept="image/*" class="hidden" @change="onFile" />
                    </div>

                    <!-- URL vídeo -->
                    <div v-else>
                        <label class="block text-xs font-medium text-zinc-500 mb-1">URL do vídeo (YouTube, Vimeo…) *</label>
                        <input v-model="form.video_url" type="url" placeholder="https://youtu.be/..." class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm focus:border-violet-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-zinc-500 mb-1">Título</label>
                        <input v-model="form.title" type="text" class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm focus:border-violet-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-zinc-500 mb-1">Descrição</label>
                        <textarea v-model="form.description" rows="2" class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm focus:border-violet-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white resize-none" />
                    </div>
                </div>
                <div class="flex justify-end gap-3 px-5 py-4 border-t border-zinc-100 dark:border-zinc-800">
                    <button @click="closeModal" class="rounded-xl px-4 py-2 text-sm font-medium text-zinc-600 hover:bg-zinc-100 dark:hover:bg-zinc-800 dark:text-zinc-300">Cancelar</button>
                    <button @click="save" :disabled="saving || !canSave()"
                        class="flex items-center gap-2 rounded-xl bg-violet-600 hover:bg-violet-700 disabled:opacity-60 px-5 py-2 text-sm font-semibold text-white">
                        <Loader2 v-if="saving" class="h-4 w-4 animate-spin" />
                        {{ saving ? 'Enviando…' : 'Adicionar' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
