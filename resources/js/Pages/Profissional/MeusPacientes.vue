<script setup>
import { ref, computed } from 'vue';
import LayoutProfissional from '@/Layouts/LayoutProfissional.vue';
import { Users, Tag, Save, ChevronDown, ChevronUp } from 'lucide-vue-next';

defineOptions({ layout: LayoutProfissional });

const props = defineProps({
    patients: { type: Array, default: () => [] },
});

// ── Pacientes ─────────────────────────────────────────────────────────
const patients = ref([...props.patients]);

// ── Abrir / fechar detalhes de cada paciente ──────────────────────────
const openId = ref(null);
function toggle(id) { openId.value = openId.value === id ? null : id; }

// ── Aba dentro do painel do paciente ─────────────────────────────────
const activeTab = ref({});
function tab(id) { return activeTab.value[id] ?? 'humor'; }
function setTab(id, t) { activeTab.value = { ...activeTab.value, [id]: t }; }

// ── Nota clínica ──────────────────────────────────────────────────────
const noteText  = ref({});
const noteSaving = ref({});
const noteSaved  = ref({});

function initNote(patient) {
    if (noteText.value[patient.id] === undefined) {
        noteText.value[patient.id] = patient.clinical_note ?? '';
    }
}

async function saveNote(patient) {
    if (!noteText.value[patient.id] && noteText.value[patient.id] !== '') return;
    noteSaving.value[patient.id] = true;
    try {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
        await fetch(`/p/meus-pacientes/${patient.id}/nota`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
            body: JSON.stringify({ note: noteText.value[patient.id], product_id: patient.product_id }),
        });
        noteSaved.value[patient.id] = true;
        setTimeout(() => { noteSaved.value[patient.id] = false; }, 2500);
    } finally {
        noteSaving.value[patient.id] = false;
    }
}

// ── Gráfico de humor (sparkline simples SVG) ──────────────────────────
function moodPath(moods) {
    if (!moods?.length) return '';
    const w = 240, h = 48, pad = 8;
    const xs = moods.map((_, i) => pad + (i / Math.max(moods.length - 1, 1)) * (w - pad * 2));
    const ys = moods.map(m => h - pad - ((m.mood - 1) / 4) * (h - pad * 2));
    return xs.map((x, i) => `${i === 0 ? 'M' : 'L'}${x.toFixed(1)},${ys[i].toFixed(1)}`).join(' ');
}
</script>

