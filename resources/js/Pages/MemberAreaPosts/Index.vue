<script setup>
import { ref, reactive } from 'vue';
import axios from 'axios';
import LayoutInfoprodutor from '@/Layouts/LayoutInfoprodutor.vue';
import { Plus, Pencil, Trash2, Eye, EyeOff, ImageIcon, X } from 'lucide-vue-next';
import RichTextEditor from '@/components/RichTextEditor.vue';

defineOptions({ layout: LayoutInfoprodutor });

const props = defineProps({
    posts: { type: Array, default: () => [] },
});

const posts = ref([...props.posts]);

// Modal
const showModal = ref(false);
const editing = ref(null);
const saving = ref(false);
const imagePreview = ref(null);
const imageFile = ref(null);
const fileInput = ref(null);

const form = reactive({
    title: '',
    category: '',
    excerpt: '',
    content: '',
    is_published: false,
});

function openCreate() {
    editing.value = null;
    Object.assign(form, { title: '', category: '', excerpt: '', content: '', is_published: false });
    imagePreview.value = null;
    imageFile.value = null;
    showModal.value = true;
}

function openEdit(post) {
    editing.value = post;
    Object.assign(form, {
        title: post.title ?? '',
        category: post.category ?? '',
        excerpt: post.excerpt ?? '',
        content: post.content ?? '',
        is_published: post.is_published ?? false,
    });
    imagePreview.value = post.image_url ?? null;
    imageFile.value = null;
    showModal.value = true;
}

function onImageChange(e) {
    const file = e.target.files[0];
    if (!file) return;
    imageFile.value = file;
    imagePreview.value = URL.createObjectURL(file);
}

async function save() {
    if (saving.value || !form.title.trim()) return;
    saving.value = true;
    try {
        const fd = new FormData();
        fd.append('title', form.title);
        fd.append('category', form.category ?? '');
        fd.append('excerpt', form.excerpt ?? '');
        fd.append('content', form.content ?? '');
        fd.append('is_published', form.is_published ? '1' : '0');
        if (imageFile.value) fd.append('image', imageFile.value);

        if (editing.value) {
            fd.append('_method', 'PUT');
            const { data } = await axios.post(`/member-posts/${editing.value.id}`, fd);
            const idx = posts.value.findIndex(p => p.id === editing.value.id);
            if (idx !== -1) posts.value[idx] = data.post;
        } else {
            const { data } = await axios.post('/member-posts', fd);
            posts.value.unshift(data.post);
        }
        showModal.value = false;
    } catch (e) {
        alert(e?.response?.data?.message || 'Erro ao salvar.');
    } finally {
        saving.value = false;
    }
}

async function destroy(post) {
    if (!confirm(`Excluir "${post.title}"?`)) return;
    try {
        await axios.delete(`/member-posts/${post.id}`);
        posts.value = posts.value.filter(p => p.id !== post.id);
    } catch {
        alert('Erro ao excluir.');
    }
}

async function togglePublish(post) {
    try {
        const { data } = await axios.put(`/member-posts/${post.id}`, {
            is_published: !post.is_published,
        });
        const idx = posts.value.findIndex(p => p.id === post.id);
        if (idx !== -1) posts.value[idx] = data.post;
    } catch {
        alert('Erro.');
    }
}
</script>

