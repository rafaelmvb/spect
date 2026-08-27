<?php

namespace App\Services;

use App\Models\ClinicalTest;
use Illuminate\Support\Facades\DB;

/**
 * Importa testes clínicos em massa.
 *
 * Dois formatos, porque são dois cenários diferentes:
 *
 *  - JSON: estrutura completa (perguntas, opções e faixas de resultado). É o
 *    caminho para migrar de outro sistema ou versionar o acervo.
 *  - CSV: uma linha por pergunta, com as colunas do teste repetidas. É o
 *    formato de quem tem o acervo numa planilha.
 *
 * Toda importação passa antes por uma validação que não escreve nada: o
 * conferir() devolve o que seria criado e os erros, e só depois o importar()
 * grava — dentro de uma transação, para um arquivo com problema no meio não
 * deixar metade do acervo cadastrada.
 */
class ClinicalTestImportService
{
    public const TIPOS_DE_QUESTAO = ['scale', 'single', 'multi', 'boolean'];

    /**
     * Analisa o conteúdo sem gravar nada.
     *
     * @return array{ok: bool, testes: list<array<string, mixed>>, erros: list<string>, resumo: array<string, int>}
     */
    public function conferir(string $conteudo, string $formato): array
    {
        $erros = [];

        try {
            $testes = $formato === 'json'
                ? $this->lerJson($conteudo)
                : $this->lerCsv($conteudo);
        } catch (\RuntimeException $e) {
            return [
                'ok' => false,
                'testes' => [],
                'erros' => [$e->getMessage()],
                'resumo' => ['testes' => 0, 'questoes' => 0, 'faixas' => 0],
            ];
        }

        $validos = [];
        foreach ($testes as $i => $teste) {
            $problemas = $this->validarTeste($teste, $i + 1);
            if ($problemas === []) {
                $validos[] = $teste;
            } else {
                array_push($erros, ...$problemas);
            }
        }

        return [
            'ok' => $erros === [] && $validos !== [],
            'testes' => $validos,
            'erros' => $erros,
            'resumo' => [
                'testes' => count($validos),
                'questoes' => array_sum(array_map(fn ($t) => count($t['questoes'] ?? []), $validos)),
                'faixas' => array_sum(array_map(fn ($t) => count($t['faixas'] ?? []), $validos)),
            ],
        ];
    }

    /**
     * Grava o que passou pela conferência.
     *
     * @param  list<array<string, mixed>>  $testes
     * @return array{criados: int, atualizados: int}
     */
    public function importar(array $testes, ?int $tenantId, bool $substituirExistentes = false): array
    {
        $criados = 0;
        $atualizados = 0;

        DB::transaction(function () use ($testes, $tenantId, $substituirExistentes, &$criados, &$atualizados) {
            foreach ($testes as $dados) {
                $existente = ClinicalTest::forTenant($tenantId)
                    ->whereNull('professional_user_id')
                    ->where('name', $dados['nome'])
                    ->first();

                if ($existente && ! $substituirExistentes) {
                    continue;
                }

                if ($existente) {
                    // Recria a estrutura: manter questão antiga junto com a nova
                    // deixaria a pontuação inconsistente.
                    $existente->questions()->delete();
                    $existente->scoringRules()->delete();
                    $teste = $existente;
                    $atualizados++;
                } else {
                    $teste = new ClinicalTest;
                    $criados++;
                }

                $teste->fill([
                    'tenant_id' => $tenantId,
                    'name' => $dados['nome'],
                    'category' => $dados['categoria'] ?? 'geral',
                    'description' => $dados['descricao'] ?? null,
                    'instructions' => $dados['instrucoes'] ?? null,
                    // A coluna e NOT NULL: sem o valor na planilha, usa o default.
                    'estimated_minutes' => $dados['minutos'] ?? 10,
                    'is_active' => $dados['ativo'] ?? true,
                    'is_child_screening' => $dados['infantil'] ?? false,
                ]);
                $teste->save();

                foreach ($dados['questoes'] ?? [] as $posicao => $questao) {
                    $criada = $teste->questions()->create([
                        'text' => $questao['texto'],
                        'type' => $questao['tipo'] ?? 'scale',
                        'scale_min' => $questao['escala_min'] ?? 1,
                        'scale_max' => $questao['escala_max'] ?? 5,
                        'scale_labels' => $questao['escala_rotulos'] ?? null,
                        'position' => $posicao + 1,
                    ]);

                    foreach ($questao['opcoes'] ?? [] as $j => $opcao) {
                        $criada->options()->create([
                            'text' => $opcao['texto'],
                            'value' => $opcao['valor'] ?? 0,
                            'position' => $j + 1,
                        ]);
                    }
                }

                foreach ($dados['faixas'] ?? [] as $faixa) {
                    $teste->scoringRules()->create([
                        'min_score' => $faixa['min'],
                        'max_score' => $faixa['max'],
                        'result_label' => $faixa['resultado'],
                        'result_description' => $faixa['interpretacao'] ?? null,
                        'challenge_tags' => $faixa['tags'] ?? [],
                    ]);
                }
            }
        });

        return ['criados' => $criados, 'atualizados' => $atualizados];
    }

