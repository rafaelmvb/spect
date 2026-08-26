<script setup>
import { ref, reactive, computed } from 'vue';
import MemberAreaAppLayout from '@/Layouts/MemberAreaAppLayout.vue';
import { ClipboardCheck, ChevronLeft, CheckCircle2, Send, Loader2 } from 'lucide-vue-next';
import { useMemberBase } from '@/composables/useMemberBase';

defineOptions({ layout: MemberAreaAppLayout });

const props = defineProps({
    product:     { type: Object, required: true },
    checkpoint:  { type: Object, required: true },
    questions:   { type: Array, default: () => [] },
    already_done:{ type: Boolean, default: false },
    answers:     { type: Object, default: () => ({}) },
    base_url:    { type: String, default: '' },
    slug:        { type: String, default: '' },
});

// Estado das respostas (pre-fill se já respondeu)
const form = reactive(Object.fromEntries(
    props.questions.map(q => [q.id, props.answers[q.id] ?? (q.type === 'checkbox' ? [] : '')])
));

const submitting = ref(false);
const done       = ref(props.already_done);
const error      = ref('');

const memberBase = useMemberBase(computed(() => props.slug));
function csrfToken() { return document.querySelector('meta[name="csrf-token"]')?.content ?? ''; }

function toggleCheck(qId, opt) {
    const arr = form[qId];
    const idx = arr.indexOf(opt);
    if (idx >= 0) arr.splice(idx, 1);
    else arr.push(opt);
}

