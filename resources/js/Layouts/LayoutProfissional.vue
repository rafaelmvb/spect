<script setup>
import { ref, computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import {
    LayoutDashboard, User, CalendarDays, Clock, Star,
    Briefcase, Image, LogOut, Menu, X, ChevronRight, DollarSign,
    ClipboardList, Users,
} from 'lucide-vue-next';

const page   = usePage();
const user   = computed(() => page.props.auth?.user);
const open   = ref(false);

const nav = [
    { label: 'Dashboard',       href: '/p/dashboard',       icon: LayoutDashboard },
    { label: 'Meu Perfil',      href: '/p/perfil',          icon: User },
    { label: 'Agenda',          href: '/p/agenda',          icon: CalendarDays },
    { label: 'Disponibilidade', href: '/p/disponibilidade', icon: Clock },
    { label: 'Serviços',        href: '/p/servicos',        icon: Briefcase },
    { label: 'Portfólio',       href: '/p/portfolio',       icon: Image },
    { label: 'Avaliações',      href: '/p/avaliacoes',      icon: Star },
    { label: 'Financeiro',      href: '/p/financeiro',      icon: DollarSign },
    { label: 'Triagens e Testes', href: '/p/triagens',      icon: ClipboardList },
    { label: 'Meus Pacientes',  href: '/p/meus-pacientes',  icon: Users },
];

function isActive(href) {
    return page.url.startsWith(href);
}
</script>

<template>
    <!-- O fundo vem da paleta clinica (.area-profissional), nao do Tailwind:
         bg-zinc-50 sobrescreveria o token por vir depois na cascata. -->
    <div class="area-profissional flex min-h-screen">
        <!-- Sidebar desktop -->
        <aside class="hidden lg:flex lg:w-64 lg:flex-col lg:fixed lg:inset-y-0 bg-white dark:bg-zinc-900 border-r border-zinc-200 dark:border-zinc-800 z-30">
            <!-- Logo / Cabeçalho -->
            <div class="flex items-center gap-3 px-5 py-5 border-b border-zinc-100 dark:border-zinc-800">
                <div class="h-9 w-9 rounded-xl bg-violet-600 flex items-center justify-center">
                    <User class="h-5 w-5 text-white" />
                </div>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-zinc-900 dark:text-white truncate">{{ user?.name }}</p>
                    <p class="text-xs text-violet-600 dark:text-violet-400">Profissional</p>
                </div>
            </div>

            <!-- Nav -->
            <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1">
                <Link
                    v-for="item in nav" :key="item.href"
                    :href="item.href"
                    class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors"
                    :class="isActive(item.href)
                        ? 'bg-violet-50 text-violet-700 dark:bg-violet-900/20 dark:text-violet-300'
                        : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white'"
                >
                    <component :is="item.icon" class="h-4 w-4 shrink-0" />
                    {{ item.label }}
                </Link>
            </nav>

            <!-- Logout -->
            <div class="px-3 py-4 border-t border-zinc-100 dark:border-zinc-800">
                <Link
                    href="/logout" method="post" as="button"
                    class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white transition-colors"
                >
                    <LogOut class="h-4 w-4" /> Sair
                </Link>
            </div>
        </aside>

        <!-- Mobile overlay -->
        <div v-if="open" class="fixed inset-0 z-40 bg-black/40 lg:hidden" @click="open = false" />

        <!-- Sidebar mobile -->
        <aside
            class="fixed inset-y-0 left-0 z-50 w-72 bg-white dark:bg-zinc-900 border-r border-zinc-200 dark:border-zinc-800 flex flex-col transition-transform lg:hidden"
            :class="open ? 'translate-x-0' : '-translate-x-full'"
        >
            <div class="flex items-center justify-between px-5 py-4 border-b border-zinc-100 dark:border-zinc-800">
                <div class="flex items-center gap-2">
                    <div class="h-8 w-8 rounded-xl bg-violet-600 flex items-center justify-center">
                        <User class="h-4 w-4 text-white" />
                    </div>
                    <span class="text-sm font-semibold text-zinc-900 dark:text-white">{{ user?.name }}</span>
                </div>
                <button @click="open = false" class="rounded-lg p-1 text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800">
                    <X class="h-5 w-5" />
                </button>
            </div>
            <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1">
                <Link
                    v-for="item in nav" :key="item.href"
                    :href="item.href"
                    @click="open = false"
                    class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors"
                    :class="isActive(item.href)
                        ? 'bg-violet-50 text-violet-700 dark:bg-violet-900/20 dark:text-violet-300'
                        : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white'"
                >
                    <component :is="item.icon" class="h-4 w-4 shrink-0" />
                    {{ item.label }}
                </Link>
            </nav>
            <div class="px-3 py-4 border-t border-zinc-100 dark:border-zinc-800">
                <Link href="/logout" method="post" as="button"
                    class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors"
                >
                    <LogOut class="h-4 w-4" /> Sair
                </Link>
            </div>
        </aside>

        <!-- Conteúdo principal -->
        <div class="flex-1 flex flex-col lg:pl-64">
            <!-- Header mobile -->
            <header class="lg:hidden sticky top-0 z-20 bg-white dark:bg-zinc-900 border-b border-zinc-200 dark:border-zinc-800 flex items-center gap-3 px-4 py-3">
                <button @click="open = true" class="rounded-lg p-1.5 text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-800">
                    <Menu class="h-5 w-5" />
                </button>
                <span class="text-sm font-semibold text-zinc-900 dark:text-white">Painel Profissional</span>
            </header>

            <main class="flex-1 p-4 md:p-6 lg:p-8">
                <slot />
            </main>
        </div>
    </div>
</template>
