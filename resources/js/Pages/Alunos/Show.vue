<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import LayoutInfoprodutor from '@/Layouts/LayoutInfoprodutor.vue';
import Button from '@/components/ui/Button.vue';
import axios from 'axios';
import {
    ArrowLeft, User, Mail, Calendar, BookOpen, ClipboardList,
    CheckSquare, Activity, Brain, Sparkles, ShieldOff, ShieldCheck,
    Pencil, Trash2, ChevronDown, ChevronUp, Loader2, ExternalLink,
    GraduationCap, BarChart2, Clock, RefreshCw, X, Map,
} from 'lucide-vue-next';

defineOptions({ layout: LayoutInfoprodutor });

const props = defineProps({
    aluno:         { type: Object, required: true },
    products:      { type: Array,  default: () => [] },
    quiz_responses:{ type: Array,  default: () => [] },
    checkpoints:   { type: Array,  default: () => [] },
    activity:      { type: Array,  default: () => [] },
    neuro_scores:  { type: Array,  default: () => [] },
    insights:      { type: Array,  default: () => [] },
    journeys:      { type: Array,  default: () => [] },
    has_report:    { type: Boolean, default: false },
    stats:         { type: Object, default: () => ({}) },
    ai_available:  { type: Boolean, default: false },
});

const activeTab = ref('visao-geral');
const expandedQuiz = ref(null);
const expandedCheckpoint = ref(null);
const generatingReport = ref(false);
const generatedInsights = ref([...props.insights]);
const toast = ref({ message: null, type: null });
const editing = ref(false);
const saving = ref(false);
const blocking = ref(false);
const deleting = ref(false);
const form = ref({
    name: props.aluno.name,
    email: props.aluno.email,
    password: '',
    product_ids: props.products.map(p => p.id),
});
let toastTimer = null;

const tabs = [
    { key: 'visao-geral',  label: 'Visão Geral',   icon: BarChart2 },
    { key: 'quizzes',      label: 'Quizzes',        icon: ClipboardList },
    { key: 'checkpoints',  label: 'Checkpoints',    icon: CheckSquare },
    { key: 'relatorio-ia', label: 'Relatório IA',   icon: Brain },
    { key: 'jornadas',     label: 'Jornadas',       icon: Map },
    { key: 'atividade',    label: 'Atividade',      icon: Activity },
];

function showToast(message, type = 'success') {
    toast.value = { message, type };
    if (toastTimer) clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { toast.value = { message: null, type: null }; }, 4000);
}

function goBack() { router.visit('/alunos'); }

function progressColor(pct) {
    if (pct >= 80) return '#22c55e';
    if (pct >= 40) return '#f59e0b';
    return '#6366f1';
}

function eventLabel(event) {
    const map = {
        'login': 'Login realizado',
        'lesson_completed': 'Aula concluída',
        'lesson_viewed': 'Aula assistida',
        'quiz_submitted': 'Quiz respondido',
        'checkpoint_completed': 'Checkpoint concluído',
        'post_liked': 'Post curtido',
        'comment_posted': 'Comentário publicado',
        'achievement_unlocked': 'Conquista desbloqueada',
    };
    return map[event] ?? event;
}

// ─── Relatórios IA ─────────────────────────────────────────────────────────
const expandedInsightId = ref(null);
const editingInsightId  = ref(null);
const editingContent    = ref('');
const savingInsight     = ref(false);

const refazerModal = ref({ open: false, insightId: null, instructions: '', loading: false });

function toggleInsight(id) {
    expandedInsightId.value = expandedInsightId.value === id ? null : id;
}

function startInsightEdit(ins) {
    editingInsightId.value  = ins.id;
    editingContent.value    = ins.content;
    expandedInsightId.value = ins.id;
}

function cancelInsightEdit() {
    editingInsightId.value = null;
    editingContent.value   = '';
}

async function saveInsightEdit(ins) {
    if (!editingContent.value.trim()) return;
    savingInsight.value = true;
    try {
        const { data } = await axios.put(
            `/alunos/${props.aluno.id}/relatorio/${ins.id}`,
            { content: editingContent.value },
        );
        const idx = generatedInsights.value.findIndex(i => i.id === ins.id);
        if (idx >= 0) generatedInsights.value[idx] = data.insight;
        editingInsightId.value = null;
        showToast('Relatório atualizado.', 'success');
    } catch {
        showToast('Erro ao salvar.', 'error');
    } finally { savingInsight.value = false; }
}

function openRefazer(ins) {
    refazerModal.value = { open: true, insightId: ins.id, instructions: '', loading: false };
}

function closeRefazer() {
    refazerModal.value = { open: false, insightId: null, instructions: '', loading: false };
}

