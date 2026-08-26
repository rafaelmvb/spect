<script setup>
import { computed } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import { LogOut, Home, BookOpen, Compass, Music, Users, Calendar, BarChart2, Route, Brain, UserCheck, ClipboardList } from 'lucide-vue-next';
import { useMusicPlayer } from '@/composables/useMusicPlayer';

const emit = defineEmits(['toggle-extra-menu']);

const page = usePage();

const slug = computed(() => page.props?.slug ?? '');

const basePath = computed(() => {
    const mb = page.props?.member_base;
    return (mb !== undefined && mb !== null) ? mb : `/m/${slug.value}`;
});

const homePath = computed(() => basePath.value || '/m');

const communityEnabled = computed(() => page.props?.member_enrolled ?? false);

const memberAreaLoginPath = computed(() => '/entrar');

const logoutHref = computed(() => `/logout?redirect=${encodeURIComponent(memberAreaLoginPath.value)}`);

const {
    currentTrack, isPlaying, progress, duration, progressPct,
    togglePlay, seek, stop, nextTrack, prevTrack, playlist, fmtTime,
} = useMusicPlayer();

const url = computed(() => page.url ?? '');

const activeSection = computed(() => {
    const u = url.value;
    if (u.includes('/modulos') || u.includes('/modulo') || u.includes('/aula')) return 'trilha';
    if (u.includes('/testes')) return 'testes';
    if (u.includes('/loja')) return 'explorar';
    if (u.includes('/musicas')) return 'musicas';
    if (u.includes('/grupos')) return 'grupos';
    if (u.includes('/eventos')) return 'eventos';
    if (u.includes('/jornadas')) return 'jornadas';
    if (u.includes('/mapa-neuro')) return 'mapa-neuro';
    if (u.includes('/profissionais')) return 'profissionais';
    if (u.includes('/resultados')) return 'resultados';
    if (u.includes('/comunidade')) return 'comunidade';
    return 'home';
});

function doSeek(e) {
    const r = e.currentTarget.getBoundingClientRect();
    seek(Math.max(0, Math.min(1, (e.clientX - r.left) / r.width)));
}
</script>

