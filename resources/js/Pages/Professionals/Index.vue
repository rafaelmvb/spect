<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import LayoutInfoprodutor from '@/Layouts/LayoutInfoprodutor.vue';
import Button from '@/components/ui/Button.vue';
import {
    Stethoscope, Plus, Pencil, Trash2, X, Loader2,
    Eye, EyeOff, Search, UserCircle2, Phone, Mail,
    BadgeCheck, DollarSign, Users, KeyRound, Copy, Check,
    ThumbsUp, ThumbsDown, AlertCircle
} from 'lucide-vue-next';

defineOptions({ layout: LayoutInfoprodutor });

const props = defineProps({
    professionals: { type: Object, default: () => ({ data: [] }) },
    stats:         { type: Object, default: () => ({}) },
    q:             { type: String, default: null },
});

// ─── Busca ────────────────────────────────────────────────────────────────────
const search = ref(props.q ?? '');
let searchTimer = null;
watch(search, (v) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get('/profissionais', v ? { q: v } : {}, { preserveState: true, replace: true });
    }, 400);
});

// ─── State ────────────────────────────────────────────────────────────────────
const toast        = ref(null);
const modal        = ref(false);
const editing      = ref(null);
const saving       = ref(false);
const avatarPreview= ref('');
const avatarInputRef = ref(null);
const detailOpen   = ref(false);
const selectedPro  = ref(null);

const emptyForm = () => ({
    name: '', email: '', phone: '', bio: '',
    specialty: '', registration_type: '', registration_number: '',
    price_per_hour: '', is_active: true, avatar: null,
    create_access: false, access_password: '',
});
const form = ref(emptyForm());

function csrfToken() { return document.querySelector('meta[name="csrf-token"]')?.content ?? ''; }
function showToast(msg, type = 'success') {
    toast.value = { msg, type };
    setTimeout(() => toast.value = null, 4000);
}

// ─── Modal criar/editar ───────────────────────────────────────────────────────
function openCreate() {
    editing.value = null;
    form.value = emptyForm();
    avatarPreview.value = '';
    modal.value = true;
}

function openEdit(pro) {
    editing.value = pro;
    form.value = {
        name: pro.name ?? '',
        email: pro.email ?? '',
        phone: pro.phone ?? '',
        bio: pro.bio ?? '',
        specialty: pro.specialty ?? '',
        registration_type: pro.registration_type ?? '',
        registration_number: pro.registration_number ?? '',
        price_per_hour: pro.price_per_hour ?? '',
        is_active: pro.is_active,
        avatar: null,
        create_access: false,
        access_password: '',
    };
    avatarPreview.value = pro.avatar_url ?? '';
    modal.value = true;
}

function onAvatar(e) {
    const f = e.target?.files?.[0];
    if (!f) return;
    form.value.avatar = f;
    avatarPreview.value = URL.createObjectURL(f);
}

function clearAvatar() {
    form.value.avatar = null;
    avatarPreview.value = '';
    if (avatarInputRef.value) avatarInputRef.value.value = '';
}

