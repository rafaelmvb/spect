<script setup>
import { ref, computed } from 'vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import LayoutInfoprodutor from '@/Layouts/LayoutInfoprodutor.vue';
import Button from '@/components/ui/Button.vue';
import CampaignFilterSelector from '@/components/email/CampaignFilterSelector.vue';
import axios from 'axios';
import { MessageSquare, Phone } from 'lucide-vue-next';

defineOptions({ layout: LayoutInfoprodutor });

const props = defineProps({
    campaign: { type: Object, required: true },
    products: { type: Array, default: () => [] },
    providers: { type: Array, default: () => [] },
    configured_providers: { type: Object, default: () => ({}) },
});

const fc = props.campaign.filter_config || {};
const form = useForm({
    name: props.campaign.name,
    provider: props.campaign.provider,
    message_body: props.campaign.message_body,
    filter_config: { type: 'all_customers', product_ids: [], inactive_days: 30, max_progress: 50, min_progress: 80, date_from: '', date_to: '', ...fc },
});

const recipientCount = ref(null);
const recipientSample = ref([]);
const loadingRecipients = ref(false);

const channelProviders = computed(() =>
    props.providers.filter(p => p.channel === props.campaign.channel)
);

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

async function previewRecipients() {
    loadingRecipients.value = true;
    recipientCount.value = null;
    recipientSample.value = [];
    try {
        const res = await axios.post('/mensagens/preview-destinatarios',
            { filter_config: form.filter_config },
            { headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' } }
        );
        recipientCount.value = res.data.count;
        recipientSample.value = res.data.sample || [];
    } catch { recipientCount.value = 0; }
    finally { loadingRecipients.value = false; }
}

function confirmSend() {
    if (!confirm('Disparar esta campanha? As mensagens serão enviadas em lotes de 20 por minuto.')) return;
    router.post(`/mensagens/${props.campaign.id}/disparar`);
}

const charCount = computed(() => form.message_body.length);
const smsSegments = computed(() => Math.ceil(charCount.value / 160) || 0);
</script>

<template>
    <div class="mx-auto max-w-4xl space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Editar campanha</h1>
            <Link href="/mensagens" class="text-sm text-zinc-600 hover:underline dark:text-zinc-400">Voltar</Link>
        </div>

        <form class="space-y-6 rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800/50"
            @submit.prevent="form.put(`/mensagens/${campaign.id}`)">

            <!-- Canal (só leitura) -->
            <div class="flex items-center gap-2 rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 dark:border-zinc-700 dark:bg-zinc-800/50">
                <component :is="campaign.channel === 'whatsapp' ? MessageSquare : Phone"
                    class="h-4 w-4" :class="campaign.channel === 'whatsapp' ? 'text-green-500' : 'text-blue-500'" />
                <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">
                    {{ campaign.channel === 'whatsapp' ? 'WhatsApp' : 'SMS' }}
                </span>
            </div>

            <!-- Provedor -->
            <div>
                <label class="mb-2 block text-sm font-semibold text-zinc-700 dark:text-zinc-300">Provedor</label>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                    <button v-for="p in channelProviders" :key="p.slug" type="button"
                        class="flex items-center justify-between gap-2 rounded-xl border px-3 py-2.5 text-sm transition"
                        :class="form.provider === p.slug ? 'border-zinc-900 bg-zinc-900 text-white dark:border-zinc-100 dark:bg-zinc-100 dark:text-zinc-900' : 'border-zinc-200 bg-zinc-50 text-zinc-600 hover:border-zinc-400 dark:border-zinc-600 dark:bg-zinc-800/50'"
                        @click="form.provider = p.slug">
                        <span class="truncate font-medium">{{ p.label }}</span>
                        <span v-if="configured_providers[p.slug]" class="shrink-0 text-xs text-emerald-500">✓</span>
                    </button>
                </div>
            </div>

            <!-- Nome -->
            <div>
                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Nome da campanha</label>
                <input v-model="form.name" type="text" required
                    class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900" />
            </div>

            <!-- Segmentação -->
            <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-5 dark:border-zinc-700 dark:bg-zinc-800/30">
                <CampaignFilterSelector v-model="form.filter_config" :products="products" />
                <div class="mt-4 flex flex-wrap items-center gap-3 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                    <Button type="button" variant="outline" size="sm" :disabled="loadingRecipients" @click="previewRecipients">
                        {{ loadingRecipients ? 'Carregando...' : '👁 Ver destinatários' }}
                    </Button>
                    <span v-if="recipientCount !== null" class="rounded-full bg-emerald-100 px-3 py-1 text-sm font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                        {{ recipientCount }} número(s)
                    </span>
                </div>
                <div v-if="recipientSample.length" class="mt-3 divide-y divide-zinc-100 rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                    <div v-for="(r, i) in recipientSample" :key="i" class="flex items-center gap-2 px-3 py-2 text-xs">
                        <span class="font-medium text-zinc-800 dark:text-white">{{ r.name }}</span>
                        <span class="text-zinc-400">—</span>
                        <span class="text-zinc-500">{{ r.phone }}</span>
                    </div>
                </div>
            </div>

            <!-- Mensagem -->
            <div>
                <div class="flex items-center justify-between">
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Mensagem</label>
                    <span class="text-xs text-zinc-400">
                        {{ charCount }} chars
                        <span v-if="campaign.channel === 'sms'"> · {{ smsSegments }} segmento(s)</span>
                    </span>
                </div>
                <p class="mt-0.5 text-xs text-zinc-500">Use <code class="rounded bg-zinc-100 px-1 dark:bg-zinc-700">{nome}</code> e <code class="rounded bg-zinc-100 px-1 dark:bg-zinc-700">{telefone}</code></p>
                <textarea v-model="form.message_body" rows="6" required
                    class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900" />
            </div>

            <div class="flex flex-wrap items-center gap-3 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                <Button type="submit" :disabled="form.processing">Salvar</Button>
                <Button type="button" variant="outline" @click="confirmSend">🚀 Disparar</Button>
                <Link href="/mensagens"><Button type="button" variant="outline">Cancelar</Button></Link>
            </div>
        </form>
    </div>
</template>
