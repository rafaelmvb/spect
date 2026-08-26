<?php

namespace App\Services;

use App\Models\AiConfig;
use App\Models\AiInteractionLog;
use App\Models\Setting;
use App\Support\SecretSetting;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiService
{
    private const OPENAI_URL    = 'https://api.openai.com/v1/chat/completions';
    private const GROQ_URL      = 'https://api.groq.com/openai/v1/chat/completions';
    private const ANTHROPIC_URL = 'https://api.anthropic.com/v1/messages';
    private const ANTHROPIC_VER = '2023-06-01';

    public function __construct(private readonly ?int $tenantId = null) {}

    public function available(): bool
    {
        return $this->anthropicKey() !== null
            || $this->openaiKey() !== null
            || $this->groqKey() !== null;
    }

    private function anthropicKey(): ?string
    {
        $k = SecretSetting::get('anthropic_api_key', $this->tenantId);
        return ($k && str_starts_with($k, 'sk-ant-')) ? $k : null;
    }

    private function openaiKey(): ?string
    {
        $k = SecretSetting::get('openai_api_key', $this->tenantId);
        return ($k && str_starts_with($k, 'sk-')) ? $k : null;
    }

    private function groqKey(): ?string
    {
        $k = SecretSetting::get('groq_api_key', $this->tenantId);
        return ($k && strlen($k) > 10) ? $k : null;
    }

    private function preferredProvider(): string
    {
        return Setting::get('ai_preferred_provider', 'anthropic', $this->tenantId);
    }

    /** Modelos padrão por provider */
    private const DEFAULT_MODELS = [
        'anthropic' => 'claude-haiku-4-5-20251001',
        'openai'    => 'gpt-4o-mini',
        'groq'      => 'llama3-8b-8192',
        'gemini'    => 'gemini-1.5-flash',
    ];

    /**
     * Resolve o model correto para o provider ativo.
     * Se o model do config pertence a outro provider (ex.: claude-* com OpenAI), usa o default do provider.
     */
    private function resolveModel(string $provider, ?string $configModel): string
    {
        if (! $configModel) {
            return self::DEFAULT_MODELS[$provider] ?? 'gpt-4o-mini';
        }

        $isAnthropicModel = str_starts_with($configModel, 'claude-');
        $isOpenAiModel    = str_starts_with($configModel, 'gpt-') || str_starts_with($configModel, 'o1') || str_starts_with($configModel, 'o3');
        $isGroqModel      = in_array($configModel, ['llama3-8b-8192', 'llama3-70b-8192', 'llama-3.3-70b-versatile', 'mixtral-8x7b-32768', 'gemma-7b-it', 'gemma2-9b-it'], true);

        $compatible = match ($provider) {
            'anthropic' => $isAnthropicModel,
            'openai'    => $isOpenAiModel,
            'groq'      => $isGroqModel,
            default     => false,
        };

        return $compatible ? $configModel : (self::DEFAULT_MODELS[$provider] ?? 'gpt-4o-mini');
    }

    /**
     * Chama a IA e retorna a resposta como string.
     * @param array $messages [['role'=>'user','content'=>'...'], ...]
     */
    public function complete(array $messages, AiConfig $config, ?int $tenantId = null, ?int $userId = null): string
    {
        $provider = $this->preferredProvider();

        // Resolve key for preferred provider, fallback to any available
        $key = match ($provider) {
            'anthropic' => $this->anthropicKey(),
            'groq'      => $this->groqKey(),
            default     => $this->openaiKey(),
        };

        if (! $key) {
            foreach (['anthropic', 'openai', 'groq'] as $p) {
                $candidate = match ($p) {
                    'anthropic' => $this->anthropicKey(),
                    'groq'      => $this->groqKey(),
                    default     => $this->openaiKey(),
                };
                if ($candidate) { $key = $candidate; $provider = $p; break; }
            }
        }

        if (! $key) {
            throw new \RuntimeException('Nenhuma chave de API de IA configurada. Configure em Integrações → LLM.');
        }

        // Garante que o model é compatível com o provider resolvido
        $resolvedModel = $this->resolveModel($provider, $config->model);

        $start = microtime(true);

        try {
            [$reply, $tokens, $model] = match ($provider) {
                'anthropic' => $this->callAnthropic($messages, $config, $key, $resolvedModel),
                'groq'      => $this->callGroq($messages, $config, $key, $resolvedModel),
                default     => $this->callOpenAi($messages, $config, $key, $resolvedModel),
            };

            $latency = (int) ((microtime(true) - $start) * 1000);
            $this->log($tenantId, $userId, $config->context, $messages, $reply, $model, $tokens, $latency, 'success');

            return $reply;
        } catch (\Throwable $e) {
            $latency = (int) ((microtime(true) - $start) * 1000);
            $this->log($tenantId, $userId, $config->context, $messages, $e->getMessage(), $config->model, 0, $latency, 'error');
            throw $e;
        }
    }

    private function callGroq(array $messages, AiConfig $config, string $key, string $model = 'llama3-8b-8192'): array
    {
        $response = Http::withToken($key)
            ->timeout(60)
            ->post(self::GROQ_URL, [
                'model'       => $model,
                'messages'    => $messages,
                'temperature' => (float) ($config->temperature ?? 0.7),
                'max_tokens'  => (int)   ($config->max_tokens  ?? 1024),
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('Groq error: ' . $response->body());
        }

        $data   = $response->json();
        $reply  = $data['choices'][0]['message']['content'] ?? '';
        $tokens = $data['usage']['total_tokens'] ?? 0;

        return [$reply, $tokens, $model];
    }

    private function callOpenAi(array $messages, AiConfig $config, string $key, string $model = 'gpt-4o-mini'): array
    {
        $response = Http::withToken($key)
            ->timeout(60)
            ->post(self::OPENAI_URL, [
                'model'       => $model,
                'messages'    => $messages,
                'temperature' => (float) ($config->temperature ?? 0.7),
                'max_tokens'  => (int)   ($config->max_tokens  ?? 1024),
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('OpenAI error: ' . $response->body());
        }

        $data   = $response->json();
        $reply  = $data['choices'][0]['message']['content'] ?? '';
        $tokens = $data['usage']['total_tokens'] ?? 0;

        return [$reply, $tokens, $model];
    }

    private function callAnthropic(array $messages, AiConfig $config, string $key, string $model = 'claude-haiku-4-5-20251001'): array
    {
        // Anthropic separa system das mensagens user/assistant
        $system   = '';
        $filtered = [];
        foreach ($messages as $m) {
            if ($m['role'] === 'system') {
                $system = $m['content'];
            } else {
                $filtered[] = $m;
            }
        }

        $body = [
            'model'      => $model,
            'max_tokens' => (int) ($config->max_tokens ?? 1024),
            'messages'   => $filtered,
        ];
        if ($system) $body['system'] = $system;

        $response = Http::withHeaders([
            'x-api-key'         => $key,
            'anthropic-version' => self::ANTHROPIC_VER,
        ])->timeout(60)->post(self::ANTHROPIC_URL, $body);

        if ($response->failed()) {
            throw new \RuntimeException('Anthropic error: ' . $response->body());
        }

        $data   = $response->json();
        $reply  = $data['content'][0]['text'] ?? '';
        $tokens = ($data['usage']['input_tokens'] ?? 0) + ($data['usage']['output_tokens'] ?? 0);

        return [$reply, $tokens, $model];
    }

    private function log(
        ?int $tenantId, ?int $userId, string $context,
        array $messages, string $output, string $model,
        int $tokens, int $latencyMs, string $status
    ): void {
        try {
            $lastUser = collect($messages)->where('role', 'user')->last();
            AiInteractionLog::create([
                'tenant_id'      => $tenantId,
                'user_id'        => $userId,
                'context'        => $context,
                'input_summary'  => mb_substr($lastUser['content'] ?? '', 0, 200),
                'output_summary' => mb_substr($output, 0, 200),
                'model'          => $model,
                'tokens_used'    => $tokens,
                'latency_ms'     => $latencyMs,
                'status'         => $status,
            ]);
        } catch (\Throwable $e) {
            Log::warning('AiService log failed: ' . $e->getMessage());
        }
    }

    /**
     * Constrói contexto do aluno para o system prompt.
     */
    public function buildUserContext(User $user, string|int $productId): string
    {
        $product = \App\Models\Product::find($productId);

        // IDs de aulas concluídas neste produto
        $completedIds = \App\Models\MemberLessonProgress::where('user_id', $user->id)
            ->where('product_id', $productId)
            ->whereNotNull('completed_at')
            ->pluck('member_lesson_id')
            ->flip();

        // Módulos e aulas do produto
        $modules = \App\Models\MemberModule::where('product_id', $productId)
            ->with('lessons:id,member_module_id,title,position')
            ->orderBy('position')
            ->get();

        $totalLessons     = 0;
        $completedLessons = 0;
        $moduleLines      = [];

        foreach ($modules as $mod) {
            $modTotal     = $mod->lessons->count();
            $modCompleted = $mod->lessons->filter(fn ($l) => isset($completedIds[$l->id]))->count();
            $totalLessons     += $modTotal;
            $completedLessons += $modCompleted;
            if ($modTotal > 0) {
                $status = $modCompleted === $modTotal ? '✅ concluído' : "{$modCompleted}/{$modTotal} aulas";
                $moduleLines[] = "  - {$mod->title}: {$status}";
            }
        }

        $pct = $totalLessons > 0 ? round($completedLessons / $totalLessons * 100) : 0;

        $lines = [
            "Aluno: {$user->name}",
            "Email: {$user->email}",
            "Curso atual: " . ($product?->name ?? 'N/A'),
            "Progresso geral: {$completedLessons}/{$totalLessons} aulas concluídas ({$pct}%)",
        ];

        if ($moduleLines) {
            $lines[] = "Progresso por módulo:";
            array_push($lines, ...$moduleLines);
        }

        // Scores neurofuncionais (se existirem)
        try {
            $neuroScores = \App\Models\NeuroUserScore::where('user_id', $user->id)
                ->with('area:id,name')
                ->latest()
                ->get()
                ->groupBy('area_id')
                ->map(fn ($g) => ['area' => $g->first()->area?->name, 'score' => $g->first()->score]);

            foreach ($neuroScores as $item) {
                if ($item['area']) {
                    $lines[] = "Score em {$item['area']}: {$item['score']}/10";
                }
            }
        } catch (\Throwable) {
            // tabela opcional — ignora se não existir
        }

        return implode("\n", $lines);
    }
}
