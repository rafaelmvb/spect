<script setup>
import { ref, watch, computed, onMounted } from 'vue';
import LayoutInfoprodutor from '@/Layouts/LayoutInfoprodutor.vue';
import Button from '@/components/ui/Button.vue';
import VueApexCharts from 'vue3-apexcharts';
import {
    BrainCircuit, Plus, Pencil, Trash2, X, Loader2,
    ChevronDown, ChevronUp, Activity, Users, BarChart3,
    RefreshCw, PlusCircle, Settings, TrendingUp, Calendar
} from 'lucide-vue-next';

defineOptions({ layout: LayoutInfoprodutor });

const props = defineProps({
    areas:           { type: Array, default: () => [] },
    stats:           { type: Object, default: () => ({}) },
    students:        { type: Array, default: () => [] },
    usersWithScores: { type: Array, default: () => [] },
});

// ─── Tab principal ────────────────────────────────────────────────────────────
const mainTab = ref('config'); // 'config' | 'analytics'

// ─── Toast ────────────────────────────────────────────────────────────────────
const toast = ref(null);
function showToast(msg, type = 'success') {
    toast.value = { msg, type };
    setTimeout(() => toast.value = null, 4000);
}
function csrfToken() { return document.querySelector('meta[name="csrf-token"]')?.content ?? ''; }
const h = () => ({ 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' });

// ─── Estado local das áreas ───────────────────────────────────────────────────
const localAreas = ref(props.areas.map(a => ({ ...a, _expanded: true })));

// ─── Paleta de cores ──────────────────────────────────────────────────────────
const colorPresets = [
    '#6366f1','#8b5cf6','#ec4899','#f43f5e','#f97316',
    '#eab308','#22c55e','#14b8a6','#06b6d4','#3b82f6',
];

const iconPresets = ['Brain','Heart','Zap','Star','Sun','Moon','Flame','Shield','Eye','Anchor'];

// ─── Modal de Área ────────────────────────────────────────────────────────────
const areaModal   = ref(false);
const editingArea = ref(null);
const savingArea  = ref(false);

const emptyAreaForm = () => ({
    name: '', description: '', color: '#6366f1', icon: 'Brain', is_active: true,
});
const areaForm = ref(emptyAreaForm());

function openCreateArea() {
    editingArea.value = null;
    areaForm.value = emptyAreaForm();
    areaModal.value = true;
}

function openEditArea(a) {
    editingArea.value = a;
    areaForm.value = {
        name: a.name, description: a.description ?? '',
        color: a.color, icon: a.icon, is_active: a.is_active,
    };
    areaModal.value = true;
}

async function saveArea() {
    if (!areaForm.value.name.trim()) { showToast('Nome é obrigatório.', 'error'); return; }
    savingArea.value = true;
    try {
        if (editingArea.value) {
            const { data } = await window.axios.put(`/mapa-neuro/areas/${editingArea.value.id}`, areaForm.value, { headers: h() });
            const idx = localAreas.value.findIndex(a => a.id === editingArea.value.id);
            if (idx >= 0) localAreas.value[idx] = { ...data.area, _expanded: localAreas.value[idx]._expanded };
            showToast('Área atualizada.');
        } else {
            const { data } = await window.axios.post('/mapa-neuro/areas', areaForm.value, { headers: h() });
            localAreas.value.push({ ...data.area, _expanded: true });
            showToast('Área criada.');
        }
        areaModal.value = false;
    } catch (e) {
        showToast(e.response?.data?.message ?? 'Erro ao salvar.', 'error');
    } finally {
        savingArea.value = false;
    }
}

async function destroyArea(a) {
    if (!confirm(`Excluir área "${a.name}"? Todos os indicadores e scores serão removidos.`)) return;
    try {
        await window.axios.delete(`/mapa-neuro/areas/${a.id}`, { headers: h() });
        localAreas.value = localAreas.value.filter(x => x.id !== a.id);
        showToast('Área excluída.');
    } catch { showToast('Erro.', 'error'); }
}

// ─── Modal de Indicador ───────────────────────────────────────────────────────
const indModal    = ref(false);
const editingInd  = ref(null);
const parentArea  = ref(null);
const savingInd   = ref(false);

const emptyIndForm = () => ({
    name: '', description: '', scale_min: 0, scale_max: 10, is_active: true,
});
const indForm = ref(emptyIndForm());

function openCreateInd(area) {
    editingInd.value = null;
    parentArea.value = area;
    indForm.value = emptyIndForm();
    indModal.value = true;
}

function openEditInd(area, ind) {
    editingInd.value = ind;
    parentArea.value = area;
    indForm.value = {
        name: ind.name, description: ind.description ?? '',
        scale_min: ind.scale_min, scale_max: ind.scale_max, is_active: ind.is_active,
    };
    indModal.value = true;
}

async function saveIndicator() {
    if (!indForm.value.name.trim()) { showToast('Nome é obrigatório.', 'error'); return; }
    savingInd.value = true;
    try {
        const areaId = parentArea.value.id;
        if (editingInd.value) {
            const { data } = await window.axios.put(
                `/mapa-neuro/areas/${areaId}/indicators/${editingInd.value.id}`,
                indForm.value, { headers: h() }
            );
            const aIdx = localAreas.value.findIndex(a => a.id === areaId);
            if (aIdx >= 0) {
                const iIdx = localAreas.value[aIdx].indicators.findIndex(i => i.id === editingInd.value.id);
                if (iIdx >= 0) localAreas.value[aIdx].indicators[iIdx] = data.indicator;
            }
            showToast('Indicador atualizado.');
        } else {
            const { data } = await window.axios.post(
                `/mapa-neuro/areas/${areaId}/indicators`,
                indForm.value, { headers: h() }
            );
            const aIdx = localAreas.value.findIndex(a => a.id === areaId);
            if (aIdx >= 0) localAreas.value[aIdx].indicators.push(data.indicator);
            showToast('Indicador criado.');
        }
        indModal.value = false;
    } catch (e) {
        showToast(e.response?.data?.message ?? 'Erro ao salvar.', 'error');
    } finally {
        savingInd.value = false;
    }
}

async function destroyIndicator(area, ind) {
    if (!confirm(`Excluir "${ind.name}"?`)) return;
    try {
        await window.axios.delete(`/mapa-neuro/areas/${area.id}/indicators/${ind.id}`, { headers: h() });
        const aIdx = localAreas.value.findIndex(a => a.id === area.id);
        if (aIdx >= 0) localAreas.value[aIdx].indicators = localAreas.value[aIdx].indicators.filter(i => i.id !== ind.id);
        showToast('Indicador excluído.');
    } catch { showToast('Erro.', 'error'); }
}

// ─── Analytics ────────────────────────────────────────────────────────────────
const selectedUser   = ref('');
const selectedPeriod = ref(90);
const loadingAnalytics = ref(false);
const analyticsData  = ref(null);

async function loadAnalytics() {
    loadingAnalytics.value = true;
    try {
        const params = new URLSearchParams({ period: selectedPeriod.value });
        if (selectedUser.value) params.set('user_id', selectedUser.value);
        const { data } = await window.axios.get(`/mapa-neuro/analytics?${params}`, { headers: h() });
        analyticsData.value = data;
    } catch { showToast('Erro ao carregar analytics.', 'error'); }
    finally { loadingAnalytics.value = false; }
}

watch(mainTab, (t) => {
    if (t === 'analytics' && !analyticsData.value) loadAnalytics();
});

// ─── ApexCharts radar ─────────────────────────────────────────────────────────
const isDark = computed(() => document.documentElement.classList.contains('dark'));

const radarOptions = computed(() => ({
    chart: {
        type: 'radar',
        toolbar: { show: false },
        background: 'transparent',
        animations: { enabled: true, speed: 400 },
    },
    colors: ['#6366f1'],
    fill: { opacity: 0.25 },
    stroke: { width: 2 },
    markers: { size: 4 },
    xaxis: { categories: analyticsData.value?.radar?.labels ?? [] },
    yaxis: { min: 0, max: 100, tickAmount: 5, labels: { formatter: v => v + '%' } },
    tooltip: { y: { formatter: v => v + '%' } },
    theme: { mode: isDark.value ? 'dark' : 'light' },
    plotOptions: {
        radar: {
            polygons: {
                strokeColors: isDark.value ? '#3f3f46' : '#e4e4e7',
                fill: { colors: [isDark.value ? '#18181b' : '#fafafa', isDark.value ? '#27272a' : '#f4f4f5'] },
            },
        },
    },
}));

const radarSeries = computed(() => [{
    name: selectedUser.value
        ? (props.usersWithScores.find(u => u.id == selectedUser.value)?.name ?? 'Usuário')
        : 'Média geral',
    data: analyticsData.value?.radar?.data ?? [],
}]);

// ─── ApexCharts linha ─────────────────────────────────────────────────────────
const lineOptions = computed(() => ({
    chart: {
        type: 'line',
        toolbar: { show: false },
        background: 'transparent',
        zoom: { enabled: false },
    },
    stroke: { curve: 'smooth', width: 2 },
    markers: { size: 3 },
    xaxis: { type: 'datetime', labels: { datetimeUTC: false } },
    yaxis: { min: 0, max: 100, labels: { formatter: v => v + '%' } },
    tooltip: { x: { format: 'dd/MM/yyyy' }, y: { formatter: v => v?.toFixed(1) + '%' } },
    legend: { position: 'top' },
    theme: { mode: isDark.value ? 'dark' : 'light' },
    colors: (analyticsData.value?.timeline ?? []).map(s => s.color),
    grid: { borderColor: isDark.value ? '#3f3f46' : '#e4e4e7' },
}));

const lineSeries = computed(() =>
    (analyticsData.value?.timeline ?? []).map(s => ({
        name: s.name,
        data: s.data.map(p => ({ x: new Date(p.x).getTime(), y: p.y })),
    }))
);

// ─── Lançar score manual ─────────────────────────────────────────────────────
const scoreModal   = ref(false);
const savingScore  = ref(false);
const scoreForm    = ref({
    user_id: '', neuro_indicator_id: '', value: '', notes: '', scored_at: '',
});

const allIndicators = computed(() =>
    localAreas.value.flatMap(a =>
        (a.indicators ?? []).filter(i => i.is_active).map(i => ({
            ...i, area_name: a.name, area_color: a.color,
            label: `${a.name} → ${i.name} (${i.scale_min}–${i.scale_max})`,
        }))
    )
);

const selectedIndicator = computed(() =>
    allIndicators.value.find(i => i.id == scoreForm.value.neuro_indicator_id)
);

async function saveScore() {
    if (!scoreForm.value.user_id || !scoreForm.value.neuro_indicator_id || scoreForm.value.value === '') {
        showToast('Preencha usuário, indicador e valor.', 'error'); return;
    }
    savingScore.value = true;
    try {
        await window.axios.post('/mapa-neuro/scores', scoreForm.value, { headers: h() });
        showToast('Score registrado.');
        scoreModal.value = false;
        if (mainTab.value === 'analytics') loadAnalytics();
    } catch (e) {
        showToast(e.response?.data?.message ?? 'Erro ao salvar score.', 'error');
    } finally {
        savingScore.value = false;
    }
}

// ─── Helpers ─────────────────────────────────────────────────────────────────
function fmtDate(d) {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('pt-BR', { day: '2-digit', month: 'short', year: 'numeric' });
}
</script>

<template>
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-violet-100 dark:bg-violet-900/30">
                    <BrainCircuit class="h-5 w-5 text-violet-600 dark:text-violet-400" />
                </div>
                <div>
                    <h1 class="text-xl font-bold text-zinc-900 dark:text-white">Mapa Neurofuncional</h1>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">Configure áreas emocionais e visualize a evolução dos alunos.</p>
                </div>
            </div>
            <div class="flex gap-2">
                <Button variant="outline" @click="scoreModal = true">
                    <Plus class="mr-2 h-4 w-4" /> Lançar score
                </Button>
                <Button @click="openCreateArea">
                    <Plus class="mr-2 h-4 w-4" /> Nova área
                </Button>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
                <p class="text-xs font-medium uppercase tracking-wide text-zinc-500">Áreas</p>
                <p class="mt-1 text-2xl font-bold tabular-nums text-violet-600">{{ stats.areas ?? 0 }}</p>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
                <p class="text-xs font-medium uppercase tracking-wide text-zinc-500">Indicadores</p>
                <p class="mt-1 text-2xl font-bold tabular-nums text-zinc-900 dark:text-white">{{ stats.indicators ?? 0 }}</p>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
                <p class="text-xs font-medium uppercase tracking-wide text-zinc-500">Avaliações</p>
                <p class="mt-1 text-2xl font-bold tabular-nums text-emerald-600">{{ stats.scores ?? 0 }}</p>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
                <p class="text-xs font-medium uppercase tracking-wide text-zinc-500">Alunos</p>
                <p class="mt-1 text-2xl font-bold tabular-nums text-blue-600">{{ stats.users ?? 0 }}</p>
            </div>
        </div>

        <!-- Tabs principais -->
        <div class="flex gap-1 rounded-2xl bg-zinc-100 p-1 dark:bg-zinc-800 max-w-sm">
            <button type="button"
                class="flex-1 rounded-xl py-2 text-sm font-medium transition"
                :class="mainTab === 'config'
                    ? 'bg-white text-zinc-900 shadow dark:bg-zinc-700 dark:text-white'
                    : 'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400'"
                @click="mainTab = 'config'">
                <Settings class="mr-1.5 inline h-4 w-4" /> Configuração
            </button>
            <button type="button"
                class="flex-1 rounded-xl py-2 text-sm font-medium transition"
                :class="mainTab === 'analytics'
                    ? 'bg-white text-zinc-900 shadow dark:bg-zinc-700 dark:text-white'
                    : 'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400'"
                @click="mainTab = 'analytics'">
                <BarChart3 class="mr-1.5 inline h-4 w-4" /> Análises
            </button>
        </div>

        <!-- ─── Tab Configuração ──────────────────────────────────────────────── -->
        <template v-if="mainTab === 'config'">
            <div v-if="!localAreas.length"
                class="flex flex-col items-center justify-center gap-3 rounded-2xl border-2 border-dashed border-zinc-200 py-20 dark:border-zinc-700">
                <BrainCircuit class="h-12 w-12 text-zinc-300 dark:text-zinc-600" />
                <p class="text-zinc-400">Nenhuma área configurada.</p>
                <Button variant="outline" @click="openCreateArea"><Plus class="mr-2 h-4 w-4" /> Criar primeira área</Button>
            </div>

            <div v-else class="space-y-4">
                <div v-for="area in localAreas" :key="area.id"
                    class="overflow-hidden rounded-2xl border border-zinc-200 dark:border-zinc-700">
                    <!-- Área header -->
                    <div class="flex items-center gap-3 px-5 py-4"
                        :style="`border-left: 4px solid ${area.color}`">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full"
                            :style="`background: ${area.color}22`">
                            <span class="text-sm font-bold" :style="`color: ${area.color}`">
                                {{ area.name.charAt(0) }}
                            </span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <h3 class="font-semibold text-zinc-900 dark:text-white">{{ area.name }}</h3>
                                <span v-if="!area.is_active" class="rounded-full bg-zinc-100 px-2 py-0.5 text-[10px] font-bold text-zinc-500 dark:bg-zinc-700">Inativa</span>
                            </div>
                            <p v-if="area.description" class="text-xs text-zinc-500 truncate">{{ area.description }}</p>
                            <p class="text-xs text-zinc-400 mt-0.5">{{ area.indicators?.length ?? 0 }} indicador{{ (area.indicators?.length ?? 0) !== 1 ? 'es' : '' }}</p>
                        </div>
                        <div class="flex shrink-0 items-center gap-1.5">
                            <Button size="sm" variant="outline" @click="openCreateInd(area)">
                                <PlusCircle class="mr-1 h-3.5 w-3.5" /> Indicador
                            </Button>
                            <button type="button" class="rounded-lg p-1.5 text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800"
                                @click="openEditArea(area)"><Pencil class="h-4 w-4" /></button>
                            <button type="button" class="rounded-lg p-1.5 text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20"
                                @click="destroyArea(area)"><Trash2 class="h-4 w-4" /></button>
                            <button type="button" class="rounded-lg p-1.5 text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800"
                                @click="area._expanded = !area._expanded">
                                <ChevronDown v-if="!area._expanded" class="h-4 w-4" />
                                <ChevronUp v-else class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <!-- Indicadores -->
                    <div v-if="area._expanded && area.indicators?.length"
                        class="border-t border-zinc-100 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900/30">
                        <div v-for="ind in area.indicators" :key="ind.id"
                            class="flex items-center gap-3 border-b border-zinc-100 px-5 py-3 last:border-0 dark:border-zinc-800">
                            <div class="h-2 w-2 shrink-0 rounded-full" :style="`background:${area.color}`" />
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ ind.name }}</p>
                                <p class="text-xs text-zinc-400">Escala {{ ind.scale_min }}–{{ ind.scale_max }}</p>
                            </div>
                            <span v-if="!ind.is_active" class="shrink-0 rounded-full bg-zinc-100 px-2 py-0.5 text-[10px] font-bold text-zinc-400 dark:bg-zinc-700">Inativo</span>
                            <div class="flex shrink-0 items-center gap-1">
                                <button type="button" class="rounded p-1 text-zinc-400 hover:text-zinc-600"
                                    @click="openEditInd(area, ind)"><Pencil class="h-3.5 w-3.5" /></button>
                                <button type="button" class="rounded p-1 text-red-400 hover:text-red-600"
                                    @click="destroyIndicator(area, ind)"><Trash2 class="h-3.5 w-3.5" /></button>
                            </div>
                        </div>
                    </div>

                    <div v-else-if="area._expanded && !area.indicators?.length"
                        class="border-t border-zinc-100 px-5 py-4 text-center dark:border-zinc-800">
                        <p class="text-xs text-zinc-400">Nenhum indicador. <button type="button" class="text-violet-600 hover:underline" @click="openCreateInd(area)">Adicionar</button></p>
                    </div>
                </div>
            </div>
        </template>

        <!-- ─── Tab Analytics ────────────────────────────────────────────────── -->
        <template v-if="mainTab === 'analytics'">
            <!-- Filtros -->
            <div class="flex flex-wrap items-end gap-3 rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
                <div class="flex-1 min-w-[180px]">
                    <label class="mb-1 block text-xs font-medium text-zinc-500">Usuário</label>
                    <select v-model="selectedUser"
                        class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm focus:border-violet-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white">
                        <option value="">Todos os usuários (média)</option>
                        <option v-for="u in usersWithScores" :key="u.id" :value="u.id">{{ u.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-zinc-500">Período</label>
                    <select v-model="selectedPeriod"
                        class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm focus:border-violet-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white">
                        <option :value="30">30 dias</option>
                        <option :value="90">90 dias</option>
                        <option :value="180">6 meses</option>
                        <option :value="365">1 ano</option>
                    </select>
                </div>
                <Button @click="loadAnalytics" :disabled="loadingAnalytics">
                    <RefreshCw class="mr-2 h-4 w-4" :class="loadingAnalytics ? 'animate-spin' : ''" />
                    Atualizar
                </Button>
            </div>

            <!-- Loading -->
            <div v-if="loadingAnalytics" class="flex justify-center py-16">
                <Loader2 class="h-8 w-8 animate-spin text-violet-500" />
            </div>

            <!-- Sem dados -->
            <div v-else-if="analyticsData && !analyticsData.radar?.data?.length"
                class="flex flex-col items-center gap-3 rounded-2xl border-2 border-dashed border-zinc-200 py-16 text-center dark:border-zinc-700">
                <Activity class="h-10 w-10 text-zinc-300 dark:text-zinc-600" />
                <p class="text-zinc-400">Nenhum dado de avaliação no período selecionado.</p>
                <Button variant="outline" @click="scoreModal = true"><Plus class="mr-2 h-4 w-4" /> Lançar primeiro score</Button>
            </div>

            <template v-else-if="analyticsData">
                <div class="grid gap-6 lg:grid-cols-2">
                    <!-- Radar -->
                    <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-800/50">
                        <h2 class="mb-1 font-semibold text-zinc-900 dark:text-white">Mapa por área</h2>
                        <p class="mb-4 text-xs text-zinc-400">Média normalizada 0–100% por área emocional</p>
                        <VueApexCharts
                            v-if="analyticsData.radar?.labels?.length"
                            type="radar"
                            height="320"
                            :options="radarOptions"
                            :series="radarSeries" />
                    </div>

                    <!-- Linha -->
                    <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-800/50">
                        <h2 class="mb-1 font-semibold text-zinc-900 dark:text-white">Evolução longitudinal</h2>
                        <p class="mb-4 text-xs text-zinc-400">Pontuação média por área ao longo do tempo</p>
                        <VueApexCharts
                            v-if="lineSeries.length"
                            type="line"
                            height="320"
                            :options="lineOptions"
                            :series="lineSeries" />
                        <div v-else class="flex h-64 items-center justify-center text-sm text-zinc-400">
                            Dados insuficientes para o gráfico de evolução.
                        </div>
                    </div>
                </div>

                <!-- Usuários recentes (apenas visão geral) -->
                <div v-if="!selectedUser && analyticsData.recentUsers?.length"
                    class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/50">
                    <div class="border-b border-zinc-100 px-5 py-3 dark:border-zinc-800">
                        <h2 class="font-semibold text-zinc-900 dark:text-white">Atividade recente (últimos 30 dias)</h2>
                    </div>
                    <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        <div v-for="u in analyticsData.recentUsers" :key="u.user_id"
                            class="flex items-center gap-3 px-5 py-3">
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-violet-100 dark:bg-violet-900/40">
                                <span class="text-xs font-bold text-violet-700 dark:text-violet-300">{{ u.name?.[0] ?? '?' }}</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-zinc-900 dark:text-white truncate">{{ u.name }}</p>
                                <p class="text-xs text-zinc-400">{{ u.email }}</p>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-sm font-semibold tabular-nums text-zinc-700 dark:text-zinc-300">{{ u.score_count }} scores</p>
                                <p class="text-xs text-zinc-400">{{ fmtDate(u.last_scored) }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </template>
    </div>

    <!-- ─── Modal Área ─────────────────────────────────────────────────────────── -->
    <Teleport to="body">
        <div v-if="areaModal" class="fixed inset-0 z-[200000] flex items-center justify-center bg-black/50 p-4" @click.self="areaModal = false">
            <div class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-zinc-900">
                <div class="flex items-center justify-between border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                    <h3 class="font-semibold text-zinc-900 dark:text-white">{{ editingArea ? 'Editar área' : 'Nova área' }}</h3>
                    <button type="button" class="rounded-lg p-1 text-zinc-400" @click="areaModal = false"><X class="h-4 w-4" /></button>
                </div>
                <div class="space-y-4 p-5">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-zinc-500">Nome *</label>
                        <input v-model="areaForm.name" type="text" placeholder="Ex: Regulação Emocional"
                            class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm focus:border-violet-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-zinc-500">Descrição</label>
                        <textarea v-model="areaForm.description" rows="2"
                            class="w-full resize-none rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm focus:border-violet-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                    </div>
                    <div>
                        <label class="mb-2 block text-xs font-medium text-zinc-500">Cor</label>
                        <div class="flex flex-wrap gap-2">
                            <button v-for="c in colorPresets" :key="c" type="button"
                                class="h-7 w-7 rounded-full border-2 transition"
                                :style="`background:${c}`"
                                :class="areaForm.color === c ? 'border-zinc-900 dark:border-white scale-110' : 'border-transparent'"
                                @click="areaForm.color = c" />
                            <input v-model="areaForm.color" type="color"
                                class="h-7 w-7 cursor-pointer rounded-full border-0 p-0" title="Cor personalizada" />
                        </div>
                    </div>
                    <label class="flex cursor-pointer items-center gap-3">
                        <button type="button"
                            class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                            :style="areaForm.is_active ? `background:${areaForm.color}` : ''"
                            :class="!areaForm.is_active ? 'bg-zinc-300 dark:bg-zinc-600' : ''"
                            @click="areaForm.is_active = !areaForm.is_active">
                            <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"
                                :class="areaForm.is_active ? 'translate-x-6' : 'translate-x-1'" />
                        </button>
                        <span class="text-sm text-zinc-700 dark:text-zinc-300">{{ areaForm.is_active ? 'Ativa' : 'Inativa' }}</span>
                    </label>
                </div>
                <div class="flex justify-end gap-2 border-t border-zinc-200 px-5 py-3 dark:border-zinc-700">
                    <Button variant="outline" @click="areaModal = false">Cancelar</Button>
                    <Button :disabled="savingArea" @click="saveArea">
                        <Loader2 v-if="savingArea" class="mr-2 h-3.5 w-3.5 animate-spin" />
                        {{ editingArea ? 'Salvar' : 'Criar' }}
                    </Button>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- ─── Modal Indicador ────────────────────────────────────────────────────── -->
    <Teleport to="body">
        <div v-if="indModal" class="fixed inset-0 z-[200000] flex items-center justify-center bg-black/50 p-4" @click.self="indModal = false">
            <div class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-zinc-900">
                <div class="flex items-center justify-between border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                    <div>
                        <h3 class="font-semibold text-zinc-900 dark:text-white">{{ editingInd ? 'Editar indicador' : 'Novo indicador' }}</h3>
                        <p class="text-xs text-zinc-400">{{ parentArea?.name }}</p>
                    </div>
                    <button type="button" class="rounded-lg p-1 text-zinc-400" @click="indModal = false"><X class="h-4 w-4" /></button>
                </div>
                <div class="space-y-4 p-5">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-zinc-500">Nome *</label>
                        <input v-model="indForm.name" type="text" placeholder="Ex: Ansiedade, Energia, Clareza mental..."
                            class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm focus:border-violet-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-zinc-500">Descrição</label>
                        <textarea v-model="indForm.description" rows="2"
                            class="w-full resize-none rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm focus:border-violet-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-zinc-500">Escala mínima</label>
                            <input v-model.number="indForm.scale_min" type="number" min="0" max="99"
                                class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm focus:border-violet-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-zinc-500">Escala máxima</label>
                            <input v-model.number="indForm.scale_max" type="number" min="1" max="100"
                                class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm focus:border-violet-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                        </div>
                    </div>
                    <p class="text-xs text-zinc-400">Exemplo: escala 0–10 para ansiedade, 1–5 para humor. O sistema normaliza automaticamente para 0–100%.</p>
                    <label class="flex cursor-pointer items-center gap-3">
                        <input type="checkbox" v-model="indForm.is_active"
                            class="h-4 w-4 rounded border-zinc-300 text-violet-600 focus:ring-violet-500" />
                        <span class="text-sm text-zinc-700 dark:text-zinc-300">Indicador ativo</span>
                    </label>
                </div>
                <div class="flex justify-end gap-2 border-t border-zinc-200 px-5 py-3 dark:border-zinc-700">
                    <Button variant="outline" @click="indModal = false">Cancelar</Button>
                    <Button :disabled="savingInd" @click="saveIndicator">
                        <Loader2 v-if="savingInd" class="mr-2 h-3.5 w-3.5 animate-spin" />
                        {{ editingInd ? 'Salvar' : 'Criar' }}
                    </Button>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- ─── Modal Score Manual ─────────────────────────────────────────────────── -->
    <Teleport to="body">
        <div v-if="scoreModal" class="fixed inset-0 z-[200000] flex items-center justify-center bg-black/50 p-4" @click.self="scoreModal = false">
            <div class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-zinc-900">
                <div class="flex items-center justify-between border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                    <h3 class="font-semibold text-zinc-900 dark:text-white">Lançar avaliação</h3>
                    <button type="button" class="rounded-lg p-1 text-zinc-400" @click="scoreModal = false"><X class="h-4 w-4" /></button>
                </div>
                <div class="space-y-4 p-5">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-zinc-500">Aluno *</label>
                        <select v-model="scoreForm.user_id"
                            class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm focus:border-violet-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white">
                            <option value="">Selecionar aluno</option>
                            <option v-for="u in students" :key="u.id" :value="u.id">{{ u.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-zinc-500">Indicador *</label>
                        <select v-model="scoreForm.neuro_indicator_id"
                            class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm focus:border-violet-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white">
                            <option value="">Selecionar indicador</option>
                            <option v-for="i in allIndicators" :key="i.id" :value="i.id">{{ i.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-zinc-500">
                            Valor *
                            <template v-if="selectedIndicator">
                                ({{ selectedIndicator.scale_min }}–{{ selectedIndicator.scale_max }})
                            </template>
                        </label>
                        <input v-model="scoreForm.value" type="number"
                            :min="selectedIndicator?.scale_min ?? 0"
                            :max="selectedIndicator?.scale_max ?? 10"
                            step="0.1"
                            class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm focus:border-violet-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-zinc-500">Data</label>
                        <input v-model="scoreForm.scored_at" type="date"
                            class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm focus:border-violet-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                        <p class="mt-1 text-xs text-zinc-400">Em branco = hoje</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-zinc-500">Observações</label>
                        <textarea v-model="scoreForm.notes" rows="2"
                            class="w-full resize-none rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm focus:border-violet-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                    </div>
                </div>
                <div class="flex justify-end gap-2 border-t border-zinc-200 px-5 py-3 dark:border-zinc-700">
                    <Button variant="outline" @click="scoreModal = false">Cancelar</Button>
                    <Button :disabled="savingScore" @click="saveScore">
                        <Loader2 v-if="savingScore" class="mr-2 h-3.5 w-3.5 animate-spin" />
                        Registrar
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
