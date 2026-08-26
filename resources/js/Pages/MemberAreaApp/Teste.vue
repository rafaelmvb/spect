<script setup>
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import MemberAreaAppLayout from '@/Layouts/MemberAreaAppLayout.vue';
import { useMemberBase } from '@/composables/useMemberBase';
import {
    ChevronLeft, ChevronRight, CheckCircle2, Clock,
    Tag, AlertCircle, Loader2, RotateCcw, Award, Share2, Copy, Check, FileText, ExternalLink,
} from 'lucide-vue-next';

defineOptions({ layout: MemberAreaAppLayout });

const props = defineProps({
    product:        { type: Object, required: true },
    config:         { type: Object, default: () => ({}) },
    slug:           { type: String, required: true },
    base_url:       { type: String, default: '' },
    test:           { type: Object, required: true },
    has_ai_report:  { type: Boolean, default: false },
    insight_url:    { type: String, default: null },
    session:        { type: Object, default: null },
    saved_answers:  { type: Object, default: () => ({}) },
});

const memberBase = useMemberBase(computed(() => props.slug));
function csrf() { return document.querySelector('meta[name="csrf-token"]')?.content ?? ''; }
const headers = () => ({ 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' });

// ── Estado da sessão ──
const sessionId   = ref(props.session?.id ?? null);
const started     = ref(!!props.session);
const completed   = ref(props.session?.status === 'completed');
const currentIdx  = ref(0);
const answers     = ref({ ...props.saved_answers });
const saving      = ref(false);
const completing  = ref(false);
const error       = ref('');

// Resultado: pré-carregado se sessão já estiver concluída ao abrir a página
const result = ref(
    props.session?.status === 'completed'
        ? {
            result_label:       props.session.result_label ?? 'Concluído',
            result_description: props.session.result_description ?? null,
            challenge_tags:     props.session.challenge_tags ?? [],
        }
        : null
);

const questions = computed(() => props.test.questions ?? []);
const total     = computed(() => questions.value.length);
const currentQ  = computed(() => questions.value[currentIdx.value]);

function isAnswered(q) {
    const ans = answers.value[q.id];
    if (ans === undefined || ans === null) return false;
    if (typeof ans === 'string') return ans.trim().length > 0;
    if (Array.isArray(ans)) return ans.length > 0;
    return true;
}

const answeredCount = computed(() => questions.value.filter(isAnswered).length);
const progressPct   = computed(() => total.value ? Math.round((answeredCount.value / total.value) * 100) : 0);

const canPrev = computed(() => currentIdx.value > 0);
const canNext = computed(() => currentIdx.value < total.value - 1);
const allAnswered = computed(() => questions.value.length > 0 && questions.value.every(isAnswered));

// ── Iniciar sessão ──
async function startSession(restart = false) {
    saving.value = true;
    error.value = '';
    try {
        const res = await fetch(`${memberBase.value}/testes/${props.test.id}/iniciar`, {
            method: 'POST',
            headers: headers(),
            body: JSON.stringify({ restart }),
        });
        const data = await res.json();
        if (!res.ok) throw new Error(data.message ?? 'Erro ao iniciar');
        sessionId.value = data.session_id;
        started.value = true;
        completed.value = false;
        result.value = null;
        if (restart) { answers.value = {}; currentIdx.value = 0; }
    } catch (e) {
        error.value = e.message;
    } finally {
        saving.value = false;
    }
}

// ── Salvar resposta ──
async function saveAnswer(questionId, answer) {
    if (!sessionId.value) return;
    const prevAnswer = answers.value[questionId];
    answers.value[questionId] = answer;

    try {
        await fetch(`${memberBase.value}/testes/${props.test.id}/responder`, {
            method: 'POST',
            headers: headers(),
            body: JSON.stringify({ session_id: sessionId.value, question_id: questionId, answer }),
        });
    } catch {
        answers.value[questionId] = prevAnswer; // rollback visual
    }
}

function selectAnswer(questionId, answer) {
    saveAnswer(questionId, answer);
}

const textDebounceTimers = {};
function handleTextInput(questionId, value) {
    answers.value[questionId] = value;
    clearTimeout(textDebounceTimers[questionId]);
    textDebounceTimers[questionId] = setTimeout(() => {
        if (value.trim()) saveAnswer(questionId, value);
    }, 700);
}

function toggleMultiAnswer(questionId, optionId) {
    const current = Array.isArray(answers.value[questionId]) ? [...answers.value[questionId]] : [];
    const idx = current.indexOf(optionId);
    if (idx >= 0) current.splice(idx, 1);
    else current.push(optionId);
    saveAnswer(questionId, current);
}

function isSelected(questionId, value) {
    const ans = answers.value[questionId];
    if (Array.isArray(ans)) return ans.includes(value);
    return ans !== undefined && (Array.isArray(ans) ? ans[0] : ans[0]) == value;
}

function isSingleSelected(questionId, value) {
    const ans = answers.value[questionId];
    if (Array.isArray(ans)) return ans[0] == value;
    return ans == value;
}

function isScaleSelected(questionId, value) {
    const ans = answers.value[questionId];
    if (Array.isArray(ans)) return Number(ans[0]) === value;
    return Number(ans) === value;
}

// ── Navegar ──
function prev() { if (canPrev.value) currentIdx.value--; }
function next() { if (canNext.value) currentIdx.value++; }

// ── Concluir ──
async function completeTest() {
    if (!allAnswered.value) { error.value = 'Responda todas as perguntas antes de concluir.'; return; }
    completing.value = true;
    error.value = '';
    try {
        const res = await fetch(`${memberBase.value}/testes/${props.test.id}/concluir`, {
            method: 'POST',
            headers: headers(),
            body: JSON.stringify({ session_id: sessionId.value }),
        });
        const data = await res.json();
        if (!res.ok) throw new Error(data.message ?? 'Erro ao concluir');
        completed.value = true;
        result.value = data;
    } catch (e) {
        error.value = e.message;
    } finally {
        completing.value = false;
    }
}

// Navega para próxima questão automaticamente ao responder (para scale/boolean)
watch(answers, () => {
    // Nada — usuário navega manualmente para ter controle
}, { deep: true });

// ── Relatório de IA ────────────────────────────────────────────
const reportUrl     = ref(props.insight_url ?? '');
const reportLoading = ref(false);
const reportError   = ref('');

async function generateReport() {
    reportLoading.value = true;
    reportError.value = '';
    try {
        const res = await fetch(`${memberBase.value}/testes/${props.test.id}/gerar-relatorio`, {
            method: 'POST',
            headers: headers(),
        });
        const data = await res.json();
        if (!res.ok) throw new Error(data.message ?? 'Erro ao gerar relatório');
        reportUrl.value = data.url;
    } catch (e) {
        reportError.value = e.message;
    } finally {
        reportLoading.value = false;
    }
}

// ── Compartilhar resultado ──────────────────────────────────────
const shareUrl     = ref('');
const shareLoading = ref(false);
const shareCopied  = ref(false);

async function generateShare() {
    if (!sessionId.value) return;
    shareLoading.value = true;
    try {
        const res = await fetch(`${memberBase.value}/testes/sessoes/${sessionId.value}/compartilhar`, {
            method: 'POST',
            headers: headers(),
        });
        const data = await res.json();
        if (res.ok) shareUrl.value = data.url;
    } finally {
        shareLoading.value = false;
    }
}

async function copyShareUrl() {
    if (!shareUrl.value) return;
    await navigator.clipboard.writeText(shareUrl.value);
    shareCopied.value = true;
    setTimeout(() => { shareCopied.value = false; }, 2500);
}
</script>

<template>
    <div class="max-w-xl mx-auto px-4 pt-4 pb-10">

        <!-- Resultado final -->
        <div v-if="completed && result" style="text-align:center; padding:40px 16px;">
            <div style="width:80px; height:80px; border-radius:50%; background:rgba(16,185,129,0.15); display:flex; align-items:center; justify-content:center; margin:0 auto 20px;">
                <Award style="width:40px; height:40px; color:var(--ma-primary);" />
            </div>
            <h2 style="font-family:'Cormorant Garamond',Georgia,serif; font-size:26px; font-weight:800; color:var(--ma-text); margin-bottom:8px;">Teste Concluído!</h2>
            <p style="font-size:22px; font-weight:700; color:var(--ma-primary); margin-bottom:8px;">{{ result.result_label }}</p>
            <p v-if="result.result_description" style="font-size:14px; color:var(--ma-text-2); max-width:380px; margin:0 auto 20px; line-height:1.6;">{{ result.result_description }}</p>

            <!-- Tags de desafio geradas -->
            <div v-if="result.challenge_tags?.length" style="margin-bottom:24px;">
                <p style="font-size:12px; color:var(--ma-text-2); margin-bottom:10px; text-transform:uppercase; letter-spacing:0.08em; font-weight:600;">Tags adicionadas ao seu perfil</p>
                <div style="display:flex; flex-wrap:wrap; gap:8px; justify-content:center;">
                    <span v-for="tag in result.challenge_tags" :key="tag"
                        style="display:flex; align-items:center; gap:5px; border-radius:20px; border:1.5px solid var(--ma-primary); color:var(--ma-primary); padding:4px 14px; font-size:13px; font-weight:700;">
                        <Tag style="width:12px; height:12px;" />{{ tag }}
                    </span>
                </div>
            </div>

            <div style="display:flex; gap:12px; justify-content:center; flex-wrap:wrap;">
                <button type="button"
                    style="border-radius:12px; border:1.5px solid var(--ma-border); background:none; color:var(--ma-text-2); padding:10px 20px; font-size:14px; font-weight:600; cursor:pointer;"
                    @click="startSession(true)">
                    <RotateCcw style="width:14px; height:14px; display:inline; margin-right:4px; vertical-align:-2px;" />Refazer
                </button>
                <button type="button"
                    style="border-radius:12px; background:var(--ma-primary); color:var(--ma-on-primary); padding:10px 20px; font-size:14px; font-weight:700; cursor:pointer; border:none;"
                    @click="router.visit(`${memberBase}/testes`)">
                    Ver Todos os Testes
                </button>
            </div>

            <!-- Relatório personalizado -->
            <div v-if="has_ai_report" style="margin-top:24px; padding-top:20px; border-top:1px solid var(--ma-border);">
                <div v-if="reportUrl" style="display:flex; flex-direction:column; align-items:center; gap:10px;">
                    <p style="font-size:13px; color:var(--ma-text-2);">Seu relatório personalizado está pronto</p>
                    <a :href="reportUrl" target="_blank"
                        style="display:inline-flex; align-items:center; gap:8px; border-radius:12px; background:var(--ma-primary); color:var(--ma-on-primary); padding:11px 22px; font-size:14px; font-weight:700; text-decoration:none;">
                        <FileText style="width:16px; height:16px;" />
                        Ver Relatório
                        <ExternalLink style="width:13px; height:13px; opacity:0.7;" />
                    </a>
                </div>
                <div v-else style="display:flex; flex-direction:column; align-items:center; gap:10px;">
                    <p style="font-size:13px; color:var(--ma-text-2);">Receba uma análise personalizada baseada nas suas respostas</p>
                    <p v-if="reportError" style="font-size:12px; color:#ef4444;">{{ reportError }}</p>
                    <button type="button" :disabled="reportLoading"
                        style="display:inline-flex; align-items:center; gap:8px; border-radius:12px; background:linear-gradient(135deg,#0ea5e9,#6366f1); color:#fff; padding:11px 22px; font-size:14px; font-weight:700; border:none; cursor:pointer; opacity:1; transition:opacity 0.2s;"
                        :style="{ opacity: reportLoading ? 0.7 : 1 }"
                        @click="generateReport">
                        <Loader2 v-if="reportLoading" style="width:16px; height:16px; animation:spin 1s linear infinite;" />
                        <FileText v-else style="width:16px; height:16px;" />
                        {{ reportLoading ? 'Gerando relatório…' : 'Gerar Relatório' }}
                    </button>
                    <p style="font-size:11px; color:var(--ma-text-inactive); text-align:center; max-width:300px;">
                        Suas respostas serão analisadas com metodologia testada e aprovada por especialistas. Não é diagnóstico.
                    </p>
                </div>
            </div>

            <!-- Compartilhar resultado -->
            <div style="margin-top:24px; padding-top:20px; border-top:1px solid var(--ma-border);">
                <p style="font-size:12px; color:var(--ma-text-2); margin-bottom:12px;">Compartilhar resultado (link válido por 7 dias)</p>
                <div v-if="!shareUrl" style="display:flex; justify-content:center;">
                    <button type="button"
                        :disabled="shareLoading"
                        style="display:flex; align-items:center; gap:8px; border-radius:12px; border:1.5px solid var(--ma-border); background:none; color:var(--ma-text); padding:10px 20px; font-size:14px; font-weight:600; cursor:pointer;"
                        @click="generateShare">
                        <Loader2 v-if="shareLoading" style="width:15px; height:15px; animation:spin 1s linear infinite;" />
                        <Share2 v-else style="width:15px; height:15px;" />
                        Gerar link
                    </button>
                </div>
                <div v-else style="display:flex; gap:8px; align-items:center; background:var(--ma-surface); border:1px solid var(--ma-border); border-radius:12px; padding:10px 14px;">
                    <p style="flex:1; font-size:12px; color:var(--ma-text-2); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin:0;">{{ shareUrl }}</p>
                    <button type="button"
                        style="flex-shrink:0; display:flex; align-items:center; gap:5px; border-radius:8px; background:var(--ma-primary); color:var(--ma-on-primary); border:none; padding:7px 12px; font-size:12px; font-weight:700; cursor:pointer;"
                        @click="copyShareUrl">
                        <Check v-if="shareCopied" style="width:13px; height:13px;" />
                        <Copy v-else style="width:13px; height:13px;" />
                        {{ shareCopied ? 'Copiado!' : 'Copiar' }}
                    </button>
                </div>
                <p style="font-size:11px; color:var(--ma-text-inactive); margin-top:8px;">Anotações clínicas nunca são incluídas no link compartilhado.</p>
            </div>
        </div>

        <!-- Tela inicial (antes de iniciar) -->
        <div v-else-if="!started" class="space-y-5">
            <div>
                <button type="button" style="display:flex; align-items:center; gap:6px; color:var(--ma-text-2); font-size:13px; background:none; border:none; cursor:pointer; padding:0; margin-bottom:16px;" @click="router.visit(`${memberBase}/testes`)">
                    <ChevronLeft style="width:16px; height:16px;" />Voltar
                </button>
                <span style="font-size:11px; text-transform:uppercase; letter-spacing:0.1em; color:var(--ma-primary); font-weight:700;">{{ test.category }}</span>
                <h1 style="font-family:'Cormorant Garamond',Georgia,serif; font-size:26px; font-weight:800; color:var(--ma-text); line-height:1.25; margin-top:4px;">{{ test.name }}</h1>
                <p v-if="test.description" style="font-size:14px; color:var(--ma-text-2); margin-top:8px; line-height:1.6;">{{ test.description }}</p>
            </div>

            <div style="display:flex; gap:12px;">
                <div style="flex:1; border-radius:12px; background:var(--ma-surface); border:1px solid var(--ma-border); padding:12px 14px; text-align:center;">
                    <p style="font-size:18px; font-weight:800; color:var(--ma-text);">{{ test.questions?.length }}</p>
                    <p style="font-size:11px; color:var(--ma-text-2);">questões</p>
                </div>
                <div style="flex:1; border-radius:12px; background:var(--ma-surface); border:1px solid var(--ma-border); padding:12px 14px; text-align:center;">
                    <p style="font-size:18px; font-weight:800; color:var(--ma-text);">~{{ test.estimated_minutes }}</p>
                    <p style="font-size:11px; color:var(--ma-text-2);">minutos</p>
                </div>
            </div>

            <div v-if="test.instructions" style="border-radius:14px; background:rgba(16,185,129,0.08); border:1px solid rgba(16,185,129,0.2); padding:14px 16px;">
                <p style="font-size:12px; font-weight:700; color:var(--ma-primary); margin-bottom:6px; text-transform:uppercase; letter-spacing:0.05em;">Instruções</p>
                <p style="font-size:13px; color:var(--ma-text-2); line-height:1.6; white-space:pre-line;">{{ test.instructions }}</p>
            </div>

            <div v-if="error" style="border-radius:12px; background:rgba(220,38,38,0.1); border:1px solid rgba(220,38,38,0.2); padding:12px 14px; display:flex; align-items:center; gap:8px;">
                <AlertCircle style="width:16px; height:16px; color:#ef4444; flex-shrink:0;" />
                <p style="font-size:13px; color:#ef4444;">{{ error }}</p>
            </div>

            <button type="button" :disabled="saving"
                style="width:100%; border-radius:14px; background:var(--ma-primary); color:var(--ma-on-primary); padding:14px; font-size:16px; font-weight:800; border:none; cursor:pointer; font-family:'Cormorant Garamond',Georgia,serif; letter-spacing:0.03em;"
                @click="startSession(false)">
                <Loader2 v-if="saving" style="width:18px; height:18px; display:inline; margin-right:6px; vertical-align:-3px; animation:spin 1s linear infinite;" />
                Iniciar Teste
            </button>
        </div>

        <!-- Questões -->
        <div v-else-if="!completed" class="space-y-5">
            <!-- Back + progresso -->
            <div style="display:flex; align-items:center; gap:12px;">
                <button type="button" style="background:none; border:none; cursor:pointer; color:var(--ma-text-2); padding:0; display:flex; align-items:center;" @click="router.visit(`${memberBase}/testes`)">
                    <ChevronLeft style="width:20px; height:20px;" />
                </button>
                <div style="flex:1;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                        <p style="font-size:12px; color:var(--ma-text-2); font-family:'DM Mono',monospace;">{{ currentIdx + 1 }}/{{ total }}</p>
                        <p style="font-size:12px; color:var(--ma-primary); font-family:'DM Mono',monospace;">{{ progressPct }}%</p>
                    </div>
                    <div style="height:4px; background:var(--ma-surface); border-radius:2px;">
                        <div style="height:100%; border-radius:2px; transition:width 0.3s ease;" :style="{ width: progressPct + '%', background: 'var(--ma-primary)' }" />
                    </div>
                </div>
            </div>

            <!-- Pergunta atual -->
            <div v-if="currentQ" style="border-radius:16px; background:var(--ma-surface); border:1px solid var(--ma-border); padding:20px;">
                <p style="font-family:'Cormorant Garamond',Georgia,serif; font-size:20px; font-weight:700; color:var(--ma-text); line-height:1.4; margin-bottom:20px;">{{ currentQ.text }}</p>

                <!-- Escala -->
                <div v-if="currentQ.type === 'scale'" style="space-y:12px;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:6px; font-size:11px; color:var(--ma-text-2); font-family:'DM Mono',monospace;">
                        <span>{{ currentQ.scale_labels?.[currentQ.scale_min] ?? 'Nunca' }}</span>
                        <span>{{ currentQ.scale_labels?.[currentQ.scale_max] ?? 'Sempre' }}</span>
                    </div>
                    <div style="display:flex; gap:8px; justify-content:center;">
                        <button v-for="v in (currentQ.scale_max - currentQ.scale_min + 1)" :key="v"
                            type="button"
                            :style="{ width:'44px', height:'44px', borderRadius:'12px', border:'2px solid', borderColor: isScaleSelected(currentQ.id, currentQ.scale_min + v - 1) ? 'var(--ma-primary)' : 'var(--ma-border)', background: isScaleSelected(currentQ.id, currentQ.scale_min + v - 1) ? 'var(--ma-primary)' : 'transparent', color: isScaleSelected(currentQ.id, currentQ.scale_min + v - 1) ? 'var(--ma-on-primary)' : 'var(--ma-text-2)', fontSize:'15px', fontWeight:'700', cursor:'pointer', flexShrink:0, fontFamily:'\'DM Mono\',monospace', transition:'all 0.15s' }"
                            @click="selectAnswer(currentQ.id, [currentQ.scale_min + v - 1])">
                            {{ currentQ.scale_min + v - 1 }}
                        </button>
                    </div>
                </div>

                <!-- Booleano (Sim / Não) -->
                <div v-else-if="currentQ.type === 'boolean'" style="display:flex; gap:12px;">
                    <button v-for="opt in [{ label: 'Sim', value: 1 }, { label: 'Não', value: 0 }]" :key="opt.value"
                        type="button"
                        :style="{ flex:1, borderRadius:'14px', border:'2px solid', borderColor: isScaleSelected(currentQ.id, opt.value) ? 'var(--ma-primary)' : 'var(--ma-border)', background: isScaleSelected(currentQ.id, opt.value) ? 'var(--ma-primary)' : 'transparent', color: isScaleSelected(currentQ.id, opt.value) ? 'var(--ma-on-primary)' : 'var(--ma-text-2)', padding:'12px', fontSize:'16px', fontWeight:'700', cursor:'pointer', fontFamily:'\'Cormorant Garamond\',Georgia,serif', transition:'all 0.15s' }"
                        @click="selectAnswer(currentQ.id, [opt.value])">
                        {{ opt.label }}
                    </button>
                </div>

                <!-- Múltipla escolha — única opção -->
                <div v-else-if="currentQ.type === 'single'" style="display:flex; flex-direction:column; gap:10px;">
                    <button v-for="opt in currentQ.options" :key="opt.id"
                        type="button"
                        :style="{ borderRadius:'14px', border:'2px solid', borderColor: isSingleSelected(currentQ.id, opt.id) ? 'var(--ma-primary)' : 'var(--ma-border)', background: isSingleSelected(currentQ.id, opt.id) ? 'rgba(16,185,129,0.1)' : 'transparent', color: isSingleSelected(currentQ.id, opt.id) ? 'var(--ma-primary)' : 'var(--ma-text-2)', padding:'12px 16px', fontSize:'15px', fontWeight:'600', cursor:'pointer', textAlign:'left', fontFamily:'\'Cormorant Garamond\',Georgia,serif', transition:'all 0.15s' }"
                        @click="selectAnswer(currentQ.id, [opt.id])">
                        {{ opt.text }}
                    </button>
                </div>

                <!-- Múltipla escolha — várias opções -->
                <div v-else-if="currentQ.type === 'multi'" style="display:flex; flex-direction:column; gap:10px;">
                    <p style="font-size:11px; color:var(--ma-text-2); margin-bottom:4px;">Selecione todas que se aplicam</p>
                    <button v-for="opt in currentQ.options" :key="opt.id"
                        type="button"
                        :style="{ borderRadius:'14px', border:'2px solid', borderColor: (Array.isArray(answers[currentQ.id]) && answers[currentQ.id].includes(opt.id)) ? 'var(--ma-primary)' : 'var(--ma-border)', background: (Array.isArray(answers[currentQ.id]) && answers[currentQ.id].includes(opt.id)) ? 'rgba(16,185,129,0.1)' : 'transparent', color: (Array.isArray(answers[currentQ.id]) && answers[currentQ.id].includes(opt.id)) ? 'var(--ma-primary)' : 'var(--ma-text-2)', padding:'12px 16px', fontSize:'15px', fontWeight:'600', cursor:'pointer', textAlign:'left', fontFamily:'\'Cormorant Garamond\',Georgia,serif', transition:'all 0.15s' }"
                        @click="toggleMultiAnswer(currentQ.id, opt.id)">
                        {{ opt.text }}
                    </button>
                </div>

                <!-- Texto livre -->
                <div v-else-if="currentQ.type === 'text'">
                    <textarea
                        :value="typeof answers[currentQ.id] === 'string' ? answers[currentQ.id] : ''"
                        rows="5"
                        placeholder="Escreva sua resposta aqui…"
                        :style="{ width:'100%', borderRadius:'14px', border:'2px solid var(--ma-border)', background:'transparent', color:'var(--ma-text)', padding:'12px 16px', fontSize:'15px', fontFamily:'\'Cormorant Garamond\',Georgia,serif', lineHeight:'1.6', resize:'vertical', outline:'none', boxSizing:'border-box', transition:'border-color 0.15s' }"
                        @input="handleTextInput(currentQ.id, $event.target.value)"
                        @focus="$event.target.style.borderColor = 'var(--ma-primary)'"
                        @blur="$event.target.style.borderColor = 'var(--ma-border)'" />
                    <p style="font-size:11px; color:var(--ma-text-2); margin-top:6px; text-align:right;">
                        {{ (typeof answers[currentQ.id] === 'string' ? answers[currentQ.id] : '').length }} caracteres
                    </p>
                </div>
            </div>

            <!-- Erro -->
            <div v-if="error" style="border-radius:12px; background:rgba(220,38,38,0.1); border:1px solid rgba(220,38,38,0.2); padding:12px 14px; display:flex; align-items:center; gap:8px;">
                <AlertCircle style="width:16px; height:16px; color:#ef4444; flex-shrink:0;" />
                <p style="font-size:13px; color:#ef4444;">{{ error }}</p>
            </div>

            <!-- Navegação -->
            <div style="display:flex; gap:10px;">
                <button type="button" :disabled="!canPrev"
                    :style="{ flex:1, borderRadius:'14px', border:'2px solid var(--ma-border)', background:'none', color: canPrev ? 'var(--ma-text-2)' : 'var(--ma-text-inactive)', padding:'12px', fontSize:'15px', fontWeight:'700', cursor: canPrev ? 'pointer' : 'default', opacity: canPrev ? 1 : 0.4, fontFamily:'\'Cormorant Garamond\',Georgia,serif', display:'flex', alignItems:'center', justifyContent:'center', gap:'6px' }"
                    @click="prev">
                    <ChevronLeft style="width:18px; height:18px;" />Anterior
                </button>

                <button v-if="canNext" type="button"
                    :style="{ flex:2, borderRadius:'14px', background:'var(--ma-primary)', color:'var(--ma-on-primary)', border:'none', padding:'12px', fontSize:'15px', fontWeight:'800', cursor:'pointer', fontFamily:'\'Cormorant Garamond\',Georgia,serif', display:'flex', alignItems:'center', justifyContent:'center', gap:'6px' }"
                    @click="next">
                    Próxima<ChevronRight style="width:18px; height:18px;" />
                </button>

                <button v-else type="button" :disabled="completing || !allAnswered"
                    :style="{ flex:2, borderRadius:'14px', background: allAnswered ? 'var(--ma-primary)' : 'var(--ma-surface)', color: allAnswered ? 'var(--ma-on-primary)' : 'var(--ma-text-inactive)', border: allAnswered ? 'none' : '2px solid var(--ma-border)', padding:'12px', fontSize:'15px', fontWeight:'800', cursor: allAnswered ? 'pointer' : 'default', opacity: completing ? 0.7 : 1, fontFamily:'\'Cormorant Garamond\',Georgia,serif', display:'flex', alignItems:'center', justifyContent:'center', gap:'6px' }"
                    @click="completeTest">
                    <Loader2 v-if="completing" style="width:16px; height:16px; animation:spin 1s linear infinite;" />
                    <CheckCircle2 v-else style="width:18px; height:18px;" />
                    Concluir
                </button>
            </div>

            <p v-if="!allAnswered" style="text-align:center; font-size:12px; color:var(--ma-text-2);">
                {{ answeredCount }}/{{ total }} questões respondidas
            </p>
        </div>
    </div>
</template>

<style scoped>
@keyframes spin { to { transform: rotate(360deg); } }
</style>
