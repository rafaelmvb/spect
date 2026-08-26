<script setup>
import { ref } from 'vue';
import LayoutInfoprodutor from '@/Layouts/LayoutInfoprodutor.vue';
import { CheckCircle, Globe, GlobeLock, Save, ChevronLeft, Loader2, ChevronDown, ChevronUp } from 'lucide-vue-next';
import { router } from '@inertiajs/vue3';

defineOptions({ layout: LayoutInfoprodutor });

const props = defineProps({
    report: { type: Object, required: true },
});

const toast      = ref(null);
const saving     = ref(false);
const publishing = ref(false);

const adminNotes    = ref(props.report.admin_notes ?? '');
const customContent = ref(props.report.custom_content ?? '');
const expanded      = ref({});

function csrf() { return document.querySelector('meta[name="csrf-token"]')?.content ?? ''; }

function showToast(msg, type = 'success') {
    toast.value = { msg, type };
    setTimeout(() => toast.value = null, 4000);
}

function toggleStep(i) { expanded.value[i] = !expanded.value[i]; }

async function save() {
    saving.value = true;
    try {
        await window.axios.put(`/relatorios/trilhas/${props.report.id}`, {
            admin_notes:    adminNotes.value,
            custom_content: customContent.value,
        }, { headers: { 'X-CSRF-TOKEN': csrf() } });
        showToast('Relatório salvo.');
    } catch (e) {
        showToast(e.response?.data?.message ?? 'Erro ao salvar.', 'error');
    } finally {
        saving.value = false;
    }
}

async function togglePublish() {
    publishing.value = true;
    const isPublished = props.report.status === 'published';
    const url = `/relatorios/trilhas/${props.report.id}/${isPublished ? 'despublicar' : 'publicar'}`;
    try {
        await window.axios.post(url, {}, { headers: { 'X-CSRF-TOKEN': csrf() } });
        showToast(isPublished ? 'Relatório despublicado.' : 'Relatório publicado para o aluno.');
        router.reload({ only: ['report'] });
    } catch (e) {
        showToast(e.response?.data?.message ?? 'Erro.', 'error');
    } finally {
        publishing.value = false;
    }
}

const data = props.report.report_data ?? {};
</script>

