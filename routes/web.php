<?php

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ResetPasswordController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

// Storage: servir arquivos de storage/app/public (sem symlink) — deve ser uma das primeiras rotas
Route::get('/storage/{path}', \App\Http\Controllers\StorageServeController::class)
    ->where('path', '.+')
    ->name('storage.serve');

// Instalador: fallback quando o servidor envia /install para o Laravel (ex: document root diferente de public/)
Route::any('/install', [\App\Http\Controllers\InstallServeController::class, '__invoke'])->defaults('path', null);
Route::any('/install/{path}', [\App\Http\Controllers\InstallServeController::class, '__invoke'])->where('path', '.+');

Route::get('/docker-setup', [\App\Http\Controllers\DockerSetupController::class, 'show'])->name('docker-setup');
Route::post('/docker-setup', [\App\Http\Controllers\DockerSetupController::class, 'store'])->middleware('throttle:10,1');

// Favicon: evita 404 no console quando o navegador solicita /favicon.ico
Route::get('/favicon.ico', function () {
    return redirect('https://cdn.getfy.cloud/collapsed-logo.png', 302);
});

// PWA Painel: manifest e service worker
Route::get('/manifest.json', [\App\Http\Controllers\PanelPwaController::class, 'manifest'])->name('panel.pwa.manifest');
Route::get('/painel-sw.js', function () {
    $path = public_path('painel-sw.js');
    if (! file_exists($path)) {
        abort(404);
    }

    return response()->file($path, [
        'Content-Type' => 'application/javascript; charset=UTF-8',
        // Evita cache agressivo do SW (senão o browser não busca a versão nova e mantém o SW antigo ativo).
        'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        'Pragma' => 'no-cache',
        'Expires' => '0',
    ]);
})->name('panel.pwa.sw');

Route::get('/', function (\Illuminate\Http\Request $request) {
    $resolved = app(\App\Services\MemberAreaResolver::class)->resolve($request);
    if ($resolved && in_array($resolved['access_type'], ['subdomain', 'custom'], true)) {
        $request->attributes->set('member_area_product', $resolved['product']);
        $request->attributes->set('member_area_access_type', $resolved['access_type']);
        $request->attributes->set('member_area_slug', $resolved['slug']);

        if (! $request->user()) {
            return redirect()->to('/login')->with('error', 'Faça login para acessar a área de membros.');
        }

        if (! $resolved['product']->hasMemberAreaAccess($request->user())) {
            return redirect()->route('checkout.show', ['slug' => $resolved['product']->checkout_slug])
                ->with('error', 'Você não tem acesso a esta área. Adquira o produto para continuar.');
        }

        return app()->call(\App\Http\Controllers\MemberAreaAppController::class.'@show', [
            'request' => $request,
            'slug' => $resolved['slug'],
        ]);
    }

    if (auth()->check()) {
        $user = auth()->user();
        if ($user->canAccessPanel()) {
            return redirect('/dashboard');
        }

        return redirect('/area-membros');
    }

    return redirect()->to('/login', 302);
});

Route::get('/cron', function () {
    $secret = config('getfy.cron_secret');
    $token = request()->query('token');
    if (! $secret || $token !== $secret) {
        abort(404);
    }
    \Illuminate\Support\Facades\Artisan::call('schedule:run');

    return response()->json(['ok' => true, 'message' => 'Schedule executed']);
})->middleware('throttle:60,1')->name('cron.url');

Route::middleware('throttle:60,1')->group(function () {
    Route::post('/webhooks/gateways/spacepag', [\App\Http\Controllers\Webhooks\SpacepagWebhookController::class, 'handle'])->name('webhooks.spacepag');
    Route::post('/webhooks/gateways/stripe', [\App\Http\Controllers\Webhooks\StripeWebhookController::class, 'handle'])->name('webhooks.stripe');
    Route::post('/webhooks/gateways/efi/pix', [\App\Http\Controllers\Webhooks\EfiWebhookController::class, 'pix'])->name('webhooks.efi.pix');
    Route::post('/webhooks/gateways/efi/pix-recorrente', [\App\Http\Controllers\Webhooks\EfiWebhookController::class, 'pixRecorrente'])->name('webhooks.efi.pix-recorrente');
    Route::post('/webhooks/gateways/efi/notification', [\App\Http\Controllers\Webhooks\EfiWebhookController::class, 'notification'])->name('webhooks.efi.notification');
    Route::post('/webhooks/gateways/mercadopago', [\App\Http\Controllers\Webhooks\MercadoPagoWebhookController::class, 'handle'])->name('webhooks.mercadopago');
    Route::post('/webhooks/gateways/pushinpay', [\App\Http\Controllers\Webhooks\PushinPayWebhookController::class, 'handle'])->name('webhooks.pushinpay');
    Route::post('/webhooks/gateways/asaas', [\App\Http\Controllers\Webhooks\AsaasWebhookController::class, 'handle'])->name('webhooks.asaas');
    Route::post('/webhooks/gateways/pagarme', [\App\Http\Controllers\Webhooks\PagarmeWebhookController::class, 'handle'])->name('webhooks.pagarme');
    Route::post('/webhooks/gateways/cajupay', [\App\Http\Controllers\Webhooks\CajuPayWebhookController::class, 'handle'])->name('webhooks.cajupay');
    // Dispatcher genérico para gateways de plugins (webhook_handler na definição do gateway)
    Route::post('/webhooks/gateways/{slug}', \App\Http\Controllers\Webhooks\GenericGatewayWebhookController::class)
        ->where('slug', '[a-z0-9_-]+')
        ->name('webhooks.gateway');
});

// Verificação pública de autenticidade do dossiê (resumo mascarado).
Route::get('/verify/{code}', [\App\Http\Controllers\PublicProofVerifyController::class, 'show'])
    ->where('code', '[0-9A-Za-z]{6,32}')
    ->middleware('throttle:30,1')
    ->name('public.proof.verify');

// Assets de plugins (imagens, etc.): GET /plugins/{slug}/assets/{path} — arquivos em plugins/{slug}/assets/
Route::get('/plugins/{slug}/assets/{path}', \App\Http\Controllers\PluginAssetController::class)
    ->where('path', '.+')
    ->name('plugins.asset');

Route::get('/resultado/{token}', [\App\Http\Controllers\TestResultShareController::class, 'show'])->name('test-result.public')->where('token', '[a-f0-9]{48}');
Route::get('/renovar/{token}', [\App\Http\Controllers\RenewalController::class, 'show'])->name('renewal.show')->where('token', '[a-zA-Z0-9]{32,64}');
Route::post('/renovar', [\App\Http\Controllers\RenewalController::class, 'process'])
    ->name('renewal.process')
    ->middleware(['throttle:checkout-process', 'throttle:checkout-pix', 'throttle:checkout-card', 'checkout.abuse']);

// Checkout Pro (API): página hospedada – dados do cliente na sessão
Route::get('/api-checkout/{token}', [\App\Http\Controllers\ApiCheckoutController::class, 'show'])->name('api-checkout.show')->where('token', '[a-zA-Z0-9\-]{36,64}');
Route::post('/api-checkout/pay', [\App\Http\Controllers\ApiCheckoutController::class, 'process'])
    ->name('api-checkout.process')
    ->middleware(['throttle:checkout-process', 'throttle:checkout-pix', 'throttle:checkout-card', 'throttle:checkout-email', 'throttle:checkout-product-ip', 'checkout.abuse']);
Route::get('/api-checkout/card-confirm', [\App\Http\Controllers\ApiCheckoutController::class, 'cardConfirm'])->name('api-checkout.card-confirm');
Route::get('/api-checkout/obrigado', [\App\Http\Controllers\ApiCheckoutController::class, 'thankYou'])->name('api-checkout.thank-you');

Route::get('/c/{slug}', [\App\Http\Controllers\CheckoutController::class, 'show'])
    ->name('checkout.show')
    ->where('slug', '[a-z0-9-]{6,16}')
    ->middleware('throttle:checkout-show');
Route::get('/checkout/pix', [\App\Http\Controllers\CheckoutController::class, 'pixPage'])->name('checkout.pix');
Route::get('/checkout/boleto', [\App\Http\Controllers\CheckoutController::class, 'boletoPage'])->name('checkout.boleto');
Route::get('/checkout/order-status', [\App\Http\Controllers\CheckoutController::class, 'orderStatus'])->name('checkout.order-status')->middleware('throttle:30,1');

// Página genérica de retorno para gateways que exigem return_url (ex.: Stripe 3DS)
// quando o tenant não definiu um default_return_url próprio.
Route::get('/payments/return/{order}', [\App\Http\Controllers\PaymentReturnController::class, 'show'])
    ->name('payments.return')
    ->where('order', '[0-9]+')
    ->middleware('throttle:60,1');
Route::post('/checkout', [\App\Http\Controllers\CheckoutController::class, 'process'])
    ->name('checkout.process')
    ->middleware(['throttle:checkout-process', 'throttle:checkout-pix', 'throttle:checkout-card', 'throttle:checkout-email', 'throttle:checkout-product-ip', 'checkout.abuse']);
Route::post('/checkout/cajupay/session', [\App\Http\Controllers\CheckoutController::class, 'cajupaySession'])
    ->name('checkout.cajupay.session')
    ->middleware(['throttle:checkout-process', 'checkout.abuse']);
Route::post('/checkout/cajupay/confirm-order', [\App\Http\Controllers\CheckoutController::class, 'cajupayConfirmOrder'])
    ->name('checkout.cajupay.confirm-order')
    ->middleware(['throttle:checkout-process', 'throttle:checkout-card', 'throttle:checkout-email', 'throttle:checkout-product-ip', 'checkout.abuse']);
// Mesmo handler que webhooks.cajupay — URL alternativa usada em docs/curl da CajuPay
Route::post('/checkout/cajupay/webhook', [\App\Http\Controllers\Webhooks\CajuPayWebhookController::class, 'handle'])
    ->name('webhooks.cajupay.checkout-alias')
    ->middleware('throttle:60,1');
// Pagar.me tokenizecard: se o submit HTML não for cancelado, evita POST na rota GET /c/{slug} (405).
Route::post('/checkout/pagarme-tokenize-sink', fn () => response()->noContent())
    ->name('checkout.pagarme-tokenize-sink')
    ->middleware('throttle:120,1');
Route::post('/api/checkout/track', [\App\Http\Controllers\CheckoutTrackingController::class, 'track'])->name('checkout.track')->middleware('throttle:60,1');
Route::post('/checkout/validate-coupon', [\App\Http\Controllers\CheckoutController::class, 'validateCoupon'])->name('checkout.validate-coupon')->middleware('throttle:30,1');

Route::get('/checkout/upsell', [\App\Http\Controllers\UpsellController::class, 'upsellPage'])->name('checkout.upsell');
Route::get('/checkout/downsell', [\App\Http\Controllers\UpsellController::class, 'downsellPage'])->name('checkout.downsell');
Route::get('/checkout/obrigado', [\App\Http\Controllers\UpsellController::class, 'thankYouPage'])->name('checkout.thank-you');
Route::post('/checkout/upsell/accept', [\App\Http\Controllers\UpsellController::class, 'acceptUpsell'])
    ->name('checkout.upsell.accept')
    ->middleware(['throttle:checkout-process', 'throttle:checkout-pix', 'throttle:checkout-card', 'throttle:checkout-email', 'throttle:checkout-product-ip', 'checkout.abuse']);
Route::post('/checkout/upsell/decline', [\App\Http\Controllers\UpsellController::class, 'declineUpsell'])->name('checkout.upsell.decline')->middleware('throttle:30,1');
Route::post('/checkout/downsell/accept', [\App\Http\Controllers\UpsellController::class, 'acceptDownsell'])->name('checkout.downsell.accept')->middleware('throttle:30,1');
Route::post('/checkout/downsell/decline', [\App\Http\Controllers\UpsellController::class, 'declineDownsell'])->name('checkout.downsell.decline')->middleware('throttle:30,1');

Route::get('/conquistas/{slug}/share', [\App\Http\Controllers\ConquistasController::class, 'share'])
    ->name('conquistas.share')
    ->where('slug', '[a-z0-9-]+');

// Login de alunos (redireciona para comunidade)
Route::get('/entrar', [\App\Http\Controllers\AlunoLoginController::class, 'show'])->name('aluno.login');
Route::post('/entrar', [\App\Http\Controllers\AlunoLoginController::class, 'login'])->middleware('throttle:5,1')->name('aluno.login.post');

