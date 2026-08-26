<script setup>
import { ref, computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, BookOpen, Award, Play, CalendarDays, Flame, Smile, Frown, Meh, SmilePlus, HeartCrack } from 'lucide-vue-next';
import MemberAreaAppLayout from '@/Layouts/MemberAreaAppLayout.vue';
import { useMemberBase } from '@/composables/useMemberBase';

defineOptions({ layout: MemberAreaAppLayout });

const props = defineProps({
    product:               { type: Object,  required: true },
    config:                { type: Object,  default: () => ({}) },
    sections:              { type: Array,   default: () => [] },
    progress_percent:      { type: Number,  default: 0 },
    continue_watching:     { type: Array,   default: () => [] },
    internal_products:     { type: Array,   default: () => [] },
    community_enabled:     { type: Boolean, default: false },
    certificate_enabled:   { type: Boolean, default: false },
    can_issue_certificate: { type: Boolean, default: false },
    base_url:              { type: String,  default: '' },
    slug:                  { type: String,  required: true },
    push_enabled:          { type: Boolean, default: false },
    vapid_public:          { type: String,  default: null },
    banners:               { type: Array,   default: () => [] },
    home_posts:            { type: Array,   default: () => [] },
    featured_courses:      { type: Array,   default: () => [] },
    streak:                { type: Object,  default: () => ({ current_streak: 0, longest_streak: 0, today_mood: null, checkin_done: false }) },
});

const page       = usePage();
const memberBase = useMemberBase(computed(() => props.slug));

const firstName = computed(() => {
    const name = page.props?.auth?.user?.name ?? '';
    return name.trim().split(/\s+/)[0] || 'aluno';
});

const firstItem = computed(() => props.continue_watching?.[0] ?? null);
const continueHref = computed(() => {
    const item = firstItem.value;
    if (!item) return `${memberBase.value}/modulos`;
    return item.module_id
        ? `${memberBase.value}/modulo/${item.module_id}?aula=${item.lesson_id}`
        : `${memberBase.value}/aula/${item.lesson_id}`;
});

// ── Banner slider ──────────────────────────────────────────────
const bannerIdx = ref(0);
function prevBanner() { bannerIdx.value = (bannerIdx.value - 1 + props.banners.length) % props.banners.length; }
function nextBanner() { bannerIdx.value = (bannerIdx.value + 1) % props.banners.length; }

// ── Slider de cursos ──────────────────────────────────────────
const coursesSliderRef = ref(null);
function slideCourses(dir) { coursesSliderRef.value?.scrollBy({ left: 220 * 3 * dir, behavior: 'smooth' }); }

// ── Streak / Mood check-in ─────────────────────────────────────
const currentStreak  = ref(props.streak.current_streak);
const checkinDone    = ref(props.streak.checkin_done);
const todayMood      = ref(props.streak.today_mood);
const moodSending    = ref(false);

const MOODS = [
    { value: 1, label: 'Muito mal',  emoji: '😞' },
    { value: 2, label: 'Mal',        emoji: '😕' },
    { value: 3, label: 'Neutro',     emoji: '😐' },
    { value: 4, label: 'Bem',        emoji: '🙂' },
    { value: 5, label: 'Muito bem',  emoji: '😄' },
];

async function submitMood(moodValue) {
    if (moodSending.value) return;
    moodSending.value = true;
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
        const res = await fetch(`${memberBase.value}/humor/checkin`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify({ mood: moodValue }),
        });
        if (res.ok) {
            const data = await res.json();
            todayMood.value    = moodValue;
            checkinDone.value  = true;
            currentStreak.value = data.current_streak;
        }
    } finally {
        moodSending.value = false;
    }
}
</script>