<template>
    <div class="max-w-4xl mx-auto space-y-6">

        <!-- Header -->
        <div class="flex items-center gap-4 flex-wrap">
            <button @click="router.visit('/relatorios/trilhas')"
                class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-2 text-zinc-500 hover:bg-zinc-50 dark:hover:bg-zinc-800 transition">
                <ChevronLeft class="h-5 w-5" />
            </button>
            <div class="flex-1 min-w-0">
                <h1 class="text-xl font-bold text-zinc-900 dark:text-white truncate">
                    {{ report.user_name }} — {{ report.journey_name }}
                </h1>
                <p class="text-sm text-zinc-500 mt-0.5">Gerado em {{ report.generated_at }} · {{ report.user_email }}</p>
            </div>
            <div class="flex gap-2 shrink-0">
                <button @click="save" :disabled="saving"
                    class="flex items-center gap-2 rounded-xl bg-zinc-900 dark:bg-zinc-100 text-white dark:text-zinc-900 px-4 py-2 text-sm font-semibold transition hover:opacity-90 disabled:opacity-60">
                    <Loader2 v-if="saving" class="h-4 w-4 animate-spin" />
                    <Save v-else class="h-4 w-4" />
                    Salvar
                </button>
                <button @click="togglePublish" :disabled="publishing"
                    class="flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold transition hover:opacity-90 disabled:opacity-60"
                    :class="report.status === 'published'
                        ? 'bg-amber-100 text-amber-700 hover:bg-amber-200'
                        : 'bg-emerald-600 text-white hover:bg-emerald-700'">
                    <Loader2 v-if="publishing" class="h-4 w-4 animate-spin" />
                    <GlobeLock v-else-if="report.status === 'published'" class="h-4 w-4" />
                    <Globe v-else class="h-4 w-4" />
                    {{ report.status === 'published' ? 'Despublicar' : 'Publicar para aluno' }}
                </button>
            </div>
        </div>

        <!-- Toast -->
        <div v-if="toast" class="rounded-xl px-4 py-3 text-sm font-medium border"
            :class="toast.type === 'error' ? 'bg-red-50 text-red-700 border-red-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200'">
            {{ toast.msg }}
        </div>

        <!-- Status badge -->
        <div class="flex items-center gap-3">
            <span class="rounded-full px-3 py-1 text-xs font-semibold"
                :class="{
                    'bg-zinc-100 text-zinc-500': report.status === 'draft',
                    'bg-blue-100 text-blue-700': report.status === 'ready',
                    'bg-emerald-100 text-emerald-700': report.status === 'published',
                }">
                {{ { draft: 'Rascunho', ready: 'Pronto', published: 'Publicado' }[report.status] }}
            </span>
            <span v-if="report.published_at" class="text-xs text-zinc-500">Publicado em {{ report.published_at }}</span>
        </div>

        <!-- Resumo do progresso -->
        <div class="grid grid-cols-3 gap-4">
            <div class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60 p-4 text-center">
                <p class="text-3xl font-bold text-violet-600">{{ data.completion_pct ?? 0 }}%</p>
                <p class="text-xs text-zinc-500 mt-1">Conclusão da trilha</p>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60 p-4 text-center">
                <p class="text-3xl font-bold text-zinc-900 dark:text-white">{{ data.completed_steps ?? 0 }}/{{ data.total_steps ?? 0 }}</p>
                <p class="text-xs text-zinc-500 mt-1">Etapas concluídas</p>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60 p-4 text-center">
                <p class="text-3xl font-bold text-emerald-600">{{ (data.neuro_scores ?? []).length }}</p>
                <p class="text-xs text-zinc-500 mt-1">Indicadores avaliados</p>
            </div>
        </div>

        <!-- Etapas com respostas -->
        <div class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60 overflow-hidden">
            <div class="px-5 py-4 border-b border-zinc-100 dark:border-zinc-700">
                <h2 class="font-semibold text-zinc-900 dark:text-white">Etapas da trilha</h2>
            </div>
            <div class="divide-y divide-zinc-100 dark:divide-zinc-700">
                <div v-for="(step, i) in (data.steps ?? [])" :key="step.id">
                    <!-- Header da etapa -->
                    <button class="w-full flex items-center gap-3 px-5 py-4 text-left hover:bg-zinc-50 dark:hover:bg-zinc-700/50 transition"
                        @click="toggleStep(i)">
                        <span class="h-6 w-6 rounded-full flex items-center justify-center shrink-0 text-xs font-bold"
                            :class="step.completed ? 'bg-emerald-100 text-emerald-700' : 'bg-zinc-100 text-zinc-500'">
                            {{ i + 1 }}
                        </span>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-zinc-900 dark:text-white">{{ step.title }}</p>
                            <p v-if="step.completed_at" class="text-xs text-zinc-400">Concluído em {{ step.completed_at }}</p>
                            <p v-else class="text-xs text-zinc-400">Não concluído</p>
                        </div>
                        <CheckCircle v-if="step.completed" class="h-5 w-5 text-emerald-500 shrink-0" />
                        <span v-else class="h-5 w-5 rounded-full border-2 border-zinc-300 dark:border-zinc-600 shrink-0" />
                        <ChevronDown v-if="!expanded[i]" class="h-4 w-4 text-zinc-400 shrink-0" />
                        <ChevronUp v-else class="h-4 w-4 text-zinc-400 shrink-0" />
                    </button>

                    <!-- Checkpoint expandido -->
                    <div v-if="expanded[i] && step.checkpoint" class="px-5 pb-5">
                        <div class="ml-9 rounded-xl border border-violet-100 dark:border-violet-900/30 bg-violet-50/50 dark:bg-violet-900/10 p-4 space-y-4">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-semibold text-violet-700 dark:text-violet-300">{{ step.checkpoint.name }}</p>
                                <p v-if="step.checkpoint.responded_at" class="text-xs text-zinc-400">{{ step.checkpoint.responded_at }}</p>
                            </div>
                            <div v-if="step.checkpoint.questions?.length" class="space-y-3">
                                <div v-for="(q, qi) in step.checkpoint.questions" :key="qi"
                                    class="rounded-lg bg-white dark:bg-zinc-800 border border-zinc-100 dark:border-zinc-700 p-3">
                                    <p class="text-xs font-medium text-zinc-500 mb-1">{{ q.label }}</p>
                                    <p v-if="q.answer" class="text-sm text-zinc-900 dark:text-white whitespace-pre-line">{{ q.answer }}</p>
                                    <p v-else class="text-xs text-zinc-400 italic">Sem resposta</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else-if="expanded[i] && !step.checkpoint" class="px-5 pb-4 ml-9 text-xs text-zinc-400">
                        Esta etapa não tem checkpoint.
                    </div>
                </div>
            </div>
        </div>

        <!-- NeuroMap scores -->
        <div v-if="(data.neuro_scores ?? []).length" class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60 p-5">
            <h2 class="font-semibold text-zinc-900 dark:text-white mb-4">Indicadores NeuroMap</h2>
            <div class="space-y-2">
                <div v-for="s in data.neuro_scores" :key="s.indicator" class="flex items-center gap-3">
                    <span class="text-xs text-zinc-500 w-32 truncate">{{ s.indicator }}</span>
                    <div class="flex-1 bg-zinc-100 dark:bg-zinc-700 rounded-full h-2">
                        <div class="h-2 rounded-full bg-violet-600" :style="`width:${Math.min(s.value * 10, 100)}%`" />
                    </div>
                    <span class="text-xs font-bold text-zinc-900 dark:text-white w-10 text-right">{{ Number(s.value).toFixed(1) }}</span>
                </div>
            </div>
        </div>

        <!-- Resumo IA (read-only se existir) -->
        <div v-if="report.ai_summary" class="rounded-2xl border border-blue-100 bg-blue-50/50 dark:border-blue-900/20 dark:bg-blue-900/10 p-5">
            <h2 class="font-semibold text-blue-700 dark:text-blue-300 mb-2">Resumo gerado por IA</h2>
            <p class="text-sm text-zinc-700 dark:text-zinc-300 whitespace-pre-line">{{ report.ai_summary }}</p>
        </div>

        <!-- Notas do admin -->
        <div class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60 p-5 space-y-3">
            <h2 class="font-semibold text-zinc-900 dark:text-white">Observações do administrador</h2>
            <p class="text-xs text-zinc-400">Visível apenas internamente. Não aparece para o aluno.</p>
            <textarea v-model="adminNotes" rows="4" placeholder="Adicione observações internas..."
                class="w-full rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900 px-4 py-3 text-sm text-zinc-900 dark:text-white focus:border-violet-500 focus:outline-none resize-none" />
        </div>

        <!-- Conteúdo personalizado -->
        <div class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60 p-5 space-y-3">
            <h2 class="font-semibold text-zinc-900 dark:text-white">Conteúdo personalizado</h2>
            <p class="text-xs text-zinc-400">Texto adicional que aparece para o aluno no relatório (feedback, recomendações etc.).</p>
            <textarea v-model="customContent" rows="6" placeholder="Escreva um feedback personalizado para o aluno..."
                class="w-full rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900 px-4 py-3 text-sm text-zinc-900 dark:text-white focus:border-violet-500 focus:outline-none resize-none" />
        </div>

        <!-- Botões de ação finais -->
        <div class="flex justify-end gap-3 pb-6">
            <button @click="save" :disabled="saving"
                class="flex items-center gap-2 rounded-xl bg-zinc-900 dark:bg-white text-white dark:text-zinc-900 px-5 py-2.5 text-sm font-semibold transition hover:opacity-90 disabled:opacity-60">
                <Loader2 v-if="saving" class="h-4 w-4 animate-spin" />
                <Save v-else class="h-4 w-4" />
                Salvar alterações
            </button>
        </div>
    </div>
</template>