Route::middleware('guest')->group(function () {
    Route::get('/criar-admin', [\App\Http\Controllers\CreateFirstAdminController::class, 'show'])->name('criar-admin');
    Route::post('/criar-admin', [\App\Http\Controllers\CreateFirstAdminController::class, 'store'])->middleware('throttle:5,1');
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:5,1');
    Route::get('/esqueci-senha', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
    Route::post('/esqueci-senha', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email')->middleware('throttle:6,1');
    Route::get('/redefinir-senha/{token}', [ResetPasswordController::class, 'showResetForm'])->name('password.reset');
    Route::post('/redefinir-senha', [ResetPasswordController::class, 'reset'])->name('password.update')->middleware('throttle:6,1');
});

Route::middleware('auth')->group(function () {
    Route::match(['get', 'post'], '/logout', [LoginController::class, 'logout'])->name('logout');
});

// Equipe: cargos e membros (admin; equipe apenas se tiver permissão)
Route::middleware(['auth', 'admin.tenant', 'role:admin|team', 'team.permission:equipe.manage'])
    ->prefix('usuarios/equipe')
    ->group(function () {
        Route::get('/', [\App\Http\Controllers\EquipeController::class, 'index'])->name('usuarios.equipe');

        Route::post('/cargos', [\App\Http\Controllers\EquipeController::class, 'storeRole'])->name('usuarios.equipe.cargos.store');
        Route::put('/cargos/{role}', [\App\Http\Controllers\EquipeController::class, 'updateRole'])->name('usuarios.equipe.cargos.update');
        Route::delete('/cargos/{role}', [\App\Http\Controllers\EquipeController::class, 'destroyRole'])->name('usuarios.equipe.cargos.destroy');

        Route::post('/membros', [\App\Http\Controllers\EquipeController::class, 'storeMember'])->name('usuarios.equipe.membros.store');
        Route::put('/membros/{member}', [\App\Http\Controllers\EquipeController::class, 'updateMember'])->name('usuarios.equipe.membros.update');
        Route::delete('/membros/{member}', [\App\Http\Controllers\EquipeController::class, 'destroyMember'])->name('usuarios.equipe.membros.destroy');

        Route::post('/logs/clear', [\App\Http\Controllers\EquipeController::class, 'clearLogs'])->name('usuarios.equipe.logs.clear');
    });

Route::middleware(['auth', 'admin.tenant', 'role:admin|team', 'audit.log'])->group(function () {
    Route::post('/painel/push-subscribe', [\App\Http\Controllers\PanelPwaController::class, 'pushSubscribe'])->name('panel.pwa.push-subscribe')->middleware('throttle:10,1');
    Route::get('/painel/notifications', [\App\Http\Controllers\PanelNotificationsController::class, 'index'])->name('panel.notifications.index');
    Route::patch('/painel/notifications/{notification}/read', [\App\Http\Controllers\PanelNotificationsController::class, 'markRead'])->name('panel.notifications.mark-read');
    Route::post('/painel/notifications/mark-read', [\App\Http\Controllers\PanelNotificationsController::class, 'markReadBatch'])->name('panel.notifications.mark-read-batch');
    Route::post('/painel/notifications/mark-all-read', [\App\Http\Controllers\PanelNotificationsController::class, 'markAllRead'])->name('panel.notifications.mark-all-read');
    Route::delete('/painel/notifications', [\App\Http\Controllers\PanelNotificationsController::class, 'clearAll'])->name('panel.notifications.clear-all');
    Route::get('/cloud/billing/status', function (Request $request) {
        if (! config('getfy.cloud_mode')) {
            abort(404);
        }

        $token = (string) env('GETFY_CLOUD_INSTALL_TOKEN', '');
        $base = (string) config('getfy.cloud.orch_api_base_url', '');
        if ($token === '' || $base === '') {
            return response()->json(['enabled' => false]);
        }

        $cacheMinutes = max(1, (int) config('getfy.cloud.billing_cache_minutes', 10));
        $cacheKey = 'cloud:billing:status';
        $lastGoodKey = 'cloud:billing:status:last_good';

        try {
            $payload = Cache::remember($cacheKey, now()->addMinutes($cacheMinutes), function () use ($base, $token, $lastGoodKey) {
                $url = $base.'/v1/public/billing/status';
                $hostHeader = parse_url($url, PHP_URL_HOST);
                $headers = array_filter([
                    'Authorization' => 'Bearer '.$token,
                    'Host' => $hostHeader ?: null,
                ]);

                $res = Http::timeout(10)
                    ->connectTimeout(5)
                    ->withHeaders($headers)
                    ->get($url);

                if ($res->status() === 401) {
                    return ['enabled' => false];
                }

                if (! $res->successful()) {
                    throw new \RuntimeException('Orchestrator retornou HTTP '.$res->status().'.');
                }

                $json = $res->json();
                if (! is_array($json)) {
                    throw new \RuntimeException('Resposta inválida do Orchestrator.');
                }

                $payload = ['enabled' => true] + $json;
                $payload['portalUrl'] = 'http://getfy.cloud/login';
                Cache::put($lastGoodKey, $payload, now()->addMinutes(60));

                return $payload;
            });

            return response()->json(is_array($payload) ? $payload : ['enabled' => false]);
        } catch (\Throwable $e) {
            $last = Cache::get($lastGoodKey);
            if (is_array($last) && isset($last['enabled'])) {
                return response()->json($last);
            }

            report($e);

            return response()->json(['enabled' => false]);
        }
    })->name('cloud.billing.status')->middleware('throttle:60,1');
    Route::get('/conquistas', [\App\Http\Controllers\ConquistasController::class, 'index'])->name('conquistas.index');
    Route::get('/meu-perfil', [\App\Http\Controllers\ProfileController::class, 'index'])->name('profile.index');
    Route::post('/meu-perfil', [\App\Http\Controllers\ProfileController::class, 'update'])->name('profile.update');
    Route::put('/meu-perfil/username', [\App\Http\Controllers\ProfileController::class, 'updateUsername'])->name('profile.update-username');
    Route::put('/meu-perfil/senha', [\App\Http\Controllers\ProfileController::class, 'updatePassword'])->name('profile.update-password');

    Route::get('/dashboard', [\App\Http\Controllers\DashboardController::class, '__invoke'])
        ->middleware('team.permission:dashboard.view')
        ->name('dashboard');

    Route::middleware('team.permission:reembolsos.view')->group(function () {
        Route::get('/reembolsos', [\App\Http\Controllers\ReembolsosController::class, 'index'])->name('reembolsos.index');
        Route::post('/reembolsos/{refundRequest}/approve', [\App\Http\Controllers\ReembolsosController::class, 'approve'])
            ->middleware('team.permission:reembolsos.manage')
            ->name('reembolsos.approve');
        Route::post('/reembolsos/{refundRequest}/reject', [\App\Http\Controllers\ReembolsosController::class, 'reject'])
            ->middleware('team.permission:reembolsos.manage')
            ->name('reembolsos.reject');
    });

    Route::middleware('team.permission:vendas.view')->group(function () {
        Route::get('/vendas', [\App\Http\Controllers\VendasController::class, 'index'])->name('vendas.index');
        Route::get('/vendas/export', [\App\Http\Controllers\VendasController::class, 'export'])->name('vendas.export');
        Route::post('/vendas/{order}/resend-access-email', [\App\Http\Controllers\VendasController::class, 'resendAccessEmail'])->name('vendas.resend-access-email');
        Route::post('/vendas/{order}/approve-manually', [\App\Http\Controllers\VendasController::class, 'approveManually'])->name('vendas.approve-manually');
        Route::post('/vendas/{order}/refund', [\App\Http\Controllers\VendasController::class, 'refund'])
            ->middleware('team.permission:reembolsos.manage')
            ->name('vendas.refund');

        // Dossiê de comprovação (gateways/compliance)
        Route::get('/vendas/{order}/comprovacao', [\App\Http\Controllers\ProofDocumentsController::class, 'show'])->name('vendas.proof.show');
        Route::post('/vendas/{order}/comprovacao/gerar', [\App\Http\Controllers\ProofDocumentsController::class, 'generate'])->name('vendas.proof.generate');
        Route::get('/vendas/{order}/comprovacao/pdf', [\App\Http\Controllers\ProofDocumentsController::class, 'pdf'])->name('vendas.proof.pdf');

        // Exportação em lote (ZIP) com filtros
        Route::get('/vendas/comprovacao/exportar', [\App\Http\Controllers\ProofExportsController::class, 'index'])->name('vendas.proof.export.index');
        Route::post('/vendas/comprovacao/exportar/zip', [\App\Http\Controllers\ProofExportsController::class, 'exportZip'])->name('vendas.proof.export.zip');
        Route::post('/vendas/comprovacao/exportar/pdf', [\App\Http\Controllers\ProofExportsController::class, 'exportPdf'])->name('vendas.proof.export.pdf');
    });

    Route::middleware('team.permission:produtos.view')->group(function () {
        Route::get('/produtos', [\App\Http\Controllers\ProdutosController::class, 'index'])->name('produtos.index');
        Route::get('/produtos/create', [\App\Http\Controllers\ProdutosController::class, 'create'])->name('produtos.create');
        Route::post('/produtos', [\App\Http\Controllers\ProdutosController::class, 'store'])->name('produtos.store');
        Route::get('/produtos/{produto}/edit', [\App\Http\Controllers\ProdutosController::class, 'edit'])->name('produtos.edit');
        Route::get('/produtos/{produto}/checkout/edit', [\App\Http\Controllers\CheckoutConfigController::class, 'edit'])->name('checkout.builder');
        Route::post('/produtos/{produto}/checkout/ensure-slug', [\App\Http\Controllers\ProdutosController::class, 'ensureCheckoutSlug'])->name('produtos.checkout.ensure-slug');
        Route::delete('/produtos/{produto}/checkout/remove-slug', [\App\Http\Controllers\ProdutosController::class, 'removeCheckoutSlug'])->name('produtos.checkout.remove-slug');
        Route::put('/produtos/{produto}/checkout-config', [\App\Http\Controllers\CheckoutConfigController::class, 'update'])->name('checkout.config.update');
        Route::post('/produtos/{produto}/checkout-upload', [\App\Http\Controllers\CheckoutConfigController::class, 'uploadImage'])->name('checkout.upload');
    });
    Route::middleware('team.permission:produtos.view')->group(function () {
        Route::get('/produtos/{produto}/upsell-page/edit', [\App\Http\Controllers\UpsellDownsellPageController::class, 'editUpsellPage'])->name('upsell-page.edit');
        Route::put('/produtos/{produto}/upsell-page/config', [\App\Http\Controllers\UpsellDownsellPageController::class, 'updateUpsellPage'])->name('upsell-page.update');
        Route::post('/produtos/{produto}/upsell-page/config', [\App\Http\Controllers\UpsellDownsellPageController::class, 'updateUpsellPage'])->name('upsell-page.update.post');
        Route::get('/produtos/{produto}/downsell-page/edit', [\App\Http\Controllers\UpsellDownsellPageController::class, 'editDownsellPage'])->name('downsell-page.edit');
        Route::put('/produtos/{produto}/downsell-page/config', [\App\Http\Controllers\UpsellDownsellPageController::class, 'updateDownsellPage'])->name('downsell-page.update');
        Route::post('/produtos/{produto}/downsell-page/config', [\App\Http\Controllers\UpsellDownsellPageController::class, 'updateDownsellPage'])->name('downsell-page.update.post');
        Route::put('/produtos/{produto}', [\App\Http\Controllers\ProdutosController::class, 'update'])->name('produtos.update');
        Route::put('/produtos/{produto}/ai-context', [\App\Http\Controllers\ProdutosAiContextController::class, 'update'])->name('produtos.ai-context.update');
        Route::post('/produtos/{produto}/ai-context/upload', [\App\Http\Controllers\ProdutosAiContextController::class, 'upload'])->name('produtos.ai-context.upload');
        Route::delete('/produtos/{produto}/ai-context/files/{fileId}', [\App\Http\Controllers\ProdutosAiContextController::class, 'destroyFile'])->name('produtos.ai-context.file.destroy');
        Route::post('/produtos/{produto}/email-template-logo', [\App\Http\Controllers\ProdutosController::class, 'uploadEmailTemplateLogo'])->name('produtos.email-template-logo');
        Route::delete('/produtos/{produto}', [\App\Http\Controllers\ProdutosController::class, 'destroy'])->name('produtos.destroy');
        Route::post('/produtos/{produto}/duplicate', [\App\Http\Controllers\ProdutosController::class, 'duplicate'])->name('produtos.duplicate');
        Route::post('/produtos/{produto}/alunos', [\App\Http\Controllers\ProdutosController::class, 'addAluno'])->name('produtos.alunos.add');
        Route::post('/produtos/{produto}/offers', [\App\Http\Controllers\ProdutosController::class, 'storeOffer'])->name('produtos.offers.store');
        Route::put('/produtos/{produto}/offers/{offer}', [\App\Http\Controllers\ProdutosController::class, 'updateOffer'])->name('produtos.offers.update');
        Route::delete('/produtos/{produto}/offers/{offer}', [\App\Http\Controllers\ProdutosController::class, 'destroyOffer'])->name('produtos.offers.destroy');
        Route::post('/produtos/{produto}/order-bumps', [\App\Http\Controllers\ProdutosController::class, 'storeOrderBump'])->name('produtos.order-bumps.store');
        Route::put('/produtos/{produto}/order-bumps/{bump}', [\App\Http\Controllers\ProdutosController::class, 'updateOrderBump'])->name('produtos.order-bumps.update');
        Route::delete('/produtos/{produto}/order-bumps/{bump}', [\App\Http\Controllers\ProdutosController::class, 'destroyOrderBump'])->name('produtos.order-bumps.destroy');
        Route::post('/produtos/{produto}/subscription-plans', [\App\Http\Controllers\ProdutosController::class, 'storeSubscriptionPlan'])->name('produtos.subscription-plans.store');
        Route::put('/produtos/{produto}/subscription-plans/{plan}', [\App\Http\Controllers\ProdutosController::class, 'updateSubscriptionPlan'])->name('produtos.subscription-plans.update');
        Route::delete('/produtos/{produto}/subscription-plans/{plan}', [\App\Http\Controllers\ProdutosController::class, 'destroySubscriptionPlan'])->name('produtos.subscription-plans.destroy');
        Route::put('/produtos/{produto}/external-member-area', [\App\Http\Controllers\ProdutosController::class, 'updateExternalMemberArea'])->name('produtos.external-member-area.update');
        Route::put('/produtos/{produto}/member-area-refund', [\App\Http\Controllers\ProdutosController::class, 'updateMemberAreaRefund'])->name('produtos.member-area-refund.update');
        Route::get('/produtos/cupons', [\App\Http\Controllers\CuponsController::class, 'index'])->name('cupons.index');
        Route::post('/produtos/cupons', [\App\Http\Controllers\CuponsController::class, 'store'])->name('cupons.store');
        Route::put('/produtos/cupons/{coupon}', [\App\Http\Controllers\CuponsController::class, 'update'])->name('cupons.update');
        Route::delete('/produtos/cupons/{coupon}', [\App\Http\Controllers\CuponsController::class, 'destroy'])->name('cupons.destroy');
        Route::get('/alunos', [\App\Http\Controllers\AlunosController::class, 'index'])->name('alunos.root');
        Route::get('/produtos/alunos', [\App\Http\Controllers\AlunosController::class, 'index'])->name('alunos.index');
        Route::get('/produtos/alunos/{aluno}', [\App\Http\Controllers\AlunosController::class, 'show'])->name('alunos.show')->where('aluno', '[0-9]+');
        Route::post('/produtos/alunos', [\App\Http\Controllers\AlunosController::class, 'store'])->name('alunos.store');
        Route::get('/produtos/alunos/import-example', [\App\Http\Controllers\AlunosController::class, 'downloadImportExample'])->name('alunos.import-example');
        Route::post('/produtos/alunos/import', [\App\Http\Controllers\AlunosController::class, 'import'])->name('alunos.import');
        Route::put('/produtos/alunos/{aluno}', [\App\Http\Controllers\AlunosController::class, 'update'])->name('alunos.update')->where('aluno', '[0-9]+');
        Route::delete('/produtos/alunos/{aluno}', [\App\Http\Controllers\AlunosController::class, 'destroy'])->name('alunos.destroy')->where('aluno', '[0-9]+');
        Route::delete('/produtos/alunos/{aluno}/produtos/{produto}', [\App\Http\Controllers\AlunosController::class, 'removeProduct'])->name('alunos.remove-product')->where('aluno', '[0-9]+');
        Route::post('/produtos/alunos/{aluno}/produtos/{produto}', [\App\Http\Controllers\AlunosController::class, 'addProduct'])->name('alunos.add-product')->where('aluno', '[0-9]+');
        Route::get('/produtos/alunos/{aluno}/quiz-responses', [\App\Http\Controllers\AlunosController::class, 'quizResponses'])->name('alunos.quiz-responses')->where('aluno', '[0-9]+');
        Route::put('/produtos/alunos/{aluno}/toggle-block', [\App\Http\Controllers\AlunosController::class, 'toggleBlock'])->name('alunos.toggle-block')->where('aluno', '[0-9]+');
        Route::get('/alunos/{aluno}', [\App\Http\Controllers\AlunosController::class, 'showPage'])->name('alunos.page')->where('aluno', '[0-9]+');
        Route::post('/alunos/{aluno}/gerar-relatorio-ia', [\App\Http\Controllers\AlunosController::class, 'generateAiReport'])->name('alunos.generate-ai-report')->where('aluno', '[0-9]+');
        Route::post('/alunos/{aluno}/atribuir-jornada', [\App\Http\Controllers\AlunosController::class, 'atribuirJornada'])->name('alunos.atribuir-jornada')->where('aluno', '[0-9]+');
        Route::get('/alunos/{aluno}/relatorio/{insight}/imprimir', [\App\Http\Controllers\AlunosController::class, 'printReport'])->name('alunos.report.print')->where('aluno', '[0-9]+');
        Route::put('/alunos/{aluno}/relatorio/{insight}', [\App\Http\Controllers\AlunosController::class, 'updateInsight'])->name('alunos.report.update')->where('aluno', '[0-9]+');

        // Member Builder (área de membros do produto)
        Route::get('/produtos/{produto}/member-builder', [\App\Http\Controllers\MemberBuilderController::class, 'index'])->name('member-builder.index');
        Route::put('/produtos/{produto}/member-builder/config', [\App\Http\Controllers\MemberBuilderController::class, 'updateConfig'])->name('member-builder.config.update');
        // POST aceito para config: frontend envia JSON e em muitos ambientes _method não é aplicado a body JSON
        Route::post('/produtos/{produto}/member-builder/config', [\App\Http\Controllers\MemberBuilderController::class, 'updateConfig'])->name('member-builder.config.update.post');
        Route::post('/produtos/{produto}/member-builder/upload', [\App\Http\Controllers\MemberBuilderController::class, 'uploadImage'])->name('member-builder.upload');
        Route::post('/produtos/{produto}/member-builder/upload-pdf', [\App\Http\Controllers\MemberBuilderController::class, 'uploadPdf'])->name('member-builder.upload-pdf');
        Route::post('/produtos/{produto}/member-builder/upload-badge', [\App\Http\Controllers\MemberBuilderController::class, 'uploadBadge'])->name('member-builder.upload-badge');
        Route::post('/produtos/{produto}/member-builder/sections', [\App\Http\Controllers\MemberBuilderController::class, 'storeSection'])->name('member-builder.sections.store');
        Route::put('/produtos/{produto}/member-builder/sections/{section}', [\App\Http\Controllers\MemberBuilderController::class, 'updateSection'])->name('member-builder.sections.update');
        Route::delete('/produtos/{produto}/member-builder/sections/{section}', [\App\Http\Controllers\MemberBuilderController::class, 'destroySection'])->name('member-builder.sections.destroy');
        Route::post('/produtos/{produto}/member-builder/sections/{section}/modules', [\App\Http\Controllers\MemberBuilderController::class, 'storeModule'])->name('member-builder.modules.store');
        Route::put('/produtos/{produto}/member-builder/modules/{module}', [\App\Http\Controllers\MemberBuilderController::class, 'updateModule'])->name('member-builder.modules.update');
        Route::delete('/produtos/{produto}/member-builder/modules/{module}', [\App\Http\Controllers\MemberBuilderController::class, 'destroyModule'])->name('member-builder.modules.destroy');
        Route::post('/produtos/{produto}/member-builder/modules/{module}/lessons', [\App\Http\Controllers\MemberBuilderController::class, 'storeLesson'])->name('member-builder.lessons.store');
        Route::put('/produtos/{produto}/member-builder/lessons/{lesson}', [\App\Http\Controllers\MemberBuilderController::class, 'updateLesson'])->name('member-builder.lessons.update');
        Route::delete('/produtos/{produto}/member-builder/lessons/{lesson}', [\App\Http\Controllers\MemberBuilderController::class, 'destroyLesson'])->name('member-builder.lessons.destroy');
        Route::get('/produtos/{produto}/member-builder/lessons/{lesson}/quiz-report', [\App\Http\Controllers\MemberAreaAppController::class, 'quizReport'])->name('member-builder.lessons.quiz-report');
        Route::post('/produtos/{produto}/member-builder/internal-products', [\App\Http\Controllers\MemberBuilderController::class, 'storeInternalProduct'])->name('member-builder.internal-products.store');
        Route::delete('/produtos/{produto}/member-builder/internal-products/{internalProduct}', [\App\Http\Controllers\MemberBuilderController::class, 'destroyInternalProduct'])->name('member-builder.internal-products.destroy');
        Route::post('/produtos/{produto}/member-builder/turmas', [\App\Http\Controllers\MemberBuilderController::class, 'storeTurma'])->name('member-builder.turmas.store');
        Route::put('/produtos/{produto}/member-builder/turmas/{turma}', [\App\Http\Controllers\MemberBuilderController::class, 'updateTurma'])->name('member-builder.turmas.update');
        Route::delete('/produtos/{produto}/member-builder/turmas/{turma}', [\App\Http\Controllers\MemberBuilderController::class, 'destroyTurma'])->name('member-builder.turmas.destroy');
        Route::post('/produtos/{produto}/member-builder/turmas/{turma}/users', [\App\Http\Controllers\MemberBuilderController::class, 'attachTurmaUser'])->name('member-builder.turmas.users.attach');
        Route::delete('/produtos/{produto}/member-builder/turmas/{turma}/users/{user}', [\App\Http\Controllers\MemberBuilderController::class, 'detachTurmaUser'])->name('member-builder.turmas.users.detach');
        Route::post('/produtos/{produto}/member-builder/alunos', [\App\Http\Controllers\MemberBuilderController::class, 'storeNewAluno'])->name('member-builder.alunos.store');
        Route::get('/produtos/{produto}/member-builder/comments', [\App\Http\Controllers\MemberBuilderController::class, 'commentsIndex'])->name('member-builder.comments.index');
        Route::put('/produtos/{produto}/member-builder/comments/{comment}', [\App\Http\Controllers\MemberBuilderController::class, 'updateComment'])->name('member-builder.comments.update');
        Route::post('/produtos/{produto}/member-builder/community-pages', [\App\Http\Controllers\MemberBuilderController::class, 'storeCommunityPage'])->name('member-builder.community-pages.store');
        Route::put('/produtos/{produto}/member-builder/community-pages/{page}', [\App\Http\Controllers\MemberBuilderController::class, 'updateCommunityPage'])->name('member-builder.community-pages.update');
        Route::delete('/produtos/{produto}/member-builder/community-pages/{page}', [\App\Http\Controllers\MemberBuilderController::class, 'destroyCommunityPage'])->name('member-builder.community-pages.destroy');
        Route::post('/produtos/{produto}/member-builder/send-push', [\App\Http\Controllers\MemberBuilderController::class, 'sendPushNotification'])->name('member-builder.send-push');
    });

    Route::get('/vendas/assinaturas', [\App\Http\Controllers\AssinaturasController::class, 'index'])
        ->middleware('team.permission:vendas.view')
        ->name('assinaturas.index');
    Route::middleware('team.permission:relatorios.view')->group(function () {
        Route::get('/relatorios',                    [\App\Http\Controllers\RelatoriosController::class, 'index'])->name('relatorios.index');
        Route::get('/relatorios/vendas',             [\App\Http\Controllers\RelatoriosController::class, 'vendas'])->name('relatorios.vendas');
        Route::get('/relatorios/alunos',             [\App\Http\Controllers\RelatoriosController::class, 'alunos'])->name('relatorios.alunos');
        Route::get('/relatorios/conversao',          [\App\Http\Controllers\RelatoriosController::class, 'conversao'])->name('relatorios.conversao');
        Route::get('/relatorios/produtos',           [\App\Http\Controllers\RelatoriosController::class, 'produtos'])->name('relatorios.produtos');
        Route::get('/relatorios/assinaturas',        [\App\Http\Controllers\RelatoriosController::class, 'assinaturas'])->name('relatorios.assinaturas');
        Route::get('/relatorios/exportacoes',        [\App\Http\Controllers\RelatoriosController::class, 'exportacoes'])->name('relatorios.exportacoes');
        Route::get('/relatorios/export/vendas',      [\App\Http\Controllers\RelatoriosController::class, 'exportVendas'])->middleware('throttle:20,1')->name('relatorios.export.vendas');
        Route::get('/relatorios/export/alunos',      [\App\Http\Controllers\RelatoriosController::class, 'exportAlunos'])->middleware('throttle:20,1')->name('relatorios.export.alunos');
        Route::get('/relatorios/export/meta-compradores', [\App\Http\Controllers\RelatoriosController::class, 'exportMetaCompradores'])->middleware('throttle:20,1')->name('relatorios.export.meta-compradores');
        Route::get('/relatorios/export/meta-abandonos',   [\App\Http\Controllers\RelatoriosController::class, 'exportMetaAbandonos'])->middleware('throttle:20,1')->name('relatorios.export.meta-abandonos');
    });

    Route::middleware('team.permission:configuracoes.view')->group(function () {
        Route::get('/configuracoes/area-aluno/data', [\App\Http\Controllers\MemberAreaSettingsController::class, 'data'])->name('member-area-settings.data');
        Route::put('/configuracoes/area-aluno', [\App\Http\Controllers\MemberAreaSettingsController::class, 'update'])->name('member-area-settings.update');
        Route::post('/configuracoes/area-aluno/upload', [\App\Http\Controllers\MemberAreaSettingsController::class, 'upload'])->name('member-area-settings.upload');

        Route::get('/configuracoes', [\App\Http\Controllers\SettingsController::class, 'index'])->name('settings.index');
        Route::put('/configuracoes', [\App\Http\Controllers\SettingsController::class, 'update'])->name('settings.update');
        Route::post('/configuracoes/currencies/import-catalog', [\App\Http\Controllers\SettingsController::class, 'importCurrencyCatalog'])->name('settings.currencies.import');
        Route::post('/configuracoes/currencies/sync-rates', [\App\Http\Controllers\SettingsController::class, 'syncCurrencyRates'])->name('settings.currencies.sync');
        Route::post('/configuracoes/email/test', [\App\Http\Controllers\EmailTestController::class, 'test'])->name('settings.email.test');
        Route::post('/configuracoes/email/connection-test', [\App\Http\Controllers\EmailTestController::class, 'connectionTest'])->name('settings.email.connection-test');
        Route::post('/configuracoes/email/send-test', [\App\Http\Controllers\EmailTestController::class, 'sendTest'])->name('settings.email.send-test');
        Route::post('/configuracoes/storage/test', [\App\Http\Controllers\StorageTestController::class, '__invoke'])->name('settings.storage.test');
        Route::post('/configuracoes/storage/migrate', [\App\Http\Controllers\StorageMigrateController::class, '__invoke'])->name('settings.storage.migrate');
        Route::get('/configuracoes/update/check', [\App\Http\Controllers\UpdateController::class, 'check'])->name('settings.update.check');
        Route::get('/configuracoes/update/integrity', [\App\Http\Controllers\UpdateController::class, 'integrity'])->name('settings.update.integrity');
        Route::post('/configuracoes/update/migrate', [\App\Http\Controllers\UpdateController::class, 'migrateNow'])->name('settings.update.migrate')->middleware('throttle:10,1');
        Route::post('/configuracoes/update/run', [\App\Http\Controllers\UpdateController::class, 'run'])->name('settings.update.run')->middleware('throttle:10,1');
        Route::get('/configuracoes/gateways/{slug}', [\App\Http\Controllers\GatewaysController::class, 'show'])->name('gateways.show');
        Route::put('/configuracoes/gateways/{slug}', [\App\Http\Controllers\GatewaysController::class, 'update'])->name('gateways.update');
        Route::post('/configuracoes/gateways/{slug}/test', [\App\Http\Controllers\GatewaysController::class, 'test'])->name('gateways.test');
    });
    // Upload de arquivo (PHP/Laravel lida melhor via POST)
    Route::post('/configuracoes/gateways/{slug}/certificate', [\App\Http\Controllers\GatewaysController::class, 'updateCertificate'])
        ->middleware('team.permission:configuracoes.view')
        ->name('gateways.certificate');
    // Compat: mantem PUT caso algum cliente antigo use
    Route::put('/configuracoes/gateways/{slug}/certificate', [\App\Http\Controllers\GatewaysController::class, 'updateCertificate'])
        ->middleware('team.permission:configuracoes.view');
    Route::put('/configuracoes/gateways/order', [\App\Http\Controllers\GatewaysController::class, 'updateOrder'])
        ->middleware('team.permission:configuracoes.view')
        ->name('gateways.order');
    Route::middleware('role:admin')->group(function () {
        Route::get('/gerenciar-plugins', [\App\Http\Controllers\PluginsController::class, 'index'])->name('plugins.index');
        Route::get('/gerenciar-plugins/store-plugins-list', [\App\Http\Controllers\PluginsController::class, 'storePluginsList'])->name('plugins.store.list');
        Route::get('/gerenciar-plugins/store-plugin/{slug}', [\App\Http\Controllers\PluginStoreController::class, 'show'])->name('plugins.store.show')->where('slug', '[a-z0-9\-]+');
        Route::post('/gerenciar-plugins/install/{slug}', [\App\Http\Controllers\PluginInstallController::class, '__invoke'])->name('plugins.install')->where('slug', '[a-z0-9\-]+')->middleware('throttle:10,1');
        Route::post('/gerenciar-plugins/install-from-zip', [\App\Http\Controllers\PluginInstallController::class, 'installFromZip'])->name('plugins.install.from-zip')->middleware('throttle:10,1');
        Route::post('/gerenciar-plugins/register-plugin/{slug}', [\App\Http\Controllers\PluginsController::class, 'registerPlugin'])->name('plugins.register')->where('slug', '[a-z0-9\-_]+')->middleware('throttle:10,1');
    });

    Route::get('/integracoes', [\App\Http\Controllers\IntegrationsController::class, 'index'])
        ->middleware('team.permission:integracoes.view')
        ->name('integrations.index');

    // API de pagamentos – aplicações
    Route::middleware('team.permission:api_pagamentos.view')->group(function () {
        Route::get('/aplicacoes-api', [\App\Http\Controllers\ApiApplicationsController::class, 'index'])->name('api-applications.index');
        Route::get('/aplicacoes-api/create', [\App\Http\Controllers\ApiApplicationsController::class, 'create'])->name('api-applications.create');
        Route::post('/aplicacoes-api', [\App\Http\Controllers\ApiApplicationsController::class, 'store'])->name('api-applications.store');
        Route::get('/aplicacoes-api/{apiApplication}/edit', [\App\Http\Controllers\ApiApplicationsController::class, 'edit'])->name('api-applications.edit');
        Route::put('/aplicacoes-api/{apiApplication}', [\App\Http\Controllers\ApiApplicationsController::class, 'update'])->name('api-applications.update');
        Route::delete('/aplicacoes-api/{apiApplication}', [\App\Http\Controllers\ApiApplicationsController::class, 'destroy'])->name('api-applications.destroy');
        Route::post('/aplicacoes-api/{apiApplication}/regenerate-key', [\App\Http\Controllers\ApiApplicationsController::class, 'regenerateKey'])->name('api-applications.regenerate-key');
        Route::post('/aplicacoes-api/{apiApplication}/logo', [\App\Http\Controllers\ApiApplicationsController::class, 'uploadLogo'])->name('api-applications.logo.upload');
        Route::delete('/aplicacoes-api/{apiApplication}/logo', [\App\Http\Controllers\ApiApplicationsController::class, 'removeLogo'])->name('api-applications.logo.remove');
        Route::get('/docs/api-pagamentos', [\App\Http\Controllers\ApiDocsController::class, '__invoke'])->name('api-docs.pagamentos');
        Route::get('/docs/api-pagamentos/testar', [\App\Http\Controllers\ApiDocsController::class, 'testar'])->name('api-docs.pagamentos.testar');
    });
    Route::middleware('team.permission:integracoes.view')->group(function () {
        // LLM
        Route::post('/integracoes/llm/key',      [\App\Http\Controllers\IntegrationsController::class, 'saveLlmKey'])->name('integrations.llm.key.save');
        Route::post('/integracoes/llm/remove',   [\App\Http\Controllers\IntegrationsController::class, 'removeLlmKey'])->name('integrations.llm.key.remove');
        Route::post('/integracoes/llm/preferred',[\App\Http\Controllers\IntegrationsController::class, 'setPreferredLlm'])->name('integrations.llm.preferred');
        // Plugins
        Route::post('/integracoes/plugins/{slug}/enable', [\App\Http\Controllers\IntegrationsController::class, 'enablePlugin'])->name('integrations.plugins.enable');
        Route::post('/integracoes/plugins/{slug}/disable', [\App\Http\Controllers\IntegrationsController::class, 'disablePlugin'])->name('integrations.plugins.disable');
        Route::delete('/integracoes/plugins/{slug}', [\App\Http\Controllers\IntegrationsController::class, 'uninstallPlugin'])->name('integrations.plugins.uninstall');

        Route::post('/integracoes/utmify', [\App\Http\Controllers\UtmifyController::class, 'store'])->name('integrations.utmify.store');
        Route::put('/integracoes/utmify/{utmify}', [\App\Http\Controllers\UtmifyController::class, 'update'])->name('integrations.utmify.update');
        Route::delete('/integracoes/utmify/{utmify}', [\App\Http\Controllers\UtmifyController::class, 'destroy'])->name('integrations.utmify.destroy');

        Route::post('/integracoes/spedy', [\App\Http\Controllers\SpedyController::class, 'store'])->name('integrations.spedy.store');
        Route::put('/integracoes/spedy/{spedy}', [\App\Http\Controllers\SpedyController::class, 'update'])->name('integrations.spedy.update');
        Route::delete('/integracoes/spedy/{spedy}', [\App\Http\Controllers\SpedyController::class, 'destroy'])->name('integrations.spedy.destroy');

        Route::post('/integracoes/cademi', [\App\Http\Controllers\CademiController::class, 'store'])->name('integrations.cademi.store');
        Route::put('/integracoes/cademi/{cademi}', [\App\Http\Controllers\CademiController::class, 'update'])->name('integrations.cademi.update');
        Route::delete('/integracoes/cademi/{cademi}', [\App\Http\Controllers\CademiController::class, 'destroy'])->name('integrations.cademi.destroy');
    Route::get('/integracoes/cademi/{cademi}/tags', [\App\Http\Controllers\CademiController::class, 'tags'])->name('integrations.cademi.tags');

        Route::get('/integracoes/webhooks', [\App\Http\Controllers\WebhookController::class, 'index'])->name('integrations.webhooks.index');
        Route::post('/integracoes/webhooks', [\App\Http\Controllers\WebhookController::class, 'store'])->name('integrations.webhooks.store');
        Route::put('/integracoes/webhooks/{webhook}', [\App\Http\Controllers\WebhookController::class, 'update'])->name('integrations.webhooks.update');
        Route::delete('/integracoes/webhooks/{webhook}', [\App\Http\Controllers\WebhookController::class, 'destroy'])->name('integrations.webhooks.destroy');
        Route::post('/integracoes/webhooks/{webhook}/test', [\App\Http\Controllers\WebhookController::class, 'test'])->name('integrations.webhooks.test');
        Route::get('/integracoes/webhooks/{webhook}/logs', [\App\Http\Controllers\WebhookController::class, 'logs'])->name('integrations.webhooks.logs');
        Route::get('/integracoes/webhooks/{webhook}/logs/{log}', [\App\Http\Controllers\WebhookController::class, 'showLog'])->name('integrations.webhooks.logs.show');
    });

    // E-mail Marketing
    // Campanhas de Mensagens (WhatsApp / SMS)
    Route::middleware(['role:admin'])->group(function () {
        Route::get('/mensagens', [\App\Http\Controllers\MessagingCampaignController::class, 'index'])->name('messaging.index');
        Route::get('/mensagens/criar', [\App\Http\Controllers\MessagingCampaignController::class, 'create'])->name('messaging.create');
        Route::post('/mensagens', [\App\Http\Controllers\MessagingCampaignController::class, 'store'])->name('messaging.store');
        Route::get('/mensagens/{campaign}/editar', [\App\Http\Controllers\MessagingCampaignController::class, 'edit'])->name('messaging.edit');
        Route::put('/mensagens/{campaign}', [\App\Http\Controllers\MessagingCampaignController::class, 'update'])->name('messaging.update');
        Route::post('/mensagens/{campaign}/disparar', [\App\Http\Controllers\MessagingCampaignController::class, 'send'])->name('messaging.send');
        Route::post('/mensagens/preview-destinatarios', [\App\Http\Controllers\MessagingCampaignController::class, 'previewRecipients'])->name('messaging.preview');
        Route::post('/mensagens/provedores/{provider}/credenciais', [\App\Http\Controllers\MessagingCampaignController::class, 'saveProviderCredentials'])->name('messaging.provider.save');
        Route::get('/mensagens/provedores/{provider}/credenciais', [\App\Http\Controllers\MessagingCampaignController::class, 'getProviderCredentials'])->name('messaging.provider.get');
    });

    // Comunidade Admin
    Route::middleware(['role:admin'])->group(function () {
        Route::get('/comunidade-admin', [\App\Http\Controllers\AdminCommunityController::class, 'index'])->name('admin-community.index');
        Route::post('/comunidade-admin/posts', [\App\Http\Controllers\AdminCommunityController::class, 'storePost'])->name('admin-community.posts.store');
        Route::put('/comunidade-admin/posts/{post}', [\App\Http\Controllers\AdminCommunityController::class, 'updatePost'])->name('admin-community.posts.update');
        Route::post('/comunidade-admin/posts/{post}/toggle-hide', [\App\Http\Controllers\AdminCommunityController::class, 'toggleHidePost'])->name('admin-community.posts.toggle-hide');
        Route::delete('/comunidade-admin/posts/{post}', [\App\Http\Controllers\AdminCommunityController::class, 'destroyPost'])->name('admin-community.posts.destroy');
        Route::post('/comunidade-admin/eventos', [\App\Http\Controllers\AdminCommunityController::class, 'storeEvent'])->name('admin-community.events.store');
        Route::put('/comunidade-admin/eventos/{event}', [\App\Http\Controllers\AdminCommunityController::class, 'updateEvent'])->name('admin-community.events.update');
        Route::delete('/comunidade-admin/eventos/{event}', [\App\Http\Controllers\AdminCommunityController::class, 'destroyEvent'])->name('admin-community.events.destroy');
        Route::post('/comunidade-admin/grupos', [\App\Http\Controllers\AdminCommunityController::class, 'storeGroup'])->name('admin-community.groups.store');
        Route::put('/comunidade-admin/grupos/{group}', [\App\Http\Controllers\AdminCommunityController::class, 'updateGroup'])->name('admin-community.groups.update');
        Route::delete('/comunidade-admin/grupos/{group}', [\App\Http\Controllers\AdminCommunityController::class, 'destroyGroup'])->name('admin-community.groups.destroy');
        Route::get('/comunidade-admin/grupos/{group}/membros', [\App\Http\Controllers\AdminCommunityController::class, 'groupMembers'])->name('admin-community.groups.members');
        Route::post('/comunidade-admin/grupos/{group}/membros', [\App\Http\Controllers\AdminCommunityController::class, 'addGroupMember'])->name('admin-community.groups.members.add');
        Route::delete('/comunidade-admin/grupos/{group}/membros/{user}', [\App\Http\Controllers\AdminCommunityController::class, 'removeGroupMember'])->name('admin-community.groups.members.remove');
        Route::post('/comunidade-admin/stories', [\App\Http\Controllers\AdminCommunityController::class, 'storeStory'])->name('admin-community.stories.store')->middleware('throttle:5,1');
        Route::delete('/comunidade-admin/stories/{story}', [\App\Http\Controllers\AdminCommunityController::class, 'destroyStory'])->name('admin-community.stories.destroy');
        Route::put('/comunidade-admin/reports/{report}/resolve', [\App\Http\Controllers\AdminCommunityController::class, 'resolveReport'])->name('admin-community.reports.resolve');
        Route::put('/comunidade-admin/reports/{report}/dismiss', [\App\Http\Controllers\AdminCommunityController::class, 'dismissReport'])->name('admin-community.reports.dismiss');
        Route::get('/comunidade-admin/bans', [\App\Http\Controllers\AdminCommunityController::class, 'communityBans'])->name('admin-community.bans.index');
        Route::post('/comunidade-admin/bans/{user}', [\App\Http\Controllers\AdminCommunityController::class, 'banUser'])->name('admin-community.bans.store');
        Route::delete('/comunidade-admin/bans/{user}', [\App\Http\Controllers\AdminCommunityController::class, 'unbanUser'])->name('admin-community.bans.destroy');
    });

    // Profissionais
    Route::middleware(['role:admin'])->group(function () {
        Route::get('/profissionais', [\App\Http\Controllers\ProfessionalsController::class, 'index'])->name('professionals.index');
        Route::post('/profissionais', [\App\Http\Controllers\ProfessionalsController::class, 'store'])->name('professionals.store');
        Route::post('/profissionais/{professional}', [\App\Http\Controllers\ProfessionalsController::class, 'update'])->name('professionals.update');
        Route::put('/profissionais/{professional}/toggle-active', [\App\Http\Controllers\ProfessionalsController::class, 'toggleActive'])->name('professionals.toggle-active');
        Route::post('/profissionais/{professional}/criar-acesso', [\App\Http\Controllers\ProfessionalsController::class, 'createAccess'])->name('professionals.create-access');
        Route::post('/profissionais/{professional}/aprovar', [\App\Http\Controllers\ProfessionalsController::class, 'approve'])->name('professionals.approve');
        Route::post('/profissionais/{professional}/rejeitar', [\App\Http\Controllers\ProfessionalsController::class, 'reject'])->name('professionals.reject');
        Route::delete('/profissionais/{professional}', [\App\Http\Controllers\ProfessionalsController::class, 'destroy'])->name('professionals.destroy');

        // Financeiro avançado
        Route::get('/financeiro', [\App\Http\Controllers\FinanceiroController::class, 'dashboard'])->name('financeiro.dashboard');

        // Comissões
        Route::get('/financeiro/comissoes', [\App\Http\Controllers\CommissionsController::class, 'index'])->name('commissions.index');
        Route::post('/financeiro/comissoes/gerar', [\App\Http\Controllers\CommissionsController::class, 'generate'])->name('commissions.generate');
        Route::post('/financeiro/comissoes/{commission}/aprovar', [\App\Http\Controllers\CommissionsController::class, 'approve'])->name('commissions.approve');
        Route::post('/financeiro/comissoes/{commission}/pago', [\App\Http\Controllers\CommissionsController::class, 'markPaid'])->name('commissions.mark-paid');
        Route::post('/financeiro/comissoes/aprovar-lote', [\App\Http\Controllers\CommissionsController::class, 'approveAll'])->name('commissions.approve-all');

        // Relatórios avançados
        Route::get('/relatorios/engajamento', [\App\Http\Controllers\RelatoriosController::class, 'engajamento'])->name('relatorios.engajamento');
        Route::get('/relatorios/retencao', [\App\Http\Controllers\RelatoriosController::class, 'retencao'])->name('relatorios.retencao');
        Route::get('/relatorios/evolucao-emocional', [\App\Http\Controllers\RelatoriosController::class, 'evolucaoEmocional'])->name('relatorios.evolucao-emocional');
        Route::get('/relatorios/conteudos', [\App\Http\Controllers\RelatoriosController::class, 'conteudos'])->name('relatorios.conteudos');
        Route::get('/relatorios/profissionais-report', [\App\Http\Controllers\RelatoriosController::class, 'profissionaisReport'])->name('relatorios.profissionais');

        // Relatórios de trilha por aluno
        Route::get('/relatorios/trilhas', [\App\Http\Controllers\JourneyReportsController::class, 'index'])->name('relatorios.trilhas');
        Route::post('/relatorios/trilhas/gerar', [\App\Http\Controllers\JourneyReportsController::class, 'generate'])->name('relatorios.trilhas.generate');
        Route::get('/relatorios/trilhas/{report}', [\App\Http\Controllers\JourneyReportsController::class, 'show'])->name('relatorios.trilhas.show');
        Route::put('/relatorios/trilhas/{report}', [\App\Http\Controllers\JourneyReportsController::class, 'update'])->name('relatorios.trilhas.update');
        Route::post('/relatorios/trilhas/{report}/publicar', [\App\Http\Controllers\JourneyReportsController::class, 'publish'])->name('relatorios.trilhas.publish');
        Route::post('/relatorios/trilhas/{report}/despublicar', [\App\Http\Controllers\JourneyReportsController::class, 'unpublish'])->name('relatorios.trilhas.unpublish');
    });

    // IA Contextual
    Route::middleware(['role:admin'])->group(function () {
        Route::get('/ia', [\App\Http\Controllers\AiController::class, 'index'])->name('ai.index');
        Route::post('/ia/prompts', [\App\Http\Controllers\AiController::class, 'storePrompt'])->name('ai.prompts.store');
        Route::put('/ia/prompts/{id}', [\App\Http\Controllers\AiController::class, 'updatePrompt'])->name('ai.prompts.update');
        Route::delete('/ia/prompts/{id}', [\App\Http\Controllers\AiController::class, 'destroyPrompt'])->name('ai.prompts.destroy');
    });

    // Agendamentos
    Route::middleware(['role:admin'])->group(function () {
        Route::get('/agendamentos', [\App\Http\Controllers\AppointmentsController::class, 'index'])->name('appointments.index');
        Route::post('/agendamentos', [\App\Http\Controllers\AppointmentsController::class, 'store'])->name('appointments.store');
        Route::put('/agendamentos/{appointment}', [\App\Http\Controllers\AppointmentsController::class, 'update'])->name('appointments.update');
        Route::put('/agendamentos/{appointment}/cancel', [\App\Http\Controllers\AppointmentsController::class, 'cancel'])->name('appointments.cancel');
        Route::put('/agendamentos/{appointment}/complete', [\App\Http\Controllers\AppointmentsController::class, 'complete'])->name('appointments.complete');
        Route::delete('/agendamentos/{appointment}', [\App\Http\Controllers\AppointmentsController::class, 'destroy'])->name('appointments.destroy');
        // Disponibilidade
        Route::get('/agendamentos/profissional/{professional}/disponibilidade', [\App\Http\Controllers\AppointmentsController::class, 'availability'])->name('appointments.availability');
        Route::post('/agendamentos/profissional/{professional}/disponibilidade', [\App\Http\Controllers\AppointmentsController::class, 'saveAvailability'])->name('appointments.availability.save');
        Route::get('/agendamentos/profissional/{professional}/slots', [\App\Http\Controllers\AppointmentsController::class, 'slots'])->name('appointments.slots');
    });

    // Mapa Neurofuncional
    Route::middleware(['role:admin'])->group(function () {
        Route::get('/mapa-neuro', [\App\Http\Controllers\NeuroMapController::class, 'index'])->name('neuro.index');
        Route::get('/mapa-neuro/analytics', [\App\Http\Controllers\NeuroMapController::class, 'analytics'])->name('neuro.analytics');
        Route::post('/mapa-neuro/scores', [\App\Http\Controllers\NeuroMapController::class, 'storeScore'])->name('neuro.scores.store');
        // Áreas
        Route::post('/mapa-neuro/areas', [\App\Http\Controllers\NeuroMapController::class, 'storeArea'])->name('neuro.areas.store');
        Route::put('/mapa-neuro/areas/{area}', [\App\Http\Controllers\NeuroMapController::class, 'updateArea'])->name('neuro.areas.update');
        Route::delete('/mapa-neuro/areas/{area}', [\App\Http\Controllers\NeuroMapController::class, 'destroyArea'])->name('neuro.areas.destroy');
        // Indicadores
        Route::post('/mapa-neuro/areas/{area}/indicators', [\App\Http\Controllers\NeuroMapController::class, 'storeIndicator'])->name('neuro.indicators.store');
        Route::put('/mapa-neuro/areas/{area}/indicators/{indicator}', [\App\Http\Controllers\NeuroMapController::class, 'updateIndicator'])->name('neuro.indicators.update');
        Route::delete('/mapa-neuro/areas/{area}/indicators/{indicator}', [\App\Http\Controllers\NeuroMapController::class, 'destroyIndicator'])->name('neuro.indicators.destroy');
    });

    // Checkpoints
    Route::middleware(['role:admin'])->group(function () {
        Route::get('/checkpoints', [\App\Http\Controllers\CheckpointsController::class, 'index'])->name('checkpoints.index');
        Route::post('/checkpoints', [\App\Http\Controllers\CheckpointsController::class, 'store'])->name('checkpoints.store');
        Route::get('/checkpoints/{checkpoint}', [\App\Http\Controllers\CheckpointsController::class, 'show'])->name('checkpoints.show');
        Route::put('/checkpoints/{checkpoint}', [\App\Http\Controllers\CheckpointsController::class, 'update'])->name('checkpoints.update');
        Route::put('/checkpoints/{checkpoint}/toggle-active', [\App\Http\Controllers\CheckpointsController::class, 'toggleActive'])->name('checkpoints.toggle-active');
        Route::delete('/checkpoints/{checkpoint}', [\App\Http\Controllers\CheckpointsController::class, 'destroy'])->name('checkpoints.destroy');
        // Questions
        Route::post('/checkpoints/{checkpoint}/questions', [\App\Http\Controllers\CheckpointsController::class, 'storeQuestion'])->name('checkpoints.questions.store');
        Route::put('/checkpoints/{checkpoint}/questions/{question}', [\App\Http\Controllers\CheckpointsController::class, 'updateQuestion'])->name('checkpoints.questions.update');
        Route::delete('/checkpoints/{checkpoint}/questions/{question}', [\App\Http\Controllers\CheckpointsController::class, 'destroyQuestion'])->name('checkpoints.questions.destroy');
        Route::post('/checkpoints/{checkpoint}/questions/reorder', [\App\Http\Controllers\CheckpointsController::class, 'reorderQuestions'])->name('checkpoints.questions.reorder');
        // Responses
        Route::get('/checkpoints/{checkpoint}/responses', [\App\Http\Controllers\CheckpointsController::class, 'responses'])->name('checkpoints.responses');
    });

    // Jornadas
    Route::middleware(['role:admin'])->group(function () {
        Route::get('/jornadas', [\App\Http\Controllers\JourneysController::class, 'index'])->name('journeys.index');
        Route::post('/jornadas', [\App\Http\Controllers\JourneysController::class, 'store'])->name('journeys.store');
        Route::get('/jornadas/{journey}', [\App\Http\Controllers\JourneysController::class, 'show'])->name('journeys.show');
        Route::post('/jornadas/{journey}', [\App\Http\Controllers\JourneysController::class, 'update'])->name('journeys.update');
        Route::put('/jornadas/{journey}/toggle-active', [\App\Http\Controllers\JourneysController::class, 'toggleActive'])->name('journeys.toggle-active');
        Route::post('/jornadas/reorder', [\App\Http\Controllers\JourneysController::class, 'reorder'])->name('journeys.reorder');
        Route::delete('/jornadas/{journey}', [\App\Http\Controllers\JourneysController::class, 'destroy'])->name('journeys.destroy');
        // Steps
        Route::post('/jornadas/{journey}/steps', [\App\Http\Controllers\JourneysController::class, 'storeStep'])->name('journeys.steps.store');
        Route::post('/jornadas/{journey}/steps/{step}', [\App\Http\Controllers\JourneysController::class, 'updateStep'])->name('journeys.steps.update');
        Route::delete('/jornadas/{journey}/steps/{step}', [\App\Http\Controllers\JourneysController::class, 'destroyStep'])->name('journeys.steps.destroy');
        Route::post('/jornadas/{journey}/steps/reorder', [\App\Http\Controllers\JourneysController::class, 'reorderSteps'])->name('journeys.steps.reorder');
        // Step items
        Route::post('/jornadas/{journey}/steps/{step}/items', [\App\Http\Controllers\JourneysController::class, 'storeStepItem'])->name('journeys.steps.items.store');
        Route::post('/jornadas/{journey}/steps/{step}/items/{item}', [\App\Http\Controllers\JourneysController::class, 'updateStepItem'])->name('journeys.steps.items.update');
        Route::delete('/jornadas/{journey}/steps/{step}/items/{item}', [\App\Http\Controllers\JourneysController::class, 'destroyStepItem'])->name('journeys.steps.items.destroy');
        Route::post('/jornadas/{journey}/steps/{step}/items-reorder', [\App\Http\Controllers\JourneysController::class, 'reorderStepItems'])->name('journeys.steps.items.reorder');
    });

    // Testes Clínicos
    Route::middleware(['role:admin'])->group(function () {
        Route::get('/testes-clinicos', [\App\Http\Controllers\ClinicalTestsController::class, 'index'])->name('clinical-tests.index');
        Route::get('/testes-clinicos/{clinicalTest}/editar', [\App\Http\Controllers\ClinicalTestsController::class, 'edit'])->name('clinical-tests.edit');
        Route::post('/testes-clinicos', [\App\Http\Controllers\ClinicalTestsController::class, 'store'])->name('clinical-tests.store');
        Route::put('/testes-clinicos/{clinicalTest}', [\App\Http\Controllers\ClinicalTestsController::class, 'update'])->name('clinical-tests.update');
        Route::put('/testes-clinicos/{clinicalTest}/toggle-active', [\App\Http\Controllers\ClinicalTestsController::class, 'toggleActive'])->name('clinical-tests.toggle-active');
        Route::post('/testes-clinicos/reorder', [\App\Http\Controllers\ClinicalTestsController::class, 'reorder'])->name('clinical-tests.reorder');
        Route::delete('/testes-clinicos/{clinicalTest}', [\App\Http\Controllers\ClinicalTestsController::class, 'destroy'])->name('clinical-tests.destroy');
        Route::get('/testes-clinicos/{clinicalTest}/perguntas', [\App\Http\Controllers\ClinicalTestsController::class, 'showQuestions'])->name('clinical-tests.questions.index');
        Route::post('/testes-clinicos/{clinicalTest}/perguntas', [\App\Http\Controllers\ClinicalTestsController::class, 'storeQuestion'])->name('clinical-tests.questions.store');
        Route::put('/testes-clinicos/{clinicalTest}/perguntas/{question}', [\App\Http\Controllers\ClinicalTestsController::class, 'updateQuestion'])->name('clinical-tests.questions.update');
        Route::delete('/testes-clinicos/{clinicalTest}/perguntas/{question}', [\App\Http\Controllers\ClinicalTestsController::class, 'destroyQuestion'])->name('clinical-tests.questions.destroy');
        Route::post('/testes-clinicos/{clinicalTest}/perguntas/reorder', [\App\Http\Controllers\ClinicalTestsController::class, 'reorderQuestions'])->name('clinical-tests.questions.reorder');
        Route::post('/testes-clinicos/{clinicalTest}/regras', [\App\Http\Controllers\ClinicalTestsController::class, 'storeScoringRule'])->name('clinical-tests.rules.store');
        Route::put('/testes-clinicos/{clinicalTest}/regras/{rule}', [\App\Http\Controllers\ClinicalTestsController::class, 'updateScoringRule'])->name('clinical-tests.rules.update');
        Route::delete('/testes-clinicos/{clinicalTest}/regras/{rule}', [\App\Http\Controllers\ClinicalTestsController::class, 'destroyScoringRule'])->name('clinical-tests.rules.destroy');
        Route::get('/testes-clinicos/{clinicalTest}/respostas', [\App\Http\Controllers\ClinicalTestsController::class, 'responses'])->name('clinical-tests.responses');
        Route::put('/testes-clinicos/{clinicalTest}/ai-context', [\App\Http\Controllers\ClinicalTestsAiContextController::class, 'update'])->name('clinical-tests.ai-context.update');
        Route::post('/testes-clinicos/{clinicalTest}/ai-context/upload', [\App\Http\Controllers\ClinicalTestsAiContextController::class, 'upload'])->name('clinical-tests.ai-context.upload');
        Route::delete('/testes-clinicos/{clinicalTest}/ai-context/files/{fileId}', [\App\Http\Controllers\ClinicalTestsAiContextController::class, 'destroyFile'])->name('clinical-tests.ai-context.file.destroy');
    });

    // Banners
    Route::middleware(['role:admin'])->group(function () {
        Route::get('/banners', [\App\Http\Controllers\BannersController::class, 'index'])->name('banners.index');
        Route::post('/banners', [\App\Http\Controllers\BannersController::class, 'store'])->name('banners.store');
        Route::post('/banners/reorder', [\App\Http\Controllers\BannersController::class, 'reorder'])->name('banners.reorder');
        Route::put('/banners/{banner}', [\App\Http\Controllers\BannersController::class, 'update'])->name('banners.update');
        Route::put('/banners/{banner}/toggle-active', [\App\Http\Controllers\BannersController::class, 'toggleActive'])->name('banners.toggle-active');
        Route::delete('/banners/{banner}', [\App\Http\Controllers\BannersController::class, 'destroy'])->name('banners.destroy');
    });

    // Posts do blog da área de membros
    Route::middleware(['role:admin'])->group(function () {
        Route::get('/member-posts', [\App\Http\Controllers\MemberAreaPostsController::class, 'index'])->name('member-posts.index');
        Route::post('/member-posts', [\App\Http\Controllers\MemberAreaPostsController::class, 'store'])->name('member-posts.store');
        Route::post('/member-posts/reorder', [\App\Http\Controllers\MemberAreaPostsController::class, 'reorder'])->name('member-posts.reorder');
        Route::post('/member-posts/upload-media', [\App\Http\Controllers\MemberAreaPostsController::class, 'uploadMedia'])->name('member-posts.upload-media');
        Route::get('/member-reports', [\App\Http\Controllers\MemberReportRequestsController::class, 'index'])->name('member-reports.index');
        Route::post('/member-reports/{memberReportRequest}/approve', [\App\Http\Controllers\MemberReportRequestsController::class, 'approve'])->name('member-reports.approve');
        Route::post('/member-reports/{memberReportRequest}/disapprove', [\App\Http\Controllers\MemberReportRequestsController::class, 'disapprove'])->name('member-reports.disapprove');
        Route::delete('/member-reports/{memberReportRequest}', [\App\Http\Controllers\MemberReportRequestsController::class, 'destroy'])->name('member-reports.destroy');
        Route::post('/member-reports/settings', [\App\Http\Controllers\MemberReportRequestsController::class, 'saveSettings'])->name('member-reports.settings');
        Route::put('/member-posts/{memberAreaPost}', [\App\Http\Controllers\MemberAreaPostsController::class, 'update'])->name('member-posts.update');
        Route::delete('/member-posts/{memberAreaPost}', [\App\Http\Controllers\MemberAreaPostsController::class, 'destroy'])->name('member-posts.destroy');
    });

    // Cursos em destaque na home da área de membros
    Route::middleware(['role:admin'])->group(function () {
        Route::get('/member-home-featured', [\App\Http\Controllers\MemberHomeFeaturedCoursesController::class, 'index'])->name('member-home-featured.index');
        Route::post('/member-home-featured/sync', [\App\Http\Controllers\MemberHomeFeaturedCoursesController::class, 'sync'])->name('member-home-featured.sync');
    });

    // Músicas
    Route::middleware(['role:admin'])->group(function () {
        Route::get('/musicas', [\App\Http\Controllers\MusicController::class, 'index'])->name('music.index');
        Route::post('/musicas/categorias', [\App\Http\Controllers\MusicController::class, 'storeCategory'])->name('music.categories.store');
        Route::put('/musicas/categorias/{category}', [\App\Http\Controllers\MusicController::class, 'updateCategory'])->name('music.categories.update');
        Route::delete('/musicas/categorias/{category}', [\App\Http\Controllers\MusicController::class, 'destroyCategory'])->name('music.categories.destroy');
        Route::post('/musicas/categorias/{category}/tracks', [\App\Http\Controllers\MusicController::class, 'storeTrack'])->name('music.tracks.store');
        Route::put('/musicas/tracks/{track}', [\App\Http\Controllers\MusicController::class, 'updateTrack'])->name('music.tracks.update');
        Route::delete('/musicas/tracks/{track}', [\App\Http\Controllers\MusicController::class, 'destroyTrack'])->name('music.tracks.destroy');
    });

    Route::middleware('team.permission:email_marketing.view')->group(function () {
        Route::get('/email-marketing', [\App\Http\Controllers\EmailMarketingController::class, 'index'])->name('email-marketing.index');
        Route::get('/email-marketing/create', [\App\Http\Controllers\EmailMarketingController::class, 'create'])->name('email-marketing.create');
        Route::post('/email-marketing/preview-recipients', [\App\Http\Controllers\EmailMarketingController::class, 'previewRecipientsByFilter'])->name('email-marketing.preview-recipients-by-filter');
        Route::post('/email-marketing', [\App\Http\Controllers\EmailMarketingController::class, 'store'])->name('email-marketing.store');
        Route::get('/email-marketing/{campaign}/edit', [\App\Http\Controllers\EmailMarketingController::class, 'edit'])->name('email-marketing.edit');
        Route::put('/email-marketing/{campaign}', [\App\Http\Controllers\EmailMarketingController::class, 'update'])->name('email-marketing.update');
        Route::post('/email-marketing/{campaign}/preview-recipients', [\App\Http\Controllers\EmailMarketingController::class, 'previewRecipients'])->name('email-marketing.preview-recipients');
        Route::post('/email-marketing/{campaign}/send', [\App\Http\Controllers\EmailMarketingController::class, 'send'])->name('email-marketing.send');
    });

});

Route::middleware(['auth', 'role:aluno'])->group(function () {
    Route::get('/area-membros', [\App\Http\Controllers\MemberAreaController::class, 'index'])->name('member-area.index');
});

// ─── Painel do Profissional ───────────────────────────────────────────────────
Route::prefix('p')->middleware(['auth', 'role:profissional'])->name('profissional.')->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\ProfessionalPanelController::class, 'dashboard'])->name('dashboard');

    // Perfil
    Route::get('/perfil', [\App\Http\Controllers\ProfessionalPanelController::class, 'perfil'])->name('perfil');
    Route::post('/perfil', [\App\Http\Controllers\ProfessionalPanelController::class, 'updatePerfil'])->name('perfil.update');

    // Serviços
    Route::get('/servicos', [\App\Http\Controllers\ProfessionalPanelController::class, 'servicos'])->name('servicos');
    Route::post('/servicos', [\App\Http\Controllers\ProfessionalPanelController::class, 'storeServico'])->name('servicos.store');
    Route::post('/servicos/{service}', [\App\Http\Controllers\ProfessionalPanelController::class, 'updateServico'])->name('servicos.update');
    Route::delete('/servicos/{service}', [\App\Http\Controllers\ProfessionalPanelController::class, 'destroyServico'])->name('servicos.destroy');

    // Portfólio
    Route::get('/portfolio', [\App\Http\Controllers\ProfessionalPanelController::class, 'portfolio'])->name('portfolio');
    Route::post('/portfolio', [\App\Http\Controllers\ProfessionalPanelController::class, 'storePortfolioItem'])->name('portfolio.store');
    Route::delete('/portfolio/{item}', [\App\Http\Controllers\ProfessionalPanelController::class, 'destroyPortfolioItem'])->name('portfolio.destroy');

    // Avaliações
    Route::get('/avaliacoes', [\App\Http\Controllers\ProfessionalPanelController::class, 'avaliacoes'])->name('avaliacoes');

    // Agenda
    Route::get('/agenda', [\App\Http\Controllers\ProfessionalPanelController::class, 'agenda'])->name('agenda');
    Route::put('/agenda/{appointment}', [\App\Http\Controllers\ProfessionalPanelController::class, 'updateAppointment'])->name('agenda.appointment.update');

    // Disponibilidade
    Route::get('/disponibilidade', [\App\Http\Controllers\ProfessionalPanelController::class, 'disponibilidade'])->name('disponibilidade');
    Route::post('/disponibilidade', [\App\Http\Controllers\ProfessionalPanelController::class, 'saveDisponibilidade'])->name('disponibilidade.save');

    // Financeiro
    Route::get('/financeiro', [\App\Http\Controllers\ProfessionalPanelController::class, 'financeiro'])->name('financeiro');

    // Triagens e Testes
    Route::get('/triagens', [\App\Http\Controllers\ProfessionalPanelController::class, 'triagens'])->name('triagens');
    Route::post('/triagens', [\App\Http\Controllers\ProfessionalPanelController::class, 'storeTest'])->name('triagens.store');
    Route::put('/triagens/{testId}', [\App\Http\Controllers\ProfessionalPanelController::class, 'updateTest'])->name('triagens.update')->whereNumber('testId');
    Route::delete('/triagens/{testId}', [\App\Http\Controllers\ProfessionalPanelController::class, 'destroyTest'])->name('triagens.destroy')->whereNumber('testId');
    Route::get('/triagens/{testId}/questoes', [\App\Http\Controllers\ProfessionalPanelController::class, 'indexTestQuestions'])->name('triagens.questions.index')->whereNumber('testId');
    Route::post('/triagens/{testId}/questoes', [\App\Http\Controllers\ProfessionalPanelController::class, 'storeTestQuestion'])->name('triagens.questions.store')->whereNumber('testId');
    Route::delete('/triagens/{testId}/questoes/{questionId}', [\App\Http\Controllers\ProfessionalPanelController::class, 'destroyTestQuestion'])->name('triagens.questions.destroy')->whereNumber('testId')->whereNumber('questionId');
    Route::post('/triagens/{testId}/regras', [\App\Http\Controllers\ProfessionalPanelController::class, 'storeTestScoringRule'])->name('triagens.rules.store')->whereNumber('testId');
    Route::delete('/triagens/{testId}/regras/{ruleId}', [\App\Http\Controllers\ProfessionalPanelController::class, 'destroyTestScoringRule'])->name('triagens.rules.destroy')->whereNumber('testId')->whereNumber('ruleId');
    Route::post('/triagens/enviar', [\App\Http\Controllers\ProfessionalPanelController::class, 'sendTest'])->name('triagens.send');

    // Meus Pacientes
    Route::get('/meus-pacientes', [\App\Http\Controllers\ProfessionalPanelController::class, 'meusPacientes'])->name('meus-pacientes');
    Route::post('/meus-pacientes/{patientId}/nota', [\App\Http\Controllers\ProfessionalPanelController::class, 'saveNote'])->name('meus-pacientes.note');
});

