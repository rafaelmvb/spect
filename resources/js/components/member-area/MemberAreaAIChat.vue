<script setup>
import { ref, nextTick, onMounted } from 'vue';
import { X, Send, Bot, RotateCcw, Loader } from 'lucide-vue-next';
import axios from 'axios';

const props = defineProps({
    basePath:     { type: String,  required: true },
    primaryColor: { type: String,  default: '#6FCF00' },
    lessonId:     { type: Number,  default: null },
});

const emit = defineEmits(['close']);

const messages       = ref([]);
const inputText      = ref('');
const loading        = ref(false);
const loadingSession = ref(true);
const errorMsg       = ref('');
const messagesRef    = ref(null);
const inputRef       = ref(null);
const sessionId      = ref('');

const WELCOME = 'Olá! Sou seu assistente virtual. Estou aqui para tirar dúvidas, ajudar nos estudos, acompanhar seu progresso e te apresentar as demais trilhas disponíveis. Como posso ajudar?';

function csrf() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

async function scrollBottom() {
    await nextTick();
    if (messagesRef.value) messagesRef.value.scrollTop = messagesRef.value.scrollHeight;
}

async function fetchHistory(sid) {
    const { data } = await axios.get(`${props.basePath}/ia/chat/history`, {
        params: { session_id: sid },
        headers: { Accept: 'application/json' },
    });
    return data.messages ?? [];
}

async function send() {
    const text = inputText.value.trim();
    if (!text || loading.value) return;

    inputText.value = '';
    errorMsg.value  = '';
    messages.value.push({ role: 'user', content: text, id: Date.now() });
    await scrollBottom();

    loading.value = true;
    try {
        const payload = { message: text, session_id: sessionId.value };
        if (props.lessonId) payload.lesson_id = props.lessonId;
        const { data } = await axios.post(
            `${props.basePath}/ia/chat`,
            payload,
            { headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' } }
        );
        messages.value.push({ role: 'assistant', content: data.reply, id: data.message_id ?? Date.now() });
    } catch (err) {
        errorMsg.value = err.response?.data?.error ?? 'Erro de conexão. Tente novamente.';
    } finally {
        loading.value = false;
        await scrollBottom();
        inputRef.value?.focus();
    }
}

async function newChat() {
    try {
        const { data } = await axios.post(`${props.basePath}/ia/chat/session`, {}, {
            headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
        });
        sessionId.value = data.session_id;
    } catch {
        sessionId.value = crypto.randomUUID?.() ?? Math.random().toString(36).slice(2);
    }
    messages.value = [{ role: 'assistant', content: 'Nova conversa iniciada. Como posso ajudar?', id: 'w2' }];
    errorMsg.value = '';
    nextTick(() => inputRef.value?.focus());
}

function onKeydown(e) {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(); }
}

// Converte texto da IA em tokens: {type:'text'|'ytlink', content, url, seconds, label}
function parseTokens(content) {
    const tokens = [];
    // Regex: [texto](https://youtu.be/... ou youtube.com/...)
    const re = /\[([^\]]+)\]\((https?:\/\/(?:youtu\.be|(?:www\.)?youtube\.com)[^\s)]+)\)/g;
    let last = 0;
    let m;
    while ((m = re.exec(content)) !== null) {
        if (m.index > last) tokens.push({ type: 'text', content: content.slice(last, m.index) });
        const tMatch = m[2].match(/[?&]t=(\d+)/);
        const seconds = tMatch ? parseInt(tMatch[1]) : null;
        const min = seconds !== null ? Math.floor(seconds / 60) : null;
        const sec = seconds !== null ? seconds % 60 : null;
        const label = min !== null ? `${min}:${String(sec).padStart(2, '0')}` : null;
        tokens.push({ type: 'ytlink', content: m[1], url: m[2], seconds, label });
        last = m.index + m[0].length;
    }
    if (last < content.length) tokens.push({ type: 'text', content: content.slice(last) });
    return tokens;
}

