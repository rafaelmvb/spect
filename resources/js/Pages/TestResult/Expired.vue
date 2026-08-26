<script setup>
import { Link2Off } from 'lucide-vue-next';
</script>

<template>
    <div style="min-height:100vh; background:#0f172a; display:flex; flex-direction:column; align-items:center; justify-content:center; padding:24px; text-align:center;">
        <Link2Off style="width:48px; height:48px; color:#475569; margin-bottom:16px;" />
        <h1 style="font-size:22px; font-weight:800; color:#f8fafc; margin-bottom:8px;">Link expirado</h1>
        <p style="font-size:14px; color:#94a3b8; max-width:300px; line-height:1.6;">
            Este link de compartilhamento não está mais disponível.<br>
            Links de resultado são válidos por 7 dias.
        </p>
    </div>
</template>
