<?php

use App\Support\VapidEnvKeys;

$versionFile = base_path('VERSION');
$version = trim((is_file($versionFile) ? file_get_contents($versionFile) : '') ?: '') ?: env('GETFY_VERSION', '1.0.0');

return [
    'installed' => is_file(base_path('.env')) && filter_var(env('APP_INSTALLED', false), FILTER_VALIDATE_BOOLEAN),
    'auto_migrate' => filter_var(env('APP_AUTO_MIGRATE', false), FILTER_VALIDATE_BOOLEAN),
    'cron_secret' => env('CRON_SECRET', null),

    /*
    | Webhooks de integração (Integrações > Webhooks): envio HTTP para URLs cadastradas.
    | Por padrão, pedido pendente e pedido pago disparam na hora (sem fila), para evitar atraso
    | quando o worker está sobrecarregado. Demais eventos seguem fila/heartbeat (ver docs).
    */
    'webhooks' => [
        'sync_critical_payment_events' => filter_var(
            env('GETFY_WEBHOOKS_SYNC_CRITICAL_PAYMENT', true),
            FILTER_VALIDATE_BOOLEAN
        ),
        /** Se true, todos os webhooks de integração rodam síncronos (pode alongar requests). */
        'dispatch_all_sync' => filter_var(env('GETFY_WEBHOOKS_DISPATCH_ALL_SYNC', false), FILTER_VALIDATE_BOOLEAN),

        /*
        | Recusa webhook de gateway sem webhook_secret configurado. Default false
        | para não derrubar confirmação de pagamento em quem ainda não definiu os
        | secrets — ative depois de configurar todos (o log aponta quais faltam).
        */
        'require_signature' => filter_var(env('GETFY_WEBHOOKS_REQUIRE_SIGNATURE', false), FILTER_VALIDATE_BOOLEAN),
    ],

    /*
    | Meta CAPI, Utmify paid, etc.: após a resposta HTTP quando fila é sync/database
    | ou GETFY_INTEGRATIONS_DISPATCH_AFTER_RESPONSE=true (default true).
    */
    'integrations' => [
        'dispatch_after_response' => filter_var(
            env('GETFY_INTEGRATIONS_DISPATCH_AFTER_RESPONSE', true),
            FILTER_VALIDATE_BOOLEAN
        ),
    ],
    'version' => $version,
    'php_path' => env('GETFY_PHP_PATH', null),
    'pwa' => [
        'vapid_public' => VapidEnvKeys::normalize(env('PWA_VAPID_PUBLIC')),
        'vapid_private' => VapidEnvKeys::normalize(env('PWA_VAPID_PRIVATE')),
    ],
    'app_name' => 'Getfy',
    'theme_primary' => '#00cc00',
    'app_logo' => 'https://cdn.getfy.cloud/logo-white.png',
    'app_logo_dark' => 'https://cdn.getfy.cloud/logo-dark.png',
    'app_logo_icon' => 'https://cdn.getfy.cloud/collapsed-logo.png',
    'app_logo_icon_dark' => 'https://cdn.getfy.cloud/collapsed-logo.png',

    /** White Label plugin (null = default / não aplicado) */
    'login_hero_image' => null,
    'favicon_url' => null,
    'pwa_theme_color' => null,
    'pwa_icon_192' => null,
    'pwa_icon_512' => null,
];
