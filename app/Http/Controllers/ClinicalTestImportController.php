<?php

namespace App\Http\Controllers;

use App\Services\ClinicalTestImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Importação em massa de testes clínicos no painel.
 */
class ClinicalTestImportController extends Controller
{
    public function __construct(private readonly ClinicalTestImportService $importador) {}

    /** CSV de exemplo já preenchido, para quem parte do zero. */
    public function modelo(): Response
    {
        return response($this->importador->modeloCsv(), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="modelo-testes-spectra.csv"',
        ]);
    }

    /** Analisa o arquivo e mostra o que seria criado, sem gravar nada. */
    public function conferir(Request $request): JsonResponse
    {
        $validado = $request->validate([
            'arquivo' => ['required', 'file', 'max:10240', 'mimes:csv,txt,json'],
        ], [
            'arquivo.mimes' => 'Envie um arquivo .csv ou .json.',
            'arquivo.max' => 'O arquivo passa de 10 MB. Divida o acervo em partes.',
        ]);

        $arquivo = $validado['arquivo'];
        $formato = strtolower($arquivo->getClientOriginalExtension()) === 'json' ? 'json' : 'csv';

        $resultado = $this->importador->conferir(
            (string) file_get_contents($arquivo->getRealPath()),
            $formato
        );

        return response()->json([
            'ok' => $resultado['ok'],
            'formato' => $formato,
            'resumo' => $resultado['resumo'],
            'erros' => $resultado['erros'],
            // Só o suficiente para o admin conferir antes de confirmar.
            'previa' => array_map(fn (array $t) => [
                'nome' => $t['nome'] ?? '',
                'categoria' => $t['categoria'] ?? 'geral',
                'infantil' => (bool) ($t['infantil'] ?? false),
                'questoes' => count($t['questoes'] ?? []),
                'faixas' => count($t['faixas'] ?? []),
            ], array_slice($resultado['testes'], 0, 50)),
        ]);
    }

    /** Grava de fato. */
    public function importar(Request $request): JsonResponse
    {
        $validado = $request->validate([
            'arquivo' => ['required', 'file', 'max:10240', 'mimes:csv,txt,json'],
            'substituir' => ['nullable', 'boolean'],
        ]);

        $arquivo = $validado['arquivo'];
        $formato = strtolower($arquivo->getClientOriginalExtension()) === 'json' ? 'json' : 'csv';

        $resultado = $this->importador->conferir(
            (string) file_get_contents($arquivo->getRealPath()),
            $formato
        );

        if (! $resultado['ok']) {
            return response()->json([
                'ok' => false,
                'message' => 'O arquivo tem erros. Corrija e envie de novo.',
                'erros' => $resultado['erros'],
            ], 422);
        }

        $gravados = $this->importador->importar(
            $resultado['testes'],
            $request->user()->tenant_id,
            (bool) ($validado['substituir'] ?? false),
        );

        $partes = [];
        if ($gravados['criados'] > 0) {
            $partes[] = $gravados['criados'].' teste(s) criado(s)';
        }
        if ($gravados['atualizados'] > 0) {
            $partes[] = $gravados['atualizados'].' atualizado(s)';
        }
        $ignorados = $resultado['resumo']['testes'] - $gravados['criados'] - $gravados['atualizados'];
        if ($ignorados > 0) {
            $partes[] = $ignorados.' já existia(m) e foi(ram) mantido(s)';
        }

        return response()->json([
            'ok' => true,
            'message' => $partes === [] ? 'Nada a importar.' : ucfirst(implode(', ', $partes)).'.',
            'resultado' => $gravados,
        ]);
    }
}
