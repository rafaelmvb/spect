<script setup>
import { ref, onMounted, computed } from 'vue';
import { Video, CheckCircle2, AlertTriangle, ExternalLink, Trash2 } from 'lucide-vue-next';

const carregando = ref(true);
const salvando = ref(false);
const dados = ref(null);
const erros = ref({});
const mensagem = ref('');

const form = ref({ client_id: '', client_secret: '', redirect: '' });

const configurado = computed(() => dados.value?.configurado === true);

async function carregar() {
    carregando.value = true;
    try {
        const { data } = await window.axios.get('/configuracoes/teleconsulta');
        dados.value = data;
        form.value.client_id = data.client_id ?? '';
        form.value.redirect = data.redirect || data.redirect_sugerido || '';
    } catch {
        mensagem.value = 'Não foi possível carregar a configuração.';
    } finally {
        carregando.value = false;
    }
}

async function salvar() {
    salvando.value = true;
    erros.value = {};
    mensagem.value = '';
    try {
        const { data } = await window.axios.put('/configuracoes/teleconsulta', form.value);
        mensagem.value = data.message;
        form.value.client_secret = '';
        await carregar();
    } catch (e) {
        if (e?.response?.status === 422) {
            erros.value = e.response.data.errors ?? {};
        } else {
            mensagem.value = 'Não foi possível salvar.';
        }
    } finally {
        salvando.value = false;
    }
}

async function remover() {
    if (!window.confirm('Remover as credenciais? Todos os profissionais serão desconectados e novas consultas ficarão sem sala de vídeo.')) return;
    try {
        const { data } = await window.axios.delete('/configuracoes/teleconsulta');
        mensagem.value = data.message;
        form.value = { client_id: '', client_secret: '', redirect: '' };
        await carregar();
    } catch {
        mensagem.value = 'Não foi possível remover.';
    }
}

function copiar(texto) {
    navigator.clipboard?.writeText(texto);
}

onMounted(carregar);
</script>