// Área de membros — rotas limpas (sem slug na URL): /m/{pagina}
Route::prefix('m')->middleware(['member.area.resolve.from.user', 'member.area.access'])->group(function () {
    Route::get('manifest.json', [\App\Http\Controllers\MemberAreaAppController::class, 'manifest'])->name('clean.member.manifest');
    Route::get('/', [\App\Http\Controllers\MemberAreaAppController::class, 'show'])->name('clean.member.home');
    Route::get('comunidade', [\App\Http\Controllers\MemberAreaAppController::class, 'comunidade'])->name('clean.member.comunidade');
    Route::post('comunidade/posts', [\App\Http\Controllers\MemberAreaAppController::class, 'storeCommunityPost']);
    Route::delete('comunidade/posts/{post}', [\App\Http\Controllers\MemberAreaAppController::class, 'destroyCommunityPost']);
    Route::post('comunidade/posts/{post}/like', [\App\Http\Controllers\MemberAreaAppController::class, 'likeCommunityPost']);
    Route::delete('comunidade/posts/{post}/like', [\App\Http\Controllers\MemberAreaAppController::class, 'unlikeCommunityPost']);
    Route::post('comunidade/posts/{post}/comments', [\App\Http\Controllers\MemberAreaAppController::class, 'storeCommunityPostComment']);
    Route::post('comunidade/posts/{post}/report', [\App\Http\Controllers\MemberAreaAppController::class, 'reportCommunityPost']);
    Route::get('modulos', [\App\Http\Controllers\MemberAreaAppController::class, 'modulos']);
    Route::get('modulos/{courseId}', [\App\Http\Controllers\MemberAreaAppController::class, 'modulosCurso']);
    Route::post('modulos/{courseId}/mega-relatorio', [\App\Http\Controllers\MemberAreaAppController::class, 'generateProductMegaReport'])->middleware('throttle:3,1');
    Route::get('modulo/{module}', [\App\Http\Controllers\MemberAreaAppController::class, 'moduleContent']);
    Route::get('aula/{lesson}', [\App\Http\Controllers\MemberAreaAppController::class, 'lesson']);
    Route::post('aula/{lesson}/like', [\App\Http\Controllers\MemberAreaAppController::class, 'toggleLessonLike'])->middleware('throttle:60,1');
    Route::post('aula/{lesson}/complete', [\App\Http\Controllers\MemberAreaAppController::class, 'completeLesson']);
    Route::post('aula/{lesson}/comments', [\App\Http\Controllers\MemberAreaAppController::class, 'storeLessonComment']);
    Route::post('aula/{lesson}/quiz', [\App\Http\Controllers\MemberAreaAppController::class, 'submitQuiz'])->middleware('throttle:10,1');
    Route::get('aula/{lesson}/pdf/{fileIndex}', [\App\Http\Controllers\MemberAreaAppController::class, 'presentationPdf'])->whereNumber('fileIndex');
    Route::get('aula/{lesson}/pdf-annotations', [\App\Http\Controllers\MemberAreaAppController::class, 'getLessonPdfAnnotations']);
    Route::put('aula/{lesson}/pdf-annotations', [\App\Http\Controllers\MemberAreaAppController::class, 'putLessonPdfAnnotations'])->middleware('throttle:120,1');
    Route::get('stories', [\App\Http\Controllers\MemberAreaAppController::class, 'storiesForProduct']);
    Route::post('stories/{storyId}/like', [\App\Http\Controllers\MemberAreaAppController::class, 'likeStory']);
    Route::post('stories/{storyId}/view', [\App\Http\Controllers\MemberAreaAppController::class, 'viewStory']);
    Route::get('grupos', [\App\Http\Controllers\MemberAreaAppController::class, 'memberGroups']);
    Route::post('grupos/{groupId}/entrar', [\App\Http\Controllers\MemberProfileController::class, 'requestGroupJoin']);
    Route::get('eventos', [\App\Http\Controllers\MemberAreaAppController::class, 'memberEvents']);
    Route::get('loja', [\App\Http\Controllers\MemberAreaAppController::class, 'loja']);
    Route::get('musicas', [\App\Http\Controllers\MemberMusicController::class, 'index']);
    Route::get('certificado', [\App\Http\Controllers\MemberAreaAppController::class, 'certificado']);
    Route::get('resultados', [\App\Http\Controllers\MemberAreaAppController::class, 'resultados']);
    Route::post('resultados/solicitar', [\App\Http\Controllers\MemberAreaAppController::class, 'solicitarRelatorio']);
    Route::post('trocar-produto', [\App\Http\Controllers\MemberAreaAppController::class, 'trocarProduto']);
    Route::get('post/{memberAreaPost}', [\App\Http\Controllers\MemberAreaAppController::class, 'showPost']);
    Route::get('perfil', [\App\Http\Controllers\MemberProfileController::class, 'show']);
    Route::get('perfil/{userId}', [\App\Http\Controllers\MemberProfileController::class, 'show']);
    Route::post('perfil/atualizar', [\App\Http\Controllers\MemberProfileController::class, 'updateProfile']);
    Route::post('amizades/{userId}', [\App\Http\Controllers\MemberProfileController::class, 'sendFriendRequest']);
    Route::put('amizades/{userId}/aceitar', [\App\Http\Controllers\MemberProfileController::class, 'acceptFriendRequest']);
    Route::delete('amizades/{userId}', [\App\Http\Controllers\MemberProfileController::class, 'declineOrUnfriend']);
    Route::post('playlists', [\App\Http\Controllers\MemberProfileController::class, 'storePlaylist']);
    Route::delete('playlists/{playlist}', [\App\Http\Controllers\MemberProfileController::class, 'destroyPlaylist']);
    Route::post('playlists/{playlist}/tracks/{trackId}', [\App\Http\Controllers\MemberProfileController::class, 'addTrackToPlaylist']);
    Route::delete('playlists/{playlist}/tracks/{trackId}', [\App\Http\Controllers\MemberProfileController::class, 'removeTrackFromPlaylist']);
    Route::post('push-subscribe', [\App\Http\Controllers\MemberAreaAppController::class, 'pushSubscribe']);
    Route::get('notifications', [\App\Http\Controllers\MemberAreaNotificationsController::class, 'index']);
    Route::patch('notifications/{notification}/read', [\App\Http\Controllers\MemberAreaNotificationsController::class, 'markRead']);
    Route::post('notifications/mark-all-read', [\App\Http\Controllers\MemberAreaNotificationsController::class, 'markAllRead']);
    Route::delete('notifications', [\App\Http\Controllers\MemberAreaNotificationsController::class, 'clearAll']);
    Route::put('conta', [\App\Http\Controllers\MemberAreaAccountController::class, 'updateProfile']);
    Route::put('conta/senha', [\App\Http\Controllers\MemberAreaAccountController::class, 'updatePassword']);
    Route::get('relatorio-ia/{insight}/imprimir', [\App\Http\Controllers\AlunosController::class, 'printStudentReport'])->name('area.report.print');
    Route::get('refund/eligibility', [\App\Http\Controllers\MemberAreaRefundController::class, 'eligibility']);
    Route::post('refund', [\App\Http\Controllers\MemberAreaRefundController::class, 'store'])->middleware('throttle:10,1');
    Route::get('jornadas', [\App\Http\Controllers\MemberJourneysController::class, 'index']);
    Route::get('jornadas/{journeyId}', [\App\Http\Controllers\MemberJourneysController::class, 'show'])->whereNumber('journeyId');
    Route::post('jornadas/{journeyId}/etapas/{stepId}/concluir', [\App\Http\Controllers\MemberJourneysController::class, 'completeStep']);
    Route::post('jornadas/{journeyId}/etapas/{stepId}/items/{itemId}/responder', [\App\Http\Controllers\MemberJourneysController::class, 'submitStepItem'])->middleware('throttle:30,1');
    Route::get('checkpoints/{checkpointId}', [\App\Http\Controllers\MemberCheckpointsController::class, 'show'])->whereNumber('checkpointId');
    Route::post('checkpoints/{checkpointId}/responder', [\App\Http\Controllers\MemberCheckpointsController::class, 'submit'])->middleware('throttle:5,1');
    Route::get('testes', [\App\Http\Controllers\MemberClinicalTestsController::class, 'index']);
    Route::get('testes/{testId}', [\App\Http\Controllers\MemberClinicalTestsController::class, 'show'])->whereNumber('testId');
    Route::post('testes/{testId}/iniciar', [\App\Http\Controllers\MemberClinicalTestsController::class, 'start'])->whereNumber('testId');
    Route::post('testes/{testId}/responder', [\App\Http\Controllers\MemberClinicalTestsController::class, 'saveAnswer'])->middleware('throttle:120,1')->whereNumber('testId');
    Route::post('testes/{testId}/concluir', [\App\Http\Controllers\MemberClinicalTestsController::class, 'complete'])->middleware('throttle:10,1')->whereNumber('testId');
    Route::post('testes/sessoes/{sessionId}/compartilhar', [\App\Http\Controllers\TestResultShareController::class, 'generate'])->whereNumber('sessionId');
    Route::post('testes/{testId}/gerar-relatorio', [\App\Http\Controllers\MemberClinicalTestsController::class, 'generateReport'])->middleware('throttle:5,1')->whereNumber('testId');
    Route::post('humor/checkin', [\App\Http\Controllers\MemberMoodController::class, 'checkin'])->middleware('throttle:10,1');
    Route::get('mapa-neuro', [\App\Http\Controllers\MemberNeuroMapController::class, 'show']);
    Route::get('profissionais', [\App\Http\Controllers\MemberProfessionalsController::class, 'index']);
    Route::get('profissionais/{professionalId}', [\App\Http\Controllers\MemberProfessionalsController::class, 'show'])->whereNumber('professionalId');
    Route::post('profissionais/{professionalId}/avaliar', [\App\Http\Controllers\MemberProfessionalsController::class, 'storeReview'])->middleware('throttle:5,1');
    Route::get('profissionais/{professionalId}/horarios', [\App\Http\Controllers\MemberProfessionalsController::class, 'slots']);
    Route::get('meus-agendamentos', [\App\Http\Controllers\MemberAppointmentsController::class, 'index']);
    Route::post('meus-agendamentos', [\App\Http\Controllers\MemberAppointmentsController::class, 'store'])->middleware('throttle:10,1');
    Route::put('meus-agendamentos/{appointment}/cancelar', [\App\Http\Controllers\MemberAppointmentsController::class, 'cancel']);
    Route::get('insights', [\App\Http\Controllers\MemberInsightsController::class, 'index']);
    Route::put('insights/{insight}/dispensar', [\App\Http\Controllers\MemberInsightsController::class, 'dismiss']);
    Route::post('insights/gerar', [\App\Http\Controllers\MemberInsightsController::class, 'generate']);
    Route::get('minha-evolucao', [\App\Http\Controllers\MemberEvolutionController::class, 'show']);
    Route::get('ia/chat', [\App\Http\Controllers\MemberAiChatController::class, 'show']);
    Route::post('ia/chat', [\App\Http\Controllers\MemberAiChatController::class, 'send']);
    Route::get('ia/chat/session', [\App\Http\Controllers\MemberAiChatController::class, 'getSession']);
    Route::post('ia/chat/session', [\App\Http\Controllers\MemberAiChatController::class, 'newSession']);
    Route::get('ia/chat/history', [\App\Http\Controllers\MemberAiChatController::class, 'history']);
    Route::get('ia/chat/sessions', [\App\Http\Controllers\MemberAiChatController::class, 'sessions']);
    Route::get('meus-relatorios', [\App\Http\Controllers\JourneyReportsController::class, 'memberIndex']);
    Route::get('meus-relatorios/{journeyId}', [\App\Http\Controllers\JourneyReportsController::class, 'memberShow']);
    Route::get('products/{relatedProduct}/open', [\App\Http\Controllers\MemberAreaAppController::class, 'openRelatedProduct'])->where('relatedProduct', '[0-9A-Za-z\\-]{1,64}');
    Route::get('products/{relatedProduct}/deliverable', [\App\Http\Controllers\MemberAreaAppController::class, 'openRelatedProductDeliverable'])->where('relatedProduct', '[0-9A-Za-z\\-]{1,64}');
});

