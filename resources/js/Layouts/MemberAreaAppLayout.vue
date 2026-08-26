<script setup>
import { computed, ref, onMounted, watch, onUnmounted } from 'vue';
import { Link, usePage, Head, router } from '@inertiajs/vue3';
import PwaInstallPrompt from '@/components/member-area/PwaInstallPrompt.vue';
import MemberAreaNotificationsPanel from '@/components/member-area/MemberAreaNotificationsPanel.vue';
import MemberAreaSidebar from '@/components/member-area/MemberAreaSidebar.vue';
import MemberAreaHeader from '@/components/member-area/MemberAreaHeader.vue';
import MemberAreaAIChat from '@/components/member-area/MemberAreaAIChat.vue';
import Button from '@/components/ui/Button.vue';
import { useMusicPlayer } from '@/composables/useMusicPlayer';

const { currentTrack, isPlaying, progress, duration, speed, showFullscreen,
        progressPct, togglePlay, seek, skip, setSpeed, fmtTime, stop, nextTrack, prevTrack, playlist } = useMusicPlayer();
import { User, X, Camera, Lock, CheckCircle, AlertCircle, Trophy, RotateCcw, Route, Brain, UserCheck, BarChart2, FileText, Shield, Sparkles, Users, Calendar, MessageCircle, Bell, Settings, LogOut, Compass, Music } from 'lucide-vue-next';

const page = usePage();
const props = computed(() => page.props);
// lesson_id só é enviado quando a página atual é uma aula em vídeo
const currentVideoLessonId = computed(() => {
    const lesson = page.props.lesson;
    if (lesson?.type === 'video' && lesson?.id) return lesson.id;
    return null;
});
const product = computed(() => props.value?.product ?? {});
const config = computed(() => props.value?.config ?? {});
const slug = computed(() => props.value?.slug ?? '');
const push_enabled = computed(() => props.value?.push_enabled ?? false);
const vapid_public = computed(() => props.value?.vapid_public ?? null);
const base_url = computed(() => props.value?.base_url ?? '');

const user = computed(() => props.value?.auth?.user ?? null);
const theme = computed(() => config.value?.theme ?? {});
const globalBranding = computed(() => props.value?.member_area_global_branding ?? {});
const sidebar = computed(() => config.value?.sidebar ?? {});
const headerLogo = computed(() =>
    config.value?.header?.logo_url || globalBranding.value?.logo_url || null
);
const sidebarItems = computed(() => sidebar.value?.items ?? [
    { title: 'Início', icon: 'home', link: '/', open_external: false },
]);

/** Número de itens no nav: sidebar + comunidade (se ativa). Se > 2, em mobile mostra hamburger. */
const totalNavCount = computed(() => {
    const items = sidebarItems.value.length;
    const withCommunity = config.value?.community_enabled ? 1 : 0;
    return items + withCommunity;
});
const certificateEnabled = computed(() => (config.value?.certificate ?? {})?.enabled ?? false);
const showMobileHamburger = computed(() => totalNavCount.value > 2);

const gamificationEnabled = computed(() => (config.value?.gamification ?? {})?.enabled ?? false);
const gamificationAchievements = computed(() => props.value?.gamification_achievements ?? []);
const showGamificationBadge = computed(() => gamificationEnabled.value && gamificationAchievements.value.length > 0);
const gamificationDropdownOpen = ref(false);
const gamificationDropdownRef = ref(null);
const lastUnlockedAchievement = computed(() => {
    const list = gamificationAchievements.value.filter((a) => a.unlocked);
    return list.length > 0 ? list[list.length - 1] : null;
});

const newlyUnlockedQueue = ref([]);
const achievementModalOpen = ref(false);
const currentAchievementModal = computed(() => newlyUnlockedQueue.value[0] ?? null);
function closeAchievementModal() {
    if (newlyUnlockedQueue.value.length > 0) {
        newlyUnlockedQueue.value = newlyUnlockedQueue.value.slice(1);
    }
    achievementModalOpen.value = newlyUnlockedQueue.value.length > 0;
}
function openAchievementModal(achievement) {
    newlyUnlockedQueue.value = [achievement];
    achievementModalOpen.value = true;
}
function openAchievementModals(achievements) {
    if (achievements?.length) {
        newlyUnlockedQueue.value = [...achievements];
        achievementModalOpen.value = true;
    }
}

function handleClickOutsideGamificationDropdown(e) {
    if (gamificationDropdownRef.value && !gamificationDropdownRef.value.contains(e.target)) gamificationDropdownOpen.value = false;
}

const mobileMenuOpen = ref(false);
const extraMenuOpen  = ref(false);
const headerScrolled = ref(false);
const chatOpen       = ref(false);

function closeMobileMenu() {
    mobileMenuOpen.value = false;
}

function onWindowScroll() {
    headerScrolled.value = typeof window !== 'undefined' && window.scrollY > 8;
}

const basePath = computed(() => {
    const mb = page.props?.member_base;
    if (mb !== undefined && mb !== null) return mb;
    return `/m/${slug.value}`;
});
/** Path "home" da área: sempre aponta para /comunidade. */
const homePath = computed(() => {
    const bp = basePath.value || '/area';
    return `${bp}/comunidade`.replace('//', '/');
});
/** Path do login da área atual. */
const memberAreaLoginPath = computed(() => '/entrar');
const logoutHref = computed(() => {
    const target = memberAreaLoginPath.value;
    return `/logout?redirect=${encodeURIComponent(target)}`;
});
const baseUrl = computed(() => {
    if (props.value?.base_url) return props.value.base_url;
    if (typeof window === 'undefined') return '';
    // Em host próprio/subdomínio, usar a origem atual para evitar montar /m/{slug} incorretamente.
    if (!window.location.pathname.startsWith('/m/')) return window.location.origin;
    return `${window.location.origin}${basePath.value}`;
});
const accountBaseUrl = computed(() => String(baseUrl.value || '').replace(/\/$/, ''));
/** Base path para API de notificações: /m/slug quando acesso por path, vazio quando por host. */
const notificationsApiBasePath = computed(() => {
    if (typeof window === 'undefined') return basePath.value;
    return window.location.pathname.startsWith('/m/') ? basePath.value : '';
});

const initials = computed(() => {
    if (!user.value?.name) return '?';
    const parts = String(user.value.name).trim().split(/\s+/);
    if (parts.length >= 2) return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    return (parts[0][0] || '?').toUpperCase();
});

const notificationsPanelOpen = ref(false);
const memberNotificationsUnreadCount = ref(props.value?.member_notifications_unread_count ?? 0);
const accountMenuOpen = ref(false);
const accountMenuRef = ref(null);
const accountModalOpen = ref(false);
const refundModalOpen = ref(false);
const refundReason = ref('');
const refundSubmitting = ref(false);
const refundError = ref('');
const refundSuccess = ref('');

const refundEligibility = computed(() => props.value?.refund_eligibility ?? null);
const showRefundMenu = computed(() => {
    const e = refundEligibility.value;
    if (!e?.enabled) return false;
    return e.can_request || (e.existing_request && ['pending', 'processing'].includes(e.existing_request.status));
});

const profileName = ref('');
const profileAvatarFile = ref(null);
const profileAvatarPreview = ref(null);
const profileSaving = ref(false);
const profileError = ref('');
const profileSuccess = ref('');

const passwordCurrent = ref('');
const passwordNew = ref('');
const passwordConfirm = ref('');
const passwordSaving = ref(false);
const passwordError = ref('');
const passwordSuccess = ref('');

function openAccountModal() {
    accountMenuOpen.value = false;
    profileName.value = user.value?.name ?? '';
    profileAvatarFile.value = null;
    profileAvatarPreview.value = null;
    profileError.value = '';
    profileSuccess.value = '';
    passwordCurrent.value = '';
    passwordNew.value = '';
    passwordConfirm.value = '';
    passwordError.value = '';
    passwordSuccess.value = '';
    accountModalOpen.value = true;
}

function closeAccountModal() {
    accountModalOpen.value = false;
}

function openRefundModal() {
    accountMenuOpen.value = false;
    refundReason.value = '';
    refundError.value = '';
    refundSuccess.value = '';
    refundModalOpen.value = true;
}

function closeRefundModal() {
    refundModalOpen.value = false;
}

async function submitRefundRequest() {
    const reason = refundReason.value.trim();
    if (reason.length < 10) {
        refundError.value = 'Descreva o motivo com pelo menos 10 caracteres.';
        return;
    }
    refundSubmitting.value = true;
    refundError.value = '';
    try {
        const res = await fetch(`${basePath.value}/refund`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': page.props.csrf_token ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ reason }),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
            refundError.value = data.message || data.errors?.reason?.[0] || 'Não foi possível enviar a solicitação.';
            return;
        }
        refundSuccess.value = data.message || 'Solicitação enviada com sucesso.';
        router.reload({ preserveScroll: true });
    } catch {
        refundError.value = 'Erro de conexão. Tente novamente.';
    } finally {
        refundSubmitting.value = false;
    }
}

