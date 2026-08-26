<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, CalendarDays, Tag } from 'lucide-vue-next';
import MemberAreaAppLayout from '@/Layouts/MemberAreaAppLayout.vue';
import { useMemberBase } from '@/composables/useMemberBase';

defineOptions({ layout: MemberAreaAppLayout });

const props = defineProps({
    post:     { type: Object, required: true },
    product:  { type: Object, required: true },
    base_url: { type: String, default: '' },
    slug:     { type: String, required: true },
    config:   { type: Object, default: () => ({}) },
});

const memberBase = useMemberBase(computed(() => props.slug));
</script>

<template>
    <div class="mx-auto max-w-3xl">

        <!-- Voltar -->
        <Link :href="memberBase" class="mb-6 inline-flex items-center gap-1.5 text-sm text-zinc-500 transition hover:text-white">
            <ChevronLeft class="h-4 w-4" /> Início
        </Link>

        <!-- Imagem de capa -->
        <div v-if="post.image_url" class="mb-8 overflow-hidden rounded-2xl">
            <img :src="post.image_url" :alt="post.title" class="w-full object-cover" style="max-height: 420px;" />
        </div>

        <!-- Cabeçalho -->
        <header class="mb-8 space-y-3">
            <div class="flex flex-wrap items-center gap-3">
                <span v-if="post.category" class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wider" :style="{ background: 'var(--ma-primary)', color: 'var(--ma-on-primary)' }">
                    <Tag class="h-3 w-3" /> {{ post.category }}
                </span>
                <span v-if="post.published_at" class="flex items-center gap-1 text-xs text-zinc-500">
                    <CalendarDays class="h-3.5 w-3.5" /> {{ post.published_at }}
                </span>
            </div>
            <h1 class="text-2xl font-bold leading-snug text-white sm:text-3xl">{{ post.title }}</h1>
            <p v-if="post.excerpt" class="text-base text-zinc-400">{{ post.excerpt }}</p>
        </header>

        <!-- Divisor -->
        <hr class="mb-8 border-[var(--ma-border)]" />

        <!-- Conteúdo -->
        <div class="prose prose-invert prose-zinc max-w-none text-zinc-300
                    prose-headings:text-white
                    prose-a:text-[var(--ma-primary)] prose-a:no-underline hover:prose-a:underline
                    prose-strong:text-white
                    prose-code:rounded prose-code:bg-zinc-800 prose-code:px-1 prose-code:text-zinc-200
                    prose-pre:bg-zinc-800 prose-pre:border prose-pre:border-[var(--ma-border)]
                    prose-img:rounded-xl prose-img:border prose-img:border-[var(--ma-border)]
                    prose-blockquote:border-[var(--ma-primary)] prose-blockquote:text-zinc-400
                    [&_iframe]:w-full [&_iframe]:aspect-video [&_iframe]:rounded-xl [&_iframe]:border [&_iframe]:border-[var(--ma-border)]
                    [&_img]:max-w-full"
             v-html="post.content"
        />

        <!-- Rodapé -->
        <div class="mt-12 border-t border-[var(--ma-border)] pt-8">
            <Link :href="memberBase" class="inline-flex items-center gap-2 rounded-xl border border-[var(--ma-border)] bg-[var(--ma-surface)] px-4 py-2.5 text-sm text-zinc-400 transition hover:border-[var(--ma-primary)]/50 hover:text-white">
                <ChevronLeft class="h-4 w-4" /> Voltar ao início
            </Link>
        </div>
    </div>
</template>