// Área de membros por produto (path: /m/{slug})
Route::prefix('m/{slug}')->where(['slug' => '[a-zA-Z0-9\-]{3,64}'])->middleware('member.area.resolve')->group(function () {
    Route::get('manifest.json', [\App\Http\Controllers\MemberAreaAppController::class, 'manifest'])->name('member-area-app.manifest');
    Route::get('sw.js', function () {
        $path = public_path('member-area-sw.js');
        if (! file_exists($path)) {
            abort(404);
        }

        return response()->file($path, ['Content-Type' => 'application/javascript']);
    })->name('member-area-app.sw');
    Route::get('login', [\App\Http\Controllers\MemberAreaLoginController::class, 'showLoginForm'])->name('member-area.login')->middleware('guest');
    Route::post('login', [\App\Http\Controllers\MemberAreaLoginController::class, 'login'])->name('member-area.login.post')->middleware(['guest', 'throttle:5,1']);
    Route::post('login-without-password', [\App\Http\Controllers\MemberAreaLoginController::class, 'loginWithoutPassword'])->name('member-area.login.without-password')->middleware(['guest', 'throttle:5,1']);
    Route::get('esqueci-senha', [\App\Http\Controllers\MemberAreaForgotPasswordController::class, 'showLinkRequestForm'])->name('member-area.password.request')->middleware('guest');
    Route::post('esqueci-senha', [\App\Http\Controllers\MemberAreaForgotPasswordController::class, 'sendResetLinkEmail'])->name('member-area.password.email')->middleware(['guest', 'throttle:6,1']);
    Route::get('access', [\App\Http\Controllers\MemberAreaLoginController::class, 'magicAccess'])->name('member-area.magic-access')->middleware('member.area.signed');

    // Apenas a home do produto (visão geral + contexto de sessão)
    Route::middleware(['member.area.access'])->group(function () {
        Route::get('/', [\App\Http\Controllers\MemberAreaAppController::class, 'show'])->name('member-area-app.show');
    });

    // Compatibilidade: redireciona /m/{slug}/{page} → /m/{page} (formato antigo)
    Route::get('{page}', function (string $page) {
        $qs = request()->getQueryString();

        return redirect('/m/'.$page.($qs ? '?'.$qs : ''), 301);
    })->where('page', '[a-z][a-z0-9\-]+');
});