<template>
    <!-- ═══ SIDEBAR DESKTOP (≥ 1024px) — card flutuante ═══ -->
    <nav class="sidebar-root hidden lg:flex print:hidden">

        <!-- Nav items -->
        <div class="sidebar-nav">
            <Link :href="homePath" :class="['nav-item', activeSection === 'home' ? 'nav-item--active' : '']">
                <Home class="nav-icon" />
                <span>Início</span>
            </Link>
            <Link :href="`${basePath}/modulos`" :class="['nav-item', activeSection === 'trilha' ? 'nav-item--active' : '']">
                <BookOpen class="nav-icon" />
                <span>Trilha</span>
            </Link>
            <Link v-if="communityEnabled" :href="`${basePath}/comunidade`" :class="['nav-item', activeSection === 'comunidade' ? 'nav-item--active' : '']">
                <Users class="nav-icon" />
                <span>Comunidade</span>
            </Link>
            <Link :href="`${basePath}/loja`" :class="['nav-item', activeSection === 'explorar' ? 'nav-item--active' : '']">
                <Compass class="nav-icon" />
                <span>Explorar</span>
            </Link>
            <Link :href="`${basePath}/jornadas`" :class="['nav-item', activeSection === 'jornadas' ? 'nav-item--active' : '']">
                <Route class="nav-icon" />
                <span>Jornadas</span>
            </Link>
            <Link :href="`${basePath}/testes`" :class="['nav-item', activeSection === 'testes' ? 'nav-item--active' : '']">
                <ClipboardList class="nav-icon" />
                <span>Testes</span>
            </Link>
            <Link :href="`${basePath}/mapa-neuro`" :class="['nav-item', activeSection === 'mapa-neuro' ? 'nav-item--active' : '']">
                <Brain class="nav-icon" />
                <span>Mapa Neuro</span>
            </Link>
            <Link :href="`${basePath}/profissionais`" :class="['nav-item', activeSection === 'profissionais' ? 'nav-item--active' : '']">
                <UserCheck class="nav-icon" />
                <span>Profissionais</span>
            </Link>
            <Link :href="`${basePath}/musicas`" :class="['nav-item', activeSection === 'musicas' ? 'nav-item--active' : '']">
                <Music class="nav-icon" />
                <span>Áudios</span>
            </Link>
            <Link :href="`${basePath}/grupos`" :class="['nav-item', activeSection === 'grupos' ? 'nav-item--active' : '']">
                <Users class="nav-icon" />
                <span>Grupos</span>
            </Link>
            <Link :href="`${basePath}/eventos`" :class="['nav-item', activeSection === 'eventos' ? 'nav-item--active' : '']">
                <Calendar class="nav-icon" />
                <span>Eventos</span>
            </Link>

            <Link :href="`${basePath}/resultados`" :class="['nav-item', activeSection === 'resultados' ? 'nav-item--active' : '']">
                <BarChart2 class="nav-icon" />
                <span>Mega Relatórios</span>
            </Link>
        </div>

        <!-- Mini player desktop -->
        <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0 translate-y-2" enter-to-class="opacity-100 translate-y-0">
            <div v-if="currentTrack && !url.includes('/musicas')" class="mini-player">
                <div class="mini-player__row">
                    <div class="mini-player__icon" :style="{ background: 'var(--ma-primary)' }" @click="router.visit(`${basePath}/musicas`)">🎵</div>
                    <div class="mini-player__info" @click="router.visit(`${basePath}/musicas`)">
                        <p class="mini-player__title">{{ currentTrack.title }}</p>
                        <p class="mini-player__time">{{ fmtTime(progress) }} / {{ fmtTime(duration) }}</p>
                    </div>
                    <button type="button" class="mini-player__close" @click.stop="stop">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div class="mini-player__bar" @click="doSeek">
                    <div class="mini-player__progress" :style="{ width: (progressPct * 100) + '%', background: 'var(--ma-primary)' }" />
                </div>
                <div class="mini-player__controls">
                    <button type="button" class="mini-player__btn" :style="{ color: playlist.length > 1 ? 'var(--ma-text-2)' : 'rgba(255,255,255,0.15)' }" @click="prevTrack">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><polygon points="19 20 9 12 19 4 19 20"/><line x1="5" y1="19" x2="5" y2="5" stroke="currentColor" stroke-width="2"/></svg>
                    </button>
                    <button type="button" class="mini-player__play" :style="{ background: 'var(--ma-primary)' }" @click="togglePlay">
                        <svg v-if="!isPlaying" width="11" height="11" viewBox="0 0 24 24" :fill="'var(--ma-on-primary)'" style="margin-left:1px;"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                        <svg v-else width="11" height="11" viewBox="0 0 24 24" :fill="'var(--ma-on-primary)'"><rect x="6" y="4" width="4" height="16" rx="1"/><rect x="14" y="4" width="4" height="16" rx="1"/></svg>
                    </button>
                    <button type="button" class="mini-player__btn" :style="{ color: playlist.length > 1 ? 'var(--ma-text-2)' : 'rgba(255,255,255,0.15)' }" @click="nextTrack">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 4 15 12 5 20 5 4"/><line x1="19" y1="5" x2="19" y2="19" stroke="currentColor" stroke-width="2"/></svg>
                    </button>
                </div>
            </div>
        </Transition>

        <!-- Footer: sair -->
        <div class="sidebar-footer">
            <Link :href="logoutHref" method="post" as="button" class="nav-item nav-item--danger">
                <LogOut class="nav-icon" />
                <span>Sair</span>
            </Link>
        </div>
    </nav>

    <!-- ═══ BOTTOM NAV MOBILE (< 1024px) ═══ -->
    <nav class="bottom-nav lg:hidden print:hidden">
        <Link :href="homePath" :class="['bottom-item', activeSection === 'home' && 'bottom-item--active']" :style="{ color: activeSection === 'home' ? 'var(--ma-primary)' : 'var(--ma-text-inactive)' }">
            <div class="bottom-icon-wrap">
                <Home class="bottom-icon" />
                <span v-if="activeSection === 'home'" class="bottom-dot" :style="{ background: 'var(--ma-primary)' }" />
            </div>
            <span class="bottom-label">Início</span>
        </Link>
        <Link :href="`${basePath}/testes`" :class="['bottom-item', activeSection === 'testes' && 'bottom-item--active']" :style="{ color: activeSection === 'testes' ? 'var(--ma-primary)' : 'var(--ma-text-inactive)' }">
            <div class="bottom-icon-wrap">
                <ClipboardList class="bottom-icon" />
                <span v-if="activeSection === 'testes'" class="bottom-dot" :style="{ background: 'var(--ma-primary)' }" />
            </div>
            <span class="bottom-label">Testes</span>
        </Link>
        <Link :href="`${basePath}/comunidade`" :class="['bottom-item', activeSection === 'comunidade' && 'bottom-item--active']" :style="{ color: activeSection === 'comunidade' ? 'var(--ma-primary)' : 'var(--ma-text-inactive)' }">
            <div class="bottom-icon-wrap">
                <Users class="bottom-icon" />
                <span v-if="activeSection === 'comunidade'" class="bottom-dot" :style="{ background: 'var(--ma-primary)' }" />
            </div>
            <span class="bottom-label">Comunidade</span>
        </Link>
        <Link :href="`${basePath}/jornadas`" :class="['bottom-item', activeSection === 'jornadas' && 'bottom-item--active']" :style="{ color: activeSection === 'jornadas' ? 'var(--ma-primary)' : 'var(--ma-text-inactive)' }">
            <div class="bottom-icon-wrap">
                <Route class="bottom-icon" />
                <span v-if="activeSection === 'jornadas'" class="bottom-dot" :style="{ background: 'var(--ma-primary)' }" />
            </div>
            <span class="bottom-label">Jornadas</span>
        </Link>
        <button type="button" class="bottom-item" :style="{ border: 'none', background: 'none', color: 'var(--ma-text-inactive)', cursor: 'pointer' }" @click="emit('toggle-extra-menu')">
            <div class="bottom-icon-wrap">
                <svg class="bottom-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </div>
            <span class="bottom-label">Mais</span>
        </button>
    </nav>
