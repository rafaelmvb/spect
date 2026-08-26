<template>
    <div class="space-y-4">
        <label class="block text-sm font-semibold text-zinc-700 dark:text-zinc-300">Segmentação de destinatários</label>

        <!-- Grid de opções -->
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <button
                v-for="opt in filterOptions"
                :key="opt.type"
                type="button"
                class="flex items-start gap-3 rounded-xl border-2 p-3 text-left transition"
                :class="modelValue.type === opt.type
                    ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-900/20'
                    : 'border-zinc-200 bg-zinc-50 hover:border-zinc-300 dark:border-zinc-600 dark:bg-zinc-800/50'"
                @click="selectType(opt.type)"
            >
                <span class="text-xl leading-none">{{ opt.icon }}</span>
                <div class="min-w-0">
                    <p class="text-sm font-medium" :class="modelValue.type === opt.type ? 'text-emerald-700 dark:text-emerald-300' : 'text-zinc-800 dark:text-zinc-200'">
                        {{ opt.label }}
                    </p>
                    <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">{{ opt.description }}</p>
                </div>
            </button>
        </div>

        <!-- Parâmetros extras por tipo -->

        <!-- Produto(s) específico(s) -->
        <div v-if="needsProducts" class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
            <label class="mb-2 block text-xs font-medium text-zinc-600 dark:text-zinc-400">
                {{ productsLabel }}
            </label>
            <div class="space-y-1.5">
                <label v-for="p in products" :key="p.id" class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2 py-1.5 hover:bg-zinc-100 dark:hover:bg-zinc-700/50">
                    <input type="checkbox" :value="p.id" :checked="(modelValue.product_ids ?? []).includes(p.id)"
                        class="h-4 w-4 rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500"
                        @change="toggleProduct(p.id)" />
                    <span class="text-sm text-zinc-800 dark:text-zinc-200">{{ p.name }}</span>
                </label>
                <p v-if="!products.length" class="text-xs text-zinc-400">Nenhum produto encontrado.</p>
            </div>
        </div>

        <!-- Dias de inatividade -->
        <div v-if="modelValue.type === 'inactive_days'" class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
            <label class="mb-2 block text-xs font-medium text-zinc-600 dark:text-zinc-400">Não acessa há quantos dias?</label>
            <div class="flex items-center gap-3">
                <input type="number" :value="modelValue.inactive_days ?? 30" min="1" max="3650"
                    class="w-24 rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900"
                    @input="emit('update:modelValue', { ...modelValue, inactive_days: parseInt($event.target.value) || 30 })" />
                <span class="text-sm text-zinc-500">dias sem acessar a área de membros</span>
            </div>
        </div>

        <!-- Progresso abaixo de X% -->
        <div v-if="modelValue.type === 'course_progress_below'" class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/50 space-y-3">
            <div>
                <label class="mb-2 block text-xs font-medium text-zinc-600 dark:text-zinc-400">Progresso máximo da trilha (ex: 50 = menos de 50% concluído)</label>
                <div class="flex items-center gap-3">
                    <input type="number" :value="modelValue.max_progress ?? 50" min="1" max="99"
                        class="w-24 rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900"
                        @input="emit('update:modelValue', { ...modelValue, max_progress: parseFloat($event.target.value) || 50 })" />
                    <span class="text-sm text-zinc-500">% de progresso</span>
                </div>
            </div>
            <div>
                <label class="mb-2 block text-xs font-medium text-zinc-600 dark:text-zinc-400">Filtrar por trilha(s) específica(s) (opcional)</label>
                <div class="space-y-1.5">
                    <label v-for="p in products" :key="p.id" class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2 py-1.5 hover:bg-zinc-100 dark:hover:bg-zinc-700/50">
                        <input type="checkbox" :value="p.id" :checked="(modelValue.product_ids ?? []).includes(p.id)"
                            class="h-4 w-4 rounded border-zinc-300 text-emerald-600"
                            @change="toggleProduct(p.id)" />
                        <span class="text-sm text-zinc-800 dark:text-zinc-200">{{ p.name }}</span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Progresso acima de X% -->
        <div v-if="modelValue.type === 'course_progress_above'" class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/50 space-y-3">
            <div>
                <label class="mb-2 block text-xs font-medium text-zinc-600 dark:text-zinc-400">Progresso mínimo da trilha (ex: 80 = concluíram pelo menos 80%)</label>
                <div class="flex items-center gap-3">
                    <input type="number" :value="modelValue.min_progress ?? 80" min="1" max="100"
                        class="w-24 rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900"
                        @input="emit('update:modelValue', { ...modelValue, min_progress: parseFloat($event.target.value) || 80 })" />
                    <span class="text-sm text-zinc-500">% de progresso</span>
                </div>
            </div>
            <div>
                <label class="mb-2 block text-xs font-medium text-zinc-600 dark:text-zinc-400">Filtrar por trilha(s) específica(s) (opcional)</label>
                <div class="space-y-1.5">
                    <label v-for="p in products" :key="p.id" class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2 py-1.5 hover:bg-zinc-100 dark:hover:bg-zinc-700/50">
                        <input type="checkbox" :value="p.id" :checked="(modelValue.product_ids ?? []).includes(p.id)"
                            class="h-4 w-4 rounded border-zinc-300 text-emerald-600"
                            @change="toggleProduct(p.id)" />
                        <span class="text-sm text-zinc-800 dark:text-zinc-200">{{ p.name }}</span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Intervalo de datas -->
        <div v-if="modelValue.type === 'purchased_date_range'" class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/50 space-y-3">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-zinc-600 dark:text-zinc-400">Data inicial</label>
                    <input type="date" :value="modelValue.date_from ?? ''"
                        class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900"
                        @input="emit('update:modelValue', { ...modelValue, date_from: $event.target.value })" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-zinc-600 dark:text-zinc-400">Data final</label>
                    <input type="date" :value="modelValue.date_to ?? ''"
                        class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900"
                        @input="emit('update:modelValue', { ...modelValue, date_to: $event.target.value })" />
                </div>
            </div>
            <div>
                <label class="mb-2 block text-xs font-medium text-zinc-600 dark:text-zinc-400">Filtrar por produto(s) (opcional)</label>
                <div class="space-y-1.5">
                    <label v-for="p in products" :key="p.id" class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2 py-1.5 hover:bg-zinc-100 dark:hover:bg-zinc-700/50">
                        <input type="checkbox" :value="p.id" :checked="(modelValue.product_ids ?? []).includes(p.id)"
                            class="h-4 w-4 rounded border-zinc-300 text-emerald-600"
                            @change="toggleProduct(p.id)" />
                        <span class="text-sm text-zinc-800 dark:text-zinc-200">{{ p.name }}</span>
                    </label>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: { type: Object, required: true },
    products: { type: Array, default: () => [] },
});
const emit = defineEmits(['update:modelValue']);

