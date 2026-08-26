<?php

return [
    /*
    | Origens HTTPS extra na diretiva CSP connect-src (PDF.js / apresentações).
    | Separadas por vírgula. Use se o URL público dos PDFs for outro domínio que não o de AWS_URL.
    */
    'extra_connect_src' => env('CSP_EXTRA_CONNECT_SRC', ''),

    /*
    | Origens extras em connect-src, para PDF.js buscar arquivo em storage externo.
    | Defina true em instalações self-hosted que não usem esse domínio.
    */
    'disable_legacy_r2_origin' => filter_var(env('CSP_DISABLE_LEGACY_R2_ORIGIN', false), FILTER_VALIDATE_BOOL),
];
