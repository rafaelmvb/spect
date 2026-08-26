<template>
    <div class="flex flex-col w-full select-none" style="font-family:'DM Sans',sans-serif;">

        <!-- ── TELA DE REVISÃO ── -->
        <div v-if="reviewing" class="flex flex-col gap-6">
            <div class="flex items-center gap-3 mb-1 px-1">
                <button type="button" @click="reviewing = false"
                    style="background:none;border:none;cursor:pointer;color:rgba(255,255,255,0.5);font-size:18px;padding:0;">←</button>
                <span style="font-size:12px;font-weight:700;letter-spacing:0.08em;color:rgba(255,255,255,0.5);text-transform:uppercase;flex:1;text-align:center;">
                    {{ lesson.title }}
                </span>
                <div style="width:20px;"/>
            </div>
            <p style="font-size:11px;font-weight:700;letter-spacing:0.1em;color:rgba(111,207,0,0.8);text-transform:uppercase;text-align:center;padding:0 4px;">
                {{ questions.length }} RESPOSTA{{ questions.length !== 1 ? 'S' : '' }} SALVA{{ questions.length !== 1 ? 'S' : '' }}
            </p>

            <div v-for="(q, qi) in questions" :key="q.id" class="flex flex-col gap-4 px-2 pb-6"
                :style="qi > 0 ? 'border-top:1px solid rgba(255,255,255,0.07);padding-top:24px;' : ''">
                <div v-if="q.category" style="font-size:10px;font-weight:700;letter-spacing:0.1em;color:rgba(111,207,0,0.7);text-transform:uppercase;">
                    {{ q.category }}
                </div>
                <!-- Mídia -->
                <div v-if="q.video_url && ytId(q.video_url)" class="overflow-hidden rounded-xl" style="position:relative;padding-top:56.25%;">
                    <iframe :src="`https://www.youtube.com/embed/${ytId(q.video_url)}?rel=0`"
                        style="position:absolute;inset:0;width:100%;height:100%;border:0;" allowfullscreen/>
                </div>
                <img v-else-if="q.image_url" :src="q.image_url" class="w-full rounded-xl object-cover" style="max-height:260px;"/>

                <p style="font-size:16px;font-weight:600;line-height:1.4;color:#fff;">{{ q.text }}</p>

                <!-- Escala (read-only) -->
                <template v-if="!q.type || q.type === 'scale'">
                    <div class="flex items-start justify-between px-1 mb-2">
                        <span style="font-size:11px;color:rgba(255,255,255,0.35);max-width:80px;text-align:left;line-height:1.3;">
                            {{ q.scale_min_label || 'Discordo totalmente' }}
                        </span>
                        <span style="font-size:11px;color:rgba(255,255,255,0.35);max-width:80px;text-align:right;line-height:1.3;">
                            {{ q.scale_max_label || 'Concordo totalmente' }}
                        </span>
                    </div>
                    <div style="display:flex;align-items:center;justify-content:center;gap:6px;flex-wrap:nowrap;width:100%;">
                        <div v-for="n in scaleRange(q)" :key="n"
                            style="flex:1;min-width:0;aspect-ratio:1/1;max-width:48px;border-radius:50%;border:2px solid;font-weight:600;display:flex;align-items:center;justify-content:center;font-size:13px;"
                            :style="savedAnswer(q.id) === n
                                ? { background:'#6FCF00', borderColor:'#6FCF00', color:'#0D1F2D' }
                                : { background:'transparent', borderColor:'rgba(255,255,255,0.12)', color:'rgba(255,255,255,0.4)' }">
                            {{ n }}
                        </div>
                    </div>
                </template>

                <!-- Verdadeiro/Falso (read-only) -->
                <template v-else-if="q.type === 'boolean'">
                    <div class="flex gap-3">
                        <div v-for="opt in [{label:'Sim',val:1},{label:'Não',val:0}]" :key="opt.val"
                            class="flex-1 py-3 rounded-xl text-center font-semibold text-sm"
                            :style="savedAnswer(q.id) === opt.val
                                ? 'background:#6FCF00;color:#0D1F2D;'
                                : 'background:rgba(255,255,255,0.07);color:rgba(255,255,255,0.3);'">
                            {{ opt.label }}
                        </div>
                    </div>
                </template>

                <!-- Escolha única (read-only) -->
                <template v-else-if="q.type === 'single'">
                    <div class="flex flex-col gap-2">
                        <div v-for="opt in (q.options ?? [])" :key="opt.id"
                            class="px-4 py-3 rounded-xl text-sm font-medium"
                            :style="savedAnswer(q.id) === opt.id
                                ? 'background:#6FCF00;color:#0D1F2D;'
                                : 'background:rgba(255,255,255,0.07);color:rgba(255,255,255,0.3);'">
                            {{ opt.text }}
                        </div>
                    </div>
                </template>

                <!-- Múltipla escolha (read-only) -->
                <template v-else-if="q.type === 'multi'">
                    <div class="flex flex-col gap-2">
                        <div v-for="opt in (q.options ?? [])" :key="opt.id"
                            class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium"
                            :style="Array.isArray(savedAnswer(q.id)) && savedAnswer(q.id).includes(opt.id)
                                ? 'background:rgba(111,207,0,0.15);color:#fff;'
                                : 'background:rgba(255,255,255,0.07);color:rgba(255,255,255,0.3);'">
                            <span class="flex h-4 w-4 shrink-0 items-center justify-center rounded border-2"
                                :style="Array.isArray(savedAnswer(q.id)) && savedAnswer(q.id).includes(opt.id)
                                    ? 'border-color:#6FCF00;background:#6FCF00;'
                                    : 'border-color:rgba(255,255,255,0.25);'">
                                <svg v-if="Array.isArray(savedAnswer(q.id)) && savedAnswer(q.id).includes(opt.id)" class="h-2.5 w-2.5" fill="none" viewBox="0 0 24 24" stroke="#0D1F2D" stroke-width="3.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                </svg>
                            </span>
                            {{ opt.text }}
                        </div>
                    </div>
                </template>

                <!-- Texto (read-only) -->
                <template v-else-if="q.type === 'text'">
                    <div style="border-left:2px solid rgba(111,207,0,0.4);padding-left:12px;">
                        <p style="font-size:13px;color:rgba(255,255,255,0.7);line-height:1.5;">{{ savedAnswer(q.id) }}</p>
                    </div>
                </template>

                <!-- Comentário salvo -->
                <div v-if="savedComment(q.id)" style="border-left:2px solid rgba(111,207,0,0.4);padding-left:12px;">
                    <p style="font-size:11px;font-weight:700;letter-spacing:0.08em;color:rgba(255,255,255,0.35);text-transform:uppercase;margin-bottom:4px;">Comentário</p>
                    <p style="font-size:13px;color:rgba(255,255,255,0.6);line-height:1.5;">{{ savedComment(q.id) }}</p>
                </div>
            </div>

            <button type="button" class="w-full py-3 rounded-xl font-semibold text-sm"
                style="background:rgba(255,255,255,0.07);color:rgba(255,255,255,0.5);border:none;cursor:pointer;margin-top:4px;"
                @click="restart">
                Refazer
            </button>
        </div>

        <!-- ── TELA CONCLUÍDO ── -->
        <div v-else-if="finished" class="flex flex-col items-center gap-5 py-16 text-center px-2">
            <div class="flex h-20 w-20 items-center justify-center rounded-full" style="background:rgba(111,207,0,0.15);">
                <svg class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="#6FCF00" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            <div>
                <h2 class="text-2xl font-bold text-white">Etapa concluída!</h2>
                <p class="mt-1" style="color:rgba(255,255,255,0.5);">
                    {{ savedResponses?.length ? 'Suas respostas estão salvas.' : 'Suas respostas foram registradas.' }}
                </p>
            </div>
            <div class="flex flex-col gap-3 w-full max-w-xs">
                <button v-if="hasReviewableAnswers" type="button"
                    class="w-full py-3 rounded-xl font-semibold text-sm transition"
                    style="background:#6FCF00;color:#0D1F2D;border:none;cursor:pointer;"
                    @click="reviewing = true">
                    Revisar minhas respostas
                </button>
                <button type="button" class="w-full py-3 rounded-xl font-semibold text-sm"
                    style="background:rgba(255,255,255,0.07);color:rgba(255,255,255,0.5);border:none;cursor:pointer;"
                    @click="restart">
                    Refazer
                </button>
            </div>
        </div>

        <!-- ── SEM PERGUNTAS ── -->
        <div v-else-if="!questions.length" class="flex flex-col items-center justify-center gap-4 py-20 text-center">
            <div class="flex h-16 w-16 items-center justify-center rounded-full" style="background:rgba(255,255,255,0.06);">
                <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="rgba(255,255,255,0.3)" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>
            <div>
                <p class="text-sm font-medium" style="color:rgba(255,255,255,0.6);">Esta etapa não tem perguntas cadastradas.</p>
                <p class="mt-1 text-xs" style="color:rgba(255,255,255,0.3);">Adicione perguntas no painel admin.</p>
            </div>
        </div>

        <!-- ── QUIZ ATIVO ── -->
        <template v-else>
            <!-- Header -->
            <div class="flex items-center gap-3 mb-5 px-1">
                <div style="color:rgba(255,255,255,0.4);font-size:18px;cursor:default;">←</div>
                <div class="flex-1 text-center">
                    <span style="font-size:12px;font-weight:700;letter-spacing:0.08em;color:rgba(255,255,255,0.5);text-transform:uppercase;">
                        {{ lesson.title }}
                    </span>
                </div>
                <div style="width:20px;"/>
            </div>

            <!-- Barra de progresso -->
            <div class="mb-2 px-1">
                <div class="h-[5px] w-full rounded-full overflow-hidden" style="background:rgba(255,255,255,0.08);">
                    <div class="h-full rounded-full transition-all duration-500"
                        :style="{ width: progressPercent + '%', background: '#6FCF00' }"/>
                </div>
            </div>
            <div class="flex items-center justify-between mb-8 px-1">
                <span style="font-size:12px;color:rgba(255,255,255,0.4);">{{ progressPercent }}%</span>
                <span style="font-size:12px;color:rgba(255,255,255,0.4);">questão {{ currentIndex + 1 }} de {{ questions.length }}</span>
            </div>

            <!-- Pergunta -->
            <div class="flex flex-col gap-6 px-2">
                <div v-if="current.category" style="font-size:11px;font-weight:700;letter-spacing:0.1em;color:rgba(111,207,0,0.8);text-transform:uppercase;">
                    {{ current.category }}
                </div>

                <!-- Mídia da pergunta -->
                <div v-if="current.video_url && ytId(current.video_url)" class="overflow-hidden rounded-xl" style="position:relative;padding-top:56.25%;">
                    <iframe :src="`https://www.youtube.com/embed/${ytId(current.video_url)}?rel=0`"
                        style="position:absolute;inset:0;width:100%;height:100%;border:0;" allowfullscreen/>
                </div>
                <img v-else-if="current.image_url" :src="current.image_url" class="w-full rounded-xl object-cover" style="max-height:260px;"/>

                <!-- Texto da pergunta -->
                <p style="font-size:22px;font-weight:600;line-height:1.4;color:#fff;">{{ current.text }}</p>

                <!-- ── ESCALA ── -->
                <div v-if="currentType === 'scale'" class="flex flex-col gap-5">
                    <span style="font-size:10px;font-weight:700;letter-spacing:0.12em;color:rgba(255,255,255,0.35);text-transform:uppercase;">ESCALA</span>
                    <div class="flex items-start justify-between px-1">
                        <span style="font-size:11px;color:rgba(255,255,255,0.4);max-width:80px;text-align:left;line-height:1.3;">{{ scaleMinLabel }}</span>
                        <span style="font-size:11px;color:rgba(255,255,255,0.4);max-width:80px;text-align:right;line-height:1.3;">{{ scaleMaxLabel }}</span>
                    </div>
                    <div style="display:flex;align-items:center;justify-content:center;gap:6px;flex-wrap:nowrap;width:100%;">
                        <button v-for="n in scaleValues" :key="n" type="button"
                            style="flex:1;min-width:0;aspect-ratio:1/1;max-width:56px;border-radius:50%;border:2px solid;font-weight:600;cursor:pointer;transition:all 0.15s;display:flex;align-items:center;justify-content:center;"
                            :style="[
                                { fontSize: scaleValues.length > 7 ? '12px' : '15px' },
                                selectedValue === n
                                    ? { background:'#6FCF00', borderColor:'#6FCF00', color:'#0D1F2D', transform:'scale(1.1)' }
                                    : { background:'transparent', borderColor:'rgba(255,255,255,0.18)', color:'rgba(255,255,255,0.7)' }
                            ]"
                            @click="selectValue(n)">{{ n }}</button>
                    </div>
                    <p v-if="!current.comment_enabled" style="font-size:11px;color:rgba(255,255,255,0.3);text-align:center;">ao clicar, avança automaticamente</p>
                </div>

                <!-- ── VERDADEIRO/FALSO ── -->
                <div v-else-if="currentType === 'boolean'" class="flex gap-3">
                    <button v-for="opt in [{label:'Sim',val:1},{label:'Não',val:0}]" :key="opt.val" type="button"
                        class="flex-1 py-4 rounded-xl font-semibold text-base transition"
                        style="border:2px solid;"
                        :style="selectedValue === opt.val
                            ? 'background:#6FCF00;border-color:#6FCF00;color:#0D1F2D;transform:scale(1.04);'
                            : 'background:transparent;border-color:rgba(255,255,255,0.18);color:rgba(255,255,255,0.8);'"
                        @click="selectValue(opt.val)">
                        {{ opt.label }}
                    </button>
                </div>

                <!-- ── ESCOLHA ÚNICA ── -->
                <div v-else-if="currentType === 'single'" class="flex flex-col gap-3">
                    <button v-for="opt in (current.options ?? [])" :key="opt.id" type="button"
                        class="w-full px-4 py-3 rounded-xl text-left font-medium text-sm transition"
                        style="border:2px solid;"
                        :style="selectedValue === opt.id
                            ? 'background:#6FCF00;border-color:#6FCF00;color:#0D1F2D;'
                            : 'background:transparent;border-color:rgba(255,255,255,0.18);color:rgba(255,255,255,0.8);'"
                        @click="selectValue(opt.id)">
                        {{ opt.text }}
                    </button>
                </div>

                <!-- ── MÚLTIPLA ESCOLHA ── -->
                <div v-else-if="currentType === 'multi'" class="flex flex-col gap-3">
                    <p style="font-size:11px;font-weight:700;letter-spacing:0.1em;color:rgba(255,255,255,0.35);text-transform:uppercase;">Selecione todas que se aplicam</p>
                    <button v-for="opt in (current.options ?? [])" :key="opt.id" type="button"
                        class="flex items-center gap-3 w-full px-4 py-3 rounded-xl text-left font-medium text-sm transition"
                        style="border:2px solid;"
                        :style="selectedMulti.includes(opt.id)
                            ? 'background:rgba(111,207,0,0.15);border-color:#6FCF00;color:#fff;'
                            : 'background:transparent;border-color:rgba(255,255,255,0.18);color:rgba(255,255,255,0.8);'"
                        @click="toggleMulti(opt.id)">
                        <span class="flex h-4 w-4 shrink-0 items-center justify-center rounded border-2 transition"
                            :style="selectedMulti.includes(opt.id)
                                ? 'border-color:#6FCF00;background:#6FCF00;'
                                : 'border-color:rgba(255,255,255,0.3);background:transparent;'">
                            <svg v-if="selectedMulti.includes(opt.id)" class="h-2.5 w-2.5" fill="none" viewBox="0 0 24 24" stroke="#0D1F2D" stroke-width="3.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                            </svg>
                        </span>
                        {{ opt.text }}
                    </button>
                </div>

                <!-- ── TEXTO LIVRE ── -->
                <div v-else-if="currentType === 'text'" class="flex flex-col gap-3">
                    <textarea v-model="textAnswer" rows="4"
                        class="w-full rounded-xl px-4 py-3 text-sm resize-none focus:outline-none transition"
                        style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.15);color:#fff;font-family:'DM Sans',sans-serif;"
                        placeholder="Digite sua resposta aqui..."/>
                </div>

                <!-- Comentário opcional (só para escala) -->
                <div v-if="currentType === 'scale' && current.comment_enabled" class="flex flex-col gap-2">
                    <textarea v-model="currentComment" rows="3"
                        class="w-full rounded-xl px-4 py-3 text-sm resize-none focus:outline-none transition"
                        style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);color:#fff;font-family:'DM Sans',sans-serif;"
                        placeholder="Opcional — adicione um comentário..."/>
                </div>

                <!-- Botão personalizado -->
                <a v-if="current.button_url" :href="current.button_url" target="_blank" rel="noopener"
                    class="flex items-center justify-center gap-2 w-full py-3 rounded-xl font-semibold text-sm transition"
                    style="background:rgba(255,255,255,0.08);color:rgba(255,255,255,0.85);text-decoration:none;border:1px solid rgba(255,255,255,0.15);">
                    {{ current.button_label || 'Saiba mais' }}
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                    </svg>
                </a>

                <!-- Botão avançar (multi, text, e scale com comentário) -->
                <button v-if="showAdvanceButton" type="button"
                    class="w-full py-3 rounded-xl font-semibold text-sm transition"
                    :disabled="!canAdvance || submitting"
                    :style="canAdvance && !submitting
                        ? 'background:#6FCF00;color:#0D1F2D;border:none;cursor:pointer;'
                        : 'background:rgba(255,255,255,0.07);color:rgba(255,255,255,0.3);border:none;cursor:not-allowed;'"
                    @click="advance">
                    {{ submitting ? 'Enviando...' : (isLast ? 'Concluir etapa' : 'Próxima pergunta →') }}
                </button>
            </div>
        </template>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useMemberBase } from '@/composables/useMemberBase';

