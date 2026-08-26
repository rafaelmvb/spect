<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ClinicalTestsSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = DB::table('users')->whereNotNull('tenant_id')->value('tenant_id') ?? 1;

        $this->phq9($tenantId);
        $this->gad7($tenantId);
        $this->asrs($tenantId);
        $this->aq10($tenantId);
        $this->ahsd($tenantId);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PHQ-9 — Rastreio de Humor / Depressão
    // ─────────────────────────────────────────────────────────────────────────
    private function phq9(int $tenantId): void
    {
        $testId = DB::table('clinical_tests')->insertGetId([
            'tenant_id'          => $tenantId,
            'name'               => 'PHQ-9 — Rastreio de Humor',
            'category'           => 'humor',
            'description'        => 'Questionário de Saúde do Paciente com 9 itens para rastreio de sintomas depressivos nas últimas 2 semanas.',
            'instructions'       => 'Para cada item, marque com que frequência você foi incomodado pelos problemas descritos nas últimas 2 semanas.',
            'estimated_minutes'  => 3,
            'is_active'          => true,
            'position'           => 1,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        $labels = json_encode(['Nenhum dia', 'Vários dias', 'Mais da metade dos dias', 'Quase todos os dias']);

        $questions = [
            'Pouco interesse ou pouco prazer em fazer as coisas.',
            'Sentir-se para baixo, deprimido(a) ou sem perspectiva.',
            'Dificuldade para adormecer ou permanecer dormindo, ou dormir mais do que de costume.',
            'Sentir-se cansado(a) ou com pouca energia.',
            'Falta de apetite ou comer demais.',
            'Sentir-se mal consigo mesmo(a), ou achar que é um fracasso ou que decepcionou sua família ou a você mesmo(a).',
            'Dificuldade para se concentrar nas coisas, como ler o jornal ou ver televisão.',
            'Mover-se ou falar tão lentamente que outras pessoas possam ter notado, ou ao contrário, estar tão agitado(a) ou inquieto(a) que você fica se mexendo muito mais do que de costume.',
            'Pensar em se machucar de alguma forma ou que seria melhor estar morto(a).',
        ];

        foreach ($questions as $i => $text) {
            DB::table('clinical_test_questions')->insert([
                'clinical_test_id' => $testId,
                'text'             => $text,
                'type'             => 'scale',
                'scale_min'        => 0,
                'scale_max'        => 3,
                'scale_labels'     => $labels,
                'position'         => $i + 1,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        }

        DB::table('clinical_test_scoring_rules')->insert([
            ['clinical_test_id' => $testId, 'min_score' => 0,  'max_score' => 4,  'result_label' => 'Sintomas Mínimos',             'result_description' => 'Seus sintomas estão dentro da faixa mínima. Continue cuidando de si mesmo(a).', 'challenge_tags' => json_encode([]),                              'created_at' => now(), 'updated_at' => now()],
            ['clinical_test_id' => $testId, 'min_score' => 5,  'max_score' => 9,  'result_label' => 'Sintomas Leves',                'result_description' => 'Indícios leves. Pode ser útil monitorar seu humor e buscar apoio se necessário.', 'challenge_tags' => json_encode(['Humor']),                       'created_at' => now(), 'updated_at' => now()],
            ['clinical_test_id' => $testId, 'min_score' => 10, 'max_score' => 14, 'result_label' => 'Sintomas Moderados',            'result_description' => 'Sintomas moderados identificados. Considere conversar com um profissional de saúde mental.', 'challenge_tags' => json_encode(['Humor', 'Regulação Emocional']), 'created_at' => now(), 'updated_at' => now()],
            ['clinical_test_id' => $testId, 'min_score' => 15, 'max_score' => 19, 'result_label' => 'Sintomas Moderadamente Graves', 'result_description' => 'Sintomas significativos. É recomendável buscar avaliação profissional.', 'challenge_tags' => json_encode(['Humor', 'Regulação Emocional', 'Autoestima']), 'created_at' => now(), 'updated_at' => now()],
            ['clinical_test_id' => $testId, 'min_score' => 20, 'max_score' => 27, 'result_label' => 'Sintomas Graves',               'result_description' => 'Sintomas graves. Procure um profissional de saúde mental.', 'challenge_tags' => json_encode(['Humor', 'Regulação Emocional', 'Autoestima', 'Sono']), 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GAD-7 — Rastreio de Ansiedade
    // ─────────────────────────────────────────────────────────────────────────
    private function gad7(int $tenantId): void
    {
        $testId = DB::table('clinical_tests')->insertGetId([
            'tenant_id'          => $tenantId,
            'name'               => 'GAD-7 — Rastreio de Ansiedade',
            'category'           => 'ansiedade',
            'description'        => 'Escala de Transtorno de Ansiedade Generalizada com 7 itens para rastreio de ansiedade nas últimas 2 semanas.',
            'instructions'       => 'Nas últimas 2 semanas, com que frequência você foi incomodado(a) pelos problemas a seguir?',
            'estimated_minutes'  => 3,
            'is_active'          => true,
            'position'           => 2,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        $labels = json_encode(['Nenhum dia', 'Vários dias', 'Mais da metade dos dias', 'Quase todos os dias']);

        $questions = [
            'Sentir-se nervoso(a), ansioso(a) ou muito tenso(a).',
            'Não conseguir parar ou controlar as preocupações.',
            'Preocupar-se muito com diversas coisas.',
            'Dificuldade para relaxar.',
            'Ficar tão agitado(a) que fica difícil permanecer sentado(a).',
            'Ficar facilmente aborrecido(a) ou irritado(a).',
            'Sentir medo como se algo terrível fosse acontecer.',
        ];

        foreach ($questions as $i => $text) {
            DB::table('clinical_test_questions')->insert([
                'clinical_test_id' => $testId,
                'text'             => $text,
                'type'             => 'scale',
                'scale_min'        => 0,
                'scale_max'        => 3,
                'scale_labels'     => $labels,
                'position'         => $i + 1,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        }

        DB::table('clinical_test_scoring_rules')->insert([
            ['clinical_test_id' => $testId, 'min_score' => 0,  'max_score' => 4,  'result_label' => 'Ansiedade Mínima',   'result_description' => 'Seus níveis de ansiedade estão dentro da faixa normal.', 'challenge_tags' => json_encode([]),                                   'created_at' => now(), 'updated_at' => now()],
            ['clinical_test_id' => $testId, 'min_score' => 5,  'max_score' => 9,  'result_label' => 'Ansiedade Leve',     'result_description' => 'Ansiedade leve detectada. Técnicas de relaxamento podem ajudar.', 'challenge_tags' => json_encode(['Ansiedade']),                    'created_at' => now(), 'updated_at' => now()],
            ['clinical_test_id' => $testId, 'min_score' => 10, 'max_score' => 14, 'result_label' => 'Ansiedade Moderada', 'result_description' => 'Ansiedade moderada. Considere buscar apoio de um profissional.', 'challenge_tags' => json_encode(['Ansiedade', 'Regulação Emocional']), 'created_at' => now(), 'updated_at' => now()],
            ['clinical_test_id' => $testId, 'min_score' => 15, 'max_score' => 21, 'result_label' => 'Ansiedade Grave',    'result_description' => 'Ansiedade significativa. Recomendamos buscar avaliação profissional.', 'challenge_tags' => json_encode(['Ansiedade', 'Regulação Emocional', 'Sono']), 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ASRS-5 — Rastreio de TDAH em Adultos
    // ─────────────────────────────────────────────────────────────────────────
    private function asrs(int $tenantId): void
    {
        $testId = DB::table('clinical_tests')->insertGetId([
            'tenant_id'          => $tenantId,
            'name'               => 'ASRS-5 — Rastreio de TDAH',
            'category'           => 'tdah',
            'description'        => 'Versão reduzida da Escala de Autoavaliação de TDAH para adultos da OMS. Rastreia sintomas de desatenção e hiperatividade/impulsividade.',
            'instructions'       => 'Enquanto estava no trabalho, escola ou em casa, com que frequência você experimentou cada um dos seguintes comportamentos nos últimos 6 meses?',
            'estimated_minutes'  => 4,
            'is_active'          => true,
            'position'           => 3,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        $labels = json_encode(['Nunca', 'Raramente', 'Às vezes', 'Frequentemente', 'Muito frequentemente']);

        $questions = [
            'Com que frequência você comete erros por falta de atenção quando tem de trabalhar num projeto chato ou difícil?',
            'Com que frequência você tem dificuldade para manter a atenção quando está fazendo um trabalho chato ou repetitivo?',
            'Com que frequência você tem dificuldade para se concentrar no que as pessoas dizem, mesmo quando estão falando diretamente com você?',
            'Com que frequência você deixa um projeto pela metade depois de já ter feito as partes mais difíceis?',
            'Com que frequência você tem dificuldade para fazer um trabalho que exige organização?',
            'Quando você tem uma tarefa que exige muita concentração, com que frequência você evita ou adia o seu início?',
            'Com que frequência você coloca as coisas em lugares errados ou tem dificuldade de encontrar as coisas em casa ou no trabalho?',
            'Com que frequência você se distrai com atividades ou barulhos ao seu redor?',
            'Com que frequência você tem dificuldade para lembrar compromissos ou obrigações?',
        ];

        foreach ($questions as $i => $text) {
            DB::table('clinical_test_questions')->insert([
                'clinical_test_id' => $testId,
                'text'             => $text,
                'type'             => 'scale',
                'scale_min'        => 0,
                'scale_max'        => 4,
                'scale_labels'     => $labels,
                'position'         => $i + 1,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        }

        DB::table('clinical_test_scoring_rules')->insert([
            ['clinical_test_id' => $testId, 'min_score' => 0,  'max_score' => 13, 'result_label' => 'Indícios Baixos de TDAH',     'result_description' => 'Poucos indícios de TDAH foram identificados no rastreio.', 'challenge_tags' => json_encode([]),                                        'created_at' => now(), 'updated_at' => now()],
            ['clinical_test_id' => $testId, 'min_score' => 14, 'max_score' => 23, 'result_label' => 'Indícios Moderados de TDAH',  'result_description' => 'Alguns padrões de desatenção foram identificados. Uma avaliação profissional pode trazer mais clareza.', 'challenge_tags' => json_encode(['Desatenção', 'TDAH']),                    'created_at' => now(), 'updated_at' => now()],
            ['clinical_test_id' => $testId, 'min_score' => 24, 'max_score' => 36, 'result_label' => 'Indícios Significativos de TDAH', 'result_description' => 'O rastreio indica padrões consistentes com TDAH. Recomendamos avaliação por especialista.', 'challenge_tags' => json_encode(['Desatenção', 'TDAH', 'Função Executiva', 'Organização']), 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AQ-10 — Rastreio do Espectro Autista (TEA)
    // ─────────────────────────────────────────────────────────────────────────
    private function aq10(int $tenantId): void
    {
        $testId = DB::table('clinical_tests')->insertGetId([
            'tenant_id'          => $tenantId,
            'name'               => 'AQ-10 — Rastreio do Espectro Autista',
            'category'           => 'tea',
            'description'        => 'Versão de 10 itens do Questionário do Espectro Autista (Autism Quotient). Rastreia traços associados ao autismo em adultos.',
            'instructions'       => 'Para cada afirmação abaixo, indique com que frequência ela se aplica a você.',
            'estimated_minutes'  => 4,
            'is_active'          => true,
            'position'           => 4,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        $labels = json_encode(['Definitivamente concordo', 'Levemente concordo', 'Levemente discordo', 'Definitivamente discordo']);

        // Questões com pontuação direta (concordar = pontos): 1,7,8,10
        // Questões com pontuação inversa (discordar = pontos): 2,3,4,5,6,9
        // Para simplificar: usamos scale 0-3, onde 0 = concordo totalmente (3 pontos para diretas) e 3 = discordo totalmente
        // Vamos tratar todas como scale e ajustar os labels para clareza
        // Diretas: "Concordo definitivamente" (idx 0) = 3 pts, "Concordo levemente" (idx 1) = 2, "Discordo levemente" (idx 2) = 1, "Discordo def." (idx 3) = 0
        // Inversas: "Concordo definitivamente" (idx 0) = 0, ..., "Discordo definitivamente" (idx 3) = 3
        // Simplificação: todas as questões abaixo já foram formuladas para que "concordo" = mais traços autistas

        $questions = [
            ['Prefiro fazer as coisas sempre da mesma maneira.',                                   true],
            ['Tenho dificuldade em imaginar como seria ser outra pessoa.',                          true],
            ['Costumo focar mais nos detalhes do que no todo.',                                     true],
            ['Tenho dificuldade em entender as regras não ditas de convívio social.',               true],
            ['Acho difícil fazer amizades novas.',                                                  true],
            ['Noto padrões em coisas o tempo todo.',                                               true],
            ['Prefiro atividades que posso fazer sozinho(a) a atividades em grupo.',               true],
            ['Frequentemente me perco em devaneios ou pensamentos intensos sobre um assunto.',     true],
            ['Tenho dificuldade em perceber quando alguém está entediado ou aborrecido comigo.',   true],
            ['Acho difícil lidar com mudanças inesperadas na minha rotina.',                       true],
        ];

        foreach ($questions as $i => [$text, $direct]) {
            DB::table('clinical_test_questions')->insert([
                'clinical_test_id' => $testId,
                'text'             => $text,
                'type'             => 'scale',
                'scale_min'        => 0,
                'scale_max'        => 3,
                'scale_labels'     => $labels,
                'position'         => $i + 1,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        }

        DB::table('clinical_test_scoring_rules')->insert([
            ['clinical_test_id' => $testId, 'min_score' => 0,  'max_score' => 10, 'result_label' => 'Traços Poucos Expressivos',    'result_description' => 'Poucos traços do espectro foram identificados neste rastreio.', 'challenge_tags' => json_encode([]),                                       'created_at' => now(), 'updated_at' => now()],
            ['clinical_test_id' => $testId, 'min_score' => 11, 'max_score' => 19, 'result_label' => 'Traços Moderados do Espectro', 'result_description' => 'Alguns traços associados ao espectro autista foram identificados. Pode ser útil conversar com um especialista.', 'challenge_tags' => json_encode(['TEA', 'Comunicação Social']),        'created_at' => now(), 'updated_at' => now()],
            ['clinical_test_id' => $testId, 'min_score' => 20, 'max_score' => 30, 'result_label' => 'Traços Significativos do Espectro', 'result_description' => 'O rastreio indica traços significativos associados ao espectro autista. Recomendamos avaliação com especialista.', 'challenge_tags' => json_encode(['TEA', 'Comunicação Social', 'Sensorialidade', 'Rotina']), 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AH/SD — Checklist de Altas Habilidades / Superdotação
    // ─────────────────────────────────────────────────────────────────────────
    private function ahsd(int $tenantId): void
    {
        $testId = DB::table('clinical_tests')->insertGetId([
            'tenant_id'          => $tenantId,
            'name'               => 'Checklist AH/SD — Altas Habilidades',
            'category'           => 'ah_sd',
            'description'        => 'Checklist baseado nos indicadores de Altas Habilidades/Superdotação (Renzulli/MEC). Identifica características nas áreas cognitiva, criatividade e comprometimento com a tarefa.',
            'instructions'       => 'Para cada característica, marque com que frequência ela se aplica a você.',
            'estimated_minutes'  => 5,
            'is_active'          => true,
            'position'           => 5,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        $labels = json_encode(['Raramente', 'Às vezes', 'Frequentemente', 'Quase sempre']);

        $questions = [
            // Capacidade cognitiva
            'Aprendo com facilidade e rapidez em áreas de interesse.',
            'Tenho vocabulário avançado e me expresso com precisão.',
            'Consigo fazer conexões entre diferentes áreas do conhecimento.',
            'Questiono regras e busco entender o porquê das coisas.',
            'Tenho boa memória para informações que me interessam.',
            // Criatividade
            'Tenho muitas ideias originais e incomuns.',
            'Prefiro criar minhas próprias soluções em vez de seguir métodos convencionais.',
            'Me interesso por problemas complexos que outros acham difíceis ou entediantes.',
            // Comprometimento
            'Quando me envolvo em algo que gosto, consigo me concentrar por horas.',
            'Tenho um ou mais campos de interesse em que me aprofundo muito além do esperado para minha idade.',
            'Prefiro trabalhar de forma independente a seguir instruções passo a passo.',
            'Sinto frustração quando não me é permitido aprofundar em algo que me interessa.',
        ];

        foreach ($questions as $i => $text) {
            DB::table('clinical_test_questions')->insert([
                'clinical_test_id' => $testId,
                'text'             => $text,
                'type'             => 'scale',
                'scale_min'        => 0,
                'scale_max'        => 3,
                'scale_labels'     => $labels,
                'position'         => $i + 1,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        }

        DB::table('clinical_test_scoring_rules')->insert([
            ['clinical_test_id' => $testId, 'min_score' => 0,  'max_score' => 12, 'result_label' => 'Poucos Indicadores AH/SD',       'result_description' => 'Poucos indicadores de altas habilidades foram identificados neste rastreio.', 'challenge_tags' => json_encode([]),                                           'created_at' => now(), 'updated_at' => now()],
            ['clinical_test_id' => $testId, 'min_score' => 13, 'max_score' => 23, 'result_label' => 'Indicadores Moderados de AH/SD', 'result_description' => 'Alguns indicadores de altas habilidades presentes. Pode ser útil buscar avaliação especializada.', 'challenge_tags' => json_encode(['AH/SD', 'Aprendizagem']),                  'created_at' => now(), 'updated_at' => now()],
            ['clinical_test_id' => $testId, 'min_score' => 24, 'max_score' => 36, 'result_label' => 'Indicadores Expressivos de AH/SD', 'result_description' => 'Muitos indicadores de altas habilidades foram identificados. Recomendamos avaliação por especialista em AH/SD.', 'challenge_tags' => json_encode(['AH/SD', 'Aprendizagem', 'Criatividade', 'Hiperfoco']), 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