<template>
    <div class="flex gap-8">

        <!-- ══════════════════════════════════════════════════════
             COLUNA PRINCIPAL
             Mobile: flex-col → ordem pelo DOM
             Desktop: flex-col com lg:order-N re-ordena seções
        ══════════════════════════════════════════════════════ -->
        <div class="min-w-0 flex-1 flex flex-col gap-8 lg:gap-10">

            <!-- ── [1 mobile] SAUDAÇÃO + SUA JORNADA ─────────── -->
            <section class="lg:hidden">

                <!-- Saudação -->
                <div class="mb-5">
                    <h1 class="text-2xl font-bold text-white leading-tight">Olá, {{ firstName }}! 👋</h1>
                    <p class="mt-1 text-sm text-zinc-400">Pronto para continuar hoje?</p>
                </div>

                <!-- ── Streak + Mood Check-in ─────────────────────── -->
                <div class="flex gap-3 mb-5">
                    <!-- Ofensiva de dias -->
                    <div class="flex-1 rounded-2xl border border-[var(--ma-border)] bg-[var(--ma-surface)] p-4 flex flex-col items-center justify-center gap-1">
                        <div class="flex items-center gap-1.5">
                            <Flame class="h-5 w-5" style="color:#f97316;" />
                            <span class="text-2xl font-extrabold text-white">{{ currentStreak }}</span>
                        </div>
                        <p class="text-[11px] font-semibold text-zinc-400 uppercase tracking-widest">dia{{ currentStreak !== 1 ? 's' : '' }} seguido{{ currentStreak !== 1 ? 's' : '' }}</p>
                    </div>

                    <!-- Humor do dia -->
                    <div class="flex-[2] rounded-2xl border border-[var(--ma-border)] bg-[var(--ma-surface)] p-4">
                        <p class="text-[11px] font-bold uppercase tracking-widest mb-3" style="color:var(--ma-primary);">
                            {{ checkinDone ? 'Seu humor hoje' : 'Como você está hoje?' }}
                        </p>
                        <!-- Já fez check-in: mostra o mood selecionado -->
                        <div v-if="checkinDone" class="flex items-center gap-2">
                            <span class="text-3xl">{{ MOODS.find(m => m.value === todayMood)?.emoji ?? '😐' }}</span>
                            <span class="text-sm font-semibold text-white">{{ MOODS.find(m => m.value === todayMood)?.label ?? '' }}</span>
                        </div>
                        <!-- Ainda não fez: botões de seleção -->
                        <div v-else class="flex gap-2 justify-between">
                            <button v-for="m in MOODS" :key="m.value" type="button"
                                :disabled="moodSending"
                                @click="submitMood(m.value)"
                                class="flex flex-col items-center gap-1 rounded-xl p-2 transition-all active:scale-90"
                                :style="{ background: 'var(--ma-bg,#090F18)', border: '1px solid var(--ma-border)', opacity: moodSending ? 0.5 : 1, cursor: moodSending ? 'not-allowed' : 'pointer' }">
                                <span class="text-xl leading-none">{{ m.emoji }}</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Card "Sua Jornada" (quando tem aula para continuar) -->
                <div v-if="firstItem" class="rounded-2xl border border-[var(--ma-border)] bg-[var(--ma-surface)] overflow-hidden">

                    <!-- Header: nome do curso + círculo de progresso -->
                    <div class="flex items-center justify-between gap-4 p-4 pb-3">
                        <div class="min-w-0">
                            <p class="text-[10px] font-bold uppercase tracking-widest mb-1" :style="{ color: 'var(--ma-primary)' }">
                                Sua Jornada
                            </p>
                            <h2 class="text-lg font-bold text-white leading-snug line-clamp-2">{{ product.name }}</h2>
                        </div>

                        <!-- Círculo de progresso SVG -->
                        <div class="relative flex h-[68px] w-[68px] shrink-0 items-center justify-center">
                            <svg class="h-full w-full -rotate-90" viewBox="0 0 36 36">
                                <circle cx="18" cy="18" r="15.9155" fill="none" stroke="rgba(255,255,255,0.08)" stroke-width="3.5"/>
                                <circle
                                    cx="18" cy="18" r="15.9155"
                                    fill="none"
                                    :stroke="'var(--ma-primary)'"
                                    stroke-width="3.5"
                                    stroke-linecap="round"
                                    :stroke-dasharray="`${Math.min(progress_percent, 100)} 100`"
                                    style="transition: stroke-dasharray 0.7s ease;"
                                />
                            </svg>
                            <span class="absolute text-[12px] font-bold text-white">{{ progress_percent }}%</span>
                        </div>
                    </div>

                    <!-- Aula atual -->
                    <Link :href="continueHref" class="flex items-center gap-3 mx-4 mb-3 rounded-xl border border-[var(--ma-border)] bg-[var(--ma-bg,#090F18)] p-3">
                        <div class="relative h-14 w-24 shrink-0 overflow-hidden rounded-lg bg-zinc-800">
                            <img v-if="firstItem.module_thumbnail" :src="firstItem.module_thumbnail" :alt="firstItem.title" class="h-full w-full object-cover" />
                            <div v-else class="flex h-full w-full items-center justify-center" :style="{ color: 'var(--ma-primary)' }">
                                <Play class="h-5 w-5" />
                            </div>
                            <!-- Play overlay -->
                            <div class="absolute inset-0 flex items-center justify-center bg-black/30">
                                <div class="flex h-7 w-7 items-center justify-center rounded-full bg-white/90">
                                    <Play class="h-3 w-3 fill-zinc-900 text-zinc-900" style="margin-left:1px;" />
                                </div>
                            </div>
                        </div>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-white">{{ firstItem.title }}</p>
                            <p v-if="firstItem.module_title" class="mt-0.5 truncate text-xs text-zinc-400">{{ firstItem.module_title }}</p>
                        </div>
                    </Link>

                    <!-- Botão continuar -->
                    <div class="px-4 pb-4">
                        <Link
                            :href="continueHref"
                            class="flex w-full items-center justify-center gap-2 rounded-xl py-3.5 text-sm font-bold uppercase tracking-wider text-zinc-900 transition hover:opacity-90 active:scale-[0.98]"
                            :style="{ background: 'var(--ma-primary)' }"
                        >
                            Continuar Trilha
                        </Link>
                    </div>
                </div>

                <!-- Fallback: barra de progresso simples (sem aula para continuar) -->
                <div v-else class="rounded-xl border border-[var(--ma-border)] bg-[var(--ma-surface)] p-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-[11px] font-semibold uppercase tracking-widest text-zinc-500">
                            <span :style="{ color: 'var(--ma-primary)' }">--</span> Meu Progresso
                        </h3>
                        <span class="text-sm font-bold text-white">{{ progress_percent }}%</span>
                    </div>
                    <div class="mt-3 h-2 overflow-hidden rounded-full bg-zinc-700">
                        <div class="h-full rounded-full transition-all duration-700" :style="{ width: `${Math.min(progress_percent, 100)}%`, background: 'var(--ma-primary)' }" />
                    </div>
                    <Link :href="`${memberBase}/modulos`" class="mt-3 flex w-full items-center justify-center rounded-xl py-3 text-sm font-bold uppercase tracking-wider transition active:scale-[0.98]" :style="{ background: 'var(--ma-primary)', color: '#0D1F2D' }">
                        {{ progress_percent > 0 ? 'Continuar Trilha' : 'Começar Trilha' }}
                    </Link>
                </div>
            </section>

            <!-- ── [2 mobile] AÇÕES RÁPIDAS (quando tem jornada ativa) ───── -->
            <section v-if="firstItem" class="lg:hidden -mt-4">
                <div class="flex gap-2">
                    <Link
                        :href="`${memberBase}/modulos`"
                        class="flex flex-1 items-center justify-center gap-1.5 rounded-xl border border-[var(--ma-border)] bg-[var(--ma-surface)] py-2.5 text-xs font-semibold text-zinc-300 transition hover:border-[var(--ma-primary)]/40"
                    >
                        <BookOpen class="h-4 w-4" /> Ver módulos
                    </Link>
                    <Link
                        v-if="certificate_enabled && can_issue_certificate"
                        :href="`${memberBase}/certificado`"
                        class="flex flex-1 items-center justify-center gap-1.5 rounded-xl border border-[var(--ma-border)] bg-[var(--ma-surface)] py-2.5 text-xs font-semibold transition"
                        :style="{ color: 'var(--ma-primary)', borderColor: 'var(--ma-primary)' }"
                    >
                        <Award class="h-4 w-4" /> Certificado
                    </Link>
                </div>
            </section>

            <!-- ── [3 mobile / 1 desktop] BANNERS ────────────── -->
            <section v-if="banners.length" class="relative overflow-hidden rounded-2xl lg:order-1">
                <div class="relative aspect-[21/8] w-full bg-zinc-900">
                    <template v-for="(banner, i) in banners" :key="banner.id">
                        <div :class="['absolute inset-0 transition-opacity duration-500', i === bannerIdx ? 'opacity-100' : 'opacity-0 pointer-events-none']">
                            <img v-if="banner.image_url" :src="banner.image_url" :alt="banner.title" class="h-full w-full object-cover" />
                            <div class="pointer-events-none absolute inset-x-0 bottom-0 h-2/3 bg-gradient-to-t from-black/80 to-transparent" />
                            <div v-if="banner.title || banner.subtitle || banner.link" class="absolute inset-x-0 bottom-0 p-6">
                                <p v-if="banner.title" class="text-2xl font-bold text-white drop-shadow-lg">{{ banner.title }}</p>
                                <p v-if="banner.subtitle" class="mt-1 text-sm text-white/80">{{ banner.subtitle }}</p>
                                <a v-if="banner.link" :href="banner.link" class="ma-btn-primary mt-3 inline-block rounded-lg px-4 py-2 text-sm font-semibold transition">{{ banner.button_text || 'Saiba mais' }}</a>
                            </div>
                        </div>
                    </template>
                </div>
                <button v-if="banners.length > 1" type="button" class="absolute left-2 top-1/2 -translate-y-1/2 rounded-full bg-black/50 p-2 text-white backdrop-blur-sm hover:bg-black/70" @click="prevBanner"><ChevronLeft class="h-5 w-5" /></button>
                <button v-if="banners.length > 1" type="button" class="absolute right-2 top-1/2 -translate-y-1/2 rounded-full bg-black/50 p-2 text-white backdrop-blur-sm hover:bg-black/70" @click="nextBanner"><ChevronRight class="h-5 w-5" /></button>
                <div v-if="banners.length > 1" class="absolute bottom-2 left-1/2 flex -translate-x-1/2 gap-1.5">
                    <button v-for="(_, i) in banners" :key="i" type="button" :class="['h-1.5 rounded-full transition-all', i === bannerIdx ? 'w-4 bg-white' : 'w-1.5 bg-white/40']" @click="bannerIdx = i" />
                </div>
            </section>

            <!-- ── [2 desktop only] CONTINUAR ASSISTINDO ─────── -->
            <section v-if="continue_watching?.length" class="hidden lg:block lg:order-2 space-y-4">
                <h2 class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-widest text-zinc-500">
                    <span :style="{ color: 'var(--ma-primary)' }">--</span> Continuar Assistindo
                </h2>
                <div class="grid gap-3 sm:grid-cols-2">
                    <Link
                        v-for="item in continue_watching.slice(0, 4)"
                        :key="item.lesson_id"
                        :href="item.module_id ? `${memberBase}/modulo/${item.module_id}?aula=${item.lesson_id}` : `${memberBase}/aula/${item.lesson_id}`"
                        class="flex items-center gap-3 rounded-xl border border-[var(--ma-border)] bg-[var(--ma-surface)] p-3 transition hover:border-[var(--ma-primary)]/40"
                    >
                        <div class="relative h-12 w-20 shrink-0 overflow-hidden rounded-lg bg-zinc-800">
                            <img v-if="item.module_thumbnail" :src="item.module_thumbnail" :alt="item.title" class="h-full w-full object-cover" />
                            <div v-else class="flex h-full w-full items-center justify-center" :style="{ color: 'var(--ma-primary)' }"><Play class="h-4 w-4" /></div>
                        </div>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-white">{{ item.title }}</p>
                            <p v-if="item.module_title" class="truncate text-xs text-zinc-500">{{ item.module_title }}</p>
                        </div>
                    </Link>
                </div>
            </section>

            <!-- ── [5 mobile / 3 desktop] TRILHAS EM DESTAQUE ── -->
            <section v-if="featured_courses.length" class="space-y-4 lg:order-3">
                <div class="flex items-center justify-between">
                    <h2 class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-widest text-zinc-500">
                        <span :style="{ color: 'var(--ma-primary)' }">--</span> Trilhas em Destaque
                    </h2>
                    <div class="flex gap-1">
                        <button type="button" class="rounded-lg p-1.5 text-zinc-500 transition hover:bg-zinc-800 hover:text-white" @click="slideCourses(-1)"><ChevronLeft class="h-5 w-5" /></button>
                        <button type="button" class="rounded-lg p-1.5 text-zinc-500 transition hover:bg-zinc-800 hover:text-white" @click="slideCourses(1)"><ChevronRight class="h-5 w-5" /></button>
                    </div>
                </div>
                <div ref="coursesSliderRef" class="no-scrollbar -mx-4 flex gap-3 overflow-x-auto scroll-smooth px-4 pb-2 snap-x snap-mandatory lg:mx-0 lg:px-0">
                    <a v-for="course in featured_courses" :key="course.id" :href="course.checkout_url" target="_blank" rel="noopener" class="group relative w-44 shrink-0 snap-start overflow-hidden rounded-2xl bg-zinc-900 last:mr-4 lg:last:mr-0">
                        <div class="aspect-[2/3] overflow-hidden bg-zinc-800">
                            <img v-if="course.image_url" :src="course.image_url" :alt="course.name" class="h-full w-full object-cover transition duration-300 group-hover:scale-105" />
                            <div v-else class="flex h-full w-full items-center justify-center text-zinc-700"><BookOpen class="h-10 w-10" /></div>
                        </div>
                        <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black via-black/70 to-transparent px-3 pb-3 pt-10">
                            <p class="line-clamp-2 text-[13px] font-bold uppercase leading-tight text-white">{{ course.name }}</p>
                            <p class="mt-1 text-[11px]" :style="{ color: 'var(--ma-primary)' }">Ver trilha</p>
                        </div>
                    </a>
                </div>
            </section>

            <!-- ── [6 mobile / 4 desktop] ÚLTIMAS NOVIDADES ──── -->
            <section v-if="home_posts.length" class="space-y-4 lg:order-4">
                <h2 class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-widest text-zinc-500">
                    <span :style="{ color: 'var(--ma-primary)' }">--</span> Últimas Novidades
                </h2>
                <Link v-if="home_posts[0]" :href="`${memberBase}/post/${home_posts[0].id}`" class="group flex flex-col overflow-hidden rounded-2xl border border-[var(--ma-border)] bg-[var(--ma-surface)] transition hover:border-[var(--ma-primary)]/50 sm:flex-row">
                    <div class="aspect-video w-full shrink-0 overflow-hidden bg-zinc-900 sm:w-[52%]">
                        <img v-if="home_posts[0].image_url" :src="home_posts[0].image_url" :alt="home_posts[0].title" class="h-full w-full object-cover transition duration-300 group-hover:scale-105" />
                        <div v-else class="flex h-full w-full items-center justify-center text-zinc-700"><BookOpen class="h-14 w-14" /></div>
                    </div>
                    <div class="flex flex-col justify-center gap-3 p-5">
                        <span v-if="home_posts[0].category" class="inline-block w-fit rounded px-2 py-0.5 text-[11px] font-bold uppercase tracking-wider" :style="{ background: 'var(--ma-primary)', color: 'var(--ma-on-primary)' }">{{ home_posts[0].category }}</span>
                        <h3 class="text-xl font-bold leading-snug text-white">{{ home_posts[0].title }}</h3>
                        <p v-if="home_posts[0].excerpt" class="text-sm text-zinc-400 line-clamp-3">{{ home_posts[0].excerpt }}</p>
                        <p v-if="home_posts[0].published_at" class="flex items-center gap-1 text-xs text-zinc-500"><CalendarDays class="h-3.5 w-3.5" /> {{ home_posts[0].published_at }}</p>
                    </div>
                </Link>
                <div v-if="home_posts.length > 1" class="grid gap-4 grid-cols-2 sm:grid-cols-3">
                    <Link v-for="post in home_posts.slice(1)" :key="post.id" :href="`${memberBase}/post/${post.id}`" class="group overflow-hidden rounded-xl border border-[var(--ma-border)] bg-[var(--ma-surface)] transition hover:border-[var(--ma-primary)]/50">
                        <div class="aspect-video overflow-hidden bg-zinc-900">
                            <img v-if="post.image_url" :src="post.image_url" :alt="post.title" class="h-full w-full object-cover transition duration-300 group-hover:scale-105" />
                            <div v-else class="flex h-full w-full items-center justify-center text-zinc-700"><BookOpen class="h-8 w-8" /></div>
                        </div>
                        <div class="p-3">
                            <span v-if="post.category" class="inline-block rounded px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wider" :style="{ background: 'rgba(111,207,0,0.12)', color: 'var(--ma-primary)' }">{{ post.category }}</span>
                            <p class="mt-1.5 line-clamp-2 text-sm font-bold text-white">{{ post.title }}</p>
                            <p v-if="post.excerpt" class="mt-1 line-clamp-2 text-xs text-zinc-500">{{ post.excerpt }}</p>
                            <p v-if="post.published_at" class="mt-1.5 text-[11px] text-zinc-600">{{ post.published_at }}</p>
                        </div>
                    </Link>
                </div>
            </section>

        </div>

        <!-- ── Sidebar desktop ────────────────────────────── -->
        <aside class="hidden lg:flex w-64 shrink-0 flex-col gap-5">

            <!-- Streak + Humor -->
            <div class="rounded-xl border border-[var(--ma-border)] bg-[var(--ma-surface)] p-4 space-y-4">
                <!-- Ofensiva -->
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-orange-500/10">
                        <Flame class="h-5 w-5" style="color:#f97316;" />
                    </div>
                    <div>
                        <p class="text-lg font-extrabold text-white leading-none">{{ currentStreak }}</p>
                        <p class="text-[11px] text-zinc-500 uppercase tracking-widest">dia{{ currentStreak !== 1 ? 's' : '' }} seguido{{ currentStreak !== 1 ? 's' : '' }}</p>
                    </div>
                </div>

                <div class="h-px bg-[var(--ma-border)]" />

                <!-- Humor -->
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-widest mb-3" style="color:var(--ma-primary);">
                        {{ checkinDone ? 'Seu humor hoje' : 'Como você está hoje?' }}
                    </p>
                    <div v-if="checkinDone" class="flex items-center gap-2">
                        <span class="text-2xl">{{ MOODS.find(m => m.value === todayMood)?.emoji ?? '😐' }}</span>
                        <span class="text-sm font-semibold text-white">{{ MOODS.find(m => m.value === todayMood)?.label ?? '' }}</span>
                    </div>
                    <div v-else class="flex gap-1.5 justify-between">
                        <button v-for="m in MOODS" :key="m.value" type="button"
                            :disabled="moodSending"
                            @click="submitMood(m.value)"
                            class="flex flex-col items-center rounded-xl p-2 transition-all hover:scale-110 active:scale-90"
                            :style="{ background: 'var(--ma-bg,#090F18)', border: '1px solid var(--ma-border)', opacity: moodSending ? 0.5 : 1, cursor: moodSending ? 'not-allowed' : 'pointer' }">
                            <span class="text-lg leading-none">{{ m.emoji }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Progresso -->
            <div class="rounded-xl border border-[var(--ma-border)] bg-[var(--ma-surface)] p-4">
                <h3 class="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-widest text-zinc-500">
                    <span :style="{ color: 'var(--ma-primary)' }">--</span> Meu Progresso
                </h3>
                <div class="mt-4">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-zinc-400">Conclusão</span>
                        <span class="font-bold text-white">{{ progress_percent }}%</span>
                    </div>
                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-zinc-700">
                        <div class="h-full rounded-full bg-[var(--ma-primary)] transition-all duration-500" :style="{ width: `${Math.min(progress_percent, 100)}%` }" />
                    </div>
                </div>
                <Link :href="`${memberBase}/modulos`" class="mt-4 flex w-full items-center justify-between rounded-lg px-3 py-2 text-sm text-zinc-400 transition hover:bg-zinc-800/60 hover:text-white">
                    Ver módulos <ChevronRight class="h-4 w-4" />
                </Link>
            </div>

            <!-- Certificado -->
            <div v-if="certificate_enabled" class="rounded-xl border border-[var(--ma-border)] bg-[var(--ma-surface)] p-4">
                <h3 class="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-widest text-zinc-500">
                    <span :style="{ color: 'var(--ma-primary)' }">--</span> Certificado
                </h3>
                <p class="mt-3 text-sm text-zinc-400">{{ can_issue_certificate ? 'Parabéns! Você concluiu a trilha.' : 'Conclua a trilha para emitir seu certificado.' }}</p>
                <Link v-if="can_issue_certificate" :href="`${memberBase}/certificado`" class="ma-btn-primary mt-3 flex w-full items-center justify-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition">
                    <Award class="h-4 w-4" /> Emitir certificado
                </Link>
                <div v-else class="mt-3 h-1.5 overflow-hidden rounded-full bg-zinc-700">
                    <div class="h-full rounded-full transition-all duration-500" :style="{ width: `${Math.min(progress_percent, 100)}%`, background: 'var(--ma-primary)', opacity: 0.5 }" />
                </div>
            </div>
        </aside>
    </div>
</template>
