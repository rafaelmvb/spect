<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';
import LayoutInfoprodutor from '@/Layouts/LayoutInfoprodutor.vue';
import { BookOpen, Check, Save } from 'lucide-vue-next';

defineOptions({ layout: LayoutInfoprodutor });

const props = defineProps({
    products: { type: Array, default: () => [] },
    featured_ids: { type: Array, default: () => [] },
});

const selected = ref(new Set(props.featured_ids));
const saving = ref(false);
const saved = ref(false);

function toggle(productId) {
    if (selected.value.has(productId)) {
        selected.value.delete(productId);
    } else {
        selected.value.add(productId);
    }
    selected.value = new Set(selected.value);
}

const selectedCount = computed(() => selected.value.size);

async function save() {
    if (saving.value) return;
    saving.value = true;
    saved.value = false;
    try {
        await axios.post('/member-home-featured/sync', {
            product_ids: [...selected.value],
        });
        saved.value = true;
        setTimeout(() => { saved.value = false; }, 2500);
    } catch {
        alert('Erro ao salvar.');
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <div class="p-6">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Trilhas em Destaque</h1>
                <p class="mt-1 text-sm text-zinc-500">Selecione as trilhas que aparecem na página inicial da área de membros.</p>
            </div>
            <button
                type="button"
                :disabled="saving"
                class="flex items-center gap-2 rounded-xl bg-[var(--color-primary)] px-4 py-2.5 text-sm font-medium text-white shadow hover:opacity-90 disabled:opacity-60"
                @click="save"
            >
                <Check v-if="saved" class="h-4 w-4" />
                <Save v-else class="h-4 w-4" />
                {{ saved ? 'Salvo!' : (saving ? 'Salvando...' : 'Salvar seleção') }}
            </button>
        </div>

        <p class="mb-4 text-sm text-zinc-500">
            <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ selectedCount }}</span> curso(s) selecionado(s)
        </p>

        <div v-if="products.length" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            <button
                v-for="product in products"
                :key="product.id"
                type="button"
                :class="[
                    'relative overflow-hidden rounded-2xl border-2 text-left transition',
                    selected.has(product.id)
                        ? 'border-[var(--color-primary)] shadow-md shadow-[var(--color-primary)]/20'
                        : 'border-zinc-200 dark:border-zinc-700',
                ]"
                @click="toggle(product.id)"
            >
                <!-- Checkmark overlay -->
                <div
                    v-if="selected.has(product.id)"
                    class="absolute right-2 top-2 z-10 flex h-7 w-7 items-center justify-center rounded-full bg-[var(--color-primary)] shadow"
                >
                    <Check class="h-4 w-4 text-white" />
                </div>

                <div class="aspect-video bg-zinc-100 dark:bg-zinc-700">
                    <img v-if="product.image_url" :src="product.image_url" :alt="product.name" class="h-full w-full object-cover" />
                    <div v-else class="flex h-full w-full items-center justify-center text-zinc-400">
                        <BookOpen class="h-10 w-10" />
                    </div>
                </div>
                <div class="bg-white p-3 dark:bg-zinc-800">
                    <p class="font-medium text-zinc-900 line-clamp-2 dark:text-white">{{ product.name }}</p>
                </div>
            </button>
        </div>

        <div v-else class="rounded-2xl border-2 border-dashed border-zinc-200 py-20 text-center dark:border-zinc-700">
            <BookOpen class="mx-auto h-10 w-10 text-zinc-300" />
            <p class="mt-3 text-zinc-500">Nenhum produto ativo encontrado.</p>
        </div>
    </div>
</template>
