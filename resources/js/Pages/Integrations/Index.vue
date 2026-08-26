<script setup>
import { ref, computed, defineAsyncComponent } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import LayoutInfoprodutor from '@/Layouts/LayoutInfoprodutor.vue';
import Button from '@/components/ui/Button.vue';
import AppCard from '@/components/integrations/AppCard.vue';
import SpedySidebar from '@/components/integrations/SpedySidebar.vue';
import UtmifySidebar from '@/components/integrations/UtmifySidebar.vue';
import WebhookSidebar from '@/components/integrations/WebhookSidebar.vue';
import MessagingSidebar from '@/components/integrations/MessagingSidebar.vue';
import GatewayCard from '@/components/settings/GatewayCard.vue';
import GatewayConfigSidebar from '@/components/settings/GatewayConfigSidebar.vue';
import { CreditCard, Zap, Bot, CheckCircle2, Trash2, Loader2, Star } from 'lucide-vue-next';

defineOptions({ layout: LayoutInfoprodutor });

const TABS = [
    { id: 'apps',     label: 'Apps',     icon: Zap },
    { id: 'gateways', label: 'Gateways', icon: CreditCard },
    { id: 'llm',      label: 'LLM',      icon: Bot },
];

const APPS_BASE = [
    {
        id: 'messaging',
        name: 'WhatsApp & SMS',
        description: 'Envie campanhas de WhatsApp e SMS segmentadas. Suporta Z-API, Evolution API, Twilio, Meta, Zenvia, Infobip e mais.',
        image: 'images/integrations/whatsapp-sms.svg',
    },
    {
        id: 'webhook',
        name: 'Webhook',
        description: 'Envie eventos da plataforma para sua URL. Configure quais eventos deseja receber e use Bearer token para autenticação.',
        image: 'images/integrations/webhook.png',
    },
    {
        id: 'utmify',
        name: 'UTMfy',
        description: 'Rastreie vendas e envie eventos para a UTMfy. Requer apenas a chave de API.',
        image: 'images/integrations/utmify.jpg',
    },
    {
        id: 'spedy',
        name: 'Spedy',
        description: 'Emissão automática de notas fiscais. Envie vendas para a Spedy e emita NF-e/NFS-e.',
        image: 'images/integrations/spedy.png',
    },
];

const props = defineProps({
    llm_providers: { type: Array,  default: () => [] },
    llm_preferred: { type: String, default: 'anthropic' },
    gateways: { type: Array, default: () => [] },
    gateway_order: {
        type: Object,
        default: () => ({ pix: [], card: [], boleto: [] }),
    },
    webhooks: { type: Array, default: () => [] },
    webhook_events: { type: Object, default: () => ({}) },
    utmify_integrations: { type: Array, default: () => [] },
    spedy_integrations: { type: Array, default: () => [] },
    products: { type: Array, default: () => [] },
    api_applications: { type: Array, default: () => [] },
    plugin_apps: { type: Array, default: () => [] },
    messaging_providers: { type: Array, default: () => [] },
    messaging_configured_providers: { type: Object, default: () => ({}) },
});

// ─── LLM ─────────────────────────────────────────────────────────────────────
const llmState     = ref(Object.fromEntries(props.llm_providers.map(p => [p.id, { ...p }])));
const llmInputs    = ref(Object.fromEntries(props.llm_providers.map(p => [p.id, ''])));
const llmSaving    = ref(Object.fromEntries(props.llm_providers.map(p => [p.id, false])));
const llmRemoving  = ref(Object.fromEntries(props.llm_providers.map(p => [p.id, false])));
const llmPreferred = ref(props.llm_preferred);
const llmToast     = ref(null);

