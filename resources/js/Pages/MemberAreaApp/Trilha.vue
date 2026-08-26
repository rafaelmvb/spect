<script setup>
import { computed } from 'vue';
import MemberAreaAppLayout from '@/Layouts/MemberAreaAppLayout.vue';
import { useMemberBase } from '@/composables/useMemberBase';
import { BookOpen, ChevronRight } from 'lucide-vue-next';

defineOptions({ layout: MemberAreaAppLayout });

const props = defineProps({
    courses:  { type: Array,  default: () => [] },
    product:  { type: Object, required: true },
    config:   { type: Object, default: () => ({}) },
    base_url: { type: String, default: '' },
    slug:     { type: String, required: true },
});

const primaryColor = computed(() => props.config?.theme?.primary || '#6FCF00');
const memberBase   = useMemberBase(computed(() => props.slug));

function courseUrl(course) {
    return `${memberBase.value}/modulos/${course.id}`;
}
</script>

<template>
    <div style="padding:16px; max-width:520px; margin:0 auto; padding-bottom:120px; font-family:'DM Sans',sans-serif;">

        <!-- Cabeçalho -->
        <div style="padding:8px 0 24px;">
            <p style="font-family:'DM Mono',monospace; font-size:11px; text-transform:uppercase; letter-spacing:1.5px; color:var(--ma-text-2); margin:0 0 6px;">sua jornada</p>
            <h1 style="font-size:28px; font-weight:800; margin:0; color:var(--ma-text);">Suas trilhas</h1>
        </div>

        <!-- Lista de cursos -->
        <div style="display:flex; flex-direction:column; gap:12px;">
            <a
                v-for="course in courses"
                :key="course.id"
                :href="courseUrl(course)"
                style="display:flex; align-items:center; gap:16px; border-radius:16px; padding:16px; text-decoration:none; border:1px solid var(--ma-border); background:var(--ma-surface); transition:border-color 0.18s;"
                @mouseover="$event.currentTarget.style.borderColor = primaryColor"
                @mouseout="$event.currentTarget.style.borderColor = 'var(--ma-border)'"
            >
                <!-- Capa ou ícone -->
                <div style="width:56px; height:56px; border-radius:12px; overflow:hidden; flex-shrink:0; background:var(--ma-surface-2); display:flex; align-items:center; justify-content:center;">
                    <img v-if="course.image_url" :src="course.image_url" :alt="course.name" style="width:100%; height:100%; object-fit:cover;" />
                    <BookOpen v-else style="width:24px; height:24px; color:var(--ma-text-2);" />
                </div>

                <!-- Info -->
                <div style="flex:1; min-width:0;">
                    <p style="font-size:15px; font-weight:700; color:var(--ma-text); margin:0 0 4px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ course.name }}</p>
                    <p v-if="course.description" style="font-size:12px; color:var(--ma-text-2); margin:0 0 8px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ course.description }}</p>

                    <!-- Barra de progresso -->
                    <div style="display:flex; align-items:center; gap:8px;">
                        <div style="flex:1; height:3px; background:var(--ma-surface-2); border-radius:2px; overflow:hidden;">
                            <div style="height:100%; border-radius:2px; transition:width 0.4s ease;"
                                :style="{ width: course.progress_percent + '%', background: primaryColor }" />
                        </div>
                        <span style="font-family:'DM Mono',monospace; font-size:11px; color:var(--ma-text-2); flex-shrink:0;">{{ course.progress_percent }}%</span>
                    </div>
                </div>

                <ChevronRight style="width:18px; height:18px; color:var(--ma-text-2); flex-shrink:0;" />
            </a>

            <div v-if="!courses.length" style="text-align:center; padding:60px 24px; color:var(--ma-text-2); font-size:15px;">
                Nenhum curso disponível.
            </div>
        </div>
    </div>
</template>