<template>
    <section class="space-y-6">

        <div class="flex items-start gap-3">
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-blue-100 dark:bg-blue-900/30">
                <Video class="h-6 w-6 text-blue-600 dark:text-blue-400" />
            </div>
            <div>
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">Teleconsulta pelo Google Meet</h2>
                <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">
                    Depois de configurar aqui, cada profissional conecta a própria conta Google e as
                    salas passam a ser criadas automaticamente.
                </p>
            </div>
        </div>

        <div v-if="carregando" class="text-sm text-zinc-500 dark:text-zinc-400">Carregando…</div>

        <template v-else>
            <div v-if="mensagem" class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800 dark:border-emerald-800/50 dark:bg-emerald-950/30 dark:text-emerald-200">
                {{ mensagem }}
            </div>

            <div :class="[
                'flex items-center gap-2 rounded-xl border p-3 text-sm',
                configurado
                    ? 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-800/50 dark:bg-emerald-950/30 dark:text-emerald-200'
                    : 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-800/50 dark:bg-amber-950/30 dark:text-amber-200',
            ]">
                <CheckCircle2 v-if="configurado" class="h-4 w-4 shrink-0" />
                <AlertTriangle v-else class="h-4 w-4 shrink-0" />
                <span v-if="configurado">Credenciais configuradas. A teleconsulta está disponível.</span>
                <span v-else>Ainda não configurado. Siga o passo a passo abaixo.</span>
            </div>

            <!-- Instruções -->
            <details class="rounded-xl border border-zinc-200 dark:border-zinc-700" :open="!configurado">
                <summary class="cursor-pointer px-4 py-3 text-sm font-medium text-zinc-900 dark:text-white">
                    Como obter as credenciais no Google Cloud
                </summary>
                <div class="space-y-4 border-t border-zinc-200 px-4 py-4 text-sm text-zinc-600 dark:border-zinc-700 dark:text-zinc-300">
                    <ol class="list-decimal space-y-3 pl-5">
                        <li>
                            Abra o
                            <a href="https://console.cloud.google.com/" target="_blank" rel="noopener noreferrer"
                                class="inline-flex items-center gap-1 text-[var(--color-primary)] hover:underline">
                                Google Cloud Console <ExternalLink class="h-3 w-3" />
                            </a>
                            e crie um projeto (ou escolha um que já exista).
                        </li>
                        <li>
                            Em <strong>APIs e serviços › Biblioteca</strong>, procure por
                            <strong>Google Meet API</strong> e clique em <strong>Ativar</strong>.
                        </li>
                        <li>
                            Em <strong>Tela de permissão OAuth</strong>, preencha nome do app e e-mail de
                            suporte. Adicione estes escopos:
                            <div class="mt-2 space-y-1">
                                <code v-for="escopo in [
                                    'https://www.googleapis.com/auth/meetings.space.created',
                                    'https://www.googleapis.com/auth/meetings.space.readonly',
                                    'https://www.googleapis.com/auth/userinfo.email',
                                ]" :key="escopo"
                                    class="block break-all rounded bg-zinc-100 px-2 py-1 text-xs dark:bg-zinc-800">{{ escopo }}</code>
                            </div>
                        </li>
                        <li>
                            Em <strong>Credenciais › Criar credenciais › ID do cliente OAuth</strong>,
                            escolha <strong>Aplicativo da Web</strong>.
                        </li>
                        <li>
                            Em <strong>URIs de redirecionamento autorizados</strong>, cole exatamente:
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <code class="break-all rounded bg-zinc-100 px-2 py-1 text-xs dark:bg-zinc-800">{{ dados?.redirect_sugerido }}</code>
                                <button type="button" @click="copiar(dados?.redirect_sugerido)"
                                    class="rounded-lg border border-zinc-200 px-2 py-1 text-xs text-zinc-600 transition hover:border-[var(--color-primary)] hover:text-[var(--color-primary)] dark:border-zinc-600 dark:text-zinc-300">
                                    Copiar
                                </button>
                            </div>
                        </li>
                        <li>Copie o <strong>ID do cliente</strong> e a <strong>Chave secreta</strong> e cole abaixo.</li>
                    </ol>

                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-amber-900 dark:border-amber-800/50 dark:bg-amber-950/30 dark:text-amber-100">
                        <p class="font-medium">Duas coisas que valem saber antes</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5">
                            <li>
                                A consulta abre no Google Meet, em outra aba. O Meet não pode ser
                                embutido dentro do painel.
                            </li>
                            <li>
                                A transcrição da sessão depende do plano do Google Workspace do
                                profissional e precisa ser ligada durante a consulta. Conta Gmail
                                comum não gera transcrição.
                            </li>
                        </ul>
                    </div>
                </div>
            </details>

            <!-- Formulário -->
            <form class="space-y-4" @submit.prevent="salvar">
                <div>
                    <label for="meet-client-id" class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        ID do cliente
                    </label>
                    <input id="meet-client-id" v-model="form.client_id" type="text" autocomplete="off"
                        placeholder="000000000000-xxxxxxxx.apps.googleusercontent.com"
                        class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2.5 text-sm text-zinc-900 focus:border-[var(--color-primary)] focus:outline-none dark:border-zinc-600 dark:bg-zinc-800 dark:text-white" />
                    <p v-if="erros.client_id" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ erros.client_id[0] }}</p>
                </div>

                <div>
                    <label for="meet-client-secret" class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        Chave secreta
                    </label>
                    <input id="meet-client-secret" v-model="form.client_secret" type="password" autocomplete="new-password"
                        :placeholder="dados?.client_secret_definido ? 'Já salva — preencha só para trocar' : 'GOCSPX-…'"
                        class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2.5 text-sm text-zinc-900 focus:border-[var(--color-primary)] focus:outline-none dark:border-zinc-600 dark:bg-zinc-800 dark:text-white" />
                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                        Guardada cifrada. Nunca é exibida de volta.
                    </p>
                    <p v-if="erros.client_secret" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ erros.client_secret[0] }}</p>
                </div>

                <div>
                    <label for="meet-redirect" class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        URI de redirecionamento
                    </label>
                    <input id="meet-redirect" v-model="form.redirect" type="url"
                        :placeholder="dados?.redirect_sugerido"
                        class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2.5 text-sm text-zinc-900 focus:border-[var(--color-primary)] focus:outline-none dark:border-zinc-600 dark:bg-zinc-800 dark:text-white" />
                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                        Precisa ser idêntico ao cadastrado no Google Cloud.
                    </p>
                    <p v-if="erros.redirect" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ erros.redirect[0] }}</p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button type="submit" :disabled="salvando"
                        class="rounded-xl bg-[var(--color-primary)] px-5 py-2.5 text-sm font-medium text-white transition hover:opacity-90 disabled:opacity-60">
                        {{ salvando ? 'Salvando…' : 'Salvar credenciais' }}
                    </button>
                    <button v-if="configurado" type="button" @click="remover"
                        class="inline-flex items-center gap-2 rounded-xl border border-zinc-200 px-4 py-2.5 text-sm font-medium text-zinc-600 transition hover:border-red-300 hover:text-red-600 dark:border-zinc-600 dark:text-zinc-300 dark:hover:border-red-700 dark:hover:text-red-400">
                        <Trash2 class="h-4 w-4" />
                        Remover
                    </button>
                </div>
            </form>

            <!-- Quem já conectou -->
            <div v-if="dados?.profissionais_conectados?.length" class="rounded-xl border border-zinc-200 dark:border-zinc-700">
                <p class="border-b border-zinc-200 px-4 py-3 text-sm font-medium text-zinc-900 dark:border-zinc-700 dark:text-white">
                    Profissionais com conta conectada
                </p>
                <ul class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    <li v-for="(p, i) in dados.profissionais_conectados" :key="i"
                        class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm">
                        <span class="text-zinc-900 dark:text-white">{{ p.profissional }}</span>
                        <span class="text-zinc-500 dark:text-zinc-400">{{ p.conta_google }}</span>
                        <span v-if="p.com_erro" class="text-amber-600 dark:text-amber-400">
                            Precisa reconectar
                        </span>
                    </li>
                </ul>
            </div>
        </template>

    </section>
</template>
