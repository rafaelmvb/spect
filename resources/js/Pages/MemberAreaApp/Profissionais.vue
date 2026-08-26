<script setup>
import { ref, computed } from 'vue';
import MemberAreaAppLayout from '@/Layouts/MemberAreaAppLayout.vue';
import { Stethoscope, Search, Star, ChevronRight, UserCircle, ShieldCheck, ShieldQuestion } from 'lucide-vue-next';
import { useMemberBase } from '@/composables/useMemberBase';

defineOptions({ layout: MemberAreaAppLayout });

const props = defineProps({
    product:       { type: Object, required: true },
    professionals: { type: Array, default: () => [] },
    vinculos:      { type: Array, default: () => [] },
    base_url:      { type: String, default: '' },
    slug:          { type: String, default: '' },
});

const memberBase = useMemberBase(computed(() => props.slug));

const listaVinculos = ref([...props.vinculos]);
const enviando = ref(null);

const convitesPendentes = computed(() => listaVinculos.value.filter(v => v.status === 'pending'));
const acessosAtivos = computed(() => listaVinculos.value.filter(v => v.status === 'active'));

async function responder(vinculo, acao) {
    if (acao === 'revogar' && !window.confirm(
        `Revogar o acesso de ${vinculo.profissional.nome}? Ele deixa de ver seus testes e sua evolução.`
    )) return;

    enviando.value = vinculo.id;
    try {
        const { data } = await window.axios.post(`${memberBase.value}/profissionais/vinculo/${vinculo.id}`, { acao });
        if (data.status === 'active') {
            vinculo.status = 'active';
        } else {
            listaVinculos.value = listaVinculos.value.filter(v => v.id !== vinculo.id);
        }
    } catch (e) {
        window.alert(e?.response?.data?.message || 'Não foi possível concluir. Tente de novo.');
    } finally {
        enviando.value = null;
    }
}

const search = ref('');

const filtered = computed(() => {
    if (!search.value.trim()) return props.professionals;
    const q = search.value.toLowerCase();
    return props.professionals.filter(p =>
        p.name.toLowerCase().includes(q) ||
        p.specialty?.toLowerCase().includes(q)
    );
});

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

        <!-- Convites e acessos concedidos -->
        <section v-if="convitesPendentes.length || acessosAtivos.length" class="space-y-3">

            <div v-for="v in convitesPendentes" :key="`p-${v.id}`"
                class="rounded-2xl border border-amber-300 bg-amber-50 p-4 dark:border-amber-700/60 dark:bg-amber-950/30">
                <div class="flex items-start gap-3">
                    <ShieldQuestion class="mt-0.5 h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400" />
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-amber-900 dark:text-amber-100">
                            {{ v.profissional.nome }} quer acompanhar você
                        </p>
                        <p class="mt-1 text-sm text-amber-800 dark:text-amber-200">
                            Se você aceitar, {{ v.profissional.nome }} passa a ver os testes que ela mesma enviar e sua
                            evolução. O que você responde por conta própria continua privado.
                        </p>
                        <p v-if="v.profissional.registro" class="mt-1 text-xs text-amber-700 dark:text-amber-300">
                            {{ v.profissional.especialidade }} · {{ v.profissional.registro }}
                        </p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <button type="button" :disabled="enviando === v.id" @click="responder(v, 'aceitar')"
                                class="rounded-xl bg-amber-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-amber-700 disabled:opacity-60">
                                Aceitar
                            </button>
                            <button type="button" :disabled="enviando === v.id" @click="responder(v, 'recusar')"
                                class="rounded-xl border border-amber-300 px-4 py-2 text-sm font-medium text-amber-800 transition hover:bg-amber-100 disabled:opacity-60 dark:border-amber-700 dark:text-amber-200 dark:hover:bg-amber-900/40">
                                Recusar
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div v-for="v in acessosAtivos" :key="`a-${v.id}`"
                class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800">
                <div class="flex min-w-0 items-center gap-3">
                    <ShieldCheck class="h-5 w-5 shrink-0 text-emerald-600 dark:text-emerald-400" />
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-zinc-900 dark:text-white">
                            {{ v.profissional.nome }} acompanha você
                        </p>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">
                            Autorizado em {{ v.respondido_em }}
                        </p>
                    </div>
                </div>
                <button type="button" :disabled="enviando === v.id" @click="responder(v, 'revogar')"
                    class="rounded-xl border border-zinc-200 px-3 py-1.5 text-sm text-zinc-600 transition hover:border-red-300 hover:text-red-600 disabled:opacity-60 dark:border-zinc-600 dark:text-zinc-300 dark:hover:border-red-700 dark:hover:text-red-400">
                    Revogar acesso
                </button>
            </div>

        </section>

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
