<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { BarChart2, ChevronRight, FileText, CheckCircle } from 'lucide-vue-next';
import { useMemberBase } from '@/composables/useMemberBase';
import MemberAreaAppLayout from '@/Layouts/MemberAreaAppLayout.vue';

defineOptions({ layout: MemberAreaAppLayout });

const props = defineProps({
    slug:    { type: String, required: true },
    reports: { type: Array, default: () => [] },
});

const memberBase = useMemberBase(computed(() => props.slug));

function openReport(r) {
    router.visit(`${memberBase.value}/meus-relatorios/${r.journey_id}`);
}
</script>

<template>
    <div class="mr-page">
        <!-- Header -->
        <div class="mr-header">
            <div class="mr-header__icon">
                <BarChart2 style="width:22px;height:22px;" />
            </div>
            <div>
                <h1 class="mr-header__title">Mega Relatórios</h1>
                <p class="mr-header__sub">Relatórios avançados gerados para você</p>
            </div>
        </div>

        <!-- Lista de relatórios -->
        <div v-if="reports.length" class="mr-list">
            <button
                v-for="r in reports"
                :key="r.id"
                class="mr-card"
                @click="openReport(r)"
            >
                <div class="mr-card__icon">
                    <FileText style="width:20px;height:20px;" />
                </div>

                <div class="mr-card__body">
                    <p class="mr-card__name">{{ r.journey_name }}</p>
                    <p class="mr-card__date">Gerado em {{ r.generated_at }}</p>

                    <div class="mr-progress">
                        <div class="mr-progress__bar">
                            <div class="mr-progress__fill" :style="{ width: r.pct + '%' }" />
                        </div>
                        <span class="mr-progress__pct">{{ r.pct }}%</span>
                    </div>
                </div>

                <div class="mr-card__badge" v-if="r.pct === 100">
                    <CheckCircle style="width:16px;height:16px;" />
                </div>
                <ChevronRight v-else style="width:18px;height:18px;color:var(--ma-text-2);flex-shrink:0;" />
            </button>
        </div>

        <!-- Estado vazio -->
        <div v-else class="mr-empty">
            <div class="mr-empty__icon">
                <BarChart2 style="width:32px;height:32px;" />
            </div>
            <p class="mr-empty__title">Nenhum resultado disponível</p>
            <p class="mr-empty__sub">Conclua as etapas de uma trilha para receber seu relatório personalizado de resultados.</p>
        </div>
    </div>
</template>

<style scoped>
.mr-page {
    max-width: 680px;
    margin: 0 auto;
    padding: 28px 16px 48px;
    font-family: 'DM Sans', sans-serif;
}

/* Header */
.mr-header {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 28px;
}
.mr-header__icon {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    background: rgba(var(--ma-primary-rgb, 111,207,0), 0.12);
    border: 1px solid rgba(var(--ma-primary-rgb, 111,207,0), 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--ma-primary, #6FCF00);
    flex-shrink: 0;
}
.mr-header__title {
    font-size: 22px;
    font-weight: 700;
    color: var(--ma-text, #fff);
    margin: 0;
}
.mr-header__sub {
    font-size: 13px;
    color: var(--ma-text-2, #7A9BAD);
    margin: 3px 0 0;
}

/* Lista */
.mr-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

/* Card */
.mr-card {
    display: flex;
    align-items: center;
    gap: 14px;
    width: 100%;
    background: var(--ma-surface, #132435);
    border: 1px solid var(--ma-border, #1E3448);
    border-radius: 16px;
    padding: 16px 18px;
    text-align: left;
    cursor: pointer;
    transition: border-color 0.18s, background 0.18s;
}
.mr-card:hover {
    border-color: var(--ma-primary, #6FCF00);
    background: rgba(var(--ma-primary-rgb, 111,207,0), 0.04);
}
.mr-card__icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: rgba(var(--ma-primary-rgb, 111,207,0), 0.1);
    border: 1px solid rgba(var(--ma-primary-rgb, 111,207,0), 0.18);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--ma-primary, #6FCF00);
    flex-shrink: 0;
}
.mr-card__body {
    flex: 1;
    min-width: 0;
}
.mr-card__name {
    font-size: 15px;
    font-weight: 600;
    color: var(--ma-text, #fff);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin: 0;
}
.mr-card__date {
    font-size: 12px;
    color: var(--ma-text-2, #7A9BAD);
    margin: 3px 0 10px;
    font-family: 'DM Mono', monospace;
}
.mr-card__badge {
    color: var(--ma-primary, #6FCF00);
    flex-shrink: 0;
}

/* Progress */
.mr-progress {
    display: flex;
    align-items: center;
    gap: 8px;
}
.mr-progress__bar {
    flex: 1;
    height: 4px;
    border-radius: 99px;
    background: rgba(255,255,255,0.08);
    overflow: hidden;
}
.mr-progress__fill {
    height: 100%;
    border-radius: 99px;
    background: var(--ma-primary, #6FCF00);
    transition: width 0.3s;
}
.mr-progress__pct {
    font-size: 11px;
    font-weight: 700;
    color: var(--ma-text-2, #7A9BAD);
    font-family: 'DM Mono', monospace;
    min-width: 28px;
    text-align: right;
}

/* Empty */
.mr-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 64px 24px;
    text-align: center;
    gap: 12px;
}
.mr-empty__icon {
    width: 64px;
    height: 64px;
    border-radius: 18px;
    background: rgba(255,255,255,0.04);
    border: 1px solid var(--ma-border, #1E3448);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--ma-text-2, #7A9BAD);
    margin-bottom: 4px;
}
.mr-empty__title {
    font-size: 16px;
    font-weight: 600;
    color: var(--ma-text-2, #7A9BAD);
    margin: 0;
}
.mr-empty__sub {
    font-size: 13px;
    color: var(--ma-text-2, #7A9BAD);
    opacity: 0.7;
    max-width: 300px;
    line-height: 1.6;
    margin: 0;
}
</style>