function onProfileAvatarChange(e) {
    const file = e.target?.files?.[0];
    if (!file) return;
    profileAvatarFile.value = file;
    profileAvatarPreview.value = URL.createObjectURL(file);
}

function handleClickOutsideAccountMenu(e) {
    if (accountMenuRef.value && !accountMenuRef.value.contains(e.target)) accountMenuOpen.value = false;
}

async function saveProfile() {
    if (!user.value || profileSaving.value) return;
    const name = profileName.value?.trim();
    if (!name) {
        profileError.value = 'Informe o nome.';
        return;
    }
    profileError.value = '';
    profileSuccess.value = '';
    profileSaving.value = true;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const url = `${accountBaseUrl.value.replace(/\/$/, '')}/conta`;
    const formData = new FormData();
    formData.append('_method', 'PUT');
    formData.append('name', name);
    if (profileAvatarFile.value) formData.append('avatar', profileAvatarFile.value);
    try {
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: formData,
        });
        const data = await res.json().catch(() => ({}));
        if (res.ok) {
            profileSuccess.value = data?.message ?? 'Perfil atualizado.';
            router.reload();
        } else {
            profileError.value = data?.message ?? data?.errors?.name?.[0] ?? data?.errors?.avatar?.[0] ?? 'Erro ao atualizar perfil.';
        }
    } catch (_) {
        profileError.value = 'Erro ao atualizar perfil.';
    } finally {
        profileSaving.value = false;
    }
}

async function savePassword() {
    if (!user.value || passwordSaving.value) return;
    if (!passwordCurrent.value || !passwordNew.value || !passwordConfirm.value) {
        passwordError.value = 'Preencha todos os campos.';
        return;
    }
    if (passwordNew.value !== passwordConfirm.value) {
        passwordError.value = 'A nova senha e a confirmação não conferem.';
        return;
    }
    if (passwordNew.value.length < 8) {
        passwordError.value = 'A nova senha deve ter no mínimo 8 caracteres.';
        return;
    }
    passwordError.value = '';
    passwordSuccess.value = '';
    passwordSaving.value = true;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const url = `${accountBaseUrl.value.replace(/\/$/, '')}/conta/senha`;
    try {
        const res = await fetch(url, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({
                current_password: passwordCurrent.value,
                password: passwordNew.value,
                password_confirmation: passwordConfirm.value,
            }),
        });
        if (res.ok) {
            passwordSuccess.value = 'Senha alterada.';
            passwordCurrent.value = '';
            passwordNew.value = '';
            passwordConfirm.value = '';
        } else {
            const data = await res.json().catch(() => ({}));
            passwordError.value = data?.errors?.current_password?.[0] ?? data?.errors?.password?.[0] ?? data?.message ?? 'Erro ao alterar senha.';
        }
    } catch (_) {
        passwordError.value = 'Erro ao alterar senha.';
    } finally {
        passwordSaving.value = false;
    }
}

onMounted(() => {
    document.documentElement.classList.add('dark');
    document.addEventListener('click', handleClickOutsideAccountMenu);
    document.addEventListener('click', handleClickOutsideGamificationDropdown);
    onWindowScroll();
    window.addEventListener('scroll', onWindowScroll, { passive: true });
});
onUnmounted(() => {
    document.documentElement.classList.remove('dark');
    document.removeEventListener('click', handleClickOutsideAccountMenu);
    document.removeEventListener('click', handleClickOutsideGamificationDropdown);
    window.removeEventListener('scroll', onWindowScroll);
});

const faviconHref = computed(() => {
    const url = globalBranding.value?.favicon_url
        ?? config.value?.logos?.favicon
        ?? config.value?.pwa?.favicon
        ?? globalBranding.value?.logo_url
        ?? null;
    if (!url || typeof window === 'undefined') return null;
    if (url.startsWith('http')) return url;
    return url.startsWith('/') ? `${window.location.origin}${url}` : `${window.location.origin}/${url.replace(/^\//, '')}`;
});

const manifestUrl = computed(() => {
    const base = baseUrl.value;
    if (!base || typeof window === 'undefined') return null;
    return `${base.endsWith('/') ? base.slice(0, -1) : base}/manifest.json`;
});

const themeColor = computed(() => globalBranding.value?.theme_color || config.value?.pwa?.theme_color || globalBranding.value?.primary_color || '#0ea5e9');
const appName = computed(() => config.value?.pwa?.name || globalBranding.value?.area_name || product.value?.name || 'App');
const pageTitle = computed(() => globalBranding.value?.area_name || product.value?.name || config.value?.pwa?.name || 'Área de Membros');

const canRegisterPush = computed(() => Boolean(
    push_enabled.value &&
    vapid_public.value &&
    typeof window !== 'undefined' &&
    typeof navigator !== 'undefined' &&
    'serviceWorker' in navigator &&
    'PushManager' in window
));

/** Detecta se o app está rodando como PWA instalado (standalone). */
const isStandalonePwa = computed(() => {
    if (typeof window === 'undefined') return false;
    if (window.matchMedia('(display-mode: standalone)').matches) return true;
    if (window.matchMedia('(display-mode: fullscreen)').matches && window.navigator.standalone === false) return false;
    return !!window.navigator.standalone;
});

const pushSubscribing = ref(false);
const pushRegistered = ref(false);
const pushAutoPromptAttempted = ref(false);

/** No PWA instalado usa chave separada para "dispensado", assim o prompt aparece após instalar e logar mesmo se dispensou no browser. */
const PUSH_PROMPT_DISMISSED_KEY = computed(() => `push_prompt_dismissed_${slug.value || 'default'}${isStandalonePwa.value ? '_standalone' : ''}`);

function shouldAutoPromptPush() {
    if (!canRegisterPush.value || pushRegistered.value || pushSubscribing.value || pushAutoPromptAttempted.value) return false;
    if (typeof Notification === 'undefined') return false;
    if (Notification.permission === 'denied') return false;
    try {
        const dismissed = localStorage.getItem(PUSH_PROMPT_DISMISSED_KEY.value);
        if (dismissed) {
            const age = Date.now() - parseInt(dismissed, 10);
            if (age < 24 * 60 * 60 * 1000) return false; // não insistir por 24h se dispensou
        }
    } catch (_) {}
    return true;
}

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; ++i) outputArray[i] = rawData.charCodeAt(i);
    return outputArray;
}

function serializeSubscription(sub) {
    const p256dh = sub?.getKey?.('p256dh');
    const auth = sub?.getKey?.('auth');
    return {
        endpoint: sub?.endpoint,
        keys: {
            p256dh: p256dh ? btoa(String.fromCharCode.apply(null, new Uint8Array(p256dh))) : '',
            auth: auth ? btoa(String.fromCharCode.apply(null, new Uint8Array(auth))) : '',
        },
    };
}

async function syncMemberPushSubscription(sub, subscribeUrl, csrf) {
    const body = serializeSubscription(sub);
    if (!body.endpoint || !body.keys?.p256dh || !body.keys?.auth) {
        throw new Error('Subscription inválida para sincronização.');
    }
    const res = await fetch(subscribeUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify(body),
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok || !data?.success) {
        throw new Error(data?.message || 'Não foi possível sincronizar a inscrição de notificações.');
    }
    return true;
}

/** Verifica se já existe subscription no browser e atualiza pushRegistered (para o painel de notificações). */
async function checkExistingSubscriptionForPanel() {
    if (!canRegisterPush.value || typeof navigator === 'undefined' || !navigator.serviceWorker?.getRegistration) return;
    const scope = baseUrl.value ? (baseUrl.value.endsWith('/') ? baseUrl.value : baseUrl.value + '/') : null;
    if (!scope) return;
    try {
        const reg = await navigator.serviceWorker.getRegistration(scope);
        const existing = await reg?.pushManager?.getSubscription?.();
        if (!existing) return;
        const subscribeUrl = `${scope}push-subscribe`;
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
        await syncMemberPushSubscription(existing, subscribeUrl, csrf);
        pushRegistered.value = true;
    } catch (e) {
        console.warn('MemberArea push sync failed (panel check):', e);
    }
}