const props = defineProps({
    lesson: { type: Object, required: true },
    slug:   { type: String, required: true },
});
const emit = defineEmits(['completed']);

const memberBase = useMemberBase(computed(() => props.slug));

const questions = computed(() => {
    const cf = props.lesson?.content_files;
    if (!cf) return [];
    if (Array.isArray(cf)) return cf;
    if (Array.isArray(cf.questions)) return cf.questions;
    return [];
});

const savedResponses     = computed(() => props.lesson?.quiz_response ?? null);
const hasReviewableAnswers = computed(() => Array.isArray(savedResponses.value) && savedResponses.value.length > 0);

const savedMap = computed(() => {
    const map = {};
    (savedResponses.value ?? []).forEach(r => { map[r.question_id] = r; });
    return map;
});
function savedAnswer(questionId) { return savedMap.value[questionId]?.value ?? null; }
function savedComment(questionId) { return savedMap.value[questionId]?.comment ?? null; }

// ── Estado ──
const currentIndex   = ref(0);
const answers        = ref([]);
const selectedValue  = ref(null);   // scale, single, boolean
const selectedMulti  = ref([]);     // multi
const textAnswer     = ref('');     // text
const currentComment = ref('');
const submitting     = ref(false);

const finished  = ref(hasReviewableAnswers.value || (props.lesson?.is_completed ?? false));
const reviewing = ref(false);

