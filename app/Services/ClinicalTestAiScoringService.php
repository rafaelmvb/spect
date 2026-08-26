<?php

namespace App\Services;

use App\Models\AiConfig;
use App\Models\ClinicalTest;
use App\Models\ClinicalTestSession;

class ClinicalTestAiScoringService
{
    /**
     * Avalia as respostas do aluno usando IA e retorna score, rótulo, descrição e tags.
     * Retorna null se a IA não estiver disponível ou falhar (use fallback por regras).
     *
     * @return array{score:int,result_label:string,result_description:string|null,challenge_tags:array}|null
     */
    public function evaluate(ClinicalTestSession $session, ClinicalTest $test, int $tenantId): ?array
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
            $answersText .= "\nPergunta: {$q->text}\n";
            $answersText .= 'Resposta: ' . $this->formatAnswer($ans->answer, $q) . "\n";
        }

        $systemPrompt = <<<'PROMPT'
Você é um sistema especializado em avaliar respostas de testes clínicos de rastreio neurofuncional.

Sua tarefa: analisar as respostas do aluno ao teste e retornar uma avaliação estruturada em JSON.

Campos obrigatórios no JSON:
- "result_label": string curta (máx 120 caracteres) com o resultado/classificação do aluno
- "result_description": string de 2 a 4 frases explicando o resultado diretamente para o aluno (use "você")
- "challenge_tags": array de strings com os principais desafios identificados (máx 6 tags, em minúsculas, sem acentos, ex: "foco", "impulsividade", "ansiedade")
- "score": número inteiro representando a pontuação avaliada (se o teste não tiver escala numérica clara, use 0)

Regras:
- Responda APENAS com o JSON, sem markdown, sem explicações fora do objeto.
- Não feche hipótese diagnóstica. Use linguagem de funcionamento ("padrão de", "tendência a"), nunca "tem TDAH", "é autista".
- Se o teste tiver regras de pontuação listadas abaixo, use-as como referência para o result_label.
PROMPT;

        // Acrescenta instruções específicas do teste
        if ($instructions) {
            $systemPrompt .= "\n\n━━━ INSTRUÇÕES ESPECÍFICAS DESTE TESTE ━━━\n{$instructions}";
        }

        // Acrescenta as regras de pontuação como referência (se houver)
        $rules = $test->scoringRules ?? collect();
        if ($rules->isNotEmpty()) {
            $rulesText = "\n\n━━━ REGRAS DE PONTUAÇÃO DO TESTE (use como referência para result_label) ━━━\n";
            foreach ($rules as $r) {
                $rulesText .= "Score {$r->min_score}–{$r->max_score}: {$r->result_label}\n";
            }
            $systemPrompt .= $rulesText;
        }

        $userMessage = "TESTE: {$test->name}\n"
            . "CATEGORIA: {$test->category}\n"
            . ($test->description ? "DESCRIÇÃO: {$test->description}\n" : '')
            . "\nRESPOSTAS DO ALUNO:{$answersText}"
            . "\nRetorne o JSON de avaliação.";

        $config = AiConfig::where('tenant_id', $tenantId)
            ->where('context', 'clinical_test_scoring')
            ->where('is_active', true)
            ->first()
            ?? new AiConfig([
                'context'     => 'clinical_test_scoring',
                'model'       => 'claude-haiku-4-5-20251001',
                'temperature' => '0.3',
                'max_tokens'  => 600,
                'is_active'   => true,
            ]);

        try {
            $raw = $aiService->complete(
                [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user',   'content' => $userMessage],
                ],
                $config,
                $tenantId,
                $session->user_id,
            );

            // Extrai JSON da resposta (pode vir com texto ao redor)
            $json = $this->extractJson($raw);
            if (! $json) {
                return null;
            }

            return [
                'score'              => (int) ($json['score'] ?? 0),
                'result_label'       => (string) ($json['result_label'] ?? 'Concluído'),
                'result_description' => isset($json['result_description']) ? (string) $json['result_description'] : null,
                'challenge_tags'     => array_values(array_filter((array) ($json['challenge_tags'] ?? []), 'is_string')),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    private function extractJson(string $text): ?array
    {
        // Tenta parsear diretamente
        $decoded = json_decode(trim($text), true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // Extrai primeiro bloco {...} se a IA adicionou texto ao redor
        if (preg_match('/\{[\s\S]*\}/u', $text, $m)) {
            $decoded = json_decode($m[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private function formatAnswer(mixed $answer, $question): string
    {
        $vals = is_array($answer) ? $answer : [$answer];

        if ($question->type === 'text') {
            return is_array($answer) ? ($answer[0] ?? '') : (string) $answer;
        }

        if ($question->type === 'boolean') {
            return ($vals[0] ?? 0) ? 'Sim' : 'Não';
        }

        if ($question->type === 'scale') {
            $v = $vals[0] ?? '?';
            return "{$v} / {$question->scale_max}";
        }

        if (in_array($question->type, ['single', 'multi'])) {
            $texts = $question->options
                ->whereIn('id', $vals)
                ->pluck('text')
                ->implode('; ');
            return $texts ?: implode(', ', $vals);
        }

        return implode(', ', $vals);
    }
}
