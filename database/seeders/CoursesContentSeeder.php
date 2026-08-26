<?php

namespace Database\Seeders;

use App\Models\MemberLesson;
use App\Models\MemberModule;
use App\Models\MemberSection;
use Illuminate\Database\Seeder;

class CoursesContentSeeder extends Seeder
{
    // ─── IDs dos produtos existentes ─────────────────────────────────────────
    private const P1 = '2594d9db-f638-4cfd-9c1c-b47c21da8a1c'; // Neurociência Aplicada ao TDAH
    private const P2 = '43d23efd-da32-49bf-a9f4-3637528fdbba'; // Regulação Emocional no TDAH
    private const P3 = 'ac9ca66c-c453-49a4-a975-be4fa067a1a3'; // Foco & Produtividade com TDAH

    public function run(): void
    {
        $this->seedP1();
        $this->seedP2();
        $this->seedP3();
    }

    // =========================================================================
    // P1 — Neurociência Aplicada ao TDAH
    // =========================================================================
    private function seedP1(): void
    {
        $pid = self::P1;

        // Seção 1 já existe (id=3). Começa na posição 1.
        $secPos = 1;

        // ── Seção: Fundamentos da Neurociência ────────────────────────────────
        $s1 = MemberSection::create([
            'product_id'   => $pid,
            'title'        => 'Fundamentos da Neurociência',
            'position'     => $secPos++,
            'cover_mode'   => 'vertical',
            'section_type' => 'courses',
        ]);

        // Módulo 1.1 — Como o Cérebro Funciona
        $m = MemberModule::create([
            'member_section_id'  => $s1->id,
            'product_id'         => $pid,
            'title'              => 'Como o Cérebro Funciona',
            'position'           => 0,
            'show_title_on_cover'=> true,
        ]);
        $this->lesson($m, 0, 'Introdução à Neuroanatomia', 'text', null, $this->textIntroNeuroanatomia(), null, null);
        $this->lesson($m, 1, 'Estruturas Cerebrais e suas Funções', 'video', '', null, null, 720);
        $this->lesson($m, 2, 'Avaliação: Conhecimentos sobre Neuroanatomia', 'quiz', null, null, [
            'questions' => [
                ['id'=>'n1', 'text'=>'Consigo identificar as principais regiões cerebrais mencionadas no módulo', 'category'=>'Autoavaliação de Conhecimento', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Não consigo', 'scale_max_label'=>'Consigo com facilidade'],
                ['id'=>'n2', 'text'=>'Compreendo a diferença entre córtex pré-frontal e sistema límbico', 'category'=>'Autoavaliação de Conhecimento', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Nada', 'scale_max_label'=>'Completamente'],
                ['id'=>'n3', 'text'=>'Consigo relacionar estruturas cerebrais com comportamentos do dia a dia', 'category'=>'Aplicação Prática', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Não consigo', 'scale_max_label'=>'Consigo com clareza'],
                ['id'=>'n4', 'text'=>'O conteúdo deste módulo foi relevante para entender meu próprio funcionamento', 'category'=>'Relevância Percebida', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Nada relevante', 'scale_max_label'=>'Extremamente relevante', 'comment_enabled'=>true],
            ],
        ], null);

        // Módulo 1.2 — Neuroplasticidade
        $m = MemberModule::create([
            'member_section_id'  => $s1->id,
            'product_id'         => $pid,
            'title'              => 'Neuroplasticidade — O Cérebro que Muda',
            'position'           => 1,
            'show_title_on_cover'=> true,
        ]);
        $this->lesson($m, 0, 'O que é Neuroplasticidade?', 'text', null, $this->textNeuroplasticidade(), null, null);
        $this->lesson($m, 1, 'Neuroplasticidade na Prática — Estudos de Caso', 'video', '', null, null, 900);
        $this->lesson($m, 2, 'Reflexão: Sua Capacidade de Mudança', 'quiz', null, null, [
            'questions' => [
                ['id'=>'np1', 'text'=>'Acredito que meu cérebro pode mudar com prática e esforço consistente', 'category'=>'Mentalidade de Crescimento', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Discordo totalmente', 'scale_max_label'=>'Concordo totalmente'],
                ['id'=>'np2', 'text'=>'Consigo identificar situações em que já percebi mudanças no meu padrão de comportamento', 'category'=>'Experiência Pessoal', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Nunca percebi', 'scale_max_label'=>'Percebo claramente', 'comment_enabled'=>true],
                ['id'=>'np3', 'text'=>'A ideia de que o cérebro é maleável me motiva a buscar mudanças', 'category'=>'Motivação', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Não me motiva', 'scale_max_label'=>'Motiva muito'],
                ['id'=>'np4', 'text'=>'Compreendo por que práticas repetitivas são fundamentais para criar novos padrões neurais', 'category'=>'Compreensão Técnica', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Não compreendo', 'scale_max_label'=>'Compreendo plenamente'],
            ],
        ], null);

        // ── Seção 2: Neurobiologia do TDAH ───────────────────────────────────
        $s2 = MemberSection::create([
            'product_id'   => $pid,
            'title'        => 'Neurobiologia do TDAH',
            'position'     => $secPos++,
            'cover_mode'   => 'vertical',
            'section_type' => 'courses',
        ]);

        // Módulo 2.1 — Diferenças Cerebrais no TDAH
        $m = MemberModule::create([
            'member_section_id'  => $s2->id,
            'product_id'         => $pid,
            'title'              => 'Diferenças Cerebrais no TDAH',
            'position'           => 0,
            'show_title_on_cover'=> true,
        ]);
        $this->lesson($m, 0, 'O Que as Neuroimagens Revelam sobre o TDAH', 'text', null, $this->textNeuroimagens(), null, null);
        $this->lesson($m, 1, 'Neuroimagem ao Vivo — Comparando Cérebros com e sem TDAH', 'video', '', null, null, 840);
        $this->lesson($m, 2, 'Avaliação: Neurobiologia do TDAH', 'quiz', null, null, [
            'questions' => [
                ['id'=>'td1', 'text'=>'Compreendo por que o TDAH não é "falta de vontade" mas uma diferença neurológica', 'category'=>'Compreensão Conceitual', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Não compreendo', 'scale_max_label'=>'Compreendo completamente'],
                ['id'=>'td2', 'text'=>'Conhecer a base neurológica do TDAH reduziu minha autocrítica', 'category'=>'Impacto Emocional', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Nenhum impacto', 'scale_max_label'=>'Impacto muito grande', 'comment_enabled'=>true],
                ['id'=>'td3', 'text'=>'Consigo explicar para outra pessoa por que o TDAH é uma condição do neurodesenvolvimento', 'category'=>'Capacidade de Explicar', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Não consigo', 'scale_max_label'=>'Consigo com facilidade'],
                ['id'=>'td4', 'text'=>'Entendo a relação entre déficit dopaminérgico e os sintomas do TDAH', 'category'=>'Compreensão Técnica', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Nada', 'scale_max_label'=>'Completamente'],
            ],
        ], null);

        // Módulo 2.2 — Dopamina e Funções Executivas
        $m = MemberModule::create([
            'member_section_id'  => $s2->id,
            'product_id'         => $pid,
            'title'              => 'Dopamina, Noradrenalina e Funções Executivas',
            'position'           => 1,
            'show_title_on_cover'=> true,
        ]);
        $this->lesson($m, 0, 'Os Neurotransmissores por Trás do TDAH', 'text', null, $this->textNeurotransmissores(), null, null);
        $this->lesson($m, 1, 'Funções Executivas — Como São Afetadas no TDAH', 'video', '', null, null, 1080);
        $this->lesson($m, 2, 'Exercício Prático: Mapeando suas Funções Executivas', 'quiz', null, null, [
            'questions' => [
                ['id'=>'fe1', 'text'=>'Tenho dificuldade em iniciar tarefas mesmo sabendo que são importantes', 'category'=>'Iniciação', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Nunca', 'scale_max_label'=>'Quase sempre'],
                ['id'=>'fe2', 'text'=>'Perco o fio do raciocínio ou esqueço o que ia fazer durante uma tarefa', 'category'=>'Memória de Trabalho', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Raramente', 'scale_max_label'=>'Muito frequentemente'],
                ['id'=>'fe3', 'text'=>'Tenho dificuldade em mudar de plano quando algo inesperado acontece', 'category'=>'Flexibilidade Cognitiva', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Nenhuma dificuldade', 'scale_max_label'=>'Muita dificuldade'],
                ['id'=>'fe4', 'text'=>'Age por impulso antes de pensar nas consequências', 'category'=>'Controle Inibitório', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Raramente', 'scale_max_label'=>'Com muita frequência', 'comment_enabled'=>true],
                ['id'=>'fe5', 'text'=>'Tenho dificuldade em planejar projetos com múltiplas etapas', 'category'=>'Planejamento', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Nenhuma', 'scale_max_label'=>'Extrema dificuldade'],
            ],
        ], null);

        // ── Seção 3: Tratamento e Intervenções ───────────────────────────────
        $s3 = MemberSection::create([
            'product_id'   => $pid,
            'title'        => 'Tratamento Baseado em Evidências',
            'position'     => $secPos++,
            'cover_mode'   => 'vertical',
            'section_type' => 'courses',
        ]);

        // Módulo 3.1 — Medicação
        $m = MemberModule::create([
            'member_section_id'  => $s3->id,
            'product_id'         => $pid,
            'title'              => 'Medicação no TDAH — Mitos e Realidades',
            'position'           => 0,
            'show_title_on_cover'=> true,
        ]);
        $this->lesson($m, 0, 'Como os Estimulantes Agem no Cérebro com TDAH', 'text', null, $this->textMedicacao(), null, null);
        $this->lesson($m, 1, 'Entrevista com Especialista — Medicação e TDAH na Prática', 'video', '', null, null, 1200);
        $this->lesson($m, 2, 'Sua Relação com o Tratamento Medicamentoso', 'quiz', null, null, [
            'questions' => [
                ['id'=>'med1', 'text'=>'Sinto que tenho informações suficientes para tomar decisões sobre tratamento', 'category'=>'Autonomia no Tratamento', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Discordo totalmente', 'scale_max_label'=>'Concordo totalmente'],
                ['id'=>'med2', 'text'=>'Consigo perceber os efeitos (positivos ou negativos) de intervenções no meu funcionamento', 'category'=>'Autoobservação', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Não consigo', 'scale_max_label'=>'Consigo claramente', 'comment_enabled'=>true],
                ['id'=>'med3', 'text'=>'Me sinto confortável discutindo opções de tratamento com profissionais de saúde', 'category'=>'Comunicação com Profissionais', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Nada confortável', 'scale_max_label'=>'Muito confortável'],
            ],
        ], null);

        // Módulo 3.2 — Intervenções Não-Farmacológicas
        $m = MemberModule::create([
            'member_section_id'  => $s3->id,
            'product_id'         => $pid,
            'title'              => 'Intervenções Não-Farmacológicas',
            'position'           => 1,
            'show_title_on_cover'=> true,
        ]);
        $this->lesson($m, 0, 'TCC, Coaching e Psicoeducação — O que Funciona', 'text', null, $this->textIntervencoesNaoFarm(), null, null);
        $this->lesson($m, 1, 'Exercício Físico e Neurociência — Um Recurso Subestimado', 'video', '', null, null, 780);
        $this->lesson($m, 2, 'Inventário de Estratégias em Uso', 'quiz', null, null, [
            'questions' => [
                ['id'=>'int1', 'text'=>'Utilizo alguma forma de atividade física regular como estratégia para meu TDAH', 'category'=>'Estilo de Vida', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Nunca', 'scale_max_label'=>'Regularmente'],
                ['id'=>'int2', 'text'=>'Tenho acesso a suporte profissional (psicólogo, psiquiatra, coach) adequado', 'category'=>'Suporte Profissional', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Nenhum acesso', 'scale_max_label'=>'Acesso pleno'],
                ['id'=>'int3', 'text'=>'Reconheço a importância de múltiplas intervenções combinadas no tratamento do TDAH', 'category'=>'Visão do Tratamento', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Não reconheço', 'scale_max_label'=>'Reconheço plenamente'],
                ['id'=>'int4', 'text'=>'Estou aplicando pelo menos uma nova estratégia desde o início deste curso', 'category'=>'Aplicação', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Nenhuma', 'scale_max_label'=>'Várias', 'comment_enabled'=>true],
            ],
        ], null);
    }

    // =========================================================================
    // P2 — Regulação Emocional no TDAH
    // =========================================================================
    private function seedP2(): void
    {
        $pid = self::P2;
        $secPos = 0;

        // ── Seção 1: Bases da Regulação Emocional ────────────────────────────
        $s1 = MemberSection::create([
            'product_id'   => $pid,
            'title'        => 'Bases da Regulação Emocional',
            'position'     => $secPos++,
            'cover_mode'   => 'vertical',
            'section_type' => 'courses',
        ]);

        $m = MemberModule::create([
            'member_section_id'  => $s1->id,
            'product_id'         => $pid,
            'title'              => 'O Que São Emoções e Como Surgem',
            'position'           => 0,
            'show_title_on_cover'=> true,
        ]);
        $this->lesson($m, 0, 'A Neurociência das Emoções', 'text', null, $this->textNeurocienciaEmocoes(), null, null);
        $this->lesson($m, 1, 'Emoções no TDAH — Por Que São Tão Intensas?', 'video', '', null, null, 840);
        $this->lesson($m, 2, 'Mapeamento Emocional Inicial', 'quiz', null, null, [
            'questions' => [
                ['id'=>'em1', 'text'=>'Consigo identificar minhas emoções no momento em que elas surgem', 'category'=>'Consciência Emocional', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Raramente', 'scale_max_label'=>'Quase sempre'],
                ['id'=>'em2', 'text'=>'Minhas reações emocionais frequentemente parecem desproporcionais ao que aconteceu', 'category'=>'Intensidade Emocional', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Raramente', 'scale_max_label'=>'Com muita frequência', 'comment_enabled'=>true],
                ['id'=>'em3', 'text'=>'Consigo distinguir o que estou sentindo de como estou agindo', 'category'=>'Diferenciação Emoção-Ação', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Não consigo', 'scale_max_label'=>'Consigo com clareza'],
                ['id'=>'em4', 'text'=>'Emoções negativas tendem a durar mais do que eu gostaria em mim', 'category'=>'Duração Emocional', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Discordo totalmente', 'scale_max_label'=>'Concordo totalmente'],
            ],
        ], null);

        $m = MemberModule::create([
            'member_section_id'  => $s1->id,
            'product_id'         => $pid,
            'title'              => 'Desregulação Emocional e TDAH',
            'position'           => 1,
            'show_title_on_cover'=> true,
        ]);
        $this->lesson($m, 0, 'RSD — Disforia Sensível à Rejeição', 'text', null, $this->textRSD(), null, null);
        $this->lesson($m, 1, 'Reconhecendo Padrões de Desregulação no Cotidiano', 'video', '', null, null, 960);
        $this->lesson($m, 2, 'Avaliação de Padrões Emocionais', 'quiz', null, null, [
            'questions' => [
                ['id'=>'rsd1', 'text'=>'Reajo intensamente a críticas ou rejeições — mesmo quando são pequenas', 'category'=>'Sensibilidade à Rejeição', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Raramente', 'scale_max_label'=>'Muito frequentemente'],
                ['id'=>'rsd2', 'text'=>'Me sinto envergonhado(a) ou humilhado(a) facilmente, mesmo em situações que outros considerariam triviais', 'category'=>'Vergonha e Humilhação', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Raramente', 'scale_max_label'=>'Com muita frequência', 'comment_enabled'=>true],
                ['id'=>'rsd3', 'text'=>'Tenho dificuldade em "soltar" situações emocionalmente carregadas', 'category'=>'Ruminação', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Nenhuma', 'scale_max_label'=>'Extrema dificuldade'],
                ['id'=>'rsd4', 'text'=>'Evito situações de possível rejeição mesmo que isso me limite', 'category'=>'Comportamento de Evitação', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Nunca', 'scale_max_label'=>'Frequentemente'],
            ],
        ], null);

        // ── Seção 2: Técnicas de Regulação ───────────────────────────────────
        $s2 = MemberSection::create([
            'product_id'   => $pid,
            'title'        => 'Técnicas Práticas de Autorregulação',
            'position'     => $secPos++,
            'cover_mode'   => 'vertical',
            'section_type' => 'courses',
        ]);

        $m = MemberModule::create([
            'member_section_id'  => $s2->id,
            'product_id'         => $pid,
            'title'              => 'Respiração e Regulação do Sistema Nervoso',
            'position'           => 0,
            'show_title_on_cover'=> true,
        ]);
        $this->lesson($m, 0, 'Como a Respiração Regula o Cérebro Emocional', 'text', null, $this->textRespiracao(), null, null);
        $this->lesson($m, 1, 'Prática Guiada — Técnica 4-7-8 e Respiração Diafragmática', 'video', '', null, null, 660);
        $this->lesson($m, 2, 'Monitoramento: Praticando Respiração Consciente', 'quiz', null, null, [
            'questions' => [
                ['id'=>'res1', 'text'=>'Praticei a técnica de respiração 4-7-8 pelo menos uma vez desde a aula', 'category'=>'Prática', 'scale_min'=>0, 'scale_max'=>1, 'scale_min_label'=>'Não pratiquei', 'scale_max_label'=>'Pratiquei'],
                ['id'=>'res2', 'text'=>'Percebi algum efeito na minha sensação de calma após praticar a respiração', 'category'=>'Efeito Percebido', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Nenhum efeito', 'scale_max_label'=>'Efeito muito claro', 'comment_enabled'=>true],
                ['id'=>'res3', 'text'=>'Consigo lembrar de usar a respiração consciente em momentos de stress', 'category'=>'Aplicação em Crise', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Raramente', 'scale_max_label'=>'Com frequência'],
                ['id'=>'res4', 'text'=>'A prática de respiração parece algo que posso manter no meu cotidiano', 'category'=>'Sustentabilidade', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Não parece viável', 'scale_max_label'=>'Totalmente viável'],
            ],
        ], null);

        $m = MemberModule::create([
            'member_section_id'  => $s2->id,
            'product_id'         => $pid,
            'title'              => 'Reestruturação Cognitiva e TCC',
            'position'           => 1,
            'show_title_on_cover'=> true,
        ]);
        $this->lesson($m, 0, 'Como Pensamentos Geram Emoções — O Modelo ABC', 'text', null, $this->textModeloABC(), null, null);
        $this->lesson($m, 1, 'Identificando e Desafiando Pensamentos Automáticos', 'video', '', null, null, 900);
        $this->lesson($m, 2, 'Praticando a Reestruturação Cognitiva', 'quiz', null, null, [
            'questions' => [
                ['id'=>'tcc1', 'text'=>'Consigo identificar pensamentos automáticos negativos quando eles surgem', 'category'=>'Consciência Cognitiva', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Raramente', 'scale_max_label'=>'Com frequência'],
                ['id'=>'tcc2', 'text'=>'Consigo questionar se meus pensamentos automáticos refletem a realidade', 'category'=>'Questionamento Cognitivo', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Raramente', 'scale_max_label'=>'Com facilidade'],
                ['id'=>'tcc3', 'text'=>'Reconheço ao menos 2 distorções cognitivas que aparecem com frequência no meu pensamento', 'category'=>'Reconhecimento de Distorções', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Não reconheço', 'scale_max_label'=>'Reconheço claramente', 'comment_enabled'=>true],
                ['id'=>'tcc4', 'text'=>'Apliquei o modelo ABC a alguma situação emocionalmente difícil nesta semana', 'category'=>'Aplicação Prática', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Não apliquei', 'scale_max_label'=>'Apliquei várias vezes'],
            ],
        ], null);

        $m = MemberModule::create([
            'member_section_id'  => $s2->id,
            'product_id'         => $pid,
            'title'              => 'Mindfulness para o Cérebro TDAH',
            'position'           => 2,
            'show_title_on_cover'=> true,
        ]);
        $this->lesson($m, 0, 'Mindfulness e TDAH — O que Funciona', 'text', null, $this->textMindfulness(), null, null);
        $this->lesson($m, 1, 'Prática Guiada — Scan Corporal e Atenção Plena Breve', 'video', '', null, null, 780);
        $this->lesson($m, 2, 'Sua Experiência com Mindfulness', 'quiz', null, null, [
            'questions' => [
                ['id'=>'mf1', 'text'=>'Já tinha alguma experiência com mindfulness ou meditação antes deste módulo', 'category'=>'Experiência Prévia', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Nenhuma', 'scale_max_label'=>'Muita experiência'],
                ['id'=>'mf2', 'text'=>'A prática de mindfulness me parece mais acessível após entender a versão adaptada para o TDAH', 'category'=>'Acessibilidade', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Não parece mais acessível', 'scale_max_label'=>'Muito mais acessível', 'comment_enabled'=>true],
                ['id'=>'mf3', 'text'=>'Consigo praticar atenção plena por pelo menos 5 minutos sem grandes dificuldades', 'category'=>'Capacidade de Prática', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Não consigo', 'scale_max_label'=>'Consigo facilmente'],
                ['id'=>'mf4', 'text'=>'Percebo benefícios claros quando pratico mindfulness regularmente', 'category'=>'Benefícios Percebidos', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Nenhum benefício', 'scale_max_label'=>'Benefícios muito claros'],
            ],
        ], null);

        // ── Seção 3: Aplicação no Dia a Dia ──────────────────────────────────
        $s3 = MemberSection::create([
            'product_id'   => $pid,
            'title'        => 'Aplicando no Dia a Dia',
            'position'     => $secPos++,
            'cover_mode'   => 'vertical',
            'section_type' => 'courses',
        ]);

        $m = MemberModule::create([
            'member_section_id'  => $s3->id,
            'product_id'         => $pid,
            'title'              => 'Regulação Emocional nos Relacionamentos',
            'position'           => 0,
            'show_title_on_cover'=> true,
        ]);
        $this->lesson($m, 0, 'Como o TDAH Impacta Relacionamentos — Padrões e Ciclos', 'text', null, $this->textRelacionamentos(), null, null);
        $this->lesson($m, 1, 'Comunicação Não-Violenta com TDAH', 'video', '', null, null, 1020);
        $this->lesson($m, 2, 'Avaliação de Padrões nos Relacionamentos', 'quiz', null, null, [
            'questions' => [
                ['id'=>'rel1', 'text'=>'Tenho dificuldade em manter a calma durante conflitos interpessoais', 'category'=>'Gestão de Conflitos', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Raramente', 'scale_max_label'=>'Quase sempre'],
                ['id'=>'rel2', 'text'=>'Após um conflito, consigo reconhecer minha parte sem me culpar excessivamente', 'category'=>'Responsabilidade Equilibrada', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Raramente', 'scale_max_label'=>'Com frequência', 'comment_enabled'=>true],
                ['id'=>'rel3', 'text'=>'Consigo pedir o que preciso de forma clara sem entrar em conflito', 'category'=>'Comunicação de Necessidades', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Raramente', 'scale_max_label'=>'Com facilidade'],
                ['id'=>'rel4', 'text'=>'Pessoas próximas entendem como o TDAH afeta meu comportamento nos relacionamentos', 'category'=>'Compreensão do Entorno', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Não entendem', 'scale_max_label'=>'Entendem muito bem'],
            ],
        ], null);

        $m = MemberModule::create([
            'member_section_id'  => $s3->id,
            'product_id'         => $pid,
            'title'              => 'Construindo sua Rotina de Bem-Estar Emocional',
            'position'           => 1,
            'show_title_on_cover'=> true,
        ]);
        $this->lesson($m, 0, 'Criando um Plano de Regulação Emocional Pessoal', 'text', null, $this->textPlanoRegulacao(), null, null);
        $this->lesson($m, 1, 'Integrando Tudo — Da Teoria à Prática Diária', 'video', '', null, null, 900);
        $this->lesson($m, 2, 'Avaliação Final — Progresso e Próximos Passos', 'quiz', null, null, [
            'questions' => [
                ['id'=>'fin1', 'text'=>'Em comparação ao início do curso, minha consciência emocional melhorou', 'category'=>'Progresso Geral', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Não melhorou', 'scale_max_label'=>'Melhorou muito'],
                ['id'=>'fin2', 'text'=>'Tenho pelo menos 2 estratégias de regulação que utilizo regularmente', 'category'=>'Estratégias em Uso', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Nenhuma', 'scale_max_label'=>'Várias'],
                ['id'=>'fin3', 'text'=>'Me sinto mais preparado(a) para lidar com situações emocionalmente desafiadoras', 'category'=>'Autoeficácia', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Não me sinto', 'scale_max_label'=>'Sinto-me muito mais preparado(a)', 'comment_enabled'=>true],
                ['id'=>'fin4', 'text'=>'Recomendaria este curso para outra pessoa com TDAH', 'category'=>'Satisfação', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Não recomendaria', 'scale_max_label'=>'Recomendaria fortemente'],
            ],
        ], null);
    }

    // =========================================================================
    // P3 — Foco & Produtividade com TDAH
    // =========================================================================
    private function seedP3(): void
    {
        $pid = self::P3;
        $secPos = 0;

        // ── Seção 1: Fundamentos do Foco ─────────────────────────────────────
        $s1 = MemberSection::create([
            'product_id'   => $pid,
            'title'        => 'Fundamentos do Foco no TDAH',
            'position'     => $secPos++,
            'cover_mode'   => 'vertical',
            'section_type' => 'courses',
        ]);

        $m = MemberModule::create([
            'member_section_id'  => $s1->id,
            'product_id'         => $pid,
            'title'              => 'Como o Foco Funciona no Cérebro TDAH',
            'position'           => 0,
            'show_title_on_cover'=> true,
        ]);
        $this->lesson($m, 0, 'O Paradoxo do Hiperfoco — Por Que Alguns Tópicos Capturam Tudo', 'text', null, $this->textHiperfoco(), null, null);
        $this->lesson($m, 1, 'Dopamina, Interesse e o Sistema de Ativação do TDAH', 'video', '', null, null, 780);
        $this->lesson($m, 2, 'Avaliação do Seu Perfil de Foco', 'quiz', null, null, [
            'questions' => [
                ['id'=>'foc1', 'text'=>'Consigo identificar as condições em que meu foco funciona naturalmente', 'category'=>'Autoconhecimento do Foco', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Raramente', 'scale_max_label'=>'Com clareza'],
                ['id'=>'foc2', 'text'=>'Tenho dificuldade em iniciar tarefas que considero pouco interessantes', 'category'=>'Iniciação', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Raramente', 'scale_max_label'=>'Quase sempre'],
                ['id'=>'foc3', 'text'=>'Entro em hiperfoco com frequência, perdendo a noção do tempo', 'category'=>'Hiperfoco', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Nunca', 'scale_max_label'=>'Muito frequentemente', 'comment_enabled'=>true],
                ['id'=>'foc4', 'text'=>'Após entender a neurologia do foco no TDAH, me sinto menos culpado(a) pela falta de foco', 'category'=>'Impacto do Conhecimento', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Nenhum impacto', 'scale_max_label'=>'Impacto muito significativo'],
            ],
        ], null);

        $m = MemberModule::create([
            'member_section_id'  => $s1->id,
            'product_id'         => $pid,
            'title'              => 'Cegueira Temporal — O Tempo no TDAH',
            'position'           => 1,
            'show_title_on_cover'=> true,
        ]);
        $this->lesson($m, 0, 'Time Blindness — Por Que o Tempo Foge', 'text', null, $this->textTimeBlindness(), null, null);
        $this->lesson($m, 1, 'Ferramentas para Tornar o Tempo Visível', 'video', '', null, null, 720);
        $this->lesson($m, 2, 'Diagnóstico: Sua Relação com o Tempo', 'quiz', null, null, [
            'questions' => [
                ['id'=>'tb1', 'text'=>'Costumo chegar atrasado(a) mesmo quando me planejei para não chegar', 'category'=>'Pontualidade', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Raramente', 'scale_max_label'=>'Quase sempre'],
                ['id'=>'tb2', 'text'=>'Subestimo quanto tempo as tarefas levam e acabo atrasando compromissos', 'category'=>'Estimativa de Tempo', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Raramente', 'scale_max_label'=>'Com muita frequência'],
                ['id'=>'tb3', 'text'=>'Perco a noção do tempo durante atividades prazerosas', 'category'=>'Hiperfoco e Tempo', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Raramente', 'scale_max_label'=>'Muito frequentemente'],
                ['id'=>'tb4', 'text'=>'Utilizo alguma ferramenta (alarmes, timers, calendário) para compensar a dificuldade com o tempo', 'category'=>'Estratégias Atuais', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Nenhuma', 'scale_max_label'=>'Várias regularmente', 'comment_enabled'=>true],
            ],
        ], null);

        // ── Seção 2: Estratégias de Produtividade ────────────────────────────
        $s2 = MemberSection::create([
            'product_id'   => $pid,
            'title'        => 'Estratégias de Produtividade Adaptadas',
            'position'     => $secPos++,
            'cover_mode'   => 'vertical',
            'section_type' => 'courses',
        ]);

        $m = MemberModule::create([
            'member_section_id'  => $s2->id,
            'product_id'         => $pid,
            'title'              => 'Técnica Pomodoro Adaptada para o TDAH',
            'position'           => 0,
            'show_title_on_cover'=> true,
        ]);
        $this->lesson($m, 0, 'Pomodoro para TDAH — Adaptando Intervalos e Pausas', 'text', null, $this->textPomodoro(), null, null);
        $this->lesson($m, 1, 'Demo Prático — Uma Sessão Pomodoro ao Vivo', 'video', '', null, null, 600);
        $this->lesson($m, 2, 'Implementando o Pomodoro na sua Rotina', 'quiz', null, null, [
            'questions' => [
                ['id'=>'pom1', 'text'=>'Já tentei a técnica Pomodoro antes deste módulo', 'category'=>'Experiência Anterior', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Nunca', 'scale_max_label'=>'Usei regularmente'],
                ['id'=>'pom2', 'text'=>'A versão adaptada (blocos de 15-20min) me parece mais viável que o Pomodoro clássico (25min)', 'category'=>'Viabilidade Percebida', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Discordo', 'scale_max_label'=>'Concordo muito'],
                ['id'=>'pom3', 'text'=>'Testei ao menos uma sessão Pomodoro adaptada desde a aula', 'category'=>'Aplicação', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Não testei', 'scale_max_label'=>'Testei várias', 'comment_enabled'=>true],
                ['id'=>'pom4', 'text'=>'O timer cria urgência que me ajuda a iniciar tarefas que procrastino', 'category'=>'Mecanismo de Ativação', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Não percebo isso', 'scale_max_label'=>'Percebo claramente'],
            ],
        ], null);

        $m = MemberModule::create([
            'member_section_id'  => $s2->id,
            'product_id'         => $pid,
            'title'              => 'Planejamento e Priorização para o TDAH',
            'position'           => 1,
            'show_title_on_cover'=> true,
        ]);
        $this->lesson($m, 0, 'Sistemas de Planejamento que Funcionam para Cérebros TDAH', 'text', null, $this->textPlanejamento(), null, null);
        $this->lesson($m, 1, 'Brain Dump, Matriz de Prioridade e Planejamento Semanal', 'video', '', null, null, 960);
        $this->lesson($m, 2, 'Seu Sistema de Planejamento Atual', 'quiz', null, null, [
            'questions' => [
                ['id'=>'plan1', 'text'=>'Utilizo algum sistema de planejamento consistentemente (papel, app, calendário)', 'category'=>'Sistema Atual', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Nenhum', 'scale_max_label'=>'Muito consistentemente'],
                ['id'=>'plan2', 'text'=>'Consigo distinguir tarefas urgentes de tarefas importantes no meu dia a dia', 'category'=>'Priorização', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Raramente', 'scale_max_label'=>'Com facilidade'],
                ['id'=>'plan3', 'text'=>'Tenho dificuldade em manter qualquer sistema de planejamento por mais de 2 semanas', 'category'=>'Consistência', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Raramente', 'scale_max_label'=>'Sempre acontece', 'comment_enabled'=>true],
                ['id'=>'plan4', 'text'=>'Farei alguma mudança no meu sistema de planejamento com base neste módulo', 'category'=>'Intenção de Mudança', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Não farei', 'scale_max_label'=>'Sim, já planejei'],
            ],
        ], null);

        $m = MemberModule::create([
            'member_section_id'  => $s2->id,
            'product_id'         => $pid,
            'title'              => 'Gerenciando Distrações e Notificações',
            'position'           => 2,
            'show_title_on_cover'=> true,
        ]);
        $this->lesson($m, 0, 'O Ambiente Digital e o Cérebro TDAH — Uma Batalha Desigual', 'text', null, $this->textDistracoes(), null, null);
        $this->lesson($m, 1, 'Configurando seu Ambiente Digital para Foco', 'video', '', null, null, 720);
        $this->lesson($m, 2, 'Auditoria do Seu Ambiente de Foco', 'quiz', null, null, [
            'questions' => [
                ['id'=>'dis1', 'text'=>'Meu celular/computador tem notificações que interrompem meu foco regularmente', 'category'=>'Notificações', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Raramente', 'scale_max_label'=>'Quase sempre'],
                ['id'=>'dis2', 'text'=>'Consigo trabalhar/estudar por 20 minutos sem checar redes sociais ou notícias', 'category'=>'Resistência à Distração', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Raramente', 'scale_max_label'=>'Facilmente'],
                ['id'=>'dis3', 'text'=>'Implementarei ao menos uma mudança no meu ambiente digital após este módulo', 'category'=>'Intenção', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Não implementarei', 'scale_max_label'=>'Já estou implementando', 'comment_enabled'=>true],
                ['id'=>'dis4', 'text'=>'Tenho um espaço físico de trabalho/estudo razoavelmente organizado e livre de distrações', 'category'=>'Ambiente Físico', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Nada organizado', 'scale_max_label'=>'Bem organizado'],
            ],
        ], null);

        // ── Seção 3: Hábitos e Sustentabilidade ──────────────────────────────
        $s3 = MemberSection::create([
            'product_id'   => $pid,
            'title'        => 'Hábitos, Rotina e Sustentabilidade',
            'position'     => $secPos++,
            'cover_mode'   => 'vertical',
            'section_type' => 'courses',
        ]);

        $m = MemberModule::create([
            'member_section_id'  => $s3->id,
            'product_id'         => $pid,
            'title'              => 'Construindo Hábitos com o Cérebro TDAH',
            'position'           => 0,
            'show_title_on_cover'=> true,
        ]);
        $this->lesson($m, 0, 'Por Que Hábitos São Tão Difíceis para Quem Tem TDAH', 'text', null, $this->textHabitos(), null, null);
        $this->lesson($m, 1, 'O Loop do Hábito Adaptado — Gatilho, Rotina, Recompensa Imediata', 'video', '', null, null, 840);
        $this->lesson($m, 2, 'Rastreando seu Progresso com Hábitos', 'quiz', null, null, [
            'questions' => [
                ['id'=>'hab1', 'text'=>'Tenho dificuldade em manter novos hábitos por mais de 2 semanas', 'category'=>'Consistência de Hábitos', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Raramente', 'scale_max_label'=>'Quase sempre'],
                ['id'=>'hab2', 'text'=>'Consigo identificar gatilhos que me ajudam a iniciar comportamentos desejados', 'category'=>'Consciência de Gatilhos', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Raramente', 'scale_max_label'=>'Com clareza', 'comment_enabled'=>true],
                ['id'=>'hab3', 'text'=>'Estou trabalhando ativamente na construção de ao menos um novo hábito', 'category'=>'Hábito em Construção', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Não estou', 'scale_max_label'=>'Sim, com consistência'],
                ['id'=>'hab4', 'text'=>'Recompensas imediatas me motivam mais do que recompensas futuras', 'category'=>'Sistema de Recompensa', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Discordo', 'scale_max_label'=>'Concordo totalmente'],
            ],
        ], null);

        $m = MemberModule::create([
            'member_section_id'  => $s3->id,
            'product_id'         => $pid,
            'title'              => 'Seu Plano de Produtividade Personalizado',
            'position'           => 1,
            'show_title_on_cover'=> true,
        ]);
        $this->lesson($m, 0, 'Integrando Tudo — Criando seu Sistema Pessoal de Produtividade', 'text', null, $this->textSistemaPersonalizado(), null, null);
        $this->lesson($m, 1, 'Estudo de Caso — Sistemas que Funcionaram na Prática', 'video', '', null, null, 900);
        $this->lesson($m, 2, 'Avaliação Final do Curso', 'quiz', null, null, [
            'questions' => [
                ['id'=>'final1', 'text'=>'Em comparação ao início do curso, minha produtividade geral melhorou', 'category'=>'Progresso', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Não melhorou', 'scale_max_label'=>'Melhorou significativamente'],
                ['id'=>'final2', 'text'=>'Tenho um sistema de trabalho/estudo que funciona para meu perfil TDAH', 'category'=>'Sistema Funcional', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Não tenho', 'scale_max_label'=>'Tenho e uso regularmente'],
                ['id'=>'final3', 'text'=>'Sinto-me menos sobrecarregado(a) com minhas responsabilidades', 'category'=>'Bem-Estar', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Nenhuma mudança', 'scale_max_label'=>'Muito menos sobrecarregado(a)', 'comment_enabled'=>true],
                ['id'=>'final4', 'text'=>'Estou confiante de que posso manter as estratégias aprendidas a longo prazo', 'category'=>'Autoeficácia', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Nada confiante', 'scale_max_label'=>'Muito confiante'],
                ['id'=>'final5', 'text'=>'Recomendaria este curso para outra pessoa com TDAH', 'category'=>'Satisfação', 'scale_min'=>1, 'scale_max'=>5, 'scale_min_label'=>'Não recomendaria', 'scale_max_label'=>'Recomendaria fortemente'],
            ],
        ], null);
    }

    // =========================================================================
    // Helper
    // =========================================================================
    private function lesson(
        MemberModule $module,
        int $position,
        string $title,
        string $type,
        ?string $contentUrl,
        ?string $contentText,
        mixed $contentFiles,
        ?int $duration
    ): MemberLesson {
        return MemberLesson::create([
            'member_module_id' => $module->id,
            'product_id'       => $module->product_id,
            'title'            => $title,
            'position'         => $position,
            'type'             => $type,
            'content_url'      => $contentUrl ?? '',
            'content_text'     => $contentText,
            'content_files'    => $contentFiles,
            'duration_seconds' => $duration,
            'is_free'          => false,
            'watermark_enabled'=> false,
        ]);
    }

    // =========================================================================
    // Textos (conteúdo HTML das aulas tipo 'text')
    // =========================================================================

    private function textIntroNeuroanatomia(): string
    {
        return '<h2>O Cérebro: Central de Comando do Comportamento</h2>
<p>O cérebro humano adulto pesa cerca de 1,4 kg e contém aproximadamente 86 bilhões de neurônios — cada um podendo fazer até 10.000 conexões sinápticas. Esse órgão extraordinário é responsável por cada pensamento, emoção, memória e comportamento que experimentamos.</p>
<h3>Lobo Frontal e Córtex Pré-Frontal</h3>
<p>O lobo frontal é frequentemente chamado de "CEO do cérebro". O córtex pré-frontal (CPF), sua região mais anterior, é responsável por:</p>
<ul>
<li><strong>Planejamento e tomada de decisão</strong> — avaliar consequências futuras</li>
<li><strong>Controle inibitório</strong> — frear impulsos inadequados</li>
<li><strong>Memória de trabalho</strong> — manter informações "online" por curtos períodos</li>
<li><strong>Flexibilidade cognitiva</strong> — mudar de estratégia quando necessário</li>
</ul>
<p>No TDAH, o desenvolvimento e funcionamento do CPF é frequentemente afetado — o que explica diretamente muitos dos sintomas centrais do transtorno.</p>
<h3>Sistema Límbico — O Centro Emocional</h3>
<p>O sistema límbico engloba estruturas como a amígdala, hipocampo e hipotálamo. A amígdala detecta ameaças e coordena respostas emocionais. O hipocampo é fundamental para a formação de memórias. Compreender essa região é essencial para entender por que emoções e memória são tão interligadas.</p>
<h3>Gânglios da Base e o Sistema de Recompensa</h3>
<p>Os gânglios da base estão profundamente envolvidos no controle motor, aprendizado de hábitos e — crucialmente para o TDAH — no sistema de recompensa dopaminérgico. Quando fazemos algo prazeroso, dopamina é liberada nessa região, criando a sensação de motivação e satisfação.</p>
<p><strong>Conexão com o TDAH:</strong> Alterações no funcionamento dos gânglios da base contribuem para as dificuldades de motivação e a busca por recompensas imediatas típicas do TDAH.</p>';
    }

    private function textNeuroplasticidade(): string
    {
        return '<h2>Neuroplasticidade — Seu Cérebro Não É Fixo</h2>
<p>Por décadas, cientistas acreditaram que o cérebro adulto era imutável — uma estrutura fixa que só poderia deteriorar com o tempo. Essa visão foi completamente revisada. Hoje sabemos que o cérebro mantém uma extraordinária capacidade de mudança ao longo de toda a vida: a <strong>neuroplasticidade</strong>.</p>
<h3>O Que É Neuroplasticidade?</h3>
<p>Neuroplasticidade é a capacidade do cérebro de reorganizar suas conexões neurais em resposta a experiências, aprendizado, ambiente e comportamentos. Isso acontece de várias formas:</p>
<ul>
<li><strong>Potenciação de longa duração (LTP):</strong> conexões entre neurônios se fortalecem com uso repetido — "neurônios que disparam juntos, se conectam juntos"</li>
<li><strong>Poda sináptica:</strong> conexões pouco usadas se enfraquecem e são eliminadas</li>
<li><strong>Neurogênese:</strong> formação de novos neurônios, especialmente no hipocampo</li>
<li><strong>Reorganização cortical:</strong> áreas cerebrais podem assumir novas funções após lesão ou aprendizado intensivo</li>
</ul>
<h3>O Que Isso Significa para o TDAH?</h3>
<p>Para quem tem TDAH, a neuroplasticidade é ao mesmo tempo um desafio e uma oportunidade. O desafio: padrões negativos reforçados ao longo da vida (procrastinação, impulsividade, evitação) criaram circuitos neurais robustos que resistem à mudança. A oportunidade: esses mesmos mecanismos permitem construir novos circuitos com prática consistente.</p>
<p>Estudos mostram que intervenções como TCC, meditação e exercício físico produzem mudanças mensuráveis na estrutura e função cerebral — mesmo em cérebros adultos com TDAH.</p>
<h3>Prática é Inegociável</h3>
<p>A neuroplasticidade não acontece passivamente. Requer prática repetida, atenção focada e — no caso do TDAH — frequentemente recompensas imediatas para manter o engajamento. Cada vez que você pratica uma nova estratégia, mesmo que de forma imperfeita, está literalmente construindo novos caminhos neurais.</p>';
    }

    private function textNeuroimagens(): string
    {
        return '<h2>O Que as Neuroimagens Revelam sobre o TDAH</h2>
<p>Graças aos avanços em tecnologias de neuroimagem como ressonância magnética funcional (fMRI), tomografia por emissão de pósitrons (PET) e eletroencefalografia (EEG), hoje temos evidências irrefutáveis de que o TDAH é uma condição neurológica — não um problema de comportamento ou criação.</p>
<h3>Diferenças de Volume Cerebral</h3>
<p>Meta-análises com milhares de participantes mostram que pessoas com TDAH têm, em média:</p>
<ul>
<li>Volume ligeiramente menor no córtex pré-frontal e nas regiões parieto-temporais</li>
<li>Gânglios da base (especialmente putâmen e caudado) com volume reduzido</li>
<li>Cerebelo levemente menor — relacionado ao timing e coordenação temporal</li>
</ul>
<p>Importante: essas diferenças são em média estatística — não diagnósticas individualmente — e tendem a reduzir com a idade em muitos casos.</p>
<h3>Maturação Cortical Atrasada</h3>
<p>Um estudo clássico do NIMH (Shaw et al., 2007) com 223 crianças mostrou que o córtex de crianças com TDAH atinge a espessura média 3 anos mais tarde que o de crianças neurotípicas. O maior atraso ocorre justamente no córtex pré-frontal — a região do controle executivo.</p>
<h3>Diferenças de Conectividade</h3>
<p>Estudos de fMRI em repouso mostram diferenças na conectividade da <em>Default Mode Network</em> (DMN) — a rede que se ativa quando "divagamos". No TDAH, a DMN frequentemente permanece ativa durante tarefas que exigem foco, interferindo com redes de atenção. Isso ajuda a explicar por que pensamentos aleatórios "invadem" durante atividades importantes.</p>';
    }

    private function textNeurotransmissores(): string
    {
        return '<h2>Dopamina, Noradrenalina e o TDAH</h2>
<p>O TDAH é frequentemente descrito como um transtorno de neurotransmissores — especialmente dopamina e noradrenalina. Entender como esses mensageiros químicos funcionam é fundamental para compreender tanto os sintomas quanto as abordagens de tratamento.</p>
<h3>Dopamina — Muito Mais do que "Prazer"</h3>
<p>A dopamina é popularmente associada ao prazer, mas sua função é mais complexa: ela sinaliza <strong>antecipação de recompensa</strong> e está profundamente envolvida na motivação, aprendizado por reforço e regulação da atenção.</p>
<p>No TDAH, dois problemas dopaminérgicos coexistem:</p>
<ol>
<li><strong>Menor disponibilidade de dopamina</strong> nas sinapses do córtex pré-frontal — reduzindo a capacidade de foco sustentado e controle executivo</li>
<li><strong>Sensibilidade reduzida dos receptores</strong> — o sistema precisa de estímulos mais intensos para "sentir" a recompensa, explicando a busca por novidade e emoção</li>
</ol>
<h3>Noradrenalina — O Sinal de Alerta</h3>
<p>A noradrenalina (ou norepinefrina) é essencial para a vigência, atenção seletiva e resposta a estímulos novos. Ela age em conjunto com a dopamina no córtex pré-frontal para regular as funções executivas. Medicamentos como a atomoxetina funcionam principalmente aumentando a disponibilidade de noradrenalina.</p>
<h3>Por Que Isso Importa na Prática?</h3>
<p>Compreender os mecanismos dopaminérgicos explica por que:</p>
<ul>
<li>Urgência e interesse elevam o foco no TDAH — aumentam liberação de dopamina</li>
<li>Exercício físico melhora temporariamente os sintomas — estimula dopamina e noradrenalina</li>
<li>Estimulantes são eficazes — aumentam a disponibilidade de dopamina e noradrenalina nas sinapses</li>
<li>Estratégias de recompensa imediata funcionam — ativam o sistema dopaminérgico de forma adequada</li>
</ul>';
    }

    private function textMedicacao(): string
    {
        return '<h2>Medicação no TDAH — Desmistificando o Tratamento</h2>
<p>A medicação para TDAH é um dos temas mais cercados de mitos, medos e desinformação. Neste módulo, vamos olhar para o que a ciência realmente diz — sem simplificações nem catastrofismos.</p>
<h3>Classes de Medicamentos</h3>
<p><strong>Estimulantes (primeira linha):</strong></p>
<ul>
<li><em>Metilfenidato</em> (Ritalina, Concerta, Venvanse) — bloqueia a recaptação de dopamina e noradrenalina</li>
<li><em>Anfetaminas</em> (Adderall, Vyvanse) — também aumentam a liberação de dopamina</li>
</ul>
<p><strong>Não-estimulantes:</strong></p>
<ul>
<li><em>Atomoxetina</em> (Strattera) — inibidor seletivo da recaptação de noradrenalina</li>
<li><em>Guanfacina / Clonidina</em> — agonistas alfa-2 adrenérgicos, frequentemente usados em crianças</li>
</ul>
<h3>O Que a Evidência Diz</h3>
<p>Revisões sistemáticas de dezenas de milhares de participantes mostram que estimulantes são o tratamento mais eficaz disponível para TDAH em magnitude de efeito. Isso não significa que funcionam para todos ou que são suficientes sozinhos.</p>
<h3>Medicação Não é a Única Resposta</h3>
<p>A abordagem mais eficaz para TDAH combina medicação (quando apropriado) com intervenções comportamentais, psicoeducação, adaptações ambientais e suporte psicológico. A medicação "abre a janela" — mas quem decide o que fazer com ela é a pessoa, através de estratégias aprendidas.</p>
<p><em>Nota: Decisões sobre medicação devem sempre ser tomadas com um profissional de saúde qualificado. Este módulo tem finalidade educativa.</em></p>';
    }

    private function textIntervencoesNaoFarm(): string
    {
        return '<h2>Intervenções Não-Farmacológicas com Evidência para o TDAH</h2>
<p>Embora a medicação seja frequentemente o tratamento mais discutido, existem várias intervenções não-farmacológicas com evidências científicas sólidas para o TDAH. Algumas funcionam de forma independente; outras potencializam os efeitos da medicação.</p>
<h3>Exercício Físico — O Medicamento Gratuito</h3>
<p>Exercícios aeróbicos de intensidade moderada a alta produzem aumentos imediatos de dopamina, noradrenalina e serotonina — mecanismo similar ao dos estimulantes. Estudos mostram melhora em atenção, memória de trabalho e controle inibitório por até 4 horas após uma sessão de 30 minutos.</p>
<p>Recomendação baseada em evidências: 30 minutos de exercício aeróbico, 3-5x por semana. Para resultados agudos de foco, exercitar-se antes de tarefas cognitivas exigentes maximiza o benefício.</p>
<h3>Terapia Cognitivo-Comportamental (TCC) Adaptada</h3>
<p>A TCC para TDAH (Safren et al.) foca em habilidades organizacionais, gerenciamento de tempo, reestruturação de pensamentos disfuncionais sobre o TDAH e estratégias para lidar com procrastinação. Ensaios clínicos randomizados mostram benefícios significativos — especialmente quando combinada com medicação.</p>
<h3>Psicoeducação</h3>
<p>O simples ato de compreender o TDAH tem impacto terapêutico mensurável. Estudos mostram que psicoeducação reduz a autocrítica, melhora a adesão ao tratamento, diminui sentimentos de vergonha e aumenta a autoeficácia. Não por acaso, você está aqui.</p>
<h3>Mindfulness Baseado em Evidências</h3>
<p>Programas de mindfulness adaptados para TDAH (como o MBCT-A) mostram redução nos sintomas de desatenção e hiperatividade, melhora na regulação emocional e redução no estresse — com tamanho de efeito menor que estimulantes, mas clinicamente significativo e sem efeitos adversos.</p>';
    }

    private function textNeurocienciaEmocoes(): string
    {
        return '<h2>A Neurociência das Emoções</h2>
<p>Emoções não são "fraqueza" ou "exagero" — são processos neurobiológicos sofisticados que evoluíram para nos proteger e orientar comportamentos. Compreender como surgem e como se propagam é o primeiro passo para regulá-las com mais habilidade.</p>
<h3>O Circuito do Medo e da Ameaça</h3>
<p>A amígdala é frequentemente chamada de "alarme de incêndio" do cérebro. Ela processa estímulos emocionalmente relevantes em milissegundos — antes mesmo que o córtex consciente seja notificado. Quando detecta uma ameaça (real ou percebida), dispara uma cascata hormonal que prepara o corpo para luta ou fuga: cortisol, adrenalina, frequência cardíaca elevada.</p>
<p>No TDAH, evidências sugerem que a comunicação entre amígdala e córtex pré-frontal é menos eficiente — o CPF tem mais dificuldade em "colocar o freio" nas respostas emocionais da amígdala.</p>
<h3>Tempo de Recuperação Emocional</h3>
<p>Após uma ativação emocional intensa, o corpo precisa de tempo para retornar à linha de base — o que neurocientistas chamam de "recuperação do sistema nervoso". Em pessoas com TDAH, esse processo tende a ser mais demorado, o que significa que as emoções não apenas chegam com mais intensidade, mas também demoram mais para se dissipar.</p>
<h3>Emoções Como Dados</h3>
<p>Uma estrutura útil: emoções são sinais informativos, não fatos sobre a realidade. Raiva sinaliza que um limite foi violado; tristeza sinaliza perda; ansiedade sinaliza ameaça percebida. O problema surge quando reagimos aos sinais sem processá-los — e esse é o núcleo da desregulação emocional no TDAH.</p>';
    }

    private function textRSD(): string
    {
        return '<h2>Disforia Sensível à Rejeição (RSD) — O Lado Emocional Oculto do TDAH</h2>
<p>A Disforia Sensível à Rejeição, ou RSD (do inglês <em>Rejection Sensitive Dysphoria</em>), é um dos aspectos menos discutidos — e mais impactantes — do TDAH. Descrita e nomeada pelo Dr. William Dodson, a RSD se caracteriza por reações emocionais intensas, súbitas e frequentemente avassaladoras a situações de rejeição percebida ou real.</p>
<h3>O Que Desencadeia a RSD</h3>
<p>A palavra-chave é "percebida". A RSD não requer rejeição real — basta a percepção de que:</p>
<ul>
<li>Alguém não aprovou você ou seu trabalho</li>
<li>Você "decepcionou" alguém importante</li>
<li>Você foi excluído(a), ignorado(a) ou preterido(a)</li>
<li>Você falhou em atingir seus próprios padrões (autocrítica intensa)</li>
</ul>
<h3>Como se Manifesta</h3>
<p>A resposta pode ser explosiva (raiva intensa, reação defensiva) ou implosiva (vergonha avassaladora, retraimento, evitação). Muitas pessoas com TDAH constroem toda a sua vida ao redor de evitar a RSD — recusando oportunidades, relacionamentos e situações que possam provocá-la.</p>
<h3>O Impacto Profundo na Autoestima</h3>
<p>Décadas de críticas (muitas vindas de pessoas que não entendiam o TDAH), falhas e feedbacks negativos constroem uma carga emocional que alimenta a RSD. A boa notícia: a consciência sobre esse mecanismo já começa a reduzir seu impacto. Quando você reconhece "isso é RSD falando", cria-se uma fissura entre o estímulo e a reação automática.</p>';
    }

    private function textRespiracao(): string
    {
        return '<h2>Respiração Consciente — Regulando o Sistema Nervoso de Dentro Para Fora</h2>
<p>A respiração é a única função autônoma do corpo que também pode ser controlada conscientemente — e esse fato tem implicações poderosas para a regulação emocional.</p>
<h3>A Conexão Respiração-Sistema Nervoso</h3>
<p>O sistema nervoso autônomo tem dois modos principais: simpático ("luta ou fuga" — ativação, estresse) e parassimpático ("descanso e digestão" — calma, recuperação). Respirações rápidas e superficiais ativam o simpático; respirações lentas e profundas ativam o parassimpático via nervo vago.</p>
<p>Isso não é metáfora — é fisiologia. Respirar lentamente literalmente muda a química do seu cérebro e corpo em segundos.</p>
<h3>Técnica 4-7-8</h3>
<p>Desenvolvida pelo Dr. Andrew Weil baseada em práticas de pranayama:</p>
<ol>
<li>Expire completamente pelo nariz</li>
<li>Inspire pelo nariz por <strong>4 segundos</strong></li>
<li>Segure o ar por <strong>7 segundos</strong></li>
<li>Expire lentamente pela boca por <strong>8 segundos</strong></li>
<li>Repita 4 ciclos</li>
</ol>
<p>A expiração prolongada (8s) é especialmente eficaz para ativar o nervo vago e o sistema parassimpático.</p>
<h3>Respiração Diafragmática</h3>
<p>A maioria das pessoas respira apenas com o tórax superior. A respiração diafragmática (abdominal) engaja o diafragma — um músculo que, quando se move, estimula o nervo vago diretamente. Coloque uma mão no abdômen: ele deve se expandir na inspiração e contrair na expiração. Se só o peito se move, você está respirando superficialmente.</p>
<h3>Para o Cérebro TDAH</h3>
<p>O desafio para quem tem TDAH é lembrar de usar a respiração <em>no momento em que é necessária</em>. Estratégias: programar lembretes, associar a gatilhos existentes (toda vez que vai checar o celular, 3 respirações antes), ou usar apps com timer de respiração.</p>';
    }

    private function textModeloABC(): string
    {
        return '<h2>O Modelo ABC da TCC — Entendendo Como Pensamentos Criam Emoções</h2>
<p>O modelo ABC é um dos conceitos centrais da Terapia Cognitivo-Comportamental e uma das ferramentas mais práticas para entender por que reagimos emocionalmente da forma que reagimos.</p>
<h3>A → B → C</h3>
<ul>
<li><strong>A (Activating event / Evento ativador):</strong> A situação objetiva que aconteceu</li>
<li><strong>B (Belief / Crença):</strong> O pensamento automático, interpretação ou crença que você ativou sobre o evento</li>
<li><strong>C (Consequence / Consequência):</strong> A emoção e o comportamento resultantes</li>
</ul>
<p>O insight central: <strong>não é A que causa C, mas B</strong>. Duas pessoas podem vivenciar o mesmo evento (A) e ter reações emocionais completamente diferentes (C) — porque ativaram crenças diferentes (B).</p>
<h3>Exemplo Prático com TDAH</h3>
<p><strong>A:</strong> Chego atrasado(a) 20 minutos para uma reunião importante</p>
<p><strong>B (disfuncional):</strong> "Sou irresponsável e incapaz. Todo mundo acha que não presto. Nunca vou mudar."</p>
<p><strong>C:</strong> Vergonha avassaladora, quero sumir, possível crise de RSD</p>
<p><strong>B (mais equilibrado):</strong> "Cheguei atrasado — é desagradável e preciso melhorar. Mas um atraso não define quem sou."</p>
<p><strong>C:</strong> Desconforto moderado, motivação para ajustar estratégias, sem crise emocional</p>
<h3>Distorções Cognitivas Comuns no TDAH</h3>
<ul>
<li><strong>Catastrofização:</strong> "Isso vai arruinar tudo"</li>
<li><strong>Leitura mental:</strong> "Tenho certeza de que ele acha que sou incompetente"</li>
<li><strong>Tudo ou nada:</strong> "Se não fiz perfeito, falhei completamente"</li>
<li><strong>Personalização:</strong> "É sempre minha culpa"</li>
<li><strong>Filtro negativo:</strong> Ignorar o que deu certo e focar apenas no erro</li>
</ul>';
    }

    private function textMindfulness(): string
    {
        return '<h2>Mindfulness e TDAH — Por Que a Versão Clássica Não Funciona (e Como Adaptar)</h2>
<p>A prática de mindfulness, com sua promessa de "esvaziar a mente" e "viver o presente", soa ao mesmo tempo atraente e frustrante para quem tem TDAH. Atraente porque o presente é exatamente onde o cérebro TDAH existe; frustrante porque sessões longas e silenciosas podem ser um verdadeiro tormento.</p>
<h3>O Que a Ciência Mostra</h3>
<p>Estudos com programas de mindfulness adaptados para TDAH (Zylowska et al., 2008; Mitchell et al., 2017) mostram reduções mensuráveis em sintomas de desatenção e hiperatividade, além de melhoras em regulação emocional e bem-estar. O mecanismo: prática de mindfulness fortalece o córtex pré-frontal e a conexão PFC-amígdala — exatamente as regiões mais afetadas no TDAH.</p>
<h3>Adaptações para o TDAH</h3>
<p>A versão "padrão" de mindfulness (sentar em silêncio por 20-40 minutos) é frequentemente impossível no início. Adaptações eficazes incluem:</p>
<ul>
<li><strong>Micropráticas de 2-5 minutos</strong> — mais fáceis de iniciar e sustentar</li>
<li><strong>Mindfulness em movimento</strong> — caminhada consciente, yoga, tai chi</li>
<li><strong>Mindfulness ativo</strong> — prestar atenção plena a uma atividade rotineira (lavar louça, tomar banho)</li>
<li><strong>Âncoras sensoriais</strong> — focar em sons, textura ou temperatura em vez de "pensamentos"</li>
<li><strong>Apps com timers e guias</strong> — estrutura externa que o TDAH precisa</li>
</ul>
<h3>A Prática É Sobre Notar, Não Sobre Silêncio</h3>
<p>O núcleo de mindfulness não é "parar de pensar" — é notar quando a mente divagou e gentilmente retornar. Para o TDAH, a mente vai divagar frequentemente. Isso não é fracasso — é a prática. Cada retorno é como uma "rosca" no músculo atencional.</p>';
    }

    private function textRelacionamentos(): string
    {
        return '<h2>TDAH e Relacionamentos — Padrões que Aparecem Sem Intenção</h2>
<p>O TDAH raramente impacta apenas quem o tem. Suas manifestações — impulsividade, esquecimentos, hiperfoco, desregulação emocional — criam padrões relacionais que frequentemente causam mal-entendidos, mágoas e conflitos, mesmo quando não existe nenhuma má intenção.</p>
<h3>Padrões Comuns</h3>
<p><strong>Esquecimentos e promessas não cumpridas:</strong> Esquecer compromissos, datas importantes ou conversas inteiras é interpretado como descaso — quando na verdade é a memória de trabalho falhando.</p>
<p><strong>Interrupções:</strong> A impulsividade faz com que pensamentos precisem ser verbalizados imediatamente antes de "desaparecerem" — o que parece falta de respeito para quem está sendo interrompido.</p>
<p><strong>Hiperfoco e "desaparecimento":</strong> Quando em hiperfoco, a pessoa com TDAH pode parecer completamente desinteressada nos outros — inclusive em parceiros(as) e filhos(as).</p>
<p><strong>Reatividade emocional:</strong> Respostas desproporcionais a situações menores criam um ambiente de "pisando em ovos" para as pessoas próximas.</p>
<h3>O Ciclo Crítico-Reativo</h3>
<p>Um ciclo muito comum: parceiro/familiar critica comportamento ligado ao TDAH → pessoa com TDAH reage com defensividade ou vergonha → parceiro se frustra com a reação → ciclo se repete e intensifica. Compreender que <strong>ambos os lados estão reagindo de forma compreensível</strong> é o ponto de partida para quebrar o ciclo.</p>
<h3>Comunicação Como Ferramenta</h3>
<p>A psicoeducação compartilhada — quando a pessoa próxima também entende o TDAH — transforma radicalmente a dinâmica. Substituir "você não liga para mim" por "quando você esquece, eu me sinto invisible" muda completamente o tom da conversa e abre espaço para soluções práticas.</p>';
    }

    private function textPlanoRegulacao(): string
    {
        return '<h2>Criando Seu Plano de Regulação Emocional</h2>
<p>Regulação emocional não acontece no caos — ela se constrói em momentos de calma, antes que a crise chegue. Este módulo é sobre criar seu plano pessoal: um conjunto de estratégias que você sabe que funcionam para você, pronto para ser acionado quando necessário.</p>
<h3>Os 4 Níveis de Intervenção</h3>
<p><strong>Nível 1 — Prevenção:</strong> Hábitos que mantêm o sistema nervoso regulado no dia a dia (sono, exercício, alimentação, limites de tecnologia). São a base. Sem eles, qualquer estratégia de crise fica fragilizada.</p>
<p><strong>Nível 2 — Sinais Precoces:</strong> Aprender a reconhecer os primeiros sinais de desregulação (tensão no pescoço, irritabilidade crescente, pensamentos acelerados) antes que a emoção tome conta. Quanto mais cedo você intervém, mais fácil é regular.</p>
<p><strong>Nível 3 — Estratégias de Crise:</strong> Técnicas para usar no pico emocional (respiração, saída física do ambiente, contagem, ancoragem sensorial). Devem ser simples — em crise, ninguém executa técnicas complexas.</p>
<p><strong>Nível 4 — Processamento Pós-Crise:</strong> Após se acalmar, voltar ao evento de forma curiosa, não punitiva. O que disparou? O que você pode aprender? O que poderia fazer diferente?</p>
<h3>Seu Plano Pessoal</h3>
<p>Não existe plano universal. O que funciona para uma pessoa pode não funcionar para outra. Seu plano deve ser baseado no que você já experimentou funcionar — e ir sendo refinado com o tempo. Escreva-o, torne-o concreto e o tenha acessível. Em crise, o cérebro não improvisa bem.</p>';
    }

    private function textHiperfoco(): string
    {
        return '<h2>O Paradoxo do Hiperfoco — Quando o Foco É Demais</h2>
<p>Um dos maiores paradoxos do TDAH: a mesma pessoa que não consegue prestar atenção por 5 minutos numa tarefa "importante" pode passar 8 horas absorta num jogo, livro ou projeto criativo sem perceber o tempo passar. Isso é o hiperfoco.</p>
<h3>O Que É Hiperfoco</h3>
<p>Hiperfoco é um estado de concentração tão intensa que filtros normais de atenção parecem desligar. Sons ao redor somem, fome desaparece, chamados são ignorados. A pessoa literalmente não consegue desviar a atenção — mesmo que queira.</p>
<p>Apesar do nome soar como uma habilidade, o hiperfoco no TDAH é frequentemente mal regulado: acontece quando não é desejado (no interesse, não na prioridade) e é difícil de ser encerrado voluntariamente.</p>
<h3>A Neurologia do Hiperfoco</h3>
<p>O Dr. William Dodson descreve o sistema de ativação do TDAH como guiado por interesse, desafio, novidade, urgência e paixão. Quando um estímulo aciona esses fatores, dopamina é liberada em quantidade suficiente para sustentar foco prolongado. O problema: esse sistema não responde a "importância" ou "responsabilidade" — apenas a ativadores emocionais e motivacionais.</p>
<h3>Usando o Hiperfoco a seu Favor</h3>
<p>Compreender seus ativadores pessoais de hiperfoco é poderoso. Você pode:</p>
<ul>
<li>Tornar tarefas mais interessantes adicionando elementos de desafio ou novidade</li>
<li>Usar a urgência artificialmente (timer, deadline criado, acordo com alguém)</li>
<li>Planejar o hiperfoco — dedicar blocos para projetos de alto interesse quando a energia está disponível</li>
<li>Criar "saídas de emergência" — alarmes para encerrar o hiperfoco quando necessário</li>
</ul>';
    }

    private function textTimeBlindness(): string
    {
        return '<h2>Time Blindness — Por Que o Tempo Parece Não Existir no TDAH</h2>
<p>Russell Barkley, um dos principais pesquisadores do TDAH, descreve o transtorno como fundamentalmente um "distúrbio da perspectiva de tempo". Pessoas com TDAH não experimentam o tempo da mesma forma que pessoas neurotípicas — uma diferença com consequências enormes no dia a dia.</p>
<h3>Dois Tempos: Agora e Não-Agora</h3>
<p>Barkley propõe que o TDAH cria uma percepção binária do tempo: existe apenas "agora" e "não-agora". O futuro — mesmo que seja daqui a 10 minutos — não tem a mesma realidade que o presente imediato. Isso explica:</p>
<ul>
<li>Por que prazos não motivam até que se tornem iminentes</li>
<li>Por que "vou fazer depois" raramente acontece — o "depois" não existe</li>
<li>Por que tarefas longas com recompensas futuras são extremamente difíceis de iniciar</li>
</ul>
<h3>Impactos Práticos</h3>
<p>A time blindness se manifesta de formas variadas:</p>
<ul>
<li><strong>Subestimativa de tempo:</strong> "Leva 10 minutos" quando leva 40</li>
<li><strong>Chegadas atrasadas:</strong> Mesmo com planejamento consciente</li>
<li><strong>Procrastinação:</strong> A tarefa "existe" apenas quando o prazo está "agora"</li>
<li><strong>Dificuldade com rotinas:</strong> Sequências temporais são difíceis de internalizar</li>
</ul>
<h3>Tornando o Tempo Visível</h3>
<p>A solução para time blindness não é "tentar mais" — é criar sistemas externos que tornem o tempo visível e concreto:</p>
<ul>
<li><strong>Relógio analógico</strong> em local visível — mostra visualmente quanto tempo resta</li>
<li><strong>Time timer</strong> — timer visual que mostra o tempo "sumindo"</li>
<li><strong>Alarmes com margens</strong> — "preciso sair às 14h" → alarme às 13h30</li>
<li><strong>Estimativas com buffer</strong> — multiplique sua estimativa por 2,5 para checar a realidade</li>
</ul>';
    }

    private function textPomodoro(): string
    {
        return '<h2>Técnica Pomodoro Adaptada — Produtividade em Sprints para o TDAH</h2>
<p>A técnica Pomodoro foi criada por Francesco Cirillo nos anos 1980 e consiste em trabalhar em blocos de 25 minutos com pausas de 5 minutos. Simples e eficaz — mas frequentemente problemática para o TDAH na versão original.</p>
<h3>Por Que o Pomodoro Clássico Falha no TDAH</h3>
<ul>
<li>25 minutos pode ser longo demais para manter foco sem distrações</li>
<li>A pausa de 5 minutos pode ser curta demais para "resetar" o sistema</li>
<li>Interromper o hiperfoco no momento de máxima produção é frustante e contraproducente</li>
<li>A rigidez do sistema conflita com o funcionamento variável do TDAH</li>
</ul>
<h3>Versão Adaptada para o TDAH</h3>
<p><strong>Bloco de trabalho:</strong> 15-20 minutos (não 25) — mais fácil de iniciar, mantém urgência<br>
<strong>Pausa ativa:</strong> 5-10 minutos com movimento real (não rolar redes sociais)<br>
<strong>Flexibilidade no hiperfoco:</strong> Se estiver em hiperfoco produtivo, ignore o timer até a próxima pausa natural<br>
<strong>Micro-recompensas:</strong> Após cada ciclo completo, pequena recompensa imediata (café, música favorita por 2 min)</p>
<h3>O Papel do Timer</h3>
<p>Para o TDAH, o timer não é apenas organizacional — é motivacional. Ele cria urgência artificial, um dos poucos combustíveis confiáveis do sistema de ativação TDAH. "Preciso terminar antes do timer" é muito mais motivador que "preciso terminar porque é importante".</p>
<h3>Ferramentas</h3>
<p>Apps recomendados: Forest (gamifica a produtividade), Focus@Will (música para foco), Be Focused, ou simplesmente o timer do celular. Importante: o timer deve fazer um som audível — não apenas vibração, que é fácil de ignorar.</p>';
    }

    private function textPlanejamento(): string
    {
        return '<h2>Sistemas de Planejamento que Funcionam para Cérebros TDAH</h2>
<p>A maioria dos sistemas de produtividade foi criada por — e para — pessoas sem TDAH. Sistemas como GTD (Getting Things Done) são excelentes, mas sua complexidade frequentemente paralisa quem tem TDAH antes mesmo de começar. Aqui apresentamos uma abordagem adaptada.</p>
<h3>O Brain Dump</h3>
<p>O primeiro passo de qualquer sistema eficaz para TDAH é o brain dump: jogar tudo que está na cabeça para fora — em papel, app ou quadro branco. Tudo: tarefas, preocupações, ideias, recados, planos. O cérebro TDAH usa capacidade preciosa de memória de trabalho para "não esquecer" coisas — o brain dump libera esse espaço.</p>
<p>Frequência ideal: diário (1-2 minutos) e semanal (10-15 minutos).</p>
<h3>Matriz de Prioridade Simples</h3>
<p>Após o brain dump, categorize cada item:</p>
<ul>
<li><strong>Faça agora:</strong> urgente + importante</li>
<li><strong>Agende:</strong> não urgente + importante</li>
<li><strong>Delegue:</strong> urgente + não importante</li>
<li><strong>Elimine:</strong> não urgente + não importante</li>
</ul>
<h3>O Planejamento Semanal — A Âncora da Semana</h3>
<p>Reserve 15-30 minutos (preferencialmente domingo ou segunda cedo) para:</p>
<ol>
<li>Revisar o que aconteceu na semana anterior (sem autocrítica — apenas observação)</li>
<li>Listar os 3 objetivos mais importantes da semana</li>
<li>Distribuir blocos de tempo para esses objetivos no calendário</li>
<li>Identificar potenciais obstáculos e planejar como contorná-los</li>
</ol>
<h3>Simplicidade é Sustentabilidade</h3>
<p>Para o TDAH, um sistema simples que você usa todos os dias vence um sistema perfeito que você usa raramente. Se seu sistema de planejamento leva mais de 5 minutos por dia, ele provavelmente é complexo demais para ser mantido.</p>';
    }

    private function textDistracoes(): string
    {
        return '<h2>O Ambiente Digital e o Cérebro TDAH — Uma Batalha Assimétrica</h2>
<p>As grandes plataformas de tecnologia empregam equipes de especialistas em psicologia do comportamento, neurociência e design de produto com um único objetivo: maximizar o tempo que você passa em suas plataformas. Para um cérebro TDAH — já propenso a buscar novidade e estimulação — essa batalha é genuinamente assimétrica.</p>
<h3>Como as Plataformas Sequestram a Atenção</h3>
<ul>
<li><strong>Scroll infinito:</strong> Elimina o "final natural" do conteúdo, mantendo você sempre em busca do próximo item</li>
<li><strong>Notificações intermitentes:</strong> A imprevisibilidade da recompensa (quando vai chegar a notificação?) é o mecanismo mais poderoso de condicionamento comportamental — o mesmo usado em máquinas caça-níqueis</li>
<li><strong>Likes e interações sociais:</strong> Ativam dopamina e RSD simultaneamente — uma combinação poderosa</li>
<li><strong>Autoplay:</strong> Elimina a necessidade de decisão ativa para continuar consumindo</li>
</ul>
<h3>Configuração de Ambiente Digital para Foco</h3>
<p><strong>No celular:</strong></p>
<ul>
<li>Desativar todas as notificações exceto chamadas e mensagens urgentes</li>
<li>Remover apps de redes sociais da tela inicial (aumenta o atrito para abri-los)</li>
<li>Usar modo foco/não perturbe durante blocos de trabalho</li>
<li>Considerar apps bloqueadores (Freedom, AppBlock) para períodos críticos</li>
</ul>
<p><strong>No computador:</strong></p>
<ul>
<li>Extensões bloqueadoras de sites (Cold Turkey, StayFocusd)</li>
<li>Desativar pop-ups de e-mail — checar em horários programados</li>
<li>Abas específicas para cada bloco de trabalho — fechar o restante</li>
</ul>';
    }

    private function textHabitos(): string
    {
        return '<h2>Por Que Hábitos São Tão Difíceis para Quem Tem TDAH — e O Que Fazer</h2>
<p>A formação de hábitos depende de um processo de aprendizado que ocorre principalmente nos gânglios da base — mas requer consistência repetida para se consolidar. Para o TDAH, dois problemas fundamentais complicam esse processo: dificuldade de iniciação e sensibilidade reduzida a recompensas atrasadas.</p>
<h3>O Loop do Hábito</h3>
<p>Charles Duhigg descreve o hábito como um loop de três partes:</p>
<ul>
<li><strong>Gatilho:</strong> Um sinal que aciona o comportamento automático</li>
<li><strong>Rotina:</strong> O comportamento em si</li>
<li><strong>Recompensa:</strong> O benefício que reforça o loop</li>
</ul>
<p>Para o TDAH, o elo mais fraco é a <strong>recompensa</strong>: se ela é abstrata ("saúde no futuro"), distante ou não sentida imediatamente, o loop não se consolida.</p>
<h3>Adaptações para o TDAH</h3>
<p><strong>1. Recompensas imediatas e concretas:</strong> Não "vou me sentir melhor com saúde". Em vez disso: "Após meditar, tomo o café especial que gosto." A recompensa deve ocorrer imediatamente após o comportamento.</p>
<p><strong>2. Hábitos âncora:</strong> Conectar o novo hábito a algo já estabelecido. "Depois que escovo os dentes (âncora), faço 5 minutos de respiração consciente (hábito novo)."</p>
<p><strong>3. Dose mínima viável:</strong> Torne o hábito tão pequeno que recusá-lo parece ridículo. Não "meditar 30 minutos". Em vez disso: "sentar e respirar por 2 minutos". A consistência importa mais que a duração.</p>
<p><strong>4. Rastreamento visual:</strong> Um calendário onde você marca cada dia cumprido cria recompensa imediata ("não quebrar a corrente") e torna o progresso visível — algo que o cérebro TDAH precisa para manter engajamento.</p>';
    }

    private function textSistemaPersonalizado(): string
    {
        return '<h2>Integrando Tudo — Criando Seu Sistema Pessoal de Produtividade</h2>
<p>Chegamos ao módulo final com um objetivo claro: não existe sistema perfeito e universal. Existe o sistema que funciona para você — e ele precisa ser construído a partir do autoconhecimento, experimentação e iteração.</p>
<h3>Os Pilares do Seu Sistema</h3>
<p><strong>1. Captura confiável</strong> — Um lugar (e apenas um) onde tudo vai: ideias, tarefas, compromissos. Pode ser um caderno, um app, um quadro branco. O critério: você deve confiá-lo completamente. Se tem dúvida se anotou, o sistema falhou.</p>
<p><strong>2. Revisão regular</strong> — O sistema morre sem revisão. Diária (5 min): o que é urgente hoje? Semanal (15 min): estou indo na direção certa? Mensal (30 min): o que precisa mudar?</p>
<p><strong>3. Blocos de tempo protegidos</strong> — Suas tarefas mais importantes precisam de espaço no calendário. Se não estão no calendário, provavelmente não vão acontecer.</p>
<p><strong>4. Ritmo de trabalho ajustado</strong> — Qual é sua janela de foco natural? Manhã, tarde, noite? Quantos minutos de trabalho antes de precisar de pausa? Respeitar seu ritmo biológico não é preguiça — é estratégia.</p>
<h3>Iteração, Não Perfeição</h3>
<p>Seu sistema vai falhar. Haverá semanas onde tudo desmorona. A diferença entre quem tem sistema e quem não tem não é que o sistema nunca falha — é que quem tem sistema sabe como reiniciar.</p>
<p>A métrica mais importante não é "segui o sistema perfeitamente?" mas "consegui retomar após a pausa?" Resiliência supera perfeição, sempre.</p>';
    }
}
