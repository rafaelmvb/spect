<script setup>
import { ref, watch, computed, onMounted, onUnmounted } from 'vue';
import { useForm, Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import MemberAreaAppLayout from '@/Layouts/MemberAreaAppLayout.vue';
import StoryViewer from '@/components/member-area/StoryViewer.vue';
import { Image, Video, X, MoreHorizontal, Trash2, PenLine, Flag } from 'lucide-vue-next';
import { getCommunityPageIconComponent } from '@/utils/communityPageIcons';
import { useMusicPlayer } from '@/composables/useMusicPlayer';
import { useMemberBase } from '@/composables/useMemberBase';

defineOptions({ layout: MemberAreaAppLayout });

const props = defineProps({
    product: { type: Object, required: true },
    config: { type: Object, default: () => ({}) },
    auth_user_id: { type: Number, default: null },
    can_delete_any_post: { type: Boolean, default: false },
    community_users_can_delete_own_posts: { type: Boolean, default: true },
    pages: { type: Array, default: () => [] },
    page: { type: Object, required: true },
    posts: { type: Object, required: true },
    slug: { type: String, required: true },
    base_url: { type: String, default: '' },
    friendship_map: { type: Object, default: () => ({}) },
});

const postsList = ref(props.posts?.data ? [...props.posts.data] : []);
watch(() => props.posts?.data, (data) => { postsList.value = data ? [...data] : []; }, { immediate: true });

const postForm = useForm({ content: '', image: null, video: null, video_url: '' });
const postImageInputRef = ref(null);
const postVideoInputRef = ref(null);
const postImagePreview = ref('');
const postVideoPreview = ref('');
const showVideoInput = ref(false);
const likeLoadingId = ref(null);
const commentLoadingId = ref(null);
const commentContentByPost = ref({});
const expandedComments = ref({});    // mostra input + todos os comentários
const expandedCaption = ref({});     // "ver mais" na legenda
const postMenuOpen = ref(null);      // menu de 3 pontinhos

// ─── Denúncias ────────────────────────────────────────────────────────────────
const reportModal = ref({ open: false, postId: null, reason: '', notes: '', loading: false, done: false });
const reportedPostIds = ref(new Set());
const reportReasons = [
    { value: 'spam',                  label: 'Spam' },
    { value: 'conteudo_inapropriado', label: 'Conteúdo inapropriado' },
    { value: 'violencia',             label: 'Violência ou ameaça' },
    { value: 'assedio',               label: 'Assédio ou bullying' },
    { value: 'desinformacao',         label: 'Desinformação' },
    { value: 'outro',                 label: 'Outro motivo' },
];

function openReportModal(postId) {
    postMenuOpen.value = null;
    reportModal.value = { open: true, postId, reason: '', notes: '', loading: false, done: false };
}

async function submitReport() {
    if (!reportModal.value.reason) return;
    reportModal.value.loading = true;
    try {
        await axios.post(`${postsBase()}/${reportModal.value.postId}/report`, {
            reason: reportModal.value.reason,
            notes: reportModal.value.notes || undefined,
        }, { headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' } });
        reportedPostIds.value.add(reportModal.value.postId);
        reportModal.value.done = true;
    } catch (err) {
        alert(err.response?.data?.message ?? 'Erro ao enviar denúncia.');
    } finally {
        reportModal.value.loading = false;
    }
}
const showNewPostModal = ref(false);
const { currentTrack } = useMusicPlayer();
const isDesktop = ref(typeof window !== 'undefined' && window.innerWidth >= 1024);
const onResize = () => { isDesktop.value = window.innerWidth >= 1024; };
onMounted(() => window.addEventListener('resize', onResize));
onUnmounted(() => window.removeEventListener('resize', onResize));
// Desktop: chat-fab fica em bottom:32px. Mobile: acima do bottom nav (80px).
const fabBottom = computed(() => {
    if (isDesktop.value) return '32px';
    return currentTrack.value ? 'calc(80px + 58px + 12px)' : 'calc(80px + 12px)';
});

// ─── Amizade no feed ─────────────────────────────────────────────────────────
const localFriendshipMap = ref({ ...props.friendship_map });

async function requestFriendFromFeed(userId) {
    if (!userId || userId === props.auth_user_id) return;
    localFriendshipMap.value[userId] = 'pending';
    try {
        await axios.post(`${memberBase.value}/amizades/${userId}`, {}, {
            headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
        });
    } catch {
        localFriendshipMap.value[userId] = null;
    }
}

const inertiaPage = usePage();
const primaryColor = computed(() =>
    inertiaPage.props?.member_area_global_branding?.primary_color
    || props.config?.theme?.primary
    || '#6FCF00'
);

function primaryRgba(opacity) {
    const hex = (primaryColor.value || '#6FCF00').replace('#', '');
    if (hex.length !== 6) return `rgba(111,207,0,${opacity})`;
    const r = parseInt(hex.substring(0, 2), 16);
    const g = parseInt(hex.substring(2, 4), 16);
    const b = parseInt(hex.substring(4, 6), 16);
    return `rgba(${r},${g},${b},${opacity})`;
}

const memberBase = useMemberBase(computed(() => props.slug));
const basePath = computed(() => `${memberBase.value}/comunidade`);
const postsBase = () => `${basePath.value}/posts`;
const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

// ─── Stories ──────────────────────────────────────────────────────────────────
const stories = ref([]);
const storyViewerOpen = ref(false);
const storyViewerIndex = ref(0);

async function loadStories() {
    try {
        const { data } = await axios.get(`${memberBase.value}/stories`,
            { headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' } });
        stories.value = data.stories ?? [];
    } catch {}
}

function openStory(index) {
    storyViewerIndex.value = index;
    storyViewerOpen.value = true;
}

onMounted(() => { loadStories(); });

function timeAgo(dt) {
    if (!dt) return '';
    const diff = (Date.now() - new Date(dt)) / 1000;
    if (diff < 60) return 'agora';
    if (diff < 3600) return Math.floor(diff / 60) + 'm';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h';
    if (diff < 604800) return Math.floor(diff / 86400) + 'd';
    return new Date(dt).toLocaleDateString('pt-BR', { day: '2-digit', month: 'short' });
}
function getInitials(name) {
    if (!name) return 'A';
    return name.split(/\s+/).map(n => n[0]).slice(0, 2).join('').toUpperCase();
}
function canDeletePost(post) {
    if (props.can_delete_any_post) return true;
    return post.user_id === props.auth_user_id && props.community_users_can_delete_own_posts;
}

function onPostImageChange(e) {
    const f = e.target?.files?.[0]; if (!f) return;
    postForm.image = f;
    postImagePreview.value = URL.createObjectURL(f);
    // limpa vídeo
    postForm.video = null; postForm.video_url = '';
    postVideoPreview.value = '';
    if (postVideoInputRef.value) postVideoInputRef.value.value = '';
    showVideoInput.value = false;
}
function clearPostImage() {
    postForm.image = null; postImagePreview.value = '';
    if (postImageInputRef.value) postImageInputRef.value.value = '';
}
function onPostVideoChange(e) {
    const f = e.target?.files?.[0]; if (!f) return;
    postForm.video = f;
    postVideoPreview.value = URL.createObjectURL(f);
    // limpa imagem
    postForm.image = null; postImagePreview.value = '';
    if (postImageInputRef.value) postImageInputRef.value.value = '';
}
function clearPostVideo() {
    postForm.video = null; postForm.video_url = ''; postVideoPreview.value = '';
    if (postVideoInputRef.value) postVideoInputRef.value.value = '';
    showVideoInput.value = false;
}
function submitPost() {
    postForm.post(`${memberBase.value}/comunidade/posts`, {
        preserveScroll: true, forceFormData: true,
        onSuccess: () => { postForm.reset(); clearPostImage(); clearPostVideo(); showNewPostModal.value = false; },
    });
}
function deletePost(post) {
    if (!confirm('Excluir esta postagem?')) return;
    postMenuOpen.value = null;
    router.delete(`${postsBase()}/${post.id}`, { preserveScroll: true });
}

async function toggleLike(post) {
    const idx = postsList.value.findIndex(p => p.id === post.id);
    if (idx < 0) return;
    likeLoadingId.value = post.id;
    try {
        const url = `${postsBase()}/${post.id}/like`;
        const headers = { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' };
        const { data } = post.user_has_liked
            ? await axios.delete(url, { headers })
            : await axios.post(url, {}, { headers });
        postsList.value[idx] = { ...postsList.value[idx], likes_count: data.likes_count, user_has_liked: data.user_has_liked };
    } finally { likeLoadingId.value = null; }
}

function toggleComments(postId) {
    expandedComments.value[postId] = !expandedComments.value[postId];
}

async function submitComment(post) {
    const content = (commentContentByPost.value[post.id] ?? '').trim();
    if (!content) return;
    const idx = postsList.value.findIndex(p => p.id === post.id);
    if (idx < 0) return;
    commentLoadingId.value = post.id;
    try {
        const { data } = await axios.post(`${postsBase()}/${post.id}/comments`, { content },
            { headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' } });
        postsList.value[idx] = { ...postsList.value[idx], comments: [...(postsList.value[idx].comments ?? []), data.comment] };
        commentContentByPost.value[post.id] = '';
        expandedComments.value[post.id] = true;
    } finally { commentLoadingId.value = null; }
}

function getVideoEmbed(url) {
    if (!url) return null;
    const yt = url.match(/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/);
    if (yt) return `https://www.youtube.com/embed/${yt[1]}`;
    const vim = url.match(/vimeo\.com\/(\d+)/);
    if (vim) return `https://player.vimeo.com/video/${vim[1]}`;
    return /\.(mp4|webm|ogg)(\?.*)?$/i.test(url) ? url : null;
}
function isDirectVideo(url) { return /\.(mp4|webm|ogg)(\?.*)?$/i.test(url ?? ''); }

// Fechar menu ao clicar fora
function closeMenus() { postMenuOpen.value = null; }
</script>

<template>
    <div style="max-width:480px; margin:0 auto;" @click="closeMenus">

        <!-- StoryViewer fullscreen -->
        <StoryViewer v-if="storyViewerOpen && stories.length"
            :stories="stories"
            :start-index="storyViewerIndex"
            :slug="slug"
            :primary-color="primaryColor"
            @close="storyViewerOpen = false; loadStories()" />

        <!-- Stories do admin (bolinhas clicáveis) -->
        <div v-if="stories.length"
            style="overflow-x:auto; scrollbar-width:none; -webkit-overflow-scrolling:touch; padding:12px 16px 10px; display:flex; gap:12px; border-bottom:1px solid var(--ma-border);">
            <div v-for="(s, i) in stories" :key="s.id"
                style="flex-shrink:0; display:flex; flex-direction:column; align-items:center; gap:5px; cursor:pointer;"
                @click="openStory(i)">
                <div style="width:64px; height:64px; border-radius:50%; overflow:hidden; position:relative; display:flex; align-items:center; justify-content:center;"
                    :style="{
                        background: s.image_url || s.video_url ? 'transparent' : s.bg_color,
                        border: `2px solid ${primaryColor}`,
                        padding: '2px',
                    }">
                    <div style="width:100%; height:100%; border-radius:50%; overflow:hidden; border:2px solid var(--ma-bg); background:var(--ma-surface);">
                        <img v-if="s.image_url" :src="s.image_url" style="width:100%;height:100%;object-fit:cover;" />
                        <video v-else-if="s.video_url" :src="s.video_url" style="width:100%;height:100%;object-fit:cover;" muted />
                        <div v-else style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:24px;" :style="{ background: s.bg_color }">✨</div>
                    </div>
                </div>
                <span style="font-size:10px; font-family:'DM Sans',sans-serif; color:var(--ma-text-2); max-width:64px; text-align:center; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                    {{ s.content ? s.content.slice(0,10)+'...' : 'Story' }}
                </span>
            </div>
        </div>

        <!-- Strip de páginas da comunidade (navegação) -->
        <div style="overflow-x:auto; scrollbar-width:none; -webkit-overflow-scrolling:touch; padding:14px 16px 10px; display:flex; gap:14px; border-bottom:1px solid var(--ma-border);">
        </div>


        <!-- ════ FEED ════════════════════════════════════════════════════════ -->
        <div>
            <article v-for="post in postsList" :key="post.id"
                style="background:var(--ma-bg); margin-bottom:2px; border-bottom:1px solid var(--ma-border);">

                <!-- Header: avatar + nome + tempo + menu -->
                <div style="display:flex; align-items:center; padding:12px 14px; gap:10px;">
                    <!-- Avatar clicável → perfil -->
                    <Link :href="`${memberBase}/perfil/${post.user_id}`"
                        style="display:flex; width:38px; height:38px; border-radius:50%; overflow:hidden; flex-shrink:0; align-items:center; justify-content:center; font-size:13px; font-weight:700; text-decoration:none; cursor:pointer;"
                        :style="{ background: primaryColor, color: 'var(--ma-on-primary)' }">
                        <img v-if="post.user?.avatar_url" :src="post.user.avatar_url" style="width:100%;height:100%;object-fit:cover;" />
                        <span v-else>{{ getInitials(post.user?.name) }}</span>
                    </Link>
                    <div style="flex:1; min-width:0;">
                        <!-- Nome + badge de amizade -->
                        <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                            <Link :href="`${memberBase}/perfil/${post.user_id}`"
                                style="font-size:14px; font-weight:600; color:var(--ma-text); font-family:'DM Sans',sans-serif; text-decoration:none; line-height:1.3;">
                                {{ post.user?.name }}
                            </Link>
                            <!-- Não mostra para o próprio usuário -->
                            <template v-if="post.user_id !== auth_user_id">
                                <!-- Amigo aceito -->
                                <span v-if="localFriendshipMap[post.user_id] === 'accepted'"
                                    style="font-size:10px; font-weight:700; padding:2px 8px; border-radius:20px; font-family:'DM Mono',monospace; letter-spacing:0.04em;"
                                    :style="{ background: primaryRgba(0.15), color: primaryColor }">
                                    ✓ Amigo
                                </span>
                                <!-- Pendente -->
                                <span v-else-if="localFriendshipMap[post.user_id] === 'pending'"
                                    style="font-size:10px; font-weight:600; padding:2px 8px; border-radius:20px; background:rgba(255,255,255,0.06); color:var(--ma-text-2); font-family:'DM Mono',monospace;">
                                    Pendente
                                </span>
                                <!-- Sem amizade -->
                                <button v-else type="button"
                                    style="font-size:10px; font-weight:700; padding:2px 10px; border-radius:20px; background:transparent; cursor:pointer; font-family:'DM Mono',monospace; letter-spacing:0.04em; transition:background 0.15s;"
                                    :style="{ border: `1px solid ${primaryRgba(0.4)}`, color: primaryColor }"
                                    @mouseenter="$event.currentTarget.style.background=primaryRgba(0.1)"
                                    @mouseleave="$event.currentTarget.style.background='transparent'"
                                    @click.stop="requestFriendFromFeed(post.user_id)">
                                    + Solicitar amizade
                                </button>
                            </template>
                        </div>
                        <p style="font-size:11px; color:var(--ma-text-2); font-family:'DM Mono',monospace;">{{ timeAgo(post.created_at) }}</p>
                    </div>
                    <!-- Menu 3 pontinhos — visível para todos -->
                    <div style="position:relative;">
                        <button type="button" style="background:none; border:none; cursor:pointer; padding:6px; border-radius:50%; color:var(--ma-text-2);"
                            @click.stop="postMenuOpen = postMenuOpen === post.id ? null : post.id">
                            <MoreHorizontal style="width:20px;height:20px;" />
                        </button>
                        <div v-if="postMenuOpen === post.id"
                            style="position:absolute; right:0; top:32px; background:var(--ma-surface); border:1px solid var(--ma-border); border-radius:12px; padding:6px; min-width:160px; z-index:50; box-shadow:0 8px 24px rgba(0,0,0,0.3);">
                            <!-- Excluir: só para dono ou admin -->
                            <button v-if="canDeletePost(post)" type="button"
                                style="display:flex; align-items:center; gap:8px; width:100%; padding:10px 14px; background:none; border:none; cursor:pointer; border-radius:8px; font-size:14px; color:var(--ma-danger); font-family:'DM Sans',sans-serif;"
                                @mouseenter="$event.currentTarget.style.background='rgba(224,85,85,0.1)'"
                                @mouseleave="$event.currentTarget.style.background='none'"
                                @click.stop="deletePost(post)">
                                <Trash2 style="width:15px;height:15px;" /> Excluir post
                            </button>
                            <!-- Denunciar: para quem não é dono -->
                            <button v-if="post.user_id !== auth_user_id" type="button"
                                :disabled="reportedPostIds.has(post.id)"
                                style="display:flex; align-items:center; gap:8px; width:100%; padding:10px 14px; background:none; border:none; cursor:pointer; border-radius:8px; font-size:14px; font-family:'DM Sans',sans-serif;"
                                :style="{ color: reportedPostIds.has(post.id) ? 'var(--ma-text-2)' : '#F59E0B', opacity: reportedPostIds.has(post.id) ? 0.5 : 1 }"
                                @mouseenter="!reportedPostIds.has(post.id) && ($event.currentTarget.style.background='rgba(245,158,11,0.1)')"
                                @mouseleave="$event.currentTarget.style.background='none'"
                                @click.stop="openReportModal(post.id)">
                                <Flag style="width:15px;height:15px;" />
                                {{ reportedPostIds.has(post.id) ? 'Denunciado' : 'Denunciar' }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- IMAGEM / VÍDEO (full width, quadrado 1:1 estilo Instagram) -->
                <div v-if="post.image_url" style="width:100%; aspect-ratio:1/1; overflow:hidden; line-height:0;">
                    <img :src="post.image_url" style="width:100%; height:100%; object-fit:cover; display:block;" />
                </div>
                <div v-else-if="post.video_url && !isDirectVideo(post.video_url) && getVideoEmbed(post.video_url)"
                    style="position:relative; padding-bottom:56.25%; background:#000; width:100%;">
                    <iframe :src="getVideoEmbed(post.video_url)" style="position:absolute;inset:0;width:100%;height:100%;border:none;" allowfullscreen />
                </div>
                <video v-else-if="post.video_url && isDirectVideo(post.video_url)" :src="post.video_url" controls
                    style="width:100%; max-height:480px; background:#000; display:block;" />

                <!-- Barra de ações (like + comentário) -->
                <div style="display:flex; align-items:center; gap:4px; padding:10px 14px 6px;">
                    <!-- Like -->
                    <button type="button"
                        style="display:flex; align-items:center; gap:1px; background:none; border:none; cursor:pointer; padding:6px 8px; border-radius:8px; transition:transform 0.1s;"
                        :disabled="likeLoadingId === post.id"
                        @click="toggleLike(post)"
                        @mousedown="$event.currentTarget.style.transform='scale(0.85)'"
                        @mouseup="$event.currentTarget.style.transform='scale(1)'">
                        <svg width="24" height="24" viewBox="0 0 24 24"
                            :fill="post.user_has_liked ? 'var(--ma-danger)' : 'none'"
                            :stroke="post.user_has_liked ? 'var(--ma-danger)' : 'var(--ma-text)'"
                            stroke-width="2" style="transition:all 0.2s;">
                            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                        </svg>
                    </button>
                    <!-- Comentário -->
                    <button type="button"
                        style="display:flex; align-items:center; background:none; border:none; cursor:pointer; padding:6px 8px; border-radius:8px;"
                        @click="toggleComments(post.id)">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--ma-text)" stroke-width="2">
                            <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
                        </svg>
                    </button>
                </div>

                <!-- Likes count -->
                <div v-if="post.likes_count > 0" style="padding:0 14px 6px;">
                    <p style="font-size:14px; font-weight:700; color:var(--ma-text); font-family:'DM Sans',sans-serif;">
                        {{ post.likes_count }} {{ post.likes_count === 1 ? 'curtida' : 'curtidas' }}
                    </p>
                </div>

                <!-- Legenda / descrição (abaixo das ações, estilo Instagram) -->
                <div v-if="post.content" style="padding:2px 14px 8px;">
                    <p style="font-size:14px; color:var(--ma-text); font-family:'DM Sans',sans-serif; line-height:1.5;">
                        <span style="font-weight:700; margin-right:6px;">{{ post.user?.name }}</span>
                        <span v-if="!expandedCaption[post.id] && post.content.length > 120">
                            {{ post.content.slice(0, 120) }}<span style="color:var(--ma-text-2); cursor:pointer;" @click="expandedCaption[post.id] = true">... <span style="font-weight:600;">ver mais</span></span>
                        </span>
                        <span v-else>{{ post.content }}</span>
                    </p>
                </div>

                <!-- Preview de comentários (últimos 2, só quando NÃO expandido) -->
                <div v-if="!expandedComments[post.id] && (post.comments ?? []).length > 0" style="padding:2px 14px 6px;">
                    <button v-if="(post.comments ?? []).length > 2" type="button"
                        style="background:none; border:none; cursor:pointer; font-size:13px; color:var(--ma-text-2); font-family:'DM Sans',sans-serif; padding:0; margin-bottom:4px; display:block;"
                        @click="toggleComments(post.id)">
                        Ver todos os {{ (post.comments ?? []).length }} comentários
                    </button>
                    <div v-for="c in (post.comments ?? []).slice(-2)" :key="c.id" style="margin-bottom:4px;">
                        <p style="font-size:13px; color:var(--ma-text); font-family:'DM Sans',sans-serif; line-height:1.5;">
                            <span style="font-weight:700; margin-right:5px;">{{ c.user?.name }}</span>{{ c.content }}
                        </p>
                    </div>
                </div>

                <!-- Comentários expandidos (todos) -->
                <div v-if="expandedComments[post.id]" style="padding:8px 14px; background:var(--ma-surface); border-top:1px solid var(--ma-border);">
                    <div v-for="c in (post.comments ?? [])" :key="c.id" style="display:flex; gap:8px; align-items:flex-start; margin-bottom:12px;">
                        <div style="width:28px; height:28px; border-radius:50%; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:10px; font-weight:700;"
                            :style="{ background: primaryColor, color: 'var(--ma-on-primary)' }">
                            {{ getInitials(c.user?.name) }}
                        </div>
                        <div style="flex:1; background:var(--ma-surface-2); border-radius:14px; padding:8px 12px;">
                            <p style="font-size:12px; font-weight:700; color:var(--ma-text); font-family:'DM Sans',sans-serif;">{{ c.user?.name }}</p>
                            <p style="font-size:13px; color:var(--ma-text-2); font-family:'DM Sans',sans-serif; margin-top:2px; line-height:1.5;">{{ c.content }}</p>
                        </div>
                    </div>
                    <!-- Input comentário (quando expandido) -->
                    <div style="display:flex; align-items:center; gap:10px; margin-top:8px;">
                        <div style="width:28px; height:28px; border-radius:50%; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:10px; font-weight:700;"
                            :style="{ background: primaryColor, color: 'var(--ma-on-primary)' }">
                            ?
                        </div>
                        <div style="flex:1; display:flex; align-items:center; background:var(--ma-bg); border-radius:20px; padding:8px 14px; border:1px solid var(--ma-border); gap:8px;">
                            <input :value="commentContentByPost[post.id] ?? ''" type="text"
                                style="flex:1; background:transparent; border:none; outline:none; font-size:13px; color:var(--ma-text); font-family:'DM Sans',sans-serif;"
                                placeholder="Adicione um comentário..."
                                @input="commentContentByPost[post.id] = $event.target.value"
                                @keydown.enter.prevent="submitComment(post)" />
                            <button type="button"
                                style="background:none; border:none; cursor:pointer; font-size:13px; font-weight:700; font-family:'DM Sans',sans-serif; transition:opacity 0.15s; padding:0;"
                                :style="{ color: primaryColor, opacity: !(commentContentByPost[post.id] ?? '').trim() ? 0.3 : 1 }"
                                :disabled="commentLoadingId === post.id || !(commentContentByPost[post.id] ?? '').trim()"
                                @click="submitComment(post)">
                                Publicar
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Input rápido de comentário (sempre visível abaixo, mesmo sem expandir) -->
                <div v-if="!expandedComments[post.id]" style="display:flex; align-items:center; gap:8px; padding:8px 14px; border-top:1px solid var(--ma-border);">
                    <input :value="commentContentByPost[post.id] ?? ''" type="text"
                        style="flex:1; background:transparent; border:none; border-bottom:1px solid var(--ma-border); outline:none; font-size:13px; color:var(--ma-text-2); font-family:'DM Sans',sans-serif; padding:4px 0;"
                        placeholder="Adicione um comentário..."
                        @input="commentContentByPost[post.id] = $event.target.value"
                        @keydown.enter.prevent="submitComment(post)"
                        @focus="expandedComments[post.id] = true" />
                    <button type="button"
                        style="background:none; border:none; cursor:pointer; font-size:13px; font-weight:700; font-family:'DM Sans',sans-serif;"
                        :style="{ color: primaryColor, opacity: !(commentContentByPost[post.id] ?? '').trim() ? 0.3 : 1 }"
                        :disabled="!(commentContentByPost[post.id] ?? '').trim()"
                        @click="submitComment(post)">
                        →
                    </button>
                </div>

                <!-- Tempo (estilo Instagram: timestamp pequenininho no final) -->
                <p style="font-size:10px; color:var(--ma-text-2); font-family:'DM Mono',monospace; padding:4px 14px 12px; text-transform:uppercase; letter-spacing:0.5px;">
                    {{ timeAgo(post.created_at) }}
                </p>
            </article>

            <!-- Sem posts -->
            <div v-if="!postsList.length" style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:60px 24px;gap:12px;text-align:center;">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--ma-border)" stroke-width="1.5"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
                <p style="color:var(--ma-text-2);font-family:'DM Sans',sans-serif;font-size:15px;">Nenhum post ainda. Seja o primeiro!</p>
            </div>

            <!-- Paginação -->
            <div v-if="posts.prev_page_url || posts.next_page_url" style="display:flex;gap:8px;padding:16px;">
                <a v-if="posts.prev_page_url" :href="posts.prev_page_url" style="flex:1;text-align:center;padding:12px;border-radius:12px;text-decoration:none;font-size:14px;color:var(--ma-text-2);background:var(--ma-surface);border:1px solid var(--ma-border);">← Anterior</a>
                <a v-if="posts.next_page_url" :href="posts.next_page_url" style="flex:1;text-align:center;padding:12px;border-radius:12px;text-decoration:none;font-size:14px;color:var(--ma-text-2);background:var(--ma-surface);border:1px solid var(--ma-border);">Próxima →</a>
            </div>
        </div>
    </div>

    <!-- FAB "Nova publicação" (acima do chat global) -->
    <div v-if="page.can_post"
        style="position:fixed; right:20px; z-index:28; display:flex; flex-direction:column; align-items:flex-end; gap:12px;"
        :style="{ bottom: `calc(${fabBottom} + 64px)`, transition: 'bottom 0.2s ease' }">
        <button type="button"
            style="width:48px; height:48px; border-radius:50%; border:none; cursor:pointer; display:flex; align-items:center; justify-content:center; box-shadow:0 4px 16px rgba(0,0,0,0.35); transition:transform 0.15s;"
            :style="{ background: primaryColor, color: 'var(--ma-on-primary)' }"
            title="Nova publicação"
            @mouseenter="$event.currentTarget.style.transform='scale(1.08)'"
            @mouseleave="$event.currentTarget.style.transform='scale(1)'"
            @click="showNewPostModal = true">
            <PenLine style="width:20px;height:20px;" />
        </button>
    </div>

    <!-- Modal nova publicação -->
    <Teleport to="body">
        <div v-if="showNewPostModal" style="position:fixed;inset:0;z-index:9998;display:flex;align-items:flex-end;justify-content:center;"
            @click.self="showNewPostModal = false">
            <div style="position:absolute;inset:0;background:rgba(0,0,0,0.6);backdrop-filter:blur(4px);" @click="showNewPostModal = false" />
            <div style="position:relative;width:100%;max-width:480px;background:var(--ma-surface);border-radius:20px 20px 0 0;padding:20px 16px 32px;box-shadow:0 -8px 40px rgba(0,0,0,0.4);">
                <!-- Handle bar -->
                <div style="width:36px;height:4px;border-radius:2px;background:var(--ma-border);margin:0 auto 18px;"></div>
                <!-- Header -->
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                    <p style="font-size:16px;font-weight:700;color:var(--ma-text);font-family:'DM Sans',sans-serif;">Nova publicação</p>
                    <button type="button" style="background:none;border:none;cursor:pointer;color:var(--ma-text-2);padding:4px;" @click="showNewPostModal = false">
                        <X style="width:20px;height:20px;" />
                    </button>
                </div>
                <!-- Textarea -->
                <textarea v-model="postForm.content" rows="4"
                    style="width:100%;background:var(--ma-bg);border:1px solid var(--ma-border);border-radius:12px;padding:12px 14px;color:var(--ma-text);font-family:'DM Sans',sans-serif;font-size:15px;resize:none;line-height:1.5;outline:none;box-sizing:border-box;"
                    placeholder="No que você está pensando?" />

                <!-- Preview imagem -->
                <div v-if="postImagePreview" style="margin:10px 0 0; position:relative; display:inline-block;">
                    <img :src="postImagePreview" style="max-height:120px; border-radius:10px; border:1px solid var(--ma-border);" />
                    <button type="button" style="position:absolute; top:-6px; right:-6px; width:22px; height:22px; border-radius:50%; background:var(--ma-danger); border:none; cursor:pointer; display:flex; align-items:center; justify-content:center; color:#fff;" @click="clearPostImage">
                        <X style="width:12px;height:12px;" />
                    </button>
                </div>

                <!-- Upload de vídeo -->
                <div v-if="showVideoInput" style="margin:8px 0 0;">
                    <input ref="postVideoInputRef" type="file" accept="video/mp4,video/webm,video/mov,video/avi,video/mkv" style="display:none;" @change="onPostVideoChange" />
                    <div v-if="postVideoPreview" style="position:relative; border-radius:10px; overflow:hidden; border:1px solid var(--ma-border); max-height:200px;">
                        <video :src="postVideoPreview" controls style="width:100%; max-height:200px; display:block; background:#000;" />
                        <button type="button" style="position:absolute; top:6px; right:6px; width:24px; height:24px; border-radius:50%; background:rgba(0,0,0,0.7); border:none; cursor:pointer; display:flex; align-items:center; justify-content:center; color:#fff;" @click="clearPostVideo">
                            <X style="width:12px;height:12px;" />
                        </button>
                    </div>
                    <button v-else type="button"
                        style="display:flex; align-items:center; gap:8px; width:100%; padding:12px 16px; background:var(--ma-bg); border:1px dashed var(--ma-border); border-radius:10px; cursor:pointer; color:var(--ma-text-2); font-family:'DM Sans',sans-serif; font-size:13px; transition:border-color 0.2s; box-sizing:border-box;"
                        @mouseenter="$event.currentTarget.style.borderColor='var(--ma-primary)'"
                        @mouseleave="$event.currentTarget.style.borderColor='var(--ma-border)'"
                        @click="postVideoInputRef?.click()">
                        <Video style="width:16px;height:16px;flex-shrink:0;" />
                        Clique para selecionar vídeo (MP4, MOV, WebM · max 100 MB)
                    </button>
                </div>

                <!-- Ações -->
                <div style="display:flex; align-items:center; gap:8px; margin-top:14px; padding-top:14px; border-top:1px solid var(--ma-border);">
                    <input ref="postImageInputRef" type="file" accept="image/*" style="display:none;" @change="onPostImageChange" />
                    <button type="button" style="display:flex; align-items:center; gap:5px; background:none; border:none; cursor:pointer; font-size:13px; color:var(--ma-text-2); padding:6px 10px; border-radius:8px;" @click="postImageInputRef?.click()">
                        <Image style="width:16px;height:16px;" /> Foto
                    </button>
                    <button type="button" style="display:flex; align-items:center; gap:5px; background:none; border:none; cursor:pointer; font-size:13px; padding:6px 10px; border-radius:8px;"
                        :style="{ color: (showVideoInput || postVideoPreview) ? primaryColor : 'var(--ma-text-2)' }"
                        @click="showVideoInput = true; $nextTick(() => !postVideoPreview && postVideoInputRef?.click())">
                        <Video style="width:16px;height:16px;" /> Vídeo
                    </button>
                    <button type="button"
                        style="margin-left:auto; padding:10px 24px; border-radius:20px; border:none; cursor:pointer; font-family:'DM Sans',sans-serif; font-size:14px; font-weight:700; color:var(--ma-on-primary); transition:opacity 0.15s;"
                        :style="{ background: primaryColor, opacity: postForm.processing ? 0.6 : 1 }"
                        :disabled="postForm.processing"
                        @click="submitPost">
                        {{ postForm.processing ? 'Publicando...' : 'Publicar' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- Modal de denúncia -->
    <Teleport to="body">
        <div v-if="reportModal.open" style="position:fixed;inset:0;z-index:9999;display:flex;align-items:center;justify-content:center;padding:16px;">
            <div style="position:absolute;inset:0;background:rgba(0,0,0,0.7);" @click="reportModal.open = false" />
            <div style="position:relative;background:var(--ma-surface);border:1px solid var(--ma-border);border-radius:16px;padding:24px;width:100%;max-width:400px;box-shadow:0 20px 60px rgba(0,0,0,0.5);">
                <!-- Sucesso -->
                <div v-if="reportModal.done" style="text-align:center;padding:16px 0;">
                    <div style="font-size:40px;margin-bottom:12px;">✅</div>
                    <p style="font-size:16px;font-weight:700;color:var(--ma-text);margin-bottom:8px;">Denúncia enviada</p>
                    <p style="font-size:13px;color:var(--ma-text-2);margin-bottom:20px;">Nossa equipe irá analisar e tomar as medidas necessárias.</p>
                    <button type="button"
                        style="padding:10px 24px;border-radius:10px;border:none;cursor:pointer;font-size:14px;font-weight:600;font-family:'DM Sans',sans-serif;"
                        :style="{ background: primaryColor, color: 'var(--ma-on-primary)' }"
                        @click="reportModal.open = false">Fechar</button>
                </div>

                <!-- Formulário -->
                <div v-else>
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
                        <p style="font-size:16px;font-weight:700;color:var(--ma-text);font-family:'DM Sans',sans-serif;">Denunciar post</p>
                        <button type="button" style="background:none;border:none;cursor:pointer;color:var(--ma-text-2);padding:4px;" @click="reportModal.open = false">
                            <X style="width:20px;height:20px;" />
                        </button>
                    </div>
                    <p style="font-size:13px;color:var(--ma-text-2);margin-bottom:16px;font-family:'DM Sans',sans-serif;">Por que você está denunciando este post?</p>
                    <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:16px;">
                        <label v-for="r in reportReasons" :key="r.value"
                            style="display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;cursor:pointer;transition:background 0.15s;"
                            :style="{ background: reportModal.reason === r.value ? 'rgba(245,158,11,0.12)' : 'transparent', border: reportModal.reason === r.value ? '1px solid rgba(245,158,11,0.4)' : '1px solid var(--ma-border)' }">
                            <input type="radio" :value="r.value" v-model="reportModal.reason" style="accent-color:#F59E0B;" />
                            <span style="font-size:14px;color:var(--ma-text);font-family:'DM Sans',sans-serif;">{{ r.label }}</span>
                        </label>
                    </div>
                    <textarea v-if="reportModal.reason === 'outro'" v-model="reportModal.notes" placeholder="Descreva o motivo..." rows="3"
                        style="width:100%;border-radius:10px;border:1px solid var(--ma-border);background:var(--ma-bg);color:var(--ma-text);padding:10px 12px;font-size:13px;font-family:'DM Sans',sans-serif;resize:none;margin-bottom:16px;box-sizing:border-box;" />
                    <div style="display:flex;gap:10px;justify-content:flex-end;">
                        <button type="button"
                            style="padding:10px 18px;border-radius:10px;border:1px solid var(--ma-border);background:transparent;cursor:pointer;font-size:14px;color:var(--ma-text-2);font-family:'DM Sans',sans-serif;"
                            @click="reportModal.open = false">Cancelar</button>
                        <button type="button"
                            :disabled="!reportModal.reason || reportModal.loading"
                            style="padding:10px 20px;border-radius:10px;border:none;cursor:pointer;font-size:14px;font-weight:600;font-family:'DM Sans',sans-serif;transition:opacity 0.15s;"
                            :style="{ background: primaryColor, color: 'var(--ma-on-primary)', opacity: (!reportModal.reason || reportModal.loading) ? 0.5 : 1 }"
                            @click="submitReport">
                            {{ reportModal.loading ? 'Enviando...' : 'Enviar denúncia' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
