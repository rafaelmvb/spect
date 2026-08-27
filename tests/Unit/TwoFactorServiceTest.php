<?php

namespace Tests\Unit;

use App\Services\TwoFactorService;
use PHPUnit\Framework\TestCase;

class TwoFactorServiceTest extends TestCase
{
    private TwoFactorService $totp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->totp = new TwoFactorService;
    }

    /**
     * Gera o código para um instante fixo, chamando o método privado.
     * É o único jeito de conferir contra os vetores da RFC sem viajar no tempo.
     */
    private function codigoNoPasso(string $segredo, int $passo): string
    {
        $metodo = new \ReflectionMethod(TwoFactorService::class, 'gerarCodigo');
        $metodo->setAccessible(true);

        return $metodo->invoke($this->totp, $segredo, $passo);
    }

    /**
     * RFC 6238, secret "12345678901234567890" em base32 = GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ.
     * Os valores esperados são os da tabela do apêndice B (SHA1, 6 dígitos).
     */
    public function test_bate_com_os_vetores_da_rfc_6238(): void
    {
        $segredo = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

        $casos = [
            59 => '287082',
            1111111109 => '081804',
            1111111111 => '050471',
            1234567890 => '005924',
            2000000000 => '279037',
        ];

        foreach ($casos as $tempo => $esperado) {
            $passo = (int) floor($tempo / 30);

            $this->assertSame(
                $esperado,
                $this->codigoNoPasso($segredo, $passo),
                "O código para t={$tempo} não bate com a RFC 6238."
            );
        }
    }

    public function test_segredo_gerado_e_base32_valido(): void
    {
        $segredo = $this->totp->gerarSegredo();

        $this->assertSame(20, strlen($segredo));
        $this->assertMatchesRegularExpression('/^[A-Z2-7]+$/', $segredo);
    }

    public function test_codigo_do_momento_e_aceito(): void
    {
        $segredo = $this->totp->gerarSegredo();
        $agora = $this->codigoNoPasso($segredo, (int) floor(time() / 30));

        $this->assertTrue($this->totp->codigoValido($segredo, $agora));
    }

    public function test_aceita_o_passo_anterior_e_o_seguinte(): void
    {
        $segredo = $this->totp->gerarSegredo();
        $passo = (int) floor(time() / 30);

        // Relógio desalinhado por até 30s não deve travar o acesso.
        $this->assertTrue($this->totp->codigoValido($segredo, $this->codigoNoPasso($segredo, $passo - 1)));
        $this->assertTrue($this->totp->codigoValido($segredo, $this->codigoNoPasso($segredo, $passo + 1)));
    }

    public function test_recusa_codigo_de_janela_distante(): void
    {
        $segredo = $this->totp->gerarSegredo();
        $passo = (int) floor(time() / 30);

        $this->assertFalse($this->totp->codigoValido($segredo, $this->codigoNoPasso($segredo, $passo - 5)));
        $this->assertFalse($this->totp->codigoValido($segredo, $this->codigoNoPasso($segredo, $passo + 5)));
    }

    public function test_recusa_codigo_malformado(): void
    {
        $segredo = $this->totp->gerarSegredo();

        foreach (['', '123', '1234567', 'abcdef'] as $invalido) {
            $this->assertFalse($this->totp->codigoValido($segredo, $invalido), "Aceitou \"{$invalido}\".");
        }
    }

    public function test_segredo_diferente_gera_codigo_diferente(): void
    {
        $passo = (int) floor(time() / 30);

        $this->assertNotSame(
            $this->codigoNoPasso($this->totp->gerarSegredo(), $passo),
            $this->codigoNoPasso($this->totp->gerarSegredo(), $passo)
        );
    }

    public function test_codigos_de_recuperacao_sao_unicos(): void
    {
        $codigos = $this->totp->gerarCodigosDeRecuperacao();

        $this->assertCount(8, $codigos);
        $this->assertSame($codigos, array_unique($codigos));
        foreach ($codigos as $c) {
            $this->assertMatchesRegularExpression('/^[A-Z0-9]{5}-[A-Z0-9]{5}$/', $c);
        }
    }
}
