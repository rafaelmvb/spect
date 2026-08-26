<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Button from '@/components/ui/Button.vue';
import { Upload, Trash2, Loader2 } from 'lucide-vue-next';

const loading   = ref(true);
const saving    = ref(false);
const error     = ref('');
const success   = ref('');
const uploading = ref(false);

const form = reactive({
    area_name:             '',
    logo_url:              '',
    favicon_url:           '',
    primary_color:         '#6FCF00',
    on_primary_color:      '#0D1F2D',
    bg_color:              '#090F18',
    surface_color:         '#132435',
    surface2_color:        '#1A3040',
    border_color:          '#1E3448',
    text_color:            '#FFFFFF',
    text2_color:           '#7A9BAD',
    text_inactive_color:   '#4A6A7A',
    danger_color:          '#E05555',
    // Componentes estruturais
    sidebar_bg_color:      '',
    header_bg_color:       '',
    nav_bg_color:          '',
    nav_active_bg_color:   '',
    nav_active_text_color: '',
    theme_color:           '',
    button_hover_bg_color:   '',
    button_hover_text_color: '',
});

const logoInput    = ref(null);
const faviconInput = ref(null);

const themes = [
    {
        name: 'Padrão',
        label: 'Verde neon · fundo azul marinho',
        primary_color: '#6FCF00', on_primary_color: '#0D1F2D',
        bg_color: '#090F18', surface_color: '#132435', surface2_color: '#1A3040',
        border_color: '#1E3448', text_color: '#FFFFFF', text2_color: '#7A9BAD',
        text_inactive_color: '#4A6A7A', danger_color: '#E05555',
    },
    {
        name: 'Oceano',
        label: 'Ciano elétrico · fundo navy',
        primary_color: '#00BCD4', on_primary_color: '#001F2D',
        bg_color: '#040D14', surface_color: '#0A1929', surface2_color: '#0F2540',
        border_color: '#163350', text_color: '#E8F4FD', text2_color: '#6B9DB8',
        text_inactive_color: '#3D6880', danger_color: '#EF5350',
    },
    {
        name: 'Violeta',
        label: 'Roxo vibrante · fundo escuro',
        primary_color: '#9C6FFF', on_primary_color: '#1A0B3D',
        bg_color: '#0D0814', surface_color: '#180E2B', surface2_color: '#221540',
        border_color: '#2D1E55', text_color: '#F0EBFF', text2_color: '#8B7BAD',
        text_inactive_color: '#5A4A7A', danger_color: '#E05555',
    },
    {
        name: 'Âmbar',
        label: 'Dourado quente · fundo carvão',
        primary_color: '#F59E0B', on_primary_color: '#1A0F00',
        bg_color: '#0D0A04', surface_color: '#1A1510', surface2_color: '#231E17',
        border_color: '#2E271E', text_color: '#FFF8E7', text2_color: '#A0907A',
        text_inactive_color: '#6A5C48', danger_color: '#EF4444',
    },
    {
        name: 'Esmeralda',
        label: 'Verde menta · fundo floresta',
        primary_color: '#10B981', on_primary_color: '#032317',
        bg_color: '#060E0A', surface_color: '#0D1F17', surface2_color: '#122A1E',
        border_color: '#1A3828', text_color: '#ECFDF5', text2_color: '#6B9B84',
        text_inactive_color: '#3D6B56', danger_color: '#E05555',
    },
    {
        name: 'Carvão',
        label: 'Branco puro · fundo preto',
        primary_color: '#FFFFFF', on_primary_color: '#111111',
        bg_color: '#0A0A0A', surface_color: '#141414', surface2_color: '#1C1C1C',
        border_color: '#252525', text_color: '#FFFFFF', text2_color: '#888888',
        text_inactive_color: '#555555', danger_color: '#E05555',
    },
    {
        name: 'Rosa Neon',
        label: 'Fúcsia · fundo grafite escuro',
        primary_color: '#FF2D78', on_primary_color: '#FFFFFF',
        bg_color: '#0C0812', surface_color: '#160D20', surface2_color: '#1E122C',
        border_color: '#2A1840', text_color: '#FFF0F6', text2_color: '#A07890',
        text_inactive_color: '#6A4A60', danger_color: '#FF5555',
    },
    {
        name: 'Safira',
        label: 'Azul royal · fundo escuro quente',
        primary_color: '#4F8EF7', on_primary_color: '#050E22',
        bg_color: '#070A12', surface_color: '#0F1626', surface2_color: '#172033',
        border_color: '#1E2C44', text_color: '#E8EEFF', text2_color: '#6878A8',
        text_inactive_color: '#3E4E78', danger_color: '#E05555',
    },
];

// ── Contraste ──────────────────────────────────────────────────────────────
function hexLuminance(hex) {
    if (!hex || !hex.startsWith('#') || hex.length < 7) return 0.5;
    const r = parseInt(hex.slice(1,3), 16) / 255;
    const g = parseInt(hex.slice(3,5), 16) / 255;
    const b = parseInt(hex.slice(5,7), 16) / 255;
    const lin = v => v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4;
    return 0.2126 * lin(r) + 0.7152 * lin(g) + 0.0722 * lin(b);
}

function contrastRatio(hex1, hex2) {
    const l1 = hexLuminance(hex1), l2 = hexLuminance(hex2);
    const [lo, hi] = l1 < l2 ? [l1, l2] : [l2, l1];
    return (hi + 0.05) / (lo + 0.05);
}

function contrastOk(bg, fg, min = 3.5) {
    if (!bg?.startsWith('#') || !fg?.startsWith('#')) return null;
    return contrastRatio(bg, fg) >= min;
}

