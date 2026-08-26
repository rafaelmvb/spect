<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AiController extends Controller
{
    private function tenantId(): ?int
    {
        return auth()->user()->tenant_id;
    }

    private function loadPrompts(int $tenantId): array
    {
        $raw = Setting::get('custom_ai_prompts', '[]', $tenantId);
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function savePrompts(int $tenantId, array $prompts): void
    {
        Setting::set('custom_ai_prompts', json_encode(array_values($prompts)), $tenantId);
    }

    public function index(): Response
    {
        return Inertia::render('Ai/Index', [
            'prompts' => $this->loadPrompts($this->tenantId()),
        ]);
    }

    public function storePrompt(Request $request): JsonResponse
    {
        $v = $request->validate([
            'type'    => ['required', 'string', 'in:chat,report'],
            'content' => ['required', 'string', 'max:5000'],
        ]);

        $tenantId = $this->tenantId();
        $prompts  = $this->loadPrompts($tenantId);

        $new = [
            'id'      => (string) Str::uuid(),
            'type'    => $v['type'],
            'content' => $v['content'],
        ];

        $prompts[] = $new;
        $this->savePrompts($tenantId, $prompts);

        return response()->json(['success' => true, 'prompt' => $new]);
    }

    public function updatePrompt(Request $request, string $id): JsonResponse
    {
        $v = $request->validate([
            'content' => ['required', 'string', 'max:5000'],
        ]);

        $tenantId = $this->tenantId();
        $prompts  = $this->loadPrompts($tenantId);
        $updated  = null;

        foreach ($prompts as &$p) {
            if ($p['id'] === $id) {
                $p['content'] = $v['content'];
                $updated = $p;
                break;
            }
        }
        unset($p);

        if (! $updated) {
            return response()->json(['error' => 'Prompt não encontrado.'], 404);
        }

        $this->savePrompts($tenantId, $prompts);

        return response()->json(['success' => true, 'prompt' => $updated]);
    }

    public function destroyPrompt(string $id): JsonResponse
    {
        $tenantId = $this->tenantId();
        $prompts  = $this->loadPrompts($tenantId);
        $prompts  = array_filter($prompts, fn ($p) => $p['id'] !== $id);
        $this->savePrompts($tenantId, $prompts);

        return response()->json(['success' => true]);
    }
}
