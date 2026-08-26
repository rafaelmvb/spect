<?php

namespace App\Http\Controllers;

use App\Models\AiChatMessage;
use App\Models\AiConfig;
use App\Models\MemberLesson;
use App\Services\AiService;
use App\Services\YouTubeTranscriptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MemberAiChatController extends Controller
{
    public function show(Request $request, ?string $slug = null): Response
    {
        $user      = auth()->user();
        $sessionId = $request->query('session', \Illuminate\Support\Str::uuid()->toString());

        $messages = AiChatMessage::where('user_id', $user->id)
            ->where('session_id', $sessionId)
            ->whereIn('role', ['user', 'assistant'])
            ->orderBy('created_at')
            ->limit(100)
            ->get()
            ->map(fn ($m) => [
                'id'         => $m->id,
                'role'       => $m->role,
                'content'    => $m->content,
                'created_at' => $m->created_at->format('H:i'),
            ]);

        $product    = $request->attributes->get('member_area_product');
        $tenantId   = $product?->tenant_id ?? auth()->user()->tenant_id;
        $aiService  = new AiService($tenantId);

        return Inertia::render('MemberAreaApp/IaChat', [
            'product'    => ['id' => $product?->id, 'name' => $product?->name ?? ''],
            'config'     => $product?->member_area_config ?? [],
            'slug'       => $slug,
            'session_id' => $sessionId,
            'messages'   => $messages,
            'available'  => $aiService->available(),
        ]);
    }

    public function send(Request $request, ?string $slug = null): JsonResponse
    {
        $validated = $request->validate([
            'message'    => ['required', 'string', 'max:2000'],
            'session_id' => ['required', 'string', 'max:64'],
            'lesson_id'  => ['nullable', 'integer'],
        ]);

        $user      = auth()->user();
        $sessionId = $validated['session_id'];
        $product   = $request->attributes->get('member_area_product');
        $productId = $product?->id;
        $tenantId  = $product?->tenant_id ?? $user->tenant_id;

        $aiService = new AiService($tenantId);

        if (! $aiService->available()) {
            return response()->json(['error' => 'IA não configurada. Contate o suporte.'], 503);
        }

        // Salvar mensagem do usuário
        AiChatMessage::create([
            'user_id'    => $user->id,
            'product_id' => $productId,
            'session_id' => $sessionId,
            'role'       => 'user',
            'content'    => $validated['message'],
        ]);

        // Buscar config de IA para o contexto 'chat'
        $config = AiConfig::where('tenant_id', $tenantId)
            ->where('context', 'chat')
            ->where('is_active', true)
            ->first();

        if (! $config) {
            $config = new AiConfig([
                'context'     => 'chat',
                'model'       => 'gpt-4o-mini',
                'temperature' => 0.7,
                'max_tokens'  => 1024,
            ]);
        }

        // Prompts customizados do admin (type: chat)
        $rawCustom      = \App\Models\Setting::get('custom_ai_prompts', '[]', $tenantId);
        $customPrompts  = json_decode($rawCustom, true) ?? [];
        $adminInstructions = collect($customPrompts)
            ->filter(fn ($p) => ($p['type'] ?? '') === 'chat' && ! empty($p['content']))
            ->pluck('content')
            ->implode("\n\n");

        // Construir contexto do aluno (curso + progresso)
        $userContext = $aiService->buildUserContext($user, $productId ?? '');

        // Contexto da aula atual com transcrição YouTube (se disponível)
        $transcriptContext = $this->buildTranscriptContext($validated['lesson_id'] ?? null, $productId);

        // Montar system prompt: instrução base → admin custom → contexto do aluno → transcrição → regras
        $baseInstruction = $adminInstructions
            ?: ($config->system_prompt ?: 'Você é um assistente educacional empático e prestativo.');

        $systemPrompt = $baseInstruction
            . "\n\n--- DADOS DO ALUNO LOGADO ---\n" . $userContext
            . $transcriptContext
            . "\n\n--- REGRAS DE COMPORTAMENTO ---\n"
            . "1. Responda SOMENTE sobre este aluno (" . $user->name . "), o curso dele e os conteúdos do treinamento.\n"
            . "2. Não discuta dados de outros alunos nem forneça informações de terceiros.\n"
            . "3. Use o nome do aluno ao se dirigir a ele.\n"
            . "4. Se perguntado sobre algo completamente fora do escopo do curso, redirecione gentilmente para temas do treinamento.\n"
            . "5. Quando o aluno perguntar sobre o progresso dele, use os dados acima para responder com precisão.";

        // Histórico recente da sessão
        $history = AiChatMessage::where('user_id', $user->id)
            ->where('session_id', $sessionId)
            ->whereIn('role', ['user', 'assistant'])
            ->orderBy('created_at')
            ->limit(20)
            ->get();

        $messages = [
            [
                'role'    => 'system',
                'content' => $systemPrompt,
            ],
        ];

        foreach ($history as $msg) {
            $messages[] = ['role' => $msg->role, 'content' => $msg->content];
        }

        try {
            $reply = $aiService->complete($messages, $config, $tenantId, $user->id);

            $saved = AiChatMessage::create([
                'user_id'    => $user->id,
                'product_id' => $productId,
                'session_id' => $sessionId,
                'role'       => 'assistant',
                'content'    => $reply,
            ]);

            return response()->json([
                'reply'      => $reply,
                'message_id' => $saved->id,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Erro ao contatar a IA. Tente novamente.'], 500);
        }
    }

    public function history(Request $request, ?string $slug = null): JsonResponse
    {
        $sessionId = $request->query('session_id');

        if (! $sessionId) {
            return response()->json(['messages' => []]);
        }

        $user = auth()->user();

        $messages = AiChatMessage::where('user_id', $user->id)
            ->where('session_id', $sessionId)
            ->whereIn('role', ['user', 'assistant'])
            ->orderBy('created_at')
            ->limit(100)
            ->get()
            ->map(fn ($m) => [
                'id'      => $m->id,
                'role'    => $m->role,
                'content' => $m->content,
            ]);

        return response()->json(['messages' => $messages]);
    }

    /**
     * Retorna a sessão ativa do aluno (a mais recente com mensagens) ou um novo UUID.
     * Nunca persiste nada — a sessão "existe" quando a primeira mensagem é salva.
     */
    public function getSession(Request $request, ?string $slug = null): JsonResponse
    {
        $user      = auth()->user();
        $product   = $request->attributes->get('member_area_product');
        $productId = $product?->id;

        $latest = AiChatMessage::where('user_id', $user->id)
            ->when($productId, fn ($q) => $q->where('product_id', $productId))
            ->latest()
            ->first();

        if ($latest) {
            return response()->json([
                'session_id' => $latest->session_id,
                'is_new'     => false,
            ]);
        }

        return response()->json([
            'session_id' => \Illuminate\Support\Str::uuid()->toString(),
            'is_new'     => true,
        ]);
    }

    /**
     * Gera um novo UUID de sessão. O aluno usará para iniciar uma nova conversa.
     */
    public function newSession(Request $request, ?string $slug = null): JsonResponse
    {
        return response()->json([
            'session_id' => \Illuminate\Support\Str::uuid()->toString(),
        ]);
    }

    public function sessions(Request $request, ?string $slug = null): JsonResponse
    {
        $user     = auth()->user();
        $sessions = AiChatMessage::where('user_id', $user->id)
            ->where('role', 'user')
            ->selectRaw('session_id, MIN(created_at) as started_at, COUNT(*) as messages')
            ->groupBy('session_id')
            ->orderByDesc('started_at')
            ->limit(20)
            ->get();

        return response()->json(['sessions' => $sessions]);
    }

    private function buildTranscriptContext(?int $lessonId, ?string $productId): string
    {
        if (! $lessonId || ! $productId) {
            return '';
        }

        $lesson = MemberLesson::where('id', $lessonId)
            ->whereHas('module', fn ($q) => $q->where('product_id', $productId))
            ->first();

        if (! $lesson || $lesson->type !== 'video' || ! $lesson->content_url) {
            return '';
        }

        $ytService = new YouTubeTranscriptService();
        $videoId   = $ytService->extractVideoId($lesson->content_url);

        if (! $videoId) {
            return '';
        }

        $transcript = $ytService->getTranscript($videoId);

        if (! $transcript) {
            // Informa que há vídeo mas sem transcrição disponível
            return "\n\n--- AULA ATUAL ---"
                . "\nTítulo: {$lesson->title}"
                . "\nLink: https://youtu.be/{$videoId}"
                . "\n(Transcrição não disponível para este vídeo)";
        }

        $transcriptText = $ytService->formatForPrompt($transcript, $videoId);

        return "\n\n--- TRANSCRIÇÃO DA AULA ATUAL (com timestamps) ---"
            . "\nAula: {$lesson->title}"
            . "\nLink: https://youtu.be/{$videoId}"
            . "\n\n{$transcriptText}"
            . "\n\n[INSTRUÇÃO] Quando citar um trecho específico desta transcrição, inclua o link"
            . " com timestamp EXATAMENTE neste formato (sem alterar):"
            . " [Ver no vídeo em M:SS](https://youtu.be/{$videoId}?t=SEGUNDOS)"
            . "\nExemplo: [Ver no vídeo em 2:45](https://youtu.be/{$videoId}?t=165)"
            . "\nUse isso sempre que o trecho for relevante para a resposta.";
    }
}