const contrastChecks = computed(() => {
    const checks = [
        { label: 'Texto principal sobre superfície',          ok: contrastOk(form.surface_color, form.text_color) },
        { label: 'Texto principal sobre fundo da página',     ok: contrastOk(form.bg_color, form.text_color) },
        { label: 'Texto sobre cor primária (botões)',         ok: contrastOk(form.primary_color, form.on_primary_color) },
        { label: 'Primária distinguível da superfície',       ok: contrastOk(form.primary_color, form.surface_color) },
    ];
    if (form.sidebar_bg_color)
        checks.push({ label: 'Texto sobre fundo do menu lateral', ok: contrastOk(form.sidebar_bg_color, form.text_color) });
    if (form.header_bg_color)
        checks.push({ label: 'Texto sobre fundo do cabeçalho',    ok: contrastOk(form.header_bg_color, form.text_color) });
    if (form.nav_active_bg_color)
        checks.push({ label: 'Texto ativo sobre fundo ativo',     ok: contrastOk(form.nav_active_bg_color, form.nav_active_text_color || form.text_color) });
    return checks;
});

const hasConflict = computed(() => contrastChecks.value.some(c => c.ok === false));

function applyTheme(theme) {
    const colorKeys = [
        'primary_color', 'on_primary_color', 'bg_color', 'surface_color',
        'surface2_color', 'border_color', 'text_color', 'text2_color',
        'text_inactive_color', 'danger_color',
    ];
    for (const key of colorKeys) {
        if (theme[key]) form[key] = theme[key];
    }
    // theme_color herda da primária
    form.theme_color = theme.primary_color || '';
    // limpa overrides de componentes → herdam surface_color (controller remove o valor antigo ao receber '')
    form.sidebar_bg_color      = '';
    form.header_bg_color       = '';
    form.nav_bg_color          = '';
    form.nav_active_bg_color   = '';
    form.nav_active_text_color = '';
}
const page = usePage();

function apiUrl(path) {
    try {
        const appUrl = String(page.props.app_url || '');
        const prefix = appUrl ? new URL(appUrl).pathname.replace(/\/$/, '') : '';
        return `${window.location.origin}${prefix}${path}`;
    } catch {
        return path;
    }
}

async function load() {
    loading.value = true;
    error.value = '';
    try {
        const res = await window.axios.get(apiUrl('/configuracoes/area-aluno/data'));
        const b = res.data?.branding ?? {};
        const fields = [
            'area_name', 'logo_url', 'favicon_url',
            'primary_color', 'on_primary_color', 'bg_color',
            'surface_color', 'surface2_color', 'border_color',
            'text_color', 'text2_color', 'text_inactive_color', 'danger_color',
            'sidebar_bg_color', 'header_bg_color', 'nav_bg_color',
            'nav_active_bg_color', 'nav_active_text_color', 'theme_color',
            'button_hover_bg_color', 'button_hover_text_color',
        ];
        for (const key of fields) {
            if (b[key]) form[key] = b[key];
        }
    } catch (e) {
        error.value = e?.response?.data?.message || 'Não foi possível carregar as configurações.';
    } finally {
        loading.value = false;
    }
}

async function save() {
    saving.value = true;
    error.value = '';
    success.value = '';
    try {
        await window.axios.put(apiUrl('/configuracoes/area-aluno'), { ...form });
        success.value = 'Configurações salvas com sucesso!';
        setTimeout(() => (success.value = ''), 3000);
    } catch (e) {
        error.value = e?.response?.data?.message || 'Erro ao salvar.';
    } finally {
        saving.value = false;
    }
}

async function uploadLogo(event) {
    const file = event.target.files?.[0];
    if (!file) return;
    uploading.value = true;
    error.value = '';
    try {
        const fd = new FormData();
        fd.append('logo', file);
        const res = await window.axios.post(apiUrl('/configuracoes/area-aluno/upload'), fd);
        form.logo_url = res.data.url ?? '';
    } catch (e) {
        error.value = e?.response?.data?.message || e?.response?.data?.errors?.logo?.[0] || 'Falha no upload.';
    } finally {
        uploading.value = false;
        if (logoInput.value) logoInput.value.value = '';
    }
}

function clearLogo() {
    form.logo_url = '';
}

async function uploadFavicon(event) {
    const file = event.target.files?.[0];
    if (!file) return;
    uploading.value = true;
    error.value = '';
    try {
        const fd = new FormData();
        fd.append('logo', file);
        const res = await window.axios.post(apiUrl('/configuracoes/area-aluno/upload'), fd);
        form.favicon_url = res.data.url ?? '';
    } catch (e) {
        error.value = e?.response?.data?.message || e?.response?.data?.errors?.logo?.[0] || 'Falha no upload do favicon.';
    } finally {
        uploading.value = false;
        if (faviconInput.value) faviconInput.value.value = '';
    }
}

function clearFavicon() {
    form.favicon_url = '';
}

onMounted(load);
</script>

