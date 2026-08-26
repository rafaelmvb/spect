<?php

namespace App\Http\Controllers;

use App\Models\Checkpoint;
use App\Models\CheckpointQuestion;
use App\Models\CheckpointResponse;
use App\Models\Journey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CheckpointsController extends Controller
{
    private function tenantId(): ?int
    {
        return auth()->user()->tenant_id;
    }

    private function formatCheckpoint(Checkpoint $c, bool $withQuestions = false): array
    {
        $data = [
            'id'               => $c->id,
            'title'            => $c->title,
            'description'      => $c->description,
            'journey_step_id'  => $c->journey_step_id,
            'unlock_next_step' => $c->unlock_next_step,
            'is_active'        => $c->is_active,
            'questions_count'  => $c->questions()->count(),
            'responses_count'  => $c->responses()->count(),
            'step_label'       => $c->journeyStep?->title,
            'journey_label'    => $c->journeyStep?->journey?->name,
            'created_at'       => $c->created_at->format('d/m/Y'),
        ];

        if ($withQuestions) {
            $data['questions'] = $c->questions->map(fn ($q) => [
                'id'       => $q->id,
                'label'    => $q->label,
                'type'     => $q->type,
                'options'  => $q->options ?? [],
                'required' => $q->required,
                'position' => $q->position,
            ])->values()->toArray();
        }

        return $data;
    }

    public function index(Request $request): Response
    {
        $tenantId = $this->tenantId();
        $search   = trim((string) ($request->query('q') ?? ''));

        $query = Checkpoint::forTenant($tenantId)
            ->with(['journeyStep.journey'])
            ->orderByDesc('created_at');

        if ($search !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';
            $query->where('title', 'like', $like);
        }

        $checkpoints = $query->paginate(20)->withQueryString()
            ->through(fn (Checkpoint $c) => $this->formatCheckpoint($c));

        $stats = [
            'total'    => Checkpoint::forTenant($tenantId)->count(),
            'ativos'   => Checkpoint::forTenant($tenantId)->where('is_active', true)->count(),
            'respostas'=> CheckpointResponse::whereHas(
                'checkpoint', fn ($q) => $q->where('tenant_id', $tenantId)
            )->count(),
        ];

        // Etapas disponíveis para associar (com nome da jornada)
        $steps = Journey::forTenant($tenantId)
            ->with('steps')
            ->orderBy('name')
            ->get()
            ->flatMap(fn ($j) => $j->steps->map(fn ($s) => [
                'id'    => $s->id,
                'label' => $j->name . ' → ' . $s->title,
            ]));

        return Inertia::render('Checkpoints/Index', [
            'checkpoints' => $checkpoints,
            'stats'       => $stats,
            'steps'       => $steps->values(),
            'q'           => $search !== '' ? $search : null,
        ]);
    }

    public function show(Checkpoint $checkpoint): JsonResponse
    {
        abort_if($checkpoint->tenant_id !== $this->tenantId(), 403);
        $checkpoint->load('questions');
        return response()->json(['checkpoint' => $this->formatCheckpoint($checkpoint, true)]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'            => ['required', 'string', 'max:255'],
            'description'      => ['nullable', 'string', 'max:5000'],
            'journey_step_id'  => ['nullable', 'integer', 'exists:journey_steps,id'],
            'unlock_next_step' => ['nullable', 'boolean'],
            'is_active'        => ['nullable', 'boolean'],
        ]);

        $checkpoint = Checkpoint::create([
            ...$validated,
            'tenant_id'        => $this->tenantId(),
            'is_active'        => $validated['is_active'] ?? true,
            'unlock_next_step' => $validated['unlock_next_step'] ?? false,
        ]);

        return response()->json(['success' => true, 'checkpoint' => $this->formatCheckpoint($checkpoint)]);
    }

    public function update(Request $request, Checkpoint $checkpoint): JsonResponse
    {
        abort_if($checkpoint->tenant_id !== $this->tenantId(), 403);

        $validated = $request->validate([
            'title'            => ['sometimes', 'required', 'string', 'max:255'],
            'description'      => ['nullable', 'string', 'max:5000'],
            'journey_step_id'  => ['nullable', 'integer', 'exists:journey_steps,id'],
            'unlock_next_step' => ['nullable', 'boolean'],
            'is_active'        => ['nullable', 'boolean'],
        ]);

        $checkpoint->update($validated);

        return response()->json(['success' => true, 'checkpoint' => $this->formatCheckpoint($checkpoint->fresh()->load('journeyStep.journey'))]);
    }

    public function toggleActive(Checkpoint $checkpoint): JsonResponse
    {
        abort_if($checkpoint->tenant_id !== $this->tenantId(), 403);
        $checkpoint->update(['is_active' => ! $checkpoint->is_active]);
        return response()->json(['success' => true, 'is_active' => $checkpoint->is_active]);
    }

    public function destroy(Checkpoint $checkpoint): JsonResponse
    {
        abort_if($checkpoint->tenant_id !== $this->tenantId(), 403);
        $checkpoint->delete();
        return response()->json(['success' => true]);
    }

    // ─── Questões ──────────────────────────────────────────────────────────────

    public function storeQuestion(Request $request, Checkpoint $checkpoint): JsonResponse
    {
        abort_if($checkpoint->tenant_id !== $this->tenantId(), 403);

        $validated = $request->validate([
            'label'    => ['required', 'string', 'max:500'],
            'type'     => ['required', 'string', 'in:text,textarea,radio,checkbox,scale,date'],
            'options'  => ['nullable', 'array'],
            'options.*'=> ['string', 'max:255'],
            'required' => ['nullable', 'boolean'],
        ]);

        $maxPos = $checkpoint->questions()->max('position') ?? -1;

        $question = $checkpoint->questions()->create([
            'label'    => $validated['label'],
            'type'     => $validated['type'],
            'options'  => $validated['options'] ?? null,
            'required' => $validated['required'] ?? false,
            'position' => $maxPos + 1,
        ]);

        return response()->json(['success' => true, 'question' => [
            'id'       => $question->id,
            'label'    => $question->label,
            'type'     => $question->type,
            'options'  => $question->options ?? [],
            'required' => $question->required,
            'position' => $question->position,
        ]]);
    }

    public function updateQuestion(Request $request, Checkpoint $checkpoint, CheckpointQuestion $question): JsonResponse
    {
        abort_if($checkpoint->tenant_id !== $this->tenantId(), 403);
        abort_if($question->checkpoint_id !== $checkpoint->id, 404);

        $validated = $request->validate([
            'label'    => ['sometimes', 'required', 'string', 'max:500'],
            'type'     => ['sometimes', 'string', 'in:text,textarea,radio,checkbox,scale,date'],
            'options'  => ['nullable', 'array'],
            'options.*'=> ['string', 'max:255'],
            'required' => ['nullable', 'boolean'],
        ]);

        $question->update($validated);

        return response()->json(['success' => true, 'question' => [
            'id'       => $question->id,
            'label'    => $question->label,
            'type'     => $question->type,
            'options'  => $question->options ?? [],
            'required' => $question->required,
            'position' => $question->position,
        ]]);
    }

    public function destroyQuestion(Checkpoint $checkpoint, CheckpointQuestion $question): JsonResponse
    {
        abort_if($checkpoint->tenant_id !== $this->tenantId(), 403);
        abort_if($question->checkpoint_id !== $checkpoint->id, 404);
        $question->delete();
        return response()->json(['success' => true]);
    }

    public function reorderQuestions(Request $request, Checkpoint $checkpoint): JsonResponse
    {
        abort_if($checkpoint->tenant_id !== $this->tenantId(), 403);
        $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']]);
        foreach ($request->ids as $pos => $id) {
            $checkpoint->questions()->where('id', $id)->update(['position' => $pos]);
        }
        return response()->json(['success' => true]);
    }

    // ─── Respostas ─────────────────────────────────────────────────────────────

    public function responses(Request $request, Checkpoint $checkpoint): JsonResponse
    {
        abort_if($checkpoint->tenant_id !== $this->tenantId(), 403);

        $responses = CheckpointResponse::where('checkpoint_id', $checkpoint->id)
            ->with(['user:id,name,email,avatar', 'answers'])
            ->orderByDesc('completed_at')
            ->paginate(20);

        $questions = $checkpoint->questions()->get(['id', 'label', 'type', 'options']);

        // Análise por pergunta
        $analysis = $questions->map(function ($q) use ($checkpoint) {
            $allAnswers = \App\Models\CheckpointResponseAnswer::where('checkpoint_question_id', $q->id)
                ->whereHas('response', fn ($r) => $r->where('checkpoint_id', $checkpoint->id))
                ->pluck('value');

            $distribution = null;
            if (in_array($q->type, ['radio', 'checkbox', 'scale'])) {
                $distribution = $allAnswers->flatMap(function ($v) use ($q) {
                    if ($q->type === 'checkbox') {
                        $decoded = json_decode($v, true);
                        return is_array($decoded) ? $decoded : [$v];
                    }
                    return [$v];
                })->countBy()->sortDesc()->toArray();
            }

            return [
                'id'           => $q->id,
                'label'        => $q->label,
                'type'         => $q->type,
                'total'        => $allAnswers->count(),
                'distribution' => $distribution,
            ];
        });

        return response()->json([
            'responses' => $responses->items(),
            'questions' => $questions,
            'analysis'  => $analysis,
            'total'     => $responses->total(),
            'last_page' => $responses->lastPage(),
        ]);
    }
}
