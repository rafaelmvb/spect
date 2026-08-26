<script setup>
import { ref, reactive } from 'vue';
import LayoutProfissional from '@/Layouts/LayoutProfissional.vue';
import { User, Camera, Save, Loader2 } from 'lucide-vue-next';

defineOptions({ layout: LayoutProfissional });

const props = defineProps({
    professional: { type: Object, required: true },
});

const saving  = ref(false);
const toast   = ref(null);
const preview = ref(props.professional.avatar_url);
const fileRef = ref(null);

const form = reactive({
    name:                props.professional.name ?? '',
    email:               props.professional.email ?? '',
    phone:               props.professional.phone ?? '',
    website:             props.professional.website ?? '',
    bio:                 props.professional.bio ?? '',
    experience:          props.professional.experience ?? '',
    specialty:           props.professional.specialty ?? '',
    registration_type:   props.professional.registration_type ?? '',
    registration_number: props.professional.registration_number ?? '',
    price_per_hour:      props.professional.price_per_hour ?? '',
    social_links: {
        instagram: props.professional.social_links?.instagram ?? '',
        linkedin:  props.professional.social_links?.linkedin ?? '',
        facebook:  props.professional.social_links?.facebook ?? '',
        youtube:   props.professional.social_links?.youtube ?? '',
        tiktok:    props.professional.social_links?.tiktok ?? '',
    },
});

let avatarFile = null;

function pickAvatar() { fileRef.value?.click(); }

function onAvatarChange(e) {
    avatarFile = e.target.files[0];
    if (avatarFile) preview.value = URL.createObjectURL(avatarFile);
}

function csrfToken() { return document.querySelector('meta[name="csrf-token"]')?.content ?? ''; }