async function submit() {
    // Validar obrigatórias
    for (const q of props.questions) {
        if (!q.required) continue;
        const v = form[q.id];
        if (v === '' || v === null || (Array.isArray(v) && v.length === 0)) {
            error.value = `A pergunta "${q.label}" é obrigatória.`;
            return;
        }
    }
    error.value = '';
    submitting.value = true;
    try {
        const url = `${props.base_url}/checkpoints/${props.checkpoint.id}/responder`;
        await window.axios.post(url, { answers: form }, {
            headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
        });
        done.value = true;
    } catch (e) {
        error.value = e.response?.data?.message ?? 'Erro ao enviar respostas. Tente novamente.';
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <div class="mx-auto max-w-xl space-y-6 px-4 py-8">
        <!-- Back -->
        <a :href="`${memberBase}/jornadas`"
            class="inline-flex items-center gap-1 text-sm font-medium text-zinc-500 hover:text-purple-600 dark:hover:text-purple-400">
            <ChevronLeft class="h-4 w-4" /> Voltar
        </a>

        <!-- Header -->
        <div class="flex items-start gap-3">
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-amber-100 dark:bg-amber-900/30">
                <ClipboardCheck class="h-6 w-6 text-amber-600 dark:text-amber-400" />
            </div>
            <div>
                <h1 class="text-xl font-bold text-zinc-900 dark:text-white">{{ checkpoint.title }}</h1>
                <p v-if="checkpoint.description" class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                    {{ checkpoint.description }}
                </p>
            </div>
        </div>

        <!-- Já respondeu -->
        <div v-if="done" class="flex flex-col items-center gap-4 rounded-2xl border border-emerald-200 bg-emerald-50 p-8 text-center dark:border-emerald-800/40 dark:bg-emerald-900/10">
            <CheckCircle2 class="h-12 w-12 text-emerald-500" />
            <div>
                <p class="text-lg font-bold text-emerald-800 dark:text-emerald-300">Checkpoint concluído!</p>
                <p class="text-sm text-emerald-600 dark:text-emerald-400">Suas respostas foram registradas com sucesso.</p>
            </div>
            <a :href="`${memberBase}/jornadas`"
                class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">
                Voltar às jornadas
            </a>
        </div>

        <!-- Formulário -->
        <template v-else>
            <div class="space-y-5">
                <div v-for="q in questions" :key="q.id"
                    class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800/60">
                    <label class="mb-3 block text-sm font-semibold text-zinc-800 dark:text-zinc-100">
                        {{ q.label }}
                        <span v-if="q.required" class="ml-1 text-red-500">*</span>
                    </label>

                    <!-- text -->
                    <input v-if="q.type === 'text'" v-model="form[q.id]" type="text"
                        class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm focus:border-purple-400 focus:outline-none dark:border-zinc-700 dark:bg-zinc-900 dark:text-white" />

                    <!-- textarea -->
                    <textarea v-else-if="q.type === 'textarea'" v-model="form[q.id]" rows="4"
                        class="w-full resize-none rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm focus:border-purple-400 focus:outline-none dark:border-zinc-700 dark:bg-zinc-900 dark:text-white" />

                    <!-- radio -->
                    <div v-else-if="q.type === 'radio'" class="space-y-2">
                        <label v-for="opt in q.options" :key="opt"
                            class="flex cursor-pointer items-center gap-3 rounded-xl border p-3 transition"
                            :class="form[q.id] === opt
                                ? 'border-purple-400 bg-purple-50 dark:border-purple-700 dark:bg-purple-900/20'
                                : 'border-zinc-200 hover:border-purple-200 dark:border-zinc-700'">
                            <input type="radio" :name="`q${q.id}`" :value="opt" v-model="form[q.id]"
                                class="h-4 w-4 accent-purple-600" />
                            <span class="text-sm text-zinc-700 dark:text-zinc-300">{{ opt }}</span>
                        </label>
                    </div>

                    <!-- checkbox -->
                    <div v-else-if="q.type === 'checkbox'" class="space-y-2">
                        <label v-for="opt in q.options" :key="opt"
                            class="flex cursor-pointer items-center gap-3 rounded-xl border p-3 transition"
                            :class="form[q.id].includes(opt)
                                ? 'border-purple-400 bg-purple-50 dark:border-purple-700 dark:bg-purple-900/20'
                                : 'border-zinc-200 hover:border-purple-200 dark:border-zinc-700'">
                            <input type="checkbox" :value="opt"
                                :checked="form[q.id].includes(opt)"
                                class="h-4 w-4 rounded accent-purple-600"
                                @change="toggleCheck(q.id, opt)" />
                            <span class="text-sm text-zinc-700 dark:text-zinc-300">{{ opt }}</span>
                        </label>
                    </div>

                    <!-- scale -->
                    <div v-else-if="q.type === 'scale'" class="space-y-2">
                        <div class="flex justify-between text-xs text-zinc-400">
                            <span>Muito baixo</span><span>Muito alto</span>
                        </div>
                        <div class="flex gap-2">
                            <button v-for="n in [1,2,3,4,5,6,7,8,9,10]" :key="n" type="button"
                                class="flex h-9 w-9 items-center justify-center rounded-xl border text-sm font-bold transition"
                                :class="form[q.id] == n
                                    ? 'border-purple-500 bg-purple-600 text-white'
                                    : 'border-zinc-200 text-zinc-500 hover:border-purple-300 dark:border-zinc-700'"
                                @click="form[q.id] = n">
                                {{ n }}
                            </button>
                        </div>
                    </div>

                    <!-- date -->
                    <input v-else-if="q.type === 'date'" v-model="form[q.id]" type="date"
                        class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm focus:border-purple-400 focus:outline-none dark:border-zinc-700 dark:bg-zinc-900 dark:text-white" />
                </div>
            </div>

            <!-- Erro -->
            <div v-if="error" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/40 dark:bg-red-900/10 dark:text-red-400">
                {{ error }}
            </div>

            <!-- Submit -->
            <button type="button" :disabled="submitting" @click="submit"
                class="flex w-full items-center justify-center gap-2 rounded-xl bg-purple-600 px-4 py-3 text-sm font-bold text-white transition hover:bg-purple-700 disabled:opacity-60">
                <Loader2 v-if="submitting" class="h-4 w-4 animate-spin" />
                <Send v-else class="h-4 w-4" />
                {{ submitting ? 'Enviando...' : 'Enviar respostas' }}
            </button>
        </template>
    </div>
</template>
