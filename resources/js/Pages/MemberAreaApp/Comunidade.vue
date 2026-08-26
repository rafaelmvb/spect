<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import MemberAreaAppLayout from '@/Layouts/MemberAreaAppLayout.vue';
import { getCommunityPageIconComponent } from '@/utils/communityPageIcons';
import { useMemberBase } from '@/composables/useMemberBase';

defineOptions({ layout: MemberAreaAppLayout });

const props = defineProps({
    product: { type: Object, required: true },
    config:  { type: Object, default: () => ({}) },
    pages:   { type: Array,  default: () => [] },
    slug:    { type: String, required: true },
    base_url:{ type: String, default: '' },
});

const page = usePage();
const primaryColor = computed(() =>
    page.props?.member_area_global_branding?.primary_color
    || props.config?.theme?.primary
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
const basePath = computed(() => `${memberBase.value}/comunidade`);
</script>

<template>
    <div style="padding: 16px; max-width: 480px; margin: 0 auto;">
        <div style="padding: 8px 0 24px;">
            <p style="font-family:'DM Mono',monospace; font-size:11px; text-transform:uppercase; letter-spacing:1.5px; color:var(--ma-text-2);">comunidade</p>
            <h1 style="font-family:'DM Sans',sans-serif; font-size:28px; font-weight:800; margin-top:6px; color:var(--ma-text);">Espaços</h1>
            <p style="font-size:13px; color:var(--ma-text-2); margin-top:4px; font-family:'DM Sans',sans-serif;">Escolha um espaço para participar.</p>
        </div>

        <div style="display:flex; flex-direction:column; gap:12px;">
            <Link v-for="p in pages" :key="p.id" :href="`${basePath}/${p.slug}`"
                style="background:var(--ma-surface); border:1px solid var(--ma-border); border-radius:14px; overflow:hidden; text-decoration:none; display:block; transition:border-color 0.2s;">
                <div v-if="p.banner_url" style="height:100px; overflow:hidden;">
                    <img :src="p.banner_url" style="width:100%;height:100%;object-fit:cover;" />
                </div>
                <div style="padding:20px; display:flex; align-items:center; gap:16px;">
                    <div style="width:52px; height:52px; border-radius:12px; flex-shrink:0; display:flex; align-items:center; justify-content:center;"
                        :style="{ background: primaryRgba(0.1), border: `1px solid ${primaryRgba(0.2)}` }">
                        <component v-if="p.icon && getCommunityPageIconComponent(p.icon)" :is="getCommunityPageIconComponent(p.icon)" style="width:24px;height:24px;" :style="{ color: primaryColor }" />
                        <span v-else-if="p.icon" style="font-size:24px;">{{ p.icon }}</span>
                        <svg v-else width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--ma-primary)" stroke-width="1.5"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
                    </div>
                    <div style="flex:1; min-width:0;">
                        <h3 style="font-family:'DM Sans',sans-serif; font-size:16px; font-weight:600; color:var(--ma-text);">{{ p.title }}</h3>
                        <p style="font-size:12px; font-family:'DM Mono',monospace; margin-top:4px;" :style="{ color: primaryColor }">entrar →</p>
                    </div>
                </div>
            </Link>

            <div v-if="!pages.length" style="text-align:center; padding:60px 24px; color:var(--ma-text-2);">
                <p style="font-family:'DM Sans',sans-serif; font-size:15px;">Nenhum espaço disponível ainda.</p>
            </div>
        </div>
    </div>
</template>