async function save() {
    saving.value = true;
    try {
        const fd = new FormData();
        Object.entries(form).forEach(([k, v]) => {
            if (k === 'social_links') {
                Object.entries(v).forEach(([sk, sv]) => fd.append(`social_links[${sk}]`, sv ?? ''));
            } else {
                fd.append(k, v ?? '');
            }
        });
        if (avatarFile) fd.append('avatar', avatarFile);

        const res = await window.axios.post('/p/perfil', fd, {
            headers: { 'X-CSRF-TOKEN': csrfToken(), 'Content-Type': 'multipart/form-data' },
        });
        if (res.data.success) showToast('Perfil atualizado com sucesso!');
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

const REGISTRATION_TYPES = ['CRP', 'CRM', 'CREFITO', 'COREN', 'CRO', 'CRN', 'CREFONO', 'Outro'];
</script>

<template>
    <div class="max-w-3xl space-y-6">
        <!-- Header -->
        <div>
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Meu Perfil</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">Mantenha seus dados atualizados para atrair mais clientes.</p>
        </div>

        <!-- Toast -->
        <div v-if="toast" class="rounded-xl px-4 py-3 text-sm font-medium"
            :class="toast.type === 'error' ? 'bg-red-50 text-red-700 border border-red-200 dark:bg-red-900/20 dark:text-red-300' : 'bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-900/20 dark:text-emerald-300'">
            {{ toast.msg }}
        </div>

        <!-- Foto de perfil -->
        <div class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60 p-5">
            <h2 class="text-sm font-semibold text-zinc-700 dark:text-zinc-200 mb-4">Foto de Perfil</h2>
            <div class="flex items-center gap-5">
                <div class="relative">
                    <div class="h-20 w-20 rounded-2xl bg-zinc-100 dark:bg-zinc-800 overflow-hidden">
                        <img v-if="preview" :src="preview" alt="avatar" class="h-full w-full object-cover" />
                        <div v-else class="h-full w-full flex items-center justify-center">
                            <User class="h-8 w-8 text-zinc-300" />
                        </div>
                    </div>
                    <button @click="pickAvatar"
                        class="absolute -bottom-1 -right-1 h-7 w-7 rounded-full bg-violet-600 hover:bg-violet-700 flex items-center justify-center shadow">
                        <Camera class="h-3.5 w-3.5 text-white" />
                    </button>
                    <input ref="fileRef" type="file" accept="image/*" class="hidden" @change="onAvatarChange" />
                </div>
                <div>
                    <p class="text-sm font-medium text-zinc-900 dark:text-white">{{ professional.name }}</p>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">{{ professional.specialty ?? 'Especialidade não informada' }}</p>
                    <button @click="pickAvatar" class="mt-2 text-xs text-violet-600 hover:underline dark:text-violet-400">Alterar foto</button>
                </div>
            </div>
        </div>

        <!-- Dados pessoais -->
        <div class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60 p-5 space-y-4">
            <h2 class="text-sm font-semibold text-zinc-700 dark:text-zinc-200">Informações Pessoais</h2>
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-zinc-500 mb-1">Nome completo *</label>
                    <input v-model="form.name" type="text" class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm focus:border-violet-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-900 dark:text-white" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-zinc-500 mb-1">E-mail</label>
                    <input v-model="form.email" type="email" class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm focus:border-violet-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-900 dark:text-white" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-zinc-500 mb-1">Telefone / WhatsApp</label>
                    <input v-model="form.phone" type="tel" class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm focus:border-violet-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-900 dark:text-white" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-zinc-500 mb-1">Website</label>
                    <input v-model="form.website" type="url" placeholder="https://" class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm focus:border-violet-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-900 dark:text-white" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-zinc-500 mb-1">Especialidade</label>
                    <input v-model="form.specialty" type="text" class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm focus:border-violet-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-900 dark:text-white" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-zinc-500 mb-1">Valor por hora (R$)</label>
                    <input v-model="form.price_per_hour" type="number" min="0" step="0.01" class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm focus:border-violet-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-900 dark:text-white" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-zinc-500 mb-1">Tipo de registro</label>
                    <select v-model="form.registration_type" class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm focus:border-violet-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-900 dark:text-white">
                        <option value="">Selecionar</option>
                        <option v-for="t in REGISTRATION_TYPES" :key="t" :value="t">{{ t }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-zinc-500 mb-1">Número de registro</label>
                    <input v-model="form.registration_number" type="text" class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm focus:border-violet-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-900 dark:text-white" />
                </div>
            </div>
        </div>

        <!-- Bio e experiências -->
        <div class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60 p-5 space-y-4">
            <h2 class="text-sm font-semibold text-zinc-700 dark:text-zinc-200">Apresentação</h2>
            <div>
                <label class="block text-xs font-medium text-zinc-500 mb-1">Descrição profissional</label>
                <textarea v-model="form.bio" rows="4" placeholder="Fale sobre você, sua abordagem e o que oferece..."
                    class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm focus:border-violet-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-900 dark:text-white resize-none" />
            </div>
            <div>
                <label class="block text-xs font-medium text-zinc-500 mb-1">Experiências e formação</label>
                <textarea v-model="form.experience" rows="4" placeholder="Formação acadêmica, pós-graduações, certificações, anos de experiência..."
                    class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm focus:border-violet-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-900 dark:text-white resize-none" />
            </div>
        </div>

        <!-- Redes sociais -->
        <div class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/60 p-5 space-y-4">
            <h2 class="text-sm font-semibold text-zinc-700 dark:text-zinc-200">Redes Sociais</h2>
            <div class="grid sm:grid-cols-2 gap-4">
                <div v-for="net in ['instagram', 'linkedin', 'facebook', 'youtube', 'tiktok']" :key="net">
                    <label class="block text-xs font-medium text-zinc-500 mb-1 capitalize">{{ net }}</label>
                    <input v-model="form.social_links[net]" type="url" :placeholder="`URL do ${net}`"
                        class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm focus:border-violet-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-900 dark:text-white" />
                </div>
            </div>
        </div>

        <!-- Botão salvar -->
        <div class="flex justify-end">
            <button @click="save" :disabled="saving"
                class="flex items-center gap-2 rounded-xl bg-violet-600 hover:bg-violet-700 disabled:opacity-60 px-6 py-2.5 text-sm font-semibold text-white transition">
                <Loader2 v-if="saving" class="h-4 w-4 animate-spin" />
                <Save v-else class="h-4 w-4" />
                {{ saving ? 'Salvando…' : 'Salvar alterações' }}
            </button>
        </div>
    </div>
</template>
