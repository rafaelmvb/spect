<script setup>
import { ref, computed } from 'vue';
import MemberAreaAppLayout from '@/Layouts/MemberAreaAppLayout.vue';
import { useMemberBase } from '@/composables/useMemberBase';
import { Baby, Plus, Pencil, Trash2, ShieldCheck, X } from 'lucide-vue-next';

defineOptions({ layout: MemberAreaAppLayout });

const props = defineProps({
    product: { type: Object, required: true },
    config: { type: Object, default: () => ({}) },
    perfis: { type: Array, default: () => [] },
    vinculos: { type: Array, default: () => [] },
    slug: { type: String, default: '' },
});

const memberBase = useMemberBase(computed(() => props.slug));

const lista = ref([...props.perfis]);
const editando = ref(null);
const salvando = ref(false);
const erros = ref({});

const VINCULO_LABEL = {
    mae: 'Mãe',
    pai: 'Pai',
    avo: 'Avó ou avô',
    responsavel_legal: 'Responsável legal',
    outro: 'Outro',
};

function formVazio() {
    return { id: null, name: '', birth_date: '', relationship: 'mae', relationship_other: '' };
}

const form = ref(formVazio());

function abrirNovo() {
    form.value = formVazio();
    erros.value = {};
    editando.value = 'novo';
}

function abrirEdicao(perfil) {
    form.value = {
        id: perfil.id,
        name: perfil.name,
        birth_date: perfil.birth_date ?? '',
        relationship: perfil.relationship,
        relationship_other: perfil.relationship_other ?? '',
    };
    erros.value = {};
    editando.value = perfil.id;
}

function fechar() {
    editando.value = null;
    erros.value = {};
}

async function salvar() {
    salvando.value = true;
    erros.value = {};

    const corpo = { ...form.value };
    if (!corpo.birth_date) delete corpo.birth_date;
    if (corpo.relationship !== 'outro') delete corpo.relationship_other;

    try {
        const url = `${memberBase.value}/perfis-infantis${form.value.id ? `/${form.value.id}` : ''}`;
        const metodo = form.value.id ? 'put' : 'post';
        const { data } = await window.axios[metodo](url, corpo);

        if (form.value.id) {
            const i = lista.value.findIndex((p) => p.id === form.value.id);
            if (i !== -1) lista.value[i] = data.perfil;
        } else {
            lista.value.push(data.perfil);
        }
        fechar();
    } catch (e) {
        if (e?.response?.status === 422) {
            erros.value = e.response.data.errors ?? {};
        } else {
            window.alert(e?.response?.data?.message || 'Não foi possível salvar. Tente de novo.');
        }
    } finally {
        salvando.value = false;
    }
}

async function excluir(perfil) {
    const aviso = perfil.rastreios_concluidos > 0
        ? `Excluir o perfil de ${perfil.name}? Os ${perfil.rastreios_concluidos} rastreio(s) dele também serão apagados, e isso não tem volta.`
        : `Excluir o perfil de ${perfil.name}?`;

    if (!window.confirm(aviso)) return;

    try {
        await window.axios.delete(`${memberBase.value}/perfis-infantis/${perfil.id}`);
        lista.value = lista.value.filter((p) => p.id !== perfil.id);
    } catch (e) {
        window.alert(e?.response?.data?.message || 'Não foi possível excluir.');
    }
}

function rotuloVinculo(perfil) {
    return perfil.vinculo_legivel || VINCULO_LABEL[perfil.relationship] || 'Responsável';
}

function idadeTexto(perfil) {
    if (perfil.idade === null || perfil.idade === undefined) return 'Idade não informada';
    return perfil.idade === 1 ? '1 ano' : `${perfil.idade} anos`;
}
</script>