async function registerPushSubscription() {
    if (!canRegisterPush.value || pushSubscribing.value) return false;
    const scope = baseUrl.value.endsWith('/') ? baseUrl.value : baseUrl.value + '/';
    const swUrl = `${scope}sw.js`;
    const subscribeUrl = `${scope}push-subscribe`;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    pushSubscribing.value = true;
    pushAutoPromptAttempted.value = true;
    try {
        const reg = await navigator.serviceWorker.register(swUrl, { scope });
        const existing = await reg.pushManager?.getSubscription?.();
        const sub = existing || await reg.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(vapid_public.value),
        });
        await syncMemberPushSubscription(sub, subscribeUrl, csrf);
        pushRegistered.value = true;
        return true;
    } catch (e) {
        if (e.name === 'NotAllowedError') {
            try {
                localStorage.setItem(PUSH_PROMPT_DISMISSED_KEY.value, Date.now().toString());
            } catch (_) {}
            return false;
        }
        console.error('Push subscribe error', e);
        alert(e?.message || 'Não foi possível ativar as notificações. Verifique as permissões do navegador.');
        return false;
    } finally {
        pushSubscribing.value = false;
    }
}

onMounted(() => {
    if (pageTitle.value) document.title = pageTitle.value;
    const scope = baseUrl.value ? (baseUrl.value.endsWith('/') ? baseUrl.value : baseUrl.value + '/') : null;
    // No PWA instalado (standalone), delay maior para o prompt aparecer depois de logar e ver a tela
    const promptDelayMs = isStandalonePwa.value ? 3000 : 1500;
    function schedulePushPrompt() {
        if (!shouldAutoPromptPush()) return;
        setTimeout(() => {
            if (shouldAutoPromptPush()) registerPushSubscription();
        }, promptDelayMs);
    }
    if (scope && typeof navigator !== 'undefined' && navigator.serviceWorker) {
        navigator.serviceWorker.register(`${scope}sw.js`, { scope }).then(async (reg) => {
            if (reg.pushManager && canRegisterPush.value) {
                try {
                    const existing = await reg.pushManager.getSubscription();
                    if (existing) {
                        const subscribeUrl = `${scope}push-subscribe`;
                        const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
                        await syncMemberPushSubscription(existing, subscribeUrl, csrf);
                        pushRegistered.value = true;
                        return;
                    }
                } catch (e) {
                    console.warn('MemberArea push sync failed (onMounted):', e);
                }
            }
            schedulePushPrompt();
        }).catch(() => {
            schedulePushPrompt();
        });
    }
});

watch(pageTitle, (t) => { if (t) document.title = t; }, { immediate: true });

watch(
    () => [props.value?.flash?.newly_unlocked_achievements, props.value?.newly_unlocked_achievements],
    ([flashList, pageList]) => {
        const list = flashList ?? pageList ?? [];
        if (Array.isArray(list) && list.length > 0) openAchievementModals(list);
    },
    { immediate: true }
);

watch(
    () => props.value?.member_notifications_unread_count,
    (v) => { if (v !== undefined) memberNotificationsUnreadCount.value = v; },
    { immediate: true }
);
</script>

