<script setup>
import { ref, computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import MemberAreaAppLayout from '@/Layouts/MemberAreaAppLayout.vue';
import { useMemberBase } from '@/composables/useMemberBase';

defineOptions({ layout: MemberAreaAppLayout });

const props = defineProps({
    product:           { type: Object,  required: true },
    config:            { type: Object,  default: () => ({}) },
    sections:          { type: Array,   default: () => [] },
    progress_percent:  { type: Number,  default: 0 },
    continue_watching: { type: Object,  default: null },
    slug:              { type: String,  required: true },
    base_url:          { type: String,  default: '' },
    course_lesson_progress: { type: Object, default: () => ({ completed: 0, total: 0 }) },
    user_has_access:   { type: Boolean, default: true },
    checkout_url:      { type: String,  default: '' },
    mega_report:       { type: Object,  default: null },
});

const page = usePage();
const primaryColor = computed(() =>
    page.props?.member_area_global_branding?.primary_color
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

const totalLessons    = computed(() => props.course_lesson_progress?.total ?? 0);
const completedCount  = computed(() => props.course_lesson_progress?.completed ?? 0);
const progressPct     = computed(() => props.progress_percent ?? 0);
const totalEtapas     = computed(() => props.sections.length);

// Índice da etapa atual (primeira não concluída e não bloqueada)
const currentIdx = computed(() => {
    for (let i = 0; i < props.sections.length; i++) {
        const st = etapaStatus(props.sections[i]);
        if (st === 'current' || st === 'available') return i;
    }
    return -1;
});

function etapaStatus(section) {
    const modules = section.modules ?? [];
    if (!modules.length) return 'empty';
    const allPurchaseLocked = modules.every(m => m.is_purchase_locked);
    if (allPurchaseLocked) return 'purchase_locked';
    const allPermLocked = modules.every(m => m.is_locked && !m.is_purchase_locked);
    if (allPermLocked) return 'locked';
    const accessibleModules = modules.filter(m => !m.is_purchase_locked);
    const allDone = accessibleModules.length > 0 && accessibleModules.every(m =>
        !m.is_locked && (m.lessons ?? []).length > 0 && m.lessons.every(l => l.is_completed)
    );
    if (allDone) return 'done';
    const anyStarted = accessibleModules.some(m => m.lessons?.some(l => l.is_completed));
    return anyStarted ? 'current' : 'available';
}

function firstModuleLink(section, preferIncomplete = false) {
    const modules = section.modules ?? [];
    if (preferIncomplete) {
        // Prioriza o primeiro módulo com aulas incompletas
        for (const m of modules) {
            if (!m.is_locked && !m.is_purchase_locked && m.lessons?.some(l => !l.is_completed)) {
                return `${memberBase.value}/modulo/${m.id}`;
            }
        }
    }
    // Fallback: primeiro módulo acessível
    for (const m of modules) {
        if (!m.is_locked && !m.is_purchase_locked) return `${memberBase.value}/modulo/${m.id}`;
    }
    return null;
}

function countLessons(section) {
    return (section.modules ?? []).reduce((acc, m) => acc + (m.lessons?.length ?? 0), 0);
}
function countCompleted(section) {
    return (section.modules ?? []).reduce((acc, m) => acc + (m.lessons?.filter(l => l.is_completed).length ?? 0), 0);
}
// Chips dos módulos com aulas ainda não concluídas (no card "current")
function moduleChips(section) {
    return (section.modules ?? [])
        .filter(m => !m.is_locked && !m.is_purchase_locked && m.lessons?.some(l => !l.is_completed))
        .map(m => m.title)
        .slice(0, 3);
}

// ── Mega Relatório ──
const generating    = ref(false);
const generateError = ref(null);

const canGenerate = computed(() => progressPct.value >= 100);

async function generateMegaRelatorio() {
    generating.value = true;
    generateError.value = null;
    try {
        const res = await window.axios.post(
            `${memberBase.value}/modulos/${props.product.id}/mega-relatorio`
        );
        router.visit(`${memberBase.value}/resultados`);
    } catch (e) {
        generateError.value = e?.response?.data?.error ?? 'Erro ao gerar relatório. Tente novamente.';
        generating.value = false;
    }
}
</script>

<template>
    <div style="padding:16px; max-width:480px; margin:0 auto; padding-bottom:120px; font-family:'DM Sans',sans-serif;">

        <!-- Cabeçalho -->
        <div style="padding:8px 0 20px;">
            <p style="font-family:'DM Mono',monospace; font-size:11px; text-transform:uppercase; letter-spacing:1.5px; color:var(--ma-text-2); margin:0 0 6px;">sua jornada</p>
            <h1 style="font-size:28px; font-weight:800; margin:0 0 16px; color:var(--ma-text);">Sua trilha.</h1>

            <!-- Barra de progresso -->
            <div style="display:flex; align-items:center; gap:12px;">
                <div style="flex:1; height:4px; background:var(--ma-surface-2); border-radius:2px; overflow:hidden;">
                    <div style="height:100%; border-radius:2px; transition:width 0.6s ease;"
                        :style="{ width: progressPct + '%', background: `linear-gradient(90deg, var(--ma-teal), ${primaryColor})` }" />
                </div>
                <span style="font-family:'DM Mono',monospace; font-size:12px; color:var(--ma-text-2); white-space:nowrap; flex-shrink:0;">
                    {{ completedCount }}/{{ totalLessons }}
                </span>
            </div>
        </div>

        <!-- Etapas -->
        <div style="display:flex; flex-direction:column;">
            <template v-for="(section, idx) in sections" :key="section.id">

                <!-- Conector entre cards -->
                <div v-if="idx > 0"
                    style="width:2px; height:24px; margin-left:24px;"
                    :style="{ background: etapaStatus(section) !== 'locked' && etapaStatus(sections[idx-1]) === 'done' ? primaryColor : 'var(--ma-border)' }" />

                <!-- ── CARD CONCLUÍDA ─────────────────────────────────────── -->
                <div v-if="etapaStatus(section) === 'done'"
                    style="border-radius:14px; padding:20px 22px; background:var(--ma-surface); border:1px solid var(--ma-border);">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                        <span style="font-family:'DM Mono',monospace; font-size:11px; text-transform:uppercase; letter-spacing:1.5px; color:var(--ma-text-2);">
                            etapa {{ String(idx + 1).padStart(2, '0') }}
                        </span>
                        <div style="width:22px; height:22px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:700; color:var(--ma-on-primary);"
                            :style="{ background: primaryColor }">✓</div>
                    </div>
                    <h2 style="font-size:20px; font-weight:600; color:var(--ma-text); margin:0 0 12px;">{{ section.title }}</h2>
                    <Link v-if="firstModuleLink(section)"
                        :href="firstModuleLink(section)"
                        style="font-size:13px; font-weight:500; color:var(--ma-text-2); text-decoration:none; font-family:'DM Mono',monospace;">
                        ver resultados →
                    </Link>
                </div>

                <!-- ── CARD VOCÊ ESTÁ AQUI (current) ─────────────────────── -->
                <div v-else-if="etapaStatus(section) === 'current'"
                    style="border-radius:14px; padding:22px 22px; position:relative; overflow:hidden;"
                    :style="{
                        background: `color-mix(in srgb, ${primaryColor} 6%, var(--ma-bg))`,
                        border: `1px solid ${primaryColor}`,
                        boxShadow: `0 0 24px ${primaryRgba(0.08)}, inset 0 0 0 1px ${primaryRgba(0.12)}`,
                    }">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                        <span style="font-family:'DM Mono',monospace; font-size:11px; text-transform:uppercase; letter-spacing:1.5px; color:var(--ma-text-2);">
                            etapa {{ String(idx + 1).padStart(2, '0') }}
                        </span>
                        <span style="font-family:'DM Mono',monospace; font-size:11px; text-transform:uppercase; letter-spacing:1.5px; font-weight:700;"
                            :style="{ color: primaryColor }">você está aqui</span>
                    </div>
                    <h2 style="font-size:22px; font-weight:700; color:var(--ma-text); margin:0 0 8px; line-height:1.25;">{{ section.title }}</h2>
                    <p v-if="section.description" style="font-size:13px; color:var(--ma-text-2); margin:0 0 14px; line-height:1.5;">{{ section.description }}</p>

                    <!-- Chips de progresso -->
                    <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:16px;">
                        <span style="font-family:'DM Mono',monospace; font-size:11px; padding:4px 10px; border-radius:20px; font-weight:600;"
                            :style="{ background: primaryRgba(0.15), color: primaryColor, border: `1px solid ${primaryRgba(0.3)}` }">
                            {{ countCompleted(section) }} de {{ countLessons(section) }} feitos
                        </span>
                        <span v-for="chip in moduleChips(section)" :key="chip"
                            style="font-family:'DM Mono',monospace; font-size:11px; padding:4px 10px; border-radius:20px; background:rgba(255,255,255,0.06); color:var(--ma-text-2); border:1px solid rgba(255,255,255,0.1);">
                            + {{ chip }}
                        </span>
                    </div>

                    <Link v-if="firstModuleLink(section, true)"
                        :href="firstModuleLink(section, true)"
                        style="font-size:14px; font-weight:600; text-decoration:none; font-family:'DM Mono',monospace;"
                        :style="{ color: primaryColor }">
                        continuar →
                    </Link>
                </div>

                <!-- ── CARD DISPONÍVEL (available) ───────────────────────── -->
                <div v-else-if="etapaStatus(section) === 'available'"
                    style="border-radius:14px; padding:22px 22px;"
                    :style="{
                        background: 'var(--ma-surface)',
                        border: `1px solid var(--ma-border)`,
                    }">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                        <span style="font-family:'DM Mono',monospace; font-size:11px; text-transform:uppercase; letter-spacing:1.5px; color:var(--ma-text-2);">
                            etapa {{ String(idx + 1).padStart(2, '0') }}
                        </span>
                        <span style="font-family:'DM Mono',monospace; font-size:11px; text-transform:uppercase; letter-spacing:1.5px; color:var(--ma-text-2);">disponível</span>
                    </div>
                    <h2 style="font-size:22px; font-weight:700; color:var(--ma-text); margin:0 0 8px; line-height:1.25;">{{ section.title }}</h2>
                    <p v-if="section.description" style="font-size:13px; color:var(--ma-text-2); margin:0 0 14px; line-height:1.5;">{{ section.description }}</p>
                    <span v-else style="font-size:13px; color:var(--ma-text-2); display:block; margin-bottom:14px;">
                        {{ countLessons(section) }} aula{{ countLessons(section) !== 1 ? 's' : '' }}
                    </span>
                    <Link v-if="firstModuleLink(section)"
                        :href="firstModuleLink(section)"
                        style="font-size:14px; font-weight:600; text-decoration:none; font-family:'DM Mono',monospace;"
                        :style="{ color: primaryColor }">
                        começar →
                    </Link>
                </div>

                <!-- ── CARD BLOQUEADA (drip) ─────────────────────────────── -->
                <div v-else-if="etapaStatus(section) === 'locked'"
                    style="border-radius:14px; padding:20px 22px; background:var(--ma-surface); border:1px solid var(--ma-border); opacity:0.45; cursor:default;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                        <span style="font-family:'DM Mono',monospace; font-size:11px; text-transform:uppercase; letter-spacing:1.5px; color:var(--ma-text-2);">
                            etapa {{ String(idx + 1).padStart(2, '0') }}
                        </span>
                        <span style="font-size:16px;">🔒</span>
                    </div>
                    <h2 style="font-size:20px; font-weight:600; color:var(--ma-text-2); margin:0;">{{ section.title }}</h2>
                </div>

                <!-- ── CARD BLOQUEADA (compra) ───────────────────────────── -->
                <a v-else-if="etapaStatus(section) === 'purchase_locked'"
                    :href="checkout_url"
                    style="border-radius:14px; padding:20px 22px; background:var(--ma-surface); border:1px solid var(--ma-border); display:block; text-decoration:none; cursor:pointer; transition:border-color 0.2s;"
                    onmouseover="this.style.borderColor='#f59e0b'" onmouseout="this.style.borderColor='var(--ma-border)'">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                        <span style="font-family:'DM Mono',monospace; font-size:11px; text-transform:uppercase; letter-spacing:1.5px; color:var(--ma-text-2);">
                            etapa {{ String(idx + 1).padStart(2, '0') }}
                        </span>
                        <span style="font-size:13px; font-weight:700; padding:4px 10px; border-radius:20px; background:rgba(245,158,11,0.15); color:#f59e0b; border:1px solid rgba(245,158,11,0.3); font-family:'DM Mono',monospace;">
                            🔐 Conteúdo pago
                        </span>
                    </div>
                    <h2 style="font-size:20px; font-weight:600; color:var(--ma-text); margin:0 0 12px;">{{ section.title }}</h2>
                    <span style="font-size:13px; font-weight:600; color:#f59e0b; font-family:'DM Mono',monospace;">comprar para acessar →</span>
                </a>

            </template>

            <div v-if="!sections.length" style="text-align:center; padding:60px 24px; color:var(--ma-text-2); font-size:15px;">
                Nenhum conteúdo disponível ainda.
            </div>
        </div>

        <!-- ── MEGA RELATÓRIO ───────────────────────────────────────── -->
        <div v-if="canGenerate && user_has_access" style="margin-top:32px; text-align:center;">

            <div v-if="generateError" style="margin-bottom:12px; padding:10px 14px; border-radius:10px; background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.25); color:#f87171; font-size:13px;">
                {{ generateError }}
            </div>

            <!-- Já tem relatório: ir direto -->
            <Link v-if="mega_report"
                :href="`${memberBase}/resultados`"
                style="display:inline-flex; align-items:center; gap:10px; padding:14px 28px; border-radius:14px; font-size:15px; font-weight:700; text-decoration:none; font-family:'DM Mono',monospace; letter-spacing:0.02em;"
                :style="{ background: primaryColor, color: '#0D1F2D' }">
                Ver Mega Relatório →
            </Link>

            <!-- Sem relatório: gerar -->
            <button v-else type="button" @click="generateMegaRelatorio" :disabled="generating"
                style="display:inline-flex; align-items:center; gap:10px; padding:14px 28px; border-radius:14px; font-size:15px; font-weight:700; cursor:pointer; border:none; font-family:'DM Mono',monospace; letter-spacing:0.02em; transition:opacity 0.2s;"
                :style="{
                    background: generating ? 'var(--ma-surface-2)' : primaryColor,
                    color: generating ? 'var(--ma-text-2)' : '#0D1F2D',
                    opacity: generating ? '0.7' : '1',
                    cursor: generating ? 'not-allowed' : 'pointer',
                }">
                <svg v-if="!generating" style="width:18px;height:18px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                </svg>
                <svg v-else style="width:18px;height:18px;animation:spin 1s linear infinite;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                {{ generating ? 'Analisando suas respostas...' : 'Gerar Mega Relatório' }}
            </button>
            <p v-if="generating" style="margin-top:10px; font-size:12px; color:var(--ma-text-2);">Isso pode levar alguns segundos...</p>
        </div>
    </div>
</template>

<style scoped>
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
</style>
