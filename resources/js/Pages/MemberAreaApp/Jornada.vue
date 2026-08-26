<script setup>
import { ref, computed } from 'vue';
import MemberAreaAppLayout from '@/Layouts/MemberAreaAppLayout.vue';
import { useMemberBase } from '@/composables/useMemberBase';
import {
    ChevronLeft, PlayCircle, HelpCircle, MessageSquare,
    CheckCircle2, Lock, ChevronDown, ChevronUp, Loader2,
} from 'lucide-vue-next';

defineOptions({ layout: MemberAreaAppLayout });

const props = defineProps({
    product:  { type: Object, required: true },
    journey:  { type: Object, required: true },
    steps:    { type: Array, default: () => [] },
    base_url: { type: String, default: '' },
    slug:     { type: String, default: '' },
});

const memberBase = useMemberBase(computed(() => props.slug));

// Local mutable state for steps/items so we can update after submission
const localSteps = ref(
    props.steps.map((s) => ({
        ...s,
        expanded: !s.completed || s.done_items < s.total_items,
        items: s.items.map((item) => ({
            ...item,
            localCompleted: item.completed,
            localIsCorrect: item.is_correct,
            localAnswer:    item.answer,
            submitting:     false,
            error:          null,
            showResult:     item.completed,
            // quiz
            selectedOption: item.answer?.option_index ?? null,
            // question
            textAnswer:     item.answer?.text ?? '',
        })),
    }))
);

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

// Convert YouTube / Vimeo URL → embed URL
function embedUrl(url) {
    if (!url) return null;
    let m = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/);
    if (m) return `https://www.youtube.com/embed/${m[1]}?rel=0`;
    m = url.match(/vimeo\.com\/(\d+)/);
    if (m) return `https://player.vimeo.com/video/${m[1]}`;
    return null;
}