<template>
    <Head>
        <title>{{ pageTitle }}</title>
        <link v-if="manifestUrl" rel="manifest" :href="manifestUrl" />
        <meta name="theme-color" :content="themeColor" />
        <meta name="mobile-web-app-capable" content="yes" />
        <link v-if="faviconHref" rel="icon" :href="faviconHref" />
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
        <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&family=DM+Sans:wght@400;500;600;700;800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet" />
    </Head>
    <div
        id="ma-root"
        class="min-h-screen"
        :style="{
            '--ma-primary':       globalBranding.primary_color   || theme.primary || '#6FCF00',
            '--ma-on-primary':    globalBranding.on_primary_color || '#0D1F2D',
            '--ma-bg':            globalBranding.bg_color         || theme.background || '#090F18',
            '--ma-surface':       globalBranding.surface_color    || '#132435',
            '--ma-surface-2':     globalBranding.surface2_color   || '#1A3040',
            '--ma-border':        globalBranding.border_color     || '#1E3448',
            '--ma-text':          globalBranding.text_color       || '#FFFFFF',
            '--ma-text-2':        globalBranding.text2_color      || '#7A9BAD',
            '--ma-text-inactive': globalBranding.text_inactive_color || '#4A6A7A',
            '--ma-danger':        globalBranding.danger_color        || '#E05555',
            // Componentes estruturais separáveis
            '--ma-sidebar-bg':    globalBranding.sidebar_bg_color    || globalBranding.surface_color  || '#132435',
            '--ma-header-bg':     globalBranding.header_bg_color     || globalBranding.surface_color  || '#132435',
            '--ma-nav-bg':        globalBranding.nav_bg_color        || globalBranding.surface_color  || '#132435',
            '--ma-nav-active-bg': globalBranding.nav_active_bg_color || 'rgba(255,255,255,0.13)',
            '--ma-nav-active-text': globalBranding.nav_active_text_color || '#FFFFFF',
            '--ma-primary-hover':    globalBranding.button_hover_bg_color   || globalBranding.primary_color   || '#6FCF00',
            '--ma-on-primary-hover': globalBranding.button_hover_text_color || globalBranding.on_primary_color || '#0D1F2D',
            '--ma-teal':          '#00C897',
            '--ma-gold':          '#E8B84B',
            fontFamily: '\'Cormorant Garamond\', Georgia, serif',
            fontSize: '18px',
            lineHeight: '1.55',
            backgroundColor: 'var(--ma-bg)',
            color: 'var(--ma-text)',
        }"
    >
        <MemberAreaHeader
            @open-notifications="notificationsPanelOpen = true"
            @open-account-menu="mobileMenuOpen = true"
            @open-account-modal="openAccountModal()"
        />
        <MemberAreaSidebar
            @open-notifications="notificationsPanelOpen = true"
            @open-account-menu="mobileMenuOpen = true"
            @toggle-extra-menu="extraMenuOpen = !extraMenuOpen"
        />

        <!-- ═══ ÁREA DE CONTEÚDO ═════════════════════════════════════════════= -->
        <!-- Mobile: padding-top 68px (header) + padding-bottom 96px (bottom nav + safe area) -->
        <!-- Desktop: padding-left 240px (sidebar) -->
        <!-- pb-safe-nav normal | pb-safe-player quando mini player ativo -->
        <div
            class="min-h-screen pt-[68px] lg:pt-[90px] lg:pl-[256px] print:pl-0 print:pt-0 print:pb-0"
            :class="currentTrack ? 'pb-[160px] lg:pb-8' : 'pb-[96px] lg:pb-4'"
        >
            <main class="px-4 pt-2 pb-2 lg:px-8 lg:pt-4 print:p-0">
                <slot />
            </main>
        </div>

        <!-- ═══ MINI PLAYER — barra acima do nav mobile ════════════════════= -->
        <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0 translate-y-2" enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-150 ease-in" leave-from-class="opacity-100" leave-to-class="opacity-0 translate-y-2">
            <div v-if="currentTrack && !page.url.includes('/musicas')"
                class="lg:hidden fixed left-0 right-0 z-[31] print:hidden"
                style="bottom:calc(86px + env(safe-area-inset-bottom, 0px)); background:rgba(13,31,45,0.98); backdrop-filter:blur(20px); border-top:1px solid var(--ma-border); padding:8px 14px;"
                @click="router.visit(`${basePath}/musicas`)">
                <div style="display:flex; align-items:center; gap:8px;">
                    <!-- Prev -->
                    <button type="button" @click.stop="prevTrack"
                        style="background:none; border:none; cursor:pointer; padding:4px; flex-shrink:0; display:flex; align-items:center;"
                        :style="{ color: playlist.length > 1 ? 'var(--ma-text-2)' : 'rgba(255,255,255,0.15)', cursor: playlist.length > 1 ? 'pointer' : 'default' }">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><polygon points="19 20 9 12 19 4 19 20"/><line x1="5" y1="19" x2="5" y2="5" stroke="currentColor" stroke-width="2"/></svg>
                    </button>
                    <!-- Info (clica para ir à página) -->
                    <div style="flex:1; min-width:0;">
                        <p style="font-size:12px; font-weight:700; color:var(--ma-text); font-family:'Cormorant Garamond',Georgia,serif; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; margin:0;">{{ currentTrack.title }}</p>
                        <p style="font-size:10px; font-family:'DM Mono',monospace; color:var(--ma-text-2); margin:0;">{{ fmtTime(progress) }} / {{ fmtTime(duration) }}</p>
                    </div>
                    <!-- Play/Pause -->
                    <button type="button" @click.stop="togglePlay"
                        style="width:34px; height:34px; border-radius:50%; border:none; cursor:pointer; display:flex; align-items:center; justify-content:center; flex-shrink:0;"
                        :style="{ background: 'var(--ma-primary)' }">
                        <svg v-if="!isPlaying" width="13" height="13" viewBox="0 0 24 24" :fill="'var(--ma-on-primary)'" style="margin-left:2px;"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                        <svg v-else width="13" height="13" viewBox="0 0 24 24" :fill="'var(--ma-on-primary)'"><rect x="6" y="4" width="4" height="16" rx="1"/><rect x="14" y="4" width="4" height="16" rx="1"/></svg>
                    </button>
                    <!-- Next -->
                    <button type="button" @click.stop="nextTrack"
                        style="background:none; border:none; cursor:pointer; padding:4px; flex-shrink:0; display:flex; align-items:center;"
                        :style="{ color: playlist.length > 1 ? 'var(--ma-text-2)' : 'rgba(255,255,255,0.15)', cursor: playlist.length > 1 ? 'pointer' : 'default' }">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 4 15 12 5 20 5 4"/><line x1="19" y1="5" x2="19" y2="19" stroke="currentColor" stroke-width="2"/></svg>
                    </button>
                    <!-- Stop (fecha o player) -->
                    <button type="button" @click.stop="stop"
                        style="background:none; border:none; cursor:pointer; padding:4px; flex-shrink:0; color:rgba(255,255,255,0.3); display:flex; align-items:center;"
                        @mouseenter="$event.currentTarget.style.color='var(--ma-danger)'"
                        @mouseleave="$event.currentTarget.style.color='rgba(255,255,255,0.3)'">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <!-- Barra de progresso -->
                <div style="margin-top:5px; width:100%; height:2px; background:rgba(255,255,255,0.08); border-radius:1px;">
                    <div style="height:100%; border-radius:1px; transition:width 0.2s linear;"
                        :style="{ width: (progressPct * 100) + '%', background: 'var(--ma-primary)' }" />
                </div>
            </div>
        </Transition>

        <!-- ═══ MENU EXTRA MOBILE (hamburger → bottom sheet) ════════════════ -->
        <Teleport to="body">
            <Transition name="bs" :duration="{ enter: 360, leave: 260 }">
                <div v-if="extraMenuOpen" class="bs-overlay lg:hidden" @click="extraMenuOpen = false">
                    <!-- Backdrop -->
                    <div class="bs__backdrop" />

                    <!-- Sheet panel -->
                    <div class="bs__panel" @click.stop>

                        <!-- Handle -->
                        <div class="bs__handle">
                            <div class="bs__handle-bar" />
                        </div>

                        <!-- Perfil do usuário -->
                        <div v-if="user" class="bs__user">
                            <div class="bs__user-avatar" :style="{ background: 'var(--ma-primary)', color: 'var(--ma-on-primary)' }">
                                <img v-if="user.avatar_url" :src="user.avatar_url" style="width:100%;height:100%;object-fit:cover;" />
                                <span v-else>{{ initials }}</span>
                            </div>
                            <div class="bs__user-info">
                                <p class="bs__user-name">{{ user.name }}</p>
                                <p class="bs__user-email">{{ user.email }}</p>
                            </div>
                        </div>

                        <!-- ── Seção: Mais recursos ── -->
                        <div class="bs__section">
                            <p class="bs__section-label">Recursos</p>
                            <Link :href="`${basePath}/musicas`" class="em-item" @click="extraMenuOpen = false">
                                <Music class="em-icon" /><span>Áudios</span>
                            </Link>
                            <Link :href="`${basePath}/loja`" class="em-item" @click="extraMenuOpen = false">
                                <Compass class="em-icon" /><span>Explorar</span>
                            </Link>
                            <Link :href="`${basePath}/mapa-neuro`" class="em-item" @click="extraMenuOpen = false">
                                <Brain class="em-icon" /><span>Mapa Neuro</span>
                            </Link>
                            <Link :href="`${basePath}/profissionais`" class="em-item" @click="extraMenuOpen = false">
                                <UserCheck class="em-icon" /><span>Profissionais</span>
                            </Link>
                            <Link :href="`${basePath}/grupos`" class="em-item" @click="extraMenuOpen = false">
                                <Users class="em-icon" /><span>Grupos</span>
                            </Link>
                            <Link :href="`${basePath}/eventos`" class="em-item" @click="extraMenuOpen = false">
                                <Calendar class="em-icon" /><span>Eventos</span>
                            </Link>
                            <Link :href="`${basePath}/resultados`" class="em-item" @click="extraMenuOpen = false">
                                <BarChart2 class="em-icon" /><span>Mega Relatórios</span>
                            </Link>
                            <Link :href="`${basePath}/insights`" class="em-item" @click="extraMenuOpen = false">
                                <Sparkles class="em-icon" /><span>Insights da IA</span>
                            </Link>
                        </div>

                        <div class="bs__divider" />

                        <!-- ── Seção: Conta ── -->
                        <div class="bs__section">
                            <p class="bs__section-label">Conta</p>
                            <Link :href="`${basePath}/perfil`" class="em-item" @click="extraMenuOpen = false">
                                <User class="em-icon" /><span>Meu perfil</span>
                            </Link>
                            <button type="button" class="em-item" @click="openAccountModal(); extraMenuOpen = false">
                                <Settings class="em-icon" /><span>Editar conta</span>
                            </button>
                            <button type="button" class="em-item" @click="notificationsPanelOpen = true; extraMenuOpen = false">
                                <Bell class="em-icon" /><span>Notificações</span>
                                <span v-if="memberNotificationsUnreadCount > 0" class="em-badge" :style="{ background: 'var(--ma-primary)', color: 'var(--ma-on-primary)' }">
                                    {{ memberNotificationsUnreadCount > 99 ? '99+' : memberNotificationsUnreadCount }}
                                </span>
                            </button>
                            <Link v-if="certificateEnabled" :href="`${basePath}/certificado`" class="em-item" @click="extraMenuOpen = false">
                                <Trophy class="em-icon" /><span>Certificado</span>
                            </Link>
                        </div>

                        <div class="bs__divider" />

                        <!-- ── Sair ── -->
                        <div class="bs__section">
                            <Link :href="logoutHref" method="post" as="button" class="em-item em-item--danger" @click="extraMenuOpen = false">
                                <LogOut class="em-icon" /><span>Sair</span>
                            </Link>
                        </div>

                        <!-- Espaço de safe area iOS -->
                        <div class="bs__safe" />
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Drawer lateral da conta (abre pelo botão Conta no bottom nav) -->
        <Teleport to="body">
            <Transition enter-active-class="transition duration-300 ease-out" enter-from-class="opacity-0"
                enter-to-class="opacity-100" leave-active-class="transition duration-200 ease-in"
                leave-from-class="opacity-100" leave-to-class="opacity-0">
                <div v-if="mobileMenuOpen" class="fixed inset-0 z-40" aria-hidden="true" @click="mobileMenuOpen = false">
                    <div class="absolute inset-0" style="background:rgba(9,21,32,0.8); backdrop-filter:blur(4px);" />
                    <!-- Drawer -->
                    <div class="absolute bottom-0 left-0 right-0 rounded-t-2xl overflow-hidden"
                        style="background:var(--ma-surface); border-top:1px solid var(--ma-border); max-height:85vh; overflow-y:auto;"
                        @click.stop>
                        <!-- Handle -->
                        <div style="display:flex; justify-content:center; padding:12px 0;">
                            <div style="width:36px; height:4px; border-radius:2px; background:var(--ma-border);" />
                        </div>

                        <!-- Avatar + nome -->
                        <div style="display:flex; flex-direction:column; align-items:center; padding:16px 24px 28px; text-align:center;">
                            <div style="width:64px; height:64px; border-radius:50%; overflow:hidden; display:flex; align-items:center; justify-content:center; font-size:24px; font-weight:800; margin-bottom:12px;"
                                        :style="{ background: 'var(--ma-primary)', color: 'var(--ma-on-primary)' }">
                                <img v-if="user?.avatar_url" :src="user.avatar_url" style="width:100%;height:100%;object-fit:cover;" />
                                <span v-else>{{ initials }}</span>
                            </div>
                            <p style="font-family:'Cormorant Garamond',Georgia,serif; font-size:20px; font-weight:800; color:var(--ma-text);">{{ user?.name }}</p>
                            <p style="font-family:'DM Mono',monospace; font-size:11px; color:var(--ma-text-2); margin-top:6px;">{{ user?.email }}</p>
                        </div>

                        <!-- Menu items -->
                        <div style="padding:0 16px 16px;">
                            <!-- Meu perfil -->
                            <Link :href="`${basePath}/perfil`"
                                style="display:flex; align-items:center; justify-content:space-between; padding:16px 0; border-bottom:1px solid var(--ma-border); text-decoration:none;"
                                @click="mobileMenuOpen = false">
                                <span style="font-size:15px; color:var(--ma-text-2); font-family:'Cormorant Garamond',Georgia,serif;">Meu perfil</span>
                                <span style="color:var(--ma-text-2);">→</span>
                            </Link>
                            <button type="button"
                                style="display:flex; align-items:center; justify-content:space-between; width:100%; padding:16px 0; border:none; border-bottom:1px solid var(--ma-border); background:none; cursor:pointer; text-align:left;"
                                @click="openAccountModal(); mobileMenuOpen = false">
                                <span style="font-size:15px; color:var(--ma-text-2); font-family:'Cormorant Garamond',Georgia,serif;">Editar conta</span>
                                <span style="color:var(--ma-text-2);">→</span>
                            </button>
                            <button v-if="showRefundMenu" type="button"
                                style="display:flex; align-items:center; justify-content:space-between; width:100%; padding:16px 0; border:none; border-bottom:1px solid var(--ma-border); background:none; cursor:pointer;"
                                @click="openRefundModal(); mobileMenuOpen = false">
                                <span style="font-size:15px; color:var(--ma-text-2); font-family:'Cormorant Garamond',Georgia,serif;">{{ refundEligibility?.can_request ? 'Solicitar reembolso' : 'Reembolso em andamento' }}</span>
                                <span style="color:var(--ma-text-2);">→</span>
                            </button>
                            <Link v-if="certificateEnabled" :href="`${basePath}/certificado`"
                                style="display:flex; align-items:center; justify-content:space-between; padding:16px 0; border-bottom:1px solid var(--ma-border); text-decoration:none;"
                                @click="mobileMenuOpen = false">
                                <span style="font-size:15px; color:var(--ma-text-2); font-family:'Cormorant Garamond',Georgia,serif;">Certificado</span>
                                <span style="color:var(--ma-text-2);">→</span>
                            </Link>
                            <button type="button"
                                style="display:flex; align-items:center; justify-content:space-between; width:100%; padding:16px 0; border:none; background:none; cursor:pointer;"
                                @click="notificationsPanelOpen = true; mobileMenuOpen = false">
                                <span style="font-size:15px; color:var(--ma-text-2); font-family:'Cormorant Garamond',Georgia,serif;">Notificações</span>
                                <span style="color:var(--ma-text-2);">→</span>
                            </button>
                            <Link :href="logoutHref" method="post" as="button"
                                style="display:flex; align-items:center; justify-content:space-between; width:100%; padding:16px 0; border:none; border-top:1px solid var(--ma-border); background:none; cursor:pointer;"
                                @click="mobileMenuOpen = false">
                                <span style="font-size:15px; font-family:'Cormorant Garamond',Georgia,serif; color:var(--ma-danger);">Sair</span>
                                <span style="color:var(--ma-danger);">→</span>
                            </Link>
                        </div>
                        <div style="height:16px;" />
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- ═══ BOTÃO FLUTUANTE DE CHAT IA ═════════════════════════════════= -->
        <button
            type="button"
            class="chat-fab print:hidden"
            :style="{ background: 'var(--ma-primary)' }"
            aria-label="Chat com IA"
            @click="chatOpen = true"
        >
            <MessageCircle class="chat-fab__icon" />
        </button>

        <!-- Painel de chat IA -->
        <Teleport to="body">
            <MemberAreaAIChat
                v-if="chatOpen"
                :base-path="basePath"
                :primary-color="theme.primary || '#6FCF00'"
                :lesson-id="currentVideoLessonId"
                @close="chatOpen = false"
            />
        </Teleport>

        <PwaInstallPrompt v-if="slug" :app-name="appName" :slug="slug" />
        <MemberAreaNotificationsPanel
            :open="notificationsPanelOpen"
            :base-path="notificationsApiBasePath"
            :push-enabled="push_enabled"
            :push-can-register="canRegisterPush"
            :push-registered="pushRegistered"
            :push-subscribing="pushSubscribing"
            :register-push="registerPushSubscription"
            :check-existing-subscription="checkExistingSubscriptionForPanel"
            @update:open="notificationsPanelOpen = $event"
            @unread-count-update="memberNotificationsUnreadCount = $event"
        />

        <!-- [placeholder removido] -->
        <Teleport to="body">
            <div
                v-if="false"
                class="hidden"
            >
                <div
                    class="absolute inset-0 bg-black/50 backdrop-blur-sm"
                    @click="closeMobileMenu"
                />
                <div
                    class="absolute right-0 top-0 bottom-0 flex h-full w-72 max-w-[85vw] flex-col border-l border-zinc-700 bg-zinc-900 shadow-2xl"
                    :style="{ paddingTop: '3.5rem' }"
                >
                    <button
                        type="button"
                        class="absolute right-3 top-3 rounded-lg p-2 text-zinc-400 hover:bg-zinc-800 hover:text-white"
                        aria-label="Fechar menu"
                        @click="closeMobileMenu"
                    >
                        <X class="h-5 w-5" />
                    </button>
                    <nav class="flex flex-col gap-1 px-4 py-2">
                        <template v-for="item in sidebarItems" :key="item.title">
                            <a
                                v-if="item.open_external"
                                :href="item.link"
                                target="_blank"
                                rel="noopener"
                                class="rounded-lg px-4 py-3 text-sm font-medium text-zinc-200 hover:bg-zinc-800 hover:text-white"
                                @click="closeMobileMenu"
                            >
                                {{ item.title }}
                            </a>
                            <Link
                                v-else
                                :href="item.link.startsWith('/') ? basePath + item.link : basePath + '/' + item.link"
                                class="rounded-lg px-4 py-3 text-sm font-medium text-zinc-200 hover:bg-zinc-800 hover:text-white"
                                @click="closeMobileMenu"
                            >
                                {{ item.title }}
                            </Link>
                        </template>
                        <Link
                            v-if="config?.community_enabled"
                            :href="`${basePath}/comunidade`"
                            class="rounded-lg px-4 py-3 text-sm font-medium text-zinc-200 hover:bg-zinc-800 hover:text-white"
                            @click="closeMobileMenu"
                        >
                            Comunidade
                        </Link>
                    </nav>
                    <div v-if="canRegisterPush && !pushRegistered" class="border-t border-zinc-700 px-4 py-3">
                        <button
                            type="button"
                            class="w-full rounded-lg bg-zinc-800 px-4 py-3 text-sm font-medium text-zinc-200 hover:bg-zinc-700"
                            :disabled="pushSubscribing"
                            @click="registerPushSubscription(); closeMobileMenu()"
                        >
                            {{ pushSubscribing ? 'Ativando…' : 'Ativar notificações' }}
                        </button>
                    </div>
                    <div v-if="user" class="mt-auto border-t border-zinc-700 px-4 py-4">
                        <div class="mb-3 flex items-center gap-3">
                            <span
                                class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full text-sm font-medium text-white"
                                :style="{ backgroundColor: 'var(--ma-primary)' }"
                            >
                                <img
                                    v-if="user.avatar_url"
                                    :src="user.avatar_url"
                                    :alt="user.name"
                                    class="h-full w-full object-cover"
                                />
                                <span v-else>{{ initials }}</span>
                            </span>
                            <div class="min-w-0">
                                <p class="truncate font-medium text-zinc-100">{{ user.name }}</p>
                                <p class="truncate text-xs text-zinc-500">{{ user.email }}</p>
                            </div>
                        </div>
                        <div class="flex flex-col gap-1">
                            <button
                                type="button"
                                class="flex w-full items-center gap-2 rounded-lg px-4 py-3 text-left text-sm font-medium text-zinc-300 hover:bg-zinc-800"
                                @click="openAccountModal(); closeMobileMenu()"
                            >
                                <User class="h-4 w-4" />
                                Minha conta
                            </button>
                            <button
                                v-if="showRefundMenu"
                                type="button"
                                class="flex w-full items-center gap-2 rounded-lg px-4 py-3 text-left text-sm font-medium text-zinc-300 hover:bg-zinc-800"
                                @click="openRefundModal(); closeMobileMenu()"
                            >
                                <RotateCcw class="h-4 w-4" />
                                {{ refundEligibility?.can_request ? 'Solicitar reembolso' : 'Reembolso em andamento' }}
                            </button>
                            <Link
                                v-if="certificateEnabled"
                                :href="`${basePath}/certificado`"
                                class="flex w-full items-center gap-2 rounded-lg px-4 py-3 text-left text-sm font-medium text-zinc-300 hover:bg-zinc-800"
                                @click="closeMobileMenu"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                                </svg>
                                Certificado
                            </Link>
                            <Link
                                :href="logoutHref"
                                method="post"
                                as="button"
                                class="flex w-full items-center gap-2 rounded-lg px-4 py-3 text-left text-sm font-medium text-zinc-300 hover:bg-zinc-800"
                                @click="closeMobileMenu"
                            >
                                Sair
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Modal Minha conta -->
        <Teleport to="body">
            <div
                v-if="accountModalOpen"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm"
                role="dialog"
                aria-modal="true"
                aria-labelledby="account-modal-title"
                @keydown.escape="closeAccountModal"
            >
                <div
                    class="w-full max-w-lg overflow-hidden rounded-2xl border border-zinc-700 bg-zinc-900 shadow-2xl"
                    @click.stop
                >
                    <!-- Header com faixa de destaque -->
                    <div
                        class="relative flex items-center justify-between px-6 py-5"
                        :style="{ background: 'linear-gradient(135deg, var(--ma-primary) 0%, color-mix(in srgb, var(--ma-primary) 80%, black) 100%)' }"
                    >
                        <div class="flex items-center gap-3">
                            <span
                                class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-white/20 text-lg font-semibold text-white backdrop-blur"
                            >
                                <img
                                    v-if="user?.avatar_url && !profileAvatarPreview"
                                    :src="user.avatar_url"
                                    :alt="user.name"
                                    class="h-full w-full object-cover"
                                />
                                <User v-else-if="profileAvatarPreview" class="h-6 w-6 text-white" />
                                <span v-else class="flex h-full w-full items-center justify-center">{{ initials }}</span>
                            </span>
                            <div>
                                <h2 id="account-modal-title" class="text-lg font-semibold text-white">Minha conta</h2>
                                <p class="text-sm text-white/80">Altere seu perfil e senha</p>
                            </div>
                        </div>
                        <button
                            type="button"
                            class="rounded-xl p-2.5 text-white/90 transition hover:bg-white/20 hover:text-white"
                            aria-label="Fechar"
                            @click="closeAccountModal"
                        >
                            <X class="h-5 w-5" />
                        </button>
                    </div>

                    <div class="max-h-[70vh] overflow-y-auto p-6 space-y-6 bg-zinc-900">
                        <!-- Card Perfil -->
                        <div class="rounded-xl border border-zinc-700 bg-zinc-800/50 p-5">
                            <div class="mb-4 flex items-center gap-2">
                                <div class="flex h-8 w-8 items-center justify-center rounded-lg text-white" :style="{ backgroundColor: 'var(--ma-primary)' }">
                                    <User class="h-4 w-4" />
                                </div>
                                <h3 class="text-sm font-semibold uppercase tracking-wide text-zinc-400">Perfil</h3>
                            </div>
                            <div class="flex flex-col gap-5 sm:flex-row sm:items-start">
                                <label class="group relative flex shrink-0 cursor-pointer">
                                    <span
                                        class="flex h-24 w-24 overflow-hidden rounded-2xl border-2 border-zinc-600 text-2xl font-medium shadow-inner transition group-hover:border-[var(--ma-primary)]"
                                        :style="{ backgroundColor: 'var(--ma-primary)', color: 'white' }"
                                    >
                                        <img
                                            v-if="profileAvatarPreview || user?.avatar_url"
                                            :src="profileAvatarPreview || user?.avatar_url"
                                            :alt="user?.name"
                                            class="h-full w-full object-cover"
                                        />
                                        <span v-else class="flex h-full w-full items-center justify-center">{{ initials }}</span>
                                    </span>
                                    <span
                                        class="absolute bottom-0 right-0 flex h-8 w-8 items-center justify-center rounded-lg bg-[var(--ma-primary)] text-white shadow-lg transition group-hover:scale-105"
                                    >
                                        <Camera class="h-4 w-4" />
                                    </span>
                                    <input type="file" accept="image/*" class="sr-only" @change="onProfileAvatarChange" />
                                </label>
                                <div class="min-w-0 flex-1 space-y-4">
                                    <div>
                                        <label class="mb-1.5 block text-sm font-medium text-zinc-300">Nome</label>
                                        <input
                                            v-model="profileName"
                                            type="text"
                                            class="w-full rounded-xl border-2 border-zinc-600 bg-zinc-800 px-4 py-2.5 text-zinc-100 placeholder-zinc-500 transition focus:border-[var(--ma-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--ma-primary)]/20"
                                            placeholder="Seu nome"
                                        />
                                    </div>
                                    <p class="text-xs text-zinc-500">Este nome aparecerá nos comentários e na comunidade.</p>
                                </div>
                            </div>
                            <div v-if="profileError" class="mt-4 flex items-center gap-2 rounded-xl bg-red-950/50 px-4 py-3 text-sm text-red-300">
                                <AlertCircle class="h-4 w-4 shrink-0" />
                                {{ profileError }}
                            </div>
                            <div v-if="profileSuccess" class="mt-4 flex items-center gap-2 rounded-xl bg-emerald-950/50 px-4 py-3 text-sm text-emerald-300">
                                <CheckCircle class="h-4 w-4 shrink-0" />
                                {{ profileSuccess }}
                            </div>
                            <button
                                type="button"
                                class="mt-4 w-full rounded-xl px-4 py-3 text-sm font-semibold text-white shadow-lg transition hover:opacity-95 disabled:opacity-50"
                                :style="{ backgroundColor: 'var(--ma-primary)' }"
                                :disabled="profileSaving"
                                @click="saveProfile"
                            >
                                {{ profileSaving ? 'Salvando…' : 'Salvar perfil' }}
                            </button>
                        </div>

                        <!-- Card Senha -->
                        <div class="rounded-xl border border-zinc-700 bg-zinc-800/50 p-5">
                            <div class="mb-4 flex items-center gap-2">
                                <div class="flex h-8 w-8 items-center justify-center rounded-lg text-white" :style="{ backgroundColor: 'var(--ma-primary)' }">
                                    <Lock class="h-4 w-4" />
                                </div>
                                <h3 class="text-sm font-semibold uppercase tracking-wide text-zinc-400">Alterar senha</h3>
                            </div>
                            <div class="space-y-4">
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-zinc-300">Senha atual</label>
                                    <input
                                        v-model="passwordCurrent"
                                        type="password"
                                        class="w-full rounded-xl border-2 border-zinc-600 bg-zinc-800 px-4 py-2.5 text-zinc-100 placeholder-zinc-500 transition focus:border-[var(--ma-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--ma-primary)]/20"
                                        placeholder="••••••••"
                                        autocomplete="current-password"
                                    />
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-zinc-300">Nova senha</label>
                                    <input
                                        v-model="passwordNew"
                                        type="password"
                                        class="w-full rounded-xl border-2 border-zinc-600 bg-zinc-800 px-4 py-2.5 text-zinc-100 placeholder-zinc-500 transition focus:border-[var(--ma-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--ma-primary)]/20"
                                        placeholder="Mínimo 8 caracteres"
                                        autocomplete="new-password"
                                    />
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-zinc-300">Confirmar nova senha</label>
                                    <input
                                        v-model="passwordConfirm"
                                        type="password"
                                        class="w-full rounded-xl border-2 border-zinc-600 bg-zinc-800 px-4 py-2.5 text-zinc-100 placeholder-zinc-500 transition focus:border-[var(--ma-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--ma-primary)]/20"
                                        placeholder="••••••••"
                                        autocomplete="new-password"
                                    />
                                </div>
                            </div>
                            <div v-if="passwordError" class="mt-4 flex items-center gap-2 rounded-xl bg-red-950/50 px-4 py-3 text-sm text-red-300">
                                <AlertCircle class="h-4 w-4 shrink-0" />
                                {{ passwordError }}
                            </div>
                            <div v-if="passwordSuccess" class="mt-4 flex items-center gap-2 rounded-xl bg-emerald-950/50 px-4 py-3 text-sm text-emerald-300">
                                <CheckCircle class="h-4 w-4 shrink-0" />
                                {{ passwordSuccess }}
                            </div>
                            <button
                                type="button"
                                class="mt-4 w-full rounded-xl border-2 border-[var(--ma-primary)] bg-transparent px-4 py-3 text-sm font-semibold text-white transition hover:bg-[var(--ma-primary)]/20 disabled:opacity-50"
                                :disabled="passwordSaving"
                                @click="savePassword"
                            >
                                {{ passwordSaving ? 'Alterando…' : 'Alterar senha' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Modal: solicitar reembolso -->
        <Teleport to="body">
            <div
                v-if="refundModalOpen"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm"
                role="dialog"
                aria-modal="true"
                aria-labelledby="refund-modal-title"
                @click.self="closeRefundModal"
            >
                <div class="w-full max-w-md rounded-2xl border border-zinc-200 bg-white p-6 shadow-xl dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 id="refund-modal-title" class="text-lg font-semibold text-zinc-900 dark:text-white">Solicitar reembolso</h2>
                        <button type="button" class="rounded-lg p-1 text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-800" @click="closeRefundModal">
                            <X class="h-5 w-5" />
                        </button>
                    </div>
                    <template v-if="refundEligibility?.can_request">
                        <p v-if="refundEligibility.days_remaining != null" class="mb-3 text-sm text-zinc-600 dark:text-zinc-400">
                            Você tem {{ refundEligibility.days_remaining }} dia(s) restante(s) para solicitar o reembolso.
                        </p>
                        <label class="mb-1.5 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Motivo da solicitação</label>
                        <textarea
                            v-model="refundReason"
                            rows="5"
                            maxlength="2000"
                            class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-100"
                            placeholder="Descreva o motivo do reembolso (mínimo 10 caracteres)…"
                        />
                        <div v-if="refundError" class="mt-3 flex items-center gap-2 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-950/40 dark:text-red-300">
                            <AlertCircle class="h-4 w-4 shrink-0" />
                            {{ refundError }}
                        </div>
                        <div v-if="refundSuccess" class="mt-3 flex items-center gap-2 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">
                            <CheckCircle class="h-4 w-4 shrink-0" />
                            {{ refundSuccess }}
                        </div>
                        <div class="mt-4 flex gap-2">
                            <Button type="button" variant="outline" class="flex-1" @click="closeRefundModal">Cancelar</Button>
                            <Button type="button" class="flex-1" :disabled="refundSubmitting" @click="submitRefundRequest">
                                {{ refundSubmitting ? 'Enviando…' : 'Enviar solicitação' }}
                            </Button>
                        </div>
                    </template>
                    <template v-else>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">
                            {{ refundEligibility?.existing_request?.status_label
                                ? `Sua solicitação está com status: ${refundEligibility.existing_request.status_label}.`
                                : (refundEligibility?.message || 'Não é possível solicitar reembolso no momento.') }}
                        </p>
                        <Button type="button" class="mt-4 w-full" @click="closeRefundModal">Fechar</Button>
                    </template>
                </div>
            </div>
        </Teleport>

        <!-- Modal: conquista desbloqueada -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="achievementModalOpen && currentAchievementModal"
                    class="fixed inset-0 z-[100] flex items-center justify-center p-4"
                    aria-modal="true"
                    role="dialog"
                >
                    <div
                        class="absolute inset-0 bg-black/60 backdrop-blur-sm"
                        @click="closeAchievementModal"
                    />
                    <Transition
                        enter-active-class="transition duration-300 ease-out"
                        enter-from-class="opacity-0 scale-95"
                        enter-to-class="opacity-100 scale-100"
                        leave-active-class="transition duration-200 ease-in"
                        leave-from-class="opacity-100 scale-100"
                        leave-to-class="opacity-0 scale-95"
                    >
                        <div
                            v-if="currentAchievementModal"
                            class="relative max-w-sm w-full rounded-2xl border border-zinc-200 bg-white p-6 shadow-2xl dark:border-zinc-700 dark:bg-zinc-900"
                            @click.stop
                        >
                            <div class="flex flex-col items-center text-center">
                                <div class="mb-4 flex h-24 w-24 items-center justify-center overflow-hidden rounded-full ring-4 ring-[var(--ma-primary)]/30">
                                    <img
                                        v-if="currentAchievementModal.image_url"
                                        :src="currentAchievementModal.image_url"
                                        :alt="currentAchievementModal.title"
                                        class="h-full w-full object-cover"
                                    />
                                    <Trophy v-else class="h-12 w-12 text-[var(--ma-primary)]" />
                                </div>
                                <p class="mb-1 text-xs font-semibold uppercase tracking-wider text-[var(--ma-primary)]">Conquista desbloqueada</p>
                                <h3 class="mb-2 text-xl font-bold text-zinc-900 dark:text-zinc-100">{{ currentAchievementModal.title }}</h3>
                                <p v-if="currentAchievementModal.description" class="mb-6 text-sm text-zinc-600 dark:text-zinc-400">{{ currentAchievementModal.description }}</p>
                                <Button type="button" @click="closeAchievementModal">Continuar</Button>
                            </div>
                        </div>
                    </Transition>
                </div>
            </Transition>
        </Teleport>

        <!-- Mini player desktop: dentro da sidebar (ver sidebar abaixo) -->

        <!-- ═══ PLAYER FULLSCREEN (desativado — player agora é a página /musicas) ═════════════════════════ -->
        <Teleport to="body">
            <Transition>
                <div v-if="false && showFullscreen && currentTrack"
                    class="fixed inset-0 z-[70] flex flex-col print:hidden"
                    style="background:var(--ma-bg); font-family:'Cormorant Garamond',Georgia,serif;">

                    <!-- Header -->
                    <div style="display:flex; align-items:center; justify-content:space-between; padding:56px 24px 16px; flex-shrink:0;">
                        <button type="button" @click="showFullscreen = false"
                            style="background:none; border:none; cursor:pointer; color:var(--ma-text-2); display:flex; align-items:center; gap:8px;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
                        </button>
                        <span style="font-family:'DM Mono',monospace; font-size:10px; color:var(--ma-text-2); text-transform:uppercase; letter-spacing:1.5px;">reproduzindo</span>
                        <div style="width:20px;" />
                    </div>

                    <!-- Artwork com waveform animado -->
                    <div style="flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; padding:0 40px;">
                        <div style="position:relative; width:220px; height:220px; display:flex; align-items:center; justify-content:center; margin-bottom:40px;">
                            <div style="position:absolute; width:220px; height:220px; border-radius:50%; border:1px solid rgba(111,207,0,0.08); animation:pulse 4s ease-in-out infinite;" />
                            <div style="position:absolute; width:185px; height:185px; border-radius:50%; border:1px solid rgba(111,207,0,0.12); animation:pulse 4s ease-in-out infinite; animation-delay:0.5s;" />
                            <div style="position:absolute; width:150px; height:150px; border-radius:50%; border:1px solid rgba(0,200,151,0.15); animation:pulse 4s ease-in-out infinite; animation-delay:1s;" />
                            <!-- Core com waveform -->
                            <div style="width:110px; height:110px; border-radius:50%; display:flex; align-items:center; justify-content:center; box-shadow:0 0 60px rgba(111,207,0,0.1);"
                                :style="{ background: `radial-gradient(circle at 40% 35%, rgba(111,207,0,0.25), rgba(0,200,151,0.15), var(--ma-surface))`, border: '1px solid rgba(111,207,0,0.2)' }">
                                <div style="display:flex; align-items:center; gap:3px; height:40px;">
                                    <div v-for="(h,i) in [60,100,50,80,45,90,55,75]" :key="i"
                                        style="width:3px; border-radius:2px; transition:transform 0.3s;"
                                        :style="{
                                            height: h+'%',
                                            background: i%2===0 ? 'var(--ma-primary)' : 'var(--ma-teal, #00C897)',
                                            opacity: isPlaying ? 0.9 : 0.4,
                                            animation: isPlaying ? `wave 1.2s ease-in-out infinite` : 'none',
                                            animationDelay: (i*0.1)+'s'
                                        }" />
                                </div>
                            </div>
                        </div>

                        <!-- Título + meta -->
                        <div style="text-align:center; width:100%;">
                            <h1 style="font-size:22px; font-weight:700; color:var(--ma-text); line-height:1.3; margin-bottom:8px;">{{ currentTrack.title }}</h1>
                            <p style="font-size:13px; color:var(--ma-text-2); font-family:'DM Mono',monospace;">{{ currentTrack.category || 'Biblioteca' }}</p>
                        </div>
                    </div>

                    <!-- Controles -->
                    <div style="padding:0 24px 60px; flex-shrink:0;">
                        <!-- Progress bar clicável -->
                        <div style="margin-bottom:20px;">
                            <div style="width:100%; height:4px; background:var(--ma-surface-2); border-radius:2px; position:relative; cursor:pointer;"
                                @click="e => { const r = e.currentTarget.getBoundingClientRect(); seek(Math.max(0, Math.min(1, (e.clientX - r.left) / r.width))); }">
                                <div style="height:100%; border-radius:2px; transition:width 0.2s linear;"
                                    :style="{ width: (progressPct * 100) + '%', background: `linear-gradient(90deg, var(--ma-teal, #00C897), var(--ma-primary))` }" />
                                <div style="position:absolute; top:-4px; width:12px; height:12px; border-radius:50%; transform:translateX(-50%); box-shadow:0 0 8px rgba(111,207,0,0.5);"
                                    :style="{ left: (progressPct * 100) + '%', background: 'var(--ma-primary)' }" />
                            </div>
                            <div style="display:flex; justify-content:space-between; margin-top:8px;">
                                <span style="font-family:'DM Mono',monospace; font-size:11px; color:var(--ma-text-2);">{{ fmtTime(progress) }}</span>
                                <span style="font-family:'DM Mono',monospace; font-size:11px; color:var(--ma-text-2);">{{ fmtTime(duration) }}</span>
                            </div>
                        </div>

                        <!-- Play/Pause + Skip -->
                        <div style="display:flex; align-items:center; justify-content:center; gap:32px; margin-bottom:24px;">
                            <button type="button" @click="skip(-15)"
                                style="background:none; border:none; cursor:pointer; color:var(--ma-text-2); display:flex; flex-direction:column; align-items:center; gap:2px;">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 4v6h6M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>
                                <span style="font-family:'DM Mono',monospace; font-size:9px;">15</span>
                            </button>
                            <button type="button" @click="togglePlay"
                                style="width:68px; height:68px; border-radius:50%; border:none; cursor:pointer; display:flex; align-items:center; justify-content:center; box-shadow:0 0 24px rgba(111,207,0,0.25);"
                                :style="{ background: 'var(--ma-primary)' }">
                                <svg v-if="!isPlaying" width="28" height="28" viewBox="0 0 24 24" :fill="'var(--ma-on-primary)'" style="margin-left:3px;"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                                <svg v-else width="28" height="28" viewBox="0 0 24 24" :fill="'var(--ma-on-primary)'"><rect x="6" y="4" width="4" height="16" rx="1"/><rect x="14" y="4" width="4" height="16" rx="1"/></svg>
                            </button>
                            <button type="button" @click="skip(15)"
                                style="background:none; border:none; cursor:pointer; color:var(--ma-text-2); display:flex; flex-direction:column; align-items:center; gap:2px;">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 4v6h-6M20.49 15a9 9 0 1 1-2.13-9.36L23 10"/></svg>
                                <span style="font-family:'DM Mono',monospace; font-size:9px;">15</span>
                            </button>
                        </div>

                        <!-- Velocidade -->
                        <div style="display:flex; justify-content:center;">
                            <div style="display:flex; gap:4px; background:var(--ma-surface); border-radius:8px; padding:3px; border:1px solid var(--ma-border);">
                                <button v-for="s in [0.5, 1, 1.5, 2]" :key="s" type="button" @click="setSpeed(s)"
                                    style="padding:6px 14px; border-radius:6px; border:none; font-family:'DM Mono',monospace; font-size:11px; cursor:pointer; transition:all 0.15s;"
                                    :style="speed === s
                                        ? { background: 'var(--ma-surface-2)', color: 'var(--ma-text)', fontWeight: '600' }
                                        : { background: 'transparent', color: 'var(--ma-text-2)' }">
                                    {{ s }}x
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<style>
/* ── Fundo escuro global no member area ── */
/* Sobrescreve bg-white e bg-zinc-* dentro do ma-root para usar as variáveis do sistema */
#ma-root .bg-white {
    background-color: var(--ma-surface) !important;
}
#ma-root .bg-zinc-50,
#ma-root .bg-zinc-100 {
    background-color: var(--ma-bg) !important;
}
#ma-root .border-zinc-200,
#ma-root .border-zinc-100 {
    border-color: var(--ma-border) !important;
}
#ma-root .text-zinc-900 {
    color: var(--ma-text) !important;
}
#ma-root .text-zinc-700,
#ma-root .text-zinc-600 {
    color: var(--ma-text-2) !important;
}
/* Hover rows que ficavam claros */
#ma-root .hover\:bg-zinc-50:hover,
#ma-root .hover\:bg-zinc-100:hover {
    background-color: rgba(255,255,255,0.05) !important;
}