    // ------------------------------------------------------------------ JSON

    /**
     * @return list<array<string, mixed>>
     */
    private function lerJson(string $conteudo): array
    {
        $dados = json_decode($conteudo, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('O arquivo não é um JSON válido: '.json_last_error_msg());
        }

        // Aceita tanto uma lista de testes quanto {"testes": [...]}.
        $lista = $dados['testes'] ?? $dados;

        if (! is_array($lista) || $lista === []) {
            throw new \RuntimeException('O JSON não traz nenhum teste.');
        }

        return array_values($lista);
    }

    // ------------------------------------------------------------------- CSV

    /**
     * Uma linha por pergunta. As colunas do teste se repetem em cada linha dele.
     *
     * @return list<array<string, mixed>>
     */
    private function lerCsv(string $conteudo): array
    {
        $linhas = preg_split('/\r\n|\r|\n/', trim($conteudo));
        if (count($linhas) < 2) {
            throw new \RuntimeException('O CSV precisa do cabeçalho e ao menos uma linha.');
        }

        $separador = $this->detectarSeparador($linhas[0]);
        $cabecalho = array_map(
            fn ($c) => strtolower(trim($c, " \t\"'\xEF\xBB\xBF")),
            str_getcsv(array_shift($linhas), $separador)
        );

        $obrigatorias = ['teste', 'pergunta'];
        $faltando = array_diff($obrigatorias, $cabecalho);
        if ($faltando !== []) {
            throw new \RuntimeException('O CSV precisa das colunas: '.implode(', ', $faltando).'.');
        }

        $porTeste = [];
        foreach ($linhas as $numero => $linha) {
            if (trim($linha) === '') {
                continue;
            }

            $valores = str_getcsv($linha, $separador);
            $registro = [];
            foreach ($cabecalho as $i => $coluna) {
                $registro[$coluna] = isset($valores[$i]) ? trim($valores[$i]) : '';
            }

            $nome = $registro['teste'] ?? '';
            if ($nome === '') {
                throw new \RuntimeException('Linha '.($numero + 2).': a coluna "teste" está vazia.');
            }

            if (! isset($porTeste[$nome])) {
                $porTeste[$nome] = [
                    'nome' => $nome,
                    'categoria' => $registro['categoria'] ?? 'geral',
                    'descricao' => $registro['descricao'] ?? null,
                    'instrucoes' => $registro['instrucoes'] ?? null,
                    'minutos' => is_numeric($registro['minutos'] ?? '') ? (int) $registro['minutos'] : null,
                    'infantil' => $this->paraBooleano($registro['infantil'] ?? ''),
                    'questoes' => [],
                    'faixas' => [],
                ];
            }

            $porTeste[$nome]['questoes'][] = [
                'texto' => $registro['pergunta'],
                'tipo' => in_array($registro['tipo'] ?? '', self::TIPOS_DE_QUESTAO, true)
                    ? $registro['tipo']
                    : 'scale',
                'escala_min' => is_numeric($registro['escala_min'] ?? '') ? (int) $registro['escala_min'] : 1,
                'escala_max' => is_numeric($registro['escala_max'] ?? '') ? (int) $registro['escala_max'] : 5,
                'opcoes' => $this->lerOpcoes($registro['opcoes'] ?? ''),
            ];

            // Faixa de resultado: repetida por linha, então entra uma vez só.
            if (($registro['faixa_resultado'] ?? '') !== '') {
                $chave = $registro['faixa_min'].'-'.$registro['faixa_max'].'-'.$registro['faixa_resultado'];
                $porTeste[$nome]['faixas'][$chave] = [
                    'min' => (int) ($registro['faixa_min'] ?? 0),
                    'max' => (int) ($registro['faixa_max'] ?? 0),
                    'resultado' => $registro['faixa_resultado'],
                    'interpretacao' => $registro['faixa_interpretacao'] ?? null,
                    'tags' => $this->lerTags($registro['faixa_tags'] ?? ''),
                ];
            }
        }

        return array_values(array_map(function (array $teste) {
            $teste['faixas'] = array_values($teste['faixas']);

            return $teste;
        }, $porTeste));
    }

    private function detectarSeparador(string $cabecalho): string
    {
        return substr_count($cabecalho, ';') > substr_count($cabecalho, ',') ? ';' : ',';
    }

