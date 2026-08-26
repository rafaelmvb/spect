<script setup>
import { ref, computed } from 'vue';
import LayoutProfissional from '@/Layouts/LayoutProfissional.vue';
import { ChevronLeft, ChevronRight, CalendarDays, Check, X, CheckCheck } from 'lucide-vue-next';
import { router } from '@inertiajs/vue3';

defineOptions({ layout: LayoutProfissional });

const props = defineProps({
    appointments: { type: Array, default: () => [] },
    year:         { type: Number, required: true },
    month:        { type: Number, required: true },
});

const STATUS_LABELS = { pending: 'Pendente', confirmed: 'Confirmado', completed: 'Realizado', cancelled: 'Cancelado', no_show: 'Não compareceu' };
const STATUS_COLORS = {
    pending:   'bg-amber-100 text-amber-700',
    confirmed: 'bg-blue-100 text-blue-700',
    completed: 'bg-emerald-100 text-emerald-700',
    cancelled: 'bg-red-100 text-red-700',
    no_show:   'bg-zinc-100 text-zinc-500',
};

const selected = ref(null);

// Calendário
const monthDate = computed(() => new Date(props.year, props.month - 1, 1));
const monthName = computed(() => monthDate.value.toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' }));

const daysInMonth = computed(() => new Date(props.year, props.month, 0).getDate());
const firstDow    = computed(() => monthDate.value.getDay());

const calendar = computed(() => {
    const cells = [];
    for (let i = 0; i < firstDow.value; i++) cells.push(null);
    for (let d = 1; d <= daysInMonth.value; d++) cells.push(d);
    return cells;
});

function dateStr(day) {
    return `${props.year}-${String(props.month).padStart(2,'0')}-${String(day).padStart(2,'0')}`;
}

function aptsForDay(day) {
    const ds = dateStr(day);
    return props.appointments.filter(a => a.scheduled_date === ds);
}

function prevMonth() {
    let m = props.month - 1, y = props.year;
    if (m < 1) { m = 12; y--; }
    router.visit(`/p/agenda?year=${y}&month=${m}`, { preserveScroll: true });
}

function nextMonth() {
    let m = props.month + 1, y = props.year;
    if (m > 12) { m = 1; y++; }
    router.visit(`/p/agenda?year=${y}&month=${m}`, { preserveScroll: true });
}

function csrf() { return document.querySelector('meta[name="csrf-token"]')?.content ?? ''; }

async function updateStatus(id, status) {
    try {
        await window.axios.put(`/p/agenda/${id}`, { status }, { headers: { 'X-CSRF-TOKEN': csrf() } });
        router.reload({ only: ['appointments'] });
        selected.value = null;
    } catch {}
}
</script>

<template>
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Agenda</h1>
                <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">Gerencie seus agendamentos.</p>
            </div>
            <div class="flex items-center gap-2">
                <button @click="prevMonth" class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-2 hover:bg-zinc-100 dark:hover:bg-zinc-800">
                    <ChevronLeft class="h-4 w-4 text-zinc-600 dark:text-zinc-300" />
                </button>
                <span class="text-sm font-semibold text-zinc-900 dark:text-white capitalize w-36 text-center">{{ monthName }}</span>
                <button @click="nextMonth" class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-2 hover:bg-zinc-100 dark:hover:bg-zinc-800">
                    <ChevronRight class="h-4 w-4 text-zinc-600 dark:text-zinc-300" />
                </button>
            </div>
        </div>

        <!-- Calendário -->
        <div class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60 overflow-hidden">
            <!-- Cabeçalho dias -->
            <div class="grid grid-cols-7 border-b border-zinc-100 dark:border-zinc-700">
                <div v-for="d in ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb']" :key="d"
                    class="py-2 text-center text-xs font-semibold text-zinc-400 uppercase tracking-wide">{{ d }}</div>
            </div>
            <!-- Células -->
            <div class="grid grid-cols-7 gap-px bg-zinc-100 dark:bg-zinc-700">
                <div v-for="(day, i) in calendar" :key="i"
                    class="bg-white dark:bg-zinc-800 min-h-[80px] p-2"
                    :class="day && aptsForDay(day).length ? 'cursor-pointer hover:bg-violet-50 dark:hover:bg-violet-900/10' : ''">
                    <template v-if="day">
                        <p class="text-xs font-medium text-zinc-600 dark:text-zinc-400 mb-1">{{ day }}</p>
                        <div class="space-y-0.5">
                            <div v-for="a in aptsForDay(day)" :key="a.id" @click="selected = a"
                                class="rounded px-1 py-0.5 text-[10px] font-semibold truncate cursor-pointer"
                                :class="STATUS_COLORS[a.status]">
                                {{ a.scheduled_time }} {{ a.client_name }}
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- Detalhe agendamento selecionado -->
        <Teleport to="body">
            <div v-if="selected" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40" @click.self="selected = null">
                <div class="w-full max-w-sm rounded-2xl bg-white dark:bg-zinc-900 shadow-2xl">
                    <div class="flex items-center justify-between px-5 py-4 border-b border-zinc-100 dark:border-zinc-800">
                        <h2 class="font-semibold text-zinc-900 dark:text-white">Agendamento</h2>
                        <button @click="selected = null" class="rounded-lg p-1 text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800"><X class="h-5 w-5" /></button>
                    </div>
                    <div class="p-5 space-y-3">
                        <div>
                            <p class="text-xs font-medium text-zinc-400 uppercase tracking-wide">Cliente</p>
                            <p class="font-semibold text-zinc-900 dark:text-white">{{ selected.client_name }}</p>
                            <p class="text-sm text-zinc-500">{{ selected.client_email }}</p>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <p class="text-xs font-medium text-zinc-400 uppercase tracking-wide">Data</p>
                                <p class="text-sm text-zinc-900 dark:text-white">{{ new Date(selected.scheduled_date + 'T00:00:00').toLocaleDateString('pt-BR') }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-medium text-zinc-400 uppercase tracking-wide">Horário</p>
                                <p class="text-sm text-zinc-900 dark:text-white">{{ selected.scheduled_time }}</p>
                            </div>
                        </div>
                        <div v-if="selected.service_name">
                            <p class="text-xs font-medium text-zinc-400 uppercase tracking-wide">Serviço</p>
                            <p class="text-sm text-zinc-900 dark:text-white">{{ selected.service_name }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-zinc-400 uppercase tracking-wide mb-1">Status</p>
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="STATUS_COLORS[selected.status]">
                                {{ STATUS_LABELS[selected.status] }}
                            </span>
                        </div>
                        <div v-if="selected.notes">
                            <p class="text-xs font-medium text-zinc-400 uppercase tracking-wide">Obs. do cliente</p>
                            <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ selected.notes }}</p>
                        </div>
                    </div>
                    <!-- Ações -->
                    <div v-if="selected.status === 'pending' || selected.status === 'confirmed'" class="flex gap-2 px-5 pb-5 flex-wrap">
                        <button v-if="selected.status === 'pending'" @click="updateStatus(selected.id, 'confirmed')"
                            class="flex items-center gap-1.5 rounded-xl bg-blue-600 hover:bg-blue-700 px-3 py-2 text-xs font-semibold text-white">
                            <Check class="h-3.5 w-3.5" /> Confirmar
                        </button>
                        <button v-if="selected.status === 'confirmed'" @click="updateStatus(selected.id, 'completed')"
                            class="flex items-center gap-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 px-3 py-2 text-xs font-semibold text-white">
                            <CheckCheck class="h-3.5 w-3.5" /> Marcar realizado
                        </button>
                        <button @click="updateStatus(selected.id, 'cancelled')"
                            class="flex items-center gap-1.5 rounded-xl bg-red-100 hover:bg-red-200 text-red-700 px-3 py-2 text-xs font-semibold">
                            <X class="h-3.5 w-3.5" /> Cancelar
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
