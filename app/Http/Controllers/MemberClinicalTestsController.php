<?php

namespace App\Http\Controllers;

use App\Models\AiInsight;
use App\Models\ClinicalTest;
use App\Models\ClinicalTestSession;
use App\Models\ClinicalTestSessionAnswer;
use App\Models\ProfessionalTestAssignment;
use App\Models\UserChallengeTag;
use App\Services\ClinicalTestAiScoringService;
use App\Services\ClinicalTestReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MemberClinicalTestsController extends Controller
{
    private function getProduct(Request $request)
    {
        return $request->attributes->get('member_area_product');
    }

    // GET /m/testes — lista todos os testes disponíveis com status do usuário
    public function index(Request $request, string $slug): Response
    {
        $product  = $this->getProduct($request);
        $user     = $request->user();
        $tenantId = $product->tenant_id;
        $category = $request->query('category');

        // Testes do admin (sem professional_user_id)
        $adminQuery = ClinicalTest::forTenant($tenantId)
            ->whereNull('professional_user_id')
            ->where('is_active', true)
            ->orderBy('position');

        if ($category && $category !== 'todos') {
            $adminQuery->where('category', $category);
        }

        $adminTests = $adminQuery->withCount('questions')->get();

        // Testes atribuídos ao paciente por profissionais vinculados
        $assignments = ProfessionalTestAssignment::where('patient_user_id', $user->id)
            ->where('product_id', $product->id)
            ->with(['test' => fn ($q) => $q->withCount('questions')])
            ->get();

        $assignedTests = $assignments->filter(fn ($a) => $a->test !== null)->map(fn ($a) => $a->test);

        // Sessões de todos os testes relevantes
        $allTestIds = $adminTests->pluck('id')->merge($assignedTests->pluck('id'))->unique();
        $todasSessoes = ClinicalTestSession::where('user_id', $user->id)
            ->whereIn('clinical_test_id', $allTestIds)
            ->get();

        // Autorrelato do proprio usuario: child_profile_id = 0.
        $sessions = $todasSessoes->where('child_profile_id', 0)->keyBy('clinical_test_id');

        // Rastreio infantil: uma sessao por crianca, agrupadas por teste.
        $sessoesPorCrianca = $todasSessoes->where('child_profile_id', '!=', 0)->groupBy('clinical_test_id');

        $perfisInfantis = \App\Models\ChildProfile::doResponsavel((int) $user->id)
            ->orderBy('name')
            ->get();

        $categories = ClinicalTest::forTenant($tenantId)
            ->whereNull('professional_user_id')
            ->where('is_active', true)
            ->distinct()
            ->pluck('category')
            ->values();

        /**
         * Para um rastreio infantil, o estado e por crianca: cada filho tem a
         * propria sessao, e um nao herda o resultado do outro.
         */
        $aplicacoesInfantis = function (ClinicalTest $t) use ($sessoesPorCrianca, $perfisInfantis) {
            $doTeste = $sessoesPorCrianca->get($t->id, collect())->keyBy('child_profile_id');

            return $perfisInfantis->map(function ($perfil) use ($doTeste) {
                $sessao = $doTeste->get($perfil->id);

                return [
                    'child_profile_id' => $perfil->id,
                    'nome' => $perfil->name,
                    'idade' => $perfil->idade(),
                    'status' => $sessao?->status ?? 'not_started',
                    'session_id' => $sessao?->id,
                    'result_label' => $sessao?->result_label,
                    'completed_at' => $sessao?->completed_at?->format('d/m/Y'),
                    'respondido_por' => $sessao?->respondent_relationship,
                ];
            })->values()->all();
        };

        $mapTest = function (ClinicalTest $t) use ($sessions, $aplicacoesInfantis) {
            $session = $sessions->get($t->id);
            return [
                'id'                => $t->id,
                'name'              => $t->name,
                'category'          => $t->category,
                'description'       => $t->description,
                'estimated_minutes' => $t->estimated_minutes,
                'questions_count'   => $t->questions_count,
                'status'            => $session?->status ?? 'not_started',
                'session_id'        => $session?->id,
                'result_label'      => $session?->result_label,
                'challenge_tags'    => $session?->challenge_tags ?? [],
                'completed_at'      => $session?->completed_at?->format('d/m/Y'),
                'is_assigned'       => false,
                'is_child_screening' => (bool) $t->is_child_screening,
                'aplicacoes_infantis' => $t->is_child_screening
                    ? $aplicacoesInfantis($t)
                    : [],
            ];
        };

        $testsData    = $adminTests->map($mapTest);
        $assignedData = $assignedTests->map(function (ClinicalTest $t) use ($sessions, $aplicacoesInfantis) {
            $session = $sessions->get($t->id);
            return [
                'id'                => $t->id,
                'name'              => $t->name,
                'category'          => $t->category,
                'description'       => $t->description,
                'estimated_minutes' => $t->estimated_minutes,
                'questions_count'   => $t->questions_count,
                'status'            => $session?->status ?? 'not_started',
                'session_id'        => $session?->id,
                'result_label'      => $session?->result_label,
                'challenge_tags'    => $session?->challenge_tags ?? [],
                'completed_at'      => $session?->completed_at?->format('d/m/Y'),
                'is_assigned'       => true,
                'is_child_screening' => (bool) $t->is_child_screening,
                'aplicacoes_infantis' => $t->is_child_screening
                    ? $aplicacoesInfantis($t)
                    : [],
            ];
        });

        $baseUrl = $request->attributes->get('member_base_url', "/m/{$slug}");

        return Inertia::render('MemberAreaApp/Testes', [
            'product'        => ['id' => $product->id, 'name' => $product->name],
            'config'         => $product->member_area_config,
            'slug'           => $slug,
            'base_url'       => $baseUrl,
            'tests'          => $testsData->values(),
            'assigned_tests' => $assignedData->values(),
            'categories'     => $categories,
            'category'       => $category ?? 'todos',
            'perfis_infantis' => $perfisInfantis->map(fn ($p) => [
                'id' => $p->id,
                'nome' => $p->name,
                'idade' => $p->idade(),
            ])->values(),
        ]);
    }

    // GET /m/testes/{testId} — carrega o teste para o aluno fazer
    public function show(Request $request, string $slug, int $testId): Response
    {
        $product = $this->getProduct($request);
        $user    = $request->user();

        $test = $this->resolveAccessibleTest($testId, $user->id, $product);

        $session = ClinicalTestSession::where('user_id', $user->id)
            ->where('clinical_test_id', $test->id)
            ->with('answers')
            ->first();

        // Mapa de respostas já salvas: question_id => answer
        $savedAnswers = [];
        if ($session) {
            foreach ($session->answers as $ans) {
                $savedAnswers[$ans->clinical_test_question_id] = $ans->answer;
            }
        }

        $baseUrl = $request->attributes->get('member_base_url', "/m/{$slug}");

        // Quando concluído, busca description da scoring rule correspondente
        $resultDescription = null;
        if ($session?->status === 'completed' && $session->result_label) {
            $rule = $test->scoringRules
                ->first(fn ($r) => $r->result_label === $session->result_label);
            $resultDescription = $rule?->result_description;
        }

        // Relatório de IA disponível para este teste?
        $hasAiReport  = ! empty(trim($test->ai_context['instructions'] ?? ''));
        $insightUrl   = null;
        if ($hasAiReport && $session?->status === 'completed') {
            $existing = AiInsight::where('user_id', $user->id)
                ->where('type', 'clinical_test_report')
                ->whereJsonContains('metadata->session_id', $session->id)
                ->first();
            if ($existing) {
                $insightUrl = "{$baseUrl}/relatorio-ia/{$existing->id}/imprimir";
            }
        }

        return Inertia::render('MemberAreaApp/Teste', [
            'product'       => ['id' => $product->id, 'name' => $product->name],
            'config'        => $product->member_area_config,
            'slug'          => $slug,
            'base_url'      => $baseUrl,
            'test'          => $this->formatTestFull($test),
            'has_ai_report' => $hasAiReport,
            'insight_url'   => $insightUrl,
            'session'       => $session ? [
                'id'                 => $session->id,
                'status'             => $session->status,
                'score'              => $session->score,
                'result_label'       => $session->result_label,
                'result_description' => $resultDescription,
                'challenge_tags'     => $session->challenge_tags ?? [],
            ] : null,
            'saved_answers' => $savedAnswers,
        ]);
    }

    // POST /m/testes/{testId}/iniciar — cria ou reinicia sessão
    public function start(Request $request, string $slug, int $testId): JsonResponse
    {
        $product = $this->getProduct($request);
        $user    = $request->user();

        $test = $this->resolveAccessibleTest($testId, $user->id, $product);

        $request->validate([
            'restart' => 'nullable|boolean',
            'child_profile_id' => 'nullable|integer',
        ]);

        // Rastreio infantil e sempre respondido por um adulto em nome da crianca,
        // nunca como autorrelato (escopo, Parte 03 par. 7).
        $perfilInfantil = null;
        if ($test->is_child_screening) {
            $perfilInfantil = \App\Models\ChildProfile::doResponsavel((int) $user->id)
                ->where('id', (int) $request->input('child_profile_id'))
                ->first();

            if (! $perfilInfantil) {
                return response()->json([
                    'success' => false,
                    'message' => 'Escolha para qual criança este rastreio será respondido.',
                    'requires_child_profile' => true,
                ], 422);
            }
        }

        $childProfileId = $perfilInfantil?->id ?? 0;

        // Reiniciar: apaga sessão anterior daquela criança (ou do autorrelato)
        if ($request->boolean('restart')) {
            ClinicalTestSession::where('user_id', $user->id)
                ->where('clinical_test_id', $test->id)
                ->where('child_profile_id', $childProfileId)
                ->delete();
        }

        $session = ClinicalTestSession::firstOrCreate(
            [
                'user_id' => $user->id,
                'clinical_test_id' => $test->id,
                'child_profile_id' => $childProfileId,
            ],
            [
                'product_id' => $product->id,
                'status' => 'in_progress',
                // Congela o vinculo no momento da aplicacao: editar o perfil
                // depois nao reescreve o relatorio ja gerado.
                'respondent_relationship' => $perfilInfantil?->relationship,
            ]
        );

        return response()->json([
            'success' => true,
            'session_id' => $session->id,
            'child_profile_id' => $childProfileId ?: null,
        ]);
    }

    // POST /m/testes/{testId}/responder — salva uma resposta (save-state por questão)
    public function saveAnswer(Request $request, string $slug, int $testId): JsonResponse
    {
        $product = $this->getProduct($request);
        $user    = $request->user();

        $validated = $request->validate([
            'session_id'  => 'required|integer',
            'question_id' => 'required|integer',
            'answer'      => 'required',
        ]);

        $session = ClinicalTestSession::where('id', $validated['session_id'])
            ->where('user_id', $user->id)
            ->where('clinical_test_id', $testId)
            ->where('status', 'in_progress')
            ->firstOrFail();

        $answer = is_array($validated['answer']) ? $validated['answer'] : [$validated['answer']];

        ClinicalTestSessionAnswer::updateOrCreate(
            [
                'clinical_test_session_id'  => $session->id,
                'clinical_test_question_id' => $validated['question_id'],
            ],
            ['answer' => $answer]
        );

        return response()->json(['success' => true]);
    }

    // POST /m/testes/{testId}/concluir — conclui o teste, calcula score, gera tags
    public function complete(Request $request, string $slug, int $testId): JsonResponse
    {
        $product = $this->getProduct($request);
        $user    = $request->user();

        $validated = $request->validate(['session_id' => 'required|integer']);

        $session = ClinicalTestSession::where('id', $validated['session_id'])
            ->where('user_id', $user->id)
            ->where('clinical_test_id', $testId)
            ->where('status', 'in_progress')
            ->with(['answers', 'test.questions.options', 'test.scoringRules'])
            ->firstOrFail();

        $test = $session->test;

        // Calcula score numérico (usado como base ou fallback)
        $score = 0;
        foreach ($session->answers as $ans) {
            $question = $test->questions->firstWhere('id', $ans->clinical_test_question_id);
            if (! $question) continue;

            $val = $ans->answer;
            if (in_array($question->type, ['scale', 'boolean'])) {
                $score += (int) ($val[0] ?? 0);
            } elseif (in_array($question->type, ['single', 'multi'])) {
                $score += $question->options
                    ->whereIn('id', $val)
                    ->sum('value');
            }
            // tipo 'text' não contribui para score numérico
        }

        $resultDescription = null;

        // Se o teste tem instruções de IA → IA decide a classificação
        $aiInstructions = trim($test->ai_context['instructions'] ?? '');
        $aiResult       = null;

        if ($aiInstructions) {
            $aiResult = (new ClinicalTestAiScoringService())->evaluate($session, $test, $product->tenant_id);
        }

        if ($aiResult) {
            $score             = $aiResult['score'] ?: $score; // preserva score numérico se IA retornar 0
            $resultLabel       = $aiResult['result_label'];
            $resultDescription = $aiResult['result_description'];
            $challengeTags     = $aiResult['challenge_tags'];
        } else {
            // Fallback: regras de pontuação estáticas
            $rule          = $test->scoringRules->first(fn ($r) => $score >= $r->min_score && $score <= $r->max_score);
            $resultLabel   = $rule?->result_label ?? 'Concluído';
            $resultDescription = $rule?->result_description;
            $challengeTags = $rule?->challenge_tags ?? [];
        }

        // Atualiza sessão como concluída
        $session->update([
            'status'        => 'completed',
            'score'         => $score,
            'result_label'  => $resultLabel,
            'challenge_tags' => $challengeTags,
            'completed_at'  => now(),
        ]);

        /*
         * Tags de desafio vao para o perfil de quem o teste descreve.
         *
         * Num rastreio infantil quem responde e o adulto, mas o resultado e da
         * crianca: gravar no responsavel contaminaria a trilha e o Mentor de IA
         * dele com o quadro do filho. Nesse caso as tags ficam apenas na sessao,
         * que pertence ao perfil infantil (escopo, Parte 01 e Parte 02 par. 2.2).
         */
        if ((int) $session->child_profile_id === 0) {
            foreach ($challengeTags as $tag) {
                UserChallengeTag::firstOrCreate([
                    'user_id'     => $user->id,
                    'tenant_id'   => $product->tenant_id,
                    'tag'         => $tag,
                    'source_type' => 'clinical_test',
                    'source_id'   => $session->id,
                ]);
            }
        }

        return response()->json([
            'success'        => true,
            'score'          => $score,
            'result_label'   => $resultLabel,
            'result_description' => $resultDescription,
            'challenge_tags' => $challengeTags,
            'child_profile_id' => $session->child_profile_id ?: null,
        ]);
    }

    // POST /m/{slug}/testes/{testId}/gerar-relatorio
    public function generateReport(Request $request, string $slug, int $testId): JsonResponse
    {
        $product = $this->getProduct($request);
        $user    = $request->user();

        $test = $this->resolveAccessibleTest($testId, $user->id, $product);

        $instructions = trim($test->ai_context['instructions'] ?? '');
        abort_if(empty($instructions), 422, 'Este teste não tem relatório de IA configurado.');

        $session = ClinicalTestSession::where('user_id', $user->id)
            ->where('clinical_test_id', $test->id)
            ->where('status', 'completed')
            ->with(['answers', 'answers.question.options'])
            ->first();

        abort_if(! $session, 422, 'Conclua o teste antes de gerar o relatório.');

        $baseUrl = $request->attributes->get('member_base_url', "/m/{$slug}");

        // Retorna insight existente sem reprocessar
        $existing = AiInsight::where('user_id', $user->id)
            ->where('type', 'clinical_test_report')
            ->whereJsonContains('metadata->session_id', $session->id)
            ->first();

        if ($existing) {
            return response()->json([
                'ok'  => true,
                'url' => "{$baseUrl}/relatorio-ia/{$existing->id}/imprimir",
            ]);
        }

        $insight = (new ClinicalTestReportService())->generate($session, $test, $user, $product->tenant_id);

        if (! $insight) {
            return response()->json(['message' => 'IA não disponível. Configure a chave de API nas configurações.'], 422);
        }

        return response()->json([
            'ok'  => true,
            'url' => "{$baseUrl}/relatorio-ia/{$insight->id}/imprimir",
        ]);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /**
     * Retorna o teste se o usuário tem acesso a ele:
     * - Teste do admin (tenant, professional_user_id IS NULL, is_active)
     * - OU teste atribuído ao usuário por um profissional vinculado
     */
    private function resolveAccessibleTest(int $testId, int $userId, $product): ClinicalTest
    {
        // Teste do admin
        $test = ClinicalTest::where('id', $testId)
            ->where('tenant_id', $product->tenant_id)
            ->whereNull('professional_user_id')
            ->where('is_active', true)
            ->with(['questions.options', 'scoringRules'])
            ->first();

        if ($test) return $test;

        // Teste atribuído por profissional
        $assignment = ProfessionalTestAssignment::where('patient_user_id', $userId)
            ->where('clinical_test_id', $testId)
            ->where('product_id', $product->id)
            ->first();

        abort_if(! $assignment, 404, 'Teste não encontrado ou sem acesso.');

        return ClinicalTest::where('id', $testId)
            ->with(['questions.options', 'scoringRules'])
            ->firstOrFail();
    }

    private function formatTestFull(ClinicalTest $test): array
    {
        return [
            'id'                => $test->id,
            'name'              => $test->name,
            'category'          => $test->category,
            'description'       => $test->description,
            'instructions'      => $test->instructions,
            'estimated_minutes' => $test->estimated_minutes,
            'questions'         => $test->questions->map(fn ($q) => [
                'id'           => $q->id,
                'text'         => $q->text,
                'type'         => $q->type,
                'scale_min'    => $q->scale_min,
                'scale_max'    => $q->scale_max,
                'scale_labels' => $q->scale_labels,
                'options'      => $q->options->map(fn ($o) => [
                    'id'    => $o->id,
                    'text'  => $o->text,
                    'value' => $o->value,
                ])->values(),
            ])->values(),
        ];
    }
}
