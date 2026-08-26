<script setup>
import { ref, computed } from 'vue';
import LayoutInfoprodutor from '@/Layouts/LayoutInfoprodutor.vue';
import Button from '@/components/ui/Button.vue';
import axios from 'axios';
import {
    Music, Plus, Pencil, Trash2, ChevronDown, ChevronRight,
    Upload, X, CheckCircle, XCircle, Users, Lock, Loader2
} from 'lucide-vue-next';

defineOptions({ layout: LayoutInfoprodutor });

const props = defineProps({
    categories: { type: Array, default: () => [] },
    products: { type: Array, default: () => [] },
});

const categories = ref([...props.categories]);
const expandedCategories = ref({});
const toast = ref({ message: null, type: null });

function showToast(message, type = 'success') {
    toast.value = { message, type };
    setTimeout(() => { toast.value = { message: null, type: null }; }, 4000);
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}
function headers(isMultipart = false) {
    const h = { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' };
    if (!isMultipart) h['Content-Type'] = 'application/json';
    return h;
}

// ─── Categorias ──────────────────────────────────────────────────────────────
const catModalOpen = ref(false);
const catModalEditing = ref(null);
const catModalName = ref('');
const catModalSaving = ref(false);

function openCatModal(cat = null) {
    catModalEditing.value = cat;
    catModalName.value = cat?.name ?? '';
    catModalOpen.value = true;
}
function closeCatModal() {
    catModalOpen.value = false;
    catModalEditing.value = null;
    catModalName.value = '';
}
async function saveCat() {
    if (!catModalName.value.trim()) return;
    catModalSaving.value = true;
    try {
        if (catModalEditing.value) {
            await axios.put(`/musicas/categorias/${catModalEditing.value.id}`, { name: catModalName.value.trim() }, { headers: headers() });
            const idx = categories.value.findIndex(c => c.id === catModalEditing.value.id);
            if (idx >= 0) categories.value[idx].name = catModalName.value.trim();
            showToast('Categoria atualizada.');
        } else {
            const { data } = await axios.post('/musicas/categorias', { name: catModalName.value.trim() }, { headers: headers() });
            categories.value.push(data);
            showToast('Categoria criada.');
        }
        closeCatModal();
    } catch (e) {
        showToast(e.response?.data?.message ?? 'Erro ao salvar.', 'error');
    } finally {
        catModalSaving.value = false;
    }
}
async function deleteCat(cat) {
    if (!confirm(`Excluir a categoria "${cat.name}" e todos os seus áudios?`)) return;
    try {
        await axios.delete(`/musicas/categorias/${cat.id}`, { headers: headers() });
        categories.value = categories.value.filter(c => c.id !== cat.id);
        showToast('Categoria excluída.');
    } catch (e) {
        showToast(e.response?.data?.message ?? 'Erro ao excluir.', 'error');
    }
}

// ─── Tracks ──────────────────────────────────────────────────────────────────
const trackModalOpen = ref(false);
const trackModalCategory = ref(null);
const trackModalEditing = ref(null);
const trackModalTitle = ref('');
const trackModalFile = ref(null);
const trackModalFileInputRef = ref(null);
const trackModalActive = ref(true);
const trackModalAllProducts = ref(true);
const trackModalProductIds = ref([]);
const trackModalSaving = ref(false);
const trackUploadProgress = ref(0);

function openTrackModal(category, track = null) {
    trackModalCategory.value = category;
    trackModalEditing.value = track;
    trackModalTitle.value = track?.title ?? '';
    trackModalFile.value = null;
    trackModalActive.value = track ? track.is_active : true;
    trackModalAllProducts.value = track ? track.available_to_all : true;
    trackModalProductIds.value = track?.product_ids ?? [];
    trackUploadProgress.value = 0;
    trackModalOpen.value = true;
}
function closeTrackModal() {
    trackModalOpen.value = false;
    trackModalCategory.value = null;
    trackModalEditing.value = null;
    trackModalFile.value = null;
    if (trackModalFileInputRef.value) trackModalFileInputRef.value.value = '';
}
function onFileChange(e) {
    const file = e.target?.files?.[0];
    if (!file) return;
    trackModalFile.value = file;
}

async function saveTrack() {
    if (!trackModalTitle.value.trim()) return;
    if (!trackModalEditing.value && !trackModalFile.value) {
        showToast('Selecione um arquivo MP3.', 'error');
        return;
    }
    trackModalSaving.value = true;
    try {
        if (trackModalEditing.value) {
            // Update (sem re-upload de arquivo)
            await axios.put(`/musicas/tracks/${trackModalEditing.value.id}`, {
                title: trackModalTitle.value.trim(),
                is_active: trackModalActive.value,
                available_to_all: trackModalAllProducts.value,
                product_ids: trackModalAllProducts.value ? [] : trackModalProductIds.value,
            }, { headers: headers() });

            const cat = categories.value.find(c => c.id === trackModalCategory.value.id);
            if (cat) {
                const tIdx = cat.tracks.findIndex(t => t.id === trackModalEditing.value.id);
                if (tIdx >= 0) {
                    cat.tracks[tIdx] = {
                        ...cat.tracks[tIdx],
                        title: trackModalTitle.value.trim(),
                        is_active: trackModalActive.value,
                        available_to_all: trackModalAllProducts.value,
                        product_ids: trackModalAllProducts.value ? [] : trackModalProductIds.value,
                    };
                }
            }
            showToast('Áudio atualizado.');
        } else {
            // Create com upload
            const formData = new FormData();
            formData.append('title', trackModalTitle.value.trim());
            formData.append('audio', trackModalFile.value);
            formData.append('is_active', trackModalActive.value ? '1' : '0');
            formData.append('available_to_all', trackModalAllProducts.value ? '1' : '0');
            if (!trackModalAllProducts.value) {
                trackModalProductIds.value.forEach(id => formData.append('product_ids[]', id));
            }

            const { data } = await axios.post(
                `/musicas/categorias/${trackModalCategory.value.id}/tracks`,
                formData,
                {
                    headers: { ...headers(true) },
                    onUploadProgress: e => {
                        trackUploadProgress.value = Math.round((e.loaded / e.total) * 100);
                    },
                }
            );

            const cat = categories.value.find(c => c.id === trackModalCategory.value.id);
            if (cat) cat.tracks.push(data);
            showToast('Áudio adicionado.');
        }
        closeTrackModal();
    } catch (e) {
        showToast(e.response?.data?.message ?? 'Erro ao salvar.', 'error');
    } finally {
        trackModalSaving.value = false;
        trackUploadProgress.value = 0;
    }
}

async function deleteTrack(category, track) {
    if (!confirm(`Excluir o áudio "${track.title}"?`)) return;
    try {
        await axios.delete(`/musicas/tracks/${track.id}`, { headers: headers() });
        const cat = categories.value.find(c => c.id === category.id);
        if (cat) cat.tracks = cat.tracks.filter(t => t.id !== track.id);
        showToast('Áudio excluído.');
    } catch (e) {
        showToast(e.response?.data?.message ?? 'Erro ao excluir.', 'error');
    }
}

async function toggleTrackActive(category, track) {
    try {
        await axios.put(`/musicas/tracks/${track.id}`, { is_active: !track.is_active }, { headers: headers() });
        track.is_active = !track.is_active;
    } catch {
        showToast('Erro ao atualizar status.', 'error');
    }
}

function productName(id) {
    return props.products.find(p => p.id === id)?.name ?? id;
}
</script>

<template>
    <!-- Toast -->
    <Transition enter-active-class="transition duration-300 ease-out" enter-from-class="opacity-0 translate-y-2" enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition duration-200 ease-in" leave-from-class="opacity-100" leave-to-class="opacity-0">
        <div v-if="toast.message" class="fixed bottom-6 right-6 z-[9999] flex items-center gap-3 rounded-xl px-5 py-3 shadow-xl text-sm font-medium text-white"
            :class="toast.type === 'error' ? 'bg-red-600' : 'bg-emerald-600'">
            {{ toast.message }}
        </div>
    </Transition>

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-violet-100 dark:bg-violet-900/30">
                    <Music class="h-5 w-5 text-violet-600 dark:text-violet-400" />
                </div>
                <div>
                    <h1 class="text-xl font-bold text-zinc-900 dark:text-white">Áudios</h1>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">Gerencie categorias e faixas de áudio.</p>
                </div>
            </div>
            <Button @click="openCatModal()">
                <Plus class="mr-2 h-4 w-4" /> Nova categoria
            </Button>
        </div>

        <!-- Sem categorias -->
        <div v-if="!categories.length" class="flex flex-col items-center justify-center gap-4 rounded-2xl border-2 border-dashed border-zinc-200 py-20 dark:border-zinc-700">
            <Music class="h-12 w-12 text-zinc-300 dark:text-zinc-600" />
            <div class="text-center">
                <p class="font-semibold text-zinc-700 dark:text-zinc-300">Nenhuma categoria ainda</p>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Crie uma categoria e depois adicione áudios.</p>
            </div>
            <Button @click="openCatModal()"><Plus class="mr-2 h-4 w-4" /> Nova categoria</Button>
        </div>

        <!-- Lista de categorias -->
        <div class="space-y-4">
            <div v-for="cat in categories" :key="cat.id" class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-800/50">
                <!-- Header da categoria -->
                <div class="flex items-center gap-3 px-5 py-4">
                    <button type="button" class="flex items-center gap-2 min-w-0 flex-1 text-left" @click="expandedCategories[cat.id] = !expandedCategories[cat.id]">
                        <component :is="expandedCategories[cat.id] ? ChevronDown : ChevronRight" class="h-4 w-4 shrink-0 text-zinc-400" />
                        <span class="font-semibold text-zinc-900 dark:text-white">{{ cat.name }}</span>
                        <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs text-zinc-500 dark:bg-zinc-700 dark:text-zinc-400">
                            {{ cat.tracks.length }} {{ cat.tracks.length === 1 ? 'áudio' : 'áudios' }}
                        </span>
                    </button>
                    <div class="flex shrink-0 items-center gap-1">
                        <button type="button" class="rounded-lg p-1.5 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-600 dark:hover:bg-zinc-700 dark:hover:text-zinc-300" title="Adicionar áudio" @click="openTrackModal(cat)">
                            <Plus class="h-4 w-4" />
                        </button>
                        <button type="button" class="rounded-lg p-1.5 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-600 dark:hover:bg-zinc-700 dark:hover:text-zinc-300" title="Editar categoria" @click="openCatModal(cat)">
                            <Pencil class="h-4 w-4" />
                        </button>
                        <button type="button" class="rounded-lg p-1.5 text-zinc-400 hover:bg-red-50 hover:text-red-500 dark:hover:bg-red-950/30 dark:hover:text-red-400" title="Excluir categoria" @click="deleteCat(cat)">
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>
                </div>

                <!-- Tracks -->
                <div v-if="expandedCategories[cat.id]" class="border-t border-zinc-100 dark:border-zinc-700">
                    <div v-if="!cat.tracks.length" class="flex items-center justify-center gap-2 py-8 text-sm text-zinc-400 dark:text-zinc-500">
                        <Music class="h-4 w-4" />
                        Nenhum áudio. <button type="button" class="font-medium text-violet-500 hover:underline" @click="openTrackModal(cat)">Adicionar</button>
                    </div>

                    <div v-else class="divide-y divide-zinc-100 dark:divide-zinc-700/50">
                        <div v-for="track in cat.tracks" :key="track.id" class="flex items-center gap-4 px-5 py-3">
                            <!-- Ícone / play area -->
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-violet-50 dark:bg-violet-900/20">
                                <Music class="h-4 w-4 text-violet-500" />
                            </div>

                            <!-- Info -->
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <p class="truncate font-medium text-zinc-900 dark:text-white">{{ track.title }}</p>
                                    <span v-if="!track.is_active" class="shrink-0 rounded-full bg-zinc-100 px-2 py-0.5 text-xs text-zinc-400 dark:bg-zinc-700">Inativo</span>
                                </div>
                                <div class="mt-0.5 flex items-center gap-2 text-xs text-zinc-400">
                                    <span v-if="track.available_to_all" class="flex items-center gap-1"><Users class="h-3 w-3" /> Todos os alunos</span>
                                    <span v-else class="flex items-center gap-1">
                                        <Lock class="h-3 w-3" />
                                        {{ track.product_ids.map(productName).join(', ') || 'Nenhuma trilha' }}
                                    </span>
                                </div>
                            </div>

                            <!-- Preview de áudio -->
                            <audio v-if="track.file_url" :src="track.file_url" controls class="h-8 w-40 shrink-0" />

                            <!-- Ações -->
                            <div class="flex shrink-0 items-center gap-1">
                                <button type="button"
                                    class="rounded-lg p-1.5 transition"
                                    :class="track.is_active ? 'text-emerald-500 hover:bg-emerald-50 dark:hover:bg-emerald-950/30' : 'text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-700'"
                                    :title="track.is_active ? 'Desativar' : 'Ativar'"
                                    @click="toggleTrackActive(cat, track)">
                                    <component :is="track.is_active ? CheckCircle : XCircle" class="h-4 w-4" />
                                </button>
                                <button type="button" class="rounded-lg p-1.5 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-600 dark:hover:bg-zinc-700 dark:hover:text-zinc-300" title="Editar" @click="openTrackModal(cat, track)">
                                    <Pencil class="h-4 w-4" />
                                </button>
                                <button type="button" class="rounded-lg p-1.5 text-zinc-400 hover:bg-red-50 hover:text-red-500 dark:hover:bg-red-950/30 dark:hover:text-red-400" title="Excluir" @click="deleteTrack(cat, track)">
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Botão adicionar dentro da categoria expandida -->
                    <div class="border-t border-zinc-100 px-5 py-3 dark:border-zinc-700">
                        <button type="button" class="flex items-center gap-2 text-sm text-violet-500 hover:text-violet-600 dark:hover:text-violet-400" @click="openTrackModal(cat)">
                            <Plus class="h-4 w-4" /> Adicionar áudio
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Categoria -->
    <Teleport to="body">
        <div v-if="catModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="closeCatModal">
            <div class="w-full max-w-sm rounded-2xl bg-white shadow-2xl dark:bg-zinc-900">
                <div class="flex items-center justify-between border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                    <h3 class="font-semibold text-zinc-900 dark:text-white">{{ catModalEditing ? 'Editar categoria' : 'Nova categoria' }}</h3>
                    <button type="button" class="rounded-lg p-1 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200" @click="closeCatModal"><X class="h-4 w-4" /></button>
                </div>
                <div class="p-5">
                    <label class="mb-1.5 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Nome da categoria</label>
                    <input v-model="catModalName" type="text"
                        class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm text-zinc-900 focus:border-violet-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                        placeholder="Ex: Meditação, Relaxamento, Foco..."
                        @keydown.enter="saveCat" />
                </div>
                <div class="flex justify-end gap-2 border-t border-zinc-200 px-5 py-3 dark:border-zinc-700">
                    <Button variant="outline" @click="closeCatModal">Cancelar</Button>
                    <Button :disabled="catModalSaving || !catModalName.trim()" @click="saveCat">
                        <Loader2 v-if="catModalSaving" class="mr-2 h-4 w-4 animate-spin" />
                        {{ catModalEditing ? 'Salvar' : 'Criar' }}
                    </Button>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- Modal Música -->
    <Teleport to="body">
        <div v-if="trackModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="closeTrackModal">
            <div class="w-full max-w-lg rounded-2xl bg-white shadow-2xl dark:bg-zinc-900">
                <div class="flex items-center justify-between border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                    <h3 class="font-semibold text-zinc-900 dark:text-white">
                        {{ trackModalEditing ? 'Editar áudio' : 'Adicionar áudio' }}
                        <span v-if="trackModalCategory" class="ml-2 text-sm font-normal text-zinc-400">— {{ trackModalCategory.name }}</span>
                    </h3>
                    <button type="button" class="rounded-lg p-1 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200" @click="closeTrackModal"><X class="h-4 w-4" /></button>
                </div>
                <div class="space-y-4 p-5">
                    <!-- Título -->
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Nome do áudio</label>
                        <input v-model="trackModalTitle" type="text"
                            class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-2.5 text-sm text-zinc-900 focus:border-violet-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                            placeholder="Ex: Relaxamento Profundo" />
                    </div>

                    <!-- Arquivo MP3 (só na criação) -->
                    <div v-if="!trackModalEditing">
                        <label class="mb-1.5 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Arquivo de áudio</label>
                        <input ref="trackModalFileInputRef" type="file" accept=".mp3,.ogg,.wav,.aac,.m4a" class="hidden" @change="onFileChange" />
                        <button type="button"
                            class="flex w-full items-center justify-center gap-2 rounded-xl border-2 border-dashed border-zinc-300 py-6 text-sm text-zinc-500 transition hover:border-violet-400 hover:text-violet-500 dark:border-zinc-600 dark:hover:border-violet-500"
                            @click="trackModalFileInputRef?.click()">
                            <Upload class="h-5 w-5" />
                            {{ trackModalFile ? trackModalFile.name : 'Clique para selecionar MP3, OGG, WAV...' }}
                        </button>
                        <!-- Progress bar -->
                        <div v-if="trackUploadProgress > 0 && trackUploadProgress < 100" class="mt-2">
                            <div class="h-1.5 w-full rounded-full bg-zinc-200 dark:bg-zinc-700">
                                <div class="h-1.5 rounded-full bg-violet-500 transition-all" :style="{ width: trackUploadProgress + '%' }" />
                            </div>
                            <p class="mt-1 text-center text-xs text-zinc-400">Enviando... {{ trackUploadProgress }}%</p>
                        </div>
                    </div>

                    <!-- Status -->
                    <div class="flex items-center justify-between rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 dark:border-zinc-700 dark:bg-zinc-800/50">
                        <div>
                            <p class="text-sm font-medium text-zinc-800 dark:text-zinc-200">Áudio ativo</p>
                            <p class="text-xs text-zinc-400">Alunos podem ouvir este áudio</p>
                        </div>
                        <button type="button"
                            class="relative inline-flex h-6 w-11 shrink-0 rounded-full border-2 border-transparent transition-colors duration-200"
                            :class="trackModalActive ? 'bg-violet-600' : 'bg-zinc-300 dark:bg-zinc-600'"
                            @click="trackModalActive = !trackModalActive">
                            <span class="inline-block h-5 w-5 rounded-full bg-white shadow transition-transform duration-200"
                                :class="trackModalActive ? 'translate-x-5' : 'translate-x-0'" />
                        </button>
                    </div>

                    <!-- Disponibilidade -->
                    <div>
                        <p class="mb-2 text-sm font-medium text-zinc-700 dark:text-zinc-300">Disponibilidade</p>
                        <div class="grid grid-cols-2 gap-3">
                            <button type="button"
                                class="flex flex-col items-center gap-1.5 rounded-xl border-2 p-3 text-center transition"
                                :class="trackModalAllProducts ? 'border-violet-500 bg-violet-50 dark:bg-violet-900/20' : 'border-zinc-200 bg-zinc-50 hover:border-zinc-300 dark:border-zinc-600 dark:bg-zinc-800/50'"
                                @click="trackModalAllProducts = true">
                                <Users class="h-5 w-5" :class="trackModalAllProducts ? 'text-violet-600' : 'text-zinc-400'" />
                                <span class="text-xs font-medium" :class="trackModalAllProducts ? 'text-violet-700 dark:text-violet-300' : 'text-zinc-600 dark:text-zinc-400'">Todos os alunos</span>
                            </button>
                            <button type="button"
                                class="flex flex-col items-center gap-1.5 rounded-xl border-2 p-3 text-center transition"
                                :class="!trackModalAllProducts ? 'border-violet-500 bg-violet-50 dark:bg-violet-900/20' : 'border-zinc-200 bg-zinc-50 hover:border-zinc-300 dark:border-zinc-600 dark:bg-zinc-800/50'"
                                @click="trackModalAllProducts = false">
                                <Lock class="h-5 w-5" :class="!trackModalAllProducts ? 'text-violet-600' : 'text-zinc-400'" />
                                <span class="text-xs font-medium" :class="!trackModalAllProducts ? 'text-violet-700 dark:text-violet-300' : 'text-zinc-600 dark:text-zinc-400'">Trilhas específicas</span>
                            </button>
                        </div>

                        <!-- Seleção de cursos -->
                        <div v-if="!trackModalAllProducts" class="mt-3 space-y-1.5 rounded-xl border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-800/50">
                            <p class="mb-2 text-xs font-medium text-zinc-500 dark:text-zinc-400">Selecione as trilhas:</p>
                            <label v-for="p in products" :key="p.id" class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2 py-1.5 hover:bg-zinc-100 dark:hover:bg-zinc-700/50">
                                <input type="checkbox" :value="p.id" v-model="trackModalProductIds"
                                    class="h-4 w-4 rounded border-zinc-300 text-violet-600 focus:ring-violet-500" />
                                <span class="text-sm text-zinc-800 dark:text-zinc-200">{{ p.name }}</span>
                            </label>
                            <p v-if="!products.length" class="text-xs text-zinc-400">Nenhuma trilha de área de membros encontrada.</p>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2 border-t border-zinc-200 px-5 py-3 dark:border-zinc-700">
                    <Button variant="outline" @click="closeTrackModal">Cancelar</Button>
                    <Button :disabled="trackModalSaving || !trackModalTitle.trim() || (!trackModalEditing && !trackModalFile)" @click="saveTrack">
                        <Loader2 v-if="trackModalSaving" class="mr-2 h-4 w-4 animate-spin" />
                        {{ trackModalEditing ? 'Salvar' : 'Adicionar áudio' }}
                    </Button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