/* ── Botão flutuante de chat IA ── */
.chat-fab {
    position: fixed;
    bottom: 100px; /* acima do bottom nav mobile (80px) */
    right: 20px;
    z-index: 29;
    width: 52px;
    height: 52px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
    transition: transform 0.15s, box-shadow 0.15s;
    text-decoration: none;
}
.chat-fab:hover {
    transform: scale(1.08);
    box-shadow: 0 6px 28px rgba(0, 0, 0, 0.5);
}
.chat-fab__icon {
    width: 26px;
    height: 26px;
    color: var(--ma-on-primary);
}
@media (min-width: 1024px) {
    .chat-fab {
        bottom: 32px; /* desktop: sem bottom nav */
    }
}

/* ══════════════════════════════════════════════
   BOTTOM SHEET — overlay + animações
   ══════════════════════════════════════════════ */
.bs-overlay {
    position: fixed;
    inset: 0;
    z-index: 45;
}

.bs__backdrop {
    position: absolute;
    inset: 0;
    background: rgba(6, 13, 20, 0.88);
    backdrop-filter: blur(8px);
}

.bs__panel {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: var(--ma-surface);
    border-radius: 24px 24px 0 0;
    border-top: 1px solid var(--ma-border);
    max-height: 88vh;
    overflow-y: auto;
    overscroll-behavior: contain;
    box-shadow: 0 -8px 48px rgba(0, 0, 0, 0.55);
}