</template>

<style scoped>
/* ── Sidebar root — card flutuante ── */
.sidebar-root {
    position: fixed;
    top: 12px;
    left: 12px;
    bottom: 12px;
    z-index: 35;
    width: 220px;
    flex-direction: column;
    background: var(--ma-sidebar-bg);
    border-radius: 18px;
    border: 1px solid var(--ma-border);
    padding: 20px 10px 16px;
    overflow: hidden;
}

/* ── Nav items ── */
.sidebar-nav {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 2px;
    overflow-y: auto;
}

.nav-item {
    display: flex;
    align-items: center;
    gap: 12px;
    border-radius: 12px;
    padding: 11px 14px;
    text-decoration: none;
    font-family: 'Cormorant Garamond', Georgia, serif;
    font-size: 17px;
    color: var(--ma-text-2);
    transition: background 0.15s, color 0.15s;
    background: none;
    border: none;
    cursor: pointer;
    width: 100%;
    text-align: left;
}
.nav-item:hover {
    background: rgba(255, 255, 255, 0.07);
    color: var(--ma-text);
}
.nav-item--active {
    background: var(--ma-nav-active-bg);
    color: var(--ma-nav-active-text);
    font-weight: 700;
}
.nav-item--active:hover {
    background: var(--ma-nav-active-bg);
}
.nav-item--sm {
    font-size: 14px;
    padding: 8px 14px;
}

