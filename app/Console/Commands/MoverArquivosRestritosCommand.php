<?php

namespace App\Console\Commands;

use App\Support\StorageVisibility;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Move para storage/app/private o conteúdo restrito que já estava em
 * storage/app/public, onde era servido sem autenticação.
 *
 * Só faz sentido em armazenamento local. Em S3/R2 o arquivo continua no mesmo
 * bucket — ali quem protege é a rota, que nunca expõe a URL direta.
 */
class MoverArquivosRestritosCommand extends Command
{
    protected $signature = 'storage:mover-restritos
                            {--dry-run : Apenas lista o que seria movido}';

    protected $description = 'Move conteúdo restrito de storage/app/public para storage/app/private';

    public function handle(): int
    {
        $publico = Storage::disk('public');
        $privado = Storage::disk('local');
        $simulacao = (bool) $this->option('dry-run');

        $movidos = 0;
        $falhas = 0;
        $jaExistiam = 0;

        foreach (StorageVisibility::prefixosRestritos() as $prefixo) {
            if (! $publico->exists($prefixo)) {
                continue;
            }

            $arquivos = $publico->allFiles($prefixo);
            if ($arquivos === []) {
                continue;
            }

            $this->line("<info>{$prefixo}</info>: ".count($arquivos).' arquivo(s)');

            foreach ($arquivos as $arquivo) {
                if ($simulacao) {
                    $this->line("  moveria: {$arquivo}");
                    $movidos++;

                    continue;
                }

                if ($privado->exists($arquivo)) {
                    // Já movido numa execução anterior; remove a cópia exposta.
                    $publico->delete($arquivo);
                    $jaExistiam++;

                    continue;
                }

                $stream = $publico->readStream($arquivo);
                if ($stream === null || $stream === false) {
                    $this->error("  falha ao ler: {$arquivo}");
                    $falhas++;

                    continue;
                }

                $gravou = $privado->writeStream($arquivo, $stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }

                if (! $gravou) {
                    $this->error("  falha ao gravar: {$arquivo}");
                    $falhas++;

                    continue;
                }

                $publico->delete($arquivo);
                $movidos++;
            }
        }

        if ($simulacao) {
            $this->info("Simulação: {$movidos} arquivo(s) seriam movidos. Rode sem --dry-run para aplicar.");

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info("Movidos: {$movidos}");
        if ($jaExistiam > 0) {
            $this->info("Já estavam no destino (cópia pública removida): {$jaExistiam}");
        }
        if ($falhas > 0) {
            $this->error("Falhas: {$falhas} — os arquivos seguem no disco público.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
