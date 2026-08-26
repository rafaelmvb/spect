<?php

namespace App\Http\Middleware;

use App\Models\MemberNotification;
use App\Models\MemberPushSubscription;
use App\Models\PanelNotification;
use App\Models\Setting;
use App\Plugins\PluginRegistry;
use App\Services\RefundService;
use App\Services\SalesAchievementsService;
use App\Services\StorageService;
use App\Services\TeamAccessService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        // O middleware ApplyWhiteLabelConfig é registrado via plugin depois do bootstrap,
        // e acaba rodando antes de StartSession (prependMiddlewareToGroup).
        // Aqui garantimos que o config seja aplicado com o usuário já resolvido.
        if (class_exists(\Plugins\WhiteLabel\ApplyWhiteLabelConfig::class)) {
            app(\Plugins\WhiteLabel\ApplyWhiteLabelConfig::class)->applyForRequest($request);
        }

        $user = $request->user();
        $tenantId = $user?->tenant_id;

        $appSettings = $user ? [
            'app_name' => config('spectra.app_name'),
            'theme_primary' => config('spectra.theme_primary'),
            'app_logo' => config('spectra.app_logo'),
            'app_logo_dark' => config('spectra.app_logo_dark'),
            'app_logo_icon' => config('spectra.app_logo_icon'),
            'app_logo_icon_dark' => config('spectra.app_logo_icon_dark'),
        ] : null;

        $publicBranding = $this->buildPublicBranding();

        $pageTitle = $this->pageTitleForRoute($request->route()?->getName());

        $pluginNavItems = [];
        $plugins = [];
        $achievementsProgress = null;
        $pushEnabled = false;
        $vapidPublic = null;
        $settingsPluginTabs = [];
        if ($user && $user->canAccessPanel()) {
            $settingsPluginTabs = PluginRegistry::getSettingsTabs();
            $pluginNavItems = PluginRegistry::getMenuItems();
            $vapidPublic = config('spectra.pwa.vapid_public');
            $pushEnabled = ! empty($vapidPublic) && ! empty(config('spectra.pwa.vapid_private'));
            $installed = PluginRegistry::installed();
            $plugins = array_map(fn ($p) => [
                'slug' => $p['slug'],
                'name' => $p['name'],
                'version' => $p['version'],
                'is_enabled' => $p['is_enabled'],
            ], $installed);
            $achievementsProgress = app(SalesAchievementsService::class)->getProgressForTenant($user->tenant_id);
        }

        $notificationsUnreadCount = 0;
        if ($user && $user->canAccessPanel()) {
            $notificationsUnreadCount = PanelNotification::forUser($user->id)->unread()->count();
        }

        // Branding global da área do aluno (carregado apenas em rotas de member area para não penalizar o painel)
        $path = $request->path();
        $isMemberArea = str_starts_with($path, 'm/') || $path === 'm';
        $isCheckout = str_starts_with($path, 'c/') || str_starts_with($path, 'checkout') || str_starts_with($path, 'api-checkout');
        $skipPanelPwa = $isMemberArea || $isCheckout;

        // Avaliada como closure lazy para garantir que os atributos de rota já foram setados
        $memberAreaGlobalBrandingFn = $isMemberArea ? function () use ($request, $user) {
            $memberAreaProduct = $request->attributes->get('member_area_product');
            $brandingTenantId = ($memberAreaProduct instanceof \App\Models\Product)
                ? $memberAreaProduct->tenant_id
                : $user?->tenant_id;
            if (! $brandingTenantId) return null;
            $raw      = Setting::get('member_area_branding', null, $brandingTenantId);
            $branding = is_string($raw) ? json_decode($raw, true) : ($raw ?: null);

            // Corrige URLs de logo/favicon que foram salvas com o host errado (ex: 127.0.0.1 local).
            // Se o path começa com /storage/ mas o host difere do atual → reescreve com o host correto.
            if (is_array($branding)) {
                $currentOrigin = $request->getSchemeAndHttpHost();
                $currentHost   = $request->getHttpHost();
                foreach (['logo_url', 'favicon_url'] as $field) {
                    $value = $branding[$field] ?? '';
                    if (! $value || ! str_starts_with($value, 'http')) continue;
                    $parsed     = parse_url($value);
                    $storedHost = ($parsed['host'] ?? '') . (isset($parsed['port']) ? ':' . $parsed['port'] : '');
                    $path       = $parsed['path'] ?? '';
                    if (str_starts_with($path, '/storage/') && $storedHost !== $currentHost) {
                        $branding[$field] = $currentOrigin . $path;
                    }
                }
            }

            return $branding;
        } : null;

        $shared = [
            ...parent::share($request),
            'csrf_token' => $request->session()->token(),
            'app_url' => rtrim(config('app.url'), '/'),
            'pageTitle' => $pageTitle,
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'username' => $user->username,
                    'role' => $user->role,
                    'avatar_url' => $user->avatar ? app(StorageService::class)->url($user->avatar) : null,
                ] : null,
                'permissions' => ($user && $user->canAccessPanel())
                    ? app(TeamAccessService::class)->permissionsFor($user)
                    : [],
                'allowed_product_ids' => ($user && $user->canAccessPanel())
                    ? app(TeamAccessService::class)->allowedProductIdsFor($user)
                    : [],
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'info' => $request->session()->get('info'),
                'status' => $request->session()->get('status'),
                'zip_unavailable' => $request->session()->get('zip_unavailable'),
                'newly_unlocked_achievements' => $request->session()->get('newly_unlocked_achievements'),
            ],
            'platform' => null,
            'appSettings' => $appSettings,
            'public_branding' => $publicBranding,
            'member_area_global_branding' => $memberAreaGlobalBrandingFn,
            'settings_plugin_tabs' => $settingsPluginTabs,
            'pluginNavItems' => $pluginNavItems,
            'plugins' => $plugins,
            'achievementsProgress' => $achievementsProgress,
            'push_enabled' => $pushEnabled,
            'vapid_public' => $pushEnabled ? $vapidPublic : null,
            'notifications_unread_count' => $notificationsUnreadCount,
            // Closures lazy: avaliadas APÓS os middlewares de rota setarem os atributos do request
            'member_base' => function () use ($request): ?string {
                $slug = $request->attributes->get('member_area_slug');
                if (! $slug) {
                    return null;
                }
                $type = $request->attributes->get('member_area_access_type');

                return in_array($type, ['subdomain', 'custom'], true) ? '' : '/m';
            },
            'member_enrolled' => function () use ($request): bool {
                return (bool) $request->attributes->get('member_area_enrolled');
            },
            'member_notifications_unread_count' => function () use ($request, $user): int {
                if (! $user) {
                    return 0;
                }
                $product = $request->route('product') ?? $request->attributes->get('member_area_product');
                if (! $product instanceof \App\Models\Product) {
                    return 0;
                }

                return MemberNotification::forUser($user->id)->forProduct($product->id)->unread()->count();
            },
            'member_push_subscribed' => function () use ($request, $user): bool {
                if (! $user) {
                    return false;
                }
                $product = $request->route('product') ?? $request->attributes->get('member_area_product');
                if (! $product instanceof \App\Models\Product) {
                    return false;
                }

                return MemberPushSubscription::where('user_id', $user->id)
                    ->where('product_id', $product->id)
                    ->exists();
            },
            'refund_eligibility' => function () use ($request, $user): ?array {
                if (! $user) {
                    return null;
                }
                $product = $request->route('product') ?? $request->attributes->get('member_area_product');
                if (! $product instanceof \App\Models\Product) {
                    return null;
                }
                if ($product->type !== \App\Models\Product::TYPE_AREA_MEMBROS) {
                    return null;
                }

                return app(RefundService::class)->eligibility($product, $user);
            },
        ];

        if (! $skipPanelPwa) {
            $shared['pwa_manifest_url'] = url('/manifest.json');
            $shared['pwa_sw_url'] = url('/painel-sw.js');
        }

        return $shared;
    }

    private function pageTitleForRoute(?string $name): ?string
    {
        $titles = [
            'dashboard' => 'Dashboard',
            'vendas.index' => 'Vendas',
            'reembolsos.index' => 'Reembolsos',
            'produtos.index' => 'Produtos',
            'produtos.create' => 'Novo produto',
            'produtos.edit' => 'Editar produto',
            'cupons.index' => 'Cupons',
            'assinaturas.index' => 'Assinaturas',
            'alunos.index' => 'Alunos',
            'relatorios.index' => 'Relatórios',
            'settings.index' => 'Configurações',
            'profile.index' => 'Meu perfil',
            'integrations.index' => 'Integrações',
            'plugins.index' => 'Plugins',
            'checkout.builder' => 'Editar checkout',
            'email-marketing.index' => 'E-mail Marketing',
            'email-marketing.create' => 'Nova campanha',
            'email-marketing.edit' => 'Editar campanha',
            'api-applications.index' => 'API de Pagamentos',
            'api-applications.create' => 'Nova aplicação API',
            'api-applications.edit' => 'Editar aplicação API',
            'conquistas.index' => 'Conquistas',
        ];

        return $name ? ($titles[$name] ?? null) : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPublicBranding(): array
    {
        $themePrimary = (string) config('spectra.theme_primary', '#00cc00');
        $pwaTheme = config('spectra.pwa_theme_color');
        $pwaTheme = ($pwaTheme !== null && $pwaTheme !== '') ? (string) $pwaTheme : $themePrimary;
        // Sem imagem padrao: o front trata string vazia escondendo o elemento.
        $favicon = (string) (config('spectra.favicon_url') ?? '');
        $loginHero = (string) (config('spectra.login_hero_image') ?? '');

        return [
            'app_name' => (string) config('spectra.app_name', config('app.name', 'Spectra')),
            'theme_primary' => $themePrimary,
            'pwa_theme_color' => $pwaTheme,
            'app_logo' => (string) config('spectra.app_logo'),
            'app_logo_dark' => (string) config('spectra.app_logo_dark'),
            'app_logo_icon' => (string) config('spectra.app_logo_icon'),
            'app_logo_icon_dark' => (string) config('spectra.app_logo_icon_dark'),
            'login_hero_image' => $loginHero,
            'favicon_url' => $favicon,
            'pwa_icon_192' => config('spectra.pwa_icon_192'),
            'pwa_icon_512' => config('spectra.pwa_icon_512'),
        ];
    }
}