onMounted(async () => {
    try {
        const { data } = await axios.get(`${props.basePath}/ia/chat/session`, {
            headers: { Accept: 'application/json' },
        });
        sessionId.value = data.session_id;

        if (data.is_new) {
            messages.value = [{ role: 'assistant', content: WELCOME, id: 'welcome' }];
        } else {
            const history = await fetchHistory(data.session_id);
            if (history.length > 0) {
                messages.value = history.map(m => ({ role: m.role, content: m.content, id: m.id }));
                await scrollBottom();
            } else {
                messages.value = [{ role: 'assistant', content: WELCOME, id: 'welcome' }];
            }
        }
    } catch {
        sessionId.value = crypto.randomUUID?.() ?? Math.random().toString(36).slice(2);
        messages.value  = [{ role: 'assistant', content: WELCOME, id: 'welcome' }];
    } finally {
        loadingSession.value = false;
        nextTick(() => inputRef.value?.focus());
    }
});
</script>

<template>
    <!-- Overlay mobile -->
    <div class="aic-overlay" @click="emit('close')" />

    <!-- Painel -->
    <div class="aic-panel">
            <!-- Header -->
            <div class="aic-header" :style="{ borderBottom: '1px solid var(--ma-border, #1E3448)' }">
                <div class="aic-header__left">
                    <div class="aic-header__icon" :style="{ background: primaryColor }">
                        <Bot style="width:16px;height:16px;color:#0D1F2D;" />
                    </div>
                    <div>
                        <p class="aic-header__title">Chat com Especialista</p>
                        <p class="aic-header__sub">Assistente virtual</p>
                    </div>
                </div>
                <div class="aic-header__actions">
                    <button type="button" class="aic-icon-btn" title="Nova conversa" @click="newChat">
                        <RotateCcw style="width:15px;height:15px;" />
                    </button>
                    <button type="button" class="aic-icon-btn" title="Fechar" @click="emit('close')">
                        <X style="width:17px;height:17px;" />
                    </button>
                </div>
            </div>

            <!-- Mensagens -->
            <div ref="messagesRef" class="aic-messages">
                <!-- Carregando sessão -->
                <div v-if="loadingSession" class="aic-msg aic-msg--ai">
                    <div class="aic-msg__avatar" :style="{ background: primaryColor }">
                        <Bot style="width:12px;height:12px;color:#0D1F2D;" />
                    </div>
                    <div class="aic-msg__bubble aic-msg__bubble--ai aic-typing">
                        <span /><span /><span />
                    </div>
                </div>

                <div v-for="msg in messages" :key="msg.id" class="aic-msg" :class="msg.role === 'user' ? 'aic-msg--user' : 'aic-msg--ai'">
                    <!-- Avatar IA -->
                    <div v-if="msg.role === 'assistant'" class="aic-msg__avatar" :style="{ background: primaryColor }">
                        <Bot style="width:12px;height:12px;color:#0D1F2D;" />
                    </div>
                    <div class="aic-msg__bubble" :class="msg.role === 'user' ? 'aic-msg__bubble--user' : 'aic-msg__bubble--ai'"
                        :style="msg.role === 'user' ? { background: primaryColor, color: '#0D1F2D' } : {}">
                        <!-- Mensagens do usuário: texto simples -->
                        <p v-if="msg.role === 'user'" class="aic-msg__text">{{ msg.content }}</p>
                        <!-- Mensagens da IA: renderiza links de timestamp do YouTube como cards -->
                        <template v-else>
                            <template v-for="(token, ti) in parseTokens(msg.content)" :key="ti">
                                <p v-if="token.type === 'text'" class="aic-msg__text">{{ token.content }}</p>
                                <a
                                    v-else
                                    :href="token.url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="aic-yt-card"
                                    :style="{ borderColor: primaryColor + '55' }"
                                >
                                    <span class="aic-yt-card__play" :style="{ background: primaryColor, color: '#0D1F2D' }">▶</span>
                                    <span class="aic-yt-card__label">{{ token.content }}</span>
                                    <span v-if="token.label" class="aic-yt-card__time">{{ token.label }}</span>
                                </a>
                            </template>
                        </template>
                    </div>
                </div>

                <!-- Typing indicator -->
                <div v-if="loading" class="aic-msg aic-msg--ai">
                    <div class="aic-msg__avatar" :style="{ background: primaryColor }">
                        <Bot style="width:12px;height:12px;color:#0D1F2D;" />
                    </div>
                    <div class="aic-msg__bubble aic-msg__bubble--ai aic-typing">
                        <span /><span /><span />
                    </div>
                </div>

                <!-- Erro -->
                <div v-if="errorMsg" class="aic-error">
                    {{ errorMsg }}
                </div>
            </div>

            <!-- Input -->
            <div class="aic-input-area">
                <textarea
                    ref="inputRef"
                    v-model="inputText"
                    class="aic-input"
                    placeholder="Digite sua mensagem..."
                    rows="1"
                    :disabled="loading"
                    @keydown="onKeydown"
                    @input="$event.target.style.height = 'auto'; $event.target.style.height = Math.min($event.target.scrollHeight, 120) + 'px'"
                />
                <button
                    type="button"
                    class="aic-send"
                    :disabled="!inputText.trim() || loading"
                    :style="{ background: inputText.trim() && !loading ? primaryColor : 'rgba(255,255,255,0.08)', color: inputText.trim() && !loading ? '#0D1F2D' : 'var(--ma-text-2, #7A9BAD)' }"
                    @click="send"
                >
                    <Loader v-if="loading" style="width:17px;height:17px;" class="aic-spin" />
                    <Send v-else style="width:17px;height:17px;" />
                </button>
            </div>
        </div>
