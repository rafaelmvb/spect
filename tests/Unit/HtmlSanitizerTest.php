<?php

namespace Tests\Unit;

use App\Support\HtmlSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class HtmlSanitizerTest extends TestCase
{
    /**
     * Bypasses que passavam pela versão baseada em regex.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function payloadsDeXss(): array
    {
        return [
            'handler sem aspas' => ['<div onclick=alert(1)>x</div>', 'onclick'],
            'handler com aspas' => ['<div onclick="alert(1)">x</div>', 'onclick'],
            'handler maiusculo' => ['<div OnClick="alert(1)">x</div>', 'onclick'],
            'href javascript sem aspas' => ['<a href=javascript:alert(1)>x</a>', 'javascript:'],
            'href javascript com aspas' => ['<a href="javascript:alert(1)">x</a>', 'javascript:'],
            'href javascript por entidade' => ['<a href="jav&#x61;script:alert(1)">x</a>', 'script:'],
            'href javascript com espaco' => ['<a href="java script:alert(1)">x</a>', 'script:'],
            'style com url javascript' => ['<div style="background:url(javascript:alert(1))">x</div>', 'javascript:'],
            'script inline' => ['<script>alert(1)</script>', 'alert'],
            'img com onerror' => ['<img src=x onerror=alert(1)>', 'onerror'],
            'iframe' => ['<iframe src="//evil.tld"></iframe>', 'iframe'],
            'svg com onload' => ['<svg onload=alert(1)></svg>', 'onload'],
            'data uri em href' => ['<a href="data:text/html;base64,PHNjcmlwdD4=">x</a>', 'data:'],
            'comentario condicional' => ['<!--[if IE]><script>alert(1)</script><![endif]-->', 'script'],
        ];
    }

    #[DataProvider('payloadsDeXss')]
    public function test_remove_vetores_de_xss(string $entrada, string $naoDeveConter): void
    {
        $saida = strtolower(HtmlSanitizer::sanitize($entrada));

        $this->assertStringNotContainsString(
            $naoDeveConter,
            $saida,
            "Payload sobreviveu à sanitização: {$entrada} => {$saida}"
        );
    }

    public function test_preserva_formatacao_legitima(): void
    {
        $html = '<p>Texto com <strong>negrito</strong>, <em>itálico</em> e <a href="https://exemplo.com" title="ir">link</a>.</p>';

        $this->assertSame($html, HtmlSanitizer::sanitize($html));
    }

    public function test_preserva_listas_e_titulos(): void
    {
        $html = '<h2>Título</h2><ul><li>Um</li><li>Dois</li></ul><blockquote>Citação</blockquote>';

        $this->assertSame($html, HtmlSanitizer::sanitize($html));
    }

    public function test_mantem_texto_de_tag_nao_permitida(): void
    {
        // <marquee> não está na allowlist, mas o texto do aluno deve continuar lá.
        $saida = HtmlSanitizer::sanitize('<marquee>conteúdo importante</marquee>');

        $this->assertStringContainsString('conteúdo importante', $saida);
        $this->assertStringNotContainsString('marquee', $saida);
    }

    public function test_descarta_corpo_de_script(): void
    {
        $saida = HtmlSanitizer::sanitize('<p>antes</p><script>roubarSessao()</script><p>depois</p>');

        $this->assertStringContainsString('antes', $saida);
        $this->assertStringContainsString('depois', $saida);
        $this->assertStringNotContainsString('roubarSessao', $saida);
    }

    public function test_aceita_ancora_e_caminho_relativo(): void
    {
        $saida = HtmlSanitizer::sanitize('<a href="#secao">a</a><a href="/aula/1">b</a>');

        $this->assertStringContainsString('href="#secao"', $saida);
        $this->assertStringContainsString('href="/aula/1"', $saida);
    }

    public function test_adiciona_noopener_em_target_blank(): void
    {
        $saida = HtmlSanitizer::sanitize('<a href="https://exemplo.com" target="_blank">x</a>');

        $this->assertStringContainsString('noopener', $saida);
    }

    public function test_remove_atributo_style(): void
    {
        $saida = HtmlSanitizer::sanitize('<div style="position:fixed;inset:0;opacity:0">x</div>');

        $this->assertStringNotContainsString('style', $saida);
        $this->assertStringContainsString('x', $saida);
    }

    public function test_string_vazia_e_nula(): void
    {
        $this->assertSame('', HtmlSanitizer::sanitize(null));
        $this->assertSame('', HtmlSanitizer::sanitize(''));
        $this->assertSame('', HtmlSanitizer::sanitize('   '));
    }

    public function test_preserva_acentuacao(): void
    {
        $saida = HtmlSanitizer::sanitize('<p>Avaliação de progresso — não perca a sessão</p>');

        $this->assertStringContainsString('Avaliação', $saida);
        $this->assertStringContainsString('sessão', $saida);
        $this->assertStringContainsString('—', $saida);
    }
}
