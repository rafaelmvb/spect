import { onBeforeUnmount, ref } from 'vue';

/**
 * Envia eventos de comportamento para /m/telemetria.
 *
 * Nada aqui pode atrapalhar quem está estudando: o envio é disparado e
 * esquecido, falha em silêncio, e a saída da página usa sendBeacon — que o
 * navegador entrega mesmo depois de a aba fechar.
 */

const CHAVE_SESSAO = 'spectra_telemetria_sessao';

/** Token efêmero que agrupa os eventos de uma visita, sem identificar a pessoa. */
function tokenDaSessao() {
    try {
        let token = sessionStorage.getItem(CHAVE_SESSAO);
        if (!token) {
            const bytes = new Uint8Array(16);
            crypto.getRandomValues(bytes);
            token = Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('');
            sessionStorage.setItem(CHAVE_SESSAO, token);
        }
        return token;
    } catch {
        // Navegação privada ou storage bloqueado: segue sem agrupar.
        return null;
    }
}

export function useTelemetria(baseUrl) {
    const ultimoEnvio = ref(0);

    function url() {
        const base = typeof baseUrl === 'string' ? baseUrl : (baseUrl?.value ?? '');
        return `${base}/telemetria`;
    }

    function montarCorpo(evento, dados = {}) {
        const corpo = { event: evento, ...dados };
        const token = tokenDaSessao();
        if (token) corpo.session_token = token;
        return corpo;
    }

    /** Envio normal, durante a navegação. */
    function registrar(evento, dados = {}) {
        try {
            window.axios.post(url(), montarCorpo(evento, dados)).catch(() => {});
        } catch {
            // Telemetria nunca interrompe o uso.
        }
    }

    /**
     * Envio na saída da página. O axios seria cancelado junto com a aba;
     * sendBeacon é entregue pelo navegador depois.
     */
    function registrarNaSaida(evento, dados = {}) {
        const corpo = montarCorpo(evento, dados);
        try {
            const payload = new Blob([JSON.stringify(corpo)], { type: 'application/json' });
            if (navigator.sendBeacon && navigator.sendBeacon(url(), payload)) return;
        } catch {
            // cai para o axios abaixo
        }
        registrar(evento, dados);
    }

    /**
     * Para eventos contínuos como scroll: no máximo um envio a cada intervalo.
     */
    function registrarComIntervalo(evento, dados = {}, intervaloMs = 10000) {
        const agora = Date.now();
        if (agora - ultimoEnvio.value < intervaloMs) return;
        ultimoEnvio.value = agora;
        registrar(evento, dados);
    }

    return { registrar, registrarNaSaida, registrarComIntervalo };
}

/**
 * Liga a telemetria a um elemento <video> ou <audio>.
 *
 * Captura pausa, retomada, conclusão e — o dado que o escopo pede e que hoje
 * não existe — o segundo exato em que a pessoa abandonou o conteúdo.
 */
export function useTelemetriaDePlayer(baseUrl, { subjectType = 'member_lesson', subjectId } = {}) {
    const { registrar, registrarNaSaida } = useTelemetria(baseUrl);

    let elemento = null;
    let concluido = false;

    function posicao() {
        return Math.floor(elemento?.currentTime ?? 0);
    }

    function duracao() {
        const d = elemento?.duration;
        return Number.isFinite(d) ? Math.floor(d) : 0;
    }

    function alvo() {
        return { subject_type: subjectType, subject_id: String(subjectId ?? '') };
    }

    function aoPausar() {
        // Pausa no fim do vídeo é conclusão, não desistência.
        if (concluido || !elemento || elemento.ended) return;
        registrar('lesson.pause', { ...alvo(), position: posicao(), duration: duracao() });
    }

    function aoRetomar() {
        if (posicao() > 2) {
            registrar('lesson.resume', { ...alvo(), position: posicao(), duration: duracao() });
        }
    }

    function aoTerminar() {
        concluido = true;
        registrar('lesson.complete', { ...alvo(), position: posicao(), duration: duracao(), value: 100 });
    }

    function aoSair() {
        if (concluido || !elemento) return;
        const total = duracao();
        if (total <= 0) return;

        const percentual = Math.round((posicao() / total) * 100);
        registrarNaSaida('lesson.abandon', {
            ...alvo(),
            position: posicao(),
            duration: total,
            value: percentual,
        });
    }

    function conectar(el) {
        if (!el) return;
        elemento = el;
        elemento.addEventListener('pause', aoPausar);
        elemento.addEventListener('play', aoRetomar);
        elemento.addEventListener('ended', aoTerminar);
        window.addEventListener('pagehide', aoSair);
    }

    function desconectar() {
        aoSair();
        if (elemento) {
            elemento.removeEventListener('pause', aoPausar);
            elemento.removeEventListener('play', aoRetomar);
            elemento.removeEventListener('ended', aoTerminar);
        }
        window.removeEventListener('pagehide', aoSair);
        elemento = null;
    }

    onBeforeUnmount(desconectar);

    return { conectar, desconectar };
}