.nav-icon {
    width: 20px;
    height: 20px;
    flex-shrink: 0;
}

.sidebar-divider {
    height: 1px;
    background: var(--ma-border);
    margin: 8px 10px;
}

/* ── Mini player ── */
.mini-player {
    margin: 0 2px 8px;
    background: rgba(255,255,255,0.04);
    border: 1px solid var(--ma-border);
    border-radius: 12px;
    padding: 10px 12px;
}
.mini-player__row { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; }
.mini-player__icon { width: 26px; height: 26px; border-radius: 6px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 11px; cursor: pointer; }
.mini-player__info { flex: 1; min-width: 0; cursor: pointer; }
.mini-player__title { font-size: 12px; font-weight: 700; color: var(--ma-text); font-family: 'Cormorant Garamond', Georgia, serif; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; margin: 0; }
.mini-player__time { font-size: 10px; font-family: 'DM Mono', monospace; color: var(--ma-text-2); margin: 0; }
.mini-player__close { background: none; border: none; cursor: pointer; padding: 2px; color: rgba(255,255,255,0.3); flex-shrink: 0; display: flex; align-items: center; transition: color 0.15s; }
.mini-player__close:hover { color: var(--ma-danger); }
.mini-player__bar { width: 100%; height: 3px; background: rgba(255,255,255,0.08); border-radius: 2px; margin-bottom: 8px; cursor: pointer; }
.mini-player__progress { height: 100%; border-radius: 2px; transition: width 0.2s linear; }
.mini-player__controls { display: flex; align-items: center; justify-content: center; gap: 12px; }
.mini-player__btn { background: none; border: none; cursor: pointer; padding: 3px; display: flex; align-items: center; }
.mini-player__play { width: 30px; height: 30px; border-radius: 50%; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; }

/* ── Footer ── */
.sidebar-footer {
    display: flex;
    flex-direction: column;
    gap: 2px;
    border-top: 1px solid var(--ma-border);
    padding-top: 10px;
    margin-top: 8px;
}

.nav-item--danger { color: var(--ma-danger); }
.nav-item--danger:hover { background: rgba(224, 85, 85, 0.08); color: var(--ma-danger); }

/* ── Bottom nav mobile — pill flutuante ── */
.bottom-nav {
    position: fixed;
    bottom: calc(14px + env(safe-area-inset-bottom, 0px));
    left: 16px;
    right: 16px;
    z-index: 30;
    display: flex;
    align-items: center;
    justify-content: space-around;
    background: var(--ma-nav-bg);
    backdrop-filter: blur(24px);
    border-radius: 26px;
    border: 1px solid var(--ma-border);
    box-shadow: 0 8px 32px rgba(0,0,0,0.5), 0 2px 8px rgba(0,0,0,0.3);
    padding: 6px 4px;
}
@media (min-width: 1024px) {
    .bottom-nav { display: none !important; }
}

.bottom-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 3px;
    padding: 4px 10px;
    text-decoration: none;
    transition: color 0.2s;
    background: none;
    border: none;
    cursor: pointer;
    -webkit-tap-highlight-color: transparent;
}

.bottom-icon-wrap {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 32px;
}

.bottom-icon { width: 22px; height: 22px; }

.bottom-dot {
    position: absolute;
    bottom: -2px;
    left: 50%;
    transform: translateX(-50%);
    width: 4px;
    height: 4px;
    border-radius: 2px;
    animation: dot-appear 0.2s ease;
}

@keyframes dot-appear {
    from { transform: translateX(-50%) scale(0); opacity: 0; }
    to   { transform: translateX(-50%) scale(1); opacity: 1; }
}

.bottom-label {
    font-size: 11px;
    font-weight: 600;
    font-family: 'Cormorant Garamond', Georgia, serif;
    letter-spacing: 0.01em;
}
</style>
