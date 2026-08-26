<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import MemberAreaAppLayout from '@/Layouts/MemberAreaAppLayout.vue';
import Button from '@/components/ui/Button.vue';
import MemberAreaVideoPlayer from '@/components/MemberAreaVideoPlayer.vue';
import MemberPdfPresentationViewer from '@/components/MemberPdfPresentationViewer.vue';
import MemberPdfReader from '@/components/MemberPdfReader.vue';
import QuizPlayer from '@/components/member-area/QuizPlayer.vue';
import { formatLessonDescription } from '@/lib/utils';
import { useMemberBase } from '@/composables/useMemberBase';

defineOptions({ layout: MemberAreaAppLayout });

const props = defineProps({
    product: { type: Object, required: true },
    config: { type: Object, default: () => ({}) },
    lesson: { type: Object, required: true },
    slug: { type: String, required: true },
    base_url: { type: String, default: '' },
    comments_enabled: { type: Boolean, default: false },
    comments_require_approval: { type: Boolean, default: true },
    lesson_comments: { type: Array, default: () => [] },
});

function normalizePdfFiles(lesson, defaultName = 'Material') {
    const list = Array.isArray(lesson?.content_files) ? lesson.content_files : [];
    const normalized = list
        .map((it) => {
            if (typeof it === 'string') return { url: it, name: defaultName };
            const url = (it?.url ?? '').toString().trim();
            if (!url) return null;
            return { url, name: (it?.name ?? defaultName).toString().trim() || defaultName };
        })
        .filter(Boolean);
    if (normalized.length === 0 && lesson?.content_url) {
        normalized.push({ url: lesson.content_url, name: defaultName });
    }
    return normalized;
}

const memberBase = useMemberBase(computed(() => props.slug));

/** URLs na mesma origem (proxy Laravel) para o pdf.js evitar CORS no R2. */
function pdfPresentationViewerFiles(basePath, lesson, defaultName = 'Apresentação') {
    const norm = normalizePdfFiles(lesson, defaultName);
    return norm.map((f, i) => ({
        ...f,
        url: `${basePath}/aula/${lesson.id}/pdf/${i}`,
    }));
}

const pdfFiles = computed(() => normalizePdfFiles(props.lesson));
const presentationFiles = computed(() =>
    props.lesson?.type === 'pdf_presentation' ? pdfPresentationViewerFiles(memberBase.value, props.lesson) : []
);

function pdfReaderViewerFiles(lesson, defaultName = 'Documento') {
    const norm = normalizePdfFiles(lesson, defaultName);
    return norm.map((f, i) => ({
        ...f,
        url: `${memberBase.value}/aula/${lesson.id}/pdf/${i}`,
    }));
}

const pdfReaderFiles = computed(() =>
    props.lesson?.type === 'pdf_reader' ? pdfReaderViewerFiles(props.lesson) : []
);

const completed = ref(props.lesson.is_completed ?? false);
const commentContent = ref('');
const commentSubmitting = ref(false);
let autoCompleteTimer = null;

function markComplete() {
    if (completed.value) return;
    router.post(`${memberBase.value}/aula/${props.lesson.id}/complete`, {}, {
        preserveScroll: true,
        onSuccess: () => { completed.value = true; },
    });
}

/** Vídeo: marcar concluído ao assistir 80% ou ao terminar. */
function scheduleAutoComplete() {
    if (!props.lesson || completed.value) return;
    if (props.lesson.type !== 'video' || !props.lesson.content_url) return;
    const durationSeconds = Math.max(30, Math.floor((props.lesson.duration_seconds || 60) * 0.8));
    autoCompleteTimer = setTimeout(() => markComplete(), durationSeconds * 1000);
}

/** Link, PDF, texto, etc.: marcar concluído ao exibir. */
function shouldAutoCompleteNonVideo() {
    if (!props.lesson || completed.value) return false;
    const t = props.lesson.type;
    if (t === 'pdf_presentation' || t === 'pdf_reader') return false;
    return t === 'link' || t === 'pdf' || t === 'text' || (t !== 'video' && (props.lesson.content_url || props.lesson.content_text));
}

