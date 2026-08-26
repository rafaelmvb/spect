<script setup>
import { ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import LayoutInfoprodutor from '@/Layouts/LayoutInfoprodutor.vue';
import Button from '@/components/ui/Button.vue';
import CampaignFilterSelector from '@/components/email/CampaignFilterSelector.vue';
import axios from 'axios';

defineOptions({ layout: LayoutInfoprodutor });

const props = defineProps({
    email_configured: { type: Boolean, default: false },
    products: { type: Array, default: () => [] },
    default_body_html: { type: String, default: '' },
});

const form = useForm({
    name: '',
    subject: '',
    body_html: props.default_body_html || '',
    filter_config: { type: 'all_customers', product_ids: [], inactive_days: 30, max_progress: 50, min_progress: 80, date_from: '', date_to: '' },
});

const recipientCount = ref(null);
const recipientSample = ref([]);
const loadingRecipients = ref(false);

function useDefaultTemplate() {
    form.body_html = props.default_body_html || '';
}

async function previewRecipients() {
    loadingRecipients.value = true;
    recipientCount.value = null;
    recipientSample.value = [];
    try {
        const res = await axios.post('/email-marketing/preview-recipients',
            { filter_config: form.filter_config },
            { headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' } }
        );
        recipientCount.value = res.data.count;
        recipientSample.value = res.data.sample || [];
    } catch {
        recipientCount.value = 0;
    } finally {
        loadingRecipients.value = false;
    }
}
</script>

<template>
    <div class="mx-auto max-w-4xl space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Nova campanha</h1>
            <Link href="/email-marketing" class="text-sm text-zinc-600 hover:underline dark:text-zinc-400">Voltar</Link>
        </div>

        <div v-if="!email_configured" class="rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950/30">
            <p class="text-sm font-medium text-amber-800 dark:text-amber-200">
                Configure o e-mail em <Link href="/configuracoes" class="underline">Configurações → E-mail</Link> antes de disparar.
            </p>
        </div>

        <form class="space-y-6 rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800/50"
            @submit.prevent="form.post('/email-marketing')">

            <!-- Nome e assunto -->
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Nome da campanha</label>
                    <input v-model="form.name" type="text" required
                        class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900" />
                    <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Assunto do e-mail</label>
                    <input v-model="form.subject" type="text" required
                        class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900" />
                    <p v-if="form.errors.subject" class="mt-1 text-sm text-red-600">{{ form.errors.subject }}</p>
                </div>
            </div>

            <!-- Segmentação -->
            <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-5 dark:border-zinc-700 dark:bg-zinc-800/30">
                <CampaignFilterSelector v-model="form.filter_config" :products="products" />

                <!-- Preview de destinatários -->
                <div class="mt-4 flex flex-wrap items-center gap-3 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                    <Button type="button" variant="outline" size="sm" :disabled="loadingRecipients" @click="previewRecipients">
                        {{ loadingRecipients ? 'Carregando...' : '👁 Ver destinatários' }}
                    </Button>
                    <span v-if="recipientCount !== null" class="rounded-full bg-emerald-100 px-3 py-1 text-sm font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                        {{ recipientCount }} destinatário(s)
                    </span>
                </div>
                <div v-if="recipientSample.length" class="mt-3 divide-y divide-zinc-100 rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                    <div v-for="(r, i) in recipientSample" :key="i" class="flex items-center gap-2 px-3 py-2 text-xs text-zinc-600 dark:text-zinc-400">
                        <span class="font-medium text-zinc-900 dark:text-white">{{ r.name }}</span>
                        <span class="text-zinc-400">—</span>
                        <span>{{ r.email }}</span>
                    </div>
                    <p v-if="recipientCount > recipientSample.length" class="px-3 py-2 text-xs text-zinc-400">
                        + {{ recipientCount - recipientSample.length }} outros...
                    </p>
                </div>
            </div>

            <!-- Corpo HTML -->
            <div>
                <div class="flex items-center justify-between">
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Corpo do e-mail (HTML)</label>
                    <Button type="button" variant="outline" size="sm" @click="useDefaultTemplate">Usar template padrão</Button>
                </div>
                <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">Use <code class="rounded bg-zinc-100 px-1 dark:bg-zinc-700">{nome}</code> e <code class="rounded bg-zinc-100 px-1 dark:bg-zinc-700">{email}</code> para personalizar.</p>
                <textarea v-model="form.body_html" rows="14" required
                    class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 font-mono text-sm dark:border-zinc-600 dark:bg-zinc-900" />
                <p v-if="form.errors.body_html" class="mt-1 text-sm text-red-600">{{ form.errors.body_html }}</p>
            </div>

            <div class="flex flex-wrap items-center gap-3 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                <Button type="submit" :disabled="form.processing">Salvar rascunho</Button>
                <Link href="/email-marketing">
                    <Button type="button" variant="outline">Cancelar</Button>
                </Link>
            </div>
        </form>
    </div>
</template>