const filterOptions = [
    { type: 'all_customers',           icon: '👥', label: 'Todos com acesso',                  description: 'Qualquer aluno com acesso a algum produto (compra ou manual).' },
    { type: 'students_of_product',     icon: '🎓', label: 'Alunos de produto específico',       description: 'Têm acesso a determinada(s) trilha(s) — inclui manuais e compradores.' },
    { type: 'bought_product',          icon: '🛒', label: 'Compradores (pedido pago)',           description: 'Fizeram um pedido pago — exclui alunos adicionados manualmente.' },
    { type: 'not_bought',              icon: '👤', label: 'Cadastrados sem compra',             description: 'Se cadastraram mas nunca realizaram um pedido pago.' },
    { type: 'not_bought_product',      icon: '🔍', label: 'Não têm produto específico',         description: 'Têm acesso a outras trilhas mas não à indicada (upsell).' },
    { type: 'never_accessed',          icon: '😴', label: 'Nunca acessaram a trilha',           description: 'Têm acesso mas nunca abriram nenhuma aula.' },
    { type: 'inactive_days',           icon: '📅', label: 'Inativos há N dias',                 description: 'Não acessam a área de membros há X dias.' },
    { type: 'course_progress_below',   icon: '📉', label: 'Progresso baixo na trilha',          description: 'Concluíram menos de X% da trilha.' },
    { type: 'course_progress_above',   icon: '📈', label: 'Progresso alto na trilha',           description: 'Concluíram pelo menos X% da trilha.' },
    { type: 'active_subscription',     icon: '✅', label: 'Assinantes ativos',                  description: 'Têm assinatura ativa no momento.' },
    { type: 'cancelled_subscription',  icon: '❌', label: 'Assinaturas canceladas',              description: 'Cancelaram a assinatura.' },
    { type: 'purchased_date_range',    icon: '🗓️', label: 'Compra em intervalo de datas',        description: 'Compraram entre uma data inicial e final.' },
    { type: 'all_registered',          icon: '📋', label: 'Todos os cadastrados',                description: 'Todos os usuários registrados (com ou sem compra).' },
];

const needsProducts = computed(() =>
    ['students_of_product', 'bought_product', 'not_bought_product', 'never_accessed'].includes(props.modelValue.type)
);

const productsLabel = computed(() => {
    const labels = {
        students_of_product: 'Selecione a(s) trilha(s):',
        bought_product: 'Selecione o(s) produto(s):',
        not_bought_product: 'Produto(s) que NÃO têm:',
        never_accessed: 'Filtrar por produto (opcional):',
    };
    return labels[props.modelValue.type] ?? 'Selecione o(s) produto(s):';
});

function selectType(type) {
    emit('update:modelValue', { type, product_ids: [], inactive_days: 30, max_progress: 50, min_progress: 80, date_from: '', date_to: '' });
}

function toggleProduct(id) {
    const current = [...(props.modelValue.product_ids ?? [])];
    const idx = current.indexOf(id);
    if (idx >= 0) current.splice(idx, 1);
    else current.push(id);
    emit('update:modelValue', { ...props.modelValue, product_ids: current });
}
</script>
