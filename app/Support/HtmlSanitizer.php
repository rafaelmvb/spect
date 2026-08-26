<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Sanitiza HTML de conteúdo de aula/seção antes de renderizar com v-html.
 *
 * Usa DOMDocument em vez de regex: a versão anterior removia handlers e URLs
 * javascript: por expressão regular e deixava passar atributo sem aspas
 * (<div onclick=alert(1)>), href sem aspas e entidade HTML no esquema
 * (jav&#x61;script:). Com o documento parseado, o que não está na allowlist
 * simplesmente não sobrevive.
 */
class HtmlSanitizer
{
    /** @var list<string> */
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'em', 'b', 'i', 'u', 's', 'a', 'ul', 'ol', 'li',
        'h1', 'h2', 'h3', 'h4', 'blockquote', 'pre', 'code', 'span', 'div',
    ];

    /**
     * Atributos aceitos por tag. 'style' fica de fora de propósito: permite
     * sobrepor a interface do aluno (clickjacking via position/opacity).
     *
     * @var array<string, list<string>>
     */
    private const ALLOWED_ATTRIBUTES = [
        'a' => ['href', 'title', 'target', 'rel'],
        '*' => ['class'],
    ];

    /** @var list<string> */
    private const ALLOWED_URL_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    public static function sanitize(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        $doc = new DOMDocument;
        $anterior = libxml_use_internal_errors(true);

        // O wrapper força UTF-8 e evita que o DOMDocument injete <html>/<body>.
        $carregou = $doc->loadHTML(
            '<?xml encoding="UTF-8"?><div id="raiz-sanitizada">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
        );

        libxml_clear_errors();
        libxml_use_internal_errors($anterior);

        if (! $carregou) {
            return '';
        }

        $raiz = $doc->getElementById('raiz-sanitizada');
        if (! $raiz instanceof DOMElement) {
            return '';
        }

        self::limparNo($raiz);

        $saida = '';
        foreach (iterator_to_array($raiz->childNodes) as $filho) {
            $saida .= $doc->saveHTML($filho);
        }

        return trim($saida);
    }

    /**
     * Percorre em profundidade removendo tags fora da allowlist. O conteúdo
     * de texto de uma tag removida é preservado — tirar <script> leva o corpo
     * junto, mas tirar <marquee> não deve apagar o parágrafo do aluno.
     */
    private static function limparNo(DOMNode $no): void
    {
        foreach (iterator_to_array($no->childNodes) as $filho) {
            if ($filho instanceof DOMElement) {
                $tag = strtolower($filho->nodeName);

                if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                    self::removerMantendoTexto($filho, $tag);

                    continue;
                }

                self::limparAtributos($filho, $tag);
                self::limparNo($filho);

                continue;
            }

            // Comentário pode carregar payload para parsers permissivos.
            if ($filho->nodeType === XML_COMMENT_NODE) {
                $no->removeChild($filho);
            }
        }
    }

    private static function removerMantendoTexto(DOMElement $elemento, string $tag): void
    {
        $pai = $elemento->parentNode;
        if ($pai === null) {
            return;
        }

        // Estes carregam código, não texto: o conteúdo vai embora com a tag.
        if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'template'], true)) {
            $pai->removeChild($elemento);

            return;
        }

        self::limparNo($elemento);
        while ($elemento->firstChild !== null) {
            $pai->insertBefore($elemento->firstChild, $elemento);
        }
        $pai->removeChild($elemento);
    }

    private static function limparAtributos(DOMElement $elemento, string $tag): void
    {
        $permitidos = array_merge(
            self::ALLOWED_ATTRIBUTES['*'] ?? [],
            self::ALLOWED_ATTRIBUTES[$tag] ?? []
        );

        foreach (iterator_to_array($elemento->attributes) as $atributo) {
            $nome = strtolower($atributo->nodeName);

            if (! in_array($nome, $permitidos, true)) {
                $elemento->removeAttribute($atributo->nodeName);

                continue;
            }

            if ($nome === 'href' && ! self::urlSegura($atributo->nodeValue)) {
                $elemento->setAttribute('href', '#');
            }
        }

        // target="_blank" sem noopener dá window.opener para a página de destino.
        if ($tag === 'a' && $elemento->getAttribute('target') === '_blank') {
            $elemento->setAttribute('rel', 'noopener noreferrer');
        }
    }

    private static function urlSegura(?string $url): bool
    {
        $url = trim((string) $url);
        if ($url === '') {
            return false;
        }

        // Link interno de âncora e caminho relativo não têm esquema.
        if (str_starts_with($url, '#') || str_starts_with($url, '/')) {
            return true;
        }

        // O DOM já decodificou entidades, então "jav&#x61;script:" chega aqui
        // como "javascript:". Sobra normalizar espaços e caracteres de controle.
        $normalizada = strtolower(preg_replace('/[\s\x00-\x1F\x7F]+/', '', $url) ?? '');

        $posicao = strpos($normalizada, ':');
        if ($posicao === false) {
            return true; // relativa
        }

        return in_array(substr($normalizada, 0, $posicao), self::ALLOWED_URL_SCHEMES, true);
    }
}
