<?php

namespace App\Http\Controllers;

use App\Models\Journey;
use App\Models\JourneyStep;
use App\Models\JourneyStepItem;
use App\Services\StorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class JourneysController extends Controller
{
    private function tenantId(): ?int
    {
        return auth()->user()->tenant_id;
    }

    private function formatJourney(Journey $j, bool $withSteps = false): array
    {
        $data = [
            'id'          => $j->id,
            'name'        => $j->name,
            'slug'        => $j->slug,
            'description' => $j->description,
            'cover_url'   => $j->cover_url,
            'is_active'   => $j->is_active,
            'position'    => $j->position,
            'steps_count' => $j->steps()->count(),
            'created_at'  => $j->created_at->format('d/m/Y'),
        ];

        if ($withSteps) {
            $data['steps'] = $j->steps->map(fn ($s) => $this->formatStep($s, true))->values()->toArray();
        }

        return $data;
    }

    private function formatStep(JourneyStep $s, bool $withItems = false): array
    {
        $data = [
            'id'                => $s->id,
            'journey_id'        => $s->journey_id,
            'title'             => $s->title,
            'description'       => $s->description,
            'cover_url'         => $s->cover_url,
            'position'          => $s->position,
            'unlock_type'       => $s->unlock_type,
            'unlock_after_days' => $s->unlock_after_days,
        ];

        if ($withItems) {
            $data['items'] = $s->items->map(fn ($item) => [
                'id'       => $item->id,
                'type'     => $item->type,
                'title'    => $item->title,
                'data'     => $item->data,
                'position' => $item->position,
            ])->values()->toArray();
        }

        return $data;
    }

    public function index(Request $request): Response
    {
        $tenantId = $this->tenantId();
        $search   = trim((string) ($request->query('q') ?? ''));

        $query = Journey::forTenant($tenantId)->withCount('steps')->orderBy('position')->orderBy('name');

        if ($search !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';
            $query->where('name', 'like', $like);
        }

        $journeys = $query->paginate(20)->withQueryString()
            ->through(fn (Journey $j) => array_merge($this->formatJourney($j), ['steps_count' => $j->steps_count]));

        $stats = [
            'total'    => Journey::forTenant($tenantId)->count(),
            'ativas'   => Journey::forTenant($tenantId)->where('is_active', true)->count(),
            'inativas' => Journey::forTenant($tenantId)->where('is_active', false)->count(),
        ];

        return Inertia::render('Journeys/Index', [
            'journeys' => $journeys,
            'stats'    => $stats,
            'q'        => $search !== '' ? $search : null,
        ]);
    }

    public function show(Journey $journey): JsonResponse
    {
        abort_if($journey->tenant_id !== $this->tenantId(), 403);
        $journey->load('steps.items');
        return response()->json(['journey' => $this->formatJourney($journey, true)]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_active'   => ['nullable', 'boolean'],
            'cover'       => ['nullable', 'file', 'image', 'max:4096'],
        ]);

        $tenantId  = $this->tenantId();
        $coverPath = null;
        if ($request->hasFile('cover')) {
            $coverPath = (new StorageService($tenantId))
                ->putFile('journeys/' . ($tenantId ?? 'global'), $request->file('cover'));
        }

        $maxPos = Journey::forTenant($tenantId)->max('position') ?? -1;

        $journey = Journey::create([
            'tenant_id'   => $tenantId,
            'name'        => $validated['name'],
            'slug'        => Str::slug($validated['name']),
            'description' => $validated['description'] ?? null,
            'cover'       => $coverPath,
            'is_active'   => $validated['is_active'] ?? true,
            'product_ids' => [],
            'position'    => $maxPos + 1,
        ]);

        return response()->json(['success' => true, 'journey' => $this->formatJourney($journey)]);
    }

    public function update(Request $request, Journey $journey): JsonResponse
    {
        abort_if($journey->tenant_id !== $this->tenantId(), 403);

        $validated = $request->validate([
            'name'        => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_active'   => ['nullable', 'boolean'],
            'cover'       => ['nullable', 'file', 'image', 'max:4096'],
        ]);

        if ($request->hasFile('cover')) {
            $validated['cover'] = (new StorageService($this->tenantId()))
                ->putFile('journeys/' . ($this->tenantId() ?? 'global'), $request->file('cover'));
        } else {
            unset($validated['cover']);
        }

        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $journey->update($validated);

        return response()->json(['success' => true, 'journey' => $this->formatJourney($journey->fresh())]);
    }

    public function toggleActive(Journey $journey): JsonResponse
    {
        abort_if($journey->tenant_id !== $this->tenantId(), 403);
        $journey->update(['is_active' => ! $journey->is_active]);
        return response()->json(['success' => true, 'is_active' => $journey->is_active]);
    }

    public function reorder(Request $request): JsonResponse
    {
        $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']]);
        $tenantId = $this->tenantId();
        foreach ($request->ids as $pos => $id) {
            Journey::forTenant($tenantId)->where('id', $id)->update(['position' => $pos]);
        }
        return response()->json(['success' => true]);
    }

    public function destroy(Journey $journey): JsonResponse
    {
        abort_if($journey->tenant_id !== $this->tenantId(), 403);
        $journey->delete();
        return response()->json(['success' => true]);
    }

    // ─── Steps ───────────────────────────────────────────────────────────────

    public function storeStep(Request $request, Journey $journey): JsonResponse
    {
        abort_if($journey->tenant_id !== $this->tenantId(), 403);

        $validated = $request->validate([
            'title'             => ['required', 'string', 'max:255'],
            'description'       => ['nullable', 'string', 'max:5000'],
            'unlock_type'       => ['nullable', 'string', 'in:livre,sequencial,manual'],
            'unlock_after_days' => ['nullable', 'integer', 'min:0'],
        ]);

        $maxPos = $journey->steps()->max('position') ?? -1;

        $step = $journey->steps()->create([
            'title'             => $validated['title'],
            'description'       => $validated['description'] ?? null,
            'unlock_type'       => $validated['unlock_type'] ?? 'livre',
            'unlock_after_days' => $validated['unlock_after_days'] ?? null,
            'position'          => $maxPos + 1,
        ]);

        $step->load('items');

        return response()->json(['success' => true, 'step' => $this->formatStep($step, true)]);
    }

    public function updateStep(Request $request, Journey $journey, JourneyStep $step): JsonResponse
    {
        abort_if($journey->tenant_id !== $this->tenantId(), 403);
        abort_if($step->journey_id !== $journey->id, 404);

        $validated = $request->validate([
            'title'             => ['sometimes', 'required', 'string', 'max:255'],
            'description'       => ['nullable', 'string', 'max:5000'],
            'unlock_type'       => ['nullable', 'string', 'in:livre,sequencial,manual'],
            'unlock_after_days' => ['nullable', 'integer', 'min:0'],
        ]);

        $step->update($validated);
        $step->load('items');

        return response()->json(['success' => true, 'step' => $this->formatStep($step->fresh(), true)]);
    }

    public function destroyStep(Journey $journey, JourneyStep $step): JsonResponse
    {
        abort_if($journey->tenant_id !== $this->tenantId(), 403);
        abort_if($step->journey_id !== $journey->id, 404);
        $step->delete();
        return response()->json(['success' => true]);
    }

    public function reorderSteps(Request $request, Journey $journey): JsonResponse
    {
        abort_if($journey->tenant_id !== $this->tenantId(), 403);
        $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']]);
        foreach ($request->ids as $pos => $id) {
            $journey->steps()->where('id', $id)->update(['position' => $pos]);
        }
        return response()->json(['success' => true]);
    }

    // ─── Step items ──────────────────────────────────────────────────────────

    public function storeStepItem(Request $request, Journey $journey, JourneyStep $step): JsonResponse
    {
        abort_if($journey->tenant_id !== $this->tenantId(), 403);
        abort_if($step->journey_id !== $journey->id, 404);

        $validated = $request->validate([
            'type'  => ['required', 'string', 'in:video,quiz,question'],
            'title' => ['nullable', 'string', 'max:255'],
            'data'  => ['required', 'array'],
        ]);

        $maxPos = $step->items()->max('position') ?? -1;

        $item = $step->items()->create([
            'type'     => $validated['type'],
            'title'    => $validated['title'] ?? null,
            'data'     => $validated['data'],
            'position' => $maxPos + 1,
        ]);

        return response()->json(['success' => true, 'item' => [
            'id'       => $item->id,
            'type'     => $item->type,
            'title'    => $item->title,
            'data'     => $item->data,
            'position' => $item->position,
        ]]);
    }

    public function updateStepItem(Request $request, Journey $journey, JourneyStep $step, JourneyStepItem $item): JsonResponse
    {
        abort_if($journey->tenant_id !== $this->tenantId(), 403);
        abort_if($step->journey_id !== $journey->id, 404);
        abort_if($item->journey_step_id !== $step->id, 404);

        $validated = $request->validate([
            'type'  => ['sometimes', 'required', 'string', 'in:video,quiz,question'],
            'title' => ['nullable', 'string', 'max:255'],
            'data'  => ['sometimes', 'required', 'array'],
        ]);

        $item->update($validated);

        return response()->json(['success' => true, 'item' => [
            'id'       => $item->id,
            'type'     => $item->type,
            'title'    => $item->title,
            'data'     => $item->data,
            'position' => $item->position,
        ]]);
    }

    public function destroyStepItem(Journey $journey, JourneyStep $step, JourneyStepItem $item): JsonResponse
    {
        abort_if($journey->tenant_id !== $this->tenantId(), 403);
        abort_if($step->journey_id !== $journey->id, 404);
        abort_if($item->journey_step_id !== $step->id, 404);
        $item->delete();
        return response()->json(['success' => true]);
    }

    public function reorderStepItems(Request $request, Journey $journey, JourneyStep $step): JsonResponse
    {
        abort_if($journey->tenant_id !== $this->tenantId(), 403);
        abort_if($step->journey_id !== $journey->id, 404);
        $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']]);
        foreach ($request->ids as $pos => $id) {
            $step->items()->where('id', $id)->update(['position' => $pos]);
        }
        return response()->json(['success' => true]);
    }
}
