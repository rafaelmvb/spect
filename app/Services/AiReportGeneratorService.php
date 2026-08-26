<?php

namespace App\Services;

use App\Models\AiConfig;
use App\Models\AiInsight;
use App\Models\CheckpointResponse;
use App\Models\Journey;
use App\Models\MemberLesson;
use App\Models\MemberLessonProgress;
use App\Models\MemberQuizResponse;
use App\Models\NeuroUserScore;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserJourneyUnlock;

class AiReportGeneratorService
{
    public function generate(User $student, int $tenantId, ?int $generatedByAdminId = null, string $instructions = '', ?Product $product = null): ?AiInsight
    {
        $aiService = new AiService($tenantId);
        if (! $aiService->available()) {
            return null;
        }

        $tenantProductIds = Product::forTenant($tenantId)->pluck('id')->toArray();

        // Respostas de quiz
        $quizText = '';
        $quizResponses = MemberQuizResponse::where('user_id', $student->id)
            ->with('lesson:id,title,product_id,content_files')
            ->orderByDesc('created_at')
            ->get()
            ->filter(fn ($r) => $r->lesson && in_array($r->lesson->product_id, $tenantProductIds, false));

        foreach ($quizResponses as $qr) {
            $questions     = $qr->lesson->content_files['questions'] ?? [];
            $questionsById = collect($questions)->keyBy('id');
            $quizText .= "\nEtapa — {$qr->lesson->title} ({$qr->created_at->format('d/m/Y')}):\n";
            foreach ($qr->responses as $resp) {
                $q    = $questionsById[$resp['question_id']]['text'] ?? '?';
                $type = $resp['type'] ?? 'scale';
                $raw  = $resp['value'];

                if ($type === 'boolean') {
                    $value = $raw ? 'Sim' : 'Não';
                } elseif ($type === 'multi' && is_array($raw)) {
                    $optMap = collect($questionsById[$resp['question_id']]['options'] ?? [])->keyBy('id');
                    $value  = collect($raw)->map(fn ($id) => $optMap[$id]['text'] ?? $id)->implode(', ');
                    $value  = $value ?: implode(', ', $raw);
                } elseif ($type === 'single') {
                    $optMap = collect($questionsById[$resp['question_id']]['options'] ?? [])->keyBy('id');
                    $value  = $optMap[$raw]['text'] ?? $raw;
                } elseif (is_array($raw)) {
                    $value = implode(', ', $raw);
                } else {
                    $value = (string) $raw;
                }

                $quizText .= "  P: {$q}\n  R: {$value}\n";
                if (! empty($resp['comment'])) {
                    $quizText .= "  Obs: {$resp['comment']}\n";
                }
            }
        }

        // Respostas de checkpoints
        $checkpointText = '';
        $checkpoints = CheckpointResponse::where('user_id', $student->id)
            ->with(['checkpoint:id,title', 'answers.question:id,label'])
            ->orderByDesc('completed_at')
            ->limit(10)
            ->get();
        foreach ($checkpoints as $cr) {
            $checkpointText .= "\nCheckpoint — {$cr->checkpoint?->title} ({$cr->completed_at?->format('d/m/Y')}):\n";
            foreach ($cr->answers as $a) {
                $checkpointText .= "  {$a->question?->label}: {$a->value}\n";
            }
        }

        // Progresso nos cursos
        $progressText = '';
        foreach ($student->products()->forTenant($tenantId)->select('products.id', 'products.name')->get() as $p) {
            $total     = MemberLesson::where('product_id', $p->id)->count();
            $completed = MemberLessonProgress::where('user_id', $student->id)
                ->where('product_id', $p->id)
                ->whereNotNull('completed_at')
                ->count();
            $pct = $total > 0 ? (int) round($completed / $total * 100) : 0;
            $progressText .= "  - {$p->name}: {$completed}/{$total} aulas ({$pct}%)\n";
        }

        // NeuroMap scores
        $neuroText = '';
        $neuroScores = NeuroUserScore::where('user_id', $student->id)
            ->with('indicator:id,name')
            ->orderByDesc('scored_at')
            ->get()
            ->groupBy('neuro_indicator_id');
        foreach ($neuroScores as $group) {
            $first = $group->first();
            $neuroText .= "  - {$first->indicator?->name}: {$first->value}/10\n";
        }

        $systemPrompt = <<<'PROMPT'
Você é o motor de análise neurofuncional da plataforma Spectra Health — especialista em AHSD (Altas Habilidades/Superdotação), TDAH, TEA e desenvolvimento humano.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
MISSÃO
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Gerar um Relatório Neurofuncional Avançado personalizado, baseado exclusivamente nas respostas do aluno aos quizzes, checkpoints e indicadores neurofuncionais.

PROIBIDO mencionar progresso de aulas, quantidade de aulas assistidas, percentual de conclusão ou qualquer dado de navegação no curso — o aluno já sabe o que fez. O relatório é sobre o que as respostas REVELAM sobre o funcionamento dele, não sobre o que ele fez ou deixou de fazer na plataforma.

O relatório deve ser leitura de funcionamento — nunca fechamento diagnóstico.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
MODELO DE ANÁLISE — 6 DOMÍNIOS RDOC
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Ao interpretar respostas, avalie o peso de cada domínio:

1. AMEAÇA (Threat) — ansiedade, hipervigilância, antecipação de perigo, GAD
2. RECOMPENSA (Reward) — motivação, prazer, sustentação de interesse, WHO-5
3. COGNIÇÃO (Cognition) — funções executivas, atenção, memória de trabalho, ASRS
4. SOCIAL (Social) — pertencimento, adaptação, custo social, mascaramento, CAT-Q
5. AROUSAL/REGULAÇÃO — ritmo interno, sono, regulação emocional, oscilação, DERS
6. SENSORIAL — hipersensibilidade, sobrecarga ambiental, processamento sensorial, HSP

Para cada domínio presente nas respostas, identifique:
- Nível qualitativo (Muito baixo / Baixo / Moderado / Alto / Muito alto)
- Padrão observado (o que as respostas mostram)
- Interação entre domínios (como se modulam mutuamente)

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
MODELO PEI (Potencial–Expressão–Impacto)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Analise o gap entre Potencial e Expressão:
- Potencial (P) = hardware neurobiológico (velocidade de processamento, conectividade, capacidade associativa)
- Expressão (E) = funções executivas + regulação emocional + caráter (constância, conscienciosidade)
- Impacto (I) = resultado observável na vida real

Identifique se o aluno está em: homeostase (P → E → I alinhados) ou alostase (gap entre P e E — potencial travado, vida cotidiana não sustenta o que a mente consegue alcançar).

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
ESTRUTURA OBRIGATÓRIA DO RELATÓRIO
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Use exatamente esta estrutura em 4 blocos:

**BLOCO 1 — O QUE APARECEU**
Descreva o que as respostas mostram: padrões de funcionamento, domínios salientes, comportamentos recorrentes. Seja específico e baseado nos dados fornecidos. Não generalize.

**BLOCO 2 — O QUE ISSO PODE SIGNIFICAR**
Traduza em linguagem de funcionamento humano. Use o modelo PEI e os 6 domínios. Explique como esses padrões se organizam na vida real: o que facilita, o que bloqueia, o que encarece a expressão do potencial.

**BLOCO 3 — O QUE ISSO AINDA NÃO SIGNIFICA**
Proteja contra leitura diagnóstica fechada. Este relatório NÃO substitui avaliação profissional, NÃO fecha hipótese clínica, NÃO é laudo, NÃO afirma condição. Seja claro sobre os limites da análise.

**BLOCO 4 — RECOMENDAÇÕES E PRÓXIMOS PASSOS**
Ofereça recomendações práticas baseadas nos padrões identificados. Inclua: áreas prioritárias de atenção, recursos internos observados (pontos fortes), e sugestões de aprofundamento ou acompanhamento.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
REGRAS DE LINGUAGEM — OBRIGATÓRIAS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
NUNCA USE:
- "você tem [condição]" / "isso confirma" / "é diagnóstico de"
- linguagem diagnóstica fechada: "tem TDAH", "é autista", "tem superdotação"
- linguagem autoajuda rasa, motivacional vazia, paternalista
- frases genéricas que servem para qualquer pessoa

SEMPRE USE:
- "o que apareceu nas suas respostas sugere" / "você demonstra" / "há um padrão em você de"
- "isso pode estar associado a como você funciona" / "o seu funcionamento se assemelha a"
- "vale atenção para" / "pode ser investigado com apoio de" / "é compatível com perfis como o seu"
- linguagem sofisticada, clara, humana e elegante — fale COM a pessoa, não sobre ela
- tom: inteligente · acolhedor sem excesso afetivo · profissional · denso mas compreensível
- Para negrito use **texto** (markdown) — NUNCA use tags HTML como &lt;strong&gt;, &lt;b&gt; etc.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
FENÔMENOS COMUNS EM PERFIS AHSD/TDAH/TEA
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Ao identificar padrões nas respostas, considere:
- Ferrari a 10km/h: potencial alto com expressão travada (procrastinação seletiva, abandono de sistema, autoboicote)
- Holograma do Eu Ideal: estrutura compensatória construída sobre trauma — controle, perfeccionismo, ansiedade generalizada
- Solidão epistemológica: ausência de pares cognitivos — isolamento por diferença de funcionamento, não por rejeição social
- Mascaramento (CAT-Q): esforço de adaptação social que esgota e oculta o funcionamento real
- Fuga distrativa: hiperfoco usado como escape da ansiedade basal elevada (parece produtividade, é regulação)
- Gap P→E: compreende muito, sustenta pouco — entender não é o mesmo que executar
- Desenvolvimento assíncrono: capacidade intelectual avançada + regulação emocional atrasada

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
INSTRUÇÕES FINAIS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
- O relatório é escrito DIRETAMENTE PARA O ALUNO — use sempre "você", nunca o nome ou terceira pessoa
- Tom: como um especialista falando diretamente com a pessoa, não sobre ela
- NUNCA mencione ausência de dados, número de respostas ou lacunas no preenchimento — analise o que existe
- Se houver dados conflitantes: nomeie a ambivalência, não tente forçar coerência
- Se os dados sugerirem sofrimento significativo: mencione a importância de acompanhamento especializado com cuidado e precisão
- Extensão ideal: 400 a 800 palavras, divididas nos 4 blocos obrigatórios
PROMPT;

        $userMessage = "Gere o relatório diretamente para o aluno (use 'você'):\n\n"
            . ($neuroText      ? "INDICADORES NEUROFUNCIONAIS:\n{$neuroText}\n" : '')
            . ($quizText       ? "RESPOSTAS DA TRILHA:{$quizText}\n" : "Sem respostas de trilha registradas.\n")
            . ($checkpointText ? "RESPOSTAS DE CHECKPOINTS:{$checkpointText}\n" : "Sem checkpoints respondidos.\n");

        if ($instructions) {
            $userMessage .= "\n\nINSTRUÇÕES DE MELHORIA PARA ESTE RELATÓRIO:\n{$instructions}";
        }

        $config = AiConfig::where('tenant_id', $tenantId)
            ->where('context', 'admin_student_report')
            ->where('is_active', true)
            ->first()
            ?? new AiConfig([
                'context'       => 'admin_student_report',
                'system_prompt' => $systemPrompt,
                'model'         => 'claude-haiku-4-5-20251001',
                'temperature'   => '0.7',
                'max_tokens'    => 2000,
                'is_active'     => true,
            ]);

        if (! $config->system_prompt) {
            $config->system_prompt = $systemPrompt;
        }

        $rawCustom    = Setting::get('custom_ai_prompts', '[]', $tenantId);
        $customList   = json_decode($rawCustom, true) ?? [];
        $finalSystemPrompt = $config->system_prompt;
        foreach (array_filter($customList, fn ($p) => ($p['type'] ?? '') === 'report') as $extra) {
            if (! empty($extra['content'])) {
                $finalSystemPrompt .= "\n\n" . $extra['content'];
            }
        }

        // Instruções específicas do produto (configuradas na aba "Instruções IA" do produto)
        if ($product) {
            $aiContext    = $product->ai_context ?? [];
            $productInstr = trim($aiContext['instructions'] ?? '');
            $productFiles = $aiContext['files'] ?? [];

            if ($productInstr) {
                $finalSystemPrompt .= "\n\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
                    . "INSTRUÇÕES ESPECÍFICAS DO PRODUTO: {$product->name}\n"
                    . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
                    . $productInstr;
            }

            if (! empty($productFiles)) {
                $fileNames = collect($productFiles)->pluck('name')->implode(', ');
                $finalSystemPrompt .= "\n\nMATERIAIS DE REFERÊNCIA DO PRODUTO: {$fileNames}";
            }
        }

        $content = $aiService->complete(
            [
                ['role' => 'system', 'content' => $finalSystemPrompt],
                ['role' => 'user',   'content' => $userMessage],
            ],
            $config,
            $tenantId,
            $student->id,
        );

        $insight = AiInsight::create([
            'tenant_id' => $tenantId,
            'user_id'   => $student->id,
            'type'      => 'admin_report',
            'title'     => 'Relatório Avançado — ' . now()->format('d/m/Y H:i'),
            'content'   => $content,
            'metadata'  => ['generated_by_admin' => $generatedByAdminId],
        ]);

        if ($product && $insight) {
            $this->recommendAndUnlockJourney($insight, $student, $tenantId, $product, $aiService, $config);
        }

        return $insight;
    }

