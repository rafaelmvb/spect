<script setup>
import { ref } from 'vue';
import LayoutInfoprodutor from '@/Layouts/LayoutInfoprodutor.vue';
import Button from '@/components/ui/Button.vue';
import { ImagePlay, Plus, Trash2, Pencil, X, Loader2, Eye, EyeOff, GripVertical, ExternalLink } from 'lucide-vue-next';

defineOptions({ layout: LayoutInfoprodutor });

const props = defineProps({
    banners: { type: Array, default: () => [] },
});

const localBanners = ref([...props.banners]);
const toast = ref(null);
const modal = ref(false);
const editing = ref(null);  // banner sendo editado
const saving = ref(false);
const imagePreview = ref('');
const imageInputRef = ref(null);

const emptyForm = () => ({
    title: '',
    subtitle: '',
    link: '',
    button_text: '',
    target: 'member_area',
    is_active: true,
    image: null,
});
const form = ref(emptyForm());

function csrfToken() { return document.querySelector('meta[name="csrf-token"]')?.content ?? ''; }

function showToast(msg, type = 'success') {
    toast.value = { msg, type };
    setTimeout(() => toast.value = null, 4000);
}

function openCreate() {
    editing.value = null;
    form.value = emptyForm();
    imagePreview.value = '';
    modal.value = true;
}

function openEdit(banner) {
    editing.value = banner;
    form.value = {
        title: banner.title ?? '',
        subtitle: banner.subtitle ?? '',
        link: banner.link ?? '',
        button_text: banner.button_text ?? '',
        target: banner.target ?? 'member_area',
        is_active: banner.is_active,
        image: null,
    };
    imagePreview.value = banner.image_url ?? '';
    modal.value = true;
}

function onImage(e) {
    const f = e.target?.files?.[0];
    if (!f) return;
    form.value.image = f;
    imagePreview.value = URL.createObjectURL(f);
}

function clearImage() {
    form.value.image = null;
    imagePreview.value = '';
    if (imageInputRef.value) imageInputRef.value.value = '';
}

