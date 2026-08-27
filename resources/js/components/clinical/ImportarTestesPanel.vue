<script setup>
import { ref, computed } from 'vue';
import { Upload, Download, FileSpreadsheet, AlertTriangle, CheckCircle2 } from 'lucide-vue-next';

const emit = defineEmits(['importado']);

// Documentação das colunas do CSV. Fica no script porque no template as aspas
// de "sim" quebrariam o atributo do v-for.
const COLUNAS = [
    ['teste', 'Nome do teste. Obrigatório, repetido em cada linha dele.'],
    ['pergunta', 'Texto da pergunta. Obrigatório.'],
    ['categoria', 'Ex.: ansiedade, sono, atenção.'],
    ['descricao / instrucoes', 'Textos que o aluno vê antes de começar.'],
    ['minutos', 'Duração estimada. Sem valor, usa 10.'],
    ['infantil', 'Escreva "sim" para rastreio respondido pelo responsável.'],
    ['tipo', 'scale, single, multi ou boolean. Sem valor, usa scale.'],
    ['escala_min / escala_max', 'Extremos da escala. Padrão 1 e 5.'],
    ['opcoes', 'Para single e multi: Nunca=0|Às vezes=1|Sempre=2'],
    ['faixa_min / faixa_max', 'Intervalo de pontuação do resultado.'],
    ['faixa_resultado', 'O que aparece para o aluno nessa faixa.'],
    ['faixa_interpretacao', 'Texto explicativo do resultado.'],
    ['faixa_tags', 'Tags separadas por vírgula, que liberam conteúdo.'],
];


const arquivo = ref(null);
const inputRef = ref(null);
const conferindo = ref(false);
const importando = ref(false);
const conferencia = ref(null);
const substituir = ref(false);
const resultado = ref('');

const podeImportar = computed(() => conferencia.value?.ok === true);

function aoEscolher(evento) {
    arquivo.value = evento.target.files?.[0] ?? null;
    conferencia.value = null;
    resultado.value = '';
}

function corpoComArquivo() {
    const fd = new FormData();
    fd.append('arquivo', arquivo.value);
    return fd;
}

async function conferir() {
    if (!arquivo.value) return;
    conferindo.value = true;
    conferencia.value = null;
    resultado.value = '';

    try {
        const { data } = await window.axios.post('/testes-clinicos/importar/conferir', corpoComArquivo());
        conferencia.value = data;
    } catch (e) {
        conferencia.value = {
            ok: false,
            erros: [e?.response?.data?.message || 'Não foi possível ler o arquivo.'],
            resumo: { testes: 0, questoes: 0, faixas: 0 },
            previa: [],
        };
    } finally {
        conferindo.value = false;
    }
}

async function importar() {
    importando.value = true;
    resultado.value = '';

    try {
        const fd = corpoComArquivo();
        if (substituir.value) fd.append('substituir', '1');

        const { data } = await window.axios.post('/testes-clinicos/importar', fd);
        resultado.value = data.message;
        conferencia.value = null;
        arquivo.value = null;
        if (inputRef.value) inputRef.value.value = '';
        emit('importado');
    } catch (e) {
        const erros = e?.response?.data?.erros;
        conferencia.value = {
            ok: false,
            erros: erros ?? [e?.response?.data?.message || 'Não foi possível importar.'],
            resumo: { testes: 0, questoes: 0, faixas: 0 },
            previa: [],
        };
    } finally {
        importando.value = false;
    }
}
</script>

