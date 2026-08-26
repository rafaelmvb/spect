<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeft, Download, ChevronDown, ChevronUp, CheckCircle, Clock, BarChart2, MessageSquare, Sparkles } from 'lucide-vue-next';
import { useMemberBase } from '@/composables/useMemberBase';
import MemberAreaAppLayout from '@/Layouts/MemberAreaAppLayout.vue';

defineOptions({ layout: MemberAreaAppLayout });

const props = defineProps({
    slug:   { type: String, required: true },
    report: { type: Object, required: true },
});

const memberBase = useMemberBase(computed(() => props.slug));
const expanded    = ref({});

function toggle(i) { expanded.value[i] = !expanded.value[i]; }
function baixarPdf() { window.print(); }

const data = computed(() => props.report.report_data ?? {});
const steps = computed(() => data.value.steps ?? []);
const neuro = computed(() => data.value.neuro_scores ?? []);
const checkpointsRespondidos = computed(() => steps.value.filter(s => s.checkpoint?.responded_at).length);
</script>

<template>
    <Head><title>Resultado — {{ report.journey_name }}</title></Head>

    <div class="rel-page print:bg-white">

        <!-- Barra de ações (oculta no print) -->
        <div class="rel-topbar print:hidden">
            <button class="rel-back" @click="router.visit(`${memberBase}/meus-relatorios`)">
                <ArrowLeft style="width:16px;height:16px;" />
                Mega Relatórios
            </button>
            <button class="rel-download" @click="baixarPdf">
                <Download style="width:15px;height:15px;" />
                Baixar PDF
            </button>
        </div>

        <!-- Cabeçalho do relatório -->
        <div class="rel-card print:rel-card-print">
            <div class="rel-card-head">
                <div>
                    <span class="rel-label">Relatório de Resultados</span>
                    <h1 class="rel-title">{{ report.journey_name }}</h1>
                    <p class="rel-subtitle">{{ data.student?.name }} · {{ data.student?.email }}</p>
                    <p class="rel-date">Gerado em {{ report.generated_at }}</p>
                </div>
                <div class="rel-pct-block">
                    <span class="rel-pct-num">{{ data.completion_pct ?? 0 }}%</span>
                    <span class="rel-pct-label">de conclusão</span>
                </div>
            </div>

            <!-- Barra de progresso geral -->
            <div class="rel-progress-bar">
                <div class="rel-progress-fill" :style="{ width: (data.completion_pct ?? 0) + '%' }" />
            </div>

            <!-- 3 métricas -->
            <div class="rel-metrics">
                <div class="rel-metric">
                    <span class="rel-metric__val">{{ data.completed_steps ?? 0 }}<span class="rel-metric__total">/{{ data.total_steps ?? 0 }}</span></span>
                    <span class="rel-metric__lbl">Etapas concluídas</span>
                </div>
                <div class="rel-metric">
                    <span class="rel-metric__val">{{ checkpointsRespondidos }}</span>
                    <span class="rel-metric__lbl">Checkpoints respondidos</span>
                </div>
                <div class="rel-metric">
                    <span class="rel-metric__val">{{ neuro.length }}</span>
                    <span class="rel-metric__lbl">Indicadores avaliados</span>
                </div>
            </div>
        </div>

        <!-- Feedback do instrutor -->
        <div v-if="report.custom_content" class="rel-card rel-card--accent print:rel-card-print">
            <div class="rel-section-head">
                <MessageSquare style="width:16px;height:16px;" />
                <span class="rel-label">Feedback do instrutor</span>
            </div>
            <p class="rel-prose">{{ report.custom_content }}</p>
        </div>

        <!-- Análise por IA -->
        <div v-if="report.ai_summary" class="rel-card rel-card--ai print:rel-card-print">
            <div class="rel-section-head">
                <Sparkles style="width:16px;height:16px;" />
                <span class="rel-label">Análise elaborada com metodologia testada e aprovada por especialistas</span>
            </div>
            <p class="rel-prose">{{ report.ai_summary }}</p>
        </div>

        <!-- Etapas -->
        <div class="rel-card print:rel-card-print">
            <div class="rel-section-head rel-section-head--padded">
                <BarChart2 style="width:16px;height:16px;" />
                <span class="rel-label">Detalhamento por etapa</span>
            </div>

            <div class="rel-steps">
                <div v-for="(step, i) in steps" :key="step.id" class="rel-step">
                    <!-- Linha clicável (screen) -->
                    <button class="rel-step-row print:hidden" @click="toggle(i)">
                        <span class="rel-step-num" :class="step.completed ? 'rel-step-num--done' : ''">
                            {{ i + 1 }}
                        </span>
                        <div class="rel-step-info">
                            <p class="rel-step-title">{{ step.title }}</p>
                            <p class="rel-step-date">
                                <template v-if="step.completed_at">Concluído em {{ step.completed_at }}</template>
                                <template v-else-if="step.completed">Concluído</template>
                                <template v-else>Não concluído</template>
                            </p>
                        </div>
                        <CheckCircle v-if="step.completed" style="width:18px;height:18px;color:var(--ma-primary,#6FCF00);flex-shrink:0;" />
                        <Clock v-else style="width:18px;height:18px;color:var(--ma-text-2,#7A9BAD);flex-shrink:0;" />
                        <component :is="expanded[i] ? ChevronUp : ChevronDown" style="width:16px;height:16px;color:var(--ma-text-2,#7A9BAD);flex-shrink:0;" />
                    </button>

                    <!-- Linha no print (sempre visível) -->
                    <div class="rel-step-row hidden print:flex">
                        <span class="rel-step-num" :class="step.completed ? 'rel-step-num--done' : ''">{{ i + 1 }}</span>
                        <div class="rel-step-info">
                            <p class="rel-step-title">{{ step.title }}</p>
                            <p v-if="step.completed_at" class="rel-step-date">{{ step.completed_at }}</p>
                        </div>
                        <span v-if="step.completed" style="font-size:11px;font-weight:700;color:#16a34a;flex-shrink:0;">✓ Concluído</span>
                        <span v-else style="font-size:11px;color:#9ca3af;flex-shrink:0;">Pendente</span>
                    </div>

                    <!-- Checkpoint expandido (screen) -->
                    <div v-if="expanded[i] && step.checkpoint" class="rel-checkpoint print:hidden">
                        <p class="rel-checkpoint__name">{{ step.checkpoint.name }}</p>
                        <div v-for="(q, qi) in step.checkpoint.questions" :key="qi" class="rel-qa">
                            <p class="rel-qa__q">{{ q.label }}</p>
                            <p class="rel-qa__a">{{ q.answer ?? '—' }}</p>
                        </div>
                    </div>

                    <!-- Checkpoint no print (sempre visível) -->
                    <div v-if="step.checkpoint?.questions?.length" class="hidden print:block rel-checkpoint-print">
                        <p class="rel-checkpoint__name">{{ step.checkpoint.name }}</p>
                        <div v-for="(q, qi) in step.checkpoint.questions" :key="qi" class="rel-qa-print">
                            <p class="rel-qa__q">{{ q.label }}</p>
                            <p class="rel-qa__a">{{ q.answer ?? '—' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Indicadores NeuroMap -->
        <div v-if="neuro.length" class="rel-card print:rel-card-print">
            <div class="rel-section-head rel-section-head--padded">
                <BarChart2 style="width:16px;height:16px;" />
                <span class="rel-label">Indicadores NeuroMap</span>
            </div>
            <div class="rel-neuro">
                <div v-for="s in neuro" :key="s.indicator" class="rel-neuro-row">
                    <span class="rel-neuro-name">{{ s.indicator }}</span>
                    <div class="rel-neuro-bar">
                        <div class="rel-neuro-fill" :style="{ width: Math.min(Number(s.value) * 10, 100) + '%' }" />
                    </div>
                    <span class="rel-neuro-val">{{ Number(s.value).toFixed(1) }}</span>
                </div>
            </div>
        </div>

        <!-- Rodapé print -->
        <div class="hidden print:block rel-print-footer">
            Resultado gerado em {{ report.generated_at }} · {{ data.student?.name }} · {{ data.student?.email }}
        </div>
    </div>
</template>

<style scoped>
.rel-page {
    max-width: 760px;
    margin: 0 auto;
    padding: 20px 16px 60px;
    font-family: 'DM Sans', sans-serif;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

/* Topbar */
.rel-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 4px;
}
.rel-back {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    color: var(--ma-text-2, #7A9BAD);
    background: none;
    border: none;
    cursor: pointer;
    padding: 6px 0;
    transition: color 0.15s;
}
.rel-back:hover { color: var(--ma-text, #fff); }

.rel-download {
    display: flex;
    align-items: center;
    gap: 7px;
    font-size: 13px;
    font-weight: 600;
    color: #0D1F2D;
    background: var(--ma-primary, #6FCF00);
    border: none;
    border-radius: 10px;
    padding: 8px 16px;
    cursor: pointer;
    transition: opacity 0.15s;
}
.rel-download:hover { opacity: 0.88; }

/* Cards */
.rel-card {
    background: var(--ma-surface, #132435);
    border: 1px solid var(--ma-border, #1E3448);
    border-radius: 18px;
    overflow: hidden;
}
.rel-card--accent {
    border-color: rgba(var(--ma-primary-rgb, 111,207,0), 0.25);
    background: rgba(var(--ma-primary-rgb, 111,207,0), 0.04);
}
.rel-card--ai {
    border-color: rgba(99,179,237,0.25);
    background: rgba(99,179,237,0.04);
}

/* Cabeçalho */
.rel-card-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    padding: 24px 24px 0;
    flex-wrap: wrap;
}
.rel-label {
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    color: var(--ma-primary, #6FCF00);
    display: block;
    margin-bottom: 6px;
}
.rel-title {
    font-size: 22px;
    font-weight: 700;
    color: var(--ma-text, #fff);
    margin: 0 0 6px;
    line-height: 1.3;
}
.rel-subtitle {
    font-size: 13px;
    color: var(--ma-text-2, #7A9BAD);
    margin: 0 0 3px;
}
.rel-date {
    font-size: 11px;
    color: var(--ma-text-2, #7A9BAD);
    font-family: 'DM Mono', monospace;
    margin: 0;
    opacity: 0.7;
}
.rel-pct-block {
    text-align: right;
    flex-shrink: 0;
}
.rel-pct-num {
    display: block;
    font-size: 42px;
    font-weight: 800;
    color: var(--ma-primary, #6FCF00);
    line-height: 1;
}
.rel-pct-label {
    font-size: 11px;
    color: var(--ma-text-2, #7A9BAD);
}

/* Progress bar */
.rel-progress-bar {
    height: 6px;
    background: rgba(255,255,255,0.06);
    margin: 20px 24px 0;
    border-radius: 99px;
    overflow: hidden;
}
.rel-progress-fill {
    height: 100%;
    background: var(--ma-primary, #6FCF00);
    border-radius: 99px;
    transition: width 0.4s ease;
}

/* Métricas */
.rel-metrics {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1px;
    margin-top: 20px;
    border-top: 1px solid var(--ma-border, #1E3448);
}
.rel-metric {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 16px 12px;
    text-align: center;
    border-right: 1px solid var(--ma-border, #1E3448);
}
.rel-metric:last-child { border-right: none; }
.rel-metric__val {
    font-size: 22px;
    font-weight: 700;
    color: var(--ma-text, #fff);
    line-height: 1;
}
.rel-metric__total {
    font-size: 14px;
    color: var(--ma-text-2, #7A9BAD);
    font-weight: 400;
}
.rel-metric__lbl {
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: var(--ma-text-2, #7A9BAD);
    margin-top: 5px;
}

/* Section head */
.rel-section-head {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--ma-primary, #6FCF00);
    padding: 18px 24px 14px;
    border-bottom: 1px solid var(--ma-border, #1E3448);
}
.rel-section-head--padded { padding-bottom: 0; border-bottom: none; }
.rel-prose {
    font-size: 14px;
    color: var(--ma-text, #fff);
    line-height: 1.7;
    white-space: pre-line;
    padding: 0 24px 20px;
    margin: 0;
}

/* Etapas */
.rel-steps { padding: 12px 0; }
.rel-step { border-bottom: 1px solid var(--ma-border, #1E3448); }
.rel-step:last-child { border-bottom: none; }

.rel-step-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 20px;
    width: 100%;
    background: none;
    border: none;
    cursor: pointer;
    text-align: left;
    transition: background 0.15s;
}
.rel-step-row:hover { background: rgba(255,255,255,0.03); }

.rel-step-num {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: rgba(255,255,255,0.06);
    border: 1px solid var(--ma-border, #1E3448);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 700;
    color: var(--ma-text-2, #7A9BAD);
    flex-shrink: 0;
    font-family: 'DM Mono', monospace;
}
.rel-step-num--done {
    background: rgba(var(--ma-primary-rgb, 111,207,0), 0.15);
    border-color: rgba(var(--ma-primary-rgb, 111,207,0), 0.3);
    color: var(--ma-primary, #6FCF00);
}
.rel-step-info { flex: 1; min-width: 0; }
.rel-step-title {
    font-size: 14px;
    font-weight: 600;
    color: var(--ma-text, #fff);
    margin: 0 0 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.rel-step-date {
    font-size: 11px;
    color: var(--ma-text-2, #7A9BAD);
    font-family: 'DM Mono', monospace;
    margin: 0;
}

/* Checkpoint */
.rel-checkpoint {
    margin: 0 20px 14px 60px;
    background: rgba(var(--ma-primary-rgb, 111,207,0), 0.04);
    border: 1px solid rgba(var(--ma-primary-rgb, 111,207,0), 0.15);
    border-radius: 12px;
    padding: 14px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.rel-checkpoint-print {
    margin: 0 20px 14px 60px;
    padding: 8px 0;
}
.rel-checkpoint__name {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--ma-primary, #6FCF00);
    margin: 0 0 4px;
}
.rel-qa {
    background: rgba(255,255,255,0.04);
    border: 1px solid var(--ma-border, #1E3448);
    border-radius: 8px;
    padding: 10px 12px;
}
.rel-qa-print { padding: 6px 0; border-bottom: 1px solid #e5e7eb; }
.rel-qa__q {
    font-size: 11px;
    font-weight: 600;
    color: var(--ma-text-2, #7A9BAD);
    margin: 0 0 4px;
}
.rel-qa__a {
    font-size: 13px;
    color: var(--ma-text, #fff);
    white-space: pre-line;
    margin: 0;
}

/* NeuroMap */
.rel-neuro {
    padding: 16px 24px 20px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.rel-neuro-row {
    display: flex;
    align-items: center;
    gap: 12px;
}
.rel-neuro-name {
    font-size: 12px;
    color: var(--ma-text-2, #7A9BAD);
    width: 140px;
    flex-shrink: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.rel-neuro-bar {
    flex: 1;
    height: 6px;
    background: rgba(255,255,255,0.06);
    border-radius: 99px;
    overflow: hidden;
}
.rel-neuro-fill {
    height: 100%;
    background: var(--ma-primary, #6FCF00);
    border-radius: 99px;
}
.rel-neuro-val {
    font-size: 12px;
    font-weight: 700;
    color: var(--ma-text, #fff);
    width: 32px;
    text-align: right;
    font-family: 'DM Mono', monospace;
}

/* Print footer */
.rel-print-footer {
    text-align: center;
    font-size: 10px;
    color: #9ca3af;
    border-top: 1px solid #e5e7eb;
    padding-top: 16px;
    margin-top: 24px;
}
</style>

<style>
@media print {
    body { background: white !important; color: black !important; }
    .print\:hidden { display: none !important; }
    .print\:block  { display: block !important; }
    .print\:flex   { display: flex !important; }
    .print\:bg-white { background: white !important; }

    /* Adapta cores para print */
    .rel-card {
        background: white !important;
        border: 1px solid #e5e7eb !important;
        border-radius: 8px !important;
        margin-bottom: 16px !important;
    }
    .rel-title, .rel-step-title, .rel-qa__a { color: black !important; }
    .rel-subtitle, .rel-date, .rel-step-date, .rel-qa__q, .rel-metric__lbl,
    .rel-neuro-name, .rel-neuro-val { color: #6b7280 !important; }
    .rel-pct-num, .rel-label, .rel-checkpoint__name { color: #16a34a !important; }
    .rel-metric__val { color: #111827 !important; }
    .rel-neuro-fill { background: #16a34a !important; }
    .rel-progress-fill { background: #16a34a !important; }
    .rel-step-num--done { color: #16a34a !important; border-color: #16a34a !important; background: #f0fdf4 !important; }

    @page { margin: 2cm; }
}
</style>
