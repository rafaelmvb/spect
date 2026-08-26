<script setup>
import LayoutProfissional from '@/Layouts/LayoutProfissional.vue';
import { CalendarDays, CheckCircle2, Clock, Star, Briefcase, TrendingUp } from 'lucide-vue-next';

defineOptions({ layout: LayoutProfissional });

const props = defineProps({
    professional:   { type: Object, required: true },
    upcoming:       { type: Array, default: () => [] },
    stats:          { type: Object, default: () => ({}) },
    recent_reviews: { type: Array, default: () => [] },
});

const STATUS_COLORS = {
    pending:   'bg-amber-100 text-amber-700 dark:bg-amber-900/20 dark:text-amber-300',
    confirmed: 'bg-blue-100 text-blue-700 dark:bg-blue-900/20 dark:text-blue-300',
    completed: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-300',
    cancelled: 'bg-red-100 text-red-700 dark:bg-red-900/20 dark:text-red-300',
    no_show:   'bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400',
};
</script>

<template>
    <div class="space-y-6">
        <!-- Header -->
        <div>
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Olá, {{ professional.name.split(' ')[0] }} 👋</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">Aqui está um resumo da sua agenda.</p>
        </div>

        <!-- Stats cards -->
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
            <div class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60 p-4 space-y-1">
                <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wide">Agendamentos</p>
                <p class="text-2xl font-bold text-violet-600">{{ stats.appointments_month ?? 0 }}</p>
                <p class="text-xs text-zinc-400">este mês</p>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60 p-4 space-y-1">
                <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wide">Realizados</p>
                <p class="text-2xl font-bold text-emerald-600">{{ stats.completed_month ?? 0 }}</p>
                <p class="text-xs text-zinc-400">este mês</p>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60 p-4 space-y-1">
                <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wide">Pendentes</p>
                <p class="text-2xl font-bold text-amber-600">{{ stats.pending ?? 0 }}</p>
                <p class="text-xs text-zinc-400">aguardando</p>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60 p-4 space-y-1">
                <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wide">Avaliação</p>
                <p class="text-2xl font-bold text-amber-500">{{ stats.avg_rating ?? '—' }}</p>
                <p class="text-xs text-zinc-400">média ({{ stats.total_reviews ?? 0 }})</p>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60 p-4 space-y-1">
                <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wide">Serviços</p>
                <p class="text-2xl font-bold text-blue-600">{{ stats.services ?? 0 }}</p>
                <p class="text-xs text-zinc-400">ativos</p>
            </div>
        </div>

        <!-- Grid: próximos agendamentos + avaliações recentes -->
        <div class="grid lg:grid-cols-2 gap-6">
            <!-- Próximos agendamentos -->
            <div class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60">
                <div class="flex items-center justify-between px-5 py-4 border-b border-zinc-100 dark:border-zinc-700">
                    <div class="flex items-center gap-2">
                        <CalendarDays class="h-4 w-4 text-violet-600" />
                        <h2 class="font-semibold text-zinc-900 dark:text-white text-sm">Próximos agendamentos</h2>
                    </div>
                    <a href="/p/agenda" class="text-xs text-violet-600 hover:underline dark:text-violet-400">Ver todos</a>
                </div>
                <div v-if="upcoming.length" class="divide-y divide-zinc-100 dark:divide-zinc-700">
                    <div v-for="a in upcoming" :key="a.id" class="flex items-start gap-3 px-5 py-3">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-zinc-900 dark:text-white truncate">{{ a.client_name }}</p>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                {{ new Date(a.scheduled_date + 'T00:00:00').toLocaleDateString('pt-BR') }} às {{ a.scheduled_time }}
                            </p>
                        </div>
                        <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold" :class="STATUS_COLORS[a.status]">
                            {{ a.status_label }}
                        </span>
                    </div>
                </div>
                <div v-else class="flex flex-col items-center gap-2 py-10 text-center px-5">
                    <CalendarDays class="h-8 w-8 text-zinc-200 dark:text-zinc-700" />
                    <p class="text-sm text-zinc-400">Nenhum agendamento futuro.</p>
                </div>
            </div>

            <!-- Avaliações recentes -->
            <div class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60">
                <div class="flex items-center justify-between px-5 py-4 border-b border-zinc-100 dark:border-zinc-700">
                    <div class="flex items-center gap-2">
                        <Star class="h-4 w-4 text-amber-500" />
                        <h2 class="font-semibold text-zinc-900 dark:text-white text-sm">Avaliações recentes</h2>
                    </div>
                    <a href="/p/avaliacoes" class="text-xs text-violet-600 hover:underline dark:text-violet-400">Ver todas</a>
                </div>
                <div v-if="recent_reviews.length" class="divide-y divide-zinc-100 dark:divide-zinc-700">
                    <div v-for="r in recent_reviews" :key="r.id" class="px-5 py-3">
                        <div class="flex items-center justify-between mb-1">
                            <p class="text-sm font-medium text-zinc-900 dark:text-white">{{ r.user_name }}</p>
                            <div class="flex gap-0.5">
                                <Star v-for="n in 5" :key="n" class="h-3 w-3" :class="n <= r.rating ? 'text-amber-400 fill-amber-400' : 'text-zinc-200 dark:text-zinc-700'" />
                            </div>
                        </div>
                        <p v-if="r.comment" class="text-xs text-zinc-500 dark:text-zinc-400 line-clamp-2">{{ r.comment }}</p>
                        <p class="text-[10px] text-zinc-400 mt-1">{{ r.created_at }}</p>
                    </div>
                </div>
                <div v-else class="flex flex-col items-center gap-2 py-10 text-center px-5">
                    <Star class="h-8 w-8 text-zinc-200 dark:text-zinc-700" />
                    <p class="text-sm text-zinc-400">Nenhuma avaliação ainda.</p>
                </div>
            </div>
        </div>
    </div>
</template>
