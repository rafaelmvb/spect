<?php

namespace App\Http\Controllers;

use App\Models\Checkpoint;
use App\Models\CheckpointResponse;
use App\Models\Journey;
use App\Models\JourneyReport;
use App\Models\JourneyStep;
use App\Models\JourneyStepProgress;
use App\Models\NeuroUserScore;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class JourneyReportsController extends Controller
{
    private function tenantId(): int
    {
        return auth()->user()->tenant_id;
    }

    // ── Admin: lista relatórios ──────────────────────────────────────────────
    public function index(Request $request): Response
    {
        $journeys = Journey::forTenant($this->tenantId())
            ->with('steps')
            ->orderBy('name')
            ->get()
            ->map(fn ($j) => [
                'id'          => $j->id,
                'name'        => $j->name,
                'total_steps' => $j->steps->count(),
            ]);

        $journeyId = $request->integer('journey_id') ?: ($journeys->first()['id'] ?? null);

        $reports = collect();
        if ($journeyId) {
            $journey  = Journey::where('tenant_id', $this->tenantId())->findOrFail($journeyId);
            $stepIds  = $journey->steps->pluck('id');
            $totalSteps = $stepIds->count();

            // Usuários que têm pelo menos 1 progresso nesta jornada
            $rows = JourneyStepProgress::whereIn('journey_step_id', $stepIds)
                ->where('journey_id', $journeyId)
                ->with('user')
                ->get()
                ->groupBy('user_id');

            $existingReports = JourneyReport::where('journey_id', $journeyId)
                ->where('tenant_id', $this->tenantId())
                ->get()
                ->keyBy('user_id');

            $reports = $rows->map(function ($progresses, $userId) use ($totalSteps, $existingReports) {
                $user      = $progresses->first()->user;
                $completed = $progresses->whereNotNull('completed_at')->count();
                $report    = $existingReports->get($userId);
                return [
                    'user_id'        => $userId,
                    'user_name'      => $user?->name ?? '—',
                    'user_email'     => $user?->email ?? '—',
                    'completed_steps'=> $completed,
                    'total_steps'    => $totalSteps,
                    'pct'            => $totalSteps > 0 ? round($completed / $totalSteps * 100) : 0,
                    'report_id'      => $report?->id,
                    'report_status'  => $report?->status ?? null,
                    'report_date'    => $report?->generated_at?->format('d/m/Y H:i'),
                ];
            })->values();
        }

        return Inertia::render('Relatorios/Trilhas', [
            'journeys'    => $journeys,
            'reports'     => $reports,
            'journey_id'  => $journeyId,
        ]);
    }

    // ── Admin: gera/regenera relatório ──────────────────────────────────────
    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'user_id'    => ['required', 'integer'],
            'journey_id' => ['required', 'integer'],
        ]);

        $journey = Journey::where('tenant_id', $this->tenantId())
            ->with('steps.checkpoint.questions')
            ->findOrFail($request->integer('journey_id'));

        $user = User::findOrFail($request->integer('user_id'));

        $data = $this->buildReportData($journey, $user);

        $report = JourneyReport::updateOrCreate(
            [
                'tenant_id'  => $this->tenantId(),
                'user_id'    => $user->id,
                'journey_id' => $journey->id,
            ],
            [
                'status'       => JourneyReport::STATUS_READY,
                'report_data'  => $data,
                'generated_at' => now(),
            ]
        );

        return response()->json(['report_id' => $report->id, 'success' => true]);
    }

    // ── Admin: visualiza/edita relatório ────────────────────────────────────
    public function show(JourneyReport $report): Response
    {
        abort_if($report->tenant_id !== $this->tenantId(), 403);

        $report->load('user', 'journey');

        return Inertia::render('Relatorios/TrilhaRelatorio', [
            'report' => [
                'id'             => $report->id,
                'status'         => $report->status,
                'generated_at'   => $report->generated_at?->format('d/m/Y H:i'),
                'published_at'   => $report->published_at?->format('d/m/Y H:i'),
                'admin_notes'    => $report->admin_notes,
                'ai_summary'     => $report->ai_summary,
                'custom_content' => $report->custom_content,
                'report_data'    => $report->report_data,
                'user_name'      => $report->user?->name,
                'user_email'     => $report->user?->email,
                'journey_name'   => $report->journey?->name,
            ],
        ]);
    }

    // ── Admin: salva edição ─────────────────────────────────────────────────
    public function update(Request $request, JourneyReport $report): JsonResponse
    {
        abort_if($report->tenant_id !== $this->tenantId(), 403);

        $request->validate([
            'admin_notes'    => ['nullable', 'string', 'max:5000'],
            'custom_content' => ['nullable', 'string', 'max:10000'],
        ]);

        $report->update($request->only('admin_notes', 'custom_content'));

        return response()->json(['success' => true]);
    }

    // ── Admin: publica relatório ─────────────────────────────────────────────
    public function publish(JourneyReport $report): JsonResponse
    {
        abort_if($report->tenant_id !== $this->tenantId(), 403);

        $report->update([
            'status'       => JourneyReport::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        return response()->json(['success' => true, 'status' => 'published']);
    }

    // ── Admin: volta para draft ──────────────────────────────────────────────
    public function unpublish(JourneyReport $report): JsonResponse
    {
        abort_if($report->tenant_id !== $this->tenantId(), 403);

        $report->update(['status' => JourneyReport::STATUS_READY, 'published_at' => null]);

        return response()->json(['success' => true, 'status' => 'ready']);
    }

    // ── Membro: ver próprio relatório ───────────────────────────────────────
    public function memberShow(Request $request, string $slug, int $journeyId): Response
    {
        $user    = auth()->user();
        $product = $request->attributes->get('member_area_product');

        $report = JourneyReport::where('user_id', $user->id)
            ->where('journey_id', $journeyId)
            ->where('status', JourneyReport::STATUS_PUBLISHED)
            ->firstOrFail();

        return Inertia::render('MemberAreaApp/MeuRelatorio', [
            'product' => ['id' => $product?->id, 'name' => $product?->name ?? ''],
            'config'  => $product?->member_area_config ?? [],
            'slug'    => $slug,
            'report'  => [
                'id'             => $report->id,
                'generated_at'   => $report->generated_at?->format('d/m/Y'),
                'admin_notes'    => $report->admin_notes,
                'ai_summary'     => $report->ai_summary,
                'custom_content' => $report->custom_content,
                'report_data'    => $report->report_data,
                'journey_name'   => $report->journey?->name ?? $report->report_data['journey']['name'] ?? '—',
            ],
        ]);
    }

    // ── Membro: lista jornadas com relatório publicado ──────────────────────
    public function memberIndex(Request $request, string $slug): Response
    {
        $user    = auth()->user();
        $product = $request->attributes->get('member_area_product');

        $reports = JourneyReport::where('user_id', $user->id)
            ->where('status', JourneyReport::STATUS_PUBLISHED)
            ->with('journey')
            ->get()
            ->map(fn ($r) => [
                'id'           => $r->id,
                'journey_id'   => $r->journey_id,
                'journey_name' => $r->journey?->name ?? '—',
                'generated_at' => $r->generated_at?->format('d/m/Y'),
                'pct'          => $r->report_data['completion_pct'] ?? 0,
            ]);

        return Inertia::render('MemberAreaApp/MeusRelatorios', [
            'product' => ['id' => $product?->id, 'name' => $product?->name ?? ''],
            'config'  => $product?->member_area_config ?? [],
            'slug'    => $slug,
            'reports' => $reports,
        ]);
    }

    // ── Builder interno do relatório ────────────────────────────────────────
    private function buildReportData(Journey $journey, User $user): array
    {
        $steps      = $journey->steps;
        $totalSteps = $steps->count();

        $progressMap = JourneyStepProgress::where('user_id', $user->id)
            ->where('journey_id', $journey->id)
            ->get()
            ->keyBy('journey_step_id');

        $stepsData = $steps->map(function (JourneyStep $step) use ($user, $progressMap) {
            $progress    = $progressMap->get($step->id);
            $completedAt = $progress?->completed_at?->format('d/m/Y H:i');

            $checkpointData = null;
            if ($step->checkpoint) {
                $checkpoint = $step->checkpoint;
                $response   = CheckpointResponse::where('checkpoint_id', $checkpoint->id)
                    ->where('user_id', $user->id)
                    ->with('answers.question')
                    ->first();

                $questions = $checkpoint->questions->map(function ($q) use ($response) {
                    $answer = $response?->answers->firstWhere('checkpoint_question_id', $q->id);
                    return [
                        'label'   => $q->label,
                        'type'    => $q->type,
                        'options' => $q->options,
                        'answer'  => $answer?->value,
                    ];
                })->values()->toArray();

                $checkpointData = [
                    'name'          => $checkpoint->title ?? 'Checkpoint',
                    'responded_at'  => $response?->completed_at?->format('d/m/Y H:i'),
                    'questions'     => $questions,
                ];
            }

            return [
                'id'           => $step->id,
                'title'        => $step->title,
                'description'  => $step->description,
                'completed'    => $progress !== null && $progress->completed_at !== null,
                'completed_at' => $completedAt,
                'checkpoint'   => $checkpointData,
            ];
        })->values()->toArray();

        $completedSteps = collect($stepsData)->where('completed', true)->count();

        // Scores NeuroMap do usuário
        $neuroScores = NeuroUserScore::where('user_id', $user->id)
            ->with('indicator.area')
            ->orderByDesc('scored_at')
            ->get()
            ->groupBy(fn ($s) => $s->indicator?->name ?? 'Outro')
            ->map(fn ($g, $indicatorName) => [
                'indicator' => $indicatorName,
                'area'      => $g->first()->indicator?->area?->name ?? '—',
                'value'     => (float) $g->first()->value,
                'scored_at' => $g->first()->scored_at?->format('d/m/Y'),
            ])
            ->values()
            ->toArray();

        return [
            'student' => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
            ],
            'journey' => [
                'id'          => $journey->id,
                'name'        => $journey->name,
                'description' => $journey->description,
            ],
            'total_steps'    => $totalSteps,
            'completed_steps'=> $completedSteps,
            'completion_pct' => $totalSteps > 0 ? round($completedSteps / $totalSteps * 100) : 0,
            'steps'          => $stepsData,
            'neuro_scores'   => $neuroScores,
            'generated_at'   => now()->toIso8601String(),
        ];
    }
}
