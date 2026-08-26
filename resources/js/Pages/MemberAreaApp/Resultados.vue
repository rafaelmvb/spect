<script setup>
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import { CheckCircle2, Clock, AlertTriangle, FileText, Download, ChevronLeft, Lock, ArrowRight, Loader2 } from 'lucide-vue-next';
import MemberAreaAppLayout from '@/Layouts/MemberAreaAppLayout.vue';
import { useMemberBase } from '@/composables/useMemberBase';
import axios from 'axios';

defineOptions({ layout: MemberAreaAppLayout });

const props = defineProps({
    courses:  { type: Array,  default: () => [] },
    product:  { type: Object, required: true },
    config:   { type: Object, default: () => ({}) },
    base_url: { type: String, default: '' },
    slug:     { type: String, required: true },
});

const memberBase = useMemberBase(computed(() => props.slug));

// Estado local dos cursos para atualizar reativamento após solicitar
const courses = ref(props.courses.map(c => ({ ...c })));

function reportPrintUrl(insightId) {
    if (!insightId) return null;
    return `${memberBase.value}/relatorio-ia/${insightId}/imprimir`;
}

// Troca de produto ativo
const switchingId = ref(null);

async function switchToProduct(course) {
    if (switchingId.value) return;
    switchingId.value = course.id;
    try {
        const { data } = await axios.post(`${memberBase.value}/trocar-produto`, { product_id: course.id });
        if (data.ok) router.visit(data.redirect ?? '/m/modulos');
    } catch {
        alert('Erro ao trocar de produto. Tente novamente.');
        switchingId.value = null;
    }
}

// Modal de confirmação
const confirmCourse = ref(null);
const requesting    = ref(false);

function openConfirm(course) {
    confirmCourse.value = course;
}

async function confirmar() {
    if (requesting.value || !confirmCourse.value) return;
    requesting.value = true;
    try {
        const { data } = await axios.post(`${memberBase.value}/resultados/solicitar`, {
            product_id: confirmCourse.value.id,
        });
        const idx = courses.value.findIndex(c => c.id === confirmCourse.value.id);
        if (idx !== -1) {
            courses.value[idx].request_status = data.status;
            courses.value[idx].approved_at    = data.approved_at;
            courses.value[idx].insight_id     = data.insight_id ?? null;
        }
        confirmCourse.value = null;
    } catch (e) {
        alert(e?.response?.data?.message || 'Erro ao solicitar.');
    } finally {
        requesting.value = false;
    }
}
</script>

