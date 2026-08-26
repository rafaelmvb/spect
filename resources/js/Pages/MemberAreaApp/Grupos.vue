<script setup>
import { ref, computed } from 'vue';
import MemberAreaAppLayout from '@/Layouts/MemberAreaAppLayout.vue';
import axios from 'axios';
import { useMemberBase } from '@/composables/useMemberBase';

defineOptions({ layout: MemberAreaAppLayout });

const props = defineProps({
    product:  { type: Object, required: true },
    config:   { type: Object, default: () => ({}) },
    slug:     { type: String, required: true },
    groups:   { type: Array,  default: () => [] },
});

const primaryColor = computed(() => props.config?.theme?.primary || '#6FCF00');
const memberBase = useMemberBase(computed(() => props.slug));
const groups = ref([...props.groups]);
const toast = ref(null);

function csrfToken() { return document.querySelector('meta[name="csrf-token"]')?.content ?? ''; }
function showToast(msg, type = 'success') {
    toast.value = { msg, type };
    setTimeout(() => toast.value = null, 3500);
}

async function joinGroup(g) {
    try {
        const { data } = await axios.post(`${memberBase.value}/grupos/${g.id}/entrar`, {},
            { headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' } });
        const idx = groups.value.findIndex(x => x.id === g.id);
        if (idx >= 0) {
            groups.value[idx] = { ...groups.value[idx], join_status: data.status, is_member: data.status === 'member' };
        }
        showToast(data.status === 'member' ? 'Você entrou no grupo!' : 'Solicitação enviada!');
    } catch { showToast('Erro ao solicitar.', 'error'); }
}

function fmtMembers(n) { return n + ' membro' + (n !== 1 ? 's' : ''); }
</script>

<template>
    <div style="max-width:600px; margin:0 auto; padding:16px 16px 24px;">
        <!-- Toast -->
        <Transition enter-active-class="transition duration-300" enter-from-class="opacity-0 translate-y-2" enter-to-class="opacity-100">
            <div v-if="toast" style="position:fixed; bottom:96px; left:50%; transform:translateX(-50%); z-index:9999; padding:12px 24px; border-radius:12px; font-size:14px; font-weight:500; white-space:nowrap; font-family:'DM Sans',sans-serif; color:#0D1F2D;"
                :style="{ background: toast.type === 'error' ? '#E05555' : primaryColor }">{{ toast.msg }}</div>
        </Transition>

        <div style="padding:8px 0 24px;">
            <p style="font-family:'DM Mono',monospace; font-size:11px; text-transform:uppercase; letter-spacing:1.5px; color:var(--ma-text-2);">comunidade</p>
            <h1 style="font-family:'DM Sans',sans-serif; font-size:28px; font-weight:800; margin-top:6px; color:var(--ma-text);">Grupos</h1>
            <p style="font-size:13px; color:var(--ma-text-2); margin-top:4px; font-family:'DM Sans',sans-serif;">Participe de grupos da comunidade.</p>
        </div>

        <div v-if="!groups.length" style="text-align:center; padding:60px 24px; color:var(--ma-text-2);">
            <p style="font-size:32px; margin-bottom:8px;">👥</p>
            <p style="font-family:'DM Sans',sans-serif; font-size:15px;">Nenhum grupo disponível ainda.</p>
        </div>

        <div style="display:flex; flex-direction:column; gap:12px;">
            <div v-for="g in groups" :key="g.id"
                style="background:var(--ma-surface); border:1px solid var(--ma-border); border-radius:16px; overflow:hidden;">
                <!-- Capa -->
                <div style="height:120px; overflow:hidden; position:relative;"
                    :style="g.image_url ? '' : `background: linear-gradient(135deg, #1A3040, #132435)`">
                    <img v-if="g.image_url" :src="g.image_url" style="width:100%;height:100%;object-fit:cover;" />
                    <div v-else style="height:100%;display:flex;align-items:center;justify-content:center;font-size:40px;">👥</div>
                    <!-- Badge privado -->
                    <span style="position:absolute;top:10px;right:10px;padding:4px 10px;border-radius:20px;font-family:'DM Mono',monospace;font-size:10px;letter-spacing:1px;"
                        :style="g.is_private ? 'background:rgba(0,0,0,0.6);color:#fff;' : `background:rgba(0,0,0,0.5);color:${primaryColor};`">
                        {{ g.is_private ? '🔒 PRIVADO' : '🌐 PÚBLICO' }}
                    </span>
                </div>

                <div style="padding:16px 18px 18px;">
                    <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:12px;">
                        <div style="flex:1; min-width:0;">
                            <h2 style="font-family:'DM Sans',sans-serif; font-size:17px; font-weight:700; color:var(--ma-text);">{{ g.name }}</h2>
                            <p v-if="g.description" style="font-size:13px; color:var(--ma-text-2); margin-top:6px; line-height:1.5; font-family:'DM Sans',sans-serif;">{{ g.description }}</p>
                            <p style="font-size:12px; font-family:'DM Mono',monospace; color:var(--ma-text-2); margin-top:8px;">{{ fmtMembers(g.members_count) }}</p>
                        </div>
                        <!-- Botão de ação -->
                        <div style="flex-shrink:0; margin-top:2px;">
                            <span v-if="g.is_member"
                                style="display:inline-flex;align-items:center;gap:4px;padding:8px 16px;border-radius:20px;font-size:12px;font-weight:600;font-family:'DM Sans',sans-serif;background:rgba(111,207,0,0.12);border:1px solid rgba(111,207,0,0.3);"
                                :style="{ color: primaryColor }">✓ Membro</span>
                            <span v-else-if="g.join_status === 'pending'"
                                style="display:inline-flex;align-items:center;padding:8px 16px;border-radius:20px;font-size:12px;font-family:'DM Sans',sans-serif;background:var(--ma-surface-2);color:var(--ma-text-2);border:1px solid var(--ma-border);">
                                Aguardando
                            </span>
                            <button v-else type="button"
                                style="padding:8px 18px;border-radius:20px;border:none;cursor:pointer;font-size:13px;font-weight:700;font-family:'DM Sans',sans-serif;color:#0D1F2D;transition:opacity 0.2s;"
                                :style="{ background: primaryColor }"
                                @click="joinGroup(g)">
                                {{ g.is_private ? 'Solicitar' : 'Entrar' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