onMounted(() => {
    if (props.lesson?.is_completed) completed.value = true;
    else if (props.lesson?.type === 'video') scheduleAutoComplete();
    else if (shouldAutoCompleteNonVideo()) setTimeout(() => markComplete(), 500);
});

onUnmounted(() => {
    if (autoCompleteTimer) clearTimeout(autoCompleteTimer);
});

function submitComment() {
    if (!props.comments_enabled || !commentContent.value?.trim()) return;
    commentSubmitting.value = true;
    router.post(`${memberBase.value}/aula/${props.lesson.id}/comments`, { content: commentContent.value.trim() }, {
        preserveScroll: true,
        onFinish: () => { commentSubmitting.value = false; commentContent.value = ''; },
    });
}
function onPdfReaderLastPage() {
    markComplete();
}

function formatCommentDate(iso) {
    if (!iso) return '';
    try {
        const d = new Date(iso);
        return d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    } catch (_) { return iso; }
}
</script>

<template>
    <div style="max-width: 600px; margin: 0 auto;">
        <!-- Back row -->
        <div style="display:flex; align-items:center; gap:12px; padding: 12px 16px 0;">
            <Link :href="`${memberBase}/modulos`"
                style="display:flex; align-items:center; justify-content:center; width:36px; height:36px; border-radius:50%; background:var(--ma-surface); border:1px solid var(--ma-border); color:var(--ma-text-2); text-decoration:none; font-size:18px;">
                ←
            </Link>
            <span style="font-family:'DM Mono',monospace; font-size:11px; text-transform:uppercase; letter-spacing:1.5px; color:var(--ma-text-2);">
                <span v-if="lesson.module">{{ lesson.module.title }}</span>
                <span v-else>aula</span>
            </span>
        </div>

        <!-- Hero / título + thumbnail -->
        <div style="position:relative; width:100%; background: linear-gradient(135deg, var(--ma-surface) 0%, #1A3A2A 50%, var(--ma-surface) 100%); margin-top:12px; min-height:200px; overflow:hidden; display:flex; align-items:center; justify-content:center;">
            <!-- Ícone decorativo -->
            <div style="position:relative; display:flex; align-items:center; justify-content:center;">
                <div style="position:absolute; width:160px; height:160px; border-radius:50%; border:1px solid rgba(111,207,0,0.12);"/>
                <div style="position:absolute; width:120px; height:120px; border-radius:50%; border:1px solid rgba(111,207,0,0.18);"/>
                <div style="width:80px; height:80px; border-radius:50%; display:flex; align-items:center; justify-content:center; background:rgba(111,207,0,0.1); border:1px solid rgba(111,207,0,0.2);">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="var(--ma-primary)" stroke-width="1.5">
                        <polygon v-if="lesson.type === 'video'" points="5 3 19 12 5 21 5 3"/>
                        <path v-else-if="lesson.type === 'text'" stroke-linecap="round" d="M4 6h16M4 12h16M4 18h10"/>
                        <path v-else d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                    </svg>
                </div>
            </div>
            <!-- Fade bottom -->
            <div style="position:absolute; bottom:0; left:0; right:0; height:80px; background:linear-gradient(transparent, var(--ma-bg));"/>
            <!-- Badge tipo -->
            <div style="position:absolute; top:16px; right:16px; font-family:'DM Mono',monospace; font-size:10px; color:var(--ma-text-2); background:rgba(0,0,0,0.3); padding:4px 10px; border-radius:20px; letter-spacing:1px;">
                {{ lesson.type }}
            </div>
        </div>

        <!-- Título e meta -->
        <div style="padding: 0 16px; margin-top:-20px; position:relative; z-index:5;">
            <div style="display:flex; gap:14px; align-items:flex-start;">
                <div style="width:52px; height:52px; border-radius:12px; flex-shrink:0; display:flex; align-items:center; justify-content:center; border:1px solid rgba(111,207,0,0.2); background:rgba(111,207,0,0.1);">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                        <circle v-if="completed" cx="12" cy="12" r="10" :fill="'var(--ma-primary)'" /><path v-if="completed" d="M9 12l2 2 4-4" stroke="#0D1F2D" stroke-width="2" stroke-linecap="round"/>
                        <polygon v-else points="5 3 19 12 5 21 5 3" :fill="'var(--ma-primary)'" />
                    </svg>
                </div>
                <div style="flex:1; min-width:0;">
                    <h1 style="font-family:'DM Sans',sans-serif; font-size:22px; font-weight:700; line-height:1.3; color:var(--ma-text);">{{ lesson.title }}</h1>
                    <p style="font-size:12px; color:var(--ma-text-2); margin-top:4px; font-family:'DM Mono',monospace;">
                        {{ lesson.duration_seconds ? Math.round(lesson.duration_seconds / 60) + ' min' : '' }}
                        <span v-if="completed" style="margin-left:8px;" :style="{ color: 'var(--ma-primary)' }">· concluída ✓</span>
                    </p>
                </div>
            </div>
        </div>

        <!-- Player / conteúdo -->
        <div style="margin: 20px 0; border-radius:14px; overflow:hidden; border:1px solid var(--ma-border); background:var(--ma-surface);">
            <h1 class="text-2xl font-bold" style="display:none;">{{ lesson.title }}</h1>

        <div>
            <template v-if="lesson.type === 'video'">
                <MemberAreaVideoPlayer
                    v-if="lesson.content_url"
                    :src="lesson.content_url"
                    :watermark-enabled="!!lesson.watermark_enabled"
                    :watermark-data="lesson.student ?? null"
                    @ended="markComplete"
                />
                <div
                    v-if="lesson.content_text"
                    class="prose prose-invert max-w-none border-t border-zinc-700 p-6"
                    v-html="formatLessonDescription(lesson.content_text)"
                />
                <div v-if="!lesson.content_url && !lesson.content_text" class="p-8 text-center text-zinc-500">
                    Conteúdo não disponível.
                </div>
            </template>
            <template v-else-if="lesson.type === 'link' && lesson.content_url">
                <div class="p-6">
                    <a :href="lesson.content_url" target="_blank" rel="noopener" class="inline-flex items-center gap-2 text-[var(--ma-primary)] hover:underline">
                        {{ lesson.link_title?.trim() || 'Abrir link externo' }}
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
                    </a>
                </div>
            </template>
            <div v-else-if="lesson.type === 'link' && lesson.content_text" class="prose prose-invert max-w-none border-t border-zinc-700 p-6" v-html="formatLessonDescription(lesson.content_text)" />
            <template v-else-if="lesson.type === 'pdf_presentation' && presentationFiles.length">
                <div class="p-4">
                    <MemberPdfPresentationViewer :files="presentationFiles" />
                </div>
                <div
                    v-if="lesson.content_text"
                    class="prose prose-invert max-w-none border-t border-zinc-700 p-6"
                    v-html="formatLessonDescription(lesson.content_text)"
                />
            </template>
            <template v-else-if="lesson.type === 'pdf_reader' && pdfReaderFiles.length">
                <div class="p-4">
                    <MemberPdfReader
                        :files="pdfReaderFiles"
                        :base-url="memberAreaBaseUrl"
                        :lesson-id="lesson.id"
                        :likes-count="lesson.likes_count ?? 0"
                        :user-liked="!!lesson.user_liked"
                        @last-page-reached="onPdfReaderLastPage"
                    />
                </div>
                <div
                    v-if="lesson.content_text"
                    class="prose prose-invert max-w-none border-t border-zinc-700 p-6"
                    v-html="formatLessonDescription(lesson.content_text)"
                />
            </template>
            <template v-else-if="lesson.type === 'pdf' && pdfFiles.length">
                <div class="p-6">
                    <div class="space-y-2">
                        <a
                            v-for="(f, i) in pdfFiles"
                            :key="`${f.url}-${i}`"
                            :href="f.url"
                            download
                            target="_blank"
                            rel="noopener"
                            class="ma-btn-primary inline-flex w-full items-center justify-center gap-2 rounded-lg px-4 py-2.5 font-medium transition"
                        >
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                            {{ f.name || 'Baixar material' }}
                        </a>
                    </div>
                </div>
            </template>
            <div v-else-if="lesson.type === 'pdf' && lesson.content_text" class="prose prose-invert max-w-none border-t border-zinc-700 p-6" v-html="formatLessonDescription(lesson.content_text)" />
            <template v-else-if="lesson.type === 'text' && lesson.content_text">
                <div class="prose prose-invert max-w-none p-6" v-html="lesson.content_text" />
            </template>
            <template v-else-if="lesson.type === 'quiz'">
                <div class="p-6">
                    <QuizPlayer
                        :lesson="lesson"
                        :slug="slug"
                        @completed="completed = true"
                    />
                </div>
            </template>
            <div v-else class="p-8 text-center text-zinc-500">
                Conteúdo não disponível.
            </div>
        </div>

        </div><!-- fecha div do player -->

        <!-- Barra de ação -->
        <div style="display:flex; align-items:center; justify-content:space-between; padding: 0 16px 20px;">
            <Link :href="`${memberBase}/modulos`"
                style="display:flex; align-items:center; gap:6px; font-size:13px; color:var(--ma-text-2); text-decoration:none; font-family:'DM Sans',sans-serif;">
                ← Trilha
            </Link>
            <button v-if="lesson.type !== 'quiz'" type="button"
                style="padding:12px 24px; border-radius:12px; border:none; cursor:pointer; font-family:'DM Sans',sans-serif; font-size:14px; font-weight:600; transition:all 0.2s;"
                :style="completed ? { background: 'rgba(111,207,0,0.12)', color: 'var(--ma-primary)', border: '1px solid rgba(111,207,0,0.3)' } : { background: 'var(--ma-primary)', color: '#0D1F2D' }"
                :disabled="completed"
                @click="markComplete">
                {{ completed ? '✓ Concluída' : 'Marcar como concluída' }}
            </button>
            <span v-else-if="completed" style="font-size:14px; font-weight:600; font-family:'DM Sans',sans-serif;" :style="{ color: 'var(--ma-primary)' }">✓ Quiz concluído</span>
        </div>

        <!-- Comentários da aula -->
        <section v-if="comments_enabled" style="margin:0 16px 20px; border-radius:14px; padding:16px; background:var(--ma-surface); border:1px solid var(--ma-border);" class="space-y-4">
            <h2 class="text-lg font-semibold">Comentários</h2>
            <ul class="space-y-3">
                <li v-for="c in lesson_comments" :key="c.id" class="flex gap-3 border-b border-zinc-700/50 pb-3 last:border-0 last:pb-0">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-full bg-[var(--ma-primary)]/20 text-sm font-semibold text-[var(--ma-primary)]">
                        <img v-if="c.user?.avatar_url" :src="c.user.avatar_url" :alt="c.user.name" class="h-full w-full object-cover" />
                        <span v-else>{{ (c.user?.name ?? 'A').split(/\s+/).map(n => n[0]).slice(0, 2).join('').toUpperCase() || 'A' }}</span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-zinc-300">{{ c.user?.name ?? 'Aluno' }}</p>
                        <p class="text-sm text-zinc-400 mt-0.5">{{ c.content }}</p>
                        <p class="text-xs text-zinc-500 mt-1">{{ formatCommentDate(c.created_at) }}</p>
                    </div>
                </li>
            </ul>
            <p v-if="!lesson_comments?.length" class="text-sm text-zinc-500">Nenhum comentário ainda.</p>
            <form @submit.prevent="submitComment" class="space-y-2">
                <textarea
                    v-model="commentContent"
                    rows="3"
                    class="w-full rounded-lg border border-zinc-600 bg-zinc-800 px-3 py-2 text-sm text-white placeholder-zinc-500 focus:border-[var(--ma-primary)] focus:ring-1 focus:ring-[var(--ma-primary)]"
                    placeholder="Escreva um comentário..."
                    maxlength="2000"
                />
                <Button type="submit" :disabled="commentSubmitting || !commentContent?.trim()">
                    {{ commentSubmitting ? 'Enviando…' : 'Enviar comentário' }}
                </Button>
            </form>
            <p v-if="comments_require_approval" class="text-xs text-zinc-500">Seus comentários serão publicados após aprovação do instrutor.</p>
        </section>
    </div>
</template>
