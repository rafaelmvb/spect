<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * As chaves de LLM eram gravadas em texto puro em `settings`, enquanto
 * credenciais de gateway e senha de SMTP ja eram cifradas. Um dump do banco
 * entregava as chaves prontas para uso.
 *
 * Cifra o que ja esta gravado. Leitura por App\Support\SecretSetting.
 */
return new class extends Migration
{
    private const CHAVES = [
        'anthropic_api_key',
        'openai_api_key',
        'groq_api_key',
        'gemini_api_key',
    ];

    public function up(): void
    {
        $linhas = DB::table('settings')
            ->whereIn('key', self::CHAVES)
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->get(['id', 'value']);

        foreach ($linhas as $linha) {
            if ($this->jaCifrado($linha->value)) {
                continue;
            }

            DB::table('settings')
                ->where('id', $linha->id)
                ->update(['value' => Crypt::encryptString($linha->value)]);
        }
    }

    public function down(): void
    {
        // Sem rollback: decifrar devolveria as chaves para texto puro no banco,
        // que e exatamente o problema que esta migration resolve.
    }

    private function jaCifrado(string $valor): bool
    {
        try {
            Crypt::decryptString($valor);

            return true;
        } catch (DecryptException) {
            return false;
        }
    }
};
