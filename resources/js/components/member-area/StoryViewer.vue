<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { X, Heart } from 'lucide-vue-next';
import axios from 'axios';
import { useMemberBase } from '@/composables/useMemberBase';

const props = defineProps({
    stories:     { type: Array, required: true },
    startIndex:  { type: Number, default: 0 },
    slug:        { type: String, required: true },
    primaryColor:{ type: String, default: '#6FCF00' },
});

const emit = defineEmits(['close']);

const memberBase = useMemberBase(computed(() => props.slug));
const currentIndex = ref(props.startIndex);
const current = computed(() => props.stories[currentIndex.value] ?? null);
const liked = ref({});
const progressPct = ref(0);
let timer = null;
let progressTimer = null;
const DURATION = 5000; // 5s por story (ou comprimento do vídeo)
const videoRef = ref(null);

function csrfToken() { return document.querySelector('meta[name="csrf-token"]')?.content ?? ''; }

function next() {
    if (currentIndex.value < props.stories.length - 1) {
        currentIndex.value++;
        resetProgress();
    } else {
        emit('close');
    }
}

function prev() {
    if (currentIndex.value > 0) {
        currentIndex.value--;
        resetProgress();
    }
}

function startProgress(duration = DURATION) {
    clearInterval(progressTimer);
    progressPct.value = 0;
    const steps = 100;
    const interval = duration / steps;
    progressTimer = setInterval(() => {
        progressPct.value += 1;
        if (progressPct.value >= 100) {
            clearInterval(progressTimer);
            next();
        }
    }, interval);
}

function resetProgress() {
    clearInterval(progressTimer);
    progressPct.value = 0;
    if (videoRef.value) videoRef.value.pause();
}

function onVideoLoaded() {
    const dur = videoRef.value?.duration * 1000 || DURATION;
    startProgress(dur);
    videoRef.value?.play().catch(() => {});
}

async function likeStory() {
    if (!current.value) return;
    const id = current.value.id;
    liked.value[id] = !liked.value[id];
    try {
        await axios.post(`${memberBase.value}/stories/${id}/like`, {},
            { headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' } });
    } catch {}
}

async function markView() {
    if (!current.value) return;
    try {
        await axios.post(`${memberBase.value}/stories/${current.value.id}/view`, {},
            { headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' } });
    } catch {}
}

watch(currentIndex, () => {
    markView();
    if (current.value?.is_video) {
        // Progresso controlado pelo vídeo
        clearInterval(progressTimer);
        progressPct.value = 0;
    } else {
        startProgress();
    }
});

// Toque/clique para navegar
function onTap(e) {
    const x = e.clientX || e.touches?.[0]?.clientX || 0;
    const w = e.currentTarget?.offsetWidth || 1;
    if (x < w * 0.35) prev();
    else next();
}

// Fechar com ESC
function onKey(e) { if (e.key === 'Escape') emit('close'); }

onMounted(() => {
    document.addEventListener('keydown', onKey);
    markView();
    if (current.value?.is_video) {
        progressPct.value = 0;
    } else {
        startProgress();
    }
    // Inicializar liked
    props.stories.forEach(s => { liked.value[s.id] = s.user_liked; });
});

onUnmounted(() => {
    document.removeEventListener('keydown', onKey);
    clearInterval(progressTimer);
});
</script>

<template>
    <!-- Fullscreen overlay estilo Instagram -->
    <div class="fixed inset-0 z-[80] flex items-center justify-center bg-black" style="touch-action:none;">
        <!-- Story atual -->
        <div class="relative h-full w-full max-w-sm mx-auto select-none" style="max-height:100vh; overflow:hidden;"
            @click="onTap" @touchend.prevent="onTap">

            <!-- Fundo colorido ou mídia -->
            <div class="absolute inset-0 flex items-center justify-center"
                :style="{ backgroundColor: current?.bg_color || '#0D1F2D' }">
                <!-- Vídeo -->
                <video v-if="current?.is_video && current?.video_url" ref="videoRef"
                    :src="current.video_url" class="h-full w-full object-cover"
                    playsinline autoplay
                    @loadedmetadata="onVideoLoaded"
                    @ended="next" />
                <!-- Imagem -->
                <img v-else-if="current?.image_url" :src="current.image_url" class="h-full w-full object-cover" />
                <!-- Só texto -->
                <div v-else class="flex flex-col items-center justify-center h-full w-full px-8 text-center">
                    <p style="font-family:'DM Sans',sans-serif; font-size:24px; font-weight:700; color:#fff; line-height:1.5;">
                        {{ current?.content }}
                    </p>
                </div>
            </div>

            <!-- Overlay gradient topo -->
            <div class="absolute inset-x-0 top-0 h-32 pointer-events-none"
                style="background: linear-gradient(to bottom, rgba(0,0,0,0.5), transparent);" />

            <!-- Barras de progresso -->
            <div class="absolute top-0 inset-x-0 flex gap-1 p-3 pointer-events-none" style="padding-top:52px;">
                <div v-for="(s, i) in stories" :key="s.id" class="h-[3px] flex-1 rounded-full overflow-hidden bg-white/30">
                    <div class="h-full rounded-full transition-none"
                        :style="{
                            width: i < currentIndex ? '100%' : (i === currentIndex ? progressPct + '%' : '0%'),
                            background: i === currentIndex ? primaryColor : 'rgba(255,255,255,0.8)'
                        }" />
                </div>
            </div>

            <!-- Botão fechar -->
            <button type="button" class="absolute top-16 right-4 z-10 flex h-9 w-9 items-center justify-center rounded-full bg-black/40 text-white"
                @click.stop="$emit('close')">
                <X class="h-5 w-5" />
            </button>

            <!-- Texto do story (sobre a mídia) -->
            <div v-if="current?.content && (current?.image_url || current?.is_video)"
                class="absolute inset-x-0 bottom-24 px-6 pointer-events-none">
                <p style="font-family:'DM Sans',sans-serif; font-size:18px; font-weight:600; color:#fff; text-shadow:0 2px 8px rgba(0,0,0,0.8); line-height:1.5; text-align:center;">
                    {{ current.content }}
                </p>
            </div>

            <!-- Barra inferior: like -->
            <div class="absolute inset-x-0 bottom-0 pb-safe flex items-center justify-center gap-6 p-6"
                style="background: linear-gradient(to top, rgba(0,0,0,0.6), transparent);"
                @click.stop>
                <!-- Curtir -->
                <button type="button"
                    class="flex flex-col items-center gap-1 transition"
                    @click.stop="likeStory">
                    <div class="flex h-12 w-12 items-center justify-center rounded-full transition"
                        :style="liked[current?.id] ? { background: '#E05555' } : { background: 'rgba(255,255,255,0.15)' }">
                        <svg width="24" height="24" viewBox="0 0 24 24"
                            :fill="liked[current?.id] ? '#fff' : 'none'"
                            stroke="#fff" stroke-width="2">
                            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                        </svg>
                    </div>
                    <span style="font-family:'DM Mono',monospace; font-size:11px; color:rgba(255,255,255,0.8);">
                        {{ current?.likes_count + (liked[current?.id] && !current?.user_liked ? 1 : 0) }}
                    </span>
                </button>
            </div>

            <!-- Zonas de toque (invisíveis) - esquerda/direita -->
            <div class="absolute inset-y-0 left-0 w-1/3" />
            <div class="absolute inset-y-0 right-0 w-1/3" />
        </div>
    </div>
</template>