async function submitRefazer() {
    refazerModal.value.loading = true;
    try {
        const { data } = await axios.post(`/alunos/${props.aluno.id}/gerar-relatorio-ia`, {
            instructions: refazerModal.value.instructions,
        });
        generatedInsights.value.unshift(data.insight);
        expandedInsightId.value = data.insight.id;
        closeRefazer();
        showToast('Novo relatório gerado!', 'success');
    } catch (err) {
        showToast(err.response?.data?.error ?? 'Erro ao gerar relatório.', 'error');
    } finally {
        refazerModal.value.loading = false;
    }
}

async function generateReport() {
    generatingReport.value = true;
    try {
        const { data } = await axios.post(`/alunos/${props.aluno.id}/gerar-relatorio-ia`);
        generatedInsights.value.unshift(data.insight);
        activeTab.value = 'relatorio-ia';
        showToast('Relatório gerado com sucesso!', 'success');
    } catch (err) {
        showToast(err.response?.data?.error ?? 'Erro ao gerar relatório.', 'error');
    } finally {
        generatingReport.value = false;
    }
}

// ─── Jornadas ──────────────────────────────────────────────────────────────
const assignedJourneys  = ref([...props.journeys]);
const atribuindoJornada = ref(false);

async function atribuirJornada() {
    atribuindoJornada.value = true;
    try {
        const { data } = await axios.post(`/alunos/${props.aluno.id}/atribuir-jornada`);
        if (data.success && data.journey) {
            const already = assignedJourneys.value.some(j => j.id === data.journey.id);
            if (!already) {
                assignedJourneys.value.unshift({
                    id: data.journey.id,
                    name: data.journey.name,
                    slug: '',
                    cover: null,
                    is_free: true,
                    unlocked_at: new Date().toLocaleDateString('pt-BR'),
                });
            }
            showToast(data.new ? 'Jornada atribuída com sucesso!' : 'Jornada já estava atribuída.', 'success');
        }
    } catch (err) {
        showToast(err.response?.data?.error ?? 'Erro ao atribuir jornada.', 'error');
    } finally {
        atribuindoJornada.value = false;
    }
}

async function toggleBlock() {
    blocking.value = true;
    try {
        const { data } = await axios.put(`/produtos/alunos/${props.aluno.id}/toggle-block`);
        showToast(data.message ?? 'Alterado.', 'success');
        router.reload({ only: ['aluno'] });
    } catch {
        showToast('Erro ao alterar bloqueio.', 'error');
    } finally { blocking.value = false; }
}

async function saveEdit() {
    saving.value = true;
    try {
        const payload = {
            name: form.value.name,
            email: form.value.email,
            product_ids: form.value.product_ids,
        };
        if (form.value.password) payload.password = form.value.password;
        await axios.put(`/produtos/alunos/${props.aluno.id}`, payload);
        showToast('Dados atualizados.', 'success');
        editing.value = false;
        router.reload({ only: ['aluno', 'products'] });
    } catch (err) {
        showToast(err.response?.data?.message ?? 'Erro ao salvar.', 'error');
    } finally { saving.value = false; }
}

async function deleteAluno() {
    if (!confirm(`Excluir o aluno "${props.aluno.name}"? Esta ação não pode ser desfeita.`)) return;
    deleting.value = true;
    try {
        await axios.delete(`/produtos/alunos/${props.aluno.id}`);
        router.visit('/alunos');
    } catch {
        showToast('Erro ao excluir aluno.', 'error');
        deleting.value = false;
    }
}

const reportContent = computed(() => generatedInsights.value.find(i => i.type === 'admin_report') ?? generatedInsights.value[0] ?? null);
</script>

