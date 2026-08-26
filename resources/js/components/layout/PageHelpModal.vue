<script setup>
import { onMounted, onUnmounted } from 'vue';
import { X } from 'lucide-vue-next';

const props = defineProps({
    help: { type: Object, required: true },
});
const emit = defineEmits(['close']);

function onKey(e) {
    if (e.key === 'Escape') emit('close');
}
onMounted(() => document.addEventListener('keydown', onKey));
onUnmounted(() => document.removeEventListener('keydown', onKey));
</script>

<template>
    <Teleport to="body">
        <!-- Backdrop -->
        <div
            class="fixed inset-0 z-[100010] flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
            :aria-label="help.title + ' — Ajuda'"
        >
            <div
                class="absolute inset-0 bg-zinc-900/60 backdrop-blur-sm"
                @click="emit('close')"
            />

            <!-- Card -->
            <div class="relative z-10 w-full max-w-lg max-h-[85vh] overflow-y-auto rounded-2xl border border-zinc-200 bg-white shadow-2xl dark:border-zinc-700 dark:bg-zinc-900">
                <!-- Cabeçalho -->
                <div class="sticky top-0 z-10 flex items-center justify-between gap-3 border-b border-zinc-100 bg-white px-5 py-4 dark:border-zinc-700/60 dark:bg-zinc-900">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[var(--color-primary)]/10 text-xl" aria-hidden="true">
                            {{ help.icon }}
                        </span>
                        <div>
                            <p class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Como usar</p>
                            <h2 class="text-base font-semibold text-zinc-900 dark:text-white">{{ help.title }}</h2>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-600 dark:hover:bg-zinc-800 dark:hover:text-zinc-300"
                        aria-label="Fechar"
                        @click="emit('close')"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <!-- Corpo -->
                <div class="px-5 py-4 space-y-5">
                    <!-- Descrição -->
                    <p class="text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">{{ help.description }}</p>

                    <!-- Seções -->
                    <div
                        v-for="(section, i) in help.sections"
                        :key="i"
                        class="rounded-xl border border-zinc-100 bg-zinc-50 p-4 dark:border-zinc-700/50 dark:bg-zinc-800/50"
                    >
                        <h3 class="mb-2.5 text-xs font-semibold uppercase tracking-wide text-[var(--color-primary)]">
                            {{ section.heading }}
                        </h3>
                        <ul class="space-y-1.5">
                            <li
                                v-for="(item, j) in section.items"
                                :key="j"
                                class="flex items-start gap-2 text-sm text-zinc-700 dark:text-zinc-300"
                            >
                                <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-[var(--color-primary)]/70" aria-hidden="true" />
                                <span>{{ item }}</span>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Rodapé -->
                <div class="border-t border-zinc-100 px-5 py-3 dark:border-zinc-700/60">
                    <p class="text-center text-xs text-zinc-400 dark:text-zinc-600">Pressione <kbd class="rounded border border-zinc-200 bg-zinc-100 px-1.5 py-0.5 font-mono text-[11px] text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-400">Esc</kbd> para fechar</p>
                </div>
            </div>
        </div>
    </Teleport>
</template>