<template>
    <div class="mx-auto max-w-2xl space-y-6">

        <div class="flex items-center gap-3">
            <Link :href="memberBase" class="text-zinc-500 transition hover:text-white"><ChevronLeft class="h-5 w-5" /></Link>
            <div>
                <h1 class="text-xl font-bold text-white">Mega Relatórios</h1>
                <p class="text-sm text-zinc-500">Acompanhe seu progresso e solicite relatórios</p>
            </div>
        </div>

        <!-- Lista de cursos -->
        <div class="space-y-3">
            <div
                v-for="course in courses"
                :key="course.id"
                class="rounded-2xl border border-[var(--ma-border)] bg-[var(--ma-surface)] p-5"
            >
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">

                    <!-- Info do curso -->
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center justify-between gap-2 flex-wrap">
                            <h2 class="font-semibold text-white">{{ course.name }}</h2>
                            <button type="button"
                                :disabled="!!switchingId"
                                class="flex shrink-0 items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition"
                                style="background:rgba(255,255,255,0.08); color:rgba(255,255,255,0.7);"
                                @click="switchToProduct(course)">
                                <Loader2 v-if="switchingId === course.id" class="h-3 w-3 animate-spin" />
                                <ArrowRight v-else class="h-3 w-3" />
                                {{ switchingId === course.id ? 'Carregando...' : 'Acessar trilha' }}
                            </button>
                        </div>

                        <!-- Barra de progresso -->
                        <div class="mt-2 flex items-center gap-2">
                            <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-zinc-700">
                                <div
                                    class="h-full rounded-full bg-[var(--ma-primary)] transition-all duration-500"
                                    :style="{ width: `${Math.min(course.progress_percent, 100)}%` }"
                                />
                            </div>
                            <span class="shrink-0 text-xs font-medium text-zinc-400">{{ course.progress_percent }}%</span>
                        </div>

                        <!-- Status conclusão -->
                        <div class="mt-2 flex items-center gap-1.5">
                            <CheckCircle2 v-if="course.completed" class="h-4 w-4 text-emerald-400" />
                            <Clock v-else class="h-4 w-4 text-amber-400" />
                            <span :class="['text-xs font-medium', course.completed ? 'text-emerald-400' : 'text-amber-400']">
                                {{ course.completed ? 'Concluído' : 'Em andamento' }}
                            </span>
                        </div>
                    </div>

                    <!-- Ação / Status da solicitação -->
                    <div class="shrink-0">

                        <!-- Sem solicitação: bloqueado por quiz < 60% -->
                        <div v-if="!course.request_status && !course.can_request" class="flex flex-col items-end gap-1.5">
                            <div class="flex items-center gap-2 rounded-xl border border-zinc-700 px-4 py-2 text-sm font-medium text-zinc-500 cursor-not-allowed">
                                <Lock class="h-4 w-4" /> Relatório bloqueado
                            </div>
                            <p class="text-xs text-zinc-500">Responda 60% dos quizzes ({{ course.quiz_percent }}% feito)</p>
                        </div>

                        <!-- Sem solicitação: botão solicitar -->
                        <button
                            v-else-if="!course.request_status"
                            type="button"
                            class="flex items-center gap-2 rounded-xl border border-[var(--ma-primary)] px-4 py-2 text-sm font-medium text-[var(--ma-primary)] transition hover:bg-[var(--ma-primary)]/10"
                            @click="openConfirm(course)"
                        >
                            <FileText class="h-4 w-4" /> Solicitar relatório
                        </button>

                        <!-- Aguardando aprovação -->
                        <div v-else-if="course.request_status === 'pending'" class="flex items-center gap-2 rounded-xl bg-amber-500/10 px-4 py-2 text-sm font-medium text-amber-400">
                            <Clock class="h-4 w-4" /> Aguardando aprovação
                        </div>

                        <!-- Aprovado / Disponível -->
                        <div v-else-if="course.request_status === 'approved'" class="flex flex-col items-end gap-2">
                            <div class="flex items-center gap-2 rounded-xl bg-emerald-500/10 px-4 py-2 text-sm font-medium text-emerald-400">
                                <CheckCircle2 class="h-4 w-4" /> Relatório aprovado
                            </div>
                            <p v-if="course.approved_at" class="text-[11px] text-zinc-500">{{ course.approved_at }}</p>
                            <a
                                v-if="course.insight_id"
                                :href="reportPrintUrl(course.insight_id)"
                                target="_blank"
                                class="ma-btn-primary flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-medium transition"
                            >
                                <Download class="h-4 w-4" /> Baixar relatório (PDF)
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="!courses.length" class="rounded-2xl border border-dashed border-[var(--ma-border)] py-16 text-center text-zinc-500">
                Nenhum curso encontrado.
            </div>
        </div>

        <!-- Modal de confirmação -->
        <Teleport to="body">
            <div v-if="confirmCourse" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4">
                <div class="w-full max-w-sm rounded-2xl bg-zinc-900 border border-zinc-700 p-6 shadow-2xl">

                    <div class="mb-4 flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-amber-500/15">
                            <AlertTriangle class="h-5 w-5 text-amber-400" />
                        </div>
                        <h3 class="font-semibold text-white">Solicitar Relatório</h3>
                    </div>

                    <p class="text-sm text-zinc-400 leading-relaxed">
                        Você está solicitando o relatório de
                        <strong class="text-white">{{ confirmCourse.name }}</strong>.
                    </p>

                    <div v-if="!confirmCourse.completed" class="mt-3 rounded-xl bg-amber-500/10 border border-amber-500/20 px-4 py-3 text-sm text-amber-300">
                        <strong>Atenção:</strong> Este curso ainda não foi concluído ({{ confirmCourse.progress_percent }}% completo).
                        O relatório pode não refletir 100% do seu desempenho.
                    </div>

                    <p class="mt-3 text-xs text-zinc-500">
                        Após solicitar, você não poderá solicitar novamente até que o administrador gere um novo relatório.
                    </p>

                    <div class="mt-5 flex gap-3">
                        <button
                            type="button"
                            class="flex-1 rounded-xl border border-[var(--ma-border)] px-4 py-2 text-sm text-zinc-400 transition hover:border-zinc-500 hover:text-white"
                            @click="confirmCourse = null"
                        >
                            Cancelar
                        </button>
                        <button
                            type="button"
                            :disabled="requesting"
                            class="ma-btn-primary flex-1 rounded-xl px-4 py-2 text-sm font-medium transition disabled:opacity-60"
                            @click="confirmar"
                        >
                            {{ requesting ? 'Solicitando...' : 'Continuar assim mesmo' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
