<template>
    <Teleport to="body">
        <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0" enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in" leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" @click.self="$emit('close')">
                <div class="w-full max-w-3xl max-h-[90vh] overflow-y-auto rounded-2xl bg-white shadow-2xl dark:bg-zinc-900">
                    <!-- Header -->
                    <div class="flex items-center justify-between border-b border-zinc-200 px-6 py-4 dark:border-zinc-700">
                        <div>
                            <h2 class="text-base font-semibold text-zinc-900 dark:text-white">Relatório do Quiz</h2>
                            <p v-if="report" class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">{{ report.lesson_title }} · {{ report.total_responses }} respostas</p>
                        </div>
                        <button type="button" class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200" @click="$emit('close')">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Loading -->
                    <div v-if="loading" class="flex items-center justify-center py-16">
                        <div class="h-8 w-8 animate-spin rounded-full border-4 border-zinc-200 border-t-emerald-500" />
                    </div>

                    <!-- Erro -->
                    <div v-else-if="error" class="p-8 text-center text-red-500">{{ error }}</div>

                    <!-- Sem respostas -->
                    <div v-else-if="report && report.total_responses === 0" class="p-12 text-center text-zinc-400 dark:text-zinc-500">
                        Nenhum aluno respondeu este quiz ainda.
                    </div>

                    <!-- Conteúdo -->
                    <div v-else-if="report" class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        <!-- Estatísticas por pergunta -->
                        <div class="p-6 space-y-5">
                            <h3 class="text-sm font-semibold text-zinc-700 dark:text-zinc-300 uppercase tracking-wide">Média por pergunta</h3>
                            <div v-for="(stat, i) in report.stats" :key="stat.question_id" class="space-y-2">
                                <div class="flex items-start justify-between gap-4">
                                    <p class="text-sm text-zinc-800 dark:text-zinc-200">
                                        <span class="font-medium text-zinc-400 dark:text-zinc-500 mr-1">{{ i + 1 }}.</span>{{ stat.text }}
                                    </p>
                                    <span class="shrink-0 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                                        média {{ stat.average ?? '—' }}
                                    </span>
                                </div>
                                <!-- Barra de distribuição -->
                                <div class="flex gap-1">
                                    <div
                                        v-for="v in stat.scale_max"
                                        :key="v"
                                        class="flex flex-col items-center gap-0.5"
                                        :style="{ flex: '1' }"
                                    >
                                        <div class="w-full rounded-sm" :style="{
                                            height: `${Math.max(4, ((stat.distribution[v] ?? 0) / stat.count) * 60)}px`,
                                            backgroundColor: 'var(--color-primary, #10b981)',
                                            opacity: 0.3 + ((stat.distribution[v] ?? 0) / stat.count) * 0.7
                                        }" />
                                        <span class="text-[10px] text-zinc-400">{{ v }}</span>
                                    </div>
                                </div>
                                <p class="text-xs text-zinc-400 dark:text-zinc-500">{{ stat.count }} respostas</p>
                            </div>
                        </div>

                        <!-- Respostas individuais -->
                        <div class="p-6 space-y-4">
                            <h3 class="text-sm font-semibold text-zinc-700 dark:text-zinc-300 uppercase tracking-wide">Respostas individuais</h3>
                            <div
                                v-for="r in report.responses"
                                :key="r.id"
                                class="rounded-xl border border-zinc-100 bg-zinc-50 p-4 space-y-3 dark:border-zinc-800 dark:bg-zinc-800/50"
                            >
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-zinc-900 dark:text-white">{{ r.user.name }}</p>
                                        <p class="text-xs text-zinc-400">{{ r.user.email }}</p>
                                    </div>
                                    <span class="text-xs text-zinc-400">{{ r.created_at }}</span>
                                </div>
                                <div class="grid gap-2">
                                    <div v-for="(res, ri) in r.responses" :key="ri" class="flex items-start gap-3">
                                        <span class="shrink-0 mt-0.5 rounded-full bg-zinc-200 dark:bg-zinc-700 px-2 py-0.5 text-xs font-bold text-zinc-600 dark:text-zinc-300">{{ res.value }}</span>
                                        <div>
                                            <p class="text-xs text-zinc-600 dark:text-zinc-400">{{ questionText(res.question_id) }}</p>
                                            <p v-if="res.comment" class="mt-0.5 text-xs italic text-zinc-400 dark:text-zinc-500">"{{ res.comment }}"</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    productId: { type: String, default: null },
    lesson: { type: Object, default: null },
});

defineEmits(['close']);

const loading = ref(false);
const error = ref(null);
const report = ref(null);

watch(() => props.open, async (val) => {
    if (!val || !props.lesson || !props.productId) return;
    loading.value = true;
    error.value = null;
    report.value = null;
    try {
        const res = await window.axios.get(`/produtos/${props.productId}/member-builder/lessons/${props.lesson.id}/quiz-report`);
        report.value = res.data;
    } catch (e) {
        error.value = 'Erro ao carregar relatório.';
    } finally {
        loading.value = false;
    }
});

function questionText(questionId) {
    const questions = props.lesson?.content_files?.questions ?? [];
    return questions.find(q => q.id === questionId)?.text ?? questionId;
}
</script>
