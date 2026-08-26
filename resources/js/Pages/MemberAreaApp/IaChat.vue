<script setup>
import { ref, nextTick, onMounted, computed } from 'vue';
import { Send, Bot, User, Sparkles, RefreshCw } from 'lucide-vue-next';
import { useMemberBase } from '@/composables/useMemberBase';

const props = defineProps({
    slug:        { type: String, required: true },
    session_id:  { type: String, required: true },
    messages:    { type: Array, default: () => [] },
    available:   { type: Boolean, default: false },
});

const memberBase = useMemberBase(computed(() => props.slug));

const messages   = ref([...props.messages]);
const input      = ref('');
const loading    = ref(false);
const messagesEl = ref(null);
const sessionId  = ref(props.session_id);

function csrf() { return document.querySelector('meta[name="csrf-token"]')?.content ?? ''; }

function scrollBottom() {
    nextTick(() => {
        if (messagesEl.value) {
            messagesEl.value.scrollTop = messagesEl.value.scrollHeight;
        }
    });
}

onMounted(scrollBottom);

async function send() {
    const text = input.value.trim();
    if (! text || loading.value) return;

    messages.value.push({ role: 'user', content: text, created_at: new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }) });
    input.value = '';
    loading.value = true;
    scrollBottom();

    try {
        const res = await window.axios.post(`${memberBase.value}/ia/chat`, {
            message:    text,
            session_id: sessionId.value,
        }, { headers: { 'X-CSRF-TOKEN': csrf() } });

        messages.value.push({
            role:       'assistant',
            content:    res.data.reply,
            created_at: new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }),
        });
    } catch (e) {
        messages.value.push({
            role:    'assistant',
            content: e.response?.data?.error ?? 'Ops, ocorreu um erro. Tente novamente.',
            error:   true,
            created_at: new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }),
        });
    } finally {
        loading.value = false;
        scrollBottom();
    }
}

function newSession() {
    sessionId.value = crypto.randomUUID();
    messages.value  = [];
}

function onKeydown(e) {
    if (e.key === 'Enter' && ! e.shiftKey) {
        e.preventDefault();
        send();
    }
}
</script>

<template>
    <div class="flex flex-col h-[calc(100vh-120px)] max-w-3xl mx-auto">
        <!-- Header -->
        <div class="flex items-center justify-between px-4 py-3 border-b border-zinc-100 dark:border-zinc-800">
            <div class="flex items-center gap-2">
                <div class="h-8 w-8 rounded-xl bg-violet-100 dark:bg-violet-900/30 flex items-center justify-center">
                    <Sparkles class="h-4 w-4 text-violet-600 dark:text-violet-400" />
                </div>
                <div>
                    <p class="text-sm font-semibold text-zinc-900 dark:text-white">Assistente Virtual</p>
                    <p class="text-[10px] text-zinc-400">Suporte emocional e orientação</p>
                </div>
            </div>
            <button @click="newSession" class="flex items-center gap-1 text-xs text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300">
                <RefreshCw class="h-3.5 w-3.5" /> Nova conversa
            </button>
        </div>

        <!-- IA não configurada -->
        <div v-if="!available" class="flex-1 flex flex-col items-center justify-center gap-3 text-center px-6">
            <Bot class="h-12 w-12 text-zinc-200 dark:text-zinc-700" />
            <p class="text-zinc-500 dark:text-zinc-400 text-sm">O assistente ainda não está configurado nesta plataforma.</p>
        </div>

        <!-- Mensagens -->
        <div v-else ref="messagesEl" class="flex-1 overflow-y-auto px-4 py-4 space-y-4">
            <!-- Estado inicial -->
            <div v-if="!messages.length" class="flex flex-col items-center gap-3 py-12 text-center">
                <div class="h-14 w-14 rounded-2xl bg-violet-100 dark:bg-violet-900/30 flex items-center justify-center">
                    <Sparkles class="h-7 w-7 text-violet-600" />
                </div>
                <p class="font-semibold text-zinc-900 dark:text-white">Olá! Como posso te ajudar?</p>
                <p class="text-sm text-zinc-500 max-w-xs">Estou aqui para apoiar sua jornada de desenvolvimento pessoal e saúde emocional.</p>
            </div>

            <!-- Bolhas de mensagens -->
            <div v-for="(msg, i) in messages" :key="i" class="flex gap-3"
                :class="msg.role === 'user' ? 'flex-row-reverse' : 'flex-row'">
                <!-- Avatar -->
                <div class="shrink-0 h-7 w-7 rounded-full flex items-center justify-center"
                    :class="msg.role === 'user' ? 'bg-violet-600' : 'bg-zinc-100 dark:bg-zinc-800'">
                    <User v-if="msg.role === 'user'" class="h-3.5 w-3.5 text-white" />
                    <Bot v-else class="h-3.5 w-3.5 text-zinc-500 dark:text-zinc-300" />
                </div>

                <!-- Conteúdo -->
                <div class="max-w-[75%]">
                    <div class="rounded-2xl px-4 py-2.5 text-sm leading-relaxed whitespace-pre-wrap"
                        :class="msg.role === 'user'
                            ? 'bg-violet-600 text-white rounded-tr-sm'
                            : msg.error ? 'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-300 rounded-tl-sm' : 'bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 border border-zinc-100 dark:border-zinc-700 rounded-tl-sm'">
                        {{ msg.content }}
                    </div>
                    <p class="text-[10px] text-zinc-400 mt-1" :class="msg.role === 'user' ? 'text-right' : 'text-left'">
                        {{ msg.created_at }}
                    </p>
                </div>
            </div>

            <!-- Digitando -->
            <div v-if="loading" class="flex gap-3">
                <div class="h-7 w-7 rounded-full bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center shrink-0">
                    <Bot class="h-3.5 w-3.5 text-zinc-500" />
                </div>
                <div class="rounded-2xl rounded-tl-sm bg-white dark:bg-zinc-800 border border-zinc-100 dark:border-zinc-700 px-4 py-3 flex gap-1.5 items-center">
                    <span class="h-1.5 w-1.5 rounded-full bg-zinc-400 animate-bounce" style="animation-delay:0ms" />
                    <span class="h-1.5 w-1.5 rounded-full bg-zinc-400 animate-bounce" style="animation-delay:150ms" />
                    <span class="h-1.5 w-1.5 rounded-full bg-zinc-400 animate-bounce" style="animation-delay:300ms" />
                </div>
            </div>
        </div>

        <!-- Input -->
        <div v-if="available" class="border-t border-zinc-100 dark:border-zinc-800 px-4 py-3">
            <div class="flex gap-2 items-end">
                <textarea v-model="input" @keydown="onKeydown" :disabled="loading"
                    placeholder="Digite sua mensagem… (Enter para enviar, Shift+Enter nova linha)"
                    rows="1"
                    class="flex-1 rounded-2xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 px-4 py-2.5 text-sm text-zinc-900 dark:text-white placeholder-zinc-400 focus:border-violet-500 focus:outline-none resize-none max-h-32 overflow-y-auto" />
                <button @click="send" :disabled="loading || !input.trim()"
                    class="h-10 w-10 rounded-2xl bg-violet-600 hover:bg-violet-700 disabled:opacity-50 flex items-center justify-center transition shrink-0">
                    <Send class="h-4 w-4 text-white" />
                </button>
            </div>
            <p class="text-[10px] text-zinc-400 mt-1.5 text-center">
                Este assistente não substitui acompanhamento profissional.
            </p>
        </div>
    </div>
</template>
