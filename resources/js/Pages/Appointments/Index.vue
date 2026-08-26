<script setup>
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import LayoutInfoprodutor from '@/Layouts/LayoutInfoprodutor.vue';
import Button from '@/components/ui/Button.vue';
import {
    CalendarDays, Plus, Pencil, Trash2, X, Loader2,
    ChevronLeft, ChevronRight, Clock, User, Phone,
    Mail, CheckCircle2, XCircle, AlertCircle, Circle,
    Settings, Save, ChevronDown
} from 'lucide-vue-next';

defineOptions({ layout: LayoutInfoprodutor });

const props = defineProps({
    appointments:  { type: Array,  default: () => [] },
    stats:         { type: Object, default: () => ({}) },
    professionals: { type: Array,  default: () => [] },
    year:          { type: Number, default: () => new Date().getFullYear() },
    month:         { type: Number, default: () => new Date().getMonth() + 1 },
    filters:       { type: Object, default: () => ({}) },
});

// ─── Toast ────────────────────────────────────────────────────────────────────
const toast = ref(null);
function showToast(msg, type = 'success') {
    toast.value = { msg, type };
    setTimeout(() => toast.value = null, 4000);
}
function csrfToken() { return document.querySelector('meta[name="csrf-token"]')?.content ?? ''; }
const h = () => ({ 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' });

// ─── Filtros ─────────────────────────────────────────────────────────────────
const filterPro    = ref(props.filters.professional_id ?? '');
const filterStatus = ref(props.filters.status ?? '');

function applyFilters(newYear = currentYear.value, newMonth = currentMonth.value) {
    const params = { year: newYear, month: newMonth };
    if (filterPro.value)    params.professional_id = filterPro.value;
    if (filterStatus.value) params.status = filterStatus.value;
    router.get('/agendamentos', params, { preserveState: true, replace: true });
}

// ─── Calendário ───────────────────────────────────────────────────────────────
const currentYear  = ref(props.year);
const currentMonth = ref(props.month);

const monthName = computed(() => {
    return new Date(currentYear.value, currentMonth.value - 1, 1)
        .toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' });
});

function prevMonth() {
    if (currentMonth.value === 1) { currentYear.value--; currentMonth.value = 12; }
    else currentMonth.value--;
    applyFilters(currentYear.value, currentMonth.value);
}
function nextMonth() {
    if (currentMonth.value === 12) { currentYear.value++; currentMonth.value = 1; }
    else currentMonth.value++;
    applyFilters(currentYear.value, currentMonth.value);
}

// Dias do calendário (grade 7 colunas, semana começa domingo)
const calendarDays = computed(() => {
    const year = currentYear.value;
    const month = currentMonth.value;
    const firstDay = new Date(year, month - 1, 1).getDay(); // 0=Dom
    const daysInMonth = new Date(year, month, 0).getDate();
    const days = [];
    for (let i = 0; i < firstDay; i++) days.push(null);
    for (let d = 1; d <= daysInMonth; d++) days.push(d);
    return days;
});

const today = new Date();
const todayStr = computed(() =>
    `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`
);

function dateStr(day) {
    return `${currentYear.value}-${String(currentMonth.value).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
}

function appointmentsForDay(day) {
    if (!day) return [];
    return props.appointments.filter(a => a.scheduled_date === dateStr(day));
}

// ─── Dia selecionado ─────────────────────────────────────────────────────────
const selectedDay = ref(null);

function selectDay(day) {
    if (!day) return;
    selectedDay.value = day;
    selectedAppointment.value = null;
}

const dayAppointments = computed(() =>
    selectedDay.value ? appointmentsForDay(selectedDay.value) : []
);

// ─── Appointment selecionado (detalhe) ───────────────────────────────────────
const selectedAppointment = ref(null);

function openDetail(a) {
    selectedAppointment.value = { ...a };
}

// ─── Modal criar/editar ───────────────────────────────────────────────────────
const apptModal  = ref(false);
const editingApt = ref(null);
const savingApt  = ref(false);
const availSlots = ref([]);
const loadingSlots = ref(false);

const emptyForm = () => ({
    professional_id: props.professionals[0]?.id ?? '',
    client_name: '', client_email: '', client_phone: '',
    scheduled_date: selectedDay.value ? dateStr(selectedDay.value) : '',
    scheduled_time: '', duration_minutes: 60,
    notes: '', status: 'pending',
});
const apptForm = ref(emptyForm());

function openCreate() {
    editingApt.value = null;
    apptForm.value = emptyForm();
    availSlots.value = [];
    if (apptForm.value.professional_id && apptForm.value.scheduled_date) {
        fetchSlots(apptForm.value.professional_id, apptForm.value.scheduled_date);
    }
    apptModal.value = true;
}

function openEdit(a) {
    editingApt.value = a;
    apptForm.value = {
        professional_id:  a.professional_id,
        client_name:      a.client_name,
        client_email:     a.client_email ?? '',
        client_phone:     a.client_phone ?? '',
        scheduled_date:   a.scheduled_date,
        scheduled_time:   a.scheduled_time?.substring(0, 5) ?? '',
        duration_minutes: a.duration_minutes,
        notes:            a.notes ?? '',
        admin_notes:      a.admin_notes ?? '',
        status:           a.status,
    };
    availSlots.value = [];
    apptModal.value = true;
}

async function fetchSlots(proId, date) {
    if (!proId || !date) return;
    loadingSlots.value = true;
    try {
        const { data } = await window.axios.get(
            `/agendamentos/profissional/${proId}/slots?date=${date}`,
            { headers: h() }
        );
        availSlots.value = data.slots ?? [];
    } catch { availSlots.value = []; }
    finally { loadingSlots.value = false; }
}

watch(() => [apptForm.value.professional_id, apptForm.value.scheduled_date], ([proId, date]) => {
    if (proId && date) fetchSlots(proId, date);
    else availSlots.value = [];
});

async function saveAppointment() {
    if (!apptForm.value.client_name.trim() || !apptForm.value.scheduled_date || !apptForm.value.scheduled_time) {
        showToast('Preencha nome, data e horário.', 'error'); return;
    }
    savingApt.value = true;
    try {
        if (editingApt.value) {
            const { data } = await window.axios.put(`/agendamentos/${editingApt.value.id}`, apptForm.value, { headers: h() });
            selectedAppointment.value = data.appointment;
            showToast('Agendamento atualizado.');
        } else {
            await window.axios.post('/agendamentos', apptForm.value, { headers: h() });
            showToast('Agendamento criado.');
        }
        apptModal.value = false;
        router.reload({ only: ['appointments', 'stats'] });
    } catch (e) {
        showToast(e.response?.data?.message ?? 'Erro ao salvar.', 'error');
    } finally {
        savingApt.value = false;
    }
}

// ─── Ações de status ─────────────────────────────────────────────────────────
const cancelModal    = ref(false);
const cancelReason   = ref('');
const cancelling     = ref(false);

function openCancel(a) {
    selectedAppointment.value = a;
    cancelReason.value = '';
    cancelModal.value = true;
}

async function confirmCancel() {
    cancelling.value = true;
    try {
        await window.axios.put(
            `/agendamentos/${selectedAppointment.value.id}/cancel`,
            { reason: cancelReason.value },
            { headers: h() }
        );
        showToast('Agendamento cancelado.');
        cancelModal.value = false;
        selectedAppointment.value = null;
        router.reload({ only: ['appointments', 'stats'] });
    } catch { showToast('Erro.', 'error'); }
    finally { cancelling.value = false; }
}

async function completeAppointment(a) {
    try {
        const { data } = await window.axios.put(`/agendamentos/${a.id}/complete`, {}, { headers: h() });
        selectedAppointment.value = data.appointment;
        showToast('Marcado como realizado.');
        router.reload({ only: ['appointments', 'stats'] });
    } catch { showToast('Erro.', 'error'); }
}

async function destroyAppointment(a) {
    if (!confirm('Excluir este agendamento?')) return;
    try {
        await window.axios.delete(`/agendamentos/${a.id}`, { headers: h() });
        selectedAppointment.value = null;
        showToast('Excluído.');
        router.reload({ only: ['appointments', 'stats'] });
    } catch { showToast('Erro.', 'error'); }
}

// ─── Disponibilidade ─────────────────────────────────────────────────────────
const availTab     = ref(false); // mostra o painel de disponibilidade
const availPro     = ref(props.professionals[0]?.id ?? '');
const loadingAvail = ref(false);
const savingAvail  = ref(false);

const weekDays = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
const availForm = ref(weekDays.map((_, dow) => ({
    day_of_week: dow, start_time: '08:00', end_time: '18:00',
    slot_duration: 60, is_active: false,
})));

async function loadAvailability(proId) {
    if (!proId) return;
    loadingAvail.value = true;
    try {
        const { data } = await window.axios.get(
            `/agendamentos/profissional/${proId}/disponibilidade`,
            { headers: h() }
        );
        const avail = data.availability ?? [];
        availForm.value = weekDays.map((_, dow) => {
            const existing = avail.find(a => a.day_of_week === dow);
            return existing
                ? { day_of_week: dow, start_time: existing.start_time?.substring(0,5), end_time: existing.end_time?.substring(0,5), slot_duration: existing.slot_duration, is_active: existing.is_active }
                : { day_of_week: dow, start_time: '08:00', end_time: '18:00', slot_duration: 60, is_active: false };
        });
    } catch { showToast('Erro ao carregar disponibilidade.', 'error'); }
    finally { loadingAvail.value = false; }
}

watch(availPro, (v) => { if (v && availTab.value) loadAvailability(v); });
watch(availTab, (v) => { if (v && availPro.value) loadAvailability(availPro.value); });

async function saveAvailability() {
    savingAvail.value = true;
    try {
        await window.axios.post(
            `/agendamentos/profissional/${availPro.value}/disponibilidade`,
            { slots: availForm.value },
            { headers: h() }
        );
        showToast('Disponibilidade salva.');
    } catch { showToast('Erro ao salvar.', 'error'); }
    finally { savingAvail.value = false; }
}

// ─── Helpers ─────────────────────────────────────────────────────────────────
const statusConfig = {
    pending:   { label: 'Pendente',          color: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',   dot: 'bg-amber-400',   icon: AlertCircle },
    confirmed: { label: 'Confirmado',         color: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',      dot: 'bg-blue-400',    icon: Circle },
    completed: { label: 'Realizado',          color: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300', dot: 'bg-emerald-400', icon: CheckCircle2 },
    cancelled: { label: 'Cancelado',          color: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300',          dot: 'bg-red-400',     icon: XCircle },
    no_show:   { label: 'Não compareceu',     color: 'bg-zinc-100 text-zinc-500 dark:bg-zinc-700 dark:text-zinc-400',         dot: 'bg-zinc-400',    icon: XCircle },
};

function sc(status) { return statusConfig[status] ?? statusConfig.pending; }

function fmtTime(t) { return t?.substring(0, 5) ?? ''; }
</script>

<template>
    <div class="space-y-5">
        <!-- Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-100 dark:bg-blue-900/30">
                    <CalendarDays class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                </div>
                <div>
                    <h1 class="text-xl font-bold text-zinc-900 dark:text-white">Agendamentos</h1>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">Calendário de consultas e disponibilidade.</p>
                </div>
            </div>
            <div class="flex gap-2">
                <Button variant="outline" @click="availTab = !availTab">
                    <Settings class="mr-2 h-4 w-4" /> Disponibilidade
                </Button>
                <Button @click="openCreate">
                    <Plus class="mr-2 h-4 w-4" /> Novo agendamento
                </Button>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
                <p class="text-xs font-medium uppercase tracking-wide text-zinc-500">Total no mês</p>
                <p class="mt-1 text-2xl font-bold tabular-nums text-zinc-900 dark:text-white">{{ stats.total ?? 0 }}</p>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
                <p class="text-xs font-medium uppercase tracking-wide text-zinc-500">Confirmados</p>
                <p class="mt-1 text-2xl font-bold tabular-nums text-blue-600">{{ stats.confirmed ?? 0 }}</p>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
                <p class="text-xs font-medium uppercase tracking-wide text-zinc-500">Realizados</p>
                <p class="mt-1 text-2xl font-bold tabular-nums text-emerald-600">{{ stats.completed ?? 0 }}</p>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
                <p class="text-xs font-medium uppercase tracking-wide text-zinc-500">Cancelados</p>
                <p class="mt-1 text-2xl font-bold tabular-nums text-red-500">{{ stats.cancelled ?? 0 }}</p>
            </div>
        </div>

        <!-- Disponibilidade panel -->
        <div v-if="availTab" class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-800/50 space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-zinc-900 dark:text-white">Disponibilidade semanal</h2>
                <button type="button" class="text-zinc-400 hover:text-zinc-600" @click="availTab = false"><X class="h-4 w-4" /></button>
            </div>

            <div class="flex items-center gap-3">
                <select v-model="availPro"
                    class="rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-white">
                    <option v-for="p in professionals" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
                <div v-if="loadingAvail" class="flex items-center gap-1.5 text-xs text-zinc-400">
                    <Loader2 class="h-4 w-4 animate-spin" /> Carregando...
                </div>
            </div>

            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                <div v-for="slot in availForm" :key="slot.day_of_week"
                    class="rounded-xl border p-3 transition"
                    :class="slot.is_active
                        ? 'border-blue-300 bg-blue-50 dark:border-blue-700 dark:bg-blue-900/20'
                        : 'border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800/30'">
                    <label class="flex items-center gap-2 mb-2 cursor-pointer">
                        <input type="checkbox" v-model="slot.is_active"
                            class="h-4 w-4 rounded border-zinc-300 text-blue-600 focus:ring-blue-500" />
                        <span class="font-semibold text-sm" :class="slot.is_active ? 'text-blue-700 dark:text-blue-300' : 'text-zinc-500'">
                            {{ weekDays[slot.day_of_week] }}
                        </span>
                    </label>
                    <template v-if="slot.is_active">
                        <div class="space-y-1.5">
                            <div class="flex gap-1.5">
                                <input v-model="slot.start_time" type="time"
                                    class="flex-1 rounded-lg border border-zinc-200 bg-white px-2 py-1 text-xs dark:border-zinc-700 dark:bg-zinc-900 dark:text-white" />
                                <input v-model="slot.end_time" type="time"
                                    class="flex-1 rounded-lg border border-zinc-200 bg-white px-2 py-1 text-xs dark:border-zinc-700 dark:bg-zinc-900 dark:text-white" />
                            </div>
                            <div class="flex items-center gap-2">
                                <label class="text-xs text-zinc-400">Duração</label>
                                <select v-model.number="slot.slot_duration"
                                    class="flex-1 rounded-lg border border-zinc-200 bg-white px-2 py-1 text-xs dark:border-zinc-700 dark:bg-zinc-900 dark:text-white">
                                    <option :value="15">15 min</option>
                                    <option :value="30">30 min</option>
                                    <option :value="45">45 min</option>
                                    <option :value="60">1 hora</option>
                                    <option :value="90">1h30</option>
                                    <option :value="120">2 horas</option>
                                </select>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <div class="flex justify-end">
                <Button :disabled="savingAvail" @click="saveAvailability">
                    <Loader2 v-if="savingAvail" class="mr-2 h-3.5 w-3.5 animate-spin" />
                    <Save v-else class="mr-2 h-3.5 w-3.5" />
                    Salvar disponibilidade
                </Button>
            </div>
        </div>

        <!-- Filtros -->
        <div class="flex flex-wrap gap-3">
            <select v-model="filterPro" @change="applyFilters()"
                class="rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-white">
                <option value="">Todos os profissionais</option>
                <option v-for="p in professionals" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
            <select v-model="filterStatus" @change="applyFilters()"
                class="rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-white">
                <option value="">Todos os status</option>
                <option value="pending">Pendente</option>
                <option value="confirmed">Confirmado</option>
                <option value="completed">Realizado</option>
                <option value="cancelled">Cancelado</option>
                <option value="no_show">Não compareceu</option>
            </select>
        </div>

        <!-- Layout: Calendário + painel direito -->
        <div class="grid gap-5 lg:grid-cols-[1fr_320px]">
            <!-- Calendário -->
            <div class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/50 overflow-hidden">
                <!-- Header navegação -->
                <div class="flex items-center justify-between border-b border-zinc-100 px-5 py-3 dark:border-zinc-800">
                    <button type="button" class="rounded-lg p-1.5 text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-700" @click="prevMonth">
                        <ChevronLeft class="h-4 w-4" />
                    </button>
                    <h2 class="font-semibold capitalize text-zinc-900 dark:text-white">{{ monthName }}</h2>
                    <button type="button" class="rounded-lg p-1.5 text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-700" @click="nextMonth">
                        <ChevronRight class="h-4 w-4" />
                    </button>
                </div>

                <!-- Grid semana cabeçalho -->
                <div class="grid grid-cols-7 border-b border-zinc-100 dark:border-zinc-800">
                    <div v-for="d in ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb']" :key="d"
                        class="py-2 text-center text-xs font-semibold uppercase tracking-wide text-zinc-400">{{ d }}</div>
                </div>

                <!-- Grid dias -->
                <div class="grid grid-cols-7">
                    <button v-for="(day, idx) in calendarDays" :key="idx" type="button"
                        class="min-h-[72px] border-b border-r border-zinc-100 p-1.5 text-left transition last:border-r-0 dark:border-zinc-800"
                        :class="[
                            !day ? 'cursor-default bg-zinc-50 dark:bg-zinc-900/30' : 'hover:bg-blue-50 dark:hover:bg-blue-900/10',
                            day && dateStr(day) === todayStr ? 'bg-blue-50 dark:bg-blue-900/20' : '',
                            day && selectedDay === day ? 'ring-2 ring-inset ring-blue-400' : '',
                        ]"
                        @click="selectDay(day)">
                        <template v-if="day">
                            <span class="flex h-6 w-6 items-center justify-center rounded-full text-xs font-semibold"
                                :class="dateStr(day) === todayStr
                                    ? 'bg-blue-600 text-white'
                                    : 'text-zinc-700 dark:text-zinc-300'">
                                {{ day }}
                            </span>
                            <div class="mt-1 space-y-0.5">
                                <div v-for="a in appointmentsForDay(day).slice(0, 3)" :key="a.id"
                                    class="truncate rounded px-1 py-0.5 text-[10px] font-medium"
                                    :class="sc(a.status).color">
                                    {{ fmtTime(a.scheduled_time) }} {{ a.client_name }}
                                </div>
                                <p v-if="appointmentsForDay(day).length > 3"
                                    class="text-[10px] text-zinc-400">
                                    +{{ appointmentsForDay(day).length - 3 }} mais
                                </p>
                            </div>
                        </template>
                    </button>
                </div>
            </div>

            <!-- Painel direito: lista do dia ou detalhe -->
            <div class="space-y-4">
                <!-- Detalhe de um agendamento -->
                <div v-if="selectedAppointment"
                    class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/50 overflow-hidden">
                    <div class="flex items-center justify-between border-b border-zinc-100 px-4 py-3 dark:border-zinc-800">
                        <h3 class="font-semibold text-zinc-900 dark:text-white text-sm">Detalhes</h3>
                        <button type="button" class="text-zinc-400 hover:text-zinc-600" @click="selectedAppointment = null"><X class="h-4 w-4" /></button>
                    </div>
                    <div class="p-4 space-y-3">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold" :class="sc(selectedAppointment.status).color">
                                <component :is="sc(selectedAppointment.status).icon" class="h-3 w-3" />
                                {{ sc(selectedAppointment.status).label }}
                            </span>
                        </div>
                        <div class="space-y-2 text-sm">
                            <div class="flex items-center gap-2 text-zinc-700 dark:text-zinc-300">
                                <User class="h-3.5 w-3.5 shrink-0 text-zinc-400" />
                                <span class="font-medium">{{ selectedAppointment.client_name }}</span>
                            </div>
                            <div v-if="selectedAppointment.client_email" class="flex items-center gap-2 text-zinc-600 dark:text-zinc-400">
                                <Mail class="h-3.5 w-3.5 shrink-0 text-zinc-400" />
                                {{ selectedAppointment.client_email }}
                            </div>
                            <div v-if="selectedAppointment.client_phone" class="flex items-center gap-2 text-zinc-600 dark:text-zinc-400">
                                <Phone class="h-3.5 w-3.5 shrink-0 text-zinc-400" />
                                {{ selectedAppointment.client_phone }}
                            </div>
                            <div class="flex items-center gap-2 text-zinc-600 dark:text-zinc-400">
                                <CalendarDays class="h-3.5 w-3.5 shrink-0 text-zinc-400" />
                                {{ new Date(selectedAppointment.scheduled_date + 'T00:00:00').toLocaleDateString('pt-BR') }}
                                às {{ fmtTime(selectedAppointment.scheduled_time) }}
                            </div>
                            <div class="flex items-center gap-2 text-zinc-600 dark:text-zinc-400">
                                <Clock class="h-3.5 w-3.5 shrink-0 text-zinc-400" />
                                {{ selectedAppointment.duration_minutes }} min · {{ selectedAppointment.professional_name }}
                            </div>
                            <div v-if="selectedAppointment.notes" class="rounded-lg bg-zinc-50 p-2 text-xs text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400">
                                {{ selectedAppointment.notes }}
                            </div>
                            <div v-if="selectedAppointment.cancelled_reason" class="rounded-lg bg-red-50 p-2 text-xs text-red-700 dark:bg-red-900/20 dark:text-red-300">
                                Motivo: {{ selectedAppointment.cancelled_reason }}
                            </div>
                        </div>

                        <!-- Ações -->
                        <div class="flex flex-col gap-1.5 pt-1">
                            <Button size="sm" variant="outline" class="w-full justify-start" @click="openEdit(selectedAppointment)">
                                <Pencil class="mr-2 h-3.5 w-3.5" /> Editar
                            </Button>
                            <button v-if="!['completed','cancelled'].includes(selectedAppointment.status)"
                                type="button"
                                class="flex w-full items-center gap-2 rounded-xl border border-emerald-300 px-3 py-2 text-xs font-medium text-emerald-700 hover:bg-emerald-50 dark:border-emerald-700 dark:text-emerald-300"
                                @click="completeAppointment(selectedAppointment)">
                                <CheckCircle2 class="h-3.5 w-3.5" /> Marcar como realizado
                            </button>
                            <button v-if="!['completed','cancelled'].includes(selectedAppointment.status)"
                                type="button"
                                class="flex w-full items-center gap-2 rounded-xl border border-red-200 px-3 py-2 text-xs font-medium text-red-600 hover:bg-red-50 dark:border-red-900/50 dark:text-red-400"
                                @click="openCancel(selectedAppointment)">
                                <XCircle class="h-3.5 w-3.5" /> Cancelar
                            </button>
                            <button type="button"
                                class="flex w-full items-center gap-2 rounded-xl border border-zinc-200 px-3 py-2 text-xs font-medium text-zinc-500 hover:bg-zinc-50 dark:border-zinc-700"
                                @click="destroyAppointment(selectedAppointment)">
                                <Trash2 class="h-3.5 w-3.5" /> Excluir
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Lista do dia selecionado -->
                <div v-else-if="selectedDay !== null"
                    class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/50 overflow-hidden">
                    <div class="flex items-center justify-between border-b border-zinc-100 px-4 py-3 dark:border-zinc-800">
                        <h3 class="font-semibold text-zinc-900 dark:text-white text-sm">
                            {{ new Date(dateStr(selectedDay) + 'T00:00:00').toLocaleDateString('pt-BR', { weekday: 'long', day: 'numeric', month: 'long' }) }}
                        </h3>
                        <button type="button" class="rounded-lg bg-blue-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-blue-700" @click="openCreate">
                            <Plus class="inline h-3 w-3 mr-0.5" /> Novo
                        </button>
                    </div>

                    <div v-if="!dayAppointments.length" class="px-4 py-8 text-center text-sm text-zinc-400">
                        Nenhum agendamento neste dia.
                    </div>
                    <div v-else class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        <button v-for="a in dayAppointments" :key="a.id" type="button"
                            class="flex w-full items-start gap-3 px-4 py-3 text-left hover:bg-zinc-50 dark:hover:bg-zinc-700/50"
                            @click="openDetail(a)">
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full"
                                :class="sc(a.status).color">
                                <component :is="sc(a.status).icon" class="h-4 w-4" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-zinc-900 dark:text-white truncate">{{ a.client_name }}</p>
                                <p class="text-xs text-zinc-400">{{ fmtTime(a.scheduled_time) }} · {{ a.professional_name }}</p>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Próximos agendamentos (quando nenhum dia selecionado) -->
                <div v-else class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/50 overflow-hidden">
                    <div class="border-b border-zinc-100 px-4 py-3 dark:border-zinc-800">
                        <h3 class="font-semibold text-zinc-900 dark:text-white text-sm">Todos do mês</h3>
                    </div>
                    <div v-if="!appointments.length" class="px-4 py-8 text-center text-sm text-zinc-400">
                        Nenhum agendamento no mês.
                    </div>
                    <div v-else class="max-h-[420px] divide-y divide-zinc-100 overflow-y-auto dark:divide-zinc-800">
                        <button v-for="a in appointments" :key="a.id" type="button"
                            class="flex w-full items-start gap-3 px-4 py-3 text-left hover:bg-zinc-50 dark:hover:bg-zinc-700/50"
                            @click="openDetail(a)">
                            <div class="h-1.5 w-1.5 mt-1.5 shrink-0 rounded-full" :class="sc(a.status).dot" />
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-zinc-900 dark:text-white truncate">{{ a.client_name }}</p>
                                <p class="text-xs text-zinc-400">
                                    {{ new Date(a.scheduled_date + 'T00:00:00').toLocaleDateString('pt-BR', { day: '2-digit', month: 'short' }) }}
                                    às {{ fmtTime(a.scheduled_time) }} · {{ a.professional_name }}
                                </p>
                            </div>
                            <span class="shrink-0 rounded-full px-1.5 py-0.5 text-[10px] font-semibold" :class="sc(a.status).color">
                                {{ sc(a.status).label }}
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ─── Modal criar/editar ─────────────────────────────────────────────────── -->
    <Teleport to="body">
        <div v-if="apptModal" class="fixed inset-0 z-[200000] flex items-center justify-center bg-black/50 p-4" @click.self="apptModal = false">
            <div class="w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-zinc-900">
                <div class="flex items-center justify-between border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                    <h3 class="font-semibold text-zinc-900 dark:text-white">{{ editingApt ? 'Editar agendamento' : 'Novo agendamento' }}</h3>
                    <button type="button" class="rounded-lg p-1 text-zinc-400" @click="apptModal = false"><X class="h-4 w-4" /></button>
                </div>
                <div class="max-h-[70vh] space-y-4 overflow-y-auto p-5">
                    <!-- Profissional -->
                    <div>
                        <label class="mb-1 block text-xs font-medium text-zinc-500">Profissional *</label>
                        <select v-model="apptForm.professional_id"
                            class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-white">
                            <option v-for="p in professionals" :key="p.id" :value="p.id">{{ p.name }}{{ p.specialty ? ` — ${p.specialty}` : '' }}</option>
                        </select>
                    </div>

                    <!-- Nome + E-mail -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-zinc-500">Nome do cliente *</label>
                            <input v-model="apptForm.client_name" type="text"
                                class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-zinc-500">Telefone</label>
                            <input v-model="apptForm.client_phone" type="text"
                                class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-zinc-500">E-mail</label>
                        <input v-model="apptForm.client_email" type="email"
                            class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                    </div>

                    <!-- Data + Horário -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-zinc-500">Data *</label>
                            <input v-model="apptForm.scheduled_date" type="date"
                                class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-zinc-500">Horário *</label>
                            <div v-if="availSlots.length" class="space-y-1">
                                <select v-model="apptForm.scheduled_time"
                                    class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-white">
                                    <option value="">Selecionar slot</option>
                                    <option v-for="s in availSlots" :key="s" :value="s">{{ s }}</option>
                                </select>
                                <p class="text-[10px] text-zinc-400">Slots disponíveis da agenda</p>
                            </div>
                            <input v-else v-model="apptForm.scheduled_time" type="time"
                                class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                            <p v-if="loadingSlots" class="text-[10px] text-zinc-400 mt-0.5 flex items-center gap-1">
                                <Loader2 class="h-3 w-3 animate-spin" /> Verificando disponibilidade...
                            </p>
                        </div>
                    </div>

                    <!-- Duração + Status -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-zinc-500">Duração</label>
                            <select v-model.number="apptForm.duration_minutes"
                                class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-white">
                                <option :value="30">30 min</option>
                                <option :value="45">45 min</option>
                                <option :value="60">1 hora</option>
                                <option :value="90">1h30</option>
                                <option :value="120">2 horas</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-zinc-500">Status</label>
                            <select v-model="apptForm.status"
                                class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-white">
                                <option value="pending">Pendente</option>
                                <option value="confirmed">Confirmado</option>
                                <option value="completed">Realizado</option>
                                <option value="cancelled">Cancelado</option>
                                <option value="no_show">Não compareceu</option>
                            </select>
                        </div>
                    </div>

                    <!-- Observações -->
                    <div>
                        <label class="mb-1 block text-xs font-medium text-zinc-500">Observações</label>
                        <textarea v-model="apptForm.notes" rows="2"
                            class="w-full resize-none rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                    </div>

                    <div v-if="editingApt">
                        <label class="mb-1 block text-xs font-medium text-zinc-500">Notas internas (não visíveis ao cliente)</label>
                        <textarea v-model="apptForm.admin_notes" rows="2"
                            class="w-full resize-none rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                    </div>
                </div>
                <div class="flex justify-end gap-2 border-t border-zinc-200 px-5 py-3 dark:border-zinc-700">
                    <Button variant="outline" @click="apptModal = false">Cancelar</Button>
                    <Button :disabled="savingApt" @click="saveAppointment">
                        <Loader2 v-if="savingApt" class="mr-2 h-3.5 w-3.5 animate-spin" />
                        {{ editingApt ? 'Salvar' : 'Agendar' }}
                    </Button>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- ─── Modal cancelamento ─────────────────────────────────────────────────── -->
    <Teleport to="body">
        <div v-if="cancelModal" class="fixed inset-0 z-[300000] flex items-center justify-center bg-black/60 p-4" @click.self="cancelModal = false">
            <div class="w-full max-w-sm overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-zinc-900">
                <div class="flex items-center justify-between border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                    <h3 class="font-semibold text-zinc-900 dark:text-white">Cancelar agendamento</h3>
                    <button type="button" class="rounded-lg p-1 text-zinc-400" @click="cancelModal = false"><X class="h-4 w-4" /></button>
                </div>
                <div class="p-5 space-y-3">
                    <p class="text-sm text-zinc-700 dark:text-zinc-300">
                        Cancelar agendamento de <strong>{{ selectedAppointment?.client_name }}</strong>?
                    </p>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-zinc-500">Motivo (opcional)</label>
                        <textarea v-model="cancelReason" rows="3"
                            class="w-full resize-none rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                    </div>
                </div>
                <div class="flex justify-end gap-2 border-t border-zinc-200 px-5 py-3 dark:border-zinc-700">
                    <Button variant="outline" @click="cancelModal = false">Voltar</Button>
                    <Button variant="destructive" :disabled="cancelling" @click="confirmCancel">
                        <Loader2 v-if="cancelling" class="mr-2 h-3.5 w-3.5 animate-spin" />
                        Confirmar cancelamento
                    </Button>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- Toast -->
    <Teleport to="body">
        <Transition enter-active-class="transition duration-200" enter-from-class="translate-y-2 opacity-0"
            leave-active-class="transition duration-150" leave-to-class="translate-y-2 opacity-0">
            <div v-if="toast"
                class="fixed bottom-6 right-6 z-[99999] rounded-xl border px-4 py-3 text-sm shadow-lg"
                :class="toast.type === 'error'
                    ? 'border-red-200 bg-red-50 text-red-800 dark:border-red-900/50 dark:bg-red-900/20 dark:text-red-200'
                    : 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-900/20 dark:text-emerald-200'">
                {{ toast.msg }}
            </div>
        </Transition>
    </Teleport>
</template>
