<?php

namespace App\Http\Controllers;

use App\Support\DockerSetupState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

/**
 * Roda migrations pendentes pelo painel.
 *
 * Existe porque o deploy padrao e por upload de arquivos, sem acesso a SSH:
 * sem isto nao ha como aplicar uma migration nova depois de subir a release.
 * Herdado do antigo UpdateController, que foi removido junto com o auto-updater.
 */
class MigrateController extends Controller
{
    public function __invoke(Request $request): JsonResponse|RedirectResponse
    {
        try {
            Artisan::call('migrate', ['--force' => true]);
            $output = (string) Artisan::output();

            try {
                Artisan::call('config:cache');
            } catch (\Throwable) {
                // Cache de config e otimizacao: falhar aqui nao invalida a migration.
            }

            $msg = 'Migrations executadas com sucesso.';
            if ($request->wantsJson()) {
                return response()->json(['success' => true, 'message' => $msg, 'output' => self::toUtf8($output)]);
            }

            return redirect()->route('settings.index', ['tab' => 'update'])->with('success', $msg);
        } catch (\Throwable $e) {
            $msg = self::withDockerHint('Falha ao rodar migrations: '.self::toUtf8($e->getMessage()));
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }

            return redirect()->route('settings.index', ['tab' => 'update'])->with('error', $msg);
        }
    }

    /**
     * A saida do artisan pode vir em encoding do sistema (Windows/PT-BR) e
     * quebrar o json_encode da resposta.
     */
    private static function toUtf8(string $str): string
    {
        if ($str === '') {
            return $str;
        }
        $utf8 = @mb_convert_encoding($str, 'UTF-8', 'UTF-8');
        if ($utf8 !== false) {
            return $utf8;
        }
        if (function_exists('iconv')) {
            $cleaned = @iconv('UTF-8', 'UTF-8//IGNORE', $str);
            if ($cleaned !== false) {
                return $cleaned;
            }
        }

        return preg_replace('/[^\x20-\x7E\x0A\x0D]/', '?', $str);
    }

    private static function withDockerHint(string $message): string
    {
        if (! DockerSetupState::isDocker()) {
            return $message;
        }

        return rtrim($message)."\n\n"
            ."Se falhar pelo painel, rode no terminal da VPS:\n"
            ."docker compose exec app php artisan migrate --force";
    }
}
