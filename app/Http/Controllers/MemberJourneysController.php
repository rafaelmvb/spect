<?php

namespace App\Http\Controllers;

use App\Models\Journey;
use App\Models\JourneyStep;
use App\Models\JourneyStepItem;
use App\Models\JourneyStepItemResponse;
use App\Models\JourneyStepProgress;
use App\Services\MemberAreaResolver;
use App\Services\StorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MemberJourneysController extends Controller
{
    public function __construct(protected MemberAreaResolver $resolver) {}

    private function getProduct(Request $request)
    {
        return $request->attributes->get('member_area_product');
    }

    public function index(Request $request, string $slug): Response
    {
        $product  = $this->getProduct($request);
        $user     = $request->user();
        $tenantId = $product->tenant_id;

        $journeys = Journey::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->withCount('steps')
            ->orderBy('position')
            ->get();

        $storage = new StorageService($tenantId);

        $data = $journeys->map(fn (Journey $j) => [
            'id'          => $j->id,
            'name'        => $j->name,
            'slug'        => $j->slug,
            'description' => $j->description,
            'cover_url'   => $j->cover ? $storage->url($j->cover) : null,
            'steps_count' => $j->steps_count ?? 0,
        ]);

        return Inertia::render('MemberAreaApp/Jornadas', [
            'product'  => ['id' => $product->id, 'name' => $product->name, 'slug' => $product->checkout_slug],
            'config'   => $product->member_area_config,
            'journeys' => $data,
            'base_url' => $this->baseUrl($product, $request),
            'slug'     => $slug,
        ]);
    }

    public function show(Request $request, string $slug, string $journeyId): Response
    {
        $product   = $this->getProduct($request);
        $user      = $request->user();
        $journeyId = (int) $journeyId;

        $journey = Journey::where('id', $journeyId)
            ->where('tenant_id', $product->tenant_id)
            ->where('is_active', true)
            ->firstOrFail();

        $storage = new StorageService($product->tenant_id);

        // Load steps with items ordered by position
        $stepsCollection = $journey->steps()->orderBy('position')->with('items')->get();

        // User's responses for all items in this journey
        $allItemIds = $stepsCollection->flatMap(fn ($s) => $s->items->pluck('id'));
        $responses  = JourneyStepItemResponse::where('user_id', $user->id)
            ->whereIn('journey_step_item_id', $allItemIds)
            ->get()
            ->keyBy('journey_step_item_id');

        // Completed step IDs
        $completedStepIds = JourneyStepProgress::where('user_id', $user->id)
            ->where('journey_id', $journey->id)
            ->pluck('journey_step_id')
            ->all();

        $stepsData = $stepsCollection->values()->map(function ($step, int $index) use ($responses, $completedStepIds, $stepsCollection) {
            // Unlock check for sequential steps
            $unlocked = true;
            if ($step->unlock_type === 'sequencial' && $index > 0) {
                $prevStep = $stepsCollection->values()->get($index - 1);
                $unlocked = $prevStep && in_array($prevStep->id, $completedStepIds);
            }

            $items = $step->items->map(function ($item) use ($responses, $unlocked) {
                $response = $responses->get($item->id);
                return [
                    'id'         => $item->id,
                    'type'       => $item->type,
                    'title'      => $item->title,
                    'data'       => $unlocked ? $item->data : null,
                    'position'   => $item->position,
                    'completed'  => $response !== null,
                    'answer'     => $response?->answer,
                    'is_correct' => $response?->is_correct,
                ];
            })->values()->all();

            $totalItems = count($items);
            $doneItems  = collect($items)->where('completed', true)->count();

            return [
                'id'          => $step->id,
                'title'       => $step->title,
                'description' => $step->description,
                'position'    => $step->position,
                'unlock_type' => $step->unlock_type,
                'unlocked'    => $unlocked,
                'completed'   => in_array($step->id, $completedStepIds),
                'total_items' => $totalItems,
                'done_items'  => $doneItems,
                'items'       => $items,
            ];
        })->all();

        return Inertia::render('MemberAreaApp/Jornada', [
            'product'  => ['id' => $product->id, 'name' => $product->name, 'slug' => $product->checkout_slug],
            'config'   => $product->member_area_config,
            'journey'  => [
                'id'          => $journey->id,
                'name'        => $journey->name,
                'description' => $journey->description,
                'cover_url'   => $journey->cover ? $storage->url($journey->cover) : null,
            ],
            'steps'    => $stepsData,
            'base_url' => $this->baseUrl($product, $request),
            'slug'     => $slug,
        ]);
    }

    public function submitStepItem(Request $request, string $slug, int $journeyId, int $stepId, int $itemId): JsonResponse
    {
        $product = $this->getProduct($request);
        $user    = $request->user();

        $journey = Journey::where('id', $journeyId)
            ->where('tenant_id', $product->tenant_id)
            ->where('is_active', true)
            ->firstOrFail();

        $step = JourneyStep::where('id', $stepId)
            ->where('journey_id', $journey->id)
            ->firstOrFail();

        $item = JourneyStepItem::where('id', $itemId)
            ->where('journey_step_id', $step->id)
            ->firstOrFail();

        $validated = $request->validate(['answer' => ['nullable', 'array']]);
        $answer    = $validated['answer'] ?? null;

        // Determine is_correct for quiz
        $isCorrect = null;
        if ($item->type === 'quiz' && $answer !== null && array_key_exists('option_index', $answer)) {
            $idx     = (int) $answer['option_index'];
            $options = $item->data['options'] ?? [];
            $isCorrect = isset($options[$idx]) && ($options[$idx]['is_correct'] ?? false) === true;
        }

        // Upsert: for quiz/question allow updating; for video idempotent create
        $existing = JourneyStepItemResponse::where('journey_step_item_id', $item->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            if ($item->type !== 'video') {
                $existing->update(['answer' => $answer, 'is_correct' => $isCorrect, 'completed_at' => now()]);
            }
        } else {
            JourneyStepItemResponse::create([
                'journey_step_item_id' => $item->id,
                'user_id'              => $user->id,
                'answer'               => $answer,
                'is_correct'           => $isCorrect,
                'completed_at'         => now(),
            ]);
        }

        // Auto-complete step if all items have responses
        $totalItems = JourneyStepItem::where('journey_step_id', $step->id)->count();
        $doneItems  = JourneyStepItemResponse::where('user_id', $user->id)
            ->whereHas('item', fn ($q) => $q->where('journey_step_id', $step->id))
            ->count();

        $stepCompleted = false;
        if ($totalItems > 0 && $doneItems >= $totalItems) {
            JourneyStepProgress::firstOrCreate(
                ['journey_id' => $journey->id, 'journey_step_id' => $step->id, 'user_id' => $user->id],
                ['completed_at' => now()]
            );
            $stepCompleted = true;
        }

        return response()->json([
            'success'        => true,
            'is_correct'     => $isCorrect,
            'step_completed' => $stepCompleted,
            'done_items'     => $doneItems,
            'total_items'    => $totalItems,
        ]);
    }

    public function completeStep(Request $request, string $slug, int $journeyId, int $stepId): JsonResponse
    {
        $product = $this->getProduct($request);
        $user    = $request->user();

        $journey = Journey::where('id', $journeyId)
            ->where('tenant_id', $product->tenant_id)
            ->firstOrFail();

        $step = JourneyStep::where('id', $stepId)
            ->where('journey_id', $journey->id)
            ->firstOrFail();

        JourneyStepProgress::firstOrCreate(
            ['journey_id' => $journey->id, 'journey_step_id' => $step->id, 'user_id' => $user->id],
            ['completed_at' => now()]
        );

        return response()->json(['success' => true]);
    }

    private function baseUrl($product, Request $request): string
    {
        return $this->resolver->baseUrlForProduct($product)
            ?? url("/m/{$product->checkout_slug}");
    }
}