async function submitItem(stepIdx, itemIdx, answer) {
    const step = localSteps.value[stepIdx];
    const item = step.items[itemIdx];
    if (item.submitting) return;

    item.submitting = true;
    item.error      = null;

    try {
        const res = await window.axios.post(
            `${memberBase.value}/jornadas/${props.journey.id}/etapas/${step.id}/items/${item.id}/responder`,
            { answer },
            { headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' } }
        );

        item.localCompleted = true;
        item.localIsCorrect = res.data.is_correct;
        item.showResult     = true;

        step.done_items  = res.data.done_items;
        step.total_items = res.data.total_items;

        if (res.data.step_completed) {
            step.completed = true;
            // Unlock the next sequential step reactively without requiring a reload
            const nextStep = localSteps.value[stepIdx + 1];
            if (nextStep && !nextStep.unlocked) {
                nextStep.unlocked = true;
                nextStep.expanded = true;
            }
        }
    } catch (e) {
        item.error = e.response?.data?.message ?? 'Erro ao salvar. Tente novamente.';
    } finally {
        item.submitting = false;
    }
}

function watchVideo(stepIdx, itemIdx) {
    submitItem(stepIdx, itemIdx, {});
}

function submitQuiz(stepIdx, itemIdx) {
    const item = localSteps.value[stepIdx].items[itemIdx];
    if (item.selectedOption === null) return;
    submitItem(stepIdx, itemIdx, { option_index: item.selectedOption });
}

function submitQuestion(stepIdx, itemIdx) {
    const item = localSteps.value[stepIdx].items[itemIdx];
    if (!item.textAnswer.trim()) return;
    submitItem(stepIdx, itemIdx, { text: item.textAnswer.trim() });
}

const typeIcon  = { video: PlayCircle, quiz: HelpCircle, question: MessageSquare };
const typeLabel = { video: 'Vídeo', quiz: 'Quiz', question: 'Pergunta aberta' };
const typeColor = { video: 'text-emerald-500', quiz: 'text-purple-500', question: 'text-sky-500' };
const typeBg    = { video: 'bg-emerald-50 dark:bg-emerald-900/20', quiz: 'bg-purple-50 dark:bg-purple-900/20', question: 'bg-sky-50 dark:bg-sky-900/20' };
const typeBorder= { video: 'border-emerald-200 dark:border-emerald-800/40', quiz: 'border-purple-200 dark:border-purple-800/40', question: 'border-sky-200 dark:border-sky-800/40' };
</script>

<template>
    <div class="mx-auto max-w-2xl space-y-6 px-4 py-8">
        <!-- Voltar -->
        <a :href="`${memberBase}/jornadas`"
            class="inline-flex items-center gap-1 text-sm font-medium text-zinc-500 hover:text-indigo-600 dark:hover:text-indigo-400">
            <ChevronLeft class="h-4 w-4" /> Todas as jornadas
        </a>

        <!-- Hero -->
        <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-800/60">
            <div v-if="journey.cover_url" class="h-40 w-full overflow-hidden">
                <img :src="journey.cover_url" :alt="journey.name" class="h-full w-full object-cover" />
            </div>
            <div class="p-5">
                <h1 class="text-xl font-bold text-zinc-900 dark:text-white">{{ journey.name }}</h1>
                <p v-if="journey.description" class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ journey.description }}</p>
                <p class="mt-3 text-xs text-zinc-400 dark:text-zinc-500">
                    {{ localSteps.length }} etapa{{ localSteps.length !== 1 ? 's' : '' }} nesta jornada
                </p>
            </div>
        </div>

        <!-- Sem etapas -->
        <div v-if="localSteps.length === 0"
            class="flex flex-col items-center gap-3 rounded-2xl border-2 border-dashed border-zinc-200 py-16 text-center dark:border-zinc-700">
            <MessageSquare class="h-10 w-10 text-zinc-300 dark:text-zinc-600" />
            <p class="text-sm text-zinc-400 dark:text-zinc-500">Nenhuma etapa disponível nesta jornada ainda.</p>
        </div>

        <!-- Etapas -->
        <div class="space-y-4">
            <div v-for="(step, sIdx) in localSteps" :key="step.id"
                class="overflow-hidden rounded-2xl border"
                :class="step.completed
                    ? 'border-emerald-200 bg-white dark:border-emerald-800/40 dark:bg-zinc-800/60'
                    : 'border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60'">

                <!-- Step header -->
                <button type="button"
                    class="flex w-full items-center gap-4 p-4 text-left transition hover:bg-zinc-50 dark:hover:bg-zinc-700/30"
                    :disabled="!step.unlocked"
                    @click="step.expanded = !step.expanded">

                    <!-- Status icon -->
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full"
                        :class="step.completed
                            ? 'bg-emerald-100 dark:bg-emerald-900/30'
                            : step.unlocked
                                ? 'bg-indigo-100 dark:bg-indigo-900/30'
                                : 'bg-zinc-100 dark:bg-zinc-700'">
                        <CheckCircle2 v-if="step.completed" class="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                        <Lock v-else-if="!step.unlocked" class="h-5 w-5 text-zinc-400 dark:text-zinc-500" />
                        <span v-else class="text-sm font-bold text-indigo-700 dark:text-indigo-300">{{ sIdx + 1 }}</span>
                    </div>

                    <!-- Info -->
                    <div class="flex-1 overflow-hidden">
                        <p class="font-semibold text-zinc-900 dark:text-white">{{ step.title }}</p>
                        <p v-if="step.description" class="mt-0.5 line-clamp-1 text-xs text-zinc-500 dark:text-zinc-400">{{ step.description }}</p>

                        <!-- Progress bar -->
                        <div v-if="step.unlocked && step.total_items > 0" class="mt-2">
                            <div class="mb-1 flex items-center justify-between text-[11px] text-zinc-400">
                                <span>{{ step.done_items }} / {{ step.total_items }} itens</span>
                                <span class="font-semibold" :class="step.completed ? 'text-emerald-600' : 'text-indigo-600'">
                                    {{ step.total_items > 0 ? Math.round(step.done_items / step.total_items * 100) : 0 }}%
                                </span>
                            </div>
                            <div class="h-1.5 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-700">
                                <div class="h-full rounded-full transition-all duration-700"
                                    :class="step.completed ? 'bg-emerald-500' : 'bg-indigo-500'"
                                    :style="{ width: (step.total_items > 0 ? Math.round(step.done_items / step.total_items * 100) : 0) + '%' }" />
                            </div>
                        </div>

                        <p v-if="!step.unlocked" class="mt-1 text-xs text-zinc-400 dark:text-zinc-500">
                            Conclua a etapa anterior para desbloquear.
                        </p>
                    </div>

                    <!-- Chevron -->
                    <div v-if="step.unlocked" class="shrink-0">
                        <ChevronUp v-if="step.expanded" class="h-4 w-4 text-zinc-400" />
                        <ChevronDown v-else class="h-4 w-4 text-zinc-400" />
                    </div>
                </button>

                <!-- Step items (expanded) -->
                <div v-if="step.unlocked && step.expanded" class="space-y-3 border-t border-zinc-100 p-4 dark:border-zinc-700">
                    <div v-if="!step.items?.length" class="py-4 text-center text-sm text-zinc-400 dark:text-zinc-500">
                        Nenhum conteúdo nesta etapa ainda.
                    </div>

                    <div v-for="(item, iIdx) in step.items" :key="item.id"
                        class="overflow-hidden rounded-xl border"
                        :class="item.localCompleted ? typeBorder[item.type] + ' ' + typeBg[item.type] : 'border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800/40'">

                        <!-- Item header -->
                        <div class="flex items-center gap-3 px-4 py-3">
                            <component :is="typeIcon[item.type]" class="h-5 w-5 shrink-0" :class="typeColor[item.type]" />
                            <div class="flex-1 overflow-hidden">
                                <p class="text-sm font-semibold text-zinc-800 dark:text-white">
                                    {{ item.title || typeLabel[item.type] }}
                                </p>
                                <p class="text-xs text-zinc-400">{{ typeLabel[item.type] }}</p>
                            </div>
                            <CheckCircle2 v-if="item.localCompleted" class="h-5 w-5 shrink-0 text-emerald-500" />
                        </div>

                        <!-- ─── VÍDEO ─────────────────────────────────────────── -->
                        <div v-if="item.type === 'video'" class="border-t border-zinc-100 p-4 dark:border-zinc-700">
                            <!-- Embed iframe -->
                            <div v-if="embedUrl(item.data?.url)" class="relative mb-3 overflow-hidden rounded-xl bg-black" style="padding-top:56.25%">
                                <iframe
                                    :src="embedUrl(item.data?.url)"
                                    class="absolute inset-0 h-full w-full"
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                    allowfullscreen />
                            </div>
                            <!-- External link fallback (non-YouTube/Vimeo URL) -->
                            <div v-else-if="item.data?.url" class="mb-3">
                                <a :href="item.data.url" target="_blank" rel="noopener"
                                    class="inline-flex items-center gap-2 rounded-xl border border-zinc-200 bg-white px-4 py-2.5 text-sm font-medium text-zinc-700 hover:border-indigo-300 hover:text-indigo-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                                    <PlayCircle class="h-4 w-4" /> Abrir vídeo
                                </a>
                            </div>
                            <!-- No URL set -->
                            <div v-else class="relative mb-3 flex flex-col items-center justify-center gap-2 overflow-hidden rounded-xl bg-zinc-900 py-10 text-center" style="min-height:140px">
                                <PlayCircle class="h-10 w-10 text-zinc-600" />
                                <p class="text-xs text-zinc-500">Vídeo não configurado. Adicione a URL no painel admin.</p>
                            </div>

                            <div v-if="!item.localCompleted">
                                <p class="mb-2 text-xs text-zinc-400 dark:text-zinc-500">Confirme que assistiu ao vídeo para continuar.</p>
                                <button type="button"
                                    class="flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-60"
                                    :disabled="item.submitting"
                                    @click="watchVideo(sIdx, iIdx)">
                                    <Loader2 v-if="item.submitting" class="h-4 w-4 animate-spin" />
                                    <CheckCircle2 v-else class="h-4 w-4" />
                                    Marcar como assistido
                                </button>
                            </div>
                            <p v-else class="text-sm font-medium text-emerald-600 dark:text-emerald-400">Vídeo assistido ✓</p>
                            <p v-if="item.error" class="mt-2 text-xs text-red-600 dark:text-red-400">{{ item.error }}</p>
                        </div>

                        <!-- ─── QUIZ ──────────────────────────────────────────── -->
                        <div v-else-if="item.type === 'quiz'" class="border-t border-zinc-100 p-4 dark:border-zinc-700">
                            <p class="mb-3 text-sm font-medium text-zinc-800 dark:text-white">{{ item.data?.question }}</p>

                            <div class="space-y-2">
                                <button v-for="(opt, oIdx) in item.data?.options" :key="oIdx" type="button"
                                    :disabled="item.localCompleted || item.submitting"
                                    class="flex w-full items-center gap-3 rounded-xl border px-4 py-3 text-left text-sm transition"
                                    :class="item.localCompleted
                                        ? opt.is_correct
                                            ? 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-200'
                                            : item.localAnswer?.option_index === oIdx && !opt.is_correct
                                                ? 'border-red-300 bg-red-50 text-red-800 dark:border-red-700 dark:bg-red-900/20 dark:text-red-200'
                                                : 'border-zinc-200 bg-zinc-50 text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800/40 dark:text-zinc-500'
                                        : item.selectedOption === oIdx
                                            ? 'border-indigo-400 bg-indigo-50 text-indigo-800 dark:border-indigo-600 dark:bg-indigo-900/20 dark:text-indigo-200'
                                            : 'border-zinc-200 bg-white text-zinc-700 hover:border-indigo-200 hover:bg-indigo-50/50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300'"
                                    @click="!item.localCompleted && (item.selectedOption = oIdx)">
                                    <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2 text-xs font-bold transition"
                                        :class="item.selectedOption === oIdx && !item.localCompleted
                                            ? 'border-indigo-500 bg-indigo-500 text-white'
                                            : item.localCompleted && opt.is_correct
                                                ? 'border-emerald-500 bg-emerald-500 text-white'
                                                : item.localCompleted && item.localAnswer?.option_index === oIdx && !opt.is_correct
                                                    ? 'border-red-500 bg-red-500 text-white'
                                                    : 'border-zinc-300 dark:border-zinc-600'">
                                        {{ String.fromCharCode(65 + oIdx) }}
                                    </span>
                                    {{ opt.text }}
                                </button>
                            </div>

                            <!-- Result feedback -->
                            <div v-if="item.showResult && item.localCompleted" class="mt-3 rounded-xl p-3"
                                :class="item.localIsCorrect ? 'bg-emerald-50 dark:bg-emerald-900/20' : 'bg-red-50 dark:bg-red-900/20'">
                                <p class="text-sm font-semibold" :class="item.localIsCorrect ? 'text-emerald-700 dark:text-emerald-300' : 'text-red-700 dark:text-red-300'">
                                    {{ item.localIsCorrect ? 'Resposta correta!' : 'Resposta incorreta.' }}
                                </p>
                                <p v-if="item.data?.explanation" class="mt-1 text-xs text-zinc-600 dark:text-zinc-400">{{ item.data.explanation }}</p>
                            </div>

                            <div v-if="!item.localCompleted" class="mt-3">
                                <button type="button"
                                    class="flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50"
                                    :disabled="item.selectedOption === null || item.submitting"
                                    @click="submitQuiz(sIdx, iIdx)">
                                    <Loader2 v-if="item.submitting" class="h-4 w-4 animate-spin" />
                                    Confirmar resposta
                                </button>
                            </div>
                            <p v-if="item.error" class="mt-2 text-xs text-red-600 dark:text-red-400">{{ item.error }}</p>
                        </div>

                        <!-- ─── PERGUNTA ABERTA ────────────────────────────────── -->
                        <div v-else-if="item.type === 'question'" class="border-t border-zinc-100 p-4 dark:border-zinc-700">
                            <p class="mb-3 text-sm font-medium text-zinc-800 dark:text-white">{{ item.data?.question }}</p>

                            <div v-if="!item.localCompleted">
                                <textarea v-model="item.textAnswer" rows="4"
                                    :placeholder="item.data?.placeholder || 'Escreva sua resposta aqui...'"
                                    class="w-full resize-none rounded-xl border border-zinc-200 bg-white px-4 py-3 text-sm text-zinc-800 placeholder-zinc-400 focus:border-sky-400 focus:outline-none dark:border-zinc-600 dark:bg-zinc-800 dark:text-white dark:placeholder-zinc-500" />
                                <button type="button"
                                    class="mt-2 flex items-center gap-2 rounded-xl bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700 disabled:opacity-50"
                                    :disabled="!item.textAnswer.trim() || item.submitting"
                                    @click="submitQuestion(sIdx, iIdx)">
                                    <Loader2 v-if="item.submitting" class="h-4 w-4 animate-spin" />
                                    Enviar resposta
                                </button>
                            </div>

                            <!-- Submitted answer -->
                            <div v-else class="rounded-xl border border-sky-200 bg-sky-50 p-4 dark:border-sky-800/40 dark:bg-sky-900/20">
                                <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-sky-600 dark:text-sky-400">Sua resposta</p>
                                <p class="text-sm text-zinc-700 dark:text-zinc-300">{{ item.localAnswer?.text }}</p>
                            </div>

                            <p v-if="item.error" class="mt-2 text-xs text-red-600 dark:text-red-400">{{ item.error }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
