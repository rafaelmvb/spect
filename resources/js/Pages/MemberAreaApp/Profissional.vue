<script setup>
import { ref, computed, watch } from 'vue';
import MemberAreaAppLayout from '@/Layouts/MemberAreaAppLayout.vue';
import {
    ChevronLeft, Star, CalendarDays, UserCircle,
    Loader2, Send, CheckCircle2, X, ChevronDown, Briefcase, Clock, CircleDollarSign,
} from 'lucide-vue-next';
import { useMemberBase } from '@/composables/useMemberBase';

defineOptions({ layout: MemberAreaAppLayout });

const props = defineProps({
    product:        { type: Object, required: true },
    professional:   { type: Object, required: true },
    reviews:        { type: Array, default: () => [] },
    services:       { type: Array, default: () => [] },
    pending_review: { type: Object, default: null },
    base_url:       { type: String, default: '' },
    slug:           { type: String, default: '' },
});

// ── Agendamento ───────────────────────────────────────────────────────────────
const bookModal       = ref(false);
const bookDate        = ref('');
const bookSlots       = ref([]);
const bookTime        = ref('');
const bookNotes       = ref('');
const selectedService = ref('');
const loadingSlots    = ref(false);
const booking         = ref(false);
const bookDone        = ref(false);
const bookError       = ref('');

// Sempre que o modal abre, reseta estado para garantir slots frescos
watch(bookModal, (isOpen) => {
    if (isOpen) {
        bookDate.value        = '';
        bookSlots.value       = [];
        bookTime.value        = '';
        bookNotes.value       = '';
        bookError.value       = '';
        bookDone.value        = false;
        selectedService.value = '';
    }
});

const localReviews = ref([...props.reviews]);

