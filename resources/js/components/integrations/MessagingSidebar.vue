<script setup>
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import { X, ChevronDown, ChevronUp, Eye, EyeOff, Loader2, CheckCircle } from 'lucide-vue-next';
import Button from '@/components/ui/Button.vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    providers: { type: Array, default: () => [] },         // lista de provedores do backend
    configured_providers: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['close', 'saved']);

const configuredProviders = ref({ ...props.configured_providers });
watch(() => props.configured_providers, v => { configuredProviders.value = { ...v }; });

const selectedProvider = ref(null);
const providerForm = ref({});
const showPassword = ref({});
const saving = ref(false);
const toast = ref(null);

function selectProvider(p) {
    selectedProvider.value = selectedProvider.value?.slug === p.slug ? null : p;
    providerForm.value = {};
    showPassword.value = {};
    if (selectedProvider.value) {
        p.credential_keys.forEach(k => { providerForm.value[k.key] = ''; });
    }
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

async function save() {
    if (!selectedProvider.value) return;
    saving.value = true;
    try {
        await axios.post(
            `/mensagens/provedores/${selectedProvider.value.slug}/credenciais`,
            providerForm.value,
            { headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' } }
        );
        configuredProviders.value[selectedProvider.value.slug] = true;
        toast.value = `✅ Credenciais do ${selectedProvider.value.label} salvas!`;
        setTimeout(() => toast.value = null, 3000);
        emit('saved');
    } catch {
        toast.value = '❌ Erro ao salvar.';
        setTimeout(() => toast.value = null, 3000);
    } finally {
        saving.value = false;
    }
}

const whatsappProviders = computed(() => props.providers.filter(p => p.channel === 'whatsapp'));
const smsProviders = computed(() => props.providers.filter(p => p.channel === 'sms'));
</script>

<template>
    <Teleport to="body">
        <div v-show="open" class="fixed inset-0 z-[100000] flex justify-end" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-zinc-900/50 dark:bg-zinc-950/60" aria-hidden="true" @click="$emit('close')" />

            <aside class="relative flex h-full w-full max-w-lg flex-col rounded-l-2xl bg-white shadow-2xl dark:bg-zinc-900">
                <!-- Header -->
                <div class="flex items-center justify-between border-b border-zinc-200 px-5 py-5 dark:border-zinc-700">
                    <div>
                        <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">WhatsApp & SMS</h2>
                        <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">Configure as credenciais de cada provedor.</p>
                    </div>
                    <button type="button" class="rounded-lg p-2 text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-800" @click="$emit('close')">
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <!-- Toast -->
                <Transition enter-active-class="transition duration-200" enter-from-class="opacity-0 -translate-y-1" enter-to-class="opacity-100">
                    <div v-if="toast" class="mx-5 mt-3 rounded-xl bg-zinc-800 px-4 py-2.5 text-sm text-white dark:bg-zinc-700">
                        {{ toast }}
                    </div>
                </Transition>

                <!-- Corpo -->
                <div class="flex-1 overflow-y-auto p-5 space-y-6">

                    <!-- WhatsApp -->
                    <div>
                        <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-green-600 dark:text-green-400">📱 WhatsApp</p>
                        <div class="space-y-2">
                            <div v-for="p in whatsappProviders" :key="p.slug"
                                class="overflow-hidden rounded-xl border transition"
                                :class="selectedProvider?.slug === p.slug ? 'border-green-400 dark:border-green-500' : 'border-zinc-200 dark:border-zinc-700'">

                                <button type="button" class="flex w-full items-center justify-between px-4 py-3 text-left hover:bg-zinc-50 dark:hover:bg-zinc-800/50"
                                    @click="selectProvider(p)">
                                    <div class="flex items-center gap-2">
                                        <CheckCircle v-if="configuredProviders[p.slug]" class="h-4 w-4 text-green-500" />
                                        <div v-else class="h-4 w-4 rounded-full border-2 border-zinc-300 dark:border-zinc-600" />
                                        <span class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ p.label }}</span>
                                    </div>
                                    <ChevronUp v-if="selectedProvider?.slug === p.slug" class="h-4 w-4 text-zinc-400" />
                                    <ChevronDown v-else class="h-4 w-4 text-zinc-400" />
                                </button>

                                <div v-if="selectedProvider?.slug === p.slug"
                                    class="border-t border-zinc-100 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/50 space-y-3">
                                    <div v-for="cred in p.credential_keys" :key="cred.key">
                                        <label class="mb-1 block text-xs font-medium text-zinc-600 dark:text-zinc-400">{{ cred.label }}</label>
                                        <div class="flex items-center gap-2">
                                            <input v-model="providerForm[cred.key]"
                                                :type="showPassword[cred.key] ? 'text' : cred.type"
                                                class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900"
                                                :placeholder="cred.label" />
                                            <button v-if="cred.type === 'password'" type="button"
                                                class="shrink-0 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300"
                                                @click="showPassword[cred.key] = !showPassword[cred.key]">
                                                <EyeOff v-if="showPassword[cred.key]" class="h-4 w-4" />
                                                <Eye v-else class="h-4 w-4" />
                                            </button>
                                        </div>
                                    </div>
                                    <Button size="sm" :disabled="saving" @click="save">
                                        <Loader2 v-if="saving" class="mr-2 h-3.5 w-3.5 animate-spin" />
                                        Salvar credenciais
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SMS -->
                    <div>
                        <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-blue-600 dark:text-blue-400">💬 SMS</p>
                        <div class="space-y-2">
                            <div v-for="p in smsProviders" :key="p.slug"
                                class="overflow-hidden rounded-xl border transition"
                                :class="selectedProvider?.slug === p.slug ? 'border-blue-400 dark:border-blue-500' : 'border-zinc-200 dark:border-zinc-700'">

                                <button type="button" class="flex w-full items-center justify-between px-4 py-3 text-left hover:bg-zinc-50 dark:hover:bg-zinc-800/50"
                                    @click="selectProvider(p)">
                                    <div class="flex items-center gap-2">
                                        <CheckCircle v-if="configuredProviders[p.slug]" class="h-4 w-4 text-blue-500" />
                                        <div v-else class="h-4 w-4 rounded-full border-2 border-zinc-300 dark:border-zinc-600" />
                                        <span class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ p.label }}</span>
                                    </div>
                                    <ChevronUp v-if="selectedProvider?.slug === p.slug" class="h-4 w-4 text-zinc-400" />
                                    <ChevronDown v-else class="h-4 w-4 text-zinc-400" />
                                </button>

                                <div v-if="selectedProvider?.slug === p.slug"
                                    class="border-t border-zinc-100 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/50 space-y-3">
                                    <div v-for="cred in p.credential_keys" :key="cred.key">
                                        <label class="mb-1 block text-xs font-medium text-zinc-600 dark:text-zinc-400">{{ cred.label }}</label>
                                        <div class="flex items-center gap-2">
                                            <input v-model="providerForm[cred.key]"
                                                :type="showPassword[cred.key] ? 'text' : cred.type"
                                                class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900"
                                                :placeholder="cred.label" />
                                            <button v-if="cred.type === 'password'" type="button"
                                                class="shrink-0 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300"
                                                @click="showPassword[cred.key] = !showPassword[cred.key]">
                                                <EyeOff v-if="showPassword[cred.key]" class="h-4 w-4" />
                                                <Eye v-else class="h-4 w-4" />
                                            </button>
                                        </div>
                                    </div>
                                    <Button size="sm" :disabled="saving" @click="save">
                                        <Loader2 v-if="saving" class="mr-2 h-3.5 w-3.5 animate-spin" />
                                        Salvar credenciais
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </Teleport>
</template>