const current     = computed(() => questions.value[currentIndex.value] ?? null);
const isLast      = computed(() => currentIndex.value === questions.value.length - 1);
const currentType = computed(() => current.value?.type ?? 'scale');

const progressPercent = computed(() => {
    if (!questions.value.length) return 0;
    return Math.round((currentIndex.value / questions.value.length) * 100);
});

const scaleValues = computed(() => {
    if (!current.value) return [];
    const min = Number(current.value.scale_min ?? 1);
    const max = Number(current.value.scale_max ?? 5);
    return Array.from({ length: max - min + 1 }, (_, i) => min + i);
});
const scaleMinLabel = computed(() => {
    if (current.value?.scale_min_label) return current.value.scale_min_label;
    const min = Number(current.value?.scale_min ?? 1);
    return (min === 0 || min === 1) ? 'Discordo totalmente' : String(min);
});
const scaleMaxLabel = computed(() => current.value?.scale_max_label || 'Concordo totalmente');

function scaleRange(q) {
    const min = Number(q.scale_min ?? 1);
    const max = Number(q.scale_max ?? 5);
    return Array.from({ length: max - min + 1 }, (_, i) => min + i);
}

function ytId(url) {
    if (!url) return null;
    const m = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/);
    return m ? m[1] : null;
}

