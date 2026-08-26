<script setup>
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import MemberAreaAppLayout from '@/Layouts/MemberAreaAppLayout.vue';
import axios from 'axios';
import { useMemberBase } from '@/composables/useMemberBase';

defineOptions({ layout: MemberAreaAppLayout });

const props = defineProps({
    product:         { type: Object, required: true },
    config:          { type: Object, default: () => ({}) },
    slug:            { type: String, required: true },
    profile_user:    { type: Object, required: true },
    is_own:          { type: Boolean, default: false },
    can_see_private: { type: Boolean, default: false },
    friendship_status: { type: String, default: null },
    friendship_initiated_by_me: { type: Boolean, default: false },
    friends:         { type: Array, default: () => [] },
    pending_requests:{ type: Array, default: () => [] },
    playlists:       { type: Array, default: () => [] },
    available_tracks:{ type: Array, default: () => [] },
    groups:          { type: Array, default: () => [] },
});

const primaryColor = computed(() => props.config?.theme?.primary || '#6FCF00');
const activeTab = ref('playlists');

// Reativo: atualiza sem reload quando amizade é aceita via AJAX
const canSeePrivateComputed = computed(() =>
    props.is_own || props.can_see_private || friendshipStatus.value === 'accepted'
);

const toast = ref(null);
function showToast(msg, type = 'success') {
    toast.value = { msg, type };
    setTimeout(() => toast.value = null, 3500);
}

