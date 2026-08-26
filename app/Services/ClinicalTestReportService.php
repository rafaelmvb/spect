<?php

namespace App\Services;

use App\Models\AiConfig;
use App\Models\AiInsight;
use App\Models\ClinicalTest;
use App\Models\ClinicalTestSession;
use App\Models\Setting;
use App\Models\User;

class ClinicalTestReportService
{
    public function generate(ClinicalTestSession $session, ClinicalTest $test, User $student, int $tenantId): ?AiInsight
    {
        $aiService = new AiService($tenantId);
        if (! $aiService->available()) {
            return null;
        }

        $aiContext    = $test->ai_context ?? [];
        $instructions = trim($aiContext['instructions'] ?? '');

        // Monta texto das perguntas + respostas
        $questionsMap = $test->questions->keyBy('id');
        $answersText  = '';
        foreach ($session->answers as $ans) {
            $q = $questionsMap->get($ans->clinical_test_question_id);
            if (! $q) continue;

            $answersText .= "\n{$q->text}\n";
            $answersText .= '  Resposta: ' . $this->formatAnswer($ans->answer, $q) . "\n";
        }

        $userMessage = "Gere o relatório diretamente para o aluno (use 'você'):\n\n"
            . "TESTE: {$test->name}\n"
            . "CATEGORIA: {$test->category}\n"
            . ($test->description ? "DESCRIÇÃO: {$test->description}\n" : '')
            . "\nPERGUNTAS E RESPOSTAS:{$answersText}"
            . "\nRESULTADO:\n"
            . "  Pontuação total: {$session->score}\n"
            . "  Classificação: {$session->result_label}\n"
            . (! empty($session->challenge_tags) ? '  Tags de desafio: ' . implode(', ', $session->challenge_tags) . "\n" : '');

        $defaultSystemPrompt = <<<'PROMPT'
Você é o sistema de análise clínica da plataforma Spectra Health, especializado em gerar relatórios interpretativos personalizados a partir de testes de rastreio.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
MISSÃO
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Gerar um relatório interpretativo e personalizado baseado nas respostas do aluno ao teste clínico fornecido.

O relatório é uma LEITURA DE FUNCIONAMENTO — nunca um fechamento diagnóstico. Não substitui avaliação profissional especializada.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
ESTRUTURA OBRIGATÓRIA DO RELATÓRIO
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

**O QUE APARECEU**
Descreva o que as respostas mostram: padrões, comportamentos recorrentes, domínios salientes. Seja específico e baseado nos dados fornecidos. Não generalize.

**O QUE ISSO PODE SIGNIFICAR**
Traduza em linguagem de funcionamento humano. Explique como esses padrões se organizam na vida real: o que facilita, o que bloqueia.

**O QUE ISSO AINDA NÃO SIGNIFICA**
Proteja contra leitura diagnóstica fechada. Este relatório NÃO substitui avaliação profissional, NÃO fecha hipótese clínica, NÃO é laudo. Seja claro sobre os limites da análise.

**RECOMENDAÇÕES E PRÓXIMOS PASSOS**
Ofereça recomendações práticas baseadas nos padrões identificados. Inclua: áreas prioritárias de atenção, recursos internos observados (pontos fortes), e sugestões de aprofundamento ou acompanhamento.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
REGRAS DE LINGUAGEM — OBRIGATÓRIAS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
NUNCA USE:
- "você tem [condição]" / "isso confirma" / "é diagnóstico de"
- Linguagem diagnóstica fechada: "tem TDAH", "é autista"
- Linguagem autoajuda rasa ou paternalista
- Frases genéricas que servem para qualquer pessoa

SEMPRE USE:
- "o que apareceu nas suas respostas sugere" / "há um padrão de"
- Tom: inteligente · acolhedor sem excesso afetivo · profissional · claro
- Para negrito use **texto** (markdown) — NUNCA tags HTML
- O relatório é escrito DIRETAMENTE PARA O ALUNO — use sempre "você"

Extensão: 300 a 600 palavras, divididas nos 4 blocos obrigatórios.
PROMPT;

        $config = AiConfig::where('tenant_id', $tenantId)
            ->where('context', 'clinical_test_report')
            ->where('is_active', true)
            ->first()
            ?? new AiConfig([
                'context'       => 'clinical_test_report',
                'system_prompt' => $defaultSystemPrompt,
                'model'         => 'claude-haiku-4-5-20251001',
                'temperature'   => '0.7',
                'max_tokens'    => 2000,
                'is_active'     => true,
            ]);

        if (! $config->system_prompt) {
            $config->system_prompt = $defaultSystemPrompt;
        }

        // Prompts globais do tenant (tipo report)
        $rawCustom   = Setting::get('custom_ai_prompts', '[]', $tenantId);
        $customList  = json_decode($rawCustom, true) ?? [];
        $finalSystem = $config->system_prompt;
        foreach (array_filter($customList, fn ($p) => ($p['type'] ?? '') === 'report') as $extra) {
            if (! empty($extra['content'])) {
                $finalSystem .= "\n\n" . $extra['content'];
            }
        }

        // Instruções específicas do teste (configuradas pelo admin)
        if ($instructions) {
            $finalSystem .= "\n\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
                . "CONTEXTO E INSTRUÇÕES ESPECÍFICAS DESTE TESTE\n"
                . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
                . $instructions;
        }

        $content = $aiService->complete(
            [
                ['role' => 'system', 'content' => $finalSystem],
                ['role' => 'user',   'content' => $userMessage],
            ],
            $config,
            $tenantId,
            $student->id,
        );

        return AiInsight::create([
            'tenant_id' => $tenantId,
            'user_id'   => $student->id,
            'type'      => 'clinical_test_report',
            'title'     => "Relatório — {$test->name} — " . now()->format('d/m/Y'),
            'content'   => $content,
            'metadata'  => ['session_id' => $session->id, 'test_id' => $test->id],
        ]);
    }

    private function formatAnswer(mixed $answer, $question): string
    {
        $vals = is_array($answer) ? $answer : [$answer];

        if ($question->type === 'boolean') {
            return ($vals[0] ?? 0) ? 'Sim' : 'Não';
        }

        if ($question->type === 'scale') {
            $v = $vals[0] ?? '?';
            return "{$v} / {$question->scale_max}";
        }

        if ($question->type === 'text') {
            return is_array($answer) ? ($answer[0] ?? '') : (string) $answer;
        }

        if (in_array($question->type, ['single', 'multi'])) {
            $optionIds = $vals;
            $texts = $question->options
                ->whereIn('id', $optionIds)
                ->pluck('text')
                ->implode('; ');
            return $texts ?: implode(', ', $optionIds);
        }

        return implode(', ', $vals);
    }
}
