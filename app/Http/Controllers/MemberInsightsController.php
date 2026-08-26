<?php

namespace App\Http\Controllers;

use App\Models\AiConfig;
use App\Models\AiInsight;
use App\Services\AiService;
use App\Services\MemberAreaResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MemberInsightsController extends Controller
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

        $insights = AiInsight::where('tenant_id', $tenantId)
            ->where('is_dismissed', false)
            ->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $user->id))
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn ($i) => [
                'id'         => $i->id,
                'type'       => $i->type,
                'title'      => $i->title,
                'content'    => $i->content,
                'is_read'    => $i->is_read,
                'created_at' => $i->created_at->format('d/m/Y H:i'),
            ]);

        // Marcar os não-lidos deste usuário como lidos
        AiInsight::where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return Inertia::render('MemberAreaApp/Insights', [
            'product'  => ['id' => $product->id, 'name' => $product->name, 'slug' => $product->checkout_slug],
            'config'   => $product->member_area_config,
            'insights' => $insights,
            'base_url' => $this->baseUrl($product, $request),
            'slug'     => $slug,
        ]);
    }

    public function generate(Request $request, string $slug): JsonResponse
    {
        $product  = $this->getProduct($request);
        $user     = $request->user();
        $tenantId = $product->tenant_id;

        $aiService = new AiService($tenantId);
        if (! $aiService->available()) {
            return response()->json(['error' => 'IA não configurada.'], 503);
        }

        $config = AiConfig::where('tenant_id', $tenantId)
            ->where('context', 'insights')
            ->where('is_active', true)
            ->first() ?? new AiConfig([
                'context'       => 'insights',
                'system_prompt' => 'Você é um assistente de saúde mental. Gere um insight personalizado, empático e motivador para o aluno com base em seu progresso. Seja conciso (máx. 3 parágrafos). Não forneça diagnósticos.',
                'model'         => 'gpt-4o-mini',
                'temperature'   => 0.8,
                'max_tokens'    => 512,
            ]);

        $userContext = $aiService->buildUserContext($user, $product->id);

        try {
            $reply = $aiService->complete([
                ['role' => 'system', 'content' => $config->system_prompt],
                ['role' => 'user',   'content' => "Gere um insight personalizado para este aluno:\n\n{$userContext}"],
            ], $config, $tenantId, $user->id);

            $insight = AiInsight::create([
                'tenant_id'  => $tenantId,
                'user_id'    => $user->id,
                'type'       => 'personalized',
                'title'      => 'Insight personalizado',
                'content'    => $reply,
                'is_read'    => false,
                'is_dismissed' => false,
            ]);

            return response()->json([
                'success' => true,
                'insight' => [
                    'id'         => $insight->id,
                    'type'       => $insight->type,
                    'title'      => $insight->title,
                    'content'    => $insight->content,
                    'is_read'    => false,
                    'created_at' => $insight->created_at->format('d/m/Y H:i'),
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Erro ao gerar insight.'], 500);
        }
    }

    public function dismiss(Request $request, string $slug, AiInsight $insight): JsonResponse
    {
        $product = $this->getProduct($request);
        $user    = $request->user();

        // Só pode dispensar o próprio insight
        abort_if($insight->tenant_id !== $product->tenant_id, 403);
        abort_if($insight->user_id && $insight->user_id !== $user->id, 403);

        $insight->update(['is_dismissed' => true]);

        return response()->json(['success' => true]);
    }

    private function baseUrl($product, Request $request): string
    {
        return $this->resolver->baseUrlForProduct($product)
            ?? url("/m/{$product->checkout_slug}");
    }
}