// PWA e login da área de membros quando acessada por subdomínio ou domínio próprio (sem prefixo /m/slug)
Route::middleware(['web', 'member.area.resolve.by.host'])->group(function () {
    Route::get('sw.js', function () {
        $path = public_path('member-area-sw.js');
        if (! file_exists($path)) {
            abort(404);
        }

        return response()->file($path, ['Content-Type' => 'application/javascript']);
    })->name('member-area-app.sw.host');
    Route::get('access', [\App\Http\Controllers\MemberAreaLoginController::class, 'magicAccessHost'])->name('member-area.magic-access.host')->middleware('member.area.signed');
    // Login da área de membros por host: não registramos GET/POST /login aqui para não sobrescrever
    // o login da plataforma. O Auth\LoginController delega para MemberAreaLoginController quando
    // o host for de área de membros (subdomínio ou domínio próprio).
    Route::post('login-without-password', function (\Illuminate\Http\Request $request) {
        $slug = $request->attributes->get('member_area_slug');
        if (! $slug) {
            abort(404);
        }

        return app()->call(\App\Http\Controllers\MemberAreaLoginController::class.'@loginWithoutPassword', [
            'request' => $request,
            'slug' => $slug,
        ]);
    })->name('member-area.login.without-password.host')->middleware(['guest', 'throttle:5,1']);

    Route::middleware(['member.area.access'])->group(function () {
        Route::get('modulos', [\App\Http\Controllers\MemberAreaAppController::class, 'modulos'])->name('member-area-app.modulos.host');
        Route::get('modulo/{module}', [\App\Http\Controllers\MemberAreaAppController::class, 'moduleContent'])->name('member-area-app.module.host');
        Route::get('aula/{lesson}', [\App\Http\Controllers\MemberAreaAppController::class, 'lesson'])->name('member-area-app.lesson.host');
        // Outros produtos (modo host): abrir dentro da mesma área (resolve para módulo embutido) ou abrir deliverable (produto tipo Link)
        Route::get('products/{relatedProduct}/open', [\App\Http\Controllers\MemberAreaAppController::class, 'openRelatedProduct'])
            ->where('relatedProduct', '[0-9A-Za-z\\-]{1,64}')
            ->name('member-area-app.products.open.host');
        Route::get('products/{relatedProduct}/deliverable', [\App\Http\Controllers\MemberAreaAppController::class, 'openRelatedProductDeliverable'])
            ->where('relatedProduct', '[0-9A-Za-z\\-]{1,64}')
            ->name('member-area-app.products.deliverable.host');
        Route::get('aula/{lesson}/pdf/{fileIndex}', [\App\Http\Controllers\MemberAreaAppController::class, 'presentationPdf'])
            ->whereNumber('fileIndex')
            ->name('member-area-app.lesson.pdf.host');
        Route::get('aula/{lesson}/pdf-annotations', [\App\Http\Controllers\MemberAreaAppController::class, 'getLessonPdfAnnotations'])->name('member-area-app.lesson.pdf-annotations.host');
        Route::put('aula/{lesson}/pdf-annotations', [\App\Http\Controllers\MemberAreaAppController::class, 'putLessonPdfAnnotations'])->middleware('throttle:120,1')->name('member-area-app.lesson.pdf-annotations.put.host');
        Route::post('aula/{lesson}/like', [\App\Http\Controllers\MemberAreaAppController::class, 'toggleLessonLike'])->middleware('throttle:60,1')->name('member-area-app.lesson.like.host');
        Route::post('aula/{lesson}/complete', [\App\Http\Controllers\MemberAreaAppController::class, 'completeLesson'])->name('member-area-app.lesson.complete.host');
        Route::post('aula/{lesson}/quiz', [\App\Http\Controllers\MemberAreaAppController::class, 'submitQuiz'])->middleware('throttle:10,1')->name('member-area-app.lesson.quiz.submit.host');
        Route::post('aula/{lesson}/comments', [\App\Http\Controllers\MemberAreaAppController::class, 'storeLessonComment'])->name('member-area-app.lesson.comments.store.host');
        Route::get('loja', [\App\Http\Controllers\MemberAreaAppController::class, 'loja'])->name('member-area-app.loja.host');
        Route::get('comunidade', [\App\Http\Controllers\MemberAreaAppController::class, 'comunidade'])->name('member-area-app.comunidade.host');
        Route::post('comunidade/posts', [\App\Http\Controllers\MemberAreaAppController::class, 'storeCommunityPost'])->name('member-area-app.comunidade.posts.store.host');
        Route::delete('comunidade/posts/{post}', [\App\Http\Controllers\MemberAreaAppController::class, 'destroyCommunityPost'])->name('member-area-app.comunidade.posts.destroy.host');
        Route::post('comunidade/posts/{post}/like', [\App\Http\Controllers\MemberAreaAppController::class, 'likeCommunityPost'])->name('member-area-app.comunidade.posts.like.host');
        Route::delete('comunidade/posts/{post}/like', [\App\Http\Controllers\MemberAreaAppController::class, 'unlikeCommunityPost'])->name('member-area-app.comunidade.posts.unlike.host');
        Route::post('comunidade/posts/{post}/comments', [\App\Http\Controllers\MemberAreaAppController::class, 'storeCommunityPostComment'])->name('member-area-app.comunidade.posts.comments.store.host');
        Route::post('comunidade/posts/{post}/report', [\App\Http\Controllers\MemberAreaAppController::class, 'reportCommunityPost'])->name('member-area-app.comunidade.posts.report.host');
        Route::get('certificado', [\App\Http\Controllers\MemberAreaAppController::class, 'certificado'])->name('member-area-app.certificado.host');
        Route::post('push-subscribe', [\App\Http\Controllers\MemberAreaAppController::class, 'pushSubscribe'])->name('member-area-app.push.subscribe.host');
        Route::get('notifications', [\App\Http\Controllers\MemberAreaNotificationsController::class, 'index'])->name('member-area-app.notifications.index.host');
        Route::patch('notifications/{notification}/read', [\App\Http\Controllers\MemberAreaNotificationsController::class, 'markRead'])->name('member-area-app.notifications.mark-read.host');
        Route::post('notifications/mark-all-read', [\App\Http\Controllers\MemberAreaNotificationsController::class, 'markAllRead'])->name('member-area-app.notifications.mark-all-read.host');
        Route::delete('notifications', [\App\Http\Controllers\MemberAreaNotificationsController::class, 'clearAll'])->name('member-area-app.notifications.clear-all.host');
        Route::put('conta', [\App\Http\Controllers\MemberAreaAccountController::class, 'updateProfile'])->name('member-area-app.conta.update.host');
        Route::put('conta/senha', [\App\Http\Controllers\MemberAreaAccountController::class, 'updatePassword'])->name('member-area-app.conta.password.host');
        Route::get('refund/eligibility', [\App\Http\Controllers\MemberAreaRefundController::class, 'eligibility'])->name('member-area-app.refund.eligibility.host');
        Route::post('refund', [\App\Http\Controllers\MemberAreaRefundController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('member-area-app.refund.store.host');

        // ── Jornadas do aluno (host)
        Route::post('jornadas/{journeyId}/etapas/{stepId}/concluir', [\App\Http\Controllers\MemberJourneysController::class, 'completeStep'])->name('member-area-app.jornada.step.complete.host');
        Route::post('jornadas/{journeyId}/etapas/{stepId}/items/{itemId}/responder', [\App\Http\Controllers\MemberJourneysController::class, 'submitStepItem'])->middleware('throttle:30,1')->name('member-area-app.jornada.step.item.respond.host');

        // ── Checkpoints do aluno (host) — apenas envio de resposta; exibição via /m/{slug}
        Route::post('checkpoints/{checkpointId}/responder', [\App\Http\Controllers\MemberCheckpointsController::class, 'submit'])->middleware('throttle:5,1')->name('member-area-app.checkpoint.submit.host');

        // ── Profissionais (host) — apenas avaliação; listagem e detalhe via /m/{slug}
        Route::post('profissionais/{professionalId}/avaliar', [\App\Http\Controllers\MemberProfessionalsController::class, 'storeReview'])->middleware('throttle:5,1')->name('member-area-app.profissional.review.host');

        // ── Agendamentos do aluno (host) ──────────────────────────────────
        Route::get('meus-agendamentos', [\App\Http\Controllers\MemberAppointmentsController::class, 'index'])->name('member-area-app.agendamentos.host');
        Route::post('meus-agendamentos', [\App\Http\Controllers\MemberAppointmentsController::class, 'store'])->middleware('throttle:10,1')->name('member-area-app.agendamentos.store.host');
        Route::put('meus-agendamentos/{appointment}/cancelar', [\App\Http\Controllers\MemberAppointmentsController::class, 'cancel'])->name('member-area-app.agendamentos.cancel.host');

        // ── Insights da IA (host) ─────────────────────────────────────────
        Route::get('insights', [\App\Http\Controllers\MemberInsightsController::class, 'index'])->name('member-area-app.insights.host');
        Route::put('insights/{insight}/dispensar', [\App\Http\Controllers\MemberInsightsController::class, 'dismiss'])->name('member-area-app.insights.dismiss.host');
    });
});
