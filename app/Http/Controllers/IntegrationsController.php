<?php

namespace App\Http\Controllers;

use App\Gateways\GatewayRegistry;
use App\Models\GatewayCredential;
use App\Models\Product;
use App\Models\Setting;
use App\Support\SecretSetting;
use App\Models\CademiIntegration;
use App\Models\SpedyIntegration;
use App\Models\UtmifyIntegration;
use App\Models\ApiApplication;
use App\Models\Webhook;
use App\Messaging\MessagingProviderRegistry;
use App\Plugins\PluginRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationsController extends Controller
{
    public function index(): Response
    {
        $tenantId = auth()->user()->tenant_id;
        $pluginApps = PluginRegistry::getIntegrationApps();

        // Plugin app badges (ex.: AutoZap "Ativo" quando configurado).
        // Plugins podem ser carregados sem autoload; por isso, usamos require_once quando necessário.
        foreach ($pluginApps as $idx => $app) {
            if (($app['id'] ?? null) !== 'autozap') {
                continue;
            }
            try {
                $pluginDir = PluginRegistry::resolvePluginDirectory('autozap');
                if (is_string($pluginDir) && $pluginDir !== '') {
                    $modelFile = rtrim($pluginDir, '/\\') . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR . 'AutoZapConnection.php';
                    if (is_file($modelFile)) {
                        require_once $modelFile;
                    }
                }

                if (class_exists(\Plugins\AutoZap\Models\AutoZapConnection::class)) {
                    $conn = \Plugins\AutoZap\Models\AutoZapConnection::forTenant($tenantId)->first();
                    $isActive = (bool) ($conn?->is_active ?? false);
                    $hasCredentials = (bool) ($conn?->hasCredentials() ?? false);
                    if ($isActive && $hasCredentials) {
                        $pluginApps[$idx]['status'] = 'active';
                    }
                }
            } catch (\Throwable) {
                // Badge é "best-effort": não deve quebrar a página de integrações.
            }
        }

        $gateways = $this->buildGatewaysList($tenantId);
        $gatewayOrderRaw = Setting::get('gateway_order', null, $tenantId);
        $gatewayOrder = is_string($gatewayOrderRaw)
            ? (json_decode($gatewayOrderRaw, true) ?: config('gateways.default_order', ['pix' => [], 'card' => [], 'boleto' => [], 'pix_auto' => []]))
            : (is_array($gatewayOrderRaw) ? $gatewayOrderRaw : config('gateways.default_order', ['pix' => [], 'card' => [], 'boleto' => [], 'pix_auto' => []]));
        $gatewayOrder = [
            'pix' => $gatewayOrder['pix'] ?? [],
            'card' => $gatewayOrder['card'] ?? [],
            'boleto' => $gatewayOrder['boleto'] ?? [],
            'pix_auto' => $gatewayOrder['pix_auto'] ?? [],
        ];

        $webhooks = Webhook::forTenant($tenantId)
            ->with('products:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (Webhook $w) => [
                'id' => $w->id,
                'name' => $w->name,
                'url' => $w->url,
                'has_bearer_token' => (bool) $w->bearer_token,
                'events' => $w->events ?? [],
                'is_active' => $w->is_active,
                'products' => $w->products->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->values()->all(),
            ])
            ->values()
            ->all();

        $webhookEvents = config('webhook_events.events', []);

        $utmifyIntegrations = UtmifyIntegration::forTenant($tenantId)
            ->with('products:id,name', 'apiApplications:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (UtmifyIntegration $i) => [
                'id' => $i->id,
                'name' => $i->name,
                'is_active' => $i->is_active,
                'configured' => $i->api_key !== null && $i->api_key !== '',
                'api_key' => $i->api_key ?? '',
                'product_ids' => $i->products->pluck('id')->values()->all(),
                'products' => $i->products->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->values()->all(),
                'api_application_ids' => $i->apiApplications->pluck('id')->values()->all(),
                'api_applications' => $i->apiApplications->map(fn ($a) => ['id' => $a->id, 'name' => $a->name])->values()->all(),
            ])
            ->values()
            ->all();

        $spedyIntegrations = SpedyIntegration::forTenant($tenantId)
            ->with('products:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (SpedyIntegration $i) => [
                'id' => $i->id,
                'name' => $i->name,
                'is_active' => $i->is_active,
                'configured' => $i->api_key !== null && $i->api_key !== '',
                'api_key' => $i->api_key ?? '',
                'environment' => $i->environment ?? SpedyIntegration::ENVIRONMENT_PRODUCTION,
                'product_ids' => $i->products->pluck('id')->values()->all(),
                'products' => $i->products->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->values()->all(),
            ])
            ->values()
            ->all();

        $cademiIntegrations = CademiIntegration::forTenant($tenantId)
            ->with('products:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (CademiIntegration $i) => [
                'id' => $i->id,
                'name' => $i->name,
                'base_url' => $i->base_url,
                'is_active' => $i->is_active,
                'configured' => $i->api_key !== null && $i->api_key !== '',
                'api_key' => $i->api_key ?? '',
                'product_ids' => $i->products->pluck('id')->values()->all(),
                'products' => $i->products->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->values()->all(),
            ])
            ->values()
            ->all();

        $products = Product::forTenant($tenantId)->orderBy('name')->get(['id', 'name']);
        $apiApplications = ApiApplication::forTenant($tenantId)->orderBy('name')->get(['id', 'name']);

        $llmProviders = [
            [
                'id'          => 'anthropic',
                'name'        => 'Anthropic',
                'subtitle'    => 'Claude Haiku · Sonnet · Opus',
                'description' => 'Melhor para análises clínicas detalhadas e relatórios avançados.',
                'color'       => '#CC785C',
                'key_hint'    => $this->maskApiKey(SecretSetting::get('anthropic_api_key', $tenantId)),
                'configured'  => SecretSetting::isSet('anthropic_api_key', $tenantId),
            ],
            [
                'id'          => 'openai',
                'name'        => 'OpenAI',
                'subtitle'    => 'GPT-4o · GPT-4o mini',
                'description' => 'Excelente para respostas gerais, chat e automações.',
                'color'       => '#10A37F',
                'key_hint'    => $this->maskApiKey(SecretSetting::get('openai_api_key', $tenantId)),
                'configured'  => SecretSetting::isSet('openai_api_key', $tenantId),
            ],
            [
                'id'          => 'groq',
                'name'        => 'Groq',
                'subtitle'    => 'LLaMA 3 · Mixtral · Gemma',
                'description' => 'Velocidade extrema com baixo custo. Compatível com API OpenAI.',
                'color'       => '#F55036',
                'key_hint'    => $this->maskApiKey(SecretSetting::get('groq_api_key', $tenantId)),
                'configured'  => SecretSetting::isSet('groq_api_key', $tenantId),
            ],
            [
                'id'          => 'gemini',
                'name'        => 'Google Gemini',
                'subtitle'    => 'Gemini 1.5 Pro · Flash',
                'description' => 'Modelo multimodal do Google. Suporte em breve.',
                'color'       => '#4285F4',
                'key_hint'    => $this->maskApiKey(SecretSetting::get('gemini_api_key', $tenantId)),
                'configured'  => SecretSetting::isSet('gemini_api_key', $tenantId),
                'coming_soon' => true,
            ],
        ];

        return Inertia::render('Integrations/Index', [
            'gateways' => $gateways,
            'gateway_order' => $gatewayOrder,
            'webhooks' => $webhooks,
            'webhook_events' => $webhookEvents,
            'utmify_integrations' => $utmifyIntegrations,
            'spedy_integrations' => $spedyIntegrations,
            'cademi_integrations' => $cademiIntegrations,
            'products' => $products,
            'api_applications' => $apiApplications,
            'plugin_apps' => $pluginApps,
            'messaging_providers' => collect(MessagingProviderRegistry::all())->map(fn ($p, $slug) => [
                'slug' => $slug,
                'label' => $p['label'],
                'channel' => $p['channel'],
                'credential_keys' => $p['credential_keys'],
            ])->values()->all(),
            'messaging_configured_providers' => collect(MessagingProviderRegistry::all())->mapWithKeys(function ($info, $slug) use ($tenantId) {
                $keys = array_column($info['credential_keys'], 'key');
                $hasAll = collect($keys)->every(fn ($k) => (bool) Setting::get($k, null, $tenantId));
                return [$slug => $hasAll];
            })->all(),
            'llm_providers'  => $llmProviders,
            'llm_preferred'  => Setting::get('ai_preferred_provider', 'anthropic', $tenantId),
        ]);
    }

    // ─── LLM ─────────────────────────────────────────────────────────────────

    private const LLM_KEY_MAP = [
        'anthropic' => 'anthropic_api_key',
        'openai'    => 'openai_api_key',
        'groq'      => 'groq_api_key',
        'gemini'    => 'gemini_api_key',
    ];

    public function saveLlmKey(Request $request): JsonResponse
    {
        $v = $request->validate([
            'provider' => ['required', 'string', 'in:anthropic,openai,groq,gemini'],
            'api_key'  => ['required', 'string', 'max:300'],
        ]);

        $tenantId = auth()->user()->tenant_id;
        SecretSetting::set(self::LLM_KEY_MAP[$v['provider']], trim($v['api_key']), $tenantId);

        return response()->json([
            'success'  => true,
            'key_hint' => $this->maskApiKey(trim($v['api_key'])),
        ]);
    }

    public function removeLlmKey(Request $request): JsonResponse
    {
        $v = $request->validate([
            'provider' => ['required', 'string', 'in:anthropic,openai,groq,gemini'],
        ]);

        $tenantId = auth()->user()->tenant_id;
        SecretSetting::set(self::LLM_KEY_MAP[$v['provider']], null, $tenantId);

        return response()->json(['success' => true]);
    }

    public function setPreferredLlm(Request $request): JsonResponse
    {
        $v = $request->validate([
            'provider' => ['required', 'string', 'in:anthropic,openai,groq,gemini'],
        ]);

        Setting::set('ai_preferred_provider', $v['provider'], auth()->user()->tenant_id);

        return response()->json(['success' => true]);
    }

    private function maskApiKey(?string $key): string
    {
        if (! $key || strlen($key) < 8) {
            return '';
        }
        $prefixLen = min(10, (int) (strlen($key) * 0.4));
        return substr($key, 0, $prefixLen) . '•••••••' . substr($key, -4);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildGatewaysList(?int $tenantId): array
    {
        $all = GatewayRegistry::all();
        $credentialBySlug = GatewayCredential::forTenant($tenantId)->get()->keyBy('gateway_slug');

        return array_map(function ($g) use ($credentialBySlug) {
            $cred = $credentialBySlug->get($g['slug'] ?? '');
            $image = $g['image'] ?? null;
            return [
                'slug' => $g['slug'],
                'name' => $g['name'],
                'image' => GatewayRegistry::resolveImageUrl(is_string($image) ? $image : null),
                'methods' => $g['methods'] ?? [],
                'scope' => $g['scope'] ?? 'national',
                'country' => $g['country'] ?? null,
                'country_name' => $g['country_name'] ?? null,
                'country_flag' => $g['country_flag'] ?? null,
                'countries' => $g['countries'] ?? null,
                'signup_url' => $g['signup_url'] ?? null,
                'is_configured' => $cred !== null,
                'is_connected' => $cred?->is_connected ?? false,
            ];
        }, $all);
    }

    public function enablePlugin(string $slug): RedirectResponse
    {
        $installed = collect(PluginRegistry::installed())->keyBy('slug');
        if (! $installed->has($slug)) {
            return back()->with('error', 'Plugin não encontrado.');
        }
        PluginRegistry::enable($slug);
        return back()->with('success', 'Plugin ativado.');
    }

    public function disablePlugin(string $slug): RedirectResponse
    {
        $installed = collect(PluginRegistry::installed())->keyBy('slug');
        if (! $installed->has($slug)) {
            return back()->with('error', 'Plugin não encontrado.');
        }
        PluginRegistry::disable($slug);
        return back()->with('success', 'Plugin desativado.');
    }

    public function uninstallPlugin(string $slug): RedirectResponse
    {
        $installed = collect(PluginRegistry::installed())->keyBy('slug');
        $plugin = $installed->get($slug);
        if (! $plugin) {
            return back()->with('error', 'Plugin não encontrado.');
        }
        $pluginPath = $plugin['path'] ?? null;
        if (! PluginRegistry::uninstall($slug, $pluginPath)) {
            return back()->with('error', 'Não foi possível excluir o plugin. Verifique permissões da pasta plugins.');
        }
        return back()->with('success', 'Plugin excluído.');
    }
}
