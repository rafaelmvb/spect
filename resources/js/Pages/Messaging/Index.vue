<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import LayoutInfoprodutor from '@/Layouts/LayoutInfoprodutor.vue';
import Button from '@/components/ui/Button.vue';
import { MessageSquare, Phone, Pencil, Send } from 'lucide-vue-next';

defineOptions({ layout: LayoutInfoprodutor });

const props = defineProps({
    campaigns: { type: Array, default: () => [] },
    providers: { type: Array, default: () => [] },
    configured_providers: { type: Object, default: () => ({}) },
});

const statusMap = {
    draft:     { label: 'Rascunho',  class: 'bg-zinc-100 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300' },
    sending:   { label: 'Enviando',  class: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300' },
    sent:      { label: 'Enviado',   class: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' },
    cancelled: { label: 'Cancelado', class: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300' },
};

function confirmSend(campaign) {
    if (!confirm(`Disparar a campanha "${campaign.name}"? As mensagens serão enviadas em lotes de 20 por minuto.`)) return;
    router.post(`/mensagens/${campaign.id}/disparar`);
}
</script>

<template>
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-green-100 dark:bg-green-900/30">
                    <MessageSquare class="h-5 w-5 text-green-600 dark:text-green-400" />
                </div>
                <div>
                    <h1 class="text-xl font-bold text-zinc-900 dark:text-white">WhatsApp & SMS</h1>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">
                        Campanhas de mensagens segmentadas.
                        <Link href="/integracoes" class="text-emerald-600 underline dark:text-emerald-400">Configurar provedores →</Link>
                    </p>
                </div>
            </div>
            <div class="flex gap-2">
                <Link href="/mensagens/criar?channel=whatsapp">
                    <Button><MessageSquare class="mr-2 h-4 w-4" /> Nova WhatsApp</Button>
                </Link>
                <Link href="/mensagens/criar?channel=sms">
                    <Button variant="outline"><Phone class="mr-2 h-4 w-4" /> Nova SMS</Button>
                </Link>
            </div>
        </div>

        <!-- Sem campanhas -->
        <div v-if="!campaigns.length"
            class="flex flex-col items-center justify-center gap-4 rounded-2xl border-2 border-dashed border-zinc-200 py-20 dark:border-zinc-700">
            <MessageSquare class="h-12 w-12 text-zinc-300 dark:text-zinc-600" />
            <div class="text-center">
                <p class="font-semibold text-zinc-700 dark:text-zinc-300">Nenhuma campanha ainda</p>
                <p class="mt-1 text-sm text-zinc-500">Crie campanhas de WhatsApp ou SMS.</p>
            </div>
            <div class="flex gap-2">
                <Link href="/mensagens/criar?channel=whatsapp"><Button>Nova WhatsApp</Button></Link>
                <Link href="/mensagens/criar?channel=sms"><Button variant="outline">Nova SMS</Button></Link>
            </div>
        </div>

        <!-- Tabela de campanhas -->
        <div v-else class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-800/50">
            <table class="w-full text-sm">
                <thead class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800">
                    <tr>
                        <th class="px-5 py-3 text-left font-medium text-zinc-500 dark:text-zinc-400">Campanha</th>
                        <th class="px-5 py-3 text-left font-medium text-zinc-500 dark:text-zinc-400">Canal</th>
                        <th class="px-5 py-3 text-left font-medium text-zinc-500 dark:text-zinc-400">Status</th>
                        <th class="px-5 py-3 text-right font-medium text-zinc-500 dark:text-zinc-400">Envios</th>
                        <th class="px-5 py-3 text-right font-medium text-zinc-500 dark:text-zinc-400">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700/50">
                    <tr v-for="c in campaigns" :key="c.id">
                        <td class="px-5 py-3">
                            <p class="font-medium text-zinc-900 dark:text-white">{{ c.name }}</p>
                            <p class="text-xs text-zinc-400">{{ c.provider }} · {{ c.created_at }}</p>
                        </td>
                        <td class="px-5 py-3">
                            <span class="flex items-center gap-1.5 text-xs font-medium"
                                :class="c.channel === 'whatsapp' ? 'text-green-600 dark:text-green-400' : 'text-blue-600 dark:text-blue-400'">
                                <MessageSquare v-if="c.channel === 'whatsapp'" class="h-3.5 w-3.5" />
                                <Phone v-else class="h-3.5 w-3.5" />
                                {{ c.channel === 'whatsapp' ? 'WhatsApp' : 'SMS' }}
                            </span>
                        </td>
                        <td class="px-5 py-3">
                            <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="statusMap[c.status]?.class">
                                {{ statusMap[c.status]?.label ?? c.status }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-right text-xs text-zinc-500">
                            <span v-if="c.total_recipients">
                                {{ c.sent_count }}/{{ c.total_recipients }}
                                <span v-if="c.failed_count" class="text-red-400">({{ c.failed_count }} falhas)</span>
                            </span>
                            <span v-else class="text-zinc-300">—</span>
                        </td>
                        <td class="px-5 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <Link v-if="c.status === 'draft'" :href="`/mensagens/${c.id}/editar`">
                                    <button type="button" class="rounded-lg p-1.5 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-700">
                                        <Pencil class="h-4 w-4" />
                                    </button>
                                </Link>
                                <button v-if="c.status === 'draft'" type="button"
                                    class="rounded-lg p-1.5 text-green-500 hover:bg-green-50 dark:hover:bg-green-950/30"
                                    title="Disparar" @click="confirmSend(c)">
                                    <Send class="h-4 w-4" />
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
