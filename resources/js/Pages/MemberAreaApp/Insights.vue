<script setup>
import { ref } from 'vue';
import MemberAreaAppLayout from '@/Layouts/MemberAreaAppLayout.vue';
import {
    Sparkles, Star, AlertTriangle, TrendingUp,
    CheckCircle2, X
} from 'lucide-vue-next';

defineOptions({ layout: MemberAreaAppLayout });

const props = defineProps({
    product:  { type: Object, required: true },
    insights: { type: Array, default: () => [] },
    base_url: { type: String, default: '' },
    slug:     { type: String, default: '' },
});

const localInsights = ref([...props.insights]);

const typeConfig = {
    recommendation: { label: 'Recomendação', icon: Star,          color: 'bg-blue-100 text-blue-700 border-blue-200 dark:bg-blue-900/20 dark:text-blue-300 dark:border-blue-800/40', accent: 'border-l-blue-400' },
    warning:        { label: 'Atenção',       icon: AlertTriangle, color: 'bg-amber-100 text-amber-700 border-amber-200 dark:bg-amber-900/20 dark:text-amber-300 dark:border-amber-800/40', accent: 'border-l-amber-400' },
    opportunity:    { label: 'Oportunidade',  icon: TrendingUp,    color: 'bg-emerald-100 text-emerald-700 border-emerald-200 dark:bg-emerald-900/20 dark:text-emerald-300 dark:border-emerald-800/40', accent: 'border-l-emerald-400' },
    milestone:      { label: 'Marco',         icon: CheckCircle2,  color: 'bg-purple-100 text-purple-700 border-purple-200 dark:bg-purple-900/20 dark:text-purple-300 dark:border-purple-800/40', accent: 'border-l-purple-400' },
};
function tc(type) { return typeConfig[type] ?? typeConfig.recommendation; }

function csrfToken() { return document.querySelector('meta[name="csrf-token"]')?.content ?? ''; }

async function dismiss(insight) {
    try {
        await window.axios.put(`${props.base_url}/insights/${insight.id}/dispensar`, {}, {
            headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
        });
        localInsights.value = localInsights.value.filter(i => i.id !== insight.id);
    } catch { /* silencioso */ }
}

const expanded = ref({});
function toggle(id) { expanded.value[id] = !expanded.value[id]; }
</script>

<template>
    <div class="mx-auto max-w-2xl space-y-6 px-4 py-8">
        <!-- Header -->
        <div class="flex items-center gap-3">
            <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-purple-100 dark:bg-purple-900/30">
                <Sparkles class="h-6 w-6 text-purple-600 dark:text-purple-400" />
            </div>
            <div>
                <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Insights Personalizados</h1>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Recomendações e observações personalizadas para você.</p>
            </div>
        </div>

        <!-- Vazio -->
        <div v-if="!localInsights.length"
            class="flex flex-col items-center gap-4 rounded-2xl border-2 border-dashed border-zinc-200 py-20 text-center dark:border-zinc-700">
            <Sparkles class="h-12 w-12 text-zinc-200 dark:text-zinc-700" />
            <div>
                <p class="font-semibold text-zinc-600 dark:text-zinc-300">Nenhum insight disponível</p>
                <p class="text-sm text-zinc-400">Os insights aparecerão aqui à medida que você evolui na plataforma.</p>
            </div>
        </div>

        <!-- Cards de insights -->
        <div v-else class="space-y-3">
            <div v-for="i in localInsights" :key="i.id"
                class="relative overflow-hidden rounded-2xl border bg-white dark:bg-zinc-800/60"
                :class="['border-l-4', tc(i.type).accent, 'border-zinc-200 dark:border-zinc-700']">

                <div class="p-4">
                    <div class="flex items-start gap-3">
                        <!-- Ícone tipo -->
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl"
                            :class="tc(i.type).color.split(' ').slice(0, 2).join(' ')">
                            <component :is="tc(i.type).icon" class="h-4 w-4" />
                        </div>

                        <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <span class="inline-block rounded-full border px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide mb-1"
                                        :class="tc(i.type).color">
                                        {{ tc(i.type).label }}
                                    </span>
                                    <h3 class="font-semibold text-sm text-zinc-900 dark:text-white">{{ i.title }}</h3>
                                </div>
                                <button type="button"
                                    class="shrink-0 rounded-lg p-1 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-600 dark:hover:bg-zinc-700"
                                    title="Dispensar" @click="dismiss(i)">
                                    <X class="h-3.5 w-3.5" />
                                </button>
                            </div>

                            <!-- Conteúdo (expandível) -->
                            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300"
                                :class="!expanded[i.id] && i.content.length > 180 ? 'line-clamp-3' : ''">
                                {{ i.content }}
                            </p>
                            <button v-if="i.content.length > 180" type="button"
                                class="mt-1 text-xs font-medium text-purple-600 hover:underline dark:text-purple-400"
                                @click="toggle(i.id)">
                                {{ expanded[i.id] ? 'Mostrar menos' : 'Ler mais' }}
                            </button>

                            <p class="mt-2 text-[10px] text-zinc-400">{{ i.created_at }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
