<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import { Bell, Search, X, User, LogOut, Settings, Award } from 'lucide-vue-next';

const emit = defineEmits(['open-notifications', 'open-account-menu', 'open-account-modal']);

const page = usePage();

const product            = computed(() => page.props?.product   ?? {});
const config             = computed(() => page.props?.config    ?? {});
const user               = computed(() => page.props?.auth?.user ?? null);
const globalBranding     = computed(() => page.props?.member_area_global_branding ?? {});
const headerLogo         = computed(() => config.value?.header?.logo_url || globalBranding.value?.logo_url || null);
const slug               = computed(() => page.props?.slug      ?? '');
const unreadCount        = computed(() => page.props?.member_notifications_unread_count ?? 0);
const certificateEnabled = computed(() => config.value?.certificate?.enabled ?? false);

const basePath = computed(() => {
    const mb = page.props?.member_base;
    return (mb !== undefined && mb !== null) ? mb : `/m/${slug.value}`;
});

const homePath = computed(() => basePath.value || '/area');

const url = computed(() => page.url ?? '');

const pageTitle = computed(() => {
    const u = url.value;
    if (u.includes('/modulo') || u.includes('/aula')) return 'Trilha';
    if (u.includes('/modulos'))    return 'Trilha';
    if (u.includes('/comunidade')) return 'Comunidade';
    if (u.includes('/loja'))       return 'Explorar';
    if (u.includes('/jornadas'))   return 'Jornadas';
    if (u.includes('/mapa-neuro')) return 'Mapa Neuro';
    if (u.includes('/profissionais')) return 'Profissionais';
    if (u.includes('/musicas'))    return 'Áudios';
    if (u.includes('/grupos'))     return 'Grupos';
    if (u.includes('/eventos'))    return 'Eventos';
    if (u.includes('/resultados')) return 'Mega Relatórios';
    if (u.includes('/perfil'))     return 'Perfil';
    if (u.includes('/certificado')) return 'Certificado';
    if (u.includes('/busca'))      return 'Busca';
    return 'Início';
});

const memberAreaLoginPath = computed(() => '/entrar');

const logoutHref = computed(() => `/logout?redirect=${encodeURIComponent(memberAreaLoginPath.value)}`);

