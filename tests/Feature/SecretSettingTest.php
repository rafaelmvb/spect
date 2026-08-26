<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\SecretSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class SecretSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_grava_cifrado_no_banco(): void
    {
        SecretSetting::set('anthropic_api_key', 'sk-ant-valor-real', 1);

        $bruto = Setting::get('anthropic_api_key', null, 1);

        $this->assertNotSame('sk-ant-valor-real', $bruto, 'A chave foi gravada em texto puro.');
        $this->assertStringNotContainsString('sk-ant-valor-real', (string) $bruto);
        $this->assertSame('sk-ant-valor-real', Crypt::decryptString($bruto));
    }

    public function test_le_de_volta_o_valor_original(): void
    {
        SecretSetting::set('openai_api_key', 'sk-abc123', 1);

        $this->assertSame('sk-abc123', SecretSetting::get('openai_api_key', 1));
    }

    public function test_le_valor_legado_em_texto_puro(): void
    {
        // Instalação que ainda não rodou a migration de cifragem.
        Setting::set('groq_api_key', 'gsk-legado-em-claro', 1);

        $this->assertSame('gsk-legado-em-claro', SecretSetting::get('groq_api_key', 1));
    }

    public function test_isolamento_por_tenant(): void
    {
        SecretSetting::set('openai_api_key', 'sk-do-tenant-1', 1);
        SecretSetting::set('openai_api_key', 'sk-do-tenant-2', 2);

        $this->assertSame('sk-do-tenant-1', SecretSetting::get('openai_api_key', 1));
        $this->assertSame('sk-do-tenant-2', SecretSetting::get('openai_api_key', 2));
    }

    public function test_remover_limpa_o_valor(): void
    {
        SecretSetting::set('gemini_api_key', 'chave', 1);
        $this->assertTrue(SecretSetting::isSet('gemini_api_key', 1));

        SecretSetting::set('gemini_api_key', null, 1);

        $this->assertFalse(SecretSetting::isSet('gemini_api_key', 1));
        $this->assertNull(SecretSetting::get('gemini_api_key', 1));
    }

    public function test_is_set_nao_precisa_decifrar(): void
    {
        $this->assertFalse(SecretSetting::isSet('anthropic_api_key', 1));

        SecretSetting::set('anthropic_api_key', 'sk-ant-x', 1);

        $this->assertTrue(SecretSetting::isSet('anthropic_api_key', 1));
    }

    public function test_migration_cifra_chave_ja_gravada_em_claro(): void
    {
        Setting::set('anthropic_api_key', 'sk-ant-antiga', 1);

        $migration = require database_path('migrations/2026_08_26_000003_cifra_chaves_de_llm_em_settings.php');
        $migration->up();

        $bruto = Setting::get('anthropic_api_key', null, 1);
        $this->assertNotSame('sk-ant-antiga', $bruto, 'A migration não cifrou a chave.');
        $this->assertSame('sk-ant-antiga', SecretSetting::get('anthropic_api_key', 1));
    }

    public function test_migration_e_idempotente(): void
    {
        SecretSetting::set('openai_api_key', 'sk-ja-cifrada', 1);
        $antes = Setting::get('openai_api_key', null, 1);

        $migration = require database_path('migrations/2026_08_26_000003_cifra_chaves_de_llm_em_settings.php');
        $migration->up();

        // Rodar de novo não pode cifrar duas vezes.
        $this->assertSame('sk-ja-cifrada', SecretSetting::get('openai_api_key', 1));
        $this->assertSame($antes, Setting::get('openai_api_key', null, 1));
    }
}
