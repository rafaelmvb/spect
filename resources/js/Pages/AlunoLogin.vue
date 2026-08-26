<script setup>
import { ref } from 'vue';
import { useForm, Head } from '@inertiajs/vue3';
import { Eye, EyeOff } from 'lucide-vue-next';

const showPw = ref(false);

const form = useForm({
    email:    '',
    password: '',
    remember: false,
});

function submit() {
    form.post('/entrar', { preserveScroll: true });
}
</script>

<template>
    <Head>
        <title>Entrar — Rastreio Spectra</title>
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
        <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet" />
    </Head>

    <div class="al-root">

        <!-- Glow central muito sutil -->
        <div class="al-glow" aria-hidden="true" />

        <!-- Logo topo-esquerda -->
        <header class="al-header">
            <span class="al-logo">Rastreio Spectra</span>
        </header>

        <!-- Formulário centralizado -->
        <main class="al-main">
            <div class="al-box">

                <h1 class="al-title">Acesse sua conta</h1>
                <p class="al-sub">Bem-vindo de volta.</p>

                <!-- Erro geral -->
                <div v-if="$page.props.flash?.error" class="al-alert">
                    {{ $page.props.flash.error }}
                </div>

                <form @submit.prevent="submit" class="al-form">

                    <!-- E-mail -->
                    <div class="lf-field">
                        <input
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
                    <div class="lf-field">
                        <div class="lf-input-wrap">
                            <input
                                v-model="form.password"
                                :type="showPw ? 'text' : 'password'"
                                autocomplete="current-password"
                                required
                                placeholder="Senha"
                                :class="['lf-input', form.errors.password ? 'lf-input--error' : '']"
                            />
                            <button type="button" class="lf-eye" :aria-label="showPw ? 'Ocultar senha' : 'Mostrar senha'" @click="showPw = !showPw">
                                <Eye v-if="showPw" />
                                <EyeOff v-else />
                            </button>
                        </div>
                        <p v-if="form.errors.password" class="lf-error">{{ form.errors.password }}</p>
                    </div>

                    <!-- Botão -->
                    <button type="submit" :disabled="form.processing" class="lf-btn">
                        <span v-if="form.processing" class="lf-btn__spin">
                            <svg viewBox="0 0 24 24" fill="none">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                            </svg>
                            Entrando…
                        </span>
                        <span v-else>Entrar</span>
                    </button>

                    <!-- Esqueci a senha -->
                    <div class="lf-extras">
                        <a href="/esqueci-senha" class="lf-link">Esqueceu a senha?</a>
                    </div>

                </form>
            </div>
        </main>

        <!-- Rodapé -->
        <footer class="al-footer">
            &copy; {{ new Date().getFullYear() }} Rastreio Spectra. Todos os direitos reservados.
        </footer>
    </div>
</template>

<style scoped>
.al-root {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    background: #060D14;
    font-family: 'Cormorant Garamond', Georgia, serif;
    color: #fff;
    position: relative;
    overflow: hidden;
}

.al-glow {
    position: fixed;
    inset: 0;
    z-index: 0;
    pointer-events: none;
    background: radial-gradient(ellipse 60% 50% at 50% 40%, rgba(111,207,0,0.07) 0%, transparent 70%);
}

/* Header */
.al-header {
    position: relative;
    z-index: 10;
    padding: 28px 40px;
}
@media (max-width: 600px) {
    .al-header { padding: 20px 24px; }
}

.al-logo {
    font-family: 'Cormorant Garamond', Georgia, serif;
    font-weight: 600;
    font-size: 22px;
    font-style: italic;
    letter-spacing: 0.04em;
    color: #fff;
}

/* Main */
.al-main {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px 24px 48px;
    position: relative;
    z-index: 10;
}

.al-box {
    width: 100%;
    max-width: 420px;
}

.al-title {
    font-family: 'Cormorant Garamond', Georgia, serif;
    font-size: clamp(30px, 5vw, 42px);
    font-weight: 600;
    color: #fff;
    margin: 0 0 8px;
    line-height: 1.1;
}
.al-sub {
    font-size: 18px;
    font-weight: 400;
    color: rgba(255,255,255,0.45);
    margin: 0 0 32px;
}

.al-alert {
    margin-bottom: 20px;
    padding: 14px 18px;
    border-radius: 6px;
    border: 1px solid rgba(224,85,85,0.3);
    background: rgba(224,85,85,0.07);
    color: #e05555;
    font-size: 15px;
    line-height: 1.5;
}

/* Form */
.al-form {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.lf-field { display: flex; flex-direction: column; }
.lf-input-wrap { position: relative; }

.lf-input {
    width: 100%;
    box-sizing: border-box;
    padding: 20px 22px;
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 6px;
    color: #fff;
    font-family: 'Cormorant Garamond', Georgia, serif;
    font-size: 18px;
    font-weight: 400;
    outline: none;
    transition: border-color 0.2s, background 0.2s;
}
.lf-input::placeholder { color: rgba(255,255,255,0.3); }
.lf-input:focus {
    border-color: #6FCF00;
    background: rgba(255,255,255,0.09);
}
.lf-input--error { border-color: #e05555; }
.lf-input-wrap .lf-input { padding-right: 54px; }

.lf-eye {
    position: absolute;
    right: 16px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: rgba(255,255,255,0.35);
    cursor: pointer;
    display: flex;
    align-items: center;
    padding: 4px;
    transition: color 0.15s;
}
.lf-eye:hover { color: rgba(255,255,255,0.75); }
.lf-eye svg  { width: 18px; height: 18px; }

.lf-error {
    margin: 6px 0 0;
    font-size: 14px;
    color: #e05555;
}

.lf-btn {
    width: 100%;
    padding: 20px;
    background: #6FCF00;
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
.lf-btn:hover:not(:disabled) { opacity: 0.88; transform: translateY(-1px); }
.lf-btn:active:not(:disabled) { transform: translateY(0); }
.lf-btn:disabled { opacity: 0.45; cursor: not-allowed; }

.lf-btn__spin {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}
.lf-btn__spin svg { width: 18px; height: 18px; animation: spin 0.9s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

.lf-extras { display: flex; justify-content: center; margin-top: 2px; }
.lf-link {
    font-size: 15px;
    color: rgba(255,255,255,0.4);
    text-decoration: underline;
    text-underline-offset: 3px;
    transition: color 0.15s;
}
.lf-link:hover { color: rgba(255,255,255,0.75); }

/* Footer */
.al-footer {
    position: relative;
    z-index: 10;
    text-align: center;
    padding: 18px 24px;
    font-size: 13px;
    color: rgba(255,255,255,0.18);
    font-style: italic;
}
</style>
