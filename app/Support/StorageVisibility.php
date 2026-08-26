<?php

namespace App\Support;

/**
 * Decide se um caminho de upload é público ou restrito.
 *
 * Antes disto tudo caía em storage/app/public e era servido sem autenticação
 * por GET /storage/{path} — material de aula, faixa de áudio e anexo de teste
 * clínico inclusive. Quem tivesse o link acessava.
 *
 * Continua público só o que precisa aparecer para visitante anônimo: imagem de
 * checkout, logo (a tela de login da área de membros usa) e foto de perfil.
 */
class StorageVisibility
{
    /**
     * Prefixos restritos e o que o segundo segmento do caminho identifica.
     *
     * 'product' => .../{product_id}/...  → exige acesso àquele produto
     * 'tenant'  => .../{tenant_id}/...   → exige pertencer ao tenant
     * null      => sem dono no caminho   → exige apenas sessão
     *
     * @var array<string, 'product'|'tenant'|null>
     */
    private const RESTRITOS = [
        'member-area' => 'product',
        'member-area-posts' => 'product',
        'community-events' => 'product',
        'community-groups' => 'product',
        'community-stories' => 'product',
        'clinical-test-ai-context' => null,
        'product-ai-context' => 'product',
        'music' => 'tenant',
        'member-posts' => 'tenant',
    ];

    public static function isRestrito(string $path): bool
    {
        return array_key_exists(self::prefixo($path), self::RESTRITOS);
    }

    /**
     * @return 'product'|'tenant'|null
     */
    public static function tipoDeDono(string $path): ?string
    {
        return self::RESTRITOS[self::prefixo($path)] ?? null;
    }

    /**
     * Segundo segmento do caminho, quando o prefixo declara um dono.
     * Ex.: member-area/9f2c.../aula.pdf → "9f2c..."
     */
    public static function donoDoCaminho(string $path): ?string
    {
        if (self::tipoDeDono($path) === null) {
            return null;
        }

        $partes = explode('/', trim(str_replace('\\', '/', $path), '/'));
        $dono = $partes[1] ?? '';

        return $dono !== '' ? $dono : null;
    }

    private static function prefixo(string $path): string
    {
        $normalizado = trim(str_replace('\\', '/', $path), '/');
        $primeiro = explode('/', $normalizado)[0] ?? '';

        return strtolower($primeiro);
    }

    /**
     * Prefixos restritos, para o comando que move o que já está gravado.
     *
     * @return list<string>
     */
    public static function prefixosRestritos(): array
    {
        return array_keys(self::RESTRITOS);
    }
}