/* Transição: backdrop fades, panel slides up */
.bs-enter-active .bs__backdrop { transition: opacity 0.3s ease; }
.bs-leave-active .bs__backdrop { transition: opacity 0.22s ease-in; }
.bs-enter-from .bs__backdrop   { opacity: 0; }
.bs-leave-to   .bs__backdrop   { opacity: 0; }

.bs-enter-active .bs__panel { transition: transform 0.36s cubic-bezier(0.32, 0.72, 0, 1); }
.bs-leave-active .bs__panel { transition: transform 0.26s cubic-bezier(0.68, -0.06, 0.72, 0.32); }
.bs-enter-from   .bs__panel { transform: translateY(100%); }
.bs-leave-to     .bs__panel { transform: translateY(100%); }

/* Handle */
.bs__handle {
    display: flex;
    justify-content: center;
    padding: 14px 0 10px;
    position: sticky;
    top: 0;
    background: var(--ma-surface);
    z-index: 1;
    border-radius: 24px 24px 0 0;
}
.bs__handle-bar {
    width: 40px;
    height: 4px;
    border-radius: 2px;
    background: rgba(255, 255, 255, 0.18);
}

/* Perfil */
.bs__user {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 4px 20px 20px;
}
.bs__user-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    font-weight: 800;
    flex-shrink: 0;
}
.bs__user-info { min-width: 0; flex: 1; }
.bs__user-name {
    font-family: 'Cormorant Garamond', Georgia, serif;
    font-size: 18px;
    font-weight: 800;
    color: var(--ma-text);
    margin: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.bs__user-email {
    font-family: 'DM Mono', monospace;
    font-size: 11px;
    color: var(--ma-text-2);
    margin: 3px 0 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* Seções */
.bs__section { padding: 4px 10px; }
.bs__section-label {
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--ma-text-2);
    opacity: 0.55;
    padding: 4px 14px 2px;
    margin: 0;
}
.bs__divider {
    height: 1px;
    background: var(--ma-border);
    margin: 6px 16px;
}
.bs__safe {
    height: calc(20px + env(safe-area-inset-bottom, 0px));
}

/* ── Itens do menu bottom sheet ── */
.em-item {
    display: flex;
    align-items: center;
    gap: 14px;
    border-radius: 12px;
    padding: 12px 14px;
    text-decoration: none;
    font-family: 'Cormorant Garamond', Georgia, serif;
    font-size: 17px;
    font-weight: 500;
    color: var(--ma-text-2);
    transition: background 0.12s, color 0.12s;
    background: none;
    border: none;
    cursor: pointer;
    width: 100%;
    text-align: left;
    -webkit-tap-highlight-color: transparent;
}
.em-item:hover,
.em-item:active {
    background: rgba(255, 255, 255, 0.07);
    color: var(--ma-text);
}
.em-item--danger { color: var(--ma-danger); }
.em-item--danger:hover,
.em-item--danger:active { background: rgba(224, 85, 85, 0.08); color: var(--ma-danger); }

.em-icon {
    width: 20px;
    height: 20px;
    flex-shrink: 0;
    opacity: 0.7;
    transition: opacity 0.12s;
}
.em-item:hover .em-icon,
.em-item:active .em-icon { opacity: 1; }
.em-item--danger .em-icon { opacity: 0.8; }

.em-badge {
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
    font-family: 'DM Mono', monospace;
}

/* ── Botão primário global com suporte a hover configurável ── */
#ma-root .ma-btn-primary {
    background-color: var(--ma-primary);
    color: var(--ma-on-primary);
}
#ma-root .ma-btn-primary:hover {
    background-color: var(--ma-primary-hover);
    color: var(--ma-on-primary-hover);
    opacity: 1;
}
</style>
