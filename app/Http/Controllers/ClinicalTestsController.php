<?php

namespace App\Http\Controllers;

use App\Models\ClinicalTest;
use App\Models\ClinicalTestQuestion;
use App\Models\ClinicalTestQuestionOption;
use App\Models\ClinicalTestScoringRule;
use App\Models\ClinicalTestSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClinicalTestsController extends Controller
{
    private function tenantId(): ?int
    {
        return auth()->user()?->tenant_id;
    }

    public function index(Request $request): Response
    {
        $tenantId = $this->tenantId();
        $search   = trim((string) ($request->query('q') ?? ''));

        $query = ClinicalTest::forTenant($tenantId)
            ->withCount(['questions', 'sessions'])
            ->orderBy('position')
            ->orderBy('name');

        if ($search !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';
            $query->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('category', 'like', $like));
        }

        $tests = $query->paginate(20)->withQueryString()
            ->through(fn (ClinicalTest $t) => $this->formatTest($t));

        $stats = [
            'total'    => ClinicalTest::forTenant($tenantId)->count(),
            'ativos'   => ClinicalTest::forTenant($tenantId)->where('is_active', true)->count(),
            'inativos' => ClinicalTest::forTenant($tenantId)->where('is_active', false)->count(),
        ];

        return Inertia::render('ClinicalTests/Index', [
            'tests'  => $tests,
            'stats'  => $stats,
            'q'      => $search !== '' ? $search : null,
        ]);
    }

    public function edit(ClinicalTest $clinicalTest): Response
    {
        $this->authorizeTenant($clinicalTest);

        $clinicalTest->loadCount(['questions', 'sessions']);

        $questions = $clinicalTest->questions()->with('options')->orderBy('position')->get()
            ->map(fn ($q) => $this->formatQuestion($q))
            ->values();

        $rules = $clinicalTest->scoringRules()->orderBy('min_score')->get()
            ->map(fn ($r) => [
                'id'                 => $r->id,
                'min_score'          => $r->min_score,
                'max_score'          => $r->max_score,
                'result_label'       => $r->result_label,
                'result_description' => $r->result_description,
                'challenge_tags'     => $r->challenge_tags ?? [],
            ])
            ->values();

        return Inertia::render('ClinicalTests/Edit', [
            'test'      => $this->formatTest($clinicalTest),
            'questions' => $questions,
            'rules'     => $rules,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'              => ['required', 'string', 'max:255'],
            'category'          => ['required', 'string', 'max:50'],
            'description'       => ['nullable', 'string', 'max:5000'],
            'instructions'      => ['nullable', 'string', 'max:5000'],
            'estimated_minutes' => ['nullable', 'integer', 'min:1', 'max:999'],
            'is_active'         => ['nullable', 'boolean'],
        ]);

        $maxPos = ClinicalTest::forTenant($this->tenantId())->max('position') ?? -1;

        $test = ClinicalTest::create([
            'tenant_id'         => $this->tenantId(),
            'name'              => $validated['name'],
            'category'          => $validated['category'],
            'description'       => $validated['description'] ?? null,
            'instructions'      => $validated['instructions'] ?? null,
            'estimated_minutes' => $validated['estimated_minutes'] ?? 5,
            'is_active'         => $validated['is_active'] ?? true,
            'position'          => $maxPos + 1,
        ]);

        return response()->json(['success' => true, 'test' => $this->formatTest($test->loadCount(['questions', 'sessions']))]);
    }

    public function update(Request $request, ClinicalTest $clinicalTest): JsonResponse
    {
        $this->authorizeTenant($clinicalTest);

        $validated = $request->validate([
            'name'              => ['sometimes', 'required', 'string', 'max:255'],
            'category'          => ['sometimes', 'required', 'string', 'max:50'],
            'description'       => ['nullable', 'string', 'max:5000'],
            'instructions'      => ['nullable', 'string', 'max:5000'],
            'estimated_minutes' => ['nullable', 'integer', 'min:1', 'max:999'],
            'is_active'         => ['nullable', 'boolean'],
        ]);

        $clinicalTest->update($validated);

        return response()->json(['success' => true, 'test' => $this->formatTest($clinicalTest->loadCount(['questions', 'sessions']))]);
    }

    public function toggleActive(ClinicalTest $clinicalTest): JsonResponse
    {
        $this->authorizeTenant($clinicalTest);
        $clinicalTest->update(['is_active' => ! $clinicalTest->is_active]);
        return response()->json(['success' => true, 'is_active' => $clinicalTest->is_active]);
    }

    public function destroy(ClinicalTest $clinicalTest): JsonResponse
    {
        $this->authorizeTenant($clinicalTest);
        $clinicalTest->delete();
        return response()->json(['success' => true]);
    }

    public function reorder(Request $request): JsonResponse
    {
        $request->validate(['ids' => 'required|array']);
        foreach ($request->ids as $pos => $id) {
            ClinicalTest::where('id', $id)->where('tenant_id', $this->tenantId())->update(['position' => $pos]);
        }
        return response()->json(['success' => true]);
    }

    // ── Perguntas ──────────────────────────────────────────────────────────────

    public function showQuestions(ClinicalTest $clinicalTest): JsonResponse
    {
        $this->authorizeTenant($clinicalTest);

        $questions = $clinicalTest->questions()->with('options')->get()
            ->map(fn ($q) => $this->formatQuestion($q));

        return response()->json(['questions' => $questions]);
    }

    public function storeQuestion(Request $request, ClinicalTest $clinicalTest): JsonResponse
    {
        $this->authorizeTenant($clinicalTest);

        $validated = $request->validate([
            'text'         => ['required', 'string', 'max:2000'],
            'type'         => ['required', 'in:scale,single,multi,boolean,text'],
            'scale_min'    => ['nullable', 'integer', 'min:1'],
            'scale_max'    => ['nullable', 'integer', 'min:2', 'max:10'],
            'scale_labels' => ['nullable', 'array'],
            'options'      => ['nullable', 'array'],
            'options.*.text'  => ['required_with:options', 'string', 'max:500'],
            'options.*.value' => ['nullable', 'integer'],
        ]);

        $maxPos = $clinicalTest->questions()->max('position') ?? -1;

        $question = $clinicalTest->questions()->create([
            'text'         => $validated['text'],
            'type'         => $validated['type'],
            'scale_min'    => $validated['scale_min'] ?? 1,
            'scale_max'    => $validated['scale_max'] ?? 5,
            'scale_labels' => $validated['scale_labels'] ?? null,
            'position'     => $maxPos + 1,
        ]);

        if (in_array($validated['type'], ['single', 'multi']) && ! empty($validated['options'])) {
            foreach ($validated['options'] as $i => $opt) {
                $question->options()->create([
                    'text'     => $opt['text'],
                    'value'    => $opt['value'] ?? $i,
                    'position' => $i,
                ]);
            }
        }

        return response()->json(['success' => true, 'question' => $this->formatQuestion($question->load('options'))]);
    }

    public function updateQuestion(Request $request, ClinicalTest $clinicalTest, ClinicalTestQuestion $question): JsonResponse
    {
        $this->authorizeTenant($clinicalTest);
        abort_if($question->clinical_test_id !== $clinicalTest->id, 404);

        $validated = $request->validate([
            'text'         => ['sometimes', 'required', 'string', 'max:2000'],
            'type'         => ['sometimes', 'required', 'in:scale,single,multi,boolean,text'],
            'scale_min'    => ['nullable', 'integer', 'min:1'],
            'scale_max'    => ['nullable', 'integer', 'min:2', 'max:10'],
            'scale_labels' => ['nullable', 'array'],
            'options'      => ['nullable', 'array'],
            'options.*.id'    => ['nullable', 'integer'],
            'options.*.text'  => ['required_with:options', 'string', 'max:500'],
            'options.*.value' => ['nullable', 'integer'],
        ]);

        $question->update(collect($validated)->except('options')->toArray());

        if (isset($validated['options'])) {
            $question->options()->delete();
            foreach ($validated['options'] as $i => $opt) {
                $question->options()->create([
                    'text'     => $opt['text'],
                    'value'    => $opt['value'] ?? $i,
                    'position' => $i,
                ]);
            }
        }

        return response()->json(['success' => true, 'question' => $this->formatQuestion($question->fresh()->load('options'))]);
    }

    public function destroyQuestion(ClinicalTest $clinicalTest, ClinicalTestQuestion $question): JsonResponse
    {
        $this->authorizeTenant($clinicalTest);
        abort_if($question->clinical_test_id !== $clinicalTest->id, 404);
        $question->delete();
        return response()->json(['success' => true]);
    }

    public function reorderQuestions(Request $request, ClinicalTest $clinicalTest): JsonResponse
    {
        $this->authorizeTenant($clinicalTest);
        $request->validate(['ids' => 'required|array']);
        foreach ($request->ids as $pos => $id) {
            ClinicalTestQuestion::where('id', $id)->where('clinical_test_id', $clinicalTest->id)->update(['position' => $pos]);
        }
        return response()->json(['success' => true]);
    }

    // ── Regras de pontuação ────────────────────────────────────────────────────

    public function storeScoringRule(Request $request, ClinicalTest $clinicalTest): JsonResponse
    {
        $this->authorizeTenant($clinicalTest);

        $validated = $request->validate([
            'min_score'          => ['required', 'integer'],
            'max_score'          => ['required', 'integer', 'gte:min_score'],
            'result_label'       => ['required', 'string', 'max:255'],
            'result_description' => ['nullable', 'string', 'max:5000'],
            'challenge_tags'     => ['nullable', 'array'],
            'challenge_tags.*'   => ['string', 'max:100'],
        ]);

        $rule = $clinicalTest->scoringRules()->create($validated);

        return response()->json(['success' => true, 'rule' => $rule]);
    }

    public function updateScoringRule(Request $request, ClinicalTest $clinicalTest, ClinicalTestScoringRule $rule): JsonResponse
    {
        $this->authorizeTenant($clinicalTest);
        abort_if($rule->clinical_test_id !== $clinicalTest->id, 404);

        $validated = $request->validate([
            'min_score'          => ['sometimes', 'required', 'integer'],
            'max_score'          => ['sometimes', 'required', 'integer'],
            'result_label'       => ['sometimes', 'required', 'string', 'max:255'],
            'result_description' => ['nullable', 'string', 'max:5000'],
            'challenge_tags'     => ['nullable', 'array'],
            'challenge_tags.*'   => ['string', 'max:100'],
        ]);

        $rule->update($validated);

        return response()->json(['success' => true, 'rule' => $rule->fresh()]);
    }

    public function destroyScoringRule(ClinicalTest $clinicalTest, ClinicalTestScoringRule $rule): JsonResponse
    {
        $this->authorizeTenant($clinicalTest);
        abort_if($rule->clinical_test_id !== $clinicalTest->id, 404);
        $rule->delete();
        return response()->json(['success' => true]);
    }

    // ── Respostas (admin) ──────────────────────────────────────────────────────

    public function responses(ClinicalTest $clinicalTest): JsonResponse
    {
        $this->authorizeTenant($clinicalTest);

        $sessions = ClinicalTestSession::where('clinical_test_id', $clinicalTest->id)
            ->where('status', 'completed')
            ->with('user:id,name,email')
            ->orderByDesc('completed_at')
            ->paginate(50);

        return response()->json($sessions);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function authorizeTenant(ClinicalTest $test): void
    {
        abort_if((int) $test->tenant_id !== (int) $this->tenantId(), 403);
    }

    private function formatTest(ClinicalTest $test): array
    {
        return [
            'id'                => $test->id,
            'name'              => $test->name,
            'category'          => $test->category,
            'description'       => $test->description,
            'instructions'      => $test->instructions,
            'estimated_minutes' => $test->estimated_minutes,
            'is_active'         => $test->is_active,
            'position'          => $test->position,
            'questions_count'   => $test->questions_count ?? 0,
            'sessions_count'    => $test->sessions_count ?? 0,
            'created_at'        => $test->created_at?->format('d/m/Y'),
            'ai_context'        => $test->ai_context ?? [],
        ];
    }

    private function formatQuestion(ClinicalTestQuestion $q): array
    {
        return [
            'id'           => $q->id,
            'text'         => $q->text,
            'type'         => $q->type,
            'scale_min'    => $q->scale_min,
            'scale_max'    => $q->scale_max,
            'scale_labels' => $q->scale_labels,
            'position'     => $q->position,
            'options'      => $q->relationLoaded('options')
                ? $q->options->map(fn ($o) => ['id' => $o->id, 'text' => $o->text, 'value' => $o->value, 'position' => $o->position])->values()
                : [],
        ];
    }
}