const canAdvance = computed(() => {
    const t = currentType.value;
    if (t === 'scale' || t === 'boolean' || t === 'single') return selectedValue.value !== null;
    if (t === 'multi') return selectedMulti.value.length > 0;
    if (t === 'text') return textAnswer.value.trim().length > 0;
    return false;
});

// Exibe botão avançar para tipos que não auto-avançam
const showAdvanceButton = computed(() => {
    const t = currentType.value;
    if (t === 'multi' || t === 'text') return true;
    if (current.value?.comment_enabled && (t === 'scale' || t === 'single' || t === 'boolean')) return true;
    return false;
});

function selectValue(n) {
    selectedValue.value = n;
    if (!current.value?.comment_enabled) {
        setTimeout(advance, 300);
    }
}

function toggleMulti(optId) {
    const idx = selectedMulti.value.indexOf(optId);
    if (idx === -1) selectedMulti.value.push(optId);
    else selectedMulti.value.splice(idx, 1);
}

function advance() {
    if (!canAdvance.value || submitting.value) return;
    const t = currentType.value;
    let value;
    if (t === 'scale' || t === 'boolean' || t === 'single') value = selectedValue.value;
    else if (t === 'multi') value = [...selectedMulti.value];
    else value = textAnswer.value.trim();

    answers.value.push({
        question_id: current.value.id,
        type: t,
        value,
        comment: currentComment.value.trim() || null,
    });

    selectedValue.value  = null;
    selectedMulti.value  = [];
    textAnswer.value     = '';
    currentComment.value = '';

    if (isLast.value) {
        submitQuiz();
    } else {
        currentIndex.value++;
    }
}

async function submitQuiz() {
    submitting.value = true;
    try {
        await window.axios.post(`${memberBase.value}/aula/${props.lesson.id}/quiz`, {
            responses: answers.value,
        });
        finished.value = true;
        emit('completed');
    } catch (e) {
        console.error('Erro ao enviar quiz', e);
    } finally {
        submitting.value = false;
    }
}

function restart() {
    currentIndex.value   = 0;
    answers.value        = [];
    selectedValue.value  = null;
    selectedMulti.value  = [];
    textAnswer.value     = '';
    currentComment.value = '';
    finished.value       = false;
    reviewing.value      = false;
}
</script>
