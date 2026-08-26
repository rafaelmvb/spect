<script setup>
import { computed } from 'vue';
import MemberAreaAppLayout from '@/Layouts/MemberAreaAppLayout.vue';
import { useMusicPlayer } from '@/composables/useMusicPlayer';

defineOptions({ layout: MemberAreaAppLayout });

const props = defineProps({
    product:    { type: Object, required: true },
    config:     { type: Object, default: () => ({}) },
    slug:       { type: String, required: true },
    categories: { type: Array, default: () => [] },
});

const primaryColor = computed(() => props.config?.theme?.primary || '#6FCF00');
const { currentTrack, isPlaying, progress, duration, progressPct, play, togglePlay, seek, skip, setSpeed, speed, fmtTime, stop, nextTrack, prevTrack, playlist, isShuffled, playAll, playShuffled } = useMusicPlayer();

// Lista plana de todas as faixas com categoria
const allTracks = computed(() => {
    const list = [];
    props.categories.forEach(cat => {
        cat.tracks?.forEach(t => list.push({ ...t, category: cat.name }));
    });
    return list;
});

function openTrack(track, catName) {
    play({ ...track, category: catName }, allTracks.value);
}

const hasPrev = computed(() => {
    if (!currentTrack.value || !playlist.value.length) return false;
    return playlist.value.findIndex(t => t.id === currentTrack.value.id) > 0;
});
const hasNext = computed(() => {
    if (!currentTrack.value || !playlist.value.length) return false;
    const idx = playlist.value.findIndex(t => t.id === currentTrack.value.id);
    return idx < playlist.value.length - 1;
});
</script>

