<?php

namespace Database\Seeders;

use App\Models\Journey;
use App\Models\JourneyStep;
use App\Models\JourneyStepItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class JourneyDemoSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId  = 1;
        $startPos  = (Journey::forTenant($tenantId)->max('position') ?? -1) + 1;

        $journeys = [

            // ─── 1. Entendendo o TDAH ─────────────────────────────────────────
            [
                'name'        => 'Entendendo o TDAH',
                'description' => 'Uma jornada para compreender o que é o TDAH, como ele funciona no cérebro e como se manifesta ao longo da vida.',
                'steps' => [
                    [
                        'title'        => 'O Que É o TDAH?',
                        'description'  => 'Fundamentos sobre o transtorno — neurobiologia, critérios diagnósticos e mitos comuns.',
                        'unlock_type'  => 'livre',
                        'items' => [
                            [
                                'type'  => 'video',
                                'title' => 'Introdução ao TDAH — Neurobiologia e Diagnóstico',
                                'data'  => ['url' => ''],
                            ],
                            [
                                'type'  => 'quiz',
                                'title' => 'Mitos e Verdades sobre o TDAH',
                                'data'  => [
                                    'question' => 'Qual das afirmações abaixo sobre o TDAH é CORRETA?',
                                    'options'  => [
                                        ['text' => 'O TDAH é resultado de falta de disciplina ou criação inadequada', 'is_correct' => false],
                                        ['text' => 'O TDAH tem base neurobiológica comprovada, com diferenças estruturais e funcionais no cérebro', 'is_correct' => true],
                                        ['text' => 'O TDAH desaparece espontaneamente na vida adulta', 'is_correct' => false],
                                        ['text' => 'O TDAH afeta somente crianças do sexo masculino', 'is_correct' => false],
                                    ],
                                    'explanation' => 'O TDAH é um transtorno do neurodesenvolvimento com forte base genética. Neuroimagens mostram diferenças no volume e ativação do córtex pré-frontal. Persiste na vida adulta em cerca de 60–70% dos casos diagnosticados na infância.',
                                ],
                            ],
                            [
                                'type'  => 'question',
                                'title' => 'Reflexão Inicial',
                                'data'  => [
                                    'question'    => 'Antes de aprofundar seus conhecimentos, descreva como o TDAH se manifesta na sua vida no dia a dia. Quais situações você percebe como mais desafiadoras?',
                                    'placeholder' => 'Escreva livremente sobre sua experiência — não há resposta certa ou errada aqui.',
                                ],
                            ],
                        ],
                    ],
                    [
                        'title'        => 'Funções Executivas',
                        'description'  => 'Entenda o papel das funções executivas e por que elas são tão impactadas no TDAH.',
                        'unlock_type'  => 'sequencial',
                        'items' => [
                            [
                                'type'  => 'video',
                                'title' => 'Funções Executivas — O Painel de Controle do Cérebro',
                                'data'  => ['url' => ''],
                            ],
                            [
                                'type'  => 'quiz',
                                'title' => 'Identificando Dificuldades Executivas',
                                'data'  => [
                                    'question' => 'Qual das opções NÃO é considerada uma função executiva central segundo Miyake et al.?',
                                    'options'  => [
                                        ['text' => 'Memória de trabalho', 'is_correct' => false],
                                        ['text' => 'Controle inibitório', 'is_correct' => false],
                                        ['text' => 'Flexibilidade cognitiva', 'is_correct' => false],
                                        ['text' => 'Memória episódica de longo prazo', 'is_correct' => true],
                                    ],
                                    'explanation' => 'As três funções executivas centrais são: memória de trabalho, controle inibitório e flexibilidade cognitiva. A memória episódica de longo prazo é um sistema de memória distinto, não classificado como função executiva central.',
                                ],
                            ],
                            [
                                'type'  => 'question',
                                'title' => 'Seu Perfil Executivo',
                                'data'  => [
                                    'question'    => 'Das funções executivas (planejamento, iniciação de tarefas, memória de trabalho, controle inibitório, flexibilidade, regulação emocional), quais você percebe como seus maiores desafios? Dê exemplos concretos do seu cotidiano.',
                                    'placeholder' => 'Ex: Tenho muita dificuldade com iniciação — começo várias coisas mas não consigo terminar...',
                                ],
                            ],
                        ],
                    ],
                    [
                        'title'        => 'TDAH e Desregulação Emocional',
                        'description'  => 'A dimensão emocional do TDAH — frequentemente subestimada e mal compreendida.',
                        'unlock_type'  => 'sequencial',
                        'items' => [
                            [
                                'type'  => 'video',
                                'title' => 'TDAH e Emoções — A Tempestade Interior',
                                'data'  => ['url' => ''],
                            ],
                            [
                                'type'  => 'quiz',
                                'title' => 'Reconhecendo Padrões Emocionais',
                                'data'  => [
                                    'question' => 'O que é "Disforia Sensível à Rejeição" (RSD) no contexto do TDAH?',
                                    'options'  => [
                                        ['text' => 'Uma fobia social comum em pessoas neurotípicas', 'is_correct' => false],
                                        ['text' => 'Uma resposta emocional intensa e instantânea à percepção de rejeição ou crítica', 'is_correct' => true],
                                        ['text' => 'Um distúrbio de personalidade separado do TDAH', 'is_correct' => false],
                                        ['text' => 'Um sintoma exclusivo do tipo hiperativo', 'is_correct' => false],
                                    ],
                                    'explanation' => 'A RSD (Rejection Sensitive Dysphoria) é caracterizada por reações emocionais avassaladoras a situações de rejeição percebida. É extremamente comum no TDAH e pode causar sofrimento intenso, impactando relacionamentos e autoestima de forma desproporcional ao evento.',
                                ],
                            ],
                            [
                                'type'  => 'question',
                                'title' => 'Mapeando Seus Gatilhos',
                                'data'  => [
                                    'question'    => 'Descreva uma situação recente em que você sentiu uma reação emocional intensa que pareceu desproporcional ao acontecimento. O que desencadeou? Como você reagiu? Como se sentiu depois?',
                                    'placeholder' => 'Seja específico e honesto — este espaço é seguro para reflexão.',
                                ],
                            ],
                        ],
                    ],
                    [
                        'title'        => 'TDAH ao Longo da Vida',
                        'description'  => 'Como o TDAH se transforma da infância à vida adulta e o que isso significa para você hoje.',
                        'unlock_type'  => 'sequencial',
                        'items' => [
                            [
                                'type'  => 'video',
                                'title' => 'TDAH na Infância, Adolescência e Vida Adulta — O Que Muda',
                                'data'  => ['url' => ''],
                            ],
                            [
                                'type'  => 'quiz',
                                'title' => 'TDAH no Adulto',
                                'data'  => [
                                    'question' => 'No TDAH adulto, qual sintoma frequentemente se transforma em relação à infância?',
                                    'options'  => [
                                        ['text' => 'A hiperatividade motora aumenta significativamente', 'is_correct' => false],
                                        ['text' => 'A hiperatividade se interioriza — surge como agitação mental e impulsividade cognitiva', 'is_correct' => true],
                                        ['text' => 'O déficit de atenção desaparece completamente', 'is_correct' => false],
                                        ['text' => 'Os sintomas se manifestam igualmente em todas as fases', 'is_correct' => false],
                                    ],
                                    'explanation' => 'No adulto, a hiperatividade motora visível da infância tende a se transformar em inquietação interior, pensamento acelerado e impulsividade nas decisões e na fala. Isso frequentemente dificulta o diagnóstico tardio, especialmente em mulheres.',
                                ],
                            ],
                            [
                                'type'  => 'question',
                                'title' => 'Sua História com o TDAH',
                                'data'  => [
                                    'question'    => 'Olhando para trás, como você percebe que o TDAH impactou diferentes fases da sua vida? Que padrões você reconhece agora que fazem mais sentido com este conhecimento?',
                                    'placeholder' => 'Reflita sobre escola, trabalho, relacionamentos, autoestima — o que faz mais sentido agora?',
                                ],
                            ],
                        ],
                    ],
                ],
            ],

            // ─── 2. Foco e Produtividade ──────────────────────────────────────
            [
                'name'        => 'Foco e Produtividade com TDAH',
                'description' => 'Estratégias práticas e adaptadas para conquistar foco, organização e produtividade sustentável — sem lutar contra o próprio cérebro.',
                'steps' => [
                    [
                        'title'        => 'Como o Foco Funciona no Cérebro TDAH',
                        'description'  => 'Entenda por que o foco é tão difícil — e por que às vezes parece impossível de controlar.',
                        'unlock_type'  => 'livre',
                        'items' => [
                            [
                                'type'  => 'video',
                                'title' => 'Dopamina, Recompensa e o Paradoxo do Foco no TDAH',
                                'data'  => ['url' => ''],
                            ],
                            [
                                'type'  => 'quiz',
                                'title' => 'O Sistema de Foco do TDAH',
                                'data'  => [
                                    'question' => 'Por que pessoas com TDAH frequentemente conseguem hiperfoco em atividades de interesse, mas não conseguem focar em tarefas importantes?',
                                    'options'  => [
                                        ['text' => 'Porque são preguiçosas e escolhem deliberadamente o que é mais fácil', 'is_correct' => false],
                                        ['text' => 'Porque o TDAH cria um sistema de foco guiado por interesse e novidade, não por importância ou urgência percebida', 'is_correct' => true],
                                        ['text' => 'Porque o hiperfoco é uma habilidade treinada e consciente', 'is_correct' => false],
                                        ['text' => 'Porque o TDAH só afeta tarefas acadêmicas', 'is_correct' => false],
                                    ],
                                    'explanation' => 'O Dr. William Dodson descreve o sistema de ativação do TDAH como movido por interesse, desafio, novidade, urgência e paixão — não por importância, recompensas futuras ou prioridades externas. Isso explica o hiperfoco em games e a dificuldade em relatórios.',
                                ],
                            ],
                            [
                                'type'  => 'question',
                                'title' => 'Seu Perfil de Foco',
                                'data'  => [
                                    'question'    => 'Em quais tipos de atividades você consegue manter foco naturalmente? O que essas atividades têm em comum? (Pense em: desafio, novidade, interesse pessoal, prazo, competição, urgência)',
                                    'placeholder' => 'Identifique seus "combustíveis de foco" pessoais — eles revelam muito sobre como acionar seu cérebro.',
                                ],
                            ],
                        ],
                    ],
                    [
                        'title'        => 'Técnica Pomodoro Adaptada para o TDAH',
                        'description'  => 'A técnica Pomodoro clássica modificada para funcionar com o ritmo real do cérebro TDAH.',
                        'unlock_type'  => 'sequencial',
                        'items' => [
                            [
                                'type'  => 'video',
                                'title' => 'Pomodoro para TDAH — Adaptando Intervalos, Ciclos e Tipos de Pausa',
                                'data'  => ['url' => ''],
                            ],
                            [
                                'type'  => 'quiz',
                                'title' => 'Pomodoro Adaptado — Conceitos Chave',
                                'data'  => [
                                    'question' => 'Ao adaptar o Pomodoro para o TDAH, qual ajuste é frequentemente mais eficaz?',
                                    'options'  => [
                                        ['text' => 'Aumentar os blocos para 90 minutos para maximizar a produção', 'is_correct' => false],
                                        ['text' => 'Reduzir os blocos para 15–20 minutos com pausas ativas de 5 minutos', 'is_correct' => true],
                                        ['text' => 'Eliminar as pausas para não perder o ritmo quando o foco finalmente chega', 'is_correct' => false],
                                        ['text' => 'Fazer apenas 1 bloco por dia para não sobrecarregar o sistema', 'is_correct' => false],
                                    ],
                                    'explanation' => 'Cérebros com TDAH frequentemente trabalham melhor em sprints curtos (15–25 min) com pausas ativas (movimento, hidratação, respiração). O timer cria urgência artificial — um dos combustíveis naturais do sistema TDAH — tornando a tarefa mais "ativável".',
                                ],
                            ],
                            [
                                'type'  => 'question',
                                'title' => 'Seu Plano Pomodoro',
                                'data'  => [
                                    'question'    => 'Como você vai implementar a técnica Pomodoro na sua rotina esta semana? Defina: duração dos seus blocos, tipo de pausa que fará, em quais tarefas vai usar e horário do dia que pretende testar.',
                                    'placeholder' => 'Seja específico — um plano vago raramente é executado. "Amanhã cedo" não é um horário.',
                                ],
                            ],
                        ],
                    ],
                    [
                        'title'        => 'Organizando Seu Ambiente para o Foco',
                        'description'  => 'O ambiente externo influencia diretamente a capacidade de foco — especialmente para quem tem TDAH.',
                        'unlock_type'  => 'sequencial',
                        'items' => [
                            [
                                'type'  => 'video',
                                'title' => 'Criando Ambientes que Trabalham Para Você, não Contra Você',
                                'data'  => ['url' => ''],
                            ],
                            [
                                'type'  => 'quiz',
                                'title' => 'Ambiente e TDAH',
                                'data'  => [
                                    'question' => 'Qual afirmação sobre som e ambiente de trabalho no TDAH é mais precisa?',
                                    'options'  => [
                                        ['text' => 'Silêncio absoluto é sempre a melhor condição para foco no TDAH', 'is_correct' => false],
                                        ['text' => 'Barulho branco ou música instrumental podem aumentar o foco em algumas pessoas com TDAH', 'is_correct' => true],
                                        ['text' => 'Música com letra não atrapalha a concentração em pessoas com TDAH', 'is_correct' => false],
                                        ['text' => 'O ambiente físico não influencia significativamente o desempenho cognitivo no TDAH', 'is_correct' => false],
                                    ],
                                    'explanation' => 'Sons de fundo moderados (barulho branco, ruído de café, música instrumental) podem ajudar a "ocupar" a parte do cérebro TDAH que busca estimulação, liberando capacidade para a tarefa principal. Cada pessoa responde diferente — é essencial testar.',
                                ],
                            ],
                            [
                                'type'  => 'question',
                                'title' => 'Redesenhando Seu Espaço',
                                'data'  => [
                                    'question'    => 'Descreva seu ambiente atual de trabalho ou estudo. Quais elementos te distraem? O que você pode mudar — remover, adicionar ou reorganizar — para criar um espaço mais favorável ao seu foco?',
                                    'placeholder' => 'Pense em: iluminação, organização da mesa, notificações do celular, pessoas ao redor, temperatura, objetos dispersivos.',
                                ],
                            ],
                        ],
                    ],
                    [
                        'title'        => 'Gerenciando o Tempo com Time Blindness',
                        'description'  => 'A "cegueira temporal" é uma das características mais impactantes do TDAH — aprenda a trabalhar com ela.',
                        'unlock_type'  => 'sequencial',
                        'items' => [
                            [
                                'type'  => 'video',
                                'title' => 'Time Blindness — Por Que o Tempo Foge para o Cérebro TDAH',
                                'data'  => ['url' => ''],
                            ],
                            [
                                'type'  => 'quiz',
                                'title' => 'Estratégias de Gerenciamento de Tempo',
                                'data'  => [
                                    'question' => 'O que é "time blindness" (cegueira temporal) no contexto do TDAH?',
                                    'options'  => [
                                        ['text' => 'Dificuldade em enxergar relógios ou calendários', 'is_correct' => false],
                                        ['text' => 'Incapacidade de perceber a passagem do tempo e planejar em função de eventos futuros', 'is_correct' => true],
                                        ['text' => 'Tendência a sempre chegar cedo aos compromissos por ansiedade', 'is_correct' => false],
                                        ['text' => 'Um distúrbio visual associado ao TDAH', 'is_correct' => false],
                                    ],
                                    'explanation' => 'Russell Barkley descreve o TDAH como um "distúrbio da perspectiva de tempo". Pessoas com TDAH vivem em dois tempos: "agora" e "não agora". O futuro não existe da mesma forma — daí a dificuldade de iniciar tarefas sem urgência imediata ou consequência percebida.',
                                ],
                            ],
                            [
                                'type'  => 'question',
                                'title' => 'Meu Sistema de Gestão do Tempo',
                                'data'  => [
                                    'question'    => 'Crie seu plano pessoal de gestão de tempo para os próximos 7 dias. Inclua: ferramentas que vai usar (alarmes, apps, calendário físico), como vai lidar com transições entre tarefas e uma estratégia para não perder compromissos importantes.',
                                    'placeholder' => 'O melhor sistema é o que você realmente usa, não o mais sofisticado. Seja honesto sobre o que é sustentável para você.',
                                ],
                            ],
                        ],
                    ],
                ],
            ],

            // ─── 3. Regulação Emocional ───────────────────────────────────────
            [
                'name'        => 'Regulação Emocional e Autoconhecimento',
                'description' => 'Desenvolva ferramentas para compreender e regular suas emoções, construir relacionamentos mais saudáveis e encontrar maior equilíbrio interno.',
                'steps' => [
                    [
                        'title'        => 'A Neurociência das Emoções no TDAH',
                        'description'  => 'Por que as emoções são tão intensas no TDAH e o que acontece no cérebro durante os picos emocionais.',
                        'unlock_type'  => 'livre',
                        'items' => [
                            [
                                'type'  => 'video',
                                'title' => 'Amígdala, Córtex Pré-Frontal e Tempestades Emocionais no TDAH',
                                'data'  => ['url' => ''],
                            ],
                            [
                                'type'  => 'quiz',
                                'title' => 'Emoções e TDAH — Fundamentos Neurológicos',
                                'data'  => [
                                    'question' => 'Por que a desregulação emocional é tão comum no TDAH?',
                                    'options'  => [
                                        ['text' => 'Porque pessoas com TDAH têm personalidade naturalmente mais sensível', 'is_correct' => false],
                                        ['text' => 'Porque o córtex pré-frontal, responsável por regular a resposta emocional, funciona diferente no TDAH', 'is_correct' => true],
                                        ['text' => 'Porque o TDAH causa transtornos de humor secundários em todos os casos', 'is_correct' => false],
                                        ['text' => 'Porque a desregulação emocional não está diretamente relacionada ao TDAH', 'is_correct' => false],
                                    ],
                                    'explanation' => 'O córtex pré-frontal regula a resposta da amígdala (centro do processamento emocional). No TDAH, essa regulação é menos eficiente, resultando em emoções que chegam com mais intensidade e demoram mais para se dissipar — não por falta de vontade, mas por diferença neurológica.',
                                ],
                            ],
                            [
                                'type'  => 'question',
                                'title' => 'Seus Padrões Emocionais',
                                'data'  => [
                                    'question'    => 'Quais emoções você tem mais dificuldade de regular? Em que situações elas surgem com mais intensidade? Você percebe algum padrão recorrente de gatilho?',
                                    'placeholder' => 'Ex: Raiva intensa quando interrompido, ansiedade desproporcional antes de compromissos, tristeza profunda após críticas...',
                                ],
                            ],
                        ],
                    ],
                    [
                        'title'        => 'Ferramentas de Autorregulação',
                        'description'  => 'Técnicas práticas e baseadas em evidências para regular as emoções no momento em que surgem.',
                        'unlock_type'  => 'sequencial',
                        'items' => [
                            [
                                'type'  => 'video',
                                'title' => 'STOP, Respiração 4-7-8 e Outras Técnicas de Regulação Imediata',
                                'data'  => ['url' => ''],
                            ],
                            [
                                'type'  => 'quiz',
                                'title' => 'Técnicas de Regulação — Como Funcionam',
                                'data'  => [
                                    'question' => 'A técnica de respiração diafragmática ajuda a regular emoções intensas porque:',
                                    'options'  => [
                                        ['text' => 'Distrai a mente do problema emocional', 'is_correct' => false],
                                        ['text' => 'Ativa o sistema nervoso parassimpático, reduzindo a resposta de luta-ou-fuga', 'is_correct' => true],
                                        ['text' => 'Aumenta a frequência cardíaca para "queimar" a emoção acumulada', 'is_correct' => false],
                                        ['text' => 'Suprime os pensamentos negativos através da concentração forçada', 'is_correct' => false],
                                    ],
                                    'explanation' => 'Respirações lentas e profundas ativam o nervo vago e o sistema parassimpático ("rest and digest"), contrabalançando a resposta simpática de estresse. Isso muda literalmente a química cerebral em segundos — é uma das poucas técnicas com efeito fisiológico imediato comprovado.',
                                ],
                            ],
                            [
                                'type'  => 'question',
                                'title' => 'Sua Caixa de Ferramentas Emocional',
                                'data'  => [
                                    'question'    => 'Escolha 2 técnicas de regulação emocional que você vai praticar esta semana. Para cada uma, descreva: quando vai usar, como vai se lembrar de usá-la no momento certo, e como vai registrar se funcionou.',
                                    'placeholder' => 'Quanto mais específico e realista for seu plano, maior a chance de funcionar em uma situação real de crise.',
                                ],
                            ],
                        ],
                    ],
                    [
                        'title'        => 'TDAH e Relacionamentos',
                        'description'  => 'Como o TDAH impacta suas relações e estratégias para construir conexões mais saudáveis e honestas.',
                        'unlock_type'  => 'sequencial',
                        'items' => [
                            [
                                'type'  => 'video',
                                'title' => 'TDAH nos Relacionamentos — Padrões Comuns e Como Quebrá-los',
                                'data'  => ['url' => ''],
                            ],
                            [
                                'type'  => 'quiz',
                                'title' => 'Relacionamentos e TDAH',
                                'data'  => [
                                    'question' => 'Qual padrão de comportamento no TDAH frequentemente impacta negativamente os relacionamentos sem intenção consciente?',
                                    'options'  => [
                                        ['text' => 'Excesso de paciência e espera nas conversas', 'is_correct' => false],
                                        ['text' => 'Interrupção de falas, esquecimento de compromissos e hiperfoco seletivo em interesses próprios', 'is_correct' => true],
                                        ['text' => 'Comunicação excessivamente formal e distante', 'is_correct' => false],
                                        ['text' => 'Dificuldade exclusiva em expressar emoções positivas', 'is_correct' => false],
                                    ],
                                    'explanation' => 'A impulsividade (interrupções, respostas rápidas), o esquecimento (datas, conversas, compromissos) e o hiperfoco seletivo podem ser interpretados como desrespeito ou desinteresse pelos outros — mesmo quando não é a intenção. A comunicação aberta sobre o TDAH pode transformar essa dinâmica.',
                                ],
                            ],
                            [
                                'type'  => 'question',
                                'title' => 'Comunicando Suas Necessidades',
                                'data'  => [
                                    'question'    => 'O que você gostaria que as pessoas próximas (família, parceiro(a), amigos) soubessem sobre o seu TDAH e suas necessidades específicas? Escreva como você explicaria isso para alguém importante na sua vida.',
                                    'placeholder' => 'Ex: "Quando esqueço algo, não é falta de cuidado. Quando interrompo, não é falta de respeito. O que me ajuda é..."',
                                ],
                            ],
                        ],
                    ],
                    [
                        'title'        => 'Construindo Resiliência Emocional',
                        'description'  => 'Transformar o conhecimento em crescimento real — desenvolvendo resiliência como prática contínua, não como destino.',
                        'unlock_type'  => 'sequencial',
                        'items' => [
                            [
                                'type'  => 'video',
                                'title' => 'Do Caos Interno à Estabilidade — Resiliência e Autocompaixão no TDAH',
                                'data'  => ['url' => ''],
                            ],
                            [
                                'type'  => 'quiz',
                                'title' => 'Resiliência e Autocompaixão',
                                'data'  => [
                                    'question' => 'A autocompaixão — tratar a si mesmo com gentileza diante de falhas — no contexto do TDAH:',
                                    'options'  => [
                                        ['text' => 'Reduz a motivação e leva à acomodação e desistência', 'is_correct' => false],
                                        ['text' => 'Melhora a regulação emocional e reduz a autocrítica paralisante', 'is_correct' => true],
                                        ['text' => 'É o mesmo que baixar os padrões pessoais', 'is_correct' => false],
                                        ['text' => 'Funciona apenas para pessoas sem histórico de trauma', 'is_correct' => false],
                                    ],
                                    'explanation' => 'Pesquisas de Kristin Neff mostram que autocompaixão está associada a maior motivação, melhor saúde mental e maior resiliência — não ao oposto. A autocrítica severa, muito comum no TDAH, frequentemente paralisa e perpetua ciclos de procrastinação e vergonha.',
                                ],
                            ],
                            [
                                'type'  => 'question',
                                'title' => 'Carta para Si Mesmo',
                                'data'  => [
                                    'question'    => 'Escreva uma breve carta para si mesmo com o que aprendeu nesta jornada. O que você quer lembrar? Que conselho daria para você do passado? Que compromisso faz com você do futuro?',
                                    'placeholder' => 'Seja honesto, gentil e específico. Esta carta é só sua — ninguém mais vai ler.',
                                ],
                            ],
                        ],
                    ],
                ],
            ],

            // ─── 4. Sono e Rotina ─────────────────────────────────────────────
            [
                'name'        => 'Sono, Rotina e Ritmo de Vida',
                'description' => 'Construa uma relação mais saudável com o sono e crie rotinas que respeitem o funcionamento único do seu cérebro — sem se forçar a ser quem você não é.',
                'steps' => [
                    [
                        'title'        => 'Sono e TDAH — A Conexão Oculta',
                        'description'  => 'Por que dormir bem é especialmente difícil no TDAH e qual o impacto real disso nos sintomas diários.',
                        'unlock_type'  => 'livre',
                        'items' => [
                            [
                                'type'  => 'video',
                                'title' => 'TDAH e Sono — Ciclo Vicioso ou Ciclo Virtuoso?',
                                'data'  => ['url' => ''],
                            ],
                            [
                                'type'  => 'quiz',
                                'title' => 'Sono e TDAH — Conexões Essenciais',
                                'data'  => [
                                    'question' => 'Qual é a relação mais bem documentada entre TDAH e qualidade do sono?',
                                    'options'  => [
                                        ['text' => 'Pessoas com TDAH precisam de menos horas de sono que a média da população', 'is_correct' => false],
                                        ['text' => 'Privação de sono piora os sintomas do TDAH, e o TDAH dificulta o sono — criando um ciclo vicioso', 'is_correct' => true],
                                        ['text' => 'O TDAH melhora automaticamente com mais horas de sono, independente da qualidade', 'is_correct' => false],
                                        ['text' => 'O TDAH não tem relação direta com a qualidade ou padrões do sono', 'is_correct' => false],
                                    ],
                                    'explanation' => 'O TDAH frequentemente envolve atraso na fase circadiana (dormir e acordar mais tarde), mente acelerada ao tentar dormir e dificuldade de iniciar o sono. A privação resultante agrava todos os sintomas do TDAH — atenção, humor e impulsividade — criando um ciclo difícil de quebrar sem intervenção intencional.',
                                ],
                            ],
                            [
                                'type'  => 'question',
                                'title' => 'Diagnóstico do Seu Sono',
                                'data'  => [
                                    'question'    => 'Descreva sua rotina de sono atual: horário que costuma ir dormir, horário que acorda, o que acontece na sua mente quando tenta dormir, quantas horas dorme em média e como se sente ao acordar.',
                                    'placeholder' => 'Seja honesto sobre seus hábitos reais — não sobre o que "deveria" fazer. Este é o ponto de partida.',
                                ],
                            ],
                        ],
                    ],
                    [
                        'title'        => 'Higiene do Sono para o Cérebro TDAH',
                        'description'  => 'Práticas específicas e adaptadas para melhorar a qualidade do sono de quem tem TDAH.',
                        'unlock_type'  => 'sequencial',
                        'items' => [
                            [
                                'type'  => 'video',
                                'title' => 'Higiene do Sono — Estratégias para Desligar o Cérebro TDAH',
                                'data'  => ['url' => ''],
                            ],
                            [
                                'type'  => 'quiz',
                                'title' => 'Hábitos de Higiene do Sono',
                                'data'  => [
                                    'question' => 'Qual prática de higiene do sono é especialmente importante para pessoas com TDAH?',
                                    'options'  => [
                                        ['text' => 'Usar o celular até adormecer para distrair a mente dos pensamentos', 'is_correct' => false],
                                        ['text' => 'Manter horários regulares de dormir e acordar mesmo nos finais de semana', 'is_correct' => true],
                                        ['text' => 'Fazer exercícios intensos logo antes de dormir para eliminar o excesso de energia', 'is_correct' => false],
                                        ['text' => 'Cochilar longamente durante o dia para compensar o sono ruim à noite', 'is_correct' => false],
                                    ],
                                    'explanation' => 'A regularidade dos horários é fundamental para sincronizar o ritmo circadiano — frequentemente desajustado no TDAH. A luz azul de telas inibe a melatonina, o exercício intenso eleva o cortisol, e cochilos longos fragmentam a pressão do sono — todos prejudicando o início e a qualidade do sono noturno.',
                                ],
                            ],
                            [
                                'type'  => 'question',
                                'title' => 'Meu Plano de Higiene do Sono',
                                'data'  => [
                                    'question'    => 'Dos hábitos de higiene do sono discutidos, escolha 3 que você vai implementar esta semana. Para cada um, defina especificamente como vai fazer isso na prática (horário, lugar, o que vai mudar de concreto).',
                                    'placeholder' => 'Dica: comece com mudanças pequenas e sustentáveis. Transformar tudo de uma vez raramente funciona para o cérebro TDAH.',
                                ],
                            ],
                        ],
                    ],
                    [
                        'title'        => 'Construindo uma Rotina Matinal',
                        'description'  => 'As manhãs ditam o tom do dia inteiro — aprenda a criar uma rotina matinal que respeite o TDAH em vez de lutar contra ele.',
                        'unlock_type'  => 'sequencial',
                        'items' => [
                            [
                                'type'  => 'video',
                                'title' => 'Rotina Matinal para Cérebros com TDAH — Começando sem Caos',
                                'data'  => ['url' => ''],
                            ],
                            [
                                'type'  => 'quiz',
                                'title' => 'Rotina Matinal Eficaz',
                                'data'  => [
                                    'question' => 'Por que "preparar as coisas na noite anterior" é uma estratégia especialmente eficaz para pessoas com TDAH?',
                                    'options'  => [
                                        ['text' => 'Porque a memória funciona melhor durante as horas noturnas', 'is_correct' => false],
                                        ['text' => 'Porque reduz o número de decisões e buscas pela manhã, quando o cérebro ainda está aquecendo', 'is_correct' => true],
                                        ['text' => 'Porque economiza tempo total no dia de forma significativa', 'is_correct' => false],
                                        ['text' => 'Porque evita o esquecimento de itens durante o restante do dia', 'is_correct' => false],
                                    ],
                                    'explanation' => 'O cérebro TDAH tem "inicialização" mais lenta pela manhã. Reduzir decisões (o que vestir, onde estão as chaves, o que levar) diminui a fadiga de decisão inicial e elimina os atritamentos que fazem as manhãs virarem caos — permitindo que a mente funcione com menos obstáculos desde cedo.',
                                ],
                            ],
                            [
                                'type'  => 'question',
                                'title' => 'Minha Rotina Matinal Ideal',
                                'data'  => [
                                    'question'    => 'Monte sua rotina matinal ideal para os próximos 7 dias. Seja completamente realista — considere sua hora real de acordar, quanto tempo tem disponível e o que é genuinamente necessário. O que você vai preparar na noite anterior?',
                                    'placeholder' => 'Ex: 06:30 acordar — 06:35 não checar celular — 06:40 água e janela aberta — 06:45 exercício leve 10min — 07:00 café...',
                                ],
                            ],
                        ],
                    ],
                    [
                        'title'        => 'Ritual Noturno e Desligamento Digital',
                        'description'  => 'Criar uma transição intencional entre o dia agitado e o sono — um dos hábitos mais transformadores para o cérebro TDAH.',
                        'unlock_type'  => 'sequencial',
                        'items' => [
                            [
                                'type'  => 'video',
                                'title' => 'Ritual Noturno — Criando a Transição Intencional para o Sono',
                                'data'  => ['url' => ''],
                            ],
                            [
                                'type'  => 'quiz',
                                'title' => 'Desligamento Noturno e Brain Dump',
                                'data'  => [
                                    'question' => 'O que é a técnica de "brain dump" noturno e como ela ajuda especificamente no TDAH?',
                                    'options'  => [
                                        ['text' => 'Uma técnica de meditação guiada que elimina preocupações gradualmente', 'is_correct' => false],
                                        ['text' => 'Escrever preocupações e pendências antes de dormir para "descarregar" a memória de trabalho e reduzir a ruminação', 'is_correct' => true],
                                        ['text' => 'Um método de planejamento antecipado de ansiedades futuras previsíveis', 'is_correct' => false],
                                        ['text' => 'Uma técnica exclusiva de terapia cognitivo-comportamental para transtornos de ansiedade', 'is_correct' => false],
                                    ],
                                    'explanation' => 'O brain dump noturno "descarrega" a memória de trabalho, que no TDAH costuma ficar processando e planejando quando deveria descansar. Ter um lugar físico para "depositar" pensamentos, pendências e preocupações permite que o cérebro solte o controle e entre no sono com menos resistência.',
                                ],
                            ],
                            [
                                'type'  => 'question',
                                'title' => 'Meu Ritual de Desligamento',
                                'data'  => [
                                    'question'    => 'Crie seu ritual de desligamento noturno personalizado. Defina: horário de início, atividades de transição (leitura, journaling, alongamento), o que vai evitar (telas, notícias, trabalho) e o horário alvo para estar na cama.',
                                    'placeholder' => 'Um ritual consistente de 20–30 minutos pode transformar completamente a qualidade do seu sono em poucas semanas.',
                                ],
                            ],
                        ],
                    ],
                ],
            ],

        ];

        $pos = $startPos;
        foreach ($journeys as $jData) {
            $journey = Journey::create([
                'tenant_id'   => $tenantId,
                'name'        => $jData['name'],
                'slug'        => Str::slug($jData['name']),
                'description' => $jData['description'],
                'is_active'   => true,
                'product_ids' => [],
                'position'    => $pos++,
            ]);

            $stepPos = 0;
            foreach ($jData['steps'] as $sData) {
                $step = JourneyStep::create([
                    'journey_id'  => $journey->id,
                    'title'       => $sData['title'],
                    'description' => $sData['description'],
                    'unlock_type' => $sData['unlock_type'],
                    'position'    => $stepPos++,
                ]);

                $itemPos = 0;
                foreach ($sData['items'] as $iData) {
                    JourneyStepItem::create([
                        'journey_step_id' => $step->id,
                        'type'            => $iData['type'],
                        'title'           => $iData['title'],
                        'data'            => $iData['data'],
                        'position'        => $itemPos++,
                    ]);
                }
            }
        }
    }
}
