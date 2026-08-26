<script setup>
import { ref, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import MemberAreaAppLayout from '@/Layouts/MemberAreaAppLayout.vue';
import { useMemberBase } from '@/composables/useMemberBase';
import {
    ClipboardList, Clock, CheckCircle2, Circle, PlayCircle,
    ChevronRight, Tag, Search, Stethoscope,
} from 'lucide-vue-next';

defineOptions({ layout: MemberAreaAppLayout });

const props = defineProps({
    product:        { type: Object, required: true },
    config:         { type: Object, default: () => ({}) },
    slug:           { type: String, required: true },
    base_url:       { type: String, default: '' },
    tests:          { type: Array, default: () => [] },
    assigned_tests: { type: Array, default: () => [] },
    categories:     { type: Array, default: () => [] },
    category:       { type: String, default: 'todos' },
});

const memberBase = useMemberBase(computed(() => props.slug));

const selectedCategory = ref(props.category);
const searchText       = ref('');

function filterByCategory(cat) {
    selectedCategory.value = cat;
    router.get(`${memberBase.value}/testes`, cat !== 'todos' ? { category: cat } : {}, { preserveState: true, replace: true });
}

const filteredTests = computed(() => {
    const q = searchText.value.trim().toLowerCase();
    if (!q) return props.tests;
    return props.tests.filter(t => t.name.toLowerCase().includes(q) || t.category.toLowerCase().includes(q));
});

const CATEGORY_LABELS = {
    tdah: 'TDAH', tea: 'TEA', ah_sd: 'AH/SD',
    humor: 'Humor', ansiedade: 'Ansiedade', geral: 'Geral',
};

function categoryLabel(cat) { return CATEGORY_LABELS[cat] ?? cat; }

const STATUS_MAP = {
    not_started: { label: 'Não iniciado', color: 'text-[var(--ma-text-2)]' },
    in_progress:  { label: 'Em andamento', color: 'text-amber-400' },
    completed:    { label: 'Concluído',    color: 'text-[var(--ma-primary)]' },
};

function statusInfo(status) { return STATUS_MAP[status] ?? STATUS_MAP.not_started; }
</script>

<template>
    <div class="max-w-2xl mx-auto px-4 pt-4 pb-8 space-y-5">
        <!-- Header -->
        <div>
            <h1 style="font-family:'Cormorant Garamond',Georgia,serif; font-size:26px; font-weight:800; color:var(--ma-text);" class="flex items-center gap-2">
                <ClipboardList style="color:var(--ma-primary); width:24px; height:24px;" />
                Testes
            </h1>
            <p style="font-size:13px; color:var(--ma-text-2); margin-top:4px;">Rastreios validados para autoconhecimento</p>
        </div>

        <!-- Busca -->
        <div style="position:relative;">
            <Search style="position:absolute; left:12px; top:50%; transform:translateY(-50%); width:16px; height:16px; color:var(--ma-text-2);" />
            <input v-model="searchText" type="text" placeholder="Buscar teste…"
                style="width:100%; border-radius:16px; border:1px solid var(--ma-border); background:var(--ma-surface); padding:10px 14px 10px 38px; font-size:14px; color:var(--ma-text); outline:none;"
                @focus="$event.target.style.borderColor='var(--ma-primary)'"
                @blur="$event.target.style.borderColor='var(--ma-border)'" />
        </div>

        <!-- Filtro de categorias -->
        <div style="display:flex; gap:8px; overflow-x:auto; padding-bottom:2px; -webkit-overflow-scrolling:touch;">
            <button type="button"
                :style="{ background: selectedCategory === 'todos' ? 'var(--ma-primary)' : 'var(--ma-surface)', color: selectedCategory === 'todos' ? 'var(--ma-on-primary)' : 'var(--ma-text-2)', border: selectedCategory === 'todos' ? 'none' : '1px solid var(--ma-border)', borderRadius:'20px', padding:'6px 16px', fontSize:'13px', fontWeight:'600', whiteSpace:'nowrap', cursor:'pointer', flexShrink:0 }"
                @click="filterByCategory('todos')">
                Todos
            </button>
            <button v-for="cat in categories" :key="cat" type="button"
                :style="{ background: selectedCategory === cat ? 'var(--ma-primary)' : 'var(--ma-surface)', color: selectedCategory === cat ? 'var(--ma-on-primary)' : 'var(--ma-text-2)', border: selectedCategory === cat ? 'none' : '1px solid var(--ma-border)', borderRadius:'20px', padding:'6px 16px', fontSize:'13px', fontWeight:'600', whiteSpace:'nowrap', cursor:'pointer', flexShrink:0 }"
                @click="filterByCategory(cat)">
                {{ categoryLabel(cat) }}
            </button>
        </div>

        <!-- Testes atribuídos pelo profissional -->
        <div v-if="assigned_tests.length" style="margin-bottom:8px;">
            <div style="display:flex; align-items:center; gap:8px; margin-bottom:12px;">
                <Stethoscope style="width:15px; height:15px; color:var(--ma-primary);" />
                <p style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:var(--ma-primary);">Enviados pelo seu profissional</p>
            </div>
            <div v-for="test in assigned_tests" :key="`a-${test.id}`"
                style="border-radius:16px; border:1.5px solid rgba(16,185,129,0.25); background:rgba(16,185,129,0.05); overflow:hidden; margin-bottom:10px;">
                <Link :href="`${memberBase}/testes/${test.id}`" style="display:block; text-decoration:none; padding:14px 16px;">
                    <div style="display:flex; align-items:flex-start; gap:12px;">
                        <div style="flex-shrink:0; margin-top:2px;">
                            <CheckCircle2 v-if="test.status === 'completed'" style="width:20px; height:20px; color:var(--ma-primary);" />
                            <PlayCircle v-else-if="test.status === 'in_progress'" style="width:20px; height:20px; color:#f59e0b;" />
                            <Circle v-else style="width:20px; height:20px; color:var(--ma-primary); opacity:.6;" />
                        </div>
                        <div style="flex:1; min-width:0;">
                            <p style="font-size:15px; font-weight:700; color:var(--ma-text); margin-bottom:2px;">{{ test.name }}</p>
                            <div style="display:flex; gap:10px; font-size:12px; color:var(--ma-text-2);">
                                <span>{{ test.estimated_minutes }} min</span>
                                <span>{{ test.questions_count }} questões</span>
                                <span :style="{ color: test.status === 'completed' ? 'var(--ma-primary)' : test.status === 'in_progress' ? '#f59e0b' : 'var(--ma-text-inactive)' }">
                                    {{ test.status === 'completed' ? 'Concluído' : test.status === 'in_progress' ? 'Em andamento' : 'Pendente' }}
                                </span>
                            </div>
                        </div>
                        <ChevronRight style="width:16px; height:16px; color:var(--ma-text-inactive); flex-shrink:0;" />
                    </div>
                </Link>
            </div>
        </div>

        <!-- Lista de testes -->
        <div v-if="!filteredTests.length && !assigned_tests.length" style="text-align:center; padding:48px 24px;">
            <ClipboardList style="width:40px; height:40px; color:var(--ma-text-inactive); margin:0 auto 12px;" />
            <p style="color:var(--ma-text-2); font-size:14px;">Nenhum teste disponível no momento.</p>
        </div>

        <div v-for="test in filteredTests" :key="test.id"
            style="border-radius:16px; border:1px solid var(--ma-border); background:var(--ma-surface); overflow:hidden;">
            <Link :href="`${memberBase}/testes/${test.id}`"
                style="display:block; text-decoration:none; padding:16px;">
                <div style="display:flex; align-items:flex-start; gap:12px;">
                    <!-- Ícone de status -->
                    <div style="flex-shrink:0; margin-top:2px;">
                        <CheckCircle2 v-if="test.status === 'completed'" style="width:22px; height:22px; color:var(--ma-primary);" />
                        <PlayCircle v-else-if="test.status === 'in_progress'" style="width:22px; height:22px; color:#f59e0b;" />
                        <Circle v-else style="width:22px; height:22px; color:var(--ma-text-inactive);" />
                    </div>

                    <!-- Info -->
                    <div style="flex:1; min-width:0;">
                        <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:4px;">
                            <p style="font-family:'Cormorant Garamond',Georgia,serif; font-size:17px; font-weight:700; color:var(--ma-text);">{{ test.name }}</p>
                            <span style="border-radius:20px; background:rgba(255,255,255,0.07); color:var(--ma-text-2); padding:2px 10px; font-size:11px; font-weight:600; flex-shrink:0;">{{ categoryLabel(test.category) }}</span>
                        </div>
                        <p v-if="test.description" style="font-size:13px; color:var(--ma-text-2); margin-bottom:6px; overflow:hidden; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical;">{{ test.description }}</p>
                        <div style="display:flex; align-items:center; gap:12px; font-size:12px; color:var(--ma-text-2);">
                            <span style="display:flex; align-items:center; gap:4px;">
                                <Clock style="width:12px; height:12px;" />{{ test.estimated_minutes }} min
                            </span>
                            <span style="display:flex; align-items:center; gap:4px;">
                                <ClipboardList style="width:12px; height:12px;" />{{ test.questions_count }} questões
                            </span>
                            <span :style="{ color: test.status === 'completed' ? 'var(--ma-primary)' : test.status === 'in_progress' ? '#f59e0b' : 'var(--ma-text-inactive)' }">
                                {{ statusInfo(test.status).label }}
                            </span>
                        </div>
                        <!-- Tags de desafio geradas -->
                        <div v-if="test.challenge_tags?.length" style="display:flex; flex-wrap:wrap; gap:6px; margin-top:8px;">
                            <span v-for="tag in test.challenge_tags" :key="tag"
                                style="display:flex; align-items:center; gap:4px; border-radius:20px; border:1px solid rgba(16,185,129,0.3); color:var(--ma-primary); padding:2px 10px; font-size:11px; font-weight:600;">
                                <Tag style="width:10px; height:10px;" />{{ tag }}
                            </span>
                        </div>
                        <p v-if="test.completed_at" style="font-size:11px; color:var(--ma-text-inactive); margin-top:6px;">Concluído em {{ test.completed_at }}</p>
                    </div>

                    <ChevronRight style="width:18px; height:18px; color:var(--ma-text-inactive); flex-shrink:0; margin-top:2px;" />
                </div>
            </Link>
        </div>
    </div>
</template>