async function save() {
    saving.value = true;
    try {
        const fd = new FormData();
        if (form.value.title)       fd.append('title', form.value.title);
        if (form.value.subtitle)    fd.append('subtitle', form.value.subtitle);
        if (form.value.link)        fd.append('link', form.value.link);
        if (form.value.button_text) fd.append('button_text', form.value.button_text);
        fd.append('target', form.value.target);
        fd.append('is_active', form.value.is_active ? '1' : '0');
        if (form.value.image)       fd.append('image', form.value.image);

        const headers = { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' };

        if (editing.value) {
            fd.append('_method', 'PUT');
            const { data } = await window.axios.post(`/banners/${editing.value.id}`, fd, { headers });
            const idx = localBanners.value.findIndex(b => b.id === editing.value.id);
            if (idx !== -1) localBanners.value[idx] = data.banner;
            showToast('Banner atualizado.');
        } else {
            const { data } = await window.axios.post('/banners', fd, { headers });
            localBanners.value.push(data.banner);
            showToast('Banner criado.');
        }
        modal.value = false;
    } catch (e) {
        showToast(e.response?.data?.message ?? 'Erro ao salvar.', 'error');
    } finally {
        saving.value = false;
    }
}

async function toggleActive(banner) {
    try {
        const { data } = await window.axios.put(`/banners/${banner.id}/toggle-active`, {}, {
            headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
        });
        const idx = localBanners.value.findIndex(b => b.id === banner.id);
        if (idx !== -1) localBanners.value[idx] = { ...localBanners.value[idx], is_active: data.is_active };
        showToast(data.is_active ? 'Banner ativado.' : 'Banner desativado.');
    } catch {
        showToast('Erro ao alterar status.', 'error');
    }
}

async function destroy(banner) {
    if (!confirm('Excluir este banner?')) return;
    try {
        await window.axios.delete(`/banners/${banner.id}`, {
            headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
        });
        localBanners.value = localBanners.value.filter(b => b.id !== banner.id);
        showToast('Banner excluído.');
    } catch {
        showToast('Erro ao excluir.', 'error');
    }
}

const targetLabels = {
    member_area: 'Área de Membros',
    dashboard:   'Painel Admin',
    both:        'Ambos',
};
</script>

<template>
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-violet-100 dark:bg-violet-900/30">
                    <ImagePlay class="h-5 w-5 text-violet-600 dark:text-violet-400" />
                </div>
                <div>
                    <h1 class="text-xl font-bold text-zinc-900 dark:text-white">Banners</h1>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">Imagens de destaque exibidas na plataforma.</p>
                </div>
            </div>
            <Button @click="openCreate">
                <Plus class="mr-2 h-4 w-4" /> Novo banner
            </Button>
        </div>

        <!-- Lista vazia -->
        <div v-if="!localBanners.length"
            class="flex flex-col items-center justify-center gap-3 rounded-2xl border-2 border-dashed border-zinc-200 py-20 dark:border-zinc-700">
            <ImagePlay class="h-12 w-12 text-zinc-300 dark:text-zinc-600" />
            <p class="text-zinc-400 dark:text-zinc-500">Nenhum banner criado ainda.</p>
            <Button variant="outline" @click="openCreate"><Plus class="mr-2 h-4 w-4" /> Criar primeiro banner</Button>
        </div>

        <!-- Grid de banners -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div v-for="banner in localBanners" :key="banner.id"
                class="group overflow-hidden rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/50">

                <!-- Preview imagem -->
                <div class="relative h-36 bg-gradient-to-br from-zinc-100 to-zinc-200 dark:from-zinc-800 dark:to-zinc-700">
                    <img v-if="banner.image_url" :src="banner.image_url" class="h-full w-full object-cover" />
                    <div v-else class="flex h-full items-center justify-center">
                        <ImagePlay class="h-10 w-10 text-zinc-300 dark:text-zinc-600" />
                    </div>
                    <!-- Badge status -->
                    <div class="absolute left-2 top-2">
                        <span class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide"
                            :class="banner.is_active
                                ? 'bg-emerald-500 text-white'
                                : 'bg-zinc-400 text-white'">
                            {{ banner.is_active ? 'Ativo' : 'Inativo' }}
                        </span>
                    </div>
                    <!-- Badge target -->
                    <div class="absolute right-2 top-2">
                        <span class="rounded-full bg-zinc-900/70 px-2 py-0.5 text-[10px] text-white">
                            {{ targetLabels[banner.target] ?? banner.target }}
                        </span>
                    </div>
                </div>

                <!-- Info -->
                <div class="p-4">
                    <p class="font-semibold text-zinc-900 dark:text-white truncate">
                        {{ banner.title || '(sem título)' }}
                    </p>
                    <p v-if="banner.subtitle" class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400 line-clamp-2">
                        {{ banner.subtitle }}
                    </p>
                    <a v-if="banner.link" :href="banner.link" target="_blank"
                        class="mt-1 flex items-center gap-1 text-xs text-violet-600 hover:underline dark:text-violet-400">
                        <ExternalLink class="h-3 w-3" /> {{ banner.link }}
                    </a>

                    <!-- Ações -->
                    <div class="mt-3 flex items-center gap-2">
                        <button type="button"
                            class="flex flex-1 items-center justify-center gap-1.5 rounded-lg border border-zinc-200 py-1.5 text-xs font-medium text-zinc-600 hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-400 dark:hover:bg-zinc-800"
                            @click="openEdit(banner)">
                            <Pencil class="h-3.5 w-3.5" /> Editar
                        </button>
                        <button type="button"
                            class="flex items-center justify-center rounded-lg border border-zinc-200 p-1.5 text-zinc-500 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
                            :title="banner.is_active ? 'Desativar' : 'Ativar'"
                            @click="toggleActive(banner)">
                            <EyeOff v-if="banner.is_active" class="h-4 w-4" />
                            <Eye v-else class="h-4 w-4 text-emerald-500" />
                        </button>
                        <button type="button"
                            class="flex items-center justify-center rounded-lg border border-red-200 p-1.5 text-red-500 hover:bg-red-50 dark:border-red-900/50 dark:hover:bg-red-900/20"
                            @click="destroy(banner)">
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast -->
    <Teleport to="body">
        <Transition enter-active-class="transition duration-200" enter-from-class="translate-y-2 opacity-0" leave-active-class="transition duration-150" leave-to-class="translate-y-2 opacity-0">
            <div v-if="toast"
                class="fixed bottom-6 right-6 z-[99999] rounded-xl border px-4 py-3 text-sm shadow-lg"
                :class="toast.type === 'error'
                    ? 'border-red-200 bg-red-50 text-red-800 dark:border-red-900/50 dark:bg-red-900/20 dark:text-red-200'
                    : 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-900/20 dark:text-emerald-200'">
                {{ toast.msg }}
            </div>
        </Transition>
    </Teleport>

    <!-- Modal criar/editar -->
    <Teleport to="body">
        <div v-if="modal" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/50 p-4" @click.self="modal = false">
            <div class="w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-zinc-900">
                <!-- Header modal -->
                <div class="flex items-center justify-between border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                    <h3 class="font-semibold text-zinc-900 dark:text-white">
                        {{ editing ? 'Editar banner' : 'Novo banner' }}
                    </h3>
                    <button type="button" class="rounded-lg p-1 text-zinc-400 hover:text-zinc-600" @click="modal = false">
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <!-- Corpo -->
                <div class="max-h-[70vh] space-y-4 overflow-y-auto p-5">
                    <!-- Imagem -->
                    <div>
                        <label class="mb-1 block text-xs font-medium text-zinc-500">Imagem do banner</label>
                        <div v-if="imagePreview" class="relative mb-2 overflow-hidden rounded-xl">
                            <img :src="imagePreview" class="h-40 w-full object-cover" />
                            <button type="button"
                                class="absolute right-2 top-2 rounded-full bg-black/60 p-1 text-white hover:bg-black/80"
                                @click="clearImage">
                                <X class="h-3.5 w-3.5" />
                            </button>
                        </div>
                        <label class="flex cursor-pointer items-center justify-center gap-2 rounded-xl border-2 border-dashed border-zinc-200 py-4 text-sm text-zinc-500 hover:border-violet-400 hover:text-violet-600 dark:border-zinc-700">
                            <ImagePlay class="h-4 w-4" />
                            {{ imagePreview ? 'Trocar imagem' : 'Selecionar imagem' }}
                            <input ref="imageInputRef" type="file" accept="image/*" class="hidden" @change="onImage" />
                        </label>
                    </div>

                    <!-- Título -->
                    <div>
                        <label class="mb-1 block text-xs font-medium text-zinc-500">Título</label>
                        <input v-model="form.title" type="text" placeholder="Ex: Novidade da semana"
                            class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm text-zinc-900 focus:border-violet-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                    </div>

                    <!-- Subtítulo -->
                    <div>
                        <label class="mb-1 block text-xs font-medium text-zinc-500">Subtítulo / Descrição</label>
                        <textarea v-model="form.subtitle" rows="2" placeholder="Texto de apoio exibido sob o título"
                            class="w-full resize-none rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm text-zinc-900 focus:border-violet-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                    </div>

                    <!-- Link + botão -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-zinc-500">Link (URL)</label>
                            <input v-model="form.link" type="url" placeholder="https://..."
                                class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm text-zinc-900 focus:border-violet-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-zinc-500">Texto do botão</label>
                            <input v-model="form.button_text" type="text" placeholder="Ex: Saiba mais"
                                class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm text-zinc-900 focus:border-violet-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                        </div>
                    </div>

                    <!-- Target + is_active -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-zinc-500">Exibir em</label>
                            <select v-model="form.target"
                                class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm text-zinc-900 focus:border-violet-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white">
                                <option value="member_area">Área de Membros</option>
                                <option value="dashboard">Painel Admin</option>
                                <option value="both">Ambos</option>
                            </select>
                        </div>
                        <div class="flex flex-col justify-center">
                            <label class="mb-1 block text-xs font-medium text-zinc-500">Status</label>
                            <label class="flex cursor-pointer items-center gap-2">
                                <button type="button"
                                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                                    :class="form.is_active ? 'bg-violet-600' : 'bg-zinc-300 dark:bg-zinc-600'"
                                    @click="form.is_active = !form.is_active">
                                    <span class="inline-block h-4 w-4 translate-x-1 transform rounded-full bg-white shadow transition-transform"
                                        :class="form.is_active ? 'translate-x-6' : 'translate-x-1'" />
                                </button>
                                <span class="text-sm text-zinc-700 dark:text-zinc-300">{{ form.is_active ? 'Ativo' : 'Inativo' }}</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="flex justify-end gap-2 border-t border-zinc-200 px-5 py-3 dark:border-zinc-700">
                    <Button variant="outline" @click="modal = false">Cancelar</Button>
                    <Button :disabled="saving" @click="save">
                        <Loader2 v-if="saving" class="mr-2 h-3.5 w-3.5 animate-spin" />
                        {{ editing ? 'Salvar' : 'Criar banner' }}
                    </Button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
