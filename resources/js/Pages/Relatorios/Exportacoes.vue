<script setup>
import { ref } from 'vue';
import RelatoriosLayout from '@/Layouts/RelatoriosLayout.vue';
import { Download, FileText, Users, ShoppingCart, Facebook } from 'lucide-vue-next';

defineOptions({ layout: RelatoriosLayout });

const props = defineProps({
    metaProducts: { type: Array, default: () => [] },
    produtos: { type: Array, default: () => [] },
});

const vendasPeriod = ref('30d');
const metaProductId = ref(props.metaProducts[0]?.id ?? '');

const exportOptions = [
    {
        id: 'vendas',
        icon: ShoppingCart,
        title: 'Exportar Vendas (CSV)',
        desc: 'Todas as vendas do período selecionado com produto, email, valor, gateway e cupom.',
        color: 'emerald',
        action: () => {
            window.location.href = `/relatorios/export/vendas?period=${vendasPeriod.value}`;
        },
    },
    {
        id: 'alunos',
        icon: Users,
        title: 'Exportar Alunos (CSV)',
        desc: 'Lista completa de alunos com nome, email, produto e data de acesso.',
        color: 'blue',
        action: () => {
            window.location.href = '/relatorios/export/alunos';
        },
    },
];

const metaExports = [
    {
        id: 'compradores',
        title: 'Meta Ads — Compradores',
        desc: 'Audiência personalizada para Facebook/Instagram com dados dos compradores dos últimos 180 dias.',
        action: () => {
            if (!metaProductId.value) return;
            window.location.href = `/relatorios/export/meta-compradores?product_id=${metaProductId.value}`;
        },
    },
    {
        id: 'abandonos',
        title: 'Meta Ads — Abandonos',
        desc: 'Audiência de pessoas que iniciaram o checkout mas não compraram (últimos 180 dias).',
        action: () => {
            if (!metaProductId.value) return;
            window.location.href = `/relatorios/export/meta-abandonos?product_id=${metaProductId.value}`;
        },
    },
];

const periods = [
    { value: '7d', label: '7 dias' },
    { value: '30d', label: '30 dias' },
    { value: '90d', label: '90 dias' },
    { value: 'mes', label: 'Este mês' },
    { value: 'ano', label: 'Este ano' },
    { value: 'total', label: 'Tudo' },
];
</script>

<template>
    <div class="space-y-8">
        <h1 class="text-xl font-bold text-zinc-900 dark:text-white">Exportações</h1>

        <!-- Exportações gerais -->
        <section>
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Dados gerais</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <!-- Vendas -->
                <div class="rounded-2xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800/50">
                    <div class="flex items-start gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-100 dark:bg-emerald-900/30">
                            <ShoppingCart class="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                        </div>
                        <div class="min-w-0">
                            <p class="font-semibold text-zinc-900 dark:text-white">Exportar Vendas (CSV)</p>
                            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Todas as vendas do período com produto, email, valor e gateway.</p>
                        </div>
                    </div>
                    <div class="mt-4 space-y-3">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-zinc-500">Período</label>
                            <div class="flex flex-wrap gap-1">
                                <button v-for="p in periods" :key="p.value" type="button"
                                    class="rounded-lg px-2.5 py-1 text-xs font-medium transition"
                                    :class="vendasPeriod === p.value ? 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' : 'border border-zinc-200 text-zinc-500 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-700'"
                                    @click="vendasPeriod = p.value">{{ p.label }}</button>
                            </div>
                        </div>
                        <button type="button"
                            class="flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 py-2.5 text-sm font-medium text-white transition hover:bg-emerald-700"
                            @click="() => window.location.href = `/relatorios/export/vendas?period=${vendasPeriod}`">
                            <Download class="h-4 w-4" /> Baixar CSV de Vendas
                        </button>
                    </div>
                </div>

                <!-- Alunos -->
                <div class="rounded-2xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800/50">
                    <div class="flex items-start gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-100 dark:bg-blue-900/30">
                            <Users class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                        </div>
                        <div class="min-w-0">
                            <p class="font-semibold text-zinc-900 dark:text-white">Exportar Alunos (CSV)</p>
                            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Lista completa de alunos com nome, email, produto e data.</p>
                        </div>
                    </div>
                    <div class="mt-4">
                        <button type="button"
                            class="flex w-full items-center justify-center gap-2 rounded-xl bg-blue-600 py-2.5 text-sm font-medium text-white transition hover:bg-blue-700"
                            @click="() => window.location.href = '/relatorios/export/alunos'">
                            <Download class="h-4 w-4" /> Baixar CSV de Alunos
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <!-- Meta Ads -->
        <section>
            <h2 class="mb-1 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Meta Ads (Facebook / Instagram)</h2>
            <p class="mb-4 text-sm text-zinc-500 dark:text-zinc-400">Exporta audiências personalizadas no formato aceito pelo Meta Ads para re-targeting e lookalike.</p>

            <div v-if="!metaProducts.length" class="rounded-xl border border-zinc-200 bg-zinc-50 p-6 text-center text-sm text-zinc-400 dark:border-zinc-700 dark:bg-zinc-800/30">
                Nenhum produto com vendas encontrado.
            </div>

            <div v-else class="space-y-4">
                <!-- Seleção de produto -->
                <div>
                    <label class="mb-2 block text-xs font-medium text-zinc-600 dark:text-zinc-400">Produto para exportar</label>
                    <select v-model="metaProductId"
                        class="rounded-xl border border-zinc-200 bg-white px-4 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <option v-for="p in metaProducts" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </select>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div v-for="exp in metaExports" :key="exp.id"
                        class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-800/50">
                        <div class="flex items-start gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-100 dark:bg-blue-900/30">
                                <Facebook class="h-5 w-5 text-blue-600" />
                            </div>
                            <div>
                                <p class="font-semibold text-zinc-900 dark:text-white">{{ exp.title }}</p>
                                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ exp.desc }}</p>
                            </div>
                        </div>
                        <button type="button" :disabled="!metaProductId"
                            class="mt-4 flex w-full items-center justify-center gap-2 rounded-xl bg-blue-600 py-2.5 text-sm font-medium text-white transition hover:bg-blue-700 disabled:opacity-50"
                            @click="exp.action">
                            <Download class="h-4 w-4" /> Baixar CSV
                        </button>
                    </div>
                </div>

                <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/30">
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">
                        <strong>Como usar:</strong> Acesse o Meta Ads → Públicos → Criar público personalizado →
                        Lista de clientes → Carregue o CSV exportado. O formato já está otimizado para o Meta.
                    </p>
                </div>
            </div>
        </section>
    </div>
</template>
