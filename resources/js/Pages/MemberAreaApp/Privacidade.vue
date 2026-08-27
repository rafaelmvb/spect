<script setup>
import { ref, computed } from 'vue';
import MemberAreaAppLayout from '@/Layouts/MemberAreaAppLayout.vue';
import { useMemberBase } from '@/composables/useMemberBase';
import { ShieldCheck, Download, Trash2, UserCheck, AlertTriangle } from 'lucide-vue-next';

defineOptions({ layout: MemberAreaAppLayout });

const props = defineProps({
    product: { type: Object, required: true },
    config: { type: Object, default: () => ({}) },
    resumo: { type: Object, default: () => ({}) },
    profissionais: { type: Array, default: () => [] },
    slug: { type: String, default: '' },
});

const memberBase = useMemberBase(computed(() => props.slug));

const excluindo = ref(false);
const mostrarExclusao = ref(false);
const senha = ref('');
const confirmacao = ref('');
const erro = ref('');

const ITENS = computed(() => [
    ['Testes respondidos', props.resumo.testes ?? 0],
    ['Registros de humor', props.resumo.humor ?? 0],
    ['Aulas acompanhadas', props.resumo.aulas ?? 0],
    ['Mensagens com o mentor', props.resumo.conversas ?? 0],
    ['Perfis de filhos', props.resumo.perfis_infantis ?? 0],
]);

async function excluir() {
    excluindo.value = true;
    erro.value = '';
    try {
        const { data } = await window.axios.delete(`${memberBase.value}/privacidade/conta`, {
            data: { senha: senha.value, confirmacao: confirmacao.value },
        });
        window.location.href = data.redirect ?? '/entrar';
    } catch (e) {
        erro.value = e?.response?.data?.message
            || e?.response?.data?.errors?.confirmacao?.[0]
            || 'Não foi possível excluir a conta.';
    } finally {
        excluindo.value = false;
    }
}
</script>

<template>
    <div class="mx-auto max-w-3xl space-y-6 px-4 py-8">

        <div class="flex items-center gap-3">
            <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-100 dark:bg-emerald-900/30">
                <ShieldCheck class="h-6 w-6 text-emerald-600 dark:text-emerald-400" />
            </div>
            <div>
                <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Seus dados</h1>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    O que guardamos, quem tem acesso e o que você pode fazer com isso.
                </p>
            </div>
        </div>

        <!-- O que existe -->
        <section class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-800">
            <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">O que está guardado</h2>
            <dl class="mt-3 divide-y divide-zinc-100 dark:divide-zinc-700">
                <div v-for="[rotulo, valor] in ITENS" :key="rotulo" class="flex justify-between py-2 text-sm">
                    <dt class="text-zinc-600 dark:text-zinc-300">{{ rotulo }}</dt>
                    <dd class="font-medium tabular-nums text-zinc-900 dark:text-white">{{ valor }}</dd>
                </div>
            </dl>
        </section>

        <!-- Quem acessa -->
        <section class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-800">
            <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">Quem tem acesso</h2>

            <div v-if="profissionais.length" class="mt-3 space-y-2">
                <div v-for="p in profissionais" :key="p.id" class="flex items-center gap-2 text-sm">
                    <UserCheck class="h-4 w-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                    <span class="text-zinc-900 dark:text-white">{{ p.nome }}</span>
                    <span class="text-zinc-400">autorizado em {{ p.desde }}</span>
                </div>
                <p class="pt-1 text-xs text-zinc-500 dark:text-zinc-400">
                    Para retirar a autorização, vá em
                    <a :href="`${memberBase}/profissionais`" class="text-[var(--ma-primary)] hover:underline">Profissionais</a>.
                </p>
            </div>

            <p v-else class="mt-3 text-sm text-zinc-500 dark:text-zinc-400">
                Nenhum profissional tem acesso aos seus dados. Suas respostas e relatórios são só seus.
            </p>
        </section>

        <!-- Exportar -->
        <section class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-800">
            <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">Baixar uma cópia</h2>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                Um arquivo com tudo que a sua conta guarda: testes, humor, progresso e conversas.
            </p>
            <a :href="`${memberBase}/privacidade/exportar`"
                class="mt-3 inline-flex items-center gap-2 rounded-xl border border-zinc-200 px-4 py-2.5 text-sm font-medium text-zinc-700 transition hover:border-[var(--ma-primary)] hover:text-[var(--ma-primary)] dark:border-zinc-600 dark:text-zinc-200">
                <Download class="h-4 w-4" />
                Baixar meus dados
            </a>
        </section>

        <!-- Excluir -->
        <section class="rounded-2xl border border-red-200 bg-red-50 p-5 dark:border-red-800/50 dark:bg-red-950/20">
            <h2 class="text-sm font-semibold text-red-900 dark:text-red-100">Excluir minha conta</h2>
            <p class="mt-1 text-sm text-red-800 dark:text-red-200">
                Apaga a conta, o histórico, os relatórios e os perfis dos seus filhos. Não tem volta.
            </p>

            <button v-if="!mostrarExclusao" type="button" @click="mostrarExclusao = true"
                class="mt-3 inline-flex items-center gap-2 rounded-xl border border-red-300 px-4 py-2.5 text-sm font-medium text-red-700 transition hover:bg-red-100 dark:border-red-700 dark:text-red-200 dark:hover:bg-red-900/40">
                <Trash2 class="h-4 w-4" />
                Quero excluir
            </button>

            <form v-else class="mt-4 space-y-3" @submit.prevent="excluir">
                <div>
                    <label for="senha-exclusao" class="mb-1 block text-sm font-medium text-red-900 dark:text-red-100">
                        Sua senha
                    </label>
                    <input id="senha-exclusao" v-model="senha" type="password" required autocomplete="current-password"
                        class="w-full rounded-xl border border-red-200 bg-white px-3 py-2.5 text-sm text-zinc-900 focus:border-red-400 focus:outline-none dark:border-red-800 dark:bg-zinc-900 dark:text-white" />
                </div>
                <div>
                    <label for="confirmacao-exclusao" class="mb-1 block text-sm font-medium text-red-900 dark:text-red-100">
                        Escreva EXCLUIR para confirmar
                    </label>
                    <input id="confirmacao-exclusao" v-model="confirmacao" type="text" required autocomplete="off"
                        class="w-full rounded-xl border border-red-200 bg-white px-3 py-2.5 text-sm text-zinc-900 focus:border-red-400 focus:outline-none dark:border-red-800 dark:bg-zinc-900 dark:text-white" />
                </div>

                <p v-if="erro" class="flex items-start gap-2 text-sm text-red-700 dark:text-red-300">
                    <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" />
                    {{ erro }}
                </p>

                <div class="flex flex-wrap gap-2">
                    <button type="submit" :disabled="excluindo"
                        class="rounded-xl bg-red-600 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-red-700 disabled:opacity-60">
                        {{ excluindo ? 'Excluindo…' : 'Excluir definitivamente' }}
                    </button>
                    <button type="button" @click="mostrarExclusao = false; erro = ''"
                        class="rounded-xl border border-red-200 px-4 py-2.5 text-sm font-medium text-red-700 transition hover:bg-red-100 dark:border-red-700 dark:text-red-200">
                        Cancelar
                    </button>
                </div>
            </form>
        </section>

    </div>
</template>