<template>
    <div class="mx-auto max-w-3xl space-y-6 px-4 py-8">

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-sky-100 dark:bg-sky-900/30">
                    <Baby class="h-6 w-6 text-sky-600 dark:text-sky-400" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Perfis dos meus filhos</h1>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">
                        Um perfil para cada criança, com os rastreios separados.
                    </p>
                </div>
            </div>
            <button type="button" @click="abrirNovo"
                class="inline-flex items-center gap-2 rounded-xl bg-[var(--ma-primary)] px-4 py-2.5 text-sm font-medium text-white transition hover:opacity-90">
                <Plus class="h-4 w-4" />
                Adicionar criança
            </button>
        </div>

        <div class="flex items-start gap-3 rounded-2xl border border-sky-200 bg-sky-50 p-4 dark:border-sky-800/50 dark:bg-sky-950/30">
            <ShieldCheck class="mt-0.5 h-5 w-5 shrink-0 text-sky-600 dark:text-sky-400" />
            <p class="text-sm text-sky-900 dark:text-sky-100">
                Os dados de cada criança são privados e ficam só com você. Nenhum profissional
                os vê sem a sua autorização, e você pode retirá-la quando quiser.
            </p>
        </div>

        <!-- Lista -->
        <div v-if="lista.length" class="space-y-3">
            <div v-for="perfil in lista" :key="perfil.id"
                class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800">
                <div class="min-w-0">
                    <p class="truncate font-medium text-zinc-900 dark:text-white">{{ perfil.name }}</p>
                    <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">
                        {{ idadeTexto(perfil) }} · Você é {{ rotuloVinculo(perfil).toLowerCase() }}
                    </p>
                    <p class="mt-1 text-xs text-zinc-400 dark:text-zinc-500">
                        {{ perfil.rastreios_concluidos }}
                        {{ perfil.rastreios_concluidos === 1 ? 'rastreio concluído' : 'rastreios concluídos' }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <button type="button" @click="abrirEdicao(perfil)" :aria-label="`Editar perfil de ${perfil.name}`"
                        class="rounded-xl border border-zinc-200 p-2 text-zinc-600 transition hover:border-[var(--ma-primary)] hover:text-[var(--ma-primary)] dark:border-zinc-600 dark:text-zinc-300">
                        <Pencil class="h-4 w-4" />
                    </button>
                    <button type="button" @click="excluir(perfil)" :aria-label="`Excluir perfil de ${perfil.name}`"
                        class="rounded-xl border border-zinc-200 p-2 text-zinc-600 transition hover:border-red-300 hover:text-red-600 dark:border-zinc-600 dark:text-zinc-300 dark:hover:border-red-700 dark:hover:text-red-400">
                        <Trash2 class="h-4 w-4" />
                    </button>
                </div>
            </div>
        </div>

        <div v-else class="rounded-2xl border border-dashed border-zinc-300 p-10 text-center dark:border-zinc-600">
            <Baby class="mx-auto mb-3 h-10 w-10 text-zinc-300 dark:text-zinc-600" />
            <p class="font-medium text-zinc-700 dark:text-zinc-200">Nenhuma criança cadastrada</p>
            <p class="mx-auto mt-1 max-w-sm text-sm text-zinc-500 dark:text-zinc-400">
                Cadastre um perfil para responder os rastreios em nome do seu filho. Cada criança
                tem os próprios resultados.
            </p>
        </div>

        <!-- Formulário -->
        <Teleport to="body">
            <div v-if="editando" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 p-4"
                @click.self="fechar">
                <div class="w-full max-w-md rounded-2xl border border-zinc-200 bg-white p-6 shadow-xl dark:border-zinc-700 dark:bg-zinc-800"
                    role="dialog" aria-modal="true" aria-labelledby="titulo-perfil">
                    <div class="mb-5 flex items-start justify-between gap-4">
                        <h2 id="titulo-perfil" class="text-lg font-semibold text-zinc-900 dark:text-white">
                            {{ form.id ? 'Editar perfil' : 'Nova criança' }}
                        </h2>
                        <button type="button" @click="fechar" aria-label="Fechar"
                            class="rounded-lg p-1 text-zinc-400 transition hover:text-zinc-600 dark:hover:text-zinc-200">
                            <X class="h-5 w-5" />
                        </button>
                    </div>

                    <form class="space-y-4" @submit.prevent="salvar">
                        <div>
                            <label for="nome-crianca" class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                Nome da criança
                            </label>
                            <input id="nome-crianca" v-model="form.name" type="text" required maxlength="120"
                                class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2.5 text-sm text-zinc-900 focus:border-[var(--ma-primary)] focus:outline-none dark:border-zinc-600 dark:bg-zinc-900 dark:text-white" />
                            <p v-if="erros.name" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ erros.name[0] }}</p>
                        </div>

                        <div>
                            <label for="nascimento-crianca" class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                Data de nascimento
                            </label>
                            <input id="nascimento-crianca" v-model="form.birth_date" type="date"
                                class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2.5 text-sm text-zinc-900 focus:border-[var(--ma-primary)] focus:outline-none dark:border-zinc-600 dark:bg-zinc-900 dark:text-white" />
                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                Alguns rastreios usam a idade para interpretar o resultado.
                            </p>
                            <p v-if="erros.birth_date" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ erros.birth_date[0] }}</p>
                        </div>

                        <div>
                            <label for="vinculo-crianca" class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                Seu vínculo com a criança
                            </label>
                            <select id="vinculo-crianca" v-model="form.relationship"
                                class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2.5 text-sm text-zinc-900 focus:border-[var(--ma-primary)] focus:outline-none dark:border-zinc-600 dark:bg-zinc-900 dark:text-white">
                                <option v-for="v in props.vinculos" :key="v" :value="v">{{ VINCULO_LABEL[v] ?? v }}</option>
                            </select>
                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                Fica registrado no relatório quem respondeu o rastreio.
                            </p>
                        </div>

                        <div v-if="form.relationship === 'outro'">
                            <label for="vinculo-outro" class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                Qual é o vínculo?
                            </label>
                            <input id="vinculo-outro" v-model="form.relationship_other" type="text" maxlength="60"
                                placeholder="Tia, padrasto, tutor…"
                                class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2.5 text-sm text-zinc-900 focus:border-[var(--ma-primary)] focus:outline-none dark:border-zinc-600 dark:bg-zinc-900 dark:text-white" />
                            <p v-if="erros.relationship_other" class="mt-1 text-sm text-red-600 dark:text-red-400">
                                {{ erros.relationship_other[0] }}
                            </p>
                        </div>

                        <div class="flex gap-2 pt-2">
                            <button type="submit" :disabled="salvando"
                                class="flex-1 rounded-xl bg-[var(--ma-primary)] px-4 py-2.5 text-sm font-medium text-white transition hover:opacity-90 disabled:opacity-60">
                                {{ salvando ? 'Salvando…' : (form.id ? 'Salvar' : 'Cadastrar') }}
                            </button>
                            <button type="button" @click="fechar"
                                class="rounded-xl border border-zinc-200 px-4 py-2.5 text-sm font-medium text-zinc-600 transition hover:bg-zinc-50 dark:border-zinc-600 dark:text-zinc-300 dark:hover:bg-zinc-700">
                                Cancelar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

    </div>
</template>