const memberBase = useMemberBase(computed(() => props.slug));
function csrfToken() { return document.querySelector('meta[name="csrf-token"]')?.content ?? ''; }
const h = () => ({ 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' });

async function loadSlots() {
    if (!bookDate.value) return;
    bookTime.value     = '';
    bookSlots.value    = [];
    loadingSlots.value = true;
    try {
        let url = `${memberBase.value}/profissionais/${props.professional.id}/horarios?date=${bookDate.value}`;
        if (selectedService.value) url += `&service_id=${selectedService.value}`;
        const { data } = await window.axios.get(url, { headers: h() });
        bookSlots.value = data.slots ?? [];
    } catch { bookSlots.value = []; }
    finally { loadingSlots.value = false; }
}

async function confirmBook() {
    if (!bookDate.value || !bookTime.value) {
        bookError.value = 'Selecione data e horário.'; return;
    }
    bookError.value = '';
    booking.value = true;
    try {
        const url = `${memberBase.value}/meus-agendamentos`;
        await window.axios.post(url, {
            professional_id: props.professional.id,
            service_id:      selectedService.value ? Number(selectedService.value) : null,
            scheduled_date:  bookDate.value,
            scheduled_time:  bookTime.value,
            notes:           bookNotes.value || null,
        }, { headers: h() });
        bookDone.value = true;
    } catch (e) {
        bookError.value = e.response?.data?.message ?? 'Erro ao agendar.';
    } finally { booking.value = false; }
}

// ── Avaliação ─────────────────────────────────────────────────────────────────
const reviewModal   = ref(false);
const reviewRating  = ref(0);
const reviewComment = ref('');
const submittingReview = ref(false);
const reviewDone    = ref(false);
const reviewError   = ref('');

async function submitReview() {
    if (!reviewRating.value) { reviewError.value = 'Selecione uma nota.'; return; }
    reviewError.value = '';
    submittingReview.value = true;
    try {
        const url = `${memberBase.value}/profissionais/${props.professional.id}/avaliar`;
        const { data } = await window.axios.post(url, {
            appointment_id: props.pending_review?.appointment_id ?? null,
            rating:         reviewRating.value,
            comment:        reviewComment.value || null,
        }, { headers: h() });
        localReviews.value.unshift(data.review);
        reviewDone.value = true;
        setTimeout(() => { reviewModal.value = false; }, 1500);
    } catch (e) {
        reviewError.value = e.response?.data?.message ?? 'Erro ao enviar avaliação.';
    } finally { submittingReview.value = false; }
}

function stars(rating) { return Array.from({ length: 5 }, (_, i) => i < Math.round(rating ?? 0)); }

const todayStr = new Date().toISOString().split('T')[0];

// Recarrega slots quando serviço muda (se já tiver data selecionada)
watch(selectedService, () => { if (bookDate.value) loadSlots(); });
</script>

<template>
    <div class="mx-auto max-w-2xl space-y-6 px-4 py-8">
        <!-- Back -->
        <a :href="`${memberBase}/profissionais`"
            class="inline-flex items-center gap-1 text-sm font-medium text-zinc-500 hover:text-purple-600 dark:hover:text-purple-400">
            <ChevronLeft class="h-4 w-4" /> Todos os profissionais
        </a>

        <!-- Perfil -->
        <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800/60">
            <div class="flex items-start gap-5">
                <div class="h-20 w-20 shrink-0 overflow-hidden rounded-2xl bg-zinc-100 dark:bg-zinc-700">
                    <img v-if="professional.avatar_url" :src="professional.avatar_url" :alt="professional.name" class="h-full w-full object-cover" />
                    <div v-else class="flex h-full w-full items-center justify-center">
                        <UserCircle class="h-10 w-10 text-zinc-300 dark:text-zinc-600" />
                    </div>
                </div>
                <div class="flex-1 min-w-0">
                    <h1 class="text-xl font-bold text-zinc-900 dark:text-white">{{ professional.name }}</h1>
                    <p v-if="professional.specialty" class="text-purple-600 dark:text-purple-400 font-medium">{{ professional.specialty }}</p>
                    <p v-if="professional.council_type" class="text-sm text-zinc-400 mt-0.5">
                        {{ professional.council_type }} {{ professional.council_number }}
                    </p>

                    <!-- Rating -->
                    <div v-if="professional.avg_rating" class="mt-2 flex items-center gap-2">
                        <div class="flex">
                            <Star v-for="(filled, i) in stars(professional.avg_rating)" :key="i"
                                class="h-4 w-4"
                                :class="filled ? 'fill-amber-400 text-amber-400' : 'text-zinc-200 dark:text-zinc-600'" />
                        </div>
                        <span class="text-sm font-bold text-zinc-700 dark:text-zinc-200">{{ professional.avg_rating }}</span>
                        <span class="text-sm text-zinc-400">({{ professional.reviews_count }})</span>
                    </div>
                </div>
            </div>

            <p v-if="professional.bio" class="mt-4 text-sm text-zinc-600 leading-relaxed dark:text-zinc-300">{{ professional.bio }}</p>

            <!-- Ações -->
            <div class="mt-5 flex flex-wrap gap-2">
                <button type="button"
                    class="flex items-center gap-2 rounded-xl bg-purple-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-purple-700"
                    @click="bookModal = true">
                    <CalendarDays class="h-4 w-4" /> Agendar consulta
                </button>

                <button v-if="pending_review && !reviewDone" type="button"
                    class="flex items-center gap-2 rounded-xl border border-amber-300 bg-amber-50 px-5 py-2.5 text-sm font-semibold text-amber-700 transition hover:bg-amber-100 dark:border-amber-700 dark:bg-amber-900/20 dark:text-amber-300"
                    @click="reviewModal = true">
                    <Star class="h-4 w-4" /> Avaliar consulta
                </button>
            </div>
        </div>

        <!-- Serviços -->
        <div v-if="services.length" class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-800/60">
            <div class="mb-4 flex items-center gap-2">
                <Briefcase class="h-4 w-4 text-purple-500" />
                <h2 class="font-semibold text-zinc-900 dark:text-white">Serviços</h2>
            </div>
            <div class="space-y-3">
                <div v-for="s in services" :key="s.id"
                    class="flex items-start justify-between gap-3 rounded-xl border border-zinc-100 bg-zinc-50/60 px-4 py-3 dark:border-zinc-700/60 dark:bg-zinc-800/40">
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-sm text-zinc-800 dark:text-zinc-100">{{ s.name }}</p>
                        <p v-if="s.description" class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400 leading-relaxed">{{ s.description }}</p>
                        <div v-if="s.duration_minutes" class="mt-1.5 flex items-center gap-1 text-xs text-zinc-400">
                            <Clock class="h-3 w-3" />
                            {{ s.duration_minutes }} min
                        </div>
                    </div>
                    <div v-if="s.price" class="shrink-0 flex items-center gap-1 text-sm font-semibold text-purple-600 dark:text-purple-400">
                        <CircleDollarSign class="h-4 w-4" />
                        R$ {{ Number(s.price).toFixed(2).replace('.', ',') }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Avaliações -->
        <div v-if="localReviews.length" class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-800/60">
            <h2 class="mb-4 font-semibold text-zinc-900 dark:text-white">Avaliações ({{ localReviews.length }})</h2>
            <div class="space-y-4">
                <div v-for="r in localReviews" :key="r.id" class="border-b border-zinc-100 pb-4 last:border-0 last:pb-0 dark:border-zinc-800">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <span class="font-medium text-sm text-zinc-800 dark:text-zinc-200">{{ r.user_name ?? 'Anônimo' }}</span>
                            <div class="flex">
                                <Star v-for="(filled, i) in stars(r.rating)" :key="i"
                                    class="h-3.5 w-3.5"
                                    :class="filled ? 'fill-amber-400 text-amber-400' : 'text-zinc-200 dark:text-zinc-600'" />
                            </div>
                        </div>
                        <span class="text-xs text-zinc-400">{{ r.created_at }}</span>
                    </div>
                    <p v-if="r.comment" class="mt-1.5 text-sm text-zinc-600 dark:text-zinc-300">{{ r.comment }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Agendar -->
    <Teleport to="body">
        <div v-if="bookModal" class="fixed inset-0 z-[200000] flex items-end sm:items-center justify-center bg-black/50 p-4"
            @click.self="bookModal = false">
            <div class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-zinc-900">
                <div class="flex items-center justify-between border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                    <h3 class="font-semibold text-zinc-900 dark:text-white">Agendar com {{ professional.name }}</h3>
                    <button type="button" @click="bookModal = false"><X class="h-4 w-4 text-zinc-400" /></button>
                </div>
                <div v-if="!bookDone" class="space-y-4 p-5">
                    <!-- Seleção de serviço -->
                    <div v-if="services.length">
                        <label class="mb-1 block text-xs font-medium text-zinc-500">Serviço *</label>
                        <select v-model="selectedService"
                            class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-white">
                            <option value="">Selecione um serviço</option>
                            <option v-for="s in services" :key="s.id" :value="s.id">
                                {{ s.name }}{{ s.duration_minutes ? ` — ${s.duration_minutes}min` : '' }}{{ s.price ? ` — R$ ${Number(s.price).toFixed(2).replace('.', ',')}` : '' }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-zinc-500">Data *</label>
                        <input type="date" v-model="bookDate" :min="todayStr"
                            class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                            @change="loadSlots" />
                    </div>

                    <div v-if="bookDate">
                        <label class="mb-1 block text-xs font-medium text-zinc-500">Horário *</label>
                        <div v-if="loadingSlots" class="flex items-center gap-2 text-sm text-zinc-400">
                            <Loader2 class="h-4 w-4 animate-spin" /> Carregando horários...
                        </div>
                        <div v-else-if="!bookSlots.length" class="text-sm text-zinc-400">
                            Sem horários disponíveis nesta data.
                        </div>
                        <div v-else class="flex flex-wrap gap-2">
                            <button v-for="t in bookSlots" :key="t" type="button"
                                class="rounded-xl border px-3 py-1.5 text-sm font-medium transition"
                                :class="bookTime === t
                                    ? 'border-purple-500 bg-purple-600 text-white'
                                    : 'border-zinc-200 text-zinc-600 hover:border-purple-300 dark:border-zinc-700 dark:text-zinc-300'"
                                @click="bookTime = t">
                                {{ t }}
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-zinc-500">Observações (opcional)</label>
                        <textarea v-model="bookNotes" rows="3"
                            class="w-full resize-none rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                    </div>

                    <div v-if="bookError" class="rounded-xl border border-red-200 bg-red-50 px-4 py-2.5 text-sm text-red-700 dark:border-red-900/40 dark:bg-red-900/10 dark:text-red-400">
                        {{ bookError }}
                    </div>

                    <div class="flex gap-2">
                        <button type="button" @click="bookModal = false"
                            class="flex-1 rounded-xl border border-zinc-200 py-2.5 text-sm font-medium text-zinc-600 dark:border-zinc-700 dark:text-zinc-300">
                            Cancelar
                        </button>
                        <button type="button" :disabled="booking" @click="confirmBook"
                            class="flex-1 flex items-center justify-center gap-2 rounded-xl bg-purple-600 py-2.5 text-sm font-semibold text-white hover:bg-purple-700 disabled:opacity-60">
                            <Loader2 v-if="booking" class="h-3.5 w-3.5 animate-spin" />
                            {{ booking ? 'Agendando...' : 'Confirmar' }}
                        </button>
                    </div>
                </div>
                <div v-else class="flex flex-col items-center gap-3 p-8 text-center">
                    <CheckCircle2 class="h-12 w-12 text-emerald-500" />
                    <p class="font-semibold text-zinc-900 dark:text-white">Agendamento solicitado!</p>
                    <p class="text-sm text-zinc-500">Você receberá a confirmação em breve.</p>
                    <button type="button" @click="bookModal = false; bookDone = false"
                        class="mt-2 rounded-xl bg-zinc-100 px-5 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300">
                        Fechar
                    </button>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- Modal: Avaliar -->
    <Teleport to="body">
        <div v-if="reviewModal" class="fixed inset-0 z-[200000] flex items-center justify-center bg-black/50 p-4"
            @click.self="reviewModal = false">
            <div class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-zinc-900">
                <div class="flex items-center justify-between border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                    <h3 class="font-semibold text-zinc-900 dark:text-white">Avaliar {{ professional.name }}</h3>
                    <button type="button" @click="reviewModal = false"><X class="h-4 w-4 text-zinc-400" /></button>
                </div>
                <div v-if="!reviewDone" class="space-y-4 p-5">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-zinc-600 dark:text-zinc-300">Sua nota</label>
                        <div class="flex gap-2">
                            <button v-for="n in [1,2,3,4,5]" :key="n" type="button"
                                @click="reviewRating = n">
                                <Star class="h-8 w-8 transition"
                                    :class="n <= reviewRating ? 'fill-amber-400 text-amber-400' : 'text-zinc-200 hover:text-amber-300 dark:text-zinc-600'" />
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-zinc-500">Comentário (opcional)</label>
                        <textarea v-model="reviewComment" rows="4"
                            placeholder="Como foi sua experiência?"
                            class="w-full resize-none rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                    </div>
                    <div v-if="reviewError" class="rounded-xl border border-red-200 bg-red-50 px-4 py-2.5 text-sm text-red-700 dark:border-red-900/40 dark:bg-red-900/10 dark:text-red-400">
                        {{ reviewError }}
                    </div>
                    <button type="button" :disabled="submittingReview" @click="submitReview"
                        class="flex w-full items-center justify-center gap-2 rounded-xl bg-amber-500 py-2.5 text-sm font-semibold text-white hover:bg-amber-600 disabled:opacity-60">
                        <Loader2 v-if="submittingReview" class="h-3.5 w-3.5 animate-spin" />
                        <Send v-else class="h-3.5 w-3.5" />
                        {{ submittingReview ? 'Enviando...' : 'Publicar avaliação' }}
                    </button>
                </div>
                <div v-else class="flex flex-col items-center gap-3 p-8 text-center">
                    <CheckCircle2 class="h-10 w-10 text-emerald-500" />
                    <p class="font-semibold text-zinc-900 dark:text-white">Avaliação enviada!</p>
                </div>
            </div>
        </div>
    </Teleport>
</template>
