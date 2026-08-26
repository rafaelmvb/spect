<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\MemberSection;
use App\Models\MemberModule;
use App\Models\MemberLesson;
use Illuminate\Support\Facades\DB;

class ProdutosSeeder extends Seeder
{
    private int $tenantId = 1;

    public function run(): void
    {
        // 1. Limpar quiz responses (não tem cascade automático)
        $prodIds = Product::where('tenant_id', $this->tenantId)->pluck('id');
        DB::table('member_quiz_responses')->whereIn('product_id', $prodIds)->delete();
        DB::table('ai_insights')->where('tenant_id', $this->tenantId)->delete();
        DB::table('member_report_requests')->where('tenant_id', $this->tenantId)->delete();
        DB::table('user_journey_unlocks')->where('tenant_id', $this->tenantId)->delete();

        // 2. Deletar produtos — FK cascades limpam sections/modules/lessons/orders
        Product::where('tenant_id', $this->tenantId)->delete();

        // 3. Criar os 3 produtos
        $this->produto1();
        $this->produto2();
        $this->produto3();
    }

    // ─────────────────────────────────────────────────────────────
    // PRODUTO 1: Rastreio & Autoconhecimento TDAH
    // ─────────────────────────────────────────────────────────────
    private function produto1(): void
    {
        $produto = Product::create([
            'tenant_id'        => $this->tenantId,
            'name'             => 'Rastreio & Autoconhecimento TDAH',
            'slug'             => 'rastreio-autoconhecimento-tdah',
            'checkout_slug'    => 'rastreio-tdah',
            'description'      => 'Programa completo de autoavaliação neuropsicológica para adultos com TDAH. Mapeie seu perfil de atenção, funções executivas e padrões comportamentais com instrumentos validados e orientação especializada.',
            'type'             => Product::TYPE_AREA_MEMBROS,
            'billing_type'     => Product::BILLING_ONE_TIME,
            'price'            => '297.00',
            'currency'         => 'BRL',
            'is_active'        => true,
            'member_area_config' => $this->memberConfig('#6366F1', 'Rastreio TDAH'),
            'ai_context' => [
                'instructions' => "Você é um especialista em neuropsicologia clínica com foco em TDAH em adultos. Ao analisar os dados deste aluno, considere:\n\n1. **Perfil de Atenção**: avalie os padrões de atenção sustentada, seletiva e alternada com base nas respostas dos quizzes.\n2. **Funções Executivas**: identifique pontos de dificuldade em planejamento, memória de trabalho, flexibilidade cognitiva e inibição de resposta.\n3. **Impacto Funcional**: relacione os dados às áreas de vida afetadas (trabalho, relacionamentos, autocuidado).\n4. **Tom**: empático, não-diagnóstico, focado em estratégias práticas. Nunca afirme diagnóstico, apenas aponte padrões.\n5. **Recomendações**: ao final, sugira 3 a 5 ações concretas adaptadas ao perfil individual do aluno.\n\nEvite linguagem técnica excessiva. Prefira exemplos do cotidiano. O relatório deve ter no máximo 600 palavras.",
                'jornadas' => [
                    ['journey_id' => 9, 'is_free' => false],
                    ['journey_id' => 11, 'is_free' => false],
                ],
            ],
        ]);

        // SEÇÃO 1: Fundamentos do TDAH
        $sec1 = MemberSection::create([
            'product_id'   => $produto->id,
            'title'        => 'Fundamentos do TDAH',
            'position'     => 1,
            'section_type' => 'trilha_aulas',
        ]);

        // Módulo 1.1: Neurobiologia
        $mod = MemberModule::create([
            'product_id'       => $produto->id,
            'member_section_id' => $sec1->id,
            'title'            => 'Neurobiologia do TDAH',
            'position'         => 1,
        ]);

        MemberLesson::create([
            'product_id'      => $produto->id,
            'member_module_id' => $mod->id,
            'title'           => 'Como o Cérebro TDAH Processa Informação',
            'position'        => 1,
            'type'            => 'quiz',
            'content_files'   => ['questions' => [
                ['id' => 'q1a', 'type' => 'scale', 'text' => 'Com que frequência você sente que sua mente "salta" de um pensamento para outro sem controle?', 'scale_min' => 1, 'scale_max' => 5, 'scale_min_label' => 'Raramente', 'scale_max_label' => 'Quase sempre', 'options' => [], 'image_url' => '', 'video_url' => '', 'button_label' => '', 'button_url' => '', 'comment_enabled' => false],
                ['id' => 'q1b', 'type' => 'boolean', 'text' => 'Você consegue manter o foco por mais de 30 minutos em tarefas que não te interessam diretamente?', 'options' => [], 'image_url' => '', 'video_url' => '', 'button_label' => '', 'button_url' => '', 'comment_enabled' => false],
                ['id' => 'q1c', 'type' => 'single', 'text' => 'Qual situação melhor descreve sua relação com o foco?', 'options' => [
                    ['id' => 'q1c1', 'text' => 'Consigo focar em qualquer tarefa com esforço suficiente'],
                    ['id' => 'q1c2', 'text' => 'Entro em hiperfoco quando o assunto me interessa, mas perco totalmente o foco no resto'],
                    ['id' => 'q1c3', 'text' => 'Tenho dificuldade de foco na maioria das situações'],
                    ['id' => 'q1c4', 'text' => 'Meu foco é imprevisível — varia muito sem razão aparente'],
                ], 'image_url' => '', 'video_url' => '', 'button_label' => '', 'button_url' => '', 'comment_enabled' => false],
                ['id' => 'q1d', 'type' => 'multi', 'text' => 'Selecione os desafios que você vivencia com frequência no dia a dia:', 'options' => [
                    ['id' => 'q1d1', 'text' => 'Esqueço tarefas ou compromissos importantes'],
                    ['id' => 'q1d2', 'text' => 'Tenho dificuldade para começar tarefas, mesmo urgentes'],
                    ['id' => 'q1d3', 'text' => 'Perco objetos com frequência (chaves, celular, documentos)'],
                    ['id' => 'q1d4', 'text' => 'Interrupo outros sem perceber durante conversas'],
                    ['id' => 'q1d5', 'text' => 'Procrastino mesmo sabendo das consequências'],
                    ['id' => 'q1d6', 'text' => 'Fico entediado rapidamente em atividades repetitivas'],
                ], 'image_url' => '', 'video_url' => '', 'button_label' => '', 'button_url' => '', 'comment_enabled' => false],
            ]],
        ]);

        MemberLesson::create([
            'product_id'      => $produto->id,
            'member_module_id' => $mod->id,
            'title'           => 'Dopamina, Motivação e o Cérebro TDAH',
            'position'        => 2,
            'type'            => 'quiz',
            'content_files'   => ['questions' => [
                ['id' => 'q2a', 'type' => 'scale', 'text' => 'Em que medida sua motivação depende de prazo, pressão ou novidade para aparecer?', 'scale_min' => 0, 'scale_max' => 10, 'scale_min_label' => 'Não depende', 'scale_max_label' => 'Depende totalmente', 'options' => [], 'image_url' => '', 'video_url' => '', 'button_label' => '', 'button_url' => '', 'comment_enabled' => true],
                ['id' => 'q2b', 'type' => 'boolean', 'text' => 'Você percebe que sua produtividade aumenta significativamente quando está próximo do prazo ("modo pânico")?', 'options' => [], 'image_url' => '', 'video_url' => '', 'button_label' => '', 'button_url' => '', 'comment_enabled' => false],
                ['id' => 'q2c', 'type' => 'text', 'text' => 'Descreva em suas palavras o que acontece com seu nível de energia e motivação ao longo de um dia típico. Quando você se sente mais produtivo e quando sente mais resistência?', 'options' => [], 'image_url' => '', 'video_url' => '', 'button_label' => 'Ver dicas sobre ritmos circadianos', 'button_url' => '', 'comment_enabled' => false],
            ]],
        ]);

        // Módulo 1.2: Diagnóstico
        $mod2 = MemberModule::create([
            'product_id'       => $produto->id,
            'member_section_id' => $sec1->id,
            'title'            => 'Diagnóstico e Identificação',
            'position'         => 2,
        ]);

        MemberLesson::create([
            'product_id'      => $produto->id,
            'member_module_id' => $mod2->id,
            'title'           => 'Critérios e Comorbidades do TDAH',
            'position'        => 1,
            'type'            => 'quiz',
            'content_files'   => ['questions' => [
                ['id' => 'q3a', 'type' => 'boolean', 'text' => 'Você já recebeu diagnóstico formal de TDAH por um profissional de saúde?', 'options' => [], 'image_url' => '', 'video_url' => '', 'button_label' => '', 'button_url' => '', 'comment_enabled' => false],
                ['id' => 'q3b', 'type' => 'multi', 'text' => 'Quais condições abaixo você já teve diagnóstico ou suspeita diagnóstica?', 'options' => [
                    ['id' => 'q3b1', 'text' => 'Ansiedade (generalizada, social ou outros tipos)'],
                    ['id' => 'q3b2', 'text' => 'Depressão ou transtorno de humor'],
                    ['id' => 'q3b3', 'text' => 'Dificuldades de aprendizagem (dislexia, disgrafia, etc.)'],
                    ['id' => 'q3b4', 'text' => 'Transtorno do espectro autista (TEA)'],
                    ['id' => 'q3b5', 'text' => 'Transtorno de sono (insônia, apneia, etc.)'],
                    ['id' => 'q3b6', 'text' => 'Nenhuma das anteriores'],
                ], 'image_url' => '', 'video_url' => '', 'button_label' => '', 'button_url' => '', 'comment_enabled' => false],
                ['id' => 'q3c', 'type' => 'single', 'text' => 'Qual foi a principal área de vida mais afetada pelo TDAH na sua experiência?', 'options' => [
                    ['id' => 'q3c1', 'text' => 'Desempenho profissional e carreira'],
                    ['id' => 'q3c2', 'text' => 'Relacionamentos afetivos e sociais'],
                    ['id' => 'q3c3', 'text' => 'Saúde e autocuidado'],
                    ['id' => 'q3c4', 'text' => 'Finanças pessoais e organização'],
                    ['id' => 'q3c5', 'text' => 'Educação e aprendizagem'],
                ], 'image_url' => '', 'video_url' => '', 'button_label' => '', 'button_url' => '', 'comment_enabled' => false],
            ]],
        ]);

        MemberLesson::create([
            'product_id'      => $produto->id,
            'member_module_id' => $mod2->id,
            'title'           => 'Meu Histórico com o TDAH',
            'position'        => 2,
            'type'            => 'quiz',
            'content_files'   => ['questions' => [
                ['id' => 'q4a', 'type' => 'scale', 'text' => 'Como você avalia o impacto do TDAH na sua qualidade de vida atual?', 'scale_min' => 1, 'scale_max' => 7, 'scale_min_label' => 'Impacto mínimo', 'scale_max_label' => 'Impacto severo', 'options' => [], 'image_url' => '', 'video_url' => '', 'button_label' => '', 'button_url' => '', 'comment_enabled' => false],
                ['id' => 'q4b', 'type' => 'text', 'text' => 'Escreva sobre uma situação recente em que o TDAH atrapalhou algo importante para você. O que aconteceu e como você se sentiu?', 'options' => [], 'image_url' => '', 'video_url' => '', 'button_label' => '', 'button_url' => '', 'comment_enabled' => false],
                ['id' => 'q4c', 'type' => 'boolean', 'text' => 'Você já fez ou faz acompanhamento psicológico ou psiquiátrico específico para TDAH?', 'options' => [], 'image_url' => '', 'video_url' => '', 'button_label' => '', 'button_url' => '', 'comment_enabled' => false],
            ]],
        ]);

        // SEÇÃO 2: Funções Executivas
        $sec2 = MemberSection::create([
            'product_id'   => $produto->id,
            'title'        => 'Mapeando Suas Funções Executivas',
            'position'     => 2,
            'section_type' => 'trilha_aulas',
        ]);

        $mod3 = MemberModule::create([
            'product_id'       => $produto->id,
            'member_section_id' => $sec2->id,
            'title'            => 'Planejamento e Organização',
            'position'         => 1,
        ]);

        MemberLesson::create([
            'product_id'      => $produto->id,
            'member_module_id' => $mod3->id,
            'title'           => 'Como Você Planeja (ou Evita Planejar)',
            'position'        => 1,
            'type'            => 'quiz',
            'content_files'   => ['questions' => [
                ['id' => 'q5a', 'type' => 'scale', 'text' => 'Com que frequência você começa projetos com entusiasmo mas não consegue terminá-los?', 'scale_min' => 1, 'scale_max' => 5, 'scale_min_label' => 'Raramente', 'scale_max_label' => 'Sempre', 'options' => [], 'image_url' => '', 'video_url' => '', 'button_label' => '', 'button_url' => '', 'comment_enabled' => false],
                ['id' => 'q5b', 'type' => 'single', 'text' => 'Qual é a sua maior dificuldade na hora de planejar uma tarefa complexa?', 'options' => [
                    ['id' => 'q5b1', 'text' => 'Não sei por onde começar — tudo parece igualmente urgente'],
                    ['id' => 'q5b2', 'text' => 'Começo a planejar mas perco o fio da meada no meio'],
                    ['id' => 'q5b3', 'text' => 'Faço planos detalhados mas raramente os executo'],
                    ['id' => 'q5b4', 'text' => 'Evito planejar e prefiro agir no improviso'],
                ], 'image_url' => '', 'video_url' => '', 'button_label' => '', 'button_url' => '', 'comment_enabled' => false],
                ['id' => 'q5c', 'type' => 'multi', 'text' => 'Quais ferramentas de organização você já tentou usar (mesmo que sem sucesso contínuo)?', 'options' => [
                    ['id' => 'q5c1', 'text' => 'Agenda física ou caderno de tarefas'],
                    ['id' => 'q5c2', 'text' => 'Aplicativos de produtividade (Notion, Todoist, etc.)'],
                    ['id' => 'q5c3', 'text' => 'Calendário digital (Google, Outlook)'],
                    ['id' => 'q5c4', 'text' => 'Post-its e lembretes físicos'],
                    ['id' => 'q5c5', 'text' => 'Alarmes e lembretes no celular'],
                    ['id' => 'q5c6', 'text' => 'Nunca consegui usar nenhuma ferramenta de forma consistente'],
                ], 'image_url' => '', 'video_url' => '', 'button_label' => '', 'button_url' => '', 'comment_enabled' => false],
            ]],
        ]);

        MemberLesson::create([
            'product_id'      => $produto->id,
            'member_module_id' => $mod3->id,
            'title'           => 'Memória de Trabalho e Esquecimento',
            'position'        => 2,
            'type'            => 'quiz',
            'content_files'   => ['questions' => [
                ['id' => 'q6a', 'type' => 'scale', 'text' => 'Com que frequência você esquece o que ia dizer no meio de uma frase ou conversa?', 'scale_min' => 1, 'scale_max' => 5, 'scale_min_label' => 'Raramente', 'scale_max_label' => 'Várias vezes ao dia', 'options' => [], 'image_url' => '', 'video_url' => '', 'button_label' => '', 'button_url' => '', 'comment_enabled' => false],
                ['id' => 'q6b', 'type' => 'boolean', 'text' => 'Você costuma entrar em um cômodo e esquecer o motivo pelo qual foi até lá?', 'options' => [], 'image_url' => '', 'video_url' => '', 'button_label' => '', 'button_url' => '', 'comment_enabled' => false],
                ['id' => 'q6c', 'type' => 'text', 'text' => 'Descreva uma situação onde o esquecimento ou falha de memória de trabalho causou um problema sério para você recentemente.', 'options' => [], 'image_url' => '', 'video_url' => '', 'button_label' => '', 'button_url' => '', 'comment_enabled' => false],
            ]],
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // PRODUTO 2: Foco & Produtividade com TDAH
    // ─────────────────────────────────────────────────────────────
    private function produto2(): void
    {
        $produto = Product::create([
            'tenant_id'        => $this->tenantId,
            'name'             => 'Foco & Produtividade com TDAH',
            'slug'             => 'foco-produtividade-tdah',
            'checkout_slug'    => 'foco-tdah',
            'description'      => 'Estratégias neurociência-based para adultos com TDAH dominarem sua gestão de tempo, criarem rotinas que funcionam de verdade e transformarem caos em produtividade consistente.',
            'type'             => Product::TYPE_AREA_MEMBROS,
            'billing_type'     => Product::BILLING_ONE_TIME,
            'price'            => '347.00',
            'currency'         => 'BRL',
            'is_active'        => true,
            'member_area_config' => $this->memberConfig('#10B981', 'Foco TDAH'),
            'ai_context' => [
                'instructions' => "Você é um especialista em produtividade e gestão do tempo para pessoas com TDAH. Ao analisar os dados deste aluno, considere:\n\n1. **Padrões de Produtividade**: identifique os horários e condições de pico de performance do aluno.\n2. **Gestão do Tempo**: avalie o nível de dificuldade com cegueira temporal, estimativa de tempo e cumprimento de prazos.\n3. **Ambiente e Rotina**: identifique quais elementos do ambiente e rotina facilitam ou sabotam o foco.\n4. **Pontos de Alavancagem**: destaque 2 a 3 mudanças de alto impacto que o aluno pode implementar imediatamente.\n5. **Tom**: prático, orientado a ação, sem julgamento. Celebre o que já funciona antes de sugerir melhorias.\n\nO relatório deve ter linguagem acessível, uso de bullets para clareza, e terminar com um plano de 7 dias personalizado com ações simples e concretas.",
                'jornadas' => [
                    ['journey_id' => 10, 'is_free' => false],
                    ['journey_id' => 12, 'is_free' => false],
                ],
            ],
        ]);

        // SEÇÃO 1: Gestão do Tempo
        $sec1 = MemberSection::create([
            'product_id'   => $produto->id,
            'title'        => 'Gestão do Tempo com Cérebro TDAH',
            'position'     => 1,
            'section_type' => 'trilha_aulas',
        ]);

        $mod1 = MemberModule::create([
            'product_id'       => $produto->id,
            'member_section_id' => $sec1->id,
            'title'            => 'Cegueira Temporal e Estimativa de Tempo',
            'position'         => 1,
        ]);

        MemberLesson::create([
            'product_id'      => $produto->id,
            'member_module_id' => $mod1->id,
            'title'           => 'Como Você Percebe (ou Não Percebe) o Tempo',
            'position'        => 1,
            'type'            => 'quiz',
            'content_files'   => ['questions' => [
                ['id' => 'p2q1a', 'type' => 'scale', 'text' => 'Com que frequência você perde a noção de quanto tempo passou enquanto está envolvido em uma atividade?', 'scale_min' => 1, 'scale_max' => 5, 'scale_min_label' => 'Raramente', 'scale_max_label' => 'Diariamente', 'options' => [], 'image_url' => '', 'video_url' => '', 'button_label' => '', 'button_url' => '', 'comment_enabled' => false],
                ['id' => 'p2q1b', 'type' => 'single', 'text' => 'Qual afirmativa melhor descreve sua relação com prazos e tempo?', 'options' => [
                    ['id' => 'op1', 'text' => 'Sempre entrego com antecedência — prefiro terminar cedo'],
                    ['id' => 'op2', 'text' => 'Entrego na hora — preciso da pressão do prazo para agir'],
                    ['id' => 'op3', 'text' => 'Frequentemente atraso — o prazo chega antes que eu perceba'],
                    ['id' => 'op4', 'text' => 'Não consigo estimar o tempo que uma tarefa vai levar'],
                ], 'image_url' => '', 'video_url' => '', 'button_label' => '', 'button_url' => '', 'comment_enabled' => false],
                ['id' => 'p2q1c', 'type' => 'boolean', 'text' => 'Você costuma subestimar o tempo necessário para tarefas e se atrasar mesmo tentando não atrasar?', 'options' => [], 'image_url' => '', 'video_url' => '', 'button_label' => '', 'button_url' => '', 'comment_enabled' => false],
                ['id' => 'p2q1d', 'type' => 'text', 'text' => 'Descreva como é sua relação com compromissos de hora marcada: você chega no horário? Quanto antes começa a se preparar? O que costuma acontecer?', 'options' => [], 'image_url' => '', 'video_url' => '', 'button_label' => '', 'button_url' => '', 'comment_enabled' => false],
            ]],
        ]);

        MemberLesson::create([
            'product_id'      => $produto->id,
            'member_module_id' => $mod1->id,
            'title'           => 'Técnicas de Ancoragem Temporal',
            'position'        => 2,
            'type'            => 'quiz',
            'content_files'   => ['questions' => [
                ['id' => 'p2q2a', 'type' => 'multi', 'text' => 'Quais estratégias de tempo você já tentou usar para lidar com a cegueira temporal?', 'options' => [
                    ['id' => 'op1', 'text' => 'Timers e cronômetros visíveis'],
                    ['id' => 'op2', 'text' => 'Alarmes no celular para transições de atividade'],
                    ['id' => 'op3', 'text' => 'Técnica Pomodoro (blocos de 25 min)'],
                    ['id' => 'op4', 'text' => 'Relógio analógico visível no ambiente de trabalho'],
                    ['id' => 'op5', 'text' => 'Planejamento com buffers de tempo extra'],
                    ['id' => 'op6', 'text' => 'Nunca tentei estratégias específicas para isso'],
                ], 'image_url' => '', 'video_url' => '', 'button_label' => '', 'button_url' => '', 'comment_enabled' => false],
                ['id' => 'p2q2b', 'type' => 'scale', 'text' => 'Como você avalia sua capacidade atual de estimar quanto tempo uma tarefa vai levar antes de começá-la?', 'scale_min' => 1, 'scale_max' => 5, 'scale_min_label' => 'Péssima', 'scale_max_label' => 'Excelente', 'options' => [], 'image_url' => '', 'video_url' => '', 'button_label' => '', 'button_url' => '', 'comment_enabled' => false],
            ]],
        ]);

        $mod2 = MemberModule::create([
            'product_id'       => $produto->id,
            'member_section_id' => $sec1->id,
            'title'            => 'Priorização e Tomada de Decisão',
            'position'         => 2,
        ]);

        MemberLesson::create([
            'product_id'      => $produto->id,
            'member_module_id' => $mod2->id,
            'title'           => 'Por Que Tudo Parece Urgente ao Mesmo Tempo',
            'position'        => 1,
            'type'            => 'quiz',
            'content_files'   => ['questions' => [
                ['id' => 'p2q3a', 'type' => 'scale', 'text' => 'Com que frequência você sente que tem tantas coisas para fazer que acaba não fazendo nenhuma ("paralisia por sobrecarga")?', 'scale_min' => 1, 'scale_max' => 5, 'scale_min_label' => 'Raramente', 'scale_max_label' => 'Quase todo dia', 'options' => [], 'image_url' => '', 'video_url' => '', 'button_label' => '', 'button_url' => '', 'comment_enabled' => true],
                ['id' => 'p2q3b', 'type' => 'boolean', 'text' => 'Você consegue dizer "não" para novas demandas quando sua agenda já está cheia?', 'options' => [], 'image_url' => '', 'video_url' => '', 'button_label' => '', 'button_url' => '', 'comment_enabled' => false],
                ['id' => 'p2q3c', 'type' => 'single', 'text' => 'Como você geralmente decide o que fazer quando tem muitas tarefas pendentes?', 'options' => [
                    ['id' => 'op1', 'text' => 'Faço a mais fácil primeiro para "ganhar impulso"'],
                    ['id' => 'op2', 'text' => 'Faço a mais urgente (com prazo mais próximo)'],
                    ['id' => 'op3', 'text' => 'Faço a mais interessante ou que mais me motiva no momento'],
                    ['id' => 'op4', 'text' => 'Fico em loop sem conseguir escolher e acabo não fazendo nada'],
                ], 'image_url' => '', 'video_url' => '', 'button_label' => '', 'button_url' => '', 'comment_enabled' => false],
            ]],
        ]);

        MemberLesson::create([
            'product_id'      => $produto->id,
            'member_module_id' => $mod2->id,
            'title'           => 'Meu Perfil de Produtividade',
            'position'        => 2,
            'type'            => 'quiz',
            'content_files'   => ['questions' => [
                ['id' => 'p2q4a', 'type' => 'scale', 'text' => 'Em qual horário do dia você se sente mais focado e produtivo?', 'scale_min' => 1, 'scale_max' => 5, 'scale_min_label' => 'Muito cedo (5h-8h)', 'scale_max_label' => 'Tarde da noite (20h+)', 'options' => [], 'image_url' => '', 'video_url' => '', 'button_label' => '', 'button_url' => '', 'comment_enabled' => false],
                ['id' => 'p2q4b', 'type' => 'multi', 'text' => 'Quais fatores mais prejudicam seu foco quando precisa trabalhar/estudar?', 'options' => [
                    ['id' => 'op1', 'text' => 'Barulho externo e interrupções'],
                    ['id' => 'op2', 'text' => 'Notificações de celular e redes sociais'],
                    ['id' => 'op3', 'text' => 'Ambiente desorganizado ou visualmente poluído'],
                    ['id' => 'op4', 'text' => 'Fome, sono ou desconforto físico'],
                    ['id' => 'op5', 'text' => 'Ansiedade ou pensamentos intrusivos'],
                    ['id' => 'op6', 'text' => 'Falta de interesse na tarefa'],
                ], 'image_url' => '', 'video_url' => '', 'button_label' => '', 'button_url' => '', 'comment_enabled' => false],
                ['id' => 'p2q4c', 'type' => 'text', 'text' => 'Descreva como é uma "boa tarde de trabalho" para você: o que precisa acontecer para que você consiga ser produtivo de verdade?', 'options' => [], 'image_url' => '', 'video_url' => '', 'button_label' => '', 'button_url' => '', 'comment_enabled' => false],
            ]],
        ]);

        // SEÇÃO 2: Ambiente e Rotina
        $sec2 = MemberSection::create([
            'product_id'   => $produto->id,
            'title'        => 'Ambiente e Rotina que Funcionam',
            'position'     => 2,
            'section_type' => 'trilha_aulas',
        ]);

        $mod3 = MemberModule::create([
            'product_id'       => $produto->id,
            'member_section_id' => $sec2->id,
            'title'            => 'Construindo uma Rotina Adaptada',
            'position'         => 1,
        ]);

        MemberLesson::create([
            'product_id'      => $produto->id,
            'member_module_id' => $mod3->id,
            'title'           => 'Rotinas: O Que Já Funciona para Mim',
            'position'        => 1,
            'type'            => 'quiz',
            'content_files'   => ['questions' => [
                ['id' => 'p2q5a', 'type' => 'boolean', 'text' => 'Você tem alguma rotina matinal relativamente consistente (acordar no mesmo horário, mesma sequência de ações)?', 'options' => [], 'image_url' => '', 'video_url' => '', 'button_label' => '', 'button_url' => '', 'comment_enabled' => false],
                ['id' => 'p2q5b', 'type' => 'scale', 'text' => 'Como você avalia a consistência da sua rotina diária atualmente?', 'scale_min' => 1, 'scale_max' => 5, 'scale_min_label' => 'Sem rotina alguma', 'scale_max_label' => 'Rotina muito consistente', 'options' => [], 'image_url' => '', 'video_url' => '', 'button_label' => '', 'button_url' => '', 'comment_enabled' => false],
                ['id' => 'p2q5c', 'type' => 'text', 'text' => 'Qual parte da sua rotina atual funciona bem e você consegue manter com regularidade? O que torna essa parte mais fácil de manter?', 'options' => [], 'image_url' => '', 'video_url' => '', 'button_label' => '', 'button_url' => '', 'comment_enabled' => false],
            ]],
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // PRODUTO 3: Regulação Emocional e Bem-Estar
    // ─────────────────────────────────────────────────────────────
    private function produto3(): void
    {
        $produto = Product::create([
            'tenant_id'        => $this->tenantId,
            'name'             => 'Regulação Emocional e Bem-Estar',
            'slug'             => 'regulacao-emocional-bem-estar',
            'checkout_slug'    => 'reg-emocional',
            'description'      => 'Programa baseado em neurociência afetiva e terapia cognitivo-comportamental adaptada para adultos com TDAH. Desenvolva inteligência emocional, reduza reatividade e construa bem-estar psicológico duradouro.',
            'type'             => Product::TYPE_AREA_MEMBROS,
            'billing_type'     => Product::BILLING_ONE_TIME,
            'price'            => '397.00',
            'currency'         => 'BRL',
            'is_active'        => true,
            'member_area_config' => $this->memberConfig('#F59E0B', 'Regulação Emocional'),
            'ai_context' => [
                'instructions' => "Você é um especialista em regulação emocional e saúde mental para pessoas com TDAH. Ao analisar os dados deste aluno, considere:\n\n1. **Perfil Emocional**: mapeie os padrões de reatividade emocional, gatilhos identificados e estratégias de coping já utilizadas.\n2. **Disregulação Emocional**: avalie a intensidade e frequência de episódios de raiva, frustração, tristeza ou ansiedade relatados.\n3. **Recursos e Forças**: identifique estratégias que já funcionam para o aluno e valide essas conquistas explicitamente.\n4. **Plano de Regulação**: sugira 3 técnicas específicas adequadas ao perfil do aluno, com instruções práticas de implementação.\n5. **Tom**: acolhedor, sem julgamento, empático. Normalize as dificuldades antes de propor soluções. Use linguagem de autocompaixão.\n\nO relatório deve começar reconhecendo o esforço do aluno em buscar autoconhecimento. Inclua uma seção 'Seus Recursos' antes das recomendações.",
                'jornadas' => [
                    ['journey_id' => 11, 'is_free' => false],
                    ['journey_id' => 12, 'is_free' => false],
                ],
            ],
        ]);

        // SEÇÃO 1: Emoções no TDAH
        $sec1 = MemberSection::create([
            'product_id'   => $produto->id,
            'title'        => 'Entendendo Suas Emoções com TDAH',
            'position'     => 1,
            'section_type' => 'trilha_aulas',
        ]);

        $mod1 = MemberModule::create([
            'product_id'       => $produto->id,
            'member_section_id' => $sec1->id,
            'title'            => 'Disregulação Emocional e Impulsividade',
            'position'         => 1,
        ]);

        MemberLesson::create([
            'product_id'      => $produto->id,
            'member_module_id' => $mod1->id,
            'title'           => 'Meu Padrão de Reatividade Emocional',
            'position'        => 1,
            'type'            => 'quiz',
            'content_files'   => ['questions' => [
                ['id' => 'p3q1a', 'type' => 'scale', 'text' => 'Com que intensidade você tende a sentir e expressar emoções em comparação à maioria das pessoas ao seu redor?', 'scale_min' => 1, 'scale_max' => 7, 'scale_min_label' => 'Muito menos intenso', 'scale_max_label' => 'Muito mais intenso', 'options' => [], 'image_url' => '', 'video_url' => '', 'button_label' => '', 'button_url' => '', 'comment_enabled' => false],
                ['id' => 'p3q1b', 'type' => 'multi', 'text' => 'Quais situações costumam ser gatilhos para reações emocionais fortes em você?', 'options' => [
                    ['id' => 'op1', 'text' => 'Críticas ou feedbacks negativos (mesmo construtivos)'],
                    ['id' => 'op2', 'text' => 'Interrupções quando estou em hiperfoco'],
                    ['id' => 'op3', 'text' => 'Situações de injustiça ou tratamento desigual'],
                    ['id' => 'op4', 'text' => 'Frustração com minha própria incapacidade de cumprir metas'],
                    ['id' => 'op5', 'text' => 'Conflitos interpessoais ou mal-entendidos'],
                    ['id' => 'op6', 'text' => 'Rejeição ou abandono (real ou percebido)'],
                    ['id' => 'op7', 'text' => 'Sobrecarga sensorial (barulho, luz intensa, multidão)'],
                ], 'image_url' => '', 'video_url' => '', 'button_label' => '', 'button_url' => '', 'comment_enabled' => false],
                ['id' => 'p3q1c', 'type' => 'boolean', 'text' => 'Você frequentemente se arrepende de coisas que falou ou fez quando estava com raiva ou muito frustrado?', 'options' => [], 'image_url' => '', 'video_url' => '', 'button_label' => '', 'button_url' => '', 'comment_enabled' => false],
                ['id' => 'p3q1d', 'type' => 'text', 'text' => 'Descreva como é para você quando uma emoção forte aparece: o que acontece no seu corpo, nos seus pensamentos e no seu comportamento?', 'options' => [], 'image_url' => '', 'video_url' => '', 'button_label' => '', 'button_url' => '', 'comment_enabled' => false],
            ]],
        ]);

        MemberLesson::create([
            'product_id'      => $produto->id,
            'member_module_id' => $mod1->id,
            'title'           => 'Disforia Sensível à Rejeição (RSD)',
            'position'        => 2,
            'type'            => 'quiz',
            'content_files'   => ['questions' => [
                ['id' => 'p3q2a', 'type' => 'scale', 'text' => 'Com que frequência você evita tentar coisas novas por medo de falhar ou de ser julgado?', 'scale_min' => 1, 'scale_max' => 5, 'scale_min_label' => 'Raramente', 'scale_max_label' => 'Frequentemente', 'options' => [], 'image_url' => '', 'video_url' => '', 'button_label' => '', 'button_url' => '', 'comment_enabled' => false],
                ['id' => 'p3q2b', 'type' => 'boolean', 'text' => 'Você consegue separar sua identidade (quem você é) do seu desempenho (o que você fez ou deixou de fazer)?', 'options' => [], 'image_url' => '', 'video_url' => '', 'button_label' => '', 'button_url' => '', 'comment_enabled' => false],
                ['id' => 'p3q2c', 'type' => 'single', 'text' => 'Como você geralmente reage a críticas, mesmo vindas de pessoas que você respeita?', 'options' => [
                    ['id' => 'op1', 'text' => 'Consigo ouvir, processar e usar como aprendizado sem me abalar muito'],
                    ['id' => 'op2', 'text' => 'Fico levemente incomodado mas me recupero rapidamente'],
                    ['id' => 'op3', 'text' => 'Sinto uma dor intensa que demora horas ou dias para passar'],
                    ['id' => 'op4', 'text' => 'Tendo a contra-atacar ou me defender imediatamente'],
                    ['id' => 'op5', 'text' => 'Me fecho e fico ruminando internamente sem falar nada'],
                ], 'image_url' => '', 'video_url' => '', 'button_label' => '', 'button_url' => '', 'comment_enabled' => false],
            ]],
        ]);

        // Módulo 1.2: Ansiedade e TDAH
        $mod2 = MemberModule::create([
            'product_id'       => $produto->id,
            'member_section_id' => $sec1->id,
            'title'            => 'Ansiedade, Autoestima e TDAH',
            'position'         => 2,
        ]);

        MemberLesson::create([
            'product_id'      => $produto->id,
            'member_module_id' => $mod2->id,
            'title'           => 'Como a Ansiedade se Manifesta no TDAH',
            'position'        => 1,
            'type'            => 'quiz',
            'content_files'   => ['questions' => [
                ['id' => 'p3q3a', 'type' => 'scale', 'text' => 'Como você avalia seu nível geral de ansiedade na última semana?', 'scale_min' => 0, 'scale_max' => 10, 'scale_min_label' => 'Sem ansiedade', 'scale_max_label' => 'Ansiedade muito intensa', 'options' => [], 'image_url' => '', 'video_url' => '', 'button_label' => '', 'button_url' => '', 'comment_enabled' => false],
                ['id' => 'p3q3b', 'type' => 'multi', 'text' => 'Quais sintomas de ansiedade você experimenta com frequência?', 'options' => [
                    ['id' => 'op1', 'text' => 'Pensamentos acelerados ou difíceis de silenciar'],
                    ['id' => 'op2', 'text' => 'Tensão muscular ou dores físicas sem causa orgânica'],
                    ['id' => 'op3', 'text' => 'Preocupação excessiva com situações futuras'],
                    ['id' => 'op4', 'text' => 'Dificuldade para relaxar mesmo quando tudo está bem'],
                    ['id' => 'op5', 'text' => 'Evitação de situações por medo do pior cenário'],
                    ['id' => 'op6', 'text' => 'Não experimento sintomas de ansiedade relevantes'],
                ], 'image_url' => '', 'video_url' => '', 'button_label' => '', 'button_url' => '', 'comment_enabled' => false],
                ['id' => 'p3q3c', 'type' => 'text', 'text' => 'O que você já descobriu que ajuda a reduzir sua ansiedade? Pode ser qualquer coisa — uma atividade, um ritual, uma pessoa, um ambiente.', 'options' => [], 'image_url' => '', 'video_url' => '', 'button_label' => '', 'button_url' => '', 'comment_enabled' => false],
            ]],
        ]);

        // SEÇÃO 2: Estratégias de Regulação
        $sec2 = MemberSection::create([
            'product_id'   => $produto->id,
            'title'        => 'Estratégias de Regulação na Prática',
            'position'     => 2,
            'section_type' => 'trilha_aulas',
        ]);

        $mod3 = MemberModule::create([
            'product_id'       => $produto->id,
            'member_section_id' => $sec2->id,
            'title'            => 'Técnicas de Autorregulação',
            'position'         => 1,
        ]);

        MemberLesson::create([
            'product_id'      => $produto->id,
            'member_module_id' => $mod3->id,
            'title'           => 'Meu Kit de Ferramentas de Regulação',
            'position'        => 1,
            'type'            => 'quiz',
            'content_files'   => ['questions' => [
                ['id' => 'p3q4a', 'type' => 'multi', 'text' => 'Quais das estratégias de regulação emocional abaixo você já tentou usar?', 'options' => [
                    ['id' => 'op1', 'text' => 'Respiração diafragmática ou técnicas de respiração'],
                    ['id' => 'op2', 'text' => 'Meditação ou mindfulness (mesmo que por poucos minutos)'],
                    ['id' => 'op3', 'text' => 'Exercício físico como válvula de escape emocional'],
                    ['id' => 'op4', 'text' => 'Journaling (escrever sobre suas emoções)'],
                    ['id' => 'op5', 'text' => 'Técnica de "pausa" antes de responder quando irritado'],
                    ['id' => 'op6', 'text' => 'Conversa com alguém de confiança quando sobrecarregado'],
                    ['id' => 'op7', 'text' => 'Nunca usei nenhuma estratégia intencional de regulação'],
                ], 'image_url' => '', 'video_url' => '', 'button_label' => '', 'button_url' => '', 'comment_enabled' => false],
                ['id' => 'p3q4b', 'type' => 'scale', 'text' => 'Qual é sua disposição atual para aprender e praticar novas técnicas de regulação emocional?', 'scale_min' => 1, 'scale_max' => 5, 'scale_min_label' => 'Sem disposição', 'scale_max_label' => 'Muito disposto', 'options' => [], 'image_url' => '', 'video_url' => '', 'button_label' => '', 'button_url' => '', 'comment_enabled' => false],
                ['id' => 'p3q4c', 'type' => 'text', 'text' => 'Se você pudesse mudar apenas UMA coisa na forma como lida com suas emoções difíceis, o que seria? Seja específico.', 'options' => [], 'image_url' => '', 'video_url' => '', 'button_label' => '', 'button_url' => '', 'comment_enabled' => false],
            ]],
        ]);

        MemberLesson::create([
            'product_id'      => $produto->id,
            'member_module_id' => $mod3->id,
            'title'           => 'Bem-Estar e Qualidade de Vida',
            'position'        => 2,
            'type'            => 'quiz',
            'content_files'   => ['questions' => [
                ['id' => 'p3q5a', 'type' => 'scale', 'text' => 'Como você avalia sua qualidade de vida geral hoje?', 'scale_min' => 0, 'scale_max' => 10, 'scale_min_label' => 'Muito ruim', 'scale_max_label' => 'Excelente', 'options' => [], 'image_url' => '', 'video_url' => '', 'button_label' => '', 'button_url' => '', 'comment_enabled' => false],
                ['id' => 'p3q5b', 'type' => 'boolean', 'text' => 'Você se sente, no geral, otimista sobre sua capacidade de melhorar sua saúde emocional?', 'options' => [], 'image_url' => '', 'video_url' => '', 'button_label' => '', 'button_url' => '', 'comment_enabled' => false],
                ['id' => 'p3q5c', 'type' => 'text', 'text' => 'O que uma vida com bem-estar emocional real significaria para você? Descreva como seria seu dia a dia se você tivesse alcançado isso.', 'options' => [], 'image_url' => '', 'video_url' => '', 'button_label' => '', 'button_url' => '', 'comment_enabled' => false],
            ]],
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────
    private function memberConfig(string $primaryColor, string $appName): array
    {
        return [
            'primary_color'       => $primaryColor,
            'app_name'            => $appName,
            'show_community'      => false,
            'show_leaderboard'    => false,
            'show_certificate'    => true,
            'show_progress'       => true,
            'lessons_completion'  => 'manual',
        ];
    }
}