<template>
    <section class="space-y-6">

        <div class="flex items-start gap-3">
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-violet-100 dark:bg-violet-900/30">
                <FileSpreadsheet class="h-6 w-6 text-violet-600 dark:text-violet-400" />
            </div>
            <div>
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">Importar testes em massa</h2>
                <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">
                    Suba o acervo inteiro de uma vez, por planilha ou JSON, em vez de cadastrar um a um.
                </p>
            </div>
        </div>

        <!-- Como montar o arquivo -->
        <details class="rounded-xl border border-zinc-200 dark:border-zinc-700">
            <summary class="cursor-pointer px-4 py-3 text-sm font-medium text-zinc-900 dark:text-white">
                Como montar a planilha
            </summary>
            <div class="space-y-4 border-t border-zinc-200 px-4 py-4 text-sm text-zinc-600 dark:border-zinc-700 dark:text-zinc-300">
                <p>
                    Cada linha é <strong>uma pergunta</strong>. As colunas do teste se repetem em todas
                    as linhas dele — é assim que o sistema sabe quais perguntas pertencem a qual teste.
                </p>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[34rem] text-left text-xs">
                        <thead class="border-b border-zinc-200 text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                            <tr>
                                <th class="py-2 pr-4 font-medium">Coluna</th>
                                <th class="py-2 font-medium">Para que serve</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            <tr v-for="c in COLUNAS" :key="c[0]">
                                <td class="py-2 pr-4 align-top">
                                    <code class="rounded bg-zinc-100 px-1.5 py-0.5 dark:bg-zinc-800">{{ c[0] }}</code>
                                </td>
                                <td class="py-2 align-top">{{ c[1] }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                    Excel em português salva com ponto e vírgula — o sistema aceita os dois separadores.
                    Também dá para enviar um <code>.json</code> com a estrutura completa.
                </p>

                <a href="/testes-clinicos/importar/modelo"
                    class="inline-flex items-center gap-2 rounded-xl border border-zinc-200 px-4 py-2 text-sm font-medium text-zinc-700 transition hover:border-[var(--color-primary)] hover:text-[var(--color-primary)] dark:border-zinc-600 dark:text-zinc-200">
                    <Download class="h-4 w-4" />
                    Baixar planilha de exemplo
                </a>
            </div>
        </details>

        <!-- Envio -->
        <div class="space-y-3">
            <label for="arquivo-testes" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                Arquivo (.csv ou .json, até 10 MB)
            </label>
            <input id="arquivo-testes" ref="inputRef" type="file" accept=".csv,.json,text/csv,application/json"
                @change="aoEscolher"
                class="block w-full text-sm text-zinc-600 file:mr-3 file:rounded-xl file:border-0 file:bg-zinc-100 file:px-4 file:py-2 file:text-sm file:font-medium file:text-zinc-700 hover:file:bg-zinc-200 dark:text-zinc-300 dark:file:bg-zinc-700 dark:file:text-zinc-200" />

            <button type="button" :disabled="!arquivo || conferindo" @click="conferir"
                class="inline-flex items-center gap-2 rounded-xl border border-zinc-200 px-4 py-2.5 text-sm font-medium text-zinc-700 transition hover:border-[var(--color-primary)] hover:text-[var(--color-primary)] disabled:opacity-50 dark:border-zinc-600 dark:text-zinc-200">
                <Upload class="h-4 w-4" />
                {{ conferindo ? 'Conferindo…' : 'Conferir arquivo' }}
            </button>
        </div>

        <!-- Erros -->
        <div v-if="conferencia && !conferencia.ok"
            class="rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-800/50 dark:bg-red-950/30">
            <div class="flex items-center gap-2 text-sm font-medium text-red-800 dark:text-red-200">
                <AlertTriangle class="h-4 w-4" />
                O arquivo precisa de ajustes
            </div>
            <ul class="mt-2 max-h-56 list-disc space-y-1 overflow-y-auto pl-5 text-sm text-red-700 dark:text-red-300">
                <li v-for="(erro, i) in conferencia.erros" :key="i">{{ erro }}</li>
            </ul>
            <p class="mt-2 text-xs text-red-700 dark:text-red-300">
                Nada foi gravado. Corrija o arquivo e confira de novo.
            </p>
        </div>

        <!-- Prévia -->
        <div v-if="conferencia?.ok" class="space-y-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-800/50 dark:bg-emerald-950/30">
            <div class="flex items-center gap-2 text-sm font-medium text-emerald-800 dark:text-emerald-200">
                <CheckCircle2 class="h-4 w-4" />
                {{ conferencia.resumo.testes }} teste(s),
                {{ conferencia.resumo.questoes }} pergunta(s) e
                {{ conferencia.resumo.faixas }} faixa(s) de resultado
            </div>

            <div class="max-h-52 overflow-y-auto rounded-lg border border-emerald-200 bg-white dark:border-emerald-800/50 dark:bg-zinc-900">
                <table class="w-full text-left text-xs">
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        <tr v-for="(t, i) in conferencia.previa" :key="i">
                            <td class="px-3 py-2 text-zinc-900 dark:text-white">
                                {{ t.nome }}
                                <span v-if="t.infantil" class="ml-1 rounded bg-sky-100 px-1.5 py-0.5 text-[10px] text-sky-700 dark:bg-sky-900/40 dark:text-sky-300">infantil</span>
                            </td>
                            <td class="px-3 py-2 text-zinc-500 dark:text-zinc-400">{{ t.categoria }}</td>
                            <td class="px-3 py-2 text-right text-zinc-500 dark:text-zinc-400">
                                {{ t.questoes }} perguntas
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <label class="flex items-start gap-2 text-sm text-emerald-900 dark:text-emerald-100">
                <input v-model="substituir" type="checkbox" class="mt-1" />
                <span>
                    Substituir testes com o mesmo nome
                    <span class="block text-xs text-emerald-700 dark:text-emerald-300">
                        As perguntas e faixas atuais deles são apagadas e recriadas. Sem marcar, os
                        que já existem são mantidos como estão.
                    </span>
                </span>
            </label>

            <button type="button" :disabled="!podeImportar || importando" @click="importar"
                class="rounded-xl bg-[var(--color-primary)] px-5 py-2.5 text-sm font-medium text-white transition hover:opacity-90 disabled:opacity-60">
                {{ importando ? 'Importando…' : 'Importar agora' }}
            </button>
        </div>

        <div v-if="resultado" class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800 dark:border-emerald-800/50 dark:bg-emerald-950/30">
            {{ resultado }}
        </div>

    </section>
</template>
