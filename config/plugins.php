<?php

/**
 * Plugins instalados via ZIP/loja:
 * - SPECTRA_PLUGINS_USER_PATH definido: usa esse caminho absoluto.
 * - SPECTRA_DOCKER=true (Compose): `.docker/plugins-installed` — mesmo volume de ambiente (.docker), separado de `storage`.
 * - Caso contrário: `storage/app/plugins-installed`.
 *
 * SPECTRA_PLUGINS_EXTRA_SCAN: pastas extras só de leitura, separadas por | (opcional).
 */
return [
    'user_install_path' => env('SPECTRA_PLUGINS_USER_PATH') ?: null,

    'docker_mode' => filter_var(env('SPECTRA_DOCKER', false), FILTER_VALIDATE_BOOLEAN),

    'extra_scan_paths' => array_values(array_filter(
        array_map('trim', explode('|', (string) env('SPECTRA_PLUGINS_EXTRA_SCAN', '')))
    )),
];