const initials = computed(() => {
    if (!user.value?.name) return '?';
    const parts = String(user.value.name).trim().split(/\s+/);
    return parts.length >= 2
        ? (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
        : (parts[0][0] || '?').toUpperCase();
});

const searchQuery = ref('');
function doSearch(e) {
    if (e) e.preventDefault();
    const q = searchQuery.value.trim();
    if (q) router.visit(`${basePath.value}/busca?q=${encodeURIComponent(q)}`);
}

function goToSearch() {
    router.visit(`${basePath.value}/busca`);
}

const profileMenuOpen = ref(false);
const profileMenuRef  = ref(null);

function toggleProfileMenu() {
    profileMenuOpen.value = !profileMenuOpen.value;
}

function closeProfileMenu() {
    profileMenuOpen.value = false;
}

function handleClickOutside(e) {
    if (profileMenuRef.value && !profileMenuRef.value.contains(e.target)) {
        profileMenuOpen.value = false;
    }
}

onMounted(() => document.addEventListener('click', handleClickOutside));
onUnmounted(() => document.removeEventListener('click', handleClickOutside));
</script>

<template>
    <header class="ma-header print:hidden">

        <!-- Logo / Marca -->
        <Link :href="homePath" class="ma-header__logo">
            <img v-if="headerLogo" :src="headerLogo" :alt="product?.name || 'Logo'" class="ma-header__logo-img" />
            <span v-else class="ma-header__logo-text">{{ pageTitle }}</span>
        </Link>

        <!-- Spacer -->
        <div class="ma-header__spacer" />

        <!-- Lado direito: busca + notificações + avatar -->
        <div class="ma-header__right">
            <!-- Search bar -->
            <form class="ma-header__search" @submit="doSearch">
                <Search class="ma-header__search-icon" />
                <input
                    v-model="searchQuery"
                    type="text"
                    class="ma-header__search-input"
                    placeholder="Pesquise módulos, aulas..."
                    @keydown.enter="doSearch"
                />
                <button v-if="searchQuery" type="button" class="ma-header__search-clear" @click="searchQuery = ''">
                    <X style="width:13px; height:13px;" />
                </button>
            </form>

            <!-- Busca (mobile only) -->
            <button type="button" class="ma-header__search-btn" @click="goToSearch">
                <Search style="width:19px; height:19px;" />
            </button>

            <!-- Notificações -->
            <button v-if="user" type="button" class="ma-header__action-btn" @click="emit('open-notifications')">
                <Bell style="width:19px; height:19px;" />
                <span v-if="unreadCount > 0" class="ma-header__badge" :style="{ backgroundColor: 'var(--ma-primary)' }">
                    {{ unreadCount > 99 ? '99+' : unreadCount }}
                </span>
            </button>

            <!-- Avatar + dropdown de perfil -->
            <div v-if="user" ref="profileMenuRef" class="ma-header__profile">
                <button
                    type="button"
                    class="ma-header__avatar"
                    :class="{ 'ma-header__avatar--active': profileMenuOpen }"
                    :style="{ background: 'var(--ma-primary)', color: 'var(--ma-on-primary)' }"
                    @click.stop="toggleProfileMenu"
                >
                    <img v-if="user.avatar_url" :src="user.avatar_url" style="width:100%;height:100%;object-fit:cover;" />
                    <span v-else>{{ initials }}</span>
                </button>

                <Transition
                    enter-active-class="pm-enter-active"
                    enter-from-class="pm-enter-from"
                    enter-to-class="pm-enter-to"
                    leave-active-class="pm-leave-active"
                    leave-from-class="pm-leave-from"
                    leave-to-class="pm-leave-to"
                >
                    <div v-if="profileMenuOpen" class="pm" @click.stop>
                        <!-- Cabeçalho do menu -->
                        <div class="pm__head">
                            <div class="pm__avatar" :style="{ background: 'var(--ma-primary)', color: 'var(--ma-on-primary)' }">
                                <img v-if="user.avatar_url" :src="user.avatar_url" style="width:100%;height:100%;object-fit:cover;" />
                                <span v-else>{{ initials }}</span>
                            </div>
                            <div class="pm__info">
                                <p class="pm__name">{{ user.name }}</p>
                                <p class="pm__email">{{ user.email }}</p>
                            </div>
                        </div>

                        <div class="pm__divider" />

                        <!-- Links de navegação -->
                        <Link :href="`${basePath}/perfil`" class="pm__item" @click="closeProfileMenu">
                            <User class="pm__item-icon" />
                            Meu perfil
                        </Link>

                        <button type="button" class="pm__item" @click="emit('open-account-modal'); closeProfileMenu()">
                            <Settings class="pm__item-icon" />
                            Editar conta
                        </button>

                        <button type="button" class="pm__item" @click="emit('open-notifications'); closeProfileMenu()">
                            <Bell class="pm__item-icon" />
                            Notificações
                            <span v-if="unreadCount > 0" class="pm__badge" :style="{ backgroundColor: 'var(--ma-primary)', color: 'var(--ma-on-primary)' }">
                                {{ unreadCount > 99 ? '99+' : unreadCount }}
                            </span>
                        </button>

                        <Link v-if="certificateEnabled" :href="`${basePath}/certificado`" class="pm__item" @click="closeProfileMenu">
                            <Award class="pm__item-icon" />
                            Certificado
                        </Link>

                        <div class="pm__divider" />

                        <Link :href="logoutHref" method="post" as="button" class="pm__item pm__item--danger" @click="closeProfileMenu">
                            <LogOut class="pm__item-icon" />
                            Sair
                        </Link>
                    </div>
                </Transition>
            </div>
        </div>
    </header>
</template>

<style scoped>
/* ── Header mobile: full-width bar ── */
.ma-header {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 40;
    height: 68px;
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 0 20px;
    background: var(--ma-header-bg);
    backdrop-filter: blur(20px);
    border-bottom: 1px solid var(--ma-border);
}

/* ── Header desktop: card flutuante à direita do sidebar ── */
@media (min-width: 1024px) {
    .ma-header {
        top: 12px;
        left: calc(220px + 12px + 12px);
        right: 12px;
        height: 64px;
        border-radius: 16px;
        border: 1px solid var(--ma-border);
        border-bottom: 1px solid var(--ma-border);
        background: var(--ma-header-bg);
        padding: 0 22px;
        gap: 16px;
    }
}

/* Logo */
.ma-header__logo {
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}
.ma-header__logo-img {
    height: 36px;
    width: auto;
    max-width: 150px;
    object-fit: contain;
}
.ma-header__logo-text {
    font-family: 'Cormorant Garamond', Georgia, serif;
    font-weight: 800;
    font-size: 17px;
    letter-spacing: 2px;
    text-transform: uppercase;
    color: var(--ma-text);
    white-space: nowrap;
}

/* Spacer */
.ma-header__spacer { flex: 1; }

/* Grupo lado direito */
.ma-header__right {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
}

/* Mobile search button (hidden on md+) */
.ma-header__search-btn {
    display: flex;
    width: 40px;
    height: 40px;
    align-items: center;
    justify-content: center;
    border-radius: 11px;
    background: none;
    border: none;
    cursor: pointer;
    color: var(--ma-text-2);
    transition: background 0.15s, color 0.15s;
    flex-shrink: 0;
}
.ma-header__search-btn:hover {
    background: rgba(255, 255, 255, 0.06);
    color: var(--ma-text);
}
@media (min-width: 768px) {
    .ma-header__search-btn { display: none; }
}

/* Search */
.ma-header__search {
    display: none;
    align-items: center;
    gap: 8px;
    width: 220px;
    background: rgba(255, 255, 255, 0.06);
    border: 1px solid var(--ma-border);
    border-radius: 24px;
    padding: 0 14px;
    transition: border-color 0.2s;
}
.ma-header__search:focus-within {
    border-color: rgba(111, 207, 0, 0.4);
}
@media (min-width: 768px) {
    .ma-header__search { display: flex; }
}
.ma-header__search-icon {
    width: 14px;
    height: 14px;
    color: var(--ma-text-2);
    flex-shrink: 0;
}
.ma-header__search-input {
    flex: 1;
    background: none;
    border: none;
    outline: none;
    font-size: 16px;
    color: var(--ma-text);
    font-family: 'Cormorant Garamond', Georgia, serif;
    padding: 9px 0;
}
.ma-header__search-input::placeholder { color: var(--ma-text-2); }
.ma-header__search-clear {
    background: none;
    border: none;
    cursor: pointer;
    color: var(--ma-text-2);
    display: flex;
    align-items: center;
    padding: 0;
    flex-shrink: 0;
    transition: color 0.15s;
}
.ma-header__search-clear:hover { color: var(--ma-text); }

/* Botão notificação */
.ma-header__action-btn {
    position: relative;
    display: flex;
    width: 40px;
    height: 40px;
    align-items: center;
    justify-content: center;
    border-radius: 11px;
    background: none;
    border: none;
    cursor: pointer;
    color: var(--ma-text-2);
    transition: background 0.15s, color 0.15s;
}
.ma-header__action-btn:hover {
    background: rgba(255, 255, 255, 0.06);
    color: var(--ma-text);
}
.ma-header__badge {
    position: absolute;
    top: -2px;
    right: -2px;
    min-width: 16px;
    height: 16px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    font-weight: 700;
    color: var(--ma-on-primary);
    padding: 0 3px;
}

/* Wrapper do perfil (posição relativa para o dropdown) */
.ma-header__profile {
    position: relative;
    flex-shrink: 0;
}

/* Avatar */
.ma-header__avatar {
    display: flex;
    width: 40px;
    height: 40px;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    border-radius: 50%;
    font-size: 13px;
    font-weight: 700;
    border: 2px solid transparent;
    cursor: pointer;
    transition: opacity 0.15s, border-color 0.15s;
}
.ma-header__avatar:hover { opacity: 0.85; }
.ma-header__avatar--active {
    border-color: var(--ma-primary);
    opacity: 1;
}

/* ── Dropdown de perfil ── */
.pm {
    position: absolute;
    top: calc(100% + 10px);
    right: 0;
    min-width: 220px;
    background: var(--ma-surface);
    border: 1px solid var(--ma-border);
    border-radius: 16px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
    z-index: 100;
    overflow: hidden;
    padding: 8px;
}

/* Cabeçalho do dropdown */
.pm__head {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 8px 10px;
}
.pm__avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 700;
    flex-shrink: 0;
}
.pm__info {
    min-width: 0;
    flex: 1;
}
.pm__name {
    font-family: 'Cormorant Garamond', Georgia, serif;
    font-size: 13px;
    font-weight: 700;
    color: var(--ma-text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin: 0;
}
.pm__email {
    font-family: 'DM Mono', monospace;
    font-size: 10px;
    color: var(--ma-text-2);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin: 2px 0 0;
}

.pm__divider {
    height: 1px;
    background: var(--ma-border);
    margin: 4px 0;
}

/* Itens do menu */
.pm__item {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    padding: 10px 10px;
    border-radius: 10px;
    border: none;
    background: none;
    font-family: 'Cormorant Garamond', Georgia, serif;
    font-size: 16px;
    font-weight: 500;
    color: var(--ma-text-2);
    text-decoration: none;
    cursor: pointer;
    transition: background 0.12s, color 0.12s;
    text-align: left;
}
.pm__item:hover {
    background: rgba(255, 255, 255, 0.06);
    color: var(--ma-text);
}
.pm__item-icon {
    width: 15px;
    height: 15px;
    flex-shrink: 0;
    opacity: 0.7;
}
.pm__item:hover .pm__item-icon {
    opacity: 1;
}
.pm__item--danger { color: var(--ma-danger); }
.pm__item--danger:hover { background: rgba(224, 85, 85, 0.08); color: var(--ma-danger); }
.pm__item--danger .pm__item-icon { opacity: 0.8; }

.pm__badge {
    margin-left: auto;
    min-width: 18px;
    height: 18px;
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    font-weight: 700;
    padding: 0 4px;
}

/* Animação de abertura */
.pm-enter-active { transition: opacity 0.15s ease, transform 0.15s ease; }
.pm-leave-active { transition: opacity 0.1s ease, transform 0.1s ease; }
.pm-enter-from  { opacity: 0; transform: translateY(-6px) scale(0.97); }
.pm-enter-to    { opacity: 1; transform: translateY(0) scale(1); }
.pm-leave-from  { opacity: 1; transform: translateY(0) scale(1); }
.pm-leave-to    { opacity: 0; transform: translateY(-4px) scale(0.97); }
</style>
