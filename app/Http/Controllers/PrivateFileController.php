<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\User;
use App\Services\StorageService;
use App\Support\StorageVisibility;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serve conteúdo restrito em GET /arquivo/{path}.
 *
 * Material de aula, faixa de áudio, mídia de comunidade e anexo de teste
 * clínico ficavam em storage/app/public e saíam por GET /storage/{path} sem
 * nenhuma checagem: bastava ter o link. Aqui exige-se sessão e, quando o
 * caminho identifica o produto, acesso àquele produto.
 */
class PrivateFileController extends Controller
{
    public function __invoke(Request $request, string $path): Response
    {
        $path = ltrim(str_replace('\\', '/', rawurldecode($path)), '/');

        if ($path === '' || str_contains($path, '..')) {
            abort(404);
        }

        // Caminho público não passa por aqui — evita virar rota alternativa
        // para o que já é servido por /storage.
        if (! StorageVisibility::isRestrito($path)) {
            abort(404);
        }

        $user = $request->user();
        if (! $user) {
            abort(403, 'Faça login para acessar este arquivo.');
        }

        $storage = new StorageService($this->tenantIdDoUsuario($user));

        // Fallback para o disco publico enquanto arquivos antigos nao foram
        // movidos por `storage:mover-restritos`. A autorizacao vale para os dois.
        $disk = $storage->restrictedDisk();
        if (! $disk->exists($path)) {
            $publico = $storage->disk();
            if (! $publico->exists($path)) {
                abort(404);
            }
            $disk = $publico;
        }

        $this->autorizar($user, $path);

        return $disk->response($path, null, [
            // Sem cache compartilhado: a resposta depende de quem pediu.
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function tenantIdDoUsuario(User $user): ?int
    {
        return $user->tenant_id !== null ? (int) $user->tenant_id : null;
    }

    private function autorizar(User $user, string $path): void
    {
        $tipo = StorageVisibility::tipoDeDono($path);
        $dono = StorageVisibility::donoDoCaminho($path);

        // Prefixo sem dono no caminho (ex.: anexo de teste clínico): basta o
        // usuário ser do painel; aluno não tem o que fazer ali.
        if ($tipo === null) {
            abort_unless($user->canAccessPanel(), 403, 'Sem acesso a este arquivo.');

            return;
        }

        if ($dono === null) {
            abort(404);
        }

        if ($tipo === 'tenant') {
            abort_unless(
                $dono === 'global' || (string) $user->tenant_id === (string) $dono,
                403,
                'Sem acesso a este arquivo.'
            );

            return;
        }

        $produto = Product::find($dono);
        if (! $produto) {
            abort(404);
        }

        // hasMemberAreaAccess já cobre o admin do tenant e quem comprou.
        abort_unless($produto->hasMemberAreaAccess($user), 403, 'Sem acesso a este conteúdo.');
    }
}