async function save() {
    if (!form.value.name.trim()) { showToast('Nome é obrigatório.', 'error'); return; }
    saving.value = true;
    try {
        const fd = new FormData();
        const fields = ['name', 'email', 'phone', 'bio', 'specialty', 'registration_type', 'registration_number', 'price_per_hour'];
        fields.forEach(f => { if (form.value[f] !== '' && form.value[f] != null) fd.append(f, form.value[f]); });
        fd.append('is_active', form.value.is_active ? '1' : '0');
        if (form.value.avatar) fd.append('avatar', form.value.avatar);

        const h = { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' };

        if (editing.value) {
            fd.append('_method', 'PUT');
            const { data } = await window.axios.post(`/profissionais/${editing.value.id}`, fd, { headers: h });
            router.reload({ only: ['professionals'] });
            if (selectedPro.value?.id === editing.value.id) selectedPro.value = data.professional;
            showToast('Profissional atualizado.');
        } else {
            const { data: created } = await window.axios.post('/profissionais', fd, { headers: h });
            router.reload({ only: ['professionals', 'stats'] });

            if (form.value.create_access && created.professional?.id) {
                try {
                    const payload = form.value.access_password ? { password: form.value.access_password } : {};
                    const { data: acc } = await window.axios.post(
                        `/profissionais/${created.professional.id}/criar-acesso`,
                        payload,
                        { headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' } },
                    );
                    accessResult.value = acc;
                    accessModal.value = true;
                } catch (e) {
                    showToast(e.response?.data?.message ?? 'Profissional criado, mas falha ao criar acesso.', 'error');
                }
            } else {
                showToast('Profissional cadastrado.');
            }
        }
        modal.value = false;
    } catch (e) {
        showToast(e.response?.data?.message ?? 'Erro ao salvar.', 'error');
    } finally {
        saving.value = false;
    }
}

// ─── Toggle ativo ─────────────────────────────────────────────────────────────
async function toggleActive(pro) {
    try {
        const { data } = await window.axios.put(`/profissionais/${pro.id}/toggle-active`, {}, {
            headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
        });
        showToast(data.is_active ? 'Profissional ativado.' : 'Profissional desativado.');
        router.reload({ only: ['professionals', 'stats'] });
    } catch { showToast('Erro.', 'error'); }
}

// ─── Excluir ──────────────────────────────────────────────────────────────────
async function destroy(pro) {
    if (!confirm(`Excluir ${pro.name}? Esta ação não pode ser desfeita.`)) return;
    try {
        await window.axios.delete(`/profissionais/${pro.id}`, {
            headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
        });
        if (selectedPro.value?.id === pro.id) { detailOpen.value = false; selectedPro.value = null; }
        router.reload({ only: ['professionals', 'stats'] });
        showToast('Profissional excluído.');
    } catch { showToast('Erro ao excluir.', 'error'); }
}

// ─── Painel de detalhes ───────────────────────────────────────────────────────
function openDetail(pro) {
    selectedPro.value = pro;
    detailOpen.value = true;
}

function getInitials(name) {
    if (!name) return '?';
    return name.split(/\s+/).map(n => n[0]).slice(0, 2).join('').toUpperCase();
}

function fmtPrice(v) {
    if (!v) return null;
    return Number(v).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

const registrationTypes = ['CRP', 'CRM', 'CREFITO', 'COREN', 'CRO', 'CFN', 'CFF', 'Outro'];

// ─── Criar acesso ─────────────────────────────────────────────────────────────
const accessModal    = ref(false);
const accessResult   = ref(null);
const accessLoading  = ref(false);
const copied         = ref(false);

async function createAccess(pro) {
    if (! pro.email) { showToast('Profissional não tem e-mail cadastrado.', 'error'); return; }
    accessLoading.value = true;
    try {
        const { data } = await window.axios.post(`/profissionais/${pro.id}/criar-acesso`, {}, {
            headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
        });
        accessResult.value = data;
        accessModal.value = true;
    } catch (e) {
        showToast(e.response?.data?.message ?? 'Erro ao criar acesso.', 'error');
    } finally {
        accessLoading.value = false;
    }
}

function copyPassword() {
    if (! accessResult.value?.temp_password) return;
    navigator.clipboard.writeText(accessResult.value.temp_password);
    copied.value = true;
    setTimeout(() => copied.value = false, 2000);
}

// ─── Aprovação/Rejeição ───────────────────────────────────────────────────────
const rejectModal      = ref(false);
const rejectTarget     = ref(null);
const rejectionReason  = ref('');
const approvalLoading  = ref(null);

async function approve(pro) {
    approvalLoading.value = pro.id;
    try {
        await window.axios.post(`/profissionais/${pro.id}/aprovar`, {}, {
            headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
        });
        showToast('Profissional aprovado.');
        router.reload({ only: ['professionals', 'stats'] });
        if (selectedPro.value?.id === pro.id) selectedPro.value = { ...selectedPro.value, status: 'approved' };
    } catch (e) {
        showToast(e.response?.data?.message ?? 'Erro ao aprovar.', 'error');
    } finally {
        approvalLoading.value = null;
    }
}

function openReject(pro) {
    rejectTarget.value = pro;
    rejectionReason.value = '';
    rejectModal.value = true;
}

async function confirmReject() {
    if (! rejectTarget.value) return;
    if (! rejectionReason.value.trim()) { showToast('Informe o motivo da rejeição.', 'error'); return; }
    approvalLoading.value = rejectTarget.value.id;
    try {
        await window.axios.post(`/profissionais/${rejectTarget.value.id}/rejeitar`, { rejection_reason: rejectionReason.value }, {
            headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
        });
        showToast('Profissional rejeitado.');
        rejectModal.value = false;
        router.reload({ only: ['professionals', 'stats'] });
        if (selectedPro.value?.id === rejectTarget.value.id) selectedPro.value = { ...selectedPro.value, status: 'rejected' };
    } catch (e) {
        showToast(e.response?.data?.message ?? 'Erro ao rejeitar.', 'error');
    } finally {
        approvalLoading.value = null;
    }
}

const APPROVAL_BADGE = {
    approved: 'bg-emerald-100 text-emerald-700',
    rejected: 'bg-red-100 text-red-600',
    pending:  'bg-amber-100 text-amber-700',
};
const APPROVAL_LABEL = { approved: 'Aprovado', rejected: 'Rejeitado', pending: 'Pendente' };
</script>

<template>
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-teal-100 dark:bg-teal-900/30">
                    <Stethoscope class="h-5 w-5 text-teal-600 dark:text-teal-400" />
                </div>
                <div>
                    <h1 class="text-xl font-bold text-zinc-900 dark:text-white">Profissionais</h1>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">Gerencie os profissionais da plataforma.</p>
                </div>
            </div>
            <Button @click="openCreate"><Plus class="mr-2 h-4 w-4" /> Cadastrar profissional</Button>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-3 gap-4">
            <div class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
                <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Total</p>
                <p class="mt-1 text-2xl font-bold tabular-nums text-zinc-900 dark:text-white">{{ stats.total ?? 0 }}</p>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
                <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Ativos</p>
                <p class="mt-1 text-2xl font-bold tabular-nums text-emerald-600 dark:text-emerald-400">{{ stats.ativos ?? 0 }}</p>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
                <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Inativos</p>
                <p class="mt-1 text-2xl font-bold tabular-nums text-zinc-400 dark:text-zinc-500">{{ stats.inativos ?? 0 }}</p>
            </div>
        </div>

        <!-- Busca -->
        <div class="relative max-w-sm">
            <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" />
            <input v-model="search" type="text" placeholder="Buscar por nome, e-mail ou especialidade..."
                class="w-full rounded-xl border border-zinc-200 bg-white py-2.5 pl-9 pr-4 text-sm text-zinc-900 placeholder-zinc-400 focus:border-teal-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:placeholder-zinc-500" />
        </div>

        <!-- Lista vazia -->
        <div v-if="!professionals.data?.length"
            class="flex flex-col items-center justify-center gap-3 rounded-2xl border-2 border-dashed border-zinc-200 py-20 dark:border-zinc-700">
            <Users class="h-12 w-12 text-zinc-300 dark:text-zinc-600" />
            <p class="text-zinc-400 dark:text-zinc-500">{{ search ? 'Nenhum profissional encontrado.' : 'Nenhum profissional cadastrado.' }}</p>
            <Button v-if="!search" variant="outline" @click="openCreate"><Plus class="mr-2 h-4 w-4" /> Cadastrar primeiro</Button>
        </div>

        <!-- Tabela desktop -->
        <div v-if="professionals.data?.length" class="hidden overflow-hidden rounded-2xl border border-zinc-200 dark:border-zinc-700 sm:block">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                <thead class="bg-zinc-50 dark:bg-zinc-800">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-zinc-500">Profissional</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-zinc-500">Especialidade</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-zinc-500">Registro</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-zinc-500">Valor/h</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-zinc-500">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-zinc-500">Aprovação</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 bg-white dark:divide-zinc-700 dark:bg-zinc-800/60">
                    <tr v-for="pro in professionals.data" :key="pro.id"
                        class="cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-700/50"
                        @click="openDetail(pro)">
                        <!-- Avatar + nome -->
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <div class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-full bg-teal-100 dark:bg-teal-900/40">
                                    <img v-if="pro.avatar_url" :src="pro.avatar_url" class="h-full w-full object-cover" />
                                    <span v-else class="text-xs font-bold text-teal-700 dark:text-teal-300">{{ getInitials(pro.name) }}</span>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-zinc-900 dark:text-white">{{ pro.name }}</p>
                                    <p v-if="pro.email" class="text-xs text-zinc-500">{{ pro.email }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm text-zinc-700 dark:text-zinc-300">{{ pro.specialty || '—' }}</td>
                        <td class="px-4 py-3 text-sm text-zinc-700 dark:text-zinc-300">
                            <span v-if="pro.registration_type && pro.registration_number">
                                {{ pro.registration_type }} {{ pro.registration_number }}
                            </span>
                            <span v-else class="text-zinc-400">—</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-zinc-700 dark:text-zinc-300">{{ fmtPrice(pro.price_per_hour) ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold"
                                :class="pro.is_active
                                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'
                                    : 'bg-zinc-100 text-zinc-500 dark:bg-zinc-700 dark:text-zinc-400'">
                                {{ pro.is_active ? 'Ativo' : 'Inativo' }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold"
                                :class="APPROVAL_BADGE[pro.status ?? 'pending']">
                                {{ APPROVAL_LABEL[pro.status ?? 'pending'] }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1" @click.stop>
                                <button type="button"
                                    class="rounded-lg p-1.5 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-600 dark:hover:bg-zinc-700"
                                    :title="pro.is_active ? 'Desativar' : 'Ativar'"
                                    @click="toggleActive(pro)">
                                    <EyeOff v-if="pro.is_active" class="h-4 w-4" />
                                    <Eye v-else class="h-4 w-4 text-emerald-500" />
                                </button>
                                <button type="button"
                                    class="rounded-lg p-1.5 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-600 dark:hover:bg-zinc-700"
                                    @click="openEdit(pro)">
                                    <Pencil class="h-4 w-4" />
                                </button>
                                <button type="button"
                                    class="rounded-lg p-1.5 text-red-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20"
                                    @click="destroy(pro)">
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Cards mobile -->
        <div v-if="professionals.data?.length" class="space-y-3 sm:hidden">
            <div v-for="pro in professionals.data" :key="pro.id"
                class="cursor-pointer rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800/50"
                @click="openDetail(pro)">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-teal-100 dark:bg-teal-900/40">
                        <img v-if="pro.avatar_url" :src="pro.avatar_url" class="h-full w-full object-cover" />
                        <span v-else class="text-sm font-bold text-teal-700 dark:text-teal-300">{{ getInitials(pro.name) }}</span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="font-medium text-zinc-900 dark:text-white truncate">{{ pro.name }}</p>
                        <p class="text-xs text-zinc-500 truncate">{{ pro.specialty || pro.email || '—' }}</p>
                    </div>
                    <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold uppercase"
                        :class="pro.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-zinc-100 text-zinc-500'">
                        {{ pro.is_active ? 'Ativo' : 'Inativo' }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Paginação -->
        <div v-if="professionals.last_page > 1" class="flex items-center justify-center gap-2">
            <a v-for="link in professionals.links" :key="link.label"
                :href="link.url ?? '#'"
                class="rounded-lg border px-3 py-1.5 text-sm"
                :class="link.active
                    ? 'border-teal-500 bg-teal-50 font-semibold text-teal-700 dark:bg-teal-900/20 dark:text-teal-300'
                    : link.url ? 'border-zinc-200 text-zinc-600 hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-400' : 'cursor-default border-transparent text-zinc-300'"
                v-html="link.label" />
        </div>
    </div>

    <!-- ─── Painel lateral de detalhes ─────────────────────────────────────────── -->
    <Teleport to="body">
        <div v-if="detailOpen && selectedPro" class="fixed inset-0 z-[100000] flex justify-end" aria-modal="true">
            <div class="fixed inset-0 bg-zinc-900/40" @click="detailOpen = false" />
            <aside class="relative flex h-full w-full max-w-sm flex-col rounded-l-2xl bg-white shadow-2xl dark:bg-zinc-900">
                <!-- Header -->
                <div class="flex items-center justify-between px-5 py-4 border-b border-zinc-100 dark:border-zinc-800">
                    <h2 class="font-semibold text-zinc-900 dark:text-white">Perfil do profissional</h2>
                    <button type="button" class="rounded-lg p-1.5 text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800"
                        @click="detailOpen = false"><X class="h-5 w-5" /></button>
                </div>

                <!-- Conteúdo -->
                <div class="flex-1 overflow-y-auto p-5 space-y-5">
                    <!-- Avatar + nome -->
                    <div class="flex flex-col items-center gap-3 text-center">
                        <div class="flex h-20 w-20 items-center justify-center overflow-hidden rounded-full bg-teal-100 dark:bg-teal-900/40">
                            <img v-if="selectedPro.avatar_url" :src="selectedPro.avatar_url" class="h-full w-full object-cover" />
                            <span v-else class="text-2xl font-bold text-teal-700 dark:text-teal-300">{{ getInitials(selectedPro.name) }}</span>
                        </div>
                        <div>
                            <p class="text-lg font-bold text-zinc-900 dark:text-white">{{ selectedPro.name }}</p>
                            <p v-if="selectedPro.specialty" class="text-sm text-teal-600 dark:text-teal-400">{{ selectedPro.specialty }}</p>
                        </div>
                        <span class="rounded-full px-3 py-1 text-xs font-semibold"
                            :class="selectedPro.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-zinc-100 text-zinc-500'">
                            {{ selectedPro.is_active ? 'Ativo' : 'Inativo' }}
                        </span>
                    </div>

                    <!-- Campos -->
                    <div class="space-y-3">
                        <div v-if="selectedPro.email" class="flex items-center gap-3 rounded-xl border border-zinc-100 p-3 dark:border-zinc-800">
                            <Mail class="h-4 w-4 shrink-0 text-zinc-400" />
                            <span class="text-sm text-zinc-700 dark:text-zinc-300">{{ selectedPro.email }}</span>
                        </div>
                        <div v-if="selectedPro.phone" class="flex items-center gap-3 rounded-xl border border-zinc-100 p-3 dark:border-zinc-800">
                            <Phone class="h-4 w-4 shrink-0 text-zinc-400" />
                            <span class="text-sm text-zinc-700 dark:text-zinc-300">{{ selectedPro.phone }}</span>
                        </div>
                        <div v-if="selectedPro.registration_type && selectedPro.registration_number"
                            class="flex items-center gap-3 rounded-xl border border-zinc-100 p-3 dark:border-zinc-800">
                            <BadgeCheck class="h-4 w-4 shrink-0 text-zinc-400" />
                            <span class="text-sm text-zinc-700 dark:text-zinc-300">
                                {{ selectedPro.registration_type }} {{ selectedPro.registration_number }}
                            </span>
                        </div>
                        <div v-if="selectedPro.price_per_hour" class="flex items-center gap-3 rounded-xl border border-zinc-100 p-3 dark:border-zinc-800">
                            <DollarSign class="h-4 w-4 shrink-0 text-zinc-400" />
                            <span class="text-sm text-zinc-700 dark:text-zinc-300">{{ fmtPrice(selectedPro.price_per_hour) }} / hora</span>
                        </div>
                        <div v-if="selectedPro.bio" class="rounded-xl border border-zinc-100 p-3 dark:border-zinc-800">
                            <p class="mb-1 text-xs font-medium uppercase tracking-wide text-zinc-400">Bio</p>
                            <p class="text-sm text-zinc-700 dark:text-zinc-300 whitespace-pre-line">{{ selectedPro.bio }}</p>
                        </div>
                    </div>

                    <!-- Status de aprovação -->
                    <div class="rounded-xl border border-zinc-100 dark:border-zinc-800 p-3 flex items-start gap-3">
                        <AlertCircle class="h-4 w-4 shrink-0 text-zinc-400 mt-0.5" />
                        <div>
                            <p class="text-xs font-medium text-zinc-500">Aprovação</p>
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold mt-0.5"
                                :class="APPROVAL_BADGE[selectedPro.status ?? 'pending']">
                                {{ APPROVAL_LABEL[selectedPro.status ?? 'pending'] }}
                            </span>
                            <p v-if="selectedPro.rejection_reason" class="text-xs text-red-500 mt-1">{{ selectedPro.rejection_reason }}</p>
                        </div>
                    </div>

                    <!-- Ações -->
                    <div class="flex flex-col gap-2 pt-2">
                        <Button variant="outline" class="w-full justify-start" @click="openEdit(selectedPro); detailOpen = false">
                            <Pencil class="h-4 w-4 mr-2" /> Editar perfil
                        </Button>
                        <button type="button"
                            class="flex w-full items-center gap-2 rounded-xl border px-4 py-2.5 text-sm font-medium transition"
                            :class="selectedPro.is_active
                                ? 'border-amber-300 text-amber-700 hover:bg-amber-50 dark:border-amber-700 dark:text-amber-300'
                                : 'border-emerald-300 text-emerald-700 hover:bg-emerald-50 dark:border-emerald-700 dark:text-emerald-300'"
                            @click="toggleActive(selectedPro)">
                            <EyeOff v-if="selectedPro.is_active" class="h-4 w-4" />
                            <Eye v-else class="h-4 w-4" />
                            {{ selectedPro.is_active ? 'Desativar' : 'Ativar' }}
                        </button>

                        <!-- Aprovar/Rejeitar -->
                        <div v-if="selectedPro.status !== 'approved'" class="flex gap-2">
                            <button type="button"
                                class="flex flex-1 items-center justify-center gap-2 rounded-xl border border-emerald-300 text-emerald-700 hover:bg-emerald-50 dark:border-emerald-700 dark:text-emerald-300 px-4 py-2.5 text-sm font-medium transition disabled:opacity-60"
                                :disabled="approvalLoading === selectedPro.id"
                                @click="approve(selectedPro)">
                                <Loader2 v-if="approvalLoading === selectedPro.id" class="h-4 w-4 animate-spin" />
                                <ThumbsUp v-else class="h-4 w-4" />
                                Aprovar
                            </button>
                            <button v-if="selectedPro.status !== 'rejected'" type="button"
                                class="flex flex-1 items-center justify-center gap-2 rounded-xl border border-red-300 text-red-600 hover:bg-red-50 dark:border-red-700 dark:text-red-400 px-4 py-2.5 text-sm font-medium transition"
                                @click="openReject(selectedPro)">
                                <ThumbsDown class="h-4 w-4" />
                                Rejeitar
                            </button>
                        </div>
                        <button v-if="selectedPro.status === 'approved'" type="button"
                            class="flex w-full items-center justify-center gap-2 rounded-xl border border-red-300 text-red-600 hover:bg-red-50 dark:border-red-700 dark:text-red-400 px-4 py-2.5 text-sm font-medium transition"
                            @click="openReject(selectedPro)">
                            <ThumbsDown class="h-4 w-4" />
                            Rejeitar aprovação
                        </button>

                        <button type="button"
                            class="flex w-full items-center gap-2 rounded-xl border border-violet-300 text-violet-700 hover:bg-violet-50 dark:border-violet-700 dark:text-violet-300 px-4 py-2.5 text-sm font-medium transition"
                            :disabled="accessLoading"
                            @click="createAccess(selectedPro)">
                            <Loader2 v-if="accessLoading" class="h-4 w-4 animate-spin" />
                            <KeyRound v-else class="h-4 w-4" />
                            {{ accessLoading ? 'Criando…' : 'Criar acesso ao painel' }}
                        </button>
                        <Button variant="destructive" class="w-full justify-start" @click="destroy(selectedPro)">
                            <Trash2 class="h-4 w-4 mr-2" /> Excluir profissional
                        </Button>
                    </div>
                </div>
            </aside>
        </div>
    </Teleport>

    <!-- ─── Modal criar/editar ─────────────────────────────────────────────────── -->
    <Teleport to="body">
        <div v-if="modal" class="fixed inset-0 z-[200000] flex items-center justify-center bg-black/50 p-4" @click.self="modal = false">
            <div class="w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-zinc-900">
                <div class="flex items-center justify-between border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                    <h3 class="font-semibold text-zinc-900 dark:text-white">
                        {{ editing ? 'Editar profissional' : 'Cadastrar profissional' }}
                    </h3>
                    <button type="button" class="rounded-lg p-1 text-zinc-400 hover:text-zinc-600" @click="modal = false">
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <div class="max-h-[70vh] space-y-4 overflow-y-auto p-5">
                    <!-- Avatar -->
                    <div class="flex items-center gap-4">
                        <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-full bg-teal-100 dark:bg-teal-900/40">
                            <img v-if="avatarPreview" :src="avatarPreview" class="h-full w-full object-cover" />
                            <UserCircle2 v-else class="h-8 w-8 text-teal-400" />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="cursor-pointer rounded-lg border border-zinc-200 px-3 py-1.5 text-xs font-medium text-zinc-600 hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-400">
                                {{ avatarPreview ? 'Trocar foto' : 'Adicionar foto' }}
                                <input ref="avatarInputRef" type="file" accept="image/*" class="hidden" @change="onAvatar" />
                            </label>
                            <button v-if="avatarPreview" type="button"
                                class="text-xs text-red-500 hover:underline text-left"
                                @click="clearAvatar">Remover</button>
                        </div>
                    </div>

                    <!-- Nome -->
                    <div>
                        <label class="mb-1 block text-xs font-medium text-zinc-500">Nome *</label>
                        <input v-model="form.name" type="text" placeholder="Nome completo"
                            class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm text-zinc-900 focus:border-teal-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                    </div>

                    <!-- Especialidade -->
                    <div>
                        <label class="mb-1 block text-xs font-medium text-zinc-500">Especialidade</label>
                        <input v-model="form.specialty" type="text" placeholder="Ex: Psicologia, Nutrição, Fisioterapia..."
                            class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm text-zinc-900 focus:border-teal-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                    </div>

                    <!-- E-mail + Telefone -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-zinc-500">E-mail</label>
                            <input v-model="form.email" type="email" placeholder="email@..."
                                class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm text-zinc-900 focus:border-teal-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-zinc-500">Telefone</label>
                            <input v-model="form.phone" type="text" placeholder="(11) 99999-9999"
                                class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm text-zinc-900 focus:border-teal-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                        </div>
                    </div>

                    <!-- Registro -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-zinc-500">Tipo de registro</label>
                            <select v-model="form.registration_type"
                                class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm text-zinc-900 focus:border-teal-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white">
                                <option value="">Selecionar</option>
                                <option v-for="t in registrationTypes" :key="t" :value="t">{{ t }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-zinc-500">Número</label>
                            <input v-model="form.registration_number" type="text" placeholder="Ex: 12345/SP"
                                class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm text-zinc-900 focus:border-teal-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                        </div>
                    </div>

                    <!-- Valor/hora -->
                    <div>
                        <label class="mb-1 block text-xs font-medium text-zinc-500">Valor por hora (R$)</label>
                        <input v-model="form.price_per_hour" type="number" min="0" step="0.01" placeholder="0,00"
                            class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm text-zinc-900 focus:border-teal-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                    </div>

                    <!-- Bio -->
                    <div>
                        <label class="mb-1 block text-xs font-medium text-zinc-500">Bio / Apresentação</label>
                        <textarea v-model="form.bio" rows="4"
                            placeholder="Descreva a experiência, abordagem e diferenciais do profissional..."
                            class="w-full resize-none rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm text-zinc-900 focus:border-teal-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                    </div>

                    <!-- Status -->
                    <label class="flex cursor-pointer items-center gap-3">
                        <button type="button"
                            class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                            :class="form.is_active ? 'bg-teal-600' : 'bg-zinc-300 dark:bg-zinc-600'"
                            @click="form.is_active = !form.is_active">
                            <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"
                                :class="form.is_active ? 'translate-x-6' : 'translate-x-1'" />
                        </button>
                        <span class="text-sm text-zinc-700 dark:text-zinc-300">{{ form.is_active ? 'Ativo' : 'Inativo' }}</span>
                    </label>

                    <!-- Criar acesso (só no cadastro) -->
                    <template v-if="!editing">
                        <div class="border-t border-zinc-100 dark:border-zinc-800 pt-4 space-y-3">
                            <label class="flex cursor-pointer items-center gap-3">
                                <button type="button"
                                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                                    :class="form.create_access ? 'bg-violet-600' : 'bg-zinc-300 dark:bg-zinc-600'"
                                    @click="form.create_access = !form.create_access">
                                    <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"
                                        :class="form.create_access ? 'translate-x-6' : 'translate-x-1'" />
                                </button>
                                <div>
                                    <p class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Criar acesso ao painel</p>
                                    <p class="text-xs text-zinc-400">Permite que o profissional acesse o painel em /p/dashboard</p>
                                </div>
                            </label>

                            <div v-if="form.create_access" class="space-y-2">
                                <label class="block text-xs font-medium text-zinc-500">Senha <span class="text-zinc-400 font-normal">(deixe em branco para gerar automaticamente)</span></label>
                                <div class="flex gap-2">
                                    <input v-model="form.access_password" type="text" placeholder="Mínimo 8 caracteres"
                                        class="flex-1 rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm focus:border-violet-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white font-mono" />
                                    <button type="button"
                                        class="shrink-0 rounded-xl border border-zinc-200 dark:border-zinc-700 px-3 py-2 text-xs font-medium text-zinc-600 hover:bg-zinc-100 dark:hover:bg-zinc-700 dark:text-zinc-300"
                                        @click="form.access_password = Math.random().toString(36).slice(-10) + Math.random().toString(36).slice(-2).toUpperCase()">
                                        Gerar
                                    </button>
                                </div>
                                <p v-if="!form.email" class="text-xs text-amber-600 dark:text-amber-400">
                                    Informe o e-mail do profissional acima para criar o acesso.
                                </p>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="flex justify-end gap-2 border-t border-zinc-200 px-5 py-3 dark:border-zinc-700">
                    <Button variant="outline" @click="modal = false">Cancelar</Button>
                    <Button :disabled="saving" @click="save">
                        <Loader2 v-if="saving" class="mr-2 h-3.5 w-3.5 animate-spin" />
                        {{ editing ? 'Salvar' : 'Cadastrar' }}
                    </Button>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- Modal acesso criado -->
    <Teleport to="body">
        <div v-if="accessModal && accessResult" class="fixed inset-0 z-[300000] flex items-center justify-center bg-black/50 p-4" @click.self="accessModal = false">
            <div class="w-full max-w-sm rounded-2xl bg-white dark:bg-zinc-900 shadow-2xl">
                <div class="flex items-center justify-between px-5 py-4 border-b border-zinc-100 dark:border-zinc-800">
                    <h3 class="font-semibold text-zinc-900 dark:text-white">
                        {{ accessResult.existing ? 'Acesso já existe' : 'Acesso criado!' }}
                    </h3>
                    <button @click="accessModal = false" class="rounded-lg p-1 text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800"><X class="h-5 w-5" /></button>
                </div>
                <div class="p-5 space-y-4">
                    <p v-if="accessResult.existing" class="text-sm text-zinc-600 dark:text-zinc-300">
                        Este profissional já possui um acesso criado com o e-mail <strong>{{ accessResult.email }}</strong>.
                    </p>
                    <template v-else>
                        <p class="text-sm text-zinc-600 dark:text-zinc-300">
                            Acesso criado para <strong>{{ accessResult.email }}</strong>. Envie as credenciais ao profissional:
                        </p>
                        <div class="rounded-xl bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 p-3 space-y-2">
                            <div class="flex items-center justify-between gap-2">
                                <div>
                                    <p class="text-[10px] font-medium text-zinc-400 uppercase tracking-wide">Senha temporária</p>
                                    <p class="font-mono font-bold text-zinc-900 dark:text-white tracking-widest">{{ accessResult.temp_password }}</p>
                                </div>
                                <button @click="copyPassword"
                                    class="shrink-0 rounded-lg p-2 border border-zinc-200 dark:border-zinc-700 text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-700 transition">
                                    <Check v-if="copied" class="h-4 w-4 text-emerald-500" />
                                    <Copy v-else class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                        <p class="text-xs text-amber-600 dark:text-amber-400">O profissional deverá alterar a senha no primeiro acesso.</p>
                    </template>
                </div>
                <div class="flex justify-end px-5 pb-5">
                    <Button @click="accessModal = false">Fechar</Button>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- Modal rejeição -->
    <Teleport to="body">
        <div v-if="rejectModal" class="fixed inset-0 z-[300000] flex items-center justify-center p-4 bg-black/40" @click.self="rejectModal = false">
            <div class="w-full max-w-sm rounded-2xl bg-white dark:bg-zinc-900 shadow-2xl">
                <div class="flex items-center justify-between px-5 py-4 border-b border-zinc-100 dark:border-zinc-800">
                    <h2 class="font-semibold text-zinc-900 dark:text-white">Rejeitar profissional</h2>
                    <button @click="rejectModal = false" class="rounded-lg p-1 text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800"><X class="h-5 w-5" /></button>
                </div>
                <div class="p-5 space-y-3">
                    <p class="text-sm text-zinc-600 dark:text-zinc-300">
                        Informe o motivo da rejeição de <strong>{{ rejectTarget?.name }}</strong>:
                    </p>
                    <textarea v-model="rejectionReason" rows="3" placeholder="Ex: Documentação incompleta, credenciais inválidas..."
                        class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                </div>
                <div class="flex justify-end gap-2 px-5 pb-5">
                    <button @click="rejectModal = false" class="rounded-xl px-4 py-2 text-sm text-zinc-600 hover:bg-zinc-100 dark:hover:bg-zinc-800 dark:text-zinc-300">Cancelar</button>
                    <button @click="confirmReject" :disabled="approvalLoading !== null"
                        class="flex items-center gap-2 rounded-xl bg-red-600 hover:bg-red-700 disabled:opacity-60 px-4 py-2 text-sm font-semibold text-white">
                        <Loader2 v-if="approvalLoading" class="h-4 w-4 animate-spin" />
                        <ThumbsDown v-else class="h-4 w-4" />
                        Confirmar rejeição
                    </button>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- Toast -->
    <Teleport to="body">
        <Transition enter-active-class="transition duration-200" enter-from-class="translate-y-2 opacity-0" leave-active-class="transition duration-150" leave-to-class="translate-y-2 opacity-0">
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
