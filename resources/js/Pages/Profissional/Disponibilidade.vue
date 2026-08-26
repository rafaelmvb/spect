<script setup>
import { ref, reactive } from 'vue';
import LayoutProfissional from '@/Layouts/LayoutProfissional.vue';
import { Save, Loader2, Clock, Coffee } from 'lucide-vue-next';

defineOptions({ layout: LayoutProfissional });

const props = defineProps({
    slots: { type: Array, default: () => [] },
});

const DAYS = [
    { value: 0, label: 'Domingo' },
    { value: 1, label: 'Segunda-feira' },
    { value: 2, label: 'Terça-feira' },
    { value: 3, label: 'Quarta-feira' },
    { value: 4, label: 'Quinta-feira' },
    { value: 5, label: 'Sexta-feira' },
    { value: 6, label: 'Sábado' },
];

function buildRow(day) {
    const ex = props.slots.find(s => s.day_of_week === day.value);
    return reactive({
        day_of_week: day.value,
        label:       day.label,
        is_active:   ex?.is_active   ?? false,
        start_time:  ex?.start_time  ?? '08:00',
        end_time:    ex?.end_time    ?? '18:00',
        has_lunch:   !!(ex?.lunch_start),
        lunch_start: ex?.lunch_start ?? '12:00',
        lunch_end:   ex?.lunch_end   ?? '13:00',
    });
}

const rows   = ref(DAYS.map(buildRow));
const saving = ref(false);
const toast  = ref(null);

function csrf() { return document.querySelector('meta[name="csrf-token"]')?.content ?? ''; }

async function save() {
    saving.value = true;
    try {
        const payload = rows.value.map(r => ({
            day_of_week: r.day_of_week,
            is_active:   r.is_active,
            start_time:  r.start_time,
            end_time:    r.end_time,
            lunch_start: r.is_active && r.has_lunch ? r.lunch_start : null,
            lunch_end:   r.is_active && r.has_lunch ? r.lunch_end   : null,
        }));
        const res = await window.axios.post('/p/disponibilidade', { slots: payload }, {
            headers: { 'X-CSRF-TOKEN': csrf() },
        });
        if (res.data.success) showToast('Disponibilidade salva com sucesso!');
    } catch (e) {
        showToast(e.response?.data?.message ?? 'Erro ao salvar.', 'error');
    } finally {
        saving.value = false;
    }
}

function showToast(msg, type = 'success') {
    toast.value = { msg, type };
    setTimeout(() => toast.value = null, 4000);
}
</script>

<template>
    <div class="max-w-3xl space-y-6">
        <!-- Header -->
        <div>
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Disponibilidade</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">
                Configure seus horários. A duração de cada consulta é definida pelo serviço agendado.
            </p>
        </div>

        <!-- Toast -->
        <div v-if="toast" class="rounded-xl px-4 py-3 text-sm font-medium"
            :class="toast.type === 'error'
                ? 'bg-red-50 text-red-700 border border-red-200 dark:bg-red-900/20 dark:text-red-400 dark:border-red-800'
                : 'bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-900/20 dark:text-emerald-400 dark:border-emerald-800'">
            {{ toast.msg }}
        </div>

        <!-- Dias -->
        <div class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60 divide-y divide-zinc-100 dark:divide-zinc-700/60">
            <div v-for="row in rows" :key="row.day_of_week" class="px-5 py-4 space-y-3"
                :class="!row.is_active && 'opacity-60'">

                <!-- Linha principal -->
                <div class="flex items-center gap-4 flex-wrap">
                    <!-- Toggle + label -->
                    <div class="w-44 flex items-center gap-3 shrink-0">
                        <button type="button" @click="row.is_active = !row.is_active"
                            class="relative inline-flex h-5 w-9 shrink-0 rounded-full transition-colors duration-200 focus:outline-none"
                            :class="row.is_active ? 'bg-violet-600' : 'bg-zinc-200 dark:bg-zinc-700'">
                            <span class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow transform ring-0 transition duration-200 ease-in-out"
                                :class="row.is_active ? 'translate-x-4' : 'translate-x-0'" />
                        </button>
                        <span class="text-sm font-medium text-zinc-900 dark:text-white whitespace-nowrap">{{ row.label }}</span>
                    </div>

                    <!-- Horários -->
                    <div class="flex items-center gap-2" :class="!row.is_active && 'pointer-events-none'">
                        <Clock class="h-3.5 w-3.5 text-zinc-400 shrink-0" />
                        <input v-model="row.start_time" type="time"
                            class="rounded-lg border border-zinc-200 bg-zinc-50 px-2 py-1.5 text-sm focus:border-violet-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-900 dark:text-white" />
                        <span class="text-zinc-400 text-sm">até</span>
                        <input v-model="row.end_time" type="time"
                            class="rounded-lg border border-zinc-200 bg-zinc-50 px-2 py-1.5 text-sm focus:border-violet-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-900 dark:text-white" />
                    </div>

                    <!-- Toggle almoço -->
                    <div v-if="row.is_active" class="flex items-center gap-2 ml-auto">
                        <Coffee class="h-3.5 w-3.5 text-zinc-400 shrink-0" />
                        <button type="button" @click="row.has_lunch = !row.has_lunch"
                            class="relative inline-flex h-5 w-9 shrink-0 rounded-full transition-colors focus:outline-none"
                            :class="row.has_lunch ? 'bg-amber-500' : 'bg-zinc-200 dark:bg-zinc-700'">
                            <span class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow transform transition duration-200"
                                :class="row.has_lunch ? 'translate-x-4' : 'translate-x-0'" />
                        </button>
                        <span class="text-xs text-zinc-500 dark:text-zinc-400 whitespace-nowrap">Almoço</span>
                    </div>
                </div>

                <!-- Linha de almoço (expandida) -->
                <div v-if="row.is_active && row.has_lunch" class="flex items-center gap-2 pl-48 flex-wrap">
                    <Coffee class="h-3.5 w-3.5 text-amber-500 shrink-0" />
                    <span class="text-xs text-amber-600 dark:text-amber-400 font-medium">Pausa:</span>
                    <input v-model="row.lunch_start" type="time"
                        class="rounded-lg border border-amber-200 bg-amber-50 px-2 py-1 text-sm focus:border-amber-500 focus:outline-none dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-200" />
                    <span class="text-zinc-400 text-sm">até</span>
                    <input v-model="row.lunch_end" type="time"
                        class="rounded-lg border border-amber-200 bg-amber-50 px-2 py-1 text-sm focus:border-amber-500 focus:outline-none dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-200" />
                </div>
            </div>
        </div>

        <p class="text-xs text-zinc-400 dark:text-zinc-500">
            A duração de cada consulta é determinada pelo serviço agendado. Configure os serviços em <strong>Serviços</strong>.
        </p>

        <div class="flex justify-end">
            <button type="button" @click="save" :disabled="saving"
                class="flex items-center gap-2 rounded-xl bg-violet-600 hover:bg-violet-700 disabled:opacity-60 px-6 py-2.5 text-sm font-semibold text-white transition">
                <Loader2 v-if="saving" class="h-4 w-4 animate-spin" />
                <Save v-else class="h-4 w-4" />
                {{ saving ? 'Salvando…' : 'Salvar disponibilidade' }}
            </button>
        </div>
    </div>
</template>
