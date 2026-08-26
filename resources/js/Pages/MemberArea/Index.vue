<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    produtos: { type: Array, default: () => [] },
});
</script>

<template>
    <!-- Layout próprio estilo wireframe — sem LayoutGuest -->
    <div style="min-height:100vh; background:#0D1F2D; font-family:'DM Sans',-apple-system,sans-serif; color:#fff;">

        <!-- Google Fonts -->
        <component :is="'link'" rel="preconnect" href="https://fonts.googleapis.com" />
        <component :is="'link'" rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
        <component :is="'link'" href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet" />

        <!-- Header mínimo -->
        <div style="display:flex; align-items:center; justify-content:space-between; padding:56px 32px 0; max-width:480px; margin:0 auto;">
            <span style="font-family:'DM Sans',sans-serif; font-weight:800; font-size:18px; letter-spacing:3px; text-transform:uppercase;">SPECTRA</span>
            <Link href="/logout" method="post" as="button"
                style="background:none; border:none; cursor:pointer; font-size:14px; color:#7A9BAD; font-family:'DM Sans',sans-serif; text-decoration:none;">
                Sair
            </Link>
        </div>

        <!-- Conteúdo principal -->
        <div style="max-width:480px; margin:0 auto; padding:40px 32px 80px;">
            <p style="font-family:'DM Mono',monospace; font-size:11px; text-transform:uppercase; letter-spacing:1.5px; color:#7A9BAD;">suas trilhas</p>
            <h1 style="font-family:'DM Sans',sans-serif; font-weight:800; font-size:28px; margin-top:8px; color:#fff;">Área de Membros</h1>
            <p style="font-size:14px; color:#7A9BAD; margin-top:6px;">Escolha uma trilha para continuar.</p>

            <!-- Lista de produtos -->
            <div style="display:flex; flex-direction:column; gap:12px; margin-top:32px;">
                <div v-for="p in produtos" :key="p.id"
                    style="background:#132435; border:1px solid #1E3448; border-radius:14px; overflow:hidden; transition:border-color 0.2s;"
                    @mouseenter="$event.currentTarget.style.borderColor='#6FCF00'"
                    @mouseleave="$event.currentTarget.style.borderColor='#1E3448'">

                    <!-- Imagem de capa -->
                    <div v-if="p.image_url" style="height:140px; overflow:hidden;">
                        <img :src="p.image_url" :alt="p.name" style="width:100%; height:100%; object-fit:cover;" />
                    </div>
                    <!-- Placeholder se não tiver imagem -->
                    <div v-else style="height:100px; background:linear-gradient(135deg, #132435 0%, #1A3A2A 50%, #132435 100%); display:flex; align-items:center; justify-content:center;">
                        <div style="width:60px; height:60px; border-radius:50%; border:1px solid rgba(111,207,0,0.2); display:flex; align-items:center; justify-content:center;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="rgba(111,207,0,0.5)" stroke-width="1.5">
                                <polygon points="5 3 19 12 5 21 5 3"/>
                            </svg>
                        </div>
                    </div>

                    <div style="padding:20px;">
                        <h2 style="font-family:'DM Sans',sans-serif; font-size:18px; font-weight:700; color:#fff;">{{ p.name }}</h2>
                        <p style="font-family:'DM Mono',monospace; font-size:11px; color:#7A9BAD; margin-top:4px; text-transform:uppercase; letter-spacing:1px;">{{ p.type === 'area_membros' ? 'Área de membros' : p.type }}</p>

                        <a v-if="p.member_area_url" :href="p.member_area_url"
                            style="display:inline-flex; align-items:center; gap:8px; margin-top:18px; padding:14px 24px; background:#6FCF00; color:#0D1F2D; border-radius:12px; font-family:'DM Sans',sans-serif; font-size:14px; font-weight:600; text-decoration:none; transition:background 0.2s; border:none; cursor:pointer;"
                            @mouseenter="$event.currentTarget.style.background='#62B800'"
                            @mouseleave="$event.currentTarget.style.background='#6FCF00'">
                            Acessar área de membros →
                        </a>
                    </div>
                </div>

                <!-- Sem produtos -->
                <div v-if="!produtos.length" style="text-align:center; padding:60px 24px; color:#7A9BAD;">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#1E3448" stroke-width="1.5" style="margin:0 auto 16px; display:block;"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg>
                    <p style="font-family:'DM Sans',sans-serif; font-size:15px;">Você ainda não tem acesso a nenhuma trilha.</p>
                </div>
            </div>
        </div>
    </div>
</template>