<template>
    <div style="min-height:100vh; background:var(--background,#0f0f12); color:var(--foreground,#e4e4e7); padding:24px;">

        <!-- Toast -->
        <Transition name="toast">
            <div v-if="toast.message" :style="{
                position:'fixed', top:'20px', right:'20px', zIndex:9999,
                background: toast.type==='error' ? '#ef4444' : '#22c55e',
                color:'#fff', padding:'12px 20px', borderRadius:'10px',
                boxShadow:'0 4px 20px rgba(0,0,0,.4)', fontWeight:600, fontSize:'14px',
            }">{{ toast.message }}</div>
        </Transition>

        <!-- Header -->
        <div style="max-width:1100px; margin:0 auto;">
            <button @click="goBack" style="display:flex;align-items:center;gap:6px;color:#a1a1aa;background:none;border:none;cursor:pointer;font-size:14px;margin-bottom:20px;padding:0;">
                <ArrowLeft :size="16" /> Voltar para Alunos
            </button>

            <!-- Perfil do aluno -->
            <div style="background:#18181b;border:1px solid #27272a;border-radius:16px;padding:24px;margin-bottom:24px;">
                <div style="display:flex;align-items:flex-start;gap:20px;flex-wrap:wrap;">
                    <!-- Avatar -->
                    <div style="width:72px;height:72px;border-radius:50%;background:#27272a;display:flex;align-items:center;justify-content:center;flex-shrink:0;overflow:hidden;">
                        <img v-if="aluno.avatar" :src="aluno.avatar" style="width:100%;height:100%;object-fit:cover;" />
                        <User v-else :size="32" color="#71717a" />
                    </div>

                    <!-- Info -->
                    <div style="flex:1;min-width:200px;">
                        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                            <h1 style="margin:0;font-size:22px;font-weight:700;">{{ aluno.name }}</h1>
                            <span v-if="aluno.is_blocked" style="background:#7f1d1d;color:#fca5a5;font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px;letter-spacing:.5px;">BLOQUEADO</span>
                            <span v-else style="background:#14532d;color:#86efac;font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px;letter-spacing:.5px;">ATIVO</span>
                        </div>
                        <div style="display:flex;align-items:center;gap:6px;color:#a1a1aa;font-size:13px;margin-top:4px;">
                            <Mail :size="13" /> {{ aluno.email }}
                        </div>
                        <div style="display:flex;align-items:center;gap:6px;color:#71717a;font-size:12px;margin-top:4px;">
                            <Calendar :size="12" /> Aluno desde {{ aluno.created_at }}
                        </div>
                    </div>

                    <!-- Ações -->
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <button @click="generateReport" :disabled="!ai_available || generatingReport"
                            style="display:flex;align-items:center;gap:6px;background:#6366f1;color:#fff;border:none;border-radius:8px;padding:8px 14px;font-size:13px;font-weight:600;cursor:pointer;opacity:1;transition:opacity .2s;"
                            :style="{ opacity: (!ai_available || generatingReport) ? '0.5' : '1' }">
                            <Loader2 v-if="generatingReport" :size="14" style="animation:spin 1s linear infinite;" />
                            <Brain v-else :size="14" />
                            {{ generatingReport ? 'Gerando...' : 'Gerar Relatório IA' }}
                        </button>
                        <button @click="editing = !editing"
                            style="display:flex;align-items:center;gap:6px;background:#27272a;color:#e4e4e7;border:none;border-radius:8px;padding:8px 14px;font-size:13px;font-weight:600;cursor:pointer;">
                            <Pencil :size="14" /> Editar
                        </button>
                        <button @click="toggleBlock" :disabled="blocking"
                            style="display:flex;align-items:center;gap:6px;background:#27272a;color:#e4e4e7;border:none;border-radius:8px;padding:8px 14px;font-size:13px;font-weight:600;cursor:pointer;">
                            <ShieldOff v-if="!aluno.is_blocked" :size="14" />
                            <ShieldCheck v-else :size="14" />
                            {{ aluno.is_blocked ? 'Desbloquear' : 'Bloquear' }}
                        </button>
                        <button @click="deleteAluno" :disabled="deleting"
                            style="display:flex;align-items:center;gap:6px;background:#7f1d1d;color:#fca5a5;border:none;border-radius:8px;padding:8px 14px;font-size:13px;font-weight:600;cursor:pointer;">
                            <Trash2 :size="14" /> Excluir
                        </button>
                    </div>
                </div>

                <!-- Form de edição inline -->
                <div v-if="editing" style="margin-top:20px;padding-top:20px;border-top:1px solid #27272a;">
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-bottom:12px;">
                        <div>
                            <label style="font-size:12px;color:#a1a1aa;display:block;margin-bottom:4px;">Nome</label>
                            <input v-model="form.name" style="width:100%;background:#09090b;border:1px solid #27272a;border-radius:8px;padding:8px 12px;color:#e4e4e7;font-size:14px;box-sizing:border-box;" />
                        </div>
                        <div>
                            <label style="font-size:12px;color:#a1a1aa;display:block;margin-bottom:4px;">E-mail</label>
                            <input v-model="form.email" type="email" style="width:100%;background:#09090b;border:1px solid #27272a;border-radius:8px;padding:8px 12px;color:#e4e4e7;font-size:14px;box-sizing:border-box;" />
                        </div>
                        <div>
                            <label style="font-size:12px;color:#a1a1aa;display:block;margin-bottom:4px;">Nova senha (opcional)</label>
                            <input v-model="form.password" type="password" placeholder="Deixe em branco para manter" style="width:100%;background:#09090b;border:1px solid #27272a;border-radius:8px;padding:8px 12px;color:#e4e4e7;font-size:14px;box-sizing:border-box;" />
                        </div>
                    </div>
                    <div style="display:flex;gap:8px;">
                        <button @click="saveEdit" :disabled="saving"
                            style="background:#6366f1;color:#fff;border:none;border-radius:8px;padding:8px 18px;font-size:13px;font-weight:600;cursor:pointer;">
                            {{ saving ? 'Salvando...' : 'Salvar' }}
                        </button>
                        <button @click="editing = false" style="background:#27272a;color:#e4e4e7;border:none;border-radius:8px;padding:8px 18px;font-size:13px;cursor:pointer;">Cancelar</button>
                    </div>
                </div>
            </div>

            <!-- Stats -->
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:24px;">
                <div v-for="stat in [
                    { label:'Aulas concluídas', value: stats.lessons_completed ?? 0, icon: BookOpen, color:'#6366f1' },
                    { label:'Quizzes respondidos', value: stats.quizzes_done ?? 0, icon: ClipboardList, color:'#f59e0b' },
                    { label:'Checkpoints feitos', value: stats.checkpoints_done ?? 0, icon: CheckSquare, color:'#22c55e' },
                    { label:'Dias ativos', value: stats.days_active ?? 0, icon: Clock, color:'#ec4899' },
                ]" :key="stat.label" style="background:#18181b;border:1px solid #27272a;border-radius:12px;padding:16px;">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                        <component :is="stat.icon" :size="16" :color="stat.color" />
                        <span style="font-size:12px;color:#71717a;">{{ stat.label }}</span>
                    </div>
                    <div style="font-size:26px;font-weight:700;">{{ stat.value }}</div>
                </div>
            </div>

            <!-- Tabs -->
            <div style="display:flex;gap:4px;border-bottom:1px solid #27272a;margin-bottom:24px;overflow-x:auto;">
                <button v-for="tab in tabs" :key="tab.key" @click="activeTab = tab.key"
                    :style="{
                        display:'flex', alignItems:'center', gap:'6px',
                        padding:'10px 16px', border:'none', cursor:'pointer',
                        fontSize:'13px', fontWeight:'600', whiteSpace:'nowrap',
                        borderBottom: activeTab === tab.key ? '2px solid #6366f1' : '2px solid transparent',
                        background:'none',
                        color: activeTab === tab.key ? '#6366f1' : '#71717a',
                    }">
                    <component :is="tab.icon" :size="14" />
                    {{ tab.label }}
                    <span v-if="tab.key==='relatorio-ia' && generatedInsights.length" style="background:#6366f1;color:#fff;font-size:10px;padding:1px 6px;border-radius:20px;">{{ generatedInsights.length }}</span>
                </button>
            </div>

            <!-- ===== TAB: VISÃO GERAL ===== -->
            <div v-if="activeTab === 'visao-geral'">
                <div v-if="products.length === 0" style="text-align:center;padding:48px;color:#71717a;">
                    Nenhuma trilha associada.
                </div>
                <div v-else style="display:grid;gap:12px;">
                    <div v-for="p in products" :key="p.id" style="background:#18181b;border:1px solid #27272a;border-radius:12px;padding:20px;">
                        <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;">
                            <div style="width:40px;height:40px;border-radius:8px;background:#27272a;overflow:hidden;flex-shrink:0;">
                                <img v-if="p.image" :src="p.image" style="width:100%;height:100%;object-fit:cover;" />
                                <div v-else style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;">
                                    <GraduationCap :size="20" color="#71717a" />
                                </div>
                            </div>
                            <div style="flex:1;">
                                <div style="font-weight:700;font-size:15px;">{{ p.name }}</div>
                                <div style="font-size:12px;color:#71717a;">Inscrito em {{ p.enrolled_at ?? '—' }}</div>
                            </div>
                            <div style="text-align:right;">
                                <div style="font-size:24px;font-weight:800;" :style="{ color: progressColor(p.progress_percent) }">{{ p.progress_percent }}%</div>
                                <div style="font-size:11px;color:#71717a;">{{ p.completed_lessons }}/{{ p.total_lessons }} aulas</div>
                            </div>
                        </div>
                        <!-- Barra de progresso -->
                        <div style="height:6px;background:#27272a;border-radius:99px;overflow:hidden;">
                            <div :style="{ width: p.progress_percent+'%', height:'100%', background: progressColor(p.progress_percent), borderRadius:'99px', transition:'width .5s' }"></div>
                        </div>
                    </div>
                </div>

                <!-- NeuroMap scores se existirem -->
                <div v-if="neuro_scores.length" style="margin-top:24px;">
                    <h3 style="margin:0 0 16px;font-size:16px;font-weight:700;display:flex;align-items:center;gap:8px;">
                        <Brain :size="16" color="#6366f1" /> Indicadores Neurofuncionais
                    </h3>
                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:10px;">
                        <div v-for="s in neuro_scores" :key="s.indicator" style="background:#18181b;border:1px solid #27272a;border-radius:10px;padding:14px;">
                            <div style="font-size:12px;color:#a1a1aa;margin-bottom:6px;">{{ s.indicator }}</div>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <div style="flex:1;height:6px;background:#27272a;border-radius:99px;overflow:hidden;">
                                    <div :style="{ width: (s.value*10)+'%', height:'100%', background:'#6366f1', borderRadius:'99px' }"></div>
                                </div>
                                <span style="font-size:14px;font-weight:700;color:#6366f1;">{{ s.value }}</span>
                            </div>
                            <div v-if="s.scored_at" style="font-size:11px;color:#52525b;margin-top:4px;">{{ s.scored_at }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ===== TAB: QUIZZES ===== -->
            <div v-if="activeTab === 'quizzes'">
                <div v-if="quiz_responses.length === 0" style="text-align:center;padding:48px;color:#71717a;">
                    Nenhum quiz respondido até o momento.
                </div>
                <div v-else style="display:grid;gap:10px;">
                    <div v-for="qr in quiz_responses" :key="qr.id" style="background:#18181b;border:1px solid #27272a;border-radius:12px;overflow:hidden;">
                        <button @click="expandedQuiz = expandedQuiz === qr.id ? null : qr.id"
                            style="width:100%;display:flex;align-items:center;gap:12px;padding:16px;background:none;border:none;cursor:pointer;color:inherit;text-align:left;">
                            <ClipboardList :size="16" color="#f59e0b" style="flex-shrink:0;" />
                            <div style="flex:1;">
                                <div style="font-size:14px;font-weight:600;">{{ qr.lesson_title }}</div>
                                <div style="font-size:12px;color:#71717a;">{{ qr.responded_at }} · {{ qr.responses.length }} respostas</div>
                            </div>
                            <ChevronDown v-if="expandedQuiz !== qr.id" :size="16" color="#52525b" />
                            <ChevronUp v-else :size="16" color="#52525b" />
                        </button>
                        <div v-if="expandedQuiz === qr.id" style="padding:0 16px 16px;border-top:1px solid #27272a;">
                            <div v-for="(resp, i) in qr.responses" :key="i" style="margin-top:14px;">
                                <div style="font-size:12px;color:#a1a1aa;margin-bottom:4px;">{{ resp.question_text }}</div>
                                <div style="font-size:14px;background:#09090b;border-radius:8px;padding:10px 14px;color:#e4e4e7;">{{ resp.value }}</div>
                                <div v-if="resp.comment" style="font-size:12px;color:#71717a;margin-top:4px;font-style:italic;">Obs: {{ resp.comment }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ===== TAB: CHECKPOINTS ===== -->
            <div v-if="activeTab === 'checkpoints'">
                <div v-if="checkpoints.length === 0" style="text-align:center;padding:48px;color:#71717a;">
                    Nenhum checkpoint respondido até o momento.
                </div>
                <div v-else style="display:grid;gap:10px;">
                    <div v-for="cp in checkpoints" :key="cp.id" style="background:#18181b;border:1px solid #27272a;border-radius:12px;overflow:hidden;">
                        <button @click="expandedCheckpoint = expandedCheckpoint === cp.id ? null : cp.id"
                            style="width:100%;display:flex;align-items:center;gap:12px;padding:16px;background:none;border:none;cursor:pointer;color:inherit;text-align:left;">
                            <CheckSquare :size="16" color="#22c55e" style="flex-shrink:0;" />
                            <div style="flex:1;">
                                <div style="font-size:14px;font-weight:600;">{{ cp.checkpoint_title }}</div>
                                <div style="font-size:12px;color:#71717a;">{{ cp.completed_at ?? '—' }} · {{ cp.answers.length }} respostas</div>
                            </div>
                            <ChevronDown v-if="expandedCheckpoint !== cp.id" :size="16" color="#52525b" />
                            <ChevronUp v-else :size="16" color="#52525b" />
                        </button>
                        <div v-if="expandedCheckpoint === cp.id" style="padding:0 16px 16px;border-top:1px solid #27272a;">
                            <div v-for="(ans, i) in cp.answers" :key="i" style="margin-top:14px;">
                                <div style="font-size:12px;color:#a1a1aa;margin-bottom:4px;">{{ ans.question }}</div>
                                <div style="font-size:14px;background:#09090b;border-radius:8px;padding:10px 14px;color:#e4e4e7;">{{ ans.value }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ===== TAB: RELATÓRIO IA ===== -->
            <div v-if="activeTab === 'relatorio-ia'">
                <!-- Sem IA configurada -->
                <div v-if="!ai_available" style="text-align:center;padding:48px;background:#18181b;border:1px solid #27272a;border-radius:16px;">
                    <Brain :size="48" color="#27272a" style="margin-bottom:16px;" />
                    <div style="font-size:18px;font-weight:700;margin-bottom:8px;">IA não configurada</div>
                    <div style="color:#71717a;font-size:14px;margin-bottom:20px;">Configure uma chave de API em Configurações → IA para gerar relatórios automáticos.</div>
                    <a href="/ia" style="display:inline-flex;align-items:center;gap:6px;background:#6366f1;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px;">
                        <ExternalLink :size="14" /> Ir para Configurações de IA
                    </a>
                </div>

                <!-- IA disponível mas sem relatórios -->
                <div v-else-if="generatedInsights.length === 0" style="text-align:center;padding:48px;background:#18181b;border:1px solid #27272a;border-radius:16px;">
                    <Sparkles :size="48" color="#6366f1" style="margin-bottom:16px;" />
                    <div style="font-size:18px;font-weight:700;margin-bottom:8px;">Relatório Avançado por IA</div>
                    <div style="color:#71717a;font-size:14px;margin-bottom:20px;max-width:480px;margin-left:auto;margin-right:auto;">
                        A IA irá analisar todas as respostas de quiz, checkpoints e progresso do aluno nas trilhas para gerar um relatório neurofuncional personalizado.
                    </div>
                    <button @click="generateReport" :disabled="generatingReport"
                        style="display:inline-flex;align-items:center;gap:8px;background:#6366f1;color:#fff;border:none;border-radius:10px;padding:12px 24px;font-size:15px;font-weight:700;cursor:pointer;">
                        <Loader2 v-if="generatingReport" :size="16" style="animation:spin 1s linear infinite;" />
                        <Brain v-else :size="16" />
                        {{ generatingReport ? 'Analisando dados...' : 'Gerar Relatório Agora' }}
                    </button>
                </div>

                <!-- Relatórios existentes -->
                <div v-else>
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
                        <h3 style="margin:0;font-size:16px;font-weight:700;display:flex;align-items:center;gap:8px;">
                            <Brain :size="16" color="#6366f1" /> Relatórios Gerados
                        </h3>
                        <button @click="generateReport" :disabled="generatingReport"
                            style="display:flex;align-items:center;gap:6px;background:#6366f1;color:#fff;border:none;border-radius:8px;padding:8px 16px;font-size:13px;font-weight:600;cursor:pointer;">
                            <Loader2 v-if="generatingReport" :size="13" style="animation:spin 1s linear infinite;" />
                            <Sparkles v-else :size="13" />
                            {{ generatingReport ? 'Gerando...' : 'Novo Relatório' }}
                        </button>
                    </div>

                    <div v-for="ins in generatedInsights" :key="ins.id"
                        style="background:#18181b;border:1px solid #27272a;border-radius:12px;margin-bottom:12px;overflow:hidden;">

                        <!-- Linha do cabeçalho (clicável para expandir) -->
                        <div style="display:flex;align-items:center;gap:12px;padding:14px 16px;cursor:pointer;"
                            @click="toggleInsight(ins.id)">
                            <Brain :size="14" color="#6366f1" style="flex-shrink:0;" />
                            <div style="flex:1;font-weight:600;font-size:14px;">{{ ins.title }}</div>
                            <span style="font-size:12px;color:#71717a;white-space:nowrap;">{{ ins.created_at }}</span>
                            <ChevronDown v-if="expandedInsightId !== ins.id" :size="15" color="#52525b" style="flex-shrink:0;" />
                            <ChevronUp v-else :size="15" color="#52525b" style="flex-shrink:0;" />
                        </div>

                        <!-- Barra de ações (sempre visível) -->
                        <div style="display:flex;align-items:center;gap:8px;padding:0 16px 14px;border-bottom:1px solid #27272a;"
                            @click.stop>
                            <button @click="startInsightEdit(ins)"
                                style="display:inline-flex;align-items:center;gap:5px;background:#27272a;color:#a1a1aa;font-size:12px;font-weight:500;padding:5px 10px;border-radius:7px;border:none;cursor:pointer;">
                                <Pencil :size="12" /> Editar
                            </button>
                            <a :href="`/alunos/${aluno.id}/relatorio/${ins.id}/imprimir`" target="_blank"
                                style="display:inline-flex;align-items:center;gap:5px;background:#27272a;color:#a1a1aa;font-size:12px;font-weight:500;padding:5px 10px;border-radius:7px;text-decoration:none;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                PDF
                            </a>
                            <button @click="openRefazer(ins)"
                                style="display:inline-flex;align-items:center;gap:5px;background:#1c1c2e;color:#818cf8;border:1px solid #3730a3;font-size:12px;font-weight:500;padding:5px 10px;border-radius:7px;cursor:pointer;">
                                <RefreshCw :size="12" /> Refazer com IA
                            </button>
                        </div>

                        <!-- Conteúdo expansível -->
                        <div v-if="expandedInsightId === ins.id" style="padding:20px;">

                            <!-- Modo leitura -->
                            <div v-if="editingInsightId !== ins.id"
                                style="font-size:14px;line-height:1.8;color:#d4d4d8;white-space:pre-wrap;background:#09090b;border-radius:8px;padding:20px;">{{ ins.content }}</div>

                            <!-- Modo edição -->
                            <div v-else>
                                <textarea v-model="editingContent"
                                    style="width:100%;min-height:400px;font-size:13px;line-height:1.8;color:#d4d4d8;background:#09090b;border:1px solid #6366f1;border-radius:8px;padding:16px;resize:vertical;font-family:inherit;outline:none;"
                                    placeholder="Conteúdo do relatório..."></textarea>
                                <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:10px;">
                                    <button @click="cancelInsightEdit"
                                        style="background:#27272a;color:#a1a1aa;border:none;padding:8px 16px;border-radius:8px;font-size:13px;cursor:pointer;">
                                        Cancelar
                                    </button>
                                    <button @click="saveInsightEdit(ins)" :disabled="savingInsight"
                                        style="background:#6366f1;color:#fff;border:none;padding:8px 18px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:6px;"
                                        :style="{ opacity: savingInsight ? 0.6 : 1 }">
                                        <Loader2 v-if="savingInsight" :size="13" style="animation:spin 1s linear infinite" />
                                        {{ savingInsight ? 'Salvando...' : 'Salvar alterações' }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ===== TAB: JORNADAS ===== -->
            <div v-if="activeTab === 'jornadas'">

                <!-- Botão atribuir -->
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
                    <div>
                        <h3 style="margin:0;font-size:15px;font-weight:700;">Jornadas atribuídas</h3>
                        <p style="margin:4px 0 0;font-size:13px;color:#71717a;">
                            {{ assignedJourneys.length === 0 ? 'Nenhuma jornada atribuída ainda.' : `${assignedJourneys.length} jornada${assignedJourneys.length !== 1 ? 's' : ''}` }}
                        </p>
                    </div>
                    <button v-if="ai_available && has_report" @click="atribuirJornada" :disabled="atribuindoJornada"
                        style="display:flex;align-items:center;gap:6px;padding:8px 16px;border-radius:8px;border:none;cursor:pointer;font-size:13px;font-weight:600;background:#6366f1;color:#fff;transition:opacity 0.2s;"
                        :style="{ opacity: atribuindoJornada ? 0.6 : 1 }">
                        <Loader2 v-if="atribuindoJornada" :size="14" style="animation:spin 1s linear infinite;" />
                        <Map v-else :size="14" />
                        {{ atribuindoJornada ? 'Atribuindo...' : 'Atribuir Jornada por IA' }}
                    </button>
                    <div v-else-if="!has_report" style="font-size:12px;color:#71717a;text-align:right;max-width:200px;">
                        Gere o Relatório IA primeiro para atribuir jornadas automaticamente.
                    </div>
                </div>

                <div v-if="assignedJourneys.length === 0"
                    style="text-align:center;padding:48px;background:#18181b;border:1px solid #27272a;border-radius:12px;">
                    <Map :size="32" color="#3f3f46" style="margin:0 auto 12px;" />
                    <p style="color:#71717a;font-size:14px;margin:0;">
                        {{ has_report ? 'Clique em "Atribuir Jornada por IA" para recomendar uma jornada com base no relatório.' : 'Nenhuma jornada atribuída.' }}
                    </p>
                </div>

                <div v-else style="display:flex;flex-direction:column;gap:12px;">
                    <div v-for="j in assignedJourneys" :key="j.id"
                        style="display:flex;align-items:center;gap:14px;padding:16px;background:#18181b;border:1px solid #27272a;border-radius:12px;">
                        <div style="width:44px;height:44px;border-radius:10px;background:#27272a;flex-shrink:0;overflow:hidden;display:flex;align-items:center;justify-content:center;">
                            <img v-if="j.cover" :src="j.cover" style="width:100%;height:100%;object-fit:cover;" />
                            <Map v-else :size="20" color="#52525b" />
                        </div>
                        <div style="flex:1;min-width:0;">
                            <div style="font-size:14px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ j.name }}</div>
                            <div style="font-size:12px;color:#71717a;margin-top:2px;">Desbloqueada em {{ j.unlocked_at }}</div>
                        </div>
                        <span :style="{ fontSize:'11px', fontWeight:600, padding:'3px 10px', borderRadius:'20px',
                            background: j.is_free ? 'rgba(34,197,94,0.12)' : 'rgba(245,158,11,0.12)',
                            color: j.is_free ? '#22c55e' : '#f59e0b',
                            border: `1px solid ${j.is_free ? 'rgba(34,197,94,0.25)' : 'rgba(245,158,11,0.25)'}` }">
                            {{ j.is_free ? 'Gratuita' : 'Paga' }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- ===== TAB: ATIVIDADE ===== -->
            <div v-if="activeTab === 'atividade'">
                <div v-if="activity.length === 0" style="text-align:center;padding:48px;color:#71717a;">
                    Nenhuma atividade registrada.
                </div>
                <div v-else style="background:#18181b;border:1px solid #27272a;border-radius:12px;overflow:hidden;">
                    <div v-for="(evt, i) in activity" :key="i"
                        :style="{ display:'flex', alignItems:'center', gap:'12px', padding:'12px 16px', borderBottom: i<activity.length-1 ? '1px solid #27272a' : 'none' }">
                        <div style="width:8px;height:8px;border-radius:50%;background:#6366f1;flex-shrink:0;"></div>
                        <div style="flex:1;font-size:14px;">{{ eventLabel(evt.event) }}</div>
                        <div style="font-size:12px;color:#71717a;white-space:nowrap;">{{ evt.created_at }}</div>
                    </div>
                </div>
            </div>

        </div>

        <!-- ===== MODAL: REFAZER RELATÓRIO ===== -->
        <Transition name="modal">
            <div v-if="refazerModal.open"
                style="position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:10000;display:flex;align-items:center;justify-content:center;padding:24px;"
                @click.self="closeRefazer">
                <div style="background:#18181b;border:1px solid #27272a;border-radius:16px;width:100%;max-width:520px;padding:28px;position:relative;">
                    <button @click="closeRefazer"
                        style="position:absolute;top:16px;right:16px;background:none;border:none;cursor:pointer;color:#52525b;">
                        <X :size="18" />
                    </button>
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;">
                        <RefreshCw :size="18" color="#818cf8" />
                        <h2 style="margin:0;font-size:17px;font-weight:700;">Refazer Relatório com IA</h2>
                    </div>
                    <p style="color:#71717a;font-size:13px;margin:0 0 20px;">
                        Um novo relatório será gerado com os dados atuais do aluno. Opcionalmente, informe o que a IA deve melhorar ou focar.
                    </p>
                    <label style="font-size:12px;color:#a1a1aa;display:block;margin-bottom:6px;">Instruções para a IA (opcional)</label>
                    <textarea v-model="refazerModal.instructions"
                        placeholder="Ex: Aprofundar a análise de regulação emocional. Usar linguagem mais acessível para o bloco 4. Focar nos padrões de atenção..."
                        style="width:100%;min-height:120px;background:#09090b;border:1px solid #3f3f46;border-radius:8px;padding:12px;color:#e4e4e7;font-size:13px;line-height:1.6;resize:vertical;font-family:inherit;outline:none;box-sizing:border-box;"
                        :disabled="refazerModal.loading"></textarea>
                    <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px;">
                        <button @click="closeRefazer" :disabled="refazerModal.loading"
                            style="background:#27272a;color:#a1a1aa;border:none;padding:9px 18px;border-radius:8px;font-size:13px;cursor:pointer;">
                            Cancelar
                        </button>
                        <button @click="submitRefazer" :disabled="refazerModal.loading"
                            style="background:#6366f1;color:#fff;border:none;padding:9px 20px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:7px;"
                            :style="{ opacity: refazerModal.loading ? 0.6 : 1 }">
                            <Loader2 v-if="refazerModal.loading" :size="13" style="animation:spin 1s linear infinite;" />
                            <RefreshCw v-else :size="13" />
                            {{ refazerModal.loading ? 'Gerando...' : 'Gerar Novo Relatório' }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>

<style>
@keyframes spin { from{transform:rotate(0deg)}to{transform:rotate(360deg)} }
.toast-enter-active,.toast-leave-active{transition:all .3s}
.toast-enter-from,.toast-leave-to{opacity:0;transform:translateY(-10px)}
.modal-enter-active,.modal-leave-active{transition:all .2s}
.modal-enter-from,.modal-leave-to{opacity:0}
.modal-enter-from > div,.modal-leave-to > div{transform:scale(.96)}
</style>
