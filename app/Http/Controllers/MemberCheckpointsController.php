<?php

namespace App\Http\Controllers;

use App\Models\Checkpoint;
use App\Models\CheckpointResponse;
use App\Models\CheckpointResponseAnswer;
use App\Services\MemberAreaResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MemberCheckpointsController extends Controller
{
    public function __construct(protected MemberAreaResolver $resolver) {}

    private function getProduct(Request $request)
    {
        return $request->attributes->get('member_area_product');
    }

    public function show(Request $request, string $slug, int $checkpointId): Response
    {
        $product    = $this->getProduct($request);
        $user       = $request->user();

        $checkpoint = Checkpoint::where('id', $checkpointId)
            ->where('tenant_id', $product->tenant_id)
            ->where('is_active', true)
            ->with('questions')
            ->firstOrFail();

        $existing = CheckpointResponse::where('checkpoint_id', $checkpoint->id)
            ->where('user_id', $user->id)
            ->with('answers')
            ->first();

        $questions = $checkpoint->questions->sortBy('position')->map(fn ($q) => [
            'id'       => $q->id,
            'label'    => $q->label,
            'type'     => $q->type,
            'options'  => $q->options ?? [],
            'required' => $q->required,
        ])->values()->all();

        return Inertia::render('MemberAreaApp/Checkpoint', [
            'product'    => ['id' => $product->id, 'name' => $product->name, 'slug' => $product->checkout_slug],
            'config'     => $product->member_area_config,
            'checkpoint' => [
                'id'          => $checkpoint->id,
                'title'       => $checkpoint->title,
                'description' => $checkpoint->description,
            ],
            'questions'   => $questions,
            'already_done' => $existing !== null,
            'answers'     => $existing ? $existing->answers->keyBy('checkpoint_question_id')
                ->map(fn ($a) => $a->value)->all() : [],
            'base_url'    => $this->baseUrl($product, $request),
            'slug'        => $slug,
        ]);
    }

    public function submit(Request $request, string $slug, int $checkpointId): JsonResponse
    {
        $product    = $this->getProduct($request);
        $user       = $request->user();

        $checkpoint = Checkpoint::where('id', $checkpointId)
            ->where('tenant_id', $product->tenant_id)
            ->where('is_active', true)
            ->with('questions')
            ->firstOrFail();

        // Idempotente — só aceita uma resposta por usuário
        if (CheckpointResponse::where('checkpoint_id', $checkpoint->id)->where('user_id', $user->id)->exists()) {
            return response()->json(['success' => true, 'already_done' => true]);
        }

        $v = $request->validate(['answers' => ['required', 'array']]);

        $response = CheckpointResponse::create([
            'checkpoint_id' => $checkpoint->id,
            'user_id'       => $user->id,
            'completed_at'  => now(),
        ]);

        foreach ($checkpoint->questions as $question) {
            $value = $v['answers'][$question->id] ?? null;
            if ($value === null && ! $question->required) continue;

            CheckpointResponseAnswer::create([
                'checkpoint_response_id'  => $response->id,
                'checkpoint_question_id'  => $question->id,
                'value'                   => is_array($value) ? json_encode($value) : (string) $value,
            ]);
        }

        return response()->json(['success' => true, 'already_done' => false]);
    }

    private function baseUrl($product, Request $request): string
    {
        return $this->resolver->baseUrlForProduct($product)
            ?? url("/m/{$product->checkout_slug}");
    }
}