    /**
     * "Nunca=0|Às vezes=1|Sempre=2"
     *
     * @return list<array{texto: string, valor: int}>
     */
    private function lerOpcoes(string $bruto): array
    {
        if (trim($bruto) === '') {
            return [];
        }

        $opcoes = [];
        foreach (explode('|', $bruto) as $parte) {
            $parte = trim($parte);
            if ($parte === '') {
                continue;
            }

            if (str_contains($parte, '=')) {
                [$texto, $valor] = explode('=', $parte, 2);
                $opcoes[] = ['texto' => trim($texto), 'valor' => (int) trim($valor)];
            } else {
                $opcoes[] = ['texto' => $parte, 'valor' => count($opcoes)];
            }
        }

        return $opcoes;
    }

    /**
     * @return list<string>
     */
    private function lerTags(string $bruto): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $bruto))));
    }

    private function paraBooleano(string $valor): bool
    {
        return in_array(strtolower(trim($valor)), ['1', 'sim', 'true', 'x', 'verdadeiro'], true);
    }

    // ------------------------------------------------------------- Validação

    /**
     * @param  array<string, mixed>  $teste
     * @return list<string>
     */
    private function validarTeste(array $teste, int $posicao): array
    {
        $erros = [];
        $rotulo = trim((string) ($teste['nome'] ?? '')) !== ''
            ? '"'.$teste['nome'].'"'
            : "teste #{$posicao}";

        if (trim((string) ($teste['nome'] ?? '')) === '') {
            $erros[] = "O {$rotulo} está sem nome.";
        }

        $questoes = $teste['questoes'] ?? [];
        if (! is_array($questoes) || $questoes === []) {
            $erros[] = "O teste {$rotulo} não tem nenhuma pergunta.";
        }

        foreach ($questoes as $i => $questao) {
            $n = $i + 1;

            if (trim((string) ($questao['texto'] ?? '')) === '') {
                $erros[] = "Pergunta {$n} de {$rotulo} está sem texto.";
            }

            $tipo = $questao['tipo'] ?? 'scale';
            if (! in_array($tipo, self::TIPOS_DE_QUESTAO, true)) {
                $erros[] = "Pergunta {$n} de {$rotulo}: tipo \"{$tipo}\" não existe. Use ".implode(', ', self::TIPOS_DE_QUESTAO).'.';
            }

            // Sem opções, uma pergunta de escolha não tem o que pontuar.
            if (in_array($tipo, ['single', 'multi'], true) && ($questao['opcoes'] ?? []) === []) {
                $erros[] = "Pergunta {$n} de {$rotulo} é do tipo \"{$tipo}\" mas não tem opções de resposta.";
            }

            if ($tipo === 'scale') {
                $min = $questao['escala_min'] ?? 1;
                $max = $questao['escala_max'] ?? 5;
                if ($max <= $min) {
                    $erros[] = "Pergunta {$n} de {$rotulo}: o fim da escala ({$max}) precisa ser maior que o início ({$min}).";
                }
            }
        }

        foreach ($teste['faixas'] ?? [] as $i => $faixa) {
            $n = $i + 1;

            if (trim((string) ($faixa['resultado'] ?? '')) === '') {
                $erros[] = "Faixa {$n} de {$rotulo} está sem o texto do resultado.";
            }
            if (($faixa['max'] ?? 0) < ($faixa['min'] ?? 0)) {
                $erros[] = "Faixa {$n} de {$rotulo}: a pontuação máxima é menor que a mínima.";
            }
        }

        return $erros;
    }

    /**
     * Modelo de CSV para quem está começando do zero.
     */
    public function modeloCsv(): string
    {
        $colunas = 'teste,categoria,descricao,instrucoes,minutos,infantil,pergunta,tipo,escala_min,escala_max,opcoes,faixa_min,faixa_max,faixa_resultado,faixa_interpretacao,faixa_tags';

        $exemplo1 = 'Escala de Atenção,atencao,Rastreio de sinais de desatenção,Responda pensando nas últimas duas semanas.,10,nao,'
            .'"Tenho dificuldade de manter o foco em tarefas longas",scale,1,5,,0,10,Sinais leves,Poucos indicadores presentes.,atencao';

        $exemplo2 = 'Escala de Atenção,,,,,,'
            .'"Perco objetos do dia a dia com frequência",scale,1,5,,11,25,Sinais moderados,Vale acompanhamento.,"atencao,organizacao"';

        $exemplo3 = 'Rastreio Infantil de Sono,sono,Respondido pelo responsável,Pense na rotina do último mês.,8,sim,'
            .'"A criança demora para adormecer",single,,,"Nunca=0|Às vezes=1|Sempre=2",0,4,Sono adequado,Sem sinais relevantes.,sono-infantil';

        return implode("\n", [$colunas, $exemplo1, $exemplo2, $exemplo3])."\n";
    }
}
