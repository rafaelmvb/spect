<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Lê e grava segredos em `settings` sempre cifrados.
 *
 * As chaves de LLM eram gravadas em texto puro, ao contrário das credenciais de
 * gateway e de SMTP. Um dump do banco entregava as chaves prontas para uso.
 *
 * A leitura aceita valor legado em claro para não derrubar instalação que ainda
 * não rodou a migration de cifragem.
 */
class SecretSetting
{
    public static function get(string $key, ?int $tenantId = null): ?string
    {
        $bruto = Setting::get($key, null, $tenantId);
        if (! is_string($bruto) || $bruto === '') {
            return null;
        }

        try {
            return Crypt::decryptString($bruto);
        } catch (DecryptException) {
            // Valor gravado antes da cifragem: devolve como está.
            return $bruto;
        }
    }

    public static function set(string $key, ?string $plain, ?int $tenantId = null): void
    {
        if ($plain === null || $plain === '') {
            Setting::set($key, '', $tenantId);

            return;
        }

        Setting::set($key, Crypt::encryptString($plain), $tenantId);
    }

    /**
     * True quando há segredo gravado, sem decifrar.
     */
    public static function isSet(string $key, ?int $tenantId = null): bool
    {
        $bruto = Setting::get($key, null, $tenantId);

        return is_string($bruto) && $bruto !== '';
    }
}