    /**
     * Re-dispara a recomendação de jornada para um aluno que já tem um relatório.
     * Retorna true se a jornada foi atribuída, false se já existia, null em caso de falha.
     */
    public function triggerJourneyForInsight(AiInsight $insight, User $student, int $tenantId, Product $product): ?bool
    {
        $aiService = new AiService($tenantId);
        if (! $aiService->available()) {
            return null;
        }

        $config = AiConfig::where('tenant_id', $tenantId)
            ->where('context', 'admin_report')
            ->where('is_active', true)
            ->first()
            ?? new AiConfig([
                'model'       => 'claude-haiku-4-5-20251001',
                'temperature' => '0.2',
                'max_tokens'  => 100,
                'is_active'   => true,
            ]);

        $existsBefore = UserJourneyUnlock::where('user_id', $student->id)
            ->where('tenant_id', $tenantId)
            ->exists();

        $this->recommendAndUnlockJourney($insight, $student, $tenantId, $product, $aiService, $config);

        $existsAfter = UserJourneyUnlock::where('user_id', $student->id)
            ->where('tenant_id', $tenantId)
            ->exists();

        if (! $existsAfter) {
            return null; // falhou
        }

        return ! $existsBefore; // true = nova atribuição, false = já existia
    }

    private function recommendAndUnlockJourney(
        AiInsight $insight,
        User $student,
        int $tenantId,
        Product $product,
        AiService $aiService,
        AiConfig $config,
    ): void {
        $aiContext = $product->ai_context ?? [];
        $jornadas  = $aiContext['jornadas'] ?? [];

        // Se jornadas específicas estão configuradas, usa só elas; senão usa todas ativas do tenant
        if (! empty($jornadas)) {
            $journeyIds = array_column($jornadas, 'journey_id');
            $journeys   = Journey::whereIn('id', $journeyIds)->where('is_active', true)->get();
        } else {
            $journeys = Journey::where('tenant_id', $tenantId)->where('is_active', true)->get();
        }

        if ($journeys->isEmpty()) {
            return;
        }

        $journeyList = $journeys
            ->map(fn ($j) => "ID: {$j->id} — \"{$j->name}\"" . ($j->description ? " — {$j->description}" : ''))
            ->implode("\n");

        $prompt = "Com base no Relatório Neurofuncional abaixo, selecione a jornada mais adequada para este aluno.\n\n"
            . "RELATÓRIO:\n{$insight->content}\n\n"
            . "JORNADAS DISPONÍVEIS:\n{$journeyList}\n\n"
            . 'Responda APENAS com JSON no formato: {"journey_id": <número inteiro>}. Nenhum texto adicional.';

        try {
            $response = $aiService->complete(
                [
                    ['role' => 'system', 'content' => 'Você é um sistema de recomendação. Responda apenas com JSON válido, sem markdown.'],
                    ['role' => 'user',   'content' => $prompt],
                ],
                $config,
                $tenantId,
                $student->id,
            );

            $raw           = preg_replace('/```json|```/i', '', trim($response ?? ''));
            $data          = json_decode($raw, true);
            $recommendedId = isset($data['journey_id']) ? (int) $data['journey_id'] : null;

            if (! $recommendedId || ! $journeys->firstWhere('id', $recommendedId)) {
                return;
            }

            $jornadaConfig = collect($jornadas)->first(fn ($j) => (int) ($j['journey_id'] ?? 0) === $recommendedId);

            UserJourneyUnlock::firstOrCreate(
                ['user_id' => $student->id, 'journey_id' => $recommendedId],
                [
                    'tenant_id'     => $tenantId,
                    'product_id'    => (string) $product->id,
                    'is_free'       => (bool) ($jornadaConfig['is_free'] ?? true),
                    'ai_insight_id' => $insight->id,
                ]
            );
        } catch (\Throwable) {
            // falha silenciosa — não interrompe a geração do relatório
        }
    }
}
