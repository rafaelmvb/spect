<?php

namespace App\Http\Controllers;

use App\Messaging\MessagingProviderRegistry;
use App\Models\MessagingCampaign;
use App\Models\Product;
use App\Models\Setting;
use App\Services\MessagingRecipientsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MessagingCampaignController extends Controller
{
    public function __construct(private MessagingRecipientsService $recipients) {}

    private function tenantId(): ?int { return auth()->user()->tenant_id; }

    // ─── Index ────────────────────────────────────────────────────────────────
    public function index(): Response
    {
        $campaigns = MessagingCampaign::where('tenant_id', $this->tenantId())
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id, 'name' => $c->name, 'channel' => $c->channel,
                'provider' => $c->provider, 'status' => $c->status,
                'total_recipients' => $c->total_recipients, 'sent_count' => $c->sent_count,
                'failed_count' => $c->failed_count, 'sent_at' => $c->sent_at?->format('d/m/Y H:i'),
                'created_at' => $c->created_at->format('d/m/Y H:i'),
            ]);

        $providers = collect(MessagingProviderRegistry::all())->map(fn ($p, $slug) => [
            'slug' => $slug, 'label' => $p['label'], 'channel' => $p['channel'],
        ])->values();

        $configuredProviders = $this->getConfiguredProviders();

        return Inertia::render('Messaging/Index', [
            'campaigns'           => $campaigns,
            'providers'           => $providers,
            'configured_providers'=> $configuredProviders,
        ]);
    }

    // ─── Create / Store ────────────────────────────────────────────────────────
    public function create(Request $request): Response
    {
        return Inertia::render('Messaging/Create', [
            'products'            => $this->products(),
            'providers'           => $this->providersForUI(),
            'configured_providers'=> $this->getConfiguredProviders(),
            'channel'             => $request->query('channel', 'whatsapp'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'channel'       => ['required', 'in:whatsapp,sms'],
            'provider'      => ['required', 'string'],
            'message_body'  => ['required', 'string'],
            'filter_config' => ['nullable', 'array'],
            'filter_config.type'          => ['nullable', 'string'],
            'filter_config.product_ids'   => ['nullable', 'array'],
            'filter_config.inactive_days' => ['nullable', 'integer'],
        ]);

        $filterConfig = $validated['filter_config'] ?? [];
        if (empty($filterConfig['type'])) $filterConfig['type'] = 'all_customers';

        MessagingCampaign::create([
            'tenant_id'    => $this->tenantId(),
            'channel'      => $validated['channel'],
            'provider'     => $validated['provider'],
            'name'         => $validated['name'],
            'message_body' => $validated['message_body'],
            'filter_config'=> $filterConfig,
            'status'       => MessagingCampaign::STATUS_DRAFT,
        ]);

        return redirect()->route('messaging.index')->with('success', 'Campanha criada.');
    }

    // ─── Edit / Update ─────────────────────────────────────────────────────────
    public function edit(MessagingCampaign $campaign): Response|RedirectResponse
    {
        if ($campaign->tenant_id !== $this->tenantId()) abort(404);
        if (! $campaign->isDraft()) {
            return redirect()->route('messaging.index')->with('info', 'Apenas rascunhos podem ser editados.');
        }

        return Inertia::render('Messaging/Edit', [
            'campaign'            => $this->campaignPayload($campaign),
            'products'            => $this->products(),
            'providers'           => $this->providersForUI(),
            'configured_providers'=> $this->getConfiguredProviders(),
        ]);
    }

    public function update(Request $request, MessagingCampaign $campaign): RedirectResponse
    {
        if ($campaign->tenant_id !== $this->tenantId() || ! $campaign->isDraft()) abort(404);

        $validated = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'provider'      => ['required', 'string'],
            'message_body'  => ['required', 'string'],
            'filter_config' => ['nullable', 'array'],
            'filter_config.type'          => ['nullable', 'string'],
            'filter_config.product_ids'   => ['nullable', 'array'],
            'filter_config.inactive_days' => ['nullable', 'integer'],
        ]);

        $filterConfig = $validated['filter_config'] ?? [];
        if (empty($filterConfig['type'])) $filterConfig['type'] = 'all_customers';

        $campaign->update([
            'provider'      => $validated['provider'],
            'name'          => $validated['name'],
            'message_body'  => $validated['message_body'],
            'filter_config' => $filterConfig,
        ]);

        return redirect()->route('messaging.index')->with('success', 'Campanha atualizada.');
    }

    // ─── Send ──────────────────────────────────────────────────────────────────
    public function send(MessagingCampaign $campaign): RedirectResponse
    {
        if ($campaign->tenant_id !== $this->tenantId() || ! $campaign->isDraft()) abort(404);

        $count = $this->recipients->getRecipients($this->tenantId(), $campaign->filter_config ?? [])->count();
        if ($count === 0) {
            return back()->with('error', 'Nenhum destinatário encontrado com esses filtros.');
        }

        $campaign->update(['status' => MessagingCampaign::STATUS_SENDING, 'total_recipients' => $count]);

        return redirect()->route('messaging.index')
            ->with('success', "Campanha iniciada. Enviando para {$count} destinatário(s) em lotes de 20 por minuto.");
    }

    // ─── Preview de destinatários ──────────────────────────────────────────────
    public function previewRecipients(Request $request): JsonResponse
    {
        $filterConfig = $request->input('filter_config', []);
        if (empty($filterConfig['type'])) $filterConfig['type'] = 'all_customers';

        $all = $this->recipients->getRecipients($this->tenantId(), $filterConfig);

        return response()->json([
            'count'  => $all->count(),
            'sample' => $all->take(10)->map(fn ($r) => ['phone' => $r['phone'], 'name' => $r['name']])->values(),
        ]);
    }

    // ─── Salvar credenciais de provedor ────────────────────────────────────────
    public function saveProviderCredentials(Request $request, string $providerSlug): JsonResponse
    {
        $registry = MessagingProviderRegistry::all()[$providerSlug] ?? null;
        if (! $registry) abort(404);

        $tenantId = $this->tenantId();
        foreach ($registry['credential_keys'] as $credDef) {
            $key = $credDef['key'];
            $value = $request->input($key);
            if ($value !== null && $value !== '') {
                Setting::set($key, encrypt($value), $tenantId);
            }
        }

        return response()->json(['success' => true, 'message' => 'Credenciais salvas.']);
    }

    public function getProviderCredentials(string $providerSlug): JsonResponse
    {
        $registry = MessagingProviderRegistry::all()[$providerSlug] ?? null;
        if (! $registry) abort(404);

        $tenantId = $this->tenantId();
        $credentials = [];
        foreach ($registry['credential_keys'] as $credDef) {
            $stored = Setting::get($credDef['key'], null, $tenantId);
            $credentials[$credDef['key']] = $stored ? '••••••••' : '';
        }

        return response()->json(['credentials' => $credentials, 'filled' => ! empty(array_filter($credentials))]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────
    private function campaignPayload(MessagingCampaign $c): array
    {
        $fc = $c->filter_config ?? [];
        return [
            'id' => $c->id, 'name' => $c->name, 'channel' => $c->channel,
            'provider' => $c->provider, 'message_body' => $c->message_body,
            'filter_config' => array_merge(['type' => 'all_customers', 'product_ids' => [], 'inactive_days' => 30], $fc),
        ];
    }

    private function products(): array
    {
        return Product::where('tenant_id', $this->tenantId())
            ->orderBy('name')->get(['id', 'name'])
            ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->values()->all();
    }

    private function providersForUI(): array
    {
        return collect(MessagingProviderRegistry::all())->map(fn ($p, $slug) => [
            'slug' => $slug, 'label' => $p['label'], 'channel' => $p['channel'],
            'credential_keys' => $p['credential_keys'],
        ])->values()->all();
    }

    private function getConfiguredProviders(): array
    {
        $tenantId = $this->tenantId();
        $configured = [];
        foreach (MessagingProviderRegistry::all() as $slug => $info) {
            $keys = array_column($info['credential_keys'], 'key');
            $hasAll = collect($keys)->every(fn ($k) => (bool) Setting::get($k, null, $tenantId));
            $configured[$slug] = $hasAll;
        }
        return $configured;
    }
}
