<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Relatório — {{ $aluno->name }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400;1,600;1,700&family=Inter:wght@400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
@page { margin: 2.5cm 2cm; size: A4 }
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0 }
body {
    font-family: "Inter", -apple-system, sans-serif;
    font-size: 11pt;
    line-height: 1.75;
    color: #1C1C1A;
    background: #fff;
    max-width: 680px;
    margin: 0 auto;
    padding: 48px 32px;
}

/* ─── CAPA ─────────────────────────────────────────────────────────────────── */
.cover {
    min-height: 88vh;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    text-align: center;
    page-break-after: always;
}
.cover-rule { width: 48px; border: none; border-top: 2px solid #999; margin-bottom: 32px }
.cover-label {
    font-family: "DM Mono", monospace;
    font-size: 9pt;
    letter-spacing: .3em;
    text-transform: uppercase;
    color: #999;
    margin-bottom: 24px;
}
.cover h1 {
    font-family: "Cormorant Garamond", Georgia, serif;
    font-size: 36pt;
    font-weight: 400;
    margin-bottom: 12px;
    line-height: 1.2;
}
.cover .sub {
    font-family: "Cormorant Garamond", Georgia, serif;
    font-style: italic;
    font-size: 13pt;
    color: #777;
    margin-bottom: 48px;
    line-height: 1.6;
    max-width: 460px;
}
.cover-rule-bottom { width: 48px; border: none; border-top: 1px solid #ccc; margin-bottom: 20px }
.cover .meta {
    font-family: "DM Mono", monospace;
    font-size: 9pt;
    color: #999;
    line-height: 2;
}

/* ─── CORPO ─────────────────────────────────────────────────────────────────── */
.sn {
    font-family: "DM Mono", monospace;
    font-size: 8pt;
    letter-spacing: .3em;
    text-transform: uppercase;
    color: #999;
    margin-top: 48px;
    margin-bottom: 4px;
    display: block;
}
h2 {
    font-family: "DM Mono", monospace;
    font-size: 9pt;
    font-weight: 500;
    letter-spacing: .25em;
    text-transform: uppercase;
    border-bottom: 1px solid #E5E4E1;
    padding-bottom: 8px;
    margin-bottom: 20px;
    margin-top: 4px;
}
h3 {
    font-family: "Cormorant Garamond", Georgia, serif;
    font-style: italic;
    font-size: 13pt;
    font-weight: 600;
    margin-top: 28px;
    margin-bottom: 10px;
}
p { margin-bottom: 14px }

.id-table { width: 100%; border-collapse: collapse; margin: 16px 0 24px; font-size: 10.5pt }
.id-table td { padding: 8px 12px; border-bottom: 1px solid #E5E4E1; vertical-align: top }
.id-table td:first-child {
    font-family: "DM Mono", monospace;
    font-size: 8pt;
    letter-spacing: .1em;
    text-transform: uppercase;
    color: #999;
    width: 160px;
    font-weight: 500;
    white-space: nowrap;
}
.id-table tr:last-child td { border-bottom: none }

.content-block { margin-top: 32px }
.content-block p { margin-bottom: 14px; text-align: justify }

blockquote {
    border-left: 3px solid #B8896A;
    padding: 10px 20px;
    margin: 20px 0;
    font-family: "Cormorant Garamond", Georgia, serif;
    font-style: italic;
    font-size: 12pt;
    color: #4a4a46;
    line-height: 1.6;
}

/* ─── RODAPÉ / ASSINATURA ────────────────────────────────────────────────────── */
.disclaimer {
    margin-top: 40px;
    padding: 16px 20px;
    background: #FAFAF8;
    border: 1px solid #E5E4E1;
    font-size: 8.5pt;
    color: #999;
    line-height: 1.6;
}
.signature {
    text-align: center;
    margin-top: 60px;
    padding-top: 20px;
}
.signature hr { width: 180px; border: none; border-top: 1px solid #999; margin: 0 auto 16px }
.signature .sig-name { font-family: "Cormorant Garamond", Georgia, serif; font-size: 14pt; font-weight: 600 }
.signature .sig-sub { font-family: "DM Mono", monospace; font-size: 8pt; letter-spacing: .2em; color: #999; margin-top: 4px }
.signature .sig-spec { font-family: "Cormorant Garamond", Georgia, serif; font-style: italic; font-size: 10pt; color: #777; margin-top: 4px }
.signature .sig-date { font-family: "DM Mono", monospace; font-size: 9pt; color: #999; margin-top: 16px }

/* ─── PRINT ──────────────────────────────────────────────────────────────────── */
@media print {
    body { padding: 0; max-width: none }
    .cover { min-height: 100vh }
    .no-print { display: none !important }
}
@media screen {
    .print-hint {
        position: fixed;
        bottom: 24px;
        right: 24px;
        background: #1C1C1A;
        color: #fff;
        font-family: "Inter", sans-serif;
        font-size: 13px;
        padding: 12px 20px;
        border-radius: 10px;
        cursor: pointer;
        z-index: 9999;
        box-shadow: 0 4px 20px rgba(0,0,0,.3);
    }
}
</style>
</head>
<body>

<!-- ─── CAPA ──────────────────────────────────────────────────────────────── -->
<div class="cover">
    <hr class="cover-rule">
    <p class="cover-label">Relatório Neurofuncional Avançado</p>
    <h1>{{ $aluno->name }}</h1>
    <p class="sub">
        Análise de indicadores neurofuncionais gerada via Plataforma Spectra<br>
        a partir de respostas de quizzes e checkpoints
    </p>
    <hr class="cover-rule-bottom">
    <p class="meta">
        {{ $insight->created_at->format('d \d\e F \d\e Y') }}<br>
        Spectra · Plataforma de Rastreio Neurofuncional Progressivo
    </p>
</div>

<!-- ─── IDENTIFICAÇÃO ─────────────────────────────────────────────────────── -->
<span class="sn">01</span>
<h2>Identificação</h2>
<table class="id-table">
    <tr><td>Nome</td><td>{{ $aluno->name }}</td></tr>
    <tr><td>E-mail</td><td>{{ $aluno->email }}</td></tr>
    <tr><td>Plataforma</td><td>Spectra App</td></tr>
    <tr><td>Gerado em</td><td>{{ $insight->created_at->format('d/m/Y \à\s H:i') }}</td></tr>
    @if(!empty($products) && count($products))
    <tr>
        <td>Trilhas</td>
        <td>{{ implode(', ', array_column($products, 'name')) }}</td>
    </tr>
    @endif
</table>

<!-- ─── NATUREZA ──────────────────────────────────────────────────────────── -->
<span class="sn">02</span>
<h2>Natureza deste Documento</h2>
<p>Este relatório apresenta uma análise dos dados obtidos através dos instrumentos respondidos por <strong>{{ $aluno->name }}</strong> na plataforma Spectra. Os resultados são indicativos e não substituem avaliação diagnóstica completa conduzida por profissional qualificado. Este documento visa oferecer hipóteses clínicas sobre processos subjacentes e sugerir pistas para exploração clínica e pessoal.</p>

<!-- ─── ANÁLISE DA IA ──────────────────────────────────────────────────────── -->
<span class="sn">03</span>
<h2>Análise Neurofuncional</h2>
<div class="content-block">
@php
    $lines = explode("\n", $insight->content);
    $buffer = '';
    $inSection = false;

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '') {
            if ($buffer !== '') {
                echo '<p>' . nl2br($buffer) . '</p>';
                $buffer = '';
            }
            continue;
        }
        // Detect section headers: lines that are mostly uppercase or start with BLOCO/━
        $isHeader = (
            preg_match('/^(BLOCO|━|#{1,3}|\*{2}[A-ZÁÉÍÓÚÂÊÎÔÛÃÕÇ\s]{5,}\*{2})/u', $trimmed)
            || (mb_strtoupper($trimmed) === $trimmed && mb_strlen($trimmed) > 5 && mb_strlen($trimmed) < 80 && !preg_match('/[.,;:!?]$/', $trimmed))
        );

        if ($isHeader) {
            if ($buffer !== '') {
                echo '<p>' . nl2br($buffer) . '</p>';
                $buffer = '';
            }
            // Clean markdown bold markers
            $heading = preg_replace('/\*{1,2}/', '', $trimmed);
            $heading = preg_replace('/^━+\s*/', '', $heading);
            echo '<h3>' . htmlspecialchars($heading) . '</h3>';
        } else {
            // Converte markdown **text** → <strong> e HTML <strong> já existente passa direto
            $formatted = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $trimmed);
            $buffer .= ($buffer !== '' ? ' ' : '') . $formatted;
        }
    }
    if ($buffer !== '') {
        echo '<p>' . nl2br($buffer) . '</p>';
    }
@endphp
</div>

<!-- ─── ASSINATURA ────────────────────────────────────────────────────────── -->
<div class="signature">
    <hr>
    <p class="sig-name">Spectra App</p>
    <p class="sig-sub">Plataforma de rastreio neurofuncional progressivo</p>
    <p class="sig-spec">
        Análise gerada com suporte de inteligência artificial<br>
        baseada na metodologia Spectra · Prisma
    </p>
    <p class="sig-date">{{ $insight->created_at->format('d \d\e F \d\e Y') }}</p>
</div>

<!-- ─── AVISO LEGAL ────────────────────────────────────────────────────────── -->
<div class="disclaimer" style="margin-top:40px">
    <strong>Aviso legal:</strong> Este documento compila indicadores obtidos via instrumentos de rastreio aplicados em plataforma digital. Não constitui diagnóstico psicológico, laudo pericial ou documento para fins jurídicos. Não substitui avaliação clínica presencial por profissional habilitado. Os termos "hipótese", "indicador" e "padrão observado" são utilizados deliberadamente em substituição a qualquer linguagem diagnóstica. Gerado pela plataforma Spectra com auxílio de inteligência artificial.
</div>

<div class="disclaimer" style="margin-top:16px">
    Este documento é sigiloso e de uso exclusivo do destinatário, conforme Resolução CFP nº 010/2005 (Código de Ética Profissional do Psicólogo) e Resolução CFP nº 06/2019 (Documentos Psicológicos). Sua reprodução, total ou parcial, sem autorização expressa do profissional responsável e do examinando, constitui infração ética e legal.
</div>

<!-- ─── BOTÃO FLUTUANTE (só na tela) ─────────────────────────────────────── -->
<button class="print-hint no-print" onclick="window.print()">
    ⬇ Salvar como PDF
</button>

<script>
// Auto-print após carregar as fontes
document.fonts.ready.then(() => setTimeout(() => window.print(), 600));
</script>
</body>
</html>
