import { ref, computed, watch } from 'vue';

const STORAGE_KEY = 'spectra_music_player';

const currentTrack  = ref(null);
const isPlaying     = ref(false);
const progress      = ref(0);
const duration      = ref(0);
const speed         = ref(1);
const playlist      = ref([]);
const isShuffled    = ref(false);
let audioEl         = null;
let _restored       = false;

function saveState() {
    if (!currentTrack.value) return;
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify({
            track: currentTrack.value,
            position: audioEl?.currentTime ?? 0,
            speed: speed.value,
        }));
    } catch {}
}

function getAudio() {
    if (!audioEl) {
        audioEl = new Audio();
        audioEl.addEventListener('timeupdate', () => {
            progress.value = audioEl.currentTime;
            // Salva posição a cada 5s
            if (Math.floor(audioEl.currentTime) % 5 === 0) saveState();
        });
        audioEl.addEventListener('loadedmetadata', () => { duration.value = audioEl.duration || 0; });
        audioEl.addEventListener('ended', () => {
            // Avança para a próxima faixa automaticamente
            if (currentTrack.value && playlist.value.length > 1) {
                const idx = playlist.value.findIndex(t => t.id === currentTrack.value.id);
                if (idx < playlist.value.length - 1) {
                    setTimeout(() => play(playlist.value[idx + 1]), 300);
                    return;
                }
            }
            isPlaying.value = false; progress.value = 0;
        });
        audioEl.addEventListener('play',  () => { isPlaying.value = true; });
        audioEl.addEventListener('pause', () => { isPlaying.value = false; });

        // Restaura última faixa (sem autoplay — aguarda interação)
        if (!_restored) {
            _restored = true;
            try {
                const saved = localStorage.getItem(STORAGE_KEY);
                if (saved) {
                    const { track, position, speed: sp } = JSON.parse(saved);
                    if (track?.file_url) {
                        currentTrack.value = track;
                        speed.value = sp ?? 1;
                        audioEl.src = track.file_url;
                        audioEl.playbackRate = sp ?? 1;
                        audioEl.load();
                        // Restaura posição após load
                        audioEl.addEventListener('loadedmetadata', () => {
                            if (position > 0) audioEl.currentTime = position;
                            progress.value = position;
                        }, { once: true });
                    }
                }
            } catch {}
        }
    }
    return audioEl;
}

function play(track, list = null) {
    const a = getAudio();
    if (list) playlist.value = list;
    if (currentTrack.value?.id === track.id) {
        togglePlay(); return;
    }
    a.pause();
    a.src = '';
    currentTrack.value = track;
    progress.value = 0; duration.value = 0; speed.value = 1;
    a.src = track.file_url;
    a.playbackRate = 1;
    a.load();
    a.play().catch(() => {});
    saveState();
}

function stop() {
    const a = getAudio();
    a.pause();
    a.src = '';
    currentTrack.value = null;
    isPlaying.value = false;
    progress.value = 0;
    duration.value = 0;
    try { localStorage.removeItem(STORAGE_KEY); } catch {}
}

function nextTrack() {
    if (!currentTrack.value || !playlist.value.length) return;
    const idx = playlist.value.findIndex(t => t.id === currentTrack.value.id);
    if (idx < playlist.value.length - 1) play(playlist.value[idx + 1]);
}

function playAll(list) {
    if (!list?.length) return;
    isShuffled.value = false;
    playlist.value = [...list];
    play(list[0], list);
}

function playShuffled(list) {
    if (!list?.length) return;
    const shuffled = [...list].sort(() => Math.random() - 0.5);
    isShuffled.value = true;
    playlist.value = shuffled;
    play(shuffled[0], shuffled);
}

function prevTrack() {
    if (!currentTrack.value || !playlist.value.length) return;
    const a = getAudio();
    // Se passou mais de 3s, volta ao início; senão vai para a anterior
    if (a.currentTime > 3) { a.currentTime = 0; return; }
    const idx = playlist.value.findIndex(t => t.id === currentTrack.value.id);
    if (idx > 0) play(playlist.value[idx - 1]);
}

function togglePlay() {
    const a = getAudio();
    if (!currentTrack.value) return;
    if (a.paused) a.play().catch(() => {}); else a.pause();
}

function seek(pct) {
    const a = getAudio();
    if (!duration.value) return;
    a.currentTime = pct * duration.value;
    progress.value = a.currentTime;
}

function skip(sec) {
    const a = getAudio();
    a.currentTime = Math.max(0, Math.min(a.currentTime + sec, duration.value));
    progress.value = a.currentTime;
}

function setSpeed(s) {
    speed.value = s;
    getAudio().playbackRate = s;
    saveState();
}

function fmtTime(s) {
    if (!s || isNaN(s)) return '0:00';
    const m = Math.floor(s / 60);
    const sec = Math.floor(s % 60);
    return `${m}:${sec.toString().padStart(2, '0')}`;
}

const progressPct = computed(() => duration.value > 0 ? progress.value / duration.value : 0);

// Inicializa o audio na primeira importação
getAudio();

export function useMusicPlayer() {
    return { currentTrack, isPlaying, progress, duration, speed, playlist, isShuffled,
             progressPct, play, togglePlay, seek, skip, setSpeed, fmtTime,
             stop, nextTrack, prevTrack, playAll, playShuffled };
}
