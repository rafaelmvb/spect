<script setup>
import { ref, nextTick, watch } from 'vue';
import { Sparkles, Send, Trash2, ShieldCheck } from 'lucide-vue-next';

const props = defineProps({
    pacienteId: { type: [String, Number], required: true },
    pacienteNome: { type: String, default: '' },
});

const mensagens = ref([]);
const pergunta = ref('');
const carregando = ref(true);
const enviando = ref(false);
const erro = ref('');
const listaRef = ref(null);

const SUGESTOES = [
    'Quais foram os principais gatilhos citados nas últimas sessões?',
    'Cruze o último teste com os relatos sobre a família.',
    'Resuma a evolução dos últimos dois meses.',
];

async function rolarParaOFim() {
    await nextTick();
    if (listaRef.value) listaRef.value.scrollTop = listaRef.value.scrollHeight;
}

async function carregar() {
    carregando.value = true;
    erro.value = '';
    try {
        const { data } = await window.axios.get(`/p/meus-pacientes/${props.pacienteId}/copiloto`);
        mensagens.value = data.mensagens ?? [];
        rolarParaOFim();
    } catch (e) {
        erro.value = e?.response?.status === 403
            ? 'Este paciente ainda não autorizou o seu acesso aos dados dele.'
            : 'Não foi possível abrir a conversa.';
    } finally {
        carregando.value = false;
    }
}

async function enviar(texto = null) {
    const conteudo = (texto ?? pergunta.value).trim();
    if (conteudo.length < 3 || enviando.value) return;

    enviando.value = true;
    erro.value = '';
    pergunta.value = '';

    // Mostra a pergunta na hora: esperar a IA para ver o que se digitou é ruim.
    mensagens.value.push({ id: `local-${Date.now()}`, role: 'user', content: conteudo });
    rolarParaOFim();

    try {
        const { data } = await window.axios.post(`/p/meus-pacientes/${props.pacienteId}/copiloto`, {
            pergunta: conteudo,
        });
        mensagens.value.push(data.resposta);
    } catch (e) {
        erro.value = e?.response?.data?.message || 'A IA não respondeu. Tente de novo.';
    } finally {
        enviando.value = false;
        rolarParaOFim();
    }
}

async function limpar() {
    if (!window.confirm('Apagar esta conversa? O histórico não volta.')) return;
    try {
        await window.axios.delete(`/p/meus-pacientes/${props.pacienteId}/copiloto`);
        mensagens.value = [];
    } catch {
        erro.value = 'Não foi possível apagar a conversa.';
    }
}

watch(() => props.pacienteId, carregar, { immediate: true });
</script>

<template>
    <section class="flex h-[32rem] flex-col rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">

        <header class="flex items-center justify-between gap-3 border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
            <div class="flex min-w-0 items-center gap-2">
                <Sparkles class="h-5 w-5 shrink-0 text-violet-600 dark:text-violet-400" />
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-zinc-900 dark:text-white">Copiloto Clínico</p>
                    <p class="truncate text-xs text-zinc-500 dark:text-zinc-400">
                        Sobre {{ pacienteNome || 'este paciente' }}
                    </p>
                </div>
            </div>
            <button v-if="mensagens.length" type="button" @click="limpar" aria-label="Apagar conversa"
                class="rounded-lg border border-zinc-200 p-1.5 text-zinc-500 transition hover:border-red-300 hover:text-red-600 dark:border-zinc-600 dark:text-zinc-400 dark:hover:border-red-700">
                <Trash2 class="h-4 w-4" />
            </button>
        </header>

        <div ref="listaRef" class="flex-1 space-y-3 overflow-y-auto px-4 py-4">
            <p v-if="carregando" class="text-sm text-zinc-500 dark:text-zinc-400">Carregando…</p>

            <template v-else-if="!mensagens.length">
                <div class="flex items-start gap-2 rounded-xl bg-zinc-50 p-3 text-sm text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                    <ShieldCheck class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                    <span>
                        O Copiloto lê apenas o que você já vê na ficha: os testes que
                        <strong>você</strong> aplicou, suas anotações e as transcrições autorizadas.
                        A conversa é sua — nenhum outro profissional a acessa.
                    </span>
                </div>

                <p class="pt-2 text-xs font-medium uppercase tracking-wide text-zinc-400">Comece por</p>
                <button v-for="s in SUGESTOES" :key="s" type="button" @click="enviar(s)"
                    class="block w-full rounded-xl border border-zinc-200 px-3 py-2 text-left text-sm text-zinc-700 transition hover:border-violet-400 hover:text-violet-700 dark:border-zinc-600 dark:text-zinc-300 dark:hover:border-violet-600">
                    {{ s }}
                </button>
            </template>

            <template v-else>
                <div v-for="m in mensagens" :key="m.id"
                    :class="['flex', m.role === 'user' ? 'justify-end' : 'justify-start']">
                    <div :class="[
                        'max-w-[85%] whitespace-pre-wrap rounded-2xl px-3.5 py-2.5 text-sm',
                        m.role === 'user'
                            ? 'bg-violet-600 text-white'
                            : 'bg-zinc-100 text-zinc-800 dark:bg-zinc-800 dark:text-zinc-100',
                    ]">
                        {{ m.content }}
                    </div>
                </div>
            </template>

            <div v-if="enviando" class="flex justify-start">
                <div class="rounded-2xl bg-zinc-100 px-3.5 py-2.5 text-sm text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                    Analisando o caso…
                </div>
            </div>
        </div>

        <p v-if="erro" class="border-t border-zinc-200 px-4 py-2 text-sm text-amber-600 dark:border-zinc-700 dark:text-amber-400">
            {{ erro }}
        </p>

        <form class="flex gap-2 border-t border-zinc-200 p-3 dark:border-zinc-700" @submit.prevent="enviar()">
            <label for="pergunta-copiloto" class="sr-only">Pergunta ao Copiloto</label>
            <input id="pergunta-copiloto" v-model="pergunta" type="text" maxlength="2000"
                placeholder="Pergunte sobre o caso…" :disabled="enviando"
                class="flex-1 rounded-xl border border-zinc-200 bg-white px-3 py-2.5 text-sm text-zinc-900 focus:border-violet-400 focus:outline-none disabled:opacity-60 dark:border-zinc-600 dark:bg-zinc-800 dark:text-white" />
            <button type="submit" :disabled="enviando || pergunta.trim().length < 3" aria-label="Enviar pergunta"
                class="rounded-xl bg-violet-600 px-4 py-2.5 text-white transition hover:bg-violet-700 disabled:opacity-50">
                <Send class="h-4 w-4" />
            </button>
        </form>

    </section>
</template>
