<script setup>
import { ref, computed } from 'vue';
import { useForm, Head } from '@inertiajs/vue3';
import { Eye, EyeOff } from 'lucide-vue-next';
import PwaInstallPrompt from '@/components/member-area/PwaInstallPrompt.vue';

const props = defineProps({
    slug:    { type: String, required: true },
    product: { type: Object, required: true },
});

const showPassword = ref(false);

const manifestUrl = computed(() => {
    if (typeof window === 'undefined') return null;
    return `${window.location.origin}/m/${props.slug}/manifest.json`;
});

const primary = computed(() => props.product.primary_color || '#6FCF00');

const form = useForm({
    email:    '',
    password: '',
    remember: false,
});

function submit() {
    if (props.product.login_without_password) {
        form.post(props.product.login_without_password_url || `/m/${props.slug}/login-without-password`);
    } else {
        form.post(`/m/${props.slug}/login`);
    }
}
</script>

<template>
    <Head>
        <title>Entrar — {{ product.title || product.name || 'Área de Membros' }}</title>
        <link v-if="manifestUrl" rel="manifest" :href="manifestUrl" />
        <meta name="theme-color" content="#060D14" />
        <meta name="mobile-web-app-capable" content="yes" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
        <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet" />
    </Head>

    <div class="login-root" :style="{ '--primary': primary }">

        <!-- Glow central muito sutil -->
        <div class="login-glow" aria-hidden="true" :style="{ background: `radial-gradient(ellipse 60% 50% at 50% 40%, ${primary}12 0%, transparent 70%)` }" />

        <!-- Logo topo-esquerda -->
        <header class="login-header">
            <a href="#" class="login-logo">
                <img v-if="product.logo_light || product.logo_dark"
                    :src="product.logo_light || product.logo_dark"
                    :alt="product.name"
                    class="login-logo__img" />
                <span v-else class="login-logo__text">{{ product.name }}</span>
            </a>
        </header>

        <!-- Formulário centralizado -->
        <main class="login-main">
            <div class="login-box">
                <h1 class="login-title">Acesse sua conta</h1>
                <p class="login-sub">
                    {{ product.login_without_password
                        ? 'Digite seu e-mail para receber o link de acesso.'
                        : 'Bem-vindo de volta.' }}
                </p>

                <form @submit.prevent="submit" class="login-form">

                    <!-- E-mail -->
                    <div class="lf-field">
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            autocomplete="email"
                            required
                            autofocus
                            placeholder="E-mail"
                            :class="['lf-input', form.errors.email ? 'lf-input--error' : '']"
                        />
                        <p v-if="form.errors.email" class="lf-error">{{ form.errors.email }}</p>
                    </div>

                    <!-- Senha -->
                    <div v-if="!product.login_without_password" class="lf-field">
                        <div class="lf-input-wrap">
                            <input
                                id="password"
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                autocomplete="current-password"
                                required
                                placeholder="Senha"
                                :class="['lf-input', form.errors.password ? 'lf-input--error' : '']"
                            />
                            <button type="button" class="lf-eye" :aria-label="showPassword ? 'Ocultar senha' : 'Mostrar senha'" @click="showPassword = !showPassword">
                                <Eye v-if="showPassword" />
                                <EyeOff v-else />
                            </button>
                        </div>
                        <p v-if="form.errors.password" class="lf-error">{{ form.errors.password }}</p>
                    </div>

                    <!-- Botão principal -->
                    <button type="submit" :disabled="form.processing" class="lf-btn">
                        <span v-if="form.processing" class="lf-btn__spin">
                            <svg viewBox="0 0 24 24" fill="none">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                            </svg>
                            Entrando…
                        </span>
                        <span v-else>{{ product.login_without_password ? 'Enviar link de acesso' : 'Entrar' }}</span>
                    </button>

                    <!-- Esqueci a senha -->
                    <div v-if="!product.login_without_password" class="lf-extras">
                        <a :href="`/m/${slug}/esqueci-senha`" class="lf-link">Esqueceu a senha?</a>
                    </div>

                </form>

                <!-- Erro de sessão -->
                <div v-if="$page.props.flash?.error" class="lf-alert">
                    {{ $page.props.flash.error }}
                </div>
            </div>
        </main>

        <!-- Rodapé -->
        <footer class="login-footer">
            &copy; {{ new Date().getFullYear() }} {{ product.name }}. Todos os direitos reservados.
        </footer>
    </div>

    <PwaInstallPrompt :app-name="product?.name || product?.title || 'App'" :slug="slug" />
</template>

