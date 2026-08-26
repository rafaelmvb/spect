<script setup>
import { Tag, Trophy, CalendarDays, Clock, ShieldCheck } from 'lucide-vue-next';

const props = defineProps({
    share: { type: Object, required: true },
});

const CATEGORY_LABELS = {
    tdah: 'TDAH', tea: 'TEA', ah_sd: 'AH/SD',
    humor: 'Humor', ansiedade: 'Ansiedade', geral: 'Geral',
};

function categoryLabel(cat) { return CATEGORY_LABELS[cat] ?? cat; }

const expiresDate = new Date(props.share.expires_at).toLocaleDateString('pt-BR');
</script>

<template>
    <div style="min-height:100vh; background:#0f172a; display:flex; flex-direction:column; align-items:center; justify-content:flex-start; padding:24px 16px 48px;">

        <!-- Card principal -->
        <div style="width:100%; max-width:480px; background:#1e293b; border-radius:20px; border:1px solid rgba(255,255,255,0.08); overflow:hidden; margin-top:24px;">

            <!-- Cabeçalho -->
            <div style="background:linear-gradient(135deg,#10b981,#059669); padding:28px 24px 24px;">
                <div style="display:flex; align-items:center; gap:12px; margin-bottom:8px;">
                    <Trophy style="width:28px; height:28px; color:#fff; flex-shrink:0;" />
                    <div>
                        <p style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.1em; color:rgba(255,255,255,0.75);">Resultado de Rastreio</p>
                        <h1 style="font-size:20px; font-weight:800; color:#fff; margin-top:2px; line-height:1.2;">{{ share.test_name }}</h1>
                    </div>
                </div>
                <span style="display:inline-block; background:rgba(255,255,255,0.2); color:#fff; border-radius:20px; padding:3px 12px; font-size:12px; font-weight:600;">
                    {{ categoryLabel(share.test_category) }}
                </span>
            </div>

            <!-- Corpo -->
            <div style="padding:24px;">
                <!-- Nome do participante -->
                <p style="font-size:13px; color:#94a3b8; margin-bottom:16px;">
                    Resultado de <strong style="color:#f8fafc;">{{ share.user_name }}</strong>
                </p>

                <!-- Resultado principal -->
                <div style="background:#0f172a; border-radius:14px; padding:20px; margin-bottom:16px; border:1px solid rgba(16,185,129,0.2);">
                    <p style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.1em; color:#10b981; margin-bottom:8px;">Resultado</p>
                    <p style="font-size:22px; font-weight:800; color:#f8fafc; line-height:1.2;">{{ share.result_label ?? '—' }}</p>
                </div>

                <!-- Challenge Tags -->
                <div v-if="share.challenge_tags?.length" style="margin-bottom:20px;">
                    <p style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.1em; color:#94a3b8; margin-bottom:10px;">Tags identificadas</p>
                    <div style="display:flex; flex-wrap:wrap; gap:8px;">
                        <span v-for="tag in share.challenge_tags" :key="tag"
                            style="display:flex; align-items:center; gap:5px; background:rgba(16,185,129,0.12); border:1px solid rgba(16,185,129,0.3); color:#10b981; border-radius:20px; padding:4px 12px; font-size:12px; font-weight:600;">
                            <Tag style="width:11px; height:11px;" />{{ tag }}
                        </span>
                    </div>
                </div>

                <!-- Metadados -->
                <div style="display:flex; gap:16px; margin-bottom:20px;">
                    <div v-if="share.completed_at" style="display:flex; align-items:center; gap:6px; font-size:12px; color:#64748b;">
                        <CalendarDays style="width:13px; height:13px;" />
                        Realizado em {{ share.completed_at }}
                    </div>
                    <div style="display:flex; align-items:center; gap:6px; font-size:12px; color:#64748b;">
                        <Clock style="width:13px; height:13px;" />
                        Link válido até {{ expiresDate }}
                    </div>
                </div>

                <!-- Aviso LGPD -->
                <div style="background:#0f172a; border-radius:12px; padding:14px; border:1px solid rgba(255,255,255,0.06);">
                    <div style="display:flex; gap:8px; align-items:flex-start;">
                        <ShieldCheck style="width:15px; height:15px; color:#64748b; flex-shrink:0; margin-top:1px;" />
                        <p style="font-size:11px; color:#64748b; line-height:1.6; margin:0;">{{ share.lgpd_notice }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Rodapé -->
        <p style="margin-top:24px; font-size:11px; color:#334155;">Rastreio Spectra · Este link expira automaticamente em 7 dias</p>
    </div>
</template>
