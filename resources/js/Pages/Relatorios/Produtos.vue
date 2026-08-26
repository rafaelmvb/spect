<script setup>
import { ref, computed } from 'vue';
import RelatoriosLayout from '@/Layouts/RelatoriosLayout.vue';
import { Package, TrendingUp, Users, RotateCcw } from 'lucide-vue-next';

defineOptions({ layout: RelatoriosLayout });

const props = defineProps({
    resultado: Array,
    receitaTotal: Number,
});

const search = ref('');
const sortBy = ref('receita');
const sortDir = ref('desc');

function fmt(v) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v ?? 0);
}
function fmtN(v) { return new Intl.NumberFormat('pt-BR').format(v ?? 0); }

const filtrado = computed(() => {
    let list = [...props.resultado];
    if (search.value.trim()) {
        const s = search.value.toLowerCase();
        list = list.filter(p => p.nome.toLowerCase().includes(s));
    }
    list.sort((a, b) => {
        const dir = sortDir.value === 'asc' ? 1 : -1;
        return (a[sortBy.value] > b[sortBy.value] ? 1 : -1) * dir;
    });
    return list;
});

function toggleSort(field) {
    if (sortBy.value === field) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = field;
        sortDir.value = 'desc';
    }
}

function pct(receita) {
    if (!props.receitaTotal) return 0;
    return ((receita / props.receitaTotal) * 100).toFixed(1);
}
</script>

<template>
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-bold text-zinc-900 dark:text-white">Performance de Produtos</h1>
            <input v-model="search" type="search" placeholder="Buscar produto..."
                class="rounded-xl border border-zinc-200 bg-white px-4 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800" />
        </div>

        <!-- Tabela -->
        <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800/50">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800">
                        <tr>
                            <th class="px-5 py-3 text-left font-medium text-zinc-500">Produto</th>
                            <th v-for="col in [
                                { key: 'receita', label: 'Receita' },
                                { key: 'vendas', label: 'Vendas' },
                                { key: 'ticket_medio', label: 'Ticket Médio' },
                                { key: 'alunos', label: 'Alunos' },
                                { key: 'taxa_engajamento', label: 'Engaj.' },
                                { key: 'taxa_reembolso', label: 'Reemb.' },
                            ]" :key="col.key"
                                class="cursor-pointer px-4 py-3 text-right font-medium text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300"
                                @click="toggleSort(col.key)">
                                {{ col.label }}
                                <span v-if="sortBy === col.key">{{ sortDir === 'desc' ? ' ↓' : ' ↑' }}</span>
                            </th>
                            <th class="px-4 py-3 text-right font-medium text-zinc-500">% Receita</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700/50">
                        <tr v-for="p in filtrado" :key="p.id"
                            class="transition hover:bg-zinc-50 dark:hover:bg-zinc-700/30">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-2">
                                    <span class="flex h-2 w-2 rounded-full" :class="p.ativo ? 'bg-emerald-500' : 'bg-zinc-400'" />
                                    <span class="font-medium text-zinc-900 dark:text-white">{{ p.nome }}</span>
                                </div>
                                <p class="text-xs text-zinc-400">{{ p.tipo }} · {{ p.billing }}</p>
                            </td>
                            <td class="px-4 py-3 text-right font-semibold text-zinc-900 dark:text-white">{{ fmt(p.receita) }}</td>
                            <td class="px-4 py-3 text-right text-zinc-600 dark:text-zinc-400">{{ fmtN(p.vendas) }}</td>
                            <td class="px-4 py-3 text-right text-zinc-600 dark:text-zinc-400">{{ fmt(p.ticket_medio) }}</td>
                            <td class="px-4 py-3 text-right text-zinc-600 dark:text-zinc-400">{{ fmtN(p.alunos) }}</td>
                            <td class="px-4 py-3 text-right">
                                <span class="font-medium" :class="p.taxa_engajamento >= 50 ? 'text-emerald-600 dark:text-emerald-400' : p.taxa_engajamento >= 20 ? 'text-amber-600 dark:text-amber-400' : 'text-red-600 dark:text-red-400'">
                                    {{ p.taxa_engajamento }}%
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <span class="font-medium" :class="p.taxa_reembolso > 10 ? 'text-red-600 dark:text-red-400' : p.taxa_reembolso > 5 ? 'text-amber-600' : 'text-emerald-600 dark:text-emerald-400'">
                                    {{ p.taxa_reembolso }}%
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    <span class="text-xs text-zinc-400">{{ pct(p.receita) }}%</span>
                                    <div class="h-1.5 w-16 rounded-full bg-zinc-100 dark:bg-zinc-700">
                                        <div class="h-1.5 rounded-full bg-violet-500" :style="{ width: Math.min(pct(p.receita), 100) + '%' }" />
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!filtrado.length">
                            <td colspan="8" class="py-8 text-center text-zinc-400">Nenhum produto encontrado.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Legenda -->
        <div class="flex flex-wrap gap-4 text-xs text-zinc-500">
            <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-emerald-500" /> Ativo</span>
            <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-zinc-400" /> Inativo</span>
            <span>Engaj. = % de alunos que abriram pelo menos uma aula</span>
            <span>Reemb. = % de vendas reembolsadas</span>
        </div>
    </div>
</template>
