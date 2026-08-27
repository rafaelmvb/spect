<script setup>
import { useTelemetriaDePlayer } from '@/composables/useTelemetria';
import { computed, ref, onMounted, onUnmounted, onBeforeUnmount } from 'vue';
import { getVideoProviderType } from '@/lib/utils';

const props = defineProps({
    src:         { type: String,  default: '' },
    poster:      { type: String,  default: '' },
    playsinline: { type: Boolean, default: true },

    // Telemetria de consumo: sem telemetryId o player nao registra nada.
    telemetryType: { type: String, default: 'member_lesson' },
    telemetryId:   { type: [String, Number], default: null },
    telemetryBase: { type: String, default: '' },
});

const emit = defineEmits(['ended']);

/*
 * O vidstack renderiza um <video> real dentro do provider: ancorar nele
 * funciona em qualquer versao, sem depender da API de eventos do custom
 * element, que muda entre releases.
 */
const { conectar: conectarTelemetria, desconectar: desconectarTelemetria } = useTelemetriaDePlayer(
    computed(() => props.telemetryBase),
    { subjectType: props.telemetryType, subjectId: props.telemetryId },
);

const containerRef = ref(null);
let observador = null;

/** O <video> aparece depois que o vidstack monta: espera por ele. */
function procurarMidia() {
    const midia = containerRef.value?.querySelector('video, audio');
    if (midia) {
        conectarTelemetria(midia);
        observador?.disconnect();
        observador = null;
        return true;
    }
    return false;
}

onMounted(() => {
    if (!props.telemetryId) return;
    if (procurarMidia()) return;

    observador = new MutationObserver(() => procurarMidia());
    observador.observe(containerRef.value, { childList: true, subtree: true });
});

onBeforeUnmount(() => {
    observador?.disconnect();
    desconectarTelemetria();
});

const providerType = computed(() => getVideoProviderType(props.src));
/** YouTube/Vimeo no iOS: Fullscreen API no player inteiro falha; Vidstack usa fullscreen no iframe do provider. */
const isEmbedProvider = computed(() => {
    const t = providerType.value;
    return t === 'youtube' || t === 'vimeo';
});
const isMobile = ref(false);
let mobileMql = null;
function onMobileQueryChange(e) {
    isMobile.value = !!e.matches;
}
const playerRef = ref(null);
let onFullscreenChangeHandler = null;

async function lockOrientationLandscape() {
    try {
        if (typeof screen === 'undefined') return;
        if (!screen.orientation || typeof screen.orientation.lock !== 'function') return;
        await screen.orientation.lock('landscape');
    } catch (_) {}
}
function unlockOrientation() {
    try {
        if (typeof screen === 'undefined') return;
        if (!screen.orientation || typeof screen.orientation.unlock !== 'function') return;
        screen.orientation.unlock();
    } catch (_) {}
}
function isPlayerFullscreen() {
    if (typeof document === 'undefined') return false;
    const el = playerRef.value;
    if (!el) return false;
    const fsEl = document.fullscreenElement || document.webkitFullscreenElement;
    if (!fsEl) return false;
    return fsEl === el || (typeof el.contains === 'function' && el.contains(fsEl));
}

// Para YouTube/Vimeo: URL de embed para iframe nativo
const iframeEmbedUrl = computed(() => {
    if (!props.src) return '';
    const u = props.src.trim();
    if (providerType.value === 'youtube') {
        const m = u.match(/(?:youtube\.com\/watch\?.*v=|youtu\.be\/|youtube\.com\/embed\/|youtube\.com\/shorts\/)([a-zA-Z0-9_-]+)/);
        return m ? `https://www.youtube-nocookie.com/embed/${m[1]}?rel=0&modestbranding=1` : '';
    }
    if (providerType.value === 'vimeo') {
        const m = u.match(/vimeo\.com\/(?:video\/)?(\d+)/);
        return m ? `https://player.vimeo.com/video/${m[1]}?dnt=1` : '';
    }
    return '';
});

// Para vídeo nativo: src direto para o media-player do vidstack
const vidstackSrc = computed(() => {
    if (!props.src || providerType.value !== 'native') return '';
    return props.src.trim();
});


onMounted(() => {
    if (typeof window !== 'undefined' && 'matchMedia' in window) {
        mobileMql = window.matchMedia('(max-width: 768px)');
        isMobile.value = !!mobileMql.matches;
        try {
            mobileMql.addEventListener('change', onMobileQueryChange);
        } catch (_) {
            try {
                mobileMql.addListener(onMobileQueryChange);
            } catch (_) {}
        }
    }
    if (typeof document !== 'undefined') {
        onFullscreenChangeHandler = () => {
            if (!isMobile.value) return;
            if (isPlayerFullscreen()) {
                setTimeout(() => lockOrientationLandscape(), 0);
            } else {
                unlockOrientation();
            }
        };
        document.addEventListener('fullscreenchange', onFullscreenChangeHandler);
        document.addEventListener('webkitfullscreenchange', onFullscreenChangeHandler);
    }
});
onUnmounted(() => {
    if (typeof document !== 'undefined' && onFullscreenChangeHandler) {
        document.removeEventListener('fullscreenchange', onFullscreenChangeHandler);
        document.removeEventListener('webkitfullscreenchange', onFullscreenChangeHandler);
        onFullscreenChangeHandler = null;
    }
    unlockOrientation();
    if (mobileMql) {
        try {
            mobileMql.removeEventListener('change', onMobileQueryChange);
        } catch (_) {
            try {
                mobileMql.removeListener(onMobileQueryChange);
            } catch (_) {}
        }
    }
});

const effectivePlaysinline = computed(() => {
    if (providerType.value !== 'native') return props.playsinline;
    if (props.playsinline === false) return false;
    return !isMobile.value;
});

function onEnded() {
    emit('ended');
}

function onContextMenu(e) {
    e.preventDefault();
}
</script>

<template>
    <div
        ref="containerRef"
        class="member-area-video-player aspect-video w-full overflow-hidden rounded-lg relative"
        @contextmenu.prevent="onContextMenu"
    >
        <!-- YouTube / Vimeo: iframe nativo (mais confiável que vidstack para embeds) -->
        <iframe
            v-if="isEmbedProvider && iframeEmbedUrl"
            :src="iframeEmbedUrl"
            class="w-full h-full border-0"
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
            allowfullscreen
            referrerpolicy="strict-origin-when-cross-origin"
        />

        <!-- Vídeo nativo (MP4, HLS, etc.): vidstack com controles completos -->
        <media-player
            v-else-if="src && vidstackSrc"
            ref="playerRef"
            class="player"
            :src="vidstackSrc"
            :playsinline="effectivePlaysinline"
            @vds-ended="onEnded"
            @vds-end="onEnded"
        >
            <media-provider></media-provider>
            <media-video-layout></media-video-layout>
        </media-player>
    </div>
</template>

<style scoped>
.member-area-video-player {
    --media-brand: #f5f5f5;
    --media-focus-ring-color: #4e9cf6;
}
.player {
    width: 100%;
    height: 100%;
    display: block;
}
.player[data-view-type='video'] {
    aspect-ratio: 16 / 9;
}
/* Poster por cima do iframe do YouTube até o usuário dar play */
.player :deep(.vds-poster),
.player :deep([data-media-poster]) {
    z-index: 1;
}
.player :deep(media-provider),
.player :deep([data-media-provider]) {
    z-index: 0;
}
/* Camada 1: esconder PiP para dificultar gravação */
.player :deep(media-pip-button) {
    display: none !important;
}
</style>