<style scoped>
/* ── Root ── */
.login-root {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    background: #060D14;
    font-family: 'Cormorant Garamond', Georgia, serif;
    color: #fff;
    position: relative;
    overflow: hidden;
}

/* Glow background */
.login-glow {
    position: fixed;
    inset: 0;
    z-index: 0;
    pointer-events: none;
}

/* ── Header ── */
.login-header {
    position: relative;
    z-index: 10;
    padding: 28px 40px;
}
@media (max-width: 600px) {
    .login-header { padding: 20px 24px; }
}

.login-logo {
    text-decoration: none;
    display: inline-flex;
    align-items: center;
}
.login-logo__img {
    height: 38px;
    width: auto;
    max-width: 180px;
    object-fit: contain;
}
.login-logo__text {
    font-family: 'Cormorant Garamond', Georgia, serif;
    font-weight: 700;
    font-size: 24px;
    letter-spacing: 0.06em;
    color: #fff;
}

/* ── Main ── */
.login-main {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px 24px 48px;
    position: relative;
    z-index: 10;
}

.login-box {
    width: 100%;
    max-width: 420px;
}

.login-title {
    font-family: 'Cormorant Garamond', Georgia, serif;
    font-size: clamp(30px, 5vw, 42px);
    font-weight: 600;
    color: #fff;
    margin: 0 0 8px;
    line-height: 1.1;
}
.login-sub {
    font-size: 18px;
    font-weight: 400;
    color: rgba(255, 255, 255, 0.45);
    margin: 0 0 32px;
    line-height: 1.5;
}

/* ── Form ── */
.login-form {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.lf-field {
    display: flex;
    flex-direction: column;
}

.lf-input-wrap {
    position: relative;
}

.lf-input {
    width: 100%;
    box-sizing: border-box;
    padding: 20px 22px;
    background: rgba(255, 255, 255, 0.06);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 6px;
    color: #fff;
    font-family: 'Cormorant Garamond', Georgia, serif;
    font-size: 18px;
    font-weight: 400;
    outline: none;
    transition: border-color 0.2s, background 0.2s;
}
.lf-input::placeholder {
    color: rgba(255, 255, 255, 0.3);
}
.lf-input:focus {
    border-color: var(--primary);
    background: rgba(255, 255, 255, 0.09);
}
.lf-input--error {
    border-color: #e05555;
}

.lf-input-wrap .lf-input {
    padding-right: 54px;
}

.lf-eye {
    position: absolute;
    right: 16px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: rgba(255, 255, 255, 0.35);
    cursor: pointer;
    display: flex;
    align-items: center;
    padding: 4px;
    transition: color 0.15s;
}
.lf-eye:hover { color: rgba(255, 255, 255, 0.75); }
.lf-eye svg  { width: 18px; height: 18px; }

.lf-error {
    margin: 6px 0 0;
    font-size: 14px;
    color: #e05555;
}

/* Botão principal */
.lf-btn {
    width: 100%;
    padding: 20px;
    background: var(--primary);
    border: none;
    border-radius: 6px;
    color: #060D14;
    font-family: 'Cormorant Garamond', Georgia, serif;
    font-size: 20px;
    font-weight: 700;
    letter-spacing: 0.04em;
    cursor: pointer;
    transition: opacity 0.15s, transform 0.1s;
    margin-top: 4px;
}
.lf-btn:hover:not(:disabled) {
    opacity: 0.88;
    transform: translateY(-1px);
}
.lf-btn:active:not(:disabled) { transform: translateY(0); }
.lf-btn:disabled { opacity: 0.45; cursor: not-allowed; }

.lf-btn__spin {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}
.lf-btn__spin svg {
    width: 18px;
    height: 18px;
    animation: spin 0.9s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* Esqueci / extras */
.lf-extras {
    display: flex;
    justify-content: center;
    margin-top: 2px;
}
.lf-link {
    font-size: 15px;
    color: rgba(255, 255, 255, 0.4);
    text-decoration: underline;
    text-underline-offset: 3px;
    transition: color 0.15s;
}
.lf-link:hover { color: rgba(255, 255, 255, 0.75); }

/* Alerta de erro */
.lf-alert {
    margin-top: 18px;
    padding: 14px 18px;
    border-radius: 6px;
    border: 1px solid rgba(224, 85, 85, 0.3);
    background: rgba(224, 85, 85, 0.07);
    color: #e05555;
    font-size: 15px;
    line-height: 1.5;
}

/* ── Footer ── */
.login-footer {
    position: relative;
    z-index: 10;
    text-align: center;
    padding: 18px 24px;
    font-size: 13px;
    color: rgba(255, 255, 255, 0.18);
    font-family: 'Cormorant Garamond', Georgia, serif;
}
</style>
