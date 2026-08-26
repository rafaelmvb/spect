<script setup>
import { ref } from 'vue';
import LayoutProfissional from '@/Layouts/LayoutProfissional.vue';
import { Star, MessageSquare, ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { router } from '@inertiajs/vue3';

defineOptions({ layout: LayoutProfissional });

const props = defineProps({
    reviews:      { type: Object, required: true },
    stats:        { type: Object, default: () => ({}) },
    distribution: { type: Object, default: () => ({}) },
});

function goPage(url) {
    if (! url) return;
    router.visit(url, { preserveScroll: true });
}

const maxCount = ref(Math.max(...Object.values(props.distribution).map(Number), 1));
</script>

<template>
    <div class="space-y-6">
        <!-- Header -->
        <div>
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Avaliações</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">Feedback dos seus clientes.</p>
        </div>

        <!-- Stats + distribuição -->
        <div class="grid lg:grid-cols-3 gap-4">
            <!-- Média geral -->
            <div class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60 p-5 flex flex-col items-center justify-center text-center">
                <p class="text-5xl font-bold text-amber-500">{{ stats.avg_rating ?? '—' }}</p>
                <div class="flex gap-0.5 mt-2">
                    <Star v-for="n in 5" :key="n" class="h-5 w-5"
                        :class="n <= Math.round(stats.avg_rating) ? 'text-amber-400 fill-amber-400' : 'text-zinc-200 dark:text-zinc-700'" />
                </div>
                <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-2">{{ stats.total ?? 0 }} avaliações</p>
            </div>

            <!-- Distribuição por estrela -->
            <div class="lg:col-span-2 rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60 p-5 space-y-2">
                <p class="text-sm font-semibold text-zinc-700 dark:text-zinc-200 mb-3">Distribuição</p>
                <div v-for="n in [5,4,3,2,1]" :key="n" class="flex items-center gap-3">
                    <div class="flex items-center gap-0.5 w-16 shrink-0">
                        <span class="text-xs text-zinc-500 w-2 text-right">{{ n }}</span>
                        <Star class="h-3 w-3 text-amber-400 fill-amber-400" />
                    </div>
                    <div class="flex-1 bg-zinc-100 dark:bg-zinc-700 rounded-full h-2">
                        <div class="h-2 rounded-full bg-amber-400 transition-all"
                            :style="`width:${maxCount > 0 ? ((distribution[n] ?? 0) / maxCount) * 100 : 0}%`" />
                    </div>
                    <span class="text-xs text-zinc-500 w-6 text-right">{{ distribution[n] ?? 0 }}</span>
                </div>
            </div>
        </div>

        <!-- Lista de avaliações -->
        <div class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60 overflow-hidden">
            <div v-if="reviews.data?.length" class="divide-y divide-zinc-100 dark:divide-zinc-700">
                <div v-for="r in reviews.data" :key="r.id" class="p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-medium text-zinc-900 dark:text-white">{{ r.user_name }}</p>
                            <p class="text-xs text-zinc-400 mt-0.5">{{ r.created_at }}</p>
                        </div>
                        <div class="flex gap-0.5 shrink-0">
                            <Star v-for="n in 5" :key="n" class="h-4 w-4"
                                :class="n <= r.rating ? 'text-amber-400 fill-amber-400' : 'text-zinc-200 dark:text-zinc-700'" />
                        </div>
                    </div>
                    <p v-if="r.comment" class="mt-3 text-sm text-zinc-600 dark:text-zinc-300 leading-relaxed">{{ r.comment }}</p>
                    <p v-else class="mt-3 text-sm italic text-zinc-400">Sem comentário.</p>
                </div>
            </div>
            <div v-else class="flex flex-col items-center gap-3 py-16 text-center">
                <MessageSquare class="h-12 w-12 text-zinc-200 dark:text-zinc-700" />
                <p class="text-zinc-500 dark:text-zinc-400">Nenhuma avaliação ainda.</p>
            </div>

            <!-- Paginação -->
            <div v-if="reviews.last_page > 1" class="flex items-center justify-between px-5 py-4 border-t border-zinc-100 dark:border-zinc-800">
                <p class="text-xs text-zinc-500">
                    Mostrando {{ reviews.from }}–{{ reviews.to }} de {{ reviews.total }}
                </p>
                <div class="flex gap-2">
                    <button @click="goPage(reviews.prev_page_url)" :disabled="!reviews.prev_page_url"
                        class="rounded-lg p-1.5 border border-zinc-200 dark:border-zinc-700 text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800 disabled:opacity-40">
                        <ChevronLeft class="h-4 w-4" />
                    </button>
                    <span class="flex items-center px-3 text-xs text-zinc-600 dark:text-zinc-300">
                        {{ reviews.current_page }} / {{ reviews.last_page }}
                    </span>
                    <button @click="goPage(reviews.next_page_url)" :disabled="!reviews.next_page_url"
                        class="rounded-lg p-1.5 border border-zinc-200 dark:border-zinc-700 text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800 disabled:opacity-40">
                        <ChevronRight class="h-4 w-4" />
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
