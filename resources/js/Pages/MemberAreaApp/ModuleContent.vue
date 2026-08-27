<script setup>
import { ref, onMounted, onUnmounted, computed, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import MemberAreaAppLayout from '@/Layouts/MemberAreaAppLayout.vue';
import Button from '@/components/ui/Button.vue';
import MemberAreaVideoPlayer from '@/components/MemberAreaVideoPlayer.vue';
import MemberPdfPresentationViewer from '@/components/MemberPdfPresentationViewer.vue';
import MemberPdfReader from '@/components/MemberPdfReader.vue';
import QuizPlayer from '@/components/member-area/QuizPlayer.vue';
import { formatLessonDescription } from '@/lib/utils';
import { Link as LinkIcon, CheckCircle, ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { useMemberBase } from '@/composables/useMemberBase';

defineOptions({ layout: MemberAreaAppLayout });

const props = defineProps({
    product: { type: Object, required: true },
    config: { type: Object, default: () => ({}) },
    slug: { type: String, required: true },
    module: { type: Object, required: true },
    lessons: { type: Array, default: () => [] },
    current_lesson: { type: Object, default: null },
    progress_percent: { type: Number, default: 0 },
    sections: { type: Array, default: () => [] },
    comments_enabled: { type: Boolean, default: false },
    comments_require_approval: { type: Boolean, default: true },
    lesson_comments: { type: Array, default: () => [] },
    base_url: { type: String, default: '' },
    course_lesson_progress: {
        type: Object,
        default: () => ({ completed: 0, total: 0 }),
    },
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

const currentPdfFiles = computed(() => normalizePdfFiles(props.current_lesson));
const currentPresentationFiles = computed(() =>
    props.current_lesson?.type === 'pdf_presentation'
        ? pdfPresentationViewerFiles(memberBase.value, props.current_lesson)
        : []
);

/** Proxy same-origin URLs para o leitor PDF (usa base_url do backend). */
function pdfReaderViewerFiles(lesson, defaultName = 'Documento') {
    const norm = normalizePdfFiles(lesson, defaultName);
    return norm.map((f, i) => ({
        ...f,
        url: `${memberBase.value}/aula/${lesson.id}/pdf/${i}`,
    }));
}

const currentPdfReaderFiles = computed(() =>
    props.current_lesson?.type === 'pdf_reader'
        ? pdfReaderViewerFiles(props.current_lesson)
        : []
);

const lessonSidebarQuery = ref('');
const filteredLessons = computed(() => {
    const q = lessonSidebarQuery.value.trim().toLowerCase();
    const list = props.lessons || [];
    if (!q) return list;
    return list.filter((l) => (l.title || '').toLowerCase().includes(q));
});

const courseProgress = computed(() => props.course_lesson_progress || { completed: 0, total: 0 });

const completedLessonIds = ref(new Set());
const completed = ref(props.current_lesson?.is_completed ?? false);
let autoCompleteTimer = null;

const isLessonCompleted = (lesson) => lesson.is_completed || completedLessonIds.value.has(lesson.id);

function lessonUrl(lessonId) {
    return `${memberBase.value}/modulo/${props.module.id}?aula=${lessonId}`;
}

function markComplete() {
    if (!props.current_lesson || completed.value) return;
    router.post(`${memberBase.value}/aula/${props.current_lesson.id}/complete`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            completed.value = true;
            completedLessonIds.value.add(props.current_lesson.id);
        },
    });
}

/** Vídeo: marcar concluído automaticamente após 80% do tempo assistido. */
function scheduleAutoComplete() {
    if (!props.current_lesson || completed.value) return;
    if (props.current_lesson.type !== 'video' || !props.current_lesson.content_url) return;
    const durationSeconds = Math.max(30, Math.floor((props.current_lesson.duration_seconds || 60) * 0.8));
    autoCompleteTimer = setTimeout(() => markComplete(), durationSeconds * 1000);
}

/** Aulas que não são vídeo (link, pdf, texto, etc.): marcar concluído ao exibir. */
function shouldAutoCompleteNonVideo() {
    if (!props.current_lesson || completed.value) return false;
    const t = props.current_lesson.type;
    if (t === 'pdf_presentation' || t === 'pdf_reader') return false;
    return t === 'link' || t === 'pdf' || t === 'text' || (t !== 'video' && (props.current_lesson.content_url || props.current_lesson.content_text));
}

onMounted(() => {
    if (props.current_lesson?.is_completed) completed.value = true;
    else if (props.current_lesson?.type === 'video') scheduleAutoComplete();
    else if (shouldAutoCompleteNonVideo()) setTimeout(() => markComplete(), 500);
});

onUnmounted(() => {
    if (autoCompleteTimer) clearTimeout(autoCompleteTimer);
});

const localComments = ref([...(props.lesson_comments ?? [])]);
watch(() => props.lesson_comments, v => { localComments.value = [...(v ?? [])]; });

const commentContent = ref('');
const commentSubmitting = ref(false);
const commentError = ref('');

async function submitComment() {
    if (!props.current_lesson || !commentContent.value?.trim()) return;
    commentSubmitting.value = true;
    commentError.value = '';
    const text = commentContent.value.trim();
    try {
        // Usa window.axios (bootstrap Laravel) que já tem CSRF configurado
        await window.axios.post(
            `${memberBase.value}/aula/${props.current_lesson.id}/comments`,
            { content: text }
        );
        localComments.value.push({
            id: Date.now(),
            content: text,
            user: { name: 'Você' },
            created_at: new Date().toISOString(),
        });
        commentContent.value = '';
    } catch (e) {
        const msg = e.response?.data?.message ?? e.response?.data?.errors?.content?.[0] ?? 'Erro ao enviar comentário.';
        commentError.value = msg;
    } finally {
        commentSubmitting.value = false;
    }
}
function formatCommentDate(iso) {
    if (!iso) return '';
    try {
        const d = new Date(iso);
        return d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    } catch (_) { return iso; }
}

// Carrossel "Outros módulos": sem scrollbar, setas só quando há overflow
const carouselRefs = ref({});
const carouselHasOverflow = ref({});

function checkCarouselOverflow(sectionId) {
    const el = carouselRefs.value[sectionId];
    if (!el || typeof el.scrollWidth !== 'number') return;
    const hasOverflow = el.scrollWidth > el.clientWidth;
    if (carouselHasOverflow.value[sectionId] === hasOverflow) return;
    carouselHasOverflow.value = { ...carouselHasOverflow.value, [sectionId]: hasOverflow };
}

function setCarouselRef(sectionId, el) {
    if (el) {
        carouselRefs.value[sectionId] = el;
        setTimeout(() => checkCarouselOverflow(sectionId), 0);
    } else {
        carouselRefs.value[sectionId] = null;
        if (carouselHasOverflow.value[sectionId] !== false) {
            carouselHasOverflow.value = { ...carouselHasOverflow.value, [sectionId]: false };
        }
    }
}

function scrollCarousel(sectionId, direction) {
    const el = carouselRefs.value[sectionId];
    if (!el) return;
    el.scrollBy({ left: 272 * direction, behavior: 'smooth' });
}

function onPdfReaderLastPage() {
    markComplete();
}
</script>

<template>
    <div style="max-width: 480px; margin: 0 auto; padding: 0 0 20px;">

        <!-- ═══ LISTA DE AULAS (sem aula selecionada) ═══════════════════════════ -->
        <template v-if="!current_lesson">
            <!-- Back row -->
            <div style="display:flex; align-items:center; gap:12px; padding:12px 16px 0;">
                <Link :href="`${memberBase}/modulos`"
                    style="display:flex; align-items:center; justify-content:center; width:36px; height:36px; border-radius:50%; background:var(--ma-surface); border:1px solid var(--ma-border); color:var(--ma-text-2); text-decoration:none; font-size:18px; line-height:1;">
                    ←
                </Link>
                <span style="font-family:'DM Mono',monospace; font-size:11px; text-transform:uppercase; letter-spacing:1.5px;" :style="{ color: 'var(--ma-primary)' }">desbloqueado</span>
            </div>

            <!-- Título do módulo -->
            <div style="padding:20px 16px 24px;">
                <h1 style="font-family:'DM Sans',sans-serif; font-size:28px; font-weight:800; line-height:1.2; color:var(--ma-text);">{{ module.title }}</h1>
                <p v-if="module.description" style="font-size:14px; color:var(--ma-text-2); margin-top:8px; line-height:1.6; font-family:'DM Sans',sans-serif;">{{ module.description }}</p>
                <p v-else style="font-size:13px; color:var(--ma-text-2); margin-top:8px; font-family:'DM Sans',sans-serif;">{{ lessons.length }} aula{{ lessons.length !== 1 ? 's' : '' }} · explore no seu ritmo.</p>
            </div>

            <!-- Lista de aulas (estilo wireframe) -->
            <div style="display:flex; flex-direction:column; gap:8px; padding:0 16px;">
                <template v-for="(lesson, idx) in lessons" :key="lesson.id">
                    <Link v-if="!lesson.is_locked"
                        :href="lessonUrl(lesson.id)"
                        style="display:flex; align-items:center; gap:14px; padding:16px 20px; background:var(--ma-surface); border:1px solid var(--ma-border); border-radius:12px; text-decoration:none; transition:border-color 0.2s;"
                        @mouseenter="$event.currentTarget.style.borderColor='var(--ma-border-active, #6FCF00)'"
                        @mouseleave="$event.currentTarget.style.borderColor='var(--ma-border)'">
                        <!-- Número / check -->
                        <div style="width:36px; height:36px; border-radius:50%; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-family:'DM Mono',monospace; font-size:13px;"
                            :style="isLessonCompleted(lesson)
                                ? { background: 'var(--ma-primary)', color: '#0D1F2D' }
                                : { background: 'var(--ma-surface-2)', border: '1px solid var(--ma-border)', color: 'var(--ma-text-2)' }">
                            {{ isLessonCompleted(lesson) ? '✓' : String(idx + 1).padStart(2, '0') }}
                        </div>
                        <!-- Info -->
                        <div style="flex:1; min-width:0;">
                            <p style="font-size:15px; font-weight:500; color:var(--ma-text); font-family:'DM Sans',sans-serif; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ lesson.title || 'Sem título' }}</p>
                            <p style="font-size:12px; color:var(--ma-text-2); margin-top:3px; font-family:'DM Mono',monospace;">
                                <span v-if="lesson.duration_seconds">🎧 {{ Math.round(lesson.duration_seconds / 60) }} min</span>
                                <span v-if="lesson.type === 'video'">🎥</span>
                                <span v-if="lesson.type === 'pdf' || lesson.type === 'pdf_reader' || lesson.type === 'pdf_presentation'">📄</span>
                                <span v-if="lesson.type === 'text'">📝</span>
                                <span v-if="lesson.type === 'quiz'">📊 quiz</span>
                                <span v-if="lesson.type === 'link'">🔗</span>
                            </p>
                        </div>
                        <!-- Seta -->
                        <span style="color:var(--ma-text-2); font-size:18px; flex-shrink:0;">→</span>
                    </Link>

                    <!-- Aula bloqueada -->
                    <div v-else
                        style="display:flex; align-items:center; gap:14px; padding:16px 20px; background:var(--ma-surface); border:1px solid var(--ma-border); border-radius:12px; opacity:0.4; cursor:default;">
                        <div style="width:36px; height:36px; border-radius:50%; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-family:'DM Mono',monospace; font-size:13px; background:var(--ma-surface-2); border:1px solid var(--ma-border); color:var(--ma-text-2);">
                            {{ String(idx + 1).padStart(2, '0') }}
                        </div>
                        <div style="flex:1; min-width:0;">
                            <p style="font-size:15px; font-weight:500; color:var(--ma-text-2); font-family:'DM Sans',sans-serif;">{{ lesson.title || 'Sem título' }}</p>
                            <p v-if="lesson.lock_message" style="font-size:11px; color:var(--ma-text-2); margin-top:3px; font-family:'DM Mono',monospace;">{{ lesson.lock_message }}</p>
                        </div>
                        <span style="font-size:16px; opacity:0.4;">🔒</span>
                    </div>
                </template>

                <div v-if="!lessons.length" style="text-align:center; padding:48px 24px; color:var(--ma-text-2); font-family:'DM Sans',sans-serif;">
                    Nenhuma aula neste módulo.
                </div>
            </div>
        </template>

        <!-- ═══ CONTEÚDO DA AULA (aula selecionada) ═════════════════════════════ -->
        <template v-if="current_lesson">
            <!-- Back row -->
            <div style="display:flex; align-items:center; gap:12px; padding:12px 16px 0;">
                <Link :href="`${memberBase}/modulo/${module.id}`"
                    style="display:flex; align-items:center; justify-content:center; width:36px; height:36px; border-radius:50%; background:var(--ma-surface); border:1px solid var(--ma-border); color:var(--ma-text-2); text-decoration:none; font-size:18px; line-height:1;">
                    ←
                </Link>
                <span style="font-family:'DM Mono',monospace; font-size:11px; text-transform:uppercase; letter-spacing:1.5px; color:var(--ma-text-2);">{{ module.title }}</span>
            </div>

            <!-- Título da aula -->
            <div style="padding:0 16px; margin-top:16px; position:relative; z-index:5;">
                <div style="display:flex; gap:14px; align-items:flex-start;">
                    <div style="width:52px; height:52px; border-radius:12px; flex-shrink:0; display:flex; align-items:center; justify-content:center; border:1px solid rgba(111,207,0,0.2); background:rgba(111,207,0,0.1);">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                            <circle v-if="completed || current_lesson.is_completed" cx="12" cy="12" r="10" fill="var(--ma-primary)"/>
                            <path v-if="completed || current_lesson.is_completed" d="M9 12l2 2 4-4" stroke="#0D1F2D" stroke-width="2" stroke-linecap="round"/>
                            <polygon v-else points="5 3 19 12 5 21 5 3" fill="var(--ma-primary)"/>
                        </svg>
                    </div>
                    <div style="flex:1; min-width:0;">
                        <h1 style="font-family:'DM Sans',sans-serif; font-size:22px; font-weight:700; line-height:1.3; color:var(--ma-text);">{{ current_lesson.title }}</h1>
                        <p style="font-size:12px; color:var(--ma-text-2); margin-top:4px; font-family:'DM Mono',monospace;">
                            {{ current_lesson.duration_seconds ? Math.round(current_lesson.duration_seconds / 60) + ' min' : '' }}
                            <span v-if="completed" style="margin-left:8px;" :style="{ color: 'var(--ma-primary)' }">· concluída ✓</span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Conteúdo da aula -->
            <div style="margin:20px 16px 0; border-radius:14px; overflow:hidden; border:1px solid var(--ma-border); background:var(--ma-surface);">
                <template v-if="current_lesson">
                <div class="rounded-xl border border-zinc-700 bg-zinc-800/50 overflow-hidden">
                    <template v-if="current_lesson.type === 'video'">
                        <MemberAreaVideoPlayer
                            v-if="current_lesson.content_url"
                            :src="current_lesson.content_url"
                            :telemetry-id="current_lesson.id"
                            :telemetry-base="memberBase"
                            @ended="markComplete"
                        />
                        <div
                            v-if="current_lesson.content_text"
                            class="prose prose-invert max-w-none border-t border-zinc-700 p-6"
                            v-html="formatLessonDescription(current_lesson.content_text)"
                        />
                        <div v-if="!current_lesson.content_url && !current_lesson.content_text" class="p-8 text-center text-zinc-500">
                            Conteúdo não disponível.
                        </div>
                    </template>
                    <template v-else-if="current_lesson.type === 'link' && current_lesson.content_url">
                        <div class="p-6">
                            <a :href="current_lesson.content_url" target="_blank" rel="noopener" class="inline-flex items-center gap-2 text-[var(--ma-primary)] hover:underline">
                                {{ current_lesson.link_title?.trim() || 'Abrir link externo' }}
                                <LinkIcon class="h-4 w-4" />
                            </a>
                        </div>
                    </template>
                    <div v-else-if="current_lesson.type === 'link' && current_lesson.content_text" class="prose prose-invert max-w-none border-t border-zinc-700 p-6" v-html="formatLessonDescription(current_lesson.content_text)" />
                    <template v-else-if="current_lesson.type === 'pdf_presentation' && currentPresentationFiles.length">
                        <div class="p-4">
                            <MemberPdfPresentationViewer :files="currentPresentationFiles" />
                        </div>
                        <div
                            v-if="current_lesson.content_text"
                            class="prose prose-invert max-w-none border-t border-zinc-700 p-6"
                            v-html="formatLessonDescription(current_lesson.content_text)"
                        />
                    </template>
                    <template v-else-if="current_lesson.type === 'pdf_reader' && currentPdfReaderFiles.length">
                        <div class="p-4">
                            <MemberPdfReader
                                :key="current_lesson.id"
                                :files="currentPdfReaderFiles"
                                :base-url="memberAreaBaseUrl"
                                :lesson-id="current_lesson.id"
                                :likes-count="current_lesson.likes_count ?? 0"
                                :user-liked="!!current_lesson.user_liked"
                                @last-page-reached="onPdfReaderLastPage"
                            />
                        </div>
                        <div
                            v-if="current_lesson.content_text"
                            class="prose prose-invert max-w-none border-t border-zinc-700 p-6"
                            v-html="formatLessonDescription(current_lesson.content_text)"
                        />
                    </template>
                    <template v-else-if="current_lesson.type === 'pdf' && currentPdfFiles.length">
                        <div class="p-6">
                            <div class="space-y-2">
                                <a
                                    v-for="(f, i) in currentPdfFiles"
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
                    <div v-else-if="current_lesson.type === 'pdf' && current_lesson.content_text" class="prose prose-invert max-w-none border-t border-zinc-700 p-6" v-html="formatLessonDescription(current_lesson.content_text)" />
                    <template v-else-if="current_lesson.type === 'text' && current_lesson.content_text">
                        <div class="prose prose-invert max-w-none p-6" v-html="current_lesson.content_text" />
                    </template>
                    <template v-else-if="current_lesson.type === 'quiz'">
                        <div class="p-6">
                            <QuizPlayer
                                :lesson="current_lesson"
                                :slug="slug"
                                @completed="completed = true"
                            />
                        </div>
                    </template>
                    <template v-else>
                        <div class="p-8 text-center text-zinc-500">Conteúdo não disponível.</div>
                    </template>
                </div>

                </template><!-- fecha template v-if="current_lesson" interno -->
            </div><!-- fecha div do player -->

            <!-- Botão concluir + próxima aula -->
            <div style="padding:16px;">
                <button v-if="current_lesson.type !== 'quiz'" type="button"
                    style="width:100%; padding:16px; border-radius:14px; border:none; font-family:'DM Sans',sans-serif; font-size:15px; font-weight:600; cursor:pointer; transition:all 0.2s; margin-bottom:12px;"
                    :style="(completed || current_lesson.is_completed)
                        ? { background: 'rgba(111,207,0,0.12)', color: 'var(--ma-primary)', border: '1px solid rgba(111,207,0,0.3)' }
                        : { background: 'var(--ma-primary)', color: '#0D1F2D' }"
                    :disabled="completed || current_lesson.is_completed"
                    @click="markComplete">
                    {{ (completed || current_lesson.is_completed) ? '✓ Aula concluída' : 'Marcar como concluída →' }}
                </button>

                <!-- Próxima aula -->
                <template v-if="lessons.length > 1">
                    <div style="margin-top:4px;">
                        <template v-for="(l, i) in lessons" :key="l.id">
                            <Link v-if="l.id === current_lesson.id && lessons[i+1] && !lessons[i+1].is_locked"
                                :href="lessonUrl(lessons[i+1].id)"
                                style="display:flex; align-items:center; gap:14px; padding:14px 16px; background:var(--ma-surface); border:1px solid var(--ma-border); border-radius:14px; text-decoration:none; transition:border-color 0.2s;"
                                @mouseenter="$event.currentTarget.style.borderColor='var(--ma-primary)'"
                                @mouseleave="$event.currentTarget.style.borderColor='var(--ma-border)'">
                                <div style="width:40px; height:40px; border-radius:10px; background:var(--ma-surface-2); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                                    <span style="font-family:'DM Mono',monospace; font-size:11px; color:var(--ma-text-2);">{{ String(i+2).padStart(2,'0') }}</span>
                                </div>
                                <div style="flex:1; min-width:0;">
                                    <p style="font-size:11px; font-family:'DM Mono',monospace; text-transform:uppercase; letter-spacing:1.5px; margin-bottom:2px;" :style="{ color: 'var(--ma-primary)' }">próxima aula</p>
                                    <p style="font-size:14px; font-weight:600; color:var(--ma-text); font-family:'DM Sans',sans-serif; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ lessons[i+1].title }}</p>
                                    <p v-if="lessons[i+1].duration_seconds" style="font-size:11px; color:var(--ma-text-2); margin-top:2px; font-family:'DM Mono',monospace;">🎧 {{ Math.round(lessons[i+1].duration_seconds/60) }} min</p>
                                </div>
                                <span style="color:var(--ma-text-2);">→</span>
                            </Link>
                        </template>
                    </div>
                </template>
            </div>

            <!-- Comentários -->
            <section v-if="comments_enabled" style="margin:0 16px 16px; padding:16px; background:var(--ma-surface); border-radius:14px; border:1px solid var(--ma-border);">
                <h2 style="font-family:'DM Sans',sans-serif; font-size:16px; font-weight:600; color:var(--ma-text); margin-bottom:16px;">Comentários</h2>
                <div v-for="c in localComments" :key="c.id" style="display:flex; gap:10px; margin-bottom:12px;">
                    <div style="width:32px; height:32px; border-radius:50%; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; background:var(--ma-primary); color:#0D1F2D;">
                        {{ (c.user?.name ?? 'A').split(/\s+/).map(n=>n[0]).slice(0,2).join('').toUpperCase() || 'A' }}
                    </div>
                    <div style="flex:1; background:var(--ma-surface-2); border-radius:12px; padding:10px 14px;">
                        <p style="font-size:13px; font-weight:600; color:var(--ma-text); font-family:'DM Sans',sans-serif;">{{ c.user?.name ?? 'Aluno' }}</p>
                        <p style="font-size:13px; color:var(--ma-text-2); margin-top:4px; font-family:'DM Sans',sans-serif;">{{ c.content }}</p>
                    </div>
                </div>
                <p v-if="!localComments.length" style="font-size:13px; color:var(--ma-text-2); font-family:'DM Sans',sans-serif; margin-bottom:12px;">Nenhum comentário ainda.</p>
                <p v-if="commentError" style="font-size:12px; color:#E05555; margin-bottom:8px; font-family:'DM Sans',sans-serif;">{{ commentError }}</p>
                <form @submit.prevent="submitComment" style="display:flex; gap:8px; margin-top:12px;">
                    <textarea v-model="commentContent" rows="2" maxlength="2000"
                        style="flex:1; background:var(--ma-bg); border:1px solid var(--ma-border); border-radius:12px; padding:10px 14px; color:var(--ma-text); font-family:'DM Sans',sans-serif; font-size:13px; resize:none; outline:none;"
                        placeholder="Escreva um comentário..." />
                    <button type="submit" :disabled="commentSubmitting || !commentContent?.trim()"
                        style="padding:0 18px; border-radius:12px; border:none; font-family:'DM Sans',sans-serif; font-size:13px; font-weight:600; cursor:pointer; background:var(--ma-primary); color:#0D1F2D; flex-shrink:0;">
                        {{ commentSubmitting ? '…' : '→' }}
                    </button>
                </form>
            </section>
        </template><!-- fecha template v-if="current_lesson" externo -->

    </div>
</template>