function csrfToken() { return document.querySelector('meta[name="csrf-token"]')?.content ?? ''; }
function headers(multipart = false) {
    const h = { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' };
    if (!multipart) h['Content-Type'] = 'application/json';
    return h;
}

const memberBase = useMemberBase(computed(() => props.slug));

// ─── Editar perfil ────────────────────────────────────────────────────────────
const editingProfile = ref(false);
const profileForm = ref({
    bio:        props.profile_user.bio ?? '',
    location:   props.profile_user.location ?? '',
    gender:     props.profile_user.gender ?? '',
    birth_date: props.profile_user.birth_date ?? '',
});
const avatarInputRef = ref(null);
const savingProfile = ref(false);

const genderLabels = {
    masculino: 'Masculino',
    feminino: 'Feminino',
    outro: 'Outro',
    prefiro_nao_dizer: 'Prefiro não dizer',
};

function openEditProfile() {
    profileForm.value = {
        bio:        props.profile_user.bio ?? '',
        location:   props.profile_user.location ?? '',
        gender:     props.profile_user.gender ?? '',
        birth_date: props.profile_user.birth_date ?? '',
    };
    editingProfile.value = true;
}

async function saveProfile() {
    savingProfile.value = true;
    try {
        await axios.post(`${memberBase.value}/perfil/atualizar`, profileForm.value, { headers: headers() });
        editingProfile.value = false;
        showToast('Perfil atualizado!');
        router.reload({ only: ['profile_user'] });
    } catch { showToast('Erro ao salvar.', 'error'); }
    finally { savingProfile.value = false; }
}

async function onAvatarChange(e) {
    const f = e.target?.files?.[0]; if (!f) return;
    if (f.size > 5 * 1024 * 1024) {
        showToast('A foto deve ter no máximo 5 MB.', 'error');
        return;
    }
    savingProfile.value = true;
    const fd = new FormData();
    fd.append('avatar', f);
    try {
        await axios.post(`${memberBase.value}/perfil/atualizar`, fd, { headers: headers(true) });
        showToast('Foto atualizada!');
        router.reload({ only: ['profile_user'] });
    } catch (err) {
        const msg = err?.response?.data?.errors?.avatar?.[0]
                 || err?.response?.data?.message
                 || 'Erro ao enviar foto.';
        showToast(msg, 'error');
    }
    finally { savingProfile.value = false; }
}

// ─── Amizades ─────────────────────────────────────────────────────────────────
const friendshipStatus = ref(props.friendship_status);
const friendshipByMe = ref(props.friendship_initiated_by_me);
const pendingRequests = ref([...props.pending_requests]);
const localFriends = ref([...props.friends]);

async function sendFriend() {
    try {
        await axios.post(`${memberBase.value}/amizades/${props.profile_user.id}`, {}, { headers: headers() });
        friendshipStatus.value = 'pending'; friendshipByMe.value = true;
        showToast('Pedido enviado!');
    } catch { showToast('Erro.', 'error'); }
}

async function acceptFriend(userId) {
    try {
        await axios.put(`${memberBase.value}/amizades/${userId}/aceitar`, {}, { headers: headers() });
        friendshipStatus.value = 'accepted';
        pendingRequests.value = pendingRequests.value.filter(r => r.requester_id !== userId);
        showToast('Amizade aceita! Conteúdo do perfil liberado.');
        // Recarrega dados do servidor para buscar playlists/grupos/amigos
        router.reload({ only: ['playlists', 'groups', 'friends', 'can_see_private', 'pending_requests'] });
    } catch { showToast('Erro.', 'error'); }
}

async function removeFriend(userId = null) {
    try {
        const targetId = userId ?? props.profile_user.id;
        await axios.delete(`${memberBase.value}/amizades/${targetId}`, { headers: headers() });
        friendshipStatus.value = null; friendshipByMe.value = false;
        if (userId) {
            pendingRequests.value = pendingRequests.value.filter(r => r.requester_id !== userId);
        } else {
            // removendo da lista de amigos do próprio perfil
            localFriends.value = localFriends.value.filter(f => f.id !== targetId);
        }
        showToast('Removido.');
    } catch { showToast('Erro.', 'error'); }
}

async function unfriendFromList(friendId) {
    try {
        await axios.delete(`${memberBase.value}/amizades/${friendId}`, { headers: headers() });
        localFriends.value = localFriends.value.filter(f => f.id !== friendId);
        showToast('Amigo removido.');
    } catch { showToast('Erro.', 'error'); }
}

// ─── Playlists ─────────────────────────────────────────────────────────────────
const playlists = ref([...props.playlists]);
const newPlaylistName = ref('');
const playlistExpanded = ref({});
const addingTrackTo = ref(null);

async function createPlaylist() {
    if (!newPlaylistName.value.trim()) return;
    try {
        const { data } = await axios.post(`${memberBase.value}/playlists`, { name: newPlaylistName.value.trim() }, { headers: headers() });
        playlists.value.push(data);
        newPlaylistName.value = '';
        showToast('Playlist criada!');
    } catch { showToast('Erro.', 'error'); }
}

async function deletePlaylist(playlistId) {
    if (!confirm('Excluir playlist?')) return;
    await axios.delete(`${memberBase.value}/playlists/${playlistId}`, { headers: headers() });
    playlists.value = playlists.value.filter(p => p.id !== playlistId);
    showToast('Playlist excluída.');
}

async function addTrack(playlistId, trackId) {
    try {
        await axios.post(`${memberBase.value}/playlists/${playlistId}/tracks/${trackId}`, {}, { headers: headers() });
        const pl = playlists.value.find(p => p.id === playlistId);
        const track = props.available_tracks.find(t => t.id === trackId);
        if (pl && track && !pl.tracks.find(t => t.id === trackId)) pl.tracks.push({ id: track.id, title: track.title });
        showToast('Áudio adicionado!');
    } catch { showToast('Já na playlist.', 'error'); }
}

async function removeTrack(playlistId, trackId) {
    await axios.delete(`${memberBase.value}/playlists/${playlistId}/tracks/${trackId}`, { headers: headers() });
    const pl = playlists.value.find(p => p.id === playlistId);
    if (pl) pl.tracks = pl.tracks.filter(t => t.id !== trackId);
}

// ─── Grupos ────────────────────────────────────────────────────────────────────
const groups = ref([...props.groups]);

async function joinGroup(groupId) {
    try {
        const { data } = await axios.post(`${memberBase.value}/grupos/${groupId}/entrar`, {}, { headers: headers() });
        const g = groups.value.find(x => x.id === groupId);
        if (g) { g.join_status = data.status; if (data.status === 'member') g.is_member = true; }
        showToast(data.status === 'member' ? 'Entrou no grupo!' : 'Solicitação enviada!');
    } catch { showToast('Erro.', 'error'); }
}

function getInitials(name) {
    if (!name) return '?';
    return name.split(/\s+/).map(n => n[0]).slice(0, 2).join('').toUpperCase();
}
</script>

<template>
    <div style="max-width:480px; margin:0 auto; padding-bottom:24px;">
        <!-- Toast -->
        <Transition enter-active-class="transition duration-300" enter-from-class="opacity-0 translate-y-2" enter-to-class="opacity-100">
            <div v-if="toast" style="position:fixed; bottom:96px; left:50%; transform:translateX(-50%); z-index:9999; padding:12px 24px; border-radius:12px; font-size:14px; font-weight:500; white-space:nowrap; font-family:'DM Sans',sans-serif;"
                :style="{ background: toast.type === 'error' ? '#E05555' : primaryColor, color: '#0D1F2D' }">
                {{ toast.msg }}
            </div>
        </Transition>

        <!-- Cabeçalho do perfil -->
        <div style="display:flex; flex-direction:column; align-items:center; padding:48px 24px 32px; position:relative;">
            <!-- Avatar -->
            <div style="position:relative;">
                <div style="width:80px; height:80px; border-radius:50%; overflow:hidden; display:flex; align-items:center; justify-content:center; font-size:32px; font-weight:800; cursor:pointer;"
                    :style="{ background: primaryColor, color: '#0D1F2D' }"
                    @click="is_own && avatarInputRef?.click()">
                    <img v-if="profile_user.avatar_url" :src="profile_user.avatar_url" style="width:100%;height:100%;object-fit:cover;" />
                    <span v-else>{{ getInitials(profile_user.name) }}</span>
                </div>
                <div v-if="is_own"
                    style="position:absolute; bottom:0; right:0; width:24px; height:24px; border-radius:50%; display:flex; align-items:center; justify-content:center; cursor:pointer; border:2px solid var(--ma-bg);"
                    :style="{ background: primaryColor, color: '#0D1F2D' }"
                    @click="avatarInputRef?.click()">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M23 19a2 2 0 01-2 2H3a2 2 0 01-2-2V8a2 2 0 012-2h4l2-3h6l2 3h4a2 2 0 012 2z"/><circle cx="12" cy="13" r="4"/></svg>
                </div>
                <input ref="avatarInputRef" type="file" accept="image/*" style="display:none;" @change="onAvatarChange" />
            </div>

            <!-- Nome -->
            <h1 style="font-family:'DM Sans',sans-serif; font-size:22px; font-weight:800; color:var(--ma-text); margin-top:14px;">{{ profile_user.name }}</h1>
            <p style="font-family:'DM Mono',monospace; font-size:11px; color:var(--ma-text-2); margin-top:6px;">membro desde {{ profile_user.joined_at }}</p>

            <!-- Bio / dados do perfil -->
            <div style="margin-top:14px; width:100%; max-width:340px; text-align:center;">
                <!-- Descrição -->
                <p v-if="profile_user.bio" style="font-size:14px; color:var(--ma-text-2); line-height:1.6; font-family:'DM Sans',sans-serif; font-style:italic; margin-bottom:10px;">
                    {{ profile_user.bio }}
                </p>

                <!-- Info chips -->
                <div style="display:flex; flex-wrap:wrap; justify-content:center; gap:8px; margin-bottom:12px;">
                    <span v-if="profile_user.age" style="display:inline-flex; align-items:center; gap:4px; padding:5px 12px; border-radius:20px; background:var(--ma-surface-2); border:1px solid var(--ma-border); font-size:12px; font-family:'DM Mono',monospace; color:var(--ma-text-2);">
                        🎂 {{ profile_user.age }} anos
                    </span>
                    <span v-if="profile_user.location" style="display:inline-flex; align-items:center; gap:4px; padding:5px 12px; border-radius:20px; background:var(--ma-surface-2); border:1px solid var(--ma-border); font-size:12px; font-family:'DM Mono',monospace; color:var(--ma-text-2);">
                        📍 {{ profile_user.location }}
                    </span>
                    <span v-if="profile_user.gender" style="display:inline-flex; align-items:center; gap:4px; padding:5px 12px; border-radius:20px; background:var(--ma-surface-2); border:1px solid var(--ma-border); font-size:12px; font-family:'DM Mono',monospace; color:var(--ma-text-2);">
                        {{ { masculino:'♂', feminino:'♀', outro:'⚧', prefiro_nao_dizer:'—' }[profile_user.gender] }} {{ { masculino:'Masculino', feminino:'Feminino', outro:'Outro', prefiro_nao_dizer:'Prefiro não dizer' }[profile_user.gender] }}
                    </span>
                </div>

                <!-- Botão editar (só dono) -->
                <button v-if="is_own && !editingProfile" type="button"
                    style="background:none; border:1px solid var(--ma-border); cursor:pointer; font-size:12px; font-family:'DM Mono',monospace; text-transform:uppercase; letter-spacing:1px; padding:8px 20px; border-radius:10px; color:var(--ma-text-2); transition:border-color 0.2s;"
                    @mouseenter="$event.currentTarget.style.borderColor=primaryColor"
                    @mouseleave="$event.currentTarget.style.borderColor='var(--ma-border)'"
                    @click="openEditProfile">
                    ✏ Editar perfil
                </button>

                <!-- Formulário de edição -->
                <div v-if="editingProfile" style="width:100%; text-align:left; margin-top:12px; background:var(--ma-surface); border:1px solid var(--ma-border); border-radius:16px; padding:20px; space-y-12px;">
                    <div style="margin-bottom:12px;">
                        <label style="display:block; font-family:'DM Mono',monospace; font-size:11px; text-transform:uppercase; letter-spacing:1px; color:var(--ma-text-2); margin-bottom:6px;">Descrição</label>
                        <textarea v-model="profileForm.bio" rows="3" maxlength="500"
                            style="width:100%; background:var(--ma-bg); border:1px solid var(--ma-border); border-radius:10px; padding:10px 12px; color:var(--ma-text); font-family:'DM Sans',sans-serif; font-size:13px; resize:none; outline:none; box-sizing:border-box;"
                            placeholder="Fale sobre você..." />
                    </div>
                    <div style="margin-bottom:12px;">
                        <label style="display:block; font-family:'DM Mono',monospace; font-size:11px; text-transform:uppercase; letter-spacing:1px; color:var(--ma-text-2); margin-bottom:6px;">Localidade</label>
                        <input v-model="profileForm.location" type="text" maxlength="100"
                            style="width:100%; background:var(--ma-bg); border:1px solid var(--ma-border); border-radius:10px; padding:10px 12px; color:var(--ma-text); font-family:'DM Sans',sans-serif; font-size:13px; outline:none; box-sizing:border-box;"
                            placeholder="Cidade, Estado" />
                    </div>
                    <div style="margin-bottom:12px;">
                        <label style="display:block; font-family:'DM Mono',monospace; font-size:11px; text-transform:uppercase; letter-spacing:1px; color:var(--ma-text-2); margin-bottom:6px;">Sexo</label>
                        <select v-model="profileForm.gender"
                            style="width:100%; background:var(--ma-bg); border:1px solid var(--ma-border); border-radius:10px; padding:10px 12px; color:var(--ma-text); font-family:'DM Sans',sans-serif; font-size:13px; outline:none; box-sizing:border-box;">
                            <option value="">Prefiro não informar</option>
                            <option value="masculino">Masculino</option>
                            <option value="feminino">Feminino</option>
                            <option value="outro">Outro</option>
                            <option value="prefiro_nao_dizer">Prefiro não dizer</option>
                        </select>
                    </div>
                    <div style="margin-bottom:16px;">
                        <label style="display:block; font-family:'DM Mono',monospace; font-size:11px; text-transform:uppercase; letter-spacing:1px; color:var(--ma-text-2); margin-bottom:6px;">Data de nascimento</label>
                        <input v-model="profileForm.birth_date" type="date"
                            style="width:100%; background:var(--ma-bg); border:1px solid var(--ma-border); border-radius:10px; padding:10px 12px; color:var(--ma-text); font-family:'DM Sans',sans-serif; font-size:13px; outline:none; box-sizing:border-box;" />
                    </div>
                    <div style="display:flex; gap:8px;">
                        <button type="button" style="flex:1; padding:10px; border-radius:10px; border:1px solid var(--ma-border); background:none; cursor:pointer; font-size:13px; font-family:'DM Sans',sans-serif; color:var(--ma-text-2);" @click="editingProfile = false">Cancelar</button>
                        <button type="button" style="flex:1; padding:10px; border-radius:10px; border:none; cursor:pointer; font-size:13px; font-weight:700; font-family:'DM Sans',sans-serif; color:#0D1F2D;" :style="{ background: primaryColor }" :disabled="savingProfile" @click="saveProfile">
                            {{ savingProfile ? '...' : 'Salvar' }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Ações de amizade (perfil alheio) -->
            <div v-if="!is_own" style="margin-top:18px; display:flex; gap:10px;">
                <button v-if="!friendshipStatus" type="button"
                    style="padding:12px 24px; border-radius:12px; border:none; cursor:pointer; font-size:14px; font-weight:600; font-family:'DM Sans',sans-serif; color:#0D1F2D;"
                    :style="{ background: primaryColor }"
                    @click="sendFriend">
                    + Adicionar amigo
                </button>
                <button v-else-if="friendshipStatus === 'pending' && friendshipByMe" type="button"
                    style="padding:12px 24px; border-radius:12px; border:none; cursor:pointer; font-size:14px; font-family:'DM Sans',sans-serif; background:var(--ma-surface-2); color:var(--ma-text-2);"
                    @click="removeFriend()">
                    Pedido enviado · cancelar
                </button>
                <template v-else-if="friendshipStatus === 'pending' && !friendshipByMe">
                    <button type="button" style="padding:12px 24px; border-radius:12px; border:none; cursor:pointer; font-size:14px; font-weight:600; font-family:'DM Sans',sans-serif; color:#0D1F2D;" :style="{ background: primaryColor }" @click="acceptFriend(profile_user.id)">Aceitar</button>
                    <button type="button" style="padding:12px 24px; border-radius:12px; border:1px solid var(--ma-border); background:none; cursor:pointer; font-size:14px; font-family:'DM Sans',sans-serif; color:var(--ma-text-2);" @click="removeFriend()">Recusar</button>
                </template>
                <button v-else-if="friendshipStatus === 'accepted'" type="button"
                    style="padding:12px 24px; border-radius:12px; border:none; cursor:pointer; font-size:14px; font-family:'DM Sans',sans-serif; background:var(--ma-surface-2); color:var(--ma-text-2);"
                    @click="removeFriend()">
                    ✓ Amigos · remover
                </button>
            </div>

            <!-- Pedidos pendentes (meu perfil) -->
            <div v-if="is_own && pendingRequests.length" style="margin-top:20px; width:100%; background:rgba(111,207,0,0.08); border:1px solid rgba(111,207,0,0.2); border-radius:14px; padding:16px;">
                <p style="font-family:'DM Mono',monospace; font-size:11px; text-transform:uppercase; letter-spacing:1px; margin-bottom:12px;" :style="{ color: primaryColor }">{{ pendingRequests.length }} pedido(s) de amizade</p>
                <div v-for="req in pendingRequests" :key="req.id" style="display:flex; align-items:center; justify-content:space-between; margin-bottom:10px;">
                    <span style="font-size:14px; color:var(--ma-text); font-family:'DM Sans',sans-serif;">{{ req.name }}</span>
                    <div style="display:flex; gap:6px;">
                        <button type="button" style="padding:6px 16px; border-radius:8px; border:none; cursor:pointer; font-size:13px; font-weight:600; color:#0D1F2D;" :style="{ background: primaryColor }" @click="acceptFriend(req.requester_id)">Aceitar</button>
                        <button type="button" style="padding:6px 16px; border-radius:8px; border:1px solid var(--ma-border); background:none; cursor:pointer; font-size:13px; color:var(--ma-text-2);" @click="removeFriend(req.requester_id)">Recusar</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div style="display:flex; gap:0; border-bottom:1px solid var(--ma-border); margin:0 16px;">
            <button v-for="tab in [
                { id: 'playlists', label: '🎵 Playlists' },
                { id: 'groups',   label: '👥 Grupos' },
                { id: 'friends',  label: `❤️ Amigos (${friends.length})` },
            ]" :key="tab.id" type="button"
                style="flex:1; padding:14px 8px; border:none; background:none; cursor:pointer; font-family:'DM Sans',sans-serif; font-size:13px; font-weight:500; transition:color 0.15s; border-bottom:2px solid transparent; margin-bottom:-1px;"
                :style="activeTab === tab.id
                    ? { color: primaryColor, borderBottomColor: primaryColor }
                    : { color: 'var(--ma-text-2)', borderBottomColor: 'transparent' }"
                @click="activeTab = tab.id">
                {{ tab.label }}
            </button>
        </div>

        <!-- ═══ ABA PLAYLISTS ══════════════════════════════════════════════════ -->
        <div v-if="activeTab === 'playlists'" style="padding:16px;">
            <!-- Aviso privacidade -->
            <div v-if="!canSeePrivateComputed && !is_own" style="text-align:center; padding:40px 24px; color:var(--ma-text-2);">
                <p style="font-size:28px; margin-bottom:12px;">🔒</p>
                <p style="font-family:'DM Sans',sans-serif; font-size:15px; font-weight:600; color:var(--ma-text); margin-bottom:6px;">Conteúdo privado</p>
                <p style="font-family:'DM Sans',sans-serif; font-size:13px;">Adicione {{ profile_user.name }} como amigo para ver as playlists, grupos e amigos.</p>
            </div>
            <template v-else>
            <!-- Criar playlist (só dono) -->
            <div v-if="is_own" style="display:flex; gap:8px; margin-bottom:20px;">
                <input v-model="newPlaylistName" type="text"
                    style="flex:1; background:var(--ma-surface); border:1px solid var(--ma-border); border-radius:12px; padding:12px 16px; color:var(--ma-text); font-family:'DM Sans',sans-serif; font-size:14px; outline:none;"
                    placeholder="Nome da nova playlist..."
                    @keydown.enter="createPlaylist" />
                <button type="button"
                    style="padding:12px 20px; border-radius:12px; border:none; cursor:pointer; font-weight:700; font-size:18px; color:#0D1F2D; flex-shrink:0;"
                    :style="{ background: primaryColor }"
                    @click="createPlaylist">+</button>
            </div>

            <div v-if="!playlists.length" style="text-align:center; padding:40px 24px; color:var(--ma-text-2); font-family:'DM Sans',sans-serif;">
                <p style="font-size:32px; margin-bottom:8px;">🎵</p>
                <p>{{ is_own ? 'Crie sua primeira playlist!' : 'Nenhuma playlist ainda.' }}</p>
            </div>

            <div v-for="pl in playlists" :key="pl.id" style="background:var(--ma-surface); border:1px solid var(--ma-border); border-radius:14px; margin-bottom:12px; overflow:hidden;">
                <!-- Header da playlist -->
                <div style="display:flex; align-items:center; justify-content:space-between; padding:14px 16px; cursor:pointer;"
                    @click="playlistExpanded[pl.id] = !playlistExpanded[pl.id]">
                    <div style="display:flex; align-items:center; gap:12px;">
                        <div style="width:44px; height:44px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:20px; background:rgba(111,207,0,0.1);">🎵</div>
                        <div>
                            <p style="font-size:15px; font-weight:600; color:var(--ma-text); font-family:'DM Sans',sans-serif;">{{ pl.name }}</p>
                            <p style="font-size:12px; font-family:'DM Mono',monospace; color:var(--ma-text-2); margin-top:2px;">{{ pl.tracks.length }} áudio{{ pl.tracks.length !== 1 ? 's' : '' }}</p>
                        </div>
                    </div>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <button v-if="is_own" type="button" style="background:none; border:none; cursor:pointer; color:#E05555; font-size:16px; padding:4px;" @click.stop="deletePlaylist(pl.id)">🗑</button>
                        <span style="color:var(--ma-text-2); font-size:16px;">{{ playlistExpanded[pl.id] ? '▲' : '▼' }}</span>
                    </div>
                </div>

                <!-- Tracks da playlist -->
                <div v-if="playlistExpanded[pl.id]" style="border-top:1px solid var(--ma-border);">
                    <div v-for="track in pl.tracks" :key="track.id"
                        style="display:flex; align-items:center; justify-content:space-between; padding:12px 16px; border-bottom:1px solid rgba(30,52,72,0.5);">
                        <div style="display:flex; align-items:center; gap:10px;">
                            <span style="font-size:16px;">🎧</span>
                            <span style="font-size:13px; color:var(--ma-text); font-family:'DM Sans',sans-serif;">{{ track.title }}</span>
                        </div>
                        <button v-if="is_own" type="button" style="background:none; border:none; cursor:pointer; color:var(--ma-text-2); font-size:14px;" @click="removeTrack(pl.id, track.id)">✕</button>
                    </div>

                    <!-- Adicionar música -->
                    <div v-if="is_own" style="padding:12px 16px;">
                        <button type="button"
                            style="width:100%; padding:10px; border-radius:10px; border:1px dashed var(--ma-border); background:none; cursor:pointer; font-size:13px; font-family:'DM Sans',sans-serif; color:var(--ma-text-2); transition:border-color 0.2s;"
                            @click="addingTrackTo = addingTrackTo === pl.id ? null : pl.id">
                            + Adicionar áudio
                        </button>
                        <div v-if="addingTrackTo === pl.id" style="margin-top:8px; max-height:200px; overflow-y:auto; background:var(--ma-surface-2); border-radius:10px; border:1px solid var(--ma-border);">
                            <div v-for="track in available_tracks" :key="track.id"
                                style="display:flex; align-items:center; justify-content:space-between; padding:10px 14px; border-bottom:1px solid rgba(30,52,72,0.4); cursor:pointer; transition:background 0.15s;"
                                @mouseenter="$event.currentTarget.style.background='rgba(111,207,0,0.05)'"
                                @mouseleave="$event.currentTarget.style.background='transparent'"
                                @click="addTrack(pl.id, track.id)">
                                <span style="font-size:13px; color:var(--ma-text); font-family:'DM Sans',sans-serif;">{{ track.title }}</span>
                                <span :style="{ color: primaryColor, fontSize: '18px' }">+</span>
                            </div>
                            <p v-if="!available_tracks.length" style="padding:12px; font-size:13px; color:var(--ma-text-2); font-family:'DM Sans',sans-serif;">Nenhum áudio disponível.</p>
                        </div>
                    </div>
                </div>
            </div>
            </template>
        </div>

        <!-- ═══ ABA GRUPOS ════════════════════════════════════════════════════ -->
        <div v-if="activeTab === 'groups'" style="padding:16px;">
            <!-- Privacidade -->
            <div v-if="!canSeePrivateComputed && !is_own" style="text-align:center; padding:40px 24px; color:var(--ma-text-2);">
                <p style="font-size:28px; margin-bottom:12px;">🔒</p>
                <p style="font-family:'DM Sans',sans-serif; font-size:15px; font-weight:600; color:var(--ma-text); margin-bottom:6px;">Conteúdo privado</p>
                <p style="font-family:'DM Sans',sans-serif; font-size:13px;">Adicione {{ profile_user.name }} como amigo para ver os grupos.</p>
            </div>
            <div v-else-if="!groups.length" style="text-align:center; padding:40px 24px; color:var(--ma-text-2); font-family:'DM Sans',sans-serif;">
                <p style="font-size:32px; margin-bottom:8px;">👥</p>
                <p>Nenhum grupo disponível ainda.</p>
            </div>
            <template v-else>
            <div v-for="g in groups" :key="g.id" style="background:var(--ma-surface); border:1px solid var(--ma-border); border-radius:14px; padding:16px; margin-bottom:10px; display:flex; align-items:center; gap:14px;">
                <div style="width:52px; height:52px; border-radius:12px; overflow:hidden; flex-shrink:0; background:var(--ma-surface-2); display:flex; align-items:center; justify-content:center;">
                    <img v-if="g.image_url" :src="g.image_url" style="width:100%;height:100%;object-fit:cover;" />
                    <span v-else style="font-size:24px;">👥</span>
                </div>
                <div style="flex:1; min-width:0;">
                    <p style="font-size:15px; font-weight:600; color:var(--ma-text); font-family:'DM Sans',sans-serif;">{{ g.name }}</p>
                    <p style="font-size:12px; font-family:'DM Mono',monospace; color:var(--ma-text-2); margin-top:2px;">{{ g.is_private ? '🔒 Privado' : '🌐 Público' }}</p>
                </div>
                <div v-if="is_own" style="flex-shrink:0;">
                    <span v-if="g.is_member"
                        style="padding:8px 16px; border-radius:10px; font-size:12px; font-weight:600; font-family:'DM Sans',sans-serif; background:rgba(111,207,0,0.12); border:1px solid rgba(111,207,0,0.3);"
                        :style="{ color: primaryColor }">
                        ✓ Membro
                    </span>
                    <span v-else-if="g.join_status === 'pending'"
                        style="padding:8px 16px; border-radius:10px; font-size:12px; font-family:'DM Sans',sans-serif; background:var(--ma-surface-2); color:var(--ma-text-2); border:1px solid var(--ma-border);">
                        Aguardando
                    </span>
                    <button v-else type="button"
                        style="padding:8px 16px; border-radius:10px; border:none; cursor:pointer; font-size:12px; font-weight:600; font-family:'DM Sans',sans-serif; color:#0D1F2D;"
                        :style="{ background: primaryColor }"
                        @click="joinGroup(g.id)">
                        {{ g.is_private ? 'Solicitar' : 'Entrar' }}
                    </button>
                </div>
            </div>
            </template>
        </div>

        <!-- ═══ ABA AMIGOS ═════════════════════════════════════════════════════ -->
        <div v-if="activeTab === 'friends'" style="padding:16px;">
            <!-- Privacidade -->
            <div v-if="!canSeePrivateComputed && !is_own" style="text-align:center; padding:40px 24px; color:var(--ma-text-2);">
                <p style="font-size:28px; margin-bottom:12px;">🔒</p>
                <p style="font-family:'DM Sans',sans-serif; font-size:15px; font-weight:600; color:var(--ma-text); margin-bottom:6px;">Conteúdo privado</p>
                <p style="font-family:'DM Sans',sans-serif; font-size:13px;">Adicione {{ profile_user.name }} como amigo para ver os amigos dele.</p>
            </div>
            <div v-else-if="!localFriends.length" style="text-align:center; padding:40px 24px; color:var(--ma-text-2); font-family:'DM Sans',sans-serif;">
                <p style="font-size:32px; margin-bottom:8px;">❤️</p>
                <p>Nenhum amigo ainda.</p>
            </div>
            <template v-else>
                <div v-for="f in localFriends" :key="f.id" style="display:flex; align-items:center; gap:14px; padding:12px 0; border-bottom:1px solid var(--ma-border);">
                    <Link :href="`${memberBase}/perfil/${f.id}`"
                        style="width:44px; height:44px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:16px; font-weight:700; flex-shrink:0; text-decoration:none;"
                        :style="{ background: primaryColor, color: '#0D1F2D' }">
                        <img v-if="f.avatar_url" :src="f.avatar_url" style="width:100%;height:100%;border-radius:50%;object-fit:cover;" />
                        <span v-else>{{ getInitials(f.name) }}</span>
                    </Link>
                    <Link :href="`${memberBase}/perfil/${f.id}`"
                        style="flex:1; font-size:15px; color:var(--ma-text); font-family:'DM Sans',sans-serif; text-decoration:none;">
                        {{ f.name }}
                    </Link>
                    <!-- Botão desamigar apenas no próprio perfil -->
                    <button v-if="is_own" type="button"
                        style="padding:6px 14px; border-radius:20px; border:1px solid rgba(224,85,85,0.4); background:transparent; cursor:pointer; font-size:12px; font-weight:600; color:#E05555; font-family:'DM Sans',sans-serif; transition:background 0.15s;"
                        @mouseenter="$event.currentTarget.style.background='rgba(224,85,85,0.1)'"
                        @mouseleave="$event.currentTarget.style.background='transparent'"
                        @click="unfriendFromList(f.id)">
                        Desamigar
                    </button>
                </div>
            </template>
        </div>
    </div>
</template>
