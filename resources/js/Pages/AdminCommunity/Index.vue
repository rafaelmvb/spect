<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import LayoutInfoprodutor from '@/Layouts/LayoutInfoprodutor.vue';
import Button from '@/components/ui/Button.vue';
import axios from 'axios';
import {
    Globe, MessageSquare, CalendarDays, Users2, Plus, Trash2, Pencil,
    X, Image, Video, MapPin, Link2, Loader2, Clock, UserMinus,
    Sparkles, Eye, EyeOff, Check, Flag, ShieldCheck, XCircle
} from 'lucide-vue-next';

defineOptions({ layout: LayoutInfoprodutor });

const props = defineProps({
    products: { type: Array, default: () => [] },
    posts:    { type: Object, default: () => ({ data: [] }) },
    events:   { type: Array, default: () => [] },
    groups:   { type: Array, default: () => [] },
    pages:    { type: Array, default: () => [] },
    stories:  { type: Array, default: () => [] },
    reports:  { type: Array, default: () => [] },
});

const activeTab = ref('posts');
const toast = ref(null);

function csrfToken() { return document.querySelector('meta[name="csrf-token"]')?.content ?? ''; }
function headers(multipart = false) {
    const h = { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' };
    if (!multipart) h['Content-Type'] = 'application/json';
    return h;
}
function showToast(msg, type = 'success') {
    toast.value = { msg, type };
    setTimeout(() => toast.value = null, 4000);
}
function fmt(dt) {
    if (!dt) return '';
    return new Date(dt).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

const inputClass = 'w-full rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm text-zinc-900 focus:border-[var(--color-primary)] focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white';

const localReports = ref([...props.reports]);
const pendingReportsCount = computed(() => localReports.value.filter(r => r.status === 'pending').length);

const tabItems = computed(() => [
    { id: 'posts',    label: 'Posts',     icon: MessageSquare },
    { id: 'stories',  label: 'Stories',   icon: Sparkles },
    { id: 'events',   label: 'Eventos',   icon: CalendarDays },
    { id: 'groups',   label: 'Grupos',    icon: Users2 },
    { id: 'reports',  label: 'Denúncias', icon: Flag, badge: pendingReportsCount.value || null },
]);

const pagesByProduct = computed(() => {
    const map = {};
    props.pages.forEach(p => {
        if (!map[p.product_id]) map[p.product_id] = [];
        map[p.product_id].push(p);
    });
    return map;
});

// ─── Posts ────────────────────────────────────────────────────────────────────
const postForm = ref({ page_id: '', content: '', video_url: '', image: null });
const postImagePreview = ref('');
const postImageInputRef = ref(null);
const showVideoInput = ref(false);
const postSaving = ref(false);

function onPostImage(e) {
    const f = e.target?.files?.[0]; if (!f) return;
    postForm.value.image = f;
    postImagePreview.value = URL.createObjectURL(f);
    showVideoInput.value = false; postForm.value.video_url = '';
}
function clearPostImage() {
    postForm.value.image = null; postImagePreview.value = '';
    if (postImageInputRef.value) postImageInputRef.value.value = '';
}

async function submitPost() {
    if (!postForm.value.page_id) { showToast('Selecione uma página.', 'error'); return; }
    if (!postForm.value.content && !postForm.value.image && !postForm.value.video_url) {
        showToast('Escreva algo ou adicione mídia.', 'error'); return;
    }
    postSaving.value = true;
    try {
        const fd = new FormData();
        fd.append('page_id', postForm.value.page_id);
        if (postForm.value.content) fd.append('content', postForm.value.content);
        if (postForm.value.image) fd.append('image', postForm.value.image);
        if (postForm.value.video_url) fd.append('video_url', postForm.value.video_url);
        await axios.post('/comunidade-admin/posts', fd, { headers: headers(true) });
        showToast('Post publicado.');
        postForm.value = { page_id: postForm.value.page_id, content: '', video_url: '', image: null };
        clearPostImage(); showVideoInput.value = false;
        router.reload({ only: ['posts'] });
    } catch (e) { showToast(e.response?.data?.message ?? 'Erro.', 'error'); }
    finally { postSaving.value = false; }
}

async function deletePost(post) {
    if (!confirm('Excluir este post permanentemente?')) return;
    await axios.delete(`/comunidade-admin/posts/${post.id}`, { headers: headers() });
    showToast('Post excluído.');
    router.reload({ only: ['posts'] });
}

async function toggleHidePost(post) {
    try {
        const { data } = await axios.post(`/comunidade-admin/posts/${post.id}/toggle-hide`, {}, { headers: headers() });
        post.is_hidden = data.is_hidden;
        showToast(data.is_hidden ? 'Post ocultado dos alunos.' : 'Post visível novamente.');
    } catch { showToast('Erro ao alterar visibilidade.', 'error'); }
}

// Edição inline de post
const editingPost = ref(null);
const editContent = ref('');
const editSaving = ref(false);

function startEditPost(post) {
    editingPost.value = post.id;
    editContent.value = post.content ?? '';
}
function cancelEdit() { editingPost.value = null; editContent.value = ''; }

async function saveEditPost(post) {
    if (!editContent.value.trim()) { showToast('Conteúdo não pode ser vazio.', 'error'); return; }
    editSaving.value = true;
    try {
        const { data } = await axios.put(`/comunidade-admin/posts/${post.id}`, { content: editContent.value }, { headers: headers() });
        post.content = data.content;
        editingPost.value = null;
        showToast('Post atualizado.');
    } catch { showToast('Erro ao salvar.', 'error'); }
    finally { editSaving.value = false; }
}

// ─── Stories ─────────────────────────────────────────────────────────────────
const stories = ref([...props.stories]);
const storyForm = ref({ product_id: props.products[0]?.id ?? '', content: '', video_url: '', bg_color: '#1e1e2e', duration_hours: 24, image: null, video: null, visibility: 'all', visible_product_ids: [] });
const storyImageInputRef = ref(null);
const storyVideoInputRef = ref(null);
const storyImagePreview = ref('');
const storyVideoPreview = ref('');
const storySaving = ref(false);
const showStoryVideoInput = ref(false);

const BG_COLORS = [
    { color: '#1e1e2e', label: 'Escuro' },
    { color: '#0f172a', label: 'Azul noite' },
    { color: '#14532d', label: 'Verde' },
    { color: '#7c2d12', label: 'Laranja' },
    { color: '#4c1d95', label: 'Roxo' },
    { color: '#0c4a6e', label: 'Azul' },
    { color: '#881337', label: 'Vermelho' },
    { color: '#064e3b', label: 'Esmeralda' },
];

function onStoryImage(e) {
    const f = e.target?.files?.[0]; if (!f) return;
    storyForm.value.image = f; storyImagePreview.value = URL.createObjectURL(f);
    storyForm.value.video = null; storyVideoPreview.value = '';
    if (storyVideoInputRef.value) storyVideoInputRef.value.value = '';
    showStoryVideoInput.value = false; storyForm.value.video_url = '';
}
function clearStoryImage() {
    storyForm.value.image = null; storyImagePreview.value = '';
    if (storyImageInputRef.value) storyImageInputRef.value.value = '';
}
function onStoryVideo(e) {
    const f = e.target?.files?.[0]; if (!f) return;
    storyForm.value.video = f; storyVideoPreview.value = URL.createObjectURL(f);
    storyForm.value.image = null; storyImagePreview.value = '';
    if (storyImageInputRef.value) storyImageInputRef.value.value = '';
    showStoryVideoInput.value = true;
}
function clearStoryVideo() {
    storyForm.value.video = null; storyVideoPreview.value = '';
    storyForm.value.video_url = '';
    if (storyVideoInputRef.value) storyVideoInputRef.value.value = '';
    showStoryVideoInput.value = false;
}

async function submitStory() {
    if (!storyForm.value.product_id) { storyForm.value.product_id = props.products[0]?.id ?? ''; }
    if (!storyForm.value.product_id) { showToast('Nenhum produto disponível.', 'error'); return; }
    if (!storyForm.value.content && !storyForm.value.image && !storyForm.value.video && !storyForm.value.video_url) {
        showToast('Adicione texto, imagem ou vídeo.', 'error'); return;
    }
    storySaving.value = true;
    try {
        const fd = new FormData();
        fd.append('product_id', storyForm.value.product_id);
        fd.append('bg_color', storyForm.value.bg_color);
        fd.append('duration_hours', storyForm.value.duration_hours);
        if (storyForm.value.content) fd.append('content', storyForm.value.content);
        if (storyForm.value.image) fd.append('image', storyForm.value.image);
        if (storyForm.value.video_url) fd.append('video_url', storyForm.value.video_url);
        if (storyForm.value.video) fd.append('video', storyForm.value.video);
        fd.append('visibility', storyForm.value.visibility);
        (storyForm.value.visible_product_ids || []).forEach(id => fd.append('visible_product_ids[]', id));
        const { data } = await axios.post('/comunidade-admin/stories', fd, { headers: headers(true) });
        stories.value.unshift(data.story);
        showToast('Story publicado!');
        storyForm.value = { product_id: storyForm.value.product_id, content: '', video_url: '', bg_color: '#1e1e2e', duration_hours: 24, image: null, video: null, visibility: 'all', visible_product_ids: [] };
        clearStoryImage(); clearStoryVideo(); showStoryVideoInput.value = false;
    } catch (e) { showToast(e.response?.data?.message ?? 'Erro.', 'error'); }
    finally { storySaving.value = false; }
}

async function deleteStory(s) {
    if (!confirm('Excluir este story?')) return;
    await axios.delete(`/comunidade-admin/stories/${s.id}`, { headers: headers() });
    stories.value = stories.value.filter(x => x.id !== s.id);
    showToast('Story excluído.');
}

function timeLeft(expiresAt) {
    const diff = new Date(expiresAt) - new Date();
    if (diff <= 0) return 'Expirado';
    const h = Math.floor(diff / 3600000);
    const m = Math.floor((diff % 3600000) / 60000);
    return h > 0 ? `${h}h ${m}m restantes` : `${m}m restantes`;
}

// ─── Eventos ─────────────────────────────────────────────────────────────────
const events = ref([...props.events]);
const eventModal = ref(false);
const eventEditing = ref(null);
const eventForm = ref({ product_id: '', title: '', description: '', starts_at: '', ends_at: '', location: '', link: '', is_online: false, visibility: 'all', visible_product_ids: [], is_paid: false, price: '', payment_link: '' });
const eventSaving = ref(false);

function openEventModal(ev = null) {
    eventEditing.value = ev;
    eventForm.value = ev ? {
        product_id: ev.product_id, title: ev.title, description: ev.description ?? '',
        starts_at: ev.starts_at?.slice(0, 16) ?? '', ends_at: ev.ends_at?.slice(0, 16) ?? '',
        location: ev.location ?? '', link: ev.link ?? '', is_online: ev.is_online,
        visibility: ev.visibility ?? 'all', visible_product_ids: ev.visible_product_ids ?? [],
        is_paid: ev.is_paid ?? false, price: ev.price ?? '', payment_link: ev.payment_link ?? '',
    } : { product_id: props.products[0]?.id ?? '', title: '', description: '', starts_at: '', ends_at: '', location: '', link: '', is_online: false, visibility: 'all', visible_product_ids: [], is_paid: false, price: '', payment_link: '' };
    eventModal.value = true;
}
async function saveEvent() {
    if (!eventForm.value.title || !eventForm.value.starts_at) { showToast('Título e data são obrigatórios.', 'error'); return; }
    eventSaving.value = true;
    try {
        if (eventEditing.value) {
            await axios.put(`/comunidade-admin/eventos/${eventEditing.value.id}`, eventForm.value, { headers: headers() });
            const idx = events.value.findIndex(e => e.id === eventEditing.value.id);
            if (idx >= 0) events.value[idx] = { ...events.value[idx], ...eventForm.value };
        } else {
            const { data } = await axios.post('/comunidade-admin/eventos', eventForm.value, { headers: headers() });
            events.value.push(data.event);
        }
        showToast(eventEditing.value ? 'Evento atualizado.' : 'Evento criado.');
        eventModal.value = false;
    } catch (e) { showToast(e.response?.data?.message ?? 'Erro.', 'error'); }
    finally { eventSaving.value = false; }
}
async function deleteEvent(ev) {
    if (!confirm(`Excluir "${ev.title}"?`)) return;
    await axios.delete(`/comunidade-admin/eventos/${ev.id}`, { headers: headers() });
    events.value = events.value.filter(e => e.id !== ev.id);
    showToast('Evento excluído.');
}

// ─── Grupos ───────────────────────────────────────────────────────────────────
const groups = ref([...props.groups]);
const groupModal = ref(false);
const groupEditing = ref(null);
const groupForm = ref({ product_id: '', name: '', description: '', is_private: false });
const groupSaving = ref(false);
const membersModal = ref(false);
const selectedGroup = ref(null);
const groupMembers = ref([]);
const membersLoading = ref(false);

function openGroupModal(g = null) {
    groupEditing.value = g;
    groupForm.value = g
        ? { product_id: g.product_id, name: g.name, description: g.description ?? '', is_private: g.is_private }
        : { product_id: props.products[0]?.id ?? '', name: '', description: '', is_private: false };
    groupModal.value = true;
}
async function saveGroup() {
    if (!groupForm.value.name) { showToast('Nome é obrigatório.', 'error'); return; }
    groupSaving.value = true;
    try {
        if (groupEditing.value) {
            await axios.put(`/comunidade-admin/grupos/${groupEditing.value.id}`, groupForm.value, { headers: headers() });
            const idx = groups.value.findIndex(g => g.id === groupEditing.value.id);
            if (idx >= 0) groups.value[idx] = { ...groups.value[idx], ...groupForm.value };
        } else {
            const { data } = await axios.post('/comunidade-admin/grupos', groupForm.value, { headers: headers() });
            groups.value.push(data.group);
        }
        showToast(groupEditing.value ? 'Grupo atualizado.' : 'Grupo criado.');
        groupModal.value = false;
    } catch (e) { showToast(e.response?.data?.message ?? 'Erro.', 'error'); }
    finally { groupSaving.value = false; }
}
async function deleteGroup(g) {
    if (!confirm(`Excluir o grupo "${g.name}"?`)) return;
    await axios.delete(`/comunidade-admin/grupos/${g.id}`, { headers: headers() });
    groups.value = groups.value.filter(x => x.id !== g.id);
    showToast('Grupo excluído.');
}
async function openMembersModal(g) {
    selectedGroup.value = g; membersLoading.value = true; membersModal.value = true;
    try {
        const { data } = await axios.get(`/comunidade-admin/grupos/${g.id}/membros`, { headers: headers() });
        groupMembers.value = data.members;
    } finally { membersLoading.value = false; }
}
async function removeMember(userId) {
    await axios.delete(`/comunidade-admin/grupos/${selectedGroup.value.id}/membros/${userId}`, { headers: headers() });
    groupMembers.value = groupMembers.value.filter(m => m.id !== userId);
    const g = groups.value.find(x => x.id === selectedGroup.value.id);
    if (g) g.members_count = Math.max(0, g.members_count - 1);
}

// ─── Denúncias ────────────────────────────────────────────────────────────────
async function resolveReport(report) {
    try {
        const { data } = await axios.put(`/comunidade-admin/reports/${report.id}/resolve`, {}, { headers: headers() });
        const idx = localReports.value.findIndex(r => r.id === report.id);
        if (idx !== -1) {
            localReports.value[idx] = { ...localReports.value[idx], status: 'resolved' };
            if (data.post_hidden) {
                localReports.value[idx].post = { ...localReports.value[idx].post, is_hidden: true };
            }
        }
        showToast('Denúncia resolvida. Post ocultado.');
    } catch { showToast('Erro ao resolver denúncia.', 'error'); }
}

async function dismissReport(report) {
    try {
        await axios.put(`/comunidade-admin/reports/${report.id}/dismiss`, {}, { headers: headers() });
        const idx = localReports.value.findIndex(r => r.id === report.id);
        if (idx !== -1) localReports.value[idx] = { ...localReports.value[idx], status: 'dismissed' };
        showToast('Denúncia descartada.');
    } catch { showToast('Erro ao descartar denúncia.', 'error'); }
}

async function toggleHideFromReport(report) {
    try {
        await axios.post(`/comunidade-admin/posts/${report.post.id}/toggle-hide`, {}, { headers: headers() });
        const idx = localReports.value.findIndex(r => r.id === report.id);
        if (idx !== -1) localReports.value[idx].post.is_hidden = !localReports.value[idx].post.is_hidden;
        showToast('Visibilidade do post atualizada.');
    } catch { showToast('Erro ao atualizar post.', 'error'); }
}
</script>

<template>
    <!-- Toast -->
    <Transition enter-active-class="transition duration-300 ease-out" enter-from-class="opacity-0 translate-y-2" enter-to-class="opacity-100">
        <div v-if="toast" class="fixed bottom-6 right-6 z-[9999] rounded-xl px-5 py-3 text-sm font-medium text-white shadow-xl"
            :class="toast.type === 'error' ? 'bg-red-600' : 'bg-emerald-600'">
            {{ toast.msg }}
        </div>
    </Transition>

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-100 dark:bg-blue-900/30">
                <Globe class="h-5 w-5 text-blue-600 dark:text-blue-400" />
            </div>
            <div>
                <h1 class="text-xl font-bold text-zinc-900 dark:text-white">Comunidade</h1>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Gerencie posts, stories, eventos e grupos.</p>
            </div>
        </div>

        <!-- Tabs -->
        <div class="flex gap-1 rounded-xl border border-zinc-200 bg-zinc-50 p-1 dark:border-zinc-700 dark:bg-zinc-800/50">
            <button v-for="tab in tabItems" :key="tab.id" type="button"
                class="relative flex flex-1 items-center justify-center gap-2 rounded-lg py-2.5 text-sm font-medium transition"
                :class="activeTab === tab.id ? 'bg-white shadow dark:bg-zinc-700 text-zinc-900 dark:text-white' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300'"
                @click="activeTab = tab.id">
                <component :is="tab.icon" class="h-4 w-4" />
                {{ tab.label }}
                <span v-if="tab.badge" class="absolute right-1.5 top-1.5 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white">
                    {{ tab.badge }}
                </span>
            </button>
        </div>

        <!-- ═══ ABA POSTS ════════════════════════════════════════════════════════ -->
        <div v-if="activeTab === 'posts'" class="space-y-5">
            <!-- Formulário -->
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-800/50">
                <h2 class="mb-4 font-semibold text-zinc-800 dark:text-white">Criar post</h2>
                <div class="space-y-3">
                    <select v-model="postForm.page_id" :class="inputClass">
                        <option value="">Selecione a página...</option>
                        <optgroup v-for="prod in products" :key="prod.id" :label="prod.name">
                            <option v-for="pg in (pagesByProduct[prod.id] ?? [])" :key="pg.id" :value="pg.id">{{ pg.title }}</option>
                        </optgroup>
                    </select>
                    <textarea v-model="postForm.content" rows="3" :class="inputClass" placeholder="Escreva o post..." />
                    <div v-if="showVideoInput" class="flex items-center gap-2 rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-800">
                        <Video class="h-4 w-4 shrink-0 text-zinc-400" />
                        <input v-model="postForm.video_url" type="url" class="flex-1 bg-transparent text-sm focus:outline-none dark:text-white" placeholder="URL do vídeo..." />
                        <button type="button" class="text-zinc-400 hover:text-zinc-600" @click="showVideoInput = false; postForm.video_url = ''"><X class="h-4 w-4" /></button>
                    </div>
                    <p v-if="postImagePreview" class="text-xs text-zinc-500 dark:text-zinc-400">📎 Imagem selecionada
                        <button type="button" class="ml-1 text-red-500 hover:underline" @click="clearPostImage">remover</button>
                    </p>
                    <input ref="postImageInputRef" type="file" accept="image/*" class="hidden" @change="onPostImage" />
                    <div class="flex items-center gap-3 border-t border-zinc-100 pt-3 dark:border-zinc-700">
                        <button type="button" class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-700" @click="postImageInputRef?.click()">
                            <Image class="h-4 w-4" /> Foto
                        </button>
                        <button type="button"
                            :class="['flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm transition hover:bg-zinc-100 dark:hover:bg-zinc-700', showVideoInput ? 'text-blue-500' : 'text-zinc-500']"
                            @click="showVideoInput = !showVideoInput">
                            <Video class="h-4 w-4" /> Vídeo
                        </button>
                        <Button class="ml-auto" size="sm" :disabled="postSaving" @click="submitPost">
                            <Loader2 v-if="postSaving" class="mr-2 h-3.5 w-3.5 animate-spin" /> Publicar
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Tabela de posts -->
            <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/50">
                <table class="w-full text-sm">
                    <thead class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-zinc-500">Autor</th>
                            <th class="px-4 py-3 text-left font-medium text-zinc-500">Página</th>
                            <th class="px-4 py-3 text-left font-medium text-zinc-500">Conteúdo</th>
                            <th class="px-4 py-3 text-left font-medium text-zinc-500">Mídia</th>
                            <th class="px-4 py-3 text-left font-medium text-zinc-500">Status</th>
                            <th class="px-4 py-3 text-left font-medium text-zinc-500">Data</th>
                            <th class="px-4 py-3 text-right font-medium text-zinc-500">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700/50">
                        <tr v-for="post in (posts?.data ?? props.posts?.data ?? [])" :key="post.id"
                            class="bg-white dark:bg-zinc-800/30"
                            :class="post.is_hidden ? 'opacity-50' : ''">
                            <td class="px-4 py-3 font-medium text-zinc-800 dark:text-zinc-200 whitespace-nowrap">
                                {{ post.user?.name ?? 'Admin' }}
                            </td>
                            <td class="px-4 py-3 text-zinc-500 dark:text-zinc-400 whitespace-nowrap text-xs">
                                {{ post.page?.title ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300 max-w-xs">
                                <!-- Modo edição inline -->
                                <div v-if="editingPost === post.id" class="flex items-end gap-2">
                                    <textarea v-model="editContent" rows="2"
                                        class="flex-1 rounded-lg border border-zinc-300 bg-white px-2 py-1 text-xs dark:border-zinc-600 dark:bg-zinc-800 dark:text-white focus:outline-none" />
                                    <div class="flex flex-col gap-1 shrink-0">
                                        <button type="button"
                                            class="rounded-lg p-1 text-emerald-500 hover:bg-emerald-50 dark:hover:bg-emerald-950/30"
                                            :disabled="editSaving" @click="saveEditPost(post)">
                                            <Check class="h-3.5 w-3.5" />
                                        </button>
                                        <button type="button" class="rounded-lg p-1 text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-700" @click="cancelEdit">
                                            <X class="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                </div>
                                <p v-else class="line-clamp-2 text-xs">{{ post.content || '(sem texto)' }}</p>
                            </td>
                            <td class="px-4 py-3 text-xs text-zinc-400">
                                <span v-if="post.image_url" class="flex items-center gap-1"><Image class="h-3.5 w-3.5" /> Imagem</span>
                                <span v-else-if="post.video_url" class="flex items-center gap-1"><Video class="h-3.5 w-3.5" /> Vídeo</span>
                                <span v-else>—</span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="post.is_hidden
                                        ? 'bg-zinc-100 text-zinc-500 dark:bg-zinc-700 dark:text-zinc-400'
                                        : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'">
                                    {{ post.is_hidden ? 'Oculto' : 'Visível' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs text-zinc-400 whitespace-nowrap">{{ fmt(post.created_at) }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <!-- Editar -->
                                    <button type="button"
                                        class="rounded-lg p-1.5 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-700"
                                        title="Editar conteúdo"
                                        @click="startEditPost(post)">
                                        <Pencil class="h-4 w-4" />
                                    </button>
                                    <!-- Ocultar / Exibir -->
                                    <button type="button"
                                        class="rounded-lg p-1.5 transition"
                                        :class="post.is_hidden
                                            ? 'text-amber-500 hover:bg-amber-50 dark:hover:bg-amber-950/30'
                                            : 'text-zinc-400 hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-700'"
                                        :title="post.is_hidden ? 'Tornar visível' : 'Ocultar dos alunos'"
                                        @click="toggleHidePost(post)">
                                        <EyeOff v-if="!post.is_hidden" class="h-4 w-4" />
                                        <Eye v-else class="h-4 w-4" />
                                    </button>
                                    <!-- Excluir -->
                                    <button type="button"
                                        class="rounded-lg p-1.5 text-zinc-400 hover:bg-red-50 hover:text-red-500 dark:hover:bg-red-950/30"
                                        title="Excluir permanentemente"
                                        @click="deletePost(post)">
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!(posts?.data ?? props.posts?.data ?? []).length">
                            <td colspan="7" class="px-4 py-10 text-center text-zinc-400">Nenhum post ainda.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ═══ ABA STORIES ══════════════════════════════════════════════════════ -->
        <div v-if="activeTab === 'stories'" class="space-y-5">
            <!-- Formulário de criação -->
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-800/50">
                <h2 class="mb-1 font-semibold text-zinc-800 dark:text-white">Criar Story</h2>
                <p class="mb-4 text-xs text-zinc-400">Stories são exibidos no topo da comunidade e expiram automaticamente. Somente admins podem publicar.</p>

                <div class="space-y-4">
                    <!-- Preview do story -->
                    <div class="flex gap-4">
                        <!-- Miniatura preview -->
                        <div class="flex h-32 w-20 shrink-0 flex-col items-center justify-center rounded-xl p-2 text-center"
                            :style="{ backgroundColor: storyForm.bg_color }">
                            <img v-if="storyImagePreview" :src="storyImagePreview" class="h-full w-full rounded-lg object-cover" />
                            <div v-else>
                                <Sparkles class="mx-auto h-5 w-5 text-white/60" />
                                <p class="mt-1 text-[10px] text-white/80 leading-tight">{{ storyForm.content || 'Preview' }}</p>
                            </div>
                        </div>

                        <div class="flex-1 space-y-3">
                            <textarea v-model="storyForm.content" rows="2" :class="inputClass"
                                placeholder="Texto do story (máx. 300 chars)..." maxlength="300" />

                            <!-- Cor de fundo -->
                            <div>
                                <label class="mb-1.5 block text-xs font-medium text-zinc-500">Cor de fundo</label>
                                <div class="flex flex-wrap gap-2">
                                    <button v-for="bg in BG_COLORS" :key="bg.color" type="button"
                                        class="h-7 w-7 rounded-lg border-2 transition"
                                        :style="{ backgroundColor: bg.color }"
                                        :class="storyForm.bg_color === bg.color ? 'border-white scale-110 shadow-lg' : 'border-transparent'"
                                        :title="bg.label"
                                        @click="storyForm.bg_color = bg.color" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Upload de vídeo (quando ativo) -->
                    <div v-if="showStoryVideoInput">
                        <input ref="storyVideoInputRef" type="file" accept="video/mp4,video/webm,video/mov" class="hidden" @change="onStoryVideo" />
                        <div v-if="storyVideoPreview" class="relative rounded-xl overflow-hidden border border-zinc-200 dark:border-zinc-700">
                            <video :src="storyVideoPreview" controls class="w-full max-h-48 bg-black" />
                            <button type="button" class="absolute top-2 right-2 rounded-full bg-red-600 p-1 text-white" @click="clearStoryVideo"><X class="h-3.5 w-3.5" /></button>
                        </div>
                        <button v-else type="button" class="flex w-full items-center justify-center gap-2 rounded-xl border-2 border-dashed border-zinc-300 py-5 text-sm text-zinc-500 transition hover:border-blue-400 hover:text-blue-500 dark:border-zinc-600"
                            @click="storyVideoInputRef?.click()">
                            <Video class="h-5 w-5" /> Selecionar vídeo MP4/WebM (máx 200MB)
                        </button>
                    </div>

                    <!-- Visibilidade -->
                    <div>
                        <label class="mb-2 block text-xs font-medium text-zinc-500">Visibilidade</label>
                        <div class="grid grid-cols-2 gap-3">
                            <button type="button"
                                class="rounded-xl border-2 p-3 text-center text-sm transition"
                                :class="storyForm.visibility === 'all' ? 'border-[var(--color-primary)] bg-[var(--color-primary)]/10 text-[var(--color-primary)]' : 'border-zinc-200 bg-zinc-50 text-zinc-600 dark:border-zinc-600 dark:bg-zinc-800/50'"
                                @click="storyForm.visibility = 'all'">
                                🌐 Todos os alunos
                            </button>
                            <button type="button"
                                class="rounded-xl border-2 p-3 text-center text-sm transition"
                                :class="storyForm.visibility === 'specific' ? 'border-[var(--color-primary)] bg-[var(--color-primary)]/10 text-[var(--color-primary)]' : 'border-zinc-200 bg-zinc-50 text-zinc-600 dark:border-zinc-600 dark:bg-zinc-800/50'"
                                @click="storyForm.visibility = 'specific'">
                                🔒 Cursos específicos
                            </button>
                        </div>
                        <div v-if="storyForm.visibility === 'specific'" class="mt-3 space-y-1.5 rounded-xl border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-800/50">
                            <label v-for="p in products" :key="p.id" class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" :value="p.id" v-model="storyForm.visible_product_ids" class="rounded text-[var(--color-primary)]" />
                                <span class="text-sm text-zinc-800 dark:text-zinc-200">{{ p.name }}</span>
                            </label>
                        </div>
                    </div>

                    <!-- Duração -->
                    <div>
                        <label class="mb-1 block text-xs font-medium text-zinc-500">Duração do story</label>
                        <select v-model="storyForm.duration_hours" :class="inputClass">
                            <option :value="6">6 horas</option>
                            <option :value="12">12 horas</option>
                            <option :value="24">24 horas (padrão)</option>
                            <option :value="48">48 horas</option>
                            <option :value="72">72 horas</option>
                        </select>
                    </div>

                    <input ref="storyImageInputRef" type="file" accept="image/*" class="hidden" @change="onStoryImage" />
                    <p v-if="storyImagePreview" class="text-xs text-zinc-500">📎 Imagem selecionada
                        <button type="button" class="ml-1 text-red-500 hover:underline" @click="clearStoryImage">remover</button>
                    </p>

                    <div class="flex items-center gap-3 border-t border-zinc-100 pt-3 dark:border-zinc-700">
                        <button type="button" class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-700"
                            @click="storyImageInputRef?.click()">
                            <Image class="h-4 w-4" /> Foto
                        </button>
                        <button type="button"
                            :class="['flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm transition hover:bg-zinc-100 dark:hover:bg-zinc-700', showStoryVideoInput ? 'text-blue-500' : 'text-zinc-500']"
                            @click="showStoryVideoInput = !showStoryVideoInput; if(!showStoryVideoInput) clearStoryVideo()">
                            <Video class="h-4 w-4" /> Vídeo
                        </button>
                        <Button class="ml-auto" :disabled="storySaving" @click="submitStory">
                            <Loader2 v-if="storySaving" class="mr-2 h-3.5 w-3.5 animate-spin" />
                            <Sparkles class="mr-2 h-3.5 w-3.5" />
                            Publicar story
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Lista de stories ativos/expirados -->
            <div v-if="!stories.length" class="flex flex-col items-center justify-center gap-3 rounded-2xl border-2 border-dashed border-zinc-200 py-12 dark:border-zinc-700">
                <Sparkles class="h-8 w-8 text-zinc-300" />
                <p class="text-sm text-zinc-400">Nenhum story publicado.</p>
            </div>

            <div v-else class="overflow-hidden rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/50">
                <table class="w-full text-sm">
                    <thead class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-zinc-500">Preview</th>
                            <th class="px-4 py-3 text-left font-medium text-zinc-500">Conteúdo</th>
                            <th class="px-4 py-3 text-left font-medium text-zinc-500">Expira</th>
                            <th class="px-4 py-3 text-left font-medium text-zinc-500">Status</th>
                            <th class="px-4 py-3 text-left font-medium text-zinc-500">Criado em</th>
                            <th class="px-4 py-3 text-right font-medium text-zinc-500">Ação</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700/50">
                        <tr v-for="s in stories" :key="s.id" class="bg-white dark:bg-zinc-800/30">
                            <td class="px-4 py-3">
                                <div class="h-10 w-7 rounded-lg flex items-center justify-center overflow-hidden"
                                    :style="{ backgroundColor: s.bg_color }">
                                    <img v-if="s.image_url" :src="s.image_url" class="h-full w-full object-cover" />
                                    <Sparkles v-else class="h-3 w-3 text-white/70" />
                                </div>
                            </td>
                            <td class="px-4 py-3 max-w-xs">
                                <p class="line-clamp-2 text-xs text-zinc-700 dark:text-zinc-300">{{ s.content || '(só mídia)' }}</p>
                            </td>
                            <td class="px-4 py-3 text-xs text-zinc-400 whitespace-nowrap">{{ fmt(s.expires_at) }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="s.is_expired ? 'bg-zinc-100 text-zinc-500 dark:bg-zinc-700 dark:text-zinc-400' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'">
                                    {{ s.is_expired ? 'Expirado' : timeLeft(s.expires_at) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs text-zinc-400 whitespace-nowrap">{{ s.created_at }}</td>
                            <td class="px-4 py-3 text-right">
                                <button type="button" class="rounded-lg p-1.5 text-zinc-400 hover:bg-red-50 hover:text-red-500 dark:hover:bg-red-950/30" @click="deleteStory(s)">
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ═══ ABA EVENTOS ══════════════════════════════════════════════════════ -->
        <div v-if="activeTab === 'events'" class="space-y-4">
            <div class="flex justify-end">
                <Button @click="openEventModal()"><Plus class="mr-2 h-4 w-4" /> Criar evento</Button>
            </div>
            <div v-if="!events.length" class="flex flex-col items-center justify-center gap-3 rounded-2xl border-2 border-dashed border-zinc-200 py-16 dark:border-zinc-700">
                <CalendarDays class="h-10 w-10 text-zinc-300" />
                <p class="text-zinc-400">Nenhum evento criado.</p>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div v-for="ev in events" :key="ev.id" class="overflow-hidden rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/50">
                    <div class="flex h-16 items-center justify-center bg-gradient-to-br from-blue-500 to-violet-600">
                        <CalendarDays class="h-7 w-7 text-white/80" />
                    </div>
                    <div class="p-4">
                        <div class="flex items-start justify-between gap-2">
                            <h3 class="font-semibold text-zinc-900 dark:text-white">{{ ev.title }}</h3>
                            <div class="flex shrink-0 gap-1">
                                <button type="button" class="rounded-lg p-1 text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-700" @click="openEventModal(ev)"><Pencil class="h-3.5 w-3.5" /></button>
                                <button type="button" class="rounded-lg p-1 text-zinc-400 hover:bg-red-50 hover:text-red-500" @click="deleteEvent(ev)"><Trash2 class="h-3.5 w-3.5" /></button>
                            </div>
                        </div>
                        <p v-if="ev.description" class="mt-1 line-clamp-2 text-xs text-zinc-500">{{ ev.description }}</p>
                        <div class="mt-2 space-y-1">
                            <div class="flex items-center gap-1.5 text-xs text-zinc-500"><Clock class="h-3.5 w-3.5" /> {{ fmt(ev.starts_at) }}</div>
                            <div v-if="ev.location || ev.is_online" class="flex items-center gap-1.5 text-xs text-zinc-500">
                                <MapPin class="h-3.5 w-3.5" /> {{ ev.is_online ? 'Online' : ev.location }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══ ABA GRUPOS ═══════════════════════════════════════════════════════ -->
        <div v-if="activeTab === 'groups'" class="space-y-4">
            <div class="flex justify-end">
                <Button @click="openGroupModal()"><Plus class="mr-2 h-4 w-4" /> Criar grupo</Button>
            </div>
            <div v-if="!groups.length" class="flex flex-col items-center justify-center gap-3 rounded-2xl border-2 border-dashed border-zinc-200 py-16 dark:border-zinc-700">
                <Users2 class="h-10 w-10 text-zinc-300" />
                <p class="text-zinc-400">Nenhum grupo criado.</p>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div v-for="g in groups" :key="g.id" class="overflow-hidden rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/50">
                    <div class="flex h-16 items-center justify-center bg-gradient-to-br from-emerald-500 to-teal-600">
                        <Users2 class="h-7 w-7 text-white/80" />
                    </div>
                    <div class="p-4">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-center gap-1.5">
                                <h3 class="font-semibold text-zinc-900 dark:text-white">{{ g.name }}</h3>
                                <span class="rounded-full px-1.5 py-0.5 text-[10px] font-medium"
                                    :class="g.is_private ? 'bg-zinc-100 text-zinc-500 dark:bg-zinc-700' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30'">
                                    {{ g.is_private ? '🔒' : '🌐' }}
                                </span>
                            </div>
                            <div class="flex shrink-0 gap-1">
                                <button type="button" class="rounded-lg p-1 text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-700" @click="openGroupModal(g)"><Pencil class="h-3.5 w-3.5" /></button>
                                <button type="button" class="rounded-lg p-1 text-zinc-400 hover:bg-red-50 hover:text-red-500" @click="deleteGroup(g)"><Trash2 class="h-3.5 w-3.5" /></button>
                            </div>
                        </div>
                        <p v-if="g.description" class="mt-1 line-clamp-2 text-xs text-zinc-500">{{ g.description }}</p>
                        <div class="mt-3 flex items-center justify-between">
                            <span class="flex items-center gap-1.5 text-xs text-zinc-400"><Users2 class="h-3.5 w-3.5" /> {{ g.members_count }} membros</span>
                            <button type="button" class="rounded-lg border border-zinc-200 px-2.5 py-1 text-xs font-medium text-zinc-600 hover:bg-zinc-50 dark:border-zinc-600 dark:text-zinc-400 dark:hover:bg-zinc-700"
                                @click="openMembersModal(g)">Membros</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══ ABA DENÚNCIAS ════════════════════════════════════════════════════ -->
        <div v-if="activeTab === 'reports'" class="space-y-4">
            <div v-if="!localReports.length" class="flex flex-col items-center justify-center gap-3 rounded-2xl border-2 border-dashed border-zinc-200 py-16 dark:border-zinc-700">
                <Flag class="h-10 w-10 text-zinc-300" />
                <p class="text-zinc-400">Nenhuma denúncia recebida.</p>
            </div>

            <div v-for="report in localReports" :key="report.id"
                class="overflow-hidden rounded-2xl border bg-white dark:bg-zinc-800/50"
                :class="report.status === 'pending' ? 'border-amber-300 dark:border-amber-700' : 'border-zinc-200 dark:border-zinc-700'">

                <!-- Cabeçalho da denúncia -->
                <div class="flex items-center justify-between gap-3 border-b border-zinc-100 px-4 py-3 dark:border-zinc-700">
                    <div class="flex items-center gap-2">
                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold"
                            :class="{
                                'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300': report.status === 'pending',
                                'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300': report.status === 'resolved',
                                'bg-zinc-100 text-zinc-500 dark:bg-zinc-700': report.status === 'dismissed',
                            }">
                            {{ report.status === 'pending' ? 'Pendente' : report.status === 'resolved' ? 'Resolvido' : 'Descartado' }}
                        </span>
                        <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ report.reason_label }}</span>
                    </div>
                    <span class="text-xs text-zinc-400">{{ report.created_at }}</span>
                </div>

                <div class="p-4 space-y-3">
                    <!-- Preview do post -->
                    <div v-if="report.post" class="rounded-xl border border-zinc-100 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-800/70">
                        <div class="flex items-center justify-between gap-2 mb-1">
                            <p class="text-xs text-zinc-500">Post de <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ report.post.author?.name ?? '—' }}</span></p>
                            <span v-if="report.post.is_hidden" class="text-[10px] font-semibold text-amber-600 dark:text-amber-400">OCULTO</span>
                        </div>
                        <p class="text-sm text-zinc-700 dark:text-zinc-300 line-clamp-3">{{ report.post.content || '(sem texto)' }}</p>
                    </div>

                    <!-- Denunciante + notas -->
                    <div class="flex items-start justify-between gap-4 text-sm">
                        <div>
                            <p class="text-xs text-zinc-400">Denunciado por</p>
                            <p class="font-medium text-zinc-800 dark:text-zinc-200">{{ report.reporter?.name ?? '—' }}</p>
                            <p class="text-xs text-zinc-400">{{ report.reporter?.email }}</p>
                        </div>
                        <div v-if="report.notes" class="max-w-xs rounded-lg border border-zinc-200 bg-white px-3 py-2 text-xs text-zinc-600 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-400 italic">
                            "{{ report.notes }}"
                        </div>
                    </div>

                    <!-- Ações -->
                    <div v-if="report.status === 'pending'" class="flex flex-wrap gap-2 pt-1">
                        <button type="button"
                            class="flex items-center gap-1.5 rounded-lg border border-amber-300 px-3 py-1.5 text-xs font-medium text-amber-700 hover:bg-amber-50 dark:border-amber-700 dark:text-amber-300 dark:hover:bg-amber-900/20"
                            @click="toggleHideFromReport(report)">
                            <EyeOff v-if="!report.post?.is_hidden" class="h-3.5 w-3.5" />
                            <Eye v-else class="h-3.5 w-3.5" />
                            {{ report.post?.is_hidden ? 'Exibir post' : 'Ocultar post' }}
                        </button>
                        <button type="button"
                            class="flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-emerald-700"
                            @click="resolveReport(report)">
                            <ShieldCheck class="h-3.5 w-3.5" /> Resolver (ocultar post)
                        </button>
                        <button type="button"
                            class="flex items-center gap-1.5 rounded-lg border border-zinc-200 px-3 py-1.5 text-xs font-medium text-zinc-500 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
                            @click="dismissReport(report)">
                            <XCircle class="h-3.5 w-3.5" /> Descartar
                        </button>
                    </div>
                    <div v-else class="text-xs text-zinc-400 pt-1">
                        Resolvido por {{ report.resolver?.name ?? '—' }} em {{ report.resolved_at }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Evento -->
    <Teleport to="body">
        <div v-if="eventModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="eventModal = false">
            <div class="w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-zinc-900">
                <div class="flex items-center justify-between border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                    <h3 class="font-semibold text-zinc-900 dark:text-white">{{ eventEditing ? 'Editar evento' : 'Novo evento' }}</h3>
                    <button type="button" class="rounded-lg p-1 text-zinc-400 hover:text-zinc-600" @click="eventModal = false"><X class="h-4 w-4" /></button>
                </div>
                <div class="max-h-[70vh] overflow-y-auto p-5 space-y-4">
                    <div><label class="mb-1 block text-xs font-medium text-zinc-500">Título *</label>
                        <input v-model="eventForm.title" type="text" :class="inputClass" placeholder="Nome do evento" /></div>
                    <div><label class="mb-1 block text-xs font-medium text-zinc-500">Descrição</label>
                        <textarea v-model="eventForm.description" rows="3" :class="inputClass" /></div>
                    <div class="grid grid-cols-2 gap-3">
                        <div><label class="mb-1 block text-xs font-medium text-zinc-500">Início *</label>
                            <input v-model="eventForm.starts_at" type="datetime-local" :class="inputClass" /></div>
                        <div><label class="mb-1 block text-xs font-medium text-zinc-500">Fim</label>
                            <input v-model="eventForm.ends_at" type="datetime-local" :class="inputClass" /></div>
                    </div>
                    <div class="flex items-center gap-3 rounded-xl border border-zinc-200 px-4 py-3 dark:border-zinc-700">
                        <button type="button" class="relative inline-flex h-6 w-11 shrink-0 rounded-full border-2 border-transparent transition-colors"
                            :class="eventForm.is_online ? 'bg-blue-600' : 'bg-zinc-300 dark:bg-zinc-600'"
                            @click="eventForm.is_online = !eventForm.is_online">
                            <span class="inline-block h-5 w-5 rounded-full bg-white shadow transition-transform" :class="eventForm.is_online ? 'translate-x-5' : 'translate-x-0'" />
                        </button>
                        <span class="text-sm text-zinc-700 dark:text-zinc-300">Evento online</span>
                    </div>
                    <div v-if="!eventForm.is_online"><label class="mb-1 block text-xs font-medium text-zinc-500">Local</label>
                        <input v-model="eventForm.location" type="text" :class="inputClass" placeholder="Endereço" /></div>
                    <div><label class="mb-1 block text-xs font-medium text-zinc-500">Link do evento</label>
                        <input v-model="eventForm.link" type="url" :class="inputClass" placeholder="https://..." /></div>

                    <!-- Visibilidade -->
                    <div>
                        <label class="mb-2 block text-xs font-medium text-zinc-500">Quem pode ver este evento?</label>
                        <div class="grid grid-cols-2 gap-2">
                            <button type="button" class="rounded-xl border-2 p-2.5 text-center text-sm transition"
                                :class="eventForm.visibility === 'all' ? 'border-[var(--color-primary)] bg-[var(--color-primary)]/10 text-[var(--color-primary)]' : 'border-zinc-200 text-zinc-600 dark:border-zinc-600'"
                                @click="eventForm.visibility = 'all'">🌐 Todos os alunos</button>
                            <button type="button" class="rounded-xl border-2 p-2.5 text-center text-sm transition"
                                :class="eventForm.visibility === 'specific' ? 'border-[var(--color-primary)] bg-[var(--color-primary)]/10 text-[var(--color-primary)]' : 'border-zinc-200 text-zinc-600 dark:border-zinc-600'"
                                @click="eventForm.visibility = 'specific'">🔒 Trilhas específicas</button>
                        </div>
                        <div v-if="eventForm.visibility === 'specific'" class="mt-2 space-y-1.5 rounded-xl border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-800/50">
                            <label v-for="p in products" :key="p.id" class="flex cursor-pointer items-center gap-2">
                                <input type="checkbox" :value="p.id" v-model="eventForm.visible_product_ids" class="rounded" />
                                <span class="text-sm text-zinc-800 dark:text-zinc-200">{{ p.name }}</span>
                            </label>
                        </div>
                    </div>

                    <!-- Evento pago -->
                    <div class="flex items-center gap-3 rounded-xl border border-zinc-200 px-4 py-3 dark:border-zinc-700">
                        <button type="button" class="relative inline-flex h-6 w-11 shrink-0 rounded-full border-2 border-transparent transition-colors"
                            :class="eventForm.is_paid ? 'bg-emerald-600' : 'bg-zinc-300 dark:bg-zinc-600'"
                            @click="eventForm.is_paid = !eventForm.is_paid">
                            <span class="inline-block h-5 w-5 rounded-full bg-white shadow transition-transform" :class="eventForm.is_paid ? 'translate-x-5' : 'translate-x-0'" />
                        </button>
                        <span class="text-sm text-zinc-700 dark:text-zinc-300">Evento pago</span>
                    </div>
                    <div v-if="eventForm.is_paid" class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-zinc-500">Valor (R$)</label>
                            <input v-model="eventForm.price" type="number" min="0" step="0.01" :class="inputClass" placeholder="0,00" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-zinc-500">Link de inscrição/pagamento</label>
                            <input v-model="eventForm.payment_link" type="url" :class="inputClass" placeholder="https://..." />
                        </div>
                    </div>
                </div>
                <div class="flex justify-end gap-2 border-t border-zinc-200 px-5 py-3 dark:border-zinc-700">
                    <Button variant="outline" @click="eventModal = false">Cancelar</Button>
                    <Button :disabled="eventSaving" @click="saveEvent">
                        <Loader2 v-if="eventSaving" class="mr-2 h-3.5 w-3.5 animate-spin" />
                        {{ eventEditing ? 'Salvar' : 'Criar evento' }}
                    </Button>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- Modal Grupo -->
    <Teleport to="body">
        <div v-if="groupModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="groupModal = false">
            <div class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-zinc-900">
                <div class="flex items-center justify-between border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                    <h3 class="font-semibold text-zinc-900 dark:text-white">{{ groupEditing ? 'Editar grupo' : 'Novo grupo' }}</h3>
                    <button type="button" class="rounded-lg p-1 text-zinc-400 hover:text-zinc-600" @click="groupModal = false"><X class="h-4 w-4" /></button>
                </div>
                <div class="p-5 space-y-4">
                    <div><label class="mb-1 block text-xs font-medium text-zinc-500">Produto</label>
                        <select v-model="groupForm.product_id" :class="inputClass">
                            <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select></div>
                    <div><label class="mb-1 block text-xs font-medium text-zinc-500">Nome *</label>
                        <input v-model="groupForm.name" type="text" :class="inputClass" placeholder="Nome do grupo" /></div>
                    <div><label class="mb-1 block text-xs font-medium text-zinc-500">Descrição</label>
                        <textarea v-model="groupForm.description" rows="2" :class="inputClass" /></div>
                    <div class="flex items-center gap-3 rounded-xl border border-zinc-200 px-4 py-3 dark:border-zinc-700">
                        <button type="button" class="relative inline-flex h-6 w-11 shrink-0 rounded-full border-2 border-transparent transition-colors"
                            :class="groupForm.is_private ? 'bg-zinc-700' : 'bg-emerald-600'"
                            @click="groupForm.is_private = !groupForm.is_private">
                            <span class="inline-block h-5 w-5 rounded-full bg-white shadow transition-transform" :class="groupForm.is_private ? 'translate-x-5' : 'translate-x-0'" />
                        </button>
                        <div>
                            <p class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ groupForm.is_private ? '🔒 Privado' : '🌐 Público' }}</p>
                            <p class="text-xs text-zinc-400">{{ groupForm.is_private ? 'Membros adicionados manualmente' : 'Qualquer aluno pode entrar' }}</p>
                        </div>
                    </div>
                </div>
                <div class="flex justify-end gap-2 border-t border-zinc-200 px-5 py-3 dark:border-zinc-700">
                    <Button variant="outline" @click="groupModal = false">Cancelar</Button>
                    <Button :disabled="groupSaving" @click="saveGroup">
                        <Loader2 v-if="groupSaving" class="mr-2 h-3.5 w-3.5 animate-spin" />
                        {{ groupEditing ? 'Salvar' : 'Criar grupo' }}
                    </Button>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- Modal Membros -->
    <Teleport to="body">
        <div v-if="membersModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="membersModal = false">
            <div class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-zinc-900">
                <div class="flex items-center justify-between border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                    <h3 class="font-semibold text-zinc-900 dark:text-white">Membros — {{ selectedGroup?.name }}</h3>
                    <button type="button" class="rounded-lg p-1 text-zinc-400 hover:text-zinc-600" @click="membersModal = false"><X class="h-4 w-4" /></button>
                </div>
                <div class="max-h-96 overflow-y-auto p-4">
                    <div v-if="membersLoading" class="flex items-center justify-center py-8"><Loader2 class="h-6 w-6 animate-spin text-zinc-400" /></div>
                    <div v-else-if="!groupMembers.length" class="py-8 text-center text-sm text-zinc-400">Nenhum membro.</div>
                    <div v-else class="space-y-2">
                        <div v-for="m in groupMembers" :key="m.id"
                            class="flex items-center justify-between rounded-xl px-3 py-2.5 hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                            <div>
                                <p class="text-sm font-medium text-zinc-900 dark:text-white">{{ m.name }}</p>
                                <p class="text-xs text-zinc-400">{{ m.email }}</p>
                            </div>
                            <button type="button" class="rounded-lg p-1.5 text-zinc-400 hover:bg-red-50 hover:text-red-500" @click="removeMember(m.id)">
                                <UserMinus class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
