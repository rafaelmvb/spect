<script setup>
import { computed } from 'vue';
import MemberAreaAppLayout from '@/Layouts/MemberAreaAppLayout.vue';

defineOptions({ layout: MemberAreaAppLayout });

const props = defineProps({
    product: { type: Object, required: true },
    config:  { type: Object, default: () => ({}) },
    slug:    { type: String, required: true },
    events:  { type: Array,  default: () => [] },
});

const primaryColor = computed(() => props.config?.theme?.primary || '#6FCF00');

const upcoming = computed(() => props.events.filter(e => !e.past));
const past     = computed(() => props.events.filter(e => e.past));

function fmtDate(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleString('pt-BR', { day: '2-digit', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}
function fmtShort(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    return { day: d.getDate(), month: d.toLocaleString('pt-BR', { month: 'short' }).toUpperCase(), hour: d.toLocaleString('pt-BR', { hour: '2-digit', minute: '2-digit' }) };
}
</script>

<template>
    <div style="max-width:600px; margin:0 auto; padding:16px 16px 24px;">
        <div style="padding:8px 0 24px;">
            <p style="font-family:'DM Mono',monospace; font-size:11px; text-transform:uppercase; letter-spacing:1.5px; color:var(--ma-text-2);">comunidade</p>
            <h1 style="font-family:'DM Sans',sans-serif; font-size:28px; font-weight:800; margin-top:6px; color:var(--ma-text);">Eventos</h1>
            <p style="font-size:13px; color:var(--ma-text-2); margin-top:4px; font-family:'DM Sans',sans-serif;">Próximos eventos da comunidade.</p>
        </div>

        <!-- Sem eventos -->
        <div v-if="!events.length" style="text-align:center; padding:60px 24px; color:var(--ma-text-2);">
            <p style="font-size:32px; margin-bottom:8px;">📅</p>
            <p style="font-family:'DM Sans',sans-serif; font-size:15px;">Nenhum evento disponível ainda.</p>
        </div>

        <!-- Próximos eventos -->
        <div v-if="upcoming.length">
            <p style="font-family:'DM Mono',monospace; font-size:11px; text-transform:uppercase; letter-spacing:1.5px; color:var(--ma-text-2); margin-bottom:12px;">Próximos</p>
            <div style="display:flex; flex-direction:column; gap:12px; margin-bottom:32px;">
                <div v-for="ev in upcoming" :key="ev.id"
                    style="background:var(--ma-surface); border-radius:16px; overflow:hidden; display:flex; gap:0; border:1px solid var(--ma-border);">

                    <!-- Data lateral -->
                    <div style="width:72px; flex-shrink:0; display:flex; flex-direction:column; align-items:center; justify-content:center; padding:16px 8px;"
                        :style="{ background: primaryColor, color: '#0D1F2D' }">
                        <span style="font-family:'DM Mono',monospace; font-size:28px; font-weight:700; line-height:1;">{{ fmtShort(ev.starts_at).day }}</span>
                        <span style="font-family:'DM Mono',monospace; font-size:11px; font-weight:600; letter-spacing:1px; margin-top:2px;">{{ fmtShort(ev.starts_at).month }}</span>
                        <span style="font-family:'DM Mono',monospace; font-size:10px; margin-top:4px; opacity:0.8;">{{ fmtShort(ev.starts_at).hour }}</span>
                    </div>

                    <!-- Info -->
                    <div style="flex:1; padding:14px 16px; min-width:0;">
                        <!-- Capa se houver -->
                        <div v-if="ev.image_url" style="height:80px; margin-bottom:12px; border-radius:10px; overflow:hidden;">
                            <img :src="ev.image_url" style="width:100%;height:100%;object-fit:cover;" />
                        </div>
                        <h3 style="font-family:'DM Sans',sans-serif; font-size:16px; font-weight:700; color:var(--ma-text);">{{ ev.title }}</h3>
                        <p v-if="ev.description" style="font-size:13px; color:var(--ma-text-2); margin-top:6px; line-height:1.5; font-family:'DM Sans',sans-serif; display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">{{ ev.description }}</p>

                        <div style="display:flex; flex-wrap:wrap; gap:8px; margin-top:10px;">
                            <span v-if="ev.is_online" style="display:inline-flex;align-items:center;gap:4px;font-size:11px;font-family:'DM Mono',monospace;color:var(--ma-text-2);">🌐 Online</span>
                            <span v-else-if="ev.location" style="display:inline-flex;align-items:center;gap:4px;font-size:11px;font-family:'DM Mono',monospace;color:var(--ma-text-2);">📍 {{ ev.location }}</span>
                            <span style="display:inline-flex;align-items:center;gap:4px;font-size:11px;font-family:'DM Mono',monospace;color:var(--ma-text-2);">👥 {{ ev.rsvp_count }} confirmados</span>
                        </div>

                        <a v-if="ev.link" :href="ev.link" target="_blank" rel="noopener"
                            style="display:inline-flex;align-items:center;gap:6px;margin-top:12px;padding:8px 18px;border-radius:20px;font-size:13px;font-weight:700;font-family:'DM Sans',sans-serif;text-decoration:none;color:#0D1F2D;transition:opacity 0.2s;"
                            :style="{ background: primaryColor }">
                            Acessar evento →
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Eventos passados -->
        <div v-if="past.length">
            <p style="font-family:'DM Mono',monospace; font-size:11px; text-transform:uppercase; letter-spacing:1.5px; color:var(--ma-text-2); margin-bottom:12px;">Anteriores</p>
            <div style="display:flex; flex-direction:column; gap:10px;">
                <div v-for="ev in past" :key="ev.id"
                    style="background:var(--ma-surface); border:1px solid var(--ma-border); border-radius:14px; padding:14px 16px; opacity:0.6; display:flex; gap:14px; align-items:center;">
                    <div style="width:44px; height:44px; border-radius:10px; flex-shrink:0; display:flex; flex-direction:column; align-items:center; justify-content:center; background:var(--ma-surface-2);">
                        <span style="font-family:'DM Mono',monospace; font-size:16px; font-weight:700; color:var(--ma-text-2); line-height:1;">{{ fmtShort(ev.starts_at).day }}</span>
                        <span style="font-family:'DM Mono',monospace; font-size:9px; color:var(--ma-text-2);">{{ fmtShort(ev.starts_at).month }}</span>
                    </div>
                    <div style="flex:1; min-width:0;">
                        <p style="font-size:14px; font-weight:600; color:var(--ma-text-2); font-family:'DM Sans',sans-serif; overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ ev.title }}</p>
                        <p style="font-size:11px; color:var(--ma-text-2); font-family:'DM Mono',monospace; margin-top:2px;">{{ fmtShort(ev.starts_at).hour }}</p>
                    </div>
                    <span style="font-size:11px; font-family:'DM Mono',monospace; color:var(--ma-text-2); flex-shrink:0;">encerrado</span>
                </div>
            </div>
        </div>
    </div>
</template>