</template>

<style scoped>
/* ── Overlay (só mobile) ── */
.aic-overlay {
    display: none;
}
@media (max-width: 1023px) {
    .aic-overlay {
        display: block;
        position: fixed;
        inset: 0;
        z-index: 69;
        background: rgba(0,0,0,0.5);
        backdrop-filter: blur(4px);
    }
}

/* ── Painel ── */
.aic-panel {
    position: fixed;
    z-index: 70;
    display: flex;
    flex-direction: column;
    background: var(--ma-surface, #132435);
    border: 1px solid var(--ma-border, #1E3448);
    box-shadow: 0 24px 80px rgba(0,0,0,0.6);
    font-family: 'DM Sans', sans-serif;

    /* Mobile: painel sobe de baixo, quase fullscreen */
    bottom: 0;
    left: 0;
    right: 0;
    height: 80vh;
    border-radius: 20px 20px 0 0;
}

@media (min-width: 1024px) {
    .aic-panel {
        /* Desktop: painel lateral direito */
        top: 12px;
        right: 12px;
        bottom: 12px;
        left: auto;
        width: 360px;
        height: auto;
        border-radius: 18px;
    }
}

/* ── Header ── */
.aic-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 16px;
    flex-shrink: 0;
}
.aic-header__left {
    display: flex;
    align-items: center;
    gap: 10px;
}
.aic-header__icon {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.aic-header__title {
    font-size: 14px;
    font-weight: 700;
    color: var(--ma-text, #FFFFFF);
    margin: 0;
}
.aic-header__sub {
    font-size: 11px;
    color: var(--ma-text-2, #7A9BAD);
    margin: 1px 0 0;
}
.aic-header__actions {
    display: flex;
    gap: 4px;
}

.aic-icon-btn {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    border: none;
    background: none;
    cursor: pointer;
    color: var(--ma-text-2, #7A9BAD);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.15s, color 0.15s;
}
.aic-icon-btn:hover {
    background: rgba(255,255,255,0.07);
    color: var(--ma-text, #FFFFFF);
}

/* ── Mensagens ── */
.aic-messages {
    flex: 1;
    overflow-y: auto;
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 12px;
    scrollbar-width: thin;
    scrollbar-color: var(--ma-border, #1E3448) transparent;
}

.aic-msg {
    display: flex;
    align-items: flex-end;
    gap: 8px;
}
.aic-msg--user {
    flex-direction: row-reverse;
}
.aic-msg--ai {
    flex-direction: row;
}

.aic-msg__avatar {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}

.aic-msg__bubble {
    max-width: 80%;
    border-radius: 16px;
    padding: 10px 14px;
}
.aic-msg__bubble--user {
    border-bottom-right-radius: 4px;
}
.aic-msg__bubble--ai {
    background: rgba(255,255,255,0.06);
    border: 1px solid var(--ma-border, #1E3448);
    border-bottom-left-radius: 4px;
}

.aic-msg__text {
    font-size: 13px;
    line-height: 1.55;
    margin: 0;
    white-space: pre-wrap;
    word-break: break-word;
    color: inherit;
}
.aic-msg__bubble--ai .aic-msg__text {
    color: var(--ma-text, #FFFFFF);
}

/* Typing dots */
.aic-typing {
    display: flex !important;
    align-items: center;
    gap: 5px;
    padding: 12px 16px;
}
.aic-typing span {
    display: block;
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: var(--ma-text-2, #7A9BAD);
    animation: aic-bounce 1.2s infinite ease-in-out;
}
.aic-typing span:nth-child(2) { animation-delay: 0.2s; }
.aic-typing span:nth-child(3) { animation-delay: 0.4s; }
@keyframes aic-bounce {
    0%, 80%, 100% { transform: translateY(0); opacity: 0.4; }
    40%            { transform: translateY(-6px); opacity: 1; }
}

/* Erro */
.aic-error {
    font-size: 12px;
    color: #E05555;
    background: rgba(224,85,85,0.1);
    border: 1px solid rgba(224,85,85,0.2);
    border-radius: 10px;
    padding: 8px 12px;
    text-align: center;
}

/* ── Input area ── */
.aic-input-area {
    display: flex;
    align-items: flex-end;
    gap: 8px;
    padding: 12px 14px;
    border-top: 1px solid var(--ma-border, #1E3448);
    flex-shrink: 0;
}

.aic-input {
    flex: 1;
    background: rgba(255,255,255,0.05);
    border: 1px solid var(--ma-border, #1E3448);
    border-radius: 14px;
    padding: 10px 14px;
    font-size: 13px;
    font-family: 'DM Sans', sans-serif;
    color: var(--ma-text, #FFFFFF);
    outline: none;
    resize: none;
    max-height: 120px;
    line-height: 1.5;
    transition: border-color 0.2s;
    scrollbar-width: none;
}
.aic-input::placeholder { color: var(--ma-text-2, #7A9BAD); }
.aic-input:focus { border-color: rgba(111,207,0,0.4); }
.aic-input:disabled { opacity: 0.5; cursor: not-allowed; }

.aic-send {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    border: none;
    cursor: pointer;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.15s, opacity 0.15s;
}
.aic-send:disabled { cursor: not-allowed; }

@keyframes aic-spin {
    from { transform: rotate(0deg); }
    to   { transform: rotate(360deg); }
}
.aic-spin { animation: aic-spin 0.8s linear infinite; }

/* ── Card de trecho de vídeo YouTube ── */
.aic-yt-card {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 6px;
    padding: 8px 12px;
    border-radius: 10px;
    border: 1px solid rgba(255,255,255,0.12);
    background: rgba(255,255,255,0.04);
    text-decoration: none;
    cursor: pointer;
    transition: background 0.15s, border-color 0.15s;
}
.aic-yt-card:hover {
    background: rgba(255,255,255,0.08);
}
.aic-yt-card__play {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    flex-shrink: 0;
}
.aic-yt-card__label {
    flex: 1;
    font-size: 12px;
    font-weight: 600;
    color: var(--ma-text, #FFFFFF);
    line-height: 1.3;
}
.aic-yt-card__time {
    font-family: 'DM Mono', monospace;
    font-size: 11px;
    color: var(--ma-text-2, #7A9BAD);
    flex-shrink: 0;
}
</style>
