<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import MemberAreaAppLayout from '@/Layouts/MemberAreaAppLayout.vue';
import { MapPin, ChevronRight, BookOpen } from 'lucide-vue-next';
import { useMemberBase } from '@/composables/useMemberBase';

defineOptions({ layout: MemberAreaAppLayout });

const props = defineProps({
    product:  { type: Object, required: true },
    journeys: { type: Array,  default: () => [] },
    base_url: { type: String, default: '' },
    slug:     { type: String, default: '' },
});

const page = usePage();
const primaryColor = computed(() =>
    page.props?.member_area_global_branding?.primary_color
    || props.product?.member_area_config?.theme?.primary
    || '#6FCF00'
);

function primaryRgba(opacity) {
    const hex = (primaryColor.value || '#6FCF00').replace('#', '');
    if (hex.length !== 6) return `rgba(111,207,0,${opacity})`;
    const r = parseInt(hex.substring(0, 2), 16);
    const g = parseInt(hex.substring(2, 4), 16);
    const b = parseInt(hex.substring(4, 6), 16);
    return `rgba(${r},${g},${b},${opacity})`;
}

const memberBase = useMemberBase(computed(() => props.slug));

function journeyUrl(journey) {
    return `${memberBase.value}/jornadas/${journey.id}`;
}
</script>

<template>
    <div style="padding:16px; max-width:480px; margin:0 auto; padding-bottom:120px; font-family:'DM Sans',sans-serif;">

        <!-- Header -->
        <div style="display:flex; align-items:center; gap:12px; padding:8px 0 24px;">
            <div style="width:44px; height:44px; border-radius:14px; display:flex; align-items:center; justify-content:center; flex-shrink:0;"
                :style="{ background: primaryRgba(0.12) }">
                <MapPin style="width:22px; height:22px;" :style="{ color: primaryColor }" />
            </div>
            <div>
                <h1 style="font-size:24px; font-weight:800; color:var(--ma-text); margin:0 0 4px;">Minhas Jornadas</h1>
                <p style="font-size:13px; color:var(--ma-text-2); margin:0; font-family:'DM Mono',monospace;">Caminhos de aprendizado recomendados por especialistas.</p>
            </div>
        </div>

        <!-- Lista de jornadas -->
        <div v-if="journeys.length" style="display:flex; flex-direction:column; gap:12px;">
            <a v-for="j in journeys" :key="j.id" :href="journeyUrl(j)"
                style="display:flex; align-items:flex-start; gap:16px; border-radius:20px; border:1px solid var(--ma-border); background:var(--ma-surface); padding:20px; text-decoration:none; transition:border-color 0.2s;"
                @mouseenter="$event.currentTarget.style.borderColor=primaryColor"
                @mouseleave="$event.currentTarget.style.borderColor='var(--ma-border)'">
                <!-- Capa -->
                <div style="width:72px; height:72px; border-radius:12px; overflow:hidden; flex-shrink:0;">
                    <img v-if="j.cover_url" :src="j.cover_url" :alt="j.name" style="width:100%; height:100%; object-fit:cover;" />
                    <div v-else style="width:100%; height:100%; display:flex; align-items:center; justify-content:center;"
                        :style="{ background: primaryRgba(0.1) }">
                        <MapPin style="width:28px; height:28px;" :style="{ color: primaryColor }" />
                    </div>
                </div>
                <div style="flex:1; min-width:0;">
                    <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:8px;">
                        <h2 style="font-size:17px; font-weight:700; color:var(--ma-text); margin:0 0 6px; line-height:1.3;">{{ j.name }}</h2>
                        <ChevronRight style="width:18px; height:18px; flex-shrink:0;" :style="{ color: primaryColor }" />
                    </div>
                    <p v-if="j.description" style="font-size:13px; color:var(--ma-text-2); margin:0 0 10px; line-height:1.5; overflow:hidden; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical;">
                        {{ j.description }}
                    </p>
                    <div style="display:flex; align-items:center; gap:6px; font-size:12px; color:var(--ma-text-2); font-family:'DM Mono',monospace;">
                        <BookOpen style="width:13px; height:13px;" />
                        {{ j.steps_count ?? 0 }} etapa{{ (j.steps_count ?? 0) !== 1 ? 's' : '' }}
                    </div>
                </div>
            </a>
        </div>

        <!-- Aguardando atribuição -->
        <div v-else style="display:flex; flex-direction:column; align-items:center; gap:12px; border-radius:16px; border:2px dashed var(--ma-border); padding:60px 24px; text-align:center;">
            <MapPin style="width:40px; height:40px; color:var(--ma-border);" />
            <div>
                <p style="font-size:15px; font-weight:600; color:var(--ma-text); margin:0 0 4px;">Aguardando atribuição</p>
                <p style="font-size:13px; color:var(--ma-text-2); margin:0;">Em breve suas jornadas personalizadas estarão disponíveis aqui.</p>
            </div>
        </div>

    </div>
</template>
