<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import LayoutInfoprodutor from '@/Layouts/LayoutInfoprodutor.vue';
import {
    LayoutDashboard, TrendingUp, Users, MousePointerClick,
    Package, RefreshCw, Download, ArrowLeft
} from 'lucide-vue-next';

defineOptions({ layout: LayoutInfoprodutor });

const page = usePage();
const currentUrl = computed(() => page.url);

const navItems = [
    { href: '/relatorios',             label: 'Visão Geral',       icon: LayoutDashboard   },
    { href: '/relatorios/vendas',      label: 'Vendas & Receita',  icon: TrendingUp        },
    { href: '/relatorios/alunos',      label: 'Alunos',            icon: Users             },
    { href: '/relatorios/conversao',   label: 'Conversão & Funil', icon: MousePointerClick },
    { href: '/relatorios/produtos',    label: 'Produtos',          icon: Package           },
    { href: '/relatorios/assinaturas', label: 'Assinaturas',       icon: RefreshCw         },
    { href: '/relatorios/exportacoes', label: 'Exportações',       icon: Download          },
];

const currentItem = computed(() =>
    navItems.find(item => {
        if (item.href === '/relatorios') return currentUrl.value === '/relatorios';
        return currentUrl.value.startsWith(item.href);
    })
);

function isActive(href) {
    if (href === '/relatorios') return currentUrl.value === '/relatorios';
    return currentUrl.value.startsWith(href);
}
</script>

<template>
    <div class="flex gap-6 pt-2 lg:gap-8">
        <!-- Submenu lateral -->
        <aside class="w-52 shrink-0">
            <div class="sticky top-4 overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-800/60">
                <!-- Botão Voltar -->
                <div class="border-b border-zinc-100 p-3 dark:border-zinc-700">
                    <Link href="/dashboard"
                        class="flex w-full items-center gap-2 rounded-xl px-3 py-2 text-sm font-medium text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-700 dark:hover:text-zinc-200">
                        <ArrowLeft class="h-4 w-4 shrink-0" />
                        Voltar ao Dashboard
                    </Link>
                </div>

                <!-- Label da seção -->
                <div class="px-4 pb-1 pt-3">
                    <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">
                        Relatórios
                    </p>
                </div>

                <!-- Itens de navegação -->
                <nav class="p-2 space-y-0.5">
                    <Link
                        v-for="item in navItems"
                        :key="item.href"
                        :href="item.href"
                        class="flex items-center gap-2.5 rounded-xl px-3 py-2.5 text-sm font-medium transition"
                        :class="isActive(item.href)
                            ? 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900'
                            : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-700 dark:hover:text-zinc-200'"
                    >
                        <component :is="item.icon" class="h-4 w-4 shrink-0" />
                        <span>{{ item.label }}</span>
                    </Link>
                </nav>
            </div>
        </aside>

        <!-- Conteúdo da página -->
        <main class="min-w-0 flex-1">
            <slot />
        </main>
    </div>
</template>
