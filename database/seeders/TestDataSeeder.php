<?php

namespace Database\Seeders;

use App\Models\Journey;
use App\Models\MemberLesson;
use App\Models\MemberLessonProgress;
use App\Models\MemberModule;
use App\Models\MemberQuizResponse;
use App\Models\MemberSection;
use App\Models\Product;
use App\Models\User;
use App\Models\UserJourneyUnlock;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId  = 1;
        $adminId   = 1;
        $studentId = 2; // rafaeloficialpaixao@gmail.com

        // ── Produto ──────────────────────────────────────────────────────────────
        $productId = (string) Str::uuid();

        Product::create([
            'id'               => $productId,
            'tenant_id'        => $tenantId,
            'name'             => 'Neurociência Aplicada ao TDAH',
            'slug'             => 'neurociencia-aplicada-ao-tdah',
            'checkout_slug'    => 'neuro-tdah',
            'description'      => 'Um curso completo sobre TDAH no adulto: fundamentos, autoconhecimento e estratégias práticas de regulação neurofuncional.',
            'type'             => 'area_membros',
            'billing_type'     => 'one_time',
            'price'            => 297.00,
            'currency'         => 'BRL',
            'is_active'        => true,
            'member_area_config' => [
                'primary_color' => '#7c3aed',
                'show_progress' => true,
            ],
            'ai_context' => [
                'instructions' => implode("\n\n", [
                    'Este curso é voltado para adultos com suspeita ou diagnóstico de TDAH (Transtorno de Déficit de Atenção e Hiperatividade).',
                    'O público típico apresenta dificuldades de foco prolongado, impulsividade, procrastinação seletiva e dysregulação emocional.',
                    'Ao gerar relatórios, priorize os domínios RDoC de Cognição (funções executivas, atenção, memória de trabalho) e Regulação/Arousal.',
                    'Identifique padrões de gap entre Potencial e Expressão (modelo PEI): o aluno frequentemente compreende muito mas sustenta pouco.',
                    'Valorize especialmente as respostas dos questionários de autoavaliação e estratégias como dados primários de funcionamento.',
                    'Evite linguagem diagnóstica fechada. Use "padrão compatível com" ou "sinais de" ao descrever fenômenos de TDAH.',
                    'O relatório gerado para este curso deve enfatizar pontos fortes observados (hiperfoco, criatividade, pensamento não-linear) além das dificuldades.',
                ]),
                'files'    => [],
                'jornadas' => [
                    ['journey_id' => 1, 'is_free' => true],
                ],
            ],
        ]);

        // Matricular estudante no produto
        DB::table('product_user')->insertOrIgnore([
            'product_id' => $productId,
            'user_id'    => $studentId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // ── Seção 1 ──────────────────────────────────────────────────────────────
        $sec1 = MemberSection::create([
            'product_id' => $productId,
            'title'      => 'Fundamentos do TDAH',
            'position'   => 1,
        ]);

        // Módulo 1.1 — Introdução
        $mod1 = MemberModule::create([
            'member_section_id' => $sec1->id,
            'product_id'        => $productId,
            'title'             => 'Introdução ao TDAH',
            'position'          => 1,
        ]);

        $lesson1 = MemberLesson::create([
            'member_module_id' => $mod1->id,
            'product_id'       => $productId,
            'title'            => 'O que é o TDAH? — Visão Neurobiológica',
            'type'             => 'video',
            'content_url'      => 'https://www.youtube.com/watch?v=CRgHGs9_5h0',
            'duration_seconds' => 900,
            'position'         => 1,
            'is_free'          => true,
        ]);

        $lesson2 = MemberLesson::create([
            'member_module_id' => $mod1->id,
            'product_id'       => $productId,
            'title'            => 'TDAH no Adulto: Como se Manifesta',
            'type'             => 'video',
            'content_url'      => 'https://www.youtube.com/watch?v=ouZrZa5pLXk',
            'duration_seconds' => 1200,
            'position'         => 2,
        ]);

        $lesson3 = MemberLesson::create([
            'member_module_id' => $mod1->id,
            'product_id'       => $productId,
            'title'            => 'Mitos e Verdades sobre o TDAH',
            'type'             => 'video',
            'content_url'      => 'https://www.youtube.com/watch?v=xMWtGozn5jU',
            'duration_seconds' => 780,
            'position'         => 3,
        ]);

        // Módulo 1.2 — Autoconhecimento
        $mod2 = MemberModule::create([
            'member_section_id' => $sec1->id,
            'product_id'        => $productId,
            'title'             => 'Autoconhecimento e Mapeamento',
            'position'          => 2,
        ]);

        $lesson4 = MemberLesson::create([
            'member_module_id' => $mod2->id,
            'product_id'       => $productId,
            'title'            => 'Como o TDAH Afeta Meu Dia a Dia',
            'type'             => 'video',
            'content_url'      => 'https://www.youtube.com/watch?v=HyoePV6PFiM',
            'duration_seconds' => 1050,
            'position'         => 1,
        ]);

        $lesson5 = MemberLesson::create([
            'member_module_id' => $mod2->id,
            'product_id'       => $productId,
            'title'            => 'Questionário de Autoavaliação TDAH',
            'type'             => 'quiz',
            'position'         => 2,
            'content_files'    => [
                'questions' => [
                    ['id' => 'q1', 'text' => 'Com que frequência você tem dificuldade para manter o foco em tarefas longas ou repetitivas?'],
                    ['id' => 'q2', 'text' => 'Você costuma procrastinar mesmo tarefas que considera importantes?'],
                    ['id' => 'q3', 'text' => 'Com que frequência perde objetos cotidianos (chaves, celular, documentos)?'],
                    ['id' => 'q4', 'text' => 'Como você se sente ao precisar esperar sua vez em situações sociais ou profissionais?'],
                    ['id' => 'q5', 'text' => 'Descreva como funciona sua atenção: quando você consegue focar com facilidade e quando é mais difícil?'],
                    ['id' => 'q6', 'text' => 'Você já foi diagnosticado(a) com TDAH, suspeita de ter, ou está investigando?'],
                ],
            ],
        ]);

        // ── Seção 2 ──────────────────────────────────────────────────────────────
        $sec2 = MemberSection::create([
            'product_id' => $productId,
            'title'      => 'Estratégias e Ferramentas',
            'position'   => 2,
        ]);

        // Módulo 2.1 — Regulação Emocional
        $mod3 = MemberModule::create([
            'member_section_id' => $sec2->id,
            'product_id'        => $productId,
            'title'             => 'Regulação Emocional no TDAH',
            'position'          => 1,
        ]);

        $lesson6 = MemberLesson::create([
            'member_module_id' => $mod3->id,
            'product_id'       => $productId,
            'title'            => 'Dysregulação Emocional: Por que acontece e como lidar',
            'type'             => 'video',
            'content_url'      => 'https://www.youtube.com/watch?v=K9G3BH6sApc',
            'duration_seconds' => 1380,
            'position'         => 1,
        ]);

        $lesson7 = MemberLesson::create([
            'member_module_id' => $mod3->id,
            'product_id'       => $productId,
            'title'            => 'Técnicas de Regulação para o Dia a Dia',
            'type'             => 'video',
            'content_url'      => 'https://www.youtube.com/watch?v=2X9KhQ8JnMc',
            'duration_seconds' => 960,
            'position'         => 2,
        ]);

        $lesson8 = MemberLesson::create([
            'member_module_id' => $mod3->id,
            'product_id'       => $productId,
            'title'            => 'Avaliação de Estratégias de Regulação',
            'type'             => 'quiz',
            'position'         => 3,
            'content_files'    => [
                'questions' => [
                    ['id' => 'r1', 'text' => 'Você já utiliza alguma estratégia de regulação emocional? Se sim, quais?'],
                    ['id' => 'r2', 'text' => 'Qual situação do dia a dia mais te desregula emocionalmente?'],
                    ['id' => 'r3', 'text' => 'Em uma escala de 1 a 5, qual seu nível de impulsividade nas respostas emocionais? (1=muito baixo, 5=muito alto)'],
                    ['id' => 'r4', 'text' => 'Você consegue identificar os sinais físicos de que está se desregulando (coração acelerado, tensão, etc.)?'],
                    ['id' => 'r5', 'text' => 'Após praticar as técnicas do módulo, o que mudou na sua percepção de regulação emocional?'],
                ],
            ],
        ]);

        // ── Respostas de Quiz do estudante ───────────────────────────────────────
        MemberQuizResponse::create([
            'lesson_id'  => $lesson5->id,
            'user_id'    => $studentId,
            'product_id' => $productId,
            'responses'  => [
                ['question_id' => 'q1', 'value' => 'Quase sempre — é muito difícil manter foco por mais de 20 minutos em qualquer tarefa que não seja altamente estimulante', 'comment' => ''],
                ['question_id' => 'q2', 'value' => 'Sim, frequentemente. Deixo tarefas importantes para o último momento e isso me gera muita angústia', 'comment' => 'Mesmo sabendo as consequências, é como se meu cérebro resistisse ativamente'],
                ['question_id' => 'q3', 'value' => 'Pelo menos 3 vezes por semana perco algo', 'comment' => ''],
                ['question_id' => 'q4', 'value' => 'Extremamente desconfortável — a espera me gera irritação intensa e pensamentos acelerados', 'comment' => ''],
                ['question_id' => 'q5', 'value' => 'Consigo focar muito bem em coisas novas, desafiantes ou de interesse genuíno. Em tarefas rotineiras ou burocráticas é quase impossível. Às vezes entro em hiperfoco e perco a noção do tempo completamente.', 'comment' => ''],
                ['question_id' => 'q6', 'value' => 'Tenho suspeita forte e estou em investigação com psiquiatra. Já usei Ritalina por 2 meses com melhora significativa.', 'comment' => ''],
            ],
        ]);

        MemberQuizResponse::create([
            'lesson_id'  => $lesson8->id,
            'user_id'    => $studentId,
            'product_id' => $productId,
            'responses'  => [
                ['question_id' => 'r1', 'value' => 'Sim. Uso box breathing quando percebo agitação, e timer Pomodoro (com resultados inconsistentes). Também uso fones com ruído branco para criar um ambiente mais controlado.', 'comment' => ''],
                ['question_id' => 'r2', 'value' => 'Interrupções inesperadas durante foco, críticas que parecem injustas e situações onde preciso esperar sem ter controle do processo', 'comment' => 'A rejeição é especialmente difícil — qualquer sinal de desaprovação me desregula por horas'],
                ['question_id' => 'r3', 'value' => '4', 'comment' => 'Minhas reações emocionais são intensas e rápidas — o problema não é sentir, é a intensidade e a dificuldade de modular'],
                ['question_id' => 'r4', 'value' => 'Parcialmente. Reconheço tensão na mandíbula e agitação nas pernas. Mas frequentemente só percebo depois que já reagi.', 'comment' => ''],
                ['question_id' => 'r5', 'value' => 'Comecei a nomear o que sinto antes de agir — isso ajudou um pouco. Ainda tenho dificuldade com a consistência de praticar as técnicas quando estou muito ativado.', 'comment' => ''],
            ],
        ]);

        // ── Progresso de aulas do estudante ──────────────────────────────────────
        $completedLessons = [$lesson1->id, $lesson2->id, $lesson3->id, $lesson4->id, $lesson5->id, $lesson6->id, $lesson7->id, $lesson8->id];
        foreach ($completedLessons as $lessonId) {
            MemberLessonProgress::firstOrCreate(
                ['user_id' => $studentId, 'member_lesson_id' => $lessonId],
                [
                    'product_id'       => $productId,
                    'completed_at'     => now()->subDays(rand(1, 14)),
                    'progress_percent' => 100,
                ]
            );
        }

        // ── Jornada ───────────────────────────────────────────────────────────────
        $journey = Journey::create([
            'tenant_id'   => $tenantId,
            'name'        => 'Jornada Neurofuncional Avançada',
            'slug'        => 'jornada-neurofuncional-avancada',
            'description' => 'Um caminho estruturado para quem já passou pelo Módulo Base e quer aprofundar a compreensão do seu funcionamento neurofuncional com suporte especializado.',
            'is_active'   => true,
            'position'    => 1,
            'product_ids' => [$productId],
        ]);

        // ── Desbloqueio da jornada para o estudante (simulando recomendação IA) ──
        UserJourneyUnlock::firstOrCreate(
            ['user_id' => $studentId, 'journey_id' => $journey->id],
            [
                'tenant_id'     => $tenantId,
                'product_id'    => $productId,
                'is_free'       => true,
                'ai_insight_id' => null,
            ]
        );

        // Atualizar ai_context do produto com o journey_id real
        $product = Product::find($productId);
        $ctx = $product->ai_context;
        $ctx['jornadas'] = [['journey_id' => $journey->id, 'is_free' => true]];
        $product->update(['ai_context' => $ctx]);

        $this->command->info('✓ Produto criado: ' . $productId);
        $this->command->info('✓ 2 seções, 3 módulos, 8 aulas (6 vídeo + 2 quiz)');
        $this->command->info('✓ Quiz respondido pelo aluno id=' . $studentId);
        $this->command->info('✓ 8 aulas marcadas como concluídas');
        $this->command->info('✓ Jornada id=' . $journey->id . ' criada e desbloqueada para o aluno');
    }
}