<template>
    <div style="max-width:480px; margin:0 auto; padding-bottom:160px;">

        <!-- ═══ PLAYER INTEGRADO (aparece quando tem faixa tocando) ═══════════ -->
        <Transition
            enter-active-class="transition-all duration-300 ease-out"
            enter-from-class="opacity-0 -translate-y-4"
            enter-to-class="opacity-100 translate-y-0">
            <div v-if="currentTrack"
                style="background:var(--ma-surface); border-bottom:1px solid var(--ma-border); padding:28px 24px 32px; text-align:center; font-family:'DM Sans',sans-serif;">

                <!-- Label -->
                <p style="font-family:'DM Mono',monospace; font-size:10px; text-transform:uppercase; letter-spacing:1.5px; color:var(--ma-text-2); margin-bottom:24px;">reproduzindo</p>

                <!-- Waveform animado -->
                <div style="position:relative; width:160px; height:160px; margin:0 auto 28px; display:flex; align-items:center; justify-content:center;">
                    <div style="position:absolute; width:160px; height:160px; border-radius:50%; border:1px solid rgba(111,207,0,0.08); animation:pulse 4s ease-in-out infinite;" />
                    <div style="position:absolute; width:130px; height:130px; border-radius:50%; border:1px solid rgba(111,207,0,0.12); animation:pulse 4s ease-in-out infinite; animation-delay:0.5s;" />
                    <div style="position:absolute; width:100px; height:100px; border-radius:50%; border:1px solid rgba(0,200,151,0.15); animation:pulse 4s ease-in-out infinite; animation-delay:1s;" />
                    <div style="width:80px; height:80px; border-radius:50%; display:flex; align-items:center; justify-content:center;"
                        :style="{ background: `radial-gradient(circle at 40% 35%, rgba(111,207,0,0.25), rgba(0,200,151,0.15), var(--ma-surface))`, border: '1px solid rgba(111,207,0,0.2)' }">
                        <div style="display:flex; align-items:center; gap:2px; height:28px;">
                            <div v-for="(h,i) in [60,100,50,80,45,90,55,75]" :key="i"
                                style="width:2.5px; border-radius:2px;"
                                :style="{
                                    height: h+'%',
                                    background: i%2===0 ? primaryColor : '#00C897',
                                    opacity: isPlaying ? 0.9 : 0.35,
                                    animation: isPlaying ? `wave 1.2s ease-in-out infinite` : 'none',
                                    animationDelay: (i*0.1)+'s'
                                }" />
                        </div>
                    </div>
                </div>

                <!-- Título -->
                <h2 style="font-size:20px; font-weight:700; color:var(--ma-text); margin-bottom:4px; line-height:1.3;">{{ currentTrack.title }}</h2>
                <p style="font-size:12px; color:var(--ma-text-2); font-family:'DM Mono',monospace; margin-bottom:20px;">{{ currentTrack.category || 'Biblioteca' }}</p>

                <!-- Barra de progresso -->
                <div style="margin-bottom:6px;">
                    <div style="width:100%; height:4px; background:var(--ma-surface-2); border-radius:2px; position:relative; cursor:pointer;"
                        @click="e => { const r = e.currentTarget.getBoundingClientRect(); seek(Math.max(0, Math.min(1, (e.clientX - r.left) / r.width))); }">
                        <div style="height:100%; border-radius:2px; transition:width 0.2s linear;"
                            :style="{ width: (progressPct * 100) + '%', background: `linear-gradient(90deg, #00C897, ${primaryColor})` }" />
                        <div style="position:absolute; top:-4px; width:12px; height:12px; border-radius:50%; transform:translateX(-50%); box-shadow:0 0 8px rgba(111,207,0,0.5);"
                            :style="{ left: (progressPct * 100) + '%', background: primaryColor }" />
                    </div>
                    <div style="display:flex; justify-content:space-between; margin-top:6px;">
                        <span style="font-family:'DM Mono',monospace; font-size:11px; color:var(--ma-text-2);">{{ fmtTime(progress) }}</span>
                        <span style="font-family:'DM Mono',monospace; font-size:11px; color:var(--ma-text-2);">{{ fmtTime(duration) }}</span>
                    </div>
                </div>

                <!-- Controles: prev | skip-15 | play/pause | skip+15 | next -->
                <div style="display:flex; align-items:center; justify-content:center; gap:20px; margin-bottom:20px;">
                    <!-- Anterior -->
                    <button type="button" @click="prevTrack"
                        style="background:none; border:none; cursor:pointer; display:flex; align-items:center; justify-content:center;"
                        :style="{ color: hasPrev ? 'var(--ma-text-2)' : 'rgba(255,255,255,0.2)', cursor: hasPrev ? 'pointer' : 'default' }">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><polygon points="19 20 9 12 19 4 19 20"/><line x1="5" y1="19" x2="5" y2="5" stroke="currentColor" stroke-width="2"/></svg>
                    </button>
                    <!-- Skip -15 -->
                    <button type="button" @click="skip(-15)"
                        style="background:none; border:none; cursor:pointer; color:var(--ma-text-2); display:flex; flex-direction:column; align-items:center; gap:2px;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 4v6h6M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>
                        <span style="font-family:'DM Mono',monospace; font-size:9px;">15</span>
                    </button>
                    <!-- Play/Pause -->
                    <button type="button" @click="togglePlay"
                        style="width:64px; height:64px; border-radius:50%; border:none; cursor:pointer; display:flex; align-items:center; justify-content:center; box-shadow:0 0 24px rgba(111,207,0,0.25);"
                        :style="{ background: primaryColor }">
                        <svg v-if="!isPlaying" width="26" height="26" viewBox="0 0 24 24" fill="#0D1F2D" style="margin-left:3px;"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                        <svg v-else width="26" height="26" viewBox="0 0 24 24" fill="#0D1F2D"><rect x="6" y="4" width="4" height="16" rx="1"/><rect x="14" y="4" width="4" height="16" rx="1"/></svg>
                    </button>
                    <!-- Skip +15 -->
                    <button type="button" @click="skip(15)"
                        style="background:none; border:none; cursor:pointer; color:var(--ma-text-2); display:flex; flex-direction:column; align-items:center; gap:2px;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 4v6h-6M20.49 15a9 9 0 1 1-2.13-9.36L23 10"/></svg>
                        <span style="font-family:'DM Mono',monospace; font-size:9px;">15</span>
                    </button>
                    <!-- Próxima -->
                    <button type="button" @click="nextTrack"
                        style="background:none; border:none; cursor:pointer; display:flex; align-items:center; justify-content:center;"
                        :style="{ color: hasNext ? 'var(--ma-text-2)' : 'rgba(255,255,255,0.2)', cursor: hasNext ? 'pointer' : 'default' }">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 4 15 12 5 20 5 4"/><line x1="19" y1="5" x2="19" y2="19" stroke="currentColor" stroke-width="2"/></svg>
                    </button>
                </div>
                <!-- Controles de modo + parar -->
                <div style="display:flex; align-items:center; justify-content:center; gap:10px; margin-top:4px;">
                    <button type="button" @click="playAll(allTracks)"
                        style="display:flex; align-items:center; gap:5px; padding:6px 14px; border-radius:20px; cursor:pointer; font-size:11px; font-weight:700; font-family:'DM Mono',monospace; transition:all 0.15s;"
                        :style="!isShuffled ? { background: primaryColor, color: '#0D1F2D', border: 'none' } : { background: 'transparent', color: 'rgba(255,255,255,0.4)', border: '1px solid rgba(255,255,255,0.12)' }">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                        Em ordem
                    </button>
                    <button type="button" @click="playShuffled(allTracks)"
                        style="display:flex; align-items:center; gap:5px; padding:6px 14px; border-radius:20px; cursor:pointer; font-size:11px; font-weight:700; font-family:'DM Mono',monospace; transition:all 0.15s;"
                        :style="isShuffled ? { background: primaryColor, color: '#0D1F2D', border: 'none' } : { background: 'transparent', color: 'rgba(255,255,255,0.4)', border: '1px solid rgba(255,255,255,0.12)' }">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="16 3 21 3 21 8"/><line x1="4" y1="20" x2="21" y2="3"/><polyline points="21 16 21 21 16 21"/><line x1="15" y1="15" x2="21" y2="21"/></svg>
                        Aleatório
                    </button>
                    <button type="button" @click="stop"
                        style="background:transparent; border:1px solid rgba(255,255,255,0.12); border-radius:20px; padding:6px 14px; cursor:pointer; color:rgba(255,255,255,0.35); font-size:11px; font-family:'DM Mono',monospace; transition:all 0.15s;"
                        @mouseenter="$event.currentTarget.style.color='#E05555'; $event.currentTarget.style.borderColor='rgba(224,85,85,0.4)'"
                        @mouseleave="$event.currentTarget.style.color='rgba(255,255,255,0.35)'; $event.currentTarget.style.borderColor='rgba(255,255,255,0.12)'">
                        ⏹ parar
                    </button>
                </div>

                <!-- Velocidade -->
                <div style="display:flex; justify-content:center;">
                    <div style="display:flex; gap:3px; background:var(--ma-bg); border-radius:8px; padding:3px; border:1px solid var(--ma-border);">
                        <button v-for="s in [0.5, 1, 1.5, 2]" :key="s" type="button" @click="setSpeed(s)"
                            style="padding:5px 12px; border-radius:6px; border:none; font-family:'DM Mono',monospace; font-size:11px; cursor:pointer; transition:all 0.15s;"
                            :style="speed === s
                                ? { background: primaryColor, color: '#0D1F2D', fontWeight: '700' }
                                : { background: 'transparent', color: 'var(--ma-text-2)' }">
                            {{ s }}x
                        </button>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- ═══ BIBLIOTECA ════════════════════════════════════════════════════ -->
        <div style="padding:16px;">
            <div style="padding:8px 0 16px;">
                <div style="display:flex; align-items:flex-end; justify-content:space-between; gap:12px;">
                    <div>
                        <p style="font-family:'DM Mono',monospace; font-size:11px; text-transform:uppercase; letter-spacing:1.5px; color:var(--ma-text-2); margin:0 0 4px;">biblioteca</p>
                        <h1 v-if="!currentTrack" style="font-family:'DM Sans',sans-serif; font-size:24px; font-weight:800; margin:0; color:var(--ma-text);">Áudios</h1>
                        <p v-if="!currentTrack" style="font-size:12px; color:var(--ma-text-2); margin:4px 0 0; font-family:'DM Sans',sans-serif;">{{ allTracks.length }} faixa{{ allTracks.length !== 1 ? 's' : '' }}</p>
                    </div>
                    <!-- Botões play all / shuffle -->
                    <div v-if="allTracks.length" style="display:flex; gap:8px; flex-shrink:0; padding-bottom:2px;">
                        <button type="button"
                            style="display:flex; align-items:center; gap:6px; padding:8px 14px; border-radius:20px; border:none; cursor:pointer; font-size:12px; font-weight:700; font-family:'DM Mono',monospace; transition:all 0.15s;"
                            :style="!isShuffled && currentTrack ? { background: primaryColor, color: '#0D1F2D' } : { background: 'var(--ma-surface)', color: 'var(--ma-text-2)', border: '1px solid var(--ma-border)' }"
                            @click="playAll(allTracks)">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                            Tocar tudo
                        </button>
                        <button type="button"
                            style="display:flex; align-items:center; gap:6px; padding:8px 14px; border-radius:20px; border:none; cursor:pointer; font-size:12px; font-weight:700; font-family:'DM Mono',monospace; transition:all 0.15s;"
                            :style="isShuffled && currentTrack ? { background: primaryColor, color: '#0D1F2D' } : { background: 'var(--ma-surface)', color: 'var(--ma-text-2)', border: '1px solid var(--ma-border)' }"
                            @click="playShuffled(allTracks)">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 3 21 3 21 8"/><line x1="4" y1="20" x2="21" y2="3"/><polyline points="21 16 21 21 16 21"/><line x1="15" y1="15" x2="21" y2="21"/></svg>
                            Aleatório
                        </button>
                    </div>
                </div>
            </div>

            <div v-if="!categories.length" style="text-align:center; padding:60px 24px; color:var(--ma-text-2);">
                <p style="font-size:32px; margin-bottom:12px;">🎵</p>
                <p style="font-family:'DM Sans',sans-serif; font-size:15px;">Nenhum áudio disponível ainda.</p>
            </div>

            <div v-for="cat in categories" :key="cat.id" style="margin-bottom:28px;">
                <p style="font-family:'DM Mono',monospace; font-size:11px; text-transform:uppercase; letter-spacing:1.5px; color:var(--ma-text-2); margin-bottom:10px;">{{ cat.name }}</p>
                <div style="display:flex; flex-direction:column; gap:6px;">
                    <div v-for="track in cat.tracks" :key="track.id"
                        style="display:flex; align-items:center; gap:14px; padding:12px 14px; background:var(--ma-surface); border-radius:12px; border:1px solid var(--ma-border); cursor:pointer; transition:all 0.2s;"
                        :style="currentTrack?.id === track.id ? { borderColor: primaryColor, background: 'rgba(111,207,0,0.06)' } : {}"
                        @click="openTrack(track, cat.name)">

                        <div style="width:42px; height:42px; border-radius:50%; flex-shrink:0; display:flex; align-items:center; justify-content:center; transition:all 0.2s;"
                            :style="currentTrack?.id === track.id ? { background: primaryColor } : { background: 'var(--ma-surface-2)', border: '1px solid var(--ma-border)' }">
                            <div v-if="currentTrack?.id === track.id && isPlaying" style="display:flex; align-items:center; gap:2px; height:16px;">
                                <div v-for="i in 4" :key="i" style="width:3px; border-radius:2px; animation:wave 1.2s ease-in-out infinite;"
                                    :style="{ height: [60,100,50,80][i-1]+'%', background: '#0D1F2D', animationDelay: ((i-1)*0.1)+'s' }" />
                            </div>
                            <svg v-else width="16" height="16" viewBox="0 0 24 24" :fill="currentTrack?.id === track.id ? '#0D1F2D' : primaryColor" style="margin-left:2px;">
                                <polygon points="5 3 19 12 5 21 5 3"/>
                            </svg>
                        </div>

                        <div style="flex:1; min-width:0;">
                            <p style="font-size:15px; font-weight:600; font-family:'DM Sans',sans-serif; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"
                                :style="{ color: currentTrack?.id === track.id ? primaryColor : 'var(--ma-text)' }">{{ track.title }}</p>
                            <p style="font-size:11px; color:var(--ma-text-2); margin-top:2px; font-family:'DM Mono',monospace;">{{ cat.name }}</p>
                        </div>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--ma-text-2)" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                    </div>
                </div>
            </div>
        </div>

        <style>
        @keyframes wave { 0%, 100% { transform: scaleY(1); } 50% { transform: scaleY(0.4); } }
        @keyframes pulse { 0%, 100% { transform: scale(1); opacity: 0.15; } 50% { transform: scale(1.05); opacity: 0.3; } }
        </style>
    </div>
</template>