<template>
    <div class="p-6">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Posts do Blog</h1>
                <p class="mt-1 text-sm text-zinc-500">Conteúdo exibido na página inicial da área de membros.</p>
            </div>
            <button
                type="button"
                class="flex items-center gap-2 rounded-xl bg-[var(--color-primary)] px-4 py-2.5 text-sm font-medium text-white shadow hover:opacity-90"
                @click="openCreate"
            >
                <Plus class="h-4 w-4" /> Novo post
            </button>
        </div>

        <!-- Lista -->
        <div v-if="posts.length" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="post in posts"
                :key="post.id"
                class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-800"
            >
                <div class="aspect-video bg-zinc-100 dark:bg-zinc-700">
                    <img v-if="post.image_url" :src="post.image_url" :alt="post.title" class="h-full w-full object-cover" />
                    <div v-else class="flex h-full w-full items-center justify-center text-zinc-400">
                        <ImageIcon class="h-10 w-10" />
                    </div>
                </div>
                <div class="p-4">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <span v-if="post.category" class="mb-1 inline-block rounded bg-[var(--color-primary)]/10 px-1.5 py-0.5 text-[11px] font-semibold uppercase text-[var(--color-primary)]">{{ post.category }}</span>
                            <h3 class="font-semibold text-zinc-900 dark:text-white line-clamp-2">{{ post.title }}</h3>
                            <p v-if="post.excerpt" class="mt-1 text-xs text-zinc-500 line-clamp-2">{{ post.excerpt }}</p>
                        </div>
                    </div>
                    <div class="mt-3 flex items-center gap-2">
                        <span
                            :class="['inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium', post.is_published ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-zinc-100 text-zinc-500 dark:bg-zinc-700 dark:text-zinc-400']"
                        >
                            <Eye v-if="post.is_published" class="h-3 w-3" />
                            <EyeOff v-else class="h-3 w-3" />
                            {{ post.is_published ? 'Publicado' : 'Rascunho' }}
                        </span>
                        <span class="text-xs text-zinc-400">{{ post.created_at }}</span>
                    </div>
                    <div class="mt-3 flex gap-2">
                        <button type="button" class="flex-1 rounded-lg border border-zinc-200 px-3 py-1.5 text-xs font-medium text-zinc-600 transition hover:bg-zinc-50 dark:border-zinc-600 dark:text-zinc-300 dark:hover:bg-zinc-700" @click="openEdit(post)">
                            <Pencil class="mr-1 inline h-3 w-3" /> Editar
                        </button>
                        <button type="button" class="rounded-lg border border-zinc-200 px-3 py-1.5 text-xs font-medium transition hover:bg-zinc-50 dark:border-zinc-600 dark:hover:bg-zinc-700" :class="post.is_published ? 'text-amber-600' : 'text-emerald-600'" @click="togglePublish(post)">
                            {{ post.is_published ? 'Despublicar' : 'Publicar' }}
                        </button>
                        <button type="button" class="rounded-lg border border-zinc-200 px-2 py-1.5 text-red-500 transition hover:bg-red-50 dark:border-zinc-600 dark:hover:bg-red-900/20" @click="destroy(post)">
                            <Trash2 class="h-3.5 w-3.5" />
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div v-else class="rounded-2xl border-2 border-dashed border-zinc-200 py-20 text-center dark:border-zinc-700">
            <ImageIcon class="mx-auto h-10 w-10 text-zinc-300" />
            <p class="mt-3 text-zinc-500">Nenhum post criado ainda.</p>
            <button type="button" class="mt-4 rounded-xl bg-[var(--color-primary)] px-4 py-2 text-sm text-white hover:opacity-90" @click="openCreate">Criar primeiro post</button>
        </div>

        <!-- Modal criar/editar -->
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
            <div class="w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-zinc-800">
                <div class="flex items-center justify-between border-b border-zinc-200 px-6 py-4 dark:border-zinc-700">
                    <h2 class="font-semibold text-zinc-900 dark:text-white">{{ editing ? 'Editar post' : 'Novo post' }}</h2>
                    <button type="button" @click="showModal = false" class="text-zinc-400 hover:text-zinc-700"><X class="h-5 w-5" /></button>
                </div>
                <div class="max-h-[70vh] overflow-y-auto p-6 space-y-4">
                    <!-- Imagem -->
                    <div>
                        <label class="mb-1 block text-xs font-medium text-zinc-600 dark:text-zinc-400">Imagem de capa</label>
                        <div
                            class="relative flex h-36 cursor-pointer items-center justify-center overflow-hidden rounded-xl border-2 border-dashed border-zinc-300 bg-zinc-50 transition hover:border-[var(--color-primary)] dark:border-zinc-600 dark:bg-zinc-700"
                            @click="fileInput?.click()"
                        >
                            <img v-if="imagePreview" :src="imagePreview" class="h-full w-full object-cover" />
                            <div v-else class="text-center text-zinc-400">
                                <ImageIcon class="mx-auto h-8 w-8" />
                                <p class="mt-1 text-xs">Clique para selecionar</p>
                            </div>
                        </div>
                        <input ref="fileInput" type="file" accept="image/*" class="hidden" @change="onImageChange" />
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-zinc-600 dark:text-zinc-400">Título *</label>
                        <input v-model="form.title" type="text" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)] dark:border-zinc-600 dark:bg-zinc-700 dark:text-white" placeholder="Título do post" />
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-zinc-600 dark:text-zinc-400">Categoria</label>
                        <input v-model="form.category" type="text" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)] dark:border-zinc-600 dark:bg-zinc-700 dark:text-white" placeholder="Ex: Novidades, Evento, Dica..." />
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-zinc-600 dark:text-zinc-400">Resumo</label>
                        <textarea v-model="form.excerpt" rows="2" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)] dark:border-zinc-600 dark:bg-zinc-700 dark:text-white" placeholder="Breve descrição exibida no card..." />
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-zinc-600 dark:text-zinc-400">Conteúdo</label>
                        <RichTextEditor v-model="form.content" />
                    </div>

                    <label class="flex cursor-pointer items-center gap-2">
                        <input v-model="form.is_published" type="checkbox" class="h-4 w-4 rounded" />
                        <span class="text-sm text-zinc-700 dark:text-zinc-300">Publicar imediatamente</span>
                    </label>
                </div>
                <div class="flex justify-end gap-3 border-t border-zinc-200 px-6 py-4 dark:border-zinc-700">
                    <button type="button" class="rounded-lg border border-zinc-300 px-4 py-2 text-sm text-zinc-600 hover:bg-zinc-50 dark:border-zinc-600 dark:text-zinc-300" @click="showModal = false">Cancelar</button>
                    <button type="button" :disabled="saving || !form.title.trim()" class="rounded-lg bg-[var(--color-primary)] px-4 py-2 text-sm font-medium text-white hover:opacity-90 disabled:opacity-50" @click="save">
                        {{ saving ? 'Salvando...' : (editing ? 'Salvar' : 'Criar') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