<template>
    <div class="space-y-8">
        <div>
            <h2 class="text-base font-semibold text-zinc-900 dark:text-white">Área do Aluno</h2>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Configure a aparência padrão da área do aluno para todos os cursos.</p>
        </div>

        <div v-if="loading" class="flex items-center gap-2 text-sm text-zinc-500">
            <Loader2 class="h-4 w-4 animate-spin" /> Carregando...
        </div>

        <template v-else>
            <p v-if="error" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-400">{{ error }}</p>
            <p v-if="success" class="rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">{{ success }}</p>

            <!-- ── Temas prontos ── -->
            <section class="space-y-3">
                <h3 class="border-b border-zinc-200 pb-2 text-sm font-semibold text-zinc-700 dark:border-zinc-700 dark:text-zinc-300">Temas prontos</h3>
                <p class="text-xs text-zinc-400">Clique para aplicar. Você pode ajustar qualquer cor depois.</p>
                <div class="grid gap-3 grid-cols-2 sm:grid-cols-3 lg:grid-cols-4">
                    <button
                        v-for="theme in themes"
                        :key="theme.name"
                        type="button"
                        class="group relative overflow-hidden rounded-2xl border-2 text-left transition-all hover:scale-[1.02] active:scale-[0.98]"
                        :style="{ borderColor: theme.border_color, background: theme.bg_color }"
                        @click="applyTheme(theme)"
                    >
                        <!-- mini header -->
                        <div class="flex items-center gap-1.5 px-2.5 py-2" :style="{ background: theme.surface_color }">
                            <div class="h-1.5 w-1.5 rounded-full" :style="{ background: theme.primary_color }" />
                            <div class="h-1.5 flex-1 rounded-full opacity-50" :style="{ background: theme.text2_color }" />
                            <div class="h-5 w-5 rounded-full text-[7px] font-bold flex items-center justify-center" :style="{ background: theme.primary_color, color: theme.on_primary_color }">A</div>
                        </div>
                        <!-- mini body -->
                        <div class="flex gap-1.5 p-2">
                            <!-- sidebar strip -->
                            <div class="w-8 rounded-lg py-1.5 flex flex-col gap-1 px-1" :style="{ background: theme.surface_color }">
                                <div class="h-1.5 rounded-full" :style="{ background: theme.primary_color, opacity: 0.9 }" />
                                <div class="h-1.5 rounded-full opacity-40" :style="{ background: theme.text2_color }" />
                                <div class="h-1.5 rounded-full opacity-40" :style="{ background: theme.text2_color }" />
                            </div>
                            <!-- content -->
                            <div class="flex-1 space-y-1">
                                <div class="rounded-lg p-1.5" :style="{ background: theme.surface_color }">
                                    <div class="mb-1 h-1.5 w-3/4 rounded-full" :style="{ background: theme.text_color }" />
                                    <div class="h-1 w-1/2 rounded-full opacity-50" :style="{ background: theme.text2_color }" />
                                    <div class="mt-1.5 inline-block rounded px-1.5 py-0.5 text-[7px] font-bold" :style="{ background: theme.primary_color, color: theme.on_primary_color }">▶</div>
                                </div>
                            </div>
                        </div>
                        <!-- label -->
                        <div class="border-t px-2.5 py-1.5" :style="{ borderColor: theme.border_color }">
                            <div class="text-[11px] font-semibold" :style="{ color: theme.text_color }">{{ theme.name }}</div>
                            <div class="text-[9px] opacity-60" :style="{ color: theme.text2_color }">{{ theme.label }}</div>
                        </div>
                        <!-- hover overlay -->
                        <div class="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center" :style="{ background: theme.primary_color + '22' }">
                            <span class="rounded-full px-2 py-0.5 text-[10px] font-bold" :style="{ background: theme.primary_color, color: theme.on_primary_color }">Aplicar</span>
                        </div>
                    </button>
                </div>
            </section>

            <!-- ── Identidade ── -->
            <section class="space-y-4">
                <h3 class="border-b border-zinc-200 pb-2 text-sm font-semibold text-zinc-700 dark:border-zinc-700 dark:text-zinc-300">Identidade</h3>

                <div>
                    <label class="mb-1 block text-xs font-medium text-zinc-600 dark:text-zinc-400">Nome da área</label>
                    <input
                        v-model="form.area_name"
                        type="text"
                        placeholder="Ex: Área de Membros, Portal do Aluno..."
                        class="block w-full rounded-xl border border-zinc-300 bg-white px-4 py-2.5 text-sm text-zinc-900 placeholder-zinc-400 transition focus:border-[var(--color-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]/20 dark:border-zinc-600 dark:bg-zinc-800 dark:text-white dark:placeholder-zinc-500"
                    />
                    <p class="mt-1 text-xs text-zinc-400">Exibido no cabeçalho e em e-mails.</p>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-medium text-zinc-600 dark:text-zinc-400">Logo</label>
                    <div v-if="form.logo_url" class="mb-3 flex items-center gap-3">
                        <div class="flex h-14 w-36 items-center justify-center overflow-hidden rounded-xl border border-zinc-200 bg-zinc-900 p-2 dark:border-zinc-700">
                            <img :src="form.logo_url" alt="Logo" class="max-h-full max-w-full object-contain" />
                        </div>
                        <button type="button" class="flex items-center gap-1 rounded-lg px-2 py-1.5 text-xs font-medium text-red-600 transition hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20" @click="clearLogo">
                            <Trash2 class="h-3.5 w-3.5" /> Remover
                        </button>
                    </div>
                    <div class="flex items-center gap-3">
                        <input
                            v-model="form.logo_url"
                            type="url"
                            placeholder="https://... ou clique para fazer upload"
                            class="block flex-1 rounded-xl border border-zinc-300 bg-white px-4 py-2.5 text-sm text-zinc-900 placeholder-zinc-400 transition focus:border-[var(--color-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]/20 dark:border-zinc-600 dark:bg-zinc-800 dark:text-white dark:placeholder-zinc-500"
                        />
                        <label class="flex cursor-pointer items-center gap-1.5 rounded-xl border border-zinc-300 bg-white px-3 py-2.5 text-sm font-medium text-zinc-700 transition hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700">
                            <Loader2 v-if="uploading" class="h-4 w-4 animate-spin" />
                            <Upload v-else class="h-4 w-4" />
                            Upload
                            <input ref="logoInput" type="file" class="sr-only" accept="image/png,image/jpeg,image/webp,image/svg+xml" @change="uploadLogo" />
                        </label>
                    </div>
                    <p class="mt-1 text-xs text-zinc-400">PNG, JPG, SVG ou WebP. Máximo 2 MB.</p>
                </div>
            </section>

            <!-- ── Botões ── -->
            <section class="space-y-3">
                <h3 class="border-b border-zinc-200 pb-2 text-sm font-semibold text-zinc-700 dark:border-zinc-700 dark:text-zinc-300">Botões</h3>
                <p class="text-xs text-zinc-400">Cores dos botões primários em estado normal e ao passar o mouse (hover).</p>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">

                    <!-- Fundo normal -->
                    <div class="rounded-xl border p-3 transition-colors"
                         :class="contrastOk(form.primary_color, form.surface_color) === false ? 'border-red-400 bg-red-50 dark:border-red-700 dark:bg-red-900/20' : 'border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800/60'">
                        <label class="mb-2 flex items-center gap-1.5 text-xs font-medium text-zinc-600 dark:text-zinc-400">
                            Fundo do botão
                            <span v-if="contrastOk(form.primary_color, form.surface_color) === false" class="text-[9px] font-bold text-red-600 dark:text-red-400">≈ superfície!</span>
                        </label>
                        <div class="flex items-center gap-2">
                            <input v-model="form.primary_color" type="color" class="h-9 w-10 cursor-pointer rounded-lg border border-zinc-300 dark:border-zinc-600" />
                            <input v-model="form.primary_color" type="text" class="flex-1 rounded-lg border border-zinc-300 bg-white px-2 py-1.5 font-mono text-xs dark:border-zinc-600 dark:bg-zinc-700 dark:text-white" placeholder="#6FCF00" />
                        </div>
                        <div class="mt-2 flex h-7 items-center justify-center rounded-lg text-[10px] font-bold"
                             :style="{ background: form.primary_color, color: form.on_primary_color }">
                            Normal
                        </div>
                    </div>

                    <!-- Texto normal -->
                    <div class="rounded-xl border p-3 transition-colors"
                         :class="contrastOk(form.primary_color, form.on_primary_color) === false ? 'border-red-400 bg-red-50 dark:border-red-700 dark:bg-red-900/20' : 'border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800/60'">
                        <label class="mb-2 flex items-center gap-1.5 text-xs font-medium text-zinc-600 dark:text-zinc-400">
                            Texto do botão
                            <span v-if="contrastOk(form.primary_color, form.on_primary_color) === false" class="text-[9px] font-bold text-red-600 dark:text-red-400">Contraste baixo!</span>
                        </label>
                        <div class="flex items-center gap-2">
                            <input v-model="form.on_primary_color" type="color" class="h-9 w-10 cursor-pointer rounded-lg border border-zinc-300 dark:border-zinc-600" />
                            <input v-model="form.on_primary_color" type="text" class="flex-1 rounded-lg border border-zinc-300 bg-white px-2 py-1.5 font-mono text-xs dark:border-zinc-600 dark:bg-zinc-700 dark:text-white" placeholder="#0D1F2D" />
                        </div>
                        <div class="mt-2 flex h-7 items-center justify-center rounded-lg text-[10px] font-bold"
                             :style="{ background: form.primary_color, color: form.on_primary_color }">
                            Normal
                        </div>
                    </div>

                    <!-- Fundo hover -->
                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-800/60">
                        <label class="mb-2 block text-xs font-medium text-zinc-600 dark:text-zinc-400">Fundo (hover)</label>
                        <div class="flex items-center gap-2">
                            <input v-model="form.button_hover_bg_color" type="color" class="h-9 w-10 cursor-pointer rounded-lg border border-zinc-300 dark:border-zinc-600" />
                            <input v-model="form.button_hover_bg_color" type="text" class="flex-1 rounded-lg border border-zinc-300 bg-white px-2 py-1.5 font-mono text-xs dark:border-zinc-600 dark:bg-zinc-700 dark:text-white" placeholder="herda fundo" />
                        </div>
                        <div class="mt-2 flex h-7 items-center justify-center rounded-lg text-[10px] font-bold"
                             :style="{ background: form.button_hover_bg_color || form.primary_color, color: form.button_hover_text_color || form.on_primary_color }">
                            Hover
                        </div>
                    </div>

                    <!-- Texto hover -->
                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-800/60">
                        <label class="mb-2 block text-xs font-medium text-zinc-600 dark:text-zinc-400">Texto (hover)</label>
                        <div class="flex items-center gap-2">
                            <input v-model="form.button_hover_text_color" type="color" class="h-9 w-10 cursor-pointer rounded-lg border border-zinc-300 dark:border-zinc-600" />
                            <input v-model="form.button_hover_text_color" type="text" class="flex-1 rounded-lg border border-zinc-300 bg-white px-2 py-1.5 font-mono text-xs dark:border-zinc-600 dark:bg-zinc-700 dark:text-white" placeholder="herda texto" />
                        </div>
                        <div class="mt-2 flex h-7 items-center justify-center rounded-lg text-[10px] font-bold"
                             :style="{ background: form.button_hover_bg_color || form.primary_color, color: form.button_hover_text_color || form.on_primary_color }">
                            Hover
                        </div>
                    </div>

                </div>
            </section>

            <!-- ── Cores de estrutura ── -->
            <section class="space-y-3">
                <h3 class="border-b border-zinc-200 pb-2 text-sm font-semibold text-zinc-700 dark:border-zinc-700 dark:text-zinc-300">Cores de estrutura</h3>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">

                    <div class="rounded-xl border p-3 transition-colors"
                         :class="contrastOk(form.bg_color, form.text_color) === false ? 'border-red-400 bg-red-50 dark:border-red-700 dark:bg-red-900/20' : 'border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800/60'">
                        <label class="mb-2 flex items-center gap-1.5 text-xs font-medium text-zinc-600 dark:text-zinc-400">
                            Fundo da página
                            <span v-if="contrastOk(form.bg_color, form.text_color) === false" class="text-[9px] font-bold text-red-600 dark:text-red-400">Texto ilegível!</span>
                        </label>
                        <div class="flex items-center gap-2">
                            <input v-model="form.bg_color" type="color" class="h-9 w-10 cursor-pointer rounded-lg border border-zinc-300 dark:border-zinc-600" />
                            <input v-model="form.bg_color" type="text" class="flex-1 rounded-lg border border-zinc-300 bg-white px-2 py-1.5 font-mono text-xs dark:border-zinc-600 dark:bg-zinc-700 dark:text-white" placeholder="#090F18" />
                        </div>
                        <div class="mt-2 flex h-7 items-center px-2 rounded-lg"
                             :style="{ background: form.bg_color }">
                            <span class="text-[10px]" :style="{ color: form.text_color }">Fundo da página</span>
                        </div>
                    </div>

                    <div class="rounded-xl border p-3 transition-colors"
                         :class="contrastOk(form.surface_color, form.text_color) === false ? 'border-red-400 bg-red-50 dark:border-red-700 dark:bg-red-900/20' : 'border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800/60'">
                        <label class="mb-2 flex items-center gap-1.5 text-xs font-medium text-zinc-600 dark:text-zinc-400">
                            Superfície / Cards
                            <span v-if="contrastOk(form.surface_color, form.text_color) === false" class="text-[9px] font-bold text-red-600 dark:text-red-400">Texto ilegível!</span>
                        </label>
                        <div class="flex items-center gap-2">
                            <input v-model="form.surface_color" type="color" class="h-9 w-10 cursor-pointer rounded-lg border border-zinc-300 dark:border-zinc-600" />
                            <input v-model="form.surface_color" type="text" class="flex-1 rounded-lg border border-zinc-300 bg-white px-2 py-1.5 font-mono text-xs dark:border-zinc-600 dark:bg-zinc-700 dark:text-white" placeholder="#132435" />
                        </div>
                        <div class="mt-2 flex h-7 items-center gap-1.5 rounded-lg border px-2"
                             :style="{ background: form.surface_color, borderColor: form.border_color }">
                            <span class="text-[10px] font-medium" :style="{ color: form.text_color }">Card / Sidebar</span>
                        </div>
                    </div>

                    <div class="rounded-xl border p-3 transition-colors"
                         :class="contrastOk(form.surface2_color, form.text2_color) === false ? 'border-red-400 bg-red-50 dark:border-red-700 dark:bg-red-900/20' : 'border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800/60'">
                        <label class="mb-2 flex items-center gap-1.5 text-xs font-medium text-zinc-600 dark:text-zinc-400">
                            Superfície secundária
                            <span v-if="contrastOk(form.surface2_color, form.text2_color) === false" class="text-[9px] font-bold text-red-600 dark:text-red-400">Texto ilegível!</span>
                        </label>
                        <div class="flex items-center gap-2">
                            <input v-model="form.surface2_color" type="color" class="h-9 w-10 cursor-pointer rounded-lg border border-zinc-300 dark:border-zinc-600" />
                            <input v-model="form.surface2_color" type="text" class="flex-1 rounded-lg border border-zinc-300 bg-white px-2 py-1.5 font-mono text-xs dark:border-zinc-600 dark:bg-zinc-700 dark:text-white" placeholder="#1A3040" />
                        </div>
                        <div class="mt-2 h-7 overflow-hidden rounded-lg border"
                             :style="{ background: form.surface_color, borderColor: form.border_color }">
                            <div class="mx-1.5 mt-1 flex h-4 items-center rounded px-1.5"
                                 :style="{ background: form.surface2_color }">
                                <span class="text-[9px]" :style="{ color: form.text2_color }">Card interno</span>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-800/60">
                        <label class="mb-2 block text-xs font-medium text-zinc-600 dark:text-zinc-400">Bordas</label>
                        <div class="flex items-center gap-2">
                            <input v-model="form.border_color" type="color" class="h-9 w-10 cursor-pointer rounded-lg border border-zinc-300 dark:border-zinc-600" />
                            <input v-model="form.border_color" type="text" class="flex-1 rounded-lg border border-zinc-300 bg-white px-2 py-1.5 font-mono text-xs dark:border-zinc-600 dark:bg-zinc-700 dark:text-white" placeholder="#1E3448" />
                        </div>
                        <div class="mt-2 flex h-7 items-center justify-center rounded-lg border-2"
                             :style="{ borderColor: form.border_color, background: form.surface_color }">
                            <span class="text-[9px]" :style="{ color: form.text2_color }">— divisória —</span>
                        </div>
                    </div>

                </div>
            </section>

            <!-- ── Texto e ícones ── -->
            <section class="space-y-3">
                <h3 class="border-b border-zinc-200 pb-2 text-sm font-semibold text-zinc-700 dark:border-zinc-700 dark:text-zinc-300">Texto e ícones</h3>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">

                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-800/60">
                        <label class="mb-2 block text-xs font-medium text-zinc-600 dark:text-zinc-400">Texto principal</label>
                        <div class="flex items-center gap-2">
                            <input v-model="form.text_color" type="color" class="h-9 w-10 cursor-pointer rounded-lg border border-zinc-300 dark:border-zinc-600" />
                            <input v-model="form.text_color" type="text" class="flex-1 rounded-lg border border-zinc-300 bg-white px-2 py-1.5 font-mono text-xs dark:border-zinc-600 dark:bg-zinc-700 dark:text-white" placeholder="#FFFFFF" />
                        </div>
                        <div class="mt-2 flex h-7 items-center rounded-lg border px-2"
                             :style="{ background: form.surface_color, borderColor: form.border_color }">
                            <span class="text-[10px] font-bold" :style="{ color: form.text_color }">Título principal</span>
                        </div>
                    </div>

                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-800/60">
                        <label class="mb-2 block text-xs font-medium text-zinc-600 dark:text-zinc-400">Texto secundário</label>
                        <div class="flex items-center gap-2">
                            <input v-model="form.text2_color" type="color" class="h-9 w-10 cursor-pointer rounded-lg border border-zinc-300 dark:border-zinc-600" />
                            <input v-model="form.text2_color" type="text" class="flex-1 rounded-lg border border-zinc-300 bg-white px-2 py-1.5 font-mono text-xs dark:border-zinc-600 dark:bg-zinc-700 dark:text-white" placeholder="#7A9BAD" />
                        </div>
                        <div class="mt-2 flex h-7 items-center rounded-lg border px-2"
                             :style="{ background: form.surface_color, borderColor: form.border_color }">
                            <span class="text-[10px]" :style="{ color: form.text2_color }">Subtítulo / ícones</span>
                        </div>
                    </div>

                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-800/60">
                        <label class="mb-2 block text-xs font-medium text-zinc-600 dark:text-zinc-400">Itens inativos</label>
                        <div class="flex items-center gap-2">
                            <input v-model="form.text_inactive_color" type="color" class="h-9 w-10 cursor-pointer rounded-lg border border-zinc-300 dark:border-zinc-600" />
                            <input v-model="form.text_inactive_color" type="text" class="flex-1 rounded-lg border border-zinc-300 bg-white px-2 py-1.5 font-mono text-xs dark:border-zinc-600 dark:bg-zinc-700 dark:text-white" placeholder="#4A6A7A" />
                        </div>
                        <div class="mt-2 flex h-7 items-center gap-1.5 rounded-lg border px-2"
                             :style="{ background: form.nav_bg_color || form.surface_color, borderColor: form.border_color }">
                            <div class="h-3 w-3 rounded" :style="{ background: form.text_inactive_color }" />
                            <span class="text-[9px]" :style="{ color: form.text_inactive_color }">Trilha</span>
                        </div>
                    </div>

                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-800/60">
                        <label class="mb-2 block text-xs font-medium text-zinc-600 dark:text-zinc-400">Cor de perigo</label>
                        <div class="flex items-center gap-2">
                            <input v-model="form.danger_color" type="color" class="h-9 w-10 cursor-pointer rounded-lg border border-zinc-300 dark:border-zinc-600" />
                            <input v-model="form.danger_color" type="text" class="flex-1 rounded-lg border border-zinc-300 bg-white px-2 py-1.5 font-mono text-xs dark:border-zinc-600 dark:bg-zinc-700 dark:text-white" placeholder="#E05555" />
                        </div>
                        <div class="mt-2 flex h-7 items-center rounded-lg border px-2"
                             :style="{ background: form.surface_color, borderColor: form.border_color }">
                            <span class="text-[10px] font-medium" :style="{ color: form.danger_color }">⟵ Sair</span>
                        </div>
                    </div>

                </div>
            </section>

            <!-- ── Navegação ── -->
            <section class="space-y-3">
                <h3 class="border-b border-zinc-200 pb-2 text-sm font-semibold text-zinc-700 dark:border-zinc-700 dark:text-zinc-300">Navegação</h3>
                <p class="text-xs text-zinc-400">Substitui "Superfície / Cards" apenas nesses componentes. Deixe em branco para herdar a superfície global.</p>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">

                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-800/60">
                        <label class="mb-2 block text-xs font-medium text-zinc-600 dark:text-zinc-400">Fundo do menu lateral</label>
                        <div class="flex items-center gap-2">
                            <input v-model="form.sidebar_bg_color" type="color" class="h-9 w-10 cursor-pointer rounded-lg border border-zinc-300 dark:border-zinc-600" />
                            <input v-model="form.sidebar_bg_color" type="text" class="flex-1 rounded-lg border border-zinc-300 bg-white px-2 py-1.5 font-mono text-xs dark:border-zinc-600 dark:bg-zinc-700 dark:text-white" placeholder="herda superfície" />
                        </div>
                        <div class="mt-2 flex flex-col gap-0.5 rounded-lg p-1.5"
                             :style="{ background: form.sidebar_bg_color || form.surface_color }">
                            <div class="rounded px-1.5 py-0.5 text-[9px] font-bold"
                                 :style="{ background: form.nav_active_bg_color || 'rgba(255,255,255,0.13)', color: form.nav_active_text_color || form.text_color }">◉ Início</div>
                            <div class="px-1.5 py-0.5 text-[9px]" :style="{ color: form.text2_color }">○ Trilha</div>
                            <div class="px-1.5 py-0.5 text-[9px]" :style="{ color: form.text2_color }">○ Jornadas</div>
                        </div>
                        <p class="mt-1.5 text-[10px] text-zinc-400">Sidebar desktop (coluna esquerda)</p>
                    </div>

                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-800/60">
                        <label class="mb-2 block text-xs font-medium text-zinc-600 dark:text-zinc-400">Fundo do cabeçalho</label>
                        <div class="flex items-center gap-2">
                            <input v-model="form.header_bg_color" type="color" class="h-9 w-10 cursor-pointer rounded-lg border border-zinc-300 dark:border-zinc-600" />
                            <input v-model="form.header_bg_color" type="text" class="flex-1 rounded-lg border border-zinc-300 bg-white px-2 py-1.5 font-mono text-xs dark:border-zinc-600 dark:bg-zinc-700 dark:text-white" placeholder="herda superfície" />
                        </div>
                        <div class="mt-2 flex h-9 items-center gap-2 rounded-lg px-2"
                             :style="{ background: form.header_bg_color || form.surface_color }">
                            <div class="h-3 w-3 flex-shrink-0 rounded-sm" :style="{ background: form.primary_color }" />
                            <span class="min-w-0 flex-1 truncate text-[9px] font-bold" :style="{ color: form.text_color }">{{ form.area_name || 'Rastreio Spectra' }}</span>
                            <div class="h-5 w-5 flex-shrink-0 rounded-full flex items-center justify-center text-[8px] font-bold"
                                 :style="{ background: form.primary_color, color: form.on_primary_color }">A</div>
                        </div>
                        <p class="mt-1.5 text-[10px] text-zinc-400">Barra superior (header)</p>
                    </div>

                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-800/60">
                        <label class="mb-2 block text-xs font-medium text-zinc-600 dark:text-zinc-400">Fundo do menu inferior (mobile)</label>
                        <div class="flex items-center gap-2">
                            <input v-model="form.nav_bg_color" type="color" class="h-9 w-10 cursor-pointer rounded-lg border border-zinc-300 dark:border-zinc-600" />
                            <input v-model="form.nav_bg_color" type="text" class="flex-1 rounded-lg border border-zinc-300 bg-white px-2 py-1.5 font-mono text-xs dark:border-zinc-600 dark:bg-zinc-700 dark:text-white" placeholder="herda superfície" />
                        </div>
                        <div class="mt-2 flex h-9 items-center justify-around rounded-lg px-1"
                             :style="{ background: form.nav_bg_color || form.surface_color }">
                            <div class="flex flex-col items-center gap-0.5">
                                <div class="h-3 w-3 rounded" :style="{ background: form.primary_color }" />
                                <span class="text-[7px]" :style="{ color: form.primary_color }">Início</span>
                            </div>
                            <div class="flex flex-col items-center gap-0.5">
                                <div class="h-3 w-3 rounded" :style="{ background: form.text_inactive_color }" />
                                <span class="text-[7px]" :style="{ color: form.text_inactive_color }">Trilha</span>
                            </div>
                            <div class="flex flex-col items-center gap-0.5">
                                <div class="h-3 w-3 rounded" :style="{ background: form.text_inactive_color }" />
                                <span class="text-[7px]" :style="{ color: form.text_inactive_color }">Mais</span>
                            </div>
                        </div>
                        <p class="mt-1.5 text-[10px] text-zinc-400">Bottom nav fixo no mobile</p>
                    </div>

                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-800/60">
                        <label class="mb-2 block text-xs font-medium text-zinc-600 dark:text-zinc-400">Fundo do item ativo</label>
                        <div class="flex items-center gap-2">
                            <input v-model="form.nav_active_bg_color" type="color" class="h-9 w-10 cursor-pointer rounded-lg border border-zinc-300 dark:border-zinc-600" />
                            <input v-model="form.nav_active_bg_color" type="text" class="flex-1 rounded-lg border border-zinc-300 bg-white px-2 py-1.5 font-mono text-xs dark:border-zinc-600 dark:bg-zinc-700 dark:text-white" placeholder="rgba(255,255,255,0.13)" />
                        </div>
                        <div class="mt-2 rounded-lg p-1"
                             :style="{ background: form.sidebar_bg_color || form.surface_color }">
                            <div class="flex items-center gap-1 rounded px-2 py-1"
                                 :style="{ background: form.nav_active_bg_color || 'rgba(255,255,255,0.13)' }">
                                <div class="h-2.5 w-2.5 rounded" :style="{ background: form.nav_active_text_color || form.text_color }" />
                                <span class="text-[9px] font-bold" :style="{ color: form.nav_active_text_color || form.text_color }">Início</span>
                            </div>
                            <div class="flex items-center gap-1 px-2 py-1 opacity-60">
                                <div class="h-2.5 w-2.5 rounded" :style="{ background: form.text2_color }" />
                                <span class="text-[9px]" :style="{ color: form.text2_color }">Trilha</span>
                            </div>
                        </div>
                        <p class="mt-1.5 text-[10px] text-zinc-400">Highlight do item selecionado no menu</p>
                    </div>

                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-800/60">
                        <label class="mb-2 block text-xs font-medium text-zinc-600 dark:text-zinc-400">Texto do item ativo</label>
                        <div class="flex items-center gap-2">
                            <input v-model="form.nav_active_text_color" type="color" class="h-9 w-10 cursor-pointer rounded-lg border border-zinc-300 dark:border-zinc-600" />
                            <input v-model="form.nav_active_text_color" type="text" class="flex-1 rounded-lg border border-zinc-300 bg-white px-2 py-1.5 font-mono text-xs dark:border-zinc-600 dark:bg-zinc-700 dark:text-white" placeholder="herda texto principal" />
                        </div>
                        <div class="mt-2 rounded-lg p-1"
                             :style="{ background: form.sidebar_bg_color || form.surface_color }">
                            <div class="flex items-center gap-1.5 rounded px-2 py-1"
                                 :style="{ background: form.nav_active_bg_color || 'rgba(255,255,255,0.13)' }">
                                <span class="text-[9px] font-bold" :style="{ color: form.nav_active_text_color || form.text_color }">◉ Início</span>
                                <span class="text-[9px] opacity-50" :style="{ color: form.text2_color }">○ Trilha</span>
                            </div>
                        </div>
                        <p class="mt-1.5 text-[10px] text-zinc-400">Cor do label e ícone no item ativo</p>
                    </div>

                </div>
            </section>

            <!-- ── Navegador ── -->
            <section class="space-y-4">
                <h3 class="border-b border-zinc-200 pb-2 text-sm font-semibold text-zinc-700 dark:border-zinc-700 dark:text-zinc-300">Navegador</h3>

                <div>
                    <label class="mb-1 block text-xs font-medium text-zinc-600 dark:text-zinc-400">Favicon</label>
                    <div v-if="form.favicon_url" class="mb-3 flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                            <img :src="form.favicon_url" alt="Favicon" class="max-h-full max-w-full object-contain" />
                        </div>
                        <button type="button" class="flex items-center gap-1 rounded-lg px-2 py-1.5 text-xs font-medium text-red-600 transition hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20" @click="clearFavicon">
                            <Trash2 class="h-3.5 w-3.5" /> Remover
                        </button>
                    </div>
                    <div class="flex items-center gap-3">
                        <input
                            v-model="form.favicon_url"
                            type="url"
                            placeholder="https://... ou clique para fazer upload"
                            class="block flex-1 rounded-xl border border-zinc-300 bg-white px-4 py-2.5 text-sm text-zinc-900 placeholder-zinc-400 transition focus:border-[var(--color-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]/20 dark:border-zinc-600 dark:bg-zinc-800 dark:text-white dark:placeholder-zinc-500"
                        />
                        <label class="flex cursor-pointer items-center gap-1.5 rounded-xl border border-zinc-300 bg-white px-3 py-2.5 text-sm font-medium text-zinc-700 transition hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700">
                            <Loader2 v-if="uploading" class="h-4 w-4 animate-spin" />
                            <Upload v-else class="h-4 w-4" />
                            Upload
                            <input ref="faviconInput" type="file" class="sr-only" accept="image/png,image/jpeg,image/webp,image/svg+xml,image/x-icon" @change="uploadFavicon" />
                        </label>
                    </div>
                    <p class="mt-1 text-xs text-zinc-400">PNG, ICO, SVG ou WebP. Recomendado 32×32 px. Substitui o ícone padrão da aba do navegador.</p>
                </div>

                <div class="max-w-xs">
                    <label class="mb-1 block text-xs font-medium text-zinc-600 dark:text-zinc-400">Cor do tema do navegador</label>
                    <div class="flex items-center gap-2">
                        <input v-model="form.theme_color" type="color" class="h-9 w-10 cursor-pointer rounded-lg border border-zinc-300 dark:border-zinc-600" />
                        <input v-model="form.theme_color" type="text" class="flex-1 rounded-lg border border-zinc-300 bg-white px-2 py-1.5 font-mono text-xs dark:border-zinc-600 dark:bg-zinc-700 dark:text-white" placeholder="herda cor primária" />
                    </div>
                    <p class="mt-1.5 text-[10px] text-zinc-400">Colore a barra do browser em Android/Chrome (meta theme-color)</p>
                </div>
            </section>

            <!-- ── Pré-visualização ── -->
            <section class="space-y-2">
                <h3 class="border-b border-zinc-200 pb-2 text-sm font-semibold text-zinc-700 dark:border-zinc-700 dark:text-zinc-300">Pré-visualização</h3>
                <div
                    class="overflow-hidden rounded-2xl border-2 transition-colors"
                    :class="hasConflict ? 'border-red-500' : 'border-zinc-300 dark:border-zinc-700'"
                    :style="{ background: form.bg_color }"
                >
                    <!-- Header -->
                    <div class="flex items-center gap-3 border-b px-4 py-3" :style="{ borderColor: form.border_color, background: form.header_bg_color || form.surface_color }">
                        <img v-if="form.logo_url" :src="form.logo_url" class="h-6 w-auto max-w-[90px] object-contain" alt="logo" />
                        <span v-else class="text-sm font-bold" :style="{ color: form.text_color }">{{ form.area_name || 'Área do Aluno' }}</span>
                        <div class="ml-auto flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold" :style="{ background: form.primary_color, color: form.on_primary_color }">A</div>
                    </div>
                    <!-- Body -->
                    <div class="flex gap-3 p-3">
                        <!-- Sidebar -->
                        <div class="hidden w-28 flex-col gap-1 rounded-xl border p-2 sm:flex" :style="{ borderColor: form.border_color, background: form.sidebar_bg_color || form.surface_color }">
                            <div class="rounded-lg px-2 py-1.5 text-xs font-bold" :style="{ background: form.nav_active_bg_color || 'rgba(255,255,255,0.13)', color: form.nav_active_text_color || form.text_color }">Início</div>
                            <div class="rounded-lg px-2 py-1.5 text-xs" :style="{ color: form.text2_color }">Trilha</div>
                            <div class="rounded-lg px-2 py-1.5 text-xs" :style="{ color: form.text2_color }">Jornadas</div>
                            <div class="mt-auto rounded-lg px-2 py-1.5 text-xs font-medium" :style="{ color: form.danger_color }">Sair</div>
                        </div>
                        <!-- Conteúdo -->
                        <div class="flex-1 space-y-2">
                            <div class="rounded-xl border p-3" :style="{ borderColor: form.border_color, background: form.surface_color }">
                                <div class="mb-1 text-xs font-bold" :style="{ color: form.text_color }">Bem-vindo</div>
                                <div class="text-[10px]" :style="{ color: form.text2_color }">Continue sua jornada de hoje</div>
                                <div class="mt-2 w-fit rounded-lg px-3 py-1.5 text-center text-[10px] font-bold" :style="{ background: form.primary_color, color: form.on_primary_color }">Continuar Trilha</div>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div class="h-10 rounded-xl border" :style="{ borderColor: form.border_color, background: form.surface2_color }" />
                                <div class="h-10 rounded-xl border" :style="{ borderColor: form.border_color, background: form.surface2_color }" />
                            </div>
                        </div>
                    </div>
                    <!-- Bottom nav (visible in preview) -->
                    <div class="flex items-center justify-around border-t px-2 py-2" :style="{ borderColor: form.border_color, background: form.nav_bg_color || form.surface_color }">
                        <div class="flex flex-col items-center gap-0.5">
                            <div class="h-4 w-4 rounded" :style="{ background: form.primary_color }" />
                            <span class="text-[9px] font-bold" :style="{ color: form.primary_color }">Início</span>
                        </div>
                        <div class="flex flex-col items-center gap-0.5">
                            <div class="h-4 w-4 rounded" :style="{ background: form.text_inactive_color }" />
                            <span class="text-[9px]" :style="{ color: form.text_inactive_color }">Trilha</span>
                        </div>
                        <div class="flex flex-col items-center gap-0.5">
                            <div class="h-4 w-4 rounded" :style="{ background: form.text_inactive_color }" />
                            <span class="text-[9px]" :style="{ color: form.text_inactive_color }">Mais</span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ── Verificação de contraste ── -->
            <section class="space-y-2">
                <h3 class="border-b border-zinc-200 pb-2 text-sm font-semibold text-zinc-700 dark:border-zinc-700 dark:text-zinc-300 flex items-center gap-2">
                    Verificação de contraste
                    <span v-if="hasConflict" class="rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-bold text-red-700 dark:bg-red-900/40 dark:text-red-400">{{ contrastChecks.filter(c => c.ok === false).length }} conflito{{ contrastChecks.filter(c => c.ok === false).length > 1 ? 's' : '' }}</span>
                    <span v-else class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400">OK</span>
                </h3>
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-800/60 space-y-2">
                    <div v-for="check in contrastChecks" :key="check.label" class="flex items-center gap-2">
                        <span
                            class="h-2.5 w-2.5 flex-shrink-0 rounded-full"
                            :class="check.ok === true ? 'bg-emerald-500' : check.ok === false ? 'bg-red-500' : 'bg-zinc-400'"
                        />
                        <span
                            class="flex-1 text-[11px]"
                            :class="check.ok === false ? 'font-semibold text-red-600 dark:text-red-400' : 'text-zinc-500 dark:text-zinc-400'"
                        >{{ check.label }}</span>
                        <span v-if="check.ok === false" class="text-[10px] font-bold text-red-600 dark:text-red-400">Conflito!</span>
                        <span v-else-if="check.ok === null" class="text-[10px] text-zinc-400">—</span>
                    </div>
                    <p class="pt-1 text-[10px] text-zinc-400">Baseado em WCAG — razão de contraste mínima 3,5:1. Conflitos podem tornar o texto ilegível.</p>
                </div>
            </section>

            <div class="flex justify-end border-t border-zinc-200 pt-4 dark:border-zinc-700">
                <Button type="button" :disabled="saving" @click="save">
                    <Loader2 v-if="saving" class="mr-2 h-4 w-4 animate-spin" />
                    Salvar configurações
                </Button>
            </div>
        </template>
    </div>
</template>