function csrfToken() { return document.querySelector('meta[name="csrf-token"]')?.content ?? ''; }
const jsonHeaders  = () => ({ 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' });

function showLlmToast(msg, type = 'success') {
    llmToast.value = { msg, type };
    setTimeout(() => llmToast.value = null, 3500);
}

function maskKey(key) {
    if (!key || key.length < 8) return '';
    const prefix = key.slice(0, Math.min(10, Math.floor(key.length * 0.4)));
    return prefix + '•••••••' + key.slice(-4);
}

async function saveLlmKey(providerId) {
    const key = llmInputs.value[providerId]?.trim();
    if (!key) { showLlmToast('Digite a chave antes de salvar.', 'error'); return; }
    llmSaving.value[providerId] = true;
    try {
        const { data } = await window.axios.post('/integracoes/llm/key', { provider: providerId, api_key: key }, { headers: jsonHeaders() });
        llmState.value[providerId].configured = true;
        llmState.value[providerId].key_hint   = data.key_hint || maskKey(key);
        llmInputs.value[providerId] = '';
        showLlmToast('Chave salva com sucesso.');
    } catch (e) {
        showLlmToast(e.response?.data?.message ?? 'Erro ao salvar chave.', 'error');
    } finally { llmSaving.value[providerId] = false; }
}

async function removeLlmKey(providerId) {
    if (!confirm('Remover a chave desta integração?')) return;
    llmRemoving.value[providerId] = true;
    try {
        await window.axios.post('/integracoes/llm/remove', { provider: providerId }, { headers: jsonHeaders() });
        llmState.value[providerId].configured = false;
        llmState.value[providerId].key_hint   = '';
        showLlmToast('Chave removida.');
    } catch (e) {
        showLlmToast(e.response?.data?.message ?? 'Erro ao remover.', 'error');
    } finally { llmRemoving.value[providerId] = false; }
}

async function setPreferred(providerId) {
    llmPreferred.value = providerId;
    try {
        await window.axios.post('/integracoes/llm/preferred', { provider: providerId }, { headers: jsonHeaders() });
        showLlmToast('Provedor preferido atualizado.');
    } catch { showLlmToast('Erro ao atualizar provedor.', 'error'); }
}

const pluginPagesGlob = import.meta.glob('../../PluginPages/**/*.vue');
const pluginComponentCache = new Map();
function resolvePluginComponent(componentName) {
    if (!componentName || typeof componentName !== 'string') return null;
    if (pluginComponentCache.has(componentName)) return pluginComponentCache.get(componentName);
    const rel = componentName.startsWith('Plugin/') ? componentName.slice(7) : componentName;
    const path = `../../PluginPages/${rel}.vue`;
    const loader = pluginPagesGlob[path];
    if (!loader) {
        pluginComponentCache.set(componentName, null);
        return null;
    }
    const asyncComp = defineAsyncComponent(loader);
    pluginComponentCache.set(componentName, asyncComp);
    return asyncComp;
}

const APPS = computed(() =>
    [
        ...APPS_BASE.map((app) => {
        if (app.id === 'utmify') {
            const hasActive = (props.utmify_integrations || []).some(
                (i) => i.configured && i.is_active
            );
            return {
                ...app,
                status: hasActive ? 'active' : undefined,
            };
        }
        if (app.id === 'spedy') {
            const hasActive = (props.spedy_integrations || []).some(
                (i) => i.configured && i.is_active
            );
            return {
                ...app,
                status: hasActive ? 'active' : undefined,
            };
        }
        if (app.id === '__cademi_removed__') {
            const hasActive = false;
            return {
                ...app,
                status: hasActive ? 'active' : undefined,
            };
        }
        return app;
    }),
        ...((props.plugin_apps || []).map((p) => ({
            id: `plugin:${p.id}`,
            plugin: true,
            plugin_component: p.component,
            name: p.name,
            description: p.description,
            image: p.image,
            status: p.status,
        }))),
    ]
);

const gatewaySidebarOpen = ref(false);
const selectedGatewaySlug = ref(null);
const webhookSidebarOpen = ref(false);
const utmifySidebarOpen = ref(false);
const spedySidebarOpen = ref(false);
const messagingSidebarOpen = ref(false);
const pluginSidebarOpen = ref(false);
const selectedPluginComponentName = ref(null);
const selectedPluginAppName = ref(null);

function openGatewaySidebar(slug) {
    selectedGatewaySlug.value = slug;
    gatewaySidebarOpen.value = true;
}

function closeGatewaySidebar() {
    gatewaySidebarOpen.value = false;
    selectedGatewaySlug.value = null;
}

function openWebhookSidebar() {
    webhookSidebarOpen.value = true;
}

function closeWebhookSidebar() {
    webhookSidebarOpen.value = false;
}

function openUtmifySidebar() {
    utmifySidebarOpen.value = true;
}

function closeUtmifySidebar() {
    utmifySidebarOpen.value = false;
}

function openSpedySidebar() {
    spedySidebarOpen.value = true;
}

function closeSpedySidebar() {
    spedySidebarOpen.value = false;
}

function openPluginSidebar(app) {
    selectedPluginComponentName.value = app?.plugin_component || null;
    selectedPluginAppName.value = app?.name || 'Integração';
    pluginSidebarOpen.value = true;
}

function closePluginSidebar() {
    pluginSidebarOpen.value = false;
    selectedPluginComponentName.value = null;
    selectedPluginAppName.value = null;
}

function onGatewaySaved() {
    router.reload({ only: ['gateways', 'gateway_order'] });
}

function onWebhookSaved() {
    router.reload();
}

function onUtmifySaved() {
    // Recarrega só a lista de integrações para não perder o valor do input da chave no sidebar
    router.reload({ only: ['utmify_integrations', 'products', 'api_applications'] });
}

function onSpedySaved() {
    router.reload({ only: ['spedy_integrations', 'products'] });
}

function onAppClick(app) {
    if (app.id === 'messaging') {
        messagingSidebarOpen.value = true;
    } else if (app.id === 'webhook') {
        openWebhookSidebar();
    } else if (app.id === 'utmify') {
        openUtmifySidebar();
    } else if (app.id === 'spedy') {
        openSpedySidebar();
    } else if (app.plugin) {
        openPluginSidebar(app);
    }
}

const page = usePage();
const currentTab = computed(() => {
    const url = page.url;
    const idx = url.indexOf('?');
    const search = idx !== -1 ? url.slice(idx) : '';
    const q = new URLSearchParams(search);
    const t = q.get('tab');
    return TABS.some((tab) => tab.id === t) ? t : 'apps';
});

function setTab(tabId) {
    router.get('/integracoes', { tab: tabId }, { preserveState: true });
}
</script>

<template>
    <div class="space-y-6">
        <nav
            class="inline-flex flex-wrap gap-1 rounded-xl bg-zinc-100/80 p-1 dark:bg-zinc-800/80"
            aria-label="Abas de integrações"
        >
            <button
                v-for="tab in TABS"
                :key="tab.id"
                type="button"
                :class="[
                    'flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition-all duration-200',
                    currentTab === tab.id
                        ? 'bg-white text-[var(--color-primary)] shadow-sm dark:bg-zinc-700 dark:text-[var(--color-primary)]'
                        : 'text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white',
                ]"
                @click="setTab(tab.id)"
            >
                <component :is="tab.icon" class="h-4 w-4 shrink-0" aria-hidden="true" />
                {{ tab.label }}
            </button>
        </nav>

        <!-- Aba Apps -->
        <template v-if="currentTab === 'apps'">
            <section>
                <h2 class="mb-2 text-lg font-semibold text-zinc-900 dark:text-white">
                    Integrações
                </h2>
                <p class="mb-6 text-sm text-zinc-600 dark:text-zinc-400">
                    Conecte sua plataforma com sistemas externos via webhooks e outras integrações.
                </p>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    <AppCard
                        v-for="app in APPS"
                        :key="app.id"
                        :app="app"
                        @click="onAppClick(app)"
                    />
                </div>
            </section>
        </template>

        <!-- Aba Gateways -->
        <template v-if="currentTab === 'gateways'">
            <section class="space-y-6">
                <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                    <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                        Gateways de pagamento
                    </h2>
                    <p class="mb-4 text-sm text-zinc-600 dark:text-zinc-400">
                        Configure os gateways que deseja usar no checkout. Clique em um card para configurar credenciais e testar a conexão.
                    </p>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <GatewayCard
                            v-for="g in gateways"
                            :key="g.slug"
                            :gateway="g"
                            @click="openGatewaySidebar(g.slug)"
                        />
                    </div>
                    <div v-if="gateways.length === 0" class="rounded-xl border border-dashed border-zinc-300 py-8 text-center text-sm text-zinc-500 dark:border-zinc-600 dark:text-zinc-400">
                        Nenhum gateway disponível.
                    </div>
                </div>
            </section>
        </template>

        <!-- Aba LLM -->
        <template v-if="currentTab === 'llm'">
            <!-- Toast local -->
            <Transition enter-active-class="transition duration-200" enter-from-class="opacity-0 -translate-y-2" enter-to-class="opacity-100 translate-y-0">
                <div v-if="llmToast" class="fixed right-6 top-20 z-[300000] flex items-center gap-2 rounded-xl px-4 py-3 text-sm font-medium shadow-lg"
                    :class="llmToast.type === 'error' ? 'bg-red-600 text-white' : 'bg-emerald-600 text-white'">
                    {{ llmToast.msg }}
                </div>
            </Transition>

            <section class="space-y-6">
                <div>
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">Modelos de Linguagem (LLM)</h2>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Configure as chaves de API dos provedores de IA usados na plataforma — chat de alunos, relatórios avançados e automações.</p>
                </div>

                <!-- Provedor preferido -->
                <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-800/50">
                    <div class="flex items-center gap-2 mb-3">
                        <Star class="h-4 w-4 text-amber-500" />
                        <h3 class="font-semibold text-sm text-zinc-900 dark:text-white">Provedor preferido</h3>
                    </div>
                    <p class="text-xs text-zinc-400 mb-4">A IA tentará usar este provedor primeiro. Se a chave não estiver configurada, usa o próximo disponível.</p>
                    <div class="flex flex-wrap gap-2">
                        <button v-for="p in llm_providers" :key="p.id" type="button"
                            class="flex items-center gap-2 rounded-xl border px-4 py-2 text-sm font-medium transition-all"
                            :class="llmPreferred === p.id
                                ? 'border-transparent text-white shadow-sm'
                                : 'border-zinc-200 text-zinc-600 hover:border-zinc-300 dark:border-zinc-700 dark:text-zinc-300'"
                            :style="llmPreferred === p.id ? `background:${p.color}` : ''"
                            @click="setPreferred(p.id)">
                            <CheckCircle2 v-if="llmPreferred === p.id" class="h-3.5 w-3.5" />
                            {{ p.name }}
                        </button>
                    </div>
                </div>

                <!-- Cards de provedores -->
                <div class="grid gap-4 sm:grid-cols-2">
                    <div v-for="p in llm_providers" :key="p.id"
                        class="relative rounded-2xl border bg-white p-5 dark:bg-zinc-800/60 transition-all"
                        :class="[
                            llmState[p.id]?.configured ? 'border-emerald-200 dark:border-emerald-800/50' : 'border-zinc-200 dark:border-zinc-700',
                            p.coming_soon ? 'opacity-75' : '',
                        ]">

                        <!-- Coming soon badge -->
                        <div v-if="p.coming_soon" class="absolute right-4 top-4 rounded-full bg-zinc-100 px-2 py-0.5 text-[10px] font-semibold text-zinc-500 dark:bg-zinc-700 dark:text-zinc-400">
                            Em breve
                        </div>

                        <!-- Header do card -->
                        <div class="flex items-start gap-3 mb-4">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-white font-bold text-sm"
                                :style="`background:${p.color}`">
                                {{ p.name.slice(0, 1) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <p class="font-semibold text-zinc-900 dark:text-white">{{ p.name }}</p>
                                    <span v-if="llmState[p.id]?.configured"
                                        class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">
                                        <CheckCircle2 class="h-3 w-3" /> Configurado
                                    </span>
                                </div>
                                <p class="text-xs text-zinc-400 mt-0.5">{{ p.subtitle }}</p>
                            </div>
                        </div>

                        <p class="mb-4 text-xs text-zinc-500 dark:text-zinc-400">{{ p.description }}</p>

                        <!-- Chave atual (mascarada) -->
                        <div v-if="llmState[p.id]?.key_hint" class="mb-3 flex items-center gap-2 rounded-lg bg-zinc-50 px-3 py-2 dark:bg-zinc-900">
                            <span class="flex-1 font-mono text-xs text-zinc-500 dark:text-zinc-400 tracking-wide">{{ llmState[p.id].key_hint }}</span>
                            <button type="button" :disabled="llmRemoving[p.id]" @click="removeLlmKey(p.id)"
                                class="shrink-0 text-red-400 hover:text-red-600 disabled:opacity-50">
                                <Loader2 v-if="llmRemoving[p.id]" class="h-3.5 w-3.5 animate-spin" />
                                <Trash2 v-else class="h-3.5 w-3.5" />
                            </button>
                        </div>

                        <!-- Input nova chave -->
                        <div class="flex gap-2" :class="{ 'pointer-events-none opacity-50': p.coming_soon }">
                            <input v-model="llmInputs[p.id]" type="password" :placeholder="`Nova chave de API ${p.name}`"
                                class="min-w-0 flex-1 rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900 dark:text-white"
                                @keyup.enter="!p.coming_soon && saveLlmKey(p.id)" />
                            <button type="button"
                                :disabled="llmSaving[p.id] || p.coming_soon || !llmInputs[p.id]?.trim()"
                                class="shrink-0 rounded-xl px-4 py-2 text-sm font-medium text-white transition-all disabled:opacity-50"
                                :style="`background:${p.color}`"
                                @click="saveLlmKey(p.id)">
                                <Loader2 v-if="llmSaving[p.id]" class="h-4 w-4 animate-spin" />
                                <span v-else>Salvar</span>
                            </button>
                        </div>
                    </div>
                </div>
            </section>
        </template>

        <GatewayConfigSidebar
            :open="gatewaySidebarOpen"
            :gateway-slug="selectedGatewaySlug"
            @close="closeGatewaySidebar"
            @saved="onGatewaySaved"
        />
        <WebhookSidebar
            :open="webhookSidebarOpen"
            :webhooks="webhooks"
            :webhook-events="webhook_events"
            :products="products"
            @close="closeWebhookSidebar"
            @saved="onWebhookSaved"
        />
        <UtmifySidebar
            :open="utmifySidebarOpen"
            :utmify_integrations="utmify_integrations"
            :products="products"
            :api_applications="api_applications"
            @close="closeUtmifySidebar"
            @saved="onUtmifySaved"
        />
        <SpedySidebar
            :open="spedySidebarOpen"
            :spedy_integrations="spedy_integrations"
            :products="products"
            @close="closeSpedySidebar"
            @saved="onSpedySaved"
        />

        <MessagingSidebar
            :open="messagingSidebarOpen"
            :providers="messaging_providers"
            :configured_providers="messaging_configured_providers"
            @close="messagingSidebarOpen = false"
            @saved="() => {}"
        />

        <!-- Plugin sidebars (ex.: AutoZap) -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition-opacity duration-200"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition-opacity duration-200"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="pluginSidebarOpen"
                    class="fixed inset-0 z-[100000] bg-black/30"
                    aria-hidden="true"
                    @click="closePluginSidebar"
                />
            </Transition>
            <Transition
                enter-active-class="transition-transform duration-300 ease-out"
                enter-from-class="translate-x-full"
                enter-to-class="translate-x-0"
                leave-active-class="transition-transform duration-300 ease-in"
                leave-from-class="translate-x-0"
                leave-to-class="translate-x-full"
            >
                <aside
                    v-if="pluginSidebarOpen"
                    class="fixed top-0 right-0 z-[100001] flex h-full w-full max-w-md flex-col bg-white shadow-2xl dark:bg-zinc-900"
                    role="dialog"
                    aria-label="Configuração da integração"
                    @click.stop
                >
                    <div class="flex shrink-0 items-center justify-between border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
                        <div class="text-lg font-semibold text-zinc-900 dark:text-white">
                            {{ selectedPluginAppName || 'Integração' }}
                        </div>
                        <button
                            type="button"
                            class="rounded-lg p-2 text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-700 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white"
                            aria-label="Fechar"
                            @click="closePluginSidebar"
                        >
                            ✕
                        </button>
                    </div>
                    <div class="flex-1 overflow-y-auto p-4">
                        <component
                            v-if="selectedPluginComponentName && resolvePluginComponent(selectedPluginComponentName)"
                            :is="resolvePluginComponent(selectedPluginComponentName)"
                            @saved="router.reload()"
                            @close="closePluginSidebar"
                        />
                        <div v-else class="rounded-xl border border-dashed border-zinc-300 p-4 text-sm text-zinc-600 dark:border-zinc-700 dark:text-zinc-400">
                            Não foi possível carregar o painel desta integração do plugin.
                        </div>
                    </div>
                </aside>
            </Transition>
        </Teleport>
    </div>
</template>
