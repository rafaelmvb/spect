<script setup>
import { ref, computed } from 'vue';
import MemberAreaAppLayout from '@/Layouts/MemberAreaAppLayout.vue';
import { Stethoscope, Search, Star, ChevronRight, UserCircle } from 'lucide-vue-next';
import { useMemberBase } from '@/composables/useMemberBase';

defineOptions({ layout: MemberAreaAppLayout });

const props = defineProps({
    product:       { type: Object, required: true },
    professionals: { type: Array, default: () => [] },
    base_url:      { type: String, default: '' },
    slug:          { type: String, default: '' },
});

const search = ref('');

const filtered = computed(() => {
    if (!search.value.trim()) return props.professionals;
    const q = search.value.toLowerCase();
    return props.professionals.filter(p =>
        p.name.toLowerCase().includes(q) ||
        p.specialty?.toLowerCase().includes(q)
    );
});

const memberBase = useMemberBase(computed(() => props.slug));
function profileUrl(p) { return `${memberBase.value}/profissionais/${p.id}`; }

function stars(rating) {
    return Array.from({ length: 5 }, (_, i) => i < Math.round(rating ?? 0));
}
</script>

<template>
    <div class="mx-auto max-w-3xl space-y-6 px-4 py-8">
        <!-- Header -->
        <div class="flex items-center gap-3">
            <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-blue-100 dark:bg-blue-900/30">
                <Stethoscope class="h-6 w-6 text-blue-600 dark:text-blue-400" />
            </div>
            <div>
                <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Profissionais</h1>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Encontre e agende com um profissional especializado.</p>
            </div>
        </div>

        <!-- Busca -->
        <div class="relative">
            <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" />
            <input v-model="search" type="search" placeholder="Buscar por nome ou especialidade..."
                class="w-full rounded-xl border border-zinc-200 bg-white py-2.5 pl-9 pr-4 text-sm focus:border-purple-400 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
        </div>

        <!-- Grid de profissionais -->
        <div v-if="filtered.length" class="grid gap-4 sm:grid-cols-2">
            <a v-for="p in filtered" :key="p.id" :href="profileUrl(p)"
                class="group flex flex-col rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm transition hover:border-purple-300 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-800/60">
                <!-- Avatar + nome -->
                <div class="flex items-start gap-3">
                    <div class="h-14 w-14 shrink-0 overflow-hidden rounded-2xl bg-zinc-100 dark:bg-zinc-700">
                        <img v-if="p.avatar_url" :src="p.avatar_url" :alt="p.name" class="h-full w-full object-cover" />
                        <div v-else class="flex h-full w-full items-center justify-center">
                            <UserCircle class="h-8 w-8 text-zinc-300 dark:text-zinc-600" />
                        </div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-zinc-900 group-hover:text-purple-700 dark:text-white dark:group-hover:text-purple-300 truncate">
                            {{ p.name }}
                        </p>
                        <p v-if="p.specialty" class="text-sm text-zinc-500 dark:text-zinc-400">{{ p.specialty }}</p>
                        <p v-if="p.council_type && p.council_number" class="text-xs text-zinc-400 mt-0.5">
                            {{ p.council_type }} {{ p.council_number }}
                        </p>
                    </div>
                </div>

                <!-- Avaliação -->
                <div v-if="p.avg_rating" class="mt-3 flex items-center gap-1.5">
                    <div class="flex">
                        <Star v-for="(filled, i) in stars(p.avg_rating)" :key="i"
                            class="h-3.5 w-3.5"
                            :class="filled ? 'fill-amber-400 text-amber-400' : 'text-zinc-200 dark:text-zinc-600'" />
                    </div>
                    <span class="text-xs font-semibold text-zinc-600 dark:text-zinc-300">{{ p.avg_rating }}</span>
                    <span class="text-xs text-zinc-400">({{ p.reviews_count }} avaliação{{ p.reviews_count !== 1 ? 'ões' : '' }})</span>
                </div>

                <div class="mt-auto flex items-center justify-end pt-3">
                    <span class="text-xs font-medium text-purple-600 group-hover:underline dark:text-purple-400">
                        Ver perfil e agendar
                    </span>
                    <ChevronRight class="h-4 w-4 text-purple-400" />
                </div>
            </a>
        </div>

        <!-- Vazio -->
        <div v-else class="flex flex-col items-center gap-3 rounded-2xl border-2 border-dashed border-zinc-200 py-16 text-center dark:border-zinc-700">
            <Stethoscope class="h-12 w-12 text-zinc-200 dark:text-zinc-700" />
            <p class="text-zinc-400">Nenhum profissional encontrado.</p>
        </div>
    </div>
</template>
