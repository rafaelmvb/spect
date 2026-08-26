<script setup>
import { ref, computed, watch } from 'vue';
import MemberAreaAppLayout from '@/Layouts/MemberAreaAppLayout.vue';
import {
    CalendarDays, Plus, X, Loader2, CheckCircle2,
    Clock, UserCircle, AlertCircle, ChevronLeft, ChevronRight,
} from 'lucide-vue-next';
import { useMemberBase } from '@/composables/useMemberBase';

defineOptions({ layout: MemberAreaAppLayout });

const props = defineProps({
    product:       { type: Object, required: true },
    appointments:  { type: Array, default: () => [] },
    professionals: { type: Array, default: () => [] },
    base_url:      { type: String, default: '' },
    slug:          { type: String, default: '' },
});

const memberBase          = useMemberBase(computed(() => props.slug));
const localAppointments   = ref([...props.appointments]);

// ── Status config ─────────────────────────────────────────────────────────────
const statusConfig = {
    pending:   { label: 'Pendente',       color: 'bg-amber-100 text-amber-700 dark:bg-amber-900/20 dark:text-amber-400',    dot: 'bg-amber-400' },
    confirmed: { label: 'Confirmado',     color: 'bg-blue-100 text-blue-700 dark:bg-blue-900/20 dark:text-blue-400',        dot: 'bg-blue-500' },
    completed: { label: 'Realizado',      color: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-400', dot: 'bg-emerald-500' },
    cancelled: { label: 'Cancelado',      color: 'bg-red-100 text-red-700 dark:bg-red-900/20 dark:text-red-400',            dot: 'bg-red-400' },
    no_show:   { label: 'Não compareceu', color: 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400',           dot: 'bg-zinc-400' },
};
function sc(status) { return statusConfig[status] ?? statusConfig.pending; }

// ── Tabs ──────────────────────────────────────────────────────────────────────
const activeTab = ref('upcoming');
const upcoming  = computed(() => localAppointments.value.filter(a => ['pending', 'confirmed'].includes(a.status)));
const past      = computed(() => localAppointments.value.filter(a => !['pending', 'confirmed'].includes(a.status)));

// ── Calendário ────────────────────────────────────────────────────────────────
const now          = new Date();
const calYear      = ref(now.getFullYear());
const calMonth     = ref(now.getMonth()); // 0-indexed

const DOW_LABELS   = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];

const calTitle = computed(() => {
    return new Date(calYear.value, calMonth.value, 1)
        .toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' });
});

function prevMonth() {
    if (calMonth.value === 0) { calMonth.value = 11; calYear.value--; }
    else calMonth.value--;
    selectedCalDate.value = null;
}
function nextMonth() {
    if (calMonth.value === 11) { calMonth.value = 0; calYear.value++; }
    else calMonth.value++;
    selectedCalDate.value = null;
}

const appointmentsByDate = computed(() => {
    const map = {};
    localAppointments.value.forEach(a => {
        if (!map[a.scheduled_date]) map[a.scheduled_date] = [];
        map[a.scheduled_date].push(a);
    });
    return map;
});

const calGrid = computed(() => {
    const y = calYear.value;
    const m = calMonth.value;
    // day-of-week of first day; convert Sun=0 to Mon-first
    const firstDow  = (new Date(y, m, 1).getDay() + 6) % 7;
    const daysInMon = new Date(y, m + 1, 0).getDate();
    const cells = Array(firstDow).fill(null);
    for (let d = 1; d <= daysInMon; d++) {
        const ds = `${y}-${String(m + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
        cells.push({ day: d, dateStr: ds, appts: appointmentsByDate.value[ds] ?? [] });
    }
    // pad tail to complete last row
    while (cells.length % 7 !== 0) cells.push(null);
    return cells;
});

const selectedCalDate    = ref(null);
const selectedDayAppts   = computed(() => selectedCalDate.value
    ? (appointmentsByDate.value[selectedCalDate.value] ?? [])
    : []);

function toggleCalDay(cell) {
    if (!cell || !cell.appts.length) { selectedCalDate.value = null; return; }
    selectedCalDate.value = selectedCalDate.value === cell.dateStr ? null : cell.dateStr;
}

// Reset selected day when switching months
watch([calYear, calMonth], () => { selectedCalDate.value = null; });

// ── Novo agendamento ─────────────────────────────────────────────────────────
const bookModal    = ref(false);
const bookProId    = ref('');
const bookDate     = ref('');
const bookSlots    = ref([]);
const bookTime     = ref('');
const bookNotes    = ref('');
const loadingSlots = ref(false);
const booking      = ref(false);
const bookDone     = ref(false);
const bookError    = ref('');

function csrfToken() { return document.querySelector('meta[name="csrf-token"]')?.content ?? ''; }
const h = () => ({ 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' });

async function loadSlots() {
    if (!bookDate.value || !bookProId.value) return;
    bookTime.value     = '';
    bookSlots.value    = [];
    loadingSlots.value = true;
    try {
        const url = `${memberBase.value}/profissionais/${bookProId.value}/horarios?date=${bookDate.value}`;
        const { data } = await window.axios.get(url, { headers: h() });
        bookSlots.value = data.slots ?? [];
    } catch { bookSlots.value = []; }
    finally { loadingSlots.value = false; }
}

async function confirmBook() {
    if (!bookProId.value || !bookDate.value || !bookTime.value) {
        bookError.value = 'Preencha todos os campos.'; return;
    }
    bookError.value = '';
    booking.value   = true;
    try {
        const { data } = await window.axios.post(`${memberBase.value}/meus-agendamentos`, {
            professional_id: bookProId.value,
            scheduled_date:  bookDate.value,
            scheduled_time:  bookTime.value,
            notes:           bookNotes.value || null,
        }, { headers: h() });
        localAppointments.value.unshift(data.appointment);
        bookDone.value = true;
    } catch (e) {
        bookError.value = e.response?.data?.message ?? 'Erro ao agendar.';
    } finally { booking.value = false; }
}

function openBookModal() {
    bookProId.value = ''; bookDate.value = ''; bookSlots.value = [];
    bookTime.value  = ''; bookNotes.value = ''; bookError.value = '';
    bookDone.value  = false;
    bookModal.value = true;
}

// ── Cancelar ─────────────────────────────────────────────────────────────────
const cancelModal  = ref(false);
const cancelTarget = ref(null);
const cancelReason = ref('');
const cancelling   = ref(false);
const cancelError  = ref('');

function openCancel(a) { cancelTarget.value = a; cancelReason.value = ''; cancelError.value = ''; cancelModal.value = true; }

async function confirmCancel() {
    if (!cancelTarget.value) return;
    cancelling.value  = true;
    cancelError.value = '';
    try {
        await window.axios.put(
            `${memberBase.value}/meus-agendamentos/${cancelTarget.value.id}/cancelar`,
            { reason: cancelReason.value || null },
            { headers: h() },
        );
        cancelTarget.value.status           = 'cancelled';
        cancelTarget.value.cancelled_reason = cancelReason.value || null;
        cancelModal.value = false;
    } catch (e) {
        cancelError.value = e.response?.data?.message ?? 'Erro ao cancelar.';
    } finally { cancelling.value = false; }
}

const todayStr = now.toISOString().split('T')[0];

function formatDate(d) {
    if (!d) return '';
    const [y, m, day] = d.split('-');
    return `${day}/${m}/${y}`;
}

// dots: up to 4, unique statuses
function apptDots(appts) {
    const seen = new Set();
    return appts
        .filter(a => { if (seen.has(a.status)) return false; seen.add(a.status); return true; })
        .slice(0, 4)
        .map(a => sc(a.status).dot);
}
</script>

<template>
    <div class="mx-auto max-w-2xl space-y-6 px-4 py-8">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-blue-100 dark:bg-blue-900/30">
                    <CalendarDays class="h-6 w-6 text-blue-600 dark:text-blue-400" />
                </div>
                <div>
                    <h1 class="text-xl font-bold text-zinc-900 dark:text-white">Meus Agendamentos</h1>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">Consultas e atendimentos.</p>
                </div>
            </div>
            <button type="button" @click="openBookModal"
                class="flex items-center gap-2 rounded-xl bg-purple-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-purple-700">
                <Plus class="h-4 w-4" /> Agendar
            </button>
        </div>

        <!-- Tabs -->
        <div class="flex gap-1 rounded-xl bg-zinc-100 p-1 dark:bg-zinc-800">
            <button v-for="tab in [
                {key:'upcoming', label:'Próximas'},
                {key:'calendar', label:'Calendário'},
                {key:'past',     label:'Histórico'},
            ]" :key="tab.key"
                type="button"
                class="flex-1 rounded-lg py-2 text-sm font-medium transition"
                :class="activeTab === tab.key
                    ? 'bg-white shadow text-zinc-900 dark:bg-zinc-700 dark:text-white'
                    : 'text-zinc-500'"
                @click="activeTab = tab.key">
                {{ tab.label }}
                <span v-if="tab.key === 'upcoming' && upcoming.length"
                    class="ml-1 inline-flex h-5 w-5 items-center justify-center rounded-full bg-purple-100 text-[10px] font-bold text-purple-700 dark:bg-purple-900/30 dark:text-purple-300">
                    {{ upcoming.length }}
                </span>
            </button>
        </div>

        <!-- ── Tab: Calendário ── -->
        <template v-if="activeTab === 'calendar'">
            <div class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60 overflow-hidden">
                <!-- Cabeçalho do mês -->
                <div class="flex items-center justify-between px-5 py-4 border-b border-zinc-100 dark:border-zinc-700">
                    <button type="button" @click="prevMonth"
                        class="flex h-8 w-8 items-center justify-center rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-700 transition">
                        <ChevronLeft class="h-4 w-4 text-zinc-500" />
                    </button>
                    <span class="text-sm font-semibold text-zinc-900 dark:text-white capitalize">{{ calTitle }}</span>
                    <button type="button" @click="nextMonth"
                        class="flex h-8 w-8 items-center justify-center rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-700 transition">
                        <ChevronRight class="h-4 w-4 text-zinc-500" />
                    </button>
                </div>

                <!-- Grid dos dias -->
                <div class="px-3 pb-3 pt-2">
                    <!-- Labels dos dias da semana -->
                    <div class="grid grid-cols-7 mb-1">
                        <div v-for="label in DOW_LABELS" :key="label"
                            class="py-1 text-center text-[10px] font-semibold uppercase tracking-wider text-zinc-400">
                            {{ label }}
                        </div>
                    </div>

                    <!-- Células -->
                    <div class="grid grid-cols-7 gap-px">
                        <div v-for="(cell, i) in calGrid" :key="i"
                            class="min-h-[3.2rem] rounded-lg flex flex-col items-center pt-1.5 pb-1 gap-0.5 transition"
                            :class="[
                                cell ? 'cursor-pointer' : '',
                                cell?.appts.length ? 'hover:bg-purple-50 dark:hover:bg-purple-900/20' : (cell ? 'hover:bg-zinc-50 dark:hover:bg-zinc-700/30' : ''),
                                selectedCalDate && cell?.dateStr === selectedCalDate ? 'bg-purple-50 ring-1 ring-purple-300 dark:bg-purple-900/20 dark:ring-purple-700' : '',
                            ]"
                            @click="toggleCalDay(cell)">
                            <template v-if="cell">
                                <!-- Número do dia -->
                                <span class="flex h-6 w-6 items-center justify-center rounded-full text-xs font-medium transition"
                                    :class="[
                                        cell.dateStr === todayStr
                                            ? 'bg-purple-600 text-white font-bold'
                                            : 'text-zinc-700 dark:text-zinc-300',
                                    ]">
                                    {{ cell.day }}
                                </span>
                                <!-- Dots de agendamentos -->
                                <div v-if="cell.appts.length" class="flex gap-0.5 flex-wrap justify-center">
                                    <span v-for="(dot, di) in apptDots(cell.appts)" :key="di"
                                        class="h-1.5 w-1.5 rounded-full" :class="dot" />
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Legenda -->
                <div class="flex items-center gap-4 px-5 py-3 border-t border-zinc-100 dark:border-zinc-700 flex-wrap">
                    <span v-for="(cfg, key) in statusConfig" :key="key"
                        class="flex items-center gap-1.5 text-[10px] text-zinc-400">
                        <span class="h-2 w-2 rounded-full" :class="cfg.dot" />
                        {{ cfg.label }}
                    </span>
                </div>
            </div>

            <!-- Detalhe do dia selecionado -->
            <div v-if="selectedCalDate" class="space-y-2">
                <p class="text-xs font-semibold text-zinc-500 uppercase tracking-wide px-1">
                    {{ formatDate(selectedCalDate) }}
                </p>
                <div v-for="a in selectedDayAppts" :key="a.id"
                    class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800/60">
                    <div class="flex items-start gap-3">
                        <div class="h-10 w-10 shrink-0 overflow-hidden rounded-xl bg-zinc-100 dark:bg-zinc-700">
                            <img v-if="a.professional_avatar" :src="a.professional_avatar" class="h-full w-full object-cover" />
                            <div v-else class="flex h-full w-full items-center justify-center">
                                <UserCircle class="h-6 w-6 text-zinc-300" />
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-sm text-zinc-900 dark:text-white">{{ a.professional_name }}</p>
                            <p v-if="a.professional_specialty" class="text-xs text-zinc-400">{{ a.professional_specialty }}</p>
                            <div class="mt-1 flex items-center gap-1.5 text-xs text-zinc-500">
                                <Clock class="h-3 w-3" /> {{ a.scheduled_time }}
                                <span v-if="a.duration_minutes">({{ a.duration_minutes }}min)</span>
                            </div>
                        </div>
                        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[10px] font-bold"
                            :class="sc(a.status).color">
                            <span class="h-1.5 w-1.5 rounded-full" :class="sc(a.status).dot" />
                            {{ sc(a.status).label }}
                        </span>
                    </div>
                    <div v-if="a.notes" class="mt-2 text-xs text-zinc-400 italic">{{ a.notes }}</div>
                    <div v-if="['pending','confirmed'].includes(a.status)" class="mt-3 flex justify-end">
                        <button type="button" @click="openCancel(a)"
                            class="text-xs font-medium text-red-500 hover:text-red-700">
                            Cancelar consulta
                        </button>
                    </div>
                </div>
            </div>

            <!-- Estado vazio do calendário -->
            <div v-else-if="!localAppointments.length"
                class="flex flex-col items-center gap-3 rounded-2xl border-2 border-dashed border-zinc-200 py-12 text-center dark:border-zinc-700">
                <CalendarDays class="h-10 w-10 text-zinc-200 dark:text-zinc-700" />
                <p class="text-sm text-zinc-400">Nenhuma consulta agendada.</p>
                <button type="button" @click="openBookModal"
                    class="rounded-xl bg-purple-600 px-4 py-2 text-sm font-semibold text-white hover:bg-purple-700">
                    Agendar agora
                </button>
            </div>
        </template>

        <!-- ── Tab: Próximas ── -->
        <div v-else-if="activeTab === 'upcoming'" class="space-y-3">
            <div v-if="!upcoming.length" class="flex flex-col items-center gap-3 rounded-2xl border-2 border-dashed border-zinc-200 py-12 text-center dark:border-zinc-700">
                <CalendarDays class="h-10 w-10 text-zinc-200 dark:text-zinc-700" />
                <p class="text-sm text-zinc-400">Nenhuma consulta agendada.</p>
                <button type="button" @click="openBookModal"
                    class="rounded-xl bg-purple-600 px-4 py-2 text-sm font-semibold text-white hover:bg-purple-700">
                    Agendar agora
                </button>
            </div>

            <div v-for="a in upcoming" :key="a.id"
                class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800/60">
                <div class="flex items-start gap-3">
                    <div class="h-12 w-12 shrink-0 overflow-hidden rounded-xl bg-zinc-100 dark:bg-zinc-700">
                        <img v-if="a.professional_avatar" :src="a.professional_avatar" class="h-full w-full object-cover" />
                        <div v-else class="flex h-full w-full items-center justify-center">
                            <UserCircle class="h-7 w-7 text-zinc-300" />
                        </div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-sm text-zinc-900 dark:text-white">{{ a.professional_name }}</p>
                        <p v-if="a.professional_specialty" class="text-xs text-zinc-400">{{ a.professional_specialty }}</p>
                        <div class="mt-1.5 flex items-center gap-3 flex-wrap text-xs text-zinc-500">
                            <span class="flex items-center gap-1">
                                <CalendarDays class="h-3 w-3" /> {{ formatDate(a.scheduled_date) }}
                            </span>
                            <span class="flex items-center gap-1">
                                <Clock class="h-3 w-3" /> {{ a.scheduled_time }}
                                <span v-if="a.duration_minutes"> ({{ a.duration_minutes }}min)</span>
                            </span>
                        </div>
                    </div>
                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[10px] font-bold"
                        :class="sc(a.status).color">
                        <span class="h-1.5 w-1.5 rounded-full" :class="sc(a.status).dot" />
                        {{ sc(a.status).label }}
                    </span>
                </div>
                <div v-if="a.notes" class="mt-2 text-xs text-zinc-400 italic">{{ a.notes }}</div>
                <div v-if="['pending','confirmed'].includes(a.status)" class="mt-3 flex justify-end">
                    <button type="button" @click="openCancel(a)"
                        class="text-xs font-medium text-red-500 hover:text-red-700">
                        Cancelar consulta
                    </button>
                </div>
            </div>
        </div>

        <!-- ── Tab: Histórico ── -->
        <div v-else class="space-y-3">
            <div v-if="!past.length" class="flex flex-col items-center gap-2 py-10 text-center">
                <CalendarDays class="h-10 w-10 text-zinc-200 dark:text-zinc-700" />
                <p class="text-sm text-zinc-400">Sem histórico de agendamentos.</p>
            </div>

            <div v-for="a in past" :key="a.id"
                class="rounded-2xl border border-zinc-100 bg-white p-4 opacity-80 dark:border-zinc-800 dark:bg-zinc-800/40">
                <div class="flex items-center gap-3">
                    <div class="h-10 w-10 shrink-0 overflow-hidden rounded-xl bg-zinc-100 dark:bg-zinc-700">
                        <img v-if="a.professional_avatar" :src="a.professional_avatar" class="h-full w-full object-cover" />
                        <div v-else class="flex h-full w-full items-center justify-center">
                            <UserCircle class="h-6 w-6 text-zinc-300" />
                        </div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ a.professional_name }}</p>
                        <p class="text-xs text-zinc-400">{{ formatDate(a.scheduled_date) }} às {{ a.scheduled_time }}</p>
                    </div>
                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold"
                        :class="sc(a.status).color">
                        {{ sc(a.status).label }}
                    </span>
                </div>
                <div v-if="a.cancelled_reason" class="mt-1.5 text-xs text-zinc-400 italic">
                    Motivo: {{ a.cancelled_reason }}
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Novo agendamento -->
    <Teleport to="body">
        <div v-if="bookModal" class="fixed inset-0 z-[200000] flex items-end sm:items-center justify-center bg-black/50 p-4"
            @click.self="bookModal = false">
            <div class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-zinc-900">
                <div class="flex items-center justify-between border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                    <h3 class="font-semibold text-zinc-900 dark:text-white">Novo agendamento</h3>
                    <button type="button" @click="bookModal = false"><X class="h-4 w-4 text-zinc-400" /></button>
                </div>
                <div v-if="!bookDone" class="space-y-4 p-5">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-zinc-500">Profissional *</label>
                        <select v-model="bookProId" @change="loadSlots"
                            class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-white">
                            <option value="">Selecionar</option>
                            <option v-for="p in professionals" :key="p.id" :value="p.id">
                                {{ p.name }}{{ p.specialty ? ` — ${p.specialty}` : '' }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-zinc-500">Data *</label>
                        <input type="date" v-model="bookDate" :min="todayStr" @change="loadSlots"
                            class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                    </div>
                    <div v-if="bookDate && bookProId">
                        <label class="mb-1 block text-xs font-medium text-zinc-500">Horário *</label>
                        <div v-if="loadingSlots" class="text-sm text-zinc-400 flex items-center gap-2">
                            <Loader2 class="h-3.5 w-3.5 animate-spin" /> Carregando...
                        </div>
                        <div v-else-if="!bookSlots.length" class="text-sm text-zinc-400">Sem horários disponíveis.</div>
                        <div v-else class="flex flex-wrap gap-2">
                            <button v-for="t in bookSlots" :key="t" type="button"
                                class="rounded-xl border px-3 py-1.5 text-sm font-medium"
                                :class="bookTime === t
                                    ? 'border-purple-500 bg-purple-600 text-white'
                                    : 'border-zinc-200 text-zinc-600 hover:border-purple-300 dark:border-zinc-700 dark:text-zinc-300'"
                                @click="bookTime = t">
                                {{ t }}
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-zinc-500">Observações</label>
                        <textarea v-model="bookNotes" rows="2"
                            class="w-full resize-none rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                    </div>
                    <div v-if="bookError" class="rounded-xl border border-red-200 bg-red-50 px-4 py-2.5 text-sm text-red-700 dark:border-red-900/40 dark:bg-red-900/10 dark:text-red-400">
                        {{ bookError }}
                    </div>
                    <div class="flex gap-2">
                        <button type="button" @click="bookModal = false"
                            class="flex-1 rounded-xl border border-zinc-200 py-2.5 text-sm font-medium text-zinc-600 dark:border-zinc-700">
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
                    <p class="font-bold text-zinc-900 dark:text-white">Agendamento solicitado!</p>
                    <p class="text-sm text-zinc-500">Aguarde a confirmação.</p>
                    <button type="button" @click="bookModal = false; bookDone = false"
                        class="mt-2 rounded-xl bg-zinc-100 px-5 py-2 text-sm font-medium dark:bg-zinc-800">
                        Fechar
                    </button>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- Modal: Cancelar -->
    <Teleport to="body">
        <div v-if="cancelModal" class="fixed inset-0 z-[200000] flex items-center justify-center bg-black/50 p-4"
            @click.self="cancelModal = false">
            <div class="w-full max-w-sm overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-zinc-900">
                <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                    <h3 class="font-semibold text-zinc-900 dark:text-white">Cancelar consulta</h3>
                </div>
                <div class="space-y-4 p-5">
                    <div class="flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700 dark:border-amber-800/40 dark:bg-amber-900/10 dark:text-amber-300">
                        <AlertCircle class="h-4 w-4 mt-0.5 shrink-0" />
                        Tem certeza que deseja cancelar esta consulta?
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-zinc-500">Motivo (opcional)</label>
                        <textarea v-model="cancelReason" rows="3"
                            class="w-full resize-none rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                    </div>
                    <div v-if="cancelError" class="text-sm text-red-600">{{ cancelError }}</div>
                    <div class="flex gap-2">
                        <button type="button" @click="cancelModal = false"
                            class="flex-1 rounded-xl border border-zinc-200 py-2.5 text-sm font-medium dark:border-zinc-700">
                            Voltar
                        </button>
                        <button type="button" :disabled="cancelling" @click="confirmCancel"
                            class="flex-1 flex items-center justify-center gap-2 rounded-xl bg-red-600 py-2.5 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-60">
                            <Loader2 v-if="cancelling" class="h-3.5 w-3.5 animate-spin" />
                            {{ cancelling ? 'Cancelando...' : 'Confirmar cancelamento' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