<template>
    <div class="max-w-3xl mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Meus Pacientes</h1>
            <p class="text-sm text-zinc-500 mt-1">Pacientes vinculados automaticamente após a 1ª consulta concluída.</p>
        </div>

        <!-- Lista de pacientes -->
        <div v-if="!patients.length" class="text-center py-16 text-zinc-400">
            <Users class="h-10 w-10 mx-auto mb-3 opacity-40" />
            <p>Nenhum paciente vinculado ainda.</p>
        </div>

        <div v-for="patient in patients" :key="patient.id" class="mb-3">
            <!-- Cabeçalho do card -->
            <button type="button" @click="() => { toggle(patient.id); initNote(patient); }"
                class="w-full flex items-center gap-3 rounded-2xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-4 text-left transition hover:border-violet-300 dark:hover:border-violet-600">
                <div class="h-10 w-10 rounded-full bg-violet-100 dark:bg-violet-900/40 flex items-center justify-center flex-shrink-0">
                    <span class="text-sm font-bold text-violet-700 dark:text-violet-300">{{ patient.name.charAt(0).toUpperCase() }}</span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-semibold text-zinc-900 dark:text-white truncate">{{ patient.name }}</p>
                    <p class="text-xs text-zinc-500 truncate">{{ patient.email }}</p>
                </div>
                <ChevronUp v-if="openId === patient.id" class="h-4 w-4 text-zinc-400 flex-shrink-0" />
                <ChevronDown v-else class="h-4 w-4 text-zinc-400 flex-shrink-0" />
            </button>

            <!-- Detalhes expandidos -->
            <div v-if="openId === patient.id"
                class="border border-t-0 border-zinc-200 dark:border-zinc-700 rounded-b-2xl bg-white dark:bg-zinc-900 px-4 pb-4">

                <!-- Tabs -->
                <div class="flex gap-1 pt-3 pb-4 border-b border-zinc-100 dark:border-zinc-800">
                    <button v-for="t in [['humor','Humor'], ['testes','Testes'], ['nota','Nota Clínica']]" :key="t[0]"
                        type="button" @click="setTab(patient.id, t[0])"
                        :class="['px-3 py-1.5 rounded-lg text-xs font-semibold transition', tab(patient.id) === t[0] ? 'bg-violet-600 text-white' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300']">
                        {{ t[1] }}
                    </button>
                </div>

                <!-- Tab: Humor -->
                <div v-if="tab(patient.id) === 'humor'" class="pt-3">
                    <p class="text-xs font-semibold uppercase tracking-widest text-zinc-400 mb-3">Histórico de humor</p>
                    <div v-if="!patient.moods?.length" class="text-sm text-zinc-400">Nenhum check-in registrado ainda.</div>
                    <div v-else>
                        <svg width="240" height="48" style="overflow:visible;">
                            <defs>
                                <linearGradient id="moodGrad" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="#7c3aed" stop-opacity="0.4"/>
                                    <stop offset="100%" stop-color="#7c3aed" stop-opacity="0"/>
                                </linearGradient>
                            </defs>
                            <path :d="moodPath(patient.moods) + ' V48 H8 Z'" fill="url(#moodGrad)" />
                            <path :d="moodPath(patient.moods)" fill="none" stroke="#7c3aed" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        <div class="flex justify-between mt-1">
                            <span class="text-[10px] text-zinc-400">{{ patient.moods[0]?.date }}</span>
                            <span class="text-[10px] text-zinc-400">{{ patient.moods[patient.moods.length - 1]?.date }}</span>
                        </div>
                    </div>
                </div>

                <!-- Tab: Testes -->
                <div v-if="tab(patient.id) === 'testes'" class="pt-3">
                    <p class="text-xs font-semibold uppercase tracking-widest text-zinc-400 mb-3">Testes concluídos</p>
                    <div v-if="!patient.tests?.length" class="text-sm text-zinc-400">Nenhum teste concluído ainda.</div>
                    <div v-for="t in patient.tests" :key="t.test_name" class="mb-3 rounded-xl border border-zinc-100 dark:border-zinc-800 p-3">
                        <div class="flex items-center justify-between mb-1">
                            <p class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">{{ t.test_name }}</p>
                            <span class="text-xs text-zinc-400">{{ t.completed_at }}</span>
                        </div>
                        <p v-if="t.result_label" class="text-sm font-bold text-violet-600 dark:text-violet-400 mb-1.5">{{ t.result_label }}</p>
                        <div v-if="t.challenge_tags?.length" class="flex flex-wrap gap-1.5">
                            <span v-for="tag in t.challenge_tags" :key="tag"
                                class="flex items-center gap-1 text-[11px] font-semibold px-2 py-0.5 rounded-full border border-violet-200 dark:border-violet-700 text-violet-700 dark:text-violet-300">
                                <Tag class="h-2.5 w-2.5" />{{ tag }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Tab: Nota Clínica (PRIVADA — nunca visível ao paciente) -->
                <div v-if="tab(patient.id) === 'nota'" class="pt-3">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-xs font-semibold uppercase tracking-widest text-zinc-400">Nota clínica privada</p>
                        <span class="text-[10px] font-semibold text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20 px-2 py-0.5 rounded-full">Nunca compartilhada</span>
                    </div>
                    <textarea v-model="noteText[patient.id]" rows="5" placeholder="Anotações clínicas privadas sobre este paciente..."
                        class="w-full rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 p-3 text-sm text-zinc-900 dark:text-white resize-none focus:outline-none focus:ring-2 focus:ring-violet-500 mb-2" />
                    <button type="button" @click="saveNote(patient)" :disabled="noteSaving[patient.id]"
                        :class="['flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold transition', noteSaved[patient.id] ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-violet-600 hover:bg-violet-700 text-white disabled:opacity-50']">
                        <Save class="h-4 w-4" />
                        {{ noteSaved[patient.id] ? 'Salvo!' : noteSaving[patient.id] ? 'Salvando…' : 'Salvar nota' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
